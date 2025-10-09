<?php
/**
 * Configurações de Busca Multi-Padrão
 *
 * Permite que administradores selecionem padrões de índice adicionais para incluir nos resultados de busca
 * além do padrão configurado da rede atual.
 *
 * @package    Meilisearch
 * @subpackage Admin
 * @since      1.0.0
 */

/**
 * Classe Meilisearch_Multi_Pattern_Search
 *
 * Gerencia a configuração de múltiplos padrões de índice para busca entre redes.
 * Isso permite buscar em diferentes redes WordPress que compartilham o mesmo
 * servidor Meilisearch.
 *
 * Recursos:
 * - Detectar todos os padrões de índice disponíveis do Meilisearch
 * - Selecionar padrões adicionais para incluir nas buscas
 * - Salvar configuração por rede
 * - Detecção de padrão em tempo real (sem cache)
 *
 * @since 1.0.0
 */
class Meilisearch_Multi_Pattern_Search {

	/**
	 * Instância do cliente Meilisearch
	 *
	 * @var Meilisearch_Client
	 */
	private $client;

	/**
	 * Nome da opção para armazenar padrões selecionados
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'meilisearch_additional_patterns';

	/**
	 * Construtor
	 *
	 * Inicializa as configurações de busca multi-padrão e hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$settings = get_site_option( 'meilisearch_settings', array() );
		if ( ! empty( $settings['host'] ) ) {
			$this->client = new Meilisearch_Client( $settings['host'], $settings['master_key'] ?? '' );
		}
	}

	/**
	 * Inicializar hooks
	 *
	 * Registra todos os hooks do WordPress necessários para a funcionalidade de busca multi-padrão.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'network_admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_post_save_additional_patterns', array( $this, 'save_settings' ) );
	}

	/**
	 * Adicionar página de menu ao admin do WordPress
	 *
	 * Adiciona a página de Busca Multi-Padrão aos menus de administração de rede e site.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_menu_page(): void {
		$page_title = __( 'Multi-Pattern Search', 'meilisearch' );
		$menu_title = __( 'Multi-Pattern Search', 'meilisearch' );
		$capability = is_multisite() ? 'manage_network_options' : 'manage_options';
		$menu_slug  = 'meilisearch-multi-pattern';
		$callback   = array( $this, 'render_page' );

		add_submenu_page(
			'meilisearch-dashboard',
			$page_title,
			$menu_title,
			$capability,
			$menu_slug,
			$callback
		);
	}

	/**
	 * Obter todos os índices do Meilisearch
	 *
	 * Busca a lista completa de índices do servidor Meilisearch.
	 * Estes dados não são armazenados em cache para garantir precisão em tempo real.
	 *
	 * @since 1.0.0
	 * @return array Array de objetos de índice com uid, primaryKey e outros metadados
	 */
	private function get_all_indexes(): array {
		if ( ! $this->client ) {
			return array();
		}

		try {
			$sdk_client = $this->client->get_client();
			$indexes_result = $sdk_client->getIndexes();
			
			$indexes = array();
			foreach ( $indexes_result->getResults() as $index ) {
				$indexes[] = array(
					'uid'        => $index->getUid(),
					'primaryKey' => $index->getPrimaryKey(),
					'createdAt'  => $index->getCreatedAt(),
					'updatedAt'  => $index->getUpdatedAt(),
				);
			}
			
			return $indexes;
		} catch ( Exception $e ) {
			error_log( 'Meilisearch Multi-Pattern: Error fetching indexes - ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Analisar nome do índice para extrair componentes do padrão
	 *
	 * Analisa um nome de índice para identificar sua estrutura de padrão.
	 * Suporta vários padrões de nomenclatura de índice multisite do WordPress.
	 *
	 * Exemplos de padrão:
	 * - wp_posts -> prefix: wp_, suffix: posts
	 * - setur_1_posts -> prefix: setur_, blog_id: 1, suffix: posts
	 * - mysite_posts -> prefix: mysite_, suffix: posts
	 *
	 * @since 1.0.0
	 * @param string $index_name O nome do índice para analisar.
	 * @return array{prefix: string, blog_id: string|null, suffix: string}|null Componentes do padrão ou null se não houver correspondência
	 */
	private function parse_index_name( string $index_name ): ?array {
		// Padrão 1: prefix_blogid_suffix (ex: setur_1_posts, setur_2_posts)
		if ( preg_match( '/^([a-zA-Z0-9_]+)_(\d+)_([a-zA-Z0-9_]+)$/', $index_name, $matches ) ) {
			return array(
				'prefix'  => $matches[1] . '_',
				'blog_id' => $matches[2],
				'suffix'  => $matches[3],
			);
		}

		// Padrão 2: prefix_suffix (ex: wp_posts, mysite_posts)
		if ( preg_match( '/^([a-zA-Z0-9_]+)_([a-zA-Z0-9_]+)$/', $index_name, $matches ) ) {
			return array(
				'prefix'  => $matches[1] . '_',
				'blog_id' => null,
				'suffix'  => $matches[2],
			);
		}

		return null;
	}

	/**
	 * Analisar todos os padrões de índice
	 *
	 * Agrupa índices por seus padrões detectados e extrai metadados para cada padrão.
	 *
	 * @since 1.0.0
	 * @return array Array de padrões com metadados (format, count, network_url, indexes)
	 */
	private function analyze_index_patterns(): array {
		$indexes = $this->get_all_indexes();
		$patterns = array();

		foreach ( $indexes as $index ) {
			$parsed = $this->parse_index_name( $index['uid'] );

			if ( ! $parsed ) {
				continue;
			}

			// Criar chave de padrão
			if ( $parsed['blog_id'] !== null ) {
				$pattern_key = $parsed['prefix'] . '{blog_id}_' . $parsed['suffix'];
			} else {
				$pattern_key = $parsed['prefix'] . $parsed['suffix'];
			}

			if ( ! isset( $patterns[ $pattern_key ] ) ) {
				$patterns[ $pattern_key ] = array(
					'format'      => $pattern_key,
					'prefix'      => $parsed['prefix'],
					'suffix'      => $parsed['suffix'],
					'has_blog_id' => $parsed['blog_id'] !== null,
					'count'       => 0,
					'indexes'     => array(),
				);
			}

			$patterns[ $pattern_key ]['count']++;
			$patterns[ $pattern_key ]['indexes'][] = $index['uid'];
		}

		// Obter URL da rede para cada padrão
		foreach ( $patterns as $key => $data ) {
			$patterns[ $key ]['network_url'] = $this->get_network_url_for_pattern( $data['indexes'] );
		}

		return $patterns;
	}

	/**
	 * Obter URL da rede a partir de documentos do índice
	 *
	 * Extrai a URL da rede buscando documentos nos índices do padrão
	 * e analisando o campo permalink.
	 *
	 * @since 1.0.0
	 * @param array $index_names Array de nomes de índice pertencentes a este padrão.
	 * @return string|null URL da rede ou null se não encontrado
	 */
	private function get_network_url_for_pattern( array $index_names ): ?string {
		if ( ! $this->client ) {
			return null;
		}

		foreach ( $index_names as $index_name ) {
			try {
				$sdk_client = $this->client->get_client();
				$index = $sdk_client->index( $index_name );
				$results = $index->search( '', array( 'limit' => 1 ) );
				$hits = $results->getHits();

				if ( ! empty( $hits ) && isset( $hits[0]['permalink'] ) ) {
					$permalink = $hits[0]['permalink'];
					$parsed = parse_url( $permalink );

					if ( isset( $parsed['scheme'], $parsed['host'] ) ) {
						$url = $parsed['scheme'] . '://' . $parsed['host'];
						if ( isset( $parsed['port'] ) ) {
							$url .= ':' . $parsed['port'];
						}
						return $url;
					}
				}
			} catch ( Exception $e ) {
				continue;
			}
		}

		return null;
	}

	/**
	 * Obter padrão da rede atual
	 *
	 * Determina o padrão de índice configurado para a rede WordPress atual.
	 *
	 * @since 1.0.0
	 * @return string O padrão de índice da rede atual
	 */
	private function get_current_pattern(): string {
		$settings = get_site_option( 'meilisearch_settings', array() );
		$format = $settings['index_format'] ?? '{prefix}posts';

		// Converter o template de formato para uma representação de padrão
		// Mantemos {blog_id} e {site_id} como está para correspondência de padrão
		// Mas substituir {prefix} com o valor de prefixo real
		
		// Obter o prefixo real para o site principal
		switch_to_blog( 1 );
		global $wpdb;
		$prefix = $wpdb->prefix;
		restore_current_blog();

		// Substituir apenas o marcador {prefix}, manter {blog_id} e {site_id} para correspondência de padrão
		$pattern = str_replace( '{prefix}', $prefix, $format );

		return $pattern;
	}

	/**
	 * Obter padrões adicionais salvos
	 *
	 * Recupera a lista de padrões adicionais selecionados para busca entre redes.
	 *
	 * @since 1.0.0
	 * @return array Array de chaves de padrão selecionadas
	 */
	public function get_additional_patterns(): array {
		if ( is_multisite() ) {
			$patterns = get_site_option( self::OPTION_NAME, array() );
		} else {
			$patterns = get_option( self::OPTION_NAME, array() );
		}

		return is_array( $patterns ) ? $patterns : array();
	}

	/**
	 * Salvar configurações
	 *
	 * Processa o envio do formulário e salva os padrões adicionais selecionados.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function save_settings(): void {
		// Verificar nonce
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'save_additional_patterns' ) ) {
			wp_die( __( 'Security check failed', 'meilisearch' ) );
		}

		// Verificar permissões
		$capability = is_multisite() ? 'manage_network_options' : 'manage_options';
		if ( ! current_user_can( $capability ) ) {
			wp_die( __( 'You do not have permission to perform this action', 'meilisearch' ) );
		}

		// Obter padrões selecionados
		$selected_patterns = isset( $_POST['additional_patterns'] ) && is_array( $_POST['additional_patterns'] )
			? array_map( 'sanitize_text_field', $_POST['additional_patterns'] )
			: array();

		// Salvar no banco de dados
		if ( is_multisite() ) {
			update_site_option( self::OPTION_NAME, $selected_patterns );
		} else {
			update_option( self::OPTION_NAME, $selected_patterns );
		}

		// Redirecionar de volta com mensagem de sucesso
		$redirect_url = add_query_arg(
			array(
				'page'    => 'meilisearch-multi-pattern',
				'updated' => 'true',
			),
			is_multisite() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Renderizar a página de configurações
	 *
	 * Exibe a interface de configuração de busca multi-padrão.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( __( 'Multi-Pattern Search', 'meilisearch' ) ); ?></h1>

			<?php if ( ! $this->client ) : ?>
				<div class="notice notice-error">
					<p>
						<?php
						esc_html_e( 'Meilisearch is not configured. Please configure it in the Settings page first.', 'meilisearch' );
						?>
					</p>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<?php
			$patterns = $this->analyze_index_patterns();
			$current_pattern = $this->get_current_pattern();
			$selected_patterns = $this->get_additional_patterns();
			$updated = isset( $_GET['updated'] ) && $_GET['updated'] === 'true';
			?>

			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved successfully!', 'meilisearch' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="card">
				<h2><?php esc_html_e( 'About Multi-Pattern Search', 'meilisearch' ); ?></h2>
				<p><?php esc_html_e( 'This feature allows you to search across multiple WordPress networks that share the same Meilisearch server. Select additional index patterns below to include their content in your search results.', 'meilisearch' ); ?></p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'save_additional_patterns' ); ?>
				<input type="hidden" name="action" value="save_additional_patterns">

				<h2><?php esc_html_e( 'Available Index Patterns', 'meilisearch' ); ?></h2>

				<?php if ( empty( $patterns ) ) : ?>
					<div class="notice notice-warning">
						<p><?php esc_html_e( 'No index patterns found in Meilisearch.', 'meilisearch' ); ?></p>
					</div>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th style="width: 50px;"><?php esc_html_e( 'Select', 'meilisearch' ); ?></th>
								<th><?php esc_html_e( 'Pattern', 'meilisearch' ); ?></th>
								<th><?php esc_html_e( 'Network URL', 'meilisearch' ); ?></th>
								<th><?php esc_html_e( 'Indexes', 'meilisearch' ); ?></th>
								<th><?php esc_html_e( 'Status', 'meilisearch' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $patterns as $pattern_key => $data ) : ?>
								<?php
								$is_current = ( $pattern_key === $current_pattern );
								$row_class = $is_current ? 'style="background-color: #e8f4f8;"' : '';
								?>
								<tr <?php echo $row_class; ?>>
									<td>
										<?php if ( $is_current ) : ?>
											<span style="color: #888;">—</span>
										<?php else : ?>
											<input 
												type="checkbox" 
												name="additional_patterns[]" 
												value="<?php echo esc_attr( $pattern_key ); ?>"
												<?php checked( in_array( $pattern_key, $selected_patterns, true ) ); ?>
											>
										<?php endif; ?>
									</td>
									<td>
										<strong><code><?php echo esc_html( $data['format'] ); ?></code></strong>
										<?php if ( $is_current ) : ?>
											<span class="dashicons dashicons-admin-site" style="color: #2271b1;"></span>
											<span style="color: #2271b1; font-weight: bold;">
												<?php esc_html_e( 'Current Network', 'meilisearch' ); ?>
											</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $data['network_url'] ) : ?>
											<a href="<?php echo esc_url( $data['network_url'] ); ?>" target="_blank">
												<?php echo esc_html( $data['network_url'] ); ?>
											</a>
										<?php else : ?>
											<span style="color: #999;">
												<?php esc_html_e( 'Unknown', 'meilisearch' ); ?>
											</span>
										<?php endif; ?>
									</td>
									<td>
										<strong><?php echo esc_html( $data['count'] ); ?></strong> 
										<?php 
										echo esc_html( 
											sprintf( 
												_n( 'index', 'indexes', $data['count'], 'meilisearch' ), 
												$data['count'] 
											) 
										); 
										?>
									</td>
									<td>
										<?php if ( $is_current ) : ?>
											<span style="color: #2271b1;">
												<strong><?php esc_html_e( 'Current', 'meilisearch' ); ?></strong>
											</span>
										<?php elseif ( in_array( $pattern_key, $selected_patterns, true ) ) : ?>
											<span style="color: #46b450;">
												<?php esc_html_e( 'Active', 'meilisearch' ); ?>
											</span>
										<?php else : ?>
											<span style="color: #999;">
												<?php esc_html_e( 'Inactive', 'meilisearch' ); ?>
											</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Save Settings', 'meilisearch' ); ?>
						</button>
					</p>
				<?php endif; ?>
			</form>

			<style>
				.card {
					padding: 15px;
					margin: 20px 0;
					background: #fff;
					border-left: 4px solid #2271b1;
				}
				.card h2 {
					margin-top: 0;
				}
			</style>
		</div>
		<?php
	}
}
