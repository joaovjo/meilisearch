---
id: api-reference
title: Referência da API
sidebar_label: API Reference
sidebar_position: 5
description: Documentação completa das APIs REST, WP-CLI e PHP
keywords:
  - api
  - rest api
  - wp-cli
  - php api
  - hooks
  - filters
tags:
  - API
  - Reference
  - Development
---

# 📖 Referência da API

Documentação completa das APIs REST, WP-CLI e PHP do plugin Meilisearch.

## REST API

### Autocomplete

```http
GET /wp-json/meilisearch/v1/autocomplete
```

Retorna sugestões de busca em tempo real.

**Parâmetros**:

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| `q` | string | Sim | Termo de busca |
| `limit` | int | Não | Máximo de resultados (padrão: 5) |
| `blog_id` | int | Não | ID do site específico |

**Exemplo**:

```bash
curl "https://site.com/wp-json/meilisearch/v1/autocomplete?q=wordpress&limit=3"
```

**Resposta**:

```json
{
  "success": true,
  "results": [
    {
      "id": "1_42",
      "title": "Guia WordPress 2024",
      "excerpt": "Aprenda WordPress...",
      "url": "https://site.com/guia-wordpress",
      "blog_id": 1,
      "post_id": 42,
      "post_type": "post",
      "date": "2024-09-15"
    }
  ],
  "total": 150,
  "query": "wordpress",
  "time": 0.023
}
```

### Search

```http
GET /wp-json/meilisearch/v1/search
```

Executa busca completa.

**Parâmetros**:

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| `q` | string | Sim | Termo de busca |
| `limit` | int | Não | Resultados por página (padrão: 10) |
| `offset` | int | Não | Offset para paginação (padrão: 0) |
| `blog_ids` | array | Não | IDs de sites para buscar |
| `post_types` | array | Não | Tipos de post para filtrar |
| `sort` | string | Não | Campo para ordenar (ex: `date:desc`) |

**Exemplo**:

```bash
curl "https://site.com/wp-json/meilisearch/v1/search?q=wordpress&limit=20&sort=date:desc"
```

**Resposta**:

```json
{
  "success": true,
  "results": [...],
  "total": 150,
  "limit": 20,
  "offset": 0,
  "query": "wordpress",
  "time": 0.045,
  "facets": {
    "post_type": {
      "post": 120,
      "page": 30
    }
  }
}
```

### Stats

```http
GET /wp-json/meilisearch/v1/stats
```

Retorna estatísticas dos índices.

**Parâmetros**:

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| `blog_id` | int | Não | ID do site específico |

**Resposta**:

```json
{
  "success": true,
  "stats": {
    "wp_1_posts": {
      "numberOfDocuments": 150,
      "isIndexing": false,
      "fieldDistribution": {
        "title": 150,
        "content": 150,
        "excerpt": 145
      }
    }
  }
}
```

## WP-CLI Commands

### Index

Indexar conteúdo.

```bash
wp meilisearch index [--network] [--url=<url>] [--blog_id=<id>]
```

**Flags**:

- `--network`: Indexa todos os sites da rede
- `--url=<url>`: Indexa site específico por URL
- `--blog_id=<id>`: Indexa site específico por ID

**Exemplos**:

```bash
# Indexar toda a rede
wp meilisearch index --network

# Indexar site específico
wp meilisearch index --blog_id=1

# Indexar por URL
wp meilisearch index --url=blog.example.com
```

### Reindex

Reindexar conteúdo (deleta e recria índice).

```bash
wp meilisearch reindex [--network] [--blog_id=<id>]
```

**Exemplo**:

```bash
# Reindexar rede completa
wp meilisearch reindex --network
```

### Search

Buscar via linha de comando.

```bash
wp meilisearch search <query> [--blog_id=<id>] [--limit=<n>]
```

**Exemplo**:

```bash
# Buscar "wordpress" no site 1
wp meilisearch search "wordpress" --blog_id=1 --limit=5
```

### Health

Verificar saúde do servidor Meilisearch.

```bash
wp meilisearch health
```

**Saída**:

```
✓ Meilisearch is healthy
  Host: http://localhost:7700
  Status: available
```

### List Indexes

Listar todos os índices.

```bash
wp meilisearch list_indexes
```

**Saída**:

```
┌──────────────┬───────────┬────────┐
│ Index Name   │ Documents │ Size   │
├──────────────┼───────────┼────────┤
│ wp_1_posts   │ 150       │ 2.3MB  │
│ wp_2_posts   │ 89        │ 1.1MB  │
│ wp_3_posts   │ 234       │ 3.5MB  │
└──────────────┴───────────┴────────┘
```

### Stats

Ver estatísticas.

```bash
wp meilisearch stats [--blog_id=<id>]
```

**Exemplo**:

```bash
# Stats da rede
wp meilisearch stats

# Stats de site específico
wp meilisearch stats --blog_id=1
```

### Create Index

Criar índice manualmente.

```bash
wp meilisearch create_index <blog_id>
```

### Delete Index

Deletar índice.

```bash
wp meilisearch delete_index <blog_id> [--yes]
```

⚠️ Requer `--yes` para confirmar.

## PHP API

### Meilisearch_Client

Classe wrapper do cliente Meilisearch.

#### Instanciar

```php
$client = new Meilisearch_Client(
    'http://localhost:7700',
    'master-key'
);
```

#### Métodos

##### get_client()

```php
$meili_client = $client->get_client();
// Retorna: MeiliSearch\Client
```

##### health()

```php
$health = $client->health();
// Retorna: array ['status' => 'available']
```

##### get_index()

```php
$index = $client->get_index('wp_1_posts');
// Retorna: MeiliSearch\Endpoints\Indexes
```

##### create_index()

```php
$client->create_index('wp_1_posts', [
    'primaryKey' => 'id'
]);
```

##### multi_search()

```php
$results = $client->multi_search([
    ['indexUid' => 'wp_1_posts', 'q' => 'wordpress'],
    ['indexUid' => 'wp_2_posts', 'q' => 'wordpress']
]);
// Retorna: array de resultados
```

### Meilisearch_Indexer

Gerencia indexação de conteúdo.

#### Instanciar

```php
$indexer = new Meilisearch_Indexer($client);
$indexer->init_hooks();
```

#### Métodos

##### index_post()

```php
$indexer->index_post($post_id, $blog_id);
```

Indexa um post específico.

##### delete_post()

```php
$indexer->delete_post($post_id, $blog_id);
```

Remove post do índice.

##### index_site()

```php
$indexer->index_site($blog_id);
```

Indexa todos os posts de um site.

##### index_network()

```php
$indexer->index_network();
```

Indexa toda a rede (usa Fibers).

### Meilisearch_Searcher

Executa buscas.

#### Instanciar

```php
$searcher = new Meilisearch_Searcher($client);
```

#### Métodos

##### search()

```php
$results = $searcher->search('wordpress', [
    'limit' => 20,
    'offset' => 0,
    'sort' => ['date:desc']
]);
```

**Retorno**:

```php
[
    'hits' => [...],
    'total' => 150,
    'query' => 'wordpress',
    'processingTimeMs' => 45
]
```

##### network_search()

```php
$results = $searcher->network_search('wordpress', [
    'blog_ids' => [1, 2, 3],
    'limit' => 20
]);
```

Busca em múltiplos sites.

## Hooks do WordPress

### Actions

#### meilisearch_after_index_post

Disparado após indexar um post.

```php
add_action('meilisearch_after_index_post', function($post_id, $blog_id, $result) {
    error_log("Post $post_id indexed in blog $blog_id");
}, 10, 3);
```

#### meilisearch_after_delete_post

Disparado após remover post do índice.

```php
add_action('meilisearch_after_delete_post', function($post_id, $blog_id) {
    error_log("Post $post_id removed from blog $blog_id");
}, 10, 2);
```

#### meilisearch_after_index_site

Disparado após indexar um site completo.

```php
add_action('meilisearch_after_index_site', function($blog_id, $count) {
    error_log("Indexed $count posts in blog $blog_id");
}, 10, 2);
```

### Filters

#### meilisearch_default_settings

Filtrar configurações padrão.

```php
add_filter('meilisearch_default_settings', function($defaults) {
    $defaults['host'] = 'http://meilisearch:7700';
    $defaults['index_prefix'] = 'prod';
    return $defaults;
});
```

#### meilisearch_index_name

Customizar nome do índice.

```php
add_filter('meilisearch_index_name', function($index_name, $blog_id) {
    // Usar domínio em vez de blog_id
    $domain = get_blog_details($blog_id)->domain;
    return "wp_{$domain}_posts";
}, 10, 2);
```

#### meilisearch_index_settings

Modificar configurações do índice.

```php
add_filter('meilisearch_index_settings', function($settings, $blog_id) {
    // Adicionar campo customizado
    $settings['searchableAttributes'][] = 'custom_field';
    $settings['filterableAttributes'][] = 'custom_taxonomy';
    return $settings;
}, 10, 2);
```

#### meilisearch_document_fields

Customizar campos do documento indexado.

```php
add_filter('meilisearch_document_fields', function($fields, $post) {
    // Adicionar campo customizado
    $fields['custom_field'] = get_post_meta($post->ID, 'custom_field', true);
    
    // Remover campo
    unset($fields['excerpt']);
    
    // Limitar tamanho
    $fields['content'] = mb_substr($fields['content'], 0, 10000);
    
    return $fields;
}, 10, 2);
```

#### meilisearch_search_params

Modificar parâmetros de busca.

```php
add_filter('meilisearch_search_params', function($params, $query) {
    // Adicionar filtro
    $params['filter'] = 'post_type = "post"';
    
    // Adicionar facets
    $params['facets'] = ['categories', 'tags'];
    
    // Highlight
    $params['attributesToHighlight'] = ['title', 'excerpt'];
    
    // Sort
    $params['sort'] = ['date:desc'];
    
    return $params;
}, 10, 2);
```

#### meilisearch_search_results

Modificar resultados antes de retornar.

```php
add_filter('meilisearch_search_results', function($results, $query) {
    // Adicionar dados adicionais
    foreach ($results['hits'] as &$hit) {
        $hit['custom_data'] = get_custom_data($hit['post_id']);
    }
    return $results;
}, 10, 2);
```

#### meilisearch_cache_ttl

Modificar TTL do cache.

```php
add_filter('meilisearch_cache_ttl', function($ttl) {
    return 600; // 10 minutos
});
```

## Exemplos de Integração

### Busca Customizada

```php
// functions.php do tema
add_action('pre_get_posts', function($query) {
    if (!is_admin() && $query->is_search() && $query->is_main_query()) {
        // Buscar apenas em posts
        $query->set('post_type', 'post');
        
        // Adicionar filtro por categoria
        if (isset($_GET['category'])) {
            $query->set('category_name', $_GET['category']);
        }
    }
});
```

### Widget de Busca Personalizado

```php
class Meilisearch_Search_Widget extends WP_Widget {
    public function widget($args, $instance) {
        echo $args['before_widget'];
        ?>
        <form role="search" method="get" action="<?php echo home_url('/'); ?>">
            <input type="search" 
                   name="s" 
                   placeholder="Buscar..." 
                   autocomplete="off"
                   data-meilisearch-autocomplete>
            <button type="submit">🔍</button>
        </form>
        <?php
        echo $args['after_widget'];
    }
}
```

### Shortcode de Busca

```php
add_shortcode('meilisearch', function($atts) {
    $atts = shortcode_atts([
        'query' => '',
        'limit' => 5,
        'post_type' => 'post'
    ], $atts);
    
    if (empty($atts['query'])) {
        return '';
    }
    
    $client = new Meilisearch_Client(/* ... */);
    $searcher = new Meilisearch_Searcher($client);
    $results = $searcher->search($atts['query'], [
        'limit' => $atts['limit'],
        'filter' => "post_type = {$atts['post_type']}"
    ]);
    
    ob_start();
    foreach ($results['hits'] as $hit) {
        echo '<div class="search-result">';
        echo '<h3>' . esc_html($hit['title']) . '</h3>';
        echo '<p>' . esc_html($hit['excerpt']) . '</p>';
        echo '</div>';
    }
    return ob_get_clean();
});

// Uso: [meilisearch query="wordpress" limit="10"]
```

## Rate Limits

| Endpoint | Rate Limit | Observações |
|----------|------------|-------------|
| `/autocomplete` | 60 req/min por IP | Cache no navegador recomendado |
| `/search` | 30 req/min por IP | Use cache de transients |
| `/stats` | 10 req/min por usuário | Dados atualizados a cada 5 min |

## Versionamento

A API segue [Semantic Versioning](https://semver.org/):

- **MAJOR**: Mudanças incompatíveis
- **MINOR**: Novas funcionalidades compatíveis
- **PATCH**: Correções de bugs

Versão atual: **1.0.0**

## Changelog

### v1.0.0 (2024-10-06)

- ✨ Lançamento inicial
- ✨ REST API para autocomplete e busca
- ✨ WP-CLI commands completos
- ✨ Hooks e filters para customização
- ✨ Multi-index search
- ✨ Fiber-based indexing

---

**Veja também**: [Guia do Desenvolvedor](usage/developer-guide.md) para mais exemplos de uso.
