# Meilisearch Network Search for WordPress

Replace WordPress default search with **Meilisearch** across your entire multisite network with automatic autocomplete.

## Features

- 🌐 **Network-wide search** - Search across all sites in your WordPress multisite network
- ⚡ **Lightning fast** - Powered by Meilisearch's instant search engine
- 🔍 **Automatic autocomplete** - Real-time search suggestions as you type
- 🎯 **Easy configuration** - Network admin settings page with connection testing
- 🔧 **WP-CLI support** - Bulk indexing and management via command line
- 🔄 **Auto-sync** - Content automatically indexed on publish/update
- 🚀 **Modern PHP** - Uses Fiber + ReactPHP for concurrent operations (PHP 8.1+)
- 📊 **Real-time Metrics** - Monitor index statistics and performance without cache
- 🔎 **Index Analyzer** - Automatically detect multiple WordPress networks and their index patterns

## Requirements

- WordPress 6.0+
- WordPress Multisite
- PHP 8.1+
- Self-hosted Meilisearch instance
- Composer (for development)

## Installation

### 1. Install Meilisearch

Follow the [official Meilisearch installation guide](https://www.meilisearch.com/docs/learn/getting_started/installation).

For Docker:
```bash
docker run -d \
  --name meilisearch \
  -p 7700:7700 \
  -e MEILI_MASTER_KEY=your-master-key \
  -v $(pwd)/meili_data:/meili_data \
  getmeili/meilisearch:latest
```

### 2. Install Plugin

1. Clone or download this repository to `wp-content/plugins/meilisearch`
2. Run `composer install --no-dev` in the plugin directory
3. Network activate the plugin in WordPress Network Admin

### 3. Configure

1. Go to **Network Admin → Settings → Meilisearch**
2. Enter your Meilisearch host URL (e.g., `http://localhost:7700`)
3. Enter your Meilisearch master key
4. Enable the plugin
5. Save settings

### 4. Index Content

Use WP-CLI to index all sites:
```bash
wp meilisearch index --network
```

Or index a specific site:
```bash
wp meilisearch index --url=site.example.com
```

## Configuration

The plugin is configured **only** in the Network Admin panel:

- **Network Admin → Meilisearch → Dashboard** - Overview and quick access
- **Network Admin → Meilisearch → Settings** - Configure connection and index format
- **Network Admin → Meilisearch → Metrics** - Real-time index statistics (no cache)
- **Network Admin → Meilisearch → Index Analyzer** - Detect multiple WordPress networks and patterns

Settings are stored network-wide using `get_site_option()`.

### Index Analyzer

The Index Analyzer page helps identify and manage multiple WordPress networks sharing the same Meilisearch server:

- **Automatic Pattern Detection** - Analyzes all indexes to identify naming patterns
- **Network URL Discovery** - Maps index patterns to their WordPress network URLs
- **Multi-Network Support** - Perfect for hosting multiple WordPress installations
- **Real-time Analysis** - No caching, always shows current state

This is especially useful when:
- Managing multiple WordPress Multisite installations
- Each network uses different index naming formats
- You need to identify which indexes belong to which network

## WP-CLI Commands

O plugin inclui comandos WP-CLI completos para gerenciamento. Veja a [documentação completa](docs/WP-CLI.md).

### Comandos Principais

```bash
# Verificar saúde do servidor Meilisearch
wp meilisearch health

# Listar todos os índices
wp meilisearch list_indexes

# Reindexar todos os blogs da rede
wp meilisearch reindex

# Reindexar blog específico
wp meilisearch reindex --blog_id=2

# Buscar posts
wp meilisearch search "termo de busca"

# Ver estatísticas
wp meilisearch stats

# Criar índice manualmente
wp meilisearch create_index 2

# Deletar índice
wp meilisearch delete_index 2 --yes
```

Para mais detalhes, exemplos e casos de uso, consulte [docs/WP-CLI.md](docs/WP-CLI.md).

## Architecture

### Multi-Index Strategy

Each site in the network gets its own Meilisearch index:
- Site 1: `wp_1_posts`
- Site 2: `wp_2_posts`
- Site 3: `wp_3_posts`

### Search Behavior

When a search is performed, the plugin:
1. Intercepts the WordPress search query (`pre_get_posts` hook)
2. Performs a multi-index search across all network sites
3. Returns combined results ordered by relevance
4. Maintains WordPress pagination

### Auto-Indexing

Content is automatically indexed when:
- A post is published or updated (`save_post` hook)
- A post is deleted (`delete_post` hook)
- A new site is created (`wpmu_new_blog` hook)
- A site is deleted (`wp_delete_site` hook)

### Indexed Content Types

The plugin indexes:
- All post types (posts, pages, custom post types)
- Post metadata (title, content, excerpt)
- Taxonomies (categories, tags)
- Author information
- Timestamps for sorting

## Development

### Setup

```bash
# Install dependencies
composer install

# Setup WordPress test environment
bash bin/install-wp-tests.sh wordpress_tests root mysql localhost latest

# Run tests
vendor/bin/phpunit

# Check code standards
vendor/bin/phpcs

# Auto-fix code standards
vendor/bin/phpcbf

# Generate API documentation
bash bin/generate-docs.sh
# Or with composer
composer docs
```

### File Structure

```
meilisearch/
├── includes/
│   ├── class-client.php          # Meilisearch client wrapper
│   ├── class-indexer.php         # Indexing logic
│   ├── class-searcher.php        # Search queries
│   ├── class-autocomplete.php    # Autocomplete API
│   └── class-cli.php             # WP-CLI commands
├── admin/
│   ├── class-dashboard.php        # Dashboard overview
│   ├── class-metrics.php          # Real-time metrics page
│   ├── class-index-analyzer.php   # Network pattern analyzer
│   └── class-network-settings.php # Network admin UI
├── public/
│   └── class-search-override.php  # Frontend search replacement
├── assets/
│   ├── js/autocomplete.js        # Frontend autocomplete
│   └── css/autocomplete.css      # Autocomplete styles
├── docs/
│   └── WP-CLI.md                 # WP-CLI documentation
└── .github/
    └── copilot-instructions.md    # AI agent instructions
```

## How It Works

### 1. Indexing with Fiber

The plugin uses PHP 8.1 Fibers for concurrent indexing:

```php
$fiber = new Fiber(function() use ($site) {
    switch_to_blog($site->blog_id);
    // Index posts for this site
    restore_current_blog();
});
$fiber->start();
```

### 2. Multi-Index Search

Searches across all network indexes simultaneously:

```php
$results = $client->multiSearch([
    ['indexUid' => 'wp_1_posts', 'q' => $query],
    ['indexUid' => 'wp_2_posts', 'q' => $query],
    ['indexUid' => 'wp_3_posts', 'q' => $query],
]);
```

### 3. Autocomplete

Real-time suggestions via REST API:

```
GET /wp-json/meilisearch/v1/autocomplete?q=search+term
```

## Troubleshooting

### Connection Failed

Check that Meilisearch is running:
```bash
curl http://localhost:7700/health
```

### No Search Results

Reindex all content:
```bash
wp meilisearch reindex --network
```

Check index status:
```bash
wp meilisearch status --network
```

### Performance Issues

- Ensure Meilisearch has adequate resources
- Consider increasing batch size in `class-indexer.php`
- Use a dedicated Meilisearch instance for production

## License

GPLv2 or later

## Contributing

Contributions are welcome! Please follow WordPress coding standards and include tests for new features.

## Credits

- Built with [Meilisearch PHP SDK](https://github.com/meilisearch/meilisearch-php)
- Uses [ReactPHP](https://reactphp.org/) for async operations
- Developed for WordPress Multisite networks
