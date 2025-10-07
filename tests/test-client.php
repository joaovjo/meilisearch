<?php

declare(strict_types=1);

/**
 * Classe Meilisearch_Client_Test
 *
 * @package Meilisearch
 */

/**
 * Caso de teste para a classe Meilisearch_Client.
 */
class Meilisearch_Client_Test extends WP_UnitTestCase
{
	/**
	 * Testar inicialização do cliente.
	 */
	public function test_client_initialization(): void
	{
		$client = new Meilisearch_Client('http://localhost:7700', 'test_key');
		static::assertInstanceOf(Meilisearch_Client::class, $client);
	}

	/**
	 * Testar geração de nome de índice.
	 */
	public function test_get_index_name(): void
	{
		$client = new Meilisearch_Client('http://localhost:7700');
		$index_name = $client->get_index_name(1);
		static::assertSame('wp_1_posts', $index_name);
	}

	/**
	 * Testar obtenção de todos os nomes de índice.
	 */
	public function test_get_all_index_names(): void
	{
		$client = new Meilisearch_Client('http://localhost:7700');
		$indexes = $client->get_all_index_names();
		static::assertIsArray($indexes);
	}
}
