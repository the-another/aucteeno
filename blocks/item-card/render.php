<?php
/**
 * Item Card Block - Server-Side Render
 *
 * This block is primarily used internally by Items Listing.
 * When rendered standalone, it shows a placeholder.
 *
 * @package Aucteeno
 * @since 1.0.0
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'aucteeno-card aucteeno-item-card aucteeno-card--placeholder',
	)
);
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="aucteeno-card__placeholder-message">
		<?php esc_html_e( 'Item Card (used in Items Listing)', 'aucteeno' ); ?>
	</div>
</div>
