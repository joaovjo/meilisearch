<?php
/**
 * WP-CLI commands for Meilisearch plugin.
 *
 * @package Meilisearch
 */

/**
 * Meilisearch WP-CLI commands.
 */
class Meilisearch_CLI {

	/**
	 * Meilisearch client instance.
	 *
	 * @var Meilisearch_Client
	 */
	private Meilisearch_Client $client;

	/**
	 * Meilisearch indexer instance.
	 *
	 * @var Meilisearch_Indexer
	 */
	private Meilisearch_Indexer $indexer;

	/**
	 * Constructor.
	 *
	 * @param Meilisearch_Client  $client  Meilisearch client instance.
	 * @param Meilisearch_Indexer $indexer Meilisearch indexer instance.
	 */
	public function __construct( Meilisearch_Client $client, Meilisearch_Indexer $indexer ) {
		$this->client  = $client;
		$this->indexer = $indexer;
	}

	/**
	 * Reindex all posts for a specific blog or all blogs in the network.
	 *
	 * ## OPTIONS
	 *
	 * [--blog_id=<blog_id>]
	 * : The blog ID to reindex. If not provided, reindexes all blogs in the network.
	 *
	 * [--url=<url>]
	 * : The blog URL to reindex (alternative to blog_id).
	 *
	 * ## EXAMPLES
	 *
	 *     # Reindex a specific blog by ID
	 *     wp meilisearch reindex --blog_id=2
	 *
	 *     # Reindex a specific blog by URL
	 *     wp meilisearch reindex --url=http://example.com/labcom/
	 *
	 *     # Reindex all blogs in the network
	 *     wp meilisearch reindex
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function reindex( $args, $assoc_args ) {
		$blog_id = $assoc_args['blog_id'] ?? null;
		$url     = $assoc_args['url'] ?? null;

		// If URL is provided, get blog_id from it.
		if ( $url && ! $blog_id ) {
			$blog_id = get_blog_id_from_url( $url );
			if ( ! $blog_id ) {
				WP_CLI::error( "Blog not found for URL: $url" );
			}
		}

		if ( $blog_id ) {
			// Reindex single blog.
			WP_CLI::log( "Reindexing blog $blog_id..." );
			$results = $this->indexer->index_site_posts( (int) $blog_id );

			if ( ! empty( $results['errors'] ) ) {
				WP_CLI::warning( 'Reindexing completed with errors:' );
				foreach ( $results['errors'] as $error ) {
					WP_CLI::warning( $error );
				}
			}

			WP_CLI::success( sprintf(
				'Reindexed %d of %d posts for blog %d',
				$results['indexed'],
				$results['total'],
				$blog_id
			) );
		} else {
			// Reindex all blogs in network.
			if ( ! is_multisite() ) {
				WP_CLI::error( 'This is not a multisite installation. Use --blog_id=1 or --url parameter.' );
			}

			$sites = get_sites( [ 'number' => 999 ] );
			WP_CLI::log( sprintf( 'Found %d sites to reindex...', count( $sites ) ) );

			$progress = \WP_CLI\Utils\make_progress_bar( 'Reindexing sites', count( $sites ) );

			$total_indexed = 0;
			$total_posts   = 0;

			foreach ( $sites as $site ) {
				$results = $this->indexer->index_site_posts( (int) $site->blog_id );
				$total_indexed += $results['indexed'];
				$total_posts   += $results['total'];
				$progress->tick();
			}

			$progress->finish();

			WP_CLI::success( sprintf(
				'Reindexed %d of %d posts across %d sites',
				$total_indexed,
				$total_posts,
				count( $sites )
			) );
		}
	}

	/**
	 * List all Meilisearch indexes.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv, yaml, count. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     # List all indexes as table
	 *     wp meilisearch list-indexes
	 *
	 *     # List all indexes as JSON
	 *     wp meilisearch list-indexes --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list_indexes( $args, $assoc_args ) {
		$format = $assoc_args['format'] ?? 'table';

		try {
			$indexes = $this->client->get_all_index_names();
			$data    = [];

			foreach ( $indexes as $index ) {
				// Extract blog_id from index name (wp_X_posts).
				preg_match( '/wp_(\d+)_posts/', $index, $matches );
				$blog_id = $matches[1] ?? 'unknown';

				$blog_name = 'Unknown';
				if ( is_numeric( $blog_id ) ) {
					$blog_details = get_blog_details( (int) $blog_id );
					$blog_name    = $blog_details ? $blog_details->blogname : 'Blog not found';
				}

				// Get index stats.
				$stats = $this->client->get_client()->index( $index )->stats();

				$data[] = [
					'index'      => $index,
					'blog_id'    => $blog_id,
					'blog_name'  => $blog_name,
					'documents'  => $stats['numberOfDocuments'] ?? 0,
					'indexing'   => ( $stats['isIndexing'] ?? false ) ? 'Yes' : 'No',
				];
			}

			WP_CLI\Utils\format_items( $format, $data, [ 'index', 'blog_id', 'blog_name', 'documents', 'indexing' ] );
		} catch ( Exception $e ) {
			WP_CLI::error( 'Failed to list indexes: ' . $e->getMessage() );
		}
	}

	/**
	 * Create index for a specific blog.
	 *
	 * ## OPTIONS
	 *
	 * <blog_id>
	 * : The blog ID to create index for.
	 *
	 * ## EXAMPLES
	 *
	 *     # Create index for blog 2
	 *     wp meilisearch create-index 2
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function create_index( $args, $assoc_args ) {
		$blog_id = (int) $args[0];

		if ( ! $blog_id ) {
			WP_CLI::error( 'Invalid blog ID provided.' );
		}

		WP_CLI::log( "Creating index for blog $blog_id..." );

		try {
			$result = $this->client->create_index( $blog_id );

			if ( $result ) {
				WP_CLI::success( "Index created for blog $blog_id: " . $this->client->get_index_name( $blog_id ) );
			} else {
				WP_CLI::error( 'Failed to create index.' );
			}
		} catch ( Exception $e ) {
			WP_CLI::error( 'Failed to create index: ' . $e->getMessage() );
		}
	}

	/**
	 * Delete index for a specific blog.
	 *
	 * ## OPTIONS
	 *
	 * <blog_id>
	 * : The blog ID to delete index for.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete index for blog 2
	 *     wp meilisearch delete-index 2 --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function delete_index( $args, $assoc_args ) {
		$blog_id = (int) $args[0];

		if ( ! $blog_id ) {
			WP_CLI::error( 'Invalid blog ID provided.' );
		}

		$index_name = $this->client->get_index_name( $blog_id );

		WP_CLI::confirm( "Are you sure you want to delete index '$index_name'?", $assoc_args );

		WP_CLI::log( "Deleting index $index_name..." );

		try {
			$result = $this->client->delete_index( $blog_id );

			if ( $result ) {
				WP_CLI::success( "Index deleted: $index_name" );
			} else {
				WP_CLI::error( 'Failed to delete index.' );
			}
		} catch ( Exception $e ) {
			WP_CLI::error( 'Failed to delete index: ' . $e->getMessage() );
		}
	}

	/**
	 * Search posts across the network.
	 *
	 * ## OPTIONS
	 *
	 * <query>
	 * : The search query.
	 *
	 * [--limit=<limit>]
	 * : Maximum number of results. Default: 20
	 *
	 * [--blog_id=<blog_id>]
	 * : Search only in specific blog. If not provided, searches all blogs.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv, yaml. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     # Search for "mundo" across all blogs
	 *     wp meilisearch search "mundo"
	 *
	 *     # Search in specific blog with limit
	 *     wp meilisearch search "inteligente" --blog_id=2 --limit=10
	 *
	 *     # Get results as JSON
	 *     wp meilisearch search "mundo" --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function search( $args, $assoc_args ) {
		$query   = $args[0] ?? '';
		$limit   = (int) ( $assoc_args['limit'] ?? 20 );
		$blog_id = isset( $assoc_args['blog_id'] ) ? (int) $assoc_args['blog_id'] : null;
		$format  = $assoc_args['format'] ?? 'table';

		if ( empty( $query ) ) {
			WP_CLI::error( 'Search query is required.' );
		}

		$searcher = new Meilisearch_Searcher( $this->client );

		try {
			if ( $blog_id ) {
				$results = $searcher->search_site( $query, $blog_id, [ 'limit' => $limit ] );
				$hits    = $results['hits'];
			} else {
				$results = $searcher->search_network( $query, [ 'limit' => $limit ] );
				$hits    = $results['hits'];
			}

			if ( empty( $hits ) ) {
				WP_CLI::warning( 'No results found.' );
				return;
			}

			$data = [];
			foreach ( $hits as $hit ) {
				$data[] = [
					'blog_id'     => $hit['blog_id'] ?? 'N/A',
					'post_id'     => $hit['id'] ?? 'N/A',
					'title'       => wp_trim_words( $hit['title'] ?? '', 10 ),
					'post_type'   => $hit['post_type'] ?? 'N/A',
					'post_status' => $hit['post_status'] ?? 'N/A',
					'permalink'   => $hit['permalink'] ?? 'N/A',
				];
			}

			WP_CLI\Utils\format_items( $format, $data, [ 'blog_id', 'post_id', 'title', 'post_type', 'post_status', 'permalink' ] );

			WP_CLI::success( sprintf( 'Found %d results for "%s"', count( $hits ), $query ) );
		} catch ( Exception $e ) {
			WP_CLI::error( 'Search failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Check Meilisearch server connection and health.
	 *
	 * ## EXAMPLES
	 *
	 *     # Check server health
	 *     wp meilisearch health
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function health( $args, $assoc_args ) {
		WP_CLI::log( 'Checking Meilisearch server health...' );

		try {
			$health = $this->client->get_client()->health();

			if ( isset( $health['status'] ) && $health['status'] === 'available' ) {
				WP_CLI::success( 'Meilisearch server is healthy and available!' );

				// Get version info.
				$version = $this->client->get_client()->version();
				WP_CLI::log( 'Version: ' . ( $version['pkgVersion'] ?? 'Unknown' ) );
			} else {
				WP_CLI::warning( 'Meilisearch server status: ' . ( $health['status'] ?? 'Unknown' ) );
			}
		} catch ( Exception $e ) {
			WP_CLI::error( 'Failed to connect to Meilisearch server: ' . $e->getMessage() );
		}
	}

	/**
	 * Get statistics about indexed documents.
	 *
	 * ## OPTIONS
	 *
	 * [--blog_id=<blog_id>]
	 * : Get stats for specific blog. If not provided, shows stats for all blogs.
	 *
	 * ## EXAMPLES
	 *
	 *     # Get stats for all blogs
	 *     wp meilisearch stats
	 *
	 *     # Get stats for specific blog
	 *     wp meilisearch stats --blog_id=2
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function stats( $args, $assoc_args ) {
		$blog_id = isset( $assoc_args['blog_id'] ) ? (int) $assoc_args['blog_id'] : null;

		try {
			if ( $blog_id ) {
				// Stats for specific blog.
				$index_name = $this->client->get_index_name( $blog_id );
				$stats      = $this->client->get_client()->index( $index_name )->stats();

				WP_CLI::log( "Statistics for blog $blog_id ($index_name):" );
				WP_CLI::log( '  Documents: ' . ( $stats['numberOfDocuments'] ?? 0 ) );
				WP_CLI::log( '  Indexing: ' . ( ( $stats['isIndexing'] ?? false ) ? 'Yes' : 'No' ) );

				if ( isset( $stats['fieldDistribution'] ) ) {
					WP_CLI::log( "\n  Field Distribution:" );
					foreach ( $stats['fieldDistribution'] as $field => $count ) {
						WP_CLI::log( "    $field: $count" );
					}
				}
			} else {
				// Stats for all indexes.
				$all_stats = $this->client->get_client()->stats();

				WP_CLI::log( 'Global Meilisearch Statistics:' );
				WP_CLI::log( '  Database Size: ' . size_format( $all_stats['databaseSize'] ?? 0 ) );
				WP_CLI::log( '  Total Indexes: ' . count( $all_stats['indexes'] ?? [] ) );

				if ( isset( $all_stats['indexes'] ) ) {
					WP_CLI::log( "\n  Indexes:" );
					foreach ( $all_stats['indexes'] as $index_name => $index_stats ) {
						WP_CLI::log( sprintf(
							'    %s: %d documents',
							$index_name,
							$index_stats['numberOfDocuments'] ?? 0
						) );
					}
				}
			}

			WP_CLI::success( 'Stats retrieved successfully.' );
		} catch ( Exception $e ) {
			WP_CLI::error( 'Failed to get stats: ' . $e->getMessage() );
		}
	}
}
