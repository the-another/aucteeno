<?php
/**
 * Aucteeno Field Image Block - Server-Side Render
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

$is_link      = $attributes['isLink'] ?? true;
$aspect_ratio = $attributes['aspectRatio'] ?? '4/3';
$permalink    = $item_data['permalink'] ?? '#';
$title        = $item_data['title'] ?? '';
$image_id     = absint( $item_data['image_id'] ?? 0 );
$image_url    = $item_data['image_url'] ?? '';

$wrapper_classes    = 'aucteeno-field-image';
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $wrapper_classes ) );

$style = "aspect-ratio: {$aspect_ratio};";

ob_start();
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $is_link ) : ?>
		<a class="aucteeno-field-image__link" href="<?php echo esc_url( $permalink ); ?>" style="<?php echo esc_attr( $style ); ?>">
	<?php else : ?>
		<div class="aucteeno-field-image__wrapper" style="<?php echo esc_attr( $style ); ?>">
	<?php endif; ?>

	<?php
	/**
	 * Filters the image HTML rendered by the field-image block.
	 *
	 * Return a non-empty string to replace the default rendering entirely.
	 * Receives the full item data array and block attributes so plugins can
	 * apply their own sizing, srcset, or markup conventions.
	 *
	 * @since 2.2.0
	 * @param string $html       Default empty string.
	 * @param array  $item_data  Full item context data array.
	 * @param array  $attributes Block attributes.
	 */
	$filtered_html = (string) apply_filters( 'aucteeno_field_image_html', '', $item_data, $attributes );
	?>
	<?php if ( '' !== $filtered_html ) : ?>
		<?php echo $filtered_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php elseif ( $image_id ) : ?>
		<?php
		echo wp_get_attachment_image(
			$image_id,
			'woocommerce_thumbnail',
			false,
			array(
				'alt'   => esc_attr( $title ),
				'style' => $style,
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	<?php elseif ( $image_url ) : ?>
		<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" style="<?php echo esc_attr( $style ); ?>" loading="lazy">
	<?php else : ?>
		<?php echo wc_placeholder_img( 'woocommerce_thumbnail', array( 'style' => $style ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>

	<?php if ( $is_link ) : ?>
		</a>
	<?php else : ?>
		</div>
	<?php endif; ?>
</div>
<?php
echo ob_get_clean();
