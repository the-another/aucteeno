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
$query_type      = $attributes['queryType'] ?? 'auctions';
$columns         = absint( $attributes['columns'] ?? 4 );
$display_layout  = $attributes['displayLayout'] ?? 'grid';
$per_page        = absint( $attributes['perPage'] ?? 12 );
$order_by        = $attributes['orderBy'] ?? 'ending_soon';
$infinite_scroll = $attributes['infiniteScroll'] ?? false;
$gap             = $attributes['gap'] ?? '1.5rem';

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
// Determine current page with multiple fallbacks since query_vars may not be parsed yet.
$page = 0;

// 1. Check WordPress query var.
if ( ! $page && get_query_var( 'paged' ) ) {
	$page = absint( get_query_var( 'paged' ) );
}

// 2. Check ?paged= query argument directly.
if ( ! $page && isset( $_GET['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = absint( $_GET['paged'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

// 3. Parse URL path for /page/N/ pattern.
if ( ! $page && isset( $_SERVER['REQUEST_URI'] ) ) {
	$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	if ( preg_match( '#/page/(\d+)/?#', $request_uri, $matches ) ) {
		$page = absint( $matches[1] );
	}
}

// 4. Check block context.
if ( ! $page && ! empty( $block->context['query/page'] ) ) {
	$page = absint( $block->context['query/page'] );
}

// 5. Default to page 1.
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

// Extract card width from first card block to apply to query loop wrapper.
$card_width = '20rem'; // Default.
if ( ! empty( $template_blocks ) && ! empty( $template_blocks[0]->parsed_block['attrs']['cardWidth'] ) ) {
	$card_width = $template_blocks[0]->parsed_block['attrs']['cardWidth'];
}

// Serialize card template for REST API pagination.
// This allows the REST endpoint to render cards with the same block structure.
$card_template_json = null;
if ( ! empty( $template_blocks ) ) {
	$card_template_json = wp_json_encode( $template_blocks[0]->parsed_block );
}

// Get current page URL without paged param for pagination base.
// Use home_url() to ensure absolute URL (remove_query_arg returns relative path).
$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
$current_url = home_url( remove_query_arg( 'paged', $request_uri ) );

// Prepare Interactivity API context.
$interactivity_context = array(
	'page'           => $page,
	'pages'          => $total_pages,
	'total'          => $total_items,
	'isLoading'      => false,
	'hasMore'        => $page < $total_pages,
	'queryType'      => $query_type,
	'userId'         => $user_id,
	'perPage'        => $per_page,
	'orderBy'        => $order_by,
	'country'        => $query_args['country'] ?? '',
	'subdivision'    => $query_args['subdivision'] ?? '',
	'infiniteScroll' => $infinite_scroll,
	'restUrl'        => rest_url( 'aucteeno/v1/' . ( 'auctions' === $query_type ? 'auctions' : 'items' ) ),
	'restNonce'      => wp_create_nonce( 'wp_rest' ),
	'blockTemplate'  => $card_template_json,
	'pageUrl'        => $current_url,
);

// Get wrapper attributes with Interactivity API directives.
$data_attrs = array(
	'class'                         => $wrapper_classes,
	'data-wp-interactive'           => 'aucteeno/query-loop',
	'data-wp-context'               => wp_json_encode( $interactivity_context ),
	'data-wp-class--is-loading'     => 'context.isLoading',
	'data-wp-init'                  => 'callbacks.onInit',
	'style'                         => sprintf(
		'--gap: %s; --card-width: %s;',
		esc_attr( $gap ),
		esc_attr( $card_width )
	),
);

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

				// Render template blocks (card) with item context.
				if ( ! empty( $template_blocks ) ) {
					foreach ( $template_blocks as $template_block ) {
						// Add card-width as a style on the card block.
						$template_block_with_width = $template_block->parsed_block;
						if ( ! isset( $template_block_with_width['attrs']['style'] ) ) {
							$template_block_with_width['attrs']['style'] = array();
						}
						$template_block_with_width['attrs']['cardWidthOverride'] = $card_width;

						$template_block_instance = new WP_Block(
							$template_block_with_width,
							$item_context
						);
						?>
						<li class="aucteeno-query-loop__item">
							<?php echo $template_block_instance->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</li>
						<?php
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
					<li class="aucteeno-query-loop__item">
						<article class="aucteeno-card aucteeno-card--<?php echo esc_attr( $status_class ); ?>" style="--card-width: <?php echo esc_attr( $card_width ); ?>">
							<?php if ( ! empty( $item_data['image_url'] ) ) : ?>
								<a class="aucteeno-card__media" href="<?php echo esc_url( $item_data['permalink'] ?? '#' ); ?>">
									<img src="<?php echo esc_url( $item_data['image_url'] ); ?>" alt="" loading="lazy" />
								</a>
							<?php endif; ?>
							<a class="aucteeno-card__title" href="<?php echo esc_url( $item_data['permalink'] ?? '#' ); ?>">
								<?php echo esc_html( $item_data['title'] ?? '' ); ?>
							</a>
						</article>
					</li>
					<?php
				}
			}
			?>
		</ul>

		<?php
		// Conditionally render pagination or infinite scroll elements.
		if ( ! $infinite_scroll ) {
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
				// Use the $page variable we already calculated above.
				$current_page = $page;

				// Get base URL without /page/X/ for building pagination links.
				$base_url = preg_replace( '#/page/\d+/?$#', '/', $current_url );
				$base_url = trailingslashit( $base_url );

				// Get pagination HTML.
				$fallback_pagination = paginate_links(
					array(
						'total'     => $total_pages,
						'current'   => $current_page,
						'prev_text' => __( '&larr; Previous', 'aucteeno' ),
						'next_text' => __( 'Next &rarr;', 'aucteeno' ),
						'format'    => '?paged=%#%', // Force query string format for Interactivity API.
					)
				);

				// Add Interactivity API directives and build clean URLs with /page/X/?paged=X format.
				$fallback_pagination = preg_replace_callback(
					'/<a([^>]*)href=["\']([^"\']*)\?paged=(\d+)[^"\']*["\'](([^>]*)>)/i',
					function ( $matches ) use ( $base_url ) {
						$before_href = $matches[1];
						$page_num    = $matches[3];
						$after_attrs = $matches[5];
						// Build URL with /page/X/?paged=X format.
						$clean_href  = $base_url . 'page/' . $page_num . '/?paged=' . $page_num;
						return '<a' . $before_href . 'href="' . esc_url( $clean_href ) . '"' . $after_attrs . ' data-wp-on--click="actions.loadPage" data-page="' . esc_attr( $page_num ) . '">';
					},
					$fallback_pagination
				);
				?>
				<nav class="aucteeno-query-loop__pagination">
					<?php echo $fallback_pagination; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</nav>
			<?php endif;
		} else {
			// Render infinite scroll elements.
			?>
			<div class="aucteeno-query-loop__loading" data-wp-class--is-visible="context.isLoading">
				<span><?php esc_html_e( 'Loading...', 'aucteeno' ); ?></span>
			</div>
			<div class="aucteeno-query-loop__sentinel" data-wp-init="callbacks.initInfiniteScroll" aria-hidden="true"></div>
			<?php
		}
		?>
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
