<?php
/**
 * Auctions Database Table Class
 *
 * Manages the auctions custom database table.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Database;

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
	public static function query_for_listing( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'page'        => 1,
			'per_page'    => 12,
			'sort'        => 'ending_soon',
			'user_id'     => 0,
			'country'     => '',
			'subdivision' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$page     = max( 1, absint( $args['page'] ) );
		$per_page = min( 50, max( 1, absint( $args['per_page'] ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$sort     = in_array( $args['sort'], array( 'ending_soon', 'newest' ), true ) ? $args['sort'] : 'ending_soon';

		$table_name  = self::get_table_name();
		$posts_table = $wpdb->posts;

		// Build WHERE clauses.
		$where_clauses = array( 'a.bidding_status IN (10, 20, 30)' ); // Running, Upcoming, Expired.
		$where_values  = array();

		// User filter.
		if ( ! empty( $args['user_id'] ) ) {
			$where_clauses[] = 'a.user_id = %d';
			$where_values[]  = absint( $args['user_id'] );
		}

		// Location filters.
		if ( ! empty( $args['country'] ) ) {
			$where_clauses[] = 'a.location_country = %s';
			$where_values[]  = sanitize_text_field( $args['country'] );
		}
		if ( ! empty( $args['subdivision'] ) ) {
			$where_clauses[] = 'a.location_subdivision = %s';
			$where_values[]  = sanitize_text_field( $args['subdivision'] );
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Build ORDER BY.
		if ( 'newest' === $sort ) {
			$order_sql = 'p.post_date DESC, a.auction_id DESC';
		} else {
			// ending_soon: Running first (by ends_at ASC), then Upcoming (by starts_at ASC), then Expired (by ends_at DESC).
			$order_sql = "
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
			";
		}

		// Count query.
		$count_sql = "SELECT COUNT(*) FROM {$table_name} a WHERE {$where_sql}";
		if ( ! empty( $where_values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

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
		$results        = $wpdb->get_results( $prepared_query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Transform results to include additional data.
		$items = array();
		foreach ( $results as $row ) {
			$auction_id = absint( $row['auction_id'] );
			$product    = wc_get_product( $auction_id );

			$image_url = '';
			$image_id  = get_post_thumbnail_id( $auction_id );
			if ( $image_id ) {
				$image_src = wp_get_attachment_image_src( $image_id, 'medium' );
				if ( $image_src ) {
					$image_url = $image_src[0];
				}
			}

			$items[] = array(
				'id'                 => $auction_id,
				'title'              => $row['post_title'],
				'permalink'          => get_permalink( $auction_id ),
				'image_url'          => $image_url,
				'user_id'            => absint( $row['user_id'] ),
				'bidding_status'     => absint( $row['bidding_status'] ),
				'bidding_starts_at'  => absint( $row['bidding_starts_at'] ),
				'bidding_ends_at'    => absint( $row['bidding_ends_at'] ),
				'location_country'   => $row['location_country'],
				'location_subdivision' => $row['location_subdivision'],
				'location_city'      => $row['location_city'],
				'current_bid'        => $product ? (float) $product->get_price() : 0,
				'reserve_price'      => $product && method_exists( $product, 'get_reserve_price' ) ? (float) $product->get_reserve_price() : 0,
			);
		}

		return array(
			'items' => $items,
			'page'  => $page,
			'pages' => max( 1, (int) ceil( $total / $per_page ) ),
			'total' => $total,
		);
	}
}
