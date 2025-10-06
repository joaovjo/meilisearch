<?php

declare(strict_types=1);

/**
 * Meilisearch Search API
 *
 * @package Meilisearch
 */

/**
 * Class Meilisearch_Search_API
 *
 * Handles REST API endpoints for search functionality.
 */
class Meilisearch_Search_API
{
	/**
	 * Meilisearch client instance.
	 *
	 * @var Meilisearch_Client
	 */
	private Meilisearch_Client $client;

	/**
	 * Constructor.
	 *
	 * @param Meilisearch_Client $client Meilisearch client instance.
	 */
	public function __construct(Meilisearch_Client $client)
	{
		$this->client = $client;
	}

	/**
	 * Initialize WordPress hooks.
	 */
	public function init_hooks(): void
	{
		add_action('rest_api_init', [$this, 'register_rest_routes']);
	}

	/**
	 * Register REST API endpoints.
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
					'sanitize_callback' => 'sanitize_text_field',
					'description' => 'Search query string',
				],
				'limit' => [
					'required' => false,
					'type' => 'integer',
					'default' => 10,
					'sanitize_callback' => 'absint',
					'minimum' => 1,
					'maximum' => 100,
					'description' => 'Number of results to return',
				],
				'offset' => [
					'required' => false,
					'type' => 'integer',
					'default' => 0,
					'sanitize_callback' => 'absint',
					'minimum' => 0,
					'description' => 'Number of results to skip',
				],
				'page' => [
					'required' => false,
					'type' => 'integer',
					'default' => 1,
					'sanitize_callback' => 'absint',
					'minimum' => 1,
					'description' => 'Page number (alternative to offset)',
				],
			],
		]);
	}

	/**
	 * Handle search REST API request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_search_request(WP_REST_Request $request): WP_REST_Response
	{
		$query = $request->get_param('q');
		$limit = $request->get_param('limit') ?? 10;
		$page = $request->get_param('page') ?? 1;
		$offset = $request->get_param('offset');

		// If offset is not provided, calculate from page.
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
			], 200);
		}

		$searcher = new Meilisearch_Searcher($this->client);
		$results = $searcher->search_network($query, [
			'limit' => $limit,
			'offset' => $offset,
		]);

		// Format results with full details.
		$formatted_results = $this->format_search_results($results['hits']);

		// Calculate pagination info.
		$total_pages = $limit > 0 ? (int) ceil($results['total'] / $limit) : 0;

		return new WP_REST_Response([
			'results' => $formatted_results,
			'total' => $results['total'],
			'query' => $query,
			'limit' => $limit,
			'offset' => $offset,
			'page' => $page,
			'total_pages' => $total_pages,
		], 200);
	}

	/**
	 * Format search results for API response.
	 *
	 * @param array $hits Raw search hits.
	 * @return array Formatted results.
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

			// Add author information if available.
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

			// Add featured image if available.
			if (!empty($hit['featured_image'])) {
				$result['featured_image'] = $hit['featured_image'];
			}

			// Add categories and tags if available.
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
