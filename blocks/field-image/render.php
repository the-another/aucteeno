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

// Get product ID from item data.
$product_id = isset( $item_data['id'] ) ? absint( $item_data['id'] ) : 0;
if ( ! $product_id ) {
	return '';
}

// Get product object.
$product = wc_get_product( $product_id );
if ( ! $product ) {
	return '';
}

$wrapper_classes = 'aucteeno-field-image';
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $wrapper_classes ) );

$style = "aspect-ratio: {$aspect_ratio};";

// Get featured image ID.
$image_id = $product->get_image_id();

ob_start();
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $is_link ) : ?>
		<a class="aucteeno-field-image__link" href="<?php echo esc_url( $permalink ); ?>" style="<?php echo esc_attr( $style ); ?>">
	<?php else : ?>
		<div class="aucteeno-field-image__wrapper" style="<?php echo esc_attr( $style ); ?>">
	<?php endif; ?>

	<?php if ( $image_id ) : ?>
		<?php
		$alt_text = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
		$alt      = ! empty( $alt_text ) ? $alt_text : $title;
		echo $product->get_image( 'woocommerce_thumbnail', array( 'alt' => $alt, 'style' => $style ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
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
