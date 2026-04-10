<?php
/**
 * Items Database Table Class
 *
 * Manages the items custom database table.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace The_Another\Plugin\Aucteeno\Database;

use The_Another\Plugin\Aucteeno\Database\Eager_Loader;
use The_Another\Plugin\Aucteeno\Permalinks\Auction_Item_Permalinks;

/**
 * Class Database_Items
 *
 * Handles items table creation and schema using dbDelta.
 */
class Database_Items {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	private const TABLE_NAME = 'aucteeno_items';

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
		item_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
		user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
		bidding_status tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
		bidding_starts_at int(10) UNSIGNED NOT NULL DEFAULT 0,
		bidding_ends_at int(10) UNSIGNED NOT NULL DEFAULT 0,
		lot_no varchar(50) NOT NULL DEFAULT '',
		lot_sort_key bigint(20) UNSIGNED NOT NULL DEFAULT 0,
		location_country varchar(2) NOT NULL DEFAULT '',
		location_subdivision varchar(50) NOT NULL DEFAULT '',
		location_city varchar(50) NOT NULL DEFAULT '',
		location_lat float NOT NULL DEFAULT 0,
		location_lng float NOT NULL DEFAULT 0,
		PRIMARY KEY  (ID),
		UNIQUE KEY item_id (item_id),
		KEY auction_id (auction_id),
		KEY user_id (user_id),
		KEY bidding_status (bidding_status),
		KEY bidding_starts_at (bidding_starts_at),
		KEY bidding_ends_at (bidding_ends_at),
		KEY lot_no (lot_no),
		KEY location_country (location_country),
		KEY location_subdivision (location_subdivision),
		KEY location_city (location_city),
		KEY idx_user_running (user_id, bidding_status, bidding_ends_at, lot_sort_key, item_id),
		KEY idx_user_upcoming (user_id, bidding_status, bidding_starts_at, lot_sort_key, item_id),
		KEY idx_user_expired (user_id, bidding_status, bidding_ends_at, item_id),
		KEY idx_running_items (bidding_status, bidding_ends_at, lot_sort_key, item_id),
		KEY idx_upcoming_items (bidding_status, bidding_starts_at, lot_sort_key, item_id),
		KEY idx_expired_items (bidding_status, bidding_ends_at, item_id),
		KEY idx_auction_running (auction_id, bidding_status, bidding_ends_at, lot_sort_key, item_id),
		KEY idx_auction_upcoming (auction_id, bidding_status, bidding_starts_at, lot_sort_key, item_id),
		KEY idx_auction_expired (auction_id, bidding_status, bidding_ends_at, item_id),
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
	 * Query items for block listing.
	 *
	 * Uses separate queries per status group (running, upcoming, expired) with
	 * proper offset calculations to ensure correct pagination across all groups.
	 * This approach allows each query to use its specialized index efficiently.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type int    $page        Page number (default 1).
	 *     @type int    $per_page    Items per page (default 12, max 50).
	 *     @type string $sort        Sort order: 'ending_soon' or 'newest'.
	 *     @type int    $user_id     Filter by user/vendor ID.
	 *     @type int    $auction_id  Filter by parent auction ID.
	 *     @type string $country     Filter by location country.
	 *     @type string $subdivision Filter by location subdivision.
	 *     @type string $search      Search keyword for post title.
	 *     @type array  $product_ids Array of product IDs to filter by.
	 * }
	 * @return array {
	 *     Query result.
	 *
	 *     @type array $items Array of item data.
	 *     @type int   $page  Current page.
	 *     @type int   $pages Total pages.
	 *     @type int   $total Total items.
	 * }
	 */
	public static function query_for_listing( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'page'        => 1,
			'per_page'    => 12,
			'sort'        => 'ending_soon',
			'user_id'     => 0,
			'auction_id'  => 0,
			'country'     => '',
			'subdivision' => '',
			'search'      => '',
			'product_ids' => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		$page     = max( 1, absint( $args['page'] ) );
		$per_page = min( 50, max( 1, absint( $args['per_page'] ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$sort     = in_array( $args['sort'], array( 'ending_soon', 'newest' ), true ) ? $args['sort'] : 'ending_soon';

		// For 'newest' sort, use the combined query approach since it doesn't group by status.
		if ( 'newest' === $sort ) {
			return self::query_for_listing_newest( $args, $page, $per_page, $offset );
		}

		// For 'ending_soon' sort, use separate queries per status group.
		return self::query_for_listing_by_status( $args, $page, $per_page, $offset );
	}

	/**
	 * Query items sorted by newest (post_date DESC).
	 *
	 * Uses a single combined query since items aren't grouped by status.
	 *
	 * @param array $args     Query arguments.
	 * @param int   $page     Current page number.
	 * @param int   $per_page Items per page.
	 * @param int   $offset   Query offset.
	 * @return array Query result.
	 */
	private static function query_for_listing_newest( array $args, int $page, int $per_page, int $offset ): array {
		global $wpdb;

		$table_name  = self::get_table_name();
		$posts_table = $wpdb->posts;

		// Build WHERE clauses.
		// Filter by status with timestamp validation to ensure accurate real-time filtering:
		// - Running (10): started and not yet ended
		// - Upcoming (20): not yet started
		// - Expired (30): already ended.
		$where_clauses = array(
			'(
				(i.bidding_status = 10 AND i.bidding_starts_at <= UNIX_TIMESTAMP() AND i.bidding_ends_at > UNIX_TIMESTAMP())
				OR (i.bidding_status = 20 AND i.bidding_starts_at > UNIX_TIMESTAMP())
				OR (i.bidding_status = 30 AND i.bidding_ends_at <= UNIX_TIMESTAMP())
			)',
		);
		$where_values  = self::build_where_values( $args );

		if ( ! empty( $args['user_id'] ) ) {
			$where_clauses[] = 'i.user_id = %d';
		}
		if ( ! empty( $args['auction_id'] ) ) {
			$where_clauses[] = 'i.auction_id = %d';
		}
		if ( ! empty( $args['country'] ) ) {
			$where_clauses[] = 'i.location_country = %s';
		}
		if ( ! empty( $args['subdivision'] ) ) {
			$where_clauses[] = 'i.location_subdivision = %s';
		}

		// Product IDs filter.
		if ( ! empty( $args['product_ids'] ) && is_array( $args['product_ids'] ) ) {
			$product_ids     = array_map( 'absint', $args['product_ids'] );
			$product_ids     = array_filter( $product_ids );
			$placeholders    = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
			$where_clauses[] = "i.item_id IN ($placeholders)";
			$where_values    = array_merge( $where_values, $product_ids );
		}

		// Search filter (requires posts table join which is already present).
		if ( ! empty( $args['search'] ) ) {
			$where_clauses[] = 'p.post_title LIKE %s';
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Count query.
		$count_sql = "
			SELECT COUNT(*)
			FROM {$table_name} i
			INNER JOIN {$posts_table} p ON i.item_id = p.ID AND p.post_status = 'publish'
			WHERE {$where_sql}
		";
		if ( ! empty( $where_values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		// Main query.
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
			LEFT JOIN {$posts_table} ap ON i.auction_id = ap.ID -- auction slug, NULL if orphaned
			WHERE {$where_sql}
			ORDER BY p.post_date DESC, i.item_id DESC
			LIMIT %d OFFSET %d
		";

		$query_values   = array_merge( $where_values, array( $per_page, $offset ) );
		$prepared_query = $wpdb->prepare( $query_sql, $query_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results        = $wpdb->get_results( $prepared_query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $results ) ) {
			return array(
				'items' => array(),
				'page'  => $page,
				'pages' => max( 1, (int) ceil( $total / $per_page ) ),
				'total' => $total,
			);
		}

		// Batch-prime caches before transform — eliminates N+1 wc_get_product() calls.
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

		return array(
			'items' => self::transform_results( $results, $image_map, $term_map, $auction_base, $item_base ),
			'page'  => $page,
			'pages' => max( 1, (int) ceil( $total / $per_page ) ),
			'total' => $total,
		);
	}

	/**
	 * Query items by status groups (running, upcoming, expired) with proper pagination.
	 *
	 * Calculates offsets across groups to ensure correct pagination:
	 * - Running items first (sorted by ends_at ASC, lot_sort_key ASC)
	 * - Upcoming items second (sorted by starts_at ASC, lot_sort_key ASC)
	 * - Expired items last (sorted by ends_at DESC)
	 *
	 * @param array $args     Query arguments.
	 * @param int   $page     Current page number.
	 * @param int   $per_page Items per page.
	 * @param int   $offset   Global offset across all groups.
	 * @return array Query result.
	 */
	private static function query_for_listing_by_status( array $args, int $page, int $per_page, int $offset ): array {
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

		// Get counts for each status group.
		$counts = self::get_status_counts( $table_name, $base_where_sql, $where_values );

		$running_count  = $counts['running'];
		$upcoming_count = $counts['upcoming'];
		$expired_count  = $counts['expired'];
		$total          = $running_count + $upcoming_count + $expired_count;

		// Calculate which groups we need and their offsets/limits.
		$results          = array();
		$remaining_offset = $offset;
		$remaining_limit  = $per_page;

		// Group 1: Running items (status = 10).
		if ( $remaining_limit > 0 && $remaining_offset < $running_count ) {
			$group_offset = $remaining_offset;
			$group_limit  = min( $remaining_limit, $running_count - $group_offset );

			$running_results = self::query_status_group(
				10,
				$base_where_sql,
				$where_values,
				'i.bidding_ends_at ASC, i.lot_sort_key ASC, i.item_id ASC',
				$group_limit,
				$group_offset
			);

			$results          = array_merge( $results, $running_results );
			$remaining_limit -= count( $running_results );
		}

		// Adjust offset for upcoming group.
		$remaining_offset = max( 0, $remaining_offset - $running_count );

		// Group 2: Upcoming items (status = 20).
		if ( $remaining_limit > 0 && $remaining_offset < $upcoming_count ) {
			$group_offset = $remaining_offset;
			$group_limit  = min( $remaining_limit, $upcoming_count - $group_offset );

			$upcoming_results = self::query_status_group(
				20,
				$base_where_sql,
				$where_values,
				'i.bidding_starts_at ASC, i.lot_sort_key ASC, i.item_id ASC',
				$group_limit,
				$group_offset
			);

			$results          = array_merge( $results, $upcoming_results );
			$remaining_limit -= count( $upcoming_results );
		}

		// Adjust offset for expired group.
		$remaining_offset = max( 0, $remaining_offset - $upcoming_count );

		// Group 3: Expired items (status = 30).
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

		return array(
			'items' => self::transform_results( $results, $image_map, $term_map, $auction_base, $item_base ),
			'page'  => $page,
			'pages' => max( 1, (int) ceil( $total / $per_page ) ),
			'total' => $total,
		);
	}

	/**
	 * Get item counts for each status group.
	 *
	 * @param string $table_name     Items table name.
	 * @param string $base_where_sql Base WHERE clause (without status).
	 * @param array  $where_values   Prepared statement values.
	 * @return array Counts per status: running, upcoming, expired.
	 */
	private static function get_status_counts( string $table_name, string $base_where_sql, array $where_values ): array {
		global $wpdb;

		$posts_table = $wpdb->posts;

		$count_sql = "
			SELECT
				i.bidding_status,
				COUNT(*) as cnt
			FROM {$table_name} i
			INNER JOIN {$posts_table} p ON i.item_id = p.ID AND p.post_status = 'publish'
			WHERE {$base_where_sql}
			  AND (
				(i.bidding_status = 10 AND i.bidding_starts_at <= UNIX_TIMESTAMP() AND i.bidding_ends_at > UNIX_TIMESTAMP())
				OR (i.bidding_status = 20 AND i.bidding_starts_at > UNIX_TIMESTAMP())
				OR (i.bidding_status = 30 AND i.bidding_ends_at <= UNIX_TIMESTAMP())
			  )
			GROUP BY i.bidding_status
		";

		if ( ! empty( $where_values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$count_results = $wpdb->get_results( $count_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		$counts = array(
			'running'  => 0,
			'upcoming' => 0,
			'expired'  => 0,
		);

		foreach ( $count_results as $row ) {
			$status = absint( $row['bidding_status'] );
			$cnt    = absint( $row['cnt'] ?? 0 );

			if ( 10 === $status ) {
				$counts['running'] = $cnt;
			} elseif ( 20 === $status ) {
				$counts['upcoming'] = $cnt;
			} elseif ( 30 === $status ) {
				$counts['expired'] = $cnt;
			}
		}

		return $counts;
	}

	/**
	 * Query a single status group.
	 *
	 * @param int    $status         Bidding status (10=running, 20=upcoming, 30=expired).
	 * @param string $base_where_sql Base WHERE clause (without status).
	 * @param array  $where_values   Prepared statement values for base WHERE.
	 * @param string $order_sql      ORDER BY clause.
	 * @param int    $limit          Number of items to fetch.
	 * @param int    $offset         Offset within this group.
	 * @return array Raw database results.
	 */
	private static function query_status_group(
		int $status,
		string $base_where_sql,
		array $where_values,
		string $order_sql,
		int $limit,
		int $offset
	): array {
		global $wpdb;

		$table_name  = self::get_table_name();
		$posts_table = $wpdb->posts;

		// Add timestamp validation based on status.
		$timestamp_condition = '';
		if ( 10 === $status ) {
			// Running: started and not yet ended.
			$timestamp_condition = 'AND i.bidding_starts_at <= UNIX_TIMESTAMP() AND i.bidding_ends_at > UNIX_TIMESTAMP()';
		} elseif ( 20 === $status ) {
			// Upcoming: not yet started.
			$timestamp_condition = 'AND i.bidding_starts_at > UNIX_TIMESTAMP()';
		} elseif ( 30 === $status ) {
			// Expired: already ended.
			$timestamp_condition = 'AND i.bidding_ends_at <= UNIX_TIMESTAMP()';
		}

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
			LEFT JOIN {$posts_table} ap ON i.auction_id = ap.ID -- auction slug, NULL if orphaned
			WHERE {$base_where_sql}
			  AND i.bidding_status = %d
			  {$timestamp_condition}
			ORDER BY {$order_sql}
			LIMIT %d OFFSET %d
		";

		$query_values   = array_merge( $where_values, array( $status, $limit, $offset ) );
		$prepared_query = $wpdb->prepare( $query_sql, $query_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $wpdb->get_results( $prepared_query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Build WHERE clause values array from args.
	 *
	 * @param array $args Query arguments.
	 * @return array Values for prepared statement.
	 */
	private static function build_where_values( array $args ): array {
		$where_values = array();

		if ( ! empty( $args['user_id'] ) ) {
			$where_values[] = absint( $args['user_id'] );
		}
		if ( ! empty( $args['auction_id'] ) ) {
			$where_values[] = absint( $args['auction_id'] );
		}
		if ( ! empty( $args['country'] ) ) {
			$where_values[] = sanitize_text_field( $args['country'] );
		}
		if ( ! empty( $args['subdivision'] ) ) {
			$where_values[] = sanitize_text_field( $args['subdivision'] );
		}

		// Add search value with LIKE wildcards.
		if ( ! empty( $args['search'] ) ) {
			global $wpdb;
			$where_values[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		}

		return $where_values;
	}

	/**
	 * Get item rows where stored bidding_status is stale (doesn't match timestamps).
	 *
	 * Same forward-only transition logic as Database_Auctions::get_stale().
	 * Uses item_id (not auction_id) as the business key.
	 *
	 * @param int $limit Maximum rows to return.
	 * @return array<array{item_id: int, bidding_starts_at: int, bidding_ends_at: int, bidding_status: int}>
	 */
	public static function get_stale( int $limit ): array {
		global $wpdb;

		$table = self::get_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare(
			"SELECT item_id, bidding_starts_at, bidding_ends_at, bidding_status
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
	 * Update bidding_status for multiple items in a single query.
	 *
	 * Uses item_id business-key column (not the auto-increment ID column).
	 * Caller must not pass an empty array.
	 *
	 * @param array<int> $item_ids   Item post IDs to update.
	 * @param int        $new_status New bidding status (10, 20, or 30).
	 * @return bool True on success.
	 */
	public static function update_bidding_status_batch( array $item_ids, int $new_status ): bool {
		if ( empty( $item_ids ) ) {
			return false;
		}

		global $wpdb;

		$table        = self::get_table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $item_ids ), '%d' ) );
		$values       = array_merge( array( $new_status ), $item_ids );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$sql    = $wpdb->prepare(
			"UPDATE {$table} SET bidding_status = %d WHERE item_id IN ({$placeholders})",
			$values
		);
		$result = $wpdb->query( $sql );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		return false !== $result;
	}

	/**
	 * Transform raw database results to item data arrays.
	 *
	 * Reads image and price data from the WP object cache (primed by Eager_Loader).
	 * Uses bidding_status from the HPS row to select the correct price meta key,
	 * eliminating the hidden wp_get_post_terms() call inside Product_Item::get_price().
	 *
	 * @since 2.1.0
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
				$permalink = home_url(
					user_trailingslashit(
						$auction_base . '/' . $auction_slug . '/' . $item_base . '/' . $row['post_name']
					) 
				);
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
}
