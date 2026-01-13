<?php
/**
 * Location Taxonomy Class
 *
 * Registers the hierarchical location taxonomy (country/state) for products.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Taxonomies;

/**
 * Class Taxonomy_Location
 *
 * Registers hierarchical location taxonomy for auction/item location.
 * Countries are top-level terms, states/subdivisions are child terms.
 */
class Taxonomy_Location {

	/**
	 * Taxonomy slug.
	 *
	 * @var string
	 */
	private const TAXONOMY = 'aucteeno-location';

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = array(
			'name'              => _x( 'Locations', 'taxonomy general name', 'aucteeno' ),
			'singular_name'     => _x( 'Location', 'taxonomy singular name', 'aucteeno' ),
			'search_items'      => __( 'Search Locations', 'aucteeno' ),
			'all_items'         => __( 'All Locations', 'aucteeno' ),
			'parent_item'       => __( 'Parent Location', 'aucteeno' ),
			'parent_item_colon' => __( 'Parent Location:', 'aucteeno' ),
			'edit_item'         => __( 'Edit Location', 'aucteeno' ),
			'update_item'       => __( 'Update Location', 'aucteeno' ),
			'add_new_item'      => __( 'Add New Location', 'aucteeno' ),
			'new_item_name'     => __( 'New Location Name', 'aucteeno' ),
			'menu_name'         => __( 'Locations', 'aucteeno' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => false,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'aucteeno-location' ),
			'show_in_rest'      => true,
		);

		register_taxonomy( self::TAXONOMY, array( 'product' ), $args );
	}
}
