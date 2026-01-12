<?php
/**
 * Aucteeno Pagination Block - Server-Side Render
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$query_data = $block->context['aucteeno/query'] ?? array();

$total_pages  = $query_data['totalPages'] ?? 1;
$current_page = $query_data['currentPage'] ?? 1;
$total        = $query_data['total'] ?? 0;

// Don't render if only one page.
if ( $total_pages <= 1 ) {
	return '';
}

$show_page_numbers = $attributes['showPageNumbers'] ?? true;
$show_prev_next    = $attributes['showPrevNext'] ?? true;

$wrapper_classes = 'aucteeno-pagination';
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $wrapper_classes ) );

// Use WP paged query var if available.
$paged = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : $current_page;

ob_start();
?>
<nav <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-label="<?php esc_attr_e( 'Pagination', 'aucteeno' ); ?>">
	<?php
	$pagination_args = array(
		'total'     => $total_pages,
		'current'   => $paged,
		'show_all'  => false,
		'mid_size'  => 2,
		'prev_next' => $show_prev_next,
		'prev_text' => __( '&larr; Previous', 'aucteeno' ),
		'next_text' => __( 'Next &rarr;', 'aucteeno' ),
	);

	if ( ! $show_page_numbers ) {
		$pagination_args['mid_size']  = 0;
		$pagination_args['end_size']  = 0;
		$pagination_args['show_all']  = false;
	}

	echo paginate_links( $pagination_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
</nav>
<?php
echo ob_get_clean();
