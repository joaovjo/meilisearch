<?php

declare(strict_types=1);

/**
 * Página de Funcionalidades Experimentais do Meilisearch
 *
 * @package Meilisearch
 */

/**
 * Classe Meilisearch_Experimental_Features
 *
 * Gerencia funcionalidades experimentais do Meilisearch.
 * @see https://www.meilisearch.com/docs/reference/api/experimental_features
 */
class Meilisearch_Experimental_Features
{
	/**
	 * Instância do cliente Meilisearch.
	 *
	 * @var Meilisearch_Client|null
	 */
	private null|Meilisearch_Client $client = null;

	/**
	 * Funcionalidades experimentais disponíveis.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $available_features = [
		'vectorStore' => [
			'name' => 'Vector Store',
			'description' => 'Enable vector search capabilities for semantic search and AI-powered features. Allows storing and searching vector embeddings.',
		],
		'metrics' => [
			'name' => 'Metrics',
			'description' => 'Enable Prometheus-compatible metrics endpoint for monitoring search performance and resource usage.',
		],
		'logsRoute' => [
			'name' => 'Logs Route',
			'description' => 'Enable API route to access Meilisearch logs directly through the API for debugging and monitoring.',
		],
		'editDocumentsByFunction' => [
			'name' => 'Edit Documents By Function',
			'description' => 'Enable document editing using custom functions for batch operations and transformations.',
		],
		'containsFilter' => [
			'name' => 'Contains Filter',
			'description' => 'Enable "contains" filter operator for partial string matching in search filters.',
		],
	];

	/**
	 * Inicializar hooks do WordPress.
	 */
	public function init_hooks(): void
	{
		add_action('network_admin_menu', [$this, 'add_network_menu']);
		add_action('network_admin_edit_meilisearch_experimental_features', [$this, 'save_experimental_features']);
	}

	/**
	 * Adicionar item de menu da administração de rede.
	 */
	public function add_network_menu(): void
	{
		add_submenu_page(
			'meilisearch-dashboard',
			__('Experimental Features', 'meilisearch'),
			__('Experimental', 'meilisearch'),
			'manage_network_options',
			'meilisearch-experimental',
			[$this, 'render_experimental_page'],
		);
	}

	/**
	 * Obter instância do cliente Meilisearch.
	 *
	 * @return Meilisearch_Client|null
	 */
	private function get_client(): null|Meilisearch_Client
	{
		if (null === $this->client) {
			$settings = get_site_option('meilisearch_settings', []);

			if (empty($settings['host'])) {
				return null;
			}

			$this->client = new Meilisearch_Client($settings['host'], $settings['master_key'] ?? '');
		}

		return $this->client;
	}

	/**
	 * Obter funcionalidades experimentais atuais do Meilisearch.
	 *
	 * @return array<string, bool>|null
	 */
	private function get_current_features(): ?array
	{
		$client = $this->get_client();
		if (null === $client) {
			return null;
		}

		return $client->get_experimental_features();
	}

	/**
	 * Atualizar funcionalidades experimentais no Meilisearch.
	 *
	 * @param array<string, bool> $features Funcionalidades para atualizar.
	 * @return bool True se atualizado com sucesso.
	 */
	private function update_features(array $features): bool
	{
		$client = $this->get_client();
		if (null === $client) {
			return false;
		}

		return $client->update_experimental_features($features);
	}

	/**
	 * Renderizar página de funcionalidades experimentais.
	 */
	public function render_experimental_page(): void
	{
		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		$current_features = $this->get_current_features();

		?>
		<div class="wrap">
			<h1><?php esc_html_e('Meilisearch Experimental Features', 'meilisearch'); ?></h1>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Exibição somente leitura da mensagem de sucesso.
			if (isset($_GET['updated']) && 'true' === $_GET['updated']): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Experimental features updated successfully.', 'meilisearch'); ?></p>
				</div>
			<?php endif; ?>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Exibição somente leitura da mensagem de erro.
			if (isset($_GET['error'])): ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e('Error updating experimental features. Please check your connection and try again.', 'meilisearch'); ?></p>
				</div>
			<?php endif; ?>

			<?php if (null === $current_features): ?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e('Error:', 'meilisearch'); ?></strong>
						<?php esc_html_e('Unable to connect to Meilisearch server. Please check your settings.', 'meilisearch'); ?>
					</p>
				</div>
			<?php else: ?>

				<div class="notice notice-warning" style="margin-top: 20px;">
					<p>
						<strong><?php esc_html_e('Warning:', 'meilisearch'); ?></strong>
						<?php esc_html_e('Experimental features are unstable and may change in future versions. Use them at your own risk in production environments.', 'meilisearch'); ?>
					</p>
				</div>

				<p class="description" style="margin-top: 20px;">
					<?php
					printf(
						/* translators: %s: link to Meilisearch documentation */
						esc_html__('Enable or disable experimental features in Meilisearch. For more information, visit the %s.', 'meilisearch'),
						'<a href="https://www.meilisearch.com/docs/reference/api/experimental_features" target="_blank">' . esc_html__('Meilisearch documentation', 'meilisearch') . '</a>'
					);
					?>
				</p>

				<form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=meilisearch_experimental_features')); ?>" style="margin-top: 30px;">
					<?php wp_nonce_field('meilisearch_experimental_features', 'meilisearch_experimental_nonce'); ?>

					<table class="form-table">
						<tbody>
							<?php foreach ($this->available_features as $feature_key => $feature_info): ?>
								<tr>
									<th scope="row">
										<label for="feature_<?php echo esc_attr($feature_key); ?>">
											<?php echo esc_html($feature_info['name']); ?>
										</label>
									</th>
									<td>
										<fieldset>
											<label>
												<input 
													type="checkbox" 
													name="experimental_features[<?php echo esc_attr($feature_key); ?>]" 
													id="feature_<?php echo esc_attr($feature_key); ?>"
													value="1"
													<?php checked(!empty($current_features[$feature_key]), true); ?>
												/>
												<?php esc_html_e('Enable', 'meilisearch'); ?>
											</label>
											<p class="description">
												<?php echo esc_html($feature_info['description']); ?>
											</p>
											<p class="description" style="margin-top: 5px;">
												<strong><?php esc_html_e('Current status:', 'meilisearch'); ?></strong>
												<?php if (!empty($current_features[$feature_key])): ?>
													<span style="color: #46b450;">✓ <?php esc_html_e('Enabled', 'meilisearch'); ?></span>
												<?php else: ?>
													<span style="color: #dc3232;">✗ <?php esc_html_e('Disabled', 'meilisearch'); ?></span>
												<?php endif; ?>
											</p>
										</fieldset>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<p class="submit">
						<?php submit_button(__('Save Experimental Features', 'meilisearch'), 'primary', 'submit', false); ?>
						<a href="<?php echo esc_url(add_query_arg('refresh', time())); ?>" class="button" style="margin-left: 10px;">
							<?php esc_html_e('Refresh Status', 'meilisearch'); ?>
						</a>
					</p>
				</form>

				<hr style="margin: 40px 0;">

				<h2><?php esc_html_e('Current Configuration', 'meilisearch'); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e('Feature', 'meilisearch'); ?></th>
							<th><?php esc_html_e('Status', 'meilisearch'); ?></th>
							<th><?php esc_html_e('API Key', 'meilisearch'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($this->available_features as $feature_key => $feature_info): ?>
							<tr>
								<td><strong><?php echo esc_html($feature_info['name']); ?></strong></td>
								<td>
									<?php if (!empty($current_features[$feature_key])): ?>
										<span style="display: inline-block; padding: 3px 8px; background: #46b450; color: white; border-radius: 3px; font-size: 11px; font-weight: bold;">
											<?php esc_html_e('ENABLED', 'meilisearch'); ?>
										</span>
									<?php else: ?>
										<span style="display: inline-block; padding: 3px 8px; background: #dc3232; color: white; border-radius: 3px; font-size: 11px; font-weight: bold;">
											<?php esc_html_e('DISABLED', 'meilisearch'); ?>
										</span>
									<?php endif; ?>
								</td>
								<td><code><?php echo esc_html($feature_key); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p style="margin-top: 20px; color: #666;">
					<em>
						<?php
						printf(
							/* translators: %s: current time */
							esc_html__('Last updated: %s', 'meilisearch'),
							esc_html(current_time('Y-m-d H:i:s'))
						);
						?>
					</em>
				</p>

			<?php endif; ?>
		</div>

		<style>
			.form-table th {
				width: 250px;
			}
			.form-table td fieldset {
				max-width: 600px;
			}
			.widefat th,
			.widefat td {
				padding: 12px 10px;
			}
		</style>
		<?php
	}

	/**
	 * Salvar funcionalidades experimentais.
	 */
	public function save_experimental_features(): void
	{
		check_admin_referer('meilisearch_experimental_features', 'meilisearch_experimental_nonce');

		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validado via nonce acima, sanitizado individualmente abaixo.
		$features_input = isset($_POST['experimental_features']) && is_array($_POST['experimental_features'])
			? wp_unslash($_POST['experimental_features'])
			: [];

		// Construir array de funcionalidades com valores booleanos
		$features = [];
		foreach ($this->available_features as $feature_key => $feature_info) {
			$features[$feature_key] = isset($features_input[$feature_key]) && $features_input[$feature_key] === '1';
		}

		// Atualizar no Meilisearch
		$success = $this->update_features($features);

		// Redirecionar com mensagem
		$redirect_url = add_query_arg(
			[
				'page' => 'meilisearch-experimental',
				'updated' => $success ? 'true' : 'false',
			],
			network_admin_url('admin.php')
		);

		if (!$success) {
			$redirect_url = add_query_arg('error', 'true', $redirect_url);
		}

		wp_redirect($redirect_url);
		exit();
	}
}
