<?php

declare(strict_types=1);

/**
 * Configurações de Pesquisa do Meilisearch
 *
 * @package Meilisearch
 */

/**
 * Classe Meilisearch_Search_Settings
 *
 * Gerencia a página de configurações de pesquisa do admin de rede.
 * Permite configurar atributos filtráveis e ordenáveis para os índices.
 */
class Meilisearch_Search_Settings
{
	/**
	 * Nome da opção para as configurações de pesquisa.
	 *
	 * @var string
	 */
	private string $option_name = 'meilisearch_search_settings';

	/**
	 * Atributos disponíveis no índice.
	 *
	 * @var array<string, string>
	 */
	private array $available_attributes = [
		'id' => 'ID do Post',
		'blog_id' => 'ID do Site',
		'title' => 'Título',
		'content' => 'Conteúdo',
		'excerpt' => 'Resumo',
		'post_type' => 'Tipo de Post',
		'post_status' => 'Status do Post',
		'date' => 'Data de Publicação',
		'modified' => 'Data de Modificação',
		'author' => 'Nome do Autor',
		'author_id' => 'ID do Autor',
		'categories' => 'Categorias',
		'tags' => 'Tags',
		'permalink' => 'Link Permanente',
	];

	/**
	 * Inicializar hooks do WordPress.
	 */
	public function init_hooks(): void
	{
		add_action('network_admin_menu', [$this, 'add_network_menu']);
		add_action('network_admin_edit_meilisearch_search_settings', [$this, 'save_search_settings']);
	}

	/**
	 * Adicionar item de menu do admin de rede.
	 */
	public function add_network_menu(): void
	{
		// Submenu de configurações de pesquisa (o menu principal é criado pela classe Dashboard)
		add_submenu_page(
			'meilisearch-dashboard',
			__('Search Configuration', 'meilisearch'),
			__('Search', 'meilisearch'),
			'manage_network_options',
			'meilisearch-search-settings',
			[$this, 'render_settings_page'],
		);
	}

	/**
	 * Renderizar página de configurações de pesquisa.
	 */
	public function render_settings_page(): void
	{
		if (!current_user_can('manage_network_options') && !is_super_admin()) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		$settings = $this->get_settings();

		?>
		<div class="wrap">
			<h1><?php esc_html_e('Meilisearch Search Configuration', 'meilisearch'); ?></h1>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Exibição somente leitura da mensagem de sucesso.
			if (isset($_GET['updated'])): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Search settings saved successfully. Remember to reindex your content for changes to take effect.', 'meilisearch'); ?></p>
				</div>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e('Configure which attributes can be used for filtering and sorting search results. These settings apply to all sites in the network.', 'meilisearch'); ?>
			</p>

			<form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=meilisearch_search_settings')); ?>">
				<?php wp_nonce_field('meilisearch_search_settings', 'meilisearch_search_settings_nonce'); ?>

				<h2><?php esc_html_e('Sortable Attributes', 'meilisearch'); ?></h2>
				<p class="description">
					<?php esc_html_e('Select which attributes can be used to sort search results. For example, you can sort by publication date (descending) to show newest content first.', 'meilisearch'); ?>
				</p>

				<table class="widefat">
					<thead>
						<tr>
							<th style="width: 50px;">
								<input type="checkbox" id="select-all-sortable" />
							</th>
							<th><?php esc_html_e('Attribute', 'meilisearch'); ?></th>
							<th><?php esc_html_e('Description', 'meilisearch'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($this->available_attributes as $key => $label): ?>
							<tr>
								<td>
									<input type="checkbox"
										   class="sortable-checkbox"
										   name="meilisearch_search_settings[sortable_attributes][]"
										   value="<?php echo esc_attr($key); ?>"
										   <?php checked(in_array($key, $settings['sortable_attributes'], true)); ?> />
								</td>
								<td><strong><?php echo esc_html($key); ?></strong></td>
								<td><?php echo esc_html($label); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<br><br>

				<h2><?php esc_html_e('Filterable Attributes', 'meilisearch'); ?></h2>
				<p class="description">
					<?php esc_html_e('Select which attributes can be used to filter search results. For example, you can filter by post type, author, categories, or tags.', 'meilisearch'); ?>
				</p>

				<table class="widefat">
					<thead>
						<tr>
							<th style="width: 50px;">
								<input type="checkbox" id="select-all-filterable" />
							</th>
							<th><?php esc_html_e('Attribute', 'meilisearch'); ?></th>
							<th><?php esc_html_e('Description', 'meilisearch'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($this->available_attributes as $key => $label): ?>
							<tr>
								<td>
									<input type="checkbox"
										   class="filterable-checkbox"
										   name="meilisearch_search_settings[filterable_attributes][]"
										   value="<?php echo esc_attr($key); ?>"
										   <?php checked(in_array($key, $settings['filterable_attributes'], true)); ?> />
								</td>
								<td><strong><?php echo esc_html($key); ?></strong></td>
								<td><?php echo esc_html($label); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<br><br>

				<h2><?php esc_html_e('Display Options', 'meilisearch'); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<?php esc_html_e('Relevance Badges', 'meilisearch'); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" 
									   name="meilisearch_search_settings[show_relevance_badges]" 
									   value="1"
									   <?php checked($settings['show_relevance_badges'] ?? true, true); ?> />
								<?php esc_html_e('Show relevance score badges in search results', 'meilisearch'); ?>
							</label>
							<p class="description">
								<?php esc_html_e('Display colored badges showing the relevance percentage next to search result titles. You can customize the badge styles in your theme CSS.', 'meilisearch'); ?>
							</p>
						</td>
					</tr>
				</table>

				<br>

				<h2><?php esc_html_e('Default Sort Order', 'meilisearch'); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="default_sort_attribute">
								<?php esc_html_e('Sort By', 'meilisearch'); ?>
							</label>
						</th>
						<td>
							<select id="default_sort_attribute" name="meilisearch_search_settings[default_sort_attribute]">
								<option value=""><?php esc_html_e('Relevance (Default)', 'meilisearch'); ?></option>
								<?php foreach ($this->available_attributes as $key => $label): ?>
									<?php if (in_array($key, $settings['sortable_attributes'], true)): ?>
										<option value="<?php echo esc_attr($key); ?>" 
												<?php selected($settings['default_sort_attribute'], $key); ?>>
											<?php echo esc_html($label); ?>
										</option>
									<?php endif; ?>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="default_sort_direction">
								<?php esc_html_e('Sort Direction', 'meilisearch'); ?>
							</label>
						</th>
						<td>
							<select id="default_sort_direction" name="meilisearch_search_settings[default_sort_direction]">
								<option value="asc" <?php selected($settings['default_sort_direction'], 'asc'); ?>>
									<?php esc_html_e('Ascending (A-Z, 0-9, oldest first)', 'meilisearch'); ?>
								</option>
								<option value="desc" <?php selected($settings['default_sort_direction'], 'desc'); ?>>
									<?php esc_html_e('Descending (Z-A, 9-0, newest first)', 'meilisearch'); ?>
								</option>
							</select>
							<p class="description">
								<?php esc_html_e('For date fields like "date" or "modified", descending order shows newest content first.', 'meilisearch'); ?>
							</p>
						</td>
					</tr>
				</table>

				<div class="notice notice-info inline">
					<p>
						<strong><?php esc_html_e('Important:', 'meilisearch'); ?></strong>
						<?php esc_html_e('After changing these settings, you need to reindex your content for the changes to take effect. Use WP-CLI:', 'meilisearch'); ?>
						<code>wp meilisearch index --network</code>
					</p>
				</div>

				<?php submit_button(__('Save Search Settings', 'meilisearch')); ?>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Select all sortable
			$('#select-all-sortable').on('change', function() {
				$('.sortable-checkbox').prop('checked', this.checked);
			});

			// Select all filterable
			$('#select-all-filterable').on('change', function() {
				$('.filterable-checkbox').prop('checked', this.checked);
			});

			// Update individual checkboxes
			$('.sortable-checkbox').on('change', function() {
				if (!this.checked) {
					$('#select-all-sortable').prop('checked', false);
				}
			});

			$('.filterable-checkbox').on('change', function() {
				if (!this.checked) {
					$('#select-all-filterable').prop('checked', false);
				}
			});
		});
		</script>

		<style>
		.widefat tbody tr:hover {
			background-color: #f5f5f5;
		}
		.notice.inline {
			padding: 12px;
			margin: 20px 0;
		}
		</style>
		<?php
	}

	/**
	 * Obter configurações de pesquisa.
	 *
	 * @return array Configurações com valores padrão.
	 */
	private function get_settings(): array
	{
		$settings = get_site_option($this->option_name, []);
		$defaults = [
			'sortable_attributes' => ['date', 'modified', 'title'],
			'filterable_attributes' => ['post_type', 'blog_id', 'author_id', 'categories', 'tags'],
			'default_sort_attribute' => 'date',
			'default_sort_direction' => 'desc',
			'show_relevance_badges' => true,
		];

		return wp_parse_args($settings, $defaults);
	}

	/**
	 * Salvar configurações de pesquisa.
	 */
	public function save_search_settings(): void
	{
		check_admin_referer('meilisearch_search_settings', 'meilisearch_search_settings_nonce');

		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validado via nonce acima, sanitizado individualmente abaixo.
		$settings = isset($_POST['meilisearch_search_settings']) ? wp_unslash($_POST['meilisearch_search_settings']) : [];

		// Sanitizar configurações.
		$sortable_attributes = isset($settings['sortable_attributes']) && is_array($settings['sortable_attributes'])
			? array_map('sanitize_key', $settings['sortable_attributes'])
			: [];

		$filterable_attributes = isset($settings['filterable_attributes']) && is_array($settings['filterable_attributes'])
			? array_map('sanitize_key', $settings['filterable_attributes'])
			: [];

		$sanitized = [
			'sortable_attributes' => $sortable_attributes,
			'filterable_attributes' => $filterable_attributes,
			'default_sort_attribute' => sanitize_key($settings['default_sort_attribute'] ?? ''),
			'default_sort_direction' => in_array($settings['default_sort_direction'] ?? 'desc', ['asc', 'desc'], true)
				? $settings['default_sort_direction']
				: 'desc',
			'show_relevance_badges' => isset($settings['show_relevance_badges']) && $settings['show_relevance_badges'] === '1',
		];

		update_site_option($this->option_name, $sanitized);

		wp_redirect(add_query_arg([
			'page' => 'meilisearch-search-settings',
			'updated' => 'true',
		], network_admin_url('admin.php')));
		exit();
	}

	/**
	 * Obter atributos ordenáveis configurados.
	 *
	 * @return array Lista de atributos ordenáveis.
	 */
	public static function get_sortable_attributes(): array
	{
		$settings = get_site_option('meilisearch_search_settings', []);
		return isset($settings['sortable_attributes']) && is_array($settings['sortable_attributes'])
			? $settings['sortable_attributes']
			: ['date', 'modified', 'title'];
	}

	/**
	 * Obter atributos filtráveis configurados.
	 *
	 * @return array Lista de atributos filtráveis.
	 */
	public static function get_filterable_attributes(): array
	{
		$settings = get_site_option('meilisearch_search_settings', []);
		return isset($settings['filterable_attributes']) && is_array($settings['filterable_attributes'])
			? $settings['filterable_attributes']
			: ['post_type', 'blog_id', 'author_id', 'categories', 'tags'];
	}

	/**
	 * Obter ordem de classificação padrão.
	 *
	 * @return array Array com 'attribute' e 'direction'.
	 */
	public static function get_default_sort(): array
	{
		$settings = get_site_option('meilisearch_search_settings', []);
		return [
			'attribute' => $settings['default_sort_attribute'] ?? 'date',
			'direction' => $settings['default_sort_direction'] ?? 'desc',
		];
	}

	/**
	 * Verificar se os badges de relevância devem ser exibidos.
	 *
	 * @return bool True se os badges devem ser exibidos, false caso contrário.
	 */
	public static function should_show_relevance_badges(): bool
	{
		$settings = get_site_option('meilisearch_search_settings', []);
		return isset($settings['show_relevance_badges']) ? (bool) $settings['show_relevance_badges'] : true;
	}
}
