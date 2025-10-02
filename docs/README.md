# Documentação do Plugin

Este diretório contém a documentação do plugin Meilisearch Network Search.

## Documentação Disponível

- **[WP-CLI.md](WP-CLI.md)** - Guia completo dos comandos WP-CLI

## Gerando Documentação da API

O plugin usa phpDocumentor para gerar documentação automática do código.

### Instalação do phpDocumentor

```bash
# Via Composer (globalmente)
composer global require phpdocumentor/phpdocumentor

# Ou via PHAR
wget https://phpdoc.org/phpDocumentor.phar
chmod +x phpDocumentor.phar
```

### Gerando a Documentação

```bash
# Na raiz do plugin
phpdoc

# Ou se instalou via PHAR
php phpDocumentor.phar
```

A documentação será gerada em `build/docs/`.

### Configuração

A configuração está em `phpdoc.xml` na raiz do plugin:

- **Parser target**: `build/api` - Cache de análise
- **Transformer target**: `build/docs` - Documentação HTML final
- **Template**: clean (template padrão limpo e moderno)
- **Arquivos incluídos**: includes/, admin/, public/, meilisearch.php
- **Arquivos ignorados**: vendor/, tests/, bin/, tools/

### Visualizando a Documentação

Após gerar, abra `build/docs/index.html` no navegador:

```bash
# Servir localmente com PHP
cd build/docs
php -S localhost:8080

# Ou abrir diretamente
xdg-open build/docs/index.html
```

## Estrutura de Documentação

### Classes Principais

1. **Meilisearch_Client** (`includes/class-client.php`)
   - Wrapper para cliente Meilisearch
   - Gerenciamento de índices
   - Configuração de atributos

2. **Meilisearch_Indexer** (`includes/class-indexer.php`)
   - Indexação de posts
   - Sincronização automática
   - Operações em lote

3. **Meilisearch_Searcher** (`includes/class-searcher.php`)
   - Buscas cross-site
   - Filtros e ordenação
   - Formatação de resultados

4. **Meilisearch_Search_Override** (`public/class-search-override.php`)
   - Substituição da busca WordPress
   - Integração com WP_Query
   - Correção de permalinks cross-site

5. **Meilisearch_Autocomplete** (`includes/class-autocomplete.php`)
   - Sugestões em tempo real
   - REST API endpoint
   - JavaScript frontend

6. **Meilisearch_CLI** (`includes/class-cli.php`)
   - Comandos WP-CLI
   - Operações administrativas
   - Automação e scripts

7. **Meilisearch_Network_Settings** (`admin/class-network-settings.php`)
   - Página de configurações
   - Validação de conexão
   - Interface administrativa

## Padrões de Documentação

### Blocos de Documentação

Todas as classes, métodos e propriedades devem ter blocos PHPDoc:

```php
/**
 * Breve descrição em uma linha.
 *
 * Descrição detalhada com múltiplas linhas
 * explicando o funcionamento, casos de uso,
 * e considerações importantes.
 *
 * @since 0.1.0
 * @package Meilisearch
 *
 * @param string $param1 Descrição do parâmetro.
 * @param int    $param2 Descrição do segundo parâmetro.
 * @return bool True em sucesso, false em falha.
 *
 * @throws Exception Quando ocorre erro de conexão.
 *
 * @example
 * $result = $instance->method('value', 123);
 */
public function method( string $param1, int $param2 ): bool {
    // ...
}
```

### Tags Importantes

- `@since` - Versão em que foi introduzido
- `@package` - Pacote/namespace do código
- `@param` - Parâmetros do método
- `@return` - Tipo de retorno
- `@throws` - Exceções que podem ser lançadas
- `@example` - Exemplo de uso
- `@see` - Referência para código relacionado
- `@link` - Link para documentação externa
- `@deprecated` - Marca como obsoleto
- `@todo` - Tarefas pendentes

### Exemplo Completo

```php
/**
 * Class Meilisearch_Client
 *
 * Wrapper for Meilisearch PHP SDK with WordPress integration.
 * Handles connection management, index operations, and settings.
 *
 * @since 0.1.0
 * @package Meilisearch
 */
class Meilisearch_Client {

    /**
     * Meilisearch client instance.
     *
     * @since 0.1.0
     * @var \Meilisearch\Client
     */
    private Client $client;

    /**
     * Constructor.
     *
     * Initializes the Meilisearch client with provided credentials.
     *
     * @since 0.1.0
     *
     * @param string $host       Meilisearch host URL.
     * @param string $master_key Master key for authentication.
     *
     * @throws \Exception If connection fails.
     */
    public function __construct( string $host, string $master_key ) {
        // ...
    }

    /**
     * Get index name for a blog.
     *
     * Generates the index name following the pattern: wp_{blog_id}_posts
     *
     * @since 0.1.0
     *
     * @param int $blog_id Blog ID.
     * @return string Index name.
     *
     * @example
     * $index = $client->get_index_name(2);
     * // Returns: "wp_2_posts"
     */
    public function get_index_name( int $blog_id ): string {
        return "wp_{$blog_id}_posts";
    }
}
```

## Mantendo a Documentação Atualizada

1. **Documente ao escrever** - Adicione PHPDoc ao criar novos métodos
2. **Revise regularmente** - Mantenha exemplos e descrições atualizados
3. **Seja descritivo** - Explique o "porquê", não apenas o "o quê"
4. **Use exemplos** - Código de exemplo ajuda muito
5. **Referencie relacionados** - Use @see e @link para conectar conceitos

## Validando Documentação

Para verificar se a documentação está completa:

```bash
# Com phpcs (coding standards)
vendor/bin/phpcs --standard=WordPress includes/ admin/ public/

# Gerar e revisar warnings do phpDocumentor
phpdoc 2>&1 | grep -i warning
```

## Contribuindo

Ao adicionar novos arquivos ou métodos:

1. Adicione blocos PHPDoc completos
2. Inclua exemplos quando relevante
3. Documente exceções e edge cases
4. Mantenha a linguagem clara e concisa
5. Use os mesmos padrões do código existente

## Recursos

- [phpDocumentor Documentation](https://docs.phpdoc.org/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- [PSR-5 PHPDoc Standard](https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc.md)
