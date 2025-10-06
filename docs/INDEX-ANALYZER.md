# Index Analyzer

## Visão Geral

O **Index Analyzer** é uma ferramenta administrativa que analisa todos os índices existentes no servidor Meilisearch para identificar diferentes redes WordPress e seus padrões de nomenclatura de índices.

## Acesso

**Network Admin → Meilisearch → Index Analyzer**

## Funcionalidades

### 1. Análise Automática de Padrões

O analisador detecta automaticamente padrões de nomenclatura comuns:

- **Formato com Prefixo**: `{prefix}posts` → `wp_posts`, `wp_2_posts`, `wp_3_posts`
- **Formato com Blog ID**: `site_{blog_id}_posts` → `site_1_posts`, `site_2_posts`
- **Formatos Customizados**: Qualquer padrão configurável

### 2. Descoberta de URLs de Rede

Para cada padrão detectado, o sistema tenta identificar:

- URL base da rede WordPress
- Domínio principal
- Protocolo (HTTP/HTTPS)

### 3. Estatísticas por Padrão

Para cada padrão de nomenclatura, você pode visualizar:

- **Formato do Padrão**: Template usado (ex: `{prefix}posts`)
- **URL da Rede**: URL base da instalação WordPress
- **Sites Únicos**: Quantidade de sites diferentes neste padrão
- **Total de Índices**: Número total de índices seguindo este padrão
- **Lista de Índices**: Todos os nomes de índices individuais

### 4. Resumo Estatístico

Visão geral de todo o servidor:

- Total de redes detectadas
- Total de índices no Meilisearch
- URL da rede WordPress atual

## Casos de Uso

### Cenário 1: Múltiplas Instalações WordPress

Você gerencia 2 ou mais instalações WordPress Multisite separadas usando o mesmo servidor Meilisearch:

```
Rede 1 (https://rede1.com.br):
- Formato: wp_{blog_id}_posts
- Índices: wp_1_posts, wp_2_posts, wp_3_posts

Rede 2 (https://rede2.com.br):
- Formato: site_{blog_id}_posts
- Índices: site_1_posts, site_2_posts
```

O Index Analyzer identifica e agrupa automaticamente esses padrões, permitindo visualizar qual rede cada conjunto de índices pertence.

### Cenário 2: Migração e Auditoria

Ao migrar de uma instalação para outra ou fazer auditoria:

- Identifique índices órfãos (que não pertencem a nenhuma rede ativa)
- Verifique se os padrões de nomenclatura estão consistentes
- Confirme que todos os sites têm seus índices criados

### Cenário 3: Troubleshooting

Quando enfrentar problemas de busca:

- Verifique se os índices estão sendo criados com o padrão correto
- Confirme qual rede WordPress está associada a quais índices
- Identifique discrepâncias entre a configuração e os índices reais

## Como Funciona

### 1. Coleta de Índices

O sistema busca **todos** os índices do servidor Meilisearch:

```php
$indexes_results = $client->get_client()->getIndexes();
```

### 2. Parsing de Padrões

Para cada índice, o nome é analisado para extrair:

- Prefixo (ex: `wp_`, `site_`)
- ID do Blog (se presente)
- Sufixo (ex: `posts`)
- Padrão genérico (ex: `{prefix}posts`)

### 3. Agrupamento

Índices com o mesmo padrão são agrupados:

```
Padrão: {prefix}posts
├── wp_posts
├── wp_2_posts
├── wp_3_posts
└── wp_4_posts
```

### 4. Identificação de Rede

Para cada grupo, o sistema:

1. Extrai os IDs de blog dos índices
2. Consulta o banco de dados WordPress (`wp_blogs` e `wp_site`)
3. Identifica a URL da rede correspondente

```php
SELECT s.domain, s.path 
FROM wp_blogs b 
INNER JOIN wp_site s ON b.site_id = s.id 
WHERE b.blog_id = ?
```

## Interface

### Tabela Principal: Padrões Detectados

| Padrão do Índice | URL da Rede | Qtd. Sites | Total Índices |
|------------------|-------------|------------|---------------|
| `{prefix}posts` | https://rede1.com.br | 4 | 4 |
| `site_{blog_id}_posts` | https://rede2.com.br | 2 | 2 |

### Detalhes por Padrão

Expandindo cada padrão, você vê:

- **Formato do Padrão**: Template exato
- **URL da Rede**: Link clicável para a instalação
- **Sites Únicos**: Quantidade de sites diferentes
- **Total de Índices**: Contagem total
- **Nomes dos Índices**: Lista completa (expansível)

### Estatísticas Resumidas

Caixa de resumo no final da página:

- **Total de Redes Detectadas**: Quantas instalações WordPress diferentes
- **Total de Índices no Meilisearch**: Soma de todos os índices
- **Rede WordPress Atual**: URL da rede onde você está logado

## Atualização

Os dados são **sempre em tempo real** (sem cache):

- Clique em **"Refresh Analysis"** para recarregar
- Útil após criar/deletar índices
- Útil após adicionar novos sites

## Limitações

### 1. Detecção de URL

A detecção de URL da rede funciona apenas para:

- Índices que contêm Blog IDs válidos
- Sites que existem no banco de dados WordPress atual
- Redes acessíveis via tabelas `wp_blogs` e `wp_site`

Para índices sem Blog ID ou de redes externas, a URL aparecerá como "Network URL not detected".

### 2. Padrões Complexos

Padrões muito customizados ou irregulares podem não ser detectados automaticamente. O sistema tenta identificar padrões comuns baseados em:

- Prefixos alfanuméricos seguidos de underscore
- IDs numéricos
- Sufixos alfanuméricos

### 3. Redes Externas

Se você estiver usando Meilisearch compartilhado com outras instalações WordPress completamente separadas (sem acesso ao banco de dados), a identificação de URL não funcionará para essas redes externas.

## Dicas

### ✅ Boas Práticas

1. **Padronização**: Use formatos consistentes de nomenclatura
2. **Documentação**: Mantenha registro de qual rede usa qual padrão
3. **Auditoria Regular**: Verifique periodicamente se novos índices foram criados corretamente
4. **Cleanup**: Remova índices órfãos de sites deletados

### 🔍 Troubleshooting

**Problema**: "Network URL not detected"

**Soluções**:
- Verifique se o índice contém um Blog ID válido no nome
- Confirme que o site existe no banco de dados
- Para redes externas, isso é esperado

**Problema**: Índices não aparecem

**Soluções**:
- Verifique a conexão com Meilisearch em **Settings**
- Confirme que os índices existem com WP-CLI: `wp meilisearch list_indexes`
- Clique em "Refresh Analysis"

**Problema**: Padrão incorreto detectado

**Soluções**:
- Verifique o formato configurado em **Settings → Index Name Format**
- Recrie os índices se necessário
- Use WP-CLI para limpar e reindexar

## Integração com Outras Funcionalidades

### Com Metrics

- **Metrics** mostra apenas índices da rede atual (filtrados por padrão configurado)
- **Index Analyzer** mostra TODOS os índices no servidor (multi-rede)

### Com Settings

O formato configurado em **Settings → Index Name Format** determina como novos índices serão criados. O Index Analyzer mostra quais padrões já existem no servidor.

### Com WP-CLI

Use comandos WP-CLI em conjunto:

```bash
# Listar índices da rede atual
wp meilisearch list_indexes

# Ver todos os índices do servidor (via Index Analyzer)
# Acesse via interface web

# Deletar índices específicos
wp meilisearch delete_index 2 --yes
```

## Exemplo Prático

### Cenário: 2 Redes WordPress

**Setup**:
- Rede A (rede1.com.br): 3 sites
- Rede B (rede2.com.br): 2 sites
- Mesmo servidor Meilisearch

**Rede A - Formato**: `wp_{blog_id}_posts`
```
wp_1_posts
wp_2_posts
wp_3_posts
```

**Rede B - Formato**: `site_{blog_id}_posts`
```
site_1_posts
site_2_posts
```

**Index Analyzer mostrará**:

| Padrão | URL | Sites | Índices |
|--------|-----|-------|---------|
| `wp_{blog_id}_posts` | https://rede1.com.br | 3 | 3 |
| `site_{blog_id}_posts` | https://rede2.com.br | 2 | 2 |

**Resumo**: 2 redes detectadas, 5 índices totais

## Segurança

- Requer capacidade `manage_network_options`
- Apenas Super Admins têm acesso
- Somente leitura (não permite modificações)
- Dados em tempo real (não armazenados)

## Performance

- **Sem Cache**: Consulta Meilisearch a cada carregamento
- **Leve**: Apenas lista metadados dos índices
- **Rápido**: Não busca documentos, apenas informações de índices
- **Escalável**: Funciona bem mesmo com dezenas de índices

## Roadmap Futuro

Possíveis melhorias futuras:

- [ ] Exportar análise em CSV/JSON
- [ ] Comparar padrões entre diferentes servidores Meilisearch
- [ ] Sugestões de otimização de nomenclatura
- [ ] Detecção de índices órfãos automaticamente
- [ ] Botão para limpar índices não utilizados
- [ ] Histórico de análises ao longo do tempo
