---
id: search-settings
title: Configurações de Busca
sidebar_label: Configurações
sidebar_position: 2
description: Configuração de atributos sortáveis e filtráveis
keywords:
  - settings
  - sortable
  - filterable
  - configuração
tags:
  - Features
  - Configuration
  - Search
---

# Search Settings

## Overview

The Search Settings page allows you to configure which attributes can be used for filtering and sorting search results across your entire WordPress multisite network. This is essential for creating powerful, flexible search experiences.

## Accessing Search Settings

1. Go to **Network Admin** → **Meilisearch** → **Search**
2. You need `manage_network_options` capability to access this page

## Configuration Options

### Sortable Attributes

Sortable attributes determine which fields can be used to order search results. Common use cases:

- **date** - Sort by publication date (newest first for blogs/news)
- **modified** - Sort by last modification date
- **title** - Alphabetical sorting
- **author** - Sort by author name
- **id** - Sort by post ID

**Example Configuration:**
- ✅ date
- ✅ modified  
- ✅ title
- ✅ author

### Filterable Attributes

Filterable attributes allow users to narrow down search results by specific criteria:

- **post_type** - Filter by content type (post, page, custom post types)
- **blog_id** - Filter by specific site in network
- **author_id** - Filter by author
- **categories** - Filter by category
- **tags** - Filter by tags
- **post_status** - Filter by publish status

**Example Configuration:**
- ✅ post_type
- ✅ blog_id
- ✅ author_id
- ✅ categories
- ✅ tags

### Default Sort Order

Configure the default sorting behavior for search results:

1. **Sort By** - Select which attribute to sort by (default: date)
2. **Sort Direction**:
   - **Ascending** - A-Z, 0-9, oldest first
   - **Descending** - Z-A, 9-0, newest first (recommended for blogs)

**Recommended for blogs/news sites:**
- Sort By: `date`
- Direction: `Descending` (shows newest content first)

## How It Works

### 1. Configure Settings

Select which attributes should be sortable and filterable using the checkboxes.

### 2. Reindex Content

⚠️ **IMPORTANTE:** Após alterar as configurações de atributos sortáveis ou filtráveis, você **DEVE reindexar** todo o conteúdo existente.

**Por quê?** Os documentos que já foram indexados antes da mudança de configuração não terão o comportamento correto de ordenação/filtragem. Apenas novos documentos adicionados após a mudança funcionarão corretamente.

**Como reindexar:**

Existem várias formas de reindexar o conteúdo existente. Consulte a documentação completa em [Reindexação de Conteúdo](../topicos-avancados/reindexing.md) para instruções detalhadas.

**Forma rápida (via Admin):**

1. Vá em **Posts** ou **Páginas** 
2. Selecione todos os itens (checkbox no topo)
3. Em **Ações em Massa**, escolha **Editar** → **Aplicar**
4. **Não modifique nada**, apenas clique em **Atualizar**

Isso força a reindexação de todos os items selecionados.

### 3. Use in Searches

#### REST API

Search with sorting via REST API:

```bash
# Sort by date descending (newest first)
curl "http://yoursite.com/wp-json/meilisearch/v1/search?q=wordpress&sort=date:desc"

# Sort by title ascending (A-Z)
curl "http://yoursite.com/wp-json/meilisearch/v1/search?q=wordpress&sort=title:asc"

# Default sorting (uses configured default)
curl "http://yoursite.com/wp-json/meilisearch/v1/search?q=wordpress"
```

#### PHP Code

```php
$client = new Meilisearch_Client($host, $master_key);
$searcher = new Meilisearch_Searcher($client);

// Search with custom sorting
$results = $searcher->search_network('wordpress', [
    'limit' => 20,
    'sort' => 'date:desc'
]);

// Search with default sorting
$results = $searcher->search_network('wordpress', [
    'limit' => 20
]);
```

## Available Attributes

Based on the document structure in Meilisearch:

| Attribute | Type | Description | Good for Sorting | Good for Filtering |
|-----------|------|-------------|------------------|-------------------|
| id | Integer | Post ID | ✅ | ✅ |
| blog_id | Integer | Site ID in network | ✅ | ✅ |
| title | String | Post title | ✅ | ❌ |
| content | String | Post content | ❌ | ❌ |
| excerpt | String | Post excerpt | ❌ | ❌ |
| post_type | String | Content type | ❌ | ✅ |
| post_status | String | Publish status | ❌ | ✅ |
| date | Integer | Publication timestamp | ✅ | ✅ |
| modified | Integer | Modification timestamp | ✅ | ✅ |
| author | String | Author name | ✅ | ❌ |
| author_id | Integer | Author ID | ✅ | ✅ |
| categories | Array | Category names | ❌ | ✅ |
| tags | Array | Tag names | ❌ | ✅ |
| permalink | String | Post URL | ❌ | ❌ |

## Best Practices

### For Blogs and News Sites

**Sortable:**
- date ✅
- modified ✅
- title ✅

**Filterable:**
- post_type ✅
- categories ✅
- tags ✅
- author_id ✅

**Default Sort:** date:desc (newest first)

### For Documentation Sites

**Sortable:**
- title ✅
- modified ✅

**Filterable:**
- post_type ✅
- categories ✅

**Default Sort:** title:asc (alphabetical)

### For E-commerce/Directory Sites

**Sortable:**
- title ✅
- date ✅
- modified ✅

**Filterable:**
- post_type ✅
- categories ✅
- tags ✅
- blog_id ✅

**Default Sort:** date:desc

## Performance Considerations

1. **Index Size**: More sortable/filterable attributes increase index size slightly
2. **Reindexing**: Changes require reindexing all content
3. **Query Speed**: Meilisearch handles sorting and filtering very efficiently

## Troubleshooting

### Sort Parameter Not Working

**Problem:** Sorting doesn't seem to affect results

**Solutions:**
1. Verify attribute is selected in Search Settings
2. Reindex content: `wp meilisearch index --network`
3. Check attribute exists in documents
4. Verify sort format: `attribute:direction` (e.g., `date:desc`)

### Filter Not Working

**Problem:** Filtering doesn't narrow results

**Solutions:**
1. Ensure attribute is selected in Filterable Attributes
2. Reindex content
3. Check filter syntax in Meilisearch documentation

### Settings Not Applying

**Problem:** Changed settings but behavior unchanged

**Solutions:**
1. **Critical:** Run reindex command after changing settings
2. Check WP debug log for errors
3. Verify Meilisearch connection is working

## Examples

### Show Newest Posts First

```php
// REST API
GET /wp-json/meilisearch/v1/search?q=news&sort=date:desc

// PHP
$results = $searcher->search_network('news', [
    'sort' => 'date:desc'
]);
```

### Show Alphabetical by Title

```php
// REST API
GET /wp-json/meilisearch/v1/search?q=documentation&sort=title:asc

// PHP
$results = $searcher->search_network('documentation', [
    'sort' => 'title:asc'
]);
```

### Recently Updated First

```php
// REST API
GET /wp-json/meilisearch/v1/search?q=updates&sort=modified:desc

// PHP
$results = $searcher->search_network('updates', [
    'sort' => 'modified:desc'
]);
```

## Related Documentation

- [Installation Guide](../installation.md)
- [Configuration Guide](../configuration.md)
- [API Reference](../api-reference.md)
- [Troubleshooting](../troubleshooting.md)
