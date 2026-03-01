<?php
/**
 * Global API functions for cross-plugin use.
 *
 * No namespace — these are global functions that other plugins (e.g. Aucteeno Nexus) can call
 * via function_exists() checks instead of relying on fragile fully-qualified class names.
 *
 * @package Aucteeno
 */

use The_Another\Plugin\Aucteeno\Database\Database_Auctions;
use The_Another\Plugin\Aucteeno\Database\Database_Items;
use The_Another\Plugin\Aucteeno\Database\Lot_Sort_Helper;

/**
 * Get the auctions HPS table name with prefix.
 *
 * @return string Full table name including wpdb prefix.
 */
function aucteeno_get_auctions_table_name(): string {
	return Database_Auctions::get_table_name();
}

/**
 * Get the items HPS table name with prefix.
 *
 * @return string Full table name including wpdb prefix.
 */
function aucteeno_get_items_table_name(): string {
	return Database_Items::get_table_name();
}

/**
 * Compute a numeric sort key for a lot number.
 *
 * @param string $lot_no     The lot number string.
 * @param int    $product_id The product ID (used as fallback).
 * @return int Numeric sort key.
 */
function aucteeno_compute_lot_sort_key( string $lot_no, int $product_id ): int {
	return Lot_Sort_Helper::compute_lot_sort_key( $lot_no, $product_id );
}
