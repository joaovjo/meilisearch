---
id: indexing
title: Sistema de Indexação
sidebar_label: Indexação
sidebar_position: 3
description: Como funciona a indexação e sincronização de conteúdo
keywords:
  - indexação
  - indexing
  - sincronização
  - fiber
  - concorrência
tags:
  - Features
  - Indexing
  - Core
---

# 📊 Sistema de Indexação

Documentação detalhada do sistema de indexação e sincronização do plugin Meilisearch.

## Visão Geral

O sistema de indexação é responsável por manter o conteúdo do WordPress sincronizado com o Meilisearch. Ele usa **PHP Fibers** (PHP 8.1+) para operações concorrentes, permitindo indexar múltiplos sites simultaneamente.

## Estratégia Multi-Index

Cada site da rede WordPress possui seu próprio índice no Meilisearch:

| Site | Blog ID | Index Name |
|------|---------|------------|
| Site Principal | 1 | `wp_1_posts` |
| Blog Notícias | 2 | `wp_2_posts` |
| Loja | 3 | `wp_3_posts` |

### Benefícios

- ✅ **Isolamento de dados** por site
- ✅ **Fácil backup/restore** individual
- ✅ **Deletar site = deletar índice** automaticamente
- ✅ **Configurações específicas** por site

## Estrutura do Documento

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

## Indexação Automática

O plugin indexa automaticamente quando:

| Evento WordPress | Ação no Meilisearch |
|------------------|---------------------|
| Post publicado | Adiciona ao índice |
| Post atualizado | Atualiza no índice |
| Post despublicado | Remove do índice |
| Post deletado | Remove do índice |
| Novo site criado | Cria novo índice |
| Site deletado | Remove índice |

### Hooks WordPress

```php
// Indexar quando post é salvo
add_action('save_post', [$indexer, 'index_post'], 10, 2);

// Remover quando post é deletado
add_action('delete_post', [$indexer, 'delete_post']);

// Criar índice quando novo site é criado
add_action('wpmu_new_blog', [$indexer, 'new_blog_created']);

// Deletar índice quando site é removido
add_action('wp_delete_site', [$indexer, 'delete_blog_index']);
```

## Indexação em Massa

### Via WP-CLI (Recomendado)

```bash
# Indexar toda a rede
wp meilisearch index --network

# Ver progresso detalhado
wp meilisearch index --network --debug

# Indexar site específico
wp meilisearch index --blog_id=1
```

### Via Dashboard Admin

1. Acesse **Network Admin → Meilisearch → Dashboard**
2. Clique em **"Reindex All Sites"**
3. Aguarde conclusão

### Usando Fibers (Concorrência)

O plugin usa PHP Fibers para processar múltiplos sites simultaneamente:

```php
// Pseudocódigo simplificado
$sites = get_sites();
$fibers = [];

foreach ($sites as $site) {
    $fibers[] = new Fiber(function() use ($site) {
        switch_to_blog($site->blog_id);
        $posts = get_posts(['numberposts' => -1]);
        
        foreach (array_chunk($posts, 100) as $batch) {
            $documents = array_map('prepare_document', $batch);
            $index->addDocuments($documents);
        }
        
        restore_current_blog();
    });
}

// Executar todos os Fibers concorrentemente
foreach ($fibers as $fiber) {
    $fiber->start();
}
```

## Batch Processing

Posts são indexados em lotes para otimizar performance:

```php
$batch_size = 100; // Padrão
$posts = get_posts(['numberposts' => -1]);
$batches = array_chunk($posts, $batch_size);

foreach ($batches as $batch) {
    $documents = array_map('prepare_document', $batch);
    $index->addDocuments($documents);
}
```

## Configuração do Índice

Cada índice é configurado com:

### Searchable Attributes

```php
[
    'title',      // Maior peso
    'excerpt',
    'content',
    'categories',
    'tags',
    'author'      // Menor peso
]
```

### Filterable Attributes

```php
[
    'blog_id',
    'post_type',
    'post_status',
    'author_id',
    'categories',
    'tags',
    'date',
    'modified'
]
```

### Sortable Attributes

```php
[
    'date',
    'modified',
    'title'
]
```

## Customização

### Modificar Campos do Documento

```php
add_filter('meilisearch_document_fields', function($fields, $post) {
    // Adicionar campo customizado
    $fields['custom_field'] = get_post_meta($post->ID, 'custom_field', true);
    
    // Remover campo
    unset($fields['excerpt']);
    
    // Limitar tamanho do conteúdo
    $fields['content'] = mb_substr($fields['content'], 0, 10000);
    
    return $fields;
}, 10, 2);
```

### Customizar Settings do Índice

```php
add_filter('meilisearch_index_settings', function($settings, $blog_id) {
    // Adicionar campo como searchable
    $settings['searchableAttributes'][] = 'custom_field';
    
    // Adicionar campo como filterable
    $settings['filterableAttributes'][] = 'custom_taxonomy';
    
    return $settings;
}, 10, 2);
```

### Customizar Nome do Índice

```php
add_filter('meilisearch_index_name', function($index_name, $blog_id) {
    // Usar domínio em vez de blog_id
    $domain = get_blog_details($blog_id)->domain;
    $domain_slug = str_replace('.', '_', $domain);
    return "wp_{$domain_slug}_posts";
}, 10, 2);
```

## Monitoramento

### Ver Estatísticas

```bash
# Via WP-CLI
wp meilisearch stats

# Saída:
# Blog ID | Index Name   | Documents | Size
# --------|--------------|-----------|------
# 1       | wp_1_posts   | 150       | 2.3MB
# 2       | wp_2_posts   | 89        | 1.1MB
```

### Métricas em Tempo Real

Acesse **Network Admin → Meilisearch → Metrics** para ver:

- Quantidade de documentos por índice
- Tamanho de cada índice
- Status de indexação (em andamento ou concluído)
- Última atualização

## Performance

### Otimizações

1. **Batch size adequado**: 100 documentos por lote
2. **Usar Fibers**: Processar múltiplos sites concorrentemente
3. **Limitar campos**: Não indexar campos desnecessários
4. **Horário adequado**: Rodar indexação em horário de baixo tráfego

### Benchmarks

| Operação | Tempo Médio | Observações |
|----------|-------------|-------------|
| Indexar 1 post | menos de 50ms | Síncrono, via hook |
| Indexar 100 posts | ~2s | Batch processing |
| Indexar site (1000 posts) | ~20s | Com Fiber |
| Indexar rede (10 sites) | ~3min | Concorrente |

## Troubleshooting

### Posts não aparecem após indexação

```bash
# Verificar se foi indexado
wp meilisearch search "titulo-do-post" --debug

# Ver estatísticas do índice
wp meilisearch stats --blog_id=1

# Verificar logs
tail -f wp-content/debug.log | grep Meilisearch
```

### Indexação muito lenta

**Causas comuns**:
- Hardware insuficiente
- Batch size muito pequeno
- Documentos muito grandes
- Rede lenta entre WordPress e Meilisearch

**Soluções**:
- Aumentar recursos do servidor
- Ajustar batch size
- Limitar campos indexados
- Colocar Meilisearch próximo ao WordPress

### Erros de sincronização

```bash
# Reindexar site específico
wp meilisearch reindex --blog_id=1

# Reindexar tudo
wp meilisearch reindex --network
```

## Próximos Passos

- [Sistema de Busca](search.md) - Como funciona a busca
- [Autocomplete](autocomplete.md) - Sugestões em tempo real
- [API Reference](../api-reference.md) - Documentação técnica
