<?php
/**
 * Item Datastore Class
 *
 * Datastore for item product type with custom price logic based on auction status.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Product_Types\Datastores;

use WC_Product_Data_Store_CPT;
use TheAnother\Plugin\Aucteeno\Helpers\Location_Helper;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Item;

/**
 * Class Datastore_Item
 *
 * Datastore for item products with price logic based on parent auction status.
 */
class Datastore_Item extends WC_Product_Data_Store_CPT {

	/**
	 * Keys handled by parent datastore (WC_Product_External).
	 * These should NOT be saved/read with our custom prefix.
	 *
	 * @var array<string>
	 */
	private const PARENT_HANDLED_KEYS = array(
		'product_url',
		'button_text',
	);

	/**
	 * Create a new product in the database.
	 *
	 * @param \WC_Product $product Product object.
	 * @return void
	 */
	public function create( &$product ): void {
		// Sync auction_id with post_parent before parent create.
		if ( $product instanceof Product_Item ) {
			$auction_id = $product->get_auction_id( 'edit' );
			if ( $auction_id > 0 ) {
				$product->set_parent_id( $auction_id );
			}
		}

		parent::create( $product );

		// Only proceed if this is a Product_Item instance.
		if ( ! ( $product instanceof Product_Item ) ) {
			return;
		}

		// Save all extra_data fields for new products.
		$this->save_item_extra_data( $product );
	}

	/**
	 * Update product data.
	 *
	 * @param \WC_Product $product Product object.
	 * @return void
	 */
	public function update( &$product ): void {
		// Only proceed if this is a Product_Item instance.
		if ( ! ( $product instanceof Product_Item ) ) {
			parent::update( $product );
			return;
		}

		// CRITICAL: Capture changes BEFORE calling parent::update().
		// Parent's apply_changes() clears the changes array!
		$changes = $product->get_changes();
		$extra_data_keys = $product->get_extra_data_keys();

		// Filter to only extra_data changes.
		$extra_data_changes = array_intersect_key( $changes, array_flip( $extra_data_keys ) );

		// Sync auction_id with post_parent before parent update.
		$product_id = $product->get_id();
		if ( array_key_exists( 'aucteeno_auction_id', $changes ) && $product_id ) {
			$auction_id = $product->get_auction_id( 'edit' );
			if ( $auction_id > 0 ) {
				wp_update_post(
					array(
						'ID'          => $product_id,
						'post_parent' => $auction_id,
					)
				);
			}
		}

		// Call parent which handles core WooCommerce fields.
		parent::update( $product );

		// Now save our custom fields using the captured changes.
		if ( ! empty( $extra_data_changes ) ) {
			$this->save_item_extra_data( $product, $extra_data_changes );
		}
	}

	/**
	 * Read product data.
	 *
	 * @param \WC_Product $product Product object.
	 * @return void
	 */
	public function read( &$product ): void {
		parent::read( $product );

		// Only proceed if this is a Product_Item instance.
		if ( ! ( $product instanceof Product_Item ) ) {
			return;
		}

		$product_id = $product->get_id();
		if ( ! $product_id ) {
			return;
		}

		// Load auction_id from post_parent.
		$parent_id = wp_get_post_parent_id( $product_id );
		if ( $parent_id > 0 ) {
			$product->set_auction_id( $parent_id );
		}

		// Load custom extra_data fields.
		$this->read_item_extra_data( $product );
	}

	/**
	 * Read item extra data from database.
	 *
	 * @param Product_Item $product Product object.
	 * @return void
	 */
	private function read_item_extra_data( Product_Item $product ): void {
		$product_id = $product->get_id();
		$extra_data_keys = $product->get_extra_data_keys();

        // Load all meta for product
        $all_meta = get_post_meta( $product_id );

		foreach ( $extra_data_keys as $key ) {
			// Skip keys handled by parent datastore.
			if ( in_array( $key, self::PARENT_HANDLED_KEYS, true ) ) {
				continue;
			}

			// Skip location - handled specially below.
			// Skip auction_id - loaded from post_parent.
			if ( 'aucteeno_location' === $key || 'aucteeno_auction_id' === $key ) {
				continue;
			}

			$setter = 'set_' . $key;
            // Drop `_aucteeno_` part from setter.
            $setter = str_replace( '_aucteeno_', '_', $setter );
			if ( ! is_callable( array( $product, $setter ) ) ) {
				continue;
			}

			$meta_key = '_' . $key;
            if (isset($all_meta[ $meta_key ])) {
                if (is_array( $all_meta[ $meta_key ] )) {
                    $value = current( $all_meta[ $meta_key ] );
                } else {
                    $value = $all_meta[ $meta_key ];
                }
            }

			// Only set if value exists in database.
			if ( !empty( $value ) ) {
				$product->{$setter}( $value );
			}
		}

		// Load location with taxonomy data.
		$this->read_location_data( $product );
	}

	/**
	 * Read location data from database.
	 *
	 * @param Product_Item $product Product object.
	 * @return void
	 */
	private function read_location_data( Product_Item $product ): void {
		$product_id = $product->get_id();

		// Load location array from meta.
		$location_data = get_post_meta( $product_id, '_aucteeno_location', true );
		if ( ! is_array( $location_data ) ) {
			$location_data = array();
		}

		// Ensure all location fields have defaults.
		$location_data = array_merge(
			array(
				'country'     => '',
				'subdivision' => '',
				'city'        => '',
				'postal_code' => '',
				'address'     => '',
				'address2'    => '',
			),
			$location_data
		);

		$product->set_location( $location_data );
	}

	/**
	 * Save item extra data to database.
	 *
	 * @param Product_Item $product Product object.
	 * @param array<string, mixed>|null $changes Changed fields. If null, saves all extra_data.
	 * @return void
	 */
	private function save_item_extra_data( Product_Item $product, ?array $changes = null ): void {
		$product_id = $product->get_id();
		if ( ! $product_id ) {
			return;
		}

		$extra_data_keys = $product->get_extra_data_keys();
		// If no specific changes provided, save all extra_data (for create).
		$keys_to_save = null === $changes
			? $extra_data_keys
			: array_keys( $changes );

		foreach ( $keys_to_save as $key ) {
			// Skip if not an extra_data key.
			if ( ! in_array( $key, $extra_data_keys, true ) ) {
				continue;
			}

			// Skip keys handled by parent datastore.
			if ( in_array( $key, self::PARENT_HANDLED_KEYS, true ) ) {
				continue;
			}

            $meta_key = '_' . $key;

			// Skip location - handled specially below.
			// Skip auction_id - synced with post_parent separately.
			if ( 'aucteeno_location' === $key || 'aucteeno_auction_id' === $key ) {
				continue;
			}

			$getter = 'get_' . $key;
			if ( ! is_callable( array( $product, $getter ) ) ) {
				continue;
			}

			$value = $product->{$getter}( 'edit' );

			// Handle string values with slashing.
			if ( is_string( $value ) ) {
				$value = wp_slash( $value );
			}

			update_post_meta( $product_id, $meta_key, $value );
		}

		// Save location if it was changed (or if creating new product).
		if ( null === $changes || array_key_exists( 'aucteeno_location', $changes ) ) {
			$this->save_location_data( $product );
		}
	}

	/**
	 * Make sure we store the product type and version (to track data changes).
	 * Override to ensure item product type is always set correctly.
	 *
	 * @param \WC_Product $product Product object.
	 * @return void
	 */
	protected function update_version_and_type( &$product ): void {
		// Ensure product type taxonomy is set correctly for item products.
		if ( $product instanceof Product_Item ) {
			wp_set_object_terms( $product->get_id(), Product_Item::PRODUCT_TYPE, 'product_type' );
		}

		// Call parent to handle version and type change actions.
		parent::update_version_and_type( $product );
	}

	/**
	 * Save location data to database.
	 *
	 * @param Product_Item $product Product object.
	 * @return void
	 */
	private function save_location_data( Product_Item $product ): void {
		$product_id = $product->get_id();
		if ( ! $product_id ) {
			return;
		}

		$location = $product->get_location( 'edit' );
		$country_term_id = 0;
		$subdivision_term_id = 0;

		// Process WooCommerce codes if provided (from dropdowns).
		if ( ! empty( $location['country'] ) ) {
			$country_code = $location['country'];
			$countries = WC()->countries->get_countries();
			if ( isset( $countries[ $country_code ] ) ) {
				$country_name = $countries[ $country_code ];
				$country_term_id = Location_Helper::get_or_create_country_term( $country_code, $country_name );
			}
		}

		if ( ! empty( $location['subdivision'] ) && ! empty( $location['country'] ) ) {
			$state_code = $location['subdivision'];
			$country_code = $location['country'];
			$states = WC()->countries->get_states( $country_code );
			if ( is_array( $states ) && isset( $states[ $state_code ] ) ) {
				$state_name = $states[ $state_code ];
				$subdivision_term_id = Location_Helper::get_or_create_subdivision_term( $country_code, $state_code, $state_name );
			}
		}

		// Assign terms to product (hierarchical taxonomy - assign both country and subdivision if available).
		$terms_to_assign = array();
		if ( $subdivision_term_id > 0 ) {
			// If subdivision exists, assign it (this also assigns parent country via hierarchy).
			$terms_to_assign[] = $subdivision_term_id;
			// Get parent country term ID.
		}
        if ( $country_term_id > 0 ) {
			// Only country term (no subdivision).
			$terms_to_assign[] = $country_term_id;
		}

		// Assign all location terms to product.
		wp_set_post_terms( $product_id, $terms_to_assign, 'aucteeno-location', false );

		// Save full location array to meta.
		update_post_meta( $product_id, '_aucteeno_location', $location );
	}
}
