<?php

declare(strict_types=1);

/**
 * Monitor de Tarefas Meilisearch
 *
 * @package Meilisearch
 */

/**
 * Classe Meilisearch_Tasks_Monitor
 *
 * Gerencia página de monitoramento de tarefas do Meilisearch.
 */
class Meilisearch_Tasks_Monitor
{
	/**
	 * Instância do cliente Meilisearch.
	 *
	 * @var Meilisearch_Client|null
	 */
	private null|Meilisearch_Client $client = null;

	/**
	 * Inicializar hooks.
	 */
	public function init_hooks(): void
	{
		add_action('network_admin_menu', [$this, 'add_menu']);
	}

	/**
	 * Adicionar item de menu da administração de rede.
	 */
	public function add_menu(): void
	{
		add_submenu_page(
			'meilisearch-dashboard',
			__('Tasks Monitor', 'meilisearch'),
			__('Tasks', 'meilisearch'),
			'manage_network_options',
			'meilisearch-tasks',
			[$this, 'render_page'],
		);
	}

	/**
	 * Obter instância do cliente Meilisearch.
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
	 * Renderizar página de monitoramento de tarefas.
	 */
	public function render_page(): void
	{
		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		$client = $this->get_client();
		if (null === $client) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e('Meilisearch Tasks Monitor', 'meilisearch'); ?></h1>
				<div class="notice notice-error">
					<p><?php esc_html_e(
						'Meilisearch is not configured. Please configure the connection settings first.',
						'meilisearch',
					); ?></p>
				</div>
			</div>
			<?php

			return;
		}

		// Obter limite de tarefas (padrão 50)
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Apenas leitura de parâmetro de filtro.
		$limit = isset($_GET['limit']) ? min((int) $_GET['limit'], 100) : 50;

		// Obter tarefas recentes
		$tasks = $client->get_recent_tasks($limit);

		// Calcular estatísticas
		$stats = [
			'total' => count($tasks),
			'succeeded' => 0,
			'processing' => 0,
			'enqueued' => 0,
			'failed' => 0,
		];

		foreach ($tasks as $task) {
			$status = $task['status'] ?? 'unknown';
			if (isset($stats[$status])) {
				$stats[$status]++;
			}
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e('Meilisearch Tasks Monitor', 'meilisearch'); ?></h1>

			<p class="description">
				<?php esc_html_e(
					'Monitor the status and history of Meilisearch tasks. Tasks are asynchronous operations like indexing, updating settings, and more.',
					'meilisearch',
				); ?>
			</p>

			<!-- Estatísticas -->
			<div class="postbox" style="margin-top: 20px;">
				<div class="inside" style="padding: 12px;">
					<h2 style="margin-top: 0;"><?php esc_html_e('Task Statistics', 'meilisearch'); ?></h2>
					<div style="display: flex; gap: 20px; flex-wrap: wrap;">
						<div style="flex: 1; min-width: 150px; padding: 15px; background: #f0f0f1; border-radius: 4px;">
							<div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html((string) $stats['total']); ?></div>
							<div style="color: #666;"><?php esc_html_e('Total Tasks', 'meilisearch'); ?></div>
						</div>
						<div style="flex: 1; min-width: 150px; padding: 15px; background: #d7f0d7; border-radius: 4px;">
							<div style="font-size: 32px; font-weight: bold; color: #00a32a;"><?php echo
								esc_html((string) $stats['succeeded'])
							; ?></div>
							<div style="color: #666;"><?php esc_html_e('Succeeded', 'meilisearch'); ?></div>
						</div>
						<div style="flex: 1; min-width: 150px; padding: 15px; background: #fff8e5; border-radius: 4px;">
							<div style="font-size: 32px; font-weight: bold; color: #f0b849;"><?php echo
								esc_html((string) $stats['processing'])
							; ?></div>
							<div style="color: #666;"><?php esc_html_e('Processing', 'meilisearch'); ?></div>
						</div>
						<div style="flex: 1; min-width: 150px; padding: 15px; background: #f0f0f1; border-radius: 4px;">
							<div style="font-size: 32px; font-weight: bold; color: #666;"><?php echo esc_html((string) $stats['enqueued']); ?></div>
							<div style="color: #666;"><?php esc_html_e('Enqueued', 'meilisearch'); ?></div>
						</div>
						<div style="flex: 1; min-width: 150px; padding: 15px; background: #ffd7d7; border-radius: 4px;">
							<div style="font-size: 32px; font-weight: bold; color: #d63638;"><?php echo esc_html((string) $stats['failed']); ?></div>
							<div style="color: #666;"><?php esc_html_e('Failed', 'meilisearch'); ?></div>
						</div>
					</div>
				</div>
			</div>

			<!-- Filtros -->
			<div style="margin: 20px 0; padding: 10px; background: #fff; border: 1px solid #ccd0d4;">
				<form method="get">
					<input type="hidden" name="page" value="meilisearch-tasks" />
					<label for="limit"><?php esc_html_e('Show:', 'meilisearch'); ?></label>
					<select name="limit" id="limit" onchange="this.form.submit()">
						<option value="20" <?php selected($limit, 20); ?>>20 <?php esc_html_e('tasks', 'meilisearch'); ?></option>
						<option value="50" <?php selected($limit, 50); ?>>50 <?php esc_html_e('tasks', 'meilisearch'); ?></option>
						<option value="100" <?php selected($limit, 100); ?>>100 <?php esc_html_e('tasks', 'meilisearch'); ?></option>
					</select>
				</form>
			</div>

			<!-- Lista de Tarefas -->
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 60px;"><?php esc_html_e('UID', 'meilisearch'); ?></th>
						<th style="width: 100px;"><?php esc_html_e('Status', 'meilisearch'); ?></th>
						<th style="width: 150px;"><?php esc_html_e('Type', 'meilisearch'); ?></th>
						<th><?php esc_html_e('Index', 'meilisearch'); ?></th>
						<th style="width: 150px;"><?php esc_html_e('Enqueued At', 'meilisearch'); ?></th>
						<th style="width: 100px;"><?php esc_html_e('Duration', 'meilisearch'); ?></th>
						<th style="width: 80px;"><?php esc_html_e('Details', 'meilisearch'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($tasks)): ?>
						<tr>
							<td colspan="7" style="text-align: center; padding: 20px;">
								<?php esc_html_e('No tasks found.', 'meilisearch'); ?>
							</td>
						</tr>
					<?php else: ?>
						<?php foreach ($tasks as $task): ?>
							<?php

							$status = $task['status'] ?? 'unknown';
							$status_colors = [
								'succeeded' => '#00a32a',
								'processing' => '#f0b849',
								'enqueued' => '#666',
								'failed' => '#d63638',
							];
							$status_color = $status_colors[$status] ?? '#666';

							// Calcular duração
							$duration = '';
							if (isset($task['startedAt'], $task['finishedAt'])) {
								$start = strtotime($task['startedAt']);
								$end = strtotime($task['finishedAt']);
								$diff = $end - $start;
								if ($diff < 1) {
									$duration = '< 1s';
								} elseif ($diff < 60) {
									$duration = $diff . 's';
								} else {
									$duration = gmdate('i:s', $diff);
								}
							}

							// Formatar data
							$enqueued = isset($task['enqueuedAt']) ? wp_date('Y-m-d H:i:s', strtotime($task['enqueuedAt'])) : '-';
							?>
							<tr>
								<td><code><?php echo esc_html((string) ($task['uid'] ?? '-')); ?></code></td>
								<td>
									<span style="display: inline-block; padding: 3px 8px; background: <?php echo esc_attr($status_color); ?>; color: white; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase;">
										<?php echo esc_html($status); ?>
									</span>
								</td>
								<td><?php echo esc_html($task['type'] ?? '-'); ?></td>
								<td><code><?php echo esc_html($task['indexUid'] ?? '-'); ?></code></td>
								<td><?php echo esc_html($enqueued); ?></td>
								<td><?php echo esc_html($duration); ?></td>
								<td>
									<?php if ('failed' === $status && isset($task['error'])): ?>
										<button type="button" class="button button-small" onclick="alert('<?php echo
											esc_js(wp_json_encode($task['error'], JSON_PRETTY_PRINT))
										; ?>')">
											<?php esc_html_e('Error', 'meilisearch'); ?>
										</button>
									<?php elseif (isset($task['details'])): ?>
										<button type="button" class="button button-small" onclick="alert('<?php echo
											esc_js(wp_json_encode($task['details'], JSON_PRETTY_PRINT))
										; ?>')">
											<?php esc_html_e('Details', 'meilisearch'); ?>
										</button>
									<?php else: ?>
										-
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<p style="margin-top: 20px; color: #666;">
				<em>
					<?php

					printf(
						/* translators: %s: current time */
						esc_html__('Last updated: %s', 'meilisearch'),
						esc_html(current_time('Y-m-d H:i:s')),
					);
					?>
				</em>
			</p>
		</div>

		<style>
			.wp-list-table th,
			.wp-list-table td {
				padding: 12px 10px;
			}
		</style>
		<?php
	}
}
