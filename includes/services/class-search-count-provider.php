<?php
/**
 * Search Count Provider Class
 *
 * Provides cached count of running and upcoming items for the Aucteeno Search block placeholder.
 *
 * @package Aucteeno
 * @since 2.0.0
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno\Services;

use The_Another\Plugin\Aucteeno\Hook_Manager;

/**
 * Class Search_Count_Provider
 *
 * Provides cached count of running and upcoming items for the Aucteeno Search block placeholder.
 */
class Search_Count_Provider {
	/**
	 * Transient key for caching count.
	 *
	 * @var string
	 */
	private const TRANSIENT_KEY = 'aucteeno_search_count_items_running_upcoming';

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
		// No hooks needed; the count is a TTL-cached approximation, so it
		// needs no save-time invalidation.
	}

	/**
	 * Get count of items the live search would return (running + upcoming).
	 *
	 * Counts items whose `bidding_ends_at` is in the future. Uses timestamps
	 * rather than `bidding_status` because status can lag behind real-time
	 * transitions; the search itself filters the same way (see
	 * Database_Items::status_clauses, Query_Orderer).
	 *
	 * Deliberately skips the wp_posts publish JOIN: trashed/deleted items are
	 * already removed from the HPS table by HPS_Sync_Handler, so the JOIN only
	 * excluded drafts while forcing a full wp_posts index scan on large sites.
	 * A slight overcount is acceptable for a placeholder (same trade-off as
	 * Database_Items::get_expired_count).
	 *
	 * @param int $cache_minutes Cache duration in minutes. 0 = bypass cache.
	 * @return int Count of items the search will return.
	 */
	public function get_running_upcoming_items_count( int $cache_minutes = 5 ): int {
		if ( $cache_minutes > 0 ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( false !== $cached ) {
				return (int) $cached;
			}
		}

		global $wpdb;
		$items_table = $wpdb->prefix . 'aucteeno_items';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$items_table} i
			WHERE i.bidding_ends_at > UNIX_TIMESTAMP()"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $cache_minutes > 0 ) {
			set_transient( self::TRANSIENT_KEY, $count, $cache_minutes * MINUTE_IN_SECONDS );
		}

		return $count;
	}
}
