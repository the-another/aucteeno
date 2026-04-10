# Query Loop Eager Loading Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reduce per-page query count from ~152 to 5–8 for 25-item auction/item listings by replacing per-row `wc_get_product()` calls with WordPress batch postmeta priming and pre-loaded taxonomy term IDs.

**Architecture:** A new `Eager_Loader` helper class encapsulates three batch operations (postmeta prime, attachment meta prime, location term load). `Database_Auctions::query_for_listing()` and `Database_Items` transform methods are refactored to call these helpers before the transform loop, replacing per-row `wc_get_product()` calls with cached `get_post_meta()` reads. Field blocks (`field-image`, `field-location`) and `Block_Data_Helper` are updated to consume the new context fields.

**Tech Stack:** PHP 8.3, WordPress 6.9+, WooCommerce 10.4.3+, PHPUnit 11, Brain\Monkey, Mockery. Tests: `make test` (Docker) or `./vendor/bin/phpunit`.

---

## File Map

| File | Change |
|---|---|
| `includes/database/class-eager-loader.php` | **Create** — batch priming helper |
| `tests/Database/Eager_Loader_Test.php` | **Create** — unit tests for Eager_Loader |
| `includes/database/class-database-auctions.php` | **Modify** lines 251–281 — replace transform loop |
| `tests/Database/Database_Auctions_Transform_Test.php` | **Create** — tests for new transform behavior |
| `includes/database/class-database-items.php` | **Modify** — SQL JOINs + transform_results signature + callers |
| `tests/Database/Database_Items_Transform_Test.php` | **Create** — tests for new transform behavior |
| `includes/helpers/class-block-data-helper.php` | **Modify** lines 110–128 — add `image_id`, remove second `wc_get_product()` |
| `tests/Helpers/Block_Data_Helper_Test.php` | **Create** — unit tests for `get_item_data()` |
| `blocks/field-image/render.php` | **Modify** lines 23–57 — use `image_id` from context |
| `blocks/field-location/render.php` | **Modify** ~15 call sites — use pre-loaded term IDs |

---

## Chunk 1: Eager_Loader class

### Task 1: `Eager_Loader::prime_post_meta()`

**Files:**
- Create: `tests/Database/Eager_Loader_Test.php`
- Create: `includes/database/class-eager-loader.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
/**
 * Eager_Loader Tests
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Database;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Database\Eager_Loader;

/**
 * Class Eager_Loader_Test
 */
class Eager_Loader_Test extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_prime_post_meta_calls_prime_post_caches_with_meta_enabled(): void {
        Functions\expect( '_prime_post_caches' )
            ->once()
            ->with( array( 1, 2 ), false, true );

        Eager_Loader::prime_post_meta( array( 1, 2 ) );
    }

    public function test_prime_post_meta_does_nothing_for_empty_ids(): void {
        // Brain\Monkey will fail the test if _prime_post_caches is called unexpectedly.
        Eager_Loader::prime_post_meta( array() );
        $this->assertTrue( true ); // Reached without unexpected calls.
    }

    public function test_prime_post_meta_falls_back_to_get_post_meta_when_prime_not_available(): void {
        // Do NOT register _prime_post_caches — function_exists() will return false.
        Functions\expect( 'get_post_meta' )
            ->twice(); // Once per ID, no key — loads all meta.

        Eager_Loader::prime_post_meta( array( 1, 2 ) );
    }
}
```

- [ ] **Step 2: Run test — confirm it fails**

```bash
./vendor/bin/phpunit tests/Database/Eager_Loader_Test.php -v
```

Expected: `Error: Class "The_Another\Plugin\Aucteeno\Database\Eager_Loader" not found`

- [ ] **Step 3: Create `class-eager-loader.php` with `prime_post_meta()`**

```php
<?php
/**
 * Eager Loader
 *
 * Batch-primes WordPress object caches to eliminate N+1 queries
 * in query_for_listing() transform loops.
 *
 * @package Aucteeno
 * @since 2.1.0
 */

namespace The_Another\Plugin\Aucteeno\Database;

/**
 * Class Eager_Loader
 *
 * Provides static helpers for batch-loading WordPress object caches
 * before iterating over query result sets.
 */
class Eager_Loader {

    /**
     * Batch-prime post meta cache for the given post IDs.
     *
     * After calling, get_post_meta() for any of these IDs serves from the
     * WP in-memory object cache — no further DB queries.
     *
     * Uses _prime_post_caches() when available (WordPress internal, 6.0+).
     * Falls back to get_post_meta($id) per-ID when the function is absent.
     *
     * @param array<int> $ids Post IDs to prime.
     * @return void
     */
    public static function prime_post_meta( array $ids ): void {
        if ( empty( $ids ) ) {
            return;
        }

        if ( function_exists( '_prime_post_caches' ) ) {
            _prime_post_caches( $ids, false, true );
            return;
        }

        // Fallback: load all meta per ID individually (higher query cost but safe).
        foreach ( $ids as $id ) {
            get_post_meta( absint( $id ) );
        }
    }
}
```

- [ ] **Step 4: Run test — confirm it passes**

```bash
./vendor/bin/phpunit tests/Database/Eager_Loader_Test.php -v
```

Expected: `OK (2 tests, 2 assertions)`

- [ ] **Step 5: Commit**

```bash
git add includes/database/class-eager-loader.php tests/Database/Eager_Loader_Test.php
git commit -m "feat: add Eager_Loader::prime_post_meta() with test"
```

---

### Task 2: `Eager_Loader::prime_images()`

**Files:**
- Modify: `tests/Database/Eager_Loader_Test.php`
- Modify: `includes/database/class-eager-loader.php`

- [ ] **Step 1: Add failing tests**

Append to `Eager_Loader_Test.php` inside the class body:

```php
public function test_prime_images_returns_post_id_to_image_id_map(): void {
    Functions\when( 'get_post_meta' )
        ->alias( function ( $id, $key, $single ) {
            return $id === 10 ? '55' : '';
        } );
    Functions\when( '_prime_post_caches' )->justReturn( null );

    $map = Eager_Loader::prime_images( array( 10, 11 ) );

    $this->assertSame( array( 10 => 55, 11 => 0 ), $map );
}

public function test_prime_images_primes_attachment_caches_for_found_thumbnails(): void {
    Functions\when( 'get_post_meta' )
        ->alias( function ( $id, $key, $single ) {
            return '55';
        } );

    Functions\expect( '_prime_post_caches' )
        ->once()
        ->with( array( 55 ), false, true );

    Eager_Loader::prime_images( array( 10 ) );
}

public function test_prime_images_skips_attachment_prime_when_no_thumbnails(): void {
    Functions\when( 'get_post_meta' )->justReturn( '' );
    // No _prime_post_caches expected — Brain\Monkey will fail if called unexpectedly.

    $map = Eager_Loader::prime_images( array( 10 ) );

    $this->assertSame( array( 10 => 0 ), $map );
}

public function test_prime_images_deduplicates_attachment_ids(): void {
    // Two posts share the same thumbnail.
    Functions\when( 'get_post_meta' )->justReturn( '55' );

    Functions\expect( '_prime_post_caches' )
        ->once()
        ->with( array( 55 ), false, true ); // Deduplicated: [55, 55] → [55]

    Eager_Loader::prime_images( array( 10, 11 ) );
}
```

- [ ] **Step 2: Run tests — confirm new tests fail**

```bash
./vendor/bin/phpunit tests/Database/Eager_Loader_Test.php -v
```

Expected: `Error: Call to undefined method ...::prime_images()`

- [ ] **Step 3: Add `prime_images()` to `class-eager-loader.php`**

```php
/**
 * Prime attachment meta caches for the thumbnails of the given post IDs.
 *
 * Must be called AFTER prime_post_meta() on the same IDs — reads
 * _thumbnail_id from the already-primed meta cache.
 *
 * @param array<int> $ids Post IDs whose thumbnails should be primed.
 * @return array<int,int> Map of post_id => attachment_id (0 if no thumbnail).
 */
public static function prime_images( array $ids ): array {
    $map            = array();
    $attachment_ids = array();

    foreach ( $ids as $id ) {
        $id       = absint( $id );
        $image_id = (int) get_post_meta( $id, '_thumbnail_id', true );
        $map[ $id ] = $image_id;
        if ( $image_id > 0 ) {
            $attachment_ids[] = $image_id;
        }
    }

    if ( ! empty( $attachment_ids ) ) {
        $unique_ids = array_values( array_unique( $attachment_ids ) );
        if ( function_exists( '_prime_post_caches' ) ) {
            _prime_post_caches( $unique_ids, false, true );
        }
    }

    return $map;
}
```

- [ ] **Step 4: Run tests — confirm all pass**

```bash
./vendor/bin/phpunit tests/Database/Eager_Loader_Test.php -v
```

Expected: `OK (6 tests, ...)`

- [ ] **Step 5: Commit**

```bash
git add includes/database/class-eager-loader.php tests/Database/Eager_Loader_Test.php
git commit -m "feat: add Eager_Loader::prime_images() with tests"
```

---

### Task 3: `Eager_Loader::load_location_terms()`

**Files:**
- Modify: `tests/Database/Eager_Loader_Test.php`
- Modify: `includes/database/class-eager-loader.php`

- [ ] **Step 1: Add failing tests**

Append to `Eager_Loader_Test.php`:

```php
public function test_load_location_terms_returns_code_to_term_id_map(): void {
    $term_us = (object) array( 'term_id' => 42 );
    $term_ks = (object) array( 'term_id' => 43 );

    Functions\expect( 'get_terms' )
        ->once()
        ->andReturn( array( $term_us, $term_ks ) );

    Functions\expect( 'get_term_meta' )
        ->with( 42, 'code', true )
        ->andReturn( 'US' );
    Functions\expect( 'get_term_meta' )
        ->with( 43, 'code', true )
        ->andReturn( 'US:KS' );

    $map = Eager_Loader::load_location_terms( array( 'US', 'US:KS' ) );

    $this->assertSame( array( 'US' => 42, 'US:KS' => 43 ), $map );
}

public function test_load_location_terms_returns_empty_map_and_skips_query_for_empty_codes(): void {
    Functions\expect( 'get_terms' )->never();

    $map = Eager_Loader::load_location_terms( array() );

    $this->assertSame( array(), $map );
}

public function test_load_location_terms_filters_empty_strings(): void {
    Functions\expect( 'get_terms' )->never();

    $map = Eager_Loader::load_location_terms( array( '', '', '' ) );

    $this->assertSame( array(), $map );
}

public function test_load_location_terms_deduplicates_codes_before_querying(): void {
    $term = (object) array( 'term_id' => 42 );

    Functions\expect( 'get_terms' )
        ->once()
        ->andReturnUsing( function ( $args ) {
            // Confirm only one unique code is passed.
            $this->assertSame( array( 'US' ), $args['meta_query'][0]['value'] );
            return array();
        } );

    Eager_Loader::load_location_terms( array( 'US', 'US', 'US' ) );
}

public function test_load_location_terms_returns_empty_map_on_wp_error(): void {
    $error = new \WP_Error( 'invalid_taxonomy', 'Invalid taxonomy.' );

    Functions\expect( 'get_terms' )->once()->andReturn( $error );
    Functions\expect( 'is_wp_error' )->once()->andReturn( true );

    $map = Eager_Loader::load_location_terms( array( 'US' ) );

    $this->assertSame( array(), $map );
}
```

- [ ] **Step 2: Run tests — confirm new tests fail**

```bash
./vendor/bin/phpunit tests/Database/Eager_Loader_Test.php::test_load_location_terms_returns_code_to_term_id_map -v
```

Expected: `Error: Call to undefined method ...::load_location_terms()`

- [ ] **Step 3: Add `load_location_terms()` to `class-eager-loader.php`**

```php
/**
 * Load location taxonomy term IDs for a set of location codes.
 *
 * Runs a single get_terms() call with meta_query IN(...) for all unique codes,
 * replacing per-item get_terms() calls in field-location/render.php.
 *
 * The aucteeno-location taxonomy uses globally unique `code` term meta:
 * countries store two-letter codes ('US'), subdivisions store 'COUNTRY:STATE' ('US:KS').
 *
 * @param array<string> $codes Mixed country and subdivision codes. Empty strings are filtered.
 * @return array<string,int> Map of code => term_id. Empty if no codes or no matches.
 */
public static function load_location_terms( array $codes ): array {
    $codes = array_values( array_unique( array_filter( $codes ) ) );

    if ( empty( $codes ) ) {
        return array();
    }

    $terms = get_terms(
        array(
            'taxonomy'   => 'aucteeno-location',
            'hide_empty' => false,
            'meta_query' => array(
                array(
                    'key'     => 'code',
                    'value'   => $codes,
                    'compare' => 'IN',
                ),
            ),
        )
    );

    $map = array();

    if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
        return $map;
    }

    foreach ( $terms as $term ) {
        $code = get_term_meta( $term->term_id, 'code', true );
        if ( ! empty( $code ) ) {
            $map[ $code ] = (int) $term->term_id;
        }
    }

    return $map;
}
```

- [ ] **Step 4: Run all Eager_Loader tests — confirm all pass**

```bash
./vendor/bin/phpunit tests/Database/Eager_Loader_Test.php -v
```

Expected: `OK (12 tests, ...)`

- [ ] **Step 5: Run full test suite — no regressions**

```bash
make test
```

Expected: all existing tests still pass.

- [ ] **Step 6: Commit**

```bash
git add includes/database/class-eager-loader.php tests/Database/Eager_Loader_Test.php
git commit -m "feat: add Eager_Loader::load_location_terms() with tests"
```

---

## Chunk 2: Database_Auctions transform

### Task 4: `Database_Auctions::query_for_listing()` transform refactor

**Files:**
- Create: `tests/Database/Database_Auctions_Transform_Test.php`
- Modify: `includes/database/class-database-auctions.php:250–289`

The existing transform loop (lines 251–281) calls `wc_get_product()` per row. Replace it with Eager_Loader calls + `get_post_meta()` reads + permalink construction from `post_name`.

Also need to add `use` statement for `Eager_Loader` and `Auction_Item_Permalinks` at the top of the class file.

- [ ] **Step 1: Write failing tests**

Create `tests/Database/Database_Auctions_Transform_Test.php`:

```php
<?php
/**
 * Database_Auctions transform tests
 *
 * Verifies that query_for_listing() does not call wc_get_product()
 * and produces the expected new item data fields.
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Database;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Database\Database_Auctions;

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

/**
 * Class Database_Auctions_Transform_Test
 */
class Database_Auctions_Transform_Test extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        $wpdb         = Mockery::mock( 'wpdb' );
        $wpdb->prefix = 'wp_';
        $wpdb->posts  = 'wp_posts';
        $GLOBALS['wpdb'] = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

        // Stub SQL methods generically.
        $wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
        $wpdb->shouldReceive( 'get_var' )->andReturn( '1' );
        $wpdb->shouldReceive( 'get_results' )->andReturn( array(
            array(
                'auction_id'           => 10,
                'user_id'              => 1,
                'bidding_status'       => 10,
                'bidding_starts_at'    => 1000,
                'bidding_ends_at'      => 9999,
                'location_country'     => 'US',
                'location_subdivision' => 'US:KS',
                'location_city'        => 'Wichita',
                'post_title'           => 'Test Auction',
                'post_name'            => 'test-auction',
            ),
        ) );

        // Eager_Loader dependencies.
        Functions\when( '_prime_post_caches' )->justReturn( null );
        Functions\when( 'get_post_meta' )->justReturn( '' );
        Functions\when( 'wp_get_attachment_image_src' )->justReturn( false );
        Functions\when( 'get_terms' )->justReturn( array() );
        Functions\when( 'get_term_meta' )->justReturn( '' );
        Functions\when( 'is_wp_error' )->justReturn( false );

        // Permalink helpers.
        Functions\when( 'get_option' )->justReturn( 'auction' );
        Functions\when( 'sanitize_title' )->returnArg();
        Functions\when( 'home_url' )->returnArg();
        Functions\when( 'user_trailingslashit' )->returnArg();
    }

    protected function tearDown(): void {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_query_for_listing_does_not_call_wc_get_product(): void {
        Functions\expect( 'wc_get_product' )->never();

        Database_Auctions::query_for_listing();
    }

    public function test_query_for_listing_item_has_image_id_field(): void {
        Functions\when( 'get_post_meta' )
            ->alias( function ( $id, $key = null, $single = false ) {
                if ( '_thumbnail_id' === $key ) {
                    return '77';
                }
                return '';
            } );

        $result = Database_Auctions::query_for_listing();

        $this->assertArrayHasKey( 'image_id', $result['items'][0] );
        $this->assertSame( 77, $result['items'][0]['image_id'] );
    }

    public function test_query_for_listing_item_has_location_term_id_fields(): void {
        $result = Database_Auctions::query_for_listing();

        $this->assertArrayHasKey( 'location_country_term_id', $result['items'][0] );
        $this->assertArrayHasKey( 'location_subdivision_term_id', $result['items'][0] );
    }

    public function test_query_for_listing_current_bid_reads_from_price_meta(): void {
        Functions\when( 'get_post_meta' )
            ->alias( function ( $id, $key = null, $single = false ) {
                if ( '_price' === $key ) {
                    return '499.00';
                }
                return '';
            } );

        $result = Database_Auctions::query_for_listing();

        $this->assertSame( 499.0, $result['items'][0]['current_bid'] );
    }

    public function test_query_for_listing_reserve_price_is_always_zero(): void {
        $result = Database_Auctions::query_for_listing();

        $this->assertSame( 0.0, $result['items'][0]['reserve_price'] );
    }

    public function test_query_for_listing_permalink_built_from_post_name(): void {
        Functions\when( 'home_url' )->alias( function ( $path ) {
            return 'https://example.com' . $path;
        } );
        Functions\when( 'user_trailingslashit' )->alias( function ( $s ) {
            return rtrim( $s, '/' ) . '/';
        } );

        $result = Database_Auctions::query_for_listing();

        $this->assertStringContainsString( 'test-auction', $result['items'][0]['permalink'] );
    }

    public function test_query_for_listing_image_url_populated_from_attachment(): void {
        Functions\when( 'get_post_meta' )
            ->alias( function ( $id, $key = null, $single = false ) {
                return '_thumbnail_id' === $key ? '77' : '';
            } );
        Functions\when( 'wp_get_attachment_image_src' )->alias( function ( $id, $size ) {
            return array( 'https://example.com/img.jpg', 100, 100, false );
        } );

        $result = Database_Auctions::query_for_listing();

        $this->assertSame( 'https://example.com/img.jpg', $result['items'][0]['image_url'] );
    }
}
```

- [ ] **Step 2: Run tests — confirm they fail**

```bash
./vendor/bin/phpunit tests/Database/Database_Auctions_Transform_Test.php -v
```

Expected: `FAIL` — `wc_get_product` IS currently being called.

- [ ] **Step 3: Add `use` statements to `class-database-auctions.php`**

At the top of `class-database-auctions.php` after the existing `namespace` line, add:

```php
use The_Another\Plugin\Aucteeno\Database\Eager_Loader;
use The_Another\Plugin\Aucteeno\Permalinks\Auction_Item_Permalinks;
```

> **Note:** The existing SELECT query already includes `p.post_name` (line ~238). No SQL change is needed for `Database_Auctions` — only the PHP transform loop needs replacing. Verify this before editing to avoid adding a duplicate column.

- [ ] **Step 4: Replace the transform loop in `query_for_listing()`**

Find and replace the block starting at `// Transform results to include additional data.` (line ~250) through the closing `}` of the foreach loop (line ~281):

```php
// Batch-prime caches before transform loop — eliminates N+1 wc_get_product() calls.
$ids = array_column( $results, 'auction_id' );
Eager_Loader::prime_post_meta( $ids );
$image_map = Eager_Loader::prime_images( $ids );

$location_codes = array_filter(
    array_merge(
        array_column( $results, 'location_country' ),
        array_column( $results, 'location_subdivision' )
    )
);
$term_map     = Eager_Loader::load_location_terms( $location_codes );
$auction_base = Auction_Item_Permalinks::get_auction_base();

$items = array();
foreach ( $results as $row ) {
    $auction_id = absint( $row['auction_id'] );
    $image_id   = $image_map[ $auction_id ] ?? 0;
    $image_src  = $image_id ? wp_get_attachment_image_src( $image_id, 'medium' ) : false;
    $image_url  = is_array( $image_src ) ? $image_src[0] : '';

    $items[] = array(
        'id'                           => $auction_id,
        'title'                        => $row['post_title'],
        'permalink'                    => home_url( user_trailingslashit( $auction_base . '/' . $row['post_name'] ) ),
        'image_url'                    => $image_url,
        'image_id'                     => $image_id,
        'user_id'                      => absint( $row['user_id'] ),
        'bidding_status'               => absint( $row['bidding_status'] ),
        'bidding_starts_at'            => absint( $row['bidding_starts_at'] ),
        'bidding_ends_at'              => absint( $row['bidding_ends_at'] ),
        'location_country'             => $row['location_country'],
        'location_subdivision'         => $row['location_subdivision'],
        'location_city'                => $row['location_city'],
        'location_country_term_id'     => $term_map[ $row['location_country'] ] ?? 0,
        'location_subdivision_term_id' => $term_map[ $row['location_subdivision'] ] ?? 0,
        'current_bid'                  => (float) get_post_meta( $auction_id, '_price', true ),
        'reserve_price'                => 0.0,
    );
}
```

- [ ] **Step 5: Run transform tests — confirm they pass**

```bash
./vendor/bin/phpunit tests/Database/Database_Auctions_Transform_Test.php -v
```

Expected: `OK (7 tests, ...)`

- [ ] **Step 6: Run full test suite — no regressions**

```bash
make test
```

- [ ] **Step 7: Commit**

```bash
git add includes/database/class-database-auctions.php tests/Database/Database_Auctions_Transform_Test.php
git commit -m "perf: replace N+1 wc_get_product() in Database_Auctions with batch eager loading"
```

---

## Chunk 3: Database_Items transform

### Task 5: SQL JOIN change — add `auction_post_name`

**Files:**
- Modify: `includes/database/class-database-items.php:236–256` (`query_for_listing_newest` SELECT)
- Modify: `includes/database/class-database-items.php:495–517` (`query_status_group` SELECT)

Both SELECT queries need an additional LEFT JOIN to get the parent auction's slug without extra queries.

- [ ] **Step 1: Add `auction_post_name` to `query_for_listing_newest()` SELECT**

In `query_for_listing_newest()` (line ~236), modify the SQL string:

```php
// Before:
$query_sql = "
    SELECT
        i.item_id,
        i.auction_id,
        ...
        p.post_title,
        p.post_name
    FROM {$table_name} i
    INNER JOIN {$posts_table} p ON i.item_id = p.ID AND p.post_status = 'publish'
    WHERE {$where_sql}
    ORDER BY p.post_date DESC, i.item_id DESC
    LIMIT %d OFFSET %d
";

// After (add ap.post_name column and LEFT JOIN):
$query_sql = "
    SELECT
        i.item_id,
        i.auction_id,
        ...
        p.post_title,
        p.post_name,
        ap.post_name AS auction_post_name
    FROM {$table_name} i
    INNER JOIN {$posts_table} p ON i.item_id = p.ID AND p.post_status = 'publish'
    LEFT JOIN {$posts_table} ap ON i.auction_id = ap.ID
    WHERE {$where_sql}
    ORDER BY p.post_date DESC, i.item_id DESC
    LIMIT %d OFFSET %d
";
```

- [ ] **Step 2: Add `auction_post_name` to `query_status_group()` SELECT**

Same change in `query_status_group()` (line ~495):

```php
// Add to SELECT list:
        ap.post_name AS auction_post_name

// Add to FROM clause (after INNER JOIN):
        LEFT JOIN {$posts_table} ap ON i.auction_id = ap.ID
```

- [ ] **Step 3: Run existing tests — confirm no regressions**

```bash
make test
```

(The `transform_results()` signature change comes next; the old signature is still in place so tests pass.)

- [ ] **Step 4: Commit SQL change**

```bash
git add includes/database/class-database-items.php
git commit -m "feat: add auction_post_name to Database_Items SQL queries via LEFT JOIN"
```

---

### Task 6: `transform_results()` refactor + callers update

**Files:**
- Modify: `includes/database/class-database-items.php` — `transform_results()`, `query_for_listing_newest()`, `query_for_listing_by_status()`
- Create: `tests/Database/Database_Items_Transform_Test.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Database/Database_Items_Transform_Test.php`:

```php
<?php
/**
 * Database_Items transform tests
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Database;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Database\Database_Items;

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

/**
 * Class Database_Items_Transform_Test
 */
class Database_Items_Transform_Test extends TestCase {

    private \Mockery\MockInterface $wpdb;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        $this->wpdb         = Mockery::mock( 'wpdb' );
        $this->wpdb->prefix = 'wp_';
        $this->wpdb->posts  = 'wp_posts';
        $GLOBALS['wpdb'] = $this->wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

        Functions\when( '_prime_post_caches' )->justReturn( null );
        Functions\when( 'get_post_meta' )->justReturn( '' );
        Functions\when( 'wp_get_attachment_image_src' )->justReturn( false );
        Functions\when( 'get_terms' )->justReturn( array() );
        Functions\when( 'get_term_meta' )->justReturn( '' );
        Functions\when( 'is_wp_error' )->justReturn( false );
        Functions\when( 'get_option' )->justReturn( 'auction' );
        Functions\when( 'sanitize_title' )->returnArg();
        Functions\when( 'home_url' )->returnArg();
        Functions\when( 'user_trailingslashit' )->returnArg();
    }

    protected function tearDown(): void {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    private function stub_wpdb_for_single_item( array $row ): void {
        $this->wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
        $this->wpdb->shouldReceive( 'get_var' )->andReturn( '1' );
        $this->wpdb->shouldReceive( 'get_results' )->andReturn( array( $row ) );
    }

    private function base_item_row( array $overrides = array() ): array {
        return array_merge(
            array(
                'item_id'           => 20,
                'auction_id'        => 10,
                'user_id'           => 1,
                'bidding_status'    => 10,
                'bidding_starts_at' => 1000,
                'bidding_ends_at'   => 9999,
                'lot_no'            => 'LOT-001',
                'lot_sort_key'      => 1,
                'location_country'  => 'US',
                'location_subdivision' => 'US:KS',
                'location_city'     => 'Wichita',
                'post_title'        => 'Test Item',
                'post_name'         => 'test-item',
                'auction_post_name' => 'test-auction',
            ),
            $overrides
        );
    }

    public function test_query_for_listing_does_not_call_wc_get_product(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row() );
        Functions\expect( 'wc_get_product' )->never();

        Database_Items::query_for_listing();
    }

    public function test_query_for_listing_item_has_image_id_field(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row() );
        Functions\when( 'get_post_meta' )
            ->alias( function ( $id, $key = null, $single = false ) {
                return '_thumbnail_id' === $key ? '99' : '';
            } );

        $result = Database_Items::query_for_listing();

        $this->assertArrayHasKey( 'image_id', $result['items'][0] );
        $this->assertSame( 99, $result['items'][0]['image_id'] );
    }

    public function test_current_bid_uses_current_bid_meta_when_running(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row( array( 'bidding_status' => 10 ) ) );
        Functions\when( 'get_post_meta' )
            ->alias( function ( $id, $key = null, $single = false ) {
                return '_aucteeno_current_bid' === $key ? '250.00' : '';
            } );

        $result = Database_Items::query_for_listing();

        $this->assertSame( 250.0, $result['items'][0]['current_bid'] );
    }

    public function test_current_bid_uses_asking_bid_meta_when_upcoming(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row( array( 'bidding_status' => 20 ) ) );
        Functions\when( 'get_post_meta' )
            ->alias( function ( $id, $key = null, $single = false ) {
                return '_aucteeno_asking_bid' === $key ? '100.00' : '';
            } );

        $result = Database_Items::query_for_listing();

        $this->assertSame( 100.0, $result['items'][0]['current_bid'] );
    }

    public function test_current_bid_uses_sold_price_meta_when_expired(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row( array( 'bidding_status' => 30 ) ) );
        Functions\when( 'get_post_meta' )
            ->alias( function ( $id, $key = null, $single = false ) {
                return '_aucteeno_sold_price' === $key ? '350.00' : '';
            } );

        $result = Database_Items::query_for_listing();

        $this->assertSame( 350.0, $result['items'][0]['current_bid'] );
    }

    public function test_reserve_price_is_always_zero(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row() );

        $result = Database_Items::query_for_listing();

        $this->assertSame( 0.0, $result['items'][0]['reserve_price'] );
    }

    public function test_permalink_built_from_auction_and_item_slugs(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row() );
        Functions\when( 'home_url' )->alias( function ( $path ) {
            return 'https://example.com' . $path;
        } );
        Functions\when( 'user_trailingslashit' )->alias( function ( $s ) {
            return rtrim( $s, '/' ) . '/';
        } );
        Functions\when( 'get_option' )
            ->alias( function ( $key, $default = '' ) {
                if ( 'aucteeno_auction_base' === $key ) return 'auction';
                if ( 'aucteeno_item_base' === $key ) return 'item';
                return $default;
            } );

        $result = Database_Items::query_for_listing();

        $this->assertStringContainsString( 'test-auction', $result['items'][0]['permalink'] );
        $this->assertStringContainsString( 'test-item', $result['items'][0]['permalink'] );
    }

    public function test_permalink_falls_back_for_orphaned_items(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row( array( 'auction_post_name' => null ) ) );
        Functions\expect( 'get_permalink' )
            ->once()
            ->with( 20 )
            ->andReturn( 'https://example.com/?p=20' );

        $result = Database_Items::query_for_listing();

        $this->assertSame( 'https://example.com/?p=20', $result['items'][0]['permalink'] );
    }

    public function test_query_for_listing_item_has_location_term_id_fields(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row() );

        $result = Database_Items::query_for_listing();

        $this->assertArrayHasKey( 'location_country_term_id', $result['items'][0] );
        $this->assertArrayHasKey( 'location_subdivision_term_id', $result['items'][0] );
    }

    public function test_query_for_listing_by_status_does_not_call_wc_get_product(): void {
        // Stub the three query_status_group() calls (running, upcoming, expired).
        $this->wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
        $this->wpdb->shouldReceive( 'get_var' )->andReturn( '1' );
        $this->wpdb->shouldReceive( 'get_results' )->andReturn( array( $this->base_item_row() ) );

        Functions\expect( 'wc_get_product' )->never();

        Database_Items::query_for_listing( 1, 25, 'status' );
    }

    public function test_query_for_listing_by_status_item_has_image_id_field(): void {
        $this->wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
        $this->wpdb->shouldReceive( 'get_var' )->andReturn( '1' );
        $this->wpdb->shouldReceive( 'get_results' )->andReturn( array(
            $this->base_item_row( array( 'bidding_status' => 10 ) ),
        ) );

        Functions\when( 'get_post_meta' )
            ->alias( function ( $id, $key = null, $single = false ) {
                return '_thumbnail_id' === $key ? '99' : '';
            } );

        $result = Database_Items::query_for_listing( 1, 25, 'status' );

        $this->assertArrayHasKey( 'image_id', $result['items'][0] );
        $this->assertSame( 99, $result['items'][0]['image_id'] );
    }
}
```

- [ ] **Step 2: Run tests — confirm they fail**

```bash
./vendor/bin/phpunit tests/Database/Database_Items_Transform_Test.php -v
```

Expected: various failures (wrong fields, `wc_get_product` still called).

- [ ] **Step 3: Add `use` statements to `class-database-items.php`**

```php
use The_Another\Plugin\Aucteeno\Database\Eager_Loader;
use The_Another\Plugin\Aucteeno\Permalinks\Auction_Item_Permalinks;
```

- [ ] **Step 4: Replace `transform_results()` with new signature and body**

> **Important:** The spec document (section 4) contains an inline code snippet showing `transform_results($results, $image_map, $term_map)` with only 3 arguments. This is incorrect in the spec. The correct signature (and the one used in Steps 5–6 below) takes 5 arguments: `$results, $image_map, $term_map, $auction_base, $item_base`. Use the code below, not the spec snippet.

Replace the entire `transform_results()` method (lines 626–665):

```php
/**
 * Transform raw database results to item data arrays.
 *
 * Reads image and price data from the WP object cache (primed by Eager_Loader).
 * Uses bidding_status from the HPS row to select the correct price meta key,
 * eliminating the hidden wp_get_post_terms() call inside Product_Item::get_price().
 *
 * @param array  $results      Raw database results (must include auction_post_name column).
 * @param array  $image_map    Map of item_id => attachment_id from Eager_Loader::prime_images().
 * @param array  $term_map     Map of location code => term_id from Eager_Loader::load_location_terms().
 * @param string $auction_base Auction URL base slug (e.g. 'auction').
 * @param string $item_base    Item URL base slug (e.g. 'item').
 * @return array Transformed item data.
 */
private static function transform_results(
    array $results,
    array $image_map,
    array $term_map,
    string $auction_base,
    string $item_base
): array {
    $items = array();

    foreach ( $results as $row ) {
        $item_id        = absint( $row['item_id'] );
        $bidding_status = absint( $row['bidding_status'] );

        $image_id  = $image_map[ $item_id ] ?? 0;
        $image_src = $image_id ? wp_get_attachment_image_src( $image_id, 'medium' ) : false;
        $image_url = is_array( $image_src ) ? $image_src[0] : '';

        $current_bid_key = match ( $bidding_status ) {
            10      => '_aucteeno_current_bid',
            20      => '_aucteeno_asking_bid',
            30      => '_aucteeno_sold_price',
            default => '_aucteeno_current_bid',
        };

        $auction_slug = $row['auction_post_name'] ?? '';
        if ( $auction_slug ) {
            $permalink = home_url( user_trailingslashit(
                $auction_base . '/' . $auction_slug . '/' . $item_base . '/' . $row['post_name']
            ) );
        } else {
            $permalink = get_permalink( $item_id );
        }

        $items[] = array(
            'id'                           => $item_id,
            'auction_id'                   => absint( $row['auction_id'] ),
            'title'                        => $row['post_title'],
            'permalink'                    => $permalink,
            'image_url'                    => $image_url,
            'image_id'                     => $image_id,
            'user_id'                      => absint( $row['user_id'] ),
            'bidding_status'               => $bidding_status,
            'bidding_starts_at'            => absint( $row['bidding_starts_at'] ),
            'bidding_ends_at'              => absint( $row['bidding_ends_at'] ),
            'lot_no'                       => $row['lot_no'],
            'lot_sort_key'                 => absint( $row['lot_sort_key'] ),
            'location_country'             => $row['location_country'],
            'location_subdivision'         => $row['location_subdivision'],
            'location_city'                => $row['location_city'],
            'location_country_term_id'     => $term_map[ $row['location_country'] ] ?? 0,
            'location_subdivision_term_id' => $term_map[ $row['location_subdivision'] ] ?? 0,
            'current_bid'                  => (float) get_post_meta( $item_id, $current_bid_key, true ),
            'reserve_price'                => 0.0,
        );
    }

    return $items;
}
```

- [ ] **Step 5: Update `query_for_listing_newest()` caller**

Replace the existing `return array(...)` block at the end of `query_for_listing_newest()` (the call to `self::transform_results($results)`) with:

```php
// Batch-prime caches before transform — eliminates N+1 wc_get_product() calls.
$ids = array_column( $results, 'item_id' );
Eager_Loader::prime_post_meta( $ids );
$image_map = Eager_Loader::prime_images( $ids );

$location_codes = array_filter(
    array_merge(
        array_column( $results, 'location_country' ),
        array_column( $results, 'location_subdivision' )
    )
);
$term_map     = Eager_Loader::load_location_terms( $location_codes );
$auction_base = Auction_Item_Permalinks::get_auction_base();
$item_base    = Auction_Item_Permalinks::get_item_base();

return array(
    'items' => self::transform_results( $results, $image_map, $term_map, $auction_base, $item_base ),
    'page'  => $page,
    'pages' => max( 1, (int) ceil( $total / $per_page ) ),
    'total' => $total,
);
```

- [ ] **Step 6: Update `query_for_listing_by_status()` caller**

In `query_for_listing_by_status()`, find the final `return array(...)` statement (after the three status group result merges). Add the same priming block before it:

```php
// Batch-prime caches before transform — all results merged first.
$ids = array_column( $results, 'item_id' );
Eager_Loader::prime_post_meta( $ids );
$image_map = Eager_Loader::prime_images( $ids );

$location_codes = array_filter(
    array_merge(
        array_column( $results, 'location_country' ),
        array_column( $results, 'location_subdivision' )
    )
);
$term_map     = Eager_Loader::load_location_terms( $location_codes );
$auction_base = Auction_Item_Permalinks::get_auction_base();
$item_base    = Auction_Item_Permalinks::get_item_base();

return array(
    'items' => self::transform_results( $results, $image_map, $term_map, $auction_base, $item_base ),
    'page'  => $page,
    'pages' => max( 1, (int) ceil( $total / $per_page ) ),
    'total' => $total,
);
```

- [ ] **Step 7: Run items transform tests — confirm all pass**

```bash
./vendor/bin/phpunit tests/Database/Database_Items_Transform_Test.php -v
```

Expected: `OK (11 tests, ...)`

- [ ] **Step 8: Run full test suite — no regressions**

```bash
make test
```

- [ ] **Step 9: Commit**

```bash
git add includes/database/class-database-items.php tests/Database/Database_Items_Transform_Test.php
git commit -m "perf: replace N+1 wc_get_product() in Database_Items with batch eager loading"
```

---

## Chunk 4: Block render files and Block_Data_Helper

### Task 7: `Block_Data_Helper::get_item_data()` — add `image_id`

**Files:**
- Create: `tests/Helpers/Block_Data_Helper_Test.php`
- Modify: `includes/helpers/class-block-data-helper.php:110–128`

- [ ] **Step 1: Write failing test**

Create `tests/Helpers/Block_Data_Helper_Test.php`:

```php
<?php
/**
 * Block_Data_Helper Tests
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Helpers;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Helpers\Block_Data_Helper;

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

/**
 * Class Block_Data_Helper_Test
 */
class Block_Data_Helper_Test extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_get_item_data_includes_image_id_field(): void {
        $wpdb         = Mockery::mock( 'wpdb' );
        $wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

        $product = Mockery::mock( 'WC_Product' );
        $product->shouldReceive( 'get_type' )->andReturn( 'aucteeno-ext-auction' );
        $product->shouldReceive( 'get_price' )->andReturn( '100' );
        $product->shouldReceive( 'get_id' )->andReturn( 5 );

        Functions\when( 'get_the_ID' )->justReturn( 5 );
        Functions\when( 'wc_get_product' )->justReturn( $product );
        Functions\when( 'is_wp_error' )->justReturn( false );

        $wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
        $wpdb->shouldReceive( 'get_row' )->andReturn( array(
            'id'                   => 5,
            'user_id'              => 1,
            'bidding_status'       => 10,
            'bidding_starts_at'    => 1000,
            'bidding_ends_at'      => 9999,
            'location_country'     => 'US',
            'location_subdivision' => '',
            'location_city'        => '',
        ) );

        $post       = new \stdClass();
        $post->post_title = 'Test Auction';
        Functions\when( 'get_post' )->justReturn( $post );
        Functions\when( 'get_permalink' )->justReturn( 'https://example.com/auction/test/' );

        // image_id now comes from get_post_meta, not wc_get_product->get_image_id()
        Functions\when( 'get_post_meta' )
            ->alias( function ( $id, $key = null, $single = false ) {
                return '_thumbnail_id' === $key ? '88' : '';
            } );
        Functions\when( 'wp_get_attachment_image_src' )->justReturn( array( 'https://example.com/img.jpg', 100, 100 ) );
        Functions\when( 'method_exists' )->justReturn( false );

        $data = Block_Data_Helper::get_item_data( 5 );

        $this->assertNotNull( $data );
        $this->assertArrayHasKey( 'image_id', $data );
        $this->assertSame( 88, $data['image_id'] );
    }

    public function test_get_item_data_does_not_call_wc_get_product_for_image(): void {
        $wpdb         = Mockery::mock( 'wpdb' );
        $wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

        $product = Mockery::mock( 'WC_Product' );
        $product->shouldReceive( 'get_type' )->andReturn( 'aucteeno-ext-auction' );
        $product->shouldReceive( 'get_price' )->andReturn( '100' );
        $product->shouldReceive( 'get_id' )->andReturn( 5 );

        // wc_get_product should be called EXACTLY ONCE (for type-check + price),
        // not a second time for the image.
        Functions\expect( 'wc_get_product' )
            ->once()
            ->andReturn( $product );

        Functions\when( 'get_the_ID' )->justReturn( 5 );
        Functions\when( 'is_wp_error' )->justReturn( false );

        $wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
        $wpdb->shouldReceive( 'get_row' )->andReturn( array(
            'id'                   => 5,
            'user_id'              => 1,
            'bidding_status'       => 10,
            'bidding_starts_at'    => 1000,
            'bidding_ends_at'      => 9999,
            'location_country'     => '',
            'location_subdivision' => '',
            'location_city'        => '',
        ) );

        $post             = new \stdClass();
        $post->post_title = 'Test';
        Functions\when( 'get_post' )->justReturn( $post );
        Functions\when( 'get_permalink' )->justReturn( 'https://example.com/' );
        Functions\when( 'get_post_meta' )->justReturn( '' );
        Functions\when( 'wp_get_attachment_image_src' )->justReturn( false );
        Functions\when( 'method_exists' )->justReturn( false );

        Block_Data_Helper::get_item_data( 5 );
    }
}
```

- [ ] **Step 2: Run tests — confirm they fail**

```bash
./vendor/bin/phpunit tests/Helpers/Block_Data_Helper_Test.php -v
```

Expected: `FAIL` — `image_id` not in return array; `wc_get_product` called twice.

- [ ] **Step 3: Update `Block_Data_Helper::get_item_data()` — lines 110–128**

Replace the image-loading block (lines 110–121) and add `image_id` to the return array (line 127):

> **Note:** There are two `wc_get_product()` calls in this method. The first (line ~45) is retained for type-checking and price. The second (line ~112) is inside the image-loading block — this is the one being removed. The "Before" snippet below is the image block only.

```php
// Before:
// Get image URL via WooCommerce product (respects Nexus image overrides).
$image_url = '';
$product   = wc_get_product( $post_id );  // Line ~112 — second call, being removed.
if ( $product ) {
    $image_id = $product->get_image_id();
    if ( $image_id ) {
        $image_src = wp_get_attachment_image_src( $image_id, 'medium' );
        if ( $image_src ) {
            $image_url = $image_src[0];
        }
    }
}

// After:
// Get image via postmeta — no WooCommerce product object needed.
$image_id  = (int) get_post_meta( $post_id, '_thumbnail_id', true );
$image_url = '';
if ( $image_id ) {
    $image_src = wp_get_attachment_image_src( $image_id, 'medium' );
    if ( is_array( $image_src ) ) {
        $image_url = $image_src[0];
    }
}
```

Then in the `$item_data` array definition, add `'image_id'` after `'image_url'`:

```php
$item_data = array(
    'id'                   => (int) $row['id'],
    'title'                => $post->post_title,
    'permalink'            => get_permalink( $post_id ),
    'image_url'            => $image_url,
    'image_id'             => $image_id,   // ← add this line
    'user_id'              => (int) $row['user_id'],
    // ... rest unchanged
);
```

- [ ] **Step 4: Run tests — confirm they pass**

```bash
./vendor/bin/phpunit tests/Helpers/Block_Data_Helper_Test.php -v
```

Expected: `OK (2 tests, ...)`

- [ ] **Step 5: Run full suite**

```bash
make test
```

- [ ] **Step 6: Commit**

```bash
git add includes/helpers/class-block-data-helper.php tests/Helpers/Block_Data_Helper_Test.php
git commit -m "feat: add image_id to Block_Data_Helper and remove redundant wc_get_product() call"
```

---

### Task 8: `field-image/render.php` — use `image_id` from context

**Files:**
- Modify: `blocks/field-image/render.php`

No unit tests for render.php files (they require full WordPress context). Manual verification steps provided below.

- [ ] **Step 1: Replace lines 23–57 of `blocks/field-image/render.php`**

Remove:
- The `$product_id` extraction (lines 23–27)
- The `wc_get_product()` call + early return (lines 29–33)
- The `$image_id = $product->get_image_id()` line (line 41)
- The `get_post_meta($image_id, '_wp_attachment_image_alt', true)` + `$product->get_image(...)` block (lines 54–57)

Full replacement (keep the `ob_start()` / `ob_get_clean()` wrapper intact):

```php
<?php
/**
 * Aucteeno Field Image Block - Server-Side Render
 *
 * @package Aucteeno
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$item_data = $block->context['aucteeno/item'] ?? null;
if ( ! $item_data ) {
    return '';
}

$is_link      = $attributes['isLink'] ?? true;
$aspect_ratio = $attributes['aspectRatio'] ?? '4/3';
$permalink    = $item_data['permalink'] ?? '#';
$title        = $item_data['title'] ?? '';
$image_id     = absint( $item_data['image_id'] ?? 0 );

$wrapper_classes    = 'aucteeno-field-image';
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $wrapper_classes ) );

$style = "aspect-ratio: {$aspect_ratio};";

ob_start();
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
    <?php if ( $is_link ) : ?>
        <a class="aucteeno-field-image__link" href="<?php echo esc_url( $permalink ); ?>" style="<?php echo esc_attr( $style ); ?>">
    <?php else : ?>
        <div class="aucteeno-field-image__wrapper" style="<?php echo esc_attr( $style ); ?>">
    <?php endif; ?>

    <?php if ( $image_id ) : ?>
        <?php
        echo wp_get_attachment_image(
            $image_id,
            'woocommerce_thumbnail',
            false,
            array( 'alt' => esc_attr( $title ), 'style' => $style )
        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    <?php else : ?>
        <?php echo wc_placeholder_img( 'woocommerce_thumbnail', array( 'style' => $style ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php endif; ?>

    <?php if ( $is_link ) : ?>
        </a>
    <?php else : ?>
        </div>
    <?php endif; ?>
</div>
<?php
echo ob_get_clean();
```

- [ ] **Step 2: Manual verification — listing page**

In WordPress admin, navigate to a page containing an `aucteeno/query-loop` block. Confirm:
- Auction/item images render correctly in the listing grid
- Items without a thumbnail show the WooCommerce placeholder image
- No PHP errors in `debug.log`
- Use Query Monitor (or `define('SAVEQUERIES', true)`) to confirm `wc_get_product` no longer appears in query list for this block

- [ ] **Step 3: Manual verification — single post page**

Navigate to a single auction/item page where `field-image` is used outside a query loop (via `Block_Data_Helper` fallback). Confirm image renders correctly.

- [ ] **Step 4: Commit**

```bash
git add blocks/field-image/render.php
git commit -m "perf: field-image reads image_id from context, removes per-item wc_get_product()"
```

---

### Task 9: `field-location/render.php` — use pre-loaded term IDs

**Files:**
- Modify: `blocks/field-location/render.php`

No unit tests for render.php. Manual verification provided.

The `$get_term_by_code` closure (lines 39–63) is **kept** but demoted to fallback. Every call site in the switch block (~15 total across 5 cases) changes from calling `$get_term_by_code(...)` unconditionally to reading from context first.

The pattern to apply at **every** `$get_term_by_code` call site:

```php
// Before:
$country_term_id = 0;
if ( $show_links && $country ) {
    $country_term_id = $get_term_by_code( $country, 0 );
}

$subdivision_term_id = 0;
if ( $show_links && $country && $subdivision_code ) {
    $subdivision_term_id = $get_term_by_code( $country . ':' . $subdivision_code, $country_term_id );
}

// After:
$country_term_id = $item_data['location_country_term_id']
    ?? ( $show_links && $country ? $get_term_by_code( $country, 0 ) : 0 );

$subdivision_term_id = $item_data['location_subdivision_term_id']
    ?? ( $show_links && $country && $subdivision_code
        ? $get_term_by_code( $country . ':' . $subdivision_code, $country_term_id )
        : 0 );
```

- [ ] **Step 1: Apply the pattern to all 5 switch cases**

The cases are `smart`, `country_only`, `city_subdivision`, `city_country`, and `default`. Search for every occurrence of `$get_term_by_code` in the switch block and apply the pattern above.

> **Note:** The spec (section 6) shows a shorter form without `&& $country` in the ternary. Use the form shown in the "After" snippet above (with `&& $country` and `&& $subdivision_code`) — it matches the existing call-site guards and avoids unnecessary closure invocations. There are approximately 7 `$get_term_by_code` call sites across the 5 cases; read the file carefully to update all of them.

For `country_only` and `city_country` cases (only country term needed):

```php
// Before:
$country_term_id = 0;
if ( $show_links && $country ) {
    $country_term_id = $get_term_by_code( $country, 0 );
}

// After:
$country_term_id = $item_data['location_country_term_id']
    ?? ( $show_links && $country ? $get_term_by_code( $country, 0 ) : 0 );
```

- [ ] **Step 2: Run full test suite — confirm no regressions**

```bash
make test
```

- [ ] **Step 3: Manual verification — listing with showLinks=true**

Configure a `field-location` block with `showLinks: true`. In a listing with items from known locations, verify:
- Location links render correctly with the right URLs
- Enable `SAVEQUERIES` and confirm `get_terms` is not called during block rendering for listing pages
- Navigate to a single item page and confirm location links still work (fallback path via `$get_term_by_code`)

- [ ] **Step 4: Commit**

```bash
git add blocks/field-location/render.php
git commit -m "perf: field-location reads pre-loaded term IDs from context, removes per-item get_terms()"
```

---

### Final: Run full test suite and lint

- [ ] **Step 1: Run all tests**

```bash
make test
```

Expected: all tests pass.

- [ ] **Step 2: Run linter**

```bash
make lint
```

Fix any PHPCS issues flagged in the new/modified files.

- [ ] **Step 3: Final commit (if lint fixes needed)**

```bash
git add -A
git commit -m "fix: PHPCS lint fixes for eager loading implementation"
```
