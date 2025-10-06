# Multi-Pattern Search

## Visão Geral

A funcionalidade **Multi-Pattern Search** (Busca Multi-Padrão) permite pesquisar simultaneamente em múltiplas redes WordPress que compartilham o mesmo servidor Meilisearch. Esta funcionalidade é especialmente útil quando você tem várias redes WordPress independentes, cada uma com seu próprio padrão de índice, mas deseja que os resultados de busca incluam conteúdo de todas elas.

## Conceito

Em um ambiente WordPress Multisite com múltiplas redes, cada rede pode ter seu próprio padrão de nomenclatura de índices no Meilisearch:

- **Rede 1**: Usa o padrão `wp_{type}` (ex: `wp_posts`)
- **Rede 2**: Usa o padrão `setur_{blog_id}_posts` (ex: `setur_1_posts`, `setur_2_posts`)

Por padrão, quando um usuário faz uma busca na Rede 1, apenas os índices que seguem o padrão `wp_{type}` são consultados. Com a Multi-Pattern Search, você pode configurar a Rede 1 para também buscar nos índices da Rede 2 (padrão `setur_{blog_id}_posts`), permitindo resultados cross-network.

## Casos de Uso

### 1. Portal Corporativo Integrado
Uma empresa possui:
- **Rede Principal** (portal público): `company_posts`
- **Rede Interna** (intranet): `intranet_{blog_id}_posts`

Configurando a Multi-Pattern Search na rede principal, os usuários podem buscar tanto no conteúdo público quanto no conteúdo da intranet (se tiverem permissão).

### 2. Rede de Sites Temáticos
Uma organização mantém múltiplas redes temáticas:
- **Rede Educação**: `edu_{blog_id}_posts`
- **Rede Turismo**: `tourism_{blog_id}_posts`
- **Rede Cultura**: `culture_{blog_id}_posts`

Cada rede pode opcionalmente incluir resultados das outras redes nas buscas.

### 3. Migração Gradual
Durante uma migração de uma estrutura de índices para outra:
- **Índices Antigos**: `old_{blog_id}_posts`
- **Índices Novos**: `new_{blog_id}_posts`

A Multi-Pattern Search permite que ambos os padrões sejam pesquisados durante o período de transição.

## Como Usar

### Acessando a Página

1. Acesse o **Admin da Rede** (Network Admin)
2. No menu lateral, clique em **Meilisearch**
3. Clique em **Multi-Pattern Search**

### Configurando Padrões Adicionais

1. A página exibirá uma tabela com todos os padrões de índice detectados no Meilisearch
2. O padrão da **rede atual** aparece destacado em azul e não pode ser desmarcado (está sempre ativo)
3. Para cada padrão adicional disponível:
   - **Pattern**: Mostra o formato do índice (ex: `setur_{blog_id}_posts`)
   - **Network URL**: URL da rede que usa este padrão
   - **Indexes**: Quantidade de índices encontrados com este padrão
   - **Status**: Indica se está Ativo, Inativo ou é o padrão Atual
4. Marque os checkboxes dos padrões que deseja incluir nas buscas
5. Clique em **Salvar Configurações**

### Exemplo de Configuração

```
┌─────────────────────────────────────────────────────────────────────┐
│ Available Index Patterns                                            │
├───────┬──────────────────────┬────────────────────┬─────────────────┤
│Select │ Pattern              │ Network URL        │ Indexes  Status │
├───────┼──────────────────────┼────────────────────┼─────────────────┤
│  ─    │ wp_posts ⭐ Atual    │ http://10.28.13.21 │ 1       Atual   │
│  ☑    │ setur_{blog_id}_posts│ http://10.28.10.21 │ 3       Ativo   │
└───────┴──────────────────────┴────────────────────┴─────────────────┘
```

Neste exemplo, a rede atual usa o padrão `wp_posts` e também está configurada para buscar no padrão `setur_{blog_id}_posts`.

## Funcionamento Técnico

### Detecção de Padrões

A página analisa todos os índices disponíveis no Meilisearch e identifica automaticamente os padrões usando regex:

- **Padrão com blog_id**: `prefix_{blog_id}_suffix` → `setur_1_posts`, `setur_2_posts`
- **Padrão sem blog_id**: `prefix_suffix` → `wp_posts`, `company_posts`

### Armazenamento da Configuração

Os padrões selecionados são salvos como uma opção de rede:
- **Multisite**: `wp_sitemeta` → `meilisearch_additional_patterns`
- **Single Site**: `wp_options` → `meilisearch_additional_patterns`

### Integração com a Busca

Quando uma busca é realizada:

1. O sistema identifica os índices da rede atual (padrão configurado)
2. Busca os padrões adicionais na configuração
3. Converte cada padrão adicional em regex
4. Busca no Meilisearch todos os índices que correspondem aos padrões adicionais
5. Combina todos os índices (atuais + adicionais) em uma única multi-search
6. Retorna os resultados agregados

### Exemplo de Código

```php
// Na classe Meilisearch_Searcher
private function get_searchable_indexes(): array {
    // Índices da rede atual
    $indexes = ['wp_posts'];
    
    // Padrões adicionais configurados
    $additional_patterns = ['setur_{blog_id}_posts'];
    
    // Busca índices que correspondem ao padrão adicional
    // Resultado: ['wp_posts', 'setur_1_posts', 'setur_2_posts', 'setur_3_posts']
    
    return $indexes;
}
```

## Características Importantes

### ✅ Sem Cache
A página **não utiliza cache**. Sempre busca os dados mais recentes diretamente do Meilisearch, garantindo que novos índices sejam detectados imediatamente.

### ✅ Real-Time
As mudanças nas configurações entram em vigor imediatamente. Não é necessário reindexar ou limpar cache.

### ✅ Segurança
- Apenas administradores de rede têm acesso à funcionalidade
- Validação de nonce em todas as submissões de formulário
- Sanitização de todos os dados de entrada

### ✅ Performance
- Usa multi-search do Meilisearch para consultar múltiplos índices em paralelo
- Não há impacto significativo na performance mesmo com muitos padrões adicionais

## Diferenças Entre Páginas

| Funcionalidade | Métricas | Index Analyzer | Multi-Pattern Search |
|---------------|----------|----------------|----------------------|
| **Objetivo** | Exibir estatísticas dos índices da rede atual | Analisar todos os padrões disponíveis | Configurar quais padrões incluir na busca |
| **Escopo** | Apenas rede atual | Todas as redes no Meilisearch | Todas as redes no Meilisearch |
| **Ação** | Visualização | Visualização | Configuração (salva settings) |
| **Impacto** | Nenhum | Nenhum | Modifica comportamento da busca |

## Limitações

1. **Permissões de Conteúdo**: A Multi-Pattern Search não verifica permissões de acesso. Se um usuário não deveria ver conteúdo de outra rede, você deve implementar filtros adicionais no frontend.

2. **Relevância**: Os resultados de diferentes redes são mesclados por score do Meilisearch. Pode ser necessário ajustar a exibição para distinguir conteúdo de diferentes redes.

3. **URLs**: Os resultados mantêm seus permalinks originais. Certifique-se de que os usuários podem acessar as URLs de outras redes.

## Troubleshooting

### Nenhum padrão aparece na lista
- Verifique se há índices criados no Meilisearch
- Confirme a conexão com o Meilisearch no Dashboard
- Verifique os logs de erro do PHP/WordPress

### Padrão selecionado mas sem resultados
- Verifique se há documentos nos índices daquele padrão
- Confirme que os índices têm documentos com `post_status = publish`
- Teste a busca diretamente no Meilisearch via API

### Resultados duplicados
- Pode ocorrer se o mesmo conteúdo está indexado em múltiplos padrões
- Considere implementar deduplicação no frontend

### URL da rede não aparece
- A URL é extraída do campo `permalink` dos documentos indexados
- Se nenhum documento for encontrado no índice, a URL aparecerá como "Desconhecido"
- Certifique-se de que há pelo menos 1 documento publicado no índice

## Exemplos de Uso

### Exemplo 1: Adicionar Segunda Rede

```bash
# Cenário inicial
Rede Atual: wp_posts (http://10.28.13.21:31103)
Outras Redes: setur_{blog_id}_posts (http://10.28.10.21:31102)

# Ação
1. Acessar Multi-Pattern Search
2. Marcar checkbox do padrão "setur_{blog_id}_posts"
3. Salvar

# Resultado
Buscas na rede atual (31103) agora incluem resultados da rede 31102
```

### Exemplo 2: Remover Padrão Adicional

```bash
# Cenário inicial
Padrões ativos: wp_posts + setur_{blog_id}_posts

# Ação
1. Acessar Multi-Pattern Search
2. Desmarcar checkbox do padrão "setur_{blog_id}_posts"
3. Salvar

# Resultado
Buscas voltam a exibir apenas resultados da rede atual
```

### Exemplo 3: Adicionar Múltiplos Padrões

```bash
# Cenário
3 redes disponíveis:
- wp_posts (rede principal)
- old_{blog_id}_posts (índices antigos)
- new_{blog_id}_posts (índices novos)

# Ação
1. Marcar ambos "old_{blog_id}_posts" e "new_{blog_id}_posts"
2. Salvar

# Resultado
Buscas incluem resultados de todas as 3 redes simultaneamente
```

## API e Filtros

### Obter padrões adicionais via código

```php
// Multisite
$patterns = get_site_option('meilisearch_additional_patterns', []);

// Single site
$patterns = get_option('meilisearch_additional_patterns', []);
```

### Modificar padrões programaticamente

```php
// Adicionar padrão
$patterns = get_site_option('meilisearch_additional_patterns', []);
$patterns[] = 'mynetwork_{blog_id}_posts';
update_site_option('meilisearch_additional_patterns', $patterns);

// Remover todos os padrões
update_site_option('meilisearch_additional_patterns', []);
```

## Considerações de Performance

A Multi-Pattern Search usa a funcionalidade de **multi-search** do Meilisearch, que executa múltiplas buscas em paralelo. O overhead é mínimo:

- **1 padrão adicional** com 3 índices → 4 buscas em paralelo
- **2 padrões adicionais** com 6 índices total → 7 buscas em paralelo

O Meilisearch é otimizado para este tipo de operação e geralmente responde em menos de 50ms mesmo com dezenas de índices.

## Conclusão

A Multi-Pattern Search é uma funcionalidade poderosa para ambientes com múltiplas redes WordPress compartilhando o mesmo Meilisearch. Ela permite criar experiências de busca unificadas sem comprometer a independência e organização das redes individuais.

Para mais informações sobre outras funcionalidades do plugin, consulte:
- [Index Analyzer](INDEX-ANALYZER.md)
- [WP-CLI Commands](WP-CLI.md)
- [README Principal](../README.md)
