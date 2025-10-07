<?php

declare(strict_types=1);

/**
 * Configurações de Rede do Meilisearch
 *
 * @package Meilisearch
 */

/**
 * Classe Meilisearch_Network_Settings
 *
 * Gerencia a página de configurações do admin de rede.
 */
class Meilisearch_Network_Settings
{
	/**
	 * Nome da opção para as configurações.
	 *
	 * @var string
	 */
	private string $option_name = 'meilisearch_settings';

	/**
	 * Inicializar hooks do WordPress.
	 */
	public function init_hooks(): void
	{
		add_action('network_admin_menu', [$this, 'add_network_menu']);
		add_action('network_admin_edit_meilisearch_settings', [$this, 'save_network_settings']);
	}

	/**
	 * Adicionar item de menu do admin de rede.
	 */
	public function add_network_menu(): void
	{
		// Submenu de configurações (o menu principal é criado pela classe Dashboard)
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
	 * Renderizar página de configurações.
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
			'post_types' => ['post', 'page'],
		];
		$settings = wp_parse_args($settings, $defaults);

		// Testar conexão se as credenciais foram fornecidas.
		$connection_status = null;
		if (isset($settings['host']) && '' !== $settings['host']) {
			$client = new Meilisearch_Client($settings['host'], $settings['master_key']);
			$connection_status = $client->test_connection();
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e('Meilisearch Network Settings', 'meilisearch'); ?></h1>

			<?php



			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Exibição somente leitura da mensagem de sucesso.



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
								• <code>wp_{blog_id}_posts</code> → wp_1_posts, wp_2_posts, wp_3_posts
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="meilisearch_post_types">
								<?php esc_html_e('Post Types to Index', 'meilisearch'); ?>
							</label>
						</th>
						<td>
							<?php
							$post_types = get_post_types(['public' => true], 'objects');
							$selected_post_types = isset($settings['post_types']) && is_array($settings['post_types']) 
								? $settings['post_types'] 
								: ['post', 'page'];
							?>
							<fieldset>
								<?php foreach ($post_types as $post_type): ?>
									<label style="display: block; margin-bottom: 5px;">
										<input type="checkbox"
											   name="meilisearch_settings[post_types][]"
											   value="<?php echo esc_attr($post_type->name); ?>"
											   <?php checked(in_array($post_type->name, $selected_post_types, true)); ?> />
										<?php echo esc_html($post_type->label); ?>
										<span style="color: #666; font-size: 0.9em;">(<?php echo esc_html($post_type->name); ?>)</span>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description">
								<?php esc_html_e('Select which post types should be indexed in Meilisearch. Only published content will be indexed.', 'meilisearch'); ?>
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
	 * Renderizar seção de status de indexação.
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
							'<code>wp meilisearch index --network</code>'
						);
						?>

							?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Salvar configurações de rede.
	 */
	public function save_network_settings(): void
	{
		check_admin_referer('meilisearch_settings', 'meilisearch_settings_nonce');

		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validado via nonce acima, sanitizado individualmente abaixo.
		$settings = isset($_POST['meilisearch_settings']) ? wp_unslash($_POST['meilisearch_settings']) : [];

		// Sanitizar configurações.
		$post_types = isset($settings['post_types']) && is_array($settings['post_types']) 
			? array_map('sanitize_key', $settings['post_types']) 
			: ['post', 'page'];
		$sanitized = [
			'host' => esc_url_raw($settings['host'] ?? ''),
			'master_key' => sanitize_text_field($settings['master_key'] ?? ''),
			'enabled' => isset($settings['enabled']) && '1' === $settings['enabled'],
			'index_format' => sanitize_text_field($settings['index_format'] ?? '{prefix}posts'),
			'post_types' => $post_types,
		];

		update_site_option($this->option_name, $sanitized);

		wp_redirect(add_query_arg([
			'page' => 'meilisearch-settings',
			'updated' => 'true',
		], network_admin_url('admin.php')));
		exit();
	}
}
