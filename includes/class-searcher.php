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
		$indexes = $this->client->get_all_index_names();
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
		}		try {
			$results = $this->client->get_client()->multiSearch($queries);
			return $this->format_results($results);
		} catch (Exception $e) {
			error_log('Meilisearch search error: ' . $e->getMessage());
			return [
				'hits' => [],
				'total' => 0,
			];
		}
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
			error_log('Meilisearch search error: ' . $e->getMessage());
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
