<?php

declare(strict_types=1);

/**
 * Meilisearch Client Wrapper
 *
 * @package Meilisearch
 */

use Meilisearch\Client;

/**
 * Class Meilisearch_Client
 *
 * Wrapper for Meilisearch PHP SDK client.
 */
class Meilisearch_Client
{
	/**
	 * Meilisearch client instance.
	 *
	 * @var Client
	 */
	private Client $client;

	/**
	 * Meilisearch host URL.
	 *
	 * @var string
	 */
	private string $host;

	/**
	 * Meilisearch master key.
	 *
	 * @var string
	 */
	private string $master_key;

	/**
	 * Constructor.
	 *
	 * @param string $host       Meilisearch host URL.
	 * @param string $master_key Meilisearch master key.
	 */
	public function __construct(string $host, string $master_key = '')
	{
		$this->host = $host;
		$this->master_key = $master_key;
		$this->client = new Client($host, $master_key);
	}

	/**
	 * Get the Meilisearch client instance.
	 *
	 * @return Client
	 */
	public function get_client(): Client
	{
		return $this->client;
	}

	/**
	 * Get index name for a specific site.
	 *
	 * @param int $blog_id Site ID.
	 * @return string Index name.
	 */
	public function get_index_name(int $blog_id): string
	{
		$settings = get_site_option('meilisearch_settings', []);
		$format = $settings['index_format'] ?? '{prefix}posts';

		// Get table prefix for the site
		switch_to_blog($blog_id);
		global $wpdb;
		$prefix = $wpdb->prefix;
		restore_current_blog();

		// Replace placeholders
		$index_name = str_replace(['{prefix}', '{blog_id}', '{site_id}'], [$prefix, $blog_id, $blog_id], $format);

		return $index_name;
	}

	/**
	 * Get all index names for the network.
	 *
	 * @return array Array of index names.
	 */
	public function get_all_index_names(): array
	{
		$sites = get_sites(['number' => 9999]);
		$indexes = [];

		foreach ($sites as $site) {
			$indexes[] = $this->get_index_name((int) $site->blog_id);
		}

		return $indexes;
	}

	/**
	 * Create index for a site.
	 *
	 * @param int $blog_id Site ID.
	 * @return array|null Task info or null on failure.
	 */
	public function create_index(int $blog_id): null|array
	{
		try {
			$index_name = $this->get_index_name($blog_id);
			$task = $this->client->createIndex($index_name, ['primaryKey' => 'id']);

			// Configure searchable attributes.
			$this->client
				->index($index_name)
				->updateSearchableAttributes(['title', 'content', 'excerpt', 'categories', 'tags', 'author']);

			// Configure filterable attributes.
			$this->client
				->index($index_name)
				->updateFilterableAttributes(['post_type', 'post_status', 'blog_id', 'author_id', 'categories', 'tags']);

			// Configure sortable attributes.
			$this->client->index($index_name)->updateSortableAttributes(['date', 'modified']);

			return $task;
		} catch (Exception $e) {
			error_log('Meilisearch create index error: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Delete index for a site.
	 *
	 * @param int $blog_id Site ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_index(int $blog_id): bool
	{
		try {
			$index_name = $this->get_index_name($blog_id);
			$this->client->deleteIndex($index_name);
			return true;
		} catch (Exception $e) {
			error_log('Meilisearch delete index error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Test connection to Meilisearch server.
	 *
	 * @return bool True if connection is successful.
	 */
	public function test_connection(): bool
	{
		try {
			$this->client->health();
			return true;
		} catch (Exception $e) {
			error_log('Meilisearch connection error: ' . $e->getMessage());
			return false;
		}
	}
}
