<?php
/**
 * Meilisearch Network Settings
 *
 * @package Meilisearch
 */

/**
 * Class Meilisearch_Network_Settings
 *
 * Handles network admin settings page.
 */
class Meilisearch_Network_Settings {

	/**
	 * Option name for settings.
	 *
	 * @var string
	 */
	private string $option_name = 'meilisearch_settings';

	/**
	 * Initialize WordPress hooks.
	 */
	public function init_hooks(): void {
		add_action( 'network_admin_menu', [ $this, 'add_network_menu' ] );
		add_action( 'network_admin_edit_meilisearch_settings', [ $this, 'save_network_settings' ] );
	}

	/**
	 * Add network admin menu item.
	 */
	public function add_network_menu(): void {
		add_submenu_page(
			'settings.php',
			__( 'Meilisearch Settings', 'meilisearch' ),
			__( 'Meilisearch', 'meilisearch' ),
			'manage_network_options',
			'meilisearch-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'meilisearch' ) );
		}

		$settings = get_site_option( $this->option_name, [] );
		$defaults = [
			'host'       => 'http://localhost:7700',
			'master_key' => '',
			'enabled'    => false,
		];
		$settings = wp_parse_args( $settings, $defaults );

		// Test connection if credentials provided.
		$connection_status = null;
		if ( ! empty( $settings['host'] ) ) {
			$client = new Meilisearch_Client( $settings['host'], $settings['master_key'] );
			$connection_status = $client->test_connection();
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Meilisearch Network Settings', 'meilisearch' ); ?></h1>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved successfully.', 'meilisearch' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( null !== $connection_status ) : ?>
				<div class="notice notice-<?php echo $connection_status ? 'success' : 'error'; ?>">
					<p>
						<strong><?php esc_html_e( 'Connection Status:', 'meilisearch' ); ?></strong>
						<?php
						echo $connection_status
							? esc_html__( 'Connected successfully!', 'meilisearch' )
							: esc_html__( 'Connection failed. Please check your credentials.', 'meilisearch' );
						?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=meilisearch_settings' ) ); ?>">
				<?php wp_nonce_field( 'meilisearch_settings', 'meilisearch_settings_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="meilisearch_enabled">
								<?php esc_html_e( 'Enable Meilisearch', 'meilisearch' ); ?>
							</label>
						</th>
						<td>
							<input type="checkbox"
								   id="meilisearch_enabled"
								   name="meilisearch_settings[enabled]"
								   value="1"
								   <?php checked( $settings['enabled'], true ); ?> />
							<p class="description">
								<?php esc_html_e( 'Enable Meilisearch search across the network.', 'meilisearch' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="meilisearch_host">
								<?php esc_html_e( 'Meilisearch Host', 'meilisearch' ); ?>
							</label>
						</th>
						<td>
							<input type="url"
								   id="meilisearch_host"
								   name="meilisearch_settings[host]"
								   value="<?php echo esc_attr( $settings['host'] ); ?>"
								   class="regular-text"
								   required />
							<p class="description">
								<?php esc_html_e( 'URL of your Meilisearch server (e.g., http://localhost:7700)', 'meilisearch' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="meilisearch_master_key">
								<?php esc_html_e( 'Master Key', 'meilisearch' ); ?>
							</label>
						</th>
						<td>
							<input type="password"
								   id="meilisearch_master_key"
								   name="meilisearch_settings[master_key]"
								   value="<?php echo esc_attr( $settings['master_key'] ); ?>"
								   class="regular-text"
								   autocomplete="off" />
							<p class="description">
								<?php esc_html_e( 'Your Meilisearch master key (leave empty if not using authentication).', 'meilisearch' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Indexing Status', 'meilisearch' ); ?></h2>
				<?php $this->render_indexing_status(); ?>

				<?php submit_button( __( 'Save Settings', 'meilisearch' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render indexing status section.
	 */
	private function render_indexing_status(): void {
		$sites       = get_sites( [ 'number' => 9999 ] );
		$total_sites = count( $sites );

		?>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Metric', 'meilisearch' ); ?></th>
					<th><?php esc_html_e( 'Value', 'meilisearch' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Total Sites in Network', 'meilisearch' ); ?></td>
					<td><?php echo esc_html( $total_sites ); ?></td>
				</tr>
				<tr>
					<td colspan="2">
						<p class="description">
							<?php
							printf(
								/* translators: %s: WP-CLI command */
								esc_html__( 'Use WP-CLI to index all sites: %s', 'meilisearch' ),
								'<code>wp meilisearch index --network</code>'
							);
							?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Save network settings.
	 */
	public function save_network_settings(): void {
		check_admin_referer( 'meilisearch_settings', 'meilisearch_settings_nonce' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'meilisearch' ) );
		}

		$settings = isset( $_POST['meilisearch_settings'] ) ? $_POST['meilisearch_settings'] : [];

		// Sanitize settings.
		$sanitized = [
			'host'       => esc_url_raw( $settings['host'] ?? '' ),
			'master_key' => sanitize_text_field( $settings['master_key'] ?? '' ),
			'enabled'    => isset( $settings['enabled'] ) && '1' === $settings['enabled'],
		];

		update_site_option( $this->option_name, $sanitized );

		wp_redirect(
			add_query_arg(
				[
					'page'    => 'meilisearch-settings',
					'updated' => 'true',
				],
				network_admin_url( 'settings.php' )
			)
		);
		exit;
	}
}
