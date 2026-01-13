<?php
/**
 * Item Product Type Register Class
 *
 * Registers the item product type for WooCommerce.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Product_Types;

use TheAnother\Plugin\Aucteeno\Hook_Manager;

/**
 * Class Product_Type_Register_Item
 *
 * Registers item product type.
 */
class Product_Type_Register_Item {

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
		// Register add to cart template hook for item products.
		// Since item products extend WC_Product_External, we use the external template.
		$this->hook_manager->register_action(
			'woocommerce_' . Product_Item::PRODUCT_TYPE . '_add_to_cart',
			array( $this, 'output_add_to_cart_template' )
		);
	}

	/**
	 * Register product type with WooCommerce.
	 *
	 * @param array<string, string> $types Existing product types.
	 * @return array<string, string> Modified product types.
	 */
	public function register_product_type( array $types ): array {
		$types[ Product_Item::PRODUCT_TYPE ] = __( 'Aucteeno Item', 'aucteeno' );
		return $types;
	}

	/**
	 * Force type resolution during the save request (fixes first-save fallback to simple).
	 *
	 * @param string $override
	 * @param int    $product_id
	 * @return string
	 */
	public function register_product_type_query( string $override, int $product_id ): string {
		if (
			is_admin()
			&& isset( $_POST['product-type'] )
			&& Product_Item::PRODUCT_TYPE === sanitize_text_field( wp_unslash( $_POST['product-type'] ) )
		) {
			return Product_Item::PRODUCT_TYPE;
		}
		return $override;
	}

	/**
	 * Add product type to selector.
	 *
	 * @param array<string, string> $types Existing product types.
	 * @return array<string, string> Modified product types.
	 */
	public function add_product_type( array $types ): array {
		$types[ Product_Item::PRODUCT_TYPE ] = '→ ' . __( 'Aucteeno Item', 'aucteeno' );
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
		if ( ! $product || $product->get_type() !== Product_Item::PRODUCT_TYPE ) {
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

	/**
	 * Output the add to cart template for item products.
	 * Since item products extend WC_Product_External, we use the external template.
	 *
	 * @return void
	 */
	public function output_add_to_cart_template(): void {
		global $product;

		if ( ! $product instanceof Product_Item ) {
			return;
		}

		// Get product URL and button text for the template.
		$product_url = $product->get_product_url();
		$button_text = $product->get_button_text();

		// Use default button text if empty.
		if ( empty( $button_text ) ) {
			$button_text = __( 'Bid now', 'aucteeno' );
		}

		// Load the custom item product template that opens in a new tab.
		// Use plugin's template directory if template exists, otherwise fall back to external template.
		$template_path = 'single-product/add-to-cart/item.php';
		$template_file = AUCTEENO_PLUGIN_DIR . 'templates/' . $template_path;
		
		if ( file_exists( $template_file ) ) {
			// Load custom template from plugin directory.
			wc_get_template(
				$template_path,
				array(
					'product_url' => $product_url,
					'button_text' => $button_text,
				),
				'',
				AUCTEENO_PLUGIN_DIR . 'templates/'
			);
		} else {
			// Fallback to external template (shouldn't happen, but safe fallback).
			wc_get_template(
				'single-product/add-to-cart/external.php',
				array(
					'product_url' => $product_url,
					'button_text' => $button_text,
				)
			);
		}
	}
}
