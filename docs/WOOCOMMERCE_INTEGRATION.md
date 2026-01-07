# WooCommerce Integration Documentation

## Overview

**Aucteeno is a WooCommerce-based plugin** that extends WooCommerce functionality for auction and item management. This document outlines all WooCommerce integration points, dependencies, and best practices.

## WooCommerce Version

- **Required Version**: WooCommerce 10.4.3
- **Repository**: https://github.com/woocommerce/woocommerce/tree/10.4.3
- **Release Date**: December 22, 2025

### WooCommerce 10.4.3 Key Features
- Security patch for Store API vulnerability
- Bug fixes for cart shortcode and HPOS sync-on-read
- Currency update for Bulgarian stores (BGN to EUR transition)

## Core Integration Points

### 1. Plugin Initialization

The plugin hooks into WooCommerce's initialization:

```php
// File: aucteeno.php
add_action(
    'woocommerce_init',
    function () {
        Aucteeno::get_instance()->start();
    }
);
```

**Key Hook**: `woocommerce_init` - Ensures WooCommerce is fully loaded before plugin initialization.

### 2. Custom Product Types

Aucteeno extends WooCommerce with two custom product types:

#### Auction Product Type
- **Slug**: `aucteeno-ext-auction`
- **Class**: `Product_Auction extends WC_Product_External`
- **Datastore**: `Datastore_Auction extends WC_Product_External_Data_Store_CPT`
- **Registration**: `Product_Type_Register_Auction`

#### Item Product Type
- **Slug**: `aucteeno-ext-item`
- **Class**: `Product_Item extends WC_Product_External`
- **Datastore**: `Datastore_Item extends WC_Product_External_Data_Store_CPT`
- **Registration**: `Product_Type_Register_Item`

### 3. WooCommerce Hooks Used

#### Product Type Registration
- `product_type_selector` - Adds custom product types to the product type dropdown
- `woocommerce_product_class` - Maps product type to custom product class
- `woocommerce_product_data_store` - Maps product type to custom datastore

#### Admin Product Edit Screen
- `woocommerce_product_options_general_product_data` - Adds custom fields to general product data section
- `woocommerce_product_data_tabs` - Adds/modifies product data tabs
- `woocommerce_product_data_panels` - Adds custom panels to product edit screen
- `woocommerce_process_product_meta` - Saves custom product meta fields

#### Product Type Reordering
- `product_type_selector` (priority 20) - Reorders product types to show auction and item after external

### 4. WooCommerce Classes Extended

#### Product Classes
- `WC_Product_External` - Base class for both auction and item products
  - Extended by: `Product_Auction` and `Product_Item`

#### Data Store Classes
- `WC_Product_External_Data_Store_CPT` - Base datastore for external products
  - Extended by: `Datastore_Auction` and `Datastore_Item`

### 5. WooCommerce Helper Functions Used

#### Product Functions
- `wc_get_product()` - Get product object by ID
- `woocommerce_wp_text_input()` - Render text input field in product edit screen
- `woocommerce_wp_textarea_input()` - Render textarea field in product edit screen
- `woocommerce_wp_select()` - Render select dropdown in product edit screen

## Integration Architecture

### Product Type Registration Flow

1. **Hook Registration** (`Aucteeno::register_product_types()`)
   - Registers `Product_Type_Register_Auction` and `Product_Type_Register_Item`
   - Both classes hook into `product_type_selector` to add product types

2. **Product Class Mapping** (`Aucteeno::register_datastores()`)
   - Filters `woocommerce_product_class` to map product types to custom classes
   - Returns `Product_Auction::class` or `Product_Item::class` based on product type

3. **Datastore Registration** (`Aucteeno::register_datastores()`)
   - Filters `woocommerce_product_data_store` to map product types to custom datastores
   - Returns `Datastore_Auction` or `Datastore_Item` instances

### Admin Meta Fields Integration

Both auction and item products use WooCommerce's admin meta field system:

- **Hook**: `woocommerce_product_options_general_product_data`
- **Save Hook**: `woocommerce_process_product_meta`
- **Helper Functions**: `woocommerce_wp_*` functions for field rendering

## WooCommerce Compatibility

### Minimum Requirements
- **WordPress**: 6.9+
- **PHP**: 8.3+
- **WooCommerce**: 10.4.3 (specific version required)

### Best Practices

1. **Always check WooCommerce availability**
   - Plugin hooks into `woocommerce_init` to ensure WooCommerce is loaded
   - No direct WooCommerce class usage before initialization

2. **Use WooCommerce hooks and filters**
   - Follow WooCommerce hook naming conventions
   - Use appropriate priorities for filters
   - Document all custom hooks

3. **Extend WooCommerce classes properly**
   - Extend appropriate base classes (`WC_Product_External` for external products)
   - Override methods only when necessary
   - Call parent methods when extending functionality

4. **Follow WooCommerce coding standards**
   - Use WooCommerce-style architecture patterns
   - Follow WordPress and WooCommerce coding standards
   - Use WooCommerce helper functions where available

## WooCommerce Repository Structure

For reference, the WooCommerce 10.4.3 repository structure includes:

- `plugins/woocommerce/` - Core WooCommerce plugin
- `packages/` - Shared packages used by WooCommerce
- `includes/` - Core WooCommerce includes (in plugin directory)
  - `class-wc-product.php` - Base product class
  - `class-wc-product-external.php` - External product class
  - `data-stores/` - Data store implementations

## Key Files to Reference

When working with WooCommerce integration, refer to:

1. **Product Classes**
   - `plugins/woocommerce/includes/class-wc-product.php`
   - `plugins/woocommerce/includes/class-wc-product-external.php`

2. **Data Stores**
   - `plugins/woocommerce/includes/data-stores/class-wc-product-external-data-store-cpt.php`

3. **Admin Functions**
   - `plugins/woocommerce/includes/admin/wc-meta-box-functions.php`

4. **Hooks Reference**
   - WooCommerce Hook Reference: https://woocommerce.github.io/code-reference/hooks/

## Testing WooCommerce Integration

When testing, ensure:
- WooCommerce 10.4.3 is installed and active
- WordPress 6.9+ is installed
- PHP 8.3+ is available
- All WooCommerce dependencies are met

## Notes for Development

1. **Version-Specific Code**: If using WooCommerce 10.4.3-specific features, document them clearly
2. **Backward Compatibility**: Consider backward compatibility if needed
3. **HPOS Support**: WooCommerce 10.4.3 includes HPOS (High-Performance Order Storage) improvements
4. **Store API**: Be aware of Store API security patches in 10.4.3

## Related Documentation

- [WooCommerce Developer Documentation](https://woocommerce.com/documentation/woocommerce/)
- [WooCommerce Code Reference](https://woocommerce.github.io/code-reference/)
- [WooCommerce GitHub Repository](https://github.com/woocommerce/woocommerce/tree/10.4.3)

