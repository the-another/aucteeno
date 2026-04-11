<?php
/**
 * Query Loop Location Filter helper.
 *
 * Wraps the apply_filters() call and return-value sanitization for the
 * aucteeno_query_loop_location filter. Extracted from render.php so the
 * logic can be unit-tested in isolation.
 *
 * @package Aucteeno
 * @since 1.2.3
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno\Blocks;

/**
 * Static helper that emits the aucteeno_query_loop_location filter.
 */
final class Query_Loop_Location_Filter {

	/**
	 * Run the aucteeno_query_loop_location filter and return a sanitized pair.
	 *
	 * @param array $location   Two-element indexed array [ string $country, string $subdivision ].
	 *                          $country is a 2-letter ISO code or empty string.
	 *                          $subdivision is "COUNTRY:REGION" or empty string.
	 * @param array $attributes The block's resolved attributes array.
	 * @param mixed $block      The block instance (WP_Block in production).
	 * @return array Two-element indexed array in the same shape.
	 */
	public static function apply( array $location, array $attributes, $block ): array {
		/**
		 * Filters the resolved location for the Aucteeno Query Loop block before querying.
		 *
		 * Fires after the base precedence chain (attribute → context → taxonomy archive)
		 * resolves $location_country and $location_subdivision, and before they are
		 * written into $query_args. Does not fire when the block is in product-IDs mode —
		 * callers wrap this call in a ! $has_product_ids guard.
		 *
		 * @since 1.2.3
		 *
		 * @param array $location   Two-element indexed array [ string $country, string $subdivision ].
		 * @param array $attributes The block's resolved attributes array.
		 * @param mixed $block      The block instance.
		 */
		$filtered = apply_filters(
			'aucteeno_query_loop_location',
			$location,
			$attributes,
			$block
		);

		if ( ! is_array( $filtered ) || 2 !== count( $filtered ) ) {
			return $location;
		}

		$country     = $location[0];
		$subdivision = $location[1];

		if ( isset( $filtered[0] ) && is_string( $filtered[0] ) ) {
			$country = sanitize_text_field( $filtered[0] );
		}
		if ( isset( $filtered[1] ) && is_string( $filtered[1] ) ) {
			$subdivision = sanitize_text_field( $filtered[1] );
		}

		return array( $country, $subdivision );
	}
}
