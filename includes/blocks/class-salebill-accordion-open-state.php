<?php
/**
 * Salebill Accordion Open State service.
 *
 * Ensures the first visible salebill accordion item is open on auction
 * pages, and keeps third-party woocommerce_product_tabs entries out of
 * the Product Details accordion.
 *
 * @package Aucteeno
 * @since TBD
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno\Blocks;

use The_Another\Plugin\Aucteeno\Hook_Manager;
use The_Another\Plugin\Aucteeno\Product_Types\Product_Auction;

/**
 * Class Salebill_Accordion_Open_State
 */
final class Salebill_Accordion_Open_State {

	/**
	 * Hook manager instance.
	 *
	 * @var Hook_Manager
	 */
	private Hook_Manager $hook_manager;

	/**
	 * Memoized is_auction_page() result for the current post, or null when
	 * not yet computed for $is_auction_page_post_id.
	 *
	 * @var bool|null
	 */
	private ?bool $is_auction_page = null;

	/**
	 * Post ID the memoized is_auction_page() result was computed for.
	 *
	 * @var int|null
	 */
	private ?int $is_auction_page_post_id = null;

	/**
	 * Constructor.
	 *
	 * @param Hook_Manager $hook_manager Hook manager instance.
	 */
	public function __construct( Hook_Manager $hook_manager ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Register hooks.
	 */
	public function init(): void {
		$this->hook_manager->register_filter(
			'render_block_woocommerce/accordion-group',
			array( $this, 'maybe_open_first_item' ),
			10,
			2
		);
		$this->hook_manager->register_filter(
			'woocommerce_disable_compatibility_layer',
			array( $this, 'disable_compatibility_layer_for_auctions' )
		);
	}

	/**
	 * Open the first accordion item when none is open.
	 *
	 * WooCommerce's AccordionItem block drives its open state from the
	 * openByDefault attribute serialized into each item's data-wp-context.
	 * When the authored Description item is hidden (empty content), no item
	 * carries the attribute — flip it on the first remaining item. This
	 * filter runs before WP processes interactivity directives, which then
	 * derive the is-open class and ARIA state from the flipped context.
	 *
	 * Scoped to the Product Details accordion via the
	 * isDescendantOfProductDetails block metadata — an accordion-group a
	 * seller embeds in their own post_content must be left untouched.
	 *
	 * @param string $block_content Rendered accordion-group HTML.
	 * @param array  $parsed_block  Parsed block, including attrs/metadata.
	 * @return string
	 */
	public function maybe_open_first_item( $block_content, $parsed_block = array() ) {
		if ( ! is_string( $block_content ) || '' === $block_content || ! $this->is_auction_page() ) {
			return $block_content;
		}

		if ( empty( $parsed_block['attrs']['metadata']['isDescendantOfProductDetails'] ) ) {
			return $block_content;
		}

		$processor   = new \WP_HTML_Tag_Processor( $block_content );
		$found_first = false;

		while ( $processor->next_tag( array( 'class_name' => 'wp-block-woocommerce-accordion-item' ) ) ) {
			if ( ! $found_first ) {
				$processor->set_bookmark( 'first-item' );
				$found_first = true;
			}

			$context = json_decode( (string) $processor->get_attribute( 'data-wp-context' ), true );
			if ( ! empty( $context['openByDefault'] ) ) {
				return $block_content; // An item is already open.
			}
		}

		if ( ! $found_first ) {
			return $block_content; // No items rendered.
		}

		$processor->seek( 'first-item' );
		$context = json_decode( (string) $processor->get_attribute( 'data-wp-context' ), true );
		if ( ! is_array( $context ) ) {
			return $block_content;
		}

		$context['openByDefault'] = true;
		$processor->set_attribute(
			'data-wp-context',
			wp_json_encode( $context, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP )
		);

		return $processor->get_updated_html();
	}

	/**
	 * Disable WC's product-tabs compatibility layer on auction pages.
	 *
	 * Without this, any third-party woocommerce_product_tabs entry (Dokan
	 * et al. — invisible on the previous layout) would be injected into the
	 * salebill accordion as an extra item.
	 *
	 * @param bool $disabled Current value.
	 * @return bool
	 */
	public function disable_compatibility_layer_for_auctions( $disabled ) {
		if ( $this->is_auction_page() ) {
			return true;
		}

		return (bool) $disabled;
	}

	/**
	 * Whether the current request renders a single auction product.
	 *
	 * Memoized per post ID — this is invoked for every accordion-group and
	 * every product-tabs render on the page, and re-resolving the product
	 * each time is wasted work once the answer is known.
	 *
	 * @return bool
	 */
	private function is_auction_page(): bool {
		// get_the_ID() returns int|false; coerce to int so the memoized post
		// ID (a nullable int) can always hold it without a type error.
		$post_id = (int) get_the_ID();

		if ( null !== $this->is_auction_page && $this->is_auction_page_post_id === $post_id ) {
			return $this->is_auction_page;
		}

		$this->is_auction_page_post_id = $post_id;

		if ( ! is_singular( 'product' ) ) {
			$this->is_auction_page = false;
			return $this->is_auction_page;
		}

		$product = wc_get_product( $post_id );

		$this->is_auction_page = $product && Product_Auction::PRODUCT_TYPE === $product->get_type();

		return $this->is_auction_page;
	}
}
