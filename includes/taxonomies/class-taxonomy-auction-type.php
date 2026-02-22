<?php
/**
 * Auction Type Taxonomy Class
 *
 * Registers the auction-type taxonomy for products.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace The_Another\Plugin\Aucteeno\Taxonomies;

/**
 * Class Taxonomy_Auction_Type
 *
 * Registers auction-type taxonomy with terms: live, online, timed.
 */
class Taxonomy_Auction_Type {

	/**
	 * Taxonomy slug.
	 *
	 * @var string
	 */
	private const TAXONOMY = 'auction-type';

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = array(
			'name'              => _x( 'Auction Types', 'taxonomy general name', 'aucteeno' ),
			'singular_name'     => _x( 'Auction Type', 'taxonomy singular name', 'aucteeno' ),
			'search_items'      => __( 'Search Auction Types', 'aucteeno' ),
			'all_items'         => __( 'All Auction Types', 'aucteeno' ),
			'parent_item'       => __( 'Parent Auction Type', 'aucteeno' ),
			'parent_item_colon' => __( 'Parent Auction Type:', 'aucteeno' ),
			'edit_item'         => __( 'Edit Auction Type', 'aucteeno' ),
			'update_item'       => __( 'Update Auction Type', 'aucteeno' ),
			'add_new_item'      => __( 'Add New Auction Type', 'aucteeno' ),
			'new_item_name'     => __( 'New Auction Type Name', 'aucteeno' ),
			'menu_name'         => __( 'Auction Types', 'aucteeno' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => false,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'auction-type' ),
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
		$terms = array( 'live', 'online', 'timed' );

		foreach ( $terms as $term ) {
			if ( ! term_exists( $term, self::TAXONOMY ) ) {
				wp_insert_term(
					ucfirst( $term ), // Name: capitalized version.
					self::TAXONOMY,
					array(
						'slug' => $term, // Slug: lowercase version.
					)
				);
			}
		}
	}
}
