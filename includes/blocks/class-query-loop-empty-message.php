<?php
/**
 * Query Loop Empty Message service.
 *
 * Composes a filter-aware "no results" message for the query loop block
 * and passes it through the aucteeno_query_loop_no_results filter.
 *
 * @package Aucteeno
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno\Blocks;

use The_Another\Plugin\Aucteeno\Hook_Manager;

/**
 * Class Query_Loop_Empty_Message
 *
 * Container-managed service that builds the empty state message
 * for the query loop block, including active filter details.
 */
final class Query_Loop_Empty_Message {

	/**
	 * Hook manager instance.
	 *
	 * @var Hook_Manager
	 */
	private Hook_Manager $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param Hook_Manager $hook_manager Hook manager instance.
	 */
	public function __construct( Hook_Manager $hook_manager ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Build a filter-aware empty state message.
	 *
	 * Composes a human-readable sentence from the query type and active filters,
	 * then passes it through the aucteeno_query_loop_no_results filter hook.
	 *
	 * The $filters array should contain both raw values (for the hook) and
	 * pre-resolved display labels (for message composition):
	 *
	 * Raw values (passed to hook for extension developers):
	 * - 'country'     => string  2-letter ISO code or ''
	 * - 'subdivision' => string  'COUNTRY:STATE' format or ''
	 * - 'search'      => string  Search term or ''
	 * - 'user_id'     => int     Seller user ID or 0
	 * - 'auction_id'  => int     Parent auction post ID or 0
	 *
	 * Display labels (used for message composition):
	 * - 'location_label' => string  Pre-resolved location name (e.g. "Canada", "Kansas, US") or ''
	 * - 'seller_name'    => string  Seller display name or ''
	 * - 'auction_title'  => string  Parent auction title or ''
	 *
	 * @param string $query_type 'auctions' or 'items'.
	 * @param array  $filters    Active filter values and display labels.
	 * @return string The composed message.
	 */
	public function get_message( string $query_type, array $filters ): string {
		// Base message.
		$base = 'auctions' === $query_type
			? __( 'No auctions found', 'aucteeno' )
			: __( 'No items found', 'aucteeno' );

		// Collect fragments.
		$fragments = array();

		// Search fragment.
		$search = trim( $filters['search'] ?? '' );
		if ( '' !== $search ) {
			/* translators: %s: search term */
			$fragments[] = sprintf( __( 'for term "%s"', 'aucteeno' ), $search );
		}

		// Location fragment (from pre-resolved label).
		$location_label = trim( $filters['location_label'] ?? '' );
		if ( '' !== $location_label ) {
			/* translators: %s: location name (e.g. "Canada" or "Kansas, US") */
			$fragments[] = sprintf( __( 'in %s', 'aucteeno' ), $location_label );
		}

		// Seller fragment (from pre-resolved name).
		$seller_name = trim( $filters['seller_name'] ?? '' );
		if ( '' !== $seller_name ) {
			/* translators: %s: seller display name */
			$fragments[] = sprintf( __( 'by %s', 'aucteeno' ), $seller_name );
		}

		// Parent auction fragment (from pre-resolved title).
		$auction_title = trim( $filters['auction_title'] ?? '' );
		if ( '' !== $auction_title ) {
			/* translators: %s: parent auction title */
			$fragments[] = sprintf( __( 'within %s', 'aucteeno' ), $auction_title );
		}

		// Compose sentence.
		$message = $base;
		if ( ! empty( $fragments ) ) {
			$message .= ' ' . implode( ' ', $fragments );
		}
		$message .= '.';

		/**
		 * Filters the "no results" message for the Aucteeno Query Loop block.
		 *
		 * Fires after the message is composed from active filters, before output.
		 * The $filters array contains both raw values (ISO codes, user IDs, post IDs)
		 * and pre-resolved display labels.
		 *
		 * @param string $message    The composed message string.
		 * @param array  $filters    The structured filters array.
		 * @param string $query_type 'auctions' or 'items'.
		 */
		return apply_filters( 'aucteeno_query_loop_no_results', $message, $filters, $query_type );
	}
}
