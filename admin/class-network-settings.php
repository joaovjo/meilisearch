<?php

declare(strict_types=1);

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
class Meilisearch_Network_Settings
{
	/**
	 * Option name for settings.
	 *
	 * @var string
	 */
	private string $option_name = 'meilisearch_settings';

	/**
	 * Initialize WordPress hooks.
	 */
	public function init_hooks(): void
	{
		add_action('network_admin_menu', [$this, 'add_network_menu']);
		add_action('network_admin_edit_meilisearch_settings', [$this, 'save_network_settings']);
	}

	/**
	 * Add network admin menu item.
	 */
	public function add_network_menu(): void
	{
		// Settings submenu (main menu is created by Dashboard class)
		add_submenu_page(
			'meilisearch-dashboard',
			__('Settings', 'meilisearch'),
			__('Settings', 'meilisearch'),
			'manage_network_options',
			'meilisearch-settings',
			[$this, 'render_settings_page'],
		);
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page(): void
	{
		if (!current_user_can('manage_network_options') && !is_super_admin()) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		$settings = get_site_option($this->option_name, []);
		$defaults = [
			'host' => 'http://localhost:7700',
			'master_key' => '',
			'enabled' => false,
			'index_format' => '{prefix}posts',
		];
		$settings = wp_parse_args($settings, $defaults);

		// Test connection if credentials provided.
		$connection_status = null;
		if (isset($settings['host']) && '' !== $settings['host']) {
			$client = new Meilisearch_Client($settings['host'], $settings['master_key']);
			$connection_status = $client->test_connection();
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e('Meilisearch Network Settings', 'meilisearch'); ?></h1>

			<?php



			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of success message.



			if (isset($_GET['updated'])): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Settings saved successfully.', 'meilisearch'); ?></p>
				</div>
			<?php endif; ?>

			<?php if (null !== $connection_status): ?>
				<div class="notice notice-<?php echo $connection_status ? 'success' : 'error'; ?>">
					<p>
						<strong><?php esc_html_e('Connection Status:', 'meilisearch'); ?></strong>
				<?php



				echo
	
				esc_html(
		
				$connection_status
			
				? __('Connected successfully!', 'meilisearch')
			
				: __('Connection failed. Please check your credentials.', 'meilisearch'),
	
				)

				;



				?>
						
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=meilisearch_settings')); ?>">
				<?php wp_nonce_field('meilisearch_settings', 'meilisearch_settings_nonce'); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="meilisearch_enabled">
								<?php esc_html_e('Enable Meilisearch', 'meilisearch'); ?>
							</label>
						</th>
						<td>
							<input type="checkbox"
								   id="meilisearch_enabled"
								   name="meilisearch_settings[enabled]"
								   value="1"
								   <?php checked($settings['enabled'], true); ?> />
							<p class="description">
								<?php esc_html_e('Enable Meilisearch search across the network.', 'meilisearch'); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="meilisearch_host">
								<?php esc_html_e('Meilisearch Host', 'meilisearch'); ?>
							</label>
						</th>
						<td>
							<input type="url"
								   id="meilisearch_host"
								   name="meilisearch_settings[host]"
								   value="<?php echo esc_attr($settings['host']); ?>"
								   class="regular-text"
								   required />
							<p class="description">
								<?php esc_html_e('URL of your Meilisearch server (e.g., http://localhost:7700)', 'meilisearch'); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="meilisearch_master_key">
								<?php esc_html_e('Master Key', 'meilisearch'); ?>
							</label>
						</th>
						<td>
							<input type="password"
								   id="meilisearch_master_key"
								   name="meilisearch_settings[master_key]"
								   value="<?php echo esc_attr($settings['master_key']); ?>"
								   class="regular-text"
								   autocomplete="off" />
							<p class="description">
								<?php esc_html_e('Your Meilisearch master key (leave empty if not using authentication).', 'meilisearch'); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="meilisearch_index_format">
								<?php esc_html_e('Index Name Format', 'meilisearch'); ?>
							</label>
						</th>
						<td>
							<input type="text"
								   id="meilisearch_index_format"
								   name="meilisearch_settings[index_format]"
								   value="<?php echo esc_attr($settings['index_format']); ?>"
								   class="regular-text"
								   placeholder="{prefix}posts" />
							<p class="description">
								<?php esc_html_e(
	
								'Index naming format. Use {prefix} for table prefix (wp_, wp_2_, wp_3_), {blog_id} for site ID, {site_id} for site ID. Default: {prefix}posts',
	

	
								'meilisearch',

								); ?><br>
								<strong><?php esc_html_e('Examples:', 'meilisearch'); ?></strong><br>
								• <code>{prefix}posts</code> → wp_posts, wp_2_posts, wp_3_posts<br>
								• <code>site_{blog_id}_posts</code> → site_1_posts, site_2_posts, site_3_posts<br>
								• <code>wp_{blog_id}_posts</code> → wp_1_posts, wp_2_posts, wp_3_posts (formato atual)
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e('Indexing Status', 'meilisearch'); ?></h2>
				<?php $this->render_indexing_status(); ?>

				<?php submit_button(__('Save Settings', 'meilisearch')); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render indexing status section.
	 */
	private function render_indexing_status(): void
	{
		$sites = get_sites(['number' => 9999]);
		$total_sites = count($sites);

		?>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e('Metric', 'meilisearch'); ?></th>
					<th><?php esc_html_e('Value', 'meilisearch'); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e('Total Sites in Network', 'meilisearch'); ?></td>
					<td><?php echo esc_html($total_sites); ?></td>
				</tr>
				<tr>
					<td colspan="2">
						<p class="description">
							<?php



							printf(
	
							/* translators: %s: WP-CLI command */
	

	
							esc_html__('Use WP-CLI to index all sites: %s', 'meilisearch'),
	

	
							'<code>wp meilisearch index --network</code>',

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
	public function save_network_settings(): void
	{
		check_admin_referer('meilisearch_settings', 'meilisearch_settings_nonce');

		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated via nonce above, sanitized individually below.
		$settings = isset($_POST['meilisearch_settings']) ? wp_unslash($_POST['meilisearch_settings']) : [];

		// Sanitize settings.
		$sanitized = [
			'host' => esc_url_raw($settings['host'] ?? ''),
			'master_key' => sanitize_text_field($settings['master_key'] ?? ''),
			'enabled' => isset($settings['enabled']) && '1' === $settings['enabled'],
			'index_format' => sanitize_text_field($settings['index_format'] ?? '{prefix}posts'),
		];

		update_site_option($this->option_name, $sanitized);

		wp_redirect(add_query_arg([
			'page' => 'meilisearch-settings',
			'updated' => 'true',
		], network_admin_url('admin.php')));
		exit();
	}
}
