# Comandos WP-CLI do Meilisearch

Este documento descreve todos os comandos WP-CLI disponíveis para gerenciar o plugin Meilisearch Network Search.

## Instalação e Configuração

Antes de usar os comandos, certifique-se de que:
1. O plugin está ativado na rede
2. As configurações do Meilisearch estão corretas (host e master key)
3. O Meilisearch está habilitado nas configurações da rede

## Comandos Disponíveis

### `wp meilisearch health`

Verifica a saúde e disponibilidade do servidor Meilisearch.

**Exemplos:**
```bash
wp meilisearch health
```

**Output:**
```
Checking Meilisearch server health...
Success: Meilisearch server is healthy and available!
Version: 1.22.2
```

---

### `wp meilisearch list_indexes`

Lista todos os índices Meilisearch criados para os blogs da rede.

**Opções:**
- `--format=<format>` - Formato de saída: table (padrão), json, csv, yaml, count

**Exemplos:**
```bash
# Listar como tabela (padrão)
wp meilisearch list_indexes

# Listar como JSON
wp meilisearch list_indexes --format=json

# Contar total de índices
wp meilisearch list_indexes --format=count
```

**Output (table):**
```
+------------+---------+----------------------------------------+-----------+----------+
| index      | blog_id | blog_name                              | documents | indexing |
+------------+---------+----------------------------------------+-----------+----------+
| wp_1_posts | 1       | Secretaria de Comunicação              | 8         | No       |
| wp_2_posts | 2       | Laboratório de Comunicação Inteligente | 11        | No       |
| wp_3_posts | 3       | Concurso Jornalístico e Publicitário   | 5         | No       |
+------------+---------+----------------------------------------+-----------+----------+
```

---

### `wp meilisearch reindex`

Reindexa posts de um blog específico ou de todos os blogs na rede.

**Opções:**
- `--blog_id=<blog_id>` - ID do blog para reindexar (opcional)
- `--url=<url>` - URL do blog para reindexar (alternativa ao blog_id)

**Exemplos:**
```bash
# Reindexar blog específico por ID
wp meilisearch reindex --blog_id=2

# Reindexar blog específico por URL
wp meilisearch reindex --url=http://example.com/labcom/

# Reindexar TODOS os blogs da rede
wp meilisearch reindex
```

**Output (blog único):**
```
Reindexing blog 2...
Success: Reindexed 11 of 11 posts for blog 2
```

**Output (todos os blogs):**
```
Found 3 sites to reindex...
Reindexing sites  100% [=======================] 0:01 / 0:01
Success: Reindexed 24 of 24 posts across 3 sites
```

**Casos de uso:**
- Após atualizar configurações do índice
- Quando há inconsistências nos resultados de busca
- Após migração ou restauração de backup
- Para aplicar novas configurações de atributos filtráveis

---

### `wp meilisearch search`

Busca posts através do Meilisearch.

**Argumentos:**
- `<query>` - Termo de busca (obrigatório)

**Opções:**
- `--limit=<limit>` - Número máximo de resultados (padrão: 20)
- `--blog_id=<blog_id>` - Buscar apenas em blog específico
- `--format=<format>` - Formato de saída: table (padrão), json, csv, yaml

**Exemplos:**
```bash
# Busca simples em todos os blogs
wp meilisearch search "mundo"

# Busca em blog específico com limite
wp meilisearch search "inteligente" --blog_id=2 --limit=10

# Busca com output JSON
wp meilisearch search "mundo" --format=json
```

**Output (table):**
```
+---------+---------+-------------+-----------+-------------+----------------------------------------------------+
| blog_id | post_id | title       | post_type | post_status | permalink                                          |
+---------+---------+-------------+-----------+-------------+----------------------------------------------------+
| 1       | 1       | Olá, mundo! | post      | publish     | http://example.com/blog/ola-mundo/                 |
| 2       | 1       | Olá, mundo! | post      | publish     | http://example.com/labcom/2025/09/22/ola-mundo/    |
+---------+---------+-------------+-----------+-------------+----------------------------------------------------+
Success: Found 2 results for "mundo"
```

**Output (JSON):**
```json
[
  {
    "blog_id": 1,
    "post_id": 1,
    "title": "Olá, mundo!",
    "post_type": "post",
    "post_status": "publish",
    "permalink": "http://example.com/blog/ola-mundo/"
  },
  {
    "blog_id": 2,
    "post_id": 1,
    "title": "Olá, mundo!",
    "post_type": "post",
    "post_status": "publish",
    "permalink": "http://example.com/labcom/2025/09/22/ola-mundo/"
  }
]
```

---

### `wp meilisearch stats`

Exibe estatísticas sobre os índices e documentos indexados.

**Opções:**
- `--blog_id=<blog_id>` - Estatísticas de blog específico (opcional)

**Exemplos:**
```bash
# Estatísticas globais
wp meilisearch stats

# Estatísticas de blog específico
wp meilisearch stats --blog_id=2
```

**Output (global):**
```
Global Meilisearch Statistics:
  Database Size: 920 KB
  Total Indexes: 3

  Indexes:
    wp_1_posts: 8 documents
    wp_2_posts: 11 documents
    wp_3_posts: 5 documents
Success: Stats retrieved successfully.
```

**Output (blog específico):**
```
Statistics for blog 2 (wp_2_posts):
  Documents: 11
  Indexing: No

  Field Distribution:
    author: 11
    author_id: 11
    blog_id: 11
    categories: 11
    content: 11
    date: 11
    excerpt: 11
    id: 11
    modified: 11
    permalink: 11
    post_status: 11
    post_type: 11
    tags: 11
    title: 11
Success: Stats retrieved successfully.
```

---

### `wp meilisearch create-index`

Cria manualmente um índice para um blog específico.

**Argumentos:**
- `<blog_id>` - ID do blog (obrigatório)

**Exemplos:**
```bash
# Criar índice para blog 2
wp meilisearch create_index 2
```

**Output:**
```
Creating index for blog 2...
Success: Index created for blog 2: wp_2_posts
```

**Nota:** Normalmente os índices são criados automaticamente quando um blog é adicionado à rede. Use este comando apenas se precisar recriar um índice manualmente.

---

### `wp meilisearch delete-index`

Deleta o índice de um blog específico.

**Argumentos:**
- `<blog_id>` - ID do blog (obrigatório)

**Opções:**
- `--yes` - Pular confirmação

**Exemplos:**
```bash
# Deletar índice com confirmação
wp meilisearch delete_index 2

# Deletar índice sem confirmação
wp meilisearch delete_index 2 --yes
```

**Output:**
```
Are you sure you want to delete index 'wp_2_posts'? [y/n] y
Deleting index wp_2_posts...
Success: Index deleted: wp_2_posts
```

**Atenção:** Este comando remove PERMANENTEMENTE todos os dados indexados do blog. Use com cuidado!

---

## Fluxos de Trabalho Comuns

### Configuração Inicial
```bash
# 1. Verificar saúde do servidor
wp meilisearch health

# 2. Reindexar todos os blogs
wp meilisearch reindex

# 3. Verificar índices criados
wp meilisearch list_indexes

# 4. Testar busca
wp meilisearch search "teste"
```

### Manutenção Regular
```bash
# Verificar estatísticas
wp meilisearch stats

# Reindexar blogs com problemas
wp meilisearch reindex --blog_id=2
```

### Troubleshooting
```bash
# 1. Verificar saúde do servidor
wp meilisearch health

# 2. Ver estatísticas detalhadas
wp meilisearch stats --blog_id=2

# 3. Testar busca específica
wp meilisearch search "termo" --blog_id=2 --format=json

# 4. Reindexar se necessário
wp meilisearch reindex --blog_id=2
```

### Adicionar Novo Blog à Rede
```bash
# 1. Criar índice para o novo blog
wp meilisearch create_index 4

# 2. Indexar posts do novo blog
wp meilisearch reindex --blog_id=4

# 3. Verificar indexação
wp meilisearch stats --blog_id=4
```

### Remover Blog da Rede
```bash
# Deletar índice do blog removido
wp meilisearch delete_index 3 --yes
```

---

## Automação com Cron

Você pode automatizar a reindexação usando cron jobs:

```bash
# Reindexar todos os blogs todas as noites às 2h
0 2 * * * /usr/local/bin/wp meilisearch reindex --path=/var/www/html --allow-root

# Verificar saúde a cada hora
0 * * * * /usr/local/bin/wp meilisearch health --path=/var/www/html --allow-root
```

---

## Integração com Scripts

### Bash Script para Monitoramento
```bash
#!/bin/bash

# Verificar saúde e enviar alerta se falhar
if ! wp meilisearch health --path=/var/www/html --allow-root > /dev/null 2>&1; then
    echo "Meilisearch está offline!" | mail -s "Alerta Meilisearch" admin@example.com
fi

# Verificar total de documentos
TOTAL=$(wp meilisearch list_indexes --format=json --path=/var/www/html --allow-root | jq -r 'map(.documents | tonumber) | add')
echo "Total de documentos indexados: $TOTAL"
```

### Python Script para Análise
```python
import subprocess
import json

# Obter estatísticas em JSON
result = subprocess.run(
    ['wp', 'meilisearch', 'search', 'termo', '--format=json', '--allow-root'],
    capture_output=True,
    text=True
)

data = json.loads(result.stdout)
print(f"Encontrados {len(data)} resultados")

for hit in data:
    print(f"Blog {hit['blog_id']}: {hit['title']}")
```

---

## Notas Importantes

1. **Permissões:** Use `--allow-root` quando executar como root em containers Docker
2. **Path:** Em ambientes onde WP não está no diretório atual, use `--path=/var/www/html`
3. **Performance:** Reindexar todos os blogs pode demorar em redes grandes. Use `--blog_id` para reindexar individualmente
4. **Network Only:** Todos os comandos requerem WordPress Multisite
5. **Configuração:** O plugin deve estar configurado e habilitado antes de usar os comandos

---

## Referências

- [WP-CLI Documentation](https://developer.wordpress.org/cli/commands/)
- [Meilisearch Documentation](https://docs.meilisearch.com/)
- [Plugin Settings](../README.md)
