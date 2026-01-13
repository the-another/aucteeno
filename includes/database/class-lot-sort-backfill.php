<?php
/**
 * Lot Sort Backfill Class
 *
 * Backfills lot_sort_key values for existing items in batches.
 *
 * @package Aucteeno
 * @since 2.2.0
 */

namespace TheAnother\Plugin\Aucteeno\Database;

/**
 * Class Lot_Sort_Backfill
 *
 * Handles batch backfilling of lot_sort_key values.
 */
class Lot_Sort_Backfill {

	/**
	 * Batch size for processing.
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 1000;

	/**
	 * Option name for storing last processed ID.
	 *
	 * @var string
	 */
	private const LAST_ID_OPTION = 'aucteeno_lot_sort_backfill_last_id';

	/**
	 * Process a batch of items.
	 *
	 * @return array{
	 *     processed: int,
	 *     remaining: bool,
	 *     last_id: int
	 * } Processing result.
	 */
	public static function process_batch(): array {
		global $wpdb;

		$table_name = Database_Items::get_table_name();
		$last_id    = (int) get_option( self::LAST_ID_OPTION, 0 );
		$batch_size = self::BATCH_SIZE;

		// Get batch of items.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, item_id, lot_no 
				FROM {$table_name} 
				WHERE ID > %d 
				ORDER BY ID ASC 
				LIMIT %d",
				$last_id,
				$batch_size
			)
		);

		if ( empty( $items ) ) {
			// Done - clean up option.
			delete_option( self::LAST_ID_OPTION );
			return array(
				'processed' => 0,
				'remaining' => false,
				'last_id'   => 0,
			);
		}

		$processed   = 0;
		$new_last_id = $last_id;

		foreach ( $items as $item ) {
			$sort_key = Lot_Sort_Helper::compute_lot_sort_key( $item->lot_no, $item->item_id );

			$updated = $wpdb->update(
				$table_name,
				array( 'lot_sort_key' => $sort_key ),
				array( 'ID' => $item->ID ),
				array( '%d' ),
				array( '%d' )
			);

			if ( false !== $updated ) {
				++$processed;
			}

			$new_last_id = max( $new_last_id, (int) $item->ID );
		}

		// Update last processed ID.
		if ( count( $items ) >= $batch_size ) {
			update_option( self::LAST_ID_OPTION, $new_last_id );
		} else {
			// Done - clean up option.
			delete_option( self::LAST_ID_OPTION );
		}

		return array(
			'processed' => $processed,
			'remaining' => count( $items ) >= $batch_size,
			'last_id'   => $new_last_id,
		);
	}

	/**
	 * Check if backfill is needed.
	 *
	 * @return bool True if backfill is needed.
	 */
	public static function is_needed(): bool {
		global $wpdb;

		$table_name = Database_Items::get_table_name();

		// Check if there are any items with lot_sort_key = 0 and non-empty lot_no.
		$count = $wpdb->get_var(
			"SELECT COUNT(*) 
			FROM {$table_name} 
			WHERE lot_sort_key = 0 
				AND lot_no != '' 
				AND lot_no IS NOT NULL"
		);

		return (int) $count > 0;
	}

	/**
	 * Get progress information.
	 *
	 * @return array{
	 *     total: int,
	 *     processed: int,
	 *     remaining: int,
	 *     percentage: float
	 * } Progress information.
	 */
	public static function get_progress(): array {
		global $wpdb;

		$table_name = Database_Items::get_table_name();

		// Total items that need backfilling.
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) 
			FROM {$table_name} 
			WHERE lot_no != '' 
				AND lot_no IS NOT NULL"
		);

		// Items already processed (have non-zero lot_sort_key).
		$processed = (int) $wpdb->get_var(
			"SELECT COUNT(*) 
			FROM {$table_name} 
			WHERE lot_sort_key != 0 
				AND lot_no != '' 
				AND lot_no IS NOT NULL"
		);

		$remaining  = max( 0, $total - $processed );
		$percentage = $total > 0 ? ( $processed / $total ) * 100 : 100.0;

		return array(
			'total'      => $total,
			'processed'  => $processed,
			'remaining'  => $remaining,
			'percentage' => round( $percentage, 2 ),
		);
	}

	/**
	 * Reset backfill progress (start over).
	 *
	 * @return void
	 */
	public static function reset(): void {
		delete_option( self::LAST_ID_OPTION );
	}
}
