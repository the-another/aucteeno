# Query Loop Eager Loading — Design Spec

**Date:** 2026-04-10
**Branch:** feat/query-loop-optimization
**Status:** Approved

---

## Problem

The query loop renders auction/item listings with inner card blocks. Each card contains up to 6 field blocks. The current implementation has N+1 query problems at the data layer:

- `Database_Auctions::query_for_listing()` calls `wc_get_product()` once per result row to fetch image ID, price, and thumbnail URL — N queries after the main SELECT.
- `Database_Items::transform_results()` has the same pattern. Additionally, `Product_Item::get_price()` internally calls `wp_get_post_terms()` per item to resolve bidding status — another N hidden taxonomy queries.
- `field-image/render.php` calls `wc_get_product()` again per item even though image data is already in context — a second N queries.
- `field-location/render.php` calls `get_terms()` with a `meta_query` 1–2× per item when `showLinks=true` — up to 2N more queries.

With 25 items per page this totals ~152+ queries. The block context-passing architecture is already correct; all field blocks receive item data from context rather than querying independently. The problem is entirely inside the data-fetching layer.

---

## Goals

- Reduce per-page query count from ~152 to ~5–8 for a 25-item listing.
- Eliminate all `wc_get_product()` calls from `query_for_listing` and field blocks for aucteeno product types.
- Eliminate the hidden `wp_get_post_terms()` call inside `Product_Item::get_price()` by reading status directly from the HPS row.
- Keep the block context-passing architecture unchanged.
- Share batch-priming logic between `Database_Auctions` and `Database_Items` via a single helper.

---

## Architecture

The optimization is purely at the data layer. The Gutenberg block hierarchy, context propagation, and rendering pipeline are untouched. Six files change.

```
query_for_listing()
  ├── SQL SELECT (1 query)
  ├── Eager_Loader::prime_post_meta($ids)        → 1 query (all postmeta)
  ├── Eager_Loader::prime_images($ids)           → 1 query (attachment meta, conditional)
  └── Eager_Loader::load_location_terms($codes) → 1 query (location taxonomy, conditional)
        ↓
  transform loop (zero DB hits — all from WP object cache)
        ↓
  item_data[] with image_id, image_url, current_bid, reserve_price,
               permalink, location_*_term_id
        ↓
  block context → field blocks read from context (zero queries)
```

---

## Component Designs

### 1. `Eager_Loader` (new file)

**Path:** `includes/database/class-eager-loader.php`
**Namespace:** `The_Another\Plugin\Aucteeno\Database`

Three static methods. All calls to `_prime_post_caches()` must be guarded with `function_exists( '_prime_post_caches' )` since it is a WordPress internal (underscore-prefixed) function with no stability guarantee. If absent, fall back to iterating `get_post_meta( $id )` per ID — higher query cost but safe.

#### `prime_post_meta(array $ids): void`

Calls `_prime_post_caches($ids, false, true)` (posts disabled; postmeta enabled). Loads all postmeta for every ID into the WP object cache in one `WHERE post_id IN (...)` query. After this call, any `get_post_meta($id, $key, true)` for these IDs serves from the in-memory WP object cache with no further DB access.

#### `prime_images(array $ids): array`

Reads `_thumbnail_id` from the already-primed meta cache (zero DB hits), collects unique non-zero attachment IDs, calls `_prime_post_caches($attachment_ids, false, true)` to cache attachment postmeta. Returns `$post_id => $image_id` map (value is `0` for posts with no thumbnail). Must be called after `prime_post_meta()` on the same ID set.

After this call, `wp_get_attachment_image_src($attachment_id, $size)` and `wp_get_attachment_image($attachment_id, $size, ...)` are cache hits. The call chain: `wp_get_attachment_image_src()` → `wp_get_attachment_metadata()` → `get_post_meta($attachment_id, '_wp_attachment_metadata', true)` — a cache hit after priming. `wp_get_attachment_image()` additionally reads `_wp_attachment_image_alt` via `get_post_meta()` — also a cache hit.

#### `load_location_terms(array $codes): array`

Accepts a flat array of location code strings (country codes like `'US'`, subdivision codes like `'US:KS'`, mixed). Filters empty strings via `array_filter()` before deduplication, then runs a single `get_terms()` call:

```php
get_terms( array(
    'taxonomy'   => 'aucteeno-location',
    'hide_empty' => false,
    'meta_query' => array(
        array( 'key' => 'code', 'value' => $codes, 'compare' => 'IN' ),
    ),
) );
```

`get_terms()` with default `update_term_meta_cache = true` batch-loads all term meta in the same call, so the subsequent `get_term_meta($term->term_id, 'code', true)` calls inside the map builder are cache hits. Returns `$code => $term_id` map. Returns empty array if `$codes` is empty (query skipped entirely).

**Assumption:** The `code` term meta value is globally unique within the `aucteeno-location` taxonomy — country terms store two-letter codes (`'US'`), subdivision terms store `'COUNTRY:STATE'` format (`'US:KS'`). These namespacing conventions ensure no collisions in the flat `IN` query. This is confirmed by `Location_Helper::get_or_create_country_term()` and `get_or_create_subdivision_term()`.

**Multi-block per page:** `_prime_post_caches()` populates the WP object cache for the full request lifetime. A second `query_for_listing()` call on the same page (multiple query loop blocks) will serve any overlapping IDs from object cache without re-priming.

---

### 2. Postmeta key reference

Before touching any transform code, the correct postmeta keys for each product type:

**Auctions (`Product_Auction`):**
- `current_bid`: `get_post_meta($id, '_price', true)` — standard WooCommerce `_price` field (no custom price fields in `Product_Auction::$extra_data`)
- `reserve_price`: always `0.0` — `Product_Auction` has no `get_reserve_price()` method; the existing code's `method_exists()` guard always returns false

**Items (`Product_Item`):**
- Custom fields use meta key `'_' . $key` convention from `Datastore_Item`
- `_aucteeno_current_bid` — amount when auction is running (status 10)
- `_aucteeno_asking_bid` — amount when auction is upcoming (status 20)
- `_aucteeno_sold_price` — amount when auction is expired (status 30)
- `current_bid` selection: read from the HPS row's `bidding_status` (already present in the SQL result), then fetch the matching meta key — eliminates the hidden `wp_get_post_terms()` call inside `Product_Item::get_price()`
- `reserve_price`: always `0.0` — `Product_Item` has no `get_reserve_price()` method

---

### 3. `Database_Auctions::query_for_listing()` changes

**Path:** `includes/database/class-database-auctions.php`

After `$wpdb->get_results(...)`, before the transform loop, insert the three batch operations:

```php
$ids = array_column( $results, 'auction_id' );
Eager_Loader::prime_post_meta( $ids );
$image_map = Eager_Loader::prime_images( $ids );

$location_codes = array_filter( array_merge(
    array_column( $results, 'location_country' ),
    array_column( $results, 'location_subdivision' )
) );
$term_map     = Eager_Loader::load_location_terms( $location_codes );
$auction_base = Auction_Item_Permalinks::get_auction_base(); // option-cached, read once
```

Transform loop replaces `wc_get_product()` with cache-hit calls:

```php
$auction_id    = absint( $row['auction_id'] );
$image_id      = $image_map[ $auction_id ] ?? 0;
$image_src     = $image_id ? wp_get_attachment_image_src( $image_id, 'medium' ) : false;
$image_url     = $image_src ? $image_src[0] : '';
$current_bid   = (float) get_post_meta( $auction_id, '_price', true );
$reserve_price = 0.0;
$permalink     = home_url( user_trailingslashit( $auction_base . '/' . $row['post_name'] ) );
```

New fields added to each item data array:

| Field | Type | Notes |
|---|---|---|
| `image_id` | `int` | WP attachment ID; `0` if no thumbnail |
| `location_country_term_id` | `int` | Taxonomy term ID; `0` if not found |
| `location_subdivision_term_id` | `int` | Taxonomy term ID; `0` if not found |

Existing `image_url` field is kept for the fallback card renderer in query-loop and REST API consumers.

---

### 4. `Database_Items` changes

**Path:** `includes/database/class-database-items.php`

#### SQL changes

All three SELECT paths (`query_for_listing_newest`, `query_status_group`) need one additional JOIN and one additional column:

```sql
-- Add to FROM clause:
LEFT JOIN {$posts_table} ap ON i.auction_id = ap.ID

-- Add to SELECT list:
ap.post_name AS auction_post_name
```

Zero extra queries — additional column on an already-joined table. Orphaned items (no valid parent auction) get `NULL` for `auction_post_name`.

#### `transform_results()` signature change

The method currently takes only `array $results`. It must also accept the three pre-computed maps and the permalink base strings so that priming happens once per `query_for_listing()` call and `$auction_base`/`$item_base` are available inside the private method:

```php
private static function transform_results(
    array $results,
    array $image_map,
    array $term_map,
    string $auction_base,
    string $item_base
): array
```

`$auction_base` and `$item_base` are read once (via `Auction_Item_Permalinks::get_auction_base()` / `get_item_base()`) in each calling method before invoking `transform_results()`, then passed through. They must not be re-read inside the transform loop.

**Both calling paths must be updated:**

`query_for_listing_newest()` — currently calls `self::transform_results($results)` inline before returning. Must be changed to run the three batch operations before calling `transform_results()`:

```php
// After get_results():
$ids = array_column( $results, 'item_id' );
Eager_Loader::prime_post_meta( $ids );
$image_map = Eager_Loader::prime_images( $ids );
$location_codes = array_filter( array_merge(
    array_column( $results, 'location_country' ),
    array_column( $results, 'location_subdivision' )
) );
$term_map = Eager_Loader::load_location_terms( $location_codes );

return array(
    'items' => self::transform_results( $results, $image_map, $term_map ),
    ...
);
```

`query_for_listing_by_status()` — merges results across up to three `query_status_group()` calls before transform. Run priming on the merged `$results` array before calling `transform_results()`. Same pattern as above.

#### `current_bid` in the transform

Use `bidding_status` from the HPS row to select the correct meta key — no `wp_get_post_terms()` call needed:

```php
$item_id        = absint( $row['item_id'] );
$bidding_status = absint( $row['bidding_status'] );

$current_bid_key = match( $bidding_status ) {
    10 => '_aucteeno_current_bid',   // running
    20 => '_aucteeno_asking_bid',    // upcoming
    30 => '_aucteeno_sold_price',    // expired
    default => '_aucteeno_current_bid',
};
$current_bid = (float) get_post_meta( $item_id, $current_bid_key, true );
```

`reserve_price` is always `0.0` (no `get_reserve_price()` on `Product_Item`).

#### Permalink construction

Both `$auction_base` and `$item_base` arrive as method parameters (see signature above). Inside the transform loop:

```php
$auction_slug = $row['auction_post_name'] ?? '';

if ( $auction_slug ) {
    $permalink = home_url( user_trailingslashit(
        $auction_base . '/' . $auction_slug . '/' . $item_base . '/' . $row['post_name']
    ) );
} else {
    // Orphaned item — no valid parent auction.
    $permalink = get_permalink( $item_id );
}
```

Same new fields as auctions: `image_id`, `location_country_term_id`, `location_subdivision_term_id`.

---

### 5. `field-image/render.php` changes

**Path:** `blocks/field-image/render.php`

Remove lines 23–33 (the `$product_id` extraction and `wc_get_product()` call). Read image ID directly from context:

```php
$image_id = absint( $item_data['image_id'] ?? 0 );
$alt      = $item_data['title'] ?? '';
$style    = "aspect-ratio: {$aspect_ratio};";
```

Replace `$product->get_image(...)` with `wp_get_attachment_image()`:

```php
if ( $image_id ) {
    echo wp_get_attachment_image(
        $image_id,
        'woocommerce_thumbnail',
        false,
        array( 'alt' => $alt, 'style' => $style )
    );
} else {
    echo wc_placeholder_img( 'woocommerce_thumbnail', array( 'style' => $style ) );
}
```

`wp_get_attachment_image()` reads alt text from attachment postmeta internally; since `Eager_Loader::prime_images()` already primed attachment postmeta, this is a cache hit. `wc_placeholder_img()` reads from WC options (already object-cached) — no DB queries.

**Behaviour change (intentional):** Items with no thumbnail currently render no output from this block (`return ''`). After this change they render the WooCommerce placeholder image. This is the correct UX for listing pages and matches WooCommerce conventions. The empty output behaviour was a side-effect of the early `return ''` on missing `$product_id`, not a deliberate design choice.

**`ob_start()` / `ob_get_clean()` wrapper:** The current file wraps output in an output buffer. The updated code may keep or remove this wrapper — either approach is valid since the block's output is already controlled by `render_callback`. Keep the wrapper for consistency with other field blocks.

**Rationale for not using `wc_get_product()->get_image()`:** For aucteeno product types, images are stored in standard WordPress postmeta (`_thumbnail_id`). `wc_get_product()` is unnecessary overhead. `wp_get_attachment_image()` produces equivalent HTML including srcset, sizes, and lazy-loading attributes.

---

### 6. `field-location/render.php` changes

**Path:** `blocks/field-location/render.php`

The `$get_term_by_code` closure is kept but demoted to fallback-only. Every call site in the switch block changes from calling `$get_term_by_code(...)` unconditionally to reading from context first:

```php
$country_term_id = $item_data['location_country_term_id']
    ?? ( $show_links ? $get_term_by_code( $country, 0 ) : 0 );

$subdivision_term_id = $item_data['location_subdivision_term_id']
    ?? ( $show_links ? $get_term_by_code( $country . ':' . $subdivision_code, $country_term_id ) : 0 );
```

**In listing context:** both values are integers pre-loaded by `Eager_Loader::load_location_terms()`; `$get_term_by_code` is never invoked.

**On single post pages** (via `Block_Data_Helper` fallback path, where `$item_data` comes from a single DB row without pre-loaded term IDs): the `??` fallback fires and `$get_term_by_code` is called as before — acceptable for a single item.

This pattern is applied at every `$get_term_by_code` call site within the switch block (5–6 locations across the `smart`, `country_only`, `city_subdivision`, `city_country`, and `default` cases).

---

### 7. `Block_Data_Helper::get_item_data()` changes

**Path:** `includes/helpers/class-block-data-helper.php`

Add `image_id` to the returned array so `field-image` works correctly on single post pages:

```php
'image_id' => (int) get_post_meta( $post_id, '_thumbnail_id', true ),
```

This is a single `get_post_meta()` call on the current post — already in the WP object cache for single-post page loads. No additional queries.

No changes needed for `location_country_term_id` / `location_subdivision_term_id` — `field-location` handles their absence via the `$get_term_by_code` fallback (see section 6).

`Block_Data_Helper::get_item_data()` still calls `wc_get_product()` for type-checking and for building `image_url`. This is intentional and left in place — single-post pages serve one item and the cost of one `wc_get_product()` call is acceptable. `image_url` construction in this helper is also retained as-is since it is used elsewhere (e.g. REST API fallback). Only `image_id` is added as a new field.

---

## Query Count Summary

### `query_for_listing_newest` path (single combined query)

| Phase | Before | After |
|---|---|---|
| COUNT query | 1 | 1 |
| Main SELECT | 1 | 1 |
| Postmeta batch (`_prime_post_caches`) | 0 | 1 |
| Attachment meta batch | 0 | 1 |
| Location terms batch | 0 | 1 |
| `wc_get_product()` in transform | N (25) | 0 |
| `wp_get_post_terms()` inside `get_price()` | N (25) | 0 |
| `wc_get_product()` in field-image | N (25) | 0 |
| `get_terms()` in field-location (showLinks=true) | 2N (50) | 0 |
| **Total (25 items)** | **~152** | **5** |

### `query_for_listing_by_status` path (status-grouped queries)

| Phase | Before | After |
|---|---|---|
| Status count query (GROUP BY) | 1 | 1 |
| Per-status SELECT queries (1–3) | 1–3 | 1–3 |
| Postmeta batch | 0 | 1 |
| Attachment meta batch | 0 | 1 |
| Location terms batch | 0 | 1 |
| `wc_get_product()` etc. in transform | ~75+ | 0 |
| **Total (25 items, all 3 status groups)** | **~152** | **7–8** |

---

## Files Changed

| File | Type | Notes |
|---|---|---|
| `includes/database/class-eager-loader.php` | New | Batch-priming logic |
| `includes/database/class-database-auctions.php` | Modified | Batch priming + postmeta reads |
| `includes/database/class-database-items.php` | Modified | Same + SQL JOIN + status-based bid key |
| `blocks/field-image/render.php` | Modified | Use `image_id` from context |
| `blocks/field-location/render.php` | Modified | Use pre-loaded term IDs from context |
| `includes/helpers/class-block-data-helper.php` | Modified | Add `image_id` to returned array |

---

## Out of Scope

- REST API controller — consumes `query_for_listing()` output; benefits automatically from new `image_id` and term ID fields without modification.
- Transient/fragment caching — premature given the query count is already reduced to 5–8.
- `field-image` on single post pages — handled by `Block_Data_Helper` change in section 7.
