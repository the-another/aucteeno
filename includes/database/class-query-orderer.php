<?php
/**
 * Query Orderer Class
 *
 * Integrates custom ordering from aucteeno_items and aucteeno_auctions tables into WP_Query.
 *
 * @package Aucteeno
 * @since 2.2.0
 */

namespace TheAnother\Plugin\Aucteeno\Database;

use TheAnother\Plugin\Aucteeno\Product_Types\Product_Auction;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Item;
use WP_Query;

/**
 * Class Query_Orderer
 *
 * Handles custom ordering for auction and item listings.
 */
class Query_Orderer {

	/**
	 * Cache group for ordered IDs.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'aucteeno_ordered';

	/**
	 * Cache TTL for ordered IDs (5 minutes).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 300;

	/**
	 * Cache TTL for counts (15 minutes).
	 *
	 * @var int
	 */
	private const COUNT_CACHE_TTL = 900;

	/**
	 * Cache key for product type term_taxonomy_id.
	 *
	 * @var string
	 */
	private const PRODUCT_TYPE_TTID_CACHE_KEY = 'aucteeno_product_type_ttid';

	/**
	 * Cache TTL for term_taxonomy_id (1 hour).
	 *
	 * @var int
	 */
	private const TTID_CACHE_TTL = 3600;

	/**
	 * Initialize the query orderer.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'pre_get_posts', array( $this, 'maybe_apply_custom_ordering' ), 20 );
		add_filter( 'found_posts', array( $this, 'found_posts' ), 10, 2 );
	}

	/**
	 * Detect and flag queries that should use custom ordering.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 * @return void
	 */
	public function maybe_apply_custom_ordering( WP_Query $query ): void {
		// Only for product post type.
		if ( 'product' !== $query->get( 'post_type' ) ) {
			return;
		}

		// Skip admin queries unless explicitly enabled.
		if ( is_admin() && ! $query->get( 'aucteeno_force_custom_order' ) ) {
			return;
		}

		// Skip single post queries.
		if ( $query->is_singular() ) {
			return;
		}

		// Check if query has product_type taxonomy filter.
		$tax_query = $query->get( 'tax_query' );
		if ( empty( $tax_query ) || ! is_array( $tax_query ) ) {
			return;
		}

		// Find product_type taxonomy in tax_query.
		$product_type = null;
		foreach ( $tax_query as $tax ) {
			if ( isset( $tax['taxonomy'] ) && 'product_type' === $tax['taxonomy'] ) {
				$product_type = $tax['terms'] ?? null;
				if ( is_array( $product_type ) && ! empty( $product_type ) ) {
					$product_type = $product_type[0];
				}
				break;
			}
		}

		// Only apply to auction or item product types.
		if ( ! in_array( $product_type, array( Product_Auction::PRODUCT_TYPE, Product_Item::PRODUCT_TYPE ), true ) ) {
			return;
		}

		// Flag this query for custom ordering.
		$query->set( 'aucteeno_custom_order', true );
		$query->set( 'aucteeno_order_type', Product_Item::PRODUCT_TYPE === $product_type ? 'items' : 'auctions' );

		// Get ordered IDs and set post__in + orderby=post__in.
		// This is simpler and more reliable than custom JOIN + GROUP BY.
		$ordered_ids = $this->get_ordered_ids( $query );
		if ( ! empty( $ordered_ids ) ) {
			$query->set( 'post__in', $ordered_ids );
			$query->set( 'orderby', 'post__in' );
			// Store for found_posts calculation.
			$query->set( 'aucteeno_ordered_ids', $ordered_ids );
		} else {
			// No results - set impossible condition.
			$query->set( 'post__in', array( 0 ) );
		}
	}


	/**
	 * Override found_posts count.
	 *
	 * @param int      $found_posts The found posts count.
	 * @param WP_Query $query The WP_Query instance.
	 * @return int Modified found posts count.
	 */
	public function found_posts( int $found_posts, WP_Query $query ): int {
		if ( ! $query->get( 'aucteeno_custom_order' ) ) {
			return $found_posts;
		}

		// Use cached count if available.
		$cached_count = $query->get( 'aucteeno_total_count' );
		if ( null !== $cached_count ) {
			return (int) $cached_count;
		}

		// Get count from custom query.
		$count = $this->get_total_count( $query );
		$query->set( 'aucteeno_total_count', $count );

		return $count;
	}

	/**
	 * Get ordered IDs for the query.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 * @return array<int> Array of ordered post IDs.
	 */
	private function get_ordered_ids( WP_Query $query ): array {
		// Check cache first.
		$cache_key = $this->get_cache_key( $query, 'ids' );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		// Generate ordered IDs.
		$order_type = $query->get( 'aucteeno_order_type' );
		if ( 'items' === $order_type ) {
			$ordered_ids = $this->get_ordered_item_ids( $query );
		} else {
			$ordered_ids = $this->get_ordered_auction_ids( $query );
		}

		// Cache the result.
		wp_cache_set( $cache_key, $ordered_ids, self::CACHE_GROUP, self::CACHE_TTL );

		return $ordered_ids;
	}

	/**
	 * Get ordered item IDs using custom SQL with optimized queries.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 * @return array<int> Array of ordered item IDs.
	 */
	private function get_ordered_item_ids( WP_Query $query ): array {
		global $wpdb;

		$items_table = Database_Items::get_table_name();
		$page        = max( 1, (int) $query->get( 'paged' ) );
		$per_page    = max( 1, (int) $query->get( 'posts_per_page' ) );

		// Get pre-resolved term_taxonomy_id for product_type.
		$ttid = $this->get_product_type_ttid( Product_Item::PRODUCT_TYPE );

		// Build WHERE conditions from query filters.
		$where_conditions = $this->build_where_conditions( $query, 'items' );

		// Use OFFSET-based pagination with optimized subqueries.
		$offset = ( $page - 1 ) * $per_page;

		// Fetch per_page items from each status group.
		// The outer query will then order across groups and apply final LIMIT/OFFSET.
		$fetch_per_group = $per_page;

		// Build optimized UNION ALL query with ORDER BY and LIMIT in each subquery.
		// Each subquery must be wrapped in parentheses when using ORDER BY/LIMIT with UNION ALL.
		$sql = "
		SELECT ordered.item_id
		FROM (
			(
				-- Running items (status = 10) - ordered and limited early
				-- Only include items that have actually started and not yet ended
				SELECT i.item_id, i.bidding_ends_at as sort_time, i.lot_sort_key, 1 as sort_group
				FROM {$items_table} i
				INNER JOIN {$wpdb->posts} p ON i.item_id = p.ID
				INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id AND tr.term_taxonomy_id = %d
				WHERE i.bidding_status = 10
					AND i.bidding_starts_at <= UNIX_TIMESTAMP()
					AND i.bidding_ends_at > UNIX_TIMESTAMP()
					AND p.post_status = 'publish'
					AND p.post_type = 'product'
					{$where_conditions}
				ORDER BY i.bidding_ends_at ASC, i.lot_sort_key ASC, i.item_id ASC
				LIMIT %d
			)
			UNION ALL
			(
				-- Upcoming items (status = 20) - ordered and limited early
				-- Only include items that haven't started yet
				SELECT i.item_id, i.bidding_starts_at as sort_time, i.lot_sort_key, 2 as sort_group
				FROM {$items_table} i
				INNER JOIN {$wpdb->posts} p ON i.item_id = p.ID
				INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id AND tr.term_taxonomy_id = %d
				WHERE i.bidding_status = 20
					AND i.bidding_starts_at > UNIX_TIMESTAMP()
					AND p.post_status = 'publish'
					AND p.post_type = 'product'
					{$where_conditions}
				ORDER BY i.bidding_starts_at ASC, i.lot_sort_key ASC, i.item_id ASC
				LIMIT %d
			)
			UNION ALL
			(
				-- Expired items (status = 30) - ordered and limited early
				-- Only include items that have already ended
				SELECT i.item_id, i.bidding_ends_at as sort_time, i.lot_sort_key, 3 as sort_group
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
				WHEN 2 THEN ordered.sort_time
				WHEN 3 THEN -ordered.sort_time
			END ASC,
			ordered.lot_sort_key ASC,
			ordered.item_id ASC
		LIMIT %d OFFSET %d";

		$results = $wpdb->get_col(
			$wpdb->prepare(
				$sql,
				$ttid,
				$fetch_per_group,
				$ttid,
				$fetch_per_group,
				$ttid,
				$fetch_per_group,
				$per_page,
				$offset
			)
		);

		return array_map( 'absint', $results );
	}

	/**
	 * Get ordered auction IDs using custom SQL with optimized queries.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 * @return array<int> Array of ordered auction IDs.
	 */
	private function get_ordered_auction_ids( WP_Query $query ): array {
		global $wpdb;

		$auctions_table = Database_Auctions::get_table_name();
		$page           = max( 1, (int) $query->get( 'paged' ) );
		$per_page       = max( 1, (int) $query->get( 'posts_per_page' ) );

		// Get pre-resolved term_taxonomy_id for product_type.
		$ttid = $this->get_product_type_ttid( Product_Auction::PRODUCT_TYPE );

		// Build WHERE conditions from query filters.
		$where_conditions = $this->build_where_conditions( $query, 'auctions' );

		// Fetch per_page items from each status group.
		// The outer query will then order across groups and apply final LIMIT/OFFSET.
		$fetch_per_group = $per_page;
		$offset          = ( $page - 1 ) * $per_page;

		// Build optimized UNION ALL query with ORDER BY and LIMIT in each subquery.
		// Each subquery must be wrapped in parentheses when using ORDER BY/LIMIT with UNION ALL.
		$sql = "
		SELECT ordered.auction_id
		FROM (
			(
				-- Running auctions (status = 10) - ordered and limited early
				-- Only include auctions that have actually started and not yet ended
				SELECT a.auction_id, a.bidding_ends_at as sort_time, 1 as sort_group
				FROM {$auctions_table} a
				INNER JOIN {$wpdb->posts} p ON a.auction_id = p.ID
				INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id AND tr.term_taxonomy_id = %d
				WHERE a.bidding_status = 10
					AND a.bidding_starts_at <= UNIX_TIMESTAMP()
					AND a.bidding_ends_at > UNIX_TIMESTAMP()
					AND p.post_status = 'publish'
					AND p.post_type = 'product'
					{$where_conditions}
				ORDER BY a.bidding_ends_at ASC, a.auction_id ASC
				LIMIT %d
			)
			UNION ALL
			(
				-- Upcoming auctions (status = 20) - ordered and limited early
				-- Only include auctions that haven't started yet
				SELECT a.auction_id, a.bidding_starts_at as sort_time, 2 as sort_group
				FROM {$auctions_table} a
				INNER JOIN {$wpdb->posts} p ON a.auction_id = p.ID
				INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id AND tr.term_taxonomy_id = %d
				WHERE a.bidding_status = 20
					AND a.bidding_starts_at > UNIX_TIMESTAMP()
					AND p.post_status = 'publish'
					AND p.post_type = 'product'
					{$where_conditions}
				ORDER BY a.bidding_starts_at ASC, a.auction_id ASC
				LIMIT %d
			)
			UNION ALL
			(
				-- Expired auctions (status = 30) - ordered and limited early
				-- Only include auctions that have already ended
				SELECT a.auction_id, a.bidding_ends_at as sort_time, 3 as sort_group
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
				WHEN 2 THEN ordered.sort_time
				WHEN 3 THEN -ordered.sort_time
			END ASC,
			ordered.auction_id ASC
		LIMIT %d OFFSET %d";

		$results = $wpdb->get_col(
			$wpdb->prepare(
				$sql,
				$ttid,
				$fetch_per_group,
				$ttid,
				$fetch_per_group,
				$ttid,
				$fetch_per_group,
				$per_page,
				$offset
			)
		);

		return array_map( 'absint', $results );
	}

	/**
	 * Build WHERE conditions from WP_Query filters.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 * @param string   $type Either 'items' or 'auctions'.
	 * @return string SQL WHERE conditions.
	 */
	private function build_where_conditions( WP_Query $query, string $type ): string {
		global $wpdb;

		$conditions  = array();
		$table_alias = 'items' === $type ? 'i' : 'a';

		// Handle auction_id filter (for items: post_parent, for auctions: not applicable but check anyway).
		if ( 'items' === $type ) {
			$auction_id = $query->get( 'post_parent' );
			if ( $auction_id > 0 ) {
				$conditions[] = $wpdb->prepare( "{$table_alias}.auction_id = %d", $auction_id );
			}
		}

		// Handle location filters (using table columns, not taxonomy).
		// Location is stored in custom tables, so we can filter directly.
		$tax_query = $query->get( 'tax_query' );
		if ( ! empty( $tax_query ) && is_array( $tax_query ) ) {
			foreach ( $tax_query as $tax ) {
				if ( isset( $tax['taxonomy'] ) && 'aucteeno-location' === $tax['taxonomy'] ) {
					// Location filtering via taxonomy - would need additional joins.
					// For now, skip location taxonomy filtering in custom SQL.
					// This will be handled by WP_Query's tax_query after our custom ordering.
				}
			}
		}

		// Handle search.
		$search = $query->get( 's' );
		if ( ! empty( $search ) ) {
			$conditions[] = $wpdb->prepare( 'p.post_title LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
		}

		if ( empty( $conditions ) ) {
			return '';
		}

		return ' AND ' . implode( ' AND ', $conditions );
	}

	/**
	 * Get total count for pagination.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 * @return int Total count.
	 */
	private function get_total_count( WP_Query $query ): int {
		// Check cache first.
		$cache_key = $this->get_cache_key( $query, 'count' );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		global $wpdb;

		$order_type = $query->get( 'aucteeno_order_type' );
		if ( 'items' === $order_type ) {
			$table_name = Database_Items::get_table_name();
			$id_column  = 'item_id';
			$ttid       = $this->get_product_type_ttid( Product_Item::PRODUCT_TYPE );
		} else {
			$table_name = Database_Auctions::get_table_name();
			$id_column  = 'auction_id';
			$ttid       = $this->get_product_type_ttid( Product_Auction::PRODUCT_TYPE );
		}

		$where_conditions = $this->build_where_conditions( $query, $order_type );

		// Optimized count query with minimal join and timestamp validation.
		$sql = "
		SELECT COUNT(*)
		FROM (
			SELECT 1
			FROM {$table_name} t
			INNER JOIN {$wpdb->posts} p ON t.{$id_column} = p.ID
			INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id AND tr.term_taxonomy_id = %d
			WHERE (
					(t.bidding_status = 10 AND t.bidding_starts_at <= UNIX_TIMESTAMP() AND t.bidding_ends_at > UNIX_TIMESTAMP())
					OR (t.bidding_status = 20 AND t.bidding_starts_at > UNIX_TIMESTAMP())
					OR (t.bidding_status = 30 AND t.bidding_ends_at <= UNIX_TIMESTAMP())
				)
				AND p.post_status = 'publish'
				AND p.post_type = 'product'
				{$where_conditions}
			LIMIT 1
		) AS count_check";

		$count = (int) $wpdb->get_var( $wpdb->prepare( $sql, $ttid ) );

		// Cache the result.
		wp_cache_set( $cache_key, $count, self::CACHE_GROUP, self::COUNT_CACHE_TTL );

		return $count;
	}

	/**
	 * Get pre-resolved term_taxonomy_id for product type.
	 *
	 * @param string $product_type_slug Product type slug.
	 * @return int Term taxonomy ID.
	 */
	private function get_product_type_ttid( string $product_type_slug ): int {
		$cache_key = self::PRODUCT_TYPE_TTID_CACHE_KEY . '_' . $product_type_slug;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		global $wpdb;

		// Get term_taxonomy_id directly without joins.
		$ttid = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT tt.term_taxonomy_id
				FROM {$wpdb->term_taxonomy} tt
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE tt.taxonomy = 'product_type'
					AND t.slug = %s
				LIMIT 1",
				$product_type_slug
			)
		);

		$ttid = (int) ( $ttid ?? 0 );

		// Cache the result.
		if ( $ttid > 0 ) {
			wp_cache_set( $cache_key, $ttid, self::CACHE_GROUP, self::TTID_CACHE_TTL );
		}

		return $ttid;
	}

	/**
	 * Generate cache key for query.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 * @param string   $suffix Cache key suffix ('ids' or 'count').
	 * @return string Cache key.
	 */
	private function get_cache_key( WP_Query $query, string $suffix ): string {
		$order_type = $query->get( 'aucteeno_order_type' );
		$page       = $query->get( 'paged' );
		$per_page   = $query->get( 'posts_per_page' );
		$filters    = array(
			'order_type' => $order_type,
			'page'       => $page,
			'per_page'   => $per_page,
			'parent'     => $query->get( 'post_parent' ),
			'search'     => $query->get( 's' ),
			'tax_query'  => $query->get( 'tax_query' ),
		);

		$key = 'aucteeno_ordered_' . md5( serialize( $filters ) ) . '_' . $suffix;
		return $key;
	}

	/**
	 * Invalidate cache for a post type.
	 *
	 * @param string $post_type Post type ('items' or 'auctions').
	 * @return void
	 */
	public static function invalidate_cache( string $post_type = '' ): void {
		// WordPress object cache doesn't support group flushing easily.
		// For now, we rely on TTL expiration.
		// In production with Redis/Memcached, implement proper group invalidation.
	}
}
