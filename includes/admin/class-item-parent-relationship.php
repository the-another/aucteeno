<?php
/**
 * Item Parent Relationship Class
 *
 * Handles synchronization between post_parent and items table auction_id.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Admin;

use TheAnother\Plugin\Aucteeno\Database\Database_Items;
use TheAnother\Plugin\Aucteeno\Hook_Manager;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Item;
use WP_Post;

/**
 * Class Item_Parent_Relationship
 *
 * Ensures data consistency between post_parent and items table auction_id.
 */
class Item_Parent_Relationship {

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
	 * Initialize parent relationship handling.
	 *
	 * @return void
	 */
	public function init(): void {
		// Sync when post is saved.
		$this->hook_manager->register_action(
			'save_post',
			array( $this, 'sync_parent_relationship' ),
			10,
			2
		);

		// Validate parent on save.
		$this->hook_manager->register_action(
			'save_post',
			array( $this, 'validate_parent_required' ),
			5,
			2
		);
	}

	/**
	 * Sync post_parent with items table auction_id.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function sync_parent_relationship( int $post_id, $post ): void {
		// Only for item products.
		if ( 'product' !== $post->post_type ) {
			return;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product || $product->get_type() !== Product_Item::PRODUCT_TYPE ) {
			return;
		}

		$parent_id = wp_get_post_parent_id( $post_id );
		if ( ! $parent_id ) {
			// Parent is required - this should be validated elsewhere.
			return;
		}

		// Update items table to sync auction_id with post_parent.
		global $wpdb;
		$table_name = Database_Items::get_table_name();

		$wpdb->update(
			$table_name,
			array( 'auction_id' => $parent_id ),
			array( 'item_id' => $post_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Validate that parent auction is required.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function validate_parent_required( int $post_id, $post ): void {
		// Only for item products.
		if ( 'product' !== $post->post_type ) {
			return;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product || $product->get_type() !== Product_Item::PRODUCT_TYPE ) {
			return;
		}

		// Skip validation if post is being deleted or moved to trash.
		if ( 'trash' === $post->post_status ) {
			return;
		}

		// Skip validation if we're in a delete context.
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( in_array( $action, array( 'delete', 'trash' ), true ) ) {
			return;
		}

		// Check if parent is being set.
		// Use aucteeno_item_parent_auction_id to avoid confusion with WordPress default parent_id.
		if ( isset( $_POST['aucteeno_item_parent_auction_id'] ) ) {
			$parent_id = absint( $_POST['aucteeno_item_parent_auction_id'] );
			if ( $parent_id <= 0 ) {
				// Prevent saving without parent.
				wp_die( esc_html__( 'Items must belong to exactly one auction. Please select a parent auction.', 'aucteeno' ) );
			}
		} else {
			// Check existing parent.
			$existing_parent = wp_get_post_parent_id( $post_id );
			if ( ! $existing_parent ) {
				// Prevent saving without parent.
				wp_die( esc_html__( 'Items must belong to exactly one auction. Please select a parent auction.', 'aucteeno' ) );
			}
		}
	}

	/**
	 * Ensure data consistency on item creation/update.
	 *
	 * @param int $item_id Item product ID.
	 * @param int $auction_id Parent auction ID.
	 * @return void
	 */
	public function ensure_consistency( int $item_id, int $auction_id ): void {
		global $wpdb;

		// Update post_parent.
		wp_update_post(
			array(
				'ID'          => $item_id,
				'post_parent' => $auction_id,
			)
		);

		// Update items table.
		$table_name = Database_Items::get_table_name();
		$existing   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$table_name} WHERE item_id = %d",
				$item_id
			)
		);

		if ( $existing ) {
			$wpdb->update(
				$table_name,
				array( 'auction_id' => $auction_id ),
				array( 'item_id' => $item_id ),
				array( '%d' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$table_name,
				array(
					'item_id'    => $item_id,
					'auction_id' => $auction_id,
				),
				array( '%d', '%d' )
			);
		}
	}
}
