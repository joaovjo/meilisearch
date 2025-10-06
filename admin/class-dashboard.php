<?php

declare(strict_types=1);

/**
 * Meilisearch Dashboard
 *
 * @package Meilisearch
 */

/**
 * Class Meilisearch_Dashboard
 *
 * Handles dashboard/overview page with system information and statistics.
 */
class Meilisearch_Dashboard
{
	/**
	 * Meilisearch client instance.
	 *
	 * @var Meilisearch_Client|null
	 */
	private null|Meilisearch_Client $client = null;

	/**
	 * Initialize hooks.
	 */
	public function init_hooks(): void
	{
		if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
			error_log('Meilisearch Dashboard: Registering hooks');
		}
		add_action('network_admin_menu', [$this, 'add_dashboard_menu']);
		add_action('admin_action_meilisearch_reindex', [$this, 'handle_reindex']);
	}

	/**
	 * Add main menu and dashboard page.
	 */
	public function add_dashboard_menu(): void
	{
		// Main menu with dashboard as the default page
		add_menu_page(
			__('Meilisearch Search', 'meilisearch'),
			__('Meilisearch', 'meilisearch'),
			'manage_network_options',
			'meilisearch-dashboard',
			[$this, 'render_dashboard'],
			'dashicons-search',
			30,
		);

		// Add dashboard submenu (will rename the first item)
		add_submenu_page(
			'meilisearch-dashboard',
			__('Dashboard', 'meilisearch'),
			__('Dashboard', 'meilisearch'),
			'manage_network_options',
			'meilisearch-dashboard',
			[$this, 'render_dashboard'],
		);
	}

	/**
	 * Get Meilisearch client instance.
	 *
	 * @return Meilisearch_Client|null
	 */
	private function get_client(): null|Meilisearch_Client
	{
		if (null !== $this->client) {
			return $this->client;
		}

		$settings = get_site_option('meilisearch_settings', []);

		if (isset($settings['host']) && '' !== $settings['host']) {
			$this->client = new Meilisearch_Client($settings['host'], $settings['master_key'] ?? '');
			return $this->client;
		}

		return null;
	}

	/**
	 * Get network statistics.
	 *
	 * @return array<string, mixed>
	 */
	private function get_network_stats(): array
	{
		$sites = get_sites(['number' => 9999]);
		$total_sites = count($sites);
		$total_posts = 0;

		foreach ($sites as $site) {
			switch_to_blog($site->blog_id);

			// Count all public post types (same as indexer uses)
			$post_types = get_post_types(['public' => true], 'names');
			foreach ($post_types as $post_type) {
				$count = wp_count_posts($post_type);
				$total_posts += $count->publish ?? 0;
			}

			restore_current_blog();
		}

		return [
			'total_sites' => $total_sites,
			'total_posts' => $total_posts,
		];
	}

	/**
	 * Get Meilisearch server info.
	 *
	 * @return array<string, mixed>
	 */
	private function get_meilisearch_info(): array
	{
		$info = [
			'status' => 'disconnected',
			'version' => 'N/A',
			'host' => 'N/A',
		];

		$settings = get_site_option('meilisearch_settings', []);
		$info['host'] = $settings['host'] ?? 'Not configured';

		$client = $this->get_client();
		if (null === $client) {
			return $info;
		}

		try {
			$health = $client->get_client()->health();
			$info['status'] = $health['status'] ?? 'unknown';

			$version = $client->get_client()->version();
			$info['version'] = $version['pkgVersion'] ?? 'Unknown';
		} catch (Exception $e) {
			$info['status'] = 'error';
		}

		return $info;
	}

	/**
	 * Handle network reindex action.
	 */
	public function handle_reindex(): void
	{
		// Ensure we're in network admin context
		if (!is_network_admin()) {
			wp_die(esc_html__('This action can only be performed in network admin.', 'meilisearch'));
		}

		if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
			error_log('Meilisearch Dashboard: handle_reindex() called');
		}

		// Verify nonce
		try {
			check_admin_referer('meilisearch_reindex');
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				error_log('Meilisearch Dashboard: Nonce verified');
			}
		} catch (\Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				error_log('Meilisearch Dashboard: Nonce verification failed - ' . $e->getMessage());
			}
			throw $e;
		}

		// Check permissions
		if (!current_user_can('manage_network_options')) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				error_log('Meilisearch Dashboard: Permission denied');
			}
			wp_die(esc_html__('You do not have permission to perform this action.', 'meilisearch'));
		}

		if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
			error_log('Meilisearch Dashboard: Getting client');
		}

		// Get Meilisearch client
		$client = $this->get_client();
		if (null === $client) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				error_log('Meilisearch Dashboard: Client is null - not configured');
			}
			wp_die(
				esc_html__('Meilisearch is not configured. Please configure the settings first.', 'meilisearch'),
				esc_html__('Configuration Required', 'meilisearch'),
				['back_link' => true],
			);
		}

		if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
			error_log('Meilisearch Dashboard: Creating indexer');
		}

		// Get indexer with client
		$indexer = new Meilisearch_Indexer($client);

		if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
			error_log('Meilisearch Dashboard: Starting bulk reindex');
		}

		// Use bulk_index_network method which handles all sites
		try {
			$results = $indexer->bulk_index_network(function ($blog_id, $site_result) {
				if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
					error_log(sprintf(
						'Meilisearch Dashboard: Blog %d - Indexed %d of %d posts',
						$blog_id,
						$site_result['indexed'],
						$site_result['total'],
					));
				}
			});

			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				error_log(sprintf(
					'Meilisearch Dashboard: Reindex complete - %d posts indexed across %d sites',
					$results['indexed_posts'],
					$results['total_sites'],
				));
			}
		} catch (\Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				error_log('Meilisearch Dashboard: Reindex error - ' . $e->getMessage());
			}
			wp_die(
				esc_html(sprintf(__('Error during reindexing: %s', 'meilisearch'), $e->getMessage())),
				esc_html__('Reindex Error', 'meilisearch'),
				['back_link' => true],
			);
		}

		if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
			error_log('Meilisearch Dashboard: Reindexing complete, redirecting...');
		}

		// Redirect back to dashboard with success message
		wp_redirect(add_query_arg([
			'page' => 'meilisearch-dashboard',
			'reindexed' => '1',
		], network_admin_url('admin.php')));
		exit();
	}

	/**
	 * Get system information.
	 *
	 * @return array<string, string>
	 */
	private function get_system_info(): array
	{
		$composer_lock = MEILISEARCH_PLUGIN_DIR . 'composer.lock';
		$dependencies = [];

		if (file_exists($composer_lock)) {
			$contents = file_get_contents($composer_lock);
			if ($contents) {
				$lock_data = json_decode($contents, true);
				if (is_array($lock_data) && isset($lock_data['packages']) && is_array($lock_data['packages'])) {
					foreach ($lock_data['packages'] as $package) {
						if (is_array($package) && isset($package['name'], $package['version'])) {
							$dependencies[$package['name']] = $package['version'];
						}
					}
				}
			}
		}

		return [
			'plugin_version' => MEILISEARCH_VERSION,
			'wordpress_version' => get_bloginfo('version'),
			'php_version' => PHP_VERSION,
			'meilisearch_php_sdk' => $dependencies['meilisearch/meilisearch-php'] ?? 'Unknown',
			'guzzle' => $dependencies['guzzlehttp/guzzle'] ?? 'Unknown',
			'react_fiber' => class_exists('Fiber') ? 'Available' : 'Not Available',
		];
	}

	/**
	 * Render dashboard page.
	 */
	public function render_dashboard(): void
	{
		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		// Check if reindex was triggered and show notice.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of success message.
		if (isset($_GET['reindexed']) && '1' === $_GET['reindexed']) { ?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php esc_html_e('Network reindexing started successfully!', 'meilisearch'); ?></strong></p>
				<p><?php esc_html_e(
					'The reindexing process is running in the background. This may take several minutes depending on the number of posts.',

					'meilisearch',
				); ?>
				</p>
			</div>
		<?php }

		try {
			$network_stats = $this->get_network_stats();
			$meilisearch_info = $this->get_meilisearch_info();
			$system_info = $this->get_system_info();
		} catch (\Exception $e) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e('Meilisearch Dashboard', 'meilisearch'); ?></h1>
				<div class="notice notice-error">
					<p><strong>Error loading dashboard:</strong> <?php echo esc_html($e->getMessage()); ?></p>
					<p><strong>File:</strong> <?php echo esc_html($e->getFile()); ?> (line <?php echo
						esc_html((string) $e->getLine())
					; ?>)</p>
				</div>
			</div>
			<?php

			return;
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e('Meilisearch Dashboard', 'meilisearch'); ?></h1>

			<div class="meilisearch-dashboard" style="margin-top: 20px;">

				<!-- Network Statistics -->
				<div class="postbox" style="margin-bottom: 20px;">
					<div class="inside" style="padding: 12px;">
						<h2 style="margin-top: 0;"><?php esc_html_e('Network Statistics', 'meilisearch'); ?></h2>
						<table class="widefat striped">
							<tbody>
								<tr>
									<td style="width: 40%;"><strong><?php esc_html_e('Total Sites', 'meilisearch'); ?></strong>
									</td>
									<td><?php echo esc_html((string) $network_stats['total_sites']); ?></td>
								</tr>
								<tr>
									<td><strong><?php esc_html_e('Total Published Posts', 'meilisearch'); ?></strong></td>
									<td><?php echo esc_html((string) $network_stats['total_posts']); ?></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Meilisearch Status -->
				<div class="postbox" style="margin-bottom: 20px;">
					<div class="inside" style="padding: 12px;">
						<h2 style="margin-top: 0;"><?php esc_html_e('Meilisearch Server', 'meilisearch'); ?></h2>
						<table class="widefat striped">
							<tbody>
								<tr>
									<td style="width: 40%;"><strong><?php esc_html_e('Host', 'meilisearch'); ?></strong></td>
									<td><?php echo esc_html($meilisearch_info['host']); ?></td>
								</tr>
								<tr>
									<td><strong><?php esc_html_e('Status', 'meilisearch'); ?></strong></td>
									<td>
										<?php

										$status_class = 'available' === $meilisearch_info['status'] ? 'green' : 'red';

										$status_text = 'available' === $meilisearch_info['status']
											? __('Connected', 'meilisearch')
											: __('Disconnected', 'meilisearch');

										?>
										<span style="color: <?php echo esc_attr($status_class); ?>; font-weight: bold;">
											● <?php echo esc_html($status_text); ?>
										</span>
									</td>
								</tr>
								<tr>
									<td><strong><?php esc_html_e('Meilisearch Version', 'meilisearch'); ?></strong></td>
									<td><?php echo esc_html($meilisearch_info['version']); ?></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<!-- System Information -->
				<div class="postbox" style="margin-bottom: 20px;">
					<div class="inside" style="padding: 12px;">
						<h2 style="margin-top: 0;"><?php esc_html_e('System Information', 'meilisearch'); ?></h2>
						<table class="widefat striped">
							<tbody>
								<tr>
									<td style="width: 40%;">
										<strong><?php esc_html_e('Plugin Version', 'meilisearch'); ?></strong>
									</td>
									<td><?php echo esc_html($system_info['plugin_version']); ?></td>
								</tr>
								<tr>
									<td><strong><?php esc_html_e('WordPress Version', 'meilisearch'); ?></strong></td>
									<td><?php echo esc_html($system_info['wordpress_version']); ?></td>
								</tr>
								<tr>
									<td><strong><?php esc_html_e('PHP Version', 'meilisearch'); ?></strong></td>
									<td><?php echo esc_html($system_info['php_version']); ?></td>
								</tr>
								<tr>
									<td><strong><?php esc_html_e('Meilisearch PHP SDK', 'meilisearch'); ?></strong></td>
									<td><?php echo esc_html($system_info['meilisearch_php_sdk']); ?></td>
								</tr>
								<tr>
									<td><strong><?php esc_html_e('Guzzle HTTP', 'meilisearch'); ?></strong></td>
									<td><?php echo esc_html($system_info['guzzle']); ?></td>
								</tr>
								<tr>
									<td><strong><?php esc_html_e('PHP Fiber Support', 'meilisearch'); ?></strong></td>
									<td>
										<?php

										$fiber_available = 'Available' === $system_info['react_fiber'];

										$fiber_color = $fiber_available ? 'green' : 'orange';

										?>
										<span style="color: <?php echo esc_attr($fiber_color); ?>; font-weight: bold;">
											<?php echo esc_html($system_info['react_fiber']); ?>
										</span>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Quick Actions -->
				<div class="postbox">
					<div class="inside" style="padding: 12px;">
						<h2 style="margin-top: 0;"><?php esc_html_e('Quick Actions', 'meilisearch'); ?></h2>
						<p>
						<a href="<?php echo esc_url(network_admin_url('admin.php?page=meilisearch-settings')); ?>"
							class="button button-primary">
							<?php esc_html_e('Configure Settings', 'meilisearch'); ?>
						</a>
						<a href="<?php echo
							esc_url(wp_nonce_url(network_admin_url('admin.php?action=meilisearch_reindex'), 'meilisearch_reindex'))
						; ?>" class="button button-secondary" onclick="return confirm('<?php echo
							esc_js(__('Are you sure you want to reindex all sites? This may take several minutes.', 'meilisearch'))
						; ?>');">
							<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
							<?php esc_html_e('Reindex Network', 'meilisearch'); ?>
							</a>
						</p>
						<p style="margin-top: 15px;">
							<strong><?php esc_html_e('WP-CLI Commands:', 'meilisearch'); ?></strong><br>
							<code>wp meilisearch reindex</code> - <?php esc_html_e('Reindex all sites', 'meilisearch'); ?><br>
							<code>wp meilisearch health</code> - <?php esc_html_e('Check server health', 'meilisearch'); ?><br>
							<code>wp meilisearch stats</code> - <?php esc_html_e('View indexing statistics', 'meilisearch'); ?>
						</p>
					</div>
				</div>

			</div>
		</div>
		<?php
	}
}
