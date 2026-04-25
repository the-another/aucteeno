<?php
/**
 * Aucteeno Field Ends At Block - Server-Side Render
 *
 * @package Aucteeno
 * @since 2.3.0
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use The_Another\Plugin\Aucteeno\Helpers\Block_Data_Helper;

$item_data = $block->context['aucteeno/item'] ?? null;
if ( ! $item_data ) {
	$item_data = Block_Data_Helper::get_item_data();
}

if ( ! $item_data ) {
	return '';
}

$timestamp = (int) ( $item_data['bidding_ends_at'] ?? 0 );
if ( $timestamp <= 0 ) {
	return '';
}

$show_label      = $attributes['showLabel'] ?? true;
$datetime_format = $attributes['dateTimeFormat'] ?? 'wp_default';
$custom_format   = $attributes['customFormat'] ?? '';

switch ( $datetime_format ) {
	case 'long':
		$php_format = 'l, j F Y \a\t H:i';
		break;
	case 'medium':
		$php_format = 'M j, Y g:i A';
		break;
	case 'custom':
		$php_format = '' !== $custom_format ? $custom_format : ( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
		break;
	case 'wp_default':
	default:
		$php_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		break;
}

// Compute bidding state from timestamps.
$bidding_starts         = (int) ( $item_data['bidding_starts_at'] ?? 0 );
$bidding_ends           = $timestamp;
$now                    = time();
$respect_bidding_status = $attributes['respectBiddingStatus'] ?? true;

if ( $now < $bidding_starts ) {
	$current_state = 'upcoming';
} elseif ( $bidding_ends > 0 && $now >= $bidding_ends ) {
	$current_state = 'expired';
} else {
	$current_state = 'running';
}

if ( $respect_bidding_status ) {
	$label_text = match ( $current_state ) {
		'upcoming' => $attributes['labelUpcoming'] ?? __( 'Bidding closes at', 'aucteeno' ),
		'running'  => $attributes['labelRunning'] ?? __( 'Bidding closes at', 'aucteeno' ),
		default    => $attributes['labelExpired'] ?? __( 'Bidding closed at', 'aucteeno' ),
	};
} else {
	$label_text = $attributes['label'] ?? __( 'Bidding closes at', 'aucteeno' );
}

$formatted = wp_date( $php_format, $timestamp );

$wrapper_attributes = get_block_wrapper_attributes();

ob_start();
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<p class="aucteeno-field-ends-at">
		<?php if ( $show_label ) : ?>
			<span class="aucteeno-field-ends-at__label"><?php echo esc_html( $label_text ); ?></span>
		<?php endif; ?>
		<time
			class="aucteeno-field-ends-at__value"
			data-aucteeno-datetime
			data-timestamp="<?php echo esc_attr( (string) $timestamp ); ?>"
			data-format="<?php echo esc_attr( $datetime_format ); ?>"
			data-custom-format="<?php echo esc_attr( $custom_format ); ?>"
			datetime="<?php echo esc_attr( gmdate( 'c', $timestamp ) ); ?>"
		><?php echo esc_html( $formatted ); ?></time>
	</p>
</div>
<?php
echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
