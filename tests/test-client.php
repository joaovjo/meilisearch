<?php

declare(strict_types=1);

/**
 * Class Meilisearch_Client_Test
 *
 * @package Meilisearch
 */

/**
 * Test case for Meilisearch_Client class.
 */
class Meilisearch_Client_Test extends WP_UnitTestCase
{
	/**
	 * Test client initialization.
	 */
	public function test_client_initialization()
	{
		$client = new Meilisearch_Client('http://localhost:7700', 'test_key');
		static::assertInstanceOf(Meilisearch_Client::class, $client);
	}

	/**
	 * Test index name generation.
	 */
	public function test_get_index_name()
	{
		$client = new Meilisearch_Client('http://localhost:7700');
		$index_name = $client->get_index_name(1);
		static::assertSame('wp_1_posts', $index_name);
	}

	/**
	 * Test getting all index names.
	 */
	public function test_get_all_index_names()
	{
		$client = new Meilisearch_Client('http://localhost:7700');
		$indexes = $client->get_all_index_names();
		static::assertIsArray($indexes);
	}
}
