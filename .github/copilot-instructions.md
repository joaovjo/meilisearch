# Meilisearch WordPress Plugin - AI Coding Agent Instructions

## Project Overview
WordPress plugin integrating Meilisearch search engine. **Early-stage project**: main plugin file exists but core functionality needs implementation.

## Architecture & Components

### Plugin Structure (WordPress Standard)
- **Entry Point**: `meilisearch.php` - Plugin header, initialization hooks
- **Text Domain**: `meilisearch` (used for i18n/l10n)
- **Function/Class Prefix**: `meilisearch_` or `Meilisearch_` (WordPress naming convention)
- **PHP Compatibility**: 5.6+ (strictly enforced by PHPCS)
- **WordPress Version**: 4.6+ minimum

### Core Dependencies
```json
"meilisearch/meilisearch-php": "^1.16"    // Official Meilisearch PHP SDK
"guzzlehttp/guzzle": "^7.10"              // HTTP client
"http-interop/http-factory-guzzle": "^1.0" // PSR-17 factory
```

**Access via Composer**: `require_once __DIR__ . '/vendor/autoload.php';`

## Development Workflow

### Running Tests
```bash
# Setup WordPress test environment (first time only)
bash bin/install-wp-tests.sh wordpress_tests root mysql localhost latest

# Run PHPUnit tests
vendor/bin/phpunit

# Tests use WordPress test suite + WP_Mock for unit testing
# Bootstrap: tests/bootstrap.php loads WP test environment
```

### Code Standards
```bash
# Check WordPress Coding Standards
vendor/bin/phpcs

# Auto-fix violations (when possible)
vendor/bin/phpcbf
```

**Critical**: PHPCS enforces WordPress Coding Standards with PHP 5.6+ compatibility. All code must pass before commits.

### Composer Scripts
```bash
composer makepot    # Generate translation POT file
composer docs       # Generate API docs with phpDocumentor
```

## WordPress Plugin Conventions

### Hook Priority
- Use WordPress action/filter hooks for all integrations
- Standard priority: 10 (default)
- Hook names: prefix with `meilisearch_` (e.g., `meilisearch_after_sync`)

### File Organization Pattern
```
meilisearch.php              # Main plugin file (header + activation)
includes/                    # Core functionality classes
  class-{name}.php          # One class per file
  functions-{feature}.php   # Procedural functions
admin/                      # Admin-specific code
  class-admin.php
public/                     # Public-facing code
languages/                  # Translation files (.pot, .po, .mo)
```

### WordPress Coding Patterns
- **Options API**: `get_option('meilisearch_settings')` for persistent storage
- **Nonces**: Always verify with `wp_verify_nonce()` for forms
- **Escaping**: `esc_html()`, `esc_attr()`, `esc_url()` before output
- **Sanitization**: `sanitize_text_field()`, `sanitize_email()` on input
- **Transients**: Use for caching (e.g., search results): `set_transient('meilisearch_cache_key', $data, HOUR_IN_SECONDS)`

## Meilisearch Integration

### Expected Implementation Pattern
```php
use MeiliSearch\Client;

// Initialize client (typically in plugin initialization)
$client = new Client('http://meilisearch:7700', 'masterKey');

// Index documents (WordPress posts/pages)
$client->index('posts')->addDocuments([...]);

// Search
$results = $client->index('posts')->search('query', [...]);
```

### WordPress Data Sync Strategy
- Hook into `save_post` action to index content on publish
- Hook into `delete_post` action to remove from index
- Provide bulk indexing via WP-CLI command or admin interface
- Consider `wp_insert_post_data` filter for modification before save

## Testing Conventions

### Test Structure
- **Location**: `tests/test-*.php`
- **Base Class**: `WP_UnitTestCase` (from WordPress test suite)
- **Naming**: Test methods must start with `test_`
- **Excluded**: `tests/test-sample.php` (example only)

### GitLab CI Pipeline
- Runs on PHP 7.4, 8.0, 8.2 with MySQL 5.7
- Executes: `phpcs` → `phpunit`
- Requires: `WP_TESTS_DIR` environment variable set

## Critical Configuration Issues

⚠️ **PHPCS Config Needs Update**: `.phpcs.xml.dist` uses placeholder values:
- Line 33: `prefixes` = "my-plugin" → Should be "meilisearch"
- Line 39: `text_domain` = "my-plugin" → Should be "meilisearch"

Update these before adding significant code to avoid mass refactoring.

## Key Files Reference

| File | Purpose |
|------|---------|
| `meilisearch.php` | Plugin entry point (currently minimal) |
| `.phpcs.xml.dist` | Code standards configuration |
| `phpunit.xml.dist` | PHPUnit test configuration |
| `.gitlab-ci.yml` | CI/CD pipeline definition |
| `composer.json` | Dependencies & scripts |
| `bin/install-wp-tests.sh` | Test environment setup script |

## Next Implementation Steps

1. Fix PHPCS configuration (prefixes and text domain)
2. Create `includes/` directory structure
3. Implement Meilisearch client initialization with WordPress options
4. Build post/page indexing hooks
5. Create search interface (potentially override WP default search)
6. Add admin settings page for Meilisearch configuration
7. Write integration tests with actual Meilisearch instance

## Common Pitfalls

- **Don't** use short array syntax `[]` - PHP 5.6 compatibility requires `array()`
- **Don't** use `::class` - not available in PHP 5.6
- **Always** prefix global functions/classes to avoid conflicts
- **Always** use WordPress functions over PHP equivalents (e.g., `wp_remote_get()` vs `file_get_contents()`)
- **Never** output unescaped data - XSS vulnerability
