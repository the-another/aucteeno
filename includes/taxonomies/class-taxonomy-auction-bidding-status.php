<?php
/**
 * Auction Bidding Status Taxonomy Class
 *
 * Registers the auction-bidding-status taxonomy for products.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Taxonomies;

/**
 * Class Taxonomy_Auction_Bidding_Status
 *
 * Registers auction-bidding-status taxonomy with terms: running, upcoming, expired.
 */
class Taxonomy_Auction_Bidding_Status {

	/**
	 * Taxonomy slug.
	 *
	 * @var string
	 */
	private const TAXONOMY = 'auction-bidding-status';

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = array(
			'name'              => _x( 'Bidding Statuses', 'taxonomy general name', 'aucteeno' ),
			'singular_name'     => _x( 'Bidding Status', 'taxonomy singular name', 'aucteeno' ),
			'search_items'      => __( 'Search Bidding Statuses', 'aucteeno' ),
			'all_items'         => __( 'All Bidding Statuses', 'aucteeno' ),
			'parent_item'       => __( 'Parent Bidding Status', 'aucteeno' ),
			'parent_item_colon' => __( 'Parent Bidding Status:', 'aucteeno' ),
			'edit_item'         => __( 'Edit Bidding Status', 'aucteeno' ),
			'update_item'       => __( 'Update Bidding Status', 'aucteeno' ),
			'add_new_item'      => __( 'Add New Bidding Status', 'aucteeno' ),
			'new_item_name'     => __( 'New Bidding Status Name', 'aucteeno' ),
			'menu_name'         => __( 'Bidding Statuses', 'aucteeno' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => false,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'auction-bidding-status' ),
			'show_in_rest'      => true,
		);

		register_taxonomy( self::TAXONOMY, array( 'product' ), $args );

		// Register default terms.
		self::register_default_terms();
	}

	/**
	 * Register default terms.
	 *
	 * @return void
	 */
	private static function register_default_terms(): void {
		$terms = array(
			'running'  => 10,
			'upcoming' => 20,
			'expired'  => 30,
		);

		foreach ( $terms as $term_slug => $order_sequence ) {
			if ( ! term_exists( $term_slug, self::TAXONOMY ) ) {
				$term_result = wp_insert_term(
					ucfirst( $term_slug ), // Name: capitalized version.
					self::TAXONOMY,
					array(
						'slug' => $term_slug, // Slug: lowercase version.
					)
				);

				// Add order_sequence meta field if term was successfully created.
				if ( ! is_wp_error( $term_result ) && isset( $term_result['term_id'] ) ) {
					add_term_meta( $term_result['term_id'], 'order_sequence', $order_sequence, true );
				}
			} else {
				// Term exists, but ensure meta field is set (in case it was deleted).
				$term = get_term_by( 'slug', $term_slug, self::TAXONOMY );
				if ( $term && ! is_wp_error( $term ) ) {
					$existing_meta = get_term_meta( $term->term_id, 'order_sequence', true );
					if ( '' === $existing_meta ) {
						add_term_meta( $term->term_id, 'order_sequence', $order_sequence, true );
					}
				}
			}
		}
	}
}
