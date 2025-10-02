<?php
/**
 * Plugin Name:     Meilisearch Network Search
 * Plugin URI:      https://github.com/joaovjo/meilisearch-plus
 * Description:     Replace WordPress search with Meilisearch across your entire multisite network with automatic autocomplete. Network-only configuration.
 * Author:          joaovjo
 * Author URI:      https://github.com/joaovjo
 * Text Domain:     meilisearch
 * Domain Path:     /languages
 * Version:         0.1.0
 * Network:         true
 * Requires PHP:    8.1
 * Requires at least: 6.0
 *
 * @package         Meilisearch
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'MEILISEARCH_VERSION', '0.1.0' );
define( 'MEILISEARCH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEILISEARCH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MEILISEARCH_PLUGIN_FILE', __FILE__ );

// Require Composer autoloader.
if ( file_exists( MEILISEARCH_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once MEILISEARCH_PLUGIN_DIR . 'vendor/autoload.php';
}

// Include core classes.
require_once MEILISEARCH_PLUGIN_DIR . 'includes/class-client.php';
require_once MEILISEARCH_PLUGIN_DIR . 'includes/class-indexer.php';
require_once MEILISEARCH_PLUGIN_DIR . 'includes/class-searcher.php';
require_once MEILISEARCH_PLUGIN_DIR . 'includes/class-autocomplete.php';

// Include admin classes.
if ( is_admin() && is_multisite() ) {
	require_once MEILISEARCH_PLUGIN_DIR . 'admin/class-network-settings.php';
}

// Include public classes.
if ( ! is_admin() ) {
	require_once MEILISEARCH_PLUGIN_DIR . 'public/class-search-override.php';
}

// Include WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once MEILISEARCH_PLUGIN_DIR . 'cli/class-commands.php';
}

/**
 * Plugin activation hook.
 * Network activation only.
 */
function meilisearch_activate( bool $network_wide ): void {
	if ( ! $network_wide ) {
		wp_die(
			esc_html__( 'This plugin must be network activated.', 'meilisearch' ),
			esc_html__( 'Network Activation Required', 'meilisearch' ),
			[ 'back_link' => true ]
		);
	}

	// Set default options.
	$default_settings = [
		'host'       => 'http://localhost:7700',
		'master_key' => '',
		'enabled'    => false,
	];

	if ( ! get_site_option( 'meilisearch_settings' ) ) {
		update_site_option( 'meilisearch_settings', $default_settings );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'meilisearch_activate' );

/**
 * Plugin deactivation hook.
 */
function meilisearch_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'meilisearch_deactivate' );

/**
 * Initialize the plugin.
 */
function meilisearch_init(): void {
	// Check if running in multisite.
	if ( ! is_multisite() ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Meilisearch plugin requires WordPress Multisite.', 'meilisearch' );
				echo '</p></div>';
			}
		);
		return;
	}

	// Initialize components.
	$settings = get_site_option( 'meilisearch_settings', [] );

	if ( ! empty( $settings['enabled'] ) && ! empty( $settings['host'] ) ) {
		// Initialize client.
		$client = new Meilisearch_Client( $settings['host'], $settings['master_key'] ?? '' );

		// Initialize indexer.
		$indexer = new Meilisearch_Indexer( $client );
		$indexer->init_hooks();

		// Initialize search override (frontend only).
		if ( ! is_admin() ) {
			$searcher = new Meilisearch_Searcher( $client );
			$search_override = new Meilisearch_Search_Override( $searcher );
			$search_override->init_hooks();

			// Initialize autocomplete.
			$autocomplete = new Meilisearch_Autocomplete( $client );
			$autocomplete->init_hooks();
		}
	}

	// Initialize network admin settings (admin only).
	if ( is_admin() && is_multisite() ) {
		$network_settings = new Meilisearch_Network_Settings();
		$network_settings->init_hooks();
	}

	// Load text domain.
	load_plugin_textdomain( 'meilisearch', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'meilisearch_init' );

