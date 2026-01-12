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
use TheAnother\Plugin\Aucteeno\Fragment_Renderer;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Auction;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Item;
use TheAnother\Plugin\Aucteeno\Helpers\DateTime_Helper;
use TheAnother\Plugin\Aucteeno\Database\Status_Mapper;

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
						'page'        => array(
							'description'       => 'Page number.',
							'type'              => 'integer',
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page'    => array(
							'description'       => 'Items per page.',
							'type'              => 'integer',
							'default'           => 10,
							'minimum'           => 1,
							'maximum'           => 50,
							'sanitize_callback' => 'absint',
						),
						'location'    => array(
							'description'       => 'Location term slug or ID.',
							'type'              => array( 'string', 'array' ),
							'default'           => '',
							'sanitize_callback' => array( $this, 'sanitize_location_param' ),
						),
						'user_id'     => array(
							'description'       => 'Filter by user/vendor ID.',
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
						'country'     => array(
							'description'       => 'Filter by location country code.',
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'subdivision' => array(
							'description'       => 'Filter by location subdivision.',
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'sort'        => array(
							'description' => 'Sort order.',
							'type'        => 'string',
							'default'     => 'ending_soon',
							'enum'        => array( 'ending_soon', 'newest' ),
						),
						'format'      => array(
							'description' => 'Response format: html (fragments) or json (data).',
							'type'        => 'string',
							'default'     => 'html',
							'enum'        => array( 'html', 'json' ),
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
						'page'        => array(
							'description'       => 'Page number.',
							'type'              => 'integer',
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page'    => array(
							'description'       => 'Items per page.',
							'type'              => 'integer',
							'default'           => 10,
							'minimum'           => 1,
							'maximum'           => 50,
							'sanitize_callback' => 'absint',
						),
						'location'    => array(
							'description'       => 'Location term slug or ID.',
							'type'              => array( 'string', 'array' ),
							'default'           => '',
							'sanitize_callback' => array( $this, 'sanitize_location_param' ),
						),
						'auction_id'  => array(
							'description'       => 'Parent auction ID.',
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
						'user_id'     => array(
							'description'       => 'Filter by user/vendor ID.',
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
						'country'     => array(
							'description'       => 'Filter by location country code.',
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'subdivision' => array(
							'description'       => 'Filter by location subdivision.',
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'sort'        => array(
							'description' => 'Sort order.',
							'type'        => 'string',
							'default'     => 'ending_soon',
							'enum'        => array( 'ending_soon', 'newest' ),
						),
						'format'      => array(
							'description' => 'Response format: html (fragments) or json (data).',
							'type'        => 'string',
							'default'     => 'html',
							'enum'        => array( 'html', 'json' ),
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
		$locations = Fragment_Renderer::get_location_terms();
		return new WP_REST_Response( $locations, 200 );
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

			$query   = new WP_Query( $query_args );
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

		// For HTML format, use the Fragment Renderer.
		$args = array(
			'page'     => $request->get_param( 'page' ) ?? 1,
			'per_page' => $request->get_param( 'per_page' ) ?? 10,
			'location' => $request->get_param( 'location' ) ?? array(),
			'sort'     => $request->get_param( 'sort' ) ?? 'ending_soon',
		);

		$result = Fragment_Renderer::auctions( $args );

		return new WP_REST_Response( $result, 200 );
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
			$items  = array();

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

		// For HTML format, use the Fragment Renderer.
		$args = array(
			'page'       => $request->get_param( 'page' ) ?? 1,
			'per_page'   => $request->get_param( 'per_page' ) ?? 10,
			'location'   => $request->get_param( 'location' ) ?? array(),
			'auction_id' => $request->get_param( 'auction_id' ) ?? 0,
			'sort'       => $request->get_param( 'sort' ) ?? 'ending_soon',
		);

		$result = Fragment_Renderer::items( $args );

		return new WP_REST_Response( $result, 200 );
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
	 * Convert Product_Auction to array for JSON response.
	 *
	 * @param Product_Auction $product Product instance.
	 * @return array Auction data array.
	 */
	private function product_to_auction_array( Product_Auction $product ): array {
		$data = array(
			'id'                        => $product->get_id(),
			'name'                      => $product->get_name(),
			'permalink'                 => $product->get_permalink(),
			'status'                    => $product->get_status(),
			'product_url'               => $product->get_product_url(),
			'button_text'               => $product->get_button_text(),
			'location'                  => $product->get_location(),
			'notice'                    => $product->get_notice(),
			'bidding_notice'            => $product->get_bidding_notice(),
			'directions'                => $product->get_directions(),
			'terms_conditions'          => $product->get_terms_conditions(),
			'bidding_starts_at_utc'     => $product->get_bidding_starts_at_utc(),
			'bidding_starts_at_local'   => $product->get_bidding_starts_at_local(),
			'bidding_starts_at_timestamp' => $product->get_bidding_starts_at_timestamp(),
			'bidding_ends_at_utc'       => $product->get_bidding_ends_at_utc(),
			'bidding_ends_at_local'     => $product->get_bidding_ends_at_local(),
			'bidding_ends_at_timestamp' => $product->get_bidding_ends_at_timestamp(),
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
			'id'                        => $product->get_id(),
			'name'                      => $product->get_name(),
			'permalink'                 => $product->get_permalink(),
			'status'                    => $product->get_status(),
			'auction_id'                => $product->get_auction_id(),
			'lot_no'                    => $product->get_lot_no(),
			'description'               => $product->get_description(),
			'asking_bid'                 => $product->get_asking_bid(),
			'current_bid'               => $product->get_current_bid(),
			'sold_price'                => $product->get_sold_price(),
			'sold_at_utc'               => $product->get_sold_at_utc(),
			'sold_at_local'             => $product->get_sold_at_local(),
			'sold_at_timestamp'         => $product->get_sold_at_timestamp(),
			'location'                  => $product->get_location(),
			'bidding_starts_at_utc'     => $product->get_bidding_starts_at_utc(),
			'bidding_starts_at_local'   => $product->get_bidding_starts_at_local(),
			'bidding_starts_at_timestamp' => $product->get_bidding_starts_at_timestamp(),
			'bidding_ends_at_utc'       => $product->get_bidding_ends_at_utc(),
			'bidding_ends_at_local'     => $product->get_bidding_ends_at_local(),
			'bidding_ends_at_timestamp' => $product->get_bidding_ends_at_timestamp(),
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
	 * @param array         $data   Data array.
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
