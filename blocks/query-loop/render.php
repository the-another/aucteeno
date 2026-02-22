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

use The_Another\Plugin\Aucteeno\Database\Database_Auctions;
use The_Another\Plugin\Aucteeno\Database\Database_Items;

// Parse attributes with defaults.
$query_type      = $attributes['queryType'] ?? 'auctions';
$columns         = absint( $attributes['columns'] ?? 4 );
$display_layout  = $attributes['displayLayout'] ?? 'grid';
$per_page        = absint( $attributes['perPage'] ?? 12 );
$order_by        = $attributes['orderBy'] ?? 'ending_soon';
$infinite_scroll = $attributes['infiniteScroll'] ?? false;
$update_url      = $attributes['updateUrl'] ?? true;
$gap             = $attributes['gap'] ?? '1.5rem';

// When specific product IDs are provided, this is a direct query for those posts.
// Do not inherit page, location, user, auction, or search from the main query context.
$has_product_ids = ! empty( $block->context['productIds'] ) && is_array( $block->context['productIds'] );

// Determine user ID from attribute or context (attribute takes priority).
$user_id = 0;
if ( ! empty( $attributes['userId'] ) ) {
	$user_id = absint( $attributes['userId'] );
} elseif ( ! empty( $block->context['userId'] ) ) {
	$user_id = absint( $block->context['userId'] );
} else {
	// Fallback: Try to get vendor ID from current page context (for seller pages).
	// Only auto-detect vendor on actual store pages to prevent unintended filtering
	// on search results, shop pages, or other non-store pages.
	$store_name    = get_query_var( 'store', '' );
	$is_store_page = ! empty( $store_name )
		|| ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() );

	if ( $is_store_page ) {
		// Try Another Blocks for Dokan Context_Detector.
		if ( class_exists( '\The_Another\Plugin\Blocks_Dokan\Helpers\Context_Detector' ) ) {
			$vendor_id = \The_Another\Plugin\Blocks_Dokan\Helpers\Context_Detector::get_vendor_id();
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
		if ( ! $user_id && ! empty( $store_name ) ) {
			$store_user = get_user_by( 'slug', $store_name );
			if ( $store_user && function_exists( 'dokan_is_user_seller' ) && dokan_is_user_seller( $store_user->ID ) ) {
				$user_id = absint( $store_user->ID );
			}
		}
	}
}

 // Determine auction ID from context (for filtering items by parent auction).
$auction_id = 0;
if ( 'items' === $query_type ) {
	// Check if we have a postId from context (i.e., we're on a single product page).
	$post_id = 0;
	if ( ! empty( $block->context['postId'] ) ) {
		$post_id = absint( $block->context['postId'] );
	} elseif ( is_singular( 'product' ) ) {
		// Fallback: Get current post ID if we're on a single product page.
		$post_id = get_the_ID();
	}

	// If we have a post ID, check if it's an auction product.
	if ( $post_id > 0 ) {
		$product = wc_get_product( $post_id );
		if ( $product && 'aucteeno-ext-auction' === $product->get_type() ) {
			$auction_id = $post_id;
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

// Extract search parameter from URL (overrides block attributes).
$search_query = '';

// 1. Check direct query argument.
if ( isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$search_query = sanitize_text_field( wp_unslash( $_GET['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

// 2. Check WordPress query var (if WordPress already parsed it).
if ( empty( $search_query ) && get_query_var( 's' ) ) {
	$search_query = sanitize_text_field( get_query_var( 's' ) );
}

// Trim whitespace and limit length for security.
$search_query = trim( substr( $search_query, 0, 200 ) );

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
	'search'   => $search_query,
);

// Add auction filter for items query (if auction context is available).
if ( $auction_id > 0 ) {
	$query_args['auction_id'] = $auction_id;
}

// Detect location from current taxonomy archive page (if viewing aucteeno-location archive).
$archive_location_country     = '';
$archive_location_subdivision = '';

if ( is_tax( 'aucteeno-location' ) ) {
	$queried_object = get_queried_object();
	if ( $queried_object && isset( $queried_object->term_id ) ) {
		$location_code = get_term_meta( $queried_object->term_id, 'code', true );
		if ( ! empty( $location_code ) ) {
			// Parse code: "CA" for countries, "US:KS" for subdivisions.
			if ( strpos( $location_code, ':' ) !== false ) {
				// Subdivision format: COUNTRY:STATE.
				$parts                        = explode( ':', $location_code, 2 );
				$archive_location_country     = isset( $parts[0] ) ? sanitize_text_field( $parts[0] ) : '';
				$archive_location_subdivision = ! empty( $parts[0] ) && ! empty( $parts[1] ) ? sanitize_text_field( $location_code ) : '';
			} else {
				// Country format: just the code.
				$archive_location_country = sanitize_text_field( $location_code );
			}
		}
	}
}

// Add location filters from attributes (with context as fallback, then archive context).
// Priority: attributes > block context > taxonomy archive context.
$location_country = '';
if ( ! empty( $attributes['locationCountry'] ) ) {
	$location_country = sanitize_text_field( $attributes['locationCountry'] );
} elseif ( ! empty( $block->context['locationCountry'] ) ) {
	$location_country = sanitize_text_field( $block->context['locationCountry'] );
} elseif ( ! empty( $archive_location_country ) ) {
	$location_country = $archive_location_country;
}

$location_subdivision = '';
if ( ! empty( $attributes['locationSubdivision'] ) ) {
	$location_subdivision = sanitize_text_field( $attributes['locationSubdivision'] );
} elseif ( ! empty( $block->context['locationSubdivision'] ) ) {
	$location_subdivision = sanitize_text_field( $block->context['locationSubdivision'] );
} elseif ( ! empty( $archive_location_subdivision ) ) {
	$location_subdivision = $archive_location_subdivision;
}

if ( ! empty( $location_country ) ) {
	$query_args['country'] = $location_country;
}
if ( ! empty( $location_subdivision ) ) {
	$query_args['subdivision'] = $location_subdivision;
}

// Add product IDs filter from context (for wishlist and other filtered views).
if ( $has_product_ids ) {
	$query_args['product_ids'] = array_map( 'absint', $block->context['productIds'] );

	// When specific post IDs are defined, show exactly those posts.
	// Strip all inherited filters — product_ids IS the query.
	$user_id      = 0;
	$auction_id   = 0;
	$page         = 1;
	$search_query = '';
	$query_args   = array(
		'page'        => 1,
		'per_page'    => $query_args['per_page'],
		'sort'        => $query_args['sort'],
		'user_id'     => 0,
		'search'      => '',
		'product_ids' => $query_args['product_ids'],
	);
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
$pattern_slug      = null;

if ( ! empty( $block->inner_blocks ) ) {
	foreach ( $block->inner_blocks as $inner_block ) {
		if ( 'aucteeno/pagination' === $inner_block->name ) {
			$pagination_blocks[] = $inner_block;
		} elseif ( 'aucteeno/card' === $inner_block->name ) {
			$template_blocks[] = $inner_block;
		} elseif ( 'core/pattern' === $inner_block->name && ! empty( $inner_block->parsed_block['attrs']['slug'] ) ) {
			// Store pattern slug for later loading.
			$pattern_slug = $inner_block->parsed_block['attrs']['slug'];
		}
	}
}

// If no card blocks found but we have a pattern reference, load the pattern content.
if ( empty( $template_blocks ) && ! empty( $pattern_slug ) ) {
	// Get pattern content from theme.
	$pattern_file = get_theme_file_path( 'patterns/' . basename( str_replace( '/', '-', $pattern_slug ) ) . '.php' );

	if ( file_exists( $pattern_file ) ) {
		// Load pattern content.
		ob_start();
		include $pattern_file;
		$pattern_content = ob_get_clean();

		// Trim any whitespace from the pattern content.
		$pattern_content = trim( $pattern_content );

		// Parse blocks from pattern content.
		$pattern_blocks = parse_blocks( $pattern_content );

		// Extract card blocks from parsed pattern (including nested search).
		$extract_cards = function( $blocks ) use ( &$extract_cards, &$template_blocks, $block ) {
			foreach ( $blocks as $pattern_block ) {
				if ( 'aucteeno/card' === ( $pattern_block['blockName'] ?? '' ) ) {
					// Create a WP_Block instance from the parsed block.
					$template_blocks[] = new WP_Block( $pattern_block, array( 'aucteeno/query-loop' => $block->context ) );
				} elseif ( ! empty( $pattern_block['innerBlocks'] ) ) {
					// Recursively search inner blocks.
					$extract_cards( $pattern_block['innerBlocks'] );
				}
			}
		};

		$extract_cards( $pattern_blocks );
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
// Preserve search parameter if present.
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
	'auctionId'      => $auction_id,
	'perPage'        => $per_page,
	'orderBy'        => $order_by,
	'country'        => $query_args['country'] ?? '',
	'subdivision'    => $query_args['subdivision'] ?? '',
	'search'         => $search_query,
	'infiniteScroll' => $infinite_scroll,
	'updateUrl'      => $update_url,
	'restUrl'        => rest_url( 'aucteeno/v1/' . ( 'auctions' === $query_type ? 'auctions' : 'items' ) ),
	'restNonce'      => wp_create_nonce( 'wp_rest' ),
	'blockTemplate'  => $card_template_json,
	'pageUrl'        => $current_url,
	'productIds'     => $has_product_ids ? array_map( 'absint', $block->context['productIds'] ) : array(),
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
