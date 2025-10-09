---
id: metrics
title: Métricas e Monitoramento
sidebar_label: Métricas
sidebar_position: 5
description: Sistema de estatísticas e monitoramento em tempo real
keywords:
  - métricas
  - estatísticas
  - monitoramento
  - dashboard
tags:
  - Features
  - Metrics
  - Monitoring
---

# 📈 Métricas e Monitoramento

Sistema completo de estatísticas e monitoramento em tempo real do plugin Meilisearch.

## Dashboard de Métricas

Acesse: **Network Admin → Meilisearch → Metrics**

O dashboard fornece uma visão abrangente do estado de todos os índices da rede.

## Métricas Disponíveis

### Por Índice

| Métrica | Descrição | Uso |
|---------|-----------|-----|
| **Documents** | Total de documentos indexados | Comparar com total de posts |
| **Size** | Tamanho do índice | Monitorar crescimento |
| **Is Indexing** | Se indexação está ativa | Detectar tarefas travadas |
| **Last Update** | Última modificação | Verificar sincronização |
| **Field Distribution** | Campos indexados por quantidade | Otimizar estrutura |

### Por Rede

| Métrica | Descrição |
|---------|-----------|
| **Total Sites** | Quantidade de sites na rede |
| **Total Indexes** | Quantidade total de índices |
| **Total Documents** | Soma de todos os documentos |
| **Total Size** | Tamanho total dos índices |
| **Connection Status** | Estado da conexão com Meilisearch |

## Auto-Refresh

O dashboard atualiza automaticamente a cada 30 segundos sem recarregar a página.

```javascript
setInterval(function() {
    fetchMetrics();
}, 30000);
```

## Via WP-CLI

### Ver Estatísticas Gerais

```bash
wp meilisearch stats

# Saída:
# Network Stats:
# - Total Sites: 3
# - Total Indexes: 3
# - Total Documents: 473
# - Total Size: 6.9MB
# - Status: Connected
```

### Ver Estatísticas por Site

```bash
wp meilisearch stats --blog_id=1

# Saída:
# Blog ID 1 Stats:
# - Index Name: wp_1_posts
# - Documents: 150
# - Size: 2.3MB
# - Is Indexing: false
# - Last Update: 2024-10-07 10:30:00
```

## REST API

### GET /wp-json/meilisearch/v1/stats

Retorna estatísticas de todos os índices.

**Parâmetros**:

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| `blog_id` | int | Não | ID do site específico |

**Exemplo**:

```bash
curl "https://seusite.com/wp-json/meilisearch/v1/stats"
```

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
        "excerpt": 145,
        "categories": 120
      }
    },
    "wp_2_posts": {
      "numberOfDocuments": 89,
      "isIndexing": false,
      "fieldDistribution": {
        "title": 89,
        "content": 89
      }
    }
  },
  "network": {
    "total_sites": 3,
    "total_indexes": 3,
    "total_documents": 473,
    "total_size": "6.9MB"
  }
}
```

## Alertas e Notificações

### Configurar Alertas

```php
// No tema ou plugin personalizado
add_action('meilisearch_metrics_check', function() {
    $stats = meilisearch_get_stats();
    
    foreach ($stats as $index_name => $stat) {
        // Alerta se índice muito grande
        if ($stat['numberOfDocuments'] > 10000) {
            wp_mail(
                'admin@example.com',
                'Meilisearch: Índice grande detectado',
                "O índice {$index_name} tem {$stat['numberOfDocuments']} documentos."
            );
        }
        
        // Alerta se desatualizado (>1 hora)
        $last_update = strtotime($stat['lastUpdate']);
        if (time() - $last_update > 3600) {
            // Enviar alerta
        }
    }
});

// Agendar verificação (a cada hora)
if (!wp_next_scheduled('meilisearch_metrics_check')) {
    wp_schedule_event(time(), 'hourly', 'meilisearch_metrics_check');
}
```

## Monitoramento de Performance

### Tempo de Resposta

```bash
# Via WP-CLI com timing
time wp meilisearch search "termo" --blog_id=1

# Saída:
# Found 15 results
# real    0m0.234s
```

### Query Log

Habilitar logging de queries:

```php
add_action('meilisearch_after_search', function($query, $results, $time) {
    error_log(sprintf(
        'Meilisearch: Query "%s" returned %d results in %.3fs',
        $query,
        $results['total'],
        $time
    ));
}, 10, 3);
```

## Exportar Métricas

### CSV

```php
// Gerar CSV das métricas
function export_meilisearch_metrics() {
    $stats = meilisearch_get_stats();
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="meilisearch-metrics.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Index', 'Documents', 'Size', 'Is Indexing']);
    
    foreach ($stats as $index_name => $stat) {
        fputcsv($output, [
            $index_name,
            $stat['numberOfDocuments'],
            $stat['size'],
            $stat['isIndexing'] ? 'Yes' : 'No'
        ]);
    }
    
    fclose($output);
    exit;
}
```

### JSON

```bash
# Via REST API
curl "https://seusite.com/wp-json/meilisearch/v1/stats" > metrics.json
```

## Integração com Ferramentas

### Grafana

```yaml
# prometheus.yml
scrape_configs:
  - job_name: 'meilisearch'
    static_configs:
      - targets: ['localhost:7700']
    metrics_path: '/metrics'
```

### New Relic

```php
if (extension_loaded('newrelic')) {
    newrelic_custom_metric('Custom/Meilisearch/Documents', $total_documents);
    newrelic_custom_metric('Custom/Meilisearch/IndexSize', $total_size);
}
```

## Troubleshooting

### Métricas não atualizam

**Verificações**:

1. Cache de transients:
   ```bash
   wp transient delete-all
   ```

2. Conexão com Meilisearch:
   ```bash
   wp meilisearch health
   ```

3. Permissões de usuário:
   - Verificar se tem `manage_network_options`

### Discrepância entre métricas e realidade

```bash
# Comparar documentos indexados vs posts no banco
wp post list --post_status=publish --format=count
wp meilisearch stats --blog_id=1

# Se diferente, reindexar
wp meilisearch reindex --blog_id=1
```

## Próximos Passos

- [Sistema de Busca](search.md)
- [Indexação](indexing.md)
- [Guia do Admin](../usage/admin-guide.md)
