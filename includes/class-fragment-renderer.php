<?php
/**
 * Fragment Renderer Class
 *
 * Renders HTML fragments for auctions and items listings.
 * Used by both REST API and SSR (block render.php).
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno;

use TheAnother\Plugin\Aucteeno\Helpers\DateTime_Helper;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Auction;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Item;
use WP_Query;

/**
 * Class Fragment_Renderer
 *
 * Renders HTML fragments for listings with pagination metadata.
 */
class Fragment_Renderer {

	/**
	 * Default per page value.
	 *
	 * @var int
	 */
	private const DEFAULT_PER_PAGE = 10;

	/**
	 * Maximum per page value.
	 *
	 * @var int
	 */
	private const MAX_PER_PAGE = 50;

	/**
	 * Render auctions fragment.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type array  $location  Location term slugs or IDs.
	 *     @type int    $page      Page number (default 1).
	 *     @type int    $per_page  Items per page (default 10, max 50).
	 *     @type string $sort      Sort order: 'ending_soon' or 'newest'.
	 * }
	 * @return array {
	 *     Fragment response.
	 *
	 *     @type string $html  HTML fragment containing auction cards.
	 *     @type int    $page  Current page number.
	 *     @type int    $pages Total number of pages.
	 *     @type int    $total Total number of auctions.
	 * }
	 */
	public static function auctions( array $args = array() ): array {
		$defaults = array(
			'location' => array(),
			'page'     => 1,
			'per_page' => self::DEFAULT_PER_PAGE,
			'sort'     => 'ending_soon',
		);

		$args = wp_parse_args( $args, $defaults );

		// Sanitize pagination.
		$page     = max( 1, (int) $args['page'] );
		$per_page = min( self::MAX_PER_PAGE, max( 1, (int) $args['per_page'] ) );
		$sort     = in_array( $args['sort'], array( 'ending_soon', 'newest' ), true ) ? $args['sort'] : 'ending_soon';

		// Build query args.
		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => Product_Auction::PRODUCT_TYPE,
				),
			),
		);

		// Add location filter.
		$locations = self::normalize_locations( $args['location'] );
		if ( ! empty( $locations ) ) {
			$query_args['tax_query']['relation'] = 'AND';
			$query_args['tax_query'][]           = array(
				'taxonomy' => 'aucteeno-location',
				'field'    => is_numeric( $locations[0] ) ? 'term_id' : 'slug',
				'terms'    => $locations,
			);
		}

		// Add sorting.
		// For 'ending_soon', Query_Orderer will handle custom ordering automatically.
		// For 'newest', use date-based ordering as fallback.
		if ( 'ending_soon' !== $sort ) {
			$query_args['orderby'] = array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			);
		}
		// Note: 'ending_soon' sort is handled by Query_Orderer via pre_get_posts hook.

		// Execute query.
		$query = new WP_Query( $query_args );

		// Build HTML.
		$html = '';
		if ( $query->have_posts() ) {
			ob_start();
			while ( $query->have_posts() ) {
				$query->the_post();
				$product = wc_get_product( get_the_ID() );

				if ( ! $product instanceof Product_Auction ) {
					continue;
				}

				$auction = self::build_auction_data( $product );
				include AUCTEENO_PLUGIN_DIR . 'templates/parts/auction-card.php';
			}
			wp_reset_postdata();
			$html = ob_get_clean();
		}

		return array(
			'html'  => $html,
			'page'  => $page,
			'pages' => (int) $query->max_num_pages,
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * Render items fragment.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type array  $location   Location term slugs or IDs.
	 *     @type int    $auction_id Parent auction ID (optional).
	 *     @type int    $page       Page number (default 1).
	 *     @type int    $per_page   Items per page (default 10, max 50).
	 *     @type string $sort       Sort order: 'ending_soon' or 'newest'.
	 * }
	 * @return array {
	 *     Fragment response.
	 *
	 *     @type string $html  HTML fragment containing item cards.
	 *     @type int    $page  Current page number.
	 *     @type int    $pages Total number of pages.
	 *     @type int    $total Total number of items.
	 * }
	 */
	public static function items( array $args = array() ): array {
		$defaults = array(
			'location'   => array(),
			'auction_id' => 0,
			'page'       => 1,
			'per_page'   => self::DEFAULT_PER_PAGE,
			'sort'       => 'ending_soon',
		);

		$args = wp_parse_args( $args, $defaults );

		// Sanitize pagination.
		$page       = max( 1, (int) $args['page'] );
		$per_page   = min( self::MAX_PER_PAGE, max( 1, (int) $args['per_page'] ) );
		$sort       = in_array( $args['sort'], array( 'ending_soon', 'newest' ), true ) ? $args['sort'] : 'ending_soon';
		$auction_id = absint( $args['auction_id'] );

		// Build query args.
		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => Product_Item::PRODUCT_TYPE,
				),
			),
		);

		// Add auction parent filter.
		if ( $auction_id > 0 ) {
			$query_args['post_parent'] = $auction_id;
		}

		// Add location filter.
		$locations = self::normalize_locations( $args['location'] );
		if ( ! empty( $locations ) ) {
			$query_args['tax_query']['relation'] = 'AND';
			$query_args['tax_query'][]           = array(
				'taxonomy' => 'aucteeno-location',
				'field'    => is_numeric( $locations[0] ) ? 'term_id' : 'slug',
				'terms'    => $locations,
			);
		}

		// Add sorting.
		// For 'ending_soon', Query_Orderer will handle custom ordering automatically.
		// For 'newest', use date-based ordering as fallback.
		if ( 'ending_soon' !== $sort ) {
			$query_args['orderby'] = array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			);
		}
		// Note: 'ending_soon' sort is handled by Query_Orderer via pre_get_posts hook.

		// Execute query.
		$query = new WP_Query( $query_args );

		// Build HTML.
		$html = '';
		if ( $query->have_posts() ) {
			ob_start();
			while ( $query->have_posts() ) {
				$query->the_post();
				$product = wc_get_product( get_the_ID() );

				if ( ! $product instanceof Product_Item ) {
					continue;
				}

				$item = self::build_item_data( $product );
				include AUCTEENO_PLUGIN_DIR . 'templates/parts/item-card.php';
			}
			wp_reset_postdata();
			$html = ob_get_clean();
		}

		return array(
			'html'  => $html,
			'page'  => $page,
			'pages' => (int) $query->max_num_pages,
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * Build auction data array for template.
	 *
	 * @param Product_Auction $product Auction product instance.
	 * @return array Auction data for template.
	 */
	private static function build_auction_data( Product_Auction $product ): array {
		$end_utc = $product->get_bidding_ends_at_utc();
		$end_ts  = $product->get_bidding_ends_at_timestamp() ?? 0;

		// Get featured image.
		$image_id  = $product->get_image_id();
		$image_url = '';
		if ( $image_id ) {
			$image_src = wp_get_attachment_image_src( $image_id, 'woocommerce_thumbnail' );
			if ( $image_src ) {
				$image_url = $image_src[0];
			}
		}

		// Get source label from auction type taxonomy.
		$source_label = '';
		$terms        = wp_get_post_terms( $product->get_id(), 'auction-type', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$source_label = $terms[0];
		}

		return array(
			'id'           => $product->get_id(),
			'title'        => $product->get_name(),
			'permalink'    => $product->get_permalink(),
			'image_url'    => $image_url,
			'source_label' => $source_label,
			'end_ts'       => $end_ts,
			'end_iso'      => DateTime_Helper::to_iso8601( $end_utc ),
		);
	}

	/**
	 * Build item data array for template.
	 *
	 * @param Product_Item $product Item product instance.
	 * @return array Item data for template.
	 */
	private static function build_item_data( Product_Item $product ): array {
		$end_utc = $product->get_bidding_ends_at_utc();
		$end_ts  = $product->get_bidding_ends_at_timestamp() ?? 0;

		// Get featured image.
		$image_id  = $product->get_image_id();
		$image_url = '';
		if ( $image_id ) {
			$image_src = wp_get_attachment_image_src( $image_id, 'woocommerce_thumbnail' );
			if ( $image_src ) {
				$image_url = $image_src[0];
			}
		}

		// Build location label.
		$location       = $product->get_location();
		$location_parts = array();

		if ( ! empty( $location['city'] ) ) {
			$location_parts[] = $location['city'];
		}

		// Get state/subdivision name from term.
		if ( ! empty( $location['subdivision'] ) ) {
			$subdivision_term = get_term( $location['subdivision'], 'aucteeno-location' );
			if ( $subdivision_term && ! is_wp_error( $subdivision_term ) ) {
				$location_parts[] = $subdivision_term->name;
			}
		}

		$location_label = implode( ', ', $location_parts );

		return array(
			'id'             => $product->get_id(),
			'title'          => $product->get_name(),
			'permalink'      => $product->get_permalink(),
			'image_url'      => $image_url,
			'location_label' => $location_label,
			'end_ts'         => $end_ts,
			'end_iso'        => DateTime_Helper::to_iso8601( $end_utc ),
		);
	}

	/**
	 * Normalize locations input to array.
	 *
	 * @param mixed $locations Location input (string, array, or empty).
	 * @return array Array of location slugs or IDs.
	 */
	private static function normalize_locations( $locations ): array {
		if ( empty( $locations ) ) {
			return array();
		}

		if ( is_string( $locations ) ) {
			$locations = array_filter( array_map( 'trim', explode( ',', $locations ) ) );
		}

		if ( ! is_array( $locations ) ) {
			return array();
		}

		return array_values( array_filter( $locations ) );
	}

	/**
	 * Get all location terms for dropdown.
	 *
	 * @return array Array of location terms with id, name, and parent.
	 */
	public static function get_location_terms(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'aucteeno-location',
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$result = array();
		foreach ( $terms as $term ) {
			$result[] = array(
				'id'     => $term->term_id,
				'slug'   => $term->slug,
				'name'   => $term->name,
				'parent' => $term->parent,
			);
		}

		return $result;
	}
}
