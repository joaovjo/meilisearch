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
			// Check if blog exists in current network.
			$blog_exists = get_blog_details($blog_id, false);
			
			if ($blog_id !== $current_blog_id && $blog_exists) {
				switch_to_blog($blog_id);
			}

			foreach ($post_ids as $post_id) {
				$post = null;
				
				// Only try to get post if blog exists in current network.
				if ($blog_exists) {
					$post = get_post($post_id);
				}
				
				// If post not found (external network), create a pseudo-post from Meilisearch data.
				if (!$post) {
					// Find the hit data for this post.
					foreach ($results['hits'] as $hit) {
						if (($hit['blog_id'] ?? 0) === $blog_id && ($hit['id'] ?? 0) === $post_id) {
							$post = $this->create_pseudo_post_from_hit($hit);
							break;
						}
					}
				}
				
				if ($post) {
					$key = $blog_id . '_' . $post_id;

					// Add blog_id to post object for reference.
					$post->meilisearch_blog_id = $blog_id;
					$post->meilisearch_external = !$blog_exists;

					// Add permalink from Meilisearch if available.
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
	 * Create a pseudo WP_Post object from Meilisearch hit data.
	 *
	 * Used for posts from external networks that don't exist in the current database.
	 *
	 * @param array $hit Meilisearch hit data.
	 * @return WP_Post|null Pseudo post object or null.
	 */
	private function create_pseudo_post_from_hit(array $hit): ?WP_Post
	{
		if (!isset($hit['id']) || !isset($hit['title'])) {
			return null;
		}

		// Create a stdClass that mimics WP_Post structure.
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

		// For external network posts, always use Meilisearch permalink.
		if (is_object($post) && isset($post->meilisearch_external) && $post->meilisearch_external) {
			if (isset($post->meilisearch_permalink)) {
				return $post->meilisearch_permalink;
			}
		}

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
