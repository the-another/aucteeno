<?php
/**
 * Install Class
 *
 * Handles plugin installation and activation tasks.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Installer;

use TheAnother\Plugin\Aucteeno\Database\Database;
use TheAnother\Plugin\Aucteeno\Permalinks\Auction_Item_Permalinks;

/**
 * Class Install
 *
 * Handles plugin installation tasks.
 */
class Install {

	/**
	 * Run installation tasks.
	 *
	 * @since 1.0.0
	 */
	public static function run(): void {
		Database::create_tables();

		// Flush rewrite rules for custom permalinks.
		Auction_Item_Permalinks::activate();
	}
}
