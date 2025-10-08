<?php

declare(strict_types=1);

/**
 * Comandos WP-CLI para o plugin Meilisearch.
 *
 * @package Meilisearch
 */

/**
 * Comandos WP-CLI do Meilisearch.
 */
class Meilisearch_CLI
{
	/**
	 * Instância do cliente Meilisearch.
	 *
	 * @var Meilisearch_Client
	 */
	private Meilisearch_Client $client;

	/**
	 * Instância do indexador Meilisearch.
	 *
	 * @var Meilisearch_Indexer
	 */
	private Meilisearch_Indexer $indexer;

	/**
	 * Construtor.
	 *
	 * @param Meilisearch_Client  $client  Instância do cliente Meilisearch.
	 * @param Meilisearch_Indexer $indexer Instância do indexador Meilisearch.
	 */
	public function __construct(Meilisearch_Client $client, Meilisearch_Indexer $indexer)
	{
		$this->client = $client;
		$this->indexer = $indexer;
	}

	/**
	 * Reindexa todos os posts de um blog específico ou todos os blogs da rede.
	 *
	 * ## OPÇÕES
	 *
	 * [--blog_id=<blog_id>]
	 * : O ID do blog a ser reindexado. Se não fornecido, reindexa todos os blogs da rede.
	 *
	 * [--url=<url>]
	 * : A URL do blog a ser reindexado (alternativa ao blog_id).
	 *
	 * ## EXEMPLOS
	 *
	 *     # Reindexar um blog específico por ID
	 *     wp meilisearch reindex --blog_id=2
	 *
	 *     # Reindexar um blog específico por URL
	 *     wp meilisearch reindex --url=http://example.com/labcom/
	 *
	 *     # Reindexar todos os blogs da rede
	 *     wp meilisearch reindex
	 *
	 * @param array $args       Argumentos posicionais.
	 * @param array $assoc_args Argumentos associativos.
	 */
	public function reindex($args, $assoc_args)
	{
		$blog_id = $assoc_args['blog_id'] ?? null;
		$url = $assoc_args['url'] ?? null;

		// Se a URL for fornecida, obter blog_id dela.
		if ($url && !$blog_id) {
			$blog_id = get_blog_id_from_url($url);
			if (!$blog_id) {
				WP_CLI::error("Blog not found for URL: $url");
			}
		}

		if ($blog_id) {
			// Reindexar um único blog.
			WP_CLI::log("Reindexing blog $blog_id...");
			$results = $this->indexer->index_site_posts((int) $blog_id);

			if (isset($results['errors']) && is_array($results['errors']) && count($results['errors']) > 0) {
				WP_CLI::warning('Reindexing completed with errors:');
				foreach ($results['errors'] as $error) {
					WP_CLI::warning($error);
				}
			}

			WP_CLI::success(sprintf('Reindexed %d of %d posts for blog %d', $results['indexed'], $results['total'], $blog_id));
		} else {
			// Reindexar todos os blogs da rede.
			if (!is_multisite()) {
				WP_CLI::error('This is not a multisite installation. Use --blog_id=1 or --url parameter.');
			}

			$sites = get_sites(['number' => 999]);
			WP_CLI::log(sprintf('Found %d sites to reindex...', count($sites)));

			$progress = \WP_CLI\Utils\make_progress_bar('Reindexing sites', count($sites));

			$total_indexed = 0;
			$total_posts = 0;

			foreach ($sites as $site) {
				$results = $this->indexer->index_site_posts((int) $site->blog_id);
				$total_indexed += $results['indexed'];
				$total_posts += $results['total'];
				$progress->tick();
			}

			$progress->finish();

			WP_CLI::success(sprintf('Reindexed %d of %d posts across %d sites', $total_indexed, $total_posts, count($sites)));
		}
	}

	/**
	 * Lista todos os índices do Meilisearch.
	 *
	 * ## OPÇÕES
	 *
	 * [--format=<format>]
	 * : Formato de saída. Opções: table, json, csv, yaml, count. Padrão: table
	 *
	 * ## EXEMPLOS
	 *
	 *     # Listar todos os índices como tabela
	 *     wp meilisearch list-indexes
	 *
	 *     # Listar todos os índices como JSON
	 *     wp meilisearch list-indexes --format=json
	 *
	 * @param array $args       Argumentos posicionais.
	 * @param array $assoc_args Argumentos associativos.
	 */
	public function list_indexes($args, $assoc_args)
	{
		$format = $assoc_args['format'] ?? 'table';

		try {
			$indexes = $this->client->get_all_index_names();
			$data = [];

			foreach ($indexes as $index) {
				// Extrair blog_id do nome do índice (wp_X_posts).
				preg_match('/wp_(\d+)_posts/', $index, $matches);
				$blog_id = $matches[1] ?? 'unknown';

				$blog_name = 'Unknown';
				if (is_numeric($blog_id)) {
					$blog_details = get_blog_details((int) $blog_id);
					$blog_name = $blog_details ? $blog_details->blogname : 'Blog not found';
				}

				// Obter estatísticas do índice.
				$stats = $this->client
					->get_client()
					->index($index)
					->stats();

				$data[] = [
					'index' => $index,
					'blog_id' => $blog_id,
					'blog_name' => $blog_name,
					'documents' => $stats['numberOfDocuments'] ?? 0,
					'indexing' => $stats['isIndexing'] ?? false ? 'Yes' : 'No',
				];
			}

			WP_CLI\Utils\format_items($format, $data, ['index', 'blog_id', 'blog_name', 'documents', 'indexing']);
		} catch (Exception $e) {
			WP_CLI::error('Failed to list indexes: ' . $e->getMessage());
		}
	}

	/**
	 * Cria índice para um blog específico.
	 *
	 * ## OPÇÕES
	 *
	 * <blog_id>
	 * : O ID do blog para criar o índice.
	 *
	 * ## EXEMPLOS
	 *
	 *     # Criar índice para o blog 2
	 *     wp meilisearch create-index 2
	 *
	 * @param array $args       Argumentos posicionais.
	 * @param array $assoc_args Argumentos associativos.
	 */
	public function create_index($args, $assoc_args)
	{
		$blog_id = (int) $args[0];

		if (!$blog_id) {
			WP_CLI::error('Invalid blog ID provided.');
		}

		WP_CLI::log("Creating index for blog $blog_id...");

		try {
			$result = $this->client->create_index($blog_id);

			if ($result) {
				WP_CLI::success("Index created for blog $blog_id: " . $this->client->get_index_name($blog_id));
			} else {
				WP_CLI::error('Failed to create index.');
			}
		} catch (Exception $e) {
			WP_CLI::error('Failed to create index: ' . $e->getMessage());
		}
	}

	/**
	 * Remove índice de um blog específico.
	 *
	 * ## OPÇÕES
	 *
	 * <blog_id>
	 * : O ID do blog para remover o índice.
	 *
	 * [--yes]
	 * : Pular prompt de confirmação.
	 *
	 * ## EXEMPLOS
	 *
	 *     # Remover índice do blog 2
	 *     wp meilisearch delete-index 2 --yes
	 *
	 * @param array $args       Argumentos posicionais.
	 * @param array $assoc_args Argumentos associativos.
	 */
	public function delete_index($args, $assoc_args)
	{
		$blog_id = (int) $args[0];

		if (!$blog_id) {
			WP_CLI::error('Invalid blog ID provided.');
		}

		$index_name = $this->client->get_index_name($blog_id);

		WP_CLI::confirm("Are you sure you want to delete index '$index_name'?", $assoc_args);

		WP_CLI::log("Deleting index $index_name...");

		try {
			$result = $this->client->delete_index($blog_id);

			if ($result) {
				WP_CLI::success("Index deleted: $index_name");
			} else {
				WP_CLI::error('Failed to delete index.');
			}
		} catch (Exception $e) {
			WP_CLI::error('Failed to delete index: ' . $e->getMessage());
		}
	}

	/**
	 * Busca posts em toda a rede.
	 *
	 * ## OPÇÕES
	 *
	 * <query>
	 * : A consulta de busca.
	 *
	 * [--limit=<limit>]
	 * : Número máximo de resultados. Padrão: 20
	 *
	 * [--blog_id=<blog_id>]
	 * : Buscar apenas em um blog específico. Se não fornecido, busca em todos os blogs.
	 *
	 * [--format=<format>]
	 * : Formato de saída. Opções: table, json, csv, yaml. Padrão: table
	 *
	 * ## EXEMPLOS
	 *
	 *     # Buscar por "mundo" em todos os blogs
	 *     wp meilisearch search "mundo"
	 *
	 *     # Buscar em blog específico com limite
	 *     wp meilisearch search "inteligente" --blog_id=2 --limit=10
	 *
	 *     # Obter resultados como JSON
	 *     wp meilisearch search "mundo" --format=json
	 *
	 * @param array $args       Argumentos posicionais.
	 * @param array $assoc_args Argumentos associativos.
	 */
	public function search($args, $assoc_args)
	{
		$query = $args[0] ?? '';
		$limit = (int) ($assoc_args['limit'] ?? 20);
		$blog_id = isset($assoc_args['blog_id']) ? (int) $assoc_args['blog_id'] : null;
		$format = $assoc_args['format'] ?? 'table';

		if (null === $query || '' === $query) {
			WP_CLI::error('Search query is required.');
		}

		$searcher = new Meilisearch_Searcher($this->client);

		try {
			if ($blog_id) {
				$results = $searcher->search_site($query, $blog_id, ['limit' => $limit]);
				$hits = $results['hits'];
			} else {
				$results = $searcher->search_network($query, ['limit' => $limit]);
				$hits = $results['hits'];
			}

			if (!isset($hits) || !is_array($hits) || count($hits) === 0) {
				WP_CLI::warning('No results found.');
				return;
			}

			$data = [];
			foreach ($hits as $hit) {
				$ranking_score = isset($hit['_rankingScore']) ? round($hit['_rankingScore'] * 100, 1) : 'N/A';
				$data[] = [
					'blog_id' => $hit['blog_id'] ?? 'N/A',
					'post_id' => $hit['id'] ?? 'N/A',
					'title' => wp_trim_words($hit['title'] ?? '', 10),
					'post_type' => $hit['post_type'] ?? 'N/A',
					'relevance' => $ranking_score . '%',
					'permalink' => $hit['permalink'] ?? 'N/A',
				];
			}

			WP_CLI\Utils\format_items($format, $data, ['blog_id', 'post_id', 'title', 'post_type', 'relevance', 'permalink']);

			WP_CLI::success(sprintf('Found %d results for "%s"', count($hits), $query));
		} catch (Exception $e) {
			WP_CLI::error('Search failed: ' . $e->getMessage());
		}
	}

	/**
	 * Verifica a conexão e saúde do servidor Meilisearch.
	 *
	 * ## EXEMPLOS
	 *
	 *     # Verificar saúde do servidor
	 *     wp meilisearch health
	 *
	 * @param array $args       Argumentos posicionais.
	 * @param array $assoc_args Argumentos associativos.
	 */
	public function health($args, $assoc_args)
	{
		WP_CLI::log('Checking Meilisearch server health...');

		try {
			$health = $this->client->get_client()->health();

			if (isset($health['status']) && 'available' === $health['status']) {
				WP_CLI::success('Meilisearch server is healthy and available!');

				// Obter informações de versão.
				$version = $this->client->get_client()->version();
				WP_CLI::log('Version: ' . ($version['pkgVersion'] ?? 'Unknown'));
			} else {
				WP_CLI::warning('Meilisearch server status: ' . ($health['status'] ?? 'Unknown'));
			}
		} catch (Exception $e) {
			WP_CLI::error('Failed to connect to Meilisearch server: ' . $e->getMessage());
		}
	}

	/**
	 * Obtém estatísticas sobre documentos indexados.
	 *
	 * ## OPÇÕES
	 *
	 * [--blog_id=<blog_id>]
	 * : Obter estatísticas para um blog específico. Se não fornecido, mostra estatísticas de todos os blogs.
	 *
	 * ## EXEMPLOS
	 *
	 *     # Obter estatísticas de todos os blogs
	 *     wp meilisearch stats
	 *
	 *     # Obter estatísticas de um blog específico
	 *     wp meilisearch stats --blog_id=2
	 *
	 * @param array $args       Argumentos posicionais.
	 * @param array $assoc_args Argumentos associativos.
	 */
	public function stats($args, $assoc_args)
	{
		$blog_id = isset($assoc_args['blog_id']) ? (int) $assoc_args['blog_id'] : null;

		try {
			if ($blog_id) {
				// Estatísticas para um blog específico.
				$index_name = $this->client->get_index_name($blog_id);
				$stats = $this->client
					->get_client()
					->index($index_name)
					->stats();

				WP_CLI::log("Statistics for blog $blog_id ($index_name):");
				WP_CLI::log('  Documents: ' . ($stats['numberOfDocuments'] ?? 0));
				WP_CLI::log('  Indexing: ' . ($stats['isIndexing'] ?? false ? 'Yes' : 'No'));

				if (isset($stats['fieldDistribution'])) {
					WP_CLI::log("\n  Field Distribution:");
					foreach ($stats['fieldDistribution'] as $field => $count) {
						WP_CLI::log("    $field: $count");
					}
				}
			} else {
				// Estatísticas para todos os índices.
				$all_stats = $this->client->get_client()->stats();

				WP_CLI::log('Global Meilisearch Statistics:');
				WP_CLI::log('  Database Size: ' . size_format($all_stats['databaseSize'] ?? 0));
				WP_CLI::log('  Total Indexes: ' . count($all_stats['indexes'] ?? []));

				if (isset($all_stats['indexes'])) {
					WP_CLI::log("\n  Indexes:");
					foreach ($all_stats['indexes'] as $index_name => $index_stats) {
						WP_CLI::log(sprintf('    %s: %d documents', $index_name, $index_stats['numberOfDocuments'] ?? 0));
					}
				}
			}

			WP_CLI::success('Stats retrieved successfully.');
		} catch (Exception $e) {
			WP_CLI::error('Failed to get stats: ' . $e->getMessage());
		}
	}
}
