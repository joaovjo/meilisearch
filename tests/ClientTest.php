<?php

declare(strict_types=1);

/**
 * Testes da classe Meilisearch_Client.
 *
 * @package Meilisearch
 */

use Meilisearch\Client as MeilisearchSdkClient;

it('inicializa o cliente e expõe o SDK do Meilisearch', function (): void {
	$client = new Meilisearch_Client('http://localhost:7700', 'test_key');

	expect($client)->toBeInstanceOf(Meilisearch_Client::class)
		->and($client->get_client())->toBeInstanceOf(MeilisearchSdkClient::class);
});

it('usa o prefixo da tabela no formato de índice padrão', function (): void {
	// Formato padrão "{prefix}posts" → "wp_posts" no blog 1.
	expect((new Meilisearch_Client('http://localhost:7700'))->get_index_name(1))
		->toBe('wp_posts');

	// No blog 2 o prefixo vira "wp_2_" → "wp_2_posts".
	expect((new Meilisearch_Client('http://localhost:7700'))->get_index_name(2))
		->toBe('wp_2_posts');
});

it('substitui o marcador {blog_id} quando configurado no formato de índice', function (): void {
	meilisearch_set_test_site_option('meilisearch_settings', [
		'index_format' => 'wp_{blog_id}_posts',
	]);

	expect((new Meilisearch_Client('http://localhost:7700'))->get_index_name(1))
		->toBe('wp_1_posts');
});

it('lista os nomes de índice de todos os sites da rede', function (): void {
	meilisearch_set_test_sites([
		(object) ['blog_id' => 1],
		(object) ['blog_id' => 2],
	]);

	$indexes = (new Meilisearch_Client('http://localhost:7700'))->get_all_index_names();

	expect($indexes)->toBeArray()
		->and($indexes)->toBe(['wp_posts', 'wp_2_posts']);
});
