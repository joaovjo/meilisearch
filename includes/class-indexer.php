<?php

declare(strict_types=1);

/**
 * Indexador Meilisearch
 *
 * @package Meilisearch
 */

use React\EventLoop\Loop;

/**
 * Classe Meilisearch_Indexer
 *
 * Gerencia a indexação de conteúdo do WordPress no Meilisearch usando Fiber para concorrência.
 */
class Meilisearch_Indexer
{
	/**
	 * Instância do cliente Meilisearch.
	 *
	 * @var Meilisearch_Client
	 */
	private Meilisearch_Client $client;

	/**
	 * Construtor.
	 *
	 * @param Meilisearch_Client $client Instância do cliente Meilisearch.
	 */
	public function __construct(Meilisearch_Client $client)
	{
		$this->client = $client;
	}

	/**
	 * Inicializar hooks do WordPress.
	 */
	public function init_hooks(): void
	{
		// Indexar ao salvar post.
		add_action('save_post', [$this, 'index_post'], 10, 2);

		// Remover do índice ao excluir post.
		add_action('delete_post', [$this, 'delete_post'], 10, 2);

		// Criar índice quando novo site é criado.
		add_action('wpmu_new_blog', [$this, 'create_site_index'], 10, 1);

		// Excluir índice quando site é excluído.
		add_action('wp_delete_site', [$this, 'delete_site_index'], 10, 1);
	}

	/**
	 * Indexar um único post.
	 *
	 * @param int     $post_id ID do post.
	 * @param WP_Post $post    Objeto do post.
	 */
	public function index_post(int $post_id, WP_Post $post): void
	{
		// Pular autoguardados e revisões.
		if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
			return;
		}

		// Verificar se o status do post deve ser indexado.
		if (!$this->should_index_post_status($post->post_status)) {
			return;
		}

		// Verificar se o tipo de post deve ser indexado.
		if (!$this->should_index_post_type($post->post_type)) {
			return;
		}

		$document = $this->prepare_document($post);
		$blog_id = get_current_blog_id();
		$index_name = $this->client->get_index_name($blog_id);

		try {
			// Garantir que o índice existe antes de indexar
			$this->ensure_index_exists($blog_id);

			$this->client
				->get_client()
				->index($index_name)
				->addDocuments([$document], 'id');
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
				error_log("Meilisearch index error for post {$post_id}: " . $e->getMessage());
			}
		}
	}

	/**
	 * Excluir um post do índice.
	 *
	 * @param int     $post_id ID do post.
	 * @param WP_Post $post    Objeto do post.
	 */
	public function delete_post(int $post_id, WP_Post $post): void
	{
		$blog_id = get_current_blog_id();
		$index_name = $this->client->get_index_name($blog_id);

		try {
			$this->client
				->get_client()
				->index($index_name)
				->deleteDocument($post_id);
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
				error_log("Meilisearch delete error for post {$post_id}: " . $e->getMessage());
			}
		}
	}

	/**
	 * Indexar em massa todos os posts da rede usando Fiber.
	 *
	 * @param callable|null $progress_callback Callback opcional para atualizações de progresso.
	 * @return array Resultados com contagens.
	 */
	public function bulk_index_network(null|callable $progress_callback = null): array
	{
		$sites = get_sites(['number' => 9999]);
		$results = [
			'total_sites' => count($sites),
			'total_posts' => 0,
			'indexed_posts' => 0,
			'errors' => [],
		];

		foreach ($sites as $site) {
			$fiber = new Fiber(function () use ($site, &$results, $progress_callback): void {
				$blog_id = (int) $site->blog_id;
				switch_to_blog($blog_id);

				$site_result = $this->index_site_posts($blog_id);
				$results['total_posts'] += $site_result['total'];
				$results['indexed_posts'] += $site_result['indexed'];

				if (isset($site_result['errors']) && is_array($site_result['errors']) && count($site_result['errors']) > 0) {
					$results['errors'][$blog_id] = $site_result['errors'];
				}

				if ($progress_callback) {
					$progress_callback($blog_id, $site_result);
				}

				restore_current_blog();
			});

			$fiber->start();
		}

		return $results;
	}

	/**
	 * Indexar todos os posts de um site específico.
	 *
	 * @param int $blog_id ID do site.
	 * @return array Resultados com contagens.
	 */
	public function index_site_posts(int $blog_id): array
	{
		$index_name = $this->client->get_index_name($blog_id);
		$results = [
			'total' => 0,
			'indexed' => 0,
			'errors' => [],
		];

		// Garantir que o índice existe antes de indexar
		if (!$this->ensure_index_exists($blog_id)) {
			$results['errors'][] = "Failed to create or access index for blog {$blog_id}";
			return $results;
		}

		// Garantir que estamos no contexto do blog correto.
		$current_blog_id = get_current_blog_id();
		if ($current_blog_id !== $blog_id) {
			switch_to_blog($blog_id);
		}

		// Obter tipos de post configurados para indexação.
		$post_types = $this->get_indexable_post_types();
		$post_statuses = $this->get_indexable_post_statuses();

		// Obter todos os posts com os status configurados.
		$args = [
			'post_type' => $post_types,
			'post_status' => $post_statuses,
			'posts_per_page' => -1,
			'orderby' => 'ID',
			'order' => 'ASC',
		];

		$posts = get_posts($args);
		$results['total'] = count($posts);

		// Preparar documentos em lotes.
		$batch_size = $this->get_batch_size();
		$documents = [];

		foreach ($posts as $post) {
			$documents[] = $this->prepare_document($post);

			// Indexar em lotes.
			if (count($documents) >= $batch_size) {
				try {
					$this->client
						->get_client()
						->index($index_name)
						->addDocuments($documents, 'id');
					$results['indexed'] += count($documents);
					$documents = [];
				} catch (Exception $e) {
					$results['errors'][] = $e->getMessage();
				}
			}
		}

		// Indexar documentos restantes.
		if (is_array($documents) && count($documents) > 0) {
			try {
				$this->client
					->get_client()
					->index($index_name)
					->addDocuments($documents, 'id');
				$results['indexed'] += count($documents);
			} catch (Exception $e) {
				$results['errors'][] = $e->getMessage();
			}
		}

		// Restaurar contexto do blog se necessário.
		if ($current_blog_id !== $blog_id) {
			restore_current_blog();
		}

		return $results;
	}

	/**
	 * Preparar um documento de post para indexação.
	 *
	 * @param WP_Post $post Objeto do post.
	 * @return array Dados do documento.
	 */
	private function prepare_document(WP_Post $post): array
	{
		$author = get_userdata((int) $post->post_author);

		// Para attachments, usar a URL direta do arquivo ao invés da página de attachment
		$permalink = 'attachment' === $post->post_type 
			? wp_get_attachment_url($post->ID) 
			: get_permalink($post->ID);

		// Se não conseguir obter o permalink, usar o GUID como fallback
		if (!$permalink || false === $permalink) {
			$permalink = $post->guid;
		}

		return [
			'id' => $post->ID,
			'blog_id' => get_current_blog_id(),
			'title' => $post->post_title,
			'content' => wp_strip_all_tags($post->post_content),
			'excerpt' => '' !== $post->post_excerpt ? $post->post_excerpt : wp_trim_words($post->post_content, 55),
			'post_type' => $post->post_type,
			'post_status' => $post->post_status,
			'date' => strtotime($post->post_date),
			'modified' => strtotime($post->post_modified),
			'author' => $author ? $author->display_name : '',
			'author_id' => $post->post_author,
			'categories' => $this->get_post_terms($post->ID, 'category'),
			'tags' => $this->get_post_terms($post->ID, 'post_tag'),
			'permalink' => $permalink,
		];
	}

	/**
	 * Obter termos do post como array de nomes.
	 *
	 * @param int    $post_id  ID do post.
	 * @param string $taxonomy Nome da taxonomia.
	 * @return array Nomes dos termos.
	 */
	private function get_post_terms(int $post_id, string $taxonomy): array
	{
		$terms = get_the_terms($post_id, $taxonomy);

		if (!$terms || is_wp_error($terms)) {
			return [];
		}

		return wp_list_pluck($terms, 'name');
	}

	/**
	 * Garantir que um índice existe para um blog.
	 * Cria o índice se ele não existir.
	 *
	 * @param int $blog_id ID do blog.
	 * @return bool True se o índice existe ou foi criado com sucesso.
	 */
	private function ensure_index_exists(int $blog_id): bool
	{
		$index_name = $this->client->get_index_name($blog_id);

		try {
			// Tentar obter estatísticas do índice para verificar se existe
			$this->client
				->get_client()
				->index($index_name)
				->stats();
			
			// Índice existe, garantir que as configurações estejam atualizadas
			$this->update_index_settings($blog_id);
			return true;
		} catch (Exception $e) {
			// Índice não existe, tentar criá-lo
			try {
				$this->client->create_index($blog_id);
				if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
					error_log("Meilisearch: Created missing index for blog {$blog_id}: {$index_name}");
				}
				
				// Aplicar configurações ao novo índice
				$this->update_index_settings($blog_id);
				return true;
			} catch (Exception $create_error) {
				if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
					error_log("Meilisearch: Failed to create index for blog {$blog_id}: " . $create_error->getMessage());
				}
				return false;
			}
		}
	}

	/**
	 * Criar índice para um novo site.
	 *
	 * @param int $blog_id ID do novo site.
	 */
	public function create_site_index(int $blog_id): void
	{
		$this->client->create_index($blog_id);
	}

	/**
	 * Excluir índice de um site excluído.
	 *
	 * @param WP_Site $site Objeto do site.
	 */
	public function delete_site_index(WP_Site $site): void
	{
		$this->client->delete_index((int) $site->blog_id);
	}

	/**
	 * Obter lista de tipos de post que devem ser indexados.
	 *
	 * @return array Lista de nomes de tipos de post.
	 */
	private function get_indexable_post_types(): array
	{
		$settings = get_site_option('meilisearch_settings', []);
		$post_types = isset($settings['post_types']) && is_array($settings['post_types']) 
			? $settings['post_types'] 
			: ['post', 'page'];

		// Garantir que temos pelo menos um tipo de post.
		if (empty($post_types)) {
			$post_types = ['post', 'page'];
		}

		return $post_types;
	}

	/**
	 * Obter lista de status de post que devem ser indexados.
	 *
	 * @return array Lista de status de post.
	 */
	private function get_indexable_post_statuses(): array
	{
		$settings = get_site_option('meilisearch_settings', []);
		$post_statuses = isset($settings['post_statuses']) && is_array($settings['post_statuses']) 
			? $settings['post_statuses'] 
			: ['publish', 'inherit'];

		// Garantir que temos pelo menos um status.
		if (empty($post_statuses)) {
			$post_statuses = ['publish', 'inherit'];
		}

		return $post_statuses;
	}

	/**
	 * Obter tamanho do lote para indexação em massa.
	 *
	 * @return int Tamanho do lote.
	 */
	private function get_batch_size(): int
	{
		$settings = get_site_option('meilisearch_settings', []);
		$batch_size = isset($settings['batch_size']) ? (int) $settings['batch_size'] : 100;

		// Garantir que o batch_size está entre 1 e 1000.
		return max(1, min(1000, $batch_size));
	}

	/**
	 * Verificar se um tipo de post deve ser indexado.
	 *
	 * @param string $post_type Nome do tipo de post.
	 * @return bool True se deve ser indexado.
	 */
	private function should_index_post_type(string $post_type): bool
	{
		$indexable_types = $this->get_indexable_post_types();
		return in_array($post_type, $indexable_types, true);
	}

	/**
	 * Verificar se um status de post deve ser indexado.
	 *
	 * @param string $post_status Status do post.
	 * @return bool True se deve ser indexado.
	 */
	private function should_index_post_status(string $post_status): bool
	{
		$indexable_statuses = $this->get_indexable_post_statuses();
		return in_array($post_status, $indexable_statuses, true);
	}

	/**
	 * Atualizar configurações do índice (atributos filtráveis e ordenáveis).
	 *
	 * @param int $blog_id ID do blog.
	 * @return bool True se as configurações foram atualizadas com sucesso.
	 */
	private function update_index_settings(int $blog_id): bool
	{
		$index_name = $this->client->get_index_name($blog_id);

		// Verificar se a classe de configurações de pesquisa está disponível
		if (!class_exists('Meilisearch_Search_Settings')) {
			return false;
		}

		try {
			// Obter atributos configurados
			$sortable_attributes = Meilisearch_Search_Settings::get_sortable_attributes();
			$filterable_attributes = Meilisearch_Search_Settings::get_filterable_attributes();

			// Atualizar configurações do índice
			$this->client
				->get_client()
				->index($index_name)
				->updateSettings([
					'sortableAttributes' => $sortable_attributes,
					'filterableAttributes' => $filterable_attributes,
				]);

			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
				error_log(sprintf(
					'Meilisearch: Updated settings for index %s - Sortable: %s, Filterable: %s',
					$index_name,
					implode(', ', $sortable_attributes),
					implode(', ', $filterable_attributes)
				));
			}

			return true;
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
				error_log("Meilisearch: Failed to update settings for index {$index_name}: " . $e->getMessage());
			}
			return false;
		}
	}
}
