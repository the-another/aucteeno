<?php
/**
 * Item Meta Fields Class
 *
 * Handles custom meta fields for item products.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Admin;

use TheAnother\Plugin\Aucteeno\Database\Database_Items;
use TheAnother\Plugin\Aucteeno\Helpers\DateTime_Helper;
use TheAnother\Plugin\Aucteeno\Helpers\Location_Helper;
use TheAnother\Plugin\Aucteeno\Hook_Manager;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Auction;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Item;

/**
 * Class Meta_Fields_Item
 *
 * Manages meta fields for item products.
 */
class Custom_Fields_Item {

	/**
	 * Hook manager instance.
	 *
	 * @var Hook_Manager
	 */
	private Hook_Manager $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param Hook_Manager $hook_manager Hook manager instance.
	 */
	public function __construct( Hook_Manager $hook_manager ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Initialize meta fields.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->hook_manager->register_action(
			'woocommerce_admin_process_product_object',
			array( $this, 'process_product_object' ),
			10,
			1
		);

		// Register tab-specific action hooks.
		$this->register_tab_content_hooks();
	}

	/**
	 * Register tab content hooks.
	 *
	 * @return void
	 */
	public function register_tab_content_hooks(): void {
		// Register via Hook_Manager (for tracking).

		$this->hook_manager->register_action(
			'aucteeno_product_tab_link',
			function( $post_id, $post_type ) {
                $this->render_link_tab( $post_id, $post_type );
			},
			10,
			2
		);
		$this->hook_manager->register_action(
			'aucteeno_product_tab_location',
			function( $post_id, $post_type ) {
                $this->render_location_tab( $post_id, $post_type );
			},
			10,
			2
		);
		$this->hook_manager->register_action(
			'aucteeno_product_tab_times',
			function( $post_id, $post_type ) {
                $this->render_times_tab( $post_id, $post_type );
			},
			10,
			2
		);
		$this->hook_manager->register_action(
			'aucteeno_product_tab_details',
			function( $post_id, $post_type ) {
                $this->render_details_tab( $post_id, $post_type );
			},
			10,
			2
		);

	}

	/**
	 * Render Link tab content.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function render_link_tab( int $post_id, string $post_type = '' ): void {
		global $post;

		// Only render for item products.
		if ( ! $post_id ) {
			$post_id = $post ? $post->ID : 0;
		}

		// Get product object to use getters (create empty instance if needed for new products).
		$product = wc_get_product( $post_id );
		if ( ! $product || ! ( $product instanceof Product_Item ) ) {
			$product = new Product_Item( 0 );
		}

		// Always render content - visibility is controlled by show_if_{PRODUCT_TYPE} CSS class.
		// WooCommerce's JavaScript will show/hide the content based on the product type selector.

		echo '<div class="show_if_' . esc_attr( Product_Item::PRODUCT_TYPE ) . '">';
		echo '<div class="options_group">';

		// Parent Auction Selection (first in Link group).
		$this->render_parent_auction_field( $post_id );

		// Lot No.
		woocommerce_wp_text_input(
			array(
				'id'          => 'aucteeno_item_lot_no',
				'label'       => __( 'Lot No.', 'aucteeno' ),
				'description' => __( 'Lot number for the item.', 'aucteeno' ),
				'type'        => 'text',
				'value'       => $product->get_lot_no(),
			)
		);

		// Product URL (from WC_Product_External).
		woocommerce_wp_text_input(
			array(
				'id'          => 'aucteeno_item_external_url',
				'label'       => __( 'Item URL', 'aucteeno' ),
				'description' => __( 'URL for the item.', 'aucteeno' ),
				'type'        => 'url',
				'value'       => $product->get_product_url(),
			)
		);

		// Button Text.
		woocommerce_wp_text_input(
			array(
				'id'          => 'aucteeno_item_button_text',
				'label'       => __( 'Button Text', 'aucteeno' ),
				'description' => __( 'Text displayed on the button.', 'aucteeno' ),
				'type'        => 'text',
				'value'       => $product->get_button_text(),
				'default'     => 'Bid now',
			)
		);

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render Location tab content.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function render_location_tab( int $post_id, string $post_type = '' ): void {
		global $post;

		// Only render for item products.
		if ( ! $post_id ) {
			$post_id = $post ? $post->ID : 0;
		}

		// Get product object to use getters (create empty instance if needed for new products).
		$product = wc_get_product( $post_id );
		if ( ! $product || ! ( $product instanceof Product_Item ) ) {
			$product = new Product_Item( 0 );
		}

		// Always render content - visibility is controlled by show_if_{PRODUCT_TYPE} CSS class.

		echo '<div class="show_if_' . esc_attr( Product_Item::PRODUCT_TYPE ) . '">';
		echo '<div class="options_group">';
		$this->render_location_fields( $product );
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render Times tab content.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function render_times_tab( int $post_id, string $post_type = '' ): void {
		global $post;

		// Only render for item products.
		if ( ! $post_id ) {
			$post_id = $post ? $post->ID : 0;
		}

		// Get product object to use getters (create empty instance if needed for new products).
		$product = wc_get_product( $post_id );
		if ( ! $product || ! ( $product instanceof Product_Item ) ) {
			$product = new Product_Item( 0 );
		}

		// Always render content - visibility is controlled by show_if_{PRODUCT_TYPE} CSS class.

		echo '<div class="show_if_' . esc_attr( Product_Item::PRODUCT_TYPE ) . '">';
		echo '<div class="options_group">';
		$this->render_times_fields( $product );
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render Details tab content.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function render_details_tab( int $post_id, string $post_type = '' ): void {
		global $post;

		// Only render for item products.
		if ( ! $post_id ) {
			$post_id = $post ? $post->ID : 0;
		}

		// Get product object to use getters (create empty instance if needed for new products).
		$product = wc_get_product( $post_id );
		if ( ! $product || ! ( $product instanceof Product_Item ) ) {
			$product = new Product_Item( 0 );
		}

		// Always render content - visibility is controlled by show_if_{PRODUCT_TYPE} CSS class.

		echo '<div class="show_if_' . esc_attr( Product_Item::PRODUCT_TYPE ) . '">';
		echo '<div class="options_group">';
		$this->render_bidding_fields( $product );
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render parent auction selection field.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function render_parent_auction_field( int $post_id ): void {
		$parent_id = wp_get_post_parent_id( $post_id );

		// Query published auctions using WooCommerce functions.
		// Only include auctions with "running" or "expired" bidding-status terms.
		$auctions = wc_get_products(
			array(
				'type'     => Product_Auction::PRODUCT_TYPE,
				'status'   => 'publish',
				'limit'    => -1,
				'orderby'  => 'title',
				'order'    => 'ASC',
				'tax_query' => array(
					array(
						'taxonomy' => 'auction-bidding-status',
						'field'    => 'slug',
						'terms'    => array( 'running', 'upcoming' ),
						'operator' => 'IN',
					),
				),
			)
		);

		echo '<p class="form-field">';
		echo '<label for="aucteeno_item_parent_auction_id">' . esc_html__( 'Parent Auction', 'aucteeno' ) . ' <span class="required">*</span></label>';
		echo '<select name="aucteeno_item_parent_auction_id" id="aucteeno_item_parent_auction_id" class="aucteeno-select2-auction" data-required="true">';
		echo '<option value="">' . esc_html__( 'Select an auction', 'aucteeno' ) . '</option>';

		if ( ! empty( $auctions ) ) {
			foreach ( $auctions as $auction_product ) {
				if ( ! $auction_product ) {
					continue;
				}
				$auction_id = $auction_product->get_id();
				$selected   = selected( $parent_id, $auction_id, false );
				echo '<option value="' . esc_attr( $auction_id ) . '" ' . $selected . '>' . esc_html( $auction_product->get_name() ) . '</option>';
			}
		}

		echo '</select>';
		echo '<span class="description">' . esc_html__( 'Items must belong to exactly one auction. Only published auctions with running or expired bidding status are shown.', 'aucteeno' ) . '</span>';
		echo '</p>';
	}

	/**
	 * Render location fields.
	 *
	 * @param Product_Item $product Product object.
	 * @return void
	 */
	private function render_location_fields( Product_Item $product ): void {
		$location = $product->get_location();

		// Get current country and state codes from taxonomy terms.
		$country_code = '';
		$state_code = '';

		// Get WooCommerce countries.
		$countries = WC()->countries->get_countries();
		if ( empty( $countries ) ) {
			$countries = array();
		}

		// Country dropdown.
		woocommerce_wp_select(
			array(
				'id' => 'aucteeno_item_location_country',
				'label' => __( 'Country', 'aucteeno' ),
				'value' => $location['country'],
				'options' => $countries,
				'wrapper_class' => 'form-field-wide',
				'custom_attributes' => array(
					'class' => 'select short aucteeno-country-select',
				),
			)
		);

		// Get states for selected country.
		$states = array();
		$show_state_field = false;
		if ( ! empty( $location['country'] ) ) {
			$wc_states = WC()->countries->get_states( $location['country'] );
			if ( is_array( $wc_states ) && ! empty( $wc_states ) ) {
				$states = $wc_states;
				$show_state_field = true;
			}
		}

		// State dropdown (always create, but hide if no states for current country).
		woocommerce_wp_select(
			array(
				'id' => 'aucteeno_item_location_state',
				'label' => __( 'State / Province', 'aucteeno' ),
				'value' => $location['subdivision'],
				'options' => $states,
				'wrapper_class' => $show_state_field ? 'form-field-wide' : 'form-field-wide' . ( empty( $states ) ? ' aucteeno-state-field-hidden' : '' ),
				'custom_attributes' => array(
					'class' => 'select short aucteeno-state-select',
					'data-country-field' => 'aucteeno_item_location_country',
				),
			)
		);

		// Hide state field if no states available for current country.
		if ( ! $show_state_field ) {
			echo '<script type="text/javascript">';
			echo 'jQuery(document).ready(function($) {';
			echo '  $("#aucteeno_item_location_state_field").hide();';
			echo '});';
			echo '</script>';
		}

		// City (first in Location group).
		woocommerce_wp_text_input(
			array(
				'id'    => 'aucteeno_item_location_city',
				'label' => __( 'City', 'aucteeno' ),
				'value' => $location['city'] ?? '',
			)
		);

		// Postal Code.
		woocommerce_wp_text_input(
			array(
				'id'    => 'aucteeno_item_location_postal_code',
				'label' => __( 'Postal Code', 'aucteeno' ),
				'value' => $location['postal_code'] ?? '',
			)
		);

		// Address.
		woocommerce_wp_text_input(
			array(
				'id'    => 'aucteeno_item_location_address',
				'label' => __( 'Address', 'aucteeno' ),
				'value' => $location['address'] ?? '',
			)
		);

		// Address 2.
		woocommerce_wp_text_input(
			array(
				'id'    => 'aucteeno_item_location_address2',
				'label' => __( 'Address 2', 'aucteeno' ),
				'value' => $location['address2'] ?? '',
			)
		);
	}

	/**
	 * Render times fields from database.
	 *
	 * @param Product_Item $product Product object.
	 * @return void
	 */
	private function render_times_fields( Product_Item $product ): void {
		// Bidding start time (local time).
		woocommerce_wp_text_input(
			array(
				'id'                => 'aucteeno_item_bidding_starts_at_local',
				'label'             => __( 'Bidding Starts At (Local time)', 'aucteeno' ),
				'type'              => 'datetime-local',
				'value'             => $product->get_bidding_starts_at_local(),
				'custom_attributes' => array(
					'step' => '1',
				),
			)
		);

		// Bidding start time (UTC time).
		woocommerce_wp_text_input(
			array(
				'id'                => 'aucteeno_item_bidding_starts_at_utc',
				'label'             => __( 'Bidding Starts At (UTC time)', 'aucteeno' ),
				'type'              => 'datetime-local',
				'value'             => $product->get_bidding_starts_at_utc(),
				'custom_attributes' => array(
					'step' => '1',
				),
			)
		);

		// Bidding end time (local time).
		woocommerce_wp_text_input(
			array(
				'id'                => 'aucteeno_item_bidding_ends_at_local',
				'label'             => __( 'Bidding Ends At (Local time)', 'aucteeno' ),
				'type'              => 'datetime-local',
				'value'             => $product->get_bidding_ends_at_local(),
				'custom_attributes' => array(
					'step' => '1',
				),
			)
		);

		// Bidding end time (UTC time).
		woocommerce_wp_text_input(
			array(
				'id'                => 'aucteeno_item_bidding_ends_at_utc',
				'label'             => __( 'Bidding Ends At (UTC time)', 'aucteeno' ),
				'type'              => 'datetime-local',
				'value'             => $product->get_bidding_ends_at_utc(),
				'custom_attributes' => array(
					'step' => '1',
				),
			)
		);
	}

	/**
	 * Render bidding fields.
	 *
	 * @param Product_Item $product Product object.
	 * @return void
	 */
	private function render_bidding_fields( Product_Item $product ): void {
		// Description (first in Details group).
		woocommerce_wp_textarea_input(
			array(
				'id'          => 'aucteeno_item_description',
				'label'       => __( 'Description', 'aucteeno' ),
				'description' => __( 'Detailed description of the item.', 'aucteeno' ),
				'value'       => $product->get_description(),
			)
		);

		// Asking bid.
		$asking_bid = $product->get_asking_bid();
		woocommerce_wp_text_input(
			array(
				'id'          => 'aucteeno_item_asking_bid',
				'label'       => __( 'Asking bid', 'aucteeno' ),
				'description' => __( 'Initial asking price for the item.', 'aucteeno' ),
				'type'        => 'number',
				'value'       => $asking_bid > 0 ? $asking_bid : '',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);

		// Current bid.
		$current_bid = $product->get_current_bid();
		woocommerce_wp_text_input(
			array(
				'id'          => 'aucteeno_item_current_bid',
				'label'       => __( 'Current bid', 'aucteeno' ),
				'description' => __( 'Current highest bid for the item.', 'aucteeno' ),
				'type'        => 'number',
				'value'       => $current_bid > 0 ? $current_bid : '',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);

		// Sold Price.
		$sold_price = $product->get_sold_price();
		woocommerce_wp_text_input(
			array(
				'id'          => 'aucteeno_item_sold_price',
				'label'       => __( 'Sold Price', 'aucteeno' ),
				'description' => __( 'Final sale price if the item has been sold.', 'aucteeno' ),
				'type'        => 'number',
				'value'       => $sold_price > 0 ? $sold_price : '',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);

		// Sold At (Local time).
		woocommerce_wp_text_input(
			array(
				'id'                => 'aucteeno_item_sold_at_local',
				'label'             => __( 'Sold At (Local time)', 'aucteeno' ),
				'type'              => 'datetime-local',
				'value'             => $product->get_sold_at_local(),
				'custom_attributes' => array(
					'step' => '1',
				),
			)
		);

		// Sold At (UTC time).
		woocommerce_wp_text_input(
			array(
				'id'                => 'aucteeno_item_sold_at_utc',
				'label'             => __( 'Sold At (UTC time)', 'aucteeno' ),
				'type'              => 'datetime-local',
				'value'             => $product->get_sold_at_utc(),
				'custom_attributes' => array(
					'step' => '1',
				),
			)
		);
	}


	/**
	 * Save meta fields.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function process_product_object( mixed $product ): void {
        if ( ! ( $product instanceof Product_Item ) ) {
            return;
        }

		// Save parent auction (mandatory).
		if ( isset( $_POST['aucteeno_item_parent_auction_id'] ) ) {
			$parent_id = absint( $_POST['aucteeno_item_parent_auction_id'] );
			if ( $parent_id > 0 ) {
				$product->set_auction_id( $parent_id );
			}
		}

		// Save bidding start datetime (prioritize local if provided, otherwise use UTC).
		if ( isset( $_POST['aucteeno_item_bidding_starts_at_local'] ) && ! empty( $_POST['aucteeno_item_bidding_starts_at_local'] ) ) {
			$datetime = sanitize_text_field( wp_unslash( $_POST['aucteeno_item_bidding_starts_at_local'] ) );
			$product->set_bidding_starts_at_local( $datetime );
		} elseif ( isset( $_POST['aucteeno_item_bidding_starts_at_utc'] ) && ! empty( $_POST['aucteeno_item_bidding_starts_at_utc'] ) ) {
			$datetime = sanitize_text_field( wp_unslash( $_POST['aucteeno_item_bidding_starts_at_utc'] ) );
			$product->set_bidding_starts_at_utc( $datetime );
		}

		// Save bidding end datetime (prioritize local if provided, otherwise use UTC).
		if ( isset( $_POST['aucteeno_item_bidding_ends_at_local'] ) && ! empty( $_POST['aucteeno_item_bidding_ends_at_local'] ) ) {
			$datetime = sanitize_text_field( wp_unslash( $_POST['aucteeno_item_bidding_ends_at_local'] ) );
			$product->set_bidding_ends_at_local( $datetime );
		} elseif ( isset( $_POST['aucteeno_item_bidding_ends_at_utc'] ) && ! empty( $_POST['aucteeno_item_bidding_ends_at_utc'] ) ) {
			$datetime = sanitize_text_field( wp_unslash( $_POST['aucteeno_item_bidding_ends_at_utc'] ) );
			$product->set_bidding_ends_at_utc( $datetime );
		}

		// Save bidding fields using product setters (tracks changes in extra_data).
		if ( isset( $_POST['aucteeno_item_asking_bid'] ) ) {
			$asking_bid = sanitize_text_field( wp_unslash( $_POST['aucteeno_item_asking_bid'] ) );
			$product->set_asking_bid( ! empty( $asking_bid ) ? floatval( $asking_bid ) : 0.0 );
		}

		if ( isset( $_POST['aucteeno_item_current_bid'] ) ) {
			$current_bid = sanitize_text_field( wp_unslash( $_POST['aucteeno_item_current_bid'] ) );
			$product->set_current_bid( ! empty( $current_bid ) ? floatval( $current_bid ) : 0.0 );
		}

		if ( isset( $_POST['aucteeno_item_sold_price'] ) ) {
			$sold_price = sanitize_text_field( wp_unslash( $_POST['aucteeno_item_sold_price'] ) );
			$product->set_sold_price( ! empty( $sold_price ) ? floatval( $sold_price ) : 0.0 );
		}

		if ( isset( $_POST['aucteeno_item_description'] ) ) {
			$product->set_description( sanitize_textarea_field( wp_unslash( $_POST['aucteeno_item_description'] ) ) );
		}

		// Save Sold At datetime (prioritize local if provided, otherwise use UTC).
		if ( isset( $_POST['aucteeno_item_sold_at_local'] ) && ! empty( $_POST['aucteeno_item_sold_at_local'] ) ) {
			$datetime = sanitize_text_field( wp_unslash( $_POST['aucteeno_item_sold_at_local'] ) );
			$product->set_sold_at_local( $datetime );
		} elseif ( isset( $_POST['aucteeno_item_sold_at_utc'] ) && ! empty( $_POST['aucteeno_item_sold_at_utc'] ) ) {
			$datetime = sanitize_text_field( wp_unslash( $_POST['aucteeno_item_sold_at_utc'] ) );
			$product->set_sold_at_utc( $datetime );
		}

		// Save location fields.
		// Store WooCommerce codes - datastore will handle term creation and assignment.
		$current_location = $product->get_location( 'edit' );
		
		// Build location array with all fields.
		$location = array(
			'city'        => isset( $_POST['aucteeno_item_location_city'] ) ? sanitize_text_field( wp_unslash( $_POST['aucteeno_item_location_city'] ) ) : ( $current_location['city'] ?? '' ),
			'postal_code' => isset( $_POST['aucteeno_item_location_postal_code'] ) ? sanitize_text_field( wp_unslash( $_POST['aucteeno_item_location_postal_code'] ) ) : ( $current_location['postal_code'] ?? '' ),
			'address'     => isset( $_POST['aucteeno_item_location_address'] ) ? sanitize_text_field( wp_unslash( $_POST['aucteeno_item_location_address'] ) ) : ( $current_location['address'] ?? '' ),
			'address2'    => isset( $_POST['aucteeno_item_location_address2'] ) ? sanitize_text_field( wp_unslash( $_POST['aucteeno_item_location_address2'] ) ) : ( $current_location['address2'] ?? '' ),
		);

		// Store WooCommerce codes if provided from dropdowns (datastore will convert to term IDs).
		if ( isset( $_POST['aucteeno_item_location_country'] ) && ! empty( $_POST['aucteeno_item_location_country'] ) ) {
			$location['country'] = sanitize_text_field( wp_unslash( $_POST['aucteeno_item_location_country'] ) );
		}

		if ( isset( $_POST['aucteeno_item_location_state'] ) && ! empty( $_POST['aucteeno_item_location_state'] ) ) {
			$location['subdivision'] = sanitize_text_field( wp_unslash( $_POST['aucteeno_item_location_state'] ) );
			// Also store country code if state is provided (needed for term creation).
		}

		$product->set_location( $location );

		// Save aucteeno general fields.
		if ( isset( $_POST['aucteeno_item_lot_no'] ) ) {
			$product->set_lot_no( sanitize_text_field( wp_unslash( $_POST['aucteeno_item_lot_no'] ) ) );
		}

		// Save external URL (maps to WooCommerce standard _product_url field).
		if ( isset( $_POST['aucteeno_item_external_url'] ) ) {
			$product->set_product_url( esc_url_raw( wp_unslash( $_POST['aucteeno_item_external_url'] ) ) );
		}

		if ( isset( $_POST['aucteeno_item_button_text'] ) ) {
			$product->set_button_text( sanitize_text_field( wp_unslash( $_POST['aucteeno_item_button_text'] ) ) );
		}

		// Save menu order.
		if ( isset( $_POST['menu_order'] ) ) {
			$product->set_menu_order( absint( $_POST['menu_order'] ) );
		}
	}
}
