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
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
	}

	/**
	 * Enfileirar scripts e estilos do admin.
	 *
	 * @param string $hook_suffix O sufixo da página atual.
	 */
	public function enqueue_admin_scripts(string $hook_suffix): void
	{
		// Apenas carregar na página de configurações de pesquisa
		if ($hook_suffix !== 'meilisearch_page_meilisearch-search-settings') {
			return;
		}

		// Enfileirar jQuery UI Sortable
		wp_enqueue_script('jquery-ui-sortable');
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
					<tr>
						<th scope="row">
							<?php esc_html_e('Post URLs', 'meilisearch'); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" 
									   name="meilisearch_search_settings[show_post_urls]" 
									   value="1"
									   <?php checked($settings['show_post_urls'] ?? true, true); ?> />
								<?php esc_html_e('Show post URLs after search results', 'meilisearch'); ?>
							</label>
							<p class="description">
								<?php esc_html_e('Display the full permalink URL after each search result content. You can customize the URL display styles in your theme CSS.', 'meilisearch'); ?>
							</p>
						</td>
					</tr>
				</table>

				<br><br>

				<h2><?php esc_html_e('Ranking Rules', 'meilisearch'); ?></h2>
				<p class="description">
					<?php
					printf(
						/* translators: 1: link to Meilisearch ranking rules documentation, 2: link to custom ranking rules documentation */
						esc_html__('Configure the order and priority of ranking rules that determine search results relevancy. Learn more about %1$s and %2$s.', 'meilisearch'),
						'<a href="https://www.meilisearch.com/docs/learn/relevancy/ranking_rules" target="_blank">' . esc_html__('ranking rules', 'meilisearch') . '</a>',
						'<a href="https://www.meilisearch.com/docs/learn/relevancy/custom_ranking_rules" target="_blank">' . esc_html__('custom ranking rules', 'meilisearch') . '</a>'
					);
					?>
				</p>

				<h3><?php esc_html_e('Built-in Ranking Rules', 'meilisearch'); ?></h3>
				<p class="description">
					<?php esc_html_e('Drag and drop to reorder the built-in ranking rules. Higher rules have more priority in determining search relevancy.', 'meilisearch'); ?>
				</p>

				<div class="ranking-rules-container">
					<ul id="ranking-rules-list" class="ranking-rules-list">
						<?php
						$built_in_rules = [
							'words' => __('Words - Match query terms count', 'meilisearch'),
							'typo' => __('Typo - Typo tolerance (fewer typos first)', 'meilisearch'),
							'proximity' => __('Proximity - Distance between matched terms', 'meilisearch'),
							'attribute' => __('Attribute - Importance of the attribute', 'meilisearch'),
							'sort' => __('Sort - Custom sorting at query time', 'meilisearch'),
							'exactness' => __('Exactness - Similarity with query terms', 'meilisearch'),
						];

						$current_rules = $settings['ranking_rules'];
						foreach ($current_rules as $index => $rule):
							if (isset($built_in_rules[$rule])):
						?>
							<li class="ranking-rule-item" data-rule="<?php echo esc_attr($rule); ?>">
								<span class="dashicons dashicons-menu drag-handle"></span>
								<span class="rule-order"><?php echo esc_html($index + 1); ?>.</span>
								<strong><?php echo esc_html(ucfirst($rule)); ?></strong>
								<span class="rule-description"> - <?php echo esc_html($built_in_rules[$rule]); ?></span>
								<input type="hidden" name="meilisearch_search_settings[ranking_rules][]" value="<?php echo esc_attr($rule); ?>" />
							</li>
						<?php
							endif;
						endforeach;
						?>
					</ul>
					<button type="button" id="reset-ranking-rules" class="button button-secondary">
						<?php esc_html_e('Reset to Default Order', 'meilisearch'); ?>
					</button>
				</div>

				<br>

				<h3><?php esc_html_e('Custom Ranking Rules', 'meilisearch'); ?></h3>
				<p class="description">
					<?php esc_html_e('Add custom ranking rules to promote certain documents. Format: attribute_name:asc or attribute_name:desc', 'meilisearch'); ?>
				</p>

				<div class="custom-ranking-rules">
					<div class="add-custom-rule">
						<select id="custom-rule-attribute">
							<option value=""><?php esc_html_e('Select attribute...', 'meilisearch'); ?></option>
							<?php foreach ($this->available_attributes as $key => $label): ?>
								<option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?> (<?php echo esc_html($key); ?>)</option>
							<?php endforeach; ?>
						</select>
						<select id="custom-rule-direction">
							<option value="asc"><?php esc_html_e('Ascending (↑)', 'meilisearch'); ?></option>
							<option value="desc"><?php esc_html_e('Descending (↓)', 'meilisearch'); ?></option>
						</select>
						<button type="button" id="add-custom-rule" class="button button-secondary">
							<?php esc_html_e('Add Rule', 'meilisearch'); ?>
						</button>
					</div>

					<ul id="custom-rules-list" class="custom-rules-list">
						<?php
						$custom_rules = $settings['custom_ranking_rules'];
						if (!empty($custom_rules)):
							foreach ($custom_rules as $custom_rule):
								$parts = explode(':', $custom_rule);
								if (count($parts) === 2):
						?>
							<li class="custom-rule-item" data-rule="<?php echo esc_attr($custom_rule); ?>">
								<span class="rule-badge"><?php echo esc_html($custom_rule); ?></span>
								<button type="button" class="button-link remove-custom-rule" data-rule="<?php echo esc_attr($custom_rule); ?>">
									<span class="dashicons dashicons-no-alt"></span>
								</button>
								<input type="hidden" name="meilisearch_search_settings[custom_ranking_rules][]" value="<?php echo esc_attr($custom_rule); ?>" />
							</li>
						<?php
								endif;
							endforeach;
						endif;
						?>
					</ul>
				</div>

				<br><br>

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

			// ===== RANKING RULES =====

			// Make ranking rules sortable
			if (typeof $.fn.sortable !== 'undefined') {
				$('#ranking-rules-list').sortable({
					handle: '.drag-handle',
					placeholder: 'ranking-rule-placeholder',
					cursor: 'move',
					update: function(event, ui) {
						updateRankingOrder();
					}
				});
				console.log('Ranking rules sortable initialized');
			} else {
				console.error('jQuery UI Sortable not available');
			}

			// Update ranking order numbers
			function updateRankingOrder() {
				$('#ranking-rules-list .ranking-rule-item').each(function(index) {
					$(this).find('.rule-order').text((index + 1) + '.');
				});
			}

			// Reset ranking rules to default
			$('#reset-ranking-rules').on('click', function() {
				if (!confirm('<?php echo esc_js(__('Reset ranking rules to Meilisearch default order?', 'meilisearch')); ?>')) {
					return;
				}

				const defaultOrder = ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'];
				const $list = $('#ranking-rules-list');
				const items = [];

				// Collect all items
				$list.find('.ranking-rule-item').each(function() {
					const rule = $(this).data('rule');
					items.push({
						rule: rule,
						element: $(this).clone()
					});
				});

				// Clear and re-add in default order
				$list.empty();
				defaultOrder.forEach(function(rule) {
					const item = items.find(function(i) { return i.rule === rule; });
					if (item) {
						$list.append(item.element);
					}
				});

				updateRankingOrder();
			});

			// Add custom ranking rule
			$('#add-custom-rule').on('click', function() {
				const attribute = $('#custom-rule-attribute').val();
				const direction = $('#custom-rule-direction').val();

				if (!attribute) {
					alert('<?php echo esc_js(__('Please select an attribute.', 'meilisearch')); ?>');
					return;
				}

				const customRule = attribute + ':' + direction;

				// Check if rule already exists
				if ($('.custom-rule-item[data-rule="' + customRule + '"]').length > 0) {
					alert('<?php echo esc_js(__('This custom rule already exists.', 'meilisearch')); ?>');
					return;
				}

				// Add the rule
				const $item = $('<li class="custom-rule-item">')
					.attr('data-rule', customRule)
					.append($('<span class="rule-badge">').text(customRule))
					.append(
						$('<button type="button" class="button-link remove-custom-rule">')
							.attr('data-rule', customRule)
							.append($('<span class="dashicons dashicons-no-alt">'))
					)
					.append(
						$('<input type="hidden">')
							.attr('name', 'meilisearch_search_settings[custom_ranking_rules][]')
							.val(customRule)
					);

				$('#custom-rules-list').append($item);

				// Reset form
				$('#custom-rule-attribute').val('');
			});

			// Remove custom ranking rule
			$(document).on('click', '.remove-custom-rule', function() {
				$(this).closest('.custom-rule-item').remove();
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
		.ranking-rules-container {
			background: #f9f9f9;
			padding: 20px;
			border: 1px solid #ddd;
			border-radius: 4px;
			margin: 15px 0;
		}
		.ranking-rules-list {
			list-style: none;
			margin: 0 0 15px 0;
			padding: 0;
		}
		.ranking-rule-item {
			background: white;
			padding: 12px 15px;
			margin-bottom: 8px;
			border: 1px solid #ddd;
			border-radius: 4px;
			cursor: move;
			display: flex;
			align-items: center;
			transition: all 0.2s;
		}
		.ranking-rule-item:hover {
			background: #f0f0f0;
			border-color: #2271b1;
		}
		.ranking-rule-item.ui-sortable-helper {
			box-shadow: 0 4px 8px rgba(0,0,0,0.2);
			opacity: 0.8;
		}
		.ranking-rule-placeholder {
			background: #e8f4f8;
			border: 2px dashed #2271b1;
			border-radius: 4px;
			height: 45px;
			margin-bottom: 8px;
		}
		.drag-handle {
			color: #999;
			margin-right: 10px;
			cursor: grab;
		}
		.drag-handle:active {
			cursor: grabbing;
		}
		.rule-order {
			font-weight: bold;
			color: #2271b1;
			margin-right: 10px;
			min-width: 25px;
		}
		.rule-description {
			color: #666;
			font-size: 13px;
		}
		.custom-ranking-rules {
			margin: 15px 0;
		}
		.add-custom-rule {
			display: flex;
			gap: 10px;
			align-items: center;
			margin-bottom: 15px;
		}
		.add-custom-rule select {
			min-width: 200px;
		}
		.custom-rules-list {
			list-style: none;
			margin: 0;
			padding: 0;
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
		}
		.custom-rule-item {
			display: flex;
			align-items: center;
			gap: 5px;
			background: #2271b1;
			color: white;
			padding: 6px 10px;
			border-radius: 3px;
			transition: all 0.2s;
		}
		.custom-rule-item:hover {
			background: #135e96;
		}
		.rule-badge {
			font-family: monospace;
			font-size: 13px;
		}
		.remove-custom-rule {
			color: white;
			padding: 0;
			margin: 0;
			background: transparent;
			border: none;
			cursor: pointer;
			opacity: 0.8;
			transition: all 0.2s;
			display: flex;
			align-items: center;
		}
		.remove-custom-rule:hover {
			opacity: 1;
			transform: scale(1.2);
			color: #ff6b6b;
		}
		.remove-custom-rule .dashicons {
			font-size: 16px;
			width: 16px;
			height: 16px;
			color: white;
			text-decoration: none;
			border: none;
		}
		.remove-custom-rule .dashicons:hover {
			color: #000000;
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
			'show_relevance_badges' => false,
			'show_post_urls' => false,
			'ranking_rules' => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'],
			'custom_ranking_rules' => [],
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

		// Sanitizar ranking rules
		$ranking_rules = isset($settings['ranking_rules']) && is_array($settings['ranking_rules'])
			? array_map('sanitize_key', $settings['ranking_rules'])
			: ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'];

		$custom_ranking_rules = isset($settings['custom_ranking_rules']) && is_array($settings['custom_ranking_rules'])
			? array_filter(array_map(function($rule) {
				// Validar formato: attribute:asc ou attribute:desc
				if (preg_match('/^[a-z_]+:(asc|desc)$/i', $rule)) {
					return sanitize_text_field($rule);
				}
				return null;
			}, $settings['custom_ranking_rules']))
			: [];

		$sanitized = [
			'sortable_attributes' => $sortable_attributes,
			'filterable_attributes' => $filterable_attributes,
			'default_sort_attribute' => sanitize_key($settings['default_sort_attribute'] ?? ''),
			'default_sort_direction' => in_array($settings['default_sort_direction'] ?? 'desc', ['asc', 'desc'], true)
				? $settings['default_sort_direction']
				: 'desc',
			'show_relevance_badges' => isset($settings['show_relevance_badges']) && $settings['show_relevance_badges'] === '1',
			'show_post_urls' => isset($settings['show_post_urls']) && $settings['show_post_urls'] === '1',
			'ranking_rules' => $ranking_rules,
			'custom_ranking_rules' => array_values($custom_ranking_rules),
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

	/**
	 * Verificar se as URLs dos posts devem ser exibidas.
	 *
	 * @return bool True se as URLs devem ser exibidas, false caso contrário.
	 */
	public static function should_show_post_urls(): bool
	{
		$settings = get_site_option('meilisearch_search_settings', []);
		return isset($settings['show_post_urls']) ? (bool) $settings['show_post_urls'] : true;
	}

	/**
	 * Obter regras de ranqueamento configuradas (built-in + custom).
	 *
	 * @return array Lista completa de regras de ranqueamento.
	 */
	public static function get_ranking_rules(): array
	{
		$settings = get_site_option('meilisearch_search_settings', []);
		$built_in_rules = isset($settings['ranking_rules']) && is_array($settings['ranking_rules'])
			? $settings['ranking_rules']
			: ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'];

		$custom_rules = isset($settings['custom_ranking_rules']) && is_array($settings['custom_ranking_rules'])
			? $settings['custom_ranking_rules']
			: [];

		return array_merge($built_in_rules, $custom_rules);
	}
}
