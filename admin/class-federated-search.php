<?php

declare(strict_types=1);

/**
 * Página de Busca Federada do Meilisearch
 *
 * @package Meilisearch
 */

/**
 * Classe Meilisearch_Federated_Search
 *
 * Gerencia buscas federadas (multi-search com federation).
 */
class Meilisearch_Federated_Search
{
	/**
	 * Instância do cliente Meilisearch.
	 *
	 * @var Meilisearch_Client|null
	 */
	private null|Meilisearch_Client $client = null;

	/**
	 * Inicializar hooks do WordPress.
	 */
	public function init_hooks(): void
	{
		add_action('network_admin_menu', [$this, 'add_network_menu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
		add_action('wp_ajax_meilisearch_federated_search', [$this, 'ajax_federated_search']);
		add_action('wp_ajax_nopriv_meilisearch_federated_search', [$this, 'ajax_federated_search']);
		
		// Shortcode para busca federada no frontend
		add_shortcode('meilisearch_federated', [$this, 'render_search_shortcode']);
	}

	/**
	 * Adicionar item de menu da administração de rede.
	 */
	public function add_network_menu(): void
	{
		add_submenu_page(
			'meilisearch-dashboard',
			__('Federated Search', 'meilisearch'),
			__('Federated Search', 'meilisearch'),
			'manage_network_options',
			'meilisearch-federated',
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
		if ('admin_page_meilisearch-federated' !== $hook) {
			return;
		}

		wp_enqueue_script(
			'meilisearch-federated',
			plugins_url('assets/js/federated-search.js', dirname(__FILE__)),
			['jquery'],
			'1.0.0',
			true
		);

		wp_localize_script(
			'meilisearch-federated',
			'meilisearchFederated',
			[
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('meilisearch_federated_search'),
			]
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
	 * Obter lista de índices disponíveis.
	 *
	 * @return array
	 */
	private function get_available_indexes(): array
	{
		$client = $this->get_client();
		if (null === $client) {
			return [];
		}

		try {
			$indexes = $client->get_client()->getAllIndexes();
			$result = [];
			
			foreach ($indexes->getResults() as $index) {
				$result[] = [
					'uid' => $index['uid'],
					'primaryKey' => $index['primaryKey'] ?? null,
					'createdAt' => $index['createdAt'],
					'updatedAt' => $index['updatedAt'],
				];
			}
			
			return $result;
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log('Meilisearch get indexes error: ' . $e->getMessage());
			}
			return [];
		}
	}

	/**
	 * Renderizar página de busca federada.
	 */
	public function render_page(): void
	{
		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		$client = $this->get_client();
		$indexes = $this->get_available_indexes();

		?>
		<div class="wrap">
			<h1><?php esc_html_e('Meilisearch Federated Search', 'meilisearch'); ?></h1>

			<?php if (null === $client): ?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e('Error:', 'meilisearch'); ?></strong>
						<?php esc_html_e('Unable to connect to Meilisearch server. Please check your settings.', 'meilisearch'); ?>
					</p>
				</div>
			<?php else: ?>

				<div class="notice notice-info" style="margin-top: 20px;">
					<p>
						<strong><?php esc_html_e('What is Federated Search?', 'meilisearch'); ?></strong><br>
						<?php esc_html_e('Federated search allows you to search across multiple indexes simultaneously and merge results into a unified view. Perfect for searching posts, pages, products, and custom content types all at once.', 'meilisearch'); ?>
					</p>
				</div>

				<!-- Search Interface -->
				<div class="federated-search-section">
					<h2><?php esc_html_e('Test Federated Search', 'meilisearch'); ?></h2>
					
					<div class="search-config">
						<h3><?php esc_html_e('Select Indexes to Search', 'meilisearch'); ?></h3>
						
						<?php if (empty($indexes)): ?>
							<p><?php esc_html_e('No indexes found. Create indexes first.', 'meilisearch'); ?></p>
						<?php else: ?>
							<form id="federated-search-form">
								<div class="index-selection">
									<?php foreach ($indexes as $index): ?>
										<label style="display: block; margin-bottom: 10px;">
											<input type="checkbox" 
												   name="indexes[]" 
												   value="<?php echo esc_attr($index['uid']); ?>"
												   class="federated-index">
											<strong><?php echo esc_html($index['uid']); ?></strong>
											<?php if ($index['primaryKey']): ?>
												<span class="description">(Primary key: <?php echo esc_html($index['primaryKey']); ?>)</span>
											<?php endif; ?>
										</label>
									<?php endforeach; ?>
								</div>

								<h3><?php esc_html_e('Search Configuration', 'meilisearch'); ?></h3>
								
								<table class="form-table">
									<tr>
										<th scope="row">
											<label for="search_query"><?php esc_html_e('Search Query', 'meilisearch'); ?></label>
										</th>
										<td>
											<input type="text" 
												   id="search_query" 
												   name="search_query" 
												   class="regular-text" 
												   placeholder="<?php esc_attr_e('Enter search terms...', 'meilisearch'); ?>">
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="limit"><?php esc_html_e('Results Limit', 'meilisearch'); ?></label>
										</th>
										<td>
											<input type="number" 
												   id="limit" 
												   name="limit" 
												   value="20" 
												   min="1" 
												   max="1000" 
												   class="small-text">
											<p class="description"><?php esc_html_e('Maximum number of results per index.', 'meilisearch'); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="federation_limit"><?php esc_html_e('Federation Limit', 'meilisearch'); ?></label>
										</th>
										<td>
											<input type="number" 
												   id="federation_limit" 
												   name="federation_limit" 
												   value="50" 
												   min="1" 
												   max="1000" 
												   class="small-text">
											<p class="description"><?php esc_html_e('Maximum total results in unified view.', 'meilisearch'); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e('Merge Strategy', 'meilisearch'); ?></th>
										<td>
											<label>
												<input type="radio" name="merge_strategy" value="merge" checked>
												<?php esc_html_e('Merge Results', 'meilisearch'); ?>
											</label>
											<p class="description"><?php esc_html_e('Combine results from all indexes into a single list.', 'meilisearch'); ?></p>
										</td>
									</tr>
								</table>

								<p class="submit">
									<button type="submit" class="button button-primary">
										<?php esc_html_e('Search', 'meilisearch'); ?>
									</button>
									<span class="spinner" style="float: none; margin: 0 10px;"></span>
								</p>
							</form>

							<!-- Results Container -->
							<div id="federated-results" style="display: none; margin-top: 30px;">
								<h3><?php esc_html_e('Search Results', 'meilisearch'); ?></h3>
								<div id="results-content"></div>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<hr style="margin: 40px 0;">

				<!-- Saved Queries -->
				<div class="federated-search-section">
					<h2><?php esc_html_e('Saved Federated Queries', 'meilisearch'); ?></h2>
					<?php $this->render_saved_queries(); ?>
				</div>

				<hr style="margin: 40px 0;">

				<!-- Implementation Guide -->
				<div class="federated-search-section">
					<h2><?php esc_html_e('Frontend Implementation', 'meilisearch'); ?></h2>
					<p><?php esc_html_e('Use the shortcode below to add federated search to any page or post:', 'meilisearch'); ?></p>
					
					<pre style="background: #f0f0f1; padding: 15px; overflow-x: auto;">[meilisearch_federated indexes="posts,pages,products" limit="20"]</pre>
					
					<h3><?php esc_html_e('Shortcode Parameters', 'meilisearch'); ?></h3>
					<ul>
						<li><code>indexes</code> - <?php esc_html_e('Comma-separated list of index UIDs to search (required)', 'meilisearch'); ?></li>
						<li><code>limit</code> - <?php esc_html_e('Results limit per index (default: 20)', 'meilisearch'); ?></li>
						<li><code>federation_limit</code> - <?php esc_html_e('Total results limit (default: 50)', 'meilisearch'); ?></li>
						<li><code>placeholder</code> - <?php esc_html_e('Search box placeholder text', 'meilisearch'); ?></li>
					</ul>
				</div>

			<?php endif; ?>
		</div>

		<style>
			.federated-search-section {
				background: #fff;
				padding: 20px;
				border: 1px solid #ccd0d4;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
				margin-bottom: 20px;
			}
			.federated-search-section h2 {
				margin-top: 0;
			}
			.index-selection {
				background: #f9f9f9;
				padding: 15px;
				border: 1px solid #ddd;
				margin-bottom: 20px;
			}
			.result-item {
				padding: 15px;
				border: 1px solid #ddd;
				margin-bottom: 10px;
				background: #fff;
			}
			.result-item-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 10px;
			}
			.result-index-badge {
				display: inline-block;
				padding: 3px 8px;
				background: #2271b1;
				color: white;
				border-radius: 3px;
				font-size: 11px;
				font-weight: bold;
			}
			.result-score {
				color: #666;
				font-size: 12px;
			}
			.result-content {
				color: #333;
			}
			pre code {
				display: block;
				padding: 10px;
				background: #f5f5f5;
				border: 1px solid #ddd;
			}
		</style>

		<script>
		jQuery(document).ready(function($) {
			$('#federated-search-form').on('submit', function(e) {
				e.preventDefault();
				
				var selectedIndexes = [];
				$('.federated-index:checked').each(function() {
					selectedIndexes.push($(this).val());
				});
				
				if (selectedIndexes.length === 0) {
					alert('<?php echo esc_js(__('Please select at least one index.', 'meilisearch')); ?>');
					return;
				}
				
				var query = $('#search_query').val();
				if (!query) {
					alert('<?php echo esc_js(__('Please enter a search query.', 'meilisearch')); ?>');
					return;
				}
				
				$('.spinner').addClass('is-active');
				$('#federated-results').hide();
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'meilisearch_federated_search',
						nonce: '<?php echo esc_js(wp_create_nonce('meilisearch_federated_search')); ?>',
						indexes: selectedIndexes,
						query: query,
						limit: $('#limit').val(),
						federation_limit: $('#federation_limit').val()
					},
					success: function(response) {
						$('.spinner').removeClass('is-active');
						
						if (response.success) {
							displayResults(response.data);
						} else {
							alert('<?php echo esc_js(__('Error:', 'meilisearch')); ?> ' + response.data.message);
						}
					},
					error: function() {
						$('.spinner').removeClass('is-active');
						alert('<?php echo esc_js(__('Request failed. Please try again.', 'meilisearch')); ?>');
					}
				});
			});
			
			function displayResults(data) {
				var html = '<div class="federated-stats">';
				html += '<p><?php echo esc_js(__('Total results:', 'meilisearch')); ?> <strong>' + data.hits.length + '</strong></p>';
				html += '<p><?php echo esc_js(__('Processing time:', 'meilisearch')); ?> <strong>' + data.processingTimeMs + 'ms</strong></p>';
				html += '</div>';
				
				if (data.hits.length === 0) {
					html += '<p><?php echo esc_js(__('No results found.', 'meilisearch')); ?></p>';
				} else {
					data.hits.forEach(function(hit) {
						html += '<div class="result-item">';
						html += '<div class="result-item-header">';
						html += '<span class="result-index-badge">' + hit._federation.indexUid + '</span>';
						if (hit._rankingScore) {
							html += '<span class="result-score"><?php echo esc_js(__('Score:', 'meilisearch')); ?> ' + hit._rankingScore.toFixed(4) + '</span>';
						}
						html += '</div>';
						html += '<div class="result-content">';
						html += '<strong>' + (hit.title || hit.name || hit.id || '<?php echo esc_js(__('Untitled', 'meilisearch')); ?>') + '</strong><br>';
						if (hit.content) {
							html += '<p>' + hit.content.substring(0, 200) + (hit.content.length > 200 ? '...' : '') + '</p>';
						}
						html += '</div>';
						html += '</div>';
					});
				}
				
				$('#results-content').html(html);
				$('#federated-results').show();
			}
		});
		</script>
		<?php
	}

	/**
	 * Renderizar queries salvos.
	 */
	private function render_saved_queries(): void
	{
		$saved_queries = get_site_option('meilisearch_federated_queries', []);

		if (empty($saved_queries)) {
			echo '<p>' . esc_html__('No saved federated queries yet.', 'meilisearch') . '</p>';
			return;
		}

		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Name', 'meilisearch'); ?></th>
					<th><?php esc_html_e('Indexes', 'meilisearch'); ?></th>
					<th><?php esc_html_e('Configuration', 'meilisearch'); ?></th>
					<th><?php esc_html_e('Actions', 'meilisearch'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($saved_queries as $id => $query): ?>
				<tr>
					<td><strong><?php echo esc_html($query['name']); ?></strong></td>
					<td><?php echo esc_html(implode(', ', $query['indexes'])); ?></td>
					<td>
						<?php
						printf(
							/* translators: 1: limit, 2: federation limit */
							esc_html__('Limit: %1$d, Federation limit: %2$d', 'meilisearch'),
							$query['limit'],
							$query['federation_limit']
						);
						?>
					</td>
					<td>
						<button class="button button-small"><?php esc_html_e('Load', 'meilisearch'); ?></button>
						|
						<button class="button button-small"><?php esc_html_e('Delete', 'meilisearch'); ?></button>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Handler AJAX para busca federada.
	 */
	public function ajax_federated_search(): void
	{
		check_ajax_referer('meilisearch_federated_search', 'nonce');

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$indexes = isset($_POST['indexes']) ? array_map('sanitize_text_field', wp_unslash($_POST['indexes'])) : [];
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$federation_limit = isset($_POST['federation_limit']) ? intval($_POST['federation_limit']) : 50;

		if (empty($indexes) || empty($query)) {
			wp_send_json_error(['message' => __('Missing required parameters.', 'meilisearch')]);
		}

		try {
			$result = $this->perform_federated_search($indexes, $query, $limit, $federation_limit);
			wp_send_json_success($result);
		} catch (Exception $e) {
			wp_send_json_error(['message' => $e->getMessage()]);
		}
	}

	/**
	 * Executar busca federada.
	 *
	 * @param array  $indexes Lista de índices.
	 * @param string $query Query de busca.
	 * @param int    $limit Limite por índice.
	 * @param int    $federation_limit Limite total federado.
	 * @return array
	 */
	private function perform_federated_search(array $indexes, string $query, int $limit, int $federation_limit): array
	{
		$client = $this->get_client();
		if (null === $client) {
			throw new Exception(__('Unable to connect to Meilisearch.', 'meilisearch'));
		}

		// Construir queries para cada índice
		$queries = [];
		foreach ($indexes as $index_uid) {
			$queries[] = [
				'indexUid' => $index_uid,
				'q' => $query,
				'limit' => $limit,
			];
		}

		// Configuração da federação
		$federation = [
			'limit' => $federation_limit,
		];

		// Executar multi-search com federation
		$results = $client->get_client()->multiSearch($queries, $federation);

		return [
			'hits' => $results['hits'] ?? [],
			'processingTimeMs' => $results['processingTimeMs'] ?? 0,
			'limit' => $results['limit'] ?? $federation_limit,
			'estimatedTotalHits' => count($results['hits'] ?? []),
		];
	}

	/**
	 * Renderizar shortcode de busca federada.
	 *
	 * @param array $atts Atributos do shortcode.
	 * @return string
	 */
	public function render_search_shortcode(array $atts): string
	{
		$atts = shortcode_atts(
			[
				'indexes' => '',
				'limit' => 20,
				'federation_limit' => 50,
				'placeholder' => __('Search...', 'meilisearch'),
			],
			$atts,
			'meilisearch_federated'
		);

		if (empty($atts['indexes'])) {
			return '<p>' . esc_html__('Error: No indexes specified.', 'meilisearch') . '</p>';
		}

		$indexes = array_map('trim', explode(',', $atts['indexes']));
		$unique_id = 'meilisearch-' . wp_rand(1000, 9999);

		wp_enqueue_script('jquery');

		ob_start();
		?>
		<div class="meilisearch-federated-search" id="<?php echo esc_attr($unique_id); ?>">
			<form class="meilisearch-search-form">
				<input type="text" 
					   class="meilisearch-search-input" 
					   placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
					   required>
				<button type="submit" class="meilisearch-search-button">
					<?php esc_html_e('Search', 'meilisearch'); ?>
				</button>
				<span class="meilisearch-spinner" style="display: none;">⏳</span>
			</form>
			<div class="meilisearch-results" style="display: none; margin-top: 20px;"></div>
		</div>

		<script>
		(function($) {
			$('#<?php echo esc_js($unique_id); ?> form').on('submit', function(e) {
				e.preventDefault();
				
				var container = $(this).closest('.meilisearch-federated-search');
				var query = container.find('.meilisearch-search-input').val();
				
				container.find('.meilisearch-spinner').show();
				container.find('.meilisearch-results').hide();
				
				$.ajax({
					url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
					type: 'POST',
					data: {
						action: 'meilisearch_federated_search',
						nonce: '<?php echo esc_js(wp_create_nonce('meilisearch_federated_search')); ?>',
						indexes: <?php echo wp_json_encode($indexes); ?>,
						query: query,
						limit: <?php echo intval($atts['limit']); ?>,
						federation_limit: <?php echo intval($atts['federation_limit']); ?>
					},
					success: function(response) {
						container.find('.meilisearch-spinner').hide();
						
						if (response.success && response.data.hits) {
							var html = '<div class="results-count"><?php echo esc_js(__('Found', 'meilisearch')); ?> ' + response.data.hits.length + ' <?php echo esc_js(__('results', 'meilisearch')); ?></div>';
							
							response.data.hits.forEach(function(hit) {
								html += '<div class="result-item">';
								html += '<h3>' + (hit.title || hit.name || hit.id || '<?php echo esc_js(__('Untitled', 'meilisearch')); ?>') + '</h3>';
								if (hit.content) {
									html += '<p>' + hit.content.substring(0, 200) + '...</p>';
								}
								html += '<small class="result-index"><?php echo esc_js(__('From:', 'meilisearch')); ?> ' + hit._federation.indexUid + '</small>';
								html += '</div>';
							});
							
							container.find('.meilisearch-results').html(html).show();
						}
					}
				});
			});
		})(jQuery);
		</script>

		<style>
		.meilisearch-federated-search {
			max-width: 800px;
			margin: 20px 0;
		}
		.meilisearch-search-form {
			display: flex;
			gap: 10px;
			align-items: center;
		}
		.meilisearch-search-input {
			flex: 1;
			padding: 10px;
			font-size: 16px;
			border: 1px solid #ddd;
			border-radius: 4px;
		}
		.meilisearch-search-button {
			padding: 10px 20px;
			background: #2271b1;
			color: white;
			border: none;
			border-radius: 4px;
			cursor: pointer;
			font-size: 16px;
		}
		.meilisearch-search-button:hover {
			background: #135e96;
		}
		.result-item {
			padding: 15px;
			border: 1px solid #ddd;
			margin-bottom: 10px;
			border-radius: 4px;
			background: #fff;
		}
		.result-item h3 {
			margin-top: 0;
			margin-bottom: 10px;
		}
		.result-index {
			color: #666;
			font-style: italic;
		}
		.results-count {
			padding: 10px;
			background: #f0f0f1;
			margin-bottom: 15px;
			border-radius: 4px;
		}
		</style>
		<?php
		return ob_get_clean();
	}
}
