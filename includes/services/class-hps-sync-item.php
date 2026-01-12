<?php
/**
 * HPS Sync Item Service Class
 *
 * Handles syncing item products to HPS (High Performance Storage) table.
 *
 * @package Aucteeno
 * @since 2.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Services;

use TheAnother\Plugin\Aucteeno\Database\Database_Items;
use TheAnother\Plugin\Aucteeno\Helpers\Bidding_Status_Mapper;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Item;

/**
 * Class HPS_Sync_Item
 *
 * Service class to handle syncing item products to HPS table.
 */
class HPS_Sync_Item {

	/**
	 * Sync item to HPS table.
	 * Insert or update item record.
	 *
	 * @param int $product_id Product ID.
	 * @return bool True on success, false on failure.
	 */
	public static function sync_item( int $product_id ): bool {
		$product = wc_get_product( $product_id );

		if ( ! $product || ! ( $product instanceof Product_Item ) ) {
			return false;
		}

		$data = self::get_item_data( $product );

		if ( empty( $data ) ) {
			return false;
		}

		global $wpdb;
		$table_name = Database_Items::get_table_name();

		// Check if record exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$table_name} WHERE item_id = %d",
				$product_id
			)
		);

	if ( $exists ) {
		// Update existing record.
		$result = $wpdb->update(
			$table_name,
			$data,
			array( 'item_id' => $product_id ),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%f', '%f' ),
			array( '%d' )
		);

		return false !== $result;
	} else {
		// Insert new record.
		$result = $wpdb->insert(
			$table_name,
			$data,
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%f', '%f' )
		);

		return false !== $result;
	}
	}

	/**
	 * Delete item from HPS table.
	 *
	 * @param int $product_id Product ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_item( int $product_id ): bool {
		global $wpdb;
		$table_name = Database_Items::get_table_name();

		$result = $wpdb->delete(
			$table_name,
			array( 'item_id' => $product_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Extract data from product object for HPS table.
	 *
	 * @param Product_Item $product Product object.
	 * @return array<string, mixed> Data array for HPS table, or empty array on failure.
	 */
	public static function get_item_data( Product_Item $product ): array {
		$product_id = $product->get_id();

		if ( ! $product_id ) {
			return array();
		}

		// Get parent auction ID.
		$auction_id = $product->get_parent_id();
		if ( ! $auction_id ) {
			return array();
		}

		// Get bidding status from parent auction taxonomy.
		$bidding_status = Bidding_Status_Mapper::get_status_from_post( $auction_id );

		// Get UTC datetime strings and convert to Unix timestamps.
		$bidding_starts_at_utc = $product->get_bidding_starts_at_utc();
		$bidding_ends_at_utc   = $product->get_bidding_ends_at_utc();

		$bidding_starts_at = 0;
		if ( ! empty( $bidding_starts_at_utc ) ) {
			$timestamp = strtotime( $bidding_starts_at_utc . ' UTC' );
			if ( $timestamp ) {
				$bidding_starts_at = $timestamp;
			}
		}

		$bidding_ends_at = 0;
		if ( ! empty( $bidding_ends_at_utc ) ) {
			$timestamp = strtotime( $bidding_ends_at_utc . ' UTC' );
			if ( $timestamp ) {
				$bidding_ends_at = $timestamp;
			}
		}

		// Get location data from aucteeno-location taxonomy terms.
		$location_terms = wp_get_post_terms( $product_id, 'aucteeno-location', array( 'fields' => 'all' ) );

		$country     = '';
		$subdivision = '';

		if ( ! is_wp_error( $location_terms ) && ! empty( $location_terms ) ) {
			foreach ( $location_terms as $term ) {
				$code = get_term_meta( $term->term_id, 'code', true );
				if ( empty( $code ) ) {
					continue;
				}

				// If term has a parent, it's a subdivision term.
				if ( $term->parent > 0 ) {
					// Subdivision code is already in COUNTRY:SUBDIVISION format.
					$subdivision = substr( sanitize_text_field( $code ), 0, 50 );
					// Extract country from subdivision code.
					if ( strpos( $code, ':' ) !== false ) {
						$parts = explode( ':', $code, 2 );
						$country = isset( $parts[0] ) ? substr( sanitize_text_field( $parts[0] ), 0, 2 ) : '';
					}
				} else {
					// Top-level term is a country term.
					$country = substr( sanitize_text_field( $code ), 0, 2 );
				}
			}
		}

		// If location is empty, try to get from parent auction.
		if ( empty( $country ) && empty( $subdivision ) ) {
			$parent_location_terms = wp_get_post_terms( $auction_id, 'aucteeno-location', array( 'fields' => 'all' ) );
			if ( ! is_wp_error( $parent_location_terms ) && ! empty( $parent_location_terms ) ) {
				foreach ( $parent_location_terms as $term ) {
					$code = get_term_meta( $term->term_id, 'code', true );
					if ( empty( $code ) ) {
						continue;
					}

					// If term has a parent, it's a subdivision term.
					if ( $term->parent > 0 ) {
						// Subdivision code is already in COUNTRY:SUBDIVISION format.
						$subdivision = substr( sanitize_text_field( $code ), 0, 50 );
						// Extract country from subdivision code.
						if ( strpos( $code, ':' ) !== false ) {
							$parts = explode( ':', $code, 2 );
							$country = isset( $parts[0] ) ? substr( sanitize_text_field( $parts[0] ), 0, 2 ) : '';
						}
					} else {
						// Top-level term is a country term.
						$country = substr( sanitize_text_field( $code ), 0, 2 );
					}
				}
			}
		}

		// Get city from location meta (city is not stored in taxonomy).
		$location = $product->get_location();
		$city     = isset( $location['city'] ) ? substr( sanitize_text_field( $location['city'] ), 0, 50 ) : '';

		// If city is empty, try to get from parent auction.
		if ( empty( $city ) ) {
			$parent_product = wc_get_product( $auction_id );
			if ( $parent_product && method_exists( $parent_product, 'get_location' ) ) {
				$parent_location = $parent_product->get_location();
				if ( ! empty( $parent_location['city'] ) ) {
					$city = substr( sanitize_text_field( $parent_location['city'] ), 0, 50 );
				}
			}
		}

		// Fallback to product location meta for country/subdivision if taxonomy terms not found.
		if ( empty( $country ) && empty( $subdivision ) ) {
			$country = isset( $location['country'] ) ? substr( sanitize_text_field( $location['country'] ), 0, 2 ) : '';
			if ( ! empty( $country ) && ! empty( $location['subdivision'] ) ) {
				$subdivision_code = sanitize_text_field( $location['subdivision'] );
				$subdivision      = $country . ':' . $subdivision_code;
				$subdivision      = substr( $subdivision, 0, 50 );
			}
		}

	// Get lot number.
	$lot_no = $product->get_lot_no();
	$lot_no = substr( sanitize_text_field( $lot_no ), 0, 50 );

	// Compute lot_sort_key from lot_no.
	$lot_sort_key = \TheAnother\Plugin\Aucteeno\Database\Lot_Sort_Helper::compute_lot_sort_key( $lot_no, $product_id );

	// Get user_id (post_author).
	$user_id = get_post_field( 'post_author', $product_id );
	$user_id = $user_id ? absint( $user_id ) : 0;

	return array(
		'auction_id'          => $auction_id,
		'item_id'            => $product_id,
		'user_id'             => $user_id,
		'bidding_status'      => $bidding_status,
		'bidding_starts_at'   => $bidding_starts_at,
		'bidding_ends_at'     => $bidding_ends_at,
		'lot_no'             => $lot_no,
		'lot_sort_key'        => $lot_sort_key,
		'location_country'    => $country,
		'location_subdivision' => $subdivision,
		'location_city'       => $city,
		'location_lat'       => 0.0,
		'location_lng'       => 0.0,
	);
	}
}

