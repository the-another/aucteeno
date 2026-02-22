<?php
/**
 * Auction Product Type Register Class
 *
 * Registers the auction product type for WooCommerce.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace The_Another\Plugin\Aucteeno\Product_Types;

use The_Another\Plugin\Aucteeno\Hook_Manager;

/**
 * Class Product_Type_Register_Auction
 *
 * Registers auction product type.
 */
class Product_Type_Register_Auction {

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
	 * Register the product type.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->hook_manager->register_filter(
			'woocommerce_product_types',
			array( $this, 'register_product_type' ),
			0
		);
		$this->hook_manager->register_filter(
			'woocommerce_product_type_query',
			array( $this, 'register_product_type_query' ),
			0,
			2
		);
		$this->hook_manager->register_filter(
			'product_type_selector',
			array( $this, 'add_product_type' )
		);
		$this->hook_manager->register_action(
			'woocommerce_product_options_general_product_data',
			array( $this, 'add_product_options' )
		);
		$this->hook_manager->register_filter(
			'woocommerce_product_data_tabs',
			array( $this, 'add_product_tabs' )
		);
	}

	/**
	 * Register product type with WooCommerce.
	 *
	 * @param array<string, string> $types Existing product types.
	 * @return array<string, string> Modified product types.
	 */
	public function register_product_type( array $types ): array {
		$types[ Product_Auction::PRODUCT_TYPE ] = __( 'Aucteeno Auction', 'aucteeno' );
		return $types;
	}

	/**
	 * Force type resolution during the save request (fixes first-save fallback to simple).
	 *
	 * @param string $override Current product type override value.
	 * @param int    $product_id Product ID being saved.
	 * @return string
	 */
	public function register_product_type_query( string $override, int $product_id ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by hook signature.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce before this hook fires.
		if (
			is_admin()
			&& isset( $_POST['product-type'] )
			&& Product_Auction::PRODUCT_TYPE === sanitize_text_field( wp_unslash( $_POST['product-type'] ) )
		) {
			return Product_Auction::PRODUCT_TYPE;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return $override;
	}

	/**
	 * Add product type to selector.
	 *
	 * @param array<string, string> $types Existing product types.
	 * @return array<string, string> Modified product types.
	 */
	public function add_product_type( array $types ): array {
		$types[ Product_Auction::PRODUCT_TYPE ] = '→ ' . __( 'Aucteeno Auction', 'aucteeno' );
		return $types;
	}

	/**
	 * Add product options.
	 *
	 * @return void
	 */
	public function add_product_options(): void {
		global $post;

		$product = wc_get_product( $post->ID );
		if ( ! $product || $product->get_type() !== Product_Auction::PRODUCT_TYPE ) {
			return;
		}

		// Product options will be added here by meta fields classes.
	}

	/**
	 * Add product tabs.
	 *
	 * @param array<string, mixed> $tabs Existing tabs.
	 * @return array<string, mixed> Modified tabs.
	 */
	public function add_product_tabs( array $tabs ): array {
		// Add custom tabs if needed.
		return $tabs;
	}
}
