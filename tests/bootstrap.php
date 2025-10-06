<?php

/**
 * Arquivo bootstrap do PHPUnit.
 *
 * @package Meilisearch
 */

$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
	$_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// Encaminhar configuração personalizada de Polyfills do PHPUnit para o arquivo bootstrap do PHPUnit.
$_phpunit_polyfills_path = getenv('WP_TESTS_PHPUNIT_POLYFILLS_PATH');
if (false !== $_phpunit_polyfills_path) {
	define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path);
}

if (!file_exists("{$_tests_dir}/includes/functions.php")) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit(1);
}

// Dar acesso à função tests_add_filter().
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Carregar manualmente o plugin sendo testado.
 */
function _manually_load_plugin()
{
	require dirname(dirname(__FILE__)) . '/meilisearch.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Iniciar o ambiente de testes do WP.
require "{$_tests_dir}/includes/bootstrap.php";
