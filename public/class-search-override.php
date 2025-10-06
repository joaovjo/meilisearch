<?php

declare(strict_types=1);

/**
 * Meilisearch Search Override
 *
 * @package Meilisearch
 */

/**
 * Class Meilisearch_Search_Override
 *
 * Replaces WordPress default search with Meilisearch.
 */
class Meilisearch_Search_Override
{
	/**
	 * Meilisearch searcher instance.
	 *
	 * @var Meilisearch_Searcher
	 */
	private Meilisearch_Searcher $searcher;

	/**
	 * Cache for Meilisearch results.
	 *
	 * @var array|null
	 */
	private null|array $cached_results = null;

	/**
	 * Map of post permalinks from Meilisearch (blog_id_postid => permalink).
	 *
	 * @var array
	 */
	private array $permalink_map = [];

	/**
	 * Constructor.
	 *
	 * @param Meilisearch_Searcher $searcher Meilisearch searcher instance.
	 */
	public function __construct(Meilisearch_Searcher $searcher)
	{
		$this->searcher = $searcher;
	}

	/**
	 * Initialize WordPress hooks.
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
	 * Override WordPress search query with Meilisearch results.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 */
	public function override_search_query(WP_Query $query): void
	{
		// Only override main search queries on frontend.
		if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
			return;
		}

		$search_term = $query->get('s');

		if (null === $search_term || '' === $search_term) {
			return;
		}

		// Mark this query as Meilisearch-powered.
		$query->set('meilisearch_query', true);

		// Get pagination parameters.
		$paged = max(1, $query->get('paged'));
		$posts_per_page = $query->get('posts_per_page');

		if ($posts_per_page < 1) {
			$posts_per_page = get_option('posts_per_page', 10);
		}

		$offset = ($paged - 1) * $posts_per_page;

		// Perform Meilisearch search.
		$results = $this->searcher->search_network($search_term, [
			'limit' => $posts_per_page,
			'offset' => $offset,
		]);

		// Cache results for use in posts_pre_query filter.
		$this->cached_results = $results;

		// Set total found posts for pagination.
		add_filter('found_posts', fn(): int => $results['total'], 10, 2);
	}

	/**
	 * Get posts from Meilisearch results (cross-site compatible).
	 *
	 * @param array|null $posts  Array of post data or null.
	 * @param WP_Query   $query  The WP_Query instance.
	 * @return array|null Array of WP_Post objects or null.
	 */
	public function get_posts_from_meilisearch(array|null $posts, WP_Query $query): array|null
	{
		// Only process Meilisearch queries.
		if (!$query->get('meilisearch_query') || null === $this->cached_results) {
			return $posts;
		}

		$results = $this->cached_results;
		$post_objects = [];
		$current_blog_id = get_current_blog_id();

		// Group results by blog_id and build permalink map.
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

				// Store permalink for later use.
				if ($permalink) {
					$this->permalink_map[$blog_id . '_' . $post_id] = $permalink;
				}
			}
		}

		// Fetch posts from each blog.
		$fetched_posts = [];
		foreach ($posts_by_blog as $blog_id => $post_ids) {
			if ($blog_id !== $current_blog_id) {
				switch_to_blog($blog_id);
			}

			foreach ($post_ids as $post_id) {
				$post = get_post($post_id);
				if ($post) {
					$key = $blog_id . '_' . $post_id;

					// Add blog_id to post object for reference.
					$post->meilisearch_blog_id = $blog_id;

					// Add permalink from Meilisearch if available.
					if (isset($this->permalink_map[$key])) {
						$post->meilisearch_permalink = $this->permalink_map[$key];
					}

					$fetched_posts[$key] = $post;
				}
			}

			if ($blog_id !== $current_blog_id) {
				restore_current_blog();
			}
		}

		// Rebuild posts array in Meilisearch order.
		foreach ($results['hits'] as $hit) {
			$blog_id = $hit['blog_id'] ?? 0;
			$post_id = $hit['id'] ?? 0;
			$key = $blog_id . '_' . $post_id;

			if (isset($fetched_posts[$key])) {
				$post_objects[] = $fetched_posts[$key];
			}
		}

		// Clear cache after returning results.
		// Keep permalink_map for later use by permalink filters.
		$this->cached_results = null;

		return $post_objects;
	}

	/**
	 * Fix cross-site permalink.
	 *
	 * @param string  $permalink The post permalink.
	 * @param WP_Post $post      Post object.
	 * @return string Corrected permalink.
	 */
	public function fix_cross_site_permalink(string $permalink, WP_Post|int $post): string
	{
		// Get current blog ID and post ID.
		$post_id = is_object($post) ? $post->ID : $post;

		// Try to get blog_id from post object first.
		$blog_id = null;
		if (is_object($post) && isset($post->meilisearch_blog_id)) {
			$blog_id = $post->meilisearch_blog_id;
		}

		// If no blog_id from object, try all possible blog IDs in the map.
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
