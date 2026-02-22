<?php
/**
 * DateTime Helper Class
 *
 * Provides utility methods for datetime conversions.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace The_Another\Plugin\Aucteeno\Helpers;

/**
 * Class DateTime_Helper
 *
 * Helper methods for datetime operations.
 */
class DateTime_Helper {

	/**
	 * Convert GMT datetime to Unix timestamp.
	 *
	 * @param string|null $datetime GMT datetime string.
	 * @return int|null Unix timestamp or null.
	 */
	public static function datetime_to_timestamp( ?string $datetime ): ?int {
		if ( empty( $datetime ) ) {
			return null;
		}

		$timestamp = strtotime( $datetime . ' GMT' );
		return false !== $timestamp ? $timestamp : null;
	}

	/**
	 * Convert Unix timestamp to GMT datetime.
	 *
	 * @param int|null $timestamp Unix timestamp.
	 * @return string|null GMT datetime string or null.
	 */
	public static function timestamp_to_datetime( ?int $timestamp ): ?string {
		if ( empty( $timestamp ) ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Format countdown fallback string for SSR.
	 *
	 * Display rules:
	 * - Past end time: "Ended"
	 * - >= 2 days: "X days"
	 * - 1 day: "1 day"
	 * - >= 2 hours: "X hours"
	 * - 1 hour: "1 hour"
	 * - >= 2 minutes: "X min"
	 * - Default: "1 min"
	 *
	 * @param int $end_ts End time as Unix timestamp.
	 * @return string Formatted countdown string.
	 */
	public static function countdown_fallback( int $end_ts ): string {
		$now = time();

		if ( $end_ts <= $now ) {
			return 'Ended';
		}

		$diff  = $end_ts - $now;
		$mins  = (int) floor( $diff / 60 );
		$hours = (int) floor( $diff / 3600 );
		$days  = (int) floor( $diff / 86400 );

		if ( $days >= 2 ) {
			return $days . ' days';
		}
		if ( 1 === $days ) {
			return '1 day';
		}
		if ( $hours >= 2 ) {
			return $hours . ' hours';
		}
		if ( 1 === $hours ) {
			return '1 hour';
		}
		if ( $mins >= 2 ) {
			return $mins . ' min';
		}

		return '1 min';
	}

	/**
	 * Convert UTC datetime string to ISO 8601 format.
	 *
	 * @param string $utc_datetime UTC datetime string (Y-m-d H:i:s format).
	 * @return string ISO 8601 formatted datetime string.
	 */
	public static function to_iso8601( string $utc_datetime ): string {
		if ( empty( $utc_datetime ) ) {
			return '';
		}

		$timestamp = strtotime( $utc_datetime . ' UTC' );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'c', $timestamp );
	}
}
