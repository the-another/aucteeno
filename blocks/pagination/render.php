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
		'format'    => '?paged=%#%', // Force query string format for Interactivity API compatibility.
	);

	if ( ! $show_page_numbers ) {
		$pagination_args['mid_size']  = 0;
		$pagination_args['end_size']  = 0;
		$pagination_args['show_all']  = false;
	}

	// Get pagination HTML.
	$pagination_html = paginate_links( $pagination_args );

	// Add Interactivity API directives to pagination links.
	// This allows the Interactivity API to handle page navigation without full page reload.
	$pagination_html = preg_replace_callback(
		'/<a([^>]*)href=["\']([^"\']*[?&]paged?=(\d+)[^"\']*)["\'](([^>]*)>)/i',
		function ( $matches ) {
			$before_href = $matches[1];
			$href        = $matches[2];
			$page        = $matches[3];
			$after_attrs = $matches[5]; // Use match 5 (without >) not match 4 (with >).
			return '<a' . $before_href . 'href="' . esc_url( $href ) . '"' . $after_attrs . ' data-wp-on--click="actions.loadPage" data-page="' . esc_attr( $page ) . '">';
		},
		$pagination_html
	);

	echo $pagination_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
</nav>
<?php
echo ob_get_clean();
