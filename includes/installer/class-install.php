<?php
/**
 * Install Class
 *
 * Handles plugin installation and activation tasks.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace The_Another\Plugin\Aucteeno\Installer;

use The_Another\Plugin\Aucteeno\Database\Database;
use The_Another\Plugin\Aucteeno\Permalinks\Auction_Item_Permalinks;
use The_Another\Plugin\Aucteeno\Product_Types\Product_Auction;
use The_Another\Plugin\Aucteeno\Product_Types\Product_Item;

/**
 * Class Install
 *
 * Handles plugin installation tasks.
 */
class Install {

	/**
	 * Run installation tasks.
	 *
	 * @since 1.0.0
	 */
	public static function run(): void {
		Database::create_tables();

		// Ensure product type taxonomy terms are created.
		self::ensure_product_type_terms();

		// Flush rewrite rules for custom permalinks.
		Auction_Item_Permalinks::activate();
	}

	/**
	 * Ensure product type taxonomy terms exist for custom product types.
	 *
	 * Creates the product type terms if they don't exist in the product_type taxonomy.
	 * This ensures WooCommerce can properly recognize and handle our custom product types.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function ensure_product_type_terms(): void {
		// Ensure WooCommerce is loaded and product_type taxonomy is registered.
		if ( ! taxonomy_exists( 'product_type' ) ) {
			return;
		}

		$product_types = array(
			Product_Auction::PRODUCT_TYPE => __( 'Aucteeno Auction', 'aucteeno' ),
			Product_Item::PRODUCT_TYPE    => __( 'Aucteeno Item', 'aucteeno' ),
		);

		foreach ( $product_types as $type_slug => $type_label ) {
			// Check if term already exists.
			$term = term_exists( $type_slug, 'product_type' );

			if ( ! $term ) {
				// Create the term if it doesn't exist.
				wp_insert_term(
					$type_slug,
					'product_type',
					array(
						'description' => $type_label,
					)
				);
			}
		}
	}
}
