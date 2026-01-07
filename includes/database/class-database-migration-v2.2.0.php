<?php
/**
 * Database Migration v2.2.0
 *
 * Adds lot_sort_key column and composite indexes for high-performance ordering.
 *
 * @package Aucteeno
 * @since 2.2.0
 */

namespace TheAnother\Plugin\Aucteeno\Database;

/**
 * Class Database_Migration_V2_2_0
 *
 * Handles migration to version 2.2.0.
 */
class Database_Migration_V2_2_0 {

	/**
	 * Run the migration.
	 *
	 * @return void
	 */
	public static function run(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$items_table   = Database_Items::get_table_name();
		$auctions_table = Database_Auctions::get_table_name();

		// Check if lot_sort_key column already exists.
		$items_has_column = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'lot_sort_key'",
				DB_NAME,
				$items_table
			)
		);

		// Add lot_sort_key column to items table if it doesn't exist.
		if ( empty( $items_has_column ) ) {
			$wpdb->query(
				"ALTER TABLE {$items_table} 
				ADD COLUMN lot_sort_key bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER lot_no"
			);
		}

		// Check if lot_sort_key column exists in auctions table.
		$auctions_has_column = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'lot_sort_key'",
				DB_NAME,
				$auctions_table
			)
		);

		// Add lot_sort_key column to auctions table if it doesn't exist.
		if ( empty( $auctions_has_column ) ) {
			$wpdb->query(
				"ALTER TABLE {$auctions_table} 
				ADD COLUMN lot_sort_key bigint(20) UNSIGNED NOT NULL DEFAULT 0"
			);
		}

		// Check for duplicate item_id before adding unique constraint.
		$duplicate_items = $wpdb->get_var(
			"SELECT COUNT(*) FROM (
				SELECT item_id, COUNT(*) as cnt 
				FROM {$items_table} 
				GROUP BY item_id 
				HAVING cnt > 1
			) AS duplicates"
		);

		// Add unique constraint on item_id if no duplicates exist.
		if ( 0 === (int) $duplicate_items ) {
			// Check if unique key already exists.
			$has_unique_item_id = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
					WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND CONSTRAINT_NAME = 'item_id'",
					DB_NAME,
					$items_table
				)
			);

			if ( empty( $has_unique_item_id ) ) {
				$wpdb->query( "ALTER TABLE {$items_table} ADD UNIQUE KEY item_id (item_id)" );
			}
		}

		// Check for duplicate auction_id before adding unique constraint.
		$duplicate_auctions = $wpdb->get_var(
			"SELECT COUNT(*) FROM (
				SELECT auction_id, COUNT(*) as cnt 
				FROM {$auctions_table} 
				GROUP BY auction_id 
				HAVING cnt > 1
			) AS duplicates"
		);

		// Add unique constraint on auction_id if no duplicates exist.
		if ( 0 === (int) $duplicate_auctions ) {
			// Check if unique key already exists.
			$has_unique_auction_id = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
					WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND CONSTRAINT_NAME = 'auction_id'",
					DB_NAME,
					$auctions_table
				)
			);

			if ( empty( $has_unique_auction_id ) ) {
				$wpdb->query( "ALTER TABLE {$auctions_table} ADD UNIQUE KEY auction_id (auction_id)" );
			}
		}

		// Add composite indexes for items table.
		$items_indexes = array(
			'idx_running_items'    => '(bidding_status, bidding_ends_at, lot_sort_key, item_id)',
			'idx_upcoming_items'  => '(bidding_status, bidding_starts_at, lot_sort_key, item_id)',
			'idx_expired_items'   => '(bidding_status, bidding_ends_at, item_id)',
			'idx_auction_running' => '(auction_id, bidding_status, bidding_ends_at, lot_sort_key, item_id)',
			'idx_auction_upcoming' => '(auction_id, bidding_status, bidding_starts_at, lot_sort_key, item_id)',
			'idx_auction_expired'  => '(auction_id, bidding_status, bidding_ends_at, item_id)',
			'idx_location_status_ends' => '(location_country, location_subdivision, bidding_status, bidding_ends_at)',
		);

		foreach ( $items_indexes as $index_name => $index_columns ) {
			// Check if index already exists.
			$index_exists = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS 
					WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
					DB_NAME,
					$items_table,
					$index_name
				)
			);

			if ( empty( $index_exists ) ) {
				$wpdb->query( "ALTER TABLE {$items_table} ADD KEY {$index_name} {$index_columns}" );
			}
		}

		// Add composite indexes for auctions table.
		$auctions_indexes = array(
			'idx_running_auctions'    => '(bidding_status, bidding_ends_at, auction_id)',
			'idx_upcoming_auctions'   => '(bidding_status, bidding_starts_at, auction_id)',
			'idx_expired_auctions'    => '(bidding_status, bidding_ends_at, auction_id)',
			'idx_location_status_ends' => '(location_country, location_subdivision, bidding_status, bidding_ends_at)',
		);

		foreach ( $auctions_indexes as $index_name => $index_columns ) {
			// Check if index already exists.
			$index_exists = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS 
					WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
					DB_NAME,
					$auctions_table,
					$index_name
				)
			);

			if ( empty( $index_exists ) ) {
				$wpdb->query( "ALTER TABLE {$auctions_table} ADD KEY {$index_name} {$index_columns}" );
			}
		}

		// Update database version.
		Database::update_db_version( '2.2.0' );
	}
}

