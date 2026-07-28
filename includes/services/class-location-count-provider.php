<?php
/**
 * Location Count Provider Class
 *
 * Provides cached location-scoped counts of auctions and items for listing UI
 * (location archive titles, "by location" navigation).
 *
 * @package Aucteeno
 * @since TBD
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno\Services;

use The_Another\Plugin\Aucteeno\Database\Database_Auctions;
use The_Another\Plugin\Aucteeno\Database\Database_Items;
use The_Another\Plugin\Aucteeno\Hook_Manager;

/**
 * Class Location_Count_Provider
 *
 * Counts read straight off the HPS tables, cached on a TTL. Both counts are
 * display approximations on high-traffic pages, so — like Search_Count_Provider
 * — they skip the wp_posts publish JOIN and take no save-time invalidation:
 * HPS_Sync_Handler already deletes trashed/deleted rows from the HPS tables, so
 * the JOIN only excluded drafts while forcing a full wp_posts index scan, and
 * save-time flushing kept the cache permanently cold during bulk imports.
 */
class Location_Count_Provider {

	/**
	 * Transient key prefix for the active auctions count.
	 *
	 * @var string
	 */
	private const AUCTIONS_TRANSIENT_PREFIX = 'aucteeno_count_active_auctions_';

	/**
	 * Transient key for the grouped item counts.
	 *
	 * @var string
	 */
	private const ITEM_LOCATIONS_TRANSIENT_KEY = 'aucteeno_count_items_by_location';

	/**
	 * Hook manager instance.
	 *
	 * @var Hook_Manager
	 */
	private Hook_Manager $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param Hook_Manager $hook_manager Hook manager instance.
	 */
	public function __construct( Hook_Manager $hook_manager ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Initialize service.
	 *
	 * @return void
	 */
	public function init(): void {
		// No hooks needed; both counts are TTL-cached approximations, so they
		// need no save-time invalidation.
	}

	/**
	 * Count auctions in a location whose bidding has not ended.
	 *
	 * Filters on timestamps rather than `bidding_status` because status can lag
	 * behind real-time transitions.
	 *
	 * @param string $country       Two-letter location country code.
	 * @param string $subdivision   Subdivision code in `COUNTRY:SUBDIVISION` form. Empty for country-wide.
	 * @param int    $cache_minutes Cache duration in minutes. 0 = bypass cache.
	 * @return int Count of auctions still open in that location.
	 */
	public function get_active_auctions_count( string $country, string $subdivision = '', int $cache_minutes = 5 ): int {
		if ( '' === $country ) {
			return 0;
		}

		$transient_key = self::AUCTIONS_TRANSIENT_PREFIX . md5( $country . '|' . $subdivision );

		if ( $cache_minutes > 0 ) {
			$cached = get_transient( $transient_key );
			if ( false !== $cached ) {
				return (int) $cached;
			}
		}

		global $wpdb;
		$auctions_table = Database_Auctions::get_table_name();

		$where  = 'a.bidding_ends_at > UNIX_TIMESTAMP() AND a.location_country = %s';
		$values = array( $country );

		if ( '' !== $subdivision ) {
			$where   .= ' AND a.location_subdivision = %s';
			$values[] = $subdivision;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders live in $where, which is assembled from literals above.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$auctions_table} a WHERE {$where}",
				$values
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		if ( $cache_minutes > 0 ) {
			set_transient( $transient_key, $count, $cache_minutes * MINUTE_IN_SECONDS );
		}

		return $count;
	}

	/**
	 * Count running and upcoming items grouped by location.
	 *
	 * Returns the raw grouped rows; presentation (term lookups, hierarchy,
	 * ordering rules) belongs to the caller.
	 *
	 * Filters on `bidding_status` rather than timestamps, matching what this
	 * navigation has always shown.
	 *
	 * @param int $cache_minutes Cache duration in minutes. 0 = bypass cache.
	 * @return array<int, array{location_country: string, location_subdivision: string, item_count: int}> Grouped counts.
	 */
	public function get_item_counts_by_location( int $cache_minutes = 5 ): array {
		if ( $cache_minutes > 0 ) {
			$cached = get_transient( self::ITEM_LOCATIONS_TRANSIENT_KEY );
			if ( false !== $cached ) {
				return (array) $cached;
			}
		}

		global $wpdb;
		$items_table = Database_Items::get_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			"SELECT location_country, location_subdivision, COUNT(*) AS item_count
			FROM {$items_table}
			WHERE bidding_status IN (10, 20)
			AND location_country != ''
			GROUP BY location_country, location_subdivision
			ORDER BY location_country ASC, location_subdivision ASC",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$counts = array();
		foreach ( (array) $results as $row ) {
			$counts[] = array(
				'location_country'     => (string) $row['location_country'],
				'location_subdivision' => (string) $row['location_subdivision'],
				'item_count'           => (int) $row['item_count'],
			);
		}

		if ( $cache_minutes > 0 ) {
			set_transient( self::ITEM_LOCATIONS_TRANSIENT_KEY, $counts, $cache_minutes * MINUTE_IN_SECONDS );
		}

		return $counts;
	}
}
