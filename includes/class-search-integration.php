<?php

declare(strict_types=1);

/**
 * Integração de Busca Nativa do WordPress com Meilisearch
 *
 * @package Meilisearch
 */

/**
 * Classe Meilisearch_Search_Integration
 *
 * Substitui a busca nativa do WordPress pelo Meilisearch e adiciona badges de relevância
 */
class Meilisearch_Search_Integration
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
		// Interceptar query de busca
		add_action('pre_get_posts', [$this, 'hijack_search_query'], 1);
		
		// Modificar resultados para incluir ranking score
		add_filter('the_title', [$this, 'add_relevance_badge_to_title'], 10, 2);
		
		// Adicionar URL após o excerpt apenas se habilitado nas configurações
		if (Meilisearch_Search_Settings::should_show_post_urls()) {
			add_filter('the_excerpt', [$this, 'add_url_after_excerpt'], 10);
			// Não usar the_content para evitar duplicação, pois alguns temas processam ambos
		}
		
		// Enfileirar CSS nos resultados de busca
		add_action('wp_enqueue_scripts', [$this, 'enqueue_search_styles']);
	}

	/**
	 * Interceptar e modificar a query de busca do WordPress.
	 *
	 * @param WP_Query $query A query do WordPress.
	 */
	public function hijack_search_query(WP_Query $query): void
	{
		// Apenas para buscas principais e não no admin
		if (!$query->is_main_query() || is_admin() || !$query->is_search()) {
			return;
		}

		// Obter termo de busca
		$search_term = $query->get('s');
		
		if (empty($search_term)) {
			return;
		}

		// Armazenar termo de busca original em variável global para uso no tema
		global $meilisearch_search_term, $meilisearch_total_results;
		$meilisearch_search_term = $search_term;

		// Executar busca no Meilisearch
		$searcher = new Meilisearch_Searcher($this->client);
		$results = $searcher->search_network($search_term, [
			'limit' => $query->get('posts_per_page') ?: 10,
			'offset' => 0,
		]);

		// Armazenar total de resultados encontrados
		$meilisearch_total_results = $results['estimatedTotalHits'] ?? count($results['hits'] ?? []);

		// Armazenar resultados e ranking scores em variável global para uso posterior
		global $meilisearch_results;
		$meilisearch_results = [];
		
		if (!empty($results['hits'])) {
			$post_ids = [];
			
			foreach ($results['hits'] as $hit) {
				$post_id = $hit['id'] ?? 0;
				$blog_id = $hit['blog_id'] ?? get_current_blog_id();
				
				if ($post_id > 0) {
					// Armazenar ranking score por post_id
					$key = $blog_id . '_' . $post_id;
					$meilisearch_results[$key] = [
						'ranking_score' => $hit['_rankingScore'] ?? 0,
						'blog_id' => $blog_id,
						'post_id' => $post_id,
					];
					
					// Adicionar apenas posts do site atual
					if ($blog_id == get_current_blog_id()) {
						$post_ids[] = $post_id;
					}
				}
			}

			// Modificar query para buscar apenas os posts encontrados pelo Meilisearch
			if (!empty($post_ids)) {
				$query->set('post__in', $post_ids);
				$query->set('orderby', 'post__in'); // Manter ordem do Meilisearch
				// NÃO limpar 's' para que o tema possa exibir o termo de busca
			}
		}
	}

	/**
	 * Adicionar badge de relevância ao título do post nos resultados de busca.
	 *
	 * @param string $title  O título do post.
	 * @param int    $post_id O ID do post.
	 * @return string Título com badge de relevância.
	 */
	public function add_relevance_badge_to_title(string $title, int $post_id): string
	{
		// Apenas em páginas de busca e não no admin
		if (!is_search() || is_admin()) {
			return $title;
		}

		// Verificar se os badges estão habilitados nas configurações
		if (!Meilisearch_Search_Settings::should_show_relevance_badges()) {
			return $title;
		}

		global $meilisearch_results;
		
		if (empty($meilisearch_results)) {
			return $title;
		}

		$blog_id = get_current_blog_id();
		$key = $blog_id . '_' . $post_id;

		if (!isset($meilisearch_results[$key])) {
			return $title;
		}

		$ranking_score = $meilisearch_results[$key]['ranking_score'];
		$relevance_percent = round($ranking_score * 100, 1);
		
		// Determinar classe CSS baseada na relevância
		if ($ranking_score >= 0.8) {
			$relevance_class = 'high';
		} elseif ($ranking_score >= 0.5) {
			$relevance_class = 'medium';
		} else {
			$relevance_class = 'low';
		}

		// Construir badge HTML
		$badge = sprintf(
			'<span class="meilisearch-relevance-badge meilisearch-relevance-%s" title="Relevância: %s%%">' .
			'<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="display:inline-block;vertical-align:middle;margin-right:3px;">' .
			'<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>' .
			'</svg>%s%%</span>',
			esc_attr($relevance_class),
			esc_attr($relevance_percent),
			esc_html($relevance_percent)
		);

		return $title . ' ' . $badge;
	}

	/**
	 * Adicionar URL após o excerpt nos resultados de busca.
	 *
	 * @param string $excerpt O excerpt do post.
	 * @return string Excerpt com URL adicionada.
	 */
	public function add_url_after_excerpt(string $excerpt): string
	{
		// Apenas em páginas de busca e não no admin
		if (!is_search() || is_admin()) {
			return $excerpt;
		}

		// Verificar se as URLs estão habilitadas nas configurações
		if (!Meilisearch_Search_Settings::should_show_post_urls()) {
			return $excerpt;
		}

		$permalink = get_permalink();
		
		// Se não conseguir obter o permalink, retornar apenas o excerpt
		if (!$permalink || false === $permalink) {
			return $excerpt;
		}
		
		$url_display = '<div class="meilisearch-result-url" style="margin-top:0.5rem;font-size:0.85rem;color:#666;word-break:break-all;">' .
			'<strong>URL:</strong> <a href="' . esc_url($permalink) . '" style="color:#0073aa;">' . esc_html($permalink) . '</a>' .
			'</div>';

		return $excerpt . $url_display;
	}

	/**
	 * Adicionar URL após o content nos resultados de busca.
	 *
	 * @param string $content O conteúdo do post.
	 * @return string Conteúdo com URL adicionada.
	 */
	public function add_url_after_content(string $content): string
	{
		// Apenas em páginas de busca e não no admin
		if (!is_search() || is_admin() || !in_the_loop() || !is_main_query()) {
			return $content;
		}

		// Verificar se as URLs estão habilitadas nas configurações
		if (!Meilisearch_Search_Settings::should_show_post_urls()) {
			return $content;
		}

		$permalink = get_permalink();
		
		$url_display = '<div class="meilisearch-result-url" style="margin-top:0.5rem;font-size:0.85rem;color:#666;word-break:break-all;">' .
			'<strong>URL:</strong> <a href="' . esc_url($permalink) . '" style="color:#0073aa;">' . esc_html($permalink) . '</a>' .
			'</div>';

		return $content . $url_display;
	}

	/**
	 * Enfileirar estilos CSS nas páginas de busca.
	 */
	public function enqueue_search_styles(): void
	{
		if (!is_search()) {
			return;
		}

		// Apenas carregar CSS se os badges estiverem habilitados
		if (!Meilisearch_Search_Settings::should_show_relevance_badges()) {
			return;
		}

		wp_enqueue_style(
			'meilisearch-search-results',
			plugins_url('assets/css/search-results.css', dirname(__FILE__)),
			[],
			'1.0.0'
		);
	}
}
