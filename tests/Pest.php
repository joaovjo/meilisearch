<?php

declare(strict_types=1);

/**
 * Configuração global do Pest.
 *
 * @package Meilisearch
 */

// Reiniciar o estado simulado do WordPress antes de cada teste.
uses()->beforeEach(function (): void {
	$GLOBALS['meilisearch_test_site_options'] = [];
	$GLOBALS['meilisearch_test_sites'] = [];
	restore_current_blog();
})->in(__DIR__);

/**
 * Definir uma opção de rede simulada consumida pelos stubs do WordPress.
 *
 * @param string $option Nome da opção.
 * @param mixed  $value  Valor da opção.
 * @return void
 */
function meilisearch_set_test_site_option(string $option, mixed $value): void
{
	$GLOBALS['meilisearch_test_site_options'][$option] = $value;
}

/**
 * Definir os sites simulados retornados por get_sites().
 *
 * @param array $sites Lista de objetos de site.
 * @return void
 */
function meilisearch_set_test_sites(array $sites): void
{
	$GLOBALS['meilisearch_test_sites'] = $sites;
}
