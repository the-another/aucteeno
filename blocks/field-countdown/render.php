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

use TheAnother\Plugin\Aucteeno\Helpers\Block_Data_Helper;

// Get item data from context or current post.
$item_data = $block->context['aucteeno/item'] ?? null;
if ( ! $item_data ) {
	$item_data = Block_Data_Helper::get_item_data();
}

if ( ! $item_data ) {
	return '';
}

$show_label      = $attributes['showLabel'] ?? true;
$date_format     = $attributes['dateFormat'] ?? 'default';
$bidding_starts  = $item_data['bidding_starts_at'] ?? 0;
$bidding_ends    = $item_data['bidding_ends_at'] ?? 0;

if ( ! function_exists( 'aucteeno_format_date' ) ) {
	/**
	 * Format a timestamp based on the selected date format.
	 *
	 * The timestamp is a Unix timestamp (UTC-based). wp_date() automatically converts
	 * it to the WordPress timezone setting for display.
	 *
	 * @param int    $timestamp   Unix timestamp (UTC-based).
	 * @param string $date_format Date format setting.
	 * @return string Formatted date string in WordPress timezone.
	 */
	function aucteeno_format_date( $timestamp, $date_format ) {
		switch ( $date_format ) {
			case 'mdy':
				return wp_date( 'm/d/Y', $timestamp );
			case 'dmy':
				return wp_date( 'd/m/Y', $timestamp );
			case 'ymd':
				return wp_date( 'Y-m-d', $timestamp );
			case 'long':
				return wp_date( 'F j, Y', $timestamp );
			case 'long_eu':
				return wp_date( 'j F Y', $timestamp );
			case 'full':
				return wp_date( 'l, F jS Y', $timestamp );
			case 'default':
			default:
				return wp_date( get_option( 'date_format' ), $timestamp );
		}
	}
}

// Calculate current state based on UTC timestamps.
// time() returns current Unix timestamp (UTC-based), ensuring timezone-agnostic comparisons.
$now = time();
if ( $now < $bidding_starts ) {
	$current_state = 'upcoming';
	$timestamp     = $bidding_starts;
} elseif ( $now >= $bidding_starts && $now < $bidding_ends ) {
	$current_state = 'running';
	$timestamp     = $bidding_ends;
} else {
	$current_state = 'expired';
	$timestamp     = $bidding_ends;
}

// Calculate countdown display.
$diff        = $timestamp - $now;
$is_showing_date = false;

// For expired items, calculate elapsed time.
if ( 'expired' === $current_state ) {
	$elapsed = abs( $diff );

	if ( $elapsed < 3600 ) {
		// Less than 1 hour ago - show minutes and seconds elapsed.
		$minutes = floor( $elapsed / 60 );
		$seconds = $elapsed % 60;
		$parts   = array();

		if ( $minutes > 0 ) {
			/* translators: %d: number of minutes */
			$parts[] = sprintf( _n( '%d minute', '%d minutes', $minutes, 'aucteeno' ), $minutes );
		}

		/* translators: %d: number of seconds */
		$parts[] = sprintf( _n( '%d second', '%d seconds', $seconds, 'aucteeno' ), $seconds );

		/* translators: %s: elapsed time (e.g., "5 minutes 30 seconds") */
		$display_value = sprintf( __( '%s ago', 'aucteeno' ), implode( ' ', $parts ) );
	} elseif ( $elapsed < 86400 ) {
		// Less than 1 day ago - show hours elapsed.
		$hours = floor( $elapsed / 3600 );
		/* translators: %d: number of hours */
		$time_string = sprintf( _n( '%d hour', '%d hours', $hours, 'aucteeno' ), $hours );
		/* translators: %s: elapsed time (e.g., "3 hours") */
		$display_value = sprintf( __( '%s ago', 'aucteeno' ), $time_string );
	} elseif ( $elapsed < 604800 ) {
		// Less than 1 week ago - show days elapsed.
		$days = floor( $elapsed / 86400 );
		/* translators: %d: number of days */
		$time_string = sprintf( _n( '%d day', '%d days', $days, 'aucteeno' ), $days );
		/* translators: %s: elapsed time (e.g., "2 days") */
		$display_value = sprintf( __( '%s ago', 'aucteeno' ), $time_string );
	} else {
		// More than 1 week ago - show the end date.
		$display_value   = aucteeno_format_date( $timestamp, $date_format );
		$is_showing_date = true;
	}
} elseif ( $diff <= 0 && 'upcoming' !== $current_state ) {
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

// Determine label based on state and whether showing date.
if ( 'expired' === $current_state ) {
	$label = __( 'Bidding ended', 'aucteeno' );
} elseif ( $is_showing_date ) {
	// When showing a date, use "on" instead of "in".
	if ( 'upcoming' === $current_state ) {
		$label = __( 'Bidding starts on', 'aucteeno' );
	} else {
		$label = __( 'Bidding ends on', 'aucteeno' );
	}
} else {
	// When showing time intervals, use "in".
	if ( 'upcoming' === $current_state ) {
		$label = __( 'Bidding starts in', 'aucteeno' );
	} else {
		$label = __( 'Bidding ends in', 'aucteeno' );
	}
}

$wrapper_classes = 'aucteeno-field-countdown';
$wrapper_classes .= ' aucteeno-field-countdown--' . $current_state;
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $wrapper_classes ) );

ob_start();
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-aucteeno-countdown data-starts-at="<?php echo esc_attr( $bidding_starts ); ?>" data-ends-at="<?php echo esc_attr( $bidding_ends ); ?>" data-current-state="<?php echo esc_attr( $current_state ); ?>" data-date-format="<?php echo esc_attr( $date_format ); ?>">
	<?php if ( $show_label ) : ?>
		<span class="aucteeno-field-countdown__label"><?php echo esc_html( $label ); ?></span>
	<?php endif; ?>
	<span class="aucteeno-field-countdown__value"><?php echo esc_html( $display_value ); ?></span>
</div>
<?php
echo ob_get_clean();
