<?php
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
class Meilisearch_Search_Override {

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
	private ?array $cached_results = null;

	/**
	 * Constructor.
	 *
	 * @param Meilisearch_Searcher $searcher Meilisearch searcher instance.
	 */
	public function __construct( Meilisearch_Searcher $searcher ) {
		$this->searcher = $searcher;
	}

	/**
	 * Initialize WordPress hooks.
	 */
	public function init_hooks(): void {
		add_action( 'pre_get_posts', [ $this, 'override_search_query' ], 10 );
		add_filter( 'posts_pre_query', [ $this, 'get_posts_from_meilisearch' ], 10, 2 );
	}

	/**
	 * Override WordPress search query with Meilisearch results.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 */
	public function override_search_query( WP_Query $query ): void {
		// Only override main search queries on frontend.
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		$search_term = $query->get( 's' );

		if ( empty( $search_term ) ) {
			return;
		}

		// Mark this query as Meilisearch-powered.
		$query->set( 'meilisearch_query', true );

		// Get pagination parameters.
		$paged          = max( 1, $query->get( 'paged' ) );
		$posts_per_page = $query->get( 'posts_per_page' );

		if ( $posts_per_page < 1 ) {
			$posts_per_page = get_option( 'posts_per_page', 10 );
		}

		$offset = ( $paged - 1 ) * $posts_per_page;

		// Perform Meilisearch search.
		$results = $this->searcher->search_network(
			$search_term,
			[
				'limit'  => $posts_per_page,
				'offset' => $offset,
			]
		);

		// Cache results for use in posts_pre_query filter.
		$this->cached_results = $results;

		// Set total found posts for pagination.
		add_filter(
			'found_posts',
			function () use ( $results ) {
				return $results['total'];
			},
			10,
			2
		);
	}

	/**
	 * Get posts from Meilisearch results (cross-site compatible).
	 *
	 * @param array|null $posts  Array of post data or null.
	 * @param WP_Query   $query  The WP_Query instance.
	 * @return array|null Array of WP_Post objects or null.
	 */
	public function get_posts_from_meilisearch( $posts, WP_Query $query ) {
		// Only process Meilisearch queries.
		if ( ! $query->get( 'meilisearch_query' ) || null === $this->cached_results ) {
			return $posts;
		}

		$results     = $this->cached_results;
		$post_objects = [];
		$current_blog_id = get_current_blog_id();

		// Group results by blog_id.
		$posts_by_blog = [];
		foreach ( $results['hits'] as $hit ) {
			$blog_id = $hit['blog_id'] ?? 0;
			$post_id = $hit['id'] ?? 0;

			if ( $blog_id && $post_id ) {
				if ( ! isset( $posts_by_blog[ $blog_id ] ) ) {
					$posts_by_blog[ $blog_id ] = [];
				}
				$posts_by_blog[ $blog_id ][] = $post_id;
			}
		}

		// Fetch posts from each blog.
		$fetched_posts = [];
		foreach ( $posts_by_blog as $blog_id => $post_ids ) {
			if ( $blog_id !== $current_blog_id ) {
				switch_to_blog( $blog_id );
			}

			foreach ( $post_ids as $post_id ) {
				$post = get_post( $post_id );
				if ( $post ) {
					// Add blog_id to post object for reference.
					$post->meilisearch_blog_id = $blog_id;
					$fetched_posts[ $blog_id . '_' . $post_id ] = $post;
				}
			}

			if ( $blog_id !== $current_blog_id ) {
				restore_current_blog();
			}
		}

		// Rebuild posts array in Meilisearch order.
		foreach ( $results['hits'] as $hit ) {
			$blog_id = $hit['blog_id'] ?? 0;
			$post_id = $hit['id'] ?? 0;
			$key     = $blog_id . '_' . $post_id;

			if ( isset( $fetched_posts[ $key ] ) ) {
				$post_objects[] = $fetched_posts[ $key ];
			}
		}

		// Clear cache.
		$this->cached_results = null;

		return ! empty( $post_objects ) ? $post_objects : [ false ];
	}
}
