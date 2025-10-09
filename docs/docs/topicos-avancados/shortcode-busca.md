---
id: shortcode
title: Shortcode de Busca
sidebar_label: Shortcode
sidebar_position: 8
description: Documentação completa do shortcode [meilisearch_search]
keywords:
  - shortcode
  - busca
  - formulário
  - resultados
  - totalizador
tags:
  - Features
  - Shortcode
  - Frontend
---

# Shortcode de Busca Meilisearch

## Visão Geral

O plugin Meilisearch para WordPress inclui um shortcode poderoso que permite adicionar funcionalidade de busca avançada em qualquer página do seu site. O destaque principal é o **totalizador de resultados**, que mostra de forma clara e precisa quantos resultados foram encontrados.

## Uso Básico

Para adicionar o formulário de busca e resultados em uma página, basta inserir o shortcode:

```
[meilisearch_search]
```

Isso criará automaticamente:
- ✅ Formulário de busca com campo de texto e botão
- ✅ **Totalizador de resultados** (contador)
- ✅ Lista de resultados formatados
- ✅ Paginação inteligente

## Totalizador de Resultados

O **totalizador** é o recurso principal deste shortcode. Ele exibe informações detalhadas sobre os resultados:

### Exemplos de Exibição

**Quando há resultados:**
```
Exibindo 1 - 10 de 45 resultados para "wordpress"
```

**Quando há apenas 1 resultado:**
```
Exibindo 1 de 1 resultado para "seo"
```

**Quando não há resultados:**
```
0 resultados encontrados para "xyz123"
```

### Características do Totalizador

- 📊 **Exibe o intervalo atual**: Mostra quais resultados estão sendo exibidos (ex: 1-10, 11-20)
- 🔢 **Total de resultados**: Informa o número total de resultados encontrados
- 🌐 **Localizado**: Usa funções de localização do WordPress para plural/singular correto
- 🎯 **Termo de busca destacado**: Mostra o termo pesquisado em negrito

## Atributos do Shortcode

Você pode personalizar o comportamento do shortcode usando atributos:

### `placeholder`
Define o texto de ajuda no campo de busca.

**Padrão:** `"Digite sua busca..."`

**Exemplo:**
```
[meilisearch_search placeholder="Buscar artigos..."]
```

### `button_text`
Define o texto do botão de busca.

**Padrão:** `"Buscar"`

**Exemplo:**
```
[meilisearch_search button_text="Pesquisar"]
```

### `results_per_page`
Define quantos resultados exibir por página.

**Padrão:** `10`

**Exemplo:**
```
[meilisearch_search results_per_page="20"]
```

### `show_excerpt`
Controla se o resumo do post deve ser exibido.

**Padrão:** `true`

**Valores:** `true` ou `false`

**Exemplo:**
```
[meilisearch_search show_excerpt="false"]
```

### `show_date`
Controla se a data de publicação deve ser exibida.

**Padrão:** `true`

**Valores:** `true` ou `false`

**Exemplo:**
```
[meilisearch_search show_date="false"]
```

### `show_author`
Controla se o nome do autor deve ser exibido.

**Padrão:** `true`

**Valores:** `true` ou `false`

**Exemplo:**
```
[meilisearch_search show_author="false"]
```

## Exemplos de Uso

### Exemplo 1: Configuração Padrão
```
[meilisearch_search]
```
Exibe busca com todas as configurações padrão e totalizador completo.

### Exemplo 2: Busca Minimalista
```
[meilisearch_search show_excerpt="false" show_date="false" show_author="false"]
```
Exibe apenas os títulos dos resultados e o totalizador.

### Exemplo 3: Busca com Mais Resultados
```
[meilisearch_search results_per_page="25" placeholder="Buscar no site..." button_text="Pesquisar Agora"]
```
Mostra 25 resultados por página com textos personalizados.

### Exemplo 4: Busca Completa Personalizada
```
[meilisearch_search 
    placeholder="O que você procura?" 
    button_text="Buscar" 
    results_per_page="15" 
    show_excerpt="true" 
    show_date="true" 
    show_author="true"]
```

## Estrutura dos Resultados

Cada resultado exibido contém:

1. **Título do Post** (link clicável)
2. **Metadados** (data e autor, se habilitados)
3. **Resumo/Excerpt** (se habilitado)
4. **Link "Leia mais"**

## Paginação

O shortcode inclui paginação automática que:

- 🔢 Mostra números de página
- ⏮️ Inclui botões "Anterior" e "Próximo"
- … Usa reticências para intervalos grandes
- 🎨 Destaca a página atual
- 🔄 Atualiza o totalizador automaticamente

### Exemplo de Paginação
```
← Anterior  1  2  3  ...  10  11  12  Próximo →
```

## Parâmetros de URL

O shortcode responde a parâmetros na URL:

- **`?s=termo`** - Define o termo de busca
- **`?paged=2`** - Define a página atual

**Exemplo de URL:**
```
https://seusite.com/busca/?s=wordpress&paged=2
```

Isso exibirá automaticamente:
- Resultados para "wordpress"
- Segunda página de resultados
- Totalizador atualizado: "Exibindo 11 - 20 de 45 resultados para 'wordpress'"

## Estilos CSS

O shortcode carrega automaticamente o arquivo CSS:
```
/wp-content/plugins/meilisearch/assets/css/search-results.css
```

### Classes CSS Disponíveis

Para personalização, você pode usar estas classes:

| Classe | Descrição |
|--------|-----------|
| `.meilisearch-search-form-wrapper` | Container do formulário |
| `.meilisearch-search-input` | Campo de texto da busca |
| `.meilisearch-search-submit` | Botão de busca |
| `.meilisearch-results-counter` | **Container do totalizador** |
| `.meilisearch-results-info` | **Texto do totalizador** |
| `.meilisearch-results-list` | Lista de resultados |
| `.meilisearch-result-item` | Item individual de resultado |
| `.meilisearch-result-title` | Título do resultado |
| `.meilisearch-result-excerpt` | Resumo do resultado |
| `.meilisearch-pagination` | Container da paginação |

### Personalizar o Totalizador

Para customizar o visual do totalizador, adicione CSS personalizado:

```css
.meilisearch-results-counter {
    background-color: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 1.5rem;
}

.meilisearch-results-info strong {
    color: #1976d2;
    font-size: 1.1rem;
}
```

## Integração com o Tema

O shortcode foi desenvolvido para funcionar com qualquer tema WordPress. Ele:

- ✅ Usa funções nativas do WordPress
- ✅ Respeita a localização (i18n) do site
- ✅ É responsivo e mobile-friendly
- ✅ Inclui suporte a modo escuro
- ✅ Segue padrões de acessibilidade

## Desenvolvimento

### Arquivo Principal
```
/wp-content/plugins/meilisearch/public/class-search-shortcode.php
```

### Métodos Principais

| Método | Descrição |
|--------|-----------|
| `render_search_form()` | Renderiza o formulário de busca |
| `render_search_results()` | Orquestra a exibição dos resultados |
| `render_results_counter()` | **Renderiza o totalizador** |
| `render_result_item()` | Renderiza um resultado individual |
| `render_pagination()` | Renderiza a paginação |

### Hooks e Filtros

O shortcode é registrado no hook `init` do WordPress:

```php
add_action('init', array($this, 'register_shortcode'));
```

Os estilos são carregados condicionalmente:

```php
add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
```

## Requisitos

- ✅ WordPress 5.0 ou superior
- ✅ Plugin Meilisearch ativado e configurado
- ✅ Índice Meilisearch com documentos indexados
- ✅ Meilisearch PHP Client configurado

## Solução de Problemas

### Totalizador não aparece
**Solução:** Certifique-se de que há uma busca ativa (parâmetro `?s=` na URL).

### Resultados não aparecem
**Solução:** Verifique se:
1. O Meilisearch está rodando
2. Os documentos foram indexados
3. O índice está configurado corretamente

### Estilos não carregam
**Solução:** Limpe o cache do site e verifique se o arquivo CSS existe em:
```
/wp-content/plugins/meilisearch/assets/css/search-results.css
```

### Paginação não funciona
**Solução:** Verifique se o permalink do WordPress está configurado (não use "Simples").

## Exemplos Práticos

### Página de Busca Dedicada

Crie uma página chamada "Busca" e adicione:

```
<!-- wp:paragraph -->
<p>Use o formulário abaixo para pesquisar em todo o conteúdo do site:</p>
<!-- /wp:paragraph -->

<!-- wp:shortcode -->
[meilisearch_search placeholder="Pesquisar artigos, páginas e posts..." results_per_page="15"]
<!-- /wp:shortcode -->
```

### Busca em Área Restrita

Para mostrar busca em uma área específica:

```
<!-- wp:heading -->
<h2>Central de Ajuda</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[meilisearch_search 
    placeholder="Como podemos ajudar?" 
    button_text="Buscar ajuda"
    show_author="false"]
<!-- /wp:shortcode -->
```

## Recursos Avançados

### Filtros Personalizados

Você pode estender a funcionalidade usando filtros WordPress (futuramente):

```php
// Exemplo futuro de filtro para customizar o totalizador
add_filter('meilisearch_results_counter_text', function($text, $from, $to, $total) {
    return "Mostrando {$from}-{$to} de {$total} itens";
}, 10, 4);
```

## Localização

O totalizador usa funções de localização do WordPress:

```php
_n(
    'Exibindo %1$s de %3$s resultado',
    'Exibindo %1$s - %2$s de %3$s resultados',
    $total_results,
    'meilisearch'
)
```

Isso garante que o texto seja corretamente traduzido e pluralizado.

## Suporte

Para problemas ou dúvidas sobre o shortcode e o totalizador:

1. Verifique esta documentação
2. Consulte os logs do WordPress em `wp-content/debug.log`
3. Verifique o status do Meilisearch
4. Consulte a documentação do Meilisearch em https://www.meilisearch.com/docs

## Changelog

### Versão 1.0.0
- ✨ Implementação inicial do shortcode `[meilisearch_search]`
- ✨ **Totalizador de resultados completo**
- ✨ Formulário de busca personalizável
- ✨ Paginação inteligente com reticências
- ✨ Suporte a múltiplos atributos
- ✨ CSS responsivo com modo escuro
- ✨ Localização completa em português

---

**Desenvolvido para WordPress com ❤️**
