<?php

declare(strict_types=1);

/**
 * Meilisearch Indexer
 *
 * @package Meilisearch
 */

use React\EventLoop\Loop;

/**
 * Class Meilisearch_Indexer
 *
 * Handles indexing of WordPress content to Meilisearch using Fiber for concurrency.
 */
class Meilisearch_Indexer
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
		// Index on post save.
		add_action('save_post', [$this, 'index_post'], 10, 2);

		// Remove from index on post delete.
		add_action('delete_post', [$this, 'delete_post'], 10, 2);

		// Create index when new site is created.
		add_action('wpmu_new_blog', [$this, 'create_site_index'], 10, 1);

		// Delete index when site is deleted.
		add_action('wp_delete_site', [$this, 'delete_site_index'], 10, 1);
	}

	/**
	 * Index a single post.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function index_post(int $post_id, WP_Post $post): void
	{
		// Skip autosaves and revisions.
		if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
			return;
		}

		// Only index published posts.
		if ('publish' !== $post->post_status) {
			return;
		}

		$document = $this->prepare_document($post);
		$blog_id = get_current_blog_id();
		$index_name = $this->client->get_index_name($blog_id);

		try {
			// Ensure index exists before indexing
			$this->ensure_index_exists($blog_id);

			$this->client
				->get_client()
				->index($index_name)
				->addDocuments([$document], 'id');
		} catch (Exception $e) {
			error_log("Meilisearch index error for post {$post_id}: " . $e->getMessage());
		}
	}

	/**
	 * Delete a post from the index.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function delete_post(int $post_id, WP_Post $post): void
	{
		$blog_id = get_current_blog_id();
		$index_name = $this->client->get_index_name($blog_id);

		try {
			$this->client
				->get_client()
				->index($index_name)
				->deleteDocument($post_id);
		} catch (Exception $e) {
			error_log("Meilisearch delete error for post {$post_id}: " . $e->getMessage());
		}
	}

	/**
	 * Bulk index all posts across the network using Fiber.
	 *
	 * @param callable|null $progress_callback Optional callback for progress updates.
	 * @return array Results with counts.
	 */
	public function bulk_index_network(null|callable $progress_callback = null): array
	{
		$sites = get_sites(['number' => 9999]);
		$results = [
			'total_sites' => count($sites),
			'total_posts' => 0,
			'indexed_posts' => 0,
			'errors' => [],
		];

		foreach ($sites as $site) {
			$fiber = new Fiber(function () use ($site, &$results, $progress_callback): void {
				$blog_id = (int) $site->blog_id;
				switch_to_blog($blog_id);

				$site_result = $this->index_site_posts($blog_id);
				$results['total_posts'] += $site_result['total'];
				$results['indexed_posts'] += $site_result['indexed'];

				if (isset($site_result['errors']) && is_array($site_result['errors']) && count($site_result['errors']) > 0) {
					$results['errors'][$blog_id] = $site_result['errors'];
				}

				if ($progress_callback) {
					$progress_callback($blog_id, $site_result);
				}

				restore_current_blog();
			});

			$fiber->start();
		}

		return $results;
	}

	/**
	 * Index all posts for a specific site.
	 *
	 * @param int $blog_id Site ID.
	 * @return array Results with counts.
	 */
	public function index_site_posts(int $blog_id): array
	{
		$index_name = $this->client->get_index_name($blog_id);
		$results = [
			'total' => 0,
			'indexed' => 0,
			'errors' => [],
		];

		// Ensure index exists before indexing
		if (!$this->ensure_index_exists($blog_id)) {
			$results['errors'][] = "Failed to create or access index for blog {$blog_id}";
			return $results;
		}

		// Ensure we're in the correct blog context.
		$current_blog_id = get_current_blog_id();
		if ($current_blog_id !== $blog_id) {
			switch_to_blog($blog_id);
		}

		// Get all published posts.
		$args = [
			'post_type' => 'any',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'ID',
			'order' => 'ASC',
		];

		$posts = get_posts($args);
		$results['total'] = count($posts);

		// Prepare documents in batches.
		$batch_size = 100;
		$documents = [];

		foreach ($posts as $post) {
			$documents[] = $this->prepare_document($post);

			// Index in batches.
			if (count($documents) >= $batch_size) {
				try {
					$this->client
						->get_client()
						->index($index_name)
						->addDocuments($documents, 'id');
					$results['indexed'] += count($documents);
					$documents = [];
				} catch (Exception $e) {
					$results['errors'][] = $e->getMessage();
				}
			}
		}

		// Index remaining documents.
		if (is_array($documents) && count($documents) > 0) {
			try {
				$this->client
					->get_client()
					->index($index_name)
					->addDocuments($documents, 'id');
				$results['indexed'] += count($documents);
			} catch (Exception $e) {
				$results['errors'][] = $e->getMessage();
			}
		}

		// Restore blog context if needed.
		if ($current_blog_id !== $blog_id) {
			restore_current_blog();
		}

		return $results;
	}

	/**
	 * Prepare a post document for indexing.
	 *
	 * @param WP_Post $post Post object.
	 * @return array Document data.
	 */
	private function prepare_document(WP_Post $post): array
	{
		$author = get_userdata((int) $post->post_author);

		return [
			'id' => $post->ID,
			'blog_id' => get_current_blog_id(),
			'title' => $post->post_title,
			'content' => wp_strip_all_tags($post->post_content),
			'excerpt' => '' !== $post->post_excerpt ? $post->post_excerpt : wp_trim_words($post->post_content, 55),
			'post_type' => $post->post_type,
			'post_status' => $post->post_status,
			'date' => strtotime($post->post_date),
			'modified' => strtotime($post->post_modified),
			'author' => $author ? $author->display_name : '',
			'author_id' => $post->post_author,
			'categories' => $this->get_post_terms($post->ID, 'category'),
			'tags' => $this->get_post_terms($post->ID, 'post_tag'),
			'permalink' => get_permalink($post->ID),
		];
	}

	/**
	 * Get post terms as array of names.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array Term names.
	 */
	private function get_post_terms(int $post_id, string $taxonomy): array
	{
		$terms = get_the_terms($post_id, $taxonomy);

		if (!$terms || is_wp_error($terms)) {
			return [];
		}

		return wp_list_pluck($terms, 'name');
	}

	/**
	 * Ensure that an index exists for a blog.
	 * Creates the index if it doesn't exist.
	 *
	 * @param int $blog_id Blog ID.
	 * @return bool True if index exists or was created successfully.
	 */
	private function ensure_index_exists(int $blog_id): bool
	{
		$index_name = $this->client->get_index_name($blog_id);

		try {
			// Try to get index stats to check if it exists
			$this->client->get_client()->index($index_name)->stats();
			return true;
		} catch (Exception $e) {
			// Index doesn't exist, try to create it
			try {
				$this->client->create_index($blog_id);
				error_log("Meilisearch: Created missing index for blog {$blog_id}: {$index_name}");
				return true;
			} catch (Exception $create_error) {
				error_log("Meilisearch: Failed to create index for blog {$blog_id}: " . $create_error->getMessage());
				return false;
			}
		}
	}

	/**
	 * Create index for a new site.
	 *
	 * @param int $blog_id New site ID.
	 */
	public function create_site_index(int $blog_id): void
	{
		$this->client->create_index($blog_id);
	}

	/**
	 * Delete index for a deleted site.
	 *
	 * @param WP_Site $site Site object.
	 */
	public function delete_site_index(WP_Site $site): void
	{
		$this->client->delete_index((int) $site->blog_id);
	}
}
