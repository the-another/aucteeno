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

/**
 * Filters the label shown beside the bidding end time.
 *
 * Lets a consuming plugin state something the block cannot know on its own —
 * for example that an auction closing its lots on a stagger has already begun
 * closing, when this block's own timestamp is the last lot's.
 *
 * Note this block renders once and never re-evaluates: `view.js` only hydrates
 * the timestamp to local time a single time. A consumer that varies the label
 * by wall-clock must therefore do its own time comparison, and the label can be
 * up to one page-cache lifetime stale.
 *
 * A non-scalar return (array, object, null) is discarded and the computed
 * label stands; only string/int/float/bool are accepted.
 *
 * @since 1.9.0
 *
 * @param string $label_text    The computed label.
 * @param array  $item_data     Item context data.
 * @param array  $attributes    Block attributes.
 * @param string $current_state Computed state: upcoming, running or expired.
 */
$filtered   = apply_filters( 'aucteeno_field_ends_at_label', $label_text, $item_data, $attributes, $current_state );
$label_text = is_scalar( $filtered ) ? (string) $filtered : $label_text;

$formatted            = wp_date( $php_format, $timestamp );
$unfiltered_formatted = $formatted;

/**
 * Filters the formatted value shown beside the label.
 *
 * A filtered value is server-rendered and is NOT hydrated to the visitor's
 * local timezone: view.js's hydrate() skips any <time> element carrying
 * data-aucteeno-datetime-hydrated="true", which this block sets below
 * whenever the filter actually changes the value. Without that flag, the
 * plain WordPress-timezone date would silently replace a consumer-supplied
 * value a few hundred milliseconds after paint - the exact load-flash this
 * filter exists to let a consumer avoid.
 *
 * A consumer supplying a value therefore owns both its timezone
 * presentation and its staleness: this block still renders once and never
 * re-evaluates, so a value that varies by wall-clock (e.g. showing elapsed
 * time once bidding has passed this timestamp) must do its own time
 * comparison, and can be up to one page-cache lifetime stale.
 *
 * @since 1.9.0
 * @param string $formatted     The formatted date string.
 * @param array  $item_data     Item context data.
 * @param array  $attributes    Block attributes.
 * @param string $current_state Computed state: upcoming, running or expired.
 */
$filtered_value = apply_filters( 'aucteeno_field_ends_at_value', $formatted, $item_data, $attributes, $current_state );
$formatted      = is_scalar( $filtered_value ) ? (string) $filtered_value : $formatted;

// Only a filter that actually changed the value opts this element out of
// client-side hydration - an unfiltered block, or one whose filter returned
// the same string, still hydrates to local time exactly as before.
$value_overridden   = $formatted !== $unfiltered_formatted;
$hydrated_attribute = $value_overridden ? ' data-aucteeno-datetime-hydrated="true"' : '';

$orientation        = $attributes['orientation'] ?? 'column';
$wrapper_attributes = get_block_wrapper_attributes(
	array( 'class' => 'is-orientation-' . sanitize_html_class( $orientation ) )
);

ob_start();
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<dl>
		<?php if ( $show_label ) : ?>
			<dt class="wp-block-aucteeno-field-ends-at__label"><?php echo esc_html( $label_text ); ?></dt>
		<?php endif; ?>
		<dd class="wp-block-aucteeno-field-ends-at__value">
			<time
				data-aucteeno-datetime
				data-timestamp="<?php echo esc_attr( (string) $timestamp ); ?>"
				data-format="<?php echo esc_attr( $datetime_format ); ?>"
				data-custom-format="<?php echo esc_attr( $custom_format ); ?>"
				datetime="<?php echo esc_attr( gmdate( 'c', $timestamp ) ); ?>"<?php echo $hydrated_attribute; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static string built above from a boolean, no user input. ?>
			><?php echo esc_html( $formatted ); ?></time>
		</dd>
	</dl>
</div>
<?php
echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
