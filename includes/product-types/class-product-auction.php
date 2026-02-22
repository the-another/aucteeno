<?php
/**
 * Auction Product Class
 *
 * Custom WooCommerce product class for auction product type.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace The_Another\Plugin\Aucteeno\Product_Types;

use WC_Product_External;
use The_Another\Plugin\Aucteeno\Product_Types\Datastores\Datastore_Auction;

/**
 * Class Product_Auction
 *
 * Product class for auction products, extends WC_Product_External.
 */
class Product_Auction extends WC_Product_External {

	use Traits\Use_Normalized_Method_Names;

	/**
	 * Product type constant.
	 *
	 * @var string
	 */
	public const PRODUCT_TYPE = 'aucteeno-ext-auction';

	/**
	 * Product type.
	 *
	 * @var string
	 */
	protected $product_type = self::PRODUCT_TYPE;

	/**
	 * Get internal type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return self::PRODUCT_TYPE;
	}

	/**
	 * Get data store.
	 * Override to ensure custom datastore is always used.
	 *
	 * @return Datastore_Auction Data store instance.
	 */
	public function get_data_store(): Datastore_Auction {
		if ( ! $this->data_store instanceof Datastore_Auction ) {
			$this->data_store = new Datastore_Auction();
		}
		return $this->data_store;
	}

	/**
	 * Extra data for this object type.
	 * Must be defined as class property BEFORE parent constructor is called.
	 * Includes parent's extra_data keys (product_url, button_text) since we override the property.
	 *
	 * Note: Timestamp fields are computed from UTC values, not stored separately.
	 *
	 * @var array<string, mixed>
	 */
	protected $extra_data = array(
		// From WC_Product_External.
		'product_url'                      => '',
		'button_text'                      => '',
		// Custom fields.
		'aucteeno_location'                => array(
			'country'     => '',
			'subdivision' => '',
			'city'        => '',
			'postal_code' => '',
			'address'     => '',
			'address2'    => '',
		),
		'aucteeno_notice'                  => '',
		'aucteeno_bidding_notice'          => '',
		'aucteeno_directions'              => '',
		'aucteeno_terms_conditions'        => '',
		'aucteeno_bidding_starts_at_utc'   => '',
		'aucteeno_bidding_starts_at_local' => '',
		'aucteeno_bidding_ends_at_utc'     => '',
		'aucteeno_bidding_ends_at_local'   => '',
	);


	/**
	 * Get location.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return array<string, string> Location data.
	 */
	public function get_location( string $context = 'view' ): array {
		$location = $this->get_prop( 'aucteeno_location', $context );
		if ( ! is_array( $location ) ) {
			$location = array();
		}

		// Ensure all keys exist with defaults.
		$defaults = array(
			'country'     => '',
			'subdivision' => '',
			'city'        => '',
			'postal_code' => '',
			'address'     => '',
			'address2'    => '',
		);

		$location = array_merge( $defaults, $location );

		// Load country and subdivision from taxonomies if not in location array.
		if ( empty( $location['country'] ) || empty( $location['subdivision'] ) ) {
			$product_id = $this->get_id();
			if ( $product_id ) {
				// Get country terms.
				$country_terms = wp_get_post_terms( $product_id, 'country', array( 'fields' => 'ids' ) );
				if ( ! empty( $country_terms ) && ! is_wp_error( $country_terms ) ) {
					$location['country'] = (string) $country_terms[0];
				}

				// Get subdivision terms.
				$subdivision_terms = wp_get_post_terms( $product_id, 'subdivision', array( 'fields' => 'ids' ) );
				if ( ! empty( $subdivision_terms ) && ! is_wp_error( $subdivision_terms ) ) {
					$location['subdivision'] = (string) $subdivision_terms[0];
				}
			}
		}

		return $location;
	}

	/**
	 * Set location.
	 *
	 * Setters should only modify object state via set_prop().
	 * Database persistence is handled by the datastore.
	 *
	 * @param array<string, string>|string $location Location data (accepts array or serialized string from meta fields).
	 * @return void
	 */
	public function set_location( array|string $location ): void {
		// Handle string input (serialized or JSON).
		if ( is_string( $location ) ) {
			// Try to unserialize first (WordPress format).
			$location = maybe_unserialize( $location );
			// If still a string, try JSON decode.
			if ( is_string( $location ) ) {
				$decoded = json_decode( $location, true );
				if ( is_array( $decoded ) ) {
					$location = $decoded;
				} else {
					// If all else fails, use empty array.
					$location = array();
				}
			}
		}

		// Ensure location is an array.
		if ( ! is_array( $location ) ) {
			$location = array();
		}

		$sanitized = array(
			'country'     => isset( $location['country'] ) ? sanitize_text_field( $location['country'] ) : '',
			'subdivision' => isset( $location['subdivision'] ) ? sanitize_text_field( $location['subdivision'] ) : '',
			'city'        => isset( $location['city'] ) ? sanitize_text_field( $location['city'] ) : '',
			'postal_code' => isset( $location['postal_code'] ) ? sanitize_text_field( $location['postal_code'] ) : '',
			'address'     => isset( $location['address'] ) ? sanitize_text_field( $location['address'] ) : '',
			'address2'    => isset( $location['address2'] ) ? sanitize_text_field( $location['address2'] ) : '',
		);

		// Only set the prop - datastore handles database persistence.
		$this->set_prop( 'aucteeno_location', $sanitized );
	}

	/**
	 * Get notice.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Notice.
	 */
	public function get_notice( string $context = 'view' ): string {
		$value = $this->get_prop( 'aucteeno_notice', $context );
		return $value ? $value : '';
	}

	/**
	 * Set notice.
	 *
	 * @param string $notice Notice.
	 * @return void
	 */
	public function set_notice( string $notice ): void {
		$this->set_prop( 'aucteeno_notice', sanitize_textarea_field( $notice ) );
	}

	/**
	 * Get bidding notice.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Bidding notice.
	 */
	public function get_bidding_notice( string $context = 'view' ): string {
		$value = $this->get_prop( 'aucteeno_bidding_notice', $context );
		return $value ? $value : '';
	}

	/**
	 * Set bidding notice.
	 *
	 * @param string $bidding_notice Bidding notice.
	 * @return void
	 */
	public function set_bidding_notice( string $bidding_notice ): void {
		$this->set_prop( 'aucteeno_bidding_notice', sanitize_textarea_field( $bidding_notice ) );
	}

	/**
	 * Get directions.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Directions.
	 */
	public function get_directions( string $context = 'view' ): string {
		$value = $this->get_prop( 'aucteeno_directions', $context );
		return $value ? $value : '';
	}

	/**
	 * Set directions.
	 *
	 * @param string $directions Directions.
	 * @return void
	 */
	public function set_directions( string $directions ): void {
		$this->set_prop( 'aucteeno_directions', sanitize_textarea_field( $directions ) );
	}

	/**
	 * Get terms and conditions.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Terms and conditions.
	 */
	public function get_terms_conditions( string $context = 'view' ): string {
		$value = $this->get_prop( 'aucteeno_terms_conditions', $context );
		return $value ? $value : '';
	}

	/**
	 * Set terms and conditions.
	 *
	 * @param string $terms_conditions Terms and conditions.
	 * @return void
	 */
	public function set_terms_conditions( string $terms_conditions ): void {
		$this->set_prop( 'aucteeno_terms_conditions', sanitize_textarea_field( $terms_conditions ) );
	}

	/**
	 * Get bidding starts at timestamp.
	 * Computed from bidding_starts_at_utc - this is a read-only derived value.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return int|null Bidding starts at timestamp.
	 */
	public function get_bidding_starts_at_timestamp( string $context = 'view' ): ?int {
		$utc = $this->get_bidding_starts_at_utc( $context );
		if ( ! empty( $utc ) ) {
			$timestamp = strtotime( $utc . ' UTC' );
			return $timestamp ? $timestamp : null;
		}
		return null;
	}

	/**
	 * Get bidding starts at UTC datetime string.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Bidding starts at UTC datetime string (Y-m-d H:i:s format).
	 */
	public function get_bidding_starts_at_utc( string $context = 'view' ): string {
		$value = $this->get_prop( 'aucteeno_bidding_starts_at_utc', $context );
		return $value ? $value : '';
	}

	/**
	 * Set bidding starts at UTC datetime string.
	 * Also syncs local datetime.
	 *
	 * @param string $utc_datetime UTC datetime string (Y-m-d H:i:s format).
	 * @return void
	 */
	public function set_bidding_starts_at_utc( string $utc_datetime ): void {
		$utc_datetime = sanitize_text_field( $utc_datetime );
		$this->set_prop( 'aucteeno_bidding_starts_at_utc', $utc_datetime );

		// Sync local datetime from UTC.
		if ( ! empty( $utc_datetime ) ) {
			$timestamp = strtotime( $utc_datetime . ' UTC' );
			if ( $timestamp ) {
				$this->set_prop( 'aucteeno_bidding_starts_at_local', wp_date( 'Y-m-d H:i:s', $timestamp ) );
			}
		} else {
			$this->set_prop( 'aucteeno_bidding_starts_at_local', '' );
		}
	}

	/**
	 * Get bidding starts at local datetime string.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Bidding starts at local datetime string (Y-m-d H:i:s format).
	 */
	public function get_bidding_starts_at_local( string $context = 'view' ): string {
		$value = $this->get_prop( 'aucteeno_bidding_starts_at_local', $context );
		return $value ? $value : '';
	}

	/**
	 * Set bidding starts at local datetime string.
	 * Also syncs UTC datetime.
	 *
	 * @param string $local_datetime Local datetime string (Y-m-d H:i:s format).
	 * @return void
	 */
	public function set_bidding_starts_at_local( string $local_datetime ): void {
		$local_datetime = sanitize_text_field( $local_datetime );
		$this->set_prop( 'aucteeno_bidding_starts_at_local', $local_datetime );

		// Sync UTC datetime from local.
		if ( ! empty( $local_datetime ) ) {
			$timestamp = strtotime( $local_datetime );
			if ( $timestamp ) {
				$this->set_prop( 'aucteeno_bidding_starts_at_utc', gmdate( 'Y-m-d H:i:s', $timestamp ) );
			}
		} else {
			$this->set_prop( 'aucteeno_bidding_starts_at_utc', '' );
		}
	}

	/**
	 * Get bidding ends at timestamp.
	 * Computed from bidding_ends_at_utc - this is a read-only derived value.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return int|null Bidding ends at timestamp.
	 */
	public function get_bidding_ends_at_timestamp( string $context = 'view' ): ?int {
		$utc = $this->get_bidding_ends_at_utc( $context );
		if ( ! empty( $utc ) ) {
			$timestamp = strtotime( $utc . ' UTC' );
			return $timestamp ? $timestamp : null;
		}
		return null;
	}

	/**
	 * Get bidding ends at UTC datetime string.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Bidding ends at UTC datetime string (Y-m-d H:i:s format).
	 */
	public function get_bidding_ends_at_utc( string $context = 'view' ): string {
		$value = $this->get_prop( 'aucteeno_bidding_ends_at_utc', $context );
		return $value ? $value : '';
	}

	/**
	 * Set bidding ends at UTC datetime string.
	 * Also syncs local datetime.
	 *
	 * @param string $utc_datetime UTC datetime string (Y-m-d H:i:s format).
	 * @return void
	 */
	public function set_bidding_ends_at_utc( string $utc_datetime ): void {
		$utc_datetime = sanitize_text_field( $utc_datetime );
		$this->set_prop( 'aucteeno_bidding_ends_at_utc', $utc_datetime );

		// Sync local datetime from UTC.
		if ( ! empty( $utc_datetime ) ) {
			$timestamp = strtotime( $utc_datetime . ' UTC' );
			if ( $timestamp ) {
				$this->set_prop( 'aucteeno_bidding_ends_at_local', wp_date( 'Y-m-d H:i:s', $timestamp ) );
			}
		} else {
			$this->set_prop( 'aucteeno_bidding_ends_at_local', '' );
		}
	}

	/**
	 * Get bidding ends at local datetime string.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Bidding ends at local datetime string (Y-m-d H:i:s format).
	 */
	public function get_bidding_ends_at_local( string $context = 'view' ): string {
		$value = $this->get_prop( 'aucteeno_bidding_ends_at_local', $context );
		return $value ? $value : '';
	}

	/**
	 * Set bidding ends at local datetime string.
	 * Also syncs UTC datetime.
	 *
	 * @param string $local_datetime Local datetime string (Y-m-d H:i:s format).
	 * @return void
	 */
	public function set_bidding_ends_at_local( string $local_datetime ): void {
		$local_datetime = sanitize_text_field( $local_datetime );
		$this->set_prop( 'aucteeno_bidding_ends_at_local', $local_datetime );

		// Sync UTC datetime from local.
		if ( ! empty( $local_datetime ) ) {
			$timestamp = strtotime( $local_datetime );
			if ( $timestamp ) {
				$this->set_prop( 'aucteeno_bidding_ends_at_utc', gmdate( 'Y-m-d H:i:s', $timestamp ) );
			}
		} else {
			$this->set_prop( 'aucteeno_bidding_ends_at_utc', '' );
		}
	}

	/**
	 * Sync product data.
	 * Called by WooCommerce during deferred product sync.
	 * Auction products don't have variations or children to sync, so this is a no-op.
	 *
	 * @param \WC_Product|int $product Product object or ID (unused, kept for compatibility).
	 * @return \WC_Product Current product instance.
	 */
	public function sync( $product = null ) {
		// Auction products don't require syncing as they don't have variations or children.
		// This method exists to satisfy WooCommerce's deferred product sync mechanism.
		return $this;
	}
}
