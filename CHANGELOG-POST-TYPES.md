# Changelog - Seleção de Tipos de Posts

## Funcionalidade Adicionada

### Opção para Selecionar Tipos de Posts para Indexar

Foi adicionada uma nova funcionalidade que permite aos administradores da rede selecionar quais tipos de posts devem ser indexados no Meilisearch.

### Alterações Realizadas

#### 1. Página de Configurações (`admin/class-network-settings.php`)

- **Novo Campo**: Adicionado campo "Post Types to Index" na página de configurações
  - Exibe checkboxes para todos os tipos de posts públicos registrados no WordPress
  - Permite seleção múltipla
  - Mostra o nome amigável e o nome técnico de cada tipo de post
  - Valores padrão: 'post' e 'page'

- **Sanitização**: Implementada sanitização adequada dos tipos de posts selecionados
  - Usa `sanitize_key()` para cada tipo de post
  - Garante que sempre há pelo menos um tipo de post selecionado (fallback para 'post' e 'page')

#### 2. Indexador (`includes/class-indexer.php`)

- **Novos Métodos Privados**:
  - `get_indexable_post_types()`: Retorna a lista de tipos de posts configurados para indexação
  - `should_index_post_type($post_type)`: Verifica se um tipo de post específico deve ser indexado

- **Modificações nos Métodos Existentes**:
  - `index_post()`: Agora verifica se o tipo de post deve ser indexado antes de processar
  - `index_site_posts()`: Usa a lista de tipos de posts configurados ao invés de 'any'

#### 3. Arquivo Principal (`meilisearch.php`)

- **Valores Padrão**: Atualizado para incluir 'post_types' => ['post', 'page'] nas configurações padrão

#### 4. Traduções (`languages/meilisearch-pt_BR.po`)

- Adicionadas novas strings de tradução:
  - "Post Types to Index" → "Tipos de Posts para Indexar"
  - "Select which post types should be indexed..." → "Selecione quais tipos de posts devem ser indexados..."

### Como Usar

1. Acesse **Rede Admin > Meilisearch > Settings**
2. Localize a seção "Post Types to Index"
3. Selecione os tipos de posts que deseja indexar:
   - Posts (post)
   - Páginas (page)
   - Qualquer outro tipo de post personalizado registrado
4. Clique em "Save Settings"
5. Execute a reindexação para aplicar as mudanças:
   ```bash
   wp meilisearch index --network
   ```

### Comportamento

- **Indexação Automática**: Quando um post é criado/atualizado, ele só será indexado se seu tipo estiver selecionado
- **Indexação em Lote**: A indexação em lote (bulk) usa apenas os tipos de posts configurados
- **Fallback**: Se nenhum tipo de post estiver selecionado, o sistema usa 'post' e 'page' como padrão
- **Tipos Públicos**: Apenas tipos de posts públicos são exibidos nas opções

### Compatibilidade

- ✅ Compatível com tipos de posts nativos do WordPress (post, page)
- ✅ Compatível com Custom Post Types
- ✅ Mantém retrocompatibilidade com instalações existentes (padrão: post e page)
- ✅ Funciona com multisite

### Exemplo de Uso

Se você tem um site com:
- Posts (post)
- Páginas (page)
- Produtos (product) - Custom Post Type
- Eventos (event) - Custom Post Type

E deseja indexar apenas Posts e Produtos:
1. Marque apenas "Posts" e "Produtos"
2. Salve as configurações
3. Execute `wp meilisearch index --network`
4. Apenas posts e produtos serão indexados, páginas e eventos serão ignorados

### Arquivos Modificados

- `admin/class-network-settings.php`
- `includes/class-indexer.php`
- `meilisearch.php`
- `languages/meilisearch-pt_BR.po`

### Testes Recomendados

1. ✅ Verificar se a interface de configuração exibe todos os tipos de posts
2. ✅ Testar salvamento das configurações
3. ✅ Verificar se apenas os tipos selecionados são indexados
4. ✅ Testar indexação automática ao criar/editar posts
5. ✅ Testar indexação em lote via WP-CLI
6. ✅ Verificar comportamento com tipos de posts personalizados
