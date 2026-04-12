# Query Loop Order: Date Closing Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rename the current `ending_soon` sort to `status_ending_soon` and introduce a new `ending_soon` that merges running+upcoming into one group ordered by `bidding_ends_at`.

**Architecture:** The change touches the sort dispatch in two database classes, the Query_Orderer's UNION ALL queries, the REST API sort enums, and the block editor UI. The new `ending_soon` uses a 2-group approach (active+expired) instead of the current 3-group (running/upcoming/expired).

**Tech Stack:** PHP 8.3, WordPress/WooCommerce, Gutenberg (React), PHPUnit + Brain Monkey

**Spec:** `docs/superpowers/specs/2026-04-12-query-loop-order-date-closing-design.md`

---

## Chunk 1: Database Layer

### Task 1: Rename `ending_soon` to `status_ending_soon` in Database_Auctions

**Files:**
- Modify: `includes/database/class-database-auctions.php:141` (allowlist), `:198-220` (ORDER BY dispatch)

- [ ] **Step 1: Update the allowlist to accept both sort keys**

In `class-database-auctions.php:141`, change:
```php
$sort = in_array( $args['sort'], array( 'ending_soon', 'newest' ), true ) ? $args['sort'] : 'ending_soon';
```
to:
```php
$sort = in_array( $args['sort'], array( 'ending_soon', 'status_ending_soon', 'newest' ), true ) ? $args['sort'] : 'ending_soon';
```

- [ ] **Step 2: Add the new `ending_soon` ORDER BY and rename old to `status_ending_soon`**

In `class-database-auctions.php:198-220`, replace the ORDER BY block. The `$use_product_ids_order` branch stays untouched. Change:
```php
} elseif ( 'newest' === $sort ) {
    $order_sql = 'p.post_date DESC, a.auction_id DESC';
} else {
    // ending_soon: Running first (by ends_at ASC), then Upcoming (by starts_at ASC), then Expired (by ends_at DESC).
    $order_sql = '
        CASE a.bidding_status
            WHEN 10 THEN 1
            WHEN 20 THEN 2
            WHEN 30 THEN 3
            ELSE 4
        END ASC,
        CASE a.bidding_status
            WHEN 10 THEN a.bidding_ends_at
            WHEN 20 THEN a.bidding_starts_at
            ELSE -a.bidding_ends_at
        END ASC,
        a.auction_id ASC
    ';
}
```
to:
```php
} elseif ( 'newest' === $sort ) {
    $order_sql = 'p.post_date DESC, a.auction_id DESC';
} elseif ( 'status_ending_soon' === $sort ) {
    // status_ending_soon: Running first (by ends_at ASC), then Upcoming (by starts_at ASC), then Expired (by ends_at DESC).
    $order_sql = '
        CASE a.bidding_status
            WHEN 10 THEN 1
            WHEN 20 THEN 2
            WHEN 30 THEN 3
            ELSE 4
        END ASC,
        CASE a.bidding_status
            WHEN 10 THEN a.bidding_ends_at
            WHEN 20 THEN a.bidding_starts_at
            ELSE -a.bidding_ends_at
        END ASC,
        a.auction_id ASC
    ';
} else {
    // ending_soon (default): Active (running+upcoming) by ends_at ASC, then Expired by ends_at DESC.
    $order_sql = '
        CASE WHEN a.bidding_status IN (10, 20) THEN 1 ELSE 2 END ASC,
        CASE WHEN a.bidding_status IN (10, 20) THEN a.bidding_ends_at ELSE -a.bidding_ends_at END ASC,
        a.auction_id ASC
    ';
}
```

- [ ] **Step 3: Commit**

```bash
git add includes/database/class-database-auctions.php
git commit -m "feat: add ending_soon 2-group sort and rename old to status_ending_soon in auctions"
```

### Task 2: Rename `ending_soon` to `status_ending_soon` in Database_Items

**Files:**
- Modify: `includes/database/class-database-items.php:155` (allowlist), `:157-166` (dispatch), `:443-601` (query_for_listing_by_status)

- [ ] **Step 1: Update the allowlist and dispatch**

In `class-database-items.php:155-166`, change:
```php
$sort = in_array( $args['sort'], array( 'ending_soon', 'newest', 'lot_number' ), true ) ? $args['sort'] : 'ending_soon';

if ( 'newest' === $sort ) {
    return self::query_for_listing_newest( $args, $page, $per_page, $offset );
}

if ( 'lot_number' === $sort ) {
    return self::query_for_listing_by_lot( $args, $page, $per_page, $offset );
}

// For 'ending_soon' sort, use separate queries per status group.
return self::query_for_listing_by_status( $args, $page, $per_page, $offset );
```
to:
```php
$sort = in_array( $args['sort'], array( 'ending_soon', 'status_ending_soon', 'newest', 'lot_number' ), true ) ? $args['sort'] : 'ending_soon';

if ( 'newest' === $sort ) {
    return self::query_for_listing_newest( $args, $page, $per_page, $offset );
}

if ( 'lot_number' === $sort ) {
    return self::query_for_listing_by_lot( $args, $page, $per_page, $offset );
}

if ( 'status_ending_soon' === $sort ) {
    // 3-group sort: running by ends_at, upcoming by starts_at, expired by ends_at DESC.
    return self::query_for_listing_by_status( $args, $page, $per_page, $offset );
}

// Default ending_soon: 2-group sort (running+upcoming by ends_at, then expired).
return self::query_for_listing_ending_soon( $args, $page, $per_page, $offset );
```

- [ ] **Step 2: Add `query_for_listing_ending_soon` method**

Add a new private static method after `query_for_listing_by_status` (after line 601). This is a 2-group variant — running+upcoming combined, then expired:

```php
/**
 * Query items by date ending with 2 groups: active (running+upcoming) and expired.
 *
 * Active items (running + upcoming) are sorted by bidding_ends_at ASC.
 * Expired items follow, sorted by bidding_ends_at DESC.
 *
 * @param array $args     Query arguments.
 * @param int   $page     Current page number.
 * @param int   $per_page Items per page.
 * @param int   $offset   Global offset across all groups.
 * @return array Query result.
 */
private static function query_for_listing_ending_soon( array $args, int $page, int $per_page, int $offset ): array {
    global $wpdb;

    $table_name = self::get_table_name();

    // Build base WHERE clause (without status filter).
    $base_where_clauses = array();
    $where_values       = self::build_where_values( $args );

    if ( ! empty( $args['user_id'] ) ) {
        $base_where_clauses[] = 'i.user_id = %d';
    }
    if ( ! empty( $args['auction_id'] ) ) {
        $base_where_clauses[] = 'i.auction_id = %d';
    }
    if ( ! empty( $args['country'] ) ) {
        $base_where_clauses[] = 'i.location_country = %s';
    }
    if ( ! empty( $args['subdivision'] ) ) {
        $base_where_clauses[] = 'i.location_subdivision = %s';
    }

    // Product IDs filter.
    if ( ! empty( $args['product_ids'] ) && is_array( $args['product_ids'] ) ) {
        $product_ids          = array_map( 'absint', $args['product_ids'] );
        $product_ids          = array_filter( $product_ids );
        $placeholders         = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
        $base_where_clauses[] = "i.item_id IN ($placeholders)";
        $where_values         = array_merge( $where_values, $product_ids );
    }

    // Search filter (requires posts table join).
    if ( ! empty( $args['search'] ) ) {
        $base_where_clauses[] = 'p.post_title LIKE %s';
    }

    $base_where_sql = ! empty( $base_where_clauses ) ? implode( ' AND ', $base_where_clauses ) : '1=1';

    // Get counts for the 2 groups: active (running+upcoming) and expired.
    $counts = self::get_status_counts( $table_name, $base_where_sql, $where_values );

    $active_count  = $counts['running'] + $counts['upcoming'];
    $expired_count = $counts['expired'];
    $total         = $active_count + $expired_count;

    // Calculate which groups we need and their offsets/limits.
    $results          = array();
    $remaining_offset = $offset;
    $remaining_limit  = $per_page;

    // Group 1: Active items (status IN 10, 20) ordered by bidding_ends_at ASC.
    if ( $remaining_limit > 0 && $remaining_offset < $active_count ) {
        $group_offset = $remaining_offset;
        $group_limit  = min( $remaining_limit, $active_count - $group_offset );

        $active_results = self::query_combined_status_group(
            array( 10, 20 ),
            $base_where_sql,
            $where_values,
            'i.bidding_ends_at ASC, i.lot_sort_key ASC, i.item_id ASC',
            $group_limit,
            $group_offset
        );

        $results          = array_merge( $results, $active_results );
        $remaining_limit -= count( $active_results );
    }

    // Adjust offset for expired group.
    $remaining_offset = max( 0, $remaining_offset - $active_count );

    // Group 2: Expired items (status = 30) ordered by bidding_ends_at DESC.
    if ( $remaining_limit > 0 && $remaining_offset < $expired_count ) {
        $group_offset = $remaining_offset;
        $group_limit  = min( $remaining_limit, $expired_count - $group_offset );

        $expired_results = self::query_status_group(
            30,
            $base_where_sql,
            $where_values,
            'i.bidding_ends_at DESC, i.item_id DESC',
            $group_limit,
            $group_offset
        );

        $results = array_merge( $results, $expired_results );
    }

    if ( empty( $results ) ) {
        return array(
            'items' => array(),
            'page'  => $page,
            'pages' => max( 1, (int) ceil( $total / $per_page ) ),
            'total' => $total,
        );
    }

    // Batch-prime caches before transform — all results merged first.
    $ids = array_column( $results, 'item_id' );
    Eager_Loader::prime_post_meta( $ids );
    $image_map = Eager_Loader::prime_images( $ids );

    $merged_codes   = array_merge(
        array_column( $results, 'location_country' ),
        array_column( $results, 'location_subdivision' )
    );
    $location_codes = array_values( array_unique( array_filter( $merged_codes ) ) );
    $term_map       = Eager_Loader::load_location_terms( $location_codes );
    $auction_base   = Auction_Item_Permalinks::get_auction_base();
    $item_base      = Auction_Item_Permalinks::get_item_base();

    $items = self::transform_results( $results, $image_map, $term_map, $auction_base, $item_base );

    /** This filter is documented in includes/database/class-database-items.php */
    $items = (array) apply_filters( 'aucteeno_products_context_data', $items, $ids );

    return array(
        'items' => $items,
        'page'  => $page,
        'pages' => max( 1, (int) ceil( $total / $per_page ) ),
        'total' => $total,
    );
}
```

- [ ] **Step 3: Add `query_combined_status_group` helper method**

Add after `query_status_group` (the method that starts around line 670). This is like `query_status_group` but accepts multiple statuses:

```php
/**
 * Query a combined status group (multiple statuses).
 *
 * @param array  $statuses       Array of bidding statuses (e.g., [10, 20]).
 * @param string $base_where_sql Base WHERE clause (without status).
 * @param array  $where_values   Prepared statement values for base WHERE.
 * @param string $order_sql      ORDER BY clause.
 * @param int    $limit          Number of items to fetch.
 * @param int    $offset         Offset within this group.
 * @return array Raw database results.
 */
private static function query_combined_status_group(
    array $statuses,
    string $base_where_sql,
    array $where_values,
    string $order_sql,
    int $limit,
    int $offset
): array {
    global $wpdb;

    $table_name  = self::get_table_name();
    $posts_table = $wpdb->posts;

    // Build status placeholders and timestamp conditions.
    $status_conditions = array();
    foreach ( $statuses as $status ) {
        if ( 10 === $status ) {
            $status_conditions[] = '(i.bidding_status = 10 AND i.bidding_starts_at <= UNIX_TIMESTAMP() AND i.bidding_ends_at > UNIX_TIMESTAMP())';
        } elseif ( 20 === $status ) {
            $status_conditions[] = '(i.bidding_status = 20 AND i.bidding_starts_at > UNIX_TIMESTAMP())';
        } elseif ( 30 === $status ) {
            $status_conditions[] = '(i.bidding_status = 30 AND i.bidding_ends_at <= UNIX_TIMESTAMP())';
        }
    }
    $status_sql = implode( ' OR ', $status_conditions );

    // SELECT columns must match query_status_group exactly for transform_results compatibility.
    $query_sql = "
        SELECT
            i.item_id,
            i.auction_id,
            i.user_id,
            i.bidding_status,
            i.bidding_starts_at,
            i.bidding_ends_at,
            i.lot_no,
            i.lot_sort_key,
            i.location_country,
            i.location_subdivision,
            i.location_city,
            p.post_title,
            p.post_name,
            ap.post_name AS auction_post_name
        FROM {$table_name} i
        INNER JOIN {$posts_table} p ON i.item_id = p.ID AND p.post_status = 'publish'
        LEFT JOIN {$posts_table} ap ON i.auction_id = ap.ID AND ap.post_status = 'publish'
        WHERE {$base_where_sql}
          AND ({$status_sql})
        ORDER BY {$order_sql}
        LIMIT %d OFFSET %d
    ";

    $query_values   = array_merge( $where_values, array( $limit, $offset ) );
    $prepared_query = $wpdb->prepare( $query_sql, $query_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

    return $wpdb->get_results( $prepared_query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
}
```

- [ ] **Step 4: Run tests to verify nothing is broken**

```bash
./vendor/bin/phpunit tests/Database/Database_Items_SQL_Test.php
./vendor/bin/phpunit tests/Database/Database_Items_Transform_Test.php
```

Expected: Tests pass. The existing `ending_soon` tests now exercise the new 2-group path.

- [ ] **Step 5: Commit**

```bash
git add includes/database/class-database-items.php
git commit -m "feat: add ending_soon 2-group sort and rename old to status_ending_soon in items"
```

### Task 3: Update Query_Orderer with sort-awareness

**Files:**
- Modify: `includes/database/class-query-orderer.php:75-136` (maybe_apply_custom_ordering), `:199-300` (get_ordered_item_ids), `:308-406` (get_ordered_auction_ids), `:562-579` (get_cache_key)

- [ ] **Step 1: Read sort from WP_Query and store it**

In `maybe_apply_custom_ordering`, between lines 118 and 122 (after `aucteeno_order_type` is set, but BEFORE `get_ordered_ids` is called at line 122), add:
```php
// Read sort preference from query var (set by block render or REST API).
$sort = $query->get( 'aucteeno_sort' );
if ( ! in_array( $sort, array( 'ending_soon', 'status_ending_soon' ), true ) ) {
    $sort = 'ending_soon';
}
// phpcs:ignore WordPressVIPMinimum.Hooks.PreGetPosts.PreGetPosts -- Intentional query modification for custom product types.
$query->set( 'aucteeno_sort', $sort );
```

- [ ] **Step 2: Update `get_ordered_auction_ids` — add `ending_soon` 2-group SQL**

The method currently has one hardcoded 3-group UNION ALL. Refactor to check `$query->get( 'aucteeno_sort' )`:

- If `status_ending_soon`: use current 3-group UNION ALL (no change to SQL)
- If `ending_soon` (default): use a 2-group UNION ALL where running+upcoming are combined in one subquery ordered by `a.bidding_ends_at ASC`, and expired in a second subquery

The new 2-group SQL for auctions:
```sql
SELECT ordered.auction_id
FROM (
    (
        -- Active auctions (running + upcoming)
        SELECT a.auction_id, a.bidding_ends_at as sort_time, 1 as sort_group
        FROM {$auctions_table} a
        INNER JOIN {$wpdb->posts} p ON a.auction_id = p.ID
        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id AND tr.term_taxonomy_id = %d
        WHERE a.bidding_status IN (10, 20)
            AND (
                (a.bidding_status = 10 AND a.bidding_starts_at <= UNIX_TIMESTAMP() AND a.bidding_ends_at > UNIX_TIMESTAMP())
                OR (a.bidding_status = 20 AND a.bidding_starts_at > UNIX_TIMESTAMP())
            )
            AND p.post_status = 'publish'
            AND p.post_type = 'product'
            {$where_conditions}
        ORDER BY a.bidding_ends_at ASC, a.auction_id ASC
        LIMIT %d
    )
    UNION ALL
    (
        -- Expired auctions
        SELECT a.auction_id, a.bidding_ends_at as sort_time, 2 as sort_group
        FROM {$auctions_table} a
        INNER JOIN {$wpdb->posts} p ON a.auction_id = p.ID
        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id AND tr.term_taxonomy_id = %d
        WHERE a.bidding_status = 30
            AND a.bidding_ends_at <= UNIX_TIMESTAMP()
            AND p.post_status = 'publish'
            AND p.post_type = 'product'
            {$where_conditions}
        ORDER BY a.bidding_ends_at DESC, a.auction_id ASC
        LIMIT %d
    )
) AS ordered
ORDER BY
    ordered.sort_group ASC,
    CASE ordered.sort_group
        WHEN 1 THEN ordered.sort_time
        WHEN 2 THEN -ordered.sort_time
    END ASC,
    ordered.auction_id ASC
LIMIT %d OFFSET %d
```

Use an if/else within the method to select between the two SQL strings based on `$query->get( 'aucteeno_sort' )`. Keep the current 3-group SQL in the `status_ending_soon` branch and add the new 2-group SQL in the `else` (default `ending_soon`) branch.

- [ ] **Step 3: Update `get_ordered_item_ids` — add `ending_soon` 2-group SQL**

Same pattern as auctions. The new 2-group SQL for items:
```sql
SELECT ordered.item_id
FROM (
    (
        -- Active items (running + upcoming)
        SELECT i.item_id, i.bidding_ends_at as sort_time, i.lot_sort_key, 1 as sort_group
        FROM {$items_table} i
        INNER JOIN {$wpdb->posts} p ON i.item_id = p.ID
        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id AND tr.term_taxonomy_id = %d
        WHERE i.bidding_status IN (10, 20)
            AND (
                (i.bidding_status = 10 AND i.bidding_starts_at <= UNIX_TIMESTAMP() AND i.bidding_ends_at > UNIX_TIMESTAMP())
                OR (i.bidding_status = 20 AND i.bidding_starts_at > UNIX_TIMESTAMP())
            )
            AND p.post_status = 'publish'
            AND p.post_type = 'product'
            {$where_conditions}
        ORDER BY i.bidding_ends_at ASC, i.lot_sort_key ASC, i.item_id ASC
        LIMIT %d
    )
    UNION ALL
    (
        -- Expired items
        SELECT i.item_id, i.bidding_ends_at as sort_time, i.lot_sort_key, 2 as sort_group
        FROM {$items_table} i
        INNER JOIN {$wpdb->posts} p ON i.item_id = p.ID
        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id AND tr.term_taxonomy_id = %d
        WHERE i.bidding_status = 30
            AND i.bidding_ends_at <= UNIX_TIMESTAMP()
            AND p.post_status = 'publish'
            AND p.post_type = 'product'
            {$where_conditions}
        ORDER BY i.bidding_ends_at DESC, i.item_id ASC
        LIMIT %d
    )
) AS ordered
ORDER BY
    ordered.sort_group ASC,
    CASE ordered.sort_group
        WHEN 1 THEN ordered.sort_time
        WHEN 2 THEN -ordered.sort_time
    END ASC,
    ordered.lot_sort_key ASC,
    ordered.item_id ASC
LIMIT %d OFFSET %d
```

- [ ] **Step 4: Add sort to cache key**

In `get_cache_key` at line 566, add `'sort'` to the `$filters` array:
```php
$filters = array(
    'order_type' => $order_type,
    'sort'       => $query->get( 'aucteeno_sort' ),
    'page'       => $page,
    'per_page'   => $per_page,
    'parent'     => $query->get( 'post_parent' ),
    'search'     => $query->get( 's' ),
    // phpcs:ignore WordPress.DB.SlowDBQuery -- Required for taxonomy/meta filtering.
    'tax_query'  => $query->get( 'tax_query' ),
);
```

- [ ] **Step 5: Run tests**

```bash
./vendor/bin/phpunit tests/Query_Orderer_Test.php
```

Expected: PASS (if test file exists; if not, skip this step).

- [ ] **Step 6: Commit**

```bash
git add includes/database/class-query-orderer.php
git commit -m "feat: add sort-awareness to Query_Orderer with ending_soon 2-group queries"
```

---

## Chunk 2: REST API, Block Editor, and Tests

### Task 4: Update REST API sort enums and WP_Query code paths

**Files:**
- Modify: `includes/rest-api/class-rest-controller.php:106-111` (auctions sort enum), `:240-244` (items sort enum), `:487-493` (auctions WP_Query sort check), `:712-719` (items WP_Query sort check)

- [ ] **Step 1: Add `status_ending_soon` to both sort enums**

At line 110 (auctions endpoint schema):
```php
'enum' => array( 'ending_soon', 'status_ending_soon', 'newest', 'lot_number' ),
```

At line 244 (items endpoint schema):
```php
'enum' => array( 'ending_soon', 'status_ending_soon', 'newest', 'lot_number' ),
```

- [ ] **Step 2: Update the WP_Query sort checks**

At line 488 (auctions), change:
```php
if ( 'ending_soon' !== $sort ) {
```
to:
```php
if ( ! in_array( $sort, array( 'ending_soon', 'status_ending_soon' ), true ) ) {
```

At line 713 (items), change:
```php
if ( 'ending_soon' !== $sort ) {
```
to:
```php
if ( ! in_array( $sort, array( 'ending_soon', 'status_ending_soon' ), true ) ) {
```

- [ ] **Step 3: Pass sort to WP_Query as `aucteeno_sort`**

This must be added BEFORE `new WP_Query( $query_args )` so the Query_Orderer can read it in `pre_get_posts`.

In the auctions WP_Query path, add to the `$query_args` array at line 461-474 (after `'tax_query'`):
```php
$query_args = array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => $per_page,
    'paged'          => $page,
    'aucteeno_sort'  => $sort,
    // ... rest of existing keys
);
```

In the items WP_Query path, add `'aucteeno_sort' => $sort` to the existing `$query_args` array at line 679-694. Keep all existing keys intact (`orderby`, `order`, `tax_query`, etc.) — just insert the new key.

- [ ] **Step 4: Commit**

```bash
git add includes/rest-api/class-rest-controller.php
git commit -m "feat: accept status_ending_soon sort in REST API and pass to Query_Orderer"
```

### Task 5: Update block.json and editor.js

**Files:**
- Modify: `blocks/query-loop/block.json:58-62`
- Modify: `blocks/query-loop/src/editor.js:332-359`

- [ ] **Step 1: Update block.json enum**

At line 61, change:
```json
"enum": [ "ending_soon", "newest", "lot_number" ]
```
to:
```json
"enum": [ "ending_soon", "status_ending_soon", "newest", "lot_number" ]
```

- [ ] **Step 2: Update editor.js SelectControl options**

At lines 335-354, change the options array to:
```js
options={ [
    {
        label: __( 'Ending Soon', 'aucteeno' ),
        value: 'ending_soon',
    },
    {
        label: __(
            'By Status & Ending',
            'aucteeno'
        ),
        value: 'status_ending_soon',
    },
    {
        label: __( 'Newest First', 'aucteeno' ),
        value: 'newest',
    },
    ...( queryType === 'items'
        ? [
                {
                    label: __(
                        'Lot Number',
                        'aucteeno'
                    ),
                    value: 'lot_number',
                },
          ]
        : [] ),
] }
```

- [ ] **Step 3: Build blocks**

```bash
npm run build
```

Expected: Build succeeds with no errors.

- [ ] **Step 4: Commit**

```bash
git add blocks/query-loop/block.json blocks/query-loop/src/editor.js dist/
git commit -m "feat: add 'By Status & Ending' sort option in block editor"
```

### Task 6: Update and add tests

**Files:**
- Modify: `tests/Database/Database_Items_SQL_Test.php`
- Modify: `tests/Database/Database_Items_Transform_Test.php`
- Modify: `tests/REST_API/REST_Controller_Test.php`

- [ ] **Step 1: Update existing SQL test comment and add `status_ending_soon` test**

In `Database_Items_SQL_Test.php`, the existing test at line 161 passes `'sort' => 'ending_soon'` which now exercises the new 2-group path. Update the comment at line 107 from `ending_soon / by_status path` to `ending_soon / ending_soon path`.

Add a new test method that passes `'sort' => 'status_ending_soon'` and asserts the 3-group behavior (same assertions as the existing test — it should call `prepare()` twice for running+upcoming groups):

Follow the same mock pattern as the existing `test_query_status_group_contains_auction_post_name` test but pass `'sort' => 'status_ending_soon'`. Use `->times(3)` for `get_results` (1 count query + 2 status groups) with `->andReturn()` returning the count rows first, then empty arrays. Use `->twice()` for `prepare` (one per status group query). The key assertion is that `prepare()` is called twice, confirming the 3-group `query_for_listing_by_status` path was taken.

- [ ] **Step 2: Update the existing `ending_soon` SQL test**

The test at line 161 now exercises the new 2-group `query_for_listing_ending_soon` path which calls `query_combined_status_group` instead of `query_status_group` twice. The mock expectations need adjusting:
- `get_results`: called twice total (1 count query + 1 combined status group query)
- `prepare`: called once (for the combined status group query; count query skips prepare when `$where_values` is empty)
- The captured SQL should contain both status conditions (e.g., `bidding_status = 10` and `bidding_status = 20` in a single query)

- [ ] **Step 3: Add `status_ending_soon` to REST controller test sort expectations**

In `REST_Controller_Test.php`, find the lines at 248 and 927 that check `$args['sort'] === 'ending_soon'` and ensure equivalent test coverage exists for `status_ending_soon`. Add a test that sends `sort=status_ending_soon` to the REST endpoint and verifies it's accepted (not rejected by enum validation).

- [ ] **Step 4: Run all tests**

```bash
make test
```

Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add tests/
git commit -m "test: add status_ending_soon tests and update ending_soon test expectations"
```

- [ ] **Step 6: Run linter**

```bash
make lint
npm run lint:js
```

Expected: No new violations. Fix any issues before proceeding.

- [ ] **Step 7: Final commit (if lint fixes needed)**

```bash
git add -A
git commit -m "style: fix lint violations"
```
