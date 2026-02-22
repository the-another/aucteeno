<?php
/**
 * Block Data Helper Class
 *
 * Provides utility methods for building block context data from current post.
 *
 * @package Aucteeno
 * @since 1.0.3
 */

namespace TheAnother\Plugin\Aucteeno\Helpers;

use TheAnother\Plugin\Aucteeno\Database\Database_Auctions;
use TheAnother\Plugin\Aucteeno\Database\Database_Items;

/**
 * Class Block_Data_Helper
 *
 * Helper methods for building block data from current post context.
 */
class Block_Data_Helper {

	/**
	 * Get item data for blocks from current post or specified post ID.
	 *
	 * This function builds the same data structure used by the query loop
	 * for use in single auction/item pages or anywhere outside the query context.
	 *
	 * @param int|null $post_id Optional post ID. If null, uses current post.
	 * @return array|null Item data array or null if not an auction/item.
	 */
	public static function get_item_data( ?int $post_id = null ): ?array {
		global $wpdb;

		// Get post ID.
		if ( null === $post_id ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return null;
		}

		// Get product.
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return null;
		}

		// Check if it's an auction or item product type.
		$product_type = $product->get_type();
		if ( ! in_array( $product_type, array( 'aucteeno-ext-auction', 'aucteeno-ext-item' ), true ) ) {
			return null;
		}

		// Determine if auction or item.
		$is_auction = 'aucteeno-ext-auction' === $product_type;

		// Get data from HPS tables.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		if ( $is_auction ) {
			$table_name = Database_Auctions::get_table_name();
			$query      = $wpdb->prepare(
				"SELECT
					auction_id AS id,
					user_id,
					bidding_status,
					bidding_starts_at,
					bidding_ends_at,
					location_country,
					location_subdivision,
					location_city
				FROM {$table_name}
				WHERE auction_id = %d",
				$post_id
			);
		} else {
			$table_name = Database_Items::get_table_name();
			$query      = $wpdb->prepare(
				"SELECT
					item_id AS id,
					auction_id,
					user_id,
					bidding_status,
					bidding_starts_at,
					bidding_ends_at,
					location_country,
					location_subdivision,
					location_city,
					lot_no
				FROM {$table_name}
				WHERE item_id = %d",
				$post_id
			);
		}

		$row = $wpdb->get_row( $query, ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		if ( ! $row ) {
			return null;
		}

		// Get post data.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		// Get image URL via WooCommerce product (respects Nexus image overrides).
		$image_url = '';
		$product   = wc_get_product( $post_id );
		if ( $product ) {
			$image_id = $product->get_image_id();
			if ( $image_id ) {
				$image_src = wp_get_attachment_image_src( $image_id, 'medium' );
				if ( $image_src ) {
					$image_url = $image_src[0];
				}
			}
		}

		// Build item data array matching query loop structure.
		$item_data = array(
			'id'                   => (int) $row['id'],
			'title'                => $post->post_title,
			'permalink'            => get_permalink( $post_id ),
			'image_url'            => $image_url,
			'user_id'              => (int) $row['user_id'],
			'bidding_status'       => (int) $row['bidding_status'],
			'bidding_starts_at'    => (int) $row['bidding_starts_at'],
			'bidding_ends_at'      => (int) $row['bidding_ends_at'],
			'location_country'     => $row['location_country'],
			'location_subdivision' => $row['location_subdivision'],
			'location_city'        => $row['location_city'],
			'current_bid'          => (float) $product->get_price(),
			'reserve_price'        => method_exists( $product, 'get_reserve_price' ) ? (float) $product->get_reserve_price() : 0,
		);

		// Add item-specific fields.
		if ( ! $is_auction && isset( $row['lot_no'] ) ) {
			$item_data['lot_no']     = $row['lot_no'];
			$item_data['auction_id'] = (int) $row['auction_id'];
		}

		return $item_data;
	}
}
