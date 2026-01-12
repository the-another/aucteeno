# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Aucteeno is a WordPress plugin that extends WooCommerce to provide comprehensive auction and item (lot) management functionality. It transforms WooCommerce into a powerful auction management system with high-performance database tables, REST API, and Gutenberg blocks.

**Critical Dependencies:**
- WordPress 6.9+
- WooCommerce 10.4.3+ (REQUIRED - entire plugin is WooCommerce-based)
- PHP 8.3+

## Development Commands

### PHP/Composer Commands (via Docker)

All commands run inside Docker containers. The Makefile handles Docker image building and volume mounting.

```bash
# Install dependencies (production)
make install

# Install dependencies (with dev dependencies)
make install-dev

# Run PHPUnit tests
make test

# Run PHPCS linter
make lint

# Auto-fix code style issues
make format

# Build Mozart dependencies (namespace prefixing)
make build

# Update composer dependencies
make update

# Clean build artifacts
make clean

# Run all checks (install-dev, build, lint, test)
make all

# Package plugin for distribution
make release
```

### JavaScript/npm Commands

```bash
# Build Gutenberg blocks for production
npm run build

# Start development mode with file watching
npm start

# Lint JavaScript files
npm run lint:js

# Lint CSS files
npm run lint:css

# Format JavaScript files
npm run format
```

### Running Tests

```bash
# Run all PHPUnit tests
make test

# Or directly (if you have composer installed locally)
composer test
# or
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Query_Orderer_Test.php
```

## Architecture Overview

### Container Pattern with Dependency Injection

Aucteeno uses a **dependency injection container** pattern inspired by WooCommerce architecture. This is the MOST IMPORTANT architectural pattern in the codebase.

**Key Classes:**
- `Container` (`includes/container/class-container.php`) - Manages service lifecycle with lazy loading and singleton support
- `Hook_Manager` (`includes/container/class-hook-manager.php`) - Tracks WordPress hooks for centralized management and deregistration

**Critical Rules:**

1. **ALWAYS use Hook_Manager for hook registration:**
   ```php
   // ✅ CORRECT
   $this->hook_manager->register_action('hook_name', [$this, 'callback']);

   // ❌ NEVER DO THIS in container-managed classes
   add_action('hook_name', [$this, 'callback']);
   ```

2. **ALWAYS inject Hook_Manager via constructor:**
   ```php
   public function __construct(Hook_Manager $hook_manager) {
       $this->hook_manager = $hook_manager;
   }
   ```

3. **Service registration pattern:**
   ```php
   $container->register(
       'service_key',
       function (Container $c) {
           return new Service_Class($c->get_hook_manager());
       },
       true // Singleton
   );

   // Initialize service (triggers lazy instantiation)
   $container->get('service_key')->init();
   ```

4. **NEVER bypass the container** - Always access services through `Container::get_instance()->get('service_key')`

See `.cursors/rules` for complete container pattern documentation.

### Plugin Initialization Flow

1. **Bootstrap** (`aucteeno.php`): Hooks into `before_woocommerce_init` → calls `Aucteeno::get_instance()->start()`
2. **Main Class** (`includes/class-aucteeno.php`):
   - Initializes Container
   - Runs database migrations
   - Registers taxonomies, product types, datastores, admin components, REST API
3. **Service Registration**: Services registered in methods like `register_admin_fields()`, `register_product_types()`, etc.

### High Performance Storage (HPS) System

The HPS system maintains synchronized copies of critical auction/item data in dedicated database tables for fast queries without complex joins.

**Custom Tables:**
- `wp_aucteeno_auctions` - Denormalized auction data (bidding status, times, locations)
- `wp_aucteeno_items` - Denormalized item data (lot numbers, parent auction references, inherited data)

**Synchronization:**
- `HPS_Sync_Handler` (`includes/services/class-hps-sync-handler.php`) orchestrates sync operations
- `HPS_Sync_Auction` and `HPS_Sync_Item` handle table-specific sync logic
- Automatic sync on product save/trash/restore/delete via WordPress hooks

**Query Integration:**
- `Query_Orderer` (`includes/database/class-query-orderer.php`) intercepts WordPress queries and applies HPS table ordering
- Enables efficient filtering by status, dates, and locations with optimized indexes

### Custom Product Types

Aucteeno extends WooCommerce with two custom product types:

1. **`aucteeno-ext-auction`** - Auction products (extends `WC_Product_External`)
   - Class: `Product_Auction` (`includes/product-types/class-product-auction.php`)
   - Datastore: `Datastore_Auction` (`includes/product-types/datastores/class-datastore-auction.php`)
   - Custom fields: bidding times, location, terms, conditions

2. **`aucteeno-ext-item`** - Item/Lot products (extends `WC_Product_External`)
   - Class: `Product_Item` (`includes/product-types/class-product-item.php`)
   - Datastore: `Datastore_Item` (`includes/product-types/datastores/class-datastore-item.php`)
   - Custom fields: lot number, parent auction, item-specific bidding data

**Important:** Items have parent-child relationships with auctions. Items inherit location and bidding status from parent auctions if not explicitly set.

### Gutenberg Blocks

Block architecture follows WordPress block development patterns:

**Block Types:**
- **Query Loop** (`blocks/query-loop/`) - Lists auctions/items with pagination
- **Card** (`blocks/card/`) - Individual auction/item card container
- **Field Blocks** (`blocks/field-*/`) - Individual data fields (title, image, countdown, location, etc.)
- **Pagination** (`blocks/pagination/`) - Pagination controls

**Build System:**
- Uses `@wordpress/scripts` with custom webpack config (`webpack.config.js`)
- Separate entry points for editor and view scripts
- Outputs to `dist/blocks/`

### REST API

**Namespace:** `aucteeno/v1`

**Endpoints:**
- `GET/POST/PUT/DELETE /auctions` and `/auctions/{id}` - Auction management
- `GET/POST/PUT/DELETE /items` and `/items/{id}` - Item management
- `GET /locations` - Location taxonomy terms

**Controller:** `REST_Controller` (`includes/rest-api/class-rest-controller.php`)

### Taxonomies

Three custom taxonomies for organizing auctions and items:

1. **`aucteeno-auction-type`** - Auction categorization
2. **`aucteeno-auction-location`** - Hierarchical location taxonomy (country → subdivision → city)
3. **`aucteeno-auction-bidding-status`** - Bidding status tracking (publish, schedule, expire)

Location taxonomy uses two-letter country codes and `COUNTRY:SUBDIVISION` format for subdivisions.

### Permalinks

Custom permalink structure for clean URLs:

- Auctions: `/auction/{slug}/`
- Items: `/auction/{auction-slug}/item/{item-slug}/`

**Handler:** `Auction_Item_Permalinks` (`includes/permalinks/class-auction-item-permalinks.php`)

Items validate that their parent auction slug matches the URL. Mismatches trigger 404s.

## Code Structure

```
includes/
├── admin/                  # WooCommerce admin integration
│   ├── class-custom-fields-auction.php
│   ├── class-custom-fields-item.php
│   ├── class-item-parent-relationship.php
│   ├── class-product-tab-aucteeno.php
│   ├── class-product-type-filter.php
│   ├── class-disable-reviews-comments.php
│   └── class-settings.php
├── container/              # Dependency injection container
│   ├── class-container.php
│   └── class-hook-manager.php
├── database/               # HPS tables and database operations
│   ├── class-database.php
│   ├── class-database-auctions.php
│   ├── class-database-items.php
│   ├── class-query-orderer.php
│   ├── class-status-mapper.php
│   ├── class-lot-sort-helper.php
│   └── class-lot-sort-backfill.php
├── product-types/          # Custom WooCommerce product types
│   ├── class-product-auction.php
│   ├── class-product-item.php
│   ├── class-product-type-register-auction.php
│   ├── class-product-type-register-item.php
│   └── datastores/
│       ├── class-datastore-auction.php
│       └── class-datastore-item.php
├── services/               # HPS sync services
│   ├── class-hps-sync-handler.php
│   ├── class-hps-sync-auction.php
│   └── class-hps-sync-item.php
├── rest-api/               # REST API endpoints
│   └── class-rest-controller.php
├── taxonomies/             # Custom taxonomies
│   ├── class-taxonomy-auction-type.php
│   ├── class-taxonomy-location.php
│   └── class-taxonomy-auction-bidding-status.php
├── permalinks/             # Custom permalink structure
│   └── class-auction-item-permalinks.php
├── installer/              # Plugin installation/uninstallation
│   ├── class-install.php
│   └── class-uninstall.php
├── helpers/                # Utility classes
├── class-aucteeno.php      # Main plugin class
├── class-blocks.php        # Gutenberg block registration
├── class-breadcrumbs.php   # WooCommerce breadcrumb integration
└── class-fragment-renderer.php # HTML fragment rendering for AJAX

blocks/                     # Gutenberg blocks
├── query-loop/
├── card/
├── field-image/
├── field-title/
├── field-countdown/
├── field-location/
├── field-current-bid/
├── field-reserve-price/
├── field-lot-number/
├── field-bidding-status/
└── pagination/

tests/                      # PHPUnit tests
```

## Coding Standards

- **PHP Standards:** WordPress Coding Standards + Automattic VIP Coding Standards
- **PHP Version:** 8.3+ (use modern PHP features: typed properties, return types, union types, etc.)
- **Linting:** PHPCS configuration in `.phpcs.xml.dist`
- **WooCommerce Integration:** Always check WooCommerce 10.4.3 compatibility
- **Namespace:** `TheAnother\Plugin\Aucteeno`

## Database Schema

### Auctions Table (`wp_aucteeno_auctions`)

Key columns:
- `auction_id` - WordPress post ID reference
- `bidding_status` - Numeric status (10=publish, 20=schedule, 30=expire)
- `bidding_starts_at`, `bidding_ends_at` - Unix timestamps
- `location_country`, `location_subdivision`, `location_city` - Geographic data
- Composite indexes for common query patterns

### Items Table (`wp_aucteeno_items`)

Key columns:
- `item_id` - WordPress post ID reference
- `auction_id` - Parent auction reference
- `lot_no` - Lot number string
- `lot_sort_key` - Numeric sorting key
- Inherits `bidding_status` and location from parent auction if not set

## Important Development Notes

1. **WooCommerce Dependency:** This plugin is entirely WooCommerce-based. Reference WooCommerce 10.4.3 codebase for patterns and compatibility.

2. **Container-Managed Classes:** When creating new services, ALWAYS use the container pattern with Hook_Manager injection.

3. **Database Sync:** When modifying product meta, the HPS tables automatically sync via `HPS_Sync_Handler`. Test that sync works correctly.

4. **Lot Sorting:** Lot numbers use `Lot_Sort_Helper` to generate numeric sort keys from alphanumeric lot numbers (e.g., "LOT-123" → 123).

5. **Block Development:** When adding/modifying blocks, update `webpack.config.js` entry points and run `npm run build`.

6. **Testing:** PHPUnit tests use Brain Monkey for mocking WordPress/WooCommerce functions. Tests run in isolation without WordPress/WooCommerce loaded.

7. **Permalinks:** After changing permalink structure, flush rewrite rules by deactivating/reactivating plugin or visiting Settings → Permalinks.

8. **Mozart:** Dependencies in `composer.json` marked for Mozart get namespaced and moved to `/dependencies/` to avoid conflicts.