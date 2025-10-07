<?php

declare(strict_types=1);

/**
 * Wrapper do Cliente Meilisearch
 *
 * @package Meilisearch
 */

use Meilisearch\Client;

/**
 * Classe Meilisearch_Client
 *
 * Wrapper para o cliente do SDK PHP do Meilisearch.
 */
class Meilisearch_Client
{
	/**
	 * Instância do cliente Meilisearch.
	 *
	 * @var Client
	 */
	private Client $client;

	/**
	 * URL do host Meilisearch.
	 *
	 * @var string
	 */
	private string $host;

	/**
	 * Chave mestra do Meilisearch.
	 *
	 * @var string
	 */
	private string $master_key;

	/**
	 * Construtor.
	 *
	 * @param string $host       URL do host Meilisearch.
	 * @param string $master_key Chave mestra do Meilisearch.
	 */
	public function __construct(string $host, string $master_key = '')
	{
		$this->host = $host;
		$this->master_key = $master_key;
		$this->client = new Client($host, $master_key);
	}

	/**
	 * Obter a instância do cliente Meilisearch.
	 *
	 * @return Client
	 */
	public function get_client(): Client
	{
		return $this->client;
	}

	/**
	 * Obter nome do índice para um site específico.
	 *
	 * @param int $blog_id ID do site.
	 * @return string Nome do índice.
	 */
	public function get_index_name(int $blog_id): string
	{
		$settings = get_site_option('meilisearch_settings', []);
		$format = $settings['index_format'] ?? '{prefix}posts';

		// Obter prefixo da tabela para o site
		switch_to_blog($blog_id);
		global $wpdb;
		$prefix = $wpdb->prefix;
		restore_current_blog();

		// Substituir marcadores
		$index_name = str_replace(['{prefix}', '{blog_id}', '{site_id}'], [$prefix, $blog_id, $blog_id], $format);

		return $index_name;
	}

	/**
	 * Obter todos os nomes de índices da rede.
	 *
	 * @return array Array de nomes de índices.
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
	 * Criar índice para um site.
	 *
	 * @param int $blog_id ID do site.
	 * @return array|null Informações da tarefa ou null em caso de falha.
	 */
	public function create_index(int $blog_id): null|array
	{
		try {
			$index_name = $this->get_index_name($blog_id);
			$task = $this->client->createIndex($index_name, ['primaryKey' => 'id']);

			// Configurar atributos pesquisáveis.
			$this->client
				->index($index_name)
				->updateSearchableAttributes(['title', 'content', 'excerpt', 'categories', 'tags', 'author']);

			// Configurar atributos filtráveis.
			$this->client
				->index($index_name)
				->updateFilterableAttributes(['post_type', 'post_status', 'blog_id', 'author_id', 'categories', 'tags']);

			// Configurar atributos ordenáveis.
			$this->client->index($index_name)->updateSortableAttributes(['date', 'modified']);

			return $task;
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
				error_log('Meilisearch create index error: ' . $e->getMessage());
			}
			return null;
		}
	}

	/**
	 * Excluir índice de um site.
	 *
	 * @param int $blog_id ID do site.
	 * @return bool True em caso de sucesso, false em caso de falha.
	 */
	public function delete_index(int $blog_id): bool
	{
		try {
			$index_name = $this->get_index_name($blog_id);
			$this->client->deleteIndex($index_name);
			return true;
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
				error_log('Meilisearch delete index error: ' . $e->getMessage());
			}
			return false;
		}
	}

	/**
	 * Testar conexão com o servidor Meilisearch.
	 *
	 * @return bool True se a conexão for bem-sucedida.
	 */
	public function test_connection(): bool
	{
		try {
			$this->client->health();
			return true;
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
				error_log('Meilisearch connection error: ' . $e->getMessage());
			}
			return false;
		}
	}
}
