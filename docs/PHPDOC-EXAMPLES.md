# Exemplos de Documentação PHPDoc

Este arquivo contém exemplos práticos de como documentar diferentes elementos do código seguindo os padrões phpDocumentor e WordPress.

## Classe Completa

```php
<?php
/**
 * Meilisearch Client Wrapper
 *
 * Provides a WordPress-friendly interface to the Meilisearch PHP SDK.
 * Handles connection management, index operations, and settings configuration
 * for WordPress Multisite environments.
 *
 * @since      0.1.0
 * @package    Meilisearch
 * @subpackage Meilisearch/Includes
 * @author     Your Name <email@example.com>
 *
 * @link https://docs.meilisearch.com/
 * @see  \Meilisearch\Client For the underlying Meilisearch client
 */
class Meilisearch_Client {

    /**
     * The Meilisearch client instance.
     *
     * @since  0.1.0
     * @access private
     * @var    \Meilisearch\Client $client Meilisearch SDK client.
     */
    private Client $client;

    /**
     * Meilisearch host URL.
     *
     * @since  0.1.0
     * @access private
     * @var    string $host The server URL (e.g., http://localhost:7700).
     */
    private string $host;

    /**
     * Initialize the client.
     *
     * Creates a new Meilisearch client instance with the provided credentials.
     * Validates the connection and throws an exception if it fails.
     *
     * @since 0.1.0
     *
     * @param string $host       The Meilisearch server URL.
     * @param string $master_key The master API key for authentication.
     *
     * @throws \Exception If connection to Meilisearch server fails.
     *
     * @example
     * ```php
     * $client = new Meilisearch_Client(
     *     'http://localhost:7700',
     *     'your-master-key'
     * );
     * ```
     */
    public function __construct( string $host, string $master_key ) {
        // Implementation
    }

    /**
     * Get the index name for a specific blog.
     *
     * Generates the standardized index name following the pattern:
     * wp_{blog_id}_posts
     *
     * @since 0.1.0
     *
     * @param int $blog_id The WordPress blog/site ID.
     * @return string The formatted index name.
     *
     * @example
     * ```php
     * $index = $client->get_index_name(2);
     * echo $index; // Outputs: "wp_2_posts"
     * ```
     */
    public function get_index_name( int $blog_id ): string {
        return "wp_{$blog_id}_posts";
    }

    /**
     * Create an index for a blog.
     *
     * Creates a new Meilisearch index with configured searchable,
     * filterable, and sortable attributes.
     *
     * @since 0.1.0
     *
     * @param int $blog_id The blog ID to create an index for.
     * @return array|null Task information or null on failure.
     *
     * @throws \Meilisearch\Exceptions\ApiException If index creation fails.
     *
     * @see https://docs.meilisearch.com/reference/api/indexes.html
     *
     * @example
     * ```php
     * try {
     *     $task = $client->create_index(2);
     *     if ($task) {
     *         echo "Index created with task UID: {$task['taskUid']}";
     *     }
     * } catch (\Exception $e) {
     *     error_log("Failed to create index: " . $e->getMessage());
     * }
     * ```
     */
    public function create_index( int $blog_id ): ?array {
        // Implementation
    }
}
```

## Método com Múltiplos Retornos

```php
/**
 * Search posts across the network.
 *
 * Performs a multi-index search across all blogs in the WordPress network.
 * Results are combined and sorted by relevance.
 *
 * @since 0.1.0
 *
 * @param string $query Search query string.
 * @param array  $args {
 *     Optional. Search arguments.
 *
 *     @type int    $limit  Maximum results per index. Default 20.
 *     @type int    $offset Results offset for pagination. Default 0.
 *     @type string $filter Filter expression. Default empty.
 *     @type array  $sort   Sort criteria. Default empty array.
 * }
 * @return array {
 *     Search results and metadata.
 *
 *     @type array $hits  Array of matching documents.
 *     @type int   $total Total number of results across all indexes.
 *     @type int   $time  Processing time in milliseconds.
 * }
 *
 * @throws \Exception If search query fails.
 *
 * @example
 * ```php
 * $results = $searcher->search_network('wordpress', [
 *     'limit' => 10,
 *     'filter' => 'post_status = publish',
 * ]);
 *
 * foreach ($results['hits'] as $hit) {
 *     echo $hit['title'] . ' (Blog ' . $hit['blog_id'] . ')';
 * }
 * ```
 */
public function search_network( string $query, array $args = [] ): array {
    // Implementation
}
```

## Hook/Filter Documentation

```php
/**
 * Filter Meilisearch search results before returning.
 *
 * Allows modification of search results after they are retrieved
 * from Meilisearch but before being returned to WordPress.
 *
 * @since 0.1.0
 *
 * @param array  $results Search results from Meilisearch.
 * @param string $query   The search query string.
 * @param array  $args    Search arguments passed to the query.
 *
 * @example
 * ```php
 * add_filter('meilisearch_search_results', function($results, $query, $args) {
 *     // Boost results from specific blog
 *     usort($results['hits'], function($a, $b) {
 *         if ($a['blog_id'] === 2) return -1;
 *         if ($b['blog_id'] === 2) return 1;
 *         return 0;
 *     });
 *     return $results;
 * }, 10, 3);
 * ```
 */
$results = apply_filters('meilisearch_search_results', $results, $query, $args);
```

## Propriedades com Tipos Complexos

```php
/**
 * Cache of search results indexed by query hash.
 *
 * Stores recent search results to avoid redundant API calls.
 * Each entry contains the full result set and metadata.
 *
 * @since 0.1.0
 * @var array<string, array{hits: array, total: int, cached_at: int}>
 */
private array $results_cache = [];

/**
 * Map of post permalinks for cross-site results.
 *
 * Keys are in format "{blog_id}_{post_id}", values are
 * the correct permalink URLs from Meilisearch.
 *
 * @since 0.1.0
 * @var array<string, string>
 */
private array $permalink_map = [];
```

## Método Deprecated

```php
/**
 * Index all posts (deprecated).
 *
 * @since      0.1.0
 * @deprecated 0.2.0 Use index_site_posts() instead.
 * @see        Meilisearch_Indexer::index_site_posts()
 *
 * @param int $blog_id Blog ID to index.
 * @return bool True on success, false on failure.
 */
public function index_all( int $blog_id ): bool {
    _deprecated_function(__METHOD__, '0.2.0', 'index_site_posts');
    return $this->index_site_posts($blog_id);
}
```

## Constantes

```php
/**
 * Plugin version number.
 *
 * @since 0.1.0
 * @var string VERSION Semantic version string.
 */
const VERSION = '0.1.0';

/**
 * Default batch size for indexing operations.
 *
 * Number of posts to process in a single batch during
 * bulk indexing operations.
 *
 * @since 0.1.0
 * @var int BATCH_SIZE Posts per batch.
 */
const BATCH_SIZE = 100;
```

## Callback Functions

```php
/**
 * WordPress action callback: Index post on save.
 *
 * Automatically indexes a post when it's published or updated.
 * Hooked to 'save_post' action.
 *
 * @since 0.1.0
 *
 * @param int     $post_id Post ID being saved.
 * @param WP_Post $post    Post object.
 * @param bool    $update  Whether this is an update or new post.
 *
 * @return void
 *
 * @see add_action()
 * @link https://developer.wordpress.org/reference/hooks/save_post/
 */
public function index_post( int $post_id, WP_Post $post, bool $update ): void {
    // Implementation
}
```

## Tags Importantes Resumidas

### Estrutura
- `@since` - Versão de introdução
- `@package` - Pacote principal
- `@subpackage` - Subpacote/módulo
- `@author` - Autor do código
- `@copyright` - Informações de copyright
- `@license` - Tipo de licença

### Documentação
- `@param` - Parâmetros do método
- `@return` - Tipo de retorno
- `@throws` - Exceções lançadas
- `@var` - Tipo de variável/propriedade

### Relacionamentos
- `@see` - Referência a código relacionado
- `@link` - Link externo
- `@uses` - Usa outro elemento
- `@used-by` - Usado por outro elemento

### Estado
- `@deprecated` - Marcado como obsoleto
- `@todo` - Trabalho pendente
- `@internal` - Uso interno apenas
- `@access` - Nível de acesso (public/private/protected)

### Exemplos
- `@example` - Exemplo de código
- `@code` - Bloco de código
- `@endcode` - Fim do bloco

### WordPress Específico
- `@global` - Variável global usada
- `@wp-hook` - Hook do WordPress
- `@wp-cli` - Comando WP-CLI

## Dicas de Boas Práticas

1. **Seja conciso mas completo** - Uma linha de resumo + detalhes
2. **Use exemplos** - Código de exemplo é muito valioso
3. **Documente exceções** - Sempre indique o que pode dar errado
4. **Tipos precisos** - Use tipos PHPDoc detalhados (array<string, int>)
5. **Links úteis** - Referencie documentação oficial
6. **Mantenha atualizado** - Documente mudanças quando alterar código
7. **Markdown suportado** - Use formatação nos blocos de descrição
```
