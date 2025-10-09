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

			// Aguardar conclusão da criação do índice antes de configurar.
			$this->client->waitForTask($task['taskUid']);

			// Configurar atributos pesquisáveis.
			$this->client
				->index($index_name)
				->updateSearchableAttributes(['title', 'content', 'excerpt', 'categories', 'tags', 'author']);

			// Configurar atributos filtráveis.
			$filterable_attributes = class_exists('Meilisearch_Search_Settings')
				? Meilisearch_Search_Settings::get_filterable_attributes()
				: ['post_type', 'post_status', 'blog_id', 'author_id', 'categories', 'tags'];

			$this->client
				->index($index_name)
				->updateFilterableAttributes($filterable_attributes);

			// Configurar atributos ordenáveis.
			$sortable_attributes = class_exists('Meilisearch_Search_Settings')
				? Meilisearch_Search_Settings::get_sortable_attributes()
				: ['date', 'modified'];

			$this->client->index($index_name)->updateSortableAttributes($sortable_attributes);

			// Configurar regras de ranqueamento.
			$ranking_rules = class_exists('Meilisearch_Search_Settings')
				? Meilisearch_Search_Settings::get_ranking_rules()
				: ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'];

			$this->client->index($index_name)->updateRankingRules($ranking_rules);

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

	/**
	 * Obter cliente HTTP interno para requisições diretas.
	 *
	 * @return \Meilisearch\Http\Client
	 */
	private function get_http_client(): \Meilisearch\Http\Client
	{
		return new \Meilisearch\Http\Client($this->host, $this->master_key);
	}

	/**
	 * Obter funcionalidades experimentais atuais do Meilisearch.
	 *
	 * @return array<string, bool>|null Array de funcionalidades ou null em caso de erro.
	 */
	public function get_experimental_features(): ?array
	{
		try {
			$http_client = $this->get_http_client();
			$response = $http_client->get('/experimental-features');
			return $response ?? [];
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
				error_log('Meilisearch get experimental features error: ' . $e->getMessage());
			}
			return null;
		}
	}

	/**
	 * Atualizar funcionalidades experimentais no Meilisearch.
	 *
	 * @param array<string, bool> $features Funcionalidades para atualizar.
	 * @return bool True se atualizado com sucesso.
	 */
	public function update_experimental_features(array $features): bool
	{
		try {
			$http_client = $this->get_http_client();
			$http_client->patch('/experimental-features', $features);
			return true;
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
				error_log('Meilisearch update experimental features error: ' . $e->getMessage());
			}
			return false;
		}
	}

	/**
	 * Obter versão do Meilisearch.
	 *
	 * @return string|null Versão ou null em caso de erro.
	 */
	public function get_version(): ?string
	{
		try {
			$version = $this->client->version();
			return $version['pkgVersion'] ?? null;
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
				error_log('Meilisearch get version error: ' . $e->getMessage());
			}
			return null;
		}
	}

	/**
	 * Obter estatísticas de um índice.
	 *
	 * @param int $blog_id ID do site.
	 * @return array|null Estatísticas ou null em caso de erro.
	 */
	public function get_index_stats(int $blog_id): ?array
	{
		try {
			$index_name = $this->get_index_name($blog_id);
			return $this->client->index($index_name)->stats();
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
				error_log('Meilisearch get index stats error: ' . $e->getMessage());
			}
			return null;
		}
	}

	/**
	 * Obter tarefas recentes.
	 *
	 * @param int $limit Número máximo de tarefas.
	 * @return array Array de tarefas.
	 */
	public function get_recent_tasks(int $limit = 20): array
	{
		try {
			$tasks = $this->client->getTasks(['limit' => $limit]);
			return $tasks->getResults();
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
				error_log('Meilisearch get recent tasks error: ' . $e->getMessage());
			}
			return [];
		}
	}

	/**
	 * Obter status de uma tarefa.
	 *
	 * @param int $task_uid UID da tarefa.
	 * @return array|null Status da tarefa ou null em caso de erro.
	 */
	public function get_task_status(int $task_uid): ?array
	{
		try {
			return $this->client->getTask($task_uid);
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
				error_log('Meilisearch get task status error: ' . $e->getMessage());
			}
			return null;
		}
	}

	/**
	 * Aguardar conclusão de uma tarefa.
	 *
	 * @param int $task_uid      UID da tarefa.
	 * @param int $timeout_ms    Timeout em milissegundos.
	 * @param int $interval_ms   Intervalo entre verificações em milissegundos.
	 * @return array|null Status final da tarefa ou null em caso de timeout/erro.
	 */
	public function wait_for_task(int $task_uid, int $timeout_ms = 5000, int $interval_ms = 50): ?array
	{
		try {
			return $this->client->waitForTask($task_uid, $timeout_ms, $interval_ms);
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
				error_log('Meilisearch wait for task error: ' . $e->getMessage());
			}
			return null;
		}
	}

	/**
	 * Executar operação com retry automático em caso de rate limiting.
	 *
	 * @param callable $operation    Operação a executar.
	 * @param int      $max_retries  Número máximo de tentativas (padrão 3).
	 * @return mixed Resultado da operação.
	 * @throws Exception Se todas as tentativas falharem.
	 */
	public function execute_with_retry(callable $operation, int $max_retries = 3)
	{
		$attempt = 0;
		$last_exception = null;

		while ($attempt < $max_retries) {
			try {
				return $operation();
			} catch (Exception $e) {
				$last_exception = $e;
				$error_message = $e->getMessage();

				// Verificar se é erro de rate limiting (429)
				$is_rate_limit = strpos($error_message, '429') !== false 
					|| strpos(strtolower($error_message), 'too many requests') !== false;

				if ($is_rate_limit && $attempt < $max_retries - 1) {
					// Backoff exponencial: 1s, 2s, 4s
					$wait_seconds = (int) pow(2, $attempt);
					
					if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
						error_log("Meilisearch rate limit hit, waiting {$wait_seconds}s before retry (attempt " . ($attempt + 1) . "/{$max_retries})");
					}
					
					sleep($wait_seconds);
					$attempt++;
					continue;
				}

				// Outros erros ou última tentativa, propagar
				throw $e;
			}
		}

		// Se chegou aqui, todas as tentativas falharam
		throw $last_exception ?? new Exception('Max retries exceeded');
	}
}
