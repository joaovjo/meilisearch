---
id: architecture
title: Arquitetura do Sistema
sidebar_label: Arquitetura
sidebar_position: 4
description: Arquitetura técnica do plugin incluindo componentes, fluxos de dados e padrões de design
keywords:
  - arquitetura
  - design patterns
  - componentes
  - fluxo de dados
tags:
  - Architecture
  - Technical
  - Advanced
---

# 🏗️ Arquitetura do Sistema

Este documento descreve a arquitetura técnica do plugin Meilisearch Network Search, incluindo sua estrutura de componentes, fluxos de dados e padrões de design.

## Visão Geral da Arquitetura

```mermaid
graph TB
    subgraph "WordPress Multisite Network"
        subgraph "Frontend"
            SearchForm[Formulário de Busca]
            Autocomplete[Autocomplete JS]
            Results[Resultados]
        end
        
        subgraph "Plugin Core"
            Client[Meilisearch Client]
            Indexer[Indexer + Fiber]
            Searcher[Searcher]
            AutocompleteAPI[Autocomplete API]
            SearchAPI[Search API]
            SearchOverride[Search Override]
        end
        
        subgraph "Admin Interface"
            Dashboard[Dashboard]
            Settings[Network Settings]
            Metrics[Metrics]
            IndexAnalyzer[Index Analyzer]
            MultiPattern[Multi-Pattern Search]
        end
        
        subgraph "CLI"
            WPCLI[WP-CLI Commands]
        end
        
        subgraph "WordPress Core"
            Hooks[WordPress Hooks]
            WPQuery[WP_Query]
            RestAPI[REST API]
        end
    end
    
    subgraph "Meilisearch Server"
        Index1[wp_1_posts]
        Index2[wp_2_posts]
        Index3[wp_3_posts]
        MeiliAPI[Meilisearch API]
    end
    
    SearchForm --> Autocomplete
    Autocomplete --> AutocompleteAPI
    SearchForm --> SearchOverride
    SearchOverride --> WPQuery
    SearchOverride --> Searcher
    
    Searcher --> Client
    AutocompleteAPI --> Client
    SearchAPI --> Client
    Indexer --> Client
    
    Client --> MeiliAPI
    
    MeiliAPI --> Index1
    MeiliAPI --> Index2
    MeiliAPI --> Index3
    
    Hooks --> Indexer
    WPCLI --> Indexer
    
    Dashboard --> Metrics
    Dashboard --> IndexAnalyzer
    Settings --> Client
    
    style Client fill:#4F46E5
    style MeiliAPI fill:#EC4899
    style Indexer fill:#10B981
    style Searcher fill:#F59E0B
```

## Componentes Principais

### 1. Meilisearch Client (`includes/class-client.php`)

Wrapper do SDK oficial do Meilisearch PHP. Gerencia conexões e autenticação.

```mermaid
classDiagram
    class Meilisearch_Client {
        -string $host
        -string $master_key
        -MeiliSearch\\Client $client
        +__construct(host, master_key)
        +get_client() Client
        +health() array
        +create_index(index_name) void
        +get_index(index_name) Index
        +multi_search(queries) array
    }
    
    class MeiliSearch_SDK {
        <<external>>
    }
    
    Meilisearch_Client --> MeiliSearch_SDK : usa
```

**Responsabilidades**:
- Estabelecer conexão com Meilisearch
- Validar credenciais (master key)
- Fornecer interface para outros componentes
- Tratar erros de conexão

### 2. Indexer (`includes/class-indexer.php`)

Gerencia a indexação de conteúdo usando PHP Fibers para operações concorrentes.

```mermaid
classDiagram
    class Meilisearch_Indexer {
        -Meilisearch_Client $client
        -array $settings
        +__construct(client)
        +init_hooks() void
        +index_post(post_id, blog_id) void
        +delete_post(post_id, blog_id) void
        +index_site(blog_id) void
        +index_network() void
        -prepare_document(post) array
        -get_index_name(blog_id) string
    }
    
    class PHP_Fiber {
        <<PHP 8.1>>
        +start() void
        +suspend() mixed
        +resume(value) void
    }
    
    Meilisearch_Indexer --> PHP_Fiber : usa
    Meilisearch_Indexer --> Meilisearch_Client : usa
```

**Estratégia Multi-Index**: Cada site da rede tem seu próprio índice Meilisearch.

**Padrão de Nomenclatura**:
```
wp_{blog_id}_posts

Exemplos:
- Site ID 1: wp_1_posts
- Site ID 2: wp_2_posts
- Site ID 42: wp_42_posts
```

### 3. Searcher (`includes/class-searcher.php`)

Executa buscas multi-índice e retorna resultados combinados.

```mermaid
classDiagram
    class Meilisearch_Searcher {
        -Meilisearch_Client $client
        +__construct(client)
        +search(query, options) array
        +multi_index_search(query, blog_ids) array
        +network_search(query) array
        -format_results(raw_results) array
        -merge_results(results_array) array
    }
    
    Meilisearch_Searcher --> Meilisearch_Client : usa
```

**Busca Multi-Índice**: Pesquisa em todos os índices da rede simultaneamente usando `multiSearch()` do Meilisearch.

### 4. Search Override (`public/class-search-override.php`)

Intercepta buscas do WordPress e substitui por Meilisearch.

```mermaid
sequenceDiagram
    participant User as Usuário
    participant Form as Formulário Busca
    participant WP as WordPress
    participant Override as Search Override
    participant Searcher as Searcher
    participant Meili as Meilisearch
    
    User->>Form: Digite termo + Enter
    Form->>WP: GET /?s=termo
    WP->>WP: pre_get_posts hook
    WP->>Override: Hook callback
    Override->>Override: is_search() && is_main_query()
    Override->>Searcher: search(termo)
    Searcher->>Meili: multiSearch([...])
    Meili-->>Searcher: Retorna IDs relevantes
    Searcher-->>Override: [post_ids]
    Override->>WP: Modifica WP_Query
    Note over Override,WP: $query->set('post__in', $post_ids)<br/>$query->set('orderby', 'post__in')
    WP->>WP: Executa query modificada
    WP-->>User: Exibe resultados
```

**Hook Principal**: `pre_get_posts` com prioridade 10.

### 5. Autocomplete (`includes/class-autocomplete.php`)

Fornece sugestões em tempo real via REST API.

```mermaid
sequenceDiagram
    participant User as Usuário
    participant JS as autocomplete.js
    participant API as REST API
    participant Auto as Autocomplete Class
    participant Meili as Meilisearch
    
    User->>JS: Digita "word"
    JS->>JS: Debounce 300ms
    JS->>API: GET /wp-json/meilisearch/v1/autocomplete?q=word
    API->>Auto: Route callback
    Auto->>Meili: search(word, limit=5)
    Meili-->>Auto: Top 5 resultados
    Auto->>Auto: Formata sugestões
    Auto-->>API: JSON response
    API-->>JS: [{title, url}, ...]
    JS->>User: Mostra dropdown
```

## Estratégia de Indexação

### Estrutura do Documento

Cada post indexado contém:

```json
{
  "id": "1_42",
  "blog_id": 1,
  "post_id": 42,
  "title": "Título do Post",
  "content": "Conteúdo completo...",
  "excerpt": "Resumo do post",
  "url": "https://site.com/post-slug",
  "author": "Nome do Autor",
  "author_id": 3,
  "post_type": "post",
  "post_status": "publish",
  "date": 1633024800,
  "modified": 1633024800,
  "categories": ["Categoria 1", "Categoria 2"],
  "tags": ["tag1", "tag2"],
  "site_name": "Nome do Site",
  "site_url": "https://site.com"
}
```

### Indexação Incremental

```mermaid
flowchart TD
    Event{Evento WordPress} --> SavePost[save_post]
    Event --> DeletePost[delete_post]
    Event --> NewBlog[wpmu_new_blog]
    Event --> DeleteSite[wp_delete_site]
    
    SavePost --> CheckStatus{Status<br/>= publish?}
    CheckStatus -->|Sim| IndexDoc[Indexar Documento]
    CheckStatus -->|Não| DeleteDoc[Remover do Índice]
    
    DeletePost --> DeleteDoc
    DeleteDoc --> MeiliDelete[Meilisearch deleteDocument]
    
    IndexDoc --> PrepareDoc[Preparar Documento]
    PrepareDoc --> MeiliAdd[Meilisearch addDocuments]
    
    NewBlog --> CreateIndex[Criar Índice wp_X_posts]
    CreateIndex --> SetSettings[Configurar Settings]
    
    DeleteSite --> GetIndexName[Obter nome do índice]
    GetIndexName --> DropIndex[Deletar Índice]
    
    style SavePost fill:#10B981
    style DeletePost fill:#EF4444
    style NewBlog fill:#3B82F6
    style DeleteSite fill:#F59E0B
```

### Indexação em Massa (Fiber)

Para indexar toda a rede, o plugin usa PHP Fibers para processar múltiplos sites concorrentemente:

```mermaid
sequenceDiagram
    participant CLI as WP-CLI
    participant Indexer as Indexer
    participant FM as Fiber Manager
    participant F1 as Fiber Site 1
    participant F2 as Fiber Site 2
    participant F3 as Fiber Site 3
    participant Meili as Meilisearch
    
    CLI->>Indexer: index_network()
    Indexer->>Indexer: get_sites()
    
    loop Para cada site
        Indexer->>FM: Criar Fiber
        FM->>F1: new Fiber(callback)
        FM->>F2: new Fiber(callback)
        FM->>F3: new Fiber(callback)
    end
    
    Indexer->>FM: run()
    
    par Execução Concorrente
        FM->>F1: start()
        F1->>F1: switch_to_blog(1)
        F1->>Meili: addDocuments([...])
        
        FM->>F2: start()
        F2->>F2: switch_to_blog(2)
        F2->>Meili: addDocuments([...])
        
        FM->>F3: start()
        F3->>F3: switch_to_blog(3)
        F3->>Meili: addDocuments([...])
    end
    
    Meili-->>F1: Task enqueued
    Meili-->>F2: Task enqueued
    Meili-->>F3: Task enqueued
    
    F1->>F1: restore_current_blog()
    F2->>F2: restore_current_blog()
    F3->>F3: restore_current_blog()
    
    FM-->>Indexer: Resultados agregados
    Indexer-->>CLI: Sucesso
```

## Fluxo de Busca Completo

```mermaid
flowchart TD
    Start([Usuário busca 'wordpress']) --> Frontend{Origem}
    
    Frontend -->|Formulário| FormSubmit[Submit Form]
    Frontend -->|Autocomplete| TypeInput[Digita no campo]
    
    FormSubmit --> PreGetPosts[Hook: pre_get_posts]
    PreGetPosts --> Override[Search Override]
    
    TypeInput --> Debounce[Debounce 300ms]
    Debounce --> RESTAPI[REST API Autocomplete]
    
    Override --> Searcher[Searcher Class]
    RESTAPI --> AutoClass[Autocomplete Class]
    AutoClass --> Searcher
    
    Searcher --> BuildQueries[Construir queries multi-index]
    BuildQueries --> MultiSearch[Meilisearch multiSearch]
    
    MultiSearch --> Index1[wp_1_posts]
    MultiSearch --> Index2[wp_2_posts]
    MultiSearch --> Index3[wp_3_posts]
    
    Index1 --> MergeResults[Merge & Sort Results]
    Index2 --> MergeResults
    Index3 --> MergeResults
    
    MergeResults --> FormatResults[Formatar para WordPress]
    
    FormatResults --> OverrideQuery[Modificar WP_Query]
    OverrideQuery --> RenderResults[Renderizar Template]
    RenderResults --> EndForm([Exibir Resultados])
    
    FormatResults --> JSONResponse[JSON Response]
    JSONResponse --> UpdateDropdown[Atualizar Dropdown]
    UpdateDropdown --> EndAuto([Mostrar Sugestões])
    
    style Start fill:#10B981
    style EndForm fill:#10B981
    style EndAuto fill:#10B981
    style MultiSearch fill:#EC4899
```

## Interface Administrativa

### Hierarquia de Menus

```mermaid
graph TD
    NetworkAdmin[Network Admin] --> SettingsMenu[Settings Menu]
    NetworkAdmin --> MeiliMenu[Meilisearch Menu]
    
    SettingsMenu --> MeiliSettings[Meilisearch Settings]
    
    MeiliMenu --> Dashboard[Dashboard]
    MeiliMenu --> Metrics[Metrics]
    MeiliMenu --> IndexAnalyzer[Index Analyzer]
    MeiliMenu --> MultiPattern[Multi-Pattern Search]
    
    Dashboard --> QuickActions[Quick Actions]
    Dashboard --> SystemStatus[System Status]
    Dashboard --> RecentActivity[Recent Activity]
    
    Metrics --> IndexStats[Index Statistics]
    Metrics --> RealTimeData[Real-Time Data]
    
    IndexAnalyzer --> DetectPatterns[Detect Patterns]
    IndexAnalyzer --> NetworkList[Network List]
    
    MultiPattern --> PatternSelector[Pattern Selector]
    MultiPattern --> SearchTest[Search Test]
    
    style NetworkAdmin fill:#4F46E5
    style MeiliMenu fill:#EC4899
    style Dashboard fill:#10B981
```

### Classes Admin

```mermaid
classDiagram
    class Meilisearch_Dashboard {
        +init_hooks() void
        +render_page() void
        -get_system_status() array
        -get_network_stats() array
    }
    
    class Meilisearch_Network_Settings {
        +init_hooks() void
        +register_settings() void
        +render_page() void
        +validate_settings(input) array
        -test_connection() bool
    }
    
    class Meilisearch_Metrics {
        +init_hooks() void
        +render_page() void
        +get_metrics_ajax() void
        -fetch_index_stats(blog_id) array
    }
    
    class Meilisearch_Index_Analyzer {
        +init_hooks() void
        +render_page() void
        +detect_patterns() array
        -analyze_index_names() array
    }
    
    class Meilisearch_Multi_Pattern_Search {
        +init_hooks() void
        +render_page() void
        +save_patterns() void
        +test_search_ajax() void
    }
    
    Meilisearch_Dashboard --> Meilisearch_Client
    Meilisearch_Metrics --> Meilisearch_Client
    Meilisearch_Index_Analyzer --> Meilisearch_Client
    Meilisearch_Multi_Pattern_Search --> Meilisearch_Client
```

## Padrões de Design Utilizados

### 1. Singleton Pattern

Não usado diretamente, mas componentes são instanciados uma vez no bootstrap.

### 2. Dependency Injection

```php
// Client injetado no Indexer
$client = new Meilisearch_Client($host, $key);
$indexer = new Meilisearch_Indexer($client);
```

### 3. Hook-based Architecture

```php
class Meilisearch_Indexer {
    public function init_hooks() {
        add_action('save_post', [$this, 'index_post'], 10, 2);
        add_action('delete_post', [$this, 'delete_post']);
        add_action('wpmu_new_blog', [$this, 'new_blog_created']);
    }
}
```

### 4. Strategy Pattern

Diferentes estratégias de busca:
- `network_search()` - Busca em toda a rede
- `site_search()` - Busca em site específico
- `multi_pattern_search()` - Busca em múltiplas redes

## Fluxo de Dados

```mermaid
flowchart LR
    subgraph "WordPress"
        WPPost[(Post Data)]
        WPHooks[Hooks]
    end
    
    subgraph "Plugin"
        Indexer[Indexer]
        Searcher[Searcher]
        Cache[Transient Cache]
    end
    
    subgraph "Meilisearch"
        Index[(Index)]
        Engine[Search Engine]
    end
    
    WPPost -->|Publish/Update| WPHooks
    WPHooks -->|save_post| Indexer
    Indexer -->|addDocuments| Index
    
    Index -->|search| Engine
    Engine -->|results| Searcher
    Searcher -->|store| Cache
    Cache -->|retrieve| Searcher
    Searcher -->|post_ids| WPPost
    
    style WPPost fill:#3B82F6
    style Index fill:#EC4899
    style Cache fill:#F59E0B
```

## Performance e Otimização

### Caching Strategy

```mermaid
graph TD
    Request[Requisição de Busca] --> CheckCache{Cache<br/>Válido?}
    CheckCache -->|Sim| ReturnCache[Retornar do Cache]
    CheckCache -->|Não| SearchMeili[Buscar no Meilisearch]
    SearchMeili --> SaveCache[Salvar no Cache TTL=300s]
    SaveCache --> ReturnResults[Retornar Resultados]
    
    style CheckCache fill:#F59E0B
    style ReturnCache fill:#10B981
```

**Transient Keys**:
- `meilisearch_search_{md5($query)}` - Resultados de busca (5 min)
- `meilisearch_stats_{blog_id}` - Estatísticas do índice (5 min)

### Batch Processing

Indexação em lotes de 100 documentos por vez:

```php
$batch_size = 100;
$posts = get_posts(['numberposts' => -1]);
$batches = array_chunk($posts, $batch_size);

foreach ($batches as $batch) {
    $documents = array_map([$this, 'prepare_document'], $batch);
    $index->addDocuments($documents);
}
```

### Async Operations

ReactPHP para operações HTTP assíncronas:

```php
use React\EventLoop\Loop;
use React\Http\Browser;

$loop = Loop::get();
$browser = new Browser($loop);

// Múltiplas requisições HTTP paralelas
$promises = [];
foreach ($indexes as $index_name) {
    $promises[] = $browser->get("http://meilisearch:7700/indexes/$index_name/stats");
}

// Aguardar todas as respostas
Promise\all($promises)->then(function($responses) {
    // Processar todas as respostas
});

$loop->run();
```

## Segurança

### Validação de Entrada

```php
// Sanitização de query de busca
$search_query = sanitize_text_field($_GET['s']);

// Validação de blog_id
$blog_id = absint($_POST['blog_id']);

// Validação de master key
$master_key = sanitize_text_field($input['master_key']);
```

### Capability Checks

```php
// Apenas super admins podem acessar configurações
if (!current_user_can('manage_network_options')) {
    wp_die(__('Access denied', 'meilisearch'));
}
```

### Nonce Verification

```php
// AJAX requests
check_ajax_referer('meilisearch_ajax', 'nonce');

// Form submissions
wp_verify_nonce($_POST['_wpnonce'], 'meilisearch_settings');
```

## Tratamento de Erros

```mermaid
flowchart TD
    Operation[Operação Meilisearch] --> Try{Try-Catch}
    Try -->|Sucesso| Success[Retornar Dados]
    Try -->|Erro| Catch[Catch Exception]
    
    Catch --> LogError[error_log]
    LogError --> CheckDebug{WP_DEBUG?}
    CheckDebug -->|Sim| ShowError[Mostrar Erro]
    CheckDebug -->|Não| SilentFail[Falha Silenciosa]
    ShowError --> Fallback[Fallback WP Search]
    SilentFail --> Fallback
    
    style Success fill:#10B981
    style Catch fill:#EF4444
    style Fallback fill:#F59E0B
```

### Graceful Degradation

Se Meilisearch falhar, o plugin reverte para busca padrão do WordPress:

```php
try {
    $results = $searcher->search($query);
} catch (Exception $e) {
    error_log('Meilisearch error: ' . $e->getMessage());
    // Deixa WordPress executar busca padrão
    return;
}
```

## Estrutura de Arquivos

```
meilisearch/
├── meilisearch.php              # Bootstrap principal
├── includes/                     # Core classes
│   ├── class-client.php
│   ├── class-indexer.php
│   ├── class-searcher.php
│   ├── class-autocomplete.php
│   ├── class-search-api.php
│   └── class-cli.php
├── admin/                        # Network admin UI
│   ├── class-dashboard.php
│   ├── class-network-settings.php
│   ├── class-metrics.php
│   ├── class-index-analyzer.php
│   └── class-multi-pattern-search.php
├── public/                       # Frontend
│   └── class-search-override.php
├── assets/                       # Static assets
│   ├── js/
│   │   └── autocomplete.js
│   └── css/
│       └── autocomplete.css
├── languages/                    # i18n
├── vendor/                       # Composer dependencies
└── tests/                        # PHPUnit tests
```

## Próximos Passos

- [Configuração Detalhada](../primeiros-passos/configuration.md)
- [Guia do Desenvolvedor](../usage/developer-guide.md)
- [Referência da API](api-reference.md)
