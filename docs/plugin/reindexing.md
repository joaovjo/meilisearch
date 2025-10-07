---
id: reindexing
title: Reindexação de Conteúdo
sidebar_label: Reindexação
sidebar_position: 6
description: Guia completo sobre quando e como reindexar o conteúdo
keywords:
  - reindexação
  - reindex
  - sincronização
  - atualização
tags:
  - Maintenance
  - Operations
---

# Reindexação de Conteúdo

## Por que reindexar?

Quando você modifica as configurações de atributos sortáveis (sortable attributes) ou filtráveis (filterable attributes) no Meilisearch, os **documentos existentes** nos índices não são automaticamente atualizados. Apenas novos documentos adicionados após a mudança de configuração terão o comportamento correto.

Por isso, é necessário **reindexar** todo o conteúdo existente sempre que você:

- Habilitar ou desabilitar atributos sortáveis na página de Configurações de Pesquisa
- Habilitar ou desabilitar atributos filtráveis
- Mudar a ordem de classificação padrão
- Após a instalação inicial do plugin (se houver conteúdo existente)

## Como Reindexar

### Método 1: Atualização em Massa via WordPress Admin

A forma mais simples de reindexar o conteúdo é forçar uma atualização em massa dos posts/páginas:

1. Acesse **Posts** ou **Páginas** no admin do WordPress
2. Selecione todos os itens (use o checkbox no topo da tabela)
3. No menu suspenso **Ações em Massa**, selecione **Editar**
4. Clique em **Aplicar**
5. **NÃO modifique nenhum campo**, apenas clique em **Atualizar**

Isso vai disparar o hook `wp_insert_post` para cada item, fazendo com que sejam reindexados automaticamente.

### Método 2: Script PHP para Reindexação Completa

Crie um arquivo temporário na raiz do WordPress (`reindex-all.php`) com o seguinte conteúdo:

```php
<?php
/**
 * Script de reindexação completa do Meilisearch
 */

require_once __DIR__ . '/wp-load.php';

if (!is_multisite()) {
    die("Este script requer WordPress Multisite\n");
}

$blogs = get_sites();
$total_posts = 0;

foreach ($blogs as $blog) {
    switch_to_blog($blog->blog_id);
    
    echo "=== Blog {$blog->blog_id}: " . get_bloginfo('name') . " ===\n";
    
    // Obter tipos de post configurados
    $settings = get_site_option('meilisearch_settings', []);
    $post_types = $settings['post_types'] ?? ['post', 'page'];
    
    // Buscar todos os posts publicados
    $args = [
        'post_type' => $post_types,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ];
    
    $post_ids = get_posts($args);
    $count = count($post_ids);
    $total_posts += $count;
    
    echo "Encontrados {$count} posts para reindexar\n";
    
    // Forçar reindexação atualizando post_modified
    foreach ($post_ids as $i => $post_id) {
        wp_update_post([
            'ID' => $post_id,
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1),
        ]);
        
        if (($i + 1) % 50 == 0) {
            echo "  Processados " . ($i + 1) . "/{$count} posts...\n";
        }
    }
    
    echo "✓ Blog {$blog->blog_id} concluído\n\n";
    
    restore_current_blog();
}

echo "\n✓ Reindexação completa!\n";
echo "Total de posts reindexados: {$total_posts}\n";
```

Execute o script:

```bash
php reindex-all.php
```

**IMPORTANTE:** Delete o arquivo `reindex-all.php` após a execução para evitar problemas de segurança.

### Método 3: Via WP-CLI (se disponível)

Se você tiver WP-CLI instalado, pode usar:

```bash
# Reindexar posts
wp post list --post_type=post --post_status=publish --format=ids | xargs -I {} wp post update {} --post_modified="$(date '+%Y-%m-%d %H:%M:%S')" --allow-root

# Reindexar páginas
wp post list --post_type=page --post_status=publish --format=ids | xargs -I {} wp post update {} --post_modified="$(date '+%Y-%m-%d %H:%M:%S')" --allow-root
```

### Método 4: Gatilho Automático (Recomendado para Produção)

O plugin já está configurado para atualizar as configurações dos índices automaticamente quando:

1. Um novo post é criado ou atualizado
2. O método `ensure_index_exists()` é chamado

Portanto, **novos conteúdos** adicionados após configurar os atributos sortáveis/filtráveis já serão indexados corretamente.

## Verificando se a Reindexação foi Bem-Sucedida

Após reindexar, teste uma busca pela API REST:

```bash
curl -s 'http://seu-site.com/wp-json/meilisearch/v1/search?q=teste' | grep '"post_date"' | head -5
```

As datas devem aparecer em **ordem decrescente** (mais recente primeiro).

## Quando NÃO é Necessário Reindexar

- Quando você apenas altera a query de busca no frontend
- Quando modifica o layout/tema do site
- Quando adiciona/remove plugins não relacionados ao Meilisearch
- Quando publica NOVO conteúdo (novos posts já são indexados com as configurações corretas)

## Solução de Problemas

### Os resultados ainda não estão ordenados corretamente

1. Verifique se os atributos sortáveis estão configurados:
   ```bash
   curl 'http://localhost:7700/indexes/secom_1_posts/settings' -H 'Authorization: Bearer SUA_MASTER_KEY'
   ```

2. Certifique-se de que a classe `Meilisearch_Search_Settings` está sendo carregada no contexto frontend (verifique o arquivo `meilisearch.php`)

3. Verifique se há erros no `wp-content/debug.log` (ative `WP_DEBUG` e `WP_DEBUG_LOG` em `wp-config.php`)

### A reindexação está muito lenta

- Processe em lotes menores (modifique `posts_per_page` no script)
- Execute durante horários de baixo tráfego
- Use WP-CLI ao invés de interface web para melhor performance

### Erro "Authorization header is missing"

O Meilisearch está configurado com autenticação. Certifique-se de que a `master_key` está corretamente configurada em **Rede > Meilisearch > Configurações**.
