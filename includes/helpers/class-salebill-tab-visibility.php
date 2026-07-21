<?php
/**
 * Salebill Tab Visibility Helper Class
 *
 * Visibility rules for the single-auction salebill accordion panels
 * (Description / Directions / Notes).
 *
 * @package Aucteeno
 * @since 1.6.0
 */

namespace The_Another\Plugin\Aucteeno\Helpers;

/**
 * Class Salebill_Tab_Visibility
 *
 * Stateless helpers deciding which salebill panels have content to show.
 */
class Salebill_Tab_Visibility {

	/**
	 * Check whether the Directions panel has content.
	 *
	 * @param object $product Auction product (Product_Auction).
	 * @return bool
	 */
	public static function is_directions_visible( $product ): bool {
		return '' !== trim( (string) $product->get_directions() );
	}

	/**
	 * Check whether the Notes panel has content.
	 *
	 * @param object $product Auction product (Product_Auction).
	 * @return bool
	 */
	public static function is_notes_visible( $product ): bool {
		return '' !== trim( (string) $product->get_notice() )
			|| '' !== trim( (string) $product->get_bidding_notice() );
	}

	/**
	 * Check whether the Description panel is visible.
	 *
	 * Visible when the post has content AND (another panel is visible OR the
	 * page-top excerpt does not already show the entire description).
	 *
	 * @param object          $product Auction product (Product_Auction).
	 * @param \WP_Post|object $post    Auction post.
	 * @return bool
	 */
	public static function is_description_visible( $product, $post ): bool {
		if ( '' === trim( (string) $post->post_content ) ) {
			return false;
		}

		if ( self::is_directions_visible( $product ) || self::is_notes_visible( $product ) ) {
			return true;
		}

		return ! self::excerpt_covers_content( $post );
	}

	/**
	 * Check whether the excerpt already shows the entire content.
	 *
	 * Uses the same excerpt machinery as the theme's core/post-excerpt block
	 * and compares both sides as normalized plain text — any difference means
	 * the Description panel adds information.
	 *
	 * @param \WP_Post|object $post Auction post.
	 * @return bool
	 */
	public static function excerpt_covers_content( $post ): bool {
		$content = (string) $post->post_content;

		// A text excerpt cannot represent embedded media.
		if ( preg_match( '/<(img|iframe|video|audio)\b/i', $content ) ) {
			return false;
		}

		$excerpt_norm = self::normalize_text( (string) get_the_excerpt( $post ) );
		$content_norm = self::normalize_text( $content );

		/** This filter is documented in wp-includes/formatting.php */
		$more_norm = self::normalize_text( (string) apply_filters( 'excerpt_more', ' [&hellip;]' ) );

		$suffixes = array( '[...]', '&hellip;', '…' );
		if ( '' !== $more_norm ) {
			array_unshift( $suffixes, $more_norm );
		}
		foreach ( $suffixes as $suffix ) {
			if ( '' !== $suffix && str_ends_with( $excerpt_norm, $suffix ) ) {
				$excerpt_norm = trim( substr( $excerpt_norm, 0, -strlen( $suffix ) ) );
			}
		}

		return $excerpt_norm === $content_norm;
	}

	/**
	 * Normalize HTML to comparable plain text.
	 *
	 * @param string $text Raw HTML/text.
	 * @return string
	 */
	private static function normalize_text( string $text ): string {
		$text = wp_strip_all_tags( $text, true );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = (string) preg_replace( '/\s+/u', ' ', $text );

		return trim( $text );
	}
}
