<?php
/**
 * Aucteeno Query Loop Block - Server-Side Render
 *
 * Renders the query loop block with auctions or items.
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

use TheAnother\Plugin\Aucteeno\Database\Database_Auctions;
use TheAnother\Plugin\Aucteeno\Database\Database_Items;

// Parse attributes with defaults.
$query_type     = $attributes['queryType'] ?? 'auctions';
$columns        = absint( $attributes['columns'] ?? 4 );
$display_layout = $attributes['displayLayout'] ?? 'grid';
$per_page       = absint( $attributes['perPage'] ?? 12 );
$order_by       = $attributes['orderBy'] ?? 'ending_soon';

// Determine user ID from attribute or context (attribute takes priority).
$user_id = 0;
if ( ! empty( $attributes['userId'] ) ) {
	$user_id = absint( $attributes['userId'] );
} elseif ( ! empty( $block->context['userId'] ) ) {
	$user_id = absint( $block->context['userId'] );
} else {
	// Fallback: Try to get vendor ID from current page context (for seller pages).
	// Try Dokan Blocks Context_Detector if available.
	if ( class_exists( '\DokanBlocks\Helpers\Context_Detector' ) ) {
		$vendor_id = \DokanBlocks\Helpers\Context_Detector::get_vendor_id();
		if ( $vendor_id ) {
			$user_id = absint( $vendor_id );
		}
	}
	
	// Try Dokan's native function if still no vendor ID.
	if ( ! $user_id && function_exists( 'dokan_get_current_seller_id' ) ) {
		$vendor_id = dokan_get_current_seller_id();
		if ( $vendor_id > 0 ) {
			$user_id = absint( $vendor_id );
		}
	}
	
	// Try to get from 'store' query var (Dokan store page URL).
	if ( ! $user_id ) {
		$store_name = get_query_var( 'store', '' );
		if ( ! empty( $store_name ) ) {
			$store_user = get_user_by( 'slug', $store_name );
			if ( $store_user && function_exists( 'dokan_is_user_seller' ) && dokan_is_user_seller( $store_user->ID ) ) {
				$user_id = absint( $store_user->ID );
			}
		}
	}
}

// Build query args, prioritizing context over attributes.
// Get page from URL query var first, then context, then default to 1.
$page = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : absint( $block->context['query/page'] ?? 1 );
if ( $page < 1 ) {
	$page = 1;
}

// Context overrides for query params.
if ( ! empty( $block->context['query/perPage'] ) ) {
	$per_page = absint( $block->context['query/perPage'] );
}
if ( ! empty( $block->context['query/orderBy'] ) ) {
	$order_by = sanitize_text_field( $block->context['query/orderBy'] );
}

$query_args = array(
	'page'     => $page,
	'per_page' => min( 50, max( 1, $per_page ) ),
	'sort'     => $order_by,
	'user_id'  => $user_id,
);

// Add location filters from context (if provided by parent block).
if ( ! empty( $block->context['locationCountry'] ) ) {
	$query_args['country'] = sanitize_text_field( $block->context['locationCountry'] );
}
if ( ! empty( $block->context['locationSubdivision'] ) ) {
	$query_args['subdivision'] = sanitize_text_field( $block->context['locationSubdivision'] );
}

// Query HPS tables directly.
$results = 'auctions' === $query_type
	? Database_Auctions::query_for_listing( $query_args )
	: Database_Items::query_for_listing( $query_args );

$items       = $results['items'] ?? array();
$total_pages = $results['pages'] ?? 1;
$total_items = $results['total'] ?? 0;

// Build wrapper classes.
$wrapper_classes = 'aucteeno-query-loop';
$wrapper_classes .= ' aucteeno-query-loop--' . esc_attr( $display_layout );
$wrapper_classes .= ' aucteeno-query-loop--' . esc_attr( $query_type );

// Build items wrapper classes.
$items_classes = 'aucteeno-items-wrap';
$items_classes .= ' aucteeno-items-' . esc_attr( $display_layout );
if ( 'grid' === $display_layout ) {
	$items_classes .= ' aucteeno-items-columns-' . absint( $columns );
}

// Get wrapper attributes.
// Add data attributes for REST API navigation.
$data_attrs = array(
	'class' => $wrapper_classes,
	'data-query-type' => esc_attr( $query_type ),
	'data-user-id' => esc_attr( $user_id ),
	'data-per-page' => esc_attr( $per_page ),
	'data-order-by' => esc_attr( $order_by ),
);

// Add location filters if present.
if ( ! empty( $block->context['locationCountry'] ) ) {
	$data_attrs['data-country'] = esc_attr( $block->context['locationCountry'] );
}
if ( ! empty( $block->context['locationSubdivision'] ) ) {
	$data_attrs['data-subdivision'] = esc_attr( $block->context['locationSubdivision'] );
}

$wrapper_attributes = get_block_wrapper_attributes( $data_attrs );

// Provide pagination context for child blocks.
$query_context = array(
	'queryId'     => 'aucteeno-query-' . wp_unique_id(),
	'query'       => array(
		'totalPages'  => $total_pages,
		'currentPage' => $page,
		'total'       => $total_items,
		'perPage'     => $per_page,
	),
);

// Merge context for child blocks.
if ( ! isset( $block->context ) ) {
	$block->context = array();
}
$block->context['aucteeno/queryId'] = $query_context['queryId'];
$block->context['aucteeno/query']   = $query_context['query'];

// Separate template blocks (card) from query-level blocks (pagination).
$template_blocks   = array();
$pagination_blocks = array();

if ( ! empty( $block->inner_blocks ) ) {
	foreach ( $block->inner_blocks as $inner_block ) {
		if ( 'aucteeno/pagination' === $inner_block->name ) {
			$pagination_blocks[] = $inner_block;
		} elseif ( 'aucteeno/card' === $inner_block->name ) {
			$template_blocks[] = $inner_block;
		}
	}
}

ob_start();

if ( ! empty( $items ) ) {
	?>
	<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<ul class="<?php echo esc_attr( $items_classes ); ?>">
			<?php
			foreach ( $items as $item_data ) {
				// Set item data in block context for inner blocks.
				$item_context = array_merge(
					$block->context,
					array(
						'aucteeno/item'     => $item_data,
						'aucteeno/itemType' => $query_type,
					)
				);
				?>
				<li class="aucteeno-query-loop__item">
					<?php
					// Render template blocks (card) with item context.
					if ( ! empty( $template_blocks ) ) {
						foreach ( $template_blocks as $template_block ) {
							$template_block_instance = new WP_Block(
								$template_block->parsed_block,
								$item_context
							);
							echo $template_block_instance->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
					} else {
						// Fallback: render a basic card if no card blocks defined.
						$status_class = 'running';
						if ( isset( $item_data['bidding_status'] ) ) {
							$status_map = array(
								10 => 'running',
								20 => 'upcoming',
								30 => 'expired',
							);
							$status_class = $status_map[ $item_data['bidding_status'] ] ?? 'running';
						}
						?>
						<article class="aucteeno-card aucteeno-card--<?php echo esc_attr( $status_class ); ?>">
							<?php if ( ! empty( $item_data['image_url'] ) ) : ?>
								<a class="aucteeno-card__media" href="<?php echo esc_url( $item_data['permalink'] ?? '#' ); ?>">
									<img src="<?php echo esc_url( $item_data['image_url'] ); ?>" alt="" loading="lazy" />
								</a>
							<?php endif; ?>
							<a class="aucteeno-card__title" href="<?php echo esc_url( $item_data['permalink'] ?? '#' ); ?>">
								<?php echo esc_html( $item_data['title'] ?? '' ); ?>
							</a>
						</article>
						<?php
					}
					?>
				</li>
				<?php
			}
			?>
		</ul>

		<?php
		// Render pagination blocks after the loop.
		$pagination_rendered = false;
		if ( ! empty( $pagination_blocks ) ) {
			foreach ( $pagination_blocks as $pagination_block ) {
				$pagination_block_instance = new WP_Block(
					$pagination_block->parsed_block,
					$block->context
				);
				echo $pagination_block_instance->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$pagination_rendered = true;
				break; // Only render first pagination block.
			}
		}

		// Fallback: Show default pagination if no pagination block is present and pages > 1.
		if ( ! $pagination_rendered && $total_pages > 1 ) :
			$current_page = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : $page;
			?>
			<nav class="aucteeno-query-loop__pagination">
				<?php
				echo paginate_links(
					array(
						'total'     => $total_pages,
						'current'   => $current_page,
						'prev_text' => __( '&larr; Previous', 'aucteeno' ),
						'next_text' => __( 'Next &rarr;', 'aucteeno' ),
					)
				);
				?>
			</nav>
		<?php endif; ?>
	</div>
	<?php
} else {
	// Empty state.
	?>
	<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<p class="aucteeno-query-loop__empty">
			<?php
			if ( 'auctions' === $query_type ) {
				esc_html_e( 'No auctions found.', 'aucteeno' );
			} else {
				esc_html_e( 'No items found.', 'aucteeno' );
			}
			?>
		</p>
	</div>
	<?php
}

echo ob_get_clean();
