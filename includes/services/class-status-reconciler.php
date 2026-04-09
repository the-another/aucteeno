<?php
/**
 * Status Reconciler Service
 *
 * Periodically corrects stale bidding_status values in HPS tables via Action Scheduler.
 *
 * @package Aucteeno
 * @since   1.1.0
 */

namespace The_Another\Plugin\Aucteeno\Services;

use The_Another\Plugin\Aucteeno\Database\Database_Auctions;
use The_Another\Plugin\Aucteeno\Database\Database_Items;
use The_Another\Plugin\Aucteeno\Helpers\Bidding_Status_Mapper;
use The_Another\Plugin\Aucteeno\Hook_Manager;

/**
 * Class Status_Reconciler
 *
 * Runs an Action Scheduler recurring action every 5 minutes.
 * Each invocation loops up to MAX_RUNS times, correcting up to BATCH_SIZE
 * stale rows per run — auctions first, then items in any remaining runs.
 */
class Status_Reconciler {

	/**
	 * Rows processed per loop iteration.
	 */
	private const BATCH_SIZE = 500;

	/**
	 * Maximum loop iterations per Action Scheduler invocation (shared across phases).
	 */
	private const MAX_RUNS = 50;

	/**
	 * Action Scheduler hook name.
	 */
	private const ACTION_HOOK = 'aucteeno_reconcile_bidding_status';

	/**
	 * Action Scheduler group.
	 */
	private const ACTION_GROUP = 'aucteeno';

	/**
	 * Recurring interval in seconds (5 minutes).
	 */
	private const SCHEDULE_INTERVAL = 300;

	/**
	 * All term_taxonomy_ids for the auction-bidding-status taxonomy.
	 * Populated on first call to bulk_set_bidding_status_term(); reset at start of run().
	 *
	 * @var int[]|null
	 */
	private ?array $ttids_cache = null;

	/**
	 * Target term_taxonomy_id per status int.
	 * Reset at start of run().
	 *
	 * @var array<int, int>
	 */
	private array $target_ttid_cache = array();

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
	 * Register hooks.
	 * Called during plugin bootstrap (before_woocommerce_init).
	 *
	 * @return void
	 */
	public function init(): void {
		// Always register the AS action handler.
		$this->hook_manager->register_action( self::ACTION_HOOK, array( $this, 'run' ) );

		// Defer scheduling until WordPress init fires (AS is available by then).
		$this->hook_manager->register_action( 'init', array( $this, 'schedule' ), 20 );

		// If init already fired (late plugin load), call directly.
		if ( did_action( 'init' ) ) {
			$this->schedule();
		}
	}

	/**
	 * Schedule the recurring action if not already scheduled.
	 * Called on WordPress init hook (priority 20) or directly on late load.
	 *
	 * @return void
	 */
	public function schedule(): void {
		if ( ! as_has_scheduled_action( self::ACTION_HOOK, array(), self::ACTION_GROUP ) ) {
			as_schedule_recurring_action( time(), self::SCHEDULE_INTERVAL, self::ACTION_HOOK, array(), self::ACTION_GROUP );
		}
	}

	/**
	 * Unschedule all instances of the reconciler action.
	 * Static so it can be called from a register_deactivation_hook callback without container access.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		as_unschedule_all_actions( self::ACTION_HOOK, array(), self::ACTION_GROUP );
	}

	/**
	 * Compute the correct bidding status from timestamps.
	 *
	 * @param int $starts_at Unix timestamp for bidding start (0 = already started).
	 * @param int $ends_at   Unix timestamp for bidding end.
	 * @return int Status: 10 = running, 20 = upcoming, 30 = expired.
	 */
	private function compute_correct_status( int $starts_at, int $ends_at ): int {
		$now = time();

		if ( $starts_at > $now ) {
			return 20; // upcoming
		}

		if ( $ends_at > $now ) {
			return 10; // running
		}

		return 30; // expired
	}

	/**
	 * Main reconciliation loop — called by Action Scheduler.
	 *
	 * @return void
	 */
	public function run(): void {
		// Reset per-invocation caches.
		$this->ttids_cache       = null;
		$this->target_ttid_cache = array();

		$runs  = 0;
		$phase = 'auctions';

		while ( $runs < self::MAX_RUNS ) {
			if ( 'auctions' === $phase ) {
				$rows = Database_Auctions::get_stale( self::BATCH_SIZE );
				if ( empty( $rows ) ) {
					$phase = 'items';
					continue; // Back to while condition; $runs NOT incremented.
				}
				$this->process_auction_batch( $rows );
				$runs++;
				if ( count( $rows ) < self::BATCH_SIZE ) {
					$phase = 'items';
				}
			} else {
				$rows = Database_Items::get_stale( self::BATCH_SIZE );
				if ( empty( $rows ) ) {
					break;
				}
				$this->process_item_batch( $rows );
				$runs++;
			}
		}
	}

	/**
	 * Process a batch of stale auction rows.
	 *
	 * @param array $rows Rows from Database_Auctions::get_stale().
	 * @return void
	 */
	private function process_auction_batch( array $rows ): void {
		// Placeholder — implemented in Task 6.
	}

	/**
	 * Process a batch of stale item rows.
	 *
	 * @param array $rows Rows from Database_Items::get_stale().
	 * @return void
	 */
	private function process_item_batch( array $rows ): void {
		// Placeholder — implemented in Task 7.
	}

	/**
	 * Bulk-replace auction-bidding-status term relationships for a set of products.
	 *
	 * @param int[] $object_ids Product IDs.
	 * @param int   $new_status Target bidding status (10, 20, or 30).
	 * @return bool True on full success.
	 */
	private function bulk_set_bidding_status_term( array $object_ids, int $new_status ): bool {
		// Placeholder — implemented in Task 5.
		return false;
	}
}
