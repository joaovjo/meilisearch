# Configuração de Tipos de Posts para Indexação

## Visão Geral

A funcionalidade de seleção de tipos de posts permite que administradores da rede escolham quais tipos de conteúdo serão indexados no Meilisearch.

## Interface de Configuração

### Localização
**Rede Admin > Meilisearch > Settings**

### Aparência da Interface

```
┌─────────────────────────────────────────────────────────────┐
│ Meilisearch Network Settings                                │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ Enable Meilisearch                                          │
│ ☑ Enable Meilisearch search across the network.            │
│                                                              │
│ Meilisearch Host                                            │
│ [http://localhost:7700                                    ] │
│ URL of your Meilisearch server (e.g., http://localhost:7700)│
│                                                              │
│ Master Key                                                  │
│ [●●●●●●●●●●●●●●                                          ] │
│ Your Meilisearch master key (leave empty if not using      │
│ authentication).                                            │
│                                                              │
│ Index Name Format                                           │
│ [{prefix}posts                                            ] │
│ Index naming format. Use {prefix} for table prefix...       │
│                                                              │
│ Post Types to Index                                         │
│ ☑ Posts (post)                                             │
│ ☑ Páginas (page)                                           │
│ ☐ Mídia (attachment)                                       │
│ ☑ Produtos (product)                                       │
│ ☐ Eventos (event)                                          │
│                                                              │
│ Select which post types should be indexed in Meilisearch.   │
│ Only published content will be indexed.                     │
│                                                              │
│ [Save Settings]                                              │
└─────────────────────────────────────────────────────────────┘
```

## Funcionalidades

### 1. Detecção Automática de Tipos de Posts

O sistema detecta automaticamente todos os tipos de posts públicos registrados:

```php
get_post_types(['public' => true], 'objects')
```

Isso inclui:
- **Tipos Nativos**: post, page, attachment
- **Custom Post Types**: Qualquer CPT registrado com `'public' => true`

### 2. Seleção Múltipla

- Use checkboxes para selecionar múltiplos tipos
- Pelo menos um tipo deve estar selecionado
- Valores padrão: `post` e `page`

### 3. Exibição Amigável

Cada checkbox mostra:
- **Label amigável**: Nome do post type (ex: "Posts", "Páginas", "Produtos")
- **Nome técnico**: Slug do post type entre parênteses (ex: "(post)", "(page)", "(product)")

```html
☑ Posts (post)
☑ Páginas (page)
☑ Produtos (product)
```

## Comportamento Técnico

### Salvamento das Configurações

```php
// Dados salvos no site option
$settings = [
    'host' => 'http://localhost:7700',
    'master_key' => 'xxx',
    'enabled' => true,
    'index_format' => '{prefix}posts',
    'post_types' => ['post', 'page', 'product']  // <-- Nova opção
];

update_site_option('meilisearch_settings', $settings);
```

### Validação e Sanitização

```php
// Sanitização aplicada
$post_types = isset($settings['post_types']) && is_array($settings['post_types']) 
    ? array_map('sanitize_key', $settings['post_types']) 
    : ['post', 'page'];

// Garante que não fica vazio
if (empty($post_types)) {
    $post_types = ['post', 'page'];
}
```

### Filtragem na Indexação

#### Indexação Individual (hook save_post)

```php
public function index_post(int $post_id, WP_Post $post): void
{
    // ... verificações existentes ...
    
    // Nova verificação
    if (!$this->should_index_post_type($post->post_type)) {
        return; // Não indexa se o tipo não está selecionado
    }
    
    // ... continua a indexação ...
}
```

#### Indexação em Lote (WP-CLI)

```php
public function index_site_posts(int $blog_id): array
{
    // Obtém tipos configurados
    $post_types = $this->get_indexable_post_types();
    
    // Query usa apenas os tipos selecionados
    $args = [
        'post_type' => $post_types,  // Em vez de 'any'
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'ID',
        'order' => 'ASC',
    ];
    
    $posts = get_posts($args);
    // ... continua a indexação ...
}
```

## Exemplos de Uso

### Exemplo 1: Blog Simples
**Cenário**: Site com apenas posts e páginas

**Configuração**:
- ☑ Posts (post)
- ☑ Páginas (page)

**Resultado**: Todos os posts e páginas publicados serão indexados.

---

### Exemplo 2: E-commerce com WooCommerce
**Cenário**: Loja online que deseja indexar produtos mas não posts do blog

**Configuração**:
- ☐ Posts (post)
- ☑ Páginas (page)
- ☑ Produtos (product)

**Resultado**: Apenas páginas e produtos serão indexados. Posts do blog não aparecerão nas buscas.

---

### Exemplo 3: Portal de Notícias com Múltiplos CPTs
**Cenário**: Site com notícias, artigos, vídeos e podcasts

**Configuração**:
- ☑ Posts (post)
- ☑ Páginas (page)
- ☑ Notícias (news)
- ☑ Artigos (article)
- ☑ Vídeos (video)
- ☐ Podcasts (podcast)

**Resultado**: Todos os tipos selecionados serão indexados, exceto podcasts.

---

### Exemplo 4: Site de Documentação
**Cenário**: Site focado em documentação técnica

**Configuração**:
- ☐ Posts (post)
- ☑ Páginas (page)
- ☑ Documentação (docs)
- ☑ Tutoriais (tutorial)

**Resultado**: Apenas páginas, documentação e tutoriais serão indexados.

## Reindexação

### Quando Reindexar?

É necessário reindexar após alterar os tipos de posts selecionados para:
1. **Adicionar conteúdo**: Incluir posts de tipos recém-selecionados
2. **Remover conteúdo**: Excluir posts de tipos desmarcados

### Como Reindexar

#### Via WP-CLI (Recomendado)

```bash
# Reindexar toda a rede
wp meilisearch index --network

# Reindexar um site específico
wp meilisearch index --blog-id=2

# Reindexar com progresso
wp meilisearch index --network --verbose
```

#### Via Código

```php
// Obter instâncias
$settings = get_site_option('meilisearch_settings', []);
$client = new Meilisearch_Client($settings['host'], $settings['master_key']);
$indexer = new Meilisearch_Indexer($client);

// Reindexar toda a rede
$results = $indexer->bulk_index_network();

// Reindexar um site específico
$results = $indexer->index_site_posts(1);
```

## Perguntas Frequentes

### P: O que acontece se eu desmarcar um tipo de post que já foi indexado?

**R**: Os documentos existentes não são removidos automaticamente. Você precisa reindexar para que os documentos sejam removidos do índice.

---

### P: Posso indexar tipos de posts não públicos?

**R**: Não, apenas tipos de posts com `'public' => true` são exibidos na interface. Isso é por design, pois tipos não públicos geralmente não devem aparecer nos resultados de busca.

---

### P: O que acontece se eu não selecionar nenhum tipo?

**R**: O sistema usa automaticamente 'post' e 'page' como fallback para evitar que a indexação pare de funcionar.

---

### P: Custom Post Types são suportados?

**R**: Sim! Qualquer Custom Post Type registrado com `'public' => true` aparecerá automaticamente na lista de opções.

---

### P: A configuração é por site ou por rede?

**R**: A configuração é por rede (network-wide). Todos os sites da rede usam a mesma configuração de tipos de posts.

---

### P: Como adicionar um novo tipo de post personalizado à lista?

**R**: Basta registrar o Custom Post Type com `'public' => true`:

```php
register_post_type('meu_tipo', [
    'public' => true,
    'label' => 'Meu Tipo',
    // ... outras configurações
]);
```

Ele aparecerá automaticamente nas configurações do Meilisearch.

## Integração com Código

### Verificar Tipos Indexados

```php
$settings = get_site_option('meilisearch_settings', []);
$indexed_types = $settings['post_types'] ?? ['post', 'page'];

if (in_array('product', $indexed_types)) {
    echo 'Produtos estão sendo indexados';
}
```

### Filtrar Resultados por Tipo

```php
// Na busca, você pode filtrar por post_type
$searcher = new Meilisearch_Searcher($client);
$results = $searcher->search([
    'q' => 'termo de busca',
    'filter' => 'post_type = "product"'
]);
```

## Logs e Debugging

### Ativar Logs de Debug

```php
// No wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Mensagens de Log

O sistema registra quando um post não é indexado por causa do tipo:

```
Meilisearch: Post ID 123 not indexed - post type 'event' not in configured types
```

## Compatibilidade

### WordPress
- ✅ WordPress 6.0+
- ✅ Multisite
- ✅ Custom Post Types

### PHP
- ✅ PHP 8.1+
- ✅ Type hints e return types

### Meilisearch
- ✅ Meilisearch v0.27+
- ✅ Qualquer versão do SDK PHP

## Segurança

### Permissões
- Apenas usuários com `manage_network_options` podem alterar as configurações
- Verificação de nonce implementada

### Sanitização
- Todos os valores são sanitizados com `sanitize_key()`
- Array vazio resulta em fallback seguro

### Validação
- Apenas tipos de posts públicos registrados são aceitos
- Valores inválidos são ignorados
