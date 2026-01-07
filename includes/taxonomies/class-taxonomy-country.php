<?php
/**
 * Country Taxonomy Class
 *
 * Registers the country taxonomy for products.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Taxonomies;

/**
 * Class Taxonomy_Country
 *
 * Registers country taxonomy for auction location.
 */
class Taxonomy_Country {

	/**
	 * Taxonomy slug.
	 *
	 * @var string
	 */
	private const TAXONOMY = 'country';

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = array(
			'name'              => _x( 'Countries', 'taxonomy general name', 'aucteeno' ),
			'singular_name'     => _x( 'Country', 'taxonomy singular name', 'aucteeno' ),
			'search_items'      => __( 'Search Countries', 'aucteeno' ),
			'all_items'         => __( 'All Countries', 'aucteeno' ),
			'parent_item'       => __( 'Parent Country', 'aucteeno' ),
			'parent_item_colon' => __( 'Parent Country:', 'aucteeno' ),
			'edit_item'         => __( 'Edit Country', 'aucteeno' ),
			'update_item'       => __( 'Update Country', 'aucteeno' ),
			'add_new_item'      => __( 'Add New Country', 'aucteeno' ),
			'new_item_name'     => __( 'New Country Name', 'aucteeno' ),
			'menu_name'         => __( 'Countries', 'aucteeno' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => false,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'country' ),
			'show_in_rest'      => true,
		);

		register_taxonomy( self::TAXONOMY, array( 'product' ), $args );
	}
}
