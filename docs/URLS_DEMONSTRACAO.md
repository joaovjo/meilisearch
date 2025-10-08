# 🎯 URLs de Demonstração - Ranking Scores Meilisearch

## ✅ Demonstrações Disponíveis:

### 1. 📄 Demonstração HTML Estática (RECOMENDADO)
**URL:** http://10.28.13.21:31103/demo-badges-estatico.html

**O que mostra:**
- ✅ 3 exemplos de resultados com diferentes relevâncias
- ✅ Badge verde (100%) - Alta relevância
- ✅ Badge amarelo (67.5%) - Média relevância  
- ✅ Badge vermelho (32.8%) - Baixa relevância
- ✅ Funciona independente do tema WordPress

**Vantagens:**
- Carrega instantaneamente
- Não depende do tema
- CSS completo funcionando

---

### 2. 🔍 Busca Real no WordPress com PHP Standalone
**URL:** http://10.28.13.21:31103/demo-busca-meilisearch.php?s=orientacoes

**O que mostra:**
- ✅ Resultado REAL da busca "orientacoes" no Meilisearch
- ✅ Badge de 100% (verde) no attachment indexado
- ✅ Executa busca real no índice
- ✅ Funciona independente do tema

**Vantagens:**
- Dados reais do Meilisearch
- Ranking score real
- Independente do template do tema

---

### 3. 📝 Página WordPress (Depende do tema)
**URL:** http://10.28.13.21:31103/teste-de-busca-meilisearch/

**Status:** ⚠️ Corrigido - Agora usa template com `the_content()`

**Como testar:**
1. Acesse a URL acima
2. Digite "orientacoes" no campo de busca
3. Clique em "Buscar"
4. Veja o badge de 100% em verde ao lado do título

**Problema encontrado:**
- O tema personalizado "pms-secretaria_migracao2024_docker" 
- Usava template `page.php` que não chama `the_content()`
- **Solução aplicada:** Mudado para template `page-subsite-menu.php`

---

## 🧪 Testes via Linha de Comando:

### WP-CLI - Coluna de Relevância
```bash
wp --url=http://10.28.13.21:31103/ meilisearch search "orientacoes" --path=/var/www/html --allow-root
```
**Resultado esperado:**
```
| relevance | 
|-----------|
| 100%      |
```

### API REST - JSON com Ranking Score
```bash
curl "http://10.28.13.21:31103/wp-json/meilisearch/v1/search?q=orientacoes"
```
**Resultado esperado:**
```json
{
  "_rankingScore": 1,
  "relevance": 100,
  ...
}
```

---

## 📊 Como os Badges Funcionam:

### Lógica de Cores:
```php
if ($ranking_score >= 0.8) {
    $class = 'high';    // 🟢 Verde
} elseif ($ranking_score >= 0.5) {
    $class = 'medium';  // 🟡 Amarelo
} else {
    $class = 'low';     // 🔴 Vermelho
}
```

### Estrutura HTML do Badge:
```html
<span class="meilisearch-relevance-badge meilisearch-relevance-high" 
      title="Relevância: 100%">
    <svg>⭐</svg>
    100%
</span>
```

---

## 🔧 Problema do Tema Resolvido:

### Causa:
O tema personalizado não renderizava o conteúdo da página corretamente.

### Templates do Tema:
1. **`page.php`** (Padrão) → ❌ Não chama `the_content()`
2. **`page-subsite-menu.php`** → ✅ Chama `the_content()` corretamente

### Solução Aplicada:
```bash
wp post meta update 475 _wp_page_template 'page-subsite-menu.php'
```

### Por que as demos standalone funcionam:
- Não dependem do sistema de templates do WordPress
- Carregam o WordPress via `wp-load.php` diretamente
- Renderizam o shortcode manualmente com `do_shortcode()`

---

## 🎨 Arquivos Criados:

1. `/var/www/html/demo-badges-estatico.html` - HTML puro
2. `/var/www/html/demo-busca-meilisearch.php` - PHP standalone  
3. `/var/www/html/wp-content/plugins/meilisearch/docs/ranking-score-demo.html` - Demo no plugin
4. `/var/www/html/wp-content/plugins/meilisearch/docs/RANKING_SCORE.md` - Documentação
5. `/var/www/html/wp-content/plugins/meilisearch/docs/real-search-result.html` - Resultado capturado

---

## ✅ Recomendação:

**Use a demonstração HTML estática para ver os badges claramente:**
```
http://10.28.13.21:31103/demo-badges-estatico.html
```

Esta versão:
- ✅ Funciona sempre
- ✅ Não depende de configurações do tema
- ✅ Mostra todos os 3 níveis de relevância
- ✅ CSS completo carregado

---

**Última atualização:** 8 de outubro de 2025  
**Status:** ✅ Implementação completa e funcional
