<?php

declare(strict_types=1);

/**
 * Página de Gerenciamento de Chat Workspaces do Meilisearch
 *
 * @package Meilisearch
 */

/**
 * Classe Meilisearch_Chat_Workspaces
 *
 * Gerencia workspaces de chat para recursos de IA conversacional.
 */
class Meilisearch_Chat_Workspaces
{
	/**
	 * Instância do cliente Meilisearch.
	 *
	 * @var Meilisearch_Client|null
	 */
	private null|Meilisearch_Client $client = null;

	/**
	 * Providers de LLM disponíveis.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $providers = [];

	/**
	 * Obter providers disponíveis (com lazy loading das traduções).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_providers(): array
	{
		if (empty($this->providers)) {
			$this->providers = [
				'openAi' => [
					'name' => __('OpenAI', 'meilisearch'),
					'requires_api_key' => true,
					'requires_base_url' => false,
					'default_base_url' => 'https://api.openai.com/v1',
					'fields' => ['apiKey', 'baseUrl'],
				],
				'azureOpenAi' => [
					'name' => __('Azure OpenAI', 'meilisearch'),
					'requires_api_key' => true,
					'requires_base_url' => true,
					'fields' => ['orgId', 'apiVersion', 'deploymentId', 'apiKey', 'baseUrl'],
				],
				'gemini' => [
					'name' => __('Google Gemini', 'meilisearch'),
					'requires_api_key' => true,
					'requires_base_url' => false,
					'fields' => ['apiKey'],
				],
				'mistral' => [
					'name' => __('Mistral AI', 'meilisearch'),
					'requires_api_key' => true,
					'requires_base_url' => false,
					'fields' => ['apiKey'],
				],
				'vLlm' => [
					'name' => __('vLLM (Local)', 'meilisearch'),
					'requires_api_key' => false,
					'requires_base_url' => true,
					'fields' => ['baseUrl'],
				],
			];
		}
		
		return $this->providers;
	}

	/**
	 * Inicializar hooks do WordPress.
	 */
	public function init_hooks(): void
	{
		add_action('network_admin_menu', [$this, 'add_network_menu']);
		add_action('network_admin_edit_meilisearch_save_chat_workspace', [$this, 'save_workspace']);
		add_action('network_admin_edit_meilisearch_delete_chat_workspace', [$this, 'delete_workspace']);
		add_action('network_admin_edit_meilisearch_test_chat_workspace', [$this, 'test_workspace']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
	}

	/**
	 * Adicionar item de menu da administração de rede.
	 */
	public function add_network_menu(): void
	{
		add_submenu_page(
			'meilisearch-dashboard',
			__('Chat Workspaces', 'meilisearch'),
			__('Chat Workspaces', 'meilisearch'),
			'manage_network_options',
			'meilisearch-chat-workspaces',
			[$this, 'render_page'],
		);
	}

	/**
	 * Enfileirar scripts e estilos.
	 *
	 * @param string $hook Hook da página.
	 */
	public function enqueue_scripts(string $hook): void
	{
		if ('admin_page_meilisearch-chat-workspaces' !== $hook) {
			return;
		}

		wp_enqueue_script(
			'meilisearch-chat-workspaces',
			plugins_url('assets/js/chat-workspaces.js', dirname(__FILE__)),
			['jquery'],
			'1.0.0',
			true
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
	 * Verificar se chat completions está habilitado.
	 *
	 * @return bool
	 */
	private function is_chat_completions_enabled(): bool
	{
		$client = $this->get_client();
		if (null === $client) {
			return false;
		}

		$features = $client->get_experimental_features();
		return !empty($features['chatCompletions']);
	}

	/**
	 * Obter lista de workspaces.
	 *
	 * @return array|null
	 */
	private function get_workspaces(): ?array
	{
		$client = $this->get_client();
		if (null === $client) {
			return null;
		}

		try {
			$result = $client->get_client()->getChatWorkspaces();
			return $result->getResults();
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log('Meilisearch get chat workspaces error: ' . $e->getMessage());
			}
			return null;
		}
	}

	/**
	 * Obter configurações de um workspace.
	 *
	 * @param string $workspace_uid UID do workspace.
	 * @return array|null
	 */
	private function get_workspace_settings(string $workspace_uid): ?array
	{
		$client = $this->get_client();
		if (null === $client) {
			return null;
		}

		try {
			$workspace = $client->get_client()->chatWorkspace($workspace_uid);
			return $workspace->getSettings();
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log('Meilisearch get workspace settings error: ' . $e->getMessage());
			}
			return null;
		}
	}

	/**
	 * Renderizar página de chat workspaces.
	 */
	public function render_page(): void
	{
		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		$client = $this->get_client();

		?>
		<div class="wrap">
			<h1><?php esc_html_e('Meilisearch Chat Workspaces', 'meilisearch'); ?></h1>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if (isset($_GET['updated']) && 'true' === $_GET['updated']): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Chat workspace saved successfully.', 'meilisearch'); ?></p>
				</div>
			<?php endif; ?>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if (isset($_GET['deleted']) && 'true' === $_GET['deleted']): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Chat workspace deleted successfully.', 'meilisearch'); ?></p>
				</div>
			<?php endif; ?>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if (isset($_GET['error'])): ?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['error']))); ?></p>
				</div>
			<?php endif; ?>

			<?php if (null === $client): ?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e('Error:', 'meilisearch'); ?></strong>
						<?php esc_html_e('Unable to connect to Meilisearch server. Please check your settings.', 'meilisearch'); ?>
					</p>
				</div>
			<?php elseif (!$this->is_chat_completions_enabled()): ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e('Chat Completions Not Enabled', 'meilisearch'); ?></strong><br>
						<?php
						printf(
							/* translators: %s: link to experimental features page */
							esc_html__('Enable the Chat Completions experimental feature in %s to use this functionality.', 'meilisearch'),
							'<a href="' . esc_url(network_admin_url('admin.php?page=meilisearch-experimental')) . '">' . esc_html__('Experimental Features', 'meilisearch') . '</a>'
						);
						?>
					</p>
				</div>
			<?php else: ?>

				<div class="notice notice-info" style="margin-top: 20px;">
					<p>
						<strong><?php esc_html_e('What are Chat Workspaces?', 'meilisearch'); ?></strong><br>
						<?php esc_html_e('Chat workspaces allow you to configure AI-powered conversational search using various LLM providers like OpenAI, Azure OpenAI, Google Gemini, Mistral AI, or local vLLM instances.', 'meilisearch'); ?>
					</p>
				</div>

				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$workspace_uid = isset($_GET['workspace']) ? sanitize_text_field(wp_unslash($_GET['workspace'])) : '';

				if ('edit' === $action && !empty($workspace_uid)) {
					$this->render_edit_form($workspace_uid);
				} elseif ('new' === $action) {
					$this->render_new_form();
				} else {
					$this->render_workspaces_list();
					$this->render_add_new_button();
				}
				?>

			<?php endif; ?>
		</div>

		<style>
			.workspace-table {
				margin-top: 20px;
			}
			.workspace-table th {
				font-weight: 600;
			}
			.provider-badge {
				display: inline-block;
				padding: 3px 8px;
				background: #2271b1;
				color: white;
				border-radius: 3px;
				font-size: 11px;
				font-weight: bold;
			}
			.status-active {
				color: #46b450;
			}
			.status-inactive {
				color: #dc3232;
			}
			.add-new-workspace {
				margin-top: 20px;
			}
			.provider-fields {
				display: none;
			}
			.provider-fields.active {
				display: table-row;
			}
		</style>
		<?php
	}

	/**
	 * Renderizar lista de workspaces.
	 */
	private function render_workspaces_list(): void
	{
		$workspaces = $this->get_workspaces();

		if (null === $workspaces) {
			echo '<div class="notice notice-error"><p>' . esc_html__('Error loading chat workspaces.', 'meilisearch') . '</p></div>';
			return;
		}

		if (empty($workspaces)) {
			echo '<div class="notice notice-info"><p>' . esc_html__('No chat workspaces found. Create your first workspace to get started.', 'meilisearch') . '</p></div>';
			return;
		}

		?>
		<h2><?php esc_html_e('Existing Workspaces', 'meilisearch'); ?></h2>
		<table class="wp-list-table widefat fixed striped workspace-table">
			<thead>
				<tr>
					<th><?php esc_html_e('Workspace UID', 'meilisearch'); ?></th>
					<th><?php esc_html_e('Provider', 'meilisearch'); ?></th>
					<th><?php esc_html_e('System Prompt', 'meilisearch'); ?></th>
					<th><?php esc_html_e('Actions', 'meilisearch'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($workspaces as $workspace): ?>
				<tr>
					<td><code><?php echo esc_html($workspace['uid']); ?></code></td>
					<td><?php $this->render_workspace_provider($workspace['uid']); ?></td>
					<td><?php $this->render_workspace_prompt($workspace['uid']); ?></td>
					<td>
						<a href="<?php echo esc_url(add_query_arg(['page' => 'meilisearch-chat-workspaces', 'action' => 'edit', 'workspace' => $workspace['uid']], network_admin_url('admin.php'))); ?>">
							<?php esc_html_e('Edit', 'meilisearch'); ?>
						</a>
						|
						<a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['page' => 'meilisearch-chat-workspaces', 'action' => 'meilisearch_delete_chat_workspace', 'workspace' => $workspace['uid']], network_admin_url('edit.php')), 'delete_workspace_' . $workspace['uid'])); ?>" 
						   onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this workspace?', 'meilisearch')); ?>');">
							<?php esc_html_e('Delete', 'meilisearch'); ?>
						</a>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renderizar provider do workspace.
	 *
	 * @param string $workspace_uid UID do workspace.
	 */
	private function render_workspace_provider(string $workspace_uid): void
	{
		$settings = $this->get_workspace_settings($workspace_uid);
		if (null !== $settings && isset($settings['source'])) {
			$providers = $this->get_providers();
			$provider_name = $providers[$settings['source']]['name'] ?? $settings['source'];
			echo '<span class="provider-badge">' . esc_html($provider_name) . '</span>';
		} else {
			echo '<span class="status-inactive">' . esc_html__('Not configured', 'meilisearch') . '</span>';
		}
	}

	/**
	 * Renderizar prompt do workspace.
	 *
	 * @param string $workspace_uid UID do workspace.
	 */
	private function render_workspace_prompt(string $workspace_uid): void
	{
		$settings = $this->get_workspace_settings($workspace_uid);
		if (null !== $settings && isset($settings['prompts']['system'])) {
			$prompt = $settings['prompts']['system'];
			$short_prompt = strlen($prompt) > 100 ? substr($prompt, 0, 100) . '...' : $prompt;
			echo '<span title="' . esc_attr($prompt) . '">' . esc_html($short_prompt) . '</span>';
		} else {
			echo '<em>' . esc_html__('No system prompt', 'meilisearch') . '</em>';
		}
	}

	/**
	 * Renderizar botão adicionar novo.
	 */
	private function render_add_new_button(): void
	{
		?>
		<div class="add-new-workspace">
			<a href="<?php echo esc_url(add_query_arg(['page' => 'meilisearch-chat-workspaces', 'action' => 'new'], network_admin_url('admin.php'))); ?>" class="button button-primary">
				<?php esc_html_e('Add New Workspace', 'meilisearch'); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Renderizar formulário de novo workspace.
	 */
	private function render_new_form(): void
	{
		$this->render_workspace_form('', []);
	}

	/**
	 * Renderizar formulário de edição.
	 *
	 * @param string $workspace_uid UID do workspace.
	 */
	private function render_edit_form(string $workspace_uid): void
	{
		$settings = $this->get_workspace_settings($workspace_uid);
		$this->render_workspace_form($workspace_uid, $settings ?? []);
	}

	/**
	 * Renderizar formulário de workspace.
	 *
	 * @param string $workspace_uid UID do workspace (vazio para novo).
	 * @param array  $settings Configurações atuais.
	 */
	private function render_workspace_form(string $workspace_uid, array $settings): void
	{
		$is_new = empty($workspace_uid);
		$current_provider = $settings['source'] ?? '';
		$providers = $this->get_providers();

		?>
		<h2>
			<?php echo $is_new ? esc_html__('Create New Workspace', 'meilisearch') : esc_html__('Edit Workspace', 'meilisearch'); ?>
			<a href="<?php echo esc_url(add_query_arg(['page' => 'meilisearch-chat-workspaces'], network_admin_url('admin.php'))); ?>" class="button">
				<?php esc_html_e('Back to List', 'meilisearch'); ?>
			</a>
		</h2>

		<form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=meilisearch_save_chat_workspace')); ?>">
			<?php wp_nonce_field('save_chat_workspace_' . $workspace_uid, 'meilisearch_workspace_nonce'); ?>
			<input type="hidden" name="is_new" value="<?php echo $is_new ? '1' : '0'; ?>">
			
			<?php if (!$is_new): ?>
				<input type="hidden" name="workspace_uid" value="<?php echo esc_attr($workspace_uid); ?>">
			<?php endif; ?>

			<table class="form-table">
				<?php if ($is_new): ?>
				<tr>
					<th scope="row">
						<label for="workspace_uid"><?php esc_html_e('Workspace UID', 'meilisearch'); ?> *</label>
					</th>
					<td>
						<input type="text" name="workspace_uid" id="workspace_uid" class="regular-text" required 
							   pattern="[a-zA-Z0-9_-]+" 
							   title="<?php esc_attr_e('Only letters, numbers, hyphens and underscores', 'meilisearch'); ?>">
						<p class="description"><?php esc_html_e('Unique identifier for this workspace. Only letters, numbers, hyphens and underscores.', 'meilisearch'); ?></p>
					</td>
				</tr>
				<?php else: ?>
				<tr>
					<th scope="row"><?php esc_html_e('Workspace UID', 'meilisearch'); ?></th>
					<td><code><?php echo esc_html($workspace_uid); ?></code></td>
				</tr>
				<?php endif; ?>

				<tr>
					<th scope="row">
						<label for="provider"><?php esc_html_e('LLM Provider', 'meilisearch'); ?> *</label>
					</th>
					<td>
						<select name="provider" id="provider" required>
							<option value=""><?php esc_html_e('Select a provider...', 'meilisearch'); ?></option>
							<?php foreach ($providers as $provider_key => $provider_info): ?>
								<option value="<?php echo esc_attr($provider_key); ?>" 
										<?php selected($current_provider, $provider_key); ?>>
									<?php echo esc_html($provider_info['name']); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e('Choose the AI provider for this workspace.', 'meilisearch'); ?></p>
					</td>
				</tr>

				<!-- Campos específicos por provider -->
				<?php foreach ($providers as $provider_key => $provider_info): ?>
					<?php if (in_array('apiKey', $provider_info['fields'], true)): ?>
					<tr class="provider-fields provider-<?php echo esc_attr($provider_key); ?>" 
						data-provider="<?php echo esc_attr($provider_key); ?>">
						<th scope="row">
							<label for="api_key_<?php echo esc_attr($provider_key); ?>">
								<?php esc_html_e('API Key', 'meilisearch'); ?>
								<?php echo $provider_info['requires_api_key'] ? ' *' : ''; ?>
							</label>
						</th>
						<td>
							<input type="password" 
								   name="api_key" 
								   id="api_key_<?php echo esc_attr($provider_key); ?>" 
								   class="regular-text"
								   value="<?php echo isset($settings['apiKey']) ? esc_attr('••••••••') : ''; ?>"
								   <?php echo $provider_info['requires_api_key'] ? 'required' : ''; ?>>
							<p class="description">
								<?php esc_html_e('Your API key for this provider.', 'meilisearch'); ?>
								<?php if (!$is_new): ?>
									<br><em><?php esc_html_e('Leave blank to keep current value.', 'meilisearch'); ?></em>
								<?php endif; ?>
							</p>
						</td>
					</tr>
					<?php endif; ?>

					<?php if (in_array('baseUrl', $provider_info['fields'], true)): ?>
					<tr class="provider-fields provider-<?php echo esc_attr($provider_key); ?>" 
						data-provider="<?php echo esc_attr($provider_key); ?>">
						<th scope="row">
							<label for="base_url_<?php echo esc_attr($provider_key); ?>">
								<?php esc_html_e('Base URL', 'meilisearch'); ?>
								<?php echo $provider_info['requires_base_url'] ? ' *' : ''; ?>
							</label>
						</th>
						<td>
							<input type="url" 
								   name="base_url" 
								   id="base_url_<?php echo esc_attr($provider_key); ?>" 
								   class="regular-text"
								   value="<?php echo esc_attr($settings['baseUrl'] ?? $provider_info['default_base_url'] ?? ''); ?>"
								   <?php echo $provider_info['requires_base_url'] ? 'required' : ''; ?>>
							<p class="description"><?php esc_html_e('API endpoint URL.', 'meilisearch'); ?></p>
						</td>
					</tr>
					<?php endif; ?>

					<?php if (in_array('orgId', $provider_info['fields'], true)): ?>
					<tr class="provider-fields provider-<?php echo esc_attr($provider_key); ?>" 
						data-provider="<?php echo esc_attr($provider_key); ?>">
						<th scope="row">
							<label for="org_id"><?php esc_html_e('Organization ID', 'meilisearch'); ?></label>
						</th>
						<td>
							<input type="text" name="org_id" id="org_id" class="regular-text" 
								   value="<?php echo esc_attr($settings['orgId'] ?? ''); ?>">
						</td>
					</tr>
					<?php endif; ?>

					<?php if (in_array('apiVersion', $provider_info['fields'], true)): ?>
					<tr class="provider-fields provider-<?php echo esc_attr($provider_key); ?>" 
						data-provider="<?php echo esc_attr($provider_key); ?>">
						<th scope="row">
							<label for="api_version"><?php esc_html_e('API Version', 'meilisearch'); ?></label>
						</th>
						<td>
							<input type="text" name="api_version" id="api_version" class="regular-text" 
								   value="<?php echo esc_attr($settings['apiVersion'] ?? ''); ?>">
						</td>
					</tr>
					<?php endif; ?>

					<?php if (in_array('deploymentId', $provider_info['fields'], true)): ?>
					<tr class="provider-fields provider-<?php echo esc_attr($provider_key); ?>" 
						data-provider="<?php echo esc_attr($provider_key); ?>">
						<th scope="row">
							<label for="deployment_id"><?php esc_html_e('Deployment ID', 'meilisearch'); ?></label>
						</th>
						<td>
							<input type="text" name="deployment_id" id="deployment_id" class="regular-text" 
								   value="<?php echo esc_attr($settings['deploymentId'] ?? ''); ?>">
						</td>
					</tr>
					<?php endif; ?>
				<?php endforeach; ?>

				<tr>
					<th scope="row">
						<label for="system_prompt"><?php esc_html_e('System Prompt', 'meilisearch'); ?> *</label>
					</th>
					<td>
						<textarea name="system_prompt" id="system_prompt" rows="6" class="large-text" required><?php echo esc_textarea($settings['prompts']['system'] ?? 'You are a helpful assistant. Answer questions based only on the provided context.'); ?></textarea>
						<p class="description"><?php esc_html_e('Instructions that guide the AI behavior.', 'meilisearch'); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<?php submit_button($is_new ? __('Create Workspace', 'meilisearch') : __('Update Workspace', 'meilisearch'), 'primary', 'submit', false); ?>
			</p>
		</form>

		<script>
		jQuery(document).ready(function($) {
			var currentProvider = '<?php echo esc_js($current_provider); ?>';
			
			function updateProviderFields() {
				var selectedProvider = $('#provider').val();
				$('.provider-fields').removeClass('active').hide();
				
				if (selectedProvider) {
					$('.provider-' + selectedProvider).addClass('active').show();
				}
			}
			
			$('#provider').on('change', updateProviderFields);
			
			if (currentProvider) {
				updateProviderFields();
			}
		});
		</script>
		<?php
	}

	/**
	 * Salvar workspace.
	 */
	public function save_workspace(): void
	{
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$workspace_uid = isset($_POST['workspace_uid']) ? sanitize_text_field(wp_unslash($_POST['workspace_uid'])) : '';
		
		check_admin_referer('save_chat_workspace_' . $workspace_uid, 'meilisearch_workspace_nonce');

		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$is_new = isset($_POST['is_new']) && '1' === $_POST['is_new'];
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$base_url = isset($_POST['base_url']) ? esc_url_raw(wp_unslash($_POST['base_url'])) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$system_prompt = isset($_POST['system_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['system_prompt'])) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$org_id = isset($_POST['org_id']) ? sanitize_text_field(wp_unslash($_POST['org_id'])) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$api_version = isset($_POST['api_version']) ? sanitize_text_field(wp_unslash($_POST['api_version'])) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$deployment_id = isset($_POST['deployment_id']) ? sanitize_text_field(wp_unslash($_POST['deployment_id'])) : '';

		$settings = [
			'source' => $provider,
			'prompts' => [
				'system' => $system_prompt,
			],
		];

		if (!empty($api_key) && '••••••••' !== $api_key) {
			$settings['apiKey'] = $api_key;
		}

		if (!empty($base_url)) {
			$settings['baseUrl'] = $base_url;
		}

		if (!empty($org_id)) {
			$settings['orgId'] = $org_id;
		}

		if (!empty($api_version)) {
			$settings['apiVersion'] = $api_version;
		}

		if (!empty($deployment_id)) {
			$settings['deploymentId'] = $deployment_id;
		}

		try {
			$client = $this->get_client();
			if (null === $client) {
				throw new Exception(__('Unable to connect to Meilisearch.', 'meilisearch'));
			}

			$workspace = $client->get_client()->chatWorkspace($workspace_uid);
			$workspace->updateSettings($settings);

			wp_redirect(
				add_query_arg(
					[
						'page' => 'meilisearch-chat-workspaces',
						'updated' => 'true',
					],
					network_admin_url('admin.php')
				)
			);
		} catch (Exception $e) {
			wp_redirect(
				add_query_arg(
					[
						'page' => 'meilisearch-chat-workspaces',
						'error' => urlencode($e->getMessage()),
					],
					network_admin_url('admin.php')
				)
			);
		}

		exit;
	}

	/**
	 * Excluir workspace.
	 */
	public function delete_workspace(): void
	{
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$workspace_uid = isset($_GET['workspace']) ? sanitize_text_field(wp_unslash($_GET['workspace'])) : '';
		
		check_admin_referer('delete_workspace_' . $workspace_uid);

		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		try {
			$client = $this->get_client();
			if (null === $client) {
				throw new Exception(__('Unable to connect to Meilisearch.', 'meilisearch'));
			}

			$workspace = $client->get_client()->chatWorkspace($workspace_uid);
			$workspace->deleteSettings();

			wp_redirect(
				add_query_arg(
					[
						'page' => 'meilisearch-chat-workspaces',
						'deleted' => 'true',
					],
					network_admin_url('admin.php')
				)
			);
		} catch (Exception $e) {
			wp_redirect(
				add_query_arg(
					[
						'page' => 'meilisearch-chat-workspaces',
						'error' => urlencode($e->getMessage()),
					],
					network_admin_url('admin.php')
				)
			);
		}

		exit;
	}

	/**
	 * Testar workspace.
	 */
	public function test_workspace(): void
	{
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$workspace_uid = isset($_GET['workspace']) ? sanitize_text_field(wp_unslash($_GET['workspace'])) : '';
		
		check_admin_referer('test_workspace_' . $workspace_uid);

		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		// TODO: Implementar teste de workspace
		wp_redirect(
			add_query_arg(
				[
					'page' => 'meilisearch-chat-workspaces',
					'message' => 'test_not_implemented',
				],
				network_admin_url('admin.php')
			)
		);

		exit;
	}
}
