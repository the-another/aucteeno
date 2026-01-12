<?php
/**
 * Aucteeno Field Countdown Block - Server-Side Render
 *
 * @package Aucteeno
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$item_data = $block->context['aucteeno/item'] ?? null;
if ( ! $item_data ) {
	return '';
}

$show_label      = $attributes['showLabel'] ?? true;
$bidding_status  = $item_data['bidding_status'] ?? 10;
$bidding_starts  = $item_data['bidding_starts_at'] ?? 0;
$bidding_ends    = $item_data['bidding_ends_at'] ?? 0;

// Determine which timestamp to use based on status.
// 20 = upcoming: use starts_at
// 10 = running, 30 = expired: use ends_at
$is_upcoming = ( 20 === (int) $bidding_status );
$timestamp   = $is_upcoming ? $bidding_starts : $bidding_ends;

// Calculate countdown display.
$now  = time();
$diff = $timestamp - $now;

// Determine label.
if ( $is_upcoming ) {
	$label = __( 'Bidding starts in', 'aucteeno' );
} elseif ( 30 === (int) $bidding_status ) {
	$label = __( 'Bidding ended', 'aucteeno' );
} else {
	$label = __( 'Bidding ends in', 'aucteeno' );
}

// Calculate display value using smart scaling.
$display_value = '';
if ( 30 === (int) $bidding_status ) {
	// Expired - show the end date.
	$display_value = wp_date( get_option( 'date_format' ), $timestamp );
} elseif ( $diff <= 0 && ! $is_upcoming ) {
	$display_value = __( 'Ended', 'aucteeno' );
} elseif ( $diff < 60 ) {
	// Less than 1 minute - show seconds.
	$display_value = sprintf( '%ds', max( 0, $diff ) );
} elseif ( $diff < 3600 ) {
	// Less than 1 hour - show minutes.
	$display_value = sprintf( '%dm', floor( $diff / 60 ) );
} elseif ( $diff < 86400 ) {
	// Less than 1 day - show hours.
	$display_value = sprintf( '%dh', floor( $diff / 3600 ) );
} elseif ( $diff < 604800 ) {
	// Less than 1 week - show days.
	$display_value = sprintf( '%dd', floor( $diff / 86400 ) );
} else {
	// More than 1 week - show date.
	$display_value = wp_date( get_option( 'date_format' ), $timestamp );
}

// Convert timestamp to ISO for JS.
$iso_timestamp = gmdate( 'c', $timestamp );

$wrapper_classes = 'aucteeno-field-countdown';
$wrapper_classes .= ' aucteeno-field-countdown--' . ( $is_upcoming ? 'upcoming' : ( 30 === (int) $bidding_status ? 'expired' : 'running' ) );
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $wrapper_classes ) );

ob_start();
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-aucteeno-countdown data-timestamp="<?php echo esc_attr( $timestamp ); ?>" data-iso="<?php echo esc_attr( $iso_timestamp ); ?>" data-status="<?php echo esc_attr( $bidding_status ); ?>">
	<?php if ( $show_label ) : ?>
		<span class="aucteeno-field-countdown__label"><?php echo esc_html( $label ); ?></span>
	<?php endif; ?>
	<span class="aucteeno-field-countdown__value"><?php echo esc_html( $display_value ); ?></span>
</div>
<?php
echo ob_get_clean();
