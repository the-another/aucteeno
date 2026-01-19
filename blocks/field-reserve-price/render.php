<?php
/**
 * Aucteeno Field Reserve Price Block - Server-Side Render
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

$show_label    = $attributes['showLabel'] ?? true;
$hide_if_zero  = $attributes['hideIfZero'] ?? true;
$reserve_price = $item_data['reserve_price'] ?? 0;

if ( $hide_if_zero && empty( $reserve_price ) ) {
	return '';
}

$formatted_price = function_exists( 'wc_price' ) ? wc_price( $reserve_price ) : '$' . number_format( $reserve_price, 2 );

$wrapper_classes = 'aucteeno-field-reserve-price';
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $wrapper_classes ) );

ob_start();
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $show_label ) : ?>
		<span class="aucteeno-field-reserve-price__label"><?php esc_html_e( 'Reserve', 'aucteeno' ); ?></span>
	<?php endif; ?>
	<span class="aucteeno-field-reserve-price__value"><?php echo $formatted_price; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
</div>
<?php
echo ob_get_clean();
