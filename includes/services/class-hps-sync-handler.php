<?php
/**
 * HPS Sync Handler Class
 *
 * Handles WordPress lifecycle hooks to sync products to HPS tables.
 *
 * @package Aucteeno
 * @since 2.0.0
 */

namespace The_Another\Plugin\Aucteeno\Services;

use The_Another\Plugin\Aucteeno\Hook_Manager;
use The_Another\Plugin\Aucteeno\Product_Types\Product_Auction;
use The_Another\Plugin\Aucteeno\Product_Types\Product_Item;
use WP_Post;

/**
 * Class HPS_Sync_Handler
 *
 * Hook handler class that registers WordPress lifecycle hooks.
 */
class HPS_Sync_Handler {

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
	 * Initialize hook handlers.
	 *
	 * @return void
	 */
	public function init(): void {
		// Sync on create/update (priority 20, after product save).
		$this->hook_manager->register_action(
			'save_post',
			array( $this, 'sync_on_save' ),
			20,
			2
		);

		// Delete from HPS before permanent deletion.
		$this->hook_manager->register_action(
			'before_delete_post',
			array( $this, 'delete_on_delete' ),
			10,
			1
		);

		// Delete from HPS when trashed.
		$this->hook_manager->register_action(
			'wp_trash_post',
			array( $this, 'delete_on_trash' ),
			10,
			1
		);

		// Recreate in HPS when restored from trash.
		$this->hook_manager->register_action(
			'untrash_post',
			array( $this, 'sync_on_untrash' ),
			10,
			1
		);
	}

	/**
	 * Sync product to HPS table on save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function sync_on_save( int $post_id, $post ): void {
		// Only for product post type.
		if ( 'product' !== $post->post_type ) {
			return;
		}

		// Skip autosaves and revisions.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			return;
		}

		// Sync auction products.
		if ( $product instanceof Product_Auction ) {
			HPS_Sync_Auction::sync_auction( $post_id );
			return;
		}

		// Sync item products.
		if ( $product instanceof Product_Item ) {
			HPS_Sync_Item::sync_item( $post_id );
			return;
		}
	}

	/**
	 * Delete product from HPS table before permanent deletion.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function delete_on_delete( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}

		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			return;
		}

		// Delete auction products.
		if ( $product instanceof Product_Auction ) {
			HPS_Sync_Auction::delete_auction( $post_id );
			return;
		}

		// Delete item products.
		if ( $product instanceof Product_Item ) {
			HPS_Sync_Item::delete_item( $post_id );
			return;
		}
	}

	/**
	 * Delete product from HPS table when trashed.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function delete_on_trash( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}

		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			return;
		}

		// Delete auction products.
		if ( $product instanceof Product_Auction ) {
			HPS_Sync_Auction::delete_auction( $post_id );
			return;
		}

		// Delete item products.
		if ( $product instanceof Product_Item ) {
			HPS_Sync_Item::delete_item( $post_id );
			return;
		}
	}

	/**
	 * Sync product to HPS table when restored from trash.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function sync_on_untrash( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}

		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			return;
		}

		// Sync auction products.
		if ( $product instanceof Product_Auction ) {
			HPS_Sync_Auction::sync_auction( $post_id );
			return;
		}

		// Sync item products.
		if ( $product instanceof Product_Item ) {
			HPS_Sync_Item::sync_item( $post_id );
			return;
		}
	}
}
