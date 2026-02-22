<?php
/**
 * Subdivision Taxonomy Class
 *
 * Registers the subdivision taxonomy (state/province) for products.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace The_Another\Plugin\Aucteeno\Taxonomies;

/**
 * Class Taxonomy_Subdivision
 *
 * Registers subdivision taxonomy for auction location (state/province).
 */
class Taxonomy_Subdivision {

	/**
	 * Taxonomy slug.
	 *
	 * @var string
	 */
	private const TAXONOMY = 'subdivision';

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = array(
			'name'              => _x( 'Subdivisions', 'taxonomy general name', 'aucteeno' ),
			'singular_name'     => _x( 'Subdivision', 'taxonomy singular name', 'aucteeno' ),
			'search_items'      => __( 'Search Subdivisions', 'aucteeno' ),
			'all_items'         => __( 'All Subdivisions', 'aucteeno' ),
			'parent_item'       => __( 'Parent Subdivision', 'aucteeno' ),
			'parent_item_colon' => __( 'Parent Subdivision:', 'aucteeno' ),
			'edit_item'         => __( 'Edit Subdivision', 'aucteeno' ),
			'update_item'       => __( 'Update Subdivision', 'aucteeno' ),
			'add_new_item'      => __( 'Add New Subdivision', 'aucteeno' ),
			'new_item_name'     => __( 'New Subdivision Name', 'aucteeno' ),
			'menu_name'         => __( 'Subdivisions', 'aucteeno' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => false,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'subdivision' ),
			'show_in_rest'      => true,
		);

		register_taxonomy( self::TAXONOMY, array( 'product' ), $args );
	}
}
