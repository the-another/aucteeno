<?php
/**
 * Database Management Class
 *
 * Handles database table creation and migrations using WordPress dbDelta.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Database;

/**
 * Class Database
 *
 * Manages database tables using WordPress dbDelta function.
 */
class Database {

	/**
	 * Database version option name.
	 *
	 * @var string
	 */
	private const string DB_VERSION_OPTION = 'aucteeno_db_version';

	/**
	 * Current database version.
	 *
	 * @var string
	 */
	private const string CURRENT_DB_VERSION = '2.2.0';

	/**
	 * Get database version.
	 *
	 * @return string Database version.
	 */
	public static function get_db_version(): string {
		return get_option( self::DB_VERSION_OPTION, '0.0.0' );
	}

	/**
	 * Update database version.
	 *
	 * @param string $version Version to set.
	 * @return void
	 */
	public static function update_db_version( string $version ): void {
		update_option( self::DB_VERSION_OPTION, $version );
	}

	/**
	 * Check if database needs update.
	 *
	 * @return bool True if update needed.
	 */
	public static function needs_update(): bool {
		return version_compare( self::get_db_version(), self::CURRENT_DB_VERSION, '<' );
	}

	/**
	 * Create or update all tables.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Create auctions table.
		Database_Auctions::create_table();

		// Create items table.
		Database_Items::create_table();

		// Update database version.
		self::update_db_version( self::CURRENT_DB_VERSION );
	}

	/**
	 * Run migrations if needed.
	 *
	 * @return void
	 */
	public static function maybe_migrate(): void {
		if ( self::needs_update() ) {
			self::create_tables();

			// Run version-specific migrations.
			$current_version = self::get_db_version();
			if ( version_compare( $current_version, '2.2.0', '<' ) ) {
				require_once __DIR__ . '/class-database-migration-v2.2.0.php';
				Database_Migration_V2_2_0::run();
			}
		}
	}
}
