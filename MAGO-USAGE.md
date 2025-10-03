# Mago - Guia de Uso

Este documento descreve como usar o Mago para formatação, linting e análise estática do código PHP do plugin Meilisearch.

## 📚 Documentação Oficial

- **Site principal**: https://mago.carthage.software/
- **Configuração**: https://mago.carthage.software/guide/configuration
- **CLI**: https://mago.carthage.software/fundamentals/command-line-interface

---

## 🚀 Scripts Composer Disponíveis

### Formatação de Código

#### `composer mago:fmt`
Formata todo o código PHP automaticamente.
```bash
composer mago:fmt
```

#### `composer mago:fmt:check`
Verifica se o código está formatado corretamente (ideal para CI/CD).
```bash
composer mago:fmt:check
```

#### `composer mago:fmt:dry`
Mostra um diff das mudanças que seriam feitas sem modificar os arquivos.
```bash
composer mago:fmt:dry
```

---

### Linting (Análise de Estilo)

#### `composer mago:lint`
Executa o linter para verificar problemas de estilo e boas práticas.
```bash
composer mago:lint
```

#### `composer mago:lint:fix`
Executa o linter e corrige automaticamente os problemas que podem ser resolvidos.
```bash
composer mago:lint:fix
```

#### `composer mago:lint:rules`
Lista todas as regras de linting ativas e suas descrições.
```bash
composer mago:lint:rules
```

---

### Análise Estática

#### `composer mago:analyze`
Executa análise estática completa do código.
```bash
composer mago:analyze
```

#### `composer mago:analyze:baseline`
Gera um arquivo baseline para ignorar issues existentes.
```bash
composer mago:analyze:baseline
```

---

### Scripts Combinados

#### `composer mago:all`
Executa formatação, linting e análise em sequência.
```bash
composer mago:all
```

#### `composer mago:check`
Verifica formatação (sem modificar), executa lint e análise (ideal para CI/CD).
```bash
composer mago:check
```

---

### Utilitários

#### `composer mago:config`
Exibe a configuração final mesclada do Mago.
```bash
composer mago:config
```

---

## 🎯 Comandos Diretos do Mago

### Comandos Principais

```bash
# Formatar código
vendor/bin/mago fmt

# Lint
vendor/bin/mago lint

# Análise estática
vendor/bin/mago analyze

# Ver AST de um arquivo
vendor/bin/mago ast includes/class-client.php
```

### Opções Úteis

```bash
# Formatar arquivo específico
vendor/bin/mago fmt includes/class-client.php

# Lint com modo pedante (todas as regras)
vendor/bin/mago lint --pedantic

# Explicar uma regra específica
vendor/bin/mago lint --explain cyclomatic-complexity

# Analisar e gerar baseline
vendor/bin/mago analyze --generate-baseline analyzer-baseline.toml

# Usar baseline existente
vendor/bin/mago analyze --baseline analyzer-baseline.toml

# Verificar apenas semântica (sem regras de lint)
vendor/bin/mago lint --semantics
```

### Opções Globais

```bash
# Usar configuração personalizada
vendor/bin/mago --config custom.toml fmt

# Sobrescrever versão PHP
vendor/bin/mago --php-version 8.3 lint

# Desabilitar cores
vendor/bin/mago --colors never lint

# Usar mais threads
vendor/bin/mago --threads 8 analyze
```

---

## ⚙️ Configuração (mago.toml)

A configuração do Mago está no arquivo `mago.toml` na raiz do plugin. Principais seções:

### `[source]`
Define quais arquivos e diretórios serão processados.

### `[formatter]`
Configurações de estilo de código (indentação, espaços, quebras de linha, etc.).

### `[linter]`
Configurações de regras de linting e integrações (WordPress, PHPUnit).

### `[linter.rules]`
Configuração individual de cada regra de linting.

### `[analyzer]`
Configurações de análise estática e categorias de issues.

---

## 🔧 Integração com CI/CD

Para usar em pipelines de CI/CD, use os comandos de verificação:

```yaml
# GitHub Actions / GitLab CI
script:
  - composer install
  - composer mago:check
```

Ou comandos individuais:

```yaml
script:
  - vendor/bin/mago fmt --check
  - vendor/bin/mago lint
  - vendor/bin/mago analyze
```

---

## 📋 Baselines

Baselines permitem ignorar issues existentes enquanto previne novos:

```bash
# Gerar baseline do linter
vendor/bin/mago lint --generate-baseline linter-baseline.toml

# Gerar baseline do analyzer
vendor/bin/mago analyze --generate-baseline analyzer-baseline.toml
```

Configure no `mago.toml`:

```toml
[linter]
baseline = "linter-baseline.toml"

[analyzer]
baseline = "analyzer-baseline.toml"
```

---

## 🎨 Integração com VSCode

Instale a extensão do Mago para VSCode:
- https://mago.carthage.software/recipes/vscode

Configuração recomendada (`.vscode/settings.json`):

```json
{
  "mago.enable": true,
  "mago.format.enable": true,
  "mago.lint.enable": true,
  "mago.analyze.enable": true,
  "[php]": {
    "editor.defaultFormatter": "mago.mago-vscode"
  }
}
```

---

## 📖 Recursos Adicionais

- **Rules & Categories**: https://mago.carthage.software/tools/linter/rules-and-categories
- **Suppressing Issues**: https://mago.carthage.software/fundamentals/suppressing-issues
- **Pager Support**: https://mago.carthage.software/fundamentals/pager-support
- **GitHub Actions**: https://mago.carthage.software/recipes/github-actions

---

## 💡 Dicas

1. **Sempre rode `composer mago:fmt` antes de commitar** para manter o código formatado.
2. **Use `composer mago:all`** durante o desenvolvimento para verificar tudo de uma vez.
3. **Configure baselines** para projetos existentes com muitos issues.
4. **Use `--explain`** para entender melhor cada regra de linting.
5. **Rode `composer mago:check`** no CI/CD** para validar o código sem modificá-lo.

---

## 🐛 Troubleshooting

### "Too few arguments" ou erros de stack
Ajuste o `stack-size` no `mago.toml`:
```toml
stack-size = 8388608  # 8 MB
```

### Performance lenta
Ajuste o número de threads:
```toml
threads = 8
```

### Muitos issues
Use baselines para projetos existentes:
```bash
composer mago:analyze:baseline
```

---

**Última atualização**: 2025-10-03
