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

- **Tests use [Pest 4.x](https://pestphp.com/)** — pure unit tests with lightweight WP function
  stubs (see `tests/bootstrap.php`); no WordPress install, DB, or WP test suite is needed.
  Run with `composer test` or `vendor/bin/pest`. Pest 4 requires **PHP 8.3+** even though the
  plugin supports PHP 8.1+, and it pulls PHPUnit 12; `10up/wp_mock` was removed because it
  conflicts with PHPUnit 12. (The old `bin/install-wp-tests.sh` WP integration flow is no longer used.)
- **Lint**: `phpcs` (WordPress Coding Standards) is the CI-canonical linter, installed globally —
  run `/home/ubuntu/.config/composer/vendor/bin/phpcs` from the repo root (uses `.phpcs.xml.dist`).
  `mago` (`composer mago:lint` / `mago:analyze` / `mago:fmt:check`) also works. `mago` uses baseline
  files (`linter-baseline.toml`, `analyzer-baseline.toml`) that are **gitignored**; if they are missing,
  regenerate them with `composer mago:lint:baseline` and `composer mago:analyze:baseline` so the
  pre-existing findings stay filtered.

### Meilisearch versions

Both the server (1.49.0) and the PHP SDK `meilisearch/meilisearch-php` (v1.16.1, pinned `^1.16.1`) are
the latest **stable** releases. SDK v2 is beta-only and intentionally not adopted.
