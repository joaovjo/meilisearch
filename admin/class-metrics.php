<?php

declare(strict_types=1);

/**
 * Página de Métricas Meilisearch
 *
 * @package Meilisearch
 */

/**
 * Classe Meilisearch_Metrics
 *
 * Exibe métricas em tempo real do servidor Meilisearch.
 */
class Meilisearch_Metrics
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
	}

	/**
	 * Adicionar item de menu da administração de rede.
	 */
	public function add_network_menu(): void
	{
		add_submenu_page(
			'meilisearch-dashboard',
			__('Metrics', 'meilisearch'),
			__('Metrics', 'meilisearch'),
			'manage_network_options',
			'meilisearch-metrics',
			[$this, 'render_metrics_page'],
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
	 * Obter estatísticas globais do Meilisearch.
	 *
	 * @return array<string, mixed>
	 */
	private function get_global_stats(): array
	{
		$client = $this->get_client();
		if (null === $client) {
			return ['error' => __('Meilisearch client not configured', 'meilisearch')];
		}

		try {
			$stats = $client->get_client()->stats();
			return $stats;
		} catch (Exception $e) {
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * Obter estatísticas de todos os índices baseado no formato configurado.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_all_indexes_stats(): array
	{
		$client = $this->get_client();
		if (null === $client) {
			return [];
		}

		try {
			$sites = get_sites(['number' => 9999]);
			$stats = [];

			foreach ($sites as $site) {
				$blog_id = (int) $site->blog_id;
				$index_name = $client->get_index_name($blog_id);

				try {
					$index = $client->get_client()->index($index_name);
					
					// Buscar informações atualizadas do índice
					$index->fetchInfo();
					
					$index_stats = $index->stats();
					
					// Obter dados do índice
					$primary_key = $index->getPrimaryKey();
					$created_at = $index->getCreatedAt();
					$updated_at = $index->getUpdatedAt();
					
					$stats[] = [
						'uid' => $index->getUid(),
						'blog_id' => $blog_id,
						'primary_key' => $primary_key ?? 'N/A',
						'created_at' => $created_at ? $created_at->format('Y-m-d H:i:s') : 'N/A',
						'updated_at' => $updated_at ? $updated_at->format('Y-m-d H:i:s') : 'N/A',
						'stats' => $index_stats,
					];
				} catch (Exception $e) {
					// Índice não existe para este site, pulá-lo
					continue;
				}
			}

			return $stats;
		} catch (Exception $e) {
			return [];
		}
	}

	/**
	 * Formatar bytes para tamanho legível.
	 *
	 * @param int $bytes Bytes para formatar.
	 * @return string Tamanho formatado.
	 */
	private function format_bytes(int $bytes): string
	{
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];
		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);
		$bytes /= 1024 ** $pow;

		return round($bytes, 2) . ' ' . $units[$pow];
	}

	/**
	 * Renderizar página de métricas.
	 */
	public function render_metrics_page(): void
	{
		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		$global_stats = $this->get_global_stats();
		$indexes_stats = $this->get_all_indexes_stats();

		?>
		<div class="wrap">
			<h1><?php esc_html_e('Meilisearch Metrics', 'meilisearch'); ?></h1>
			<p><?php esc_html_e('Real-time metrics from Meilisearch server (not cached)', 'meilisearch'); ?></p>

			<?php if (isset($global_stats['error'])): ?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e('Error:', 'meilisearch'); ?></strong>
						<?php echo esc_html($global_stats['error']); ?>
					</p>
				</div>
			<?php else: ?>

				<!-- Global Stats -->
				<h2><?php esc_html_e('Global Statistics', 'meilisearch'); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e('Metric', 'meilisearch'); ?></th>
							<th><?php esc_html_e('Value', 'meilisearch'); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong><?php esc_html_e('Total Database Size', 'meilisearch'); ?></strong></td>
							<td><?php echo esc_html($this->format_bytes($global_stats['databaseSize'] ?? 0)); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e('Last Update', 'meilisearch'); ?></strong></td>
							<td><?php echo esc_html($global_stats['lastUpdate'] ?? 'N/A'); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e('Total Indexes', 'meilisearch'); ?></strong></td>
							<td><?php echo esc_html(count($global_stats['indexes'] ?? [])); ?></td>
						</tr>
					</tbody>
				</table>

				<!-- Indexes Stats -->
				<h2 style="margin-top: 30px;"><?php esc_html_e('Indexes Statistics', 'meilisearch'); ?></h2>
				
				<?php if (empty($indexes_stats)): ?>
					<p><?php esc_html_e('No indexes found.', 'meilisearch'); ?></p>
				<?php else: ?>
					<?php foreach ($indexes_stats as $index): ?>
						<div class="postbox" style="margin-bottom: 20px;">
							<div class="postbox-header">
								<h3 class="hndle">
									<?php echo esc_html($index['uid']); ?>
									<span style="color: #666; font-weight: normal; font-size: 12px;">
										(<?php
										/* translators: %d: site/blog ID */
										printf(esc_html__('Site ID: %d', 'meilisearch'), $index['blog_id']);
										?>)
									</span>
								</h3>
							</div>
							<div class="inside">
								<table class="widefat">
									<tr>
										<td style="width: 30%;"><strong><?php esc_html_e('Primary Key', 'meilisearch'); ?></strong></td>
										<td><?php echo esc_html($index['primary_key'] ?? 'N/A'); ?></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e('Created At', 'meilisearch'); ?></strong></td>
										<td><?php echo esc_html($index['created_at'] ?? 'N/A'); ?></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e('Updated At', 'meilisearch'); ?></strong></td>
										<td><?php echo esc_html($index['updated_at'] ?? 'N/A'); ?></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e('Number of Documents', 'meilisearch'); ?></strong></td>
										<td><?php echo esc_html(number_format($index['stats']['numberOfDocuments'] ?? 0)); ?></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e('Is Indexing', 'meilisearch'); ?></strong></td>
										<td>
											<?php if ($index['stats']['isIndexing'] ?? false): ?>
												<span style="color: orange;">⏳ <?php esc_html_e('Yes', 'meilisearch'); ?></span>
											<?php else: ?>
												<span style="color: green;">✓ <?php esc_html_e('No', 'meilisearch'); ?></span>
											<?php endif; ?>
										</td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e('Field Distribution', 'meilisearch'); ?></strong></td>
										<td>
											<?php if (!empty($index['stats']['fieldDistribution'])): ?>
												<details>
													<summary style="cursor: pointer;"><?php esc_html_e('View Details', 'meilisearch'); ?></summary>
													<ul style="margin-top: 10px;">
														<?php foreach ($index['stats']['fieldDistribution'] as $field => $count): ?>
															<li><code><?php echo esc_html($field); ?></code>: <?php echo esc_html(number_format($count)); ?></li>
														<?php endforeach; ?>
													</ul>
												</details>
											<?php else: ?>
												<?php esc_html_e('No field distribution available', 'meilisearch'); ?>
											<?php endif; ?>
										</td>
									</tr>
								</table>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>

				<!-- Refresh Button -->
				<div style="margin-top: 30px;">
					<p>
						<a href="<?php echo esc_url(add_query_arg('refresh', time())); ?>" class="button button-primary">
							<?php esc_html_e('Refresh Metrics', 'meilisearch'); ?>
					</a>
					<span style="margin-left: 10px; color: #666;">
						<?php
						printf(
							/* translators: %s: current time */
							esc_html__('Last updated: %s', 'meilisearch'),
							esc_html(current_time('Y-m-d H:i:s'))
						);
						?>

							?>
						</span>
					</p>
				</div>

			<?php endif; ?>
		</div>

		<style>
			.postbox {
				border: 1px solid #c3c4c7;
			}
			.postbox-header {
				background: #f6f7f7;
				border-bottom: 1px solid #c3c4c7;
				padding: 10px 15px;
			}
			.postbox-header h3 {
				margin: 0;
				font-size: 14px;
			}
			.postbox .inside {
				padding: 15px;
			}
			.postbox .inside table {
				margin: 0;
			}
			.postbox .inside table td {
				padding: 10px;
			}
			details summary {
				font-weight: 600;
			}
			details ul {
				margin-left: 20px;
			}
		</style>
		<?php
	}
}
