<?php
/**
 * Items Database Table Class
 *
 * Manages the items custom database table.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Database;

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
		// - Expired (30): already ended
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
			$product_ids         = array_map( 'absint', $args['product_ids'] );
			$product_ids         = array_filter( $product_ids );
			$placeholders        = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
			$where_clauses[]     = "i.item_id IN ($placeholders)";
			$where_values        = array_merge( $where_values, $product_ids );
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
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

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
				p.post_name
			FROM {$table_name} i
			INNER JOIN {$posts_table} p ON i.item_id = p.ID AND p.post_status = 'publish'
			WHERE {$where_sql}
			ORDER BY p.post_date DESC, i.item_id DESC
			LIMIT %d OFFSET %d
		";

		$query_values   = array_merge( $where_values, array( $per_page, $offset ) );
		$prepared_query = $wpdb->prepare( $query_sql, $query_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results        = $wpdb->get_results( $prepared_query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => self::transform_results( $results ),
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
			$product_ids         = array_map( 'absint', $args['product_ids'] );
			$product_ids         = array_filter( $product_ids );
			$placeholders        = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
			$base_where_clauses[] = "i.item_id IN ($placeholders)";
			$where_values        = array_merge( $where_values, $product_ids );
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

		return array(
			'items' => self::transform_results( $results ),
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

		$count_results = $wpdb->get_results( $count_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$counts = array(
			'running'  => 0,
			'upcoming' => 0,
			'expired'  => 0,
		);

		foreach ( $count_results as $row ) {
			$status = absint( $row['bidding_status'] );
			$cnt    = absint( $row['cnt'] );

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
				p.post_name
			FROM {$table_name} i
			INNER JOIN {$posts_table} p ON i.item_id = p.ID AND p.post_status = 'publish'
			WHERE {$base_where_sql}
			  AND i.bidding_status = %d
			  {$timestamp_condition}
			ORDER BY {$order_sql}
			LIMIT %d OFFSET %d
		";

		$query_values   = array_merge( $where_values, array( $status, $limit, $offset ) );
		$prepared_query = $wpdb->prepare( $query_sql, $query_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $wpdb->get_results( $prepared_query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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

		// NEW: Add search value with LIKE wildcards.
		if ( ! empty( $args['search'] ) ) {
			global $wpdb;
			$where_values[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		}

		return $where_values;
	}

	/**
	 * Transform raw database results to item data arrays.
	 *
	 * @param array $results Raw database results.
	 * @return array Transformed item data.
	 */
	private static function transform_results( array $results ): array {
		$items = array();

		foreach ( $results as $row ) {
			$item_id = absint( $row['item_id'] );
			$product = wc_get_product( $item_id );

			$image_url = '';
			if ( $product ) {
				$image_id = $product->get_image_id();
				if ( $image_id ) {
					$image_src = wp_get_attachment_image_src( $image_id, 'medium' );
					if ( $image_src ) {
						$image_url = $image_src[0];
					}
				}
			}

			$items[] = array(
				'id'                   => $item_id,
				'auction_id'           => absint( $row['auction_id'] ),
				'title'                => $row['post_title'],
				'permalink'            => get_permalink( $item_id ),
				'image_url'            => $image_url,
				'user_id'              => absint( $row['user_id'] ),
				'bidding_status'       => absint( $row['bidding_status'] ),
				'bidding_starts_at'    => absint( $row['bidding_starts_at'] ),
				'bidding_ends_at'      => absint( $row['bidding_ends_at'] ),
				'lot_no'               => $row['lot_no'],
				'lot_sort_key'         => absint( $row['lot_sort_key'] ),
				'location_country'     => $row['location_country'],
				'location_subdivision' => $row['location_subdivision'],
				'location_city'        => $row['location_city'],
				'current_bid'          => $product ? (float) $product->get_price() : 0,
				'reserve_price'        => $product && method_exists( $product, 'get_reserve_price' ) ? (float) $product->get_reserve_price() : 0,
			);
		}

		return $items;
	}
}
