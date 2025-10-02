<?php
/**
 * Meilisearch WP-CLI Commands
 *
 * @package Meilisearch
 */

/**
 * Class Meilisearch_Commands
 *
 * WP-CLI commands for Meilisearch operations.
 */
class Meilisearch_Commands {

	/**
	 * Meilisearch client instance.
	 *
	 * @var Meilisearch_Client|null
	 */
	private ?Meilisearch_Client $client = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$settings = get_site_option( 'meilisearch_settings', [] );

		if ( ! empty( $settings['host'] ) ) {
			$this->client = new Meilisearch_Client(
				$settings['host'],
				$settings['master_key'] ?? ''
			);
		}
	}

	/**
	 * Index all content across the network.
	 *
	 * ## OPTIONS
	 *
	 * [--network]
	 * : Index all sites in the network.
	 *
	 * [--url=<url>]
	 * : Index a specific site by URL.
	 *
	 * ## EXAMPLES
	 *
	 *     wp meilisearch index --network
	 *     wp meilisearch index --url=site.example.com
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function index( array $args, array $assoc_args ): void {
		if ( ! $this->client ) {
			WP_CLI::error( 'Meilisearch is not configured. Please configure settings first.' );
			return;
		}

		$indexer = new Meilisearch_Indexer( $this->client );

		if ( isset( $assoc_args['network'] ) ) {
			WP_CLI::log( 'Starting network-wide indexing...' );

			$progress = \WP_CLI\Utils\make_progress_bar( 'Indexing sites', count( get_sites() ) );

			$results = $indexer->bulk_index_network(
				function ( $blog_id, $result ) use ( $progress ) {
					$progress->tick();
					WP_CLI::log( 
						sprintf(
							'Site %d: %d/%d posts indexed',
							$blog_id,
							$result['indexed'],
							$result['total']
						)
					);
				}
			);

			$progress->finish();

			WP_CLI::success(
				sprintf(
					'Indexed %d posts across %d sites.',
					$results['indexed_posts'],
					$results['total_sites']
				)
			);

			if ( ! empty( $results['errors'] ) ) {
				WP_CLI::warning( 'Some errors occurred during indexing.' );
				foreach ( $results['errors'] as $blog_id => $errors ) {
					WP_CLI::log( "Site {$blog_id} errors: " . implode( ', ', $errors ) );
				}
			}
		} else {
			$blog_id = get_current_blog_id();
			WP_CLI::log( "Indexing site {$blog_id}..." );

			$result = $indexer->index_site_posts( $blog_id );

			WP_CLI::success(
				sprintf(
					'Indexed %d/%d posts for site %d.',
					$result['indexed'],
					$result['total'],
					$blog_id
				)
			);
		}
	}

	/**
	 * Reindex all content (clear and rebuild).
	 *
	 * ## OPTIONS
	 *
	 * [--network]
	 * : Reindex all sites in the network.
	 *
	 * ## EXAMPLES
	 *
	 *     wp meilisearch reindex --network
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function reindex( array $args, array $assoc_args ): void {
		if ( ! $this->client ) {
			WP_CLI::error( 'Meilisearch is not configured. Please configure settings first.' );
			return;
		}

		WP_CLI::confirm( 'This will clear all existing indexes and rebuild them. Continue?' );

		if ( isset( $assoc_args['network'] ) ) {
			$sites = get_sites( [ 'number' => 9999 ] );

			foreach ( $sites as $site ) {
				WP_CLI::log( "Recreating index for site {$site->blog_id}..." );
				$this->client->delete_index( $site->blog_id );
				$this->client->create_index( $site->blog_id );
			}
		} else {
			$blog_id = get_current_blog_id();
			WP_CLI::log( "Recreating index for site {$blog_id}..." );
			$this->client->delete_index( $blog_id );
			$this->client->create_index( $blog_id );
		}

		// Now index.
		$this->index( $args, $assoc_args );
	}

	/**
	 * Clear all Meilisearch indexes.
	 *
	 * ## EXAMPLES
	 *
	 *     wp meilisearch clear-index
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function clear_index( array $args, array $assoc_args ): void {
		if ( ! $this->client ) {
			WP_CLI::error( 'Meilisearch is not configured. Please configure settings first.' );
			return;
		}

		WP_CLI::confirm( 'This will delete all Meilisearch indexes. Continue?' );

		$sites = get_sites( [ 'number' => 9999 ] );

		foreach ( $sites as $site ) {
			WP_CLI::log( "Deleting index for site {$site->blog_id}..." );
			$this->client->delete_index( $site->blog_id );
		}

		WP_CLI::success( 'All indexes cleared.' );
	}

	/**
	 * Check indexing status across the network.
	 *
	 * ## OPTIONS
	 *
	 * [--network]
	 * : Check status for all sites.
	 *
	 * ## EXAMPLES
	 *
	 *     wp meilisearch status --network
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function status( array $args, array $assoc_args ): void {
		if ( ! $this->client ) {
			WP_CLI::error( 'Meilisearch is not configured. Please configure settings first.' );
			return;
		}

		// Test connection.
		if ( ! $this->client->test_connection() ) {
			WP_CLI::error( 'Cannot connect to Meilisearch server.' );
			return;
		}

		WP_CLI::success( 'Connected to Meilisearch server.' );

		$sites = isset( $assoc_args['network'] ) 
			? get_sites( [ 'number' => 9999 ] ) 
			: [ get_site( get_current_blog_id() ) ];

		$rows = [];

		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );

			$index_name  = $this->client->get_index_name( $site->blog_id );
			$posts_count = wp_count_posts()->publish ?? 0;

			try {
				$index = $this->client->get_client()->index( $index_name );
				$stats = $index->stats();
				$indexed_count = $stats['numberOfDocuments'] ?? 0;
			} catch ( Exception $e ) {
				$indexed_count = 0;
			}

			$rows[] = [
				'Site ID'      => $site->blog_id,
				'Index'        => $index_name,
				'Posts'        => $posts_count,
				'Indexed'      => $indexed_count,
				'Status'       => $posts_count === $indexed_count ? '✓ Synced' : '⚠ Out of sync',
			];

			restore_current_blog();
		}

		WP_CLI\Utils\format_items( 'table', $rows, array_keys( $rows[0] ) );
	}
}

// Register WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'meilisearch', 'Meilisearch_Commands' );
}
