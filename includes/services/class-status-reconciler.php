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

		// If init already fully completed (late plugin load), call directly.
		//
		// `did_action( 'init' )` returns truthy the moment the `init` hook
		// STARTS firing — including while `before_woocommerce_init` runs
		// inside WooCommerce's own `init` callback at priority 0. Action
		// Scheduler does not initialize its data store until `init`
		// priority 1, so calling `as_has_scheduled_action()` from that
		// window triggers a "called before the Action Scheduler data
		// store was initialized" _doing_it_wrong notice.
		//
		// Combining `did_action()` with `! doing_action()` ensures we only
		// call `schedule()` directly when `init` has fully completed; the
		// normal case is handled by the deferred priority-20 callback above.
		if ( did_action( 'init' ) && ! doing_action( 'init' ) ) {
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
			return 20; // Upcoming.
		}

		if ( $ends_at > $now ) {
			return 10; // Running.
		}

		return 30; // Expired.
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
				++$runs;
				if ( count( $rows ) < self::BATCH_SIZE ) {
					$phase = 'items';
				}
			} else {
				$rows = Database_Items::get_stale( self::BATCH_SIZE );
				if ( empty( $rows ) ) {
					break;
				}
				$this->process_item_batch( $rows );
				++$runs;
			}
		}
	}

	/**
	 * Process a batch of stale auction rows.
	 *
	 * Updates taxonomy term relationships first; HPS updated second only if taxonomy succeeded.
	 * Fires wp_update_term_count once after all groups if any taxonomy write succeeded.
	 *
	 * @param array $rows Rows from Database_Auctions::get_stale().
	 * @return void
	 */
	private function process_auction_batch( array $rows ): void {
		// Group auction IDs by their correct status.
		$groups = array();
		foreach ( $rows as $row ) {
			$new_status              = $this->compute_correct_status(
				(int) $row['bidding_starts_at'],
				(int) $row['bidding_ends_at']
			);
			$groups[ $new_status ][] = (int) $row['auction_id'];
		}

		$any_taxonomy_updated = false;
		$taxonomy_ok_groups   = array();

		foreach ( $groups as $new_status => $ids ) {
			if ( empty( $ids ) ) {
				continue;
			}

			// Taxonomy first — failure means HPS update is skipped for this group.
			if ( $this->bulk_set_bidding_status_term( $ids, $new_status ) ) {
				$any_taxonomy_updated              = true;
				$taxonomy_ok_groups[ $new_status ] = $ids;
			} else {
				wc_get_logger()->error(
					'Failed to update taxonomy for auction IDs: ' . implode( ', ', $ids ),
					array( 'source' => 'aucteeno-reconciler' )
				);
			}
		}

		// HPS update only for groups where taxonomy succeeded.
		foreach ( $taxonomy_ok_groups as $new_status => $ids ) {
			if ( ! Database_Auctions::update_bidding_status_batch( $ids, $new_status ) ) {
				wc_get_logger()->error(
					'Failed to update HPS bidding_status for auction IDs: ' . implode( ', ', $ids ),
					array( 'source' => 'aucteeno-reconciler' )
				);
			}
		}

		// Recount term associations once after all groups — only if any taxonomy write succeeded.
		if ( $any_taxonomy_updated && ! empty( $this->ttids_cache ) ) {
			wp_update_term_count( $this->ttids_cache, 'auction-bidding-status' );
		}
	}

	/**
	 * Process a batch of stale item rows.
	 *
	 * Updates HPS table only — items never hold auction-bidding-status taxonomy terms directly.
	 *
	 * @param array $rows Rows from Database_Items::get_stale().
	 * @return void
	 */
	private function process_item_batch( array $rows ): void {
		// Group item IDs by their correct status.
		$groups = array();
		foreach ( $rows as $row ) {
			$new_status              = $this->compute_correct_status(
				(int) $row['bidding_starts_at'],
				(int) $row['bidding_ends_at']
			);
			$groups[ $new_status ][] = (int) $row['item_id'];
		}

		foreach ( $groups as $new_status => $ids ) {
			if ( empty( $ids ) ) {
				continue;
			}

			if ( ! Database_Items::update_bidding_status_batch( $ids, $new_status ) ) {
				wc_get_logger()->error(
					'Failed to update HPS bidding_status for item IDs: ' . implode( ', ', $ids ),
					array( 'source' => 'aucteeno-reconciler' )
				);
			}
		}
		// No taxonomy update — items never hold auction-bidding-status terms directly.
	}

	/**
	 * Bulk-replace auction-bidding-status term relationships for a set of products.
	 *
	 * @param int[] $object_ids Product IDs.
	 * @param int   $new_status Target bidding status (10, 20, or 30).
	 * @return bool True on full success.
	 */
	private function bulk_set_bidding_status_term( array $object_ids, int $new_status ): bool {
		global $wpdb;

		if ( empty( $object_ids ) ) {
			return false;
		}

		// Step 1: Fetch all term_taxonomy_ids for the taxonomy (cached per run()).
		if ( null === $this->ttids_cache ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$raw               = $wpdb->get_col(
				"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'auction-bidding-status'"
			);
			$this->ttids_cache = $raw ? $raw : array();
		}

		if ( empty( $this->ttids_cache ) ) {
			wc_get_logger()->error( 'auction-bidding-status taxonomy has no terms', array( 'source' => 'aucteeno-reconciler' ) );
			return false;
		}

		// Step 2: Resolve target term_taxonomy_id (cached per run() per status).
		if ( ! isset( $this->target_ttid_cache[ $new_status ] ) ) {
			$slug = Bidding_Status_Mapper::number_to_term( $new_status );

			if ( '' === $slug ) {
				wc_get_logger()->error(
					"No term slug found for bidding status {$new_status}",
					array( 'source' => 'aucteeno-reconciler' )
				);
				return false;
			}

			$term = get_term_by( 'slug', $slug, 'auction-bidding-status' );

			if ( ! $term || is_wp_error( $term ) ) {
				wc_get_logger()->error(
					"Term not found for slug '{$slug}' in auction-bidding-status",
					array( 'source' => 'aucteeno-reconciler' )
				);
				return false;
			}

			$this->target_ttid_cache[ $new_status ] = (int) $term->term_taxonomy_id;
		}

		$target_ttid       = $this->target_ttid_cache[ $new_status ];
		$obj_placeholders  = implode( ', ', array_fill( 0, count( $object_ids ), '%d' ) );
		$ttid_placeholders = implode( ', ', array_fill( 0, count( $this->ttids_cache ), '%d' ) );
		$delete_values     = array_merge( $object_ids, $this->ttids_cache );
		$insert_values     = array();

		foreach ( $object_ids as $object_id ) {
			$insert_values[] = $object_id;
			$insert_values[] = $target_ttid;
		}

		// Step 3: DELETE old bidding-status term relationships.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared
		$delete_sql = $wpdb->prepare(
			"DELETE FROM {$wpdb->term_relationships}
         WHERE object_id IN ({$obj_placeholders})
           AND term_taxonomy_id IN ({$ttid_placeholders})",
			$delete_values
		);
		$wpdb->query( $delete_sql );

		// Step 4: INSERT new term relationships (ON DUPLICATE KEY = idempotent for AS retries).
		$insert_row_placeholders = implode(
			', ',
			array_fill( 0, count( $object_ids ), '(%d, %d, 0)' )
		);
		$insert_sql              = $wpdb->prepare(
			"INSERT INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order)
         VALUES {$insert_row_placeholders}
         ON DUPLICATE KEY UPDATE term_order = term_order",
			$insert_values
		);
		$result                  = $wpdb->query( $insert_sql );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared

		// Detect failure via query() return value only — not $wpdb->last_error which may carry
		// stale values from the preceding DELETE.
		return false !== $result;
	}
}
