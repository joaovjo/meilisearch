# AGENTS.md

## Cursor Cloud specific instructions

This repository is the **Meilisearch Network Search** WordPress plugin (a WordPress
**Multisite, network-only** plugin). It is not a standalone app: to run it end-to-end
you need a WordPress Multisite install plus a running Meilisearch server and MySQL/MariaDB.
Standard dev commands live in `README.md` (Development section) and `composer.json` scripts.

The Cloud VM snapshot already has these installed (do **not** reinstall): PHP 8.3 + extensions,
Composer, MariaDB, WP-CLI (`wp`), Subversion (`svn`), the Meilisearch binary, and globally
(via `composer global`) `phpcs` + WordPress Coding Standards + `yoast/phpunit-polyfills`.
The startup update script only runs `composer install`.

### Services and how to (re)start them

None of these auto-start; start what you need each session. Prefer `tmux` for long-running ones.

- **MariaDB** (DB for WordPress + tests). Data dir `/var/lib/mysql` persists; the server process does not.
  Start: `sudo mysqld_safe --datadir=/var/lib/mysql >/tmp/mariadb.log 2>&1 &` then `sudo mysqladmin ping`.
  Root password is `mysql` (host `127.0.0.1`). Databases: `wordpress` (site) and `wordpress_tests` (tests).
- **Meilisearch** (search engine): `meilisearch --http-addr 127.0.0.1:7700 --master-key masterKey123 --env development --db-path /tmp/meili_data`.
  Health check: `curl -s http://127.0.0.1:7700/health`.
- **WordPress dev server**: from `/home/ubuntu/wpsite` run `wp server --host=0.0.0.0 --port=8080`.
  Site URL: `http://localhost:8080` (admin `admin` / `admin123`). The plugin is symlinked from
  this repo into `wp-content/plugins/meilisearch` and is network-activated.

### Meilisearch connection settings

Stored as a network option, not a normal option. Read/set with WP-CLI `eval`:
`wp eval 'var_export(get_site_option("meilisearch_settings"));'`. Current config points at
`http://127.0.0.1:7700` with master key `masterKey123` and `enabled => true`.

### Running the plugin (WP-CLI)

From `/home/ubuntu/wpsite`: `wp meilisearch health`, `wp meilisearch reindex`,
`wp meilisearch search "<query>"`, `wp meilisearch stats`. Reindexing then searching is the
quickest end-to-end check.

### Tests and lint

- **PHPUnit** requires the WP test suite and env vars. The suite lives at `/tmp/wordpress-tests-lib`
  (recreate if `/tmp` was cleared: `bash bin/install-wp-tests.sh wordpress_tests root mysql 127.0.0.1 latest true`
  — the script has CRLF line endings, so run a normalized copy: `tr -d '\r' < bin/install-wp-tests.sh > /tmp/iwt.sh && bash /tmp/iwt.sh ...`).
  Run as **multisite** (this is a multisite plugin) with polyfills path set:
  `WP_TESTS_DIR=/tmp/wordpress-tests-lib WP_TESTS_PHPUNIT_POLYFILLS_PATH=/home/ubuntu/.config/composer/vendor/yoast/phpunit-polyfills WP_MULTISITE=1 vendor/bin/phpunit`.
- **Lint**: the CI-canonical linter is `phpcs` (WordPress Coding Standards), installed globally:
  run `/home/ubuntu/.config/composer/vendor/bin/phpcs` from the repo root (uses `.phpcs.xml.dist`).

### Known pre-existing issues (not environment problems)

- `tests/test-client.php::test_get_index_name` asserts `wp_1_posts` but the WP test suite's table
  prefix is `wptests_`, so it yields `wptests_posts` and this one assertion fails. The other tests pass.
- `mago` (composer dev tool, `composer mago:*` scripts) does **not** run: `mago.toml`'s `[analyzer]`
  section uses field names the pinned mago 1.43.0 binary rejects (config drift). Use `phpcs` for linting.
- The search filters on `post_status`, but the indexer does not register `post_status` as a Meilisearch
  filterable attribute, so `wp meilisearch search` / frontend search return no hits after a fresh reindex.
  Workaround (data layer, no code change): add it after reindexing —
  `curl -H "Authorization: Bearer masterKey123" -H "Content-Type: application/json" -X PUT http://127.0.0.1:7700/indexes/wp_posts/settings/filterable-attributes -d '["post_type","blog_id","author_id","categories","tags","post_status"]'`.
