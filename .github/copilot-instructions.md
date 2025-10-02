# Meilisearch WordPress Plugin - AI Coding Agent Instructions

## Project Overview
WordPress **Multisite Network-level** plugin integrating self-hosted Meilisearch search engine. Replaces WordPress default search with Meilisearch across entire network. **Early-stage project**: main plugin file exists but core functionality needs implementation.

## Architecture & Components

### Plugin Structure (WordPress Multisite Network Plugin)
- **Entry Point**: `meilisearch.php` - Plugin header, network activation hooks
- **Text Domain**: `meilisearch` (used for i18n/l10n)
- **Function/Class Prefix**: `meilisearch_` or `Meilisearch_` (WordPress naming convention)
- **PHP Compatibility**: **8.1+ MINIMUM** (uses Fiber + modern syntax)
- **WordPress Version**: Latest (tested on 6.8.3)
- **Multisite**: Network-only plugin (configured in Network Admin dashboard only)

### Core Dependencies
```json
"meilisearch/meilisearch-php": "^1.16"    // Official Meilisearch PHP SDK
"guzzlehttp/guzzle": "^7.10"              // HTTP client
"http-interop/http-factory-guzzle": "^1.0" // PSR-17 factory
"react/*": "^1.x"                         // ReactPHP for async operations
```

**Modern PHP Features Used**: 
- **Fiber** (PHP 8.1+) for concurrent indexing operations
- **ReactPHP** for async HTTP requests and event loop
- Array syntax `[]`, typed properties, named arguments, etc.

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

**Note**: PHPCS currently configured for PHP 5.6+ but project requires PHP 8.1+. Configuration should be updated to reflect modern PHP standards.

### Composer Scripts
```bash
composer makepot    # Generate translation POT file
composer docs       # Generate API docs with phpDocumentor
```

### WP-CLI Commands
```bash
# Bulk indexing entire network
wp meilisearch index --network

# Index specific site
wp meilisearch index --url=site.example.com

# Reindex all content
wp meilisearch reindex --network

# Clear index
wp meilisearch clear-index

# Check sync status
wp meilisearch status --network
```

## WordPress Multisite Architecture

### Network-Only Configuration
- **Plugin Type**: Network-activated only (must use `is_plugin_active_for_network()`)
- **Settings Location**: Network Admin → Settings → Meilisearch
- **Settings Storage**: `get_site_option()` / `update_site_option()` (NOT regular options)
- **Capability Required**: `manage_network_options`

### File Organization Pattern
```
meilisearch.php              # Main plugin file (network activation)
includes/                    # Core functionality classes
  class-client.php          # Meilisearch client wrapper
  class-indexer.php         # Fiber-based concurrent indexer
  class-searcher.php        # Search query handler
  class-autocomplete.php    # Autocomplete functionality
admin/                      # Network admin settings
  class-network-settings.php # WordPress Settings API for network
public/                     # Frontend search replacement
  class-search-override.php # Replace WP_Query search
languages/                  # Translation files (.pot, .po, .mo)
cli/                        # WP-CLI commands
  class-commands.php
```

### Hook Priority
- Use WordPress action/filter hooks for all integrations
- Standard priority: 10 (default)
- Hook names: prefix with `meilisearch_` (e.g., `meilisearch_after_sync`)
- **Network hooks**: `network_admin_menu`, `update_site_option_{option_name}`

### WordPress Multisite Coding Patterns
- **Network Options API**: `get_site_option('meilisearch_settings')` for network-wide settings
- **Site-specific Options**: `switch_to_blog($blog_id)` when accessing individual site data
- **Nonces**: Always verify with `wp_verify_nonce()` for forms
- **Escaping**: `esc_html()`, `esc_attr()`, `esc_url()` before output
- **Sanitization**: `sanitize_text_field()`, `sanitize_email()` on input
- **Network Transients**: Use for caching: `set_site_transient('meilisearch_cache_key', $data, HOUR_IN_SECONDS)`
- **Iterating Sites**: Use `get_sites()` to loop through network sites

## Meilisearch Integration (Self-Hosted)

### Connection Configuration
```php
use MeiliSearch\Client;

// Get network settings
$settings = get_site_option('meilisearch_settings', [
    'host' => 'http://localhost:7700',
    'master_key' => '',
]);

$client = new Client($settings['host'], $settings['master_key']);
```

### Multi-Index Strategy (One Index Per Site)
```php
// Index naming convention: wp_{blog_id}_posts
// Example: wp_1_posts, wp_2_posts, wp_3_posts

foreach (get_sites() as $site) {
    $index_name = "wp_{$site->blog_id}_posts";
    $client->index($index_name)->addDocuments([...]);
}
```

### Global Search Across All Indexes
```php
// Meilisearch multi-index search
$indexes = ['wp_1_posts', 'wp_2_posts', 'wp_3_posts'];
$results = $client->multiSearch([
    ['indexUid' => 'wp_1_posts', 'q' => $query],
    ['indexUid' => 'wp_2_posts', 'q' => $query],
    ['indexUid' => 'wp_3_posts', 'q' => $query],
]);
```

### Async Indexing with Fiber + ReactPHP
```php
use React\EventLoop\Loop;
use Fiber;

// Concurrent indexing across sites
$loop = Loop::get();
$fibers = [];

foreach (get_sites() as $site) {
    $fibers[] = new Fiber(function() use ($site) {
        switch_to_blog($site->blog_id);
        // Index posts for this site
        $posts = get_posts(['numberposts' => -1]);
        // ... index to Meilisearch
        restore_current_blog();
    });
}

// Resume fibers concurrently
foreach ($fibers as $fiber) {
    $fiber->start();
}
```

### WordPress Data Sync Strategy (Network-Wide)
- Hook into `save_post` action to index content on publish (all sites)
- Hook into `delete_post` action to remove from index
- Hook into `wpmu_new_blog` action to create index for new sites
- Hook into `wp_delete_site` action to remove index when site deleted
- Provide bulk indexing via **WP-CLI commands** with `--network` flag
- Index **all post types**: posts, pages, custom post types
- Index **all taxonomies**: categories, tags, custom taxonomies
- Index **comments** with post association
- Index **user profiles** (authors)
- Index **attachments/media** metadata

## Testing Conventions

### Test Structure
- **Location**: `tests/test-*.php`
- **Base Class**: `WP_UnitTestCase` (from WordPress test suite)
- **Naming**: Test methods must start with `test_`
- **Excluded**: `tests/test-sample.php` (example only)

### GitLab CI Pipeline
- Should run on PHP 8.1, 8.2, 8.3 (update `.gitlab-ci.yml`)
- Executes: `phpcs` → `phpunit`
- Requires: `WP_TESTS_DIR` environment variable set
- Consider adding Meilisearch service container for integration tests

## Search Replacement Implementation

### Override WordPress Default Search
```php
// Hook into pre_get_posts to replace WP_Query search
add_action('pre_get_posts', function($query) {
    if (!is_admin() && $query->is_main_query() && $query->is_search()) {
        // Perform Meilisearch query instead
        $search_term = $query->get('s');
        $results = meilisearch_search($search_term);
        
        // Override query with Meilisearch results
        $query->set('post__in', $results['post_ids']);
        $query->set('orderby', 'post__in');
    }
});
```

### Automatic Autocomplete
```php
// REST API endpoint for autocomplete
add_action('rest_api_init', function() {
    register_rest_route('meilisearch/v1', '/autocomplete', [
        'methods' => 'GET',
        'callback' => 'meilisearch_autocomplete_callback',
        'permission_callback' => '__return_true',
    ]);
});

// Frontend JavaScript automatically injected
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script('meilisearch-autocomplete', 
        plugin_dir_url(__FILE__) . 'assets/js/autocomplete.js',
        ['jquery'], '1.0', true
    );
    wp_localize_script('meilisearch-autocomplete', 'meilisearchConfig', [
        'apiUrl' => rest_url('meilisearch/v1/autocomplete'),
        'nonce' => wp_create_nonce('wp_rest'),
    ]);
});
```

## Critical Configuration Requirements

⚠️ **Configuration Updates Needed**:

1. **PHPCS Config** (`.phpcs.xml.dist`):
   - Line 12: `testVersion` = "5.6-" → Should be "8.1-"
   - Line 33: `prefixes` = "my-plugin" → Should be "meilisearch"
   - Line 39: `text_domain` = "my-plugin" → Should be "meilisearch"

2. **GitLab CI** (`.gitlab-ci.yml`):
   - Remove PHP 7.4, 8.0 jobs
   - Add PHP 8.1, 8.3 jobs
   - Add Meilisearch service container

3. **Composer** (`composer.json`):
   - Add `"php": ">=8.1"` requirement
   - Add ReactPHP dependencies

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

1. **Configuration Updates**:
   - Fix PHPCS config (PHP 8.1+, prefixes, text domain)
   - Update GitLab CI for PHP 8.1+
   - Add ReactPHP dependencies to composer.json

2. **Core Infrastructure**:
   - Create `includes/class-client.php` - Meilisearch client wrapper
   - Create `includes/class-indexer.php` - Fiber-based concurrent indexer
   - Create `includes/class-searcher.php` - Search query handler

3. **Network Admin Interface**:
   - Create `admin/class-network-settings.php`
   - Use WordPress Settings API with `network_admin_menu`
   - Settings: Meilisearch host, master key, index prefix

4. **Search Replacement**:
   - Create `public/class-search-override.php`
   - Hook into `pre_get_posts` to replace WP_Query
   - Implement multi-index search across network

5. **Autocomplete**:
   - Create REST API endpoint (`/wp-json/meilisearch/v1/autocomplete`)
   - Create `assets/js/autocomplete.js` with search suggestions
   - Auto-inject script on frontend

6. **Indexing Hooks**:
   - `save_post` for real-time indexing
   - `delete_post` for removal
   - `wpmu_new_blog` for new site index creation
   - `wp_delete_site` for index cleanup

7. **WP-CLI Commands**:
   - Create `cli/class-commands.php`
   - Commands: `index`, `reindex`, `clear-index`, `status`
   - Support `--network` flag

8. **Testing**:
   - Integration tests with Meilisearch container
   - Test multisite scenarios
   - Test Fiber concurrency

## Common Pitfalls

- **Don't** use `get_option()` - use `get_site_option()` for network settings
- **Don't** forget `switch_to_blog()` / `restore_current_blog()` when iterating sites
- **Don't** block the event loop in ReactPHP - use async operations
- **Always** handle Fiber suspension points properly (avoid infinite loops)
- **Always** prefix global functions/classes to avoid conflicts
- **Always** check `is_multisite()` and network admin capabilities
- **Never** output unescaped data - XSS vulnerability
- **Never** index sensitive data without filtering (passwords, tokens, etc.)

## WordPress Multisite Context Switching

```php
// Correct pattern for indexing across network
foreach (get_sites(['number' => 9999]) as $site) {
    switch_to_blog($site->blog_id);
    
    // Now in context of specific site
    $posts = get_posts(['numberposts' => -1]);
    $index_name = "wp_{$site->blog_id}_posts";
    
    // Index to Meilisearch
    $client->index($index_name)->addDocuments($posts);
    
    restore_current_blog(); // CRITICAL: always restore
}
```

## ReactPHP + Fiber Integration Example

```php
use React\EventLoop\Loop;
use React\Http\Browser;

$loop = Loop::get();
$browser = new Browser($loop);

$fiber = new Fiber(function() use ($browser, $client) {
    // Async HTTP request to Meilisearch
    $promise = $browser->post(
        'http://localhost:7700/indexes/wp_1_posts/documents',
        ['Content-Type' => 'application/json'],
        json_encode($documents)
    );
    
    // Suspend fiber until promise resolves
    $response = Fiber::suspend($promise);
    
    return $response;
});

$fiber->start();
```
