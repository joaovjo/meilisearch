#!/bin/bash

# Script para gerar documentação do plugin Meilisearch
# Baixa o phpDocumentor se necessário e gera a documentação

set -e

PHPDOC_VERSION="v3.8.1"
PHPDOC_PHAR="tools/phpDocumentor.phar"
PHPDOC_URL="https://github.com/phpDocumentor/phpDocumentor/releases/download/${PHPDOC_VERSION}/phpDocumentor.phar"

# Cores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Meilisearch Documentation Generator${NC}"
echo "======================================"

# Verificar se o diretório tools existe
if [ ! -d "tools" ]; then
    echo -e "${YELLOW}Creating tools directory...${NC}"
    mkdir -p tools
fi

# Baixar phpDocumentor se não existir
if [ ! -f "$PHPDOC_PHAR" ]; then
    echo -e "${YELLOW}Downloading phpDocumentor ${PHPDOC_VERSION}...${NC}"
    curl -L "$PHPDOC_URL" -o "$PHPDOC_PHAR"
    chmod +x "$PHPDOC_PHAR"
    echo -e "${GREEN}✓ phpDocumentor downloaded${NC}"
else
    echo -e "${GREEN}✓ phpDocumentor found${NC}"
fi

# Limpar documentação anterior
if [ -d "build/docs" ]; then
    echo -e "${YELLOW}Cleaning previous documentation...${NC}"
    rm -rf build/docs
    rm -rf build/api
fi

# Gerar documentação
echo -e "${YELLOW}Generating documentation...${NC}"
php "$PHPDOC_PHAR" --config=phpdoc.xml

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Documentation generated successfully!${NC}"
    echo ""
    echo "Documentation available at: build/docs/index.html"
    echo ""
    echo "To view locally, run:"
    echo "  cd build/docs && php -S localhost:8080"
else
    echo -e "${RED}✗ Error generating documentation${NC}"
    exit 1
fi
