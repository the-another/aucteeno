<?php
/**
 * Aucteeno Field Title Block - Server-Side Render
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

$tag_name  = $attributes['tagName'] ?? 'h3';
$is_link   = $attributes['isLink'] ?? true;
$title     = $item_data['title'] ?? '';
$permalink = $item_data['permalink'] ?? '#';

// Validate tag name.
$allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div' );
if ( ! in_array( $tag_name, $allowed_tags, true ) ) {
	$tag_name = 'h3';
}

$wrapper_classes = 'aucteeno-field-title';
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $wrapper_classes ) );

ob_start();
?>
<<?php echo esc_html( $tag_name ); ?> <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $is_link ) : ?>
		<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
	<?php else : ?>
		<?php echo esc_html( $title ); ?>
	<?php endif; ?>
</<?php echo esc_html( $tag_name ); ?>>
<?php
echo ob_get_clean();
