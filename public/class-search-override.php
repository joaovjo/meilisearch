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

		// Get pagination parameters.
		$paged       = max( 1, $query->get( 'paged' ) );
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

		// Extract post IDs from results.
		$post_ids = [];
		foreach ( $results['hits'] as $hit ) {
			if ( isset( $hit['id'] ) && isset( $hit['blog_id'] ) ) {
				// Switch to correct blog context for post ID.
				switch_to_blog( $hit['blog_id'] );
				$post_ids[] = $hit['id'];
				restore_current_blog();
			}
		}

		if ( empty( $post_ids ) ) {
			// No results - set impossible condition.
			$query->set( 'post__in', [ 0 ] );
		} else {
			// Set post IDs in order.
			$query->set( 'post__in', $post_ids );
			$query->set( 'orderby', 'post__in' );
			$query->set( 'ignore_sticky_posts', true );
		}

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
}
