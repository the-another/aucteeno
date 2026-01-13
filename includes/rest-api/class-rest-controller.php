<?php
/**
 * REST API Controller Class
 *
 * Handles REST API endpoints for auctions and items.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\REST_API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Query;
use WP_Block;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Auction;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Item;
use TheAnother\Plugin\Aucteeno\Helpers\DateTime_Helper;
use TheAnother\Plugin\Aucteeno\Database\Status_Mapper;
use TheAnother\Plugin\Aucteeno\Database\Database_Auctions;
use TheAnother\Plugin\Aucteeno\Database\Database_Items;

/**
 * Class REST_Controller
 *
 * REST API controller for auctions and items.
 */
class REST_Controller extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'aucteeno/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Auction routes.
		register_rest_route(
			$this->namespace,
			'/auctions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_auctions' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'page'           => array(
							'description'       => 'Page number.',
							'type'              => 'integer',
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page'       => array(
							'description'       => 'Items per page.',
							'type'              => 'integer',
							'default'           => 10,
							'minimum'           => 1,
							'maximum'           => 50,
							'sanitize_callback' => 'absint',
						),
						'location'       => array(
							'description'       => 'Location term slug or ID.',
							'type'              => array( 'string', 'array' ),
							'default'           => '',
							'sanitize_callback' => array( $this, 'sanitize_location_param' ),
						),
						'user_id'        => array(
							'description'       => 'Filter by user/vendor ID.',
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
						'country'        => array(
							'description'       => 'Filter by location country code.',
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'subdivision'    => array(
							'description'       => 'Filter by location subdivision.',
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'sort'           => array(
							'description' => 'Sort order.',
							'type'        => 'string',
							'default'     => 'ending_soon',
							'enum'        => array( 'ending_soon', 'newest' ),
						),
						'format'         => array(
							'description' => 'Response format: html (fragments) or json (data).',
							'type'        => 'string',
							'default'     => 'html',
							'enum'        => array( 'html', 'json' ),
						),
						'block_template' => array(
							'description'       => 'Block template JSON for rendering cards with same structure as initial load.',
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'wp_kses_post',
						),
						'page_url'       => array(
							'description'       => 'Original page URL for pagination link generation.',
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'esc_url_raw',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_auction' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/auctions/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_auction' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_auction' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_auction' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);

		// Locations route for filters.
		register_rest_route(
			$this->namespace,
			'/locations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_locations' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// Item routes.
		register_rest_route(
			$this->namespace,
			'/items',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'page'           => array(
							'description'       => 'Page number.',
							'type'              => 'integer',
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page'       => array(
							'description'       => 'Items per page.',
							'type'              => 'integer',
							'default'           => 10,
							'minimum'           => 1,
							'maximum'           => 50,
							'sanitize_callback' => 'absint',
						),
						'location'       => array(
							'description'       => 'Location term slug or ID.',
							'type'              => array( 'string', 'array' ),
							'default'           => '',
							'sanitize_callback' => array( $this, 'sanitize_location_param' ),
						),
						'auction_id'     => array(
							'description'       => 'Parent auction ID.',
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
						'user_id'        => array(
							'description'       => 'Filter by user/vendor ID.',
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
						'country'        => array(
							'description'       => 'Filter by location country code.',
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'subdivision'    => array(
							'description'       => 'Filter by location subdivision.',
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'sort'           => array(
							'description' => 'Sort order.',
							'type'        => 'string',
							'default'     => 'ending_soon',
							'enum'        => array( 'ending_soon', 'newest' ),
						),
						'format'         => array(
							'description' => 'Response format: html (fragments) or json (data).',
							'type'        => 'string',
							'default'     => 'html',
							'enum'        => array( 'html', 'json' ),
						),
						'block_template' => array(
							'description'       => 'Block template JSON for rendering cards with same structure as initial load.',
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'wp_kses_post',
						),
						'page_url'       => array(
							'description'       => 'Original page URL for pagination link generation.',
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'esc_url_raw',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/items/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Check if user can get items.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'read' );
	}

	/**
	 * Check if user can get a single item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return current_user_can( 'read' );
	}

	/**
	 * Check if user can create items.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check if user can update items.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check if user can delete items.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return current_user_can( 'delete_posts' );
	}

	/**
	 * Get locations for filter dropdowns.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_locations( $request ) {
		$locations = self::get_location_terms();
		return new WP_REST_Response( $locations, 200 );
	}

	/**
	 * Get all location terms for dropdown.
	 *
	 * @return array Array of location terms with id, name, and parent.
	 */
	private static function get_location_terms(): array {
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

	/**
	 * Sanitize location parameter.
	 *
	 * @param mixed $value Location value.
	 * @return array Sanitized location array.
	 */
	public function sanitize_location_param( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		if ( is_string( $value ) ) {
			return array_filter( array_map( 'sanitize_text_field', explode( ',', $value ) ) );
		}

		if ( is_array( $value ) ) {
			return array_filter( array_map( 'sanitize_text_field', $value ) );
		}

		return array();
	}

	/**
	 * Get auctions.
	 *
	 * Returns HTML fragments by default, or JSON data if format=json.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_auctions( $request ) {
		$format = $request->get_param( 'format' ) ?? 'html';

		// For JSON format, query products directly.
		if ( 'json' === $format ) {
			$page     = $request->get_param( 'page' ) ?? 1;
			$per_page = $request->get_param( 'per_page' ) ?? 10;
			$location = $request->get_param( 'location' ) ?? array();
			$sort     = $request->get_param( 'sort' ) ?? 'ending_soon';

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
			$locations = $this->sanitize_location_param( $location );
			if ( ! empty( $locations ) ) {
				$query_args['tax_query']['relation'] = 'AND';
				$query_args['tax_query'][]           = array(
					'taxonomy' => 'aucteeno-location',
					'field'    => is_numeric( $locations[0] ) ? 'term_id' : 'slug',
					'terms'    => $locations,
				);
			}

			// Add sorting.
			if ( 'ending_soon' !== $sort ) {
				$query_args['orderby'] = array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				);
			}

			$query    = new WP_Query( $query_args );
			$auctions = array();

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$product = wc_get_product( get_the_ID() );

					if ( $product instanceof Product_Auction ) {
						$auctions[] = $this->product_to_auction_array( $product );
					}
				}
				wp_reset_postdata();
			}

			return new WP_REST_Response( $auctions, 200 );
		}

		// For HTML format, use HPS database query and render HTML.
		$args = array(
			'page'        => $request->get_param( 'page' ) ?? 1,
			'per_page'    => $request->get_param( 'per_page' ) ?? 10,
			'sort'        => $request->get_param( 'sort' ) ?? 'ending_soon',
			'user_id'     => $request->get_param( 'user_id' ) ?? 0,
			'country'     => $request->get_param( 'country' ) ?? '',
			'subdivision' => $request->get_param( 'subdivision' ) ?? '',
		);

		$result = Database_Auctions::query_for_listing( $args );

		// Parse block template if provided.
		$block_template_json = $request->get_param( 'block_template' ) ?? '';
		$block_template      = ! empty( $block_template_json ) ? json_decode( $block_template_json, true ) : null;

		// Render HTML for each auction using block template or fallback.
		ob_start();
		foreach ( $result['items'] as $item_data ) {
			echo $this->render_card( $item_data, 'auctions', $block_template );
		}
		$html = ob_get_clean();

		// Get page URL for pagination links.
		$page_url = $request->get_param( 'page_url' ) ?? '';

		// Render pagination HTML.
		$pagination_html = $this->render_pagination( $result['page'], $result['pages'], $page_url );

		return new WP_REST_Response(
			array(
				'html'       => $html,
				'pagination' => $pagination_html,
				'page'       => $result['page'],
				'pages'      => $result['pages'],
				'total'      => $result['total'],
			),
			200
		);
	}

	/**
	 * Get single auction.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_auction( $request ) {
		$id      = (int) $request['id'];
		$product = wc_get_product( $id );

		if ( ! $product || ! ( $product instanceof Product_Auction ) ) {
			return new WP_Error( 'not_found', __( 'Auction not found.', 'aucteeno' ), array( 'status' => 404 ) );
		}

		$auction = $this->product_to_auction_array( $product );

		return new WP_REST_Response( $auction, 200 );
	}

	/**
	 * Create auction.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_auction( $request ) {
		$data = $request->get_json_params();

		// Create new product.
		$product = new Product_Auction();
		$product->set_name( $data['name'] ?? '' );
		$product->set_status( 'publish' );

		// Set custom fields.
		$this->set_auction_data_from_array( $product, $data );

		$id = $product->save();

		if ( ! $id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create auction.', 'aucteeno' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'id' => $id ), 201 );
	}

	/**
	 * Update auction.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_auction( $request ) {
		$id      = (int) $request['id'];
		$product = wc_get_product( $id );

		if ( ! $product || ! ( $product instanceof Product_Auction ) ) {
			return new WP_Error( 'not_found', __( 'Auction not found.', 'aucteeno' ), array( 'status' => 404 ) );
		}

		$data = $request->get_json_params();

		// Update basic fields.
		if ( isset( $data['name'] ) ) {
			$product->set_name( $data['name'] );
		}

		// Set custom fields.
		$this->set_auction_data_from_array( $product, $data );

		$product->save();

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Delete auction.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_auction( $request ) {
		$id      = (int) $request['id'];
		$product = wc_get_product( $id );

		if ( ! $product || ! ( $product instanceof Product_Auction ) ) {
			return new WP_Error( 'not_found', __( 'Auction not found.', 'aucteeno' ), array( 'status' => 404 ) );
		}

		// Delete the product (moves to trash by default).
		$deleted = wp_delete_post( $id, true );

		if ( ! $deleted ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete auction.', 'aucteeno' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Get items.
	 *
	 * Returns HTML fragments by default, or JSON data if format=json.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$format = $request->get_param( 'format' ) ?? 'html';

		// For JSON format, query products directly.
		if ( 'json' === $format ) {
			$page       = $request->get_param( 'page' ) ?? 1;
			$per_page   = $request->get_param( 'per_page' ) ?? 10;
			$location   = $request->get_param( 'location' ) ?? array();
			$auction_id = $request->get_param( 'auction_id' ) ?? 0;
			$sort       = $request->get_param( 'sort' ) ?? 'ending_soon';

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
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			);

			// Add auction parent filter.
			if ( $auction_id > 0 ) {
				$query_args['post_parent'] = $auction_id;
			}

			// Add location filter.
			$locations = $this->sanitize_location_param( $location );
			if ( ! empty( $locations ) ) {
				$query_args['tax_query']['relation'] = 'AND';
				$query_args['tax_query'][]           = array(
					'taxonomy' => 'aucteeno-location',
					'field'    => is_numeric( $locations[0] ) ? 'term_id' : 'slug',
					'terms'    => $locations,
				);
			}

			// Add sorting.
			if ( 'ending_soon' !== $sort ) {
				$query_args['orderby'] = array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				);
				unset( $query_args['order'] );
			}

			$query = new WP_Query( $query_args );
			$items = array();

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$product = wc_get_product( get_the_ID() );

					if ( $product instanceof Product_Item ) {
						$items[] = $this->product_to_item_array( $product );
					}
				}
				wp_reset_postdata();
			}

			return new WP_REST_Response( $items, 200 );
		}

		// For HTML format, use HPS database query and render HTML.
		$args = array(
			'page'        => $request->get_param( 'page' ) ?? 1,
			'per_page'    => $request->get_param( 'per_page' ) ?? 10,
			'sort'        => $request->get_param( 'sort' ) ?? 'ending_soon',
			'user_id'     => $request->get_param( 'user_id' ) ?? 0,
			'country'     => $request->get_param( 'country' ) ?? '',
			'subdivision' => $request->get_param( 'subdivision' ) ?? '',
			'auction_id'  => $request->get_param( 'auction_id' ) ?? 0,
		);

		$result = Database_Items::query_for_listing( $args );

		// Parse block template if provided.
		$block_template_json = $request->get_param( 'block_template' ) ?? '';
		$block_template      = ! empty( $block_template_json ) ? json_decode( $block_template_json, true ) : null;

		// Render HTML for each item using block template or fallback.
		ob_start();
		foreach ( $result['items'] as $item_data ) {
			echo $this->render_card( $item_data, 'items', $block_template );
		}
		$html = ob_get_clean();

		// Get page URL for pagination links.
		$page_url = $request->get_param( 'page_url' ) ?? '';

		// Render pagination HTML.
		$pagination_html = $this->render_pagination( $result['page'], $result['pages'], $page_url );

		return new WP_REST_Response(
			array(
				'html'       => $html,
				'pagination' => $pagination_html,
				'page'       => $result['page'],
				'pages'      => $result['pages'],
				'total'      => $result['total'],
			),
			200
		);
	}

	/**
	 * Get single item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$id      = (int) $request['id'];
		$product = wc_get_product( $id );

		if ( ! $product || ! ( $product instanceof Product_Item ) ) {
			return new WP_Error( 'not_found', __( 'Item not found.', 'aucteeno' ), array( 'status' => 404 ) );
		}

		$item = $this->product_to_item_array( $product );

		return new WP_REST_Response( $item, 200 );
	}

	/**
	 * Create item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$data = $request->get_json_params();

		// Validate parent auction is provided.
		if ( empty( $data['auction_id'] ) ) {
			return new WP_Error( 'missing_auction', __( 'Parent auction is required.', 'aucteeno' ), array( 'status' => 400 ) );
		}

		// Create new product.
		$product = new Product_Item();
		$product->set_name( $data['name'] ?? '' );
		$product->set_status( 'publish' );
		$product->set_auction_id( $data['auction_id'] );

		// Set custom fields.
		$this->set_item_data_from_array( $product, $data );

		$id = $product->save();

		if ( ! $id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create item.', 'aucteeno' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'id' => $id ), 201 );
	}

	/**
	 * Update item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$id      = (int) $request['id'];
		$product = wc_get_product( $id );

		if ( ! $product || ! ( $product instanceof Product_Item ) ) {
			return new WP_Error( 'not_found', __( 'Item not found.', 'aucteeno' ), array( 'status' => 404 ) );
		}

		$data = $request->get_json_params();

		// Update basic fields.
		if ( isset( $data['name'] ) ) {
			$product->set_name( $data['name'] );
		}

		// Set custom fields.
		$this->set_item_data_from_array( $product, $data );

		$product->save();

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Delete item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$id      = (int) $request['id'];
		$product = wc_get_product( $id );

		if ( ! $product || ! ( $product instanceof Product_Item ) ) {
			return new WP_Error( 'not_found', __( 'Item not found.', 'aucteeno' ), array( 'status' => 404 ) );
		}

		// Delete the product (moves to trash by default).
		$deleted = wp_delete_post( $id, true );

		if ( ! $deleted ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete item.', 'aucteeno' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Render a card using block template or fallback.
	 *
	 * If a block template is provided (from query-loop's card block), it renders
	 * using WordPress block system to match initial page load. Otherwise, falls
	 * back to a basic card structure.
	 *
	 * @param array      $item_data      Item data from HPS query.
	 * @param string     $query_type     Query type ('auctions' or 'items').
	 * @param array|null $block_template Parsed block template array or null.
	 * @return string Rendered HTML.
	 */
	private function render_card( array $item_data, string $query_type, ?array $block_template ): string {
		// If block template provided, render using WordPress block system.
		if ( $block_template && class_exists( 'WP_Block' ) ) {
			$context = array(
				'aucteeno/item'     => $item_data,
				'aucteeno/itemType' => $query_type,
			);

			$block = new WP_Block( $block_template, $context );
			return $block->render();
		}

		// Fallback: render a basic card.
		return $this->render_fallback_card( $item_data );
	}

	/**
	 * Render a basic fallback card when no block template is available.
	 *
	 * @param array $item_data Item data from HPS query.
	 * @return string Rendered HTML.
	 */
	private function render_fallback_card( array $item_data ): string {
		$status_map   = array(
			10 => 'running',
			20 => 'upcoming',
			30 => 'expired',
		);
		$status_class = $status_map[ $item_data['bidding_status'] ?? 10 ] ?? 'running';

		ob_start();
		?>
		<article class="aucteeno-card aucteeno-card--<?php echo esc_attr( $status_class ); ?>">
			<?php if ( ! empty( $item_data['image_url'] ) ) : ?>
				<a class="aucteeno-card__media" href="<?php echo esc_url( $item_data['permalink'] ?? '#' ); ?>">
					<img src="<?php echo esc_url( $item_data['image_url'] ); ?>" alt="" loading="lazy" />
				</a>
			<?php endif; ?>
			<a class="aucteeno-card__title" href="<?php echo esc_url( $item_data['permalink'] ?? '#' ); ?>">
				<?php echo esc_html( $item_data['title'] ?? '' ); ?>
			</a>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render pagination HTML for AJAX responses.
	 *
	 * @param int    $current_page Current page number.
	 * @param int    $total_pages  Total number of pages.
	 * @param string $base_url     Base URL for pagination links.
	 * @return string Pagination HTML.
	 */
	private function render_pagination( int $current_page, int $total_pages, string $base_url = '' ): string {
		if ( $total_pages <= 1 ) {
			return '';
		}

		$args = array(
			'total'     => $total_pages,
			'current'   => $current_page,
			'prev_text' => __( '&larr; Previous', 'aucteeno' ),
			'next_text' => __( 'Next &rarr;', 'aucteeno' ),
			'format'    => '?paged=%#%',
		);

		// Use provided base URL to avoid REST API URL in pagination links.
		if ( ! empty( $base_url ) ) {
			$args['base'] = trailingslashit( $base_url ) . '%_%';
		}

		$pagination = paginate_links( $args );

		if ( ! $pagination ) {
			return '';
		}

		// Add Interactivity API directives to pagination links.
		// Build clean URLs with both /page/X/ (pretty permalink) and ?paged=X (query string).
		$pagination = preg_replace_callback(
			'/<a([^>]*)href=["\']([^"\']*)\?paged=(\d+)[^"\']*["\'](([^>]*)>)/i',
			function ( $matches ) {
				$before_href = $matches[1];
				$base_href   = $matches[2]; // URL before ?paged=
				$page_num    = $matches[3];
				$after_attrs = $matches[5];
				// Build URL with /page/X/?paged=X format.
				$clean_href  = trailingslashit( $base_href ) . 'page/' . $page_num . '/?paged=' . $page_num;
				return '<a' . $before_href . 'href="' . esc_url( $clean_href ) . '"' . $after_attrs . ' data-wp-on--click="actions.loadPage" data-page="' . esc_attr( $page_num ) . '">';
			},
			$pagination
		);

		return $pagination;
	}

	/**
	 * Convert Product_Auction to array for JSON response.
	 *
	 * @param Product_Auction $product Product instance.
	 * @return array Auction data array.
	 */
	private function product_to_auction_array( Product_Auction $product ): array {
		$data = array(
			'id'                          => $product->get_id(),
			'name'                        => $product->get_name(),
			'permalink'                   => $product->get_permalink(),
			'status'                      => $product->get_status(),
			'product_url'                 => $product->get_product_url(),
			'button_text'                 => $product->get_button_text(),
			'location'                    => $product->get_location(),
			'notice'                      => $product->get_notice(),
			'bidding_notice'              => $product->get_bidding_notice(),
			'directions'                  => $product->get_directions(),
			'terms_conditions'            => $product->get_terms_conditions(),
			'bidding_starts_at_utc'       => $product->get_bidding_starts_at_utc(),
			'bidding_starts_at_local'     => $product->get_bidding_starts_at_local(),
			'bidding_starts_at_timestamp' => $product->get_bidding_starts_at_timestamp(),
			'bidding_ends_at_utc'         => $product->get_bidding_ends_at_utc(),
			'bidding_ends_at_local'       => $product->get_bidding_ends_at_local(),
			'bidding_ends_at_timestamp'   => $product->get_bidding_ends_at_timestamp(),
		);

		return $data;
	}

	/**
	 * Convert Product_Item to array for JSON response.
	 *
	 * @param Product_Item $product Product instance.
	 * @return array Item data array.
	 */
	private function product_to_item_array( Product_Item $product ): array {
		$data = array(
			'id'                          => $product->get_id(),
			'name'                        => $product->get_name(),
			'permalink'                   => $product->get_permalink(),
			'status'                      => $product->get_status(),
			'auction_id'                  => $product->get_auction_id(),
			'lot_no'                      => $product->get_lot_no(),
			'description'                 => $product->get_description(),
			'asking_bid'                  => $product->get_asking_bid(),
			'current_bid'                 => $product->get_current_bid(),
			'sold_price'                  => $product->get_sold_price(),
			'sold_at_utc'                 => $product->get_sold_at_utc(),
			'sold_at_local'               => $product->get_sold_at_local(),
			'sold_at_timestamp'           => $product->get_sold_at_timestamp(),
			'location'                    => $product->get_location(),
			'bidding_starts_at_utc'       => $product->get_bidding_starts_at_utc(),
			'bidding_starts_at_local'     => $product->get_bidding_starts_at_local(),
			'bidding_starts_at_timestamp' => $product->get_bidding_starts_at_timestamp(),
			'bidding_ends_at_utc'         => $product->get_bidding_ends_at_utc(),
			'bidding_ends_at_local'       => $product->get_bidding_ends_at_local(),
			'bidding_ends_at_timestamp'   => $product->get_bidding_ends_at_timestamp(),
		);

		return $data;
	}

	/**
	 * Set auction product data from array.
	 *
	 * @param Product_Auction $product Product instance.
	 * @param array           $data    Data array.
	 * @return void
	 */
	private function set_auction_data_from_array( Product_Auction $product, array $data ): void {
		if ( isset( $data['product_url'] ) ) {
			$product->set_product_url( $data['product_url'] );
		}

		if ( isset( $data['button_text'] ) ) {
			$product->set_button_text( $data['button_text'] );
		}

		if ( isset( $data['location'] ) ) {
			$product->set_location( $data['location'] );
		}

		if ( isset( $data['notice'] ) ) {
			$product->set_notice( $data['notice'] );
		}

		if ( isset( $data['bidding_notice'] ) ) {
			$product->set_bidding_notice( $data['bidding_notice'] );
		}

		if ( isset( $data['directions'] ) ) {
			$product->set_directions( $data['directions'] );
		}

		if ( isset( $data['terms_conditions'] ) ) {
			$product->set_terms_conditions( $data['terms_conditions'] );
		}

		// Handle datetime fields - convert GMT to UTC if needed.
		if ( isset( $data['bidding_starts_at_gmt'] ) ) {
			$product->set_bidding_starts_at_utc( $data['bidding_starts_at_gmt'] );
		} elseif ( isset( $data['bidding_starts_at_utc'] ) ) {
			$product->set_bidding_starts_at_utc( $data['bidding_starts_at_utc'] );
		}

		if ( isset( $data['bidding_ends_at_gmt'] ) ) {
			$product->set_bidding_ends_at_utc( $data['bidding_ends_at_gmt'] );
		} elseif ( isset( $data['bidding_ends_at_utc'] ) ) {
			$product->set_bidding_ends_at_utc( $data['bidding_ends_at_utc'] );
		}
	}

	/**
	 * Set item product data from array.
	 *
	 * @param Product_Item $product Product instance.
	 * @param array        $data   Data array.
	 * @return void
	 */
	private function set_item_data_from_array( Product_Item $product, array $data ): void {
		if ( isset( $data['auction_id'] ) ) {
			$product->set_auction_id( $data['auction_id'] );
		}

		if ( isset( $data['lot_no'] ) ) {
			$product->set_lot_no( $data['lot_no'] );
		}

		if ( isset( $data['description'] ) ) {
			$product->set_description( $data['description'] );
		}

		if ( isset( $data['asking_bid'] ) ) {
			$product->set_asking_bid( $data['asking_bid'] );
		}

		if ( isset( $data['current_bid'] ) ) {
			$product->set_current_bid( $data['current_bid'] );
		}

		if ( isset( $data['sold_price'] ) ) {
			$product->set_sold_price( $data['sold_price'] );
		}

		if ( isset( $data['location'] ) ) {
			$product->set_location( $data['location'] );
		}

		// Handle datetime fields - convert GMT to UTC if needed.
		if ( isset( $data['bidding_starts_at_gmt'] ) ) {
			$product->set_bidding_starts_at_utc( $data['bidding_starts_at_gmt'] );
		} elseif ( isset( $data['bidding_starts_at_utc'] ) ) {
			$product->set_bidding_starts_at_utc( $data['bidding_starts_at_utc'] );
		}

		if ( isset( $data['bidding_ends_at_gmt'] ) ) {
			$product->set_bidding_ends_at_utc( $data['bidding_ends_at_gmt'] );
		} elseif ( isset( $data['bidding_ends_at_utc'] ) ) {
			$product->set_bidding_ends_at_utc( $data['bidding_ends_at_utc'] );
		}

		if ( isset( $data['sold_at_gmt'] ) ) {
			$product->set_sold_at_utc( $data['sold_at_gmt'] );
		} elseif ( isset( $data['sold_at_utc'] ) ) {
			$product->set_sold_at_utc( $data['sold_at_utc'] );
		}
	}
}
