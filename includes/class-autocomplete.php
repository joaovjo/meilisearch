<?php

declare(strict_types=1);

/**
 * Autocompletar Meilisearch
 *
 * @package Meilisearch
 */

/**
 * Classe Meilisearch_Autocomplete
 *
 * Gerencia funcionalidade de autocompletar para busca.
 */
class Meilisearch_Autocomplete
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
	 * Inicializar hooks do WordPress.
	 */
	public function init_hooks(): void
	{
		add_action('rest_api_init', [$this, 'register_rest_route']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
	}

	/**
	 * Registrar endpoint REST API para autocompletar.
	 */
	public function register_rest_route(): void
	{
		register_rest_route('meilisearch/v1', '/autocomplete', [
			'methods' => 'GET',
			'callback' => [$this, 'handle_autocomplete_request'],
			'permission_callback' => '__return_true',
			'args' => [
				'q' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'limit' => [
					'required' => false,
					'type' => 'integer',
					'default' => 5,
					'sanitize_callback' => 'absint',
				],
			],
		]);
	}

	/**
	 * Gerenciar requisição REST API de autocompletar.
	 *
	 * @param WP_REST_Request $request Objeto de requisição.
	 * @return WP_REST_Response Objeto de resposta.
	 */
	public function handle_autocomplete_request(WP_REST_Request $request): WP_REST_Response
	{
		$query = $request->get_param('q');
		$limit = $request->get_param('limit') ?? 5;

		if (null === $query || '' === $query || strlen($query) < 2) {
			return new WP_REST_Response([], 200);
		}

		$searcher = new Meilisearch_Searcher($this->client);
		$suggestions = $searcher->get_suggestions($query, $limit);

		return new WP_REST_Response($suggestions, 200);
	}

	/**
	 * Enfileirar scripts e estilos de autocompletar.
	 */
	public function enqueue_scripts(): void
	{
		wp_enqueue_script(
			'meilisearch-autocomplete',
			MEILISEARCH_PLUGIN_URL . 'assets/js/autocomplete.js',
			['jquery'],
			MEILISEARCH_VERSION,
			true,
		);

		wp_enqueue_style(
			'meilisearch-autocomplete',
			MEILISEARCH_PLUGIN_URL . 'assets/css/autocomplete.css',
			[],
			MEILISEARCH_VERSION,
		);

		wp_localize_script('meilisearch-autocomplete', 'meilisearchConfig', [
			'apiUrl' => rest_url('meilisearch/v1/autocomplete'),
			'nonce' => wp_create_nonce('wp_rest'),
		]);
	}
}
