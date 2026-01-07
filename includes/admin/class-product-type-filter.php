<?php
/**
 * Product Type Filter Class
 *
 * Handles custom product type filter dropdown in admin product listing.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Admin;

use TheAnother\Plugin\Aucteeno\Hook_Manager;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Item;

/**
 * Class Product_Type_Filter
 *
 * Modifies the product type filter dropdown to show Auction and Item
 * as sub-options under External/Affiliate product.
 */
class Product_Type_Filter {

	/**
	 * Hook manager instance.
	 *
	 * @var Hook_Manager
	 */
	private $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param Hook_Manager $hook_manager Hook manager instance.
	 */
	public function __construct( Hook_Manager $hook_manager ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Initialize the product type filter.
	 *
	 * @return void
	 */
	public function init(): void {
		// Modify the product type selector dropdown using WooCommerce filter.
		// Use priority 30 to run after product type registration (which uses priority 10).
		$this->hook_manager->register_filter(
			'product_type_selector',
			array( $this, 'modify_product_type_selector' ),
			30
		);

		// Handle filtering query for product listing page.
		$this->hook_manager->register_filter(
			'request',
			array( $this, 'filter_products_by_type' )
		);
	}

	/**
	 * Modify the product type selector dropdown to show Auction and Item as sub-options under External.
	 *
	 * Auction and Item are fundamentally external products (they extend WC_Product_External),
	 * so they should appear as sub-options under the External/Affiliate product type.
	 *
	 * @param array<string, string> $types Product types array with keys as type slugs and values as labels.
	 * @return array<string, string> Modified product types array.
	 */
	public function modify_product_type_selector( array $types ): array {
		// Only modify on product edit page to prevent duplication.
		// This ensures the filter only runs in the correct context.
		global $pagenow, $post_type;

		// Check if we're on the product edit page.
		if ( 'post.php' !== $pagenow && 'post-new.php' !== $pagenow ) {
			return $types;
		}

		// Check if we're editing a product.
		if ( 'product' !== $post_type ) {
			return $types;
		}

		// Check if we have the custom product types.
		if ( ! isset( $types['aucteeno-ext-auction'] ) && ! isset( $types[ Product_Item::PRODUCT_TYPE ] ) ) {
			return $types;
		}

		// Store auction and item types.
		// These are external products, so they should appear under external.
		$auction_type = isset( $types['aucteeno-ext-auction'] ) ? $types['aucteeno-ext-auction'] : null;
		$item_type    = isset( $types[ Product_Item::PRODUCT_TYPE ] ) ? $types[ Product_Item::PRODUCT_TYPE ] : null;

		// Remove them from the array temporarily.
		unset( $types['aucteeno-ext-auction'] );
		unset( $types[ Product_Item::PRODUCT_TYPE ] );

		// Rebuild the array with auction and item as sub-options under external.
		$ordered_types  = array();
		$found_external = false;

		foreach ( $types as $key => $label ) {
			$ordered_types[ $key ] = $label;

			// After external (which is the base type for auction and item), insert auction and item as sub-options.
			if ( 'external' === $key ) {
				$found_external = true;
				if ( $auction_type ) {
					// Use arrow prefix to show as sub-option under external.
					// The product type value remains 'aucteeno-ext-auction' but it's displayed as a sub-option.
					$ordered_types['aucteeno-ext-auction'] = $auction_type;
				}
				if ( $item_type ) {
					// Use arrow prefix to show as sub-option under external.
					// The product type value remains the constant value but it's displayed as a sub-option.
					$ordered_types[ Product_Item::PRODUCT_TYPE ] = $item_type;
				}
			}
		}

		// If external wasn't found, append auction and item at the end.
		if ( ! $found_external ) {
			if ( $auction_type ) {
				$ordered_types['aucteeno-ext-auction'] = $auction_type;
			}
			if ( $item_type ) {
				$ordered_types[ Product_Item::PRODUCT_TYPE ] = $item_type;
			}
		}

		return $ordered_types;
	}

	/**
	 * Filter products by product type in admin listing.
	 *
	 * @param array<string, mixed> $vars Query vars.
	 * @return array<string, mixed> Modified query vars.
	 */
	public function filter_products_by_type( array $vars ): array {
		global $typenow;

		if ( 'product' !== $typenow ) {
			return $vars;
		}

		if ( ! isset( $_GET['product_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $vars;
		}

		$product_type = sanitize_text_field( wp_unslash( $_GET['product_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $product_type ) ) {
			return $vars;
		}

		// Handle external type - show external, auction, and item products.
		if ( 'external' === $product_type ) {
			// WooCommerce uses product_type taxonomy, so we need to query for multiple terms.
			$vars['tax_query'] = isset( $vars['tax_query'] ) ? $vars['tax_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query

			$vars['tax_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => array( 'external', 'aucteeno-ext-auction', Product_Item::PRODUCT_TYPE ),
				'operator' => 'IN',
			);
		} elseif ( in_array( $product_type, array( 'aucteeno-ext-auction', Product_Item::PRODUCT_TYPE ), true ) ) {
			// Handle auction and item types specifically using taxonomy.
			$vars['tax_query'] = isset( $vars['tax_query'] ) ? $vars['tax_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query

			$vars['tax_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => $product_type,
			);
		} else {
			// For other product types, WooCommerce handles it via taxonomy automatically.
			// We don't need to do anything special here.
		}

		return $vars;
	}
}

