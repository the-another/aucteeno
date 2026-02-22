<?php
/**
 * Auction Meta Fields Class
 *
 * Handles custom meta fields for auction products.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace The_Another\Plugin\Aucteeno\Admin;

use The_Another\Plugin\Aucteeno\Helpers\Location_Helper;
use The_Another\Plugin\Aucteeno\Hook_Manager;
use The_Another\Plugin\Aucteeno\Product_Types\Product_Auction;

/**
 * Class Meta_Fields_Auction
 *
 * Manages meta fields for auction products.
 */
class Custom_Fields_Auction {


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
		// Register on woocommerce_admin_process_product_object hook.
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
			function ( $post_id, $post_type ) {
				$this->render_link_tab( $post_id, $post_type );
			},
			10,
			2
		);
		$this->hook_manager->register_action(
			'aucteeno_product_tab_location',
			function ( $post_id, $post_type ) {
				$this->render_location_tab( $post_id, $post_type );
			},
			10,
			2
		);
		$this->hook_manager->register_action(
			'aucteeno_product_tab_times',
			function ( $post_id, $post_type ) {
				$this->render_times_tab( $post_id, $post_type );
			},
			10,
			2
		);
		$this->hook_manager->register_action(
			'aucteeno_product_tab_details',
			function ( $post_id, $post_type ) {
				$this->render_details_tab( $post_id, $post_type );
			},
			10,
			2
		);
	}

	/**
	 * Render Link tab content.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function render_link_tab( int $post_id, string $post_type = '' ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by hook signature.
		global $post;

		// Only render for auction products.
		if ( ! $post_id ) {
			$post_id = $post ? $post->ID : 0;
		}

		// Get product object to use getters (create empty instance if needed for new products).
		$product = wc_get_product( $post_id );
		if ( ! $product || ! ( $product instanceof Product_Auction ) ) {
			$product = new Product_Auction( 0 );
		}

		// Always render content - visibility is controlled by show_if_aucteeno-ext-auction CSS class.
		// WooCommerce's JavaScript will show/hide the content based on the product type selector.
		// This matches the standard WooCommerce pattern where content is always output and CSS/JS handles visibility.

		echo '<div class="show_if_aucteeno-ext-auction">';
		echo '<div class="options_group">';

		woocommerce_wp_text_input(
			array(
				'id'          => 'aucteeno_auction_external_url',
				'label'       => __( 'Auction URL', 'aucteeno' ),
				'description' => __( 'URL for the auction.', 'aucteeno' ),
				'type'        => 'url',
				'value'       => $product->get_product_url(),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => 'aucteeno_auction_button_text',
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
	 * @param int    $post_id Post ID.
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function render_location_tab( int $post_id, string $post_type = '' ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by hook signature.
		global $post;

		// Only render for auction products.
		if ( ! $post_id ) {
			$post_id = $post ? $post->ID : 0;
		}

		// Get product object to use getters (create empty instance if needed for new products).
		$product = wc_get_product( $post_id );
		if ( ! $product || ! ( $product instanceof Product_Auction ) ) {
			$product = new Product_Auction( 0 );
		}

		// Always render content - visibility is controlled by show_if_aucteeno-ext-auction CSS class.

		echo '<div class="show_if_aucteeno-ext-auction">';
		echo '<div class="options_group">';
		$this->render_location_fields( $product );
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render Times tab content.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function render_times_tab( int $post_id, string $post_type = '' ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by hook signature.
		global $post;

		// Only render for auction products.
		if ( ! $post_id ) {
			$post_id = $post ? $post->ID : 0;
		}

		// Get product object to use getters (create empty instance if needed for new products).
		$product = wc_get_product( $post_id );
		if ( ! $product || ! ( $product instanceof Product_Auction ) ) {
			$product = new Product_Auction( 0 );
		}

		// Always render content - visibility is controlled by show_if_aucteeno-ext-auction CSS class.

		echo '<div class="show_if_aucteeno-ext-auction">';
		echo '<div class="options_group">';
		$this->render_times_fields( $product );
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render Details tab content.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function render_details_tab( int $post_id, string $post_type = '' ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by hook signature.
		global $post;

		// Only render for auction products.
		if ( ! $post_id ) {
			$post_id = $post ? $post->ID : 0;
		}

		// Get product object to use getters (create empty instance if needed for new products).
		$product = wc_get_product( $post_id );
		if ( ! $product || ! ( $product instanceof Product_Auction ) ) {
			$product = new Product_Auction( 0 );
		}

		// Always render content - visibility is controlled by show_if_aucteeno-ext-auction CSS class.

		echo '<div class="show_if_aucteeno-ext-auction">';
		echo '<div class="options_group">';
		echo '<div id="aucteeno_auction_notice">';

		woocommerce_wp_textarea_input(
			array(
				'id'          => 'aucteeno_auction_notice',
				'label'       => __( 'General Auction Notice', 'aucteeno' ),
				'description' => __( 'General notice for the auction.', 'aucteeno' ),
				'value'       => $product->get_notice(),
			)
		);

		woocommerce_wp_textarea_input(
			array(
				'id'          => 'aucteeno_auction_bidding_notice',
				'label'       => __( 'Bidding Notice', 'aucteeno' ),
				'description' => __( 'Bidding-specific notice.', 'aucteeno' ),
				'value'       => $product->get_bidding_notice(),
			)
		);

		woocommerce_wp_textarea_input(
			array(
				'id'          => 'aucteeno_auction_directions',
				'label'       => __( 'Directions', 'aucteeno' ),
				'description' => __( 'Directions for the auction.', 'aucteeno' ),
				'value'       => $product->get_directions(),
			)
		);

		woocommerce_wp_textarea_input(
			array(
				'id'          => 'aucteeno_auction_terms_conditions',
				'label'       => __( 'Terms and Conditions', 'aucteeno' ),
				'description' => __( 'Auction-specific terms and conditions. Leave empty to use general terms.', 'aucteeno' ),
				'value'       => $product->get_terms_conditions(),
			)
		);

		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render times fields from database.
	 *
	 * @param Product_Auction $product Product object.
	 * @return void
	 */
	private function render_times_fields( Product_Auction $product ): void {
		// Bidding start time (local time).
		woocommerce_wp_text_input(
			array(
				'id'                => 'aucteeno_auction_bidding_starts_at_local',
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
				'id'                => 'aucteeno_auction_bidding_starts_at_utc',
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
				'id'                => 'aucteeno_auction_bidding_ends_at_local',
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
				'id'                => 'aucteeno_auction_bidding_ends_at_utc',
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
	 * Render location fields.
	 *
	 * @param Product_Auction $product Product object.
	 * @return void
	 */
	private function render_location_fields( Product_Auction $product ): void {
		$location = $product->get_location();

		// Get current country and state codes from taxonomy terms.
		$country_code = '';
		$state_code   = '';

		// Get WooCommerce countries.
		$countries = WC()->countries->get_countries();
		if ( empty( $countries ) ) {
			$countries = array();
		}

		// Country dropdown.
		woocommerce_wp_select(
			array(
				'id'                => 'aucteeno_auction_location_country',
				'label'             => __( 'Country', 'aucteeno' ),
				'value'             => $location['country'],
				'options'           => $countries,
				'wrapper_class'     => 'form-field-wide',
				'custom_attributes' => array(
					'class' => 'select short aucteeno-country-select',
				),
			)
		);

		// Get states for selected country.
		$states           = array();
		$show_state_field = false;
		if ( ! empty( $location['country'] ) ) {
			$wc_states = WC()->countries->get_states( $location['country'] );
			if ( is_array( $wc_states ) && ! empty( $wc_states ) ) {
				$states           = $wc_states;
				$show_state_field = true;
			}
		}

		// State dropdown (always create, but hide if no states for current country).
		woocommerce_wp_select(
			array(
				'id'                => 'aucteeno_auction_location_state',
				'label'             => __( 'State / Province', 'aucteeno' ),
				'value'             => $location['subdivision'],
				'options'           => $states,
				'wrapper_class'     => $show_state_field ? 'form-field-wide' : 'form-field-wide' . ( empty( $states ) ? ' aucteeno-state-field-hidden' : '' ),
				'custom_attributes' => array(
					'class'              => 'select short aucteeno-state-select',
					'data-country-field' => 'aucteeno_auction_location_country',
				),
			)
		);

		// Hide state field if no states available for current country.
		if ( ! $show_state_field ) {
			echo '<script type="text/javascript">';
			echo 'jQuery(document).ready(function($) {';
			echo '  $("#aucteeno_auction_location_state_field").hide();';
			echo '});';
			echo '</script>';
		}

		// City (first in Location group).
		woocommerce_wp_text_input(
			array(
				'id'    => 'aucteeno_auction_location_city',
				'label' => __( 'City', 'aucteeno' ),
				'value' => $location['city'] ?? '',
			)
		);

		// Postal Code.
		woocommerce_wp_text_input(
			array(
				'id'    => 'aucteeno_auction_location_postal_code',
				'label' => __( 'Postal Code', 'aucteeno' ),
				'value' => $location['postal_code'] ?? '',
			)
		);

		// Address.
		woocommerce_wp_text_input(
			array(
				'id'    => 'aucteeno_auction_location_address',
				'label' => __( 'Address', 'aucteeno' ),
				'value' => $location['address'] ?? '',
			)
		);

		// Address 2.
		woocommerce_wp_text_input(
			array(
				'id'    => 'aucteeno_auction_location_address2',
				'label' => __( 'Address 2', 'aucteeno' ),
				'value' => $location['address2'] ?? '',
			)
		);
	}


	/**
	 * Save meta fields.
	 *
	 * @param mixed $product Product object to save meta fields for.
	 * @return void
	 */
	public function process_product_object( mixed $product ): void {
		if ( ! ( $product instanceof Product_Auction ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce before this hook fires.

		// Save bidding start datetime (prioritize local if provided, otherwise use UTC).
		if ( isset( $_POST['aucteeno_auction_bidding_starts_at_local'] ) && ! empty( $_POST['aucteeno_auction_bidding_starts_at_local'] ) ) {
			$datetime = sanitize_text_field( wp_unslash( $_POST['aucteeno_auction_bidding_starts_at_local'] ) );
			$product->set_bidding_starts_at_local( $datetime );
		} elseif ( isset( $_POST['aucteeno_auction_bidding_starts_at_utc'] ) && ! empty( $_POST['aucteeno_auction_bidding_starts_at_utc'] ) ) {
			$datetime = sanitize_text_field( wp_unslash( $_POST['aucteeno_auction_bidding_starts_at_utc'] ) );
			$product->set_bidding_starts_at_utc( $datetime );
		}

		// Save bidding end datetime (prioritize local if provided, otherwise use UTC).
		if ( isset( $_POST['aucteeno_auction_bidding_ends_at_local'] ) && ! empty( $_POST['aucteeno_auction_bidding_ends_at_local'] ) ) {
			$datetime = sanitize_text_field( wp_unslash( $_POST['aucteeno_auction_bidding_ends_at_local'] ) );
			$product->set_bidding_ends_at_local( $datetime );
		} elseif ( isset( $_POST['aucteeno_auction_bidding_ends_at_utc'] ) && ! empty( $_POST['aucteeno_auction_bidding_ends_at_utc'] ) ) {
			$datetime = sanitize_text_field( wp_unslash( $_POST['aucteeno_auction_bidding_ends_at_utc'] ) );
			$product->set_bidding_ends_at_utc( $datetime );
		}

		// Save notice.
		if ( isset( $_POST['aucteeno_auction_notice'] ) ) {
			$product->set_notice( sanitize_textarea_field( wp_unslash( $_POST['aucteeno_auction_notice'] ) ) );
		}

		// Save bidding notice.
		if ( isset( $_POST['aucteeno_auction_bidding_notice'] ) ) {
			$product->set_bidding_notice( sanitize_textarea_field( wp_unslash( $_POST['aucteeno_auction_bidding_notice'] ) ) );
		}

		// Save directions.
		if ( isset( $_POST['aucteeno_auction_directions'] ) ) {
			$product->set_directions( sanitize_textarea_field( wp_unslash( $_POST['aucteeno_auction_directions'] ) ) );
		}

		// Save terms and conditions.
		if ( isset( $_POST['aucteeno_auction_terms_conditions'] ) ) {
			$product->set_terms_conditions( sanitize_textarea_field( wp_unslash( $_POST['aucteeno_auction_terms_conditions'] ) ) );
		}

		// Save location fields.
		// Store WooCommerce codes - datastore will handle term creation and assignment.
		$current_location = $product->get_location( 'edit' );

		// Build location array with all fields.
		$location = array(
			'city'        => isset( $_POST['aucteeno_auction_location_city'] ) ? sanitize_text_field( wp_unslash( $_POST['aucteeno_auction_location_city'] ) ) : ( $current_location['city'] ?? '' ),
			'postal_code' => isset( $_POST['aucteeno_auction_location_postal_code'] ) ? sanitize_text_field( wp_unslash( $_POST['aucteeno_auction_location_postal_code'] ) ) : ( $current_location['postal_code'] ?? '' ),
			'address'     => isset( $_POST['aucteeno_auction_location_address'] ) ? sanitize_text_field( wp_unslash( $_POST['aucteeno_auction_location_address'] ) ) : ( $current_location['address'] ?? '' ),
			'address2'    => isset( $_POST['aucteeno_auction_location_address2'] ) ? sanitize_text_field( wp_unslash( $_POST['aucteeno_auction_location_address2'] ) ) : ( $current_location['address2'] ?? '' ),
		);

		// Store WooCommerce codes if provided from dropdowns (datastore will convert to term IDs).
		if ( isset( $_POST['aucteeno_auction_location_country'] ) && ! empty( $_POST['aucteeno_auction_location_country'] ) ) {
			$location['country'] = sanitize_text_field( wp_unslash( $_POST['aucteeno_auction_location_country'] ) );
		}

		if ( isset( $_POST['aucteeno_auction_location_state'] ) && ! empty( $_POST['aucteeno_auction_location_state'] ) ) {
			$location['subdivision'] = sanitize_text_field( wp_unslash( $_POST['aucteeno_auction_location_state'] ) );
			// Also store country code if state is provided (needed for term creation).
		}

		$product->set_location( $location );

		// Save aucteeno general fields.
		if ( isset( $_POST['aucteeno_auction_external_url'] ) ) {
			$product->set_product_url( esc_url_raw( wp_unslash( $_POST['aucteeno_auction_external_url'] ) ) );
		}

		if ( isset( $_POST['aucteeno_auction_button_text'] ) ) {
			$product->set_button_text( sanitize_text_field( wp_unslash( $_POST['aucteeno_auction_button_text'] ) ) );
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
