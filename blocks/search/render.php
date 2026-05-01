<?php
/**
 * Aucteeno Search Block - Server-Side Render
 *
 * Renders the unexpanded search trigger box with placeholder text and data attributes.
 * The modal itself is built client-side by view.js on first focus.
 *
 * @package Aucteeno
 * @since 2.0.0
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (unused).
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use The_Another\Plugin\Aucteeno\Container;
use The_Another\Plugin\Aucteeno\Services\Search_Block_Service;
use The_Another\Plugin\Aucteeno\Services\Search_Count_Provider;

// Defensive: render.php may be invoked in contexts where Aucteeno hasn't booted
// (e.g. plugin active but WooCommerce inactive, REST preview, WP-CLI cache warmups).
// In those cases, render an empty placeholder rather than fataling.
try {
	$container = Container::get_instance();

	/** @var Search_Count_Provider $count_provider */
	$count_provider = $container->get( 'search_count_provider' );

	/** @var Search_Block_Service $block_service */
	$block_service = $container->get( 'search_block_service' );
} catch ( \Throwable $e ) {
	return '';
}

$default_type         = ( 'auctions' === ( $attributes['defaultType'] ?? 'items' ) ) ? 'auctions' : 'items';
$debounce_preset      = (string) ( $attributes['debouncePreset'] ?? 'normal' );
$count_cache_minutes  = max( 0, (int) ( $attributes['countCacheMinutes'] ?? 5 ) );
$recent_timeout_sec   = max( 1, min( 60, (int) ( $attributes['recentSearchTimeoutSec'] ?? 10 ) ) );
$placeholder_template = (string) ( $attributes['placeholderTemplate'] ?? '%d items to search from' );
$items_page_id        = (int) ( $attributes['viewAllItemsPageId'] ?? 0 );
$auctions_page_id     = (int) ( $attributes['viewAllAuctionsPageId'] ?? 0 );

$debounce_ms_map = array(
	'instant' => 0,
	'fast'    => 150,
	'normal'  => 250,
	'relaxed' => 500,
);
$debounce_ms     = $debounce_ms_map[ $debounce_preset ] ?? 250;

$count       = $count_provider->get_running_upcoming_items_count( $count_cache_minutes );
// Token replacement (not sprintf) so the already-formatted, comma-separated count survives intact;
// sprintf( '%d', '5,289' ) would parse to 5.
$placeholder = str_replace( '%d', number_format_i18n( $count ), $placeholder_template );

$items_opts    = $block_service->get_page_options( $items_page_id );
$auctions_opts = $block_service->get_page_options( $auctions_page_id );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'                  => 'wp-block-aucteeno-search',
		'data-default-type'      => $default_type,
		'data-debounce-ms'       => (string) $debounce_ms,
		'data-recent-timeout-sec' => (string) $recent_timeout_sec,
		'data-items-per-page'    => (string) $items_opts['perPage'],
		'data-items-order-by'    => $items_opts['orderBy'],
		'data-items-page-url'    => $items_opts['pageUrl'],
		'data-auctions-per-page' => (string) $auctions_opts['perPage'],
		'data-auctions-order-by' => $auctions_opts['orderBy'],
		'data-auctions-page-url' => $auctions_opts['pageUrl'],
		'data-rest-root'         => esc_url_raw( rest_url( 'aucteeno/v1/' ) ),
		'data-rest-nonce'        => wp_create_nonce( 'wp_rest' ),
	)
);
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes returns escaped output ?>>
	<button type="button"
			class="wp-block-aucteeno-search__trigger"
			aria-label="<?php esc_attr_e( 'Open search', 'aucteeno' ); ?>"
			aria-haspopup="dialog"
			data-original-placeholder="<?php echo esc_attr( $placeholder ); ?>">
		<span class="wp-block-aucteeno-search__placeholder"><?php echo esc_html( $placeholder ); ?></span>
		<span class="wp-block-aucteeno-search__submit" aria-hidden="true">
			<svg class="wp-block-aucteeno-search__icon" aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 1 0-.7.7l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"/></svg>
		</span>
	</button>
</div>
