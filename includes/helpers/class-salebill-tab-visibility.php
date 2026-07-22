<?php
/**
 * Salebill Tab Visibility Helper Class
 *
 * Visibility rules for the single-auction salebill accordion panels
 * (Description / Directions / Notes).
 *
 * @package Aucteeno
 * @since TBD
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

		// Compare before stripping any "more" suffix: content that genuinely
		// ends in an ellipsis (e.g. "...") must compare equal to an excerpt
		// ending the same way, without the suffix stripping below eating a
		// character that belongs to the real content.
		if ( $excerpt_norm === $content_norm ) {
			return true;
		}

		/** This filter is documented in wp-includes/formatting.php */
		$more_norm = self::normalize_text( (string) apply_filters( 'excerpt_more', ' [&hellip;]' ) );

		// normalize_text() folds the typographic ellipsis (…) to "...", so a
		// "[…]" more-string now normalizes identically to the defensive
		// '[...]' entry below — a separate '…' entry would never match. A
		// literal '&hellip;' entry is likewise dead: entities are decoded
		// (and then folded) well before this point, so that substring can
		// never survive into $excerpt_norm. The bare '...' entry is a
		// defensive fallback for an auto-trimmed excerpt using a bare
		// ellipsis more-string; since we only reach this branch when the
		// excerpt and content already differ, stripping it can only turn a
		// "not covered" result into "covered" (fail open — show the panel),
		// never hide content the excerpt actually omits.
		$suffixes = array( '[...]', '...' );
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

		// WordPress texturizes get_the_excerpt() output (curly quotes,
		// en/em dashes, ellipsis, non-breaking spaces) but post_content is
		// compared raw — fold both sides to the same plain-ASCII-ish form
		// so texturization alone never causes a mismatch.
		$text = strtr(
			$text,
			array(
				'’'        => "'",
				'‘'        => "'",
				'“'        => '"',
				'”'        => '"',
				'–'        => '-',
				'—'        => '-',
				'…'        => '...',
				"\u{00A0}" => ' ',
			)
		);

		$text = (string) preg_replace( '/\s+/u', ' ', $text );

		return trim( $text );
	}
}
