<?php
/**
 * Lot Sort Helper Class
 *
 * Computes natural lot number sort keys for efficient database ordering.
 *
 * @package Aucteeno
 * @since 2.2.0
 */

namespace The_Another\Plugin\Aucteeno\Database;

/**
 * Class Lot_Sort_Helper
 *
 * Handles natural lot number ordering computation.
 */
class Lot_Sort_Helper {

	/**
	 * Base multiplier for lot number encoding.
	 * 10000 is sufficient since there usually wouldn't be more variations than this inside a single auction.
	 *
	 * @var int
	 */
	private const BASE_MULTIPLIER = 10000;

	/**
	 * Compute lot_sort_key from lot_no string.
	 *
	 * Encoding: numeric_part * 10000 + suffix_ordinal
	 * - Extracts leading numeric digits as primary sort value
	 * - Extracts suffix characters (A-Z, 0-9) for secondary sorting
	 * - Examples: "2" → 20000, "10" → 100000, "10A" → 100001, "10B" → 100002
	 *
	 * @param string $lot_no Lot number string (e.g., "10", "10A", "LOT-5").
	 * @param int    $item_id Item ID for fallback tiebreaker (optional).
	 * @return int Sort key as BIGINT UNSIGNED.
	 */
	public static function compute_lot_sort_key( string $lot_no, int $item_id = 0 ): int {
		if ( empty( $lot_no ) ) {
			// Empty lot_no: use item_id as tiebreaker if provided, otherwise 0.
			return $item_id > 0 ? (int) ( $item_id % self::BASE_MULTIPLIER ) : 0;
		}

		// Remove common prefixes like "LOT-", "LOT ", "LOT:", etc.
		$lot_no = preg_replace( '/^LOT[-:\s]+/i', '', $lot_no );
		$lot_no = trim( $lot_no );

		if ( empty( $lot_no ) ) {
			return $item_id > 0 ? (int) ( $item_id % self::BASE_MULTIPLIER ) : 0;
		}

		// Extract leading numeric part.
		if ( ! preg_match( '/^(\d+)/', $lot_no, $matches ) ) {
			// No leading digits found: treat as 0, use item_id as tiebreaker.
			return $item_id > 0 ? (int) ( $item_id % self::BASE_MULTIPLIER ) : 0;
		}

		$numeric_part = (int) $matches[1];

		// Extract suffix (everything after the first numeric group).
		$suffix = substr( $lot_no, strlen( $matches[0] ) );
		$suffix = trim( $suffix );

		// Compute suffix ordinal.
		$suffix_ordinal = 0;
		if ( ! empty( $suffix ) ) {
			// Handle single character suffixes (A-Z, 0-9).
			$first_char = strtoupper( $suffix[0] );
			if ( ctype_alpha( $first_char ) ) {
				// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
				// A=1, B=2, ..., Z=26.
				$suffix_ordinal = ord( $first_char ) - ord( 'A' ) + 1;
			} elseif ( ctype_digit( $first_char ) ) {
				// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
				// Numeric suffix: 0=0, 1=27, 2=28, ..., 9=35.
				$suffix_ordinal = (int) $first_char + 27;
			}
			// If suffix is longer or contains special chars, use first char only.
			// Max suffix_ordinal is 35 (for '9'), which fits within 9999 limit.
		}

		// Encode: numeric_part * BASE_MULTIPLIER + suffix_ordinal.
		$sort_key = ( $numeric_part * self::BASE_MULTIPLIER ) + $suffix_ordinal;

		// Ensure result fits in BIGINT UNSIGNED (max ~4.2 billion).
		// With BASE_MULTIPLIER=10000, max numeric_part is ~420,000 which is more than sufficient.
		return (int) min( $sort_key, PHP_INT_MAX );
	}

	/**
	 * Batch compute lot_sort_key for multiple lot numbers.
	 *
	 * @param array<int, string> $lot_numbers Array of lot_no strings keyed by item_id.
	 * @return array<int, int> Array of sort keys keyed by item_id.
	 */
	public static function batch_compute( array $lot_numbers ): array {
		$results = array();
		foreach ( $lot_numbers as $item_id => $lot_no ) {
			$results[ $item_id ] = self::compute_lot_sort_key( (string) $lot_no, (int) $item_id );
		}
		return $results;
	}
}
