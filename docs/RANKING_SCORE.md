# 🎯 Demonstração Visual - Ranking Score do Meilisearch

## 📖 O que foi implementado?

A visualização dos **scores de relevância** do Meilisearch foi adicionada ao plugin, permitindo que usuários vejam o quão relevante cada resultado de busca é em relação à consulta realizada.

---

## 🎨 Demonstração Visual

Para ver a demonstração em HTML puro, abra o arquivo:

```
/var/www/html/wp-content/plugins/meilisearch/docs/ranking-score-demo.html
```

Ou acesse a página de teste WordPress criada:

**URL:** http://10.28.13.21:31103/teste-de-busca-meilisearch/

---

## 📊 O que é Ranking Score?

O **Ranking Score** é uma métrica retornada pelo Meilisearch que indica a relevância de um resultado de busca. Ele varia de **0.0 a 1.0**, onde:

- **1.0** = Correspondência perfeita
- **0.5** = Correspondência mediana
- **0.0** = Baixa correspondência

### Como é calculado?

O Meilisearch calcula o score baseado em vários fatores:

1. **Typo tolerance** - Tolerância a erros de digitação
2. **Words** - Quantidade de palavras encontradas
3. **Proximity** - Proximidade das palavras entre si
4. **Attribute ranking** - Peso dos campos onde os termos foram encontrados
5. **Sort** - Ordenação customizada (se configurada)
6. **Exactness** - Quão exata é a correspondência

---

## 🎨 Badges Visuais Implementados

Os badges são exibidos ao lado do título de cada resultado, com cores diferentes baseadas na relevância:

### 🟢 Alta Relevância (≥80%)
- **Cor:** Verde
- **Significado:** Resultado muito relevante para sua busca
- **Exemplo:** 100%, 95.3%, 82.1%

### 🟡 Média Relevância (50-79%)
- **Cor:** Amarelo
- **Significado:** Resultado moderadamente relevante
- **Exemplo:** 67.5%, 58.2%, 51.0%

### 🔴 Baixa Relevância (<50%)
- **Cor:** Vermelho
- **Significado:** Resultado pouco relevante (mas ainda contém os termos buscados)
- **Exemplo:** 32.8%, 15.6%, 8.2%

---

## 💻 Implementação Técnica

### 1. Backend - Ativação do Ranking Score

**Arquivo:** `includes/class-searcher.php`

```php
// Habilita o retorno do _rankingScore na API
$search->setShowRankingScore(true);

// ou em array de parâmetros:
'showRankingScore' => true
```

### 2. Frontend - Cálculo e Exibição

**Arquivo:** `public/class-search-shortcode.php`

```php
// Extrai o ranking score do resultado
$ranking_score = isset($hit['_rankingScore']) ? $hit['_rankingScore'] : 0;

// Converte para percentual (0.953 → 95.3%)
$relevance_percent = round($ranking_score * 100, 1);

// Define a classe CSS baseada na relevância
$relevance_class = $ranking_score >= 0.8 ? 'high' 
                 : ($ranking_score >= 0.5 ? 'medium' : 'low');
```

### 3. HTML do Badge

```html
<span class="meilisearch-relevance-badge meilisearch-relevance-high" title="Relevância: 95.3%">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
    </svg>
    95.3%
</span>
```

### 4. CSS - Estilos dos Badges

**Arquivo:** `assets/css/search-results.css`

```css
/* Badge Verde - Alta Relevância */
.meilisearch-relevance-high {
    color: #0d6832;
    background-color: #d1f4dd;
    border: 1px solid #9ae6b4;
}

/* Badge Amarelo - Média Relevância */
.meilisearch-relevance-medium {
    color: #805600;
    background-color: #fef3c7;
    border: 1px solid #fcd34d;
}

/* Badge Vermelho - Baixa Relevância */
.meilisearch-relevance-low {
    color: #991b1b;
    background-color: #fee2e2;
    border: 1px solid #fca5a5;
}
```

---

## 🧪 Como Testar

### 1. Via WP-CLI

```bash
wp --url=http://10.28.13.21:31103/ meilisearch search "orientacoes" --path=/var/www/html --allow-root
```

**Resultado:**
```
+---------+---------+-------------+------------+-----------+----------------------------------------------+
| blog_id | post_id | title       | post_type  | relevance | permalink                                    |
+---------+---------+-------------+------------+-----------+----------------------------------------------+
| 2       | 124     | orientacoes | attachment | 100%      | http://10.28.13.21:31103/labcom/orientacoes/ |
+---------+---------+-------------+------------+-----------+----------------------------------------------+
```

### 2. Via Shortcode WordPress

1. Crie uma página com o shortcode:
   ```
   [meilisearch_search]
   ```

2. Acesse a página e faça uma busca

3. Observe os badges coloridos ao lado de cada título

### 3. Via Demonstração HTML

Abra o arquivo: `/var/www/html/wp-content/plugins/meilisearch/docs/ranking-score-demo.html`

---

## 📱 Responsividade

Os badges são totalmente responsivos e funcionam em:

- ✅ Desktop
- ✅ Tablet
- ✅ Mobile
- ✅ Modo claro e escuro

---

## 🎯 Benefícios para o Usuário

1. **Transparência:** Usuários veem claramente quais resultados são mais relevantes
2. **Confiança:** Badges visuais transmitem credibilidade aos resultados
3. **Eficiência:** Facilita a identificação rápida dos melhores resultados
4. **Feedback Visual:** Cores diferentes facilitam o scan visual da página

---

## 🔧 Arquivos Modificados

```
plugins/meilisearch/
├── includes/
│   ├── class-searcher.php          # Ativado showRankingScore
│   └── class-cli.php                # Adicionada coluna "relevance"
├── public/
│   └── class-search-shortcode.php   # Cálculo e exibição dos badges
├── assets/
│   └── css/
│       └── search-results.css       # Estilos dos badges
└── docs/
    ├── ranking-score-demo.html      # Demonstração visual
    └── RANKING_SCORE.md             # Esta documentação
```

---

## 🎓 Referências

- [Meilisearch Ranking Rules Documentation](https://www.meilisearch.com/docs/learn/core_concepts/relevancy)
- [Meilisearch Ranking Score API](https://www.meilisearch.com/docs/reference/api/search#ranking-score)

---

## ✅ Checklist de Implementação

- [x] Ativar `showRankingScore` nas buscas
- [x] Calcular percentual de relevância
- [x] Adicionar badges HTML com SVG de estrela
- [x] Criar estilos CSS para 3 níveis de relevância
- [x] Adicionar suporte para modo escuro
- [x] Modificar output do WP-CLI
- [x] Criar demonstração visual em HTML
- [x] Criar documentação completa
- [x] Testar em busca real (100% para "orientacoes")

---

**Implementado em:** Janeiro de 2025  
**Versão do Plugin:** 1.0.0  
**Compatível com:** Meilisearch v1.x, WordPress 6.0+
