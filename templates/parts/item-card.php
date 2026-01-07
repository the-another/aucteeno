<?php
/**
 * Item Card Template Part
 *
 * Renders a single item card. Used by SSR and REST fragment rendering.
 *
 * Expected $item array keys:
 * - id             (int)    Product ID
 * - title          (string) Item title
 * - permalink      (string) Item URL
 * - image_url      (string) Featured image URL (optional)
 * - location_label (string) Location display text
 * - end_ts         (int)    End time as Unix timestamp
 * - end_iso        (string) End time as ISO 8601 string
 *
 * @package Aucteeno
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use TheAnother\Plugin\Aucteeno\Helpers\DateTime_Helper;

// Ensure $item is available.
if ( ! isset( $item ) || ! is_array( $item ) ) {
	return;
}

$id             = $item['id'] ?? 0;
$title          = $item['title'] ?? '';
$permalink      = $item['permalink'] ?? '#';
$image_url      = $item['image_url'] ?? '';
$location_label = $item['location_label'] ?? '';
$end_ts         = $item['end_ts'] ?? 0;
$end_iso        = $item['end_iso'] ?? '';
?>
<article class="aucteeno-card aucteeno-item-card" data-item-id="<?php echo esc_attr( $id ); ?>">
	<a class="aucteeno-card__media" href="<?php echo esc_url( $permalink ); ?>">
		<?php if ( ! empty( $image_url ) ) : ?>
			<img src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy" />
		<?php else : ?>
			<div class="aucteeno-card__media-placeholder" aria-hidden="true"></div>
		<?php endif; ?>
	</a>

	<a class="aucteeno-card__title" href="<?php echo esc_url( $permalink ); ?>">
		<?php echo esc_html( $title ); ?>
	</a>

	<div class="aucteeno-card__footer">
		<div class="aucteeno-card__countdown">
			<div class="aucteeno-card__countdown-label">Bidding ends in</div>
			<div class="aucteeno-card__countdown-value"
				 data-aucteeno-role="countdown"
				 data-end="<?php echo esc_attr( $end_iso ); ?>">
				<?php echo esc_html( DateTime_Helper::countdown_fallback( (int) $end_ts ) ); ?>
			</div>
		</div>

		<?php if ( ! empty( $location_label ) ) : ?>
			<div class="aucteeno-item-card__location">
				<?php echo esc_html( $location_label ); ?>
			</div>
		<?php endif; ?>
	</div>
</article>
