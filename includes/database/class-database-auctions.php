<?php
/**
 * Auctions Database Table Class
 *
 * Manages the auctions custom database table.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace The_Another\Plugin\Aucteeno\Database;

use The_Another\Plugin\Aucteeno\Database\Eager_Loader;

/**
 * Class Database_Auctions
 *
 * Handles auctions table creation and schema using dbDelta.
 */
class Database_Auctions {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	private const TABLE_NAME = 'aucteeno_auctions';

	/**
	 * Cache group for expired counts.
	 *
	 * @var string
	 */
	private const EXPIRED_COUNT_CACHE_GROUP = 'aucteeno_expired_auctions';

	/**
	 * Cache TTL for expired auction counts (seconds).
	 *
	 * @var int
	 */
	private const EXPIRED_COUNT_TTL = 60;

	/**
	 * Get full table name with prefix.
	 *
	 * @return string Table name with prefix.
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Get table schema for dbDelta.
	 *
	 * @return string CREATE TABLE SQL statement.
	 */
	public static function get_schema(): string {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
		ID bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		auction_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
		user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
		bidding_status tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
		bidding_starts_at int(10) UNSIGNED NOT NULL DEFAULT 0,
		bidding_ends_at int(10) UNSIGNED NOT NULL DEFAULT 0,
		location_country varchar(2) NOT NULL DEFAULT '',
		location_subdivision varchar(50) NOT NULL DEFAULT '',
		location_city varchar(50) NOT NULL DEFAULT '',
		location_lat float NOT NULL DEFAULT 0,
		location_lng float NOT NULL DEFAULT 0,
		PRIMARY KEY  (ID),
		UNIQUE KEY auction_id (auction_id),
		KEY user_id (user_id),
		KEY bidding_status (bidding_status),
		KEY bidding_starts_at (bidding_starts_at),
		KEY bidding_ends_at (bidding_ends_at),
		KEY location_country (location_country),
		KEY location_subdivision (location_subdivision),
		KEY location_city (location_city),
		KEY idx_user_running (user_id, bidding_status, bidding_ends_at, auction_id),
		KEY idx_user_upcoming (user_id, bidding_status, bidding_starts_at, auction_id),
		KEY idx_user_expired (user_id, bidding_status, bidding_ends_at, auction_id),
		KEY idx_running_auctions (bidding_status, bidding_ends_at, auction_id),
		KEY idx_upcoming_auctions (bidding_status, bidding_starts_at, auction_id),
		KEY idx_expired_auctions (bidding_status, bidding_ends_at, auction_id),
		KEY idx_location_status_ends (location_country, location_subdivision, bidding_status, bidding_ends_at)
	) {$charset_collate};";

		return $sql;
	}

	/**
	 * Create or update the table using dbDelta.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = self::get_schema();
		dbDelta( $sql );
	}

	/**
	 * Query auctions for block listing.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type int    $page        Page number (default 1).
	 *     @type int    $per_page    Items per page (default 12, max 50).
	 *     @type string $sort        Sort order: 'ending_soon' or 'newest'.
	 *     @type int    $user_id     Filter by user/vendor ID.
	 *     @type string $country     Filter by location country.
	 *     @type string $subdivision Filter by location subdivision.
	 *     @type string $search      Search keyword for post title.
	 *     @type array  $product_ids Array of product IDs to filter by.
	 * }
	 * @return array {
	 *     Query result.
	 *
	 *     @type array $items Array of auction data.
	 *     @type int   $page  Current page.
	 *     @type int   $pages Total pages.
	 *     @type int   $total Total auctions.
	 * }
	 */
	public function query_for_listing( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'page'        => 1,
			'per_page'    => 12,
			'sort'        => 'ending_soon',
			'user_id'     => 0,
			'country'     => '',
			'subdivision' => '',
			'search'      => '',
			'product_ids' => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		$page     = max( 1, absint( $args['page'] ) );
		$per_page = min( 50, max( 1, absint( $args['per_page'] ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$sort     = in_array( $args['sort'], array( 'ending_soon', 'status_ending_soon', 'newest' ), true ) ? $args['sort'] : 'ending_soon';

		$table_name  = self::get_table_name();
		$posts_table = $wpdb->posts;

		// Build base WHERE clauses (without status filter).
		$base_where_clauses = array();
		$where_values       = array();

		// User filter.
		if ( ! empty( $args['user_id'] ) ) {
			$base_where_clauses[] = 'a.user_id = %d';
			$where_values[]       = absint( $args['user_id'] );
		}

		// Location filters.
		if ( ! empty( $args['country'] ) ) {
			$base_where_clauses[] = 'a.location_country = %s';
			$where_values[]       = sanitize_text_field( $args['country'] );
		}
		if ( ! empty( $args['subdivision'] ) ) {
			$base_where_clauses[] = 'a.location_subdivision = %s';
			$where_values[]       = sanitize_text_field( $args['subdivision'] );
		}

		// Search filter (requires posts table join which is already present).
		if ( ! empty( $args['search'] ) ) {
			$base_where_clauses[] = 'p.post_title LIKE %s';
			$where_values[]       = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		}

		// Product IDs filter.
		$use_product_ids_order = false;
		if ( ! empty( $args['product_ids'] ) && is_array( $args['product_ids'] ) ) {
			$product_ids = array_map( 'absint', $args['product_ids'] );
			$product_ids = array_filter( $product_ids );
			if ( ! empty( $product_ids ) ) {
				$placeholders          = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
				$base_where_clauses[]  = "a.auction_id IN ($placeholders)";
				$where_values          = array_merge( $where_values, $product_ids );
				$use_product_ids_order = true;
			}
		}

		$base_where_sql = ! empty( $base_where_clauses ) ? implode( ' AND ', $base_where_clauses ) : '1=1';

		// Count via per-status queries for optimal index usage.
		$total = $this->get_total_status_count( $table_name, $posts_table, $base_where_sql, $where_values );

		// Status filter for the data query (LIMIT-bounded, less critical).
		$status_filter = '(
				(a.bidding_status = 10 AND a.bidding_starts_at <= UNIX_TIMESTAMP() AND a.bidding_ends_at > UNIX_TIMESTAMP())
				OR (a.bidding_status = 20 AND a.bidding_starts_at > UNIX_TIMESTAMP())
				OR (a.bidding_status = 30 AND a.bidding_ends_at <= UNIX_TIMESTAMP())
			)';
		$where_sql     = $base_where_sql . ' AND ' . $status_filter;

		// Build ORDER BY.
		if ( $use_product_ids_order ) {
			// Preserve the order of the provided product IDs array.
			$field_placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
				$order_sql      = $wpdb->prepare( "FIELD(a.auction_id, $field_placeholders)", $product_ids ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		// Main query.
		$query_sql = "
			SELECT
				a.auction_id,
				a.user_id,
				a.bidding_status,
				a.bidding_starts_at,
				a.bidding_ends_at,
				a.location_country,
				a.location_subdivision,
				a.location_city,
				p.post_title,
				p.post_name
			FROM {$table_name} a
			INNER JOIN {$posts_table} p ON a.auction_id = p.ID AND p.post_status = 'publish'
			WHERE {$where_sql}
			ORDER BY {$order_sql}
			LIMIT %d OFFSET %d
		";

		$query_values   = array_merge( $where_values, array( $per_page, $offset ) );
		$prepared_query = $wpdb->prepare( $query_sql, $query_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results        = $wpdb->get_results( $prepared_query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		// Batch-prime caches before transform loop — eliminates N+1 wc_get_product() calls.
		$ids = array_column( $results, 'auction_id' );
		Eager_Loader::prime_post_meta( $ids );
		$image_map = Eager_Loader::prime_images( $ids );

		$merged_codes   = array_merge(
			array_column( $results, 'location_country' ),
			array_column( $results, 'location_subdivision' )
		);
		$location_codes = array_values( array_unique( array_filter( $merged_codes ) ) );
		$term_map       = Eager_Loader::load_location_terms( $location_codes );

		$items = array();
		foreach ( $results as $row ) {
			$auction_id = absint( $row['auction_id'] );
			$image_id   = $image_map[ $auction_id ] ?? 0;
			$image_src  = $image_id ? wp_get_attachment_image_src( $image_id, 'medium' ) : false;
			$image_url  = is_array( $image_src ) ? $image_src[0] : '';

			/**
			 * Filters the item data array placed into block context for an auction.
			 *
			 * Fires per-item. Post meta for $auction_id is already primed —
			 * get_post_meta() calls in callbacks are zero-cost cache hits.
			 * For batch enrichment across all items in the result set, use the
			 * aucteeno_products_context_data filter instead.
			 *
			 * @since 2.2.0
			 * @param array $item_data  Item context data array.
			 * @param int   $auction_id Auction post ID.
			 */
			$items[] = apply_filters(
				'aucteeno_product_context_data',
				array(
					'id'                           => $auction_id,
					'title'                        => $row['post_title'],
					'permalink'                    => get_permalink( $auction_id ),
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
					// Product_Auction has no get_reserve_price() method; field is always 0 until implemented.
					'reserve_price'                => 0.0,
				),
				$auction_id
			);
		}

		/**
		 * Filters the complete items array after all per-item context data is built.
		 *
		 * Fires once per query. Use this filter (rather than aucteeno_product_context_data)
		 * when enrichment requires a single batch query across all result IDs — e.g.
		 * fetching rows from a custom table or an external API for all IDs at once.
		 * Post meta for all IDs is already primed via Eager_Loader::prime_post_meta().
		 *
		 * @since 2.2.0
		 * @param array $items    Array of item data arrays in display order.
		 * @param int[] $post_ids Ordered auction post IDs matching $items.
		 */
		$items = (array) apply_filters( 'aucteeno_products_context_data', $items, $ids );

		return array(
			'items' => $items,
			'page'  => $page,
			'pages' => max( 1, (int) ceil( $total / $per_page ) ),
			'total' => $total,
		);
	}

	/**
	 * Get auction rows where stored bidding_status is stale (doesn't match timestamps).
	 *
	 * Only forward transitions are detected:
	 * - upcoming (20) → running (10): starts_at <= NOW && ends_at > NOW
	 * - running (10) → expired (30): ends_at <= NOW
	 *
	 * Rows with bidding_ends_at = 0 are excluded (times not set).
	 * Rows with bidding_starts_at = 0 are included (treated as already started).
	 *
	 * @param int $limit Maximum rows to return.
	 * @return array<array{auction_id: int, bidding_starts_at: int, bidding_ends_at: int, bidding_status: int}>
	 */
	public function get_stale( int $limit ): array {
		global $wpdb;

		$table = self::get_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare(
			"SELECT auction_id, bidding_starts_at, bidding_ends_at, bidding_status
			 FROM {$table}
			 WHERE bidding_ends_at > 0
			   AND (
			     (bidding_status = 20 AND bidding_starts_at <= UNIX_TIMESTAMP() AND bidding_ends_at > UNIX_TIMESTAMP())
			     OR (bidding_status = 10 AND bidding_ends_at <= UNIX_TIMESTAMP())
			   )
			 LIMIT %d",
			$limit
		);

		$result = $wpdb->get_results( $sql, ARRAY_A );
		return $result ? $result : array();
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Update bidding_status for multiple auctions in a single query.
	 *
	 * Caller must not pass an empty array — this is a logic error.
	 * Uses the auction_id business-key column (not the auto-increment ID column).
	 *
	 * @param array<int> $auction_ids  Auction post IDs to update.
	 * @param int        $new_status   New bidding status (10, 20, or 30).
	 * @return bool True on success.
	 */
	public function update_bidding_status_batch( array $auction_ids, int $new_status ): bool {
		if ( empty( $auction_ids ) ) {
			return false;
		}

		global $wpdb;

		$table        = self::get_table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $auction_ids ), '%d' ) );
		$values       = array_merge( array( $new_status ), $auction_ids );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$sql    = $wpdb->prepare(
			"UPDATE {$table} SET bidding_status = %d WHERE auction_id IN ({$placeholders})",
			$values
		);
		$result = $wpdb->query( $sql );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		return false !== $result;
	}

	/**
	 * Get total auction count across all active statuses.
	 *
	 * Uses three separate COUNT queries so MySQL can leverage the per-status
	 * composite indexes (idx_running_auctions, idx_upcoming_auctions, idx_expired_auctions)
	 * instead of falling back to a full table scan with OR.
	 *
	 * The expired count skips the wp_posts JOIN (expired auctions are settled and
	 * unlikely to change post_status) and is cached via wp_cache.
	 *
	 * @param string $table_name     Auctions table name.
	 * @param string $posts_table    Posts table name.
	 * @param string $base_where_sql Base WHERE clause (without status).
	 * @param array  $where_values   Prepared statement values.
	 * @return int Total count across all statuses.
	 */
	private function get_total_status_count( string $table_name, string $posts_table, string $base_where_sql, array $where_values ): int {
		global $wpdb;

		// Running and upcoming: JOIN wp_posts for publish check (small result sets).
		$base_sql = "
			SELECT COUNT(*) FROM {$table_name} a
			INNER JOIN {$posts_table} p ON a.auction_id = p.ID AND p.post_status = 'publish'
			WHERE {$base_where_sql}
		";

		$running_sql  = $base_sql . ' AND a.bidding_status = 10 AND a.bidding_starts_at <= UNIX_TIMESTAMP() AND a.bidding_ends_at > UNIX_TIMESTAMP()';
		$upcoming_sql = $base_sql . ' AND a.bidding_status = 20 AND a.bidding_starts_at > UNIX_TIMESTAMP()';

		if ( ! empty( $where_values ) ) {
			$running_sql  = $wpdb->prepare( $running_sql, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$upcoming_sql = $wpdb->prepare( $upcoming_sql, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$running  = (int) $wpdb->get_var( $running_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$upcoming = (int) $wpdb->get_var( $upcoming_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		// Expired: no JOIN (settled auctions), cached via transient.
		$expired = $this->get_expired_count( $table_name, $base_where_sql, $where_values );

		return $running + $upcoming + $expired;
	}

	/**
	 * Get expired auction count with wp_cache.
	 *
	 * Skips the wp_posts JOIN since expired auctions are settled and unlikely to
	 * change post_status. Uses a pure index scan on idx_expired_auctions.
	 *
	 * @param string $table_name     Auctions table name.
	 * @param string $base_where_sql Base WHERE clause (without status).
	 * @param array  $where_values   Prepared statement values.
	 * @return int Expired auction count.
	 */
	private function get_expired_count( string $table_name, string $base_where_sql, array $where_values ): int {
		$cache_key = md5( $base_where_sql . wp_json_encode( $where_values ) );
		$cached    = wp_cache_get( $cache_key, self::EXPIRED_COUNT_CACHE_GROUP );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		global $wpdb;

		$expired_sql = "
			SELECT COUNT(*) FROM {$table_name} a
			WHERE {$base_where_sql}
			AND a.bidding_status = 30
			AND a.bidding_ends_at <= UNIX_TIMESTAMP()
		";

		if ( ! empty( $where_values ) ) {
			$expired_sql = $wpdb->prepare( $expired_sql, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$count = (int) $wpdb->get_var( $expired_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
		wp_cache_set( $cache_key, $count, self::EXPIRED_COUNT_CACHE_GROUP, self::EXPIRED_COUNT_TTL );

		return $count;
	}
}
