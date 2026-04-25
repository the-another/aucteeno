<?php
/**
 * Aucteeno Product Details Block - Server-Side Render
 *
 * Fetches item data once for the current single-product page (or explicit
 * productId attribute) and re-instantiates each inner block with the
 * aucteeno/item and aucteeno/itemType context keys merged in.
 *
 * Pattern mirrors blocks/card/render.php; the difference is the source of
 * $item_data — Block_Data_Helper instead of query-loop iteration.
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

$product_id = (int) ( $attributes['productId'] ?? 0 );
if ( $product_id <= 0 ) {
	$product_id = (int) get_the_ID();
}

if ( $product_id <= 0 ) {
	return '';
}

$item_data = Block_Data_Helper::get_item_data( $product_id );
if ( ! $item_data ) {
	return '';
}

$item_type = ! empty( $item_data['auction_id'] ) ? 'items' : 'auctions';

$wrapper_attributes = get_block_wrapper_attributes(
	array( 'class' => 'aucteeno-product-details' )
);

ob_start();
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-item-id="<?php echo esc_attr( (string) ( $item_data['id'] ?? 0 ) ); ?>">
	<?php
	if ( ! empty( $block->inner_blocks ) ) {
		foreach ( $block->inner_blocks as $inner_block ) {
			$inner_block_instance = new WP_Block(
				$inner_block->parsed_block,
				array_merge(
					$block->context,
					array(
						'postId'            => $product_id,
						'postType'          => 'product',
						'aucteeno/item'     => $item_data,
						'aucteeno/itemType' => $item_type,
					)
				)
			);
			echo $inner_block_instance->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	} elseif ( ! empty( $content ) ) {
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	?>
</div>
<?php
echo ob_get_clean();
