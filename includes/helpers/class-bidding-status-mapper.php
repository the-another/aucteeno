<?php
/**
 * Bidding Status Mapper Class
 *
 * Maps auction-bidding-status taxonomy terms to tinyint values using order_sequence term meta.
 *
 * @package Aucteeno
 * @since 2.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Helpers;

/**
 * Class Bidding_Status_Mapper
 *
 * Maps taxonomy term slugs/IDs to tinyint values by reading order_sequence meta.
 */
class Bidding_Status_Mapper {

	/**
	 * Taxonomy slug.
	 *
	 * @var string
	 */
	private const TAXONOMY = 'auction-bidding-status';

	/**
	 * Default bidding status value if term or meta not found.
	 *
	 * @var int
	 */
	private const DEFAULT_STATUS = 10;

	/**
	 * Convert term slug or ID to number by reading order_sequence meta.
	 *
	 * @param string|int $term_slug_or_id Term slug or term ID.
	 * @return int Bidding status number (tinyint).
	 */
	public static function term_to_number( string|int $term_slug_or_id ): int {
		$term = null;

		if ( is_int( $term_slug_or_id ) ) {
			$term = get_term( $term_slug_or_id, self::TAXONOMY );
		} else {
			$term = get_term_by( 'slug', $term_slug_or_id, self::TAXONOMY );
		}

		if ( ! $term || is_wp_error( $term ) ) {
			return self::DEFAULT_STATUS;
		}

		$order_sequence = get_term_meta( $term->term_id, 'order_sequence', true );

		if ( '' === $order_sequence || false === $order_sequence ) {
			return self::DEFAULT_STATUS;
		}

		return (int) $order_sequence;
	}

	/**
	 * Convert number back to term slug by finding term with matching order_sequence.
	 *
	 * @param int $number Bidding status number (tinyint).
	 * @return string Term slug, or empty string if not found.
	 */
	public static function number_to_term( int $number ): string {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'meta_query' => array(
					array(
						'key'   => 'order_sequence',
						'value' => $number,
					),
				),
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		return $terms[0]->slug;
	}

	/**
	 * Get bidding status number from product post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return int Bidding status number (tinyint).
	 */
	public static function get_status_from_post( int $post_id ): int {
		$terms = wp_get_post_terms( $post_id, self::TAXONOMY, array( 'fields' => 'slugs' ) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return self::DEFAULT_STATUS;
		}

		return self::term_to_number( $terms[0] );
	}
}

