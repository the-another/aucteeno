<?php
/**
 * Auction Card Template Part
 *
 * Renders a single auction card. Used by SSR and REST fragment rendering.
 *
 * Expected $auction array keys:
 * - id           (int)    Product ID
 * - title        (string) Auction title
 * - permalink    (string) Auction URL
 * - image_url    (string) Featured image URL (optional)
 * - source_label (string) Source label text (optional)
 * - end_ts       (int)    End time as Unix timestamp
 * - end_iso      (string) End time as ISO 8601 string
 *
 * @package Aucteeno
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use TheAnother\Plugin\Aucteeno\Helpers\DateTime_Helper;

// Ensure $auction is available.
if ( ! isset( $auction ) || ! is_array( $auction ) ) {
	return;
}

$id           = $auction['id'] ?? 0;
$title        = $auction['title'] ?? '';
$permalink    = $auction['permalink'] ?? '#';
$image_url    = $auction['image_url'] ?? '';
$source_label = $auction['source_label'] ?? '';
$end_ts       = $auction['end_ts'] ?? 0;
$end_iso      = $auction['end_iso'] ?? '';
?>
<article class="aucteeno-card aucteeno-auction-card" data-auction-id="<?php echo esc_attr( $id ); ?>">
	<?php if ( ! empty( $source_label ) ) : ?>
		<div class="aucteeno-auction-card__source">
			<?php echo esc_html( $source_label ); ?>
		</div>
	<?php endif; ?>

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
	</div>
</article>
