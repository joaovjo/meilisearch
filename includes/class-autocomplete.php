<?php

declare(strict_types=1);

/**
 * Meilisearch Autocomplete
 *
 * @package Meilisearch
 */

/**
 * Class Meilisearch_Autocomplete
 *
 * Handles autocomplete functionality for search.
 */
class Meilisearch_Autocomplete
{
	/**
	 * Meilisearch client instance.
	 *
	 * @var Meilisearch_Client
	 */
	private Meilisearch_Client $client;

	/**
	 * Constructor.
	 *
	 * @param Meilisearch_Client $client Meilisearch client instance.
	 */
	public function __construct(Meilisearch_Client $client)
	{
		$this->client = $client;
	}

	/**
	 * Initialize WordPress hooks.
	 */
	public function init_hooks(): void
	{
		add_action('rest_api_init', [$this, 'register_rest_route']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
	}

	/**
	 * Register REST API endpoint for autocomplete.
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
	 * Handle autocomplete REST API request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_autocomplete_request(WP_REST_Request $request): WP_REST_Response
	{
		$query = $request->get_param('q');
		$limit = $request->get_param('limit') ?: 5;

		if (null === $query || '' === $query || strlen($query) < 2) {
			return new WP_REST_Response([], 200);
		}

		$searcher = new Meilisearch_Searcher($this->client);
		$suggestions = $searcher->get_suggestions($query, $limit);

		return new WP_REST_Response($suggestions, 200);
	}

	/**
	 * Enqueue autocomplete scripts and styles.
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
