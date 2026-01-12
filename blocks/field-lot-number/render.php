<?php
/**
 * Aucteeno Field Lot Number Block - Server-Side Render
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$item_data = $block->context['aucteeno/item'] ?? null;
$item_type = $block->context['aucteeno/itemType'] ?? 'auctions';

// Only show for items, not auctions.
if ( 'items' !== $item_type || ! $item_data ) {
	return '';
}

$show_label = $attributes['showLabel'] ?? true;
$prefix     = $attributes['prefix'] ?? 'Lot #';
$lot_no     = $item_data['lot_no'] ?? '';

if ( empty( $lot_no ) ) {
	return '';
}

$wrapper_classes = 'aucteeno-field-lot-number';
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $wrapper_classes ) );

ob_start();
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $show_label && ! empty( $prefix ) ) : ?>
		<span class="aucteeno-field-lot-number__prefix"><?php echo esc_html( $prefix ); ?></span>
	<?php endif; ?>
	<span class="aucteeno-field-lot-number__value"><?php echo esc_html( $lot_no ); ?></span>
</div>
<?php
echo ob_get_clean();
