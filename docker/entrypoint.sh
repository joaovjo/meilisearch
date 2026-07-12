#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR="/var/www/html/wp-content/plugins/meilisearch"

if [[ -f "${PLUGIN_DIR}/composer.json" ]]; then
	composer install --working-dir="${PLUGIN_DIR}" --no-interaction
fi

exec /usr/local/bin/docker-entrypoint.sh "$@"
