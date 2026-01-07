# Aucteeno

Aucteeno is a WordPress plugin that extends WooCommerce to provide comprehensive auction and item management functionality. It enables businesses to create, manage, and display auctions with associated items (lots) in a structured, high-performance environment.

## What Aucteeno Does

Aucteeno transforms WooCommerce into a powerful auction management system by providing:

### Core Functionality

- **Auction Management**: Create and manage auction events with custom metadata including bidding times, locations, terms, and conditions
- **Item (Lot) Management**: Organize items within auctions with lot numbers, descriptions, bidding information, and sale tracking
- **Location-Based Organization**: Structure auctions and items by geographic location using hierarchical location taxonomies (country, subdivision, city)
- **Bidding Status Tracking**: Track auction and item statuses (upcoming, running, expired) through custom taxonomies
- **Custom Permalinks**: Clean URL structures for auctions (`/auction/{slug}/`) and items (`/auction/{auction-slug}/item/{item-slug}/`)
- **REST API**: Full REST API endpoints for programmatic access to auction and item data
- **Gutenberg Blocks**: Ready-to-use blocks for displaying auction listings, item listings, and individual cards
- **High-Performance Queries**: Optimized database tables for fast filtering, sorting, and querying of auctions and items

### Key Features

- **Parent-Child Relationships**: Items are linked to their parent auctions, maintaining hierarchical organization
- **Time-Based Bidding**: Support for bidding start and end times with timezone handling
- **Location Hierarchies**: Multi-level location organization (country → subdivision → city)
- **Custom Product Types**: Extends WooCommerce with two new product types: `aucteeno-ext-auction` and `aucteeno-ext-item`
- **Admin Interface**: Custom product tabs and fields for managing auction and item data
- **Fragment Rendering**: HTML fragment rendering for AJAX-powered listings
- **Query Optimization**: Custom query ordering system for efficient database queries

## Requirements

- WordPress 6.9 or higher
- WooCommerce (latest version recommended)
- PHP 8.3 or higher

## Installation

1. Upload the plugin files to `/wp-content/plugins/aucteeno/`
2. Activate the plugin through the WordPress admin panel
3. The plugin will automatically create necessary database tables on activation

## Hooks Reference

Aucteeno provides extensive WordPress hooks for developers to extend and customize functionality. All hooks are managed through the plugin's `Hook_Manager` class for centralized tracking and easy deregistration.

### Product Tab Hooks

These hooks allow customization of the Aucteeno product data tab in the WooCommerce admin:

#### Filters

- **`aucteeno_product_panel_tabs`** - Modify or add tabs within the Aucteeno product panel
  - Parameters: `$tabs` (array), `$post_id` (int), `$post_type` (string)
  - Returns: Array of tab keys and labels
  
- **`aucteeno_product_tab_classes`** - Modify CSS classes applied to tab links
  - Parameters: `$classes` (array)
  - Returns: Array of CSS class strings

#### Actions

- **`aucteeno_product_tab_link`** - Render content for the "Link" tab
  - Parameters: `$post_id` (int), `$post_type` (string)
  
- **`aucteeno_product_tab_location`** - Render content for the "Location" tab
  - Parameters: `$post_id` (int), `$post_type` (string)
  
- **`aucteeno_product_tab_times`** - Render content for the "Times" tab
  - Parameters: `$post_id` (int), `$post_type` (string)
  
- **`aucteeno_product_tab_details`** - Render content for the "Details" tab
  - Parameters: `$post_id` (int), `$post_type` (string)
  
- **`aucteeno_product_tab_{$tab_key}`** - Dynamic action hook for custom tabs
  - Parameters: `$post_id` (int), `$post_type` (string)
  - Replace `{$tab_key}` with your custom tab key

### WooCommerce Integration Hooks

- **`woocommerce_product_class`** - Filter product class names for custom product types
- **`woocommerce_data_stores`** - Register custom datastores for auction and item product types
- **`woocommerce_product_data_tabs`** - Add the Aucteeno tab to product data tabs
- **`woocommerce_product_data_panels`** - Render the Aucteeno product panel
- **`woocommerce_process_product_meta`** - Save custom product meta data

### Product Type Registration Hooks

- **`product_type_selector`** - Add auction and item product types to the selector
- **`woocommerce_product_type_options`** - Add product type options
- **`woocommerce_product_after_variable_attributes`** - Handle variable product attributes
- **`woocommerce_save_product_variation`** - Save product variation data

### Permalink Hooks

- **`query_vars`** - Register custom query variables for auction and item slugs
- **`post_type_link`** - Filter permalink generation for auction and item products
- **`pre_handle_404`** - Prevent premature 404 errors for custom permalinks
- **`template_redirect`** - Validate item URLs and enforce 404 on mismatch

### Query and Ordering Hooks

- **`pre_get_posts`** - Apply custom ordering to queries (priority 20)
- **`found_posts`** - Adjust found posts count for custom queries

### Admin Hooks

- **`admin_menu`** - Add settings page to admin menu
- **`admin_init`** - Register settings and handle admin initialization
- **`save_post_product`** - Handle product save operations
- **`wp_trash_post`** - Handle product trashing
- **`untrash_post`** - Handle product restoration
- **`before_delete_post`** - Handle product deletion

### REST API Hooks

The plugin registers REST API routes under the `aucteeno/v1` namespace:

- `GET /wp-json/aucteeno/v1/auctions` - List auctions
- `GET /wp-json/aucteeno/v1/auctions/{id}` - Get single auction
- `POST /wp-json/aucteeno/v1/auctions` - Create auction
- `PUT /wp-json/aucteeno/v1/auctions/{id}` - Update auction
- `DELETE /wp-json/aucteeno/v1/auctions/{id}` - Delete auction
- `GET /wp-json/aucteeno/v1/items` - List items
- `GET /wp-json/aucteeno/v1/items/{id}` - Get single item
- `POST /wp-json/aucteeno/v1/items` - Create item
- `PUT /wp-json/aucteeno/v1/items/{id}` - Update item
- `DELETE /wp-json/aucteeno/v1/items/{id}` - Delete item
- `GET /wp-json/aucteeno/v1/locations` - Get location terms

### Breadcrumb Hooks

- **`woocommerce_breadcrumb_defaults`** - Filter breadcrumb defaults for auction and item products

### Block Registration Hooks

- **`block_categories_all`** - Register the Aucteeno block category

## HPS (High Performance Storage) System

Aucteeno implements a High Performance Storage (HPS) system to optimize database queries for auctions and items. This system maintains synchronized copies of critical product data in dedicated database tables, enabling fast queries without complex joins across WordPress post and meta tables.

### How HPS Works

The HPS system automatically syncs auction and item product data to two custom database tables:

1. **`wp_aucteeno_auctions`** - Stores auction-specific data
2. **`wp_aucteeno_items`** - Stores item-specific data

### Data Synchronization

The HPS system automatically synchronizes data when:

- **Product Created/Updated**: When an auction or item product is saved, its data is automatically synced to the HPS table
- **Product Trashed**: When a product is moved to trash, it's removed from the HPS table
- **Product Restored**: When a product is restored from trash, it's re-added to the HPS table
- **Product Deleted**: When a product is permanently deleted, it's removed from the HPS table

### HPS Table Structure

#### Auctions Table (`wp_aucteeno_auctions`)

Stores denormalized auction data including:
- `auction_id` - Reference to the WordPress post ID
- `bidding_status` - Numeric status (10=publish, 20=schedule, 30=expire)
- `bidding_starts_at` - Unix timestamp for bidding start time
- `bidding_ends_at` - Unix timestamp for bidding end time
- `location_country` - Two-letter country code
- `location_subdivision` - Subdivision code (format: `COUNTRY:SUBDIVISION`)
- `location_city` - City name
- `location_lat` / `location_lng` - Geographic coordinates (reserved for future use)

#### Items Table (`wp_aucteeno_items`)

Stores denormalized item data including:
- `item_id` - Reference to the WordPress post ID
- `auction_id` - Reference to parent auction post ID
- `bidding_status` - Inherited from parent auction
- `bidding_starts_at` - Unix timestamp for bidding start time
- `bidding_ends_at` - Unix timestamp for bidding end time
- `lot_no` - Lot number string
- `lot_sort_key` - Numeric key for sorting lots
- `location_country` - Two-letter country code (inherited from auction if not set)
- `location_subdivision` - Subdivision code
- `location_city` - City name (inherited from auction if not set)
- `location_lat` / `location_lng` - Geographic coordinates (reserved for future use)

### Benefits of HPS

1. **Performance**: Queries can filter and sort by bidding status, dates, and locations without complex joins
2. **Indexing**: Optimized indexes on frequently queried columns (status, dates, locations)
3. **Scalability**: Handles large numbers of auctions and items efficiently
4. **Query Optimization**: Composite indexes support common query patterns (e.g., running auctions by location, items ending soon)

### HPS Sync Handler

The `HPS_Sync_Handler` class manages the synchronization process by hooking into WordPress lifecycle events:

- **`save_post`** (priority 20) - Syncs on product save
- **`before_delete_post`** (priority 10) - Removes on permanent deletion
- **`wp_trash_post`** (priority 10) - Removes on trashing
- **`untrash_post`** (priority 10) - Re-syncs on restoration

### Query Orderer Integration

The HPS tables work in conjunction with the `Query_Orderer` class, which intercepts WordPress queries and applies custom ordering logic when querying auctions or items. This allows the plugin to leverage HPS table indexes for optimal performance.

## Development

### Code Structure

- `includes/` - Core plugin classes
  - `admin/` - Admin interface components
  - `database/` - Database management and HPS tables
  - `product-types/` - Custom WooCommerce product types
  - `rest-api/` - REST API endpoints
  - `services/` - Service classes including HPS sync handlers
  - `taxonomies/` - Custom taxonomies
- `blocks/` - Gutenberg block definitions
- `templates/` - PHP templates for rendering
- `assets/` - CSS and JavaScript files

### Testing

The plugin includes PHPUnit tests. Run tests with:

```bash
make test
```

### Code Standards

The plugin follows WordPress Coding Standards and uses PHPCS for code quality checks.

## License

GPL v2 or later

## Support

- **Issues**: [GitHub Issues](https://github.com/theanother/aucteeno/issues)
- **Source**: [GitHub Repository](https://github.com/theanother/aucteeno)
- **Homepage**: [Plugin Homepage](https://theanother.org/plugin/aucteeno/)

