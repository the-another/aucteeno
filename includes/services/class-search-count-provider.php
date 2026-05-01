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
		// No hooks needed; invalidation lives in Search_Block_Service so the
		// service stays a pure read-side cache.
	}

	/**
	 * Get count of items with bidding_status IN (10, 20).
	 *
	 * @param int $cache_minutes Cache duration in minutes. 0 = bypass cache.
	 * @return int Count of running and upcoming items.
	 */
	public function get_running_upcoming_items_count( int $cache_minutes = 5 ): int {
		if ( $cache_minutes > 0 ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( false !== $cached ) {
				return (int) $cached;
			}
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}aucteeno_items WHERE bidding_status IN (%d, %d)",
				10,
				20
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $cache_minutes > 0 ) {
			set_transient( self::TRANSIENT_KEY, $count, $cache_minutes * MINUTE_IN_SECONDS );
		}

		return $count;
	}

	/**
	 * Clear cached count.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		delete_transient( self::TRANSIENT_KEY );
	}
}
