<?php
/**
 * Aucteeno Field Location Block - Server-Side Render
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$item_data = $block->context['aucteeno/item'] ?? null;
if ( ! $item_data ) {
	return '';
}

$show_icon    = $attributes['showIcon'] ?? true;
$format       = $attributes['format'] ?? 'city_country';
$city         = $item_data['location_city'] ?? '';
$subdivision  = $item_data['location_subdivision'] ?? '';
$country      = $item_data['location_country'] ?? '';

// Format location display.
$parts = array();
switch ( $format ) {
	case 'city_only':
		if ( $city ) $parts[] = $city;
		break;
	case 'country_only':
		if ( $country ) $parts[] = $country;
		break;
	case 'city_subdivision':
		if ( $city ) $parts[] = $city;
		if ( $subdivision ) $parts[] = $subdivision;
		break;
	case 'city_country':
	default:
		if ( $city ) $parts[] = $city;
		if ( $country ) $parts[] = $country;
		break;
}

$location_text = implode( ', ', $parts );
if ( empty( $location_text ) ) {
	return '';
}

$wrapper_classes = 'aucteeno-field-location';
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $wrapper_classes ) );

ob_start();
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $show_icon ) : ?>
		<span class="aucteeno-field-location__icon" aria-hidden="true">📍</span>
	<?php endif; ?>
	<span class="aucteeno-field-location__text"><?php echo esc_html( $location_text ); ?></span>
</div>
<?php
echo ob_get_clean();
