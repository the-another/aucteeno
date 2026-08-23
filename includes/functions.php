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

/**
 * Count auctions in a location whose bidding has not ended.
 *
 * Cached; safe to call on every render of a location archive.
 *
 * @param string $country     Two-letter location country code.
 * @param string $subdivision Subdivision code in `COUNTRY:SUBDIVISION` form. Empty for country-wide.
 * @return int Count of auctions still open in that location.
 */
function aucteeno_get_active_auctions_count( string $country, string $subdivision = '' ): int {
	return Container::get_instance()->get( 'location_count_provider' )->get_active_auctions_count( $country, $subdivision );
}

/**
 * Count running and upcoming items grouped by location.
 *
 * Returns raw grouped rows — each with location_country, location_subdivision
 * and item_count — leaving term lookups and hierarchy to the caller. Cached.
 *
 * @return array<int, array{location_country: string, location_subdivision: string, item_count: int}> Grouped counts.
 */
function aucteeno_get_item_counts_by_location(): array {
	return Container::get_instance()->get( 'location_count_provider' )->get_item_counts_by_location();
}

if ( ! function_exists( 'aucteeno_format_date' ) ) {
	/**
	 * Format a timestamp based on the selected date format.
	 *
	 * The timestamp is a Unix timestamp (UTC-based). wp_date() automatically converts
	 * it to the WordPress timezone setting for display.
	 *
	 * Guarded against a double-load of this file (e.g. this file being
	 * required more than once within the same process, such as in tests).
	 * This is the single definition; nothing else in the plugin declares it.
	 *
	 * @param int    $timestamp   Unix timestamp (UTC-based).
	 * @param string $date_format Date format setting.
	 * @return string Formatted date string in WordPress timezone.
	 */
	function aucteeno_format_date( $timestamp, $date_format ) {
		switch ( $date_format ) {
			case 'mdy':
				return wp_date( 'm/d/Y', $timestamp );
			case 'dmy':
				return wp_date( 'd/m/Y', $timestamp );
			case 'ymd':
				return wp_date( 'Y-m-d', $timestamp );
			case 'long':
				return wp_date( 'F j, Y', $timestamp );
			case 'long_eu':
				return wp_date( 'j F Y', $timestamp );
			case 'full':
				return wp_date( 'l, F jS Y', $timestamp );
			case 'default':
			default:
				return wp_date( get_option( 'date_format' ), $timestamp );
		}
	}
}

/**
 * Format an elapsed duration since a moment in time, with the same smart
 * scaling a countdown display uses for its own expired state: seconds and
 * minutes, then hours, then days, then a formatted date once the duration
 * passes a week.
 *
 * Generic on purpose: a consuming plugin calls this to render a matching
 * "time since" string anywhere it has a UTC timestamp to measure from - for
 * example, a plugin that needs to say how long ago a closing auction ended.
 *
 * @since 1.9.0
 *
 * @param int    $elapsed     Elapsed duration in seconds. Must be >= 0.
 * @param int    $timestamp   Unix timestamp the elapsed duration is measured
 *                             from - used only once the duration scales up to
 *                             a formatted date.
 * @param string $date_format Date format setting; see aucteeno_format_date().
 * @return array{ display_value: string, is_showing_date: bool }
 */
function aucteeno_format_elapsed( int $elapsed, int $timestamp, string $date_format ): array {
	if ( $elapsed < 3600 ) {
		// Less than 1 hour ago - show minutes and seconds elapsed.
		$minutes = floor( $elapsed / 60 );
		$seconds = $elapsed % 60;
		$parts   = array();

		if ( $minutes > 0 ) {
			/* translators: %d: number of minutes */
			$parts[] = sprintf( _n( '%d minute', '%d minutes', $minutes, 'aucteeno' ), $minutes );
		}

		/* translators: %d: number of seconds */
		$parts[] = sprintf( _n( '%d second', '%d seconds', $seconds, 'aucteeno' ), $seconds );

		return array(
			/* translators: %s: elapsed time (e.g., "5 minutes 30 seconds") */
			'display_value'   => sprintf( __( '%s ago', 'aucteeno' ), implode( ' ', $parts ) ),
			'is_showing_date' => false,
		);
	}

	if ( $elapsed < 86400 ) {
		// Less than 1 day ago - show hours elapsed.
		$hours = floor( $elapsed / 3600 );
		/* translators: %d: number of hours */
		$time_string = sprintf( _n( '%d hour', '%d hours', $hours, 'aucteeno' ), $hours );

		return array(
			/* translators: %s: elapsed time (e.g., "3 hours") */
			'display_value'   => sprintf( __( '%s ago', 'aucteeno' ), $time_string ),
			'is_showing_date' => false,
		);
	}

	if ( $elapsed < 604800 ) {
		// Less than 1 week ago - show days elapsed.
		$days = floor( $elapsed / 86400 );
		/* translators: %d: number of days */
		$time_string = sprintf( _n( '%d day', '%d days', $days, 'aucteeno' ), $days );

		return array(
			/* translators: %s: elapsed time (e.g., "2 days") */
			'display_value'   => sprintf( __( '%s ago', 'aucteeno' ), $time_string ),
			'is_showing_date' => false,
		);
	}

	// More than 1 week ago - show the date.
	return array(
		'display_value'   => aucteeno_format_date( $timestamp, $date_format ),
		'is_showing_date' => true,
	);
}
