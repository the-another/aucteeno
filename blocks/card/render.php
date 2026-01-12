<?php
/**
 * Aucteeno Card Block - Server-Side Render
 *
 * Renders the card block for a single auction or item.
 *
 * @package Aucteeno
 * @since 2.0.0
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (inner blocks).
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get item data from context.
$item_data = $block->context['aucteeno/item'] ?? null;
$item_type = $block->context['aucteeno/itemType'] ?? 'auctions';

// If no item data, return empty.
if ( ! $item_data ) {
	return '';
}

// Extract attributes.
$use_image_as_background = $attributes['useImageAsBackground'] ?? false;
$background_overlay      = $attributes['backgroundOverlay'] ?? 0.5;

// Map bidding_status to class name.
$status_map = array(
	10 => 'running',
	20 => 'upcoming',
	30 => 'expired',
);
$bidding_status = $item_data['bidding_status'] ?? 10;
$status_class   = $status_map[ $bidding_status ] ?? 'running';

// Build wrapper classes.
$wrapper_classes = array(
	'aucteeno-card',
	'aucteeno-card--' . $status_class,
	'aucteeno-card--' . ( 'auctions' === $item_type ? 'auction' : 'item' ),
);

if ( $use_image_as_background ) {
	$wrapper_classes[] = 'aucteeno-card--has-background';
}

// Build inline styles.
$inline_styles = array();

if ( $use_image_as_background && ! empty( $item_data['image_url'] ) ) {
	$inline_styles[] = sprintf(
		'background-image: linear-gradient(rgba(0, 0, 0, %s), rgba(0, 0, 0, %s)), url(%s)',
		esc_attr( $background_overlay ),
		esc_attr( $background_overlay ),
		esc_url( $item_data['image_url'] )
	);
	$inline_styles[] = 'background-size: cover';
	$inline_styles[] = 'background-position: center';
	$inline_styles[] = 'background-repeat: no-repeat';
}

// Add spacing styles from block attributes.
if ( ! empty( $attributes['style']['spacing']['padding'] ) ) {
	$padding = $attributes['style']['spacing']['padding'];
	if ( ! empty( $padding['top'] ) ) {
		$inline_styles[] = 'padding-top: ' . esc_attr( $padding['top'] );
	}
	if ( ! empty( $padding['right'] ) ) {
		$inline_styles[] = 'padding-right: ' . esc_attr( $padding['right'] );
	}
	if ( ! empty( $padding['bottom'] ) ) {
		$inline_styles[] = 'padding-bottom: ' . esc_attr( $padding['bottom'] );
	}
	if ( ! empty( $padding['left'] ) ) {
		$inline_styles[] = 'padding-left: ' . esc_attr( $padding['left'] );
	}
}

// Get wrapper attributes.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => implode( ' ', $wrapper_classes ),
	)
);

// Build style attribute.
$style_attr = '';
if ( ! empty( $inline_styles ) ) {
	$style_attr = ' style="' . esc_attr( implode( '; ', $inline_styles ) ) . '"';
}

// Add layout classes if WordPress didn't add them.
if ( ! empty( $attributes['layout']['type'] ) && strpos( $wrapper_attributes, 'is-layout-' ) === false ) {
	$layout_type  = $attributes['layout']['type'];
	$layout_class = 'is-layout-' . $layout_type;

	if ( 'flex' === $layout_type && ! empty( $attributes['layout']['orientation'] ) ) {
		$orientation = $attributes['layout']['orientation'];
		if ( 'vertical' === $orientation ) {
			$layout_class .= ' is-vertical';
		}
	}

	$wrapper_attributes = str_replace(
		'class="',
		'class="' . esc_attr( $layout_class ) . ' ',
		$wrapper_attributes
	);
}

// Render the card.
ob_start();
?>
<article <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-item-id="<?php echo esc_attr( $item_data['id'] ?? 0 ); ?>">
	<?php
	// Render inner blocks with item context.
	if ( ! empty( $block->inner_blocks ) ) {
		foreach ( $block->inner_blocks as $inner_block ) {
			$inner_block_instance = new WP_Block(
				$inner_block->parsed_block,
				array_merge(
					$block->context,
					array(
						'aucteeno/item'     => $item_data,
						'aucteeno/itemType' => $item_type,
					)
				)
			);
			echo $inner_block_instance->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	} elseif ( ! empty( $content ) ) {
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		// Fallback: render basic card content.
		?>
		<?php if ( ! empty( $item_data['image_url'] ) && ! $use_image_as_background ) : ?>
			<a class="aucteeno-card__media" href="<?php echo esc_url( $item_data['permalink'] ?? '#' ); ?>">
				<img src="<?php echo esc_url( $item_data['image_url'] ); ?>" alt="" loading="lazy" />
			</a>
		<?php endif; ?>
		<a class="aucteeno-card__title" href="<?php echo esc_url( $item_data['permalink'] ?? '#' ); ?>">
			<?php echo esc_html( $item_data['title'] ?? '' ); ?>
		</a>
		<?php
	}
	?>
</article>
<?php
echo ob_get_clean();
