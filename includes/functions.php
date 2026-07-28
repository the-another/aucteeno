<?php
/**
 * Global API functions for cross-plugin use.
 *
 * No namespace — these are global functions that other plugins (e.g. Aucteeno Nexus) can call
 * via function_exists() checks instead of relying on fragile fully-qualified class names.
 *
 * @package Aucteeno
 */

use The_Another\Plugin\Aucteeno\Container;
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

/**
 * Query auctions for listing.
 *
 * @param array $args Query arguments (page, per_page, sort, user_id, country, subdivision, search, product_ids).
 * @return array { items: array, page: int, pages: int, total: int }
 */
function aucteeno_query_auctions( array $args = array() ): array {
	return Container::get_instance()->get( 'database_auctions' )->query_for_listing( $args );
}

/**
 * Query items for listing.
 *
 * @param array $args Query arguments (page, per_page, sort, user_id, auction_id, country, subdivision, search, product_ids).
 * @return array { items: array, page: int, pages: int, total: int }
 */
function aucteeno_query_items( array $args = array() ): array {
	return Container::get_instance()->get( 'database_items' )->query_for_listing( $args );
}

/**
 * Get the number of items the live search would return (running + upcoming).
 *
 * Exposed so themes and sibling plugins reuse the one cached, index-only count
 * instead of each carrying its own copy of the SQL. Copies drift: a theme-side
 * duplicate kept the wp_posts publish JOIN long after the plugin dropped it,
 * costing tens of seconds per cold render on large sites.
 *
 * @param int $cache_minutes Cache duration in minutes. 0 = bypass cache.
 * @return int Count of items the search will return.
 */
function aucteeno_get_running_upcoming_items_count( int $cache_minutes = 5 ): int {
	return Container::get_instance()->get( 'search_count_provider' )->get_running_upcoming_items_count( $cache_minutes );
}
