<?php
/**
 * Multi-Pattern Search Settings
 *
 * Allows administrators to select additional index patterns to include in search results
 * beyond the current network's configured pattern.
 *
 * @package    Meilisearch
 * @subpackage Admin
 * @since      1.0.0
 */

/**
 * Class Meilisearch_Multi_Pattern_Search
 *
 * Manages the configuration of multiple index patterns for cross-network search.
 * This allows searching across different WordPress networks that share the same
 * Meilisearch server.
 *
 * Features:
 * - Detect all available index patterns from Meilisearch
 * - Select additional patterns to include in searches
 * - Save configuration per network
 * - Real-time pattern detection (no cache)
 *
 * @since 1.0.0
 */
class Meilisearch_Multi_Pattern_Search {

	/**
	 * Meilisearch client instance
	 *
	 * @var Meilisearch_Client
	 */
	private $client;

	/**
	 * Option name for storing selected patterns
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'meilisearch_additional_patterns';

	/**
	 * Constructor
	 *
	 * Initializes the multi-pattern search settings and hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$settings = get_site_option( 'meilisearch_settings', array() );
		if ( ! empty( $settings['host'] ) ) {
			$this->client = new Meilisearch_Client( $settings['host'], $settings['master_key'] ?? '' );
		}
	}

	/**
	 * Initialize hooks
	 *
	 * Registers all necessary WordPress hooks for the multi-pattern search functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'network_admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_post_save_additional_patterns', array( $this, 'save_settings' ) );
	}

	/**
	 * Add menu page to WordPress admin
	 *
	 * Adds the Multi-Pattern Search page to both network admin and site admin menus.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_menu_page(): void {
		$page_title = __( 'Multi-Pattern Search', 'meilisearch' );
		$menu_title = __( 'Multi-Pattern Search', 'meilisearch' );
		$capability = is_multisite() ? 'manage_network_options' : 'manage_options';
		$menu_slug  = 'meilisearch-multi-pattern';
		$callback   = array( $this, 'render_page' );

		add_submenu_page(
			'meilisearch-dashboard',
			$page_title,
			$menu_title,
			$capability,
			$menu_slug,
			$callback
		);
	}

	/**
	 * Get all indexes from Meilisearch
	 *
	 * Fetches the complete list of indexes from the Meilisearch server.
	 * This data is not cached to ensure real-time accuracy.
	 *
	 * @since 1.0.0
	 * @return array Array of index objects with uid, primaryKey, and other metadata
	 */
	private function get_all_indexes(): array {
		if ( ! $this->client ) {
			return array();
		}

		try {
			$sdk_client = $this->client->get_client();
			$indexes_result = $sdk_client->getIndexes();
			
			$indexes = array();
			foreach ( $indexes_result->getResults() as $index ) {
				$indexes[] = array(
					'uid'        => $index->getUid(),
					'primaryKey' => $index->getPrimaryKey(),
					'createdAt'  => $index->getCreatedAt(),
					'updatedAt'  => $index->getUpdatedAt(),
				);
			}
			
			return $indexes;
		} catch ( Exception $e ) {
			error_log( 'Meilisearch Multi-Pattern: Error fetching indexes - ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Parse index name to extract pattern components
	 *
	 * Analyzes an index name to identify its pattern structure.
	 * Supports various WordPress multisite index naming patterns.
	 *
	 * Pattern examples:
	 * - wp_posts -> prefix: wp_, suffix: posts
	 * - setur_1_posts -> prefix: setur_, blog_id: 1, suffix: posts
	 * - mysite_posts -> prefix: mysite_, suffix: posts
	 *
	 * @since 1.0.0
	 * @param string $index_name The index name to parse.
	 * @return array{prefix: string, blog_id: string|null, suffix: string}|null Pattern components or null if no match
	 */
	private function parse_index_name( string $index_name ): ?array {
		// Pattern 1: prefix_blogid_suffix (e.g., setur_1_posts, setur_2_posts)
		if ( preg_match( '/^([a-zA-Z0-9_]+)_(\d+)_([a-zA-Z0-9_]+)$/', $index_name, $matches ) ) {
			return array(
				'prefix'  => $matches[1] . '_',
				'blog_id' => $matches[2],
				'suffix'  => $matches[3],
			);
		}

		// Pattern 2: prefix_suffix (e.g., wp_posts, mysite_posts)
		if ( preg_match( '/^([a-zA-Z0-9_]+)_([a-zA-Z0-9_]+)$/', $index_name, $matches ) ) {
			return array(
				'prefix'  => $matches[1] . '_',
				'blog_id' => null,
				'suffix'  => $matches[2],
			);
		}

		return null;
	}

	/**
	 * Analyze all index patterns
	 *
	 * Groups indexes by their detected patterns and extracts metadata for each pattern.
	 *
	 * @since 1.0.0
	 * @return array Array of patterns with metadata (format, count, network_url, indexes)
	 */
	private function analyze_index_patterns(): array {
		$indexes = $this->get_all_indexes();
		$patterns = array();

		foreach ( $indexes as $index ) {
			$parsed = $this->parse_index_name( $index['uid'] );

			if ( ! $parsed ) {
				continue;
			}

			// Create pattern key
			if ( $parsed['blog_id'] !== null ) {
				$pattern_key = $parsed['prefix'] . '{blog_id}_' . $parsed['suffix'];
			} else {
				$pattern_key = $parsed['prefix'] . $parsed['suffix'];
			}

			if ( ! isset( $patterns[ $pattern_key ] ) ) {
				$patterns[ $pattern_key ] = array(
					'format'      => $pattern_key,
					'prefix'      => $parsed['prefix'],
					'suffix'      => $parsed['suffix'],
					'has_blog_id' => $parsed['blog_id'] !== null,
					'count'       => 0,
					'indexes'     => array(),
				);
			}

			$patterns[ $pattern_key ]['count']++;
			$patterns[ $pattern_key ]['indexes'][] = $index['uid'];
		}

		// Get network URL for each pattern
		foreach ( $patterns as $key => $data ) {
			$patterns[ $key ]['network_url'] = $this->get_network_url_for_pattern( $data['indexes'] );
		}

		return $patterns;
	}

	/**
	 * Get network URL from index documents
	 *
	 * Extracts the network URL by searching documents in the pattern's indexes
	 * and parsing the permalink field.
	 *
	 * @since 1.0.0
	 * @param array $index_names Array of index names belonging to this pattern.
	 * @return string|null Network URL or null if not found
	 */
	private function get_network_url_for_pattern( array $index_names ): ?string {
		if ( ! $this->client ) {
			return null;
		}

		foreach ( $index_names as $index_name ) {
			try {
				$sdk_client = $this->client->get_client();
				$index = $sdk_client->index( $index_name );
				$results = $index->search( '', array( 'limit' => 1 ) );
				$hits = $results->getHits();

				if ( ! empty( $hits ) && isset( $hits[0]['permalink'] ) ) {
					$permalink = $hits[0]['permalink'];
					$parsed = parse_url( $permalink );

					if ( isset( $parsed['scheme'], $parsed['host'] ) ) {
						$url = $parsed['scheme'] . '://' . $parsed['host'];
						if ( isset( $parsed['port'] ) ) {
							$url .= ':' . $parsed['port'];
						}
						return $url;
					}
				}
			} catch ( Exception $e ) {
				continue;
			}
		}

		return null;
	}

	/**
	 * Get current network's pattern
	 *
	 * Determines the index pattern configured for the current WordPress network.
	 *
	 * @since 1.0.0
	 * @return string The current network's index pattern
	 */
	private function get_current_pattern(): string {
		$settings = get_site_option( 'meilisearch_settings', array() );
		$format = $settings['index_format'] ?? '{prefix}posts';

		// Get the actual prefix for the main site
		switch_to_blog( 1 );
		global $wpdb;
		$prefix = $wpdb->prefix;
		restore_current_blog();

		// Replace placeholders with actual values to get the real pattern
		$pattern = str_replace( '{prefix}', $prefix, $format );
		$pattern = str_replace( '{blog_id}', '', $pattern ); // Remove blog_id placeholder
		$pattern = str_replace( '{site_id}', '', $pattern ); // Remove site_id placeholder
		
		// Clean up any double underscores or trailing underscores
		$pattern = preg_replace( '/_+/', '_', $pattern );
		$pattern = rtrim( $pattern, '_' );

		return $pattern;
	}

	/**
	 * Get saved additional patterns
	 *
	 * Retrieves the list of additional patterns selected for cross-network search.
	 *
	 * @since 1.0.0
	 * @return array Array of selected pattern keys
	 */
	public function get_additional_patterns(): array {
		if ( is_multisite() ) {
			$patterns = get_site_option( self::OPTION_NAME, array() );
		} else {
			$patterns = get_option( self::OPTION_NAME, array() );
		}

		return is_array( $patterns ) ? $patterns : array();
	}

	/**
	 * Save settings
	 *
	 * Processes the form submission and saves selected additional patterns.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function save_settings(): void {
		// Check nonce
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'save_additional_patterns' ) ) {
			wp_die( __( 'Security check failed', 'meilisearch' ) );
		}

		// Check permissions
		$capability = is_multisite() ? 'manage_network_options' : 'manage_options';
		if ( ! current_user_can( $capability ) ) {
			wp_die( __( 'You do not have permission to perform this action', 'meilisearch' ) );
		}

		// Get selected patterns
		$selected_patterns = isset( $_POST['additional_patterns'] ) && is_array( $_POST['additional_patterns'] )
			? array_map( 'sanitize_text_field', $_POST['additional_patterns'] )
			: array();

		// Save to database
		if ( is_multisite() ) {
			update_site_option( self::OPTION_NAME, $selected_patterns );
		} else {
			update_option( self::OPTION_NAME, $selected_patterns );
		}

		// Redirect back with success message
		$redirect_url = add_query_arg(
			array(
				'page'    => 'meilisearch-multi-pattern',
				'updated' => 'true',
			),
			is_multisite() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render the settings page
	 *
	 * Displays the multi-pattern search configuration interface.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( __( 'Multi-Pattern Search', 'meilisearch' ) ); ?></h1>

			<?php if ( ! $this->client ) : ?>
				<div class="notice notice-error">
					<p>
						<?php
						esc_html_e( 'Meilisearch is not configured. Please configure it in the Settings page first.', 'meilisearch' );
						?>
					</p>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<?php
			$patterns = $this->analyze_index_patterns();
			$current_pattern = $this->get_current_pattern();
			$selected_patterns = $this->get_additional_patterns();
			$updated = isset( $_GET['updated'] ) && $_GET['updated'] === 'true';
			?>

			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved successfully!', 'meilisearch' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="card">
				<h2><?php esc_html_e( 'About Multi-Pattern Search', 'meilisearch' ); ?></h2>
				<p><?php esc_html_e( 'This feature allows you to search across multiple WordPress networks that share the same Meilisearch server. Select additional index patterns below to include their content in your search results.', 'meilisearch' ); ?></p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'save_additional_patterns' ); ?>
				<input type="hidden" name="action" value="save_additional_patterns">

				<h2><?php esc_html_e( 'Available Index Patterns', 'meilisearch' ); ?></h2>

				<?php if ( empty( $patterns ) ) : ?>
					<div class="notice notice-warning">
						<p><?php esc_html_e( 'No index patterns found in Meilisearch.', 'meilisearch' ); ?></p>
					</div>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th style="width: 50px;"><?php esc_html_e( 'Select', 'meilisearch' ); ?></th>
								<th><?php esc_html_e( 'Pattern', 'meilisearch' ); ?></th>
								<th><?php esc_html_e( 'Network URL', 'meilisearch' ); ?></th>
								<th><?php esc_html_e( 'Indexes', 'meilisearch' ); ?></th>
								<th><?php esc_html_e( 'Status', 'meilisearch' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $patterns as $pattern_key => $data ) : ?>
								<?php
								$is_current = ( $pattern_key === $current_pattern );
								$row_class = $is_current ? 'style="background-color: #e8f4f8;"' : '';
								?>
								<tr <?php echo $row_class; ?>>
									<td>
										<?php if ( $is_current ) : ?>
											<span style="color: #888;">—</span>
										<?php else : ?>
											<input 
												type="checkbox" 
												name="additional_patterns[]" 
												value="<?php echo esc_attr( $pattern_key ); ?>"
												<?php checked( in_array( $pattern_key, $selected_patterns, true ) ); ?>
											>
										<?php endif; ?>
									</td>
									<td>
										<strong><code><?php echo esc_html( $data['format'] ); ?></code></strong>
										<?php if ( $is_current ) : ?>
											<span class="dashicons dashicons-admin-site" style="color: #2271b1;"></span>
											<span style="color: #2271b1; font-weight: bold;">
												<?php esc_html_e( 'Current Network', 'meilisearch' ); ?>
											</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $data['network_url'] ) : ?>
											<a href="<?php echo esc_url( $data['network_url'] ); ?>" target="_blank">
												<?php echo esc_html( $data['network_url'] ); ?>
											</a>
										<?php else : ?>
											<span style="color: #999;">
												<?php esc_html_e( 'Unknown', 'meilisearch' ); ?>
											</span>
										<?php endif; ?>
									</td>
									<td>
										<strong><?php echo esc_html( $data['count'] ); ?></strong> 
										<?php 
										echo esc_html( 
											sprintf( 
												_n( 'index', 'indexes', $data['count'], 'meilisearch' ), 
												$data['count'] 
											) 
										); 
										?>
									</td>
									<td>
										<?php if ( $is_current ) : ?>
											<span style="color: #2271b1;">
												<strong><?php esc_html_e( 'Current', 'meilisearch' ); ?></strong>
											</span>
										<?php elseif ( in_array( $pattern_key, $selected_patterns, true ) ) : ?>
											<span style="color: #46b450;">
												<?php esc_html_e( 'Active', 'meilisearch' ); ?>
											</span>
										<?php else : ?>
											<span style="color: #999;">
												<?php esc_html_e( 'Inactive', 'meilisearch' ); ?>
											</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Save Settings', 'meilisearch' ); ?>
						</button>
					</p>
				<?php endif; ?>
			</form>

			<style>
				.card {
					padding: 15px;
					margin: 20px 0;
					background: #fff;
					border-left: 4px solid #2271b1;
				}
				.card h2 {
					margin-top: 0;
				}
			</style>
		</div>
		<?php
	}
}
