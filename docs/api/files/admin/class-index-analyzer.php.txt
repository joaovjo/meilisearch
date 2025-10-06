<?php

declare(strict_types=1);

/**
 * Meilisearch Index Analyzer Page
 *
 * @package Meilisearch
 */

/**
 * Class Meilisearch_Index_Analyzer
 *
 * Analyzes all Meilisearch indexes to identify WordPress networks and their index naming patterns.
 */
class Meilisearch_Index_Analyzer
{
	/**
	 * Meilisearch client instance.
	 *
	 * @var Meilisearch_Client|null
	 */
	private null|Meilisearch_Client $client = null;

	/**
	 * Initialize WordPress hooks.
	 */
	public function init_hooks(): void
	{
		add_action('network_admin_menu', [$this, 'add_network_menu']);
	}

	/**
	 * Add network admin menu item.
	 */
	public function add_network_menu(): void
	{
		add_submenu_page(
			'meilisearch-dashboard',
			__('Index Analyzer', 'meilisearch'),
			__('Index Analyzer', 'meilisearch'),
			'manage_network_options',
			'meilisearch-index-analyzer',
			[$this, 'render_analyzer_page'],
		);
	}

	/**
	 * Get Meilisearch client instance.
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
	 * Get all indexes from Meilisearch server.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_all_indexes(): array
	{
		$client = $this->get_client();
		if (null === $client) {
			return [];
		}

		try {
			$indexes_results = $client->get_client()->getIndexes();
			$indexes = $indexes_results->getResults();
			$index_list = [];

			foreach ($indexes as $index) {
				$index_list[] = [
					'uid' => $index->getUid(),
					'primary_key' => $index->getPrimaryKey(),
					'created_at' => $index->getCreatedAt()?->format('Y-m-d H:i:s'),
				];
			}

			return $index_list;
		} catch (Exception $e) {
			return [];
		}
	}

	/**
	 * Parse index name to extract pattern components.
	 *
	 * @param string $index_name Index name to parse.
	 * @return array<string, mixed> Parsed components.
	 */
	private function parse_index_name(string $index_name): array
	{
		$parsed = [
			'prefix' => null,
			'blog_id' => null,
			'suffix' => null,
			'pattern' => null,
		];

		// Try to match common patterns
		// Pattern 1: wp_posts, wp_2_posts, wp_3_posts (prefix format)
		if (preg_match('/^(wp_)(\d+)_(.+)$/', $index_name, $matches)) {
			$parsed['prefix'] = $matches[1] . $matches[2] . '_';
			$parsed['blog_id'] = (int) $matches[2];
			$parsed['suffix'] = $matches[3];
			$parsed['pattern'] = '{prefix}' . $matches[3];
		} elseif (preg_match('/^(wp_)(.+)$/', $index_name, $matches)) {
			$parsed['prefix'] = $matches[1];
			$parsed['suffix'] = $matches[2];
			$parsed['pattern'] = '{prefix}' . $matches[2];
		}
		// Pattern 2: site_1_posts, site_2_posts (blog_id format)
		elseif (preg_match('/^([a-z_]+)_(\d+)_(.+)$/', $index_name, $matches)) {
			$parsed['prefix'] = $matches[1] . '_';
			$parsed['blog_id'] = (int) $matches[2];
			$parsed['suffix'] = $matches[3];
			$parsed['pattern'] = $matches[1] . '_{blog_id}_' . $matches[3];
		}
		// Pattern 3: simple format without numbers
		else {
			$parsed['pattern'] = $index_name;
			$parsed['suffix'] = $index_name;
		}

		return $parsed;
	}

	/**
	 * Analyze indexes and group by network pattern.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function analyze_index_patterns(): array
	{
		$indexes = $this->get_all_indexes();
		$patterns = [];

		foreach ($indexes as $index) {
			$parsed = $this->parse_index_name($index['uid']);
			$pattern = $parsed['pattern'] ?? 'unknown';

			if (!isset($patterns[$pattern])) {
				$patterns[$pattern] = [
					'pattern' => $pattern,
					'indexes' => [],
					'blog_ids' => [],
					'network_url' => null,
					'count' => 0,
				];
			}

			$patterns[$pattern]['indexes'][] = $index['uid'];
			$patterns[$pattern]['count']++;

			if (null !== $parsed['blog_id']) {
				$patterns[$pattern]['blog_ids'][] = $parsed['blog_id'];
			}
		}

		// Try to identify network URLs for each pattern
		foreach ($patterns as $pattern => &$data) {
			if (!empty($data['indexes'])) {
				$network_url = $this->get_network_url_for_pattern($data['blog_ids'], $data['indexes']);
				$data['network_url'] = $network_url;
				
				// Get site names from Meilisearch data
				$site_names = $this->get_site_names_from_indexes($data['indexes']);
				$data['site_names'] = $site_names;
			}
		}

		return $patterns;
	}

	/**
	 * Get network URL for a set of blog IDs and index names.
	 *
	 * @param array<int>    $blog_ids    Blog IDs to check.
	 * @param array<string> $index_names Index names from this pattern.
	 * @return string|null Network URL or null if not found.
	 */
	private function get_network_url_for_pattern(array $blog_ids, array $index_names): null|string
	{
		if (empty($index_names)) {
			return null;
		}

		$client = $this->get_client();
		if (null === $client) {
			return null;
		}

		// Try each index name until we find one with documents
		foreach ($index_names as $index_name) {
			try {
				// Get a sample document from the index to extract the permalink
				$results = $client->get_client()
					->index($index_name)
					->search('', ['limit' => 1]);
				
				// Get hits from the SearchResult object
				$hits = $results->getHits();
				
				if (!empty($hits)) {
					$document = $hits[0];
					
					// Extract URL from permalink field
					if (isset($document['permalink'])) {
						$parsed = parse_url($document['permalink']);
						if ($parsed && isset($parsed['scheme'], $parsed['host'])) {
							$url = $parsed['scheme'] . '://' . $parsed['host'];
							
							// Include port if present
							if (isset($parsed['port'])) {
								$url .= ':' . $parsed['port'];
							}
							
							return $url;
						}
					}
				}
			} catch (Exception $e) {
				// Index doesn't exist or has no documents, try next index
				continue;
			}
		}

		return null;
	}

	/**
	 * Get site names for blog IDs using Meilisearch data.
	 *
	 * @param array<string> $index_names Index names from this pattern.
	 * @return array<int, string> Array of blog_id => site_name.
	 */
	private function get_site_names_from_indexes(array $index_names): array
	{
		$client = $this->get_client();
		if (null === $client) {
			return [];
		}

		$site_names = [];

		// Try each index name to extract site information
		foreach ($index_names as $index_name) {
			try {
				// Get a sample document from the index
				$results = $client->get_client()
					->index($index_name)
					->search('', ['limit' => 1]);
				
				$hits = $results->getHits();
				
				if (!empty($hits)) {
					$document = $hits[0];
					
					// Extract blog_id and site name from document
					if (isset($document['blog_id'])) {
						$blog_id = (int) $document['blog_id'];
						
						// Try to get site name from permalink
						if (isset($document['permalink'])) {
							$parsed = parse_url($document['permalink']);
							if ($parsed && isset($parsed['host'])) {
								$site_name = $parsed['host'];
								
								// Add path if it's a subdirectory site
								if (isset($parsed['path']) && $parsed['path'] !== '/') {
									$path_parts = explode('/', trim($parsed['path'], '/'));
									if (!empty($path_parts[0])) {
										$site_name .= '/' . $path_parts[0];
									}
								}
								
								$site_names[$blog_id] = $site_name;
							}
						}
					}
				}
			} catch (Exception $e) {
				// Index doesn't exist or has no documents, skip
				continue;
			}
		}

		return $site_names;
	}

	/**
	 * Render analyzer page.
	 */
	public function render_analyzer_page(): void
	{
		if (!current_user_can('manage_network_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'meilisearch'));
		}

		$client = $this->get_client();
		if (null === $client) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e('Meilisearch Index Analyzer', 'meilisearch'); ?></h1>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e('Error:', 'meilisearch'); ?></strong>
						<?php esc_html_e('Meilisearch client not configured', 'meilisearch'); ?>
					</p>
				</div>
			</div>
			<?php
			return;
		}

		$patterns = $this->analyze_index_patterns();
		
		// Get current network URL for comparison
		$current_network_url = untrailingslashit(network_site_url());
		
		// Sort patterns to put current network first
		uasort($patterns, function($a, $b) use ($current_network_url) {
			$a_is_current = ($a['network_url'] && $a['network_url'] === $current_network_url);
			$b_is_current = ($b['network_url'] && $b['network_url'] === $current_network_url);
			
			// Current network comes first
			if ($a_is_current && !$b_is_current) {
				return -1;
			}
			if (!$a_is_current && $b_is_current) {
				return 1;
			}
			
			// Otherwise sort alphabetically by pattern
			return strcmp($a['pattern'], $b['pattern']);
		});

		?>
		<div class="wrap">
			<h1><?php esc_html_e('Meilisearch Index Analyzer', 'meilisearch'); ?></h1>
			<p><?php esc_html_e('Analysis of all indexes in Meilisearch server to identify WordPress networks and their naming patterns (not cached)', 'meilisearch'); ?></p>

			<?php if (empty($patterns)): ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e('No indexes found in Meilisearch server.', 'meilisearch'); ?></p>
				</div>
			<?php else: ?>

				<h2><?php esc_html_e('Detected Network Patterns', 'meilisearch'); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 30%;"><?php esc_html_e('Index Name Pattern', 'meilisearch'); ?></th>
							<th style="width: 40%;"><?php esc_html_e('Network URL', 'meilisearch'); ?></th>
							<th style="width: 15%;"><?php esc_html_e('Sites Count', 'meilisearch'); ?></th>
							<th style="width: 15%;"><?php esc_html_e('Total Indexes', 'meilisearch'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($patterns as $pattern_data): ?>
							<?php 
							$is_current_network = ($pattern_data['network_url'] && $pattern_data['network_url'] === $current_network_url);
							$row_class = $is_current_network ? 'current-network-row' : '';
							?>
							<tr class="<?php echo esc_attr($row_class); ?>">
								<td>
									<strong><code><?php echo esc_html($pattern_data['pattern']); ?></code></strong>
									<?php if ($is_current_network): ?>
										<span class="current-network-badge"><?php esc_html_e('Current Network', 'meilisearch'); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ($pattern_data['network_url']): ?>
										<a href="<?php echo esc_url($pattern_data['network_url']); ?>" target="_blank" rel="noopener noreferrer">
											<?php echo esc_html($pattern_data['network_url']); ?>
											<span class="dashicons dashicons-external" style="font-size: 14px; vertical-align: middle;"></span>
										</a>
									<?php else: ?>
										<span style="color: #999;">
											<?php esc_html_e('Network URL not detected', 'meilisearch'); ?>
										</span>
									<?php endif; ?>
								</td>
								<td>
									<?php echo esc_html(count(array_unique($pattern_data['blog_ids']))); ?>
								</td>
								<td>
									<strong><?php echo esc_html($pattern_data['count']); ?></strong>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<!-- Detailed breakdown by pattern -->
				<h2 style="margin-top: 30px;"><?php esc_html_e('Index Details by Pattern', 'meilisearch'); ?></h2>
				
				<?php foreach ($patterns as $pattern_data): ?>
					<?php 
					$is_current_network = ($pattern_data['network_url'] && $pattern_data['network_url'] === $current_network_url);
					$postbox_class = $is_current_network ? 'postbox current-network-postbox' : 'postbox';
					?>
					<div class="<?php echo esc_attr($postbox_class); ?>" style="margin-bottom: 20px;">
						<div class="postbox-header">
							<h3 class="hndle">
								<code><?php echo esc_html($pattern_data['pattern']); ?></code>
								<span style="color: #666; font-weight: normal; font-size: 12px; margin-left: 10px;">
									(<?php
									/* translators: %d: number of indexes */
									printf(esc_html__('%d indexes', 'meilisearch'), $pattern_data['count']);
									?>)
								</span>
								<?php if ($is_current_network): ?>
									<span class="current-network-badge" style="margin-left: 10px;"><?php esc_html_e('Current Network', 'meilisearch'); ?></span>
								<?php endif; ?>
							</h3>
						</div>
						<div class="inside">
							<table class="widefat">
								<tr>
									<td style="width: 30%;"><strong><?php esc_html_e('Pattern Format', 'meilisearch'); ?></strong></td>
									<td><code><?php echo esc_html($pattern_data['pattern']); ?></code></td>
								</tr>
								<tr>
									<td><strong><?php esc_html_e('Network URL', 'meilisearch'); ?></strong></td>
									<td>
										<?php if ($pattern_data['network_url']): ?>
											<a href="<?php echo esc_url($pattern_data['network_url']); ?>" target="_blank" rel="noopener noreferrer">
												<?php echo esc_html($pattern_data['network_url']); ?>
											</a>
										<?php else: ?>
											<?php esc_html_e('Not detected', 'meilisearch'); ?>
										<?php endif; ?>
									</td>
								</tr>
								<tr>
									<td><strong><?php esc_html_e('Unique Sites', 'meilisearch'); ?></strong></td>
									<td><?php echo esc_html(count(array_unique($pattern_data['blog_ids']))); ?></td>
								</tr>
								<tr>
									<td><strong><?php esc_html_e('Total Indexes', 'meilisearch'); ?></strong></td>
									<td><?php echo esc_html($pattern_data['count']); ?></td>
								</tr>
								<?php if (!empty($pattern_data['site_names'])): ?>
								<tr>
									<td><strong><?php esc_html_e('Sites', 'meilisearch'); ?></strong></td>
									<td>
										<details>
											<summary style="cursor: pointer;">
												<?php
												/* translators: %d: number of sites */
												printf(esc_html__('View %d sites', 'meilisearch'), count($pattern_data['site_names']));
												?>
											</summary>
											<ul style="margin-top: 10px; max-height: 300px; overflow-y: auto;">
												<?php foreach ($pattern_data['site_names'] as $blog_id => $site_name): ?>
													<li>
														<strong><?php echo esc_html($site_name); ?></strong>
														<span style="color: #666; font-size: 12px;">(Blog ID: <?php echo esc_html($blog_id); ?>)</span>
													</li>
												<?php endforeach; ?>
											</ul>
										</details>
									</td>
								</tr>
								<?php endif; ?>
								<tr>
									<td><strong><?php esc_html_e('Index Names', 'meilisearch'); ?></strong></td>
									<td>
										<details>
											<summary style="cursor: pointer;">
												<?php esc_html_e('View all index names', 'meilisearch'); ?>
											</summary>
											<ul style="margin-top: 10px; max-height: 300px; overflow-y: auto;">
												<?php foreach ($pattern_data['indexes'] as $index_name): ?>
													<li><code><?php echo esc_html($index_name); ?></code></li>
												<?php endforeach; ?>
											</ul>
										</details>
									</td>
								</tr>
							</table>
						</div>
					</div>
				<?php endforeach; ?>

				<!-- Summary Statistics -->
				<div class="postbox" style="margin-top: 30px;">
					<div class="postbox-header">
						<h3 class="hndle"><?php esc_html_e('Summary Statistics', 'meilisearch'); ?></h3>
					</div>
					<div class="inside">
						<table class="widefat">
							<tr>
								<td style="width: 50%;"><strong><?php esc_html_e('Total Detected Networks', 'meilisearch'); ?></strong></td>
								<td><?php echo esc_html(count($patterns)); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e('Total Indexes in Meilisearch', 'meilisearch'); ?></strong></td>
								<td><?php echo esc_html(array_sum(array_column($patterns, 'count'))); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e('Current WordPress Network', 'meilisearch'); ?></strong></td>
								<td>
									<?php
									$current_network = network_site_url();
									echo esc_html($current_network);
									?>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Refresh Button -->
				<div style="margin-top: 30px;">
					<p>
						<a href="<?php echo esc_url(add_query_arg('refresh', time())); ?>" class="button button-primary">
							<?php esc_html_e('Refresh Analysis', 'meilisearch'); ?>
						</a>
						<span style="margin-left: 10px; color: #666;">
							<?php
							printf(
								/* translators: %s: current time */
								esc_html__('Last analyzed: %s', 'meilisearch'),
								esc_html(current_time('Y-m-d H:i:s')),
							);
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
				background: #f9f9f9;
				padding: 10px;
				border: 1px solid #ddd;
				border-radius: 3px;
			}
			details ul li {
				padding: 5px 0;
				border-bottom: 1px solid #eee;
			}
			details ul li:last-child {
				border-bottom: none;
			}
			.wp-list-table code {
				background: #f0f0f0;
				padding: 2px 6px;
				border-radius: 3px;
				font-size: 13px;
			}
			
			/* Current Network Styles */
			.current-network-badge {
				display: inline-block;
				background: #2271b1;
				color: #fff;
				padding: 2px 8px;
				border-radius: 3px;
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
				margin-left: 8px;
				vertical-align: middle;
			}
			.current-network-row {
				background-color: #e7f5ff !important;
				border-left: 3px solid #2271b1;
			}
			.current-network-row td {
				font-weight: 500;
			}
			.current-network-postbox {
				border: 2px solid #2271b1 !important;
				box-shadow: 0 1px 3px rgba(34, 113, 177, 0.2);
			}
			.current-network-postbox .postbox-header {
				background: #e7f5ff;
				border-bottom: 2px solid #2271b1;
			}
		</style>
		<?php
	}
}
