<?php
/**
 * Aucteeno Field Current Bid Block - Server-Side Render
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

$show_label  = $attributes['showLabel'] ?? true;
$label       = $attributes['label'] ?? '';
$current_bid = $item_data['current_bid'] ?? 0;

if ( empty( $label ) ) {
	$label = __( 'Current Bid', 'aucteeno' );
}

$formatted_price = function_exists( 'wc_price' ) ? wc_price( $current_bid ) : '$' . number_format( $current_bid, 2 );

$wrapper_classes = 'aucteeno-field-current-bid';
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $wrapper_classes ) );

ob_start();
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $show_label ) : ?>
		<span class="aucteeno-field-current-bid__label"><?php echo esc_html( $label ); ?></span>
	<?php endif; ?>
	<span class="aucteeno-field-current-bid__value"><?php echo $formatted_price; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
</div>
<?php
echo ob_get_clean();
