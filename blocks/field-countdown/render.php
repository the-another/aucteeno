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

use The_Another\Plugin\Aucteeno\Helpers\Block_Data_Helper;

// Get item data from context or current post.
$item_data = $block->context['aucteeno/item'] ?? null;
if ( ! $item_data ) {
	$item_data = Block_Data_Helper::get_item_data();
}

if ( ! $item_data ) {
	return '';
}

$show_label             = $attributes['showLabel'] ?? true;
$date_format            = $attributes['dateFormat'] ?? 'default';
$target_date            = $attributes['targetDate'] ?? 'auto';
$respect_bidding_status = $attributes['respectBiddingStatus'] ?? true;
$single_label           = $attributes['label'] ?? __( 'Bidding ends in', 'aucteeno' );
$label_upcoming_time    = $attributes['labelUpcomingTime'] ?? __( 'Bidding starts in', 'aucteeno' );
$label_upcoming_date    = $attributes['labelUpcomingDate'] ?? __( 'Bidding starts on', 'aucteeno' );
$label_running_time     = $attributes['labelRunningTime'] ?? __( 'Bidding ends in', 'aucteeno' );
$label_running_date     = $attributes['labelRunningDate'] ?? __( 'Bidding ends on', 'aucteeno' );
$label_expired          = $attributes['labelExpired'] ?? __( 'Bidding ended', 'aucteeno' );
$bidding_starts         = $item_data['bidding_starts_at'] ?? 0;
$bidding_ends           = $item_data['bidding_ends_at'] ?? 0;

// aucteeno_format_date() is defined once, in includes/functions.php, which
// the plugin bootstrap loads (aucteeno.php) before any block can render.

// Calculate current state based on UTC timestamps.
// time() returns current Unix timestamp (UTC-based), ensuring timezone-agnostic comparisons.
$now = time();
if ( $now < $bidding_starts ) {
	$current_state = 'upcoming';
	$auto_target   = $bidding_starts;
} elseif ( $now >= $bidding_starts && $now < $bidding_ends ) {
	$current_state = 'running';
	$auto_target   = $bidding_ends;
} else {
	$current_state = 'expired';
	$auto_target   = $bidding_ends;
}

// Resolve target timestamp from picker.
if ( 'starts_at' === $target_date ) {
	$timestamp = $bidding_starts;
} elseif ( 'ends_at' === $target_date ) {
	$timestamp = $bidding_ends;
} else {
	$timestamp = $auto_target;
}

// Effective state: when targetDate is forced, derive from now-vs-target;
// otherwise mirror the auction's actual state.
if ( 'starts_at' === $target_date ) {
	$effective_state = ( $now < $bidding_starts ) ? 'upcoming' : 'expired';
} elseif ( 'ends_at' === $target_date ) {
	$effective_state = ( $now < $bidding_ends ) ? 'running' : 'expired';
} else {
	$effective_state = $current_state;
}

// Calculate countdown display.
$diff            = $timestamp - $now;
$is_showing_date = false;

// For expired items, calculate elapsed time.
if ( 'expired' === $effective_state ) {
	$elapsed_result  = aucteeno_format_elapsed( (int) abs( $diff ), $timestamp, $date_format );
	$display_value   = $elapsed_result['display_value'];
	$is_showing_date = $elapsed_result['is_showing_date'];
} elseif ( $diff <= 0 && 'upcoming' !== $effective_state ) {
	$display_value = __( 'Ended', 'aucteeno' );
} elseif ( $diff < 3600 ) {
	// Less than 1 hour - show minutes and seconds.
	$minutes = floor( $diff / 60 );
	$seconds = $diff % 60;
	$parts   = array();

	if ( $minutes > 0 ) {
		/* translators: %d: number of minutes */
		$parts[] = sprintf( _n( '%d minute', '%d minutes', $minutes, 'aucteeno' ), $minutes );
	}

	/* translators: %d: number of seconds */
	$parts[] = sprintf( _n( '%d second', '%d seconds', $seconds, 'aucteeno' ), $seconds );

	$display_value = implode( ' ', $parts );
} elseif ( $diff < 86400 ) {
	// Less than 1 day - show hours.
	$hours = floor( $diff / 3600 );
	/* translators: %d: number of hours */
	$display_value = sprintf( _n( '%d hour', '%d hours', $hours, 'aucteeno' ), $hours );
} elseif ( $diff < 604800 ) {
	// Less than 1 week - show days.
	$days = floor( $diff / 86400 );
	/* translators: %d: number of days */
	$display_value = sprintf( _n( '%d day', '%d days', $days, 'aucteeno' ), $days );
} else {
	// More than 1 week - show date.
	$display_value   = aucteeno_format_date( $timestamp, $date_format );
	$is_showing_date = true;
}

/**
 * Filters the countdown's displayed value with a consumer-supplied override.
 *
 * A consumer returns a value plus the window it applies to. The window — rather
 * than a bare replacement string — is what lets view.js re-evaluate the override
 * on every tick without calling back into PHP, so the override survives the
 * per-second re-render instead of being clobbered by it.
 *
 * Validation here mirrors applyOverride() in src/countdown-utils.js. Keep them
 * in step.
 *
 * @since 1.9.0
 *
 * @param array  $override        Empty array, or array{ value: string, from: int, until: int, state?: string, label?: string, since?: int }.
 * @param array  $item_data       Item context data.
 * @param array  $attributes      Block attributes.
 * @param string $effective_state Computed effective state (upcoming|running|expired).
 */
$override = apply_filters( 'aucteeno_field_countdown_override', array(), $item_data, $attributes, $effective_state );

$override_value = '';
$override_from  = 0;
$override_until = 0;
$override_state = '';
$override_label = '';
$override_since = 0;

if ( is_array( $override ) && isset( $override['value'] ) && is_string( $override['value'] ) && '' !== $override['value'] ) {
	$candidate_from  = is_numeric( $override['from'] ?? null ) ? (int) $override['from'] : 0;
	$candidate_until = is_numeric( $override['until'] ?? null ) ? (int) $override['until'] : 0;

	if ( $candidate_from > 0 && $candidate_until > 0 && $candidate_from < $candidate_until ) {
		$override_value = $override['value'];
		$override_from  = $candidate_from;
		$override_until = $candidate_until;

		// Interpolated into a class name below, so it must be sanitised here.
		$raw_state      = $override['state'] ?? '';
		$override_state = is_string( $raw_state ) ? sanitize_html_class( $raw_state ) : '';

		// sanitize_html_class() ends in a filter a site can hook, and the client
		// re-checks this token before handing it to classList. Keep both sides
		// agreeing on what a valid suffix is.
		if ( ! preg_match( '/^[A-Za-z0-9_-]*$/', $override_state ) ) {
			$override_state = '';
		}

		// A non-string label is ignored rather than rendered, same as value.
		$raw_label      = $override['label'] ?? '';
		$override_label = is_string( $raw_label ) ? $raw_label : '';

		// Same is_numeric guard the from/until bounds use above.
		$override_since = is_numeric( $override['since'] ?? null ) ? (int) $override['since'] : 0;
	}
}

// A starts_at pin is an explicit request to count to the start, so an override
// must not hijack it. An ends_at pin only selects which timestamp to count to —
// the override's window sits entirely inside that mode's running span.
// Inclusive-exclusive, so a window can end exactly where 'expired' begins.
$override_active = '' !== $override_value
	&& 'starts_at' !== $target_date
	&& $now >= $override_from
	&& $now < $override_until;

if ( $override_active ) {
	$display_value   = $override_value;
	$is_showing_date = false;

	// `since` only drives the display while the override itself is active, and
	// only once it has actually started - a `since` still in the future would
	// otherwise render a negative elapsed time.
	if ( $override_since > 0 && $now >= $override_since ) {
		$elapsed_result  = aucteeno_format_elapsed( (int) ( $now - $override_since ), $override_since, $date_format );
		$display_value   = $elapsed_result['display_value'];
		$is_showing_date = $elapsed_result['is_showing_date'];
	}
}

// Determine label based on state and whether showing date. An active
// override's own label, when supplied, wins outright.
if ( $override_active && '' !== $override_label ) {
	$label = $override_label;
} elseif ( ! $respect_bidding_status ) {
	$label = $single_label;
} elseif ( 'expired' === $effective_state ) {
	$label = $label_expired;
} elseif ( $is_showing_date ) {
	$label = ( 'upcoming' === $effective_state ) ? $label_upcoming_date : $label_running_date;
} else {
	$label = ( 'upcoming' === $effective_state ) ? $label_upcoming_time : $label_running_time;
}

$state_class = ( $override_active && '' !== $override_state ) ? $override_state : $current_state;

$wrapper_classes    = 'aucteeno-field-countdown';
$wrapper_classes   .= ' aucteeno-field-countdown--' . $state_class;
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $wrapper_classes ) );

// Emitted only when a well-formed override exists and targetDate isn't
// pinned to starts_at — a block with no consumer registered must render
// byte-identically to before this filter existed, and both sides already
// ignore the override entirely under a starts_at pin (see $override_active
// above / view.js's own targetDate check), so the wire shouldn't carry data
// neither side will ever look at. Emitted even when the window has not
// opened yet, so the client can apply it on the right tick without a reload.
$override_attributes = '';
if ( '' !== $override_value && 'starts_at' !== $target_date ) {
	$override_attributes = sprintf(
		' data-override-value="%s" data-override-from="%d" data-override-until="%d" data-override-state="%s"',
		esc_attr( $override_value ),
		$override_from,
		$override_until,
		esc_attr( $override_state )
	);

	// label/since are each independently optional, so each is only emitted
	// when the consumer actually supplied it.
	if ( '' !== $override_label ) {
		$override_attributes .= sprintf( ' data-override-label="%s"', esc_attr( $override_label ) );
	}

	if ( $override_since > 0 ) {
		$override_attributes .= sprintf( ' data-override-since="%d"', $override_since );
	}
}

ob_start();
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-aucteeno-countdown data-starts-at="<?php echo esc_attr( $bidding_starts ); ?>" data-ends-at="<?php echo esc_attr( $bidding_ends ); ?>" data-current-state="<?php echo esc_attr( $current_state ); ?>" data-date-format="<?php echo esc_attr( $date_format ); ?>" data-target-date="<?php echo esc_attr( $target_date ); ?>" data-respect-bidding-status="<?php echo $respect_bidding_status ? '1' : '0'; ?>" data-label="<?php echo esc_attr( $single_label ); ?>" data-label-upcoming-time="<?php echo esc_attr( $label_upcoming_time ); ?>" data-label-upcoming-date="<?php echo esc_attr( $label_upcoming_date ); ?>" data-label-running-time="<?php echo esc_attr( $label_running_time ); ?>" data-label-running-date="<?php echo esc_attr( $label_running_date ); ?>" data-label-expired="<?php echo esc_attr( $label_expired ); ?>"<?php echo $override_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr()'d parts above. ?>>
	<?php if ( $show_label ) : ?>
		<span class="aucteeno-field-countdown__label"><?php echo esc_html( $label ); ?></span>
	<?php endif; ?>
	<span class="aucteeno-field-countdown__value"><?php echo esc_html( $display_value ); ?></span>
</div>
<?php
echo ob_get_clean();
