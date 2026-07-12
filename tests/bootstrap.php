<?php

declare(strict_types=1);

/**
 * Bootstrap dos testes unitários (Pest).
 *
 * Os testes exercitam as classes do plugin de forma isolada, usando stubs
 * leves das funções do WordPress consumidas pelo código sob teste. Não é
 * necessário instalar o WordPress nem um banco de dados.
 *
 * @package Meilisearch
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Objeto $wpdb mínimo para simular o prefixo de tabela por site da rede.
if (!isset($GLOBALS['wpdb'])) {
	$GLOBALS['wpdb'] = new class {
		/**
		 * Prefixo base da rede.
		 *
		 * @var string
		 */
		public string $base_prefix = 'wp_';

		/**
		 * Prefixo do site atual.
		 *
		 * @var string
		 */
		public string $prefix = 'wp_';
	};
}

if (!function_exists('get_site_option')) {
	/**
	 * Stub de get_site_option() baseado em valores definidos pelo teste.
	 *
	 * @param string $option  Nome da opção de rede.
	 * @param mixed  $default Valor padrão.
	 * @return mixed
	 */
	function get_site_option(string $option, mixed $default = false): mixed
	{
		return $GLOBALS['meilisearch_test_site_options'][$option] ?? $default;
	}
}

if (!function_exists('switch_to_blog')) {
	/**
	 * Stub de switch_to_blog() que replica o prefixo de tabela do WordPress.
	 *
	 * @param int $blog_id ID do site.
	 * @return bool
	 */
	function switch_to_blog(int $blog_id): bool
	{
		$wpdb = $GLOBALS['wpdb'];
		$wpdb->prefix = $blog_id > 1 ? $wpdb->base_prefix . $blog_id . '_' : $wpdb->base_prefix;
		return true;
	}
}

if (!function_exists('restore_current_blog')) {
	/**
	 * Stub de restore_current_blog().
	 *
	 * @return bool
	 */
	function restore_current_blog(): bool
	{
		$GLOBALS['wpdb']->prefix = $GLOBALS['wpdb']->base_prefix;
		return true;
	}
}

if (!function_exists('get_sites')) {
	/**
	 * Stub de get_sites() baseado nos sites definidos pelo teste.
	 *
	 * @param array $args Argumentos (ignorados).
	 * @return array
	 */
	function get_sites(array $args = []): array
	{
		return $GLOBALS['meilisearch_test_sites'] ?? [];
	}
}

// Carregar apenas a classe sob teste (sem o plugin completo, que exige ABSPATH).
require_once dirname(__DIR__) . '/includes/class-client.php';
