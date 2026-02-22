<?php
/**
 * Disable Reviews and Comments Class
 *
 * Disables WooCommerce reviews and WordPress comments on auction and item product pages.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Admin;

use TheAnother\Plugin\Aucteeno\Hook_Manager;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Auction;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Item;

/**
 * Class Disable_Reviews_Comments
 *
 * Handles disabling reviews and comments for auction and item products.
 */
class Disable_Reviews_Comments {

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
	 * Initialize the class.
	 *
	 * @return void
	 */
	public function init(): void {
		// Disable comments on product save for auction and item products.
		$this->hook_manager->register_action(
			'save_post_product',
			array( $this, 'disable_comments_on_save' ),
			10,
			2
		);

		// Force comment status to closed for auction and item products.
		$this->hook_manager->register_filter(
			'get_default_comment_status',
			array( $this, 'force_comment_status_closed' ),
			10,
			3
		);

		// Remove reviews tab from product tabs for auction and item products.
		$this->hook_manager->register_filter(
			'woocommerce_product_tabs',
			array( $this, 'remove_reviews_tab' ),
			98
		);

		// Prevent comment form from displaying on auction and item product pages.
		$this->hook_manager->register_filter(
			'comments_open',
			array( $this, 'close_comments' ),
			10,
			2
		);

		// Disable WooCommerce reviews specifically.
		$this->hook_manager->register_filter(
			'woocommerce_product_reviews_enabled',
			array( $this, 'disable_woocommerce_reviews' ),
			10,
			2
		);

		// Hide existing comments on auction and item product pages.
		$this->hook_manager->register_filter(
			'wp_count_comments',
			array( $this, 'hide_comments_count' ),
			10,
			2
		);

		// Prevent comment queries from returning comments for auction and item products.
		$this->hook_manager->register_filter(
			'comments_clauses',
			array( $this, 'exclude_product_comments' ),
			10,
			2
		);
	}

	/**
	 * Check if product is auction or item type.
	 *
	 * @param int|\WC_Product|null $product Product ID or product object.
	 * @return bool True if product is auction or item type.
	 */
	private function is_auction_or_item_product( $product ): bool {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return false;
		}

		$product_type = $product->get_type();
		return in_array(
			$product_type,
			array(
				Product_Auction::PRODUCT_TYPE,
				Product_Item::PRODUCT_TYPE,
			),
			true
		);
	}

	/**
	 * Disable comments when saving auction or item products.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function disable_comments_on_save( int $post_id, $post ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by hook signature.
		if ( ! $this->is_auction_or_item_product( $post_id ) ) {
			return;
		}

		// Remove the action temporarily to avoid infinite loop.
		remove_action( 'save_post_product', array( $this, 'disable_comments_on_save' ), 10 );

		// Update comment status to closed.
		wp_update_post(
			array(
				'ID'             => $post_id,
				'comment_status' => 'closed',
			)
		);

		// Re-add the action.
		add_action( 'save_post_product', array( $this, 'disable_comments_on_save' ), 10, 2 );
	}

	/**
	 * Force comment status to closed for auction and item products.
	 *
	 * @param string  $status Default comment status.
	 * @param string  $post_type Post type.
	 * @param ?string $comment_type Comment type.
	 * @return string Comment status.
	 */
	public function force_comment_status_closed( string $status, string $post_type, ?string $comment_type = null ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by hook signature.
		if ( 'product' !== $post_type ) {
			return $status;
		}

		// Check if we're in a product context.
		global $post;
		if ( $post && isset( $post->ID ) ) {
			if ( $this->is_auction_or_item_product( $post->ID ) ) {
				return 'closed';
			}
		}

		return $status;
	}

	/**
	 * Remove reviews tab from product tabs for auction and item products.
	 *
	 * @param array<string, mixed> $tabs Product tabs.
	 * @return array<string, mixed> Modified product tabs.
	 */
	public function remove_reviews_tab( array $tabs ): array {
		global $product;

		if ( ! $product || ! $this->is_auction_or_item_product( $product ) ) {
			return $tabs;
		}

		// Remove reviews tab.
		if ( isset( $tabs['reviews'] ) ) {
			unset( $tabs['reviews'] );
		}

		return $tabs;
	}

	/**
	 * Close comments for auction and item products.
	 *
	 * @param bool $open Whether comments are open.
	 * @param int  $post_id Post ID.
	 * @return bool Whether comments are open.
	 */
	public function close_comments( bool $open, int $post_id ): bool {
		if ( $this->is_auction_or_item_product( $post_id ) ) {
			return false;
		}

		return $open;
	}

	/**
	 * Disable WooCommerce reviews for auction and item products.
	 *
	 * @param bool $enabled Whether reviews are enabled.
	 * @param int  $product_id Product ID.
	 * @return bool Whether reviews are enabled.
	 */
	public function disable_woocommerce_reviews( bool $enabled, int $product_id ): bool {
		if ( $this->is_auction_or_item_product( $product_id ) ) {
			return false;
		}

		return $enabled;
	}

	/**
	 * Hide comments count for auction and item products.
	 *
	 * @param \stdClass $counts Comment counts object.
	 * @param int       $post_id Post ID.
	 * @return \stdClass Modified comment counts.
	 */
	public function hide_comments_count( $counts, int $post_id ): \stdClass {
		if ( $this->is_auction_or_item_product( $post_id ) ) {
			// Return zero counts for all comment types.
			$counts->approved  = 0;
			$counts->moderated = 0;
			$counts->spam      = 0;
			$counts->trash     = 0;
			if ( isset( $counts->{'post-trashed'} ) ) {
				$counts->{'post-trashed'} = 0;
			}
			$counts->total_comments = 0;
			$counts->all            = 0;
		}

		return $counts;
	}

	/**
	 * Exclude comments from auction and item products in comment queries.
	 *
	 * @param array<string, string> $clauses Comment query clauses.
	 * @param \WP_Comment_Query     $query Comment query object.
	 * @return array<string, string> Modified comment query clauses.
	 */
	public function exclude_product_comments( array $clauses, $query ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by hook signature.
		global $wpdb;

		// Get post IDs for auction and item products.
		$auction_type = Product_Auction::PRODUCT_TYPE;
		$item_type    = Product_Item::PRODUCT_TYPE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$product_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = 'product'
				AND pm.meta_key = '_product_type'
				AND pm.meta_value IN (%s, %s)",
				$auction_type,
				$item_type
			)
		);

		if ( ! empty( $product_ids ) ) {
			$product_ids_int    = array_map( 'intval', $product_ids );
			$product_ids_string = implode( ',', $product_ids_int );
			$clauses['where']  .= " AND {$wpdb->comments}.comment_post_ID NOT IN ($product_ids_string)";
		}

		return $clauses;
	}
}
