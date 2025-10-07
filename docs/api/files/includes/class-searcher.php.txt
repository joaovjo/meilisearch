<?php

declare(strict_types=1);

/**
 * Buscador Meilisearch
 *
 * @package Meilisearch
 */

use Meilisearch\Contracts\SearchQuery;

/**
 * Classe Meilisearch_Searcher
 *
 * Gerencia consultas de busca ao Meilisearch.
 */
class Meilisearch_Searcher
{
	/**
	 * Instância do cliente Meilisearch.
	 *
	 * @var Meilisearch_Client
	 */
	private Meilisearch_Client $client;

	/**
	 * Construtor.
	 *
	 * @param Meilisearch_Client $client Instância do cliente Meilisearch.
	 */
	public function __construct(Meilisearch_Client $client)
	{
		$this->client = $client;
	}

	/**
	 * Buscar em todos os índices da rede.
	 *
	 * @param string $query  Consulta de busca.
	 * @param array  $args   Argumentos opcionais de busca.
	 * @return array Resultados da busca.
	 */
	public function search_network(string $query, array $args = []): array
	{
		$indexes = $this->get_searchable_indexes();
		$queries = [];

		$default_args = [
			'limit' => 20,
			'offset' => 0,
			'sort' => '',
		];

		$args = wp_parse_args($args, $default_args);

		// Ensure limit and offset are integers.
		$args['limit'] = (int) $args['limit'];
		$args['offset'] = (int) $args['offset'];

		// Aplicar ordenação padrão se não fornecida
		if (empty($args['sort']) && class_exists('Meilisearch_Search_Settings')) {
			$default_sort = Meilisearch_Search_Settings::get_default_sort();
			if (!empty($default_sort['attribute'])) {
				$args['sort'] = $default_sort['attribute'] . ':' . $default_sort['direction'];
			}
		}

		// Preparar consultas de multi-busca.
		foreach ($indexes as $index) {
			$search_query = (new SearchQuery())
				->setIndexUid($index)
				->setQuery($query)
				->setLimit($args['limit'])
				->setOffset($args['offset'])
				->setFilter(['post_status = publish']);

			// Adicionar ordenação se fornecida
			if (!empty($args['sort'])) {
				$search_query->setSort([$args['sort']]);
			}

			$queries[] = $search_query;
		}
		try {
			$results = $this->client->get_client()->multiSearch($queries);
			return $this->format_results($results);
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
				error_log('Meilisearch search error: ' . $e->getMessage());
			}
			return [
				'hits' => [],
				'total' => 0,
			];
		}
	}

	/**
	 * Obter todos os índices pesquisáveis incluindo padrões adicionais.
	 *
	 * Retorna índices da rede atual mais quaisquer padrões adicionais
	 * selecionados nas configurações de Busca Multi-Padrão.
	 *
	 * @since 1.0.0
	 * @return array Array de nomes de índices para buscar
	 */
	private function get_searchable_indexes(): array
	{
		// Iniciar com índices da rede atual
		$indexes = $this->client->get_all_index_names();

		// Obter configuração de padrões adicionais
		$additional_patterns = is_multisite() 
			? get_site_option('meilisearch_additional_patterns', [])
			: get_option('meilisearch_additional_patterns', []);

		if (empty($additional_patterns) || !is_array($additional_patterns)) {
			return $indexes;
		}

		// Obter todos os índices disponíveis do Meilisearch
		try {
			$sdk_client = $this->client->get_client();
			$all_indexes = $sdk_client->getIndexes();
			$all_index_names = [];

			foreach ($all_indexes->getResults() as $index) {
				$all_index_names[] = $index->getUid();
			}

			// Fazer correspondência de índices contra padrões adicionais
			foreach ($additional_patterns as $pattern) {
				$pattern_regex = $this->convert_pattern_to_regex($pattern);

				foreach ($all_index_names as $index_name) {
					if (preg_match($pattern_regex, $index_name) && !in_array($index_name, $indexes, true)) {
						$indexes[] = $index_name;
					}
				}
			}
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
				error_log('Meilisearch get indexes error: ' . $e->getMessage());
			}
		}

		return $indexes;
	}

	/**
	 * Converter formato de padrão para regex
	 *
	 * Converte formato de padrão de índice (ex: "setur_{blog_id}_posts") para um padrão regex.
	 *
	 * @since 1.0.0
	 * @param string $pattern String de formato de padrão.
	 * @return string Padrão regex
	 */
	private function convert_pattern_to_regex(string $pattern): string
	{
		// Escapar caracteres regex especiais exceto nossos marcadores
		$regex = preg_quote($pattern, '/');

		// Substituir marcador {blog_id} com padrão de número
		$regex = str_replace('\{blog_id\}', '\d+', $regex);

		// Substituir marcador {type} se presente
		$regex = str_replace('\{type\}', '[a-z]+', $regex);

		return '/^' . $regex . '$/';
	}

	/**
	 * Buscar em um índice específico de site.
	 *
	 * @param string $query   Consulta de busca.
	 * @param int    $blog_id ID do site.
	 * @param array  $args    Argumentos opcionais de busca.
	 * @return array Resultados da busca.
	 */
	public function search_site(string $query, int $blog_id, array $args = []): array
	{
		$index_name = $this->client->get_index_name($blog_id);

		$default_args = [
			'limit' => 20,
			'offset' => 0,
			'sort' => '',
		];

		$args = wp_parse_args($args, $default_args);

		// Garantir que limit e offset são inteiros.
		$args['limit'] = (int) $args['limit'];
		$args['offset'] = (int) $args['offset'];

		// Aplicar ordenação padrão se não fornecida
		if (empty($args['sort']) && class_exists('Meilisearch_Search_Settings')) {
			$default_sort = Meilisearch_Search_Settings::get_default_sort();
			if (!empty($default_sort['attribute'])) {
				$args['sort'] = $default_sort['attribute'] . ':' . $default_sort['direction'];
			}
		}

		// Preparar parâmetros de busca
		$search_params = [
			'limit' => $args['limit'],
			'offset' => $args['offset'],
			'filter' => ['post_status = publish'],
		];

		// Adicionar ordenação se fornecida
		if (!empty($args['sort'])) {
			$search_params['sort'] = [$args['sort']];
		}

	try {
			$results = $this->client
				->get_client()
				->index($index_name)
				->search($query, $search_params);

			return [
				'hits' => $results->getHits(),
				'total' => $results->getEstimatedTotalHits(),
			];
		} catch (Exception $e) {
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Apenas log de debug.
				error_log('Meilisearch search error: ' . $e->getMessage());
			}
			return [
				'hits' => [],
				'total' => 0,
			];
		}
	}

	/**
	 * Obter sugestões de autocompletar.
	 *
	 * @param string $query Consulta de busca.
	 * @param int    $limit Número de sugestões.
	 * @return array Sugestões.
	 */
	public function get_suggestions(string $query, int $limit = 5): array
	{
		$results = $this->search_network($query, [
			'limit' => $limit,
			'offset' => 0,
		]);

		$suggestions = [];

		foreach ($results['hits'] as $hit) {
			$suggestions[] = [
				'title' => $hit['title'] ?? '',
				'excerpt' => $hit['excerpt'] ?? '',
				'permalink' => $hit['permalink'] ?? '',
				'blog_id' => $hit['blog_id'] ?? 0,
			];
		}

		return $suggestions;
	}

	/**
	 * Formatar resultados de multi-busca.
	 *
	 * @param array $results Resultados brutos do Meilisearch.
	 * @return array Resultados formatados.
	 */
	private function format_results(array $results): array
	{
		$hits = [];
		$total = 0;

		if (isset($results['results'])) {
			foreach ($results['results'] as $result) {
				$hits = array_merge($hits, $result['hits'] ?? []);
				$total += $result['estimatedTotalHits'] ?? 0;
			}
		}

		return [
			'hits' => $hits,
			'total' => $total,
		];
	}
}
