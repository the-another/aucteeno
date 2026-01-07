<?php
/**
 * Auction Listing Block - Server-Side Render
 *
 * Renders the auction listing block with SSR for the first page.
 *
 * @package Aucteeno
 * @since 1.0.0
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (empty for dynamic blocks).
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use TheAnother\Plugin\Aucteeno\Fragment_Renderer;

// Parse attributes with defaults.
$attrs = wp_parse_args(
	$attributes,
	array(
		'perPage'               => 10,
		'sort'                  => 'ending_soon',
		'locationFilterEnabled' => true,
		'defaultLocations'      => array(),
		'columnsDesktop'        => 5,
		'columnsTablet'         => 2,
		'columnsMobile'         => 1,
		'showSourceLabel'       => true,
		'showImage'             => true,
		'emptyStateText'        => 'No auctions found.',
	)
);

// Generate unique instance ID.
$instance = wp_unique_id( 'aucteeno-auctions-' );

// Get SSR first page via Fragment Renderer.
$result = Fragment_Renderer::auctions(
	array(
		'location' => $attrs['defaultLocations'],
		'page'     => 1,
		'per_page' => (int) $attrs['perPage'],
		'sort'     => (string) $attrs['sort'],
	)
);

// Build state for frontend JS.
$state = array(
	'endpoint'       => rest_url( 'aucteeno/v1/auctions' ),
	'perPage'        => (int) $attrs['perPage'],
	'sort'           => (string) $attrs['sort'],
	'locations'      => array_values( (array) $attrs['defaultLocations'] ),
	'page'           => (int) ( $result['page'] ?? 1 ),
	'pages'          => (int) ( $result['pages'] ?? 1 ),
	'isLoading'      => false,
	'emptyStateText' => (string) $attrs['emptyStateText'],
);

// Build wrapper attributes.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                  => 'aucteeno-listing aucteeno-auction-listing',
		'data-wp-interactive'    => 'aucteeno/listings',
		'data-aucteeno-instance' => $instance,
		'data-aucteeno-kind'     => 'auctions',
		'data-aucteeno-state'    => wp_json_encode( $state ),
	)
);

// Build CSS custom properties for grid columns.
$style_vars = sprintf(
	'--aucteeno-cols-desktop:%d;--aucteeno-cols-tablet:%d;--aucteeno-cols-mobile:%d;',
	(int) $attrs['columnsDesktop'],
	(int) $attrs['columnsTablet'],
	(int) $attrs['columnsMobile']
);

// Get location terms for filter dropdown.
$location_terms = array();
if ( $attrs['locationFilterEnabled'] ) {
	$location_terms = Fragment_Renderer::get_location_terms();
}
?>
<section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( ! empty( $attrs['locationFilterEnabled'] ) ) : ?>
		<div class="aucteeno-listing__filters">
			<label class="aucteeno-filter">
				<span class="aucteeno-filter__label"><?php esc_html_e( 'Location', 'aucteeno' ); ?></span>
				<select class="aucteeno-filter__select"
						data-wp-on--change="actions.onLocationChange"
						data-aucteeno-role="location-select">
					<option value=""><?php esc_html_e( 'All locations', 'aucteeno' ); ?></option>
					<?php
					foreach ( $location_terms as $term ) :
						$indent = $term['parent'] > 0 ? '— ' : '';
						?>
						<option value="<?php echo esc_attr( $term['slug'] ); ?>">
							<?php echo esc_html( $indent . $term['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>
	<?php endif; ?>

	<div class="aucteeno-grid" data-aucteeno-role="grid" style="<?php echo esc_attr( $style_vars ); ?>">
		<?php
		if ( ! empty( $result['html'] ) ) {
			echo $result['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped in template parts.
		} else {
			echo '<div class="aucteeno-empty">' . esc_html( $attrs['emptyStateText'] ) . '</div>';
		}
		?>
	</div>

	<div class="aucteeno-infinite-sentinel" data-aucteeno-role="sentinel" aria-hidden="true"></div>
	<div class="aucteeno-loading" data-aucteeno-role="loading" hidden><?php esc_html_e( 'Loading...', 'aucteeno' ); ?></div>
</section>
