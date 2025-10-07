<?php

declare(strict_types=1);

/**
 * API de Busca Meilisearch
 *
 * @package Meilisearch
 */

/**
 * Classe Meilisearch_Search_API
 *
 * Gerencia endpoints REST API para funcionalidade de busca.
 */
class Meilisearch_Search_API
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
		add_action('rest_api_init', [$this, 'register_rest_routes']);
	}

	/**
	 * Registrar endpoints REST API.
	 */
	public function register_rest_routes(): void
	{
		register_rest_route('meilisearch/v1', '/search', [
			'methods' => 'GET',
			'callback' => [$this, 'handle_search_request'],
			'permission_callback' => '__return_true',
			'args' => [
				'q' => [
					'required' => true,
					'type' => 'string',
					'description' => __('Search query string', 'meilisearch'),
				],
				'limit' => [
					'type' => 'integer',
					'default' => 10,
					'minimum' => 1,
					'maximum' => 100,
					'description' => __('Number of results per page', 'meilisearch'),
				],
				'page' => [
					'type' => 'integer',
					'default' => 1,
					'minimum' => 1,
					'description' => __('Page number', 'meilisearch'),
				],
				'offset' => [
					'type' => 'integer',
					'minimum' => 0,
					'description' => __('Offset for results', 'meilisearch'),
				],
				'sort' => [
					'type' => 'string',
					'description' => __('Sort parameter in format "attribute:direction" (e.g., "date:desc")', 'meilisearch'),
				],
			],
		]);
	}	/**
	 * Gerenciar requisição REST API de busca.
	 *
	 * @param WP_REST_Request $request Objeto de requisição.
	 * @return WP_REST_Response Objeto de resposta.
	 */
	public function handle_search_request(WP_REST_Request $request): WP_REST_Response
	{
		$query = $request->get_param('q');
		$limit = $request->get_param('limit') ?? 10;
		$page = $request->get_param('page') ?? 1;
		$offset = $request->get_param('offset');
		$sort = $request->get_param('sort') ?? '';

		// Se offset não foi fornecido, calcular a partir da página.
		if (null === $offset) {
			$offset = ($page - 1) * $limit;
		}

		if (null === $query || '' === $query) {
			return new WP_REST_Response([
				'results' => [],
				'total' => 0,
				'query' => $query,
				'limit' => $limit,
				'offset' => $offset,
				'page' => $page,
				'sort' => $sort,
			], 200);
		}

		$searcher = new Meilisearch_Searcher($this->client);
		
		// Preparar argumentos de busca
		$search_args = [
			'limit' => $limit,
			'offset' => $offset,
		];
		
		// Adicionar ordenação se fornecida
		if (!empty($sort)) {
			$search_args['sort'] = $sort;
		}
		
		$results = $searcher->search_network($query, $search_args);

		// Formatar resultados com detalhes completos.
		$formatted_results = $this->format_search_results($results['hits']);

		// Calcular informações de paginação.
		$total_pages = $limit > 0 ? (int) ceil($results['total'] / $limit) : 0;

		return new WP_REST_Response([
			'results' => $formatted_results,
			'total' => $results['total'],
			'query' => $query,
			'limit' => $limit,
			'offset' => $offset,
			'page' => $page,
			'total_pages' => $total_pages,
			'sort' => $sort,
		], 200);
	}

	/**
	 * Formatar resultados de busca para resposta da API.
	 *
	 * @param array $hits Hits de busca brutos.
	 * @return array Resultados formatados.
	 */
	private function format_search_results(array $hits): array
	{
		$results = [];

		foreach ($hits as $hit) {
			$result = [
				'id' => $hit['id'] ?? 0,
				'title' => $hit['title'] ?? '',
				'excerpt' => $hit['excerpt'] ?? '',
				'content' => $hit['content'] ?? '',
				'permalink' => $hit['permalink'] ?? '',
				'blog_id' => $hit['blog_id'] ?? 0,
				'post_type' => $hit['post_type'] ?? 'post',
				'post_status' => $hit['post_status'] ?? 'publish',
				'post_date' => $hit['post_date'] ?? '',
				'post_modified' => $hit['post_modified'] ?? '',
				'author_id' => $hit['author_id'] ?? 0,
			];

			// Adicionar informações do autor se disponível.
			if (!empty($hit['author_id'])) {
				$author = get_userdata((int) $hit['author_id']);
				if ($author) {
					$result['author'] = [
						'id' => $author->ID,
						'name' => $author->display_name,
						'url' => get_author_posts_url($author->ID),
					];
				}
			}

			// Adicionar imagem destacada se disponível.
			if (!empty($hit['featured_image'])) {
				$result['featured_image'] = $hit['featured_image'];
			}

			// Adicionar categorias e tags se disponíveis.
			if (!empty($hit['categories'])) {
				$result['categories'] = $hit['categories'];
			}

			if (!empty($hit['tags'])) {
				$result['tags'] = $hit['tags'];
			}

			$results[] = $result;
		}

		return $results;
	}
}
