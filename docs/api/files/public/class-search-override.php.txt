<?php

declare(strict_types=1);

/**
 * Substituição de Busca Meilisearch
 *
 * @package Meilisearch
 */

/**
 * Classe Meilisearch_Search_Override
 *
 * Substitui a busca padrão do WordPress pelo Meilisearch.
 */
class Meilisearch_Search_Override
{
	/**
	 * Instância do buscador Meilisearch.
	 *
	 * @var Meilisearch_Searcher
	 */
	private Meilisearch_Searcher $searcher;

	/**
	 * Cache para resultados do Meilisearch.
	 *
	 * @var array|null
	 */
	private null|array $cached_results = null;

	/**
	 * Mapa de permalinks de posts do Meilisearch (blog_id_postid => permalink).
	 *
	 * @var array
	 */
	private array $permalink_map = [];

	/**
	 * Construtor.
	 *
	 * @param Meilisearch_Searcher $searcher Instância do buscador Meilisearch.
	 */
	public function __construct(Meilisearch_Searcher $searcher)
	{
		$this->searcher = $searcher;
	}

	/**
	 * Inicializar hooks do WordPress.
	 */
	public function init_hooks(): void
	{
		add_action('pre_get_posts', [$this, 'override_search_query'], 10);
		add_filter('posts_pre_query', [$this, 'get_posts_from_meilisearch'], 10, 2);
		add_filter('post_link', [$this, 'fix_cross_site_permalink'], 10, 2);
		add_filter('page_link', [$this, 'fix_cross_site_permalink'], 10, 2);
		add_filter('post_type_link', [$this, 'fix_cross_site_permalink'], 10, 2);
	}

	/**
	 * Substituir consulta de busca do WordPress com resultados do Meilisearch.
	 *
	 * @param WP_Query $query A instância WP_Query.
	 */
	public function override_search_query(WP_Query $query): void
	{
		// Substituir apenas consultas de busca principais no frontend.
		if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
			return;
		}

		$search_term = $query->get('s');

		if (null === $search_term || '' === $search_term) {
			return;
		}

		// Marcar esta consulta como alimentada por Meilisearch.
		$query->set('meilisearch_query', true);

		// Obter parâmetros de paginação.
		$paged = max(1, $query->get('paged'));
		$posts_per_page = $query->get('posts_per_page');

		if ($posts_per_page < 1) {
			$posts_per_page = get_option('posts_per_page', 10);
		}

		$offset = ($paged - 1) * $posts_per_page;

		// Executar busca Meilisearch.
		$results = $this->searcher->search_network($search_term, [
			'limit' => $posts_per_page,
			'offset' => $offset,
		]);

		// Armazenar resultados em cache para uso no filtro posts_pre_query.
		$this->cached_results = $results;

		// Definir total de posts encontrados para paginação.
		add_filter('found_posts', fn(): int => $results['total'], 10, 2);
	}

	/**
	 * Obter posts dos resultados do Meilisearch (compatível com múltiplos sites).
	 *
	 * @param array|null $posts  Array de dados de post ou null.
	 * @param WP_Query   $query  A instância WP_Query.
	 * @return array|null Array de objetos WP_Post ou null.
	 */
	public function get_posts_from_meilisearch(array|null $posts, WP_Query $query): array|null
	{
		// Processar apenas consultas Meilisearch.
		if (!$query->get('meilisearch_query') || null === $this->cached_results) {
			return $posts;
		}

		$results = $this->cached_results;
		$post_objects = [];
		$current_blog_id = get_current_blog_id();

		// Agrupar resultados por blog_id e construir mapa de permalink.
		$posts_by_blog = [];
		foreach ($results['hits'] as $hit) {
			$blog_id = $hit['blog_id'] ?? 0;
			$post_id = $hit['id'] ?? 0;
			$permalink = $hit['permalink'] ?? '';

			if ($blog_id && $post_id) {
				if (!isset($posts_by_blog[$blog_id])) {
					$posts_by_blog[$blog_id] = [];
				}
				$posts_by_blog[$blog_id][] = $post_id;

				// Armazenar permalink para uso posterior.
				if ($permalink) {
					$this->permalink_map[$blog_id . '_' . $post_id] = $permalink;
				}
			}
		}

		// Buscar posts de cada blog.
		$fetched_posts = [];
		foreach ($posts_by_blog as $blog_id => $post_ids) {
			// Verificar se o blog existe na rede atual.
			$blog_exists = get_blog_details($blog_id, false);
			
			if ($blog_id !== $current_blog_id && $blog_exists) {
				switch_to_blog($blog_id);
			}

			foreach ($post_ids as $post_id) {
				$post = null;
				
				// Tentar obter post apenas se o blog existe na rede atual.
				if ($blog_exists) {
					$post = get_post($post_id);
				}
				
				// Se post não foi encontrado (rede externa), criar um pseudo-post a partir dos dados do Meilisearch.
				if (!$post) {
					// Encontrar os dados do hit para este post.
					foreach ($results['hits'] as $hit) {
						if (($hit['blog_id'] ?? 0) === $blog_id && ($hit['id'] ?? 0) === $post_id) {
							$post = $this->create_pseudo_post_from_hit($hit);
							break;
						}
					}
				}
				
				if ($post) {
					$key = $blog_id . '_' . $post_id;

					// Adicionar blog_id ao objeto post para referência.
					$post->meilisearch_blog_id = $blog_id;
					$post->meilisearch_external = !$blog_exists;

					// Adicionar permalink do Meilisearch se disponível.
					if (isset($this->permalink_map[$key])) {
						$post->meilisearch_permalink = $this->permalink_map[$key];
					}

					$fetched_posts[$key] = $post;
				}
			}

			if ($blog_id !== $current_blog_id && $blog_exists) {
				restore_current_blog();
			}
		}

		// Reconstruir array de posts na ordem do Meilisearch.
		foreach ($results['hits'] as $hit) {
			$blog_id = $hit['blog_id'] ?? 0;
			$post_id = $hit['id'] ?? 0;
			$key = $blog_id . '_' . $post_id;

			if (isset($fetched_posts[$key])) {
				$post_objects[] = $fetched_posts[$key];
			}
		}

		// Limpar cache após retornar resultados.
		// Manter permalink_map para uso posterior pelos filtros de permalink.
		$this->cached_results = null;

		return $post_objects;
	}

	/**
	 * Criar um objeto pseudo WP_Post a partir de dados do hit do Meilisearch.
	 *
	 * Usado para posts de redes externas que não existem no banco de dados atual.
	 *
	 * @param array $hit Dados do hit do Meilisearch.
	 * @return WP_Post|null Objeto pseudo post ou null.
	 */
	private function create_pseudo_post_from_hit(array $hit): ?WP_Post
	{
		if (!isset($hit['id']) || !isset($hit['title'])) {
			return null;
		}

		// Criar um stdClass que imita a estrutura de WP_Post.
		$post_data = [
			'ID'                    => $hit['id'],
			'post_author'           => $hit['author_id'] ?? 0,
			'post_date'             => isset($hit['date']) ? date('Y-m-d H:i:s', $hit['date']) : '',
			'post_date_gmt'         => isset($hit['date']) ? gmdate('Y-m-d H:i:s', $hit['date']) : '',
			'post_content'          => $hit['content'] ?? '',
			'post_title'            => $hit['title'] ?? '',
			'post_excerpt'          => $hit['excerpt'] ?? '',
			'post_status'           => $hit['post_status'] ?? 'publish',
			'comment_status'        => 'closed',
			'ping_status'           => 'closed',
			'post_password'         => '',
			'post_name'             => sanitize_title($hit['title'] ?? ''),
			'to_ping'               => '',
			'pinged'                => '',
			'post_modified'         => isset($hit['modified']) ? date('Y-m-d H:i:s', $hit['modified']) : '',
			'post_modified_gmt'     => isset($hit['modified']) ? gmdate('Y-m-d H:i:s', $hit['modified']) : '',
			'post_content_filtered' => '',
			'post_parent'           => 0,
			'guid'                  => $hit['permalink'] ?? '',
			'menu_order'            => 0,
			'post_type'             => $hit['post_type'] ?? 'post',
			'post_mime_type'        => '',
			'comment_count'         => 0,
			'filter'                => 'raw',
		];

		return new WP_Post((object) $post_data);
	}

	/**
	 * Corrigir permalink entre sites.
	 *
	 * @param string  $permalink O permalink do post.
	 * @param WP_Post $post      Objeto do post.
	 * @return string Permalink corrigido.
	 */
	public function fix_cross_site_permalink(string $permalink, WP_Post|int $post): string
	{
		// Obter ID do blog atual e ID do post.
		$post_id = is_object($post) ? $post->ID : $post;

		// Para posts de rede externa, sempre usar permalink do Meilisearch.
		if (is_object($post) && isset($post->meilisearch_external) && $post->meilisearch_external) {
			if (isset($post->meilisearch_permalink)) {
				return $post->meilisearch_permalink;
			}
		}

		// Tentar obter blog_id do objeto post primeiro.
		$blog_id = null;
		if (is_object($post) && isset($post->meilisearch_blog_id)) {
			$blog_id = $post->meilisearch_blog_id;
		}

		// Se não houver blog_id do objeto, tentar todos os blog_ids possíveis no mapa.
		if (!$blog_id) {
			foreach ($this->permalink_map as $key => $stored_permalink) {
				if (str_contains($key, '_' . $post_id)) {
					return $stored_permalink;
				}
			}
			return $permalink;
		}

		$key = $blog_id . '_' . $post_id;
		if (isset($this->permalink_map[$key])) {
			return $this->permalink_map[$key];
		}

		return $permalink;
	}
}
