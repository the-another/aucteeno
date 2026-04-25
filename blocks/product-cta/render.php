<?php
/**
 * Aucteeno Product CTA Block - Server-side rendering.
 *
 * @package Aucteeno
 * @since 1.5.0
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'aucteeno_product_cta_process_url' ) ) {
	/**
	 * Process item CTA URL with sanitization and UTM parameters.
	 *
	 * @param string $url Raw product URL.
	 * @return string Processed URL with UTM parameters.
	 */
	function aucteeno_product_cta_process_url( string $url ): string {
		$parsed_url      = wp_parse_url( $url );
		$existing_params = array();

		if ( isset( $parsed_url['query'] ) ) {
			parse_str( $parsed_url['query'], $existing_params );
		}

		$base_url = '';
		if ( isset( $parsed_url['scheme'] ) ) {
			$base_url .= $parsed_url['scheme'] . '://';
		}
		if ( isset( $parsed_url['host'] ) ) {
			$base_url .= $parsed_url['host'];
		}
		if ( isset( $parsed_url['port'] ) ) {
			$base_url .= ':' . $parsed_url['port'];
		}
		if ( isset( $parsed_url['path'] ) ) {
			$base_url .= $parsed_url['path'];
		}

		$base_url = esc_url_raw( $base_url );
		$base_url = preg_replace( '#([^:])//+#', '$1/', $base_url );

		$utm_params = array(
			'utm_source' => 'aucteeno',
			'utm_medium' => 'syndication',
		);

		$all_params = array_merge( $existing_params, $utm_params );

		return add_query_arg( $all_params, $base_url );
	}
}

// Resolve product: aucteeno/item context → postId context → global.
$aucteeno_product    = null;
$aucteeno_product_id = 0;

$aucteeno_item_data = $block->context['aucteeno/item'] ?? null;
if ( $aucteeno_item_data && isset( $aucteeno_item_data['id'] ) ) {
	$aucteeno_product_id = absint( $aucteeno_item_data['id'] );
	$aucteeno_product    = wc_get_product( $aucteeno_product_id );
}

if ( ! $aucteeno_product instanceof \WC_Product ) {
	$aucteeno_post_id = isset( $block->context['postId'] ) ? absint( $block->context['postId'] ) : 0;
	if ( $aucteeno_post_id > 0 ) {
		$aucteeno_product = wc_get_product( $aucteeno_post_id );
	}
}

if ( ! $aucteeno_product instanceof \WC_Product ) {
	global $product;
	$aucteeno_product = $product;
}

if ( ! $aucteeno_product instanceof \WC_Product ) {
	return '';
}

// Only render for aucteeno-ext-item products.
if ( 'aucteeno-ext-item' !== $aucteeno_product->get_type() ) {
	return '';
}

$aucteeno_show_bidding  = isset( $attributes['showBiddingButton'] ) ? (bool) $attributes['showBiddingButton'] : true;
$aucteeno_show_wishlist = isset( $attributes['showWishlistButton'] ) ? (bool) $attributes['showWishlistButton'] : true;
$aucteeno_bidding_text  = isset( $attributes['biddingButtonText'] ) ? sanitize_text_field( $attributes['biddingButtonText'] ) : __( 'View Bidding Page', 'aucteeno' );
$aucteeno_wishlist_text = isset( $attributes['wishlistButtonText'] ) ? sanitize_text_field( $attributes['wishlistButtonText'] ) : __( 'Add to Watchlist', 'aucteeno' );
$aucteeno_bidding_icon  = isset( $attributes['biddingButtonIcon'] ) ? sanitize_text_field( $attributes['biddingButtonIcon'] ) : 'none';
$aucteeno_wishlist_icon = isset( $attributes['wishlistButtonIcon'] ) ? sanitize_text_field( $attributes['wishlistButtonIcon'] ) : 'none';
$aucteeno_layout        = isset( $attributes['layout'] ) ? sanitize_text_field( $attributes['layout'] ) : 'horizontal';
$aucteeno_alignment     = isset( $attributes['buttonAlignment'] ) ? sanitize_text_field( $attributes['buttonAlignment'] ) : 'center';

if ( ! $aucteeno_show_bidding && ! $aucteeno_show_wishlist ) {
	return '';
}

$aucteeno_product_url = $aucteeno_product->get_product_url();
if ( empty( $aucteeno_product_url ) && $aucteeno_show_bidding ) {
	$aucteeno_show_bidding = false;
	if ( ! $aucteeno_show_wishlist ) {
		return '';
	}
}

$aucteeno_form_action_url   = '';
$aucteeno_form_query_params = array();
if ( $aucteeno_show_bidding ) {
	$aucteeno_processed_url = aucteeno_product_cta_process_url( $aucteeno_product_url );
	$aucteeno_parsed_url    = wp_parse_url( $aucteeno_processed_url );

	if ( isset( $aucteeno_parsed_url['scheme'] ) ) {
		$aucteeno_form_action_url .= $aucteeno_parsed_url['scheme'] . '://';
	}
	if ( isset( $aucteeno_parsed_url['host'] ) ) {
		$aucteeno_form_action_url .= $aucteeno_parsed_url['host'];
	}
	if ( isset( $aucteeno_parsed_url['port'] ) ) {
		$aucteeno_form_action_url .= ':' . $aucteeno_parsed_url['port'];
	}
	if ( isset( $aucteeno_parsed_url['path'] ) ) {
		$aucteeno_form_action_url .= $aucteeno_parsed_url['path'];
	}

	if ( isset( $aucteeno_parsed_url['query'] ) ) {
		parse_str( $aucteeno_parsed_url['query'], $aucteeno_form_query_params );
	}
}

$aucteeno_wrapper_classes = array(
	'is-layout-' . $aucteeno_layout,
	'is-content-justification-' . $aucteeno_alignment,
);

$aucteeno_wrapper_attributes = get_block_wrapper_attributes(
	array( 'class' => implode( ' ', $aucteeno_wrapper_classes ) )
);

require_once AUCTEENO_PLUGIN_DIR . 'includes/blocks/class-product-cta-renderer.php';

$aucteeno_buttons = array();

if ( $aucteeno_show_bidding ) {
	$aucteeno_buttons[] = array(
		'id'       => 'bidding',
		'wrapper'  => 'form',
		'text'     => $aucteeno_bidding_text,
		'classes'  => array( 'is-bidding' ),
		'icon'     => $aucteeno_bidding_icon,
		'attrs'    => array( 'type' => 'submit' ),
		'form'     => array(
			'action'        => $aucteeno_form_action_url,
			'method'        => 'get',
			'target'        => '_blank',
			'rel'           => 'noopener noreferrer',
			'classes'       => array( 'is-bidding' ),
			'hidden_fields' => $aucteeno_form_query_params,
		),
		'priority' => 10,
	);
}

/**
 * Filters the list of CTA buttons rendered after bidding.
 *
 * @param array      $buttons     Ordered list of button structs.
 * @param WC_Product $product     The product being rendered.
 * @param array      $attributes  Block attributes.
 */
$aucteeno_extension_buttons = apply_filters(
	'aucteeno_product_cta_buttons',
	array(),
	$aucteeno_product,
	$attributes
);

$aucteeno_buttons      = array_merge( $aucteeno_buttons, (array) $aucteeno_extension_buttons );
$aucteeno_buttons_html = \The_Another\Plugin\Aucteeno\Blocks\Product_Cta_Renderer::render_collection( $aucteeno_buttons );

if ( '' === $aucteeno_buttons_html ) {
	return '';
}
?>
<div <?php echo $aucteeno_wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="wp-block-aucteeno-product-cta__buttons">
		<?php echo $aucteeno_buttons_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside renderer ?>
	</div>
</div>
