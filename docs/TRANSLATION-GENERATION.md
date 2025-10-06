# Geração de Arquivos de Tradução

Este documento descreve o processo de geração e atualização dos arquivos de tradução do plugin Meilisearch usando WP-CLI.

## 📋 Resumo da Geração

**Data**: 6 de outubro de 2025  
**Ferramenta**: WP-CLI (wp i18n)  
**Locale**: pt_BR (Português do Brasil)

### Arquivos Gerados

```
languages/
├── meilisearch.pot          (13KB - 136 strings)
├── meilisearch-pt_BR.po     (17KB - 136 strings traduzidas)
└── meilisearch-pt_BR.mo     (9.7KB - formato binário)
```

## 🔧 Comandos Utilizados

### 1. Gerar arquivo .pot (template de tradução)

```bash
wp i18n make-pot . languages/meilisearch.pot --allow-root
```

**O que faz**: Extrai todas as strings traduzíveis do código PHP e cria um arquivo template (.pot).

**Resultado**: 
- ✅ Plugin file detected
- ✅ 136 strings extraídas
- ⚠️ 6 warnings sobre placeholders sem comentários "translators:"

### 2. Atualizar arquivo .po existente

```bash
wp i18n update-po languages/meilisearch.pot languages/meilisearch-pt_BR.po --allow-root
```

**O que faz**: Atualiza o arquivo de tradução português com as novas strings do .pot.

**Resultado**:
- ✅ Updated 1 file
- ✅ Mantém traduções existentes
- ✅ Adiciona novas strings (incluindo as 2 novas do Post Types)

### 3. Compilar arquivo .mo

```bash
wp i18n make-mo languages/ --allow-root
```

**O que faz**: Compila o arquivo .po em formato binário .mo para uso pelo WordPress.

**Resultado**:
- ✅ Created 1 file (meilisearch-pt_BR.mo - 9.7KB)

## 📝 Novas Strings Adicionadas

As seguintes strings foram adicionadas ao sistema de tradução:

### 1. Label do Campo

```php
// Localização: admin/class-network-settings.php:218
__('Post Types to Index', 'meilisearch')
```

**Tradução**:
```
msgid "Post Types to Index"
msgstr "Tipos de Posts para Indexar"
```

### 2. Descrição do Campo

```php
// Localização: admin/class-network-settings.php:241
__('Select which post types should be indexed in Meilisearch. Only published content will be indexed.', 'meilisearch')
```

**Tradução**:
```
msgid "Select which post types should be indexed in Meilisearch. Only published content will be indexed."
msgstr "Selecione quais tipos de posts devem ser indexados no Meilisearch. Apenas conteúdo publicado será indexado."
```

## ⚠️ Avisos (Warnings)

Durante a geração, foram identificados 6 avisos sobre strings com placeholders sem comentários explicativos:

```
Warning: The string "Error during reindexing: %s" contains placeholders but has no "translators:" comment
Warning: The string "%d indexes" contains placeholders but has no "translators:" comment
Warning: The string "View %d sites" contains placeholders but has no "translators:" comment
Warning: The string "Site ID: %d" contains placeholders but has no "translators:" comment
Warning: The string "Last updated: %s" contains placeholders but has no "translators:" comment
Warning: The string "Use WP-CLI to index all sites: %s" contains placeholders but has no "translators:" comment
```

### Como Corrigir (Opcional)

Adicione comentários antes das strings com placeholders:

```php
// Antes
printf(__('Error during reindexing: %s', 'meilisearch'), $error);

// Depois
/* translators: %s: error message */
printf(__('Error during reindexing: %s', 'meilisearch'), $error);
```

## 📊 Estatísticas

| Métrica | Valor |
|---------|-------|
| Total de strings | 136 |
| Strings traduzidas (pt_BR) | 136 |
| Cobertura de tradução | 100% |
| Tamanho do .pot | 13KB |
| Tamanho do .po | 17KB |
| Tamanho do .mo | 9.7KB |

## 🔄 Fluxo de Trabalho Completo

```
┌──────────────────────────────────────────────────┐
│ 1. Código PHP com strings __() e _e()           │
└────────────────┬─────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────┐
│ 2. wp i18n make-pot                              │
│    → Gera meilisearch.pot (template)            │
└────────────────┬─────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────┐
│ 3. wp i18n update-po                             │
│    → Atualiza meilisearch-pt_BR.po               │
└────────────────┬─────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────┐
│ 4. Tradução manual (se necessário)               │
│    → Editar .po com Poedit ou editor de texto   │
└────────────────┬─────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────┐
│ 5. wp i18n make-mo                               │
│    → Compila para meilisearch-pt_BR.mo          │
└────────────────┬─────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────┐
│ 6. WordPress carrega automaticamente             │
│    → Strings aparecem traduzidas no admin       │
└──────────────────────────────────────────────────┘
```

## 🚀 Comandos de Manutenção

### Regenerar todos os arquivos

```bash
# 1. Gerar .pot
wp i18n make-pot . languages/meilisearch.pot --allow-root

# 2. Atualizar .po
wp i18n update-po languages/meilisearch.pot languages/meilisearch-pt_BR.po --allow-root

# 3. Compilar .mo
wp i18n make-mo languages/ --allow-root
```

### Adicionar novo idioma

```bash
# 1. Criar arquivo .po para novo idioma (ex: espanhol)
cp languages/meilisearch.pot languages/meilisearch-es_ES.po

# 2. Editar cabeçalho do arquivo
# Language: es_ES
# Language-Team: Spanish (Spain)

# 3. Traduzir strings manualmente ou com ferramenta

# 4. Compilar
wp i18n make-mo languages/ --allow-root
```

### Verificar strings não traduzidas

```bash
# Buscar strings vazias no .po
grep -A 1 '^msgid' languages/meilisearch-pt_BR.po | grep 'msgstr ""' -B 1
```

## 🛠️ Ferramentas Recomendadas

### Poedit
- **Download**: https://poedit.net/
- **Função**: Editor visual de arquivos .po/.pot
- **Vantagens**: 
  - Interface gráfica amigável
  - Detecção automática de strings não traduzidas
  - Sugestões de tradução
  - Validação de formatação

### WP-CLI i18n
- **Documentação**: https://developer.wordpress.org/cli/commands/i18n/
- **Comandos disponíveis**:
  - `make-pot` - Gerar arquivo .pot
  - `make-mo` - Compilar .po para .mo
  - `make-json` - Gerar JSON para Gutenberg
  - `update-po` - Atualizar arquivo .po

### Visual Studio Code Extensions
- **WP-CLI**: Integração com WP-CLI
- **PHP Gettext**: Syntax highlighting para .po/.pot
- **i18n Ally**: Gerenciamento de traduções inline

## 📚 Referências

- [WordPress Internationalization (i18n)](https://developer.wordpress.org/apis/handbook/internationalization/)
- [WP-CLI i18n Command](https://developer.wordpress.org/cli/commands/i18n/)
- [GNU gettext Manual](https://www.gnu.org/software/gettext/manual/)
- [Translating WordPress Plugins](https://developer.wordpress.org/plugins/internationalization/localization/)

## ✅ Checklist de Tradução

- [x] Arquivo .pot gerado com todas as strings
- [x] Arquivo .po atualizado com novas strings
- [x] Todas as strings traduzidas (100%)
- [x] Arquivo .mo compilado
- [x] Traduções testadas no WordPress
- [ ] Comentários "translators:" adicionados (opcional)
- [ ] Arquivos JSON gerados para Gutenberg (se necessário)
- [ ] Testes de plural forms (se aplicável)

## 🌍 Suporte Multi-idioma

O plugin está preparado para suportar múltiplos idiomas. Para adicionar novos idiomas:

1. Copie `meilisearch.pot` para `meilisearch-{locale}.po`
2. Traduza as strings
3. Compile para `.mo`
4. WordPress carregará automaticamente baseado no idioma do site

### Idiomas Atualmente Suportados

- ✅ **pt_BR** - Português (Brasil) - 100% traduzido
- 📝 Outros idiomas podem ser adicionados facilmente

## 🔍 Testando as Traduções

### No WordPress

1. Acesse **Rede Admin > Meilisearch > Settings**
2. Verifique se os labels aparecem em português:
   - "Tipos de Posts para Indexar"
   - "Selecione quais tipos de posts devem ser indexados..."

### Debug

Se as traduções não aparecerem:

```php
// Adicione no wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Verifique se o WordPress está carregando o arquivo
$locale = get_locale();
$mofile = WP_PLUGIN_DIR . '/meilisearch/languages/meilisearch-' . $locale . '.mo';
error_log('Translation file: ' . $mofile);
error_log('File exists: ' . (file_exists($mofile) ? 'yes' : 'no'));
```

## 📅 Histórico de Atualizações

| Data | Ação | Detalhes |
|------|------|----------|
| 2025-10-06 | Geração inicial | 136 strings extraídas |
| 2025-10-06 | Novas strings | +2 strings (Post Types to Index) |
| 2025-10-06 | Tradução pt_BR | 100% traduzido |
| 2025-10-06 | Compilação | Arquivo .mo gerado (9.7KB) |

---

**Nota**: Este documento será atualizado sempre que novas strings forem adicionadas ou traduções forem atualizadas.
