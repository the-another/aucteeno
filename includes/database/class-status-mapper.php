<?php
/**
 * Status Mapper Class
 *
 * Maps WordPress post status strings to numeric values for database storage.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Database;

/**
 * Class Status_Mapper
 *
 * Maps post status strings to numeric values:
 * - publish → 10
 * - schedule → 20
 * - expire → 30
 */
class Status_Mapper {

	/**
	 * Status to number mapping.
	 *
	 * @var array<string, int>
	 */
	private const STATUS_MAP = array(
		'publish'  => 10,
		'schedule' => 20,
		'expire'   => 30,
	);

	/**
	 * Number to status mapping.
	 *
	 * @var array<int, string>
	 */
	private const NUMBER_MAP = array(
		10 => 'publish',
		20 => 'schedule',
		30 => 'expire',
	);

	/**
	 * Convert status string to number.
	 *
	 * @param string $status Status string.
	 * @return int Status number.
	 */
	public static function status_to_number( string $status ): int {
		return self::STATUS_MAP[ $status ] ?? 10; // Default to publish.
	}

	/**
	 * Convert status number to string.
	 *
	 * @param int $number Status number.
	 * @return string Status string.
	 */
	public static function number_to_status( int $number ): string {
		return self::NUMBER_MAP[ $number ] ?? 'publish'; // Default to publish.
	}

	/**
	 * Get all valid status strings.
	 *
	 * @return array<string> Array of status strings.
	 */
	public static function get_valid_statuses(): array {
		return array_keys( self::STATUS_MAP );
	}

	/**
	 * Get all valid status numbers.
	 *
	 * @return array<int> Array of status numbers.
	 */
	public static function get_valid_numbers(): array {
		return array_values( self::STATUS_MAP );
	}

	/**
	 * Check if status is valid.
	 *
	 * @param string $status Status string.
	 * @return bool True if valid.
	 */
	public static function is_valid_status( string $status ): bool {
		return isset( self::STATUS_MAP[ $status ] );
	}

	/**
	 * Check if number is valid.
	 *
	 * @param int $number Status number.
	 * @return bool True if valid.
	 */
	public static function is_valid_number( int $number ): bool {
		return isset( self::NUMBER_MAP[ $number ] );
	}
}
