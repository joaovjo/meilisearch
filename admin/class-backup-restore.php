<?php

declare(strict_types=1);

/**
 * Página de Backup e Restore do Meilisearch
 *
 * @package Meilisearch
 */

/**
 * Classe Meilisearch_Backup_Restore
 *
 * Gerencia backups (dumps) e snapshots do Meilisearch.
 */
class Meilisearch_Backup_Restore
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
		add_action('network_admin_edit_meilisearch_create_dump', [$this, 'create_dump']);
		add_action('network_admin_edit_meilisearch_create_snapshot', [$this, 'create_snapshot']);
		add_action('network_admin_edit_meilisearch_update_backup_schedule', [$this, 'update_backup_schedule']);
		add_action('meilisearch_scheduled_backup', [$this, 'run_scheduled_backup']);
	}

	/**
	 * Adicionar item de menu da administração de rede.
	 */
	public function add_network_menu(): void
	{
		add_submenu_page(
			'meilisearch-dashboard',
			__('Backup & Restore', 'meilisearch'),
			__('Backup & Restore', 'meilisearch'),
			'manage_network_options',
			'meilisearch-backup',
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
	 * Renderizar página de backup e restore.
	 */
	public function render_page(): void
	{
		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		$client = $this->get_client();

		?>
		<div class="wrap">
			<h1><?php esc_html_e('Meilisearch Backup & Restore', 'meilisearch'); ?></h1>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if (isset($_GET['dump_created'])): ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php esc_html_e('Dump created successfully!', 'meilisearch'); ?>
						<?php
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended
						$task_uid = isset($_GET['task_uid']) ? intval($_GET['task_uid']) : 0;
						if ($task_uid > 0):
							printf(
								/* translators: %d: task UID */
								esc_html__('Task UID: %d', 'meilisearch'),
								$task_uid
							);
						endif;
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if (isset($_GET['snapshot_created'])): ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php esc_html_e('Snapshot created successfully!', 'meilisearch'); ?>
						<?php
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended
						$task_uid = isset($_GET['task_uid']) ? intval($_GET['task_uid']) : 0;
						if ($task_uid > 0):
							printf(
								/* translators: %d: task UID */
								esc_html__('Task UID: %d', 'meilisearch'),
								$task_uid
							);
						endif;
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if (isset($_GET['schedule_updated'])): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Backup schedule settings saved successfully.', 'meilisearch'); ?></p>
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
			<?php else: ?>

				<div class="notice notice-info" style="margin-top: 20px;">
					<p>
						<strong><?php esc_html_e('About Backups', 'meilisearch'); ?></strong><br>
						<?php esc_html_e('Dumps create a complete backup of your Meilisearch data that can be imported later. Snapshots are lightweight copies of the current state (self-hosted only).', 'meilisearch'); ?>
					</p>
				</div>

				<!-- Dumps Section -->
				<div class="backup-section">
					<h2><?php esc_html_e('Database Dumps', 'meilisearch'); ?></h2>
					<p><?php esc_html_e('Create a complete backup of all indexes and settings.', 'meilisearch'); ?></p>
					
					<form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=meilisearch_create_dump')); ?>" style="margin-top: 20px;">
						<?php wp_nonce_field('create_dump', 'meilisearch_dump_nonce'); ?>
						
						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e('Dump Type', 'meilisearch'); ?></th>
								<td>
									<label>
										<input type="radio" name="dump_type" value="manual" checked>
										<?php esc_html_e('Manual Backup', 'meilisearch'); ?>
									</label>
									<p class="description"><?php esc_html_e('Create a backup now.', 'meilisearch'); ?></p>
								</td>
							</tr>
						</table>

						<?php submit_button(__('Create Dump Now', 'meilisearch'), 'primary', 'submit', true, ['onclick' => 'return confirm("' . esc_js(__('This will create a backup of all your Meilisearch data. Continue?', 'meilisearch')) . '");']); ?>
					</form>

					<hr style="margin: 30px 0;">

					<h3><?php esc_html_e('Automatic Backups', 'meilisearch'); ?></h3>
					<?php $this->render_scheduled_backups_section(); ?>
				</div>

				<hr style="margin: 40px 0;">

				<!-- Snapshots Section -->
				<div class="backup-section">
					<h2><?php esc_html_e('Snapshots', 'meilisearch'); ?></h2>
					<p>
						<?php esc_html_e('Create a snapshot of the current database state (self-hosted instances only).', 'meilisearch'); ?>
						<br>
						<em><?php esc_html_e('Note: Snapshots are not available on Meilisearch Cloud.', 'meilisearch'); ?></em>
					</p>
					
					<form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=meilisearch_create_snapshot')); ?>" style="margin-top: 20px;">
						<?php wp_nonce_field('create_snapshot', 'meilisearch_snapshot_nonce'); ?>
						
						<?php submit_button(__('Create Snapshot Now', 'meilisearch'), 'secondary', 'submit', true, ['onclick' => 'return confirm("' . esc_js(__('This will create a snapshot of your Meilisearch database. Continue?', 'meilisearch')) . '");']); ?>
					</form>
				</div>

				<hr style="margin: 40px 0;">

				<!-- Recent Tasks -->
				<div class="backup-section">
					<h2><?php esc_html_e('Recent Backup Tasks', 'meilisearch'); ?></h2>
					<?php $this->render_recent_backup_tasks(); ?>
				</div>

			<?php endif; ?>
		</div>

		<style>
			.backup-section {
				background: #fff;
				padding: 20px;
				border: 1px solid #ccd0d4;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
				margin-bottom: 20px;
			}
			.backup-section h2 {
				margin-top: 0;
			}
			.task-table {
				margin-top: 20px;
			}
			.task-status-succeeded {
				color: #46b450;
				font-weight: bold;
			}
			.task-status-failed {
				color: #dc3232;
				font-weight: bold;
			}
			.task-status-processing {
				color: #f0b849;
				font-weight: bold;
			}
			.task-status-enqueued {
				color: #2271b1;
				font-weight: bold;
			}
		</style>
		<?php
	}

	/**
	 * Renderizar seção de backups agendados.
	 */
	private function render_scheduled_backups_section(): void
	{
		$schedule_enabled = get_site_option('meilisearch_backup_schedule_enabled', false);
		$schedule_frequency = get_site_option('meilisearch_backup_schedule_frequency', 'daily');

		?>
		<form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=meilisearch_update_backup_schedule')); ?>">
			<?php wp_nonce_field('update_backup_schedule', 'meilisearch_schedule_nonce'); ?>
			
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e('Enable Automatic Backups', 'meilisearch'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="schedule_enabled" value="1" <?php checked($schedule_enabled, true); ?>>
							<?php esc_html_e('Enable scheduled backups', 'meilisearch'); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Frequency', 'meilisearch'); ?></th>
					<td>
						<select name="schedule_frequency">
							<option value="hourly" <?php selected($schedule_frequency, 'hourly'); ?>><?php esc_html_e('Hourly', 'meilisearch'); ?></option>
							<option value="twicedaily" <?php selected($schedule_frequency, 'twicedaily'); ?>><?php esc_html_e('Twice Daily', 'meilisearch'); ?></option>
							<option value="daily" <?php selected($schedule_frequency, 'daily'); ?>><?php esc_html_e('Daily', 'meilisearch'); ?></option>
							<option value="weekly" <?php selected($schedule_frequency, 'weekly'); ?>><?php esc_html_e('Weekly', 'meilisearch'); ?></option>
						</select>
					</td>
				</tr>
			</table>

			<?php submit_button(__('Save Schedule Settings', 'meilisearch'), 'secondary'); ?>
		</form>

		<?php
		if ($schedule_enabled) {
			$next_scheduled = wp_next_scheduled('meilisearch_scheduled_backup');
			if ($next_scheduled) {
				echo '<p class="description">';
				printf(
					/* translators: %s: formatted date and time */
					esc_html__('Next scheduled backup: %s', 'meilisearch'),
					esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled))
				);
				echo '</p>';
			}
		}
	}

	/**
	 * Renderizar tarefas recentes de backup.
	 */
	private function render_recent_backup_tasks(): void
	{
		$client = $this->get_client();
		if (null === $client) {
			return;
		}

		$tasks = $client->get_recent_tasks(20);
		
		// Filtrar apenas tarefas de dump e snapshot
		$backup_tasks = array_filter($tasks, function($task) {
			return in_array($task['type'], ['dumpCreation', 'snapshotCreation'], true);
		});

		if (empty($backup_tasks)) {
			echo '<p>' . esc_html__('No recent backup tasks found.', 'meilisearch') . '</p>';
			return;
		}

		?>
		<table class="wp-list-table widefat fixed striped task-table">
			<thead>
				<tr>
					<th><?php esc_html_e('Task UID', 'meilisearch'); ?></th>
					<th><?php esc_html_e('Type', 'meilisearch'); ?></th>
					<th><?php esc_html_e('Status', 'meilisearch'); ?></th>
					<th><?php esc_html_e('Enqueued At', 'meilisearch'); ?></th>
					<th><?php esc_html_e('Duration', 'meilisearch'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($backup_tasks as $task): ?>
				<tr>
					<td><code><?php echo esc_html($task['uid']); ?></code></td>
					<td>
						<?php
						$type_label = 'dumpCreation' === $task['type'] ? __('Dump', 'meilisearch') : __('Snapshot', 'meilisearch');
						echo esc_html($type_label);
						?>
					</td>
					<td class="task-status-<?php echo esc_attr(strtolower($task['status'])); ?>">
						<?php echo esc_html(ucfirst($task['status'])); ?>
					</td>
					<td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($task['enqueuedAt']))); ?></td>
					<td>
						<?php
						if (isset($task['duration'])) {
							echo esc_html($task['duration']);
						} else {
							echo '—';
						}
						?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Criar dump.
	 */
	public function create_dump(): void
	{
		check_admin_referer('create_dump', 'meilisearch_dump_nonce');

		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		try {
			$client = $this->get_client();
			if (null === $client) {
				throw new Exception(__('Unable to connect to Meilisearch.', 'meilisearch'));
			}

			$task = $client->get_client()->createDump();
			
			// Registrar backup no WordPress
			$this->log_backup('dump', $task['taskUid']);

			wp_redirect(
				add_query_arg(
					[
						'page' => 'meilisearch-backup',
						'dump_created' => 'true',
						'task_uid' => $task['taskUid'],
					],
					network_admin_url('admin.php')
				)
			);
		} catch (Exception $e) {
			wp_redirect(
				add_query_arg(
					[
						'page' => 'meilisearch-backup',
						'error' => urlencode($e->getMessage()),
					],
					network_admin_url('admin.php')
				)
			);
		}

		exit;
	}

	/**
	 * Criar snapshot.
	 */
	public function create_snapshot(): void
	{
		check_admin_referer('create_snapshot', 'meilisearch_snapshot_nonce');

		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		try {
			$client = $this->get_client();
			if (null === $client) {
				throw new Exception(__('Unable to connect to Meilisearch.', 'meilisearch'));
			}

			$task = $client->get_client()->createSnapshot();
			
			// Registrar backup no WordPress
			$this->log_backup('snapshot', $task['taskUid']);

			wp_redirect(
				add_query_arg(
					[
						'page' => 'meilisearch-backup',
						'snapshot_created' => 'true',
						'task_uid' => $task['taskUid'],
					],
					network_admin_url('admin.php')
				)
			);
		} catch (Exception $e) {
			wp_redirect(
				add_query_arg(
					[
						'page' => 'meilisearch-backup',
						'error' => urlencode($e->getMessage()),
					],
					network_admin_url('admin.php')
				)
			);
		}

		exit;
	}

	/**
	 * Registrar backup no log do WordPress.
	 *
	 * @param string $type Tipo de backup (dump ou snapshot).
	 * @param int    $task_uid UID da tarefa.
	 */
	private function log_backup(string $type, int $task_uid): void
	{
		$backups = get_site_option('meilisearch_backup_log', []);
		
		$backups[] = [
			'type' => $type,
			'task_uid' => $task_uid,
			'timestamp' => current_time('timestamp'),
			'user_id' => get_current_user_id(),
		];

		// Manter apenas os últimos 50 registros
		if (count($backups) > 50) {
			$backups = array_slice($backups, -50);
		}

		update_site_option('meilisearch_backup_log', $backups);
	}

	/**
	 * Atualizar configurações de agendamento de backup.
	 */
	public function update_backup_schedule(): void
	{
		check_admin_referer('update_backup_schedule', 'meilisearch_schedule_nonce');

		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$schedule_enabled = isset($_POST['schedule_enabled']) && '1' === $_POST['schedule_enabled'];
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$schedule_frequency = isset($_POST['schedule_frequency']) ? sanitize_text_field(wp_unslash($_POST['schedule_frequency'])) : 'daily';

		// Validar frequência
		if (!in_array($schedule_frequency, ['hourly', 'twicedaily', 'daily', 'weekly'], true)) {
			$schedule_frequency = 'daily';
		}

		// Atualizar opções
		update_site_option('meilisearch_backup_schedule_enabled', $schedule_enabled);
		update_site_option('meilisearch_backup_schedule_frequency', $schedule_frequency);

		// Limpar agendamento existente
		$timestamp = wp_next_scheduled('meilisearch_scheduled_backup');
		if ($timestamp) {
			wp_unschedule_event($timestamp, 'meilisearch_scheduled_backup');
		}

		// Criar novo agendamento se habilitado
		if ($schedule_enabled) {
			wp_schedule_event(time(), $schedule_frequency, 'meilisearch_scheduled_backup');
		}

		wp_redirect(
			add_query_arg(
				[
					'page' => 'meilisearch-backup',
					'schedule_updated' => 'true',
				],
				network_admin_url('admin.php')
			)
		);

		exit;
	}

	/**
	 * Executar backup agendado.
	 */
	public function run_scheduled_backup(): void
	{
		$schedule_enabled = get_site_option('meilisearch_backup_schedule_enabled', false);
		
		if (!$schedule_enabled) {
			return;
		}

		try {
			$client = $this->get_client();
			if (null === $client) {
				return;
			}

			$task = $client->get_client()->createDump();
			$this->log_backup('dump', $task['taskUid']);

			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log('Meilisearch scheduled backup created successfully. Task UID: ' . $task['taskUid']);
			}
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log('Meilisearch scheduled backup error: ' . $e->getMessage());
			}
		}
	}
}
