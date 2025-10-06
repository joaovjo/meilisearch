<?php

declare(strict_types=1);

/**
 * Meilisearch Searcher
 *
 * @package Meilisearch
 */

use Meilisearch\Contracts\SearchQuery;

/**
 * Class Meilisearch_Searcher
 *
 * Handles search queries to Meilisearch.
 */
class Meilisearch_Searcher
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
	 * Search across all network indexes.
	 *
	 * @param string $query  Search query.
	 * @param array  $args   Optional search arguments.
	 * @return array Search results.
	 */
	public function search_network(string $query, array $args = []): array
	{
		$indexes = $this->get_searchable_indexes();
		$queries = [];

		$default_args = [
			'limit' => 20,
			'offset' => 0,
		];

		$args = wp_parse_args($args, $default_args);

		// Ensure limit and offset are integers.
		$args['limit'] = (int) $args['limit'];
		$args['offset'] = (int) $args['offset'];

		// Prepare multi-search queries.
		foreach ($indexes as $index) {
			$search_query = (new SearchQuery())
				->setIndexUid($index)
				->setQuery($query)
				->setLimit($args['limit'])
				->setOffset($args['offset'])
				->setFilter(['post_status = publish']);

			$queries[] = $search_query;
		}
		try {
			$results = $this->client->get_client()->multiSearch($queries);
			return $this->format_results($results);
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only.
				error_log('Meilisearch search error: ' . $e->getMessage());
			}
			return [
				'hits' => [],
				'total' => 0,
			];
		}
	}

	/**
	 * Get all searchable indexes including additional patterns.
	 *
	 * Returns indexes from the current network plus any additional patterns
	 * selected in the Multi-Pattern Search settings.
	 *
	 * @since 1.0.0
	 * @return array Array of index names to search
	 */
	private function get_searchable_indexes(): array
	{
		// Start with current network indexes
		$indexes = $this->client->get_all_index_names();

		// Get additional patterns configuration
		$additional_patterns = is_multisite() 
			? get_site_option('meilisearch_additional_patterns', [])
			: get_option('meilisearch_additional_patterns', []);

		if (empty($additional_patterns) || !is_array($additional_patterns)) {
			return $indexes;
		}

		// Get all available indexes from Meilisearch
		try {
			$sdk_client = $this->client->get_client();
			$all_indexes = $sdk_client->getIndexes();
			$all_index_names = [];

			foreach ($all_indexes->getResults() as $index) {
				$all_index_names[] = $index->getUid();
			}

			// Match indexes against additional patterns
			foreach ($additional_patterns as $pattern) {
				$pattern_regex = $this->convert_pattern_to_regex($pattern);

				foreach ($all_index_names as $index_name) {
					if (preg_match($pattern_regex, $index_name) && !in_array($index_name, $indexes, true)) {
						$indexes[] = $index_name;
					}
				}
			}
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only.
				error_log('Meilisearch get indexes error: ' . $e->getMessage());
			}
		}

		return $indexes;
	}

	/**
	 * Convert pattern format to regex
	 *
	 * Converts index pattern format (e.g., "setur_{blog_id}_posts") to a regex pattern.
	 *
	 * @since 1.0.0
	 * @param string $pattern Pattern format string.
	 * @return string Regex pattern
	 */
	private function convert_pattern_to_regex(string $pattern): string
	{
		// Escape special regex characters except our placeholders
		$regex = preg_quote($pattern, '/');

		// Replace {blog_id} placeholder with number pattern
		$regex = str_replace('\{blog_id\}', '\d+', $regex);

		// Replace {type} placeholder if present
		$regex = str_replace('\{type\}', '[a-z]+', $regex);

		return '/^' . $regex . '$/';
	}

	/**
	 * Search in a specific site index.
	 *
	 * @param string $query   Search query.
	 * @param int    $blog_id Site ID.
	 * @param array  $args    Optional search arguments.
	 * @return array Search results.
	 */
	public function search_site(string $query, int $blog_id, array $args = []): array
	{
		$index_name = $this->client->get_index_name($blog_id);

		$default_args = [
			'limit' => 20,
			'offset' => 0,
		];

		$args = wp_parse_args($args, $default_args);

		// Ensure limit and offset are integers.
		$args['limit'] = (int) $args['limit'];
		$args['offset'] = (int) $args['offset'];

		try {
			$results = $this->client
				->get_client()
				->index($index_name)
				->search($query, [
					'limit' => $args['limit'],
					'offset' => $args['offset'],
					'filter' => ['post_status = publish'],
				]);

			return [
				'hits' => $results->getHits(),
				'total' => $results->getEstimatedTotalHits(),
			];
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only.
				error_log('Meilisearch search error: ' . $e->getMessage());
			}
			return [
				'hits' => [],
				'total' => 0,
			];
		}
	}

	/**
	 * Get autocomplete suggestions.
	 *
	 * @param string $query Search query.
	 * @param int    $limit Number of suggestions.
	 * @return array Suggestions.
	 */
	public function get_suggestions(string $query, int $limit = 5): array
	{
		$results = $this->search_network($query, [
			'limit' => $limit,
			'offset' => 0,
		]);

		$suggestions = [];

		foreach ($results['hits'] as $hit) {
			$suggestions[] = [
				'title' => $hit['title'] ?? '',
				'excerpt' => $hit['excerpt'] ?? '',
				'permalink' => $hit['permalink'] ?? '',
				'blog_id' => $hit['blog_id'] ?? 0,
			];
		}

		return $suggestions;
	}

	/**
	 * Format multi-search results.
	 *
	 * @param array $results Raw Meilisearch results.
	 * @return array Formatted results.
	 */
	private function format_results(array $results): array
	{
		$hits = [];
		$total = 0;

		if (isset($results['results'])) {
			foreach ($results['results'] as $result) {
				$hits = array_merge($hits, $result['hits'] ?? []);
				$total += $result['estimatedTotalHits'] ?? 0;
			}
		}

		return [
			'hits' => $hits,
			'total' => $total,
		];
	}
}
