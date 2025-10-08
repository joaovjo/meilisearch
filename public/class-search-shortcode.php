<?php

declare(strict_types=1);

/**
 * Shortcode de Busca Meilisearch
 *
 * @package Meilisearch
 */

/**
 * Classe Meilisearch_Search_Shortcode
 *
 * Fornece um shortcode [meilisearch_search] para exibir interface de busca
 * com totalizador de resultados.
 */
class Meilisearch_Search_Shortcode
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
		add_shortcode('meilisearch_search', [$this, 'render_search_shortcode']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_search_assets']);
	}

	/**
	 * Enfileirar assets CSS e JS para o shortcode.
	 */
	public function enqueue_search_assets(): void
	{
		// Apenas enfileirar se a página contém o shortcode.
		global $post;
		if (!$post || !has_shortcode($post->post_content, 'meilisearch_search')) {
			return;
		}

		wp_enqueue_style(
			'meilisearch-search-results',
			plugins_url('assets/css/search-results.css', dirname(__FILE__)),
			[],
			'1.0.0'
		);
	}

	/**
	 * Renderizar shortcode de busca.
	 *
	 * @param array  $atts    Atributos do shortcode.
	 * @param string $content Conteúdo do shortcode.
	 * @return string HTML renderizado.
	 */
	public function render_search_shortcode(array $atts = [], string $content = ''): string
	{
		// Atributos padrão.
		$atts = shortcode_atts([
			'placeholder' => __('Digite sua busca...', 'meilisearch'),
			'button_text' => __('Buscar', 'meilisearch'),
			'results_per_page' => 10,
			'show_excerpt' => 'yes',
			'show_date' => 'yes',
			'show_author' => 'yes',
		], $atts, 'meilisearch_search');

		// Obter termo de busca da URL.
		$search_query = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
		$paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

		// Iniciar buffer de saída.
		ob_start();

		// Renderizar formulário de busca.
		$this->render_search_form($search_query, $atts);

		// Se houver termo de busca, executar busca e exibir resultados.
		if (!empty($search_query)) {
			$this->render_search_results($search_query, $paged, $atts);
		}

		return ob_get_clean();
	}

	/**
	 * Renderizar formulário de busca.
	 *
	 * @param string $search_query Termo de busca atual.
	 * @param array  $atts         Atributos do shortcode.
	 */
	private function render_search_form(string $search_query, array $atts): void
	{
		$current_url = remove_query_arg(['s', 'paged']);
		?>
		<div class="meilisearch-search-form-wrapper">
			<form role="search" method="get" class="meilisearch-search-form" action="<?php echo esc_url($current_url); ?>">
				<div class="meilisearch-search-form-inner">
					<label class="screen-reader-text" for="meilisearch-search-input">
						<?php esc_html_e('Buscar:', 'meilisearch'); ?>
					</label>
					<input
						type="search"
						id="meilisearch-search-input"
						class="meilisearch-search-input"
						name="s"
						value="<?php echo esc_attr($search_query); ?>"
						placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
						required
					/>
					<button type="submit" class="meilisearch-search-submit">
						<?php echo esc_html($atts['button_text']); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Renderizar resultados de busca.
	 *
	 * @param string $search_query Termo de busca.
	 * @param int    $paged        Página atual.
	 * @param array  $atts         Atributos do shortcode.
	 */
	private function render_search_results(string $search_query, int $paged, array $atts): void
	{
		$results_per_page = absint($atts['results_per_page']);
		$offset = ($paged - 1) * $results_per_page;

		// Executar busca via Meilisearch.
		$searcher = new Meilisearch_Searcher($this->client);
		$results = $searcher->search_network($search_query, [
			'limit' => $results_per_page,
			'offset' => $offset,
		]);

		$total_results = $results['total'] ?? 0;
		$hits = $results['hits'] ?? [];

		?>
		<div class="meilisearch-search-results-wrapper">
			<?php $this->render_results_counter($search_query, $total_results, $paged, $results_per_page); ?>

			<?php if (empty($hits)) : ?>
				<div class="meilisearch-no-results">
					<p><?php
						printf(
							/* translators: %s: termo de busca */
							esc_html__('Nenhum resultado encontrado para "%s".', 'meilisearch'),
							'<strong>' . esc_html($search_query) . '</strong>'
						);
					?></p>
					<p><?php esc_html_e('Tente usar palavras-chave diferentes ou verifique a ortografia.', 'meilisearch'); ?></p>
				</div>
			<?php else : ?>
				<div class="meilisearch-results-list">
					<?php foreach ($hits as $hit) : ?>
						<?php $this->render_result_item($hit, $atts); ?>
					<?php endforeach; ?>
				</div>

				<?php $this->render_pagination($total_results, $paged, $results_per_page, $search_query); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renderizar contador de resultados (totalizador).
	 *
	 * @param string $search_query     Termo de busca.
	 * @param int    $total_results    Total de resultados encontrados.
	 * @param int    $paged            Página atual.
	 * @param int    $results_per_page Resultados por página.
	 */
	private function render_results_counter(string $search_query, int $total_results, int $paged, int $results_per_page): void
	{
		$start = $total_results > 0 ? (($paged - 1) * $results_per_page) + 1 : 0;
		$end = min($paged * $results_per_page, $total_results);

		?>
		<div class="meilisearch-results-counter">
			<?php if ($total_results > 0) : ?>
				<p class="meilisearch-results-info">
					<?php
					printf(
						/* translators: 1: start number, 2: end number (only in plural), 3: total results, 4: search term */
						esc_html(_n(
							'Exibindo %1$s de %3$s resultado para "%4$s"',
							'Exibindo %1$s - %2$s de %3$s resultados para "%4$s"',
							$total_results,
							'meilisearch'
						)),
						'<strong>' . number_format_i18n($start) . '</strong>',
						'<strong>' . number_format_i18n($end) . '</strong>',
						'<strong>' . number_format_i18n($total_results) . '</strong>',
						'<strong>' . esc_html($search_query) . '</strong>'
					);
					?>
				</p>
			<?php else : ?>
				<p class="meilisearch-results-info meilisearch-no-results-info">
					<?php
					printf(
						/* translators: %s: search term */
						esc_html__('0 resultados encontrados para "%s"', 'meilisearch'),
						'<strong>' . esc_html($search_query) . '</strong>'
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renderizar um item de resultado.
	 *
	 * @param array $hit  Dados do resultado.
	 * @param array $atts Atributos do shortcode.
	 */
	private function render_result_item(array $hit, array $atts): void
	{
		$title = $hit['title'] ?? __('Sem título', 'meilisearch');
		$permalink = $hit['permalink'] ?? '#';
		$excerpt = $hit['excerpt'] ?? '';
		$content = $hit['content'] ?? '';
		$post_type = $hit['post_type'] ?? 'post';
		$blog_id = $hit['blog_id'] ?? get_current_blog_id();
		
		// Calcular ranking score e classe de relevância.
		$ranking_score = isset($hit['_rankingScore']) ? $hit['_rankingScore'] : 0;
		$relevance_percent = round($ranking_score * 100, 1);
		$relevance_class = $ranking_score >= 0.8 ? 'high' : ($ranking_score >= 0.5 ? 'medium' : 'low');
		
		// Formatar data se disponível.
		$post_date = '';
		if (!empty($hit['date'])) {
			$timestamp = is_numeric($hit['date']) ? $hit['date'] : strtotime($hit['date']);
			$post_date = date_i18n(get_option('date_format'), $timestamp);
		}

		// Obter nome do autor se disponível.
		$author_name = '';
		if (!empty($hit['author']['name'])) {
			$author_name = $hit['author']['name'];
		}

		// Se não houver excerpt, criar um do conteúdo.
		if (empty($excerpt) && !empty($content)) {
			$excerpt = wp_trim_words(wp_strip_all_tags($content), 30);
		}

		?>
		<article class="meilisearch-result-item" data-blog-id="<?php echo esc_attr($blog_id); ?>" data-post-type="<?php echo esc_attr($post_type); ?>">
			<header class="meilisearch-result-header">
				<h2 class="meilisearch-result-title">
					<a href="<?php echo esc_url($permalink); ?>">
						<?php echo esc_html($title); ?>
					</a>
					<?php if ($ranking_score > 0) : ?>
						<span class="meilisearch-relevance-badge meilisearch-relevance-<?php echo esc_attr($relevance_class); ?>" title="Relevância: <?php echo esc_attr($relevance_percent); ?>%">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="display:inline-block;vertical-align:middle;margin-right:3px;">
								<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
							</svg>
							<?php echo esc_html($relevance_percent); ?>%
						</span>
					<?php endif; ?>
				</h2>

				<?php if (('yes' === $atts['show_date'] && !empty($post_date)) || ('yes' === $atts['show_author'] && !empty($author_name))) : ?>
					<div class="meilisearch-result-meta">
						<?php if ('yes' === $atts['show_date'] && !empty($post_date)) : ?>
							<span class="meilisearch-result-date">
								<time datetime="<?php echo esc_attr(date('c', $timestamp ?? time())); ?>">
									<?php echo esc_html($post_date); ?>
								</time>
							</span>
						<?php endif; ?>

						<?php if ('yes' === $atts['show_author'] && !empty($author_name)) : ?>
							<?php if ('yes' === $atts['show_date'] && !empty($post_date)) : ?>
								<span class="meilisearch-meta-separator"> • </span>
							<?php endif; ?>
							<span class="meilisearch-result-author">
								<?php
								printf(
									/* translators: %s: nome do autor */
									esc_html__('por %s', 'meilisearch'),
									esc_html($author_name)
								);
								?>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</header>

			<?php if ('yes' === $atts['show_excerpt'] && !empty($excerpt)) : ?>
				<div class="meilisearch-result-excerpt">
					<p><?php echo esc_html($excerpt); ?></p>
				</div>
			<?php endif; ?>

			<footer class="meilisearch-result-footer">
				<a href="<?php echo esc_url($permalink); ?>" class="meilisearch-result-link">
					<?php esc_html_e('Ler mais', 'meilisearch'); ?> →
				</a>
			</footer>
		</article>
		<?php
	}

	/**
	 * Renderizar paginação.
	 *
	 * @param int    $total_results    Total de resultados.
	 * @param int    $paged            Página atual.
	 * @param int    $results_per_page Resultados por página.
	 * @param string $search_query     Termo de busca.
	 */
	private function render_pagination(int $total_results, int $paged, int $results_per_page, string $search_query): void
	{
		$total_pages = ceil($total_results / $results_per_page);

		if ($total_pages <= 1) {
			return;
		}

		$current_url = remove_query_arg(['paged']);

		?>
		<nav class="meilisearch-pagination" role="navigation" aria-label="<?php esc_attr_e('Paginação de resultados', 'meilisearch'); ?>">
			<ul class="meilisearch-pagination-list">
				<?php if ($paged > 1) : ?>
					<li class="meilisearch-pagination-item meilisearch-pagination-prev">
						<a href="<?php echo esc_url(add_query_arg(['s' => $search_query, 'paged' => $paged - 1], $current_url)); ?>" rel="prev">
							← <?php esc_html_e('Anterior', 'meilisearch'); ?>
						</a>
					</li>
				<?php endif; ?>

				<?php
				// Gerar números de página (mostrar 5 páginas ao redor da atual).
				$range = 2;
				$start_page = max(1, $paged - $range);
				$end_page = min($total_pages, $paged + $range);

				// Primeira página.
				if ($start_page > 1) {
					?>
					<li class="meilisearch-pagination-item">
						<a href="<?php echo esc_url(add_query_arg(['s' => $search_query, 'paged' => 1], $current_url)); ?>">1</a>
					</li>
					<?php if ($start_page > 2) : ?>
						<li class="meilisearch-pagination-item meilisearch-pagination-ellipsis">
							<span>...</span>
						</li>
					<?php endif; ?>
				<?php } ?>

				<?php for ($i = $start_page; $i <= $end_page; $i++) : ?>
					<li class="meilisearch-pagination-item <?php echo $i === $paged ? 'meilisearch-pagination-current' : ''; ?>">
						<?php if ($i === $paged) : ?>
							<span aria-current="page"><?php echo esc_html($i); ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url(add_query_arg(['s' => $search_query, 'paged' => $i], $current_url)); ?>">
								<?php echo esc_html($i); ?>
							</a>
						<?php endif; ?>
					</li>
				<?php endfor; ?>

				<?php
				// Última página.
				if ($end_page < $total_pages) {
					if ($end_page < $total_pages - 1) : ?>
						<li class="meilisearch-pagination-item meilisearch-pagination-ellipsis">
							<span>...</span>
						</li>
					<?php endif; ?>
					<li class="meilisearch-pagination-item">
						<a href="<?php echo esc_url(add_query_arg(['s' => $search_query, 'paged' => $total_pages], $current_url)); ?>">
							<?php echo esc_html($total_pages); ?>
						</a>
					</li>
				<?php } ?>

				<?php if ($paged < $total_pages) : ?>
					<li class="meilisearch-pagination-item meilisearch-pagination-next">
						<a href="<?php echo esc_url(add_query_arg(['s' => $search_query, 'paged' => $paged + 1], $current_url)); ?>" rel="next">
							<?php esc_html_e('Próxima', 'meilisearch'); ?> →
						</a>
					</li>
				<?php endif; ?>
			</ul>
		</nav>
		<?php
	}
}
