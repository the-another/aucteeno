<?php
/**
 * Breadcrumbs Class
 *
 * Handles custom breadcrumbs for auction and item products.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace The_Another\Plugin\Aucteeno;

use The_Another\Plugin\Aucteeno\Product_Types\Product_Auction;
use The_Another\Plugin\Aucteeno\Product_Types\Product_Item;

/**
 * Class Breadcrumbs
 *
 * Customizes WooCommerce breadcrumbs for auction and item products.
 */
class Breadcrumbs {

	/**
	 * Hook manager instance.
	 *
	 * @var Hook_Manager
	 */
	private Hook_Manager $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param Hook_Manager $hook_manager Hook manager instance.
	 */
	public function __construct( Hook_Manager $hook_manager ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Initialize breadcrumb customization.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->hook_manager->register_filter(
			'woocommerce_get_breadcrumb',
			array( $this, 'customize_breadcrumbs' ),
			20,
			2
		);
	}

	/**
	 * Customize breadcrumbs for auction and item products.
	 *
	 * @param array<int, array<int, string>> $crumbs    Existing breadcrumb array.
	 * @param object                         $breadcrumb Breadcrumb object (unused).
	 * @return array<int, array<int, string>> Modified breadcrumb array.
	 */
	public function customize_breadcrumbs( array $crumbs, $breadcrumb ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by hook signature.
		// Only modify breadcrumbs on single product pages.
		if ( ! is_product() ) {
			return $crumbs;
		}

		global $post;

		if ( ! $post || 'product' !== $post->post_type ) {
			return $crumbs;
		}

		$product = wc_get_product( $post->ID );

		if ( ! $product ) {
			return $crumbs;
		}

		$product_type = $product->get_type();

		// Handle auction products.
		if ( Product_Auction::PRODUCT_TYPE === $product_type && $product instanceof Product_Auction ) {
			return $this->get_auction_breadcrumbs( $product );
		}

		// Handle item products.
		if ( Product_Item::PRODUCT_TYPE === $product_type && $product instanceof Product_Item ) {
			return $this->get_item_breadcrumbs( $product );
		}

		return $crumbs;
	}

	/**
	 * Get breadcrumbs for auction products.
	 * Format: Home / {auction.title}
	 *
	 * @param Product_Auction $product Auction product object.
	 * @return array<int, array<int, string>> Breadcrumb array.
	 */
	private function get_auction_breadcrumbs( Product_Auction $product ): array {
		$crumbs = array();

		// Home link.
		$crumbs[] = array(
			__( 'Home', 'aucteeno' ),
			home_url( '/' ),
		);

		// Auction title.
		$crumbs[] = array(
			$product->get_name(),
			get_permalink( $product->get_id() ),
		);

		return $crumbs;
	}

	/**
	 * Get breadcrumbs for item products.
	 * Format: Home / {auction.title} / {item.title}
	 *
	 * @param Product_Item $product Item product object.
	 * @return array<int, array<int, string>> Breadcrumb array.
	 */
	private function get_item_breadcrumbs( Product_Item $product ): array {
		$crumbs = array();

		// Home link.
		$crumbs[] = array(
			__( 'Home', 'aucteeno' ),
			home_url( '/' ),
		);

		// Get parent auction.
		$auction_id = $product->get_auction_id();

		if ( $auction_id > 0 ) {
			$auction = wc_get_product( $auction_id );

			if ( $auction && $auction instanceof Product_Auction ) {
				// Auction title.
				$crumbs[] = array(
					$auction->get_name(),
					get_permalink( $auction->get_id() ),
				);
			}
		}

		// Item title.
		$crumbs[] = array(
			$product->get_name(),
			get_permalink( $product->get_id() ),
		);

		return $crumbs;
	}
}
