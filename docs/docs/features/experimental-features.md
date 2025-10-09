# Funcionalidades Experimentais

A página de **Funcionalidades Experimentais** permite habilitar recursos avançados e experimentais do Meilisearch diretamente pela interface de administração do WordPress.

## Acesso

A página pode ser acessada através de:

**Administração de Rede** → **Meilisearch** → **Experimental**

## Recursos Disponíveis

### Vector Store

**Status:** Experimental  
**Descrição:** Habilita capacidades de busca vetorial para pesquisa semântica e recursos alimentados por IA. Permite armazenar e pesquisar embeddings vetoriais.

**Casos de uso:**
- Busca semântica baseada em significado, não apenas palavras-chave
- Integração com modelos de IA para recomendações de conteúdo
- Busca por similaridade de documentos

### Metrics

**Status:** Experimental  
**Descrição:** Habilita endpoint de métricas compatível com Prometheus para monitorar performance de busca e uso de recursos.

**Casos de uso:**
- Monitoramento em tempo real do servidor Meilisearch
- Integração com ferramentas de observabilidade (Grafana, Prometheus)
- Análise de performance e uso de recursos

### Logs Route

**Status:** Experimental  
**Descrição:** Habilita rota da API para acessar logs do Meilisearch diretamente através da API para debugging e monitoramento.

**Casos de uso:**
- Debugging de problemas de indexação
- Análise de erros e warnings
- Auditoria de operações do servidor

### Edit Documents By Function

**Status:** Experimental  
**Descrição:** Habilita edição de documentos usando funções customizadas para operações em lote e transformações.

**Casos de uso:**
- Transformação em massa de documentos indexados
- Aplicação de regras de negócio durante indexação
- Normalização de dados automatizada

### Contains Filter

**Status:** Experimental  
**Descrição:** Habilita operador de filtro "contains" para correspondência parcial de strings em filtros de busca.

**Casos de uso:**
- Busca por parte de um nome ou texto
- Filtros mais flexíveis em campos de texto
- Autocomplete avançado com filtragem parcial

## Como Usar

1. **Acesse a página**: Navegue para **Administração de Rede** → **Meilisearch** → **Experimental**

2. **Revise o aviso**: Leia atentamente o aviso sobre instabilidade das funcionalidades experimentais

3. **Habilite recursos**: Marque as caixas de seleção dos recursos que deseja habilitar

4. **Salve**: Clique em **Salvar Funcionalidades Experimentais**

5. **Verifique o status**: A tabela "Configuração Atual" mostra o estado real de cada recurso no servidor

## Avisos Importantes

⚠️ **Funcionalidades experimentais são instáveis** e podem:
- Mudar sem aviso prévio
- Ser removidas em versões futuras
- Causar comportamento inesperado
- Não ter suporte completo

**Recomendações:**
- ✅ Teste em ambiente de desenvolvimento primeiro
- ✅ Faça backup dos dados antes de habilitar
- ✅ Monitore logs após habilitação
- ❌ Não use em produção sem testes extensivos

## Configuração via API

As funcionalidades experimentais também podem ser configuradas diretamente via API do Meilisearch:

```bash
curl -X PATCH 'http://localhost:7700/experimental-features' \
  -H 'Authorization: Bearer MASTER_KEY' \
  -H 'Content-Type: application/json' \
  --data-binary '{
    "vectorStore": true,
    "metrics": false,
    "logsRoute": false,
    "editDocumentsByFunction": false,
    "containsFilter": false
  }'
```

## Status Atual

A página sempre exibe o status atual de cada funcionalidade experimental:
- ✅ **ATIVADO**: Recurso está ativo no servidor
- ❌ **DESATIVADO**: Recurso está inativo no servidor

O botão **Atualizar Status** recarrega os dados mais recentes do servidor.

## Requisitos

- Meilisearch v1.3.0 ou superior
- Permissões de **Administrador de Rede**
- Conexão ativa com o servidor Meilisearch

## Solução de Problemas

### Não consigo acessar a página

**Causa:** Falta de permissões ou plugin desativado  
**Solução:** Verifique se você é administrador de rede e se o plugin está ativado para rede

### Erro ao salvar configurações

**Causa:** Problemas de conexão com servidor Meilisearch  
**Solução:** 
1. Verifique as configurações em **Meilisearch** → **Settings**
2. Confirme que o servidor está rodando: `curl http://localhost:7700/health`
3. Verifique os logs do servidor Meilisearch

### Funcionalidade não está funcionando após habilitar

**Causa:** Versão do Meilisearch incompatível ou recurso realmente experimental  
**Solução:**
1. Verifique a versão do Meilisearch: `curl http://localhost:7700/version`
2. Consulte a [documentação oficial](https://www.meilisearch.com/docs/reference/api/experimental_features)
3. Verifique os logs do servidor para erros

## Referências

- [Documentação Oficial - Experimental Features](https://www.meilisearch.com/docs/reference/api/experimental_features)
- [Meilisearch GitHub](https://github.com/meilisearch/meilisearch)
- [Roadmap do Meilisearch](https://roadmap.meilisearch.com/)
