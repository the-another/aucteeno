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
			KEY bidding_status (bidding_status),
			KEY bidding_starts_at (bidding_starts_at),
			KEY bidding_ends_at (bidding_ends_at),
			KEY lot_no (lot_no),
			KEY location_country (location_country),
			KEY location_subdivision (location_subdivision),
			KEY location_city (location_city),
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
}
