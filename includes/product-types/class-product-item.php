<?php
/**
 * Item Product Class
 *
 * Custom WooCommerce product class for item product type.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Product_Types;

use WC_Product_External;
use TheAnother\Plugin\Aucteeno\Product_Types\Datastores\Datastore_Item;

/**
 * Class Product_Item
 *
 * Product class for item products, extends WC_Product_External.
 */
class Product_Item extends WC_Product_External {

	use Traits\Use_Normalized_Method_Names;

	/**
	 * Product type constant.
	 *
	 * @var string
	 */
	public const PRODUCT_TYPE = 'aucteeno-ext-item';

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
	 * @return Datastore_Item Data store instance.
	 */
	public function get_data_store(): Datastore_Item {
		if ( ! $this->data_store instanceof Datastore_Item ) {
			$this->data_store = new Datastore_Item();
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
		'aucteeno_auction_id'              => 0,
		'aucteeno_lot_no'                  => '',
		'aucteeno_description'             => '',
		'aucteeno_asking_bid'              => 0.0,
		'aucteeno_current_bid'             => 0.0,
		'aucteeno_sold_price'              => 0.0,
		'aucteeno_sold_at_utc'             => '',
		'aucteeno_sold_at_local'           => '',
		'aucteeno_location'                => array(
			'country'     => '',
			'subdivision' => '',
			'city'        => '',
			'postal_code' => '',
			'address'     => '',
			'address2'    => '',
		),
		'aucteeno_bidding_starts_at_utc'   => '',
		'aucteeno_bidding_starts_at_local' => '',
		'aucteeno_bidding_ends_at_utc'     => '',
		'aucteeno_bidding_ends_at_local'   => '',
	);


	/**
	 * Get auction ID.
	 * Falls back to parent auction ID if not set in extra_data.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return int Auction ID.
	 */
	public function get_auction_id( string $context = 'view' ): int {
		$auction_id = $this->get_prop( 'aucteeno_auction_id', $context );
		if ( null !== $auction_id && '' !== $auction_id && $auction_id > 0 ) {
			return (int) $auction_id;
		}
		// Fall back to parent auction ID from post_parent.
		$parent_id = wp_get_post_parent_id( $this->get_id() );
		return $parent_id > 0 ? $parent_id : 0;
	}

	/**
	 * Set auction ID.
	 * Also updates post_parent to keep them in sync.
	 *
	 * @param int|string $auction_id Auction ID (accepts int or string from meta fields).
	 * @return void
	 */
	public function set_auction_id( int|string $auction_id ): void {
		$auction_id = max( 0, absint( $auction_id ) );
		$this->set_prop( 'aucteeno_auction_id', $auction_id );
	}

	/**
	 * Get lot number.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Lot number.
	 */
	public function get_lot_no( string $context = 'view' ): string {
		$value = $this->get_prop( 'aucteeno_lot_no', $context );
		return null !== $value && '' !== $value ? (string) $value : '';
	}

	/**
	 * Set lot number.
	 *
	 * @param string $lot_no Lot number.
	 * @return void
	 */
	public function set_lot_no( string $lot_no ): void {
		$this->set_prop( 'aucteeno_lot_no', sanitize_text_field( $lot_no ) );
	}

	/**
	 * Get description.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Description.
	 */
	public function get_description( $context = 'view' ) {
		return $this->get_prop( 'aucteeno_description', $context );
	}

	/**
	 * Set description.
	 *
	 * @param string $description Description.
	 * @return void
	 */
	public function set_description( $description ): void {
		$this->set_prop( 'aucteeno_description', sanitize_textarea_field( $description ) );
	}

	/**
	 * Get asking bid.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return float Asking bid.
	 */
	public function get_asking_bid( string $context = 'view' ): float {
		$value = $this->get_prop( 'aucteeno_asking_bid', $context );
		return null !== $value && '' !== $value ? (float) $value : 0.0;
	}

	/**
	 * Set asking bid.
	 *
	 * @param float|string $asking_bid Asking bid (accepts float or string from meta fields).
	 * @return void
	 */
	public function set_asking_bid( float|string $asking_bid ): void {
		$this->set_prop( 'aucteeno_asking_bid', max( 0.0, (float) $asking_bid ) );
	}

	/**
	 * Get current bid.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return float Current bid.
	 */
	public function get_current_bid( string $context = 'view' ): float {
		$value = $this->get_prop( 'aucteeno_current_bid', $context );
		return null !== $value && '' !== $value ? (float) $value : 0.0;
	}

	/**
	 * Set current bid.
	 *
	 * @param float|string $current_bid Current bid (accepts float or string from meta fields).
	 * @return void
	 */
	public function set_current_bid( float|string $current_bid ): void {
		$this->set_prop( 'aucteeno_current_bid', max( 0.0, (float) $current_bid ) );
	}

	/**
	 * Get sold price.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return float Sold price.
	 */
	public function get_sold_price( string $context = 'view' ): float {
		$value = $this->get_prop( 'aucteeno_sold_price', $context );
		return null !== $value && '' !== $value ? (float) $value : 0.0;
	}

	/**
	 * Set sold price.
	 *
	 * @param float|string $sold_price Sold price (accepts float or string from meta fields).
	 * @return void
	 */
	public function set_sold_price( float|string $sold_price ): void {
		$this->set_prop( 'aucteeno_sold_price', max( 0.0, (float) $sold_price ) );
	}

	/**
	 * Get sold at timestamp.
	 * Computed from sold_at_utc - this is a read-only derived value.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return int|null Sold at timestamp.
	 */
	public function get_sold_at_timestamp( string $context = 'view' ): ?int {
		$utc = $this->get_sold_at_utc( $context );
		if ( ! empty( $utc ) ) {
			$timestamp = strtotime( $utc . ' UTC' );
			return $timestamp ? $timestamp : null;
		}
		return null;
	}

	/**
	 * Get sold at UTC datetime string.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Sold at UTC datetime string (Y-m-d H:i:s format).
	 */
	public function get_sold_at_utc( string $context = 'view' ): string {
		$value = $this->get_prop( 'aucteeno_sold_at_utc', $context );
		return $value ? $value : '';
	}

	/**
	 * Set sold at UTC datetime string.
	 * Also syncs local datetime.
	 *
	 * @param string $utc_datetime UTC datetime string (Y-m-d H:i:s format).
	 * @return void
	 */
	public function set_sold_at_utc( string $utc_datetime ): void {
		$utc_datetime = sanitize_text_field( $utc_datetime );
		$this->set_prop( 'aucteeno_sold_at_utc', $utc_datetime );

		// Sync local datetime from UTC.
		if ( ! empty( $utc_datetime ) ) {
			$timestamp = strtotime( $utc_datetime . ' UTC' );
			if ( $timestamp ) {
				$this->set_prop( 'aucteeno_sold_at_local', wp_date( 'Y-m-d H:i:s', $timestamp ) );
			}
		} else {
			$this->set_prop( 'aucteeno_sold_at_local', '' );
		}
	}

	/**
	 * Get sold at local datetime string.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Sold at local datetime string (Y-m-d H:i:s format).
	 */
	public function get_sold_at_local( string $context = 'view' ): string {
		$value = $this->get_prop( 'aucteeno_sold_at_local', $context );
		return $value ? $value : '';
	}

	/**
	 * Set sold at local datetime string.
	 * Also syncs UTC datetime.
	 *
	 * @param string $local_datetime Local datetime string (Y-m-d H:i:s format).
	 * @return void
	 */
	public function set_sold_at_local( string $local_datetime ): void {
		$local_datetime = sanitize_text_field( $local_datetime );
		$this->set_prop( 'aucteeno_sold_at_local', $local_datetime );

		// Sync UTC datetime from local.
		if ( ! empty( $local_datetime ) ) {
			$timestamp = strtotime( $local_datetime );
			if ( $timestamp ) {
				$this->set_prop( 'aucteeno_sold_at_utc', gmdate( 'Y-m-d H:i:s', $timestamp ) );
			}
		} else {
			$this->set_prop( 'aucteeno_sold_at_utc', '' );
		}
	}

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
	 * Get price.
	 * Override to calculate price based on parent auction bidding status.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Price.
	 */
	public function get_price( $context = 'view' ) {
		$parent_id = $this->get_parent_id();
		if ( ! $parent_id ) {
			// If no parent, return the product's current price.
			return parent::get_price( $context );
		}

		// Get parent auction bidding status from taxonomy.
		$bidding_status_terms = wp_get_post_terms( $parent_id, 'auction-bidding-status', array( 'fields' => 'slugs' ) );

		if ( is_wp_error( $bidding_status_terms ) || empty( $bidding_status_terms ) ) {
			// If no bidding status found, return the product's current price.
			return parent::get_price( $context );
		}

		$status = $bidding_status_terms[0]; // Get the first term slug.

		// Get item data from extra_data.
		$asking_bid  = $this->get_asking_bid( $context );
		$current_bid = $this->get_current_bid( $context );
		$sold_price  = $this->get_sold_price( $context );

		// Return price based on auction bidding status.
		switch ( $status ) {
			case 'upcoming':
				return $asking_bid > 0 ? (string) $asking_bid : '';
			case 'running':
				return $current_bid > 0 ? (string) $current_bid : '';
			case 'expired':
				return $sold_price > 0 ? (string) $sold_price : '';
			default:
				return parent::get_price( $context );
		}
	}

	/**
	 * Check if product is purchasable.
	 * Override parent to return true when product has a URL and is published.
	 * This allows the "Bid Now" button to be displayed on the product page.
	 *
	 * @return bool True if product is purchasable.
	 */
	public function is_purchasable(): bool {
		// Product must exist and be published (or user can edit).
		if ( ! $this->exists() ) {
			return false;
		}

		$status = $this->get_status();
		if ( 'publish' !== $status && ! current_user_can( 'edit_post', $this->get_id() ) ) {
			return false;
		}

		// External products need a product URL to be purchasable.
		$product_url = $this->get_product_url();
		if ( empty( $product_url ) ) {
			return false;
		}

		// Apply filter to allow customization.
		return apply_filters( 'woocommerce_is_purchasable', true, $this );
	}

	/**
	 * Sync product data.
	 * Called by WooCommerce during deferred product sync.
	 * Item products don't have variations or children to sync, so this is a no-op.
	 *
	 * @param \WC_Product|int $product Product object or ID (unused, kept for compatibility).
	 * @return \WC_Product Current product instance.
	 */
	public function sync( $product = null ) {
		// Item products don't require syncing as they don't have variations or children.
		// This method exists to satisfy WooCommerce's deferred product sync mechanism.
		return $this;
	}
}
