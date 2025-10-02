# Diagnóstico e Resolução - Meilisearch WordPress Plugin

**Data**: 2 de outubro de 2025  
**Versão do Plugin**: 0.1.0  
**Status Final**: ✅ **RESOLVIDO E FUNCIONANDO**

## Resumo Executivo

O plugin Meilisearch apresentou **três problemas críticos** que foram identificados e resolvidos:

1. **Configuração incorreta da URL** (faltava porta :7700)
2. **Erro de primaryKey** (múltiplos campos terminando em 'id')
3. **Chamada incorreta do multiSearch()** (arrays ao invés de objetos SearchQuery)

Os dois primeiros problemas impediam a indexação. O terceiro problema causava erros fatais nas buscas do frontend. Todos foram corrigidos e o sistema está agora totalmente funcional com **24 documentos indexados** em 3 sites da rede e **busca funcionando** em todos os sites.

---

## 🔍 Análise dos Problemas

### Problema 1: Configuração da URL

**Sintoma nos logs**:
```
[02-Oct-2025 18:08:18 UTC] Meilisearch connection error: cURL error 7: Failed to connect to localhost port 7700
[02-Oct-2025 18:08:42 UTC] Meilisearch connection error: 404 Not Found
```

**Diagnóstico**:
- Configuração inicial do WordPress: `http://10.28.13.21` (sem porta)
- Configuração correta do Meilisearch: `http://10.28.13.21:7700`
- Requests tentavam conectar na porta 80 (HTTP padrão) e encontravam nginx

**Solução Aplicada**:
```bash
wp site option update meilisearch_settings \
  '{"host":"http://10.28.13.21:7700","master_key":"5jbV22TkJ06JWHYjihuc2PkLBYffP3h9","enabled":true}' \
  --format=json
```

**Resultado**: ✅ Conexão estabelecida com sucesso

---

### Problema 2: Primary Key Inference

**Sintoma nas tasks**:
```json
{
  "status": "failed",
  "error": {
    "message": "The primary key inference failed as the engine found 3 fields ending with `id` in their names: 'author_id' and 'blog_id'. Please specify the primary key manually using the `primaryKey` query parameter.",
    "code": "index_primary_key_multiple_candidates_found"
  }
}
```

**Diagnóstico**:
- Documentos WordPress contêm 3 campos terminando em 'id':
  - `id` (chave primária correta)
  - `author_id` (ID do autor)
  - `blog_id` (ID do site)
- Meilisearch não conseguiu inferir automaticamente qual usar

**Código Original** (`class-indexer.php`):
```php
$client->index($index_name)->addDocuments($documents);
```

**Código Corrigido**:
```php
$client->index($index_name)->addDocuments($documents, 'id');
```

**Locais corrigidos**:
- `index_post()` - Linha 65
- `index_site_posts()` - Linhas 177 e 192 (batch e remaining)

**Resultado**: ✅ Documentos indexados com sucesso

---

### Problema 3: Chamada Incorreta do multiSearch()

**Sintoma nos logs**:
```
[02-Oct-2025 18:21:13 UTC] PHP Fatal error: Uncaught Error: Call to a member function toArray() on array
in /var/www/html/wp-content/plugins/meilisearch/vendor/meilisearch/meilisearch-php/src/Endpoints/Delegates/HandlesMultiSearch.php:23
Stack trace:
#0 /var/www/html/wp-content/plugins/meilisearch/includes/class-searcher.php(60): Meilisearch\Client->multiSearch(Array)
#1 /var/www/html/wp-content/plugins/meilisearch/public/class-search-override.php(66): Meilisearch_Searcher->search_network('exemplo', Array)
```

**Diagnóstico**:
- O método `multiSearch()` do SDK Meilisearch espera um **array de objetos `SearchQuery`**
- O código estava passando um **array de arrays simples**
- Na linha 23 de `HandlesMultiSearch.php`, o SDK tenta chamar `$query->toArray()`, mas recebe um array ao invés de um objeto
- Isso causava erro fatal toda vez que um usuário fazia uma busca no frontend

**Código Original** (`class-searcher.php`):
```php
// Prepare multi-search queries.
foreach ( $indexes as $index ) {
    $queries[] = [
        'indexUid' => $index,
        'q'        => $query,
        'limit'    => $args['limit'],
        'offset'   => $args['offset'],
    ];
}

try {
    $results = $this->client->get_client()->multiSearch( [ 'queries' => $queries ] );
    return $this->format_results( $results );
```

**Código Corrigido**:
```php
use Meilisearch\Contracts\SearchQuery;

// Prepare multi-search queries.
foreach ( $indexes as $index ) {
    $search_query = ( new SearchQuery() )
        ->setIndexUid( $index )
        ->setQuery( $query )
        ->setLimit( $args['limit'] )
        ->setOffset( $args['offset'] );
    
    $queries[] = $search_query;
}

try {
    $results = $this->client->get_client()->multiSearch( $queries );
    return $this->format_results( $results );
```

**Mudanças Aplicadas**:
1. Importado `use Meilisearch\Contracts\SearchQuery;` no topo do arquivo
2. Modificado loop para criar objetos `SearchQuery` ao invés de arrays
3. Removido wrapper `[ 'queries' => $queries ]` e passando apenas `$queries`

**Resultado**: ✅ Busca no frontend funcionando sem erros

---

## 📊 Estado Final da Indexação

### Estatísticas do Meilisearch

```json
{
  "databaseSize": 774144,
  "usedDatabaseSize": 540672,
  "lastUpdate": "2025-10-02T18:20:16.392526517Z",
  "indexes": {
    "wp_1_posts": {
      "numberOfDocuments": 8,
      "avgDocumentSize": 1016,
      "isIndexing": false
    },
    "wp_2_posts": {
      "numberOfDocuments": 11,
      "avgDocumentSize": 1853,
      "isIndexing": false
    },
    "wp_3_posts": {
      "numberOfDocuments": 5,
      "avgDocumentSize": 1630,
      "isIndexing": false
    }
  }
}
```

### Status por Site

| Site ID | Índice      | Posts no WP | Documentos Indexados | Status         |
|---------|-------------|-------------|----------------------|----------------|
| 1       | wp_1_posts  | 1           | 8                    | ✅ Sincronizado |
| 2       | wp_2_posts  | 1           | 11                   | ✅ Sincronizado |
| 3       | wp_3_posts  | 0           | 5                    | ✅ Sincronizado |

**Nota**: A contagem "Posts" considera apenas posts com `post_type='post'`. Os números maiores incluem pages, custom post types (banners), etc.

---

## 🧪 Testes de Validação

### 1. Verificação de Conexão
```bash
wp meilisearch status --network
# Output: Success: Connected to Meilisearch server.
```

### 2. Busca de Teste
**Query**: "wordpress"

**Resultado**:
```json
{
  "hits": [
    {
      "id": 1,
      "title": "Olá, mundo!",
      "content": "Boas-vindas ao WordPress. Esse é o seu primeiro post...",
      "blog_id": 1,
      "permalink": "http://10.28.13.21:31103/blog/ola-mundo/"
    }
  ],
  "estimatedTotalHits": 1,
  "processingTimeMs": 1
}
```

**Resultado**: ✅ Busca funcionando corretamente (1ms de resposta)

### 3. Busca no Frontend

**URLs Testadas**:
- http://10.28.13.21:31103/concursojornalisticoepublicitario/?s=exemplo
- http://10.28.13.21:31103/labcom/?s=inteligente

**Resultado para "inteligente"**:
```html
<h2>Resultados da pesquisa para "inteligente"</h2>
<article id="post_101">
  <h4><a href="http://10.28.13.21:31103/labcom/dica-inteligente/">Dica Inteligente</a></h4>
</article>
<article id="post_10">
  <h4><a href="http://10.28.13.21:31103/labcom/politica-municipal-de-comunicacao-inteligente/">
    Política Municipal de Comunicação Inteligente
  </a></h4>
</article>
```

**Resultado**: ✅ Busca no frontend retornando resultados do Meilisearch (2 posts encontrados)

### 4. Tasks do Meilisearch
Últimas 3 tasks (todas bem-sucedidas):

```json
[
  {
    "uid": 8,
    "index_uid": "wp_3_posts",
    "status": "succeeded",
    "type": "documentAdditionOrUpdate",
    "details": {
      "receivedDocuments": 5,
      "indexedDocuments": 5
    }
  },
  {
    "uid": 7,
    "index_uid": "wp_2_posts",
    "status": "succeeded",
    "type": "documentAdditionOrUpdate",
    "details": {
      "receivedDocuments": 11,
      "indexedDocuments": 11
    }
  },
  {
    "uid": 6,
    "index_uid": "wp_1_posts",
    "status": "succeeded",
    "type": "documentAdditionOrUpdate",
    "details": {
      "receivedDocuments": 8,
      "indexedDocuments": 8
    }
  }
]
```

---

## 📋 Campos Indexados

Cada documento contém os seguintes campos:

| Campo        | Tipo     | Searchable | Filterable | Sortable | Exemplo                              |
|--------------|----------|------------|------------|----------|--------------------------------------|
| id           | integer  | ❌         | ✅         | ❌       | 1                                    |
| blog_id      | integer  | ❌         | ✅         | ❌       | 1                                    |
| title        | string   | ✅         | ❌         | ❌       | "Olá, mundo!"                        |
| content      | string   | ✅         | ❌         | ❌       | "Boas-vindas ao WordPress..."        |
| excerpt      | string   | ✅         | ❌         | ❌       | "Boas-vindas ao WordPress..."        |
| post_type    | string   | ❌         | ✅         | ❌       | "post"                               |
| post_status  | string   | ❌         | ✅         | ❌       | "publish"                            |
| date         | timestamp| ❌         | ❌         | ✅       | 1758302040                           |
| modified     | timestamp| ❌         | ❌         | ✅       | 1758302040                           |
| author       | string   | ✅         | ❌         | ❌       | "baltazzar"                          |
| author_id    | string   | ❌         | ✅         | ❌       | "1"                                  |
| categories   | array    | ✅         | ✅         | ❌       | ["Sem categoria"]                    |
| tags         | array    | ✅         | ✅         | ❌       | []                                   |
| permalink    | string   | ❌         | ❌         | ❌       | "http://10.28.13.21:31103/blog/..."  |

---

## 🔧 Arquivos Modificados

### 1. `/var/www/html/wp-content/plugins/meilisearch/includes/class-indexer.php`

**Linha 65** - Método `index_post()`:
```php
- $this->client->get_client()->index($index_name)->addDocuments([$document]);
+ $this->client->get_client()->index($index_name)->addDocuments([$document], 'id');
```

**Linha 177** - Método `index_site_posts()` (batch):
```php
- $this->client->get_client()->index($index_name)->addDocuments($documents);
+ $this->client->get_client()->index($index_name)->addDocuments($documents, 'id');
```

**Linha 192** - Método `index_site_posts()` (remaining):
```php
- $this->client->get_client()->index($index_name)->addDocuments($documents);
+ $this->client->get_client()->index($index_name)->addDocuments($documents, 'id');
```

### 2. `/var/www/html/wp-content/plugins/meilisearch/includes/class-searcher.php`

**Topo do arquivo** - Adicionar import:
```php
<?php
/**
 * Meilisearch Searcher
 *
 * @package Meilisearch
 */

+ use Meilisearch\Contracts\SearchQuery;

/**
 * Class Meilisearch_Searcher
```

**Linhas 52-58** - Método `search_network()`:
```php
// Prepare multi-search queries.
foreach ( $indexes as $index ) {
-   $queries[] = [
-       'indexUid' => $index,
-       'q'        => $query,
-       'limit'    => $args['limit'],
-       'offset'   => $args['offset'],
-   ];
+   $search_query = ( new SearchQuery() )
+       ->setIndexUid( $index )
+       ->setQuery( $query )
+       ->setLimit( $args['limit'] )
+       ->setOffset( $args['offset'] );
+   
+   $queries[] = $search_query;
}
```

**Linha 60** - Método `search_network()`:
```php
try {
-   $results = $this->client->get_client()->multiSearch( [ 'queries' => $queries ] );
+   $results = $this->client->get_client()->multiSearch( $queries );
    return $this->format_results( $results );
```

---

## 🎯 Conclusão

### Status Atual
✅ **TOTALMENTE FUNCIONAL**

- 3 índices criados com sucesso
- 24 documentos indexados (8 + 11 + 5)
- Busca retornando resultados em 1ms
- Autocomplete pronto para uso
- Todas as tasks bem-sucedidas

### Próximos Passos Recomendados

1. **Testar busca no frontend**:
   - Acessar qualquer site da rede
   - Usar a caixa de busca
   - Verificar resultados e autocomplete

2. **Monitorar logs**:
   ```bash
   tail -f /var/www/html/wp-content/debug.log
   ```

3. **Configurar indexação automática**:
   - O plugin já tem hooks configurados
   - Novos posts serão indexados automaticamente
   - Use `wp meilisearch reindex --network` se necessário

4. **Performance**:
   - Considerar aumentar `batch_size` de 100 para 500 em produção
   - Monitorar uso de memória do Meilisearch
   - Avaliar uso de cache para queries frequentes

### Lições Aprendidas

1. **Sempre especificar primaryKey** quando há campos ambíguos terminando em 'id'
2. **Verificar URL completa** incluindo porta em ambientes Docker/containers
3. **Usar objetos SDK corretos** - o método `multiSearch()` espera objetos `SearchQuery`, não arrays
4. **Consultar documentação do SDK** - verificar tipos esperados pelos métodos
5. **Usar MCP tools** para diagnóstico rápido de Meilisearch
6. **Testar busca no frontend** após implementar para validar integração completa

---

## 📞 Suporte

Para problemas futuros:

1. Verificar conexão: `wp meilisearch status --network`
2. Ver logs: `/var/www/html/wp-content/debug.log`
3. Verificar tasks: API `/tasks` ou MCP tool `get-tasks`
4. Reindexar: `wp meilisearch reindex --network`

---

**Documento gerado em**: 2025-10-02 18:21 UTC  
**Versão**: 1.0
