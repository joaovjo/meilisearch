<?php

/**
 * Plugin Name:     Meilisearch Network Search
 * Plugin URI:      https://github.com/joaovjo/meilisearch
 * Description:     Replace WordPress search with Meilisearch across your entire multisite network with automatic autocomplete. Network-only configuration.
 * Author:          joaovjo
 * Author URI:      https://github.com/joaovjo
 * Text Domain:     meilisearch
 * Domain Path:     /languages
 * Version:         0.1.0
 * Network:         true
 * Requires PHP:    8.1
 * Requires at least: 6.0
 * License:         GPL-2.0-or-later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package         Meilisearch
 */

// Sair se acessado diretamente.
if (!defined('ABSPATH')) {
	exit();
}

// Iniciar buffer de saída para prevenir qualquer saída acidental de arquivos vendor.
ob_start();

// Constantes do plugin.
define('MEILISEARCH_VERSION', '0.1.0');
define('MEILISEARCH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MEILISEARCH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MEILISEARCH_PLUGIN_FILE', __FILE__);

// Requer autoloader do Composer.
if (file_exists(MEILISEARCH_PLUGIN_DIR . 'vendor/autoload.php')) {
	require_once MEILISEARCH_PLUGIN_DIR . 'vendor/autoload.php';
}

// Limpar qualquer saída do autoload do vendor.
$vendor_output = ob_get_clean();
if (!empty($vendor_output) && defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log de debug apenas.
	error_log('Meilisearch vendor output: ' . $vendor_output);
}

// Incluir classes principais.
require_once MEILISEARCH_PLUGIN_DIR . 'includes/class-client.php';
require_once MEILISEARCH_PLUGIN_DIR . 'includes/class-indexer.php';
require_once MEILISEARCH_PLUGIN_DIR . 'includes/class-searcher.php';
require_once MEILISEARCH_PLUGIN_DIR . 'includes/class-autocomplete.php';
require_once MEILISEARCH_PLUGIN_DIR . 'includes/class-search-api.php';
require_once MEILISEARCH_PLUGIN_DIR . 'includes/class-search-integration.php';

// Incluir classe de configurações de pesquisa (necessária no frontend e admin).
require_once MEILISEARCH_PLUGIN_DIR . 'admin/class-search-settings.php';

// Incluir classes admin.
if (is_admin() && is_multisite()) {
	require_once MEILISEARCH_PLUGIN_DIR . 'admin/class-dashboard.php';
	require_once MEILISEARCH_PLUGIN_DIR . 'admin/class-metrics.php';
	require_once MEILISEARCH_PLUGIN_DIR . 'admin/class-index-analyzer.php';
	require_once MEILISEARCH_PLUGIN_DIR . 'admin/class-multi-pattern-search.php';
	require_once MEILISEARCH_PLUGIN_DIR . 'admin/class-network-settings.php';
	require_once MEILISEARCH_PLUGIN_DIR . 'admin/class-tasks-monitor.php';
	require_once MEILISEARCH_PLUGIN_DIR . 'admin/class-backup-restore.php';
	require_once MEILISEARCH_PLUGIN_DIR . 'admin/class-federated-search.php';
}

// Incluir classes públicas.
if (!is_admin()) {
	require_once MEILISEARCH_PLUGIN_DIR . 'public/class-search-override.php';
	require_once MEILISEARCH_PLUGIN_DIR . 'public/class-search-shortcode.php';
}

// Incluir comandos WP-CLI.
if (defined('WP_CLI') && WP_CLI) {
	require_once MEILISEARCH_PLUGIN_DIR . 'includes/class-cli.php';
}

/**
 * Hook de ativação do Plugin.
 * Ativação somente em rede.
 */
function meilisearch_activate(bool $network_wide): void
{
	if (!$network_wide) {
		wp_die(
			esc_html__('This plugin must be network activated.', 'meilisearch'),
			esc_html__('Network Activation Required', 'meilisearch'),
			['back_link' => true],
		);
	}

	// Definir opções padrão.
	$default_settings = [
		'host' => 'http://localhost:7700',
		'master_key' => '',
		'enabled' => false,
		'post_types' => ['post', 'page'],
	];

	if (!get_site_option('meilisearch_settings')) {
		update_site_option('meilisearch_settings', $default_settings);
	}

	// Limpar regras de reescrita.
	flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'meilisearch_activate');

/**
 * Hook de desativação do Plugin.
 */
function meilisearch_deactivate(): void
{
	flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'meilisearch_deactivate');

/**
 * Inicializar o plugin.
 */
function meilisearch_init(): void
{
	// Verificar se está rodando em multisite.
	if (!is_multisite()) {
		add_action('admin_notices', function () {
			echo '<div class="notice notice-error"><p>';
			esc_html_e('Meilisearch plugin requires WordPress Multisite.', 'meilisearch');
			echo '</p></div>';
		});
		return;
	}

	// Inicializar componentes.
	$settings = get_site_option('meilisearch_settings', []);

	if (!empty($settings['enabled']) && !empty($settings['host'])) {
		// Inicializar cliente.
		$client = new Meilisearch_Client($settings['host'], $settings['master_key'] ?? '');

		// Inicializar indexador.
		$indexer = new Meilisearch_Indexer($client);
		$indexer->init_hooks();

		// Inicializar override de busca (apenas frontend).
		if (!is_admin()) {
			$searcher = new Meilisearch_Searcher($client);
			$search_override = new Meilisearch_Search_Override($searcher);
			$search_override->init_hooks();

			// Inicializar autocomplete.
			$autocomplete = new Meilisearch_Autocomplete($client);
			$autocomplete->init_hooks();

			// Inicializar shortcode de busca.
			$search_shortcode = new Meilisearch_Search_Shortcode($client);
			$search_shortcode->init_hooks();

			// Inicializar integração com busca nativa (badges de relevância).
			$search_integration = new Meilisearch_Search_Integration($client);
			$search_integration->init_hooks();
		}

		// Inicializar Search API (sempre disponível).
		$search_api = new Meilisearch_Search_API($client);
		$search_api->init_hooks();

		// Registrar comandos WP-CLI.
		if (defined('WP_CLI') && WP_CLI) {
			WP_CLI::add_command('meilisearch', new Meilisearch_CLI($client, $indexer));
		}
	}

	// Inicializar dashboard e configurações do admin de rede (apenas admin).
	if (is_admin() && is_multisite()) {
		$dashboard = new Meilisearch_Dashboard();
		$dashboard->init_hooks();

		$metrics = new Meilisearch_Metrics();
		$metrics->init_hooks();

		$index_analyzer = new Meilisearch_Index_Analyzer();
		$index_analyzer->init_hooks();

		$multi_pattern = new Meilisearch_Multi_Pattern_Search();
		$multi_pattern->init_hooks();

		$network_settings = new Meilisearch_Network_Settings();
		$network_settings->init_hooks();

		$search_settings = new Meilisearch_Search_Settings();
		$search_settings->init_hooks();

		$tasks_monitor = new Meilisearch_Tasks_Monitor();
		$tasks_monitor->init_hooks();

		$backup_restore = new Meilisearch_Backup_Restore();
		$backup_restore->init_hooks();

		$federated_search = new Meilisearch_Federated_Search();
		$federated_search->init_hooks();
	}
}

add_action('plugins_loaded', 'meilisearch_init');

/**
 * Carregar traduções do plugin.
 * Executado no hook 'init' conforme recomendação do WordPress 6.7+.
 */
function meilisearch_load_textdomain(): void
{
	load_plugin_textdomain('meilisearch', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_action('init', 'meilisearch_load_textdomain');

/**
 * Obter o total de resultados encontrados pelo Meilisearch.
 *
 * Função helper para usar em templates.
 *
 * @return int Total de resultados encontrados, ou 0 se não houver.
 */
function meilisearch_get_total_results(): int
{
	global $meilisearch_total_results;
	return $meilisearch_total_results ?? 0;
}

/**
 * Exibir mensagem com o total de resultados encontrados.
 *
 * Função helper para usar em templates.
 *
 * @param string $format Formato da mensagem. Use %d para o número e %s para o termo de busca.
 * @return void
 */
function meilisearch_display_total_results(string $format = ''): void
{
	if (!is_search()) {
		return;
	}

	global $meilisearch_total_results, $meilisearch_search_term;
	$total = $meilisearch_total_results ?? 0;
	$term = $meilisearch_search_term ?? get_search_query();

	if (empty($format)) {
		if ($total === 0) {
			/* translators: %s: search term */
			$format = __('Nenhum resultado encontrado para "%s"', 'meilisearch');
		} elseif ($total === 1) {
			/* translators: %s: search term */
			$format = __('1 resultado encontrado para "%s"', 'meilisearch');
		} else {
			/* translators: 1: number of results, 2: search term */
			$format = __('%1$d resultados encontrados para "%2$s"', 'meilisearch');
		}
	}

	if ($total === 1) {
		echo '<p class="meilisearch-total-results">' . esc_html(sprintf($format, esc_html($term))) . '</p>';
	} else {
		echo '<p class="meilisearch-total-results">' . esc_html(sprintf($format, $total, esc_html($term))) . '</p>';
	}
}
