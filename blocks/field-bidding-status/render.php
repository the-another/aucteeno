<?php
/**
 * Aucteeno Field Bidding Status Block - Server-Side Render
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$item_data = $block->context['aucteeno/item'] ?? null;
if ( ! $item_data ) {
	return '';
}

$bidding_status = $item_data['bidding_status'] ?? 10;

$status_map = array(
	10 => array(
		'label' => __( 'Running', 'aucteeno' ),
		'class' => 'running',
	),
	20 => array(
		'label' => __( 'Upcoming', 'aucteeno' ),
		'class' => 'upcoming',
	),
	30 => array(
		'label' => __( 'Expired', 'aucteeno' ),
		'class' => 'expired',
	),
);

$status = $status_map[ $bidding_status ] ?? $status_map[10];

$wrapper_classes = 'aucteeno-field-bidding-status aucteeno-field-bidding-status--' . $status['class'];
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $wrapper_classes ) );

ob_start();
?>
<span <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php echo esc_html( $status['label'] ); ?>
</span>
<?php
echo ob_get_clean();
