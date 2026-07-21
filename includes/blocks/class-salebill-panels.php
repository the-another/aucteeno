<?php
/**
 * Salebill Panels renderer.
 *
 * Generates the HTML for the salebill accordion panel blocks
 * (Description / Directions / Notes). An empty return value makes
 * WooCommerce's Product Details block remove the whole accordion item.
 *
 * @package Aucteeno
 * @since 1.6.0
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno\Blocks;

use The_Another\Plugin\Aucteeno\Helpers\Salebill_Tab_Visibility;
use The_Another\Plugin\Aucteeno\Product_Types\Product_Auction;

/**
 * Class Salebill_Panels
 *
 * Static render methods consumed by the blocks/salebill-* render.php shims.
 */
final class Salebill_Panels {

	/**
	 * Resolve the current auction product and post, or null when not on one.
	 *
	 * @return array|null Array with 'product' and 'post' keys, or null.
	 */
	public static function resolve_auction_context(): ?array {
		$post = get_post();
		if ( ! $post ) {
			return null;
		}

		$product = wc_get_product( $post->ID );
		if ( ! $product || Product_Auction::PRODUCT_TYPE !== $product->get_type() ) {
			return null;
		}

		return array(
			'product' => $product,
			'post'    => $post,
		);
	}

	/**
	 * Render the Description panel content.
	 *
	 * @return string HTML, empty when the panel must be hidden.
	 */
	public static function render_description(): string {
		$context = self::resolve_auction_context();
		if ( null === $context ) {
			return '';
		}

		if ( ! Salebill_Tab_Visibility::is_description_visible( $context['product'], $context['post'] ) ) {
			return '';
		}

		/** This filter is documented in wp-includes/post-template.php */
		return (string) apply_filters( 'the_content', $context['post']->post_content );
	}

	/**
	 * Render the Directions panel content.
	 *
	 * @return string HTML, empty when the panel must be hidden.
	 */
	public static function render_directions(): string {
		$context = self::resolve_auction_context();
		if ( null === $context || ! Salebill_Tab_Visibility::is_directions_visible( $context['product'] ) ) {
			return '';
		}

		return wpautop( esc_html( trim( (string) $context['product']->get_directions() ) ) );
	}

	/**
	 * Render the Notes panel content.
	 *
	 * @return string HTML, empty when the panel must be hidden.
	 */
	public static function render_notes(): string {
		$context = self::resolve_auction_context();
		if ( null === $context || ! Salebill_Tab_Visibility::is_notes_visible( $context['product'] ) ) {
			return '';
		}

		$notice         = trim( (string) $context['product']->get_notice() );
		$bidding_notice = trim( (string) $context['product']->get_bidding_notice() );
		$show_headings  = '' !== $notice && '' !== $bidding_notice;

		$html = '';
		if ( '' !== $notice ) {
			if ( $show_headings ) {
				$html .= '<h4>' . esc_html__( 'Auction Notice', 'aucteeno' ) . '</h4>';
			}
			$html .= wpautop( esc_html( $notice ) );
		}
		if ( '' !== $bidding_notice ) {
			if ( $show_headings ) {
				$html .= '<h4>' . esc_html__( 'Bidding Notice', 'aucteeno' ) . '</h4>';
			}
			$html .= wpautop( esc_html( $bidding_notice ) );
		}

		return $html;
	}
}
