<?php
/**
 * Block_Data_Helper Tests
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Helpers;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Helpers\Block_Data_Helper;

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

/**
 * Class Block_Data_Helper_Test
 */
class Block_Data_Helper_Test extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Helper: build a mock $wpdb and product for a standard get_item_data() call.
	 *
	 * @return array{wpdb: \Mockery\MockInterface, product: \Mockery\MockInterface}
	 */
	private function setup_standard_mocks(): array {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$product = Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'get_type' )->andReturn( 'aucteeno-ext-auction' );
		$product->shouldReceive( 'get_price' )->andReturn( '100' );
		$product->shouldReceive( 'get_id' )->andReturn( 5 );

		$wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
		$wpdb->shouldReceive( 'get_row' )->andReturn( array(
			'id'                   => 5,
			'user_id'              => 1,
			'bidding_status'       => 10,
			'bidding_starts_at'    => 1000,
			'bidding_ends_at'      => 9999,
			'location_country'     => 'US',
			'location_subdivision' => '',
			'location_city'        => '',
		) );

		$post             = new \stdClass();
		$post->post_title = 'Test Auction';
		Functions\when( 'get_the_ID' )->justReturn( 5 );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/auction/test/' );
		Functions\when( 'wp_get_attachment_image_src' )->justReturn( false );

		return array( 'wpdb' => $wpdb, 'product' => $product );
	}

	/**
	 * Test that get_item_data includes image_id from post meta.
	 *
	 * @return void
	 */
	public function test_get_item_data_includes_image_id_field(): void {
		$mocks = $this->setup_standard_mocks();
		Functions\when( 'wc_get_product' )->justReturn( $mocks['product'] );
		Functions\when( 'get_post_meta' )
			->alias( function ( $id, $key = null, $single = false ) {
				return '_thumbnail_id' === $key ? '88' : '';
			} );

		$data = Block_Data_Helper::get_item_data( 5 );

		$this->assertNotNull( $data );
		$this->assertArrayHasKey( 'image_id', $data );
		$this->assertSame( 88, $data['image_id'] );
	}

	/**
	 * Test that get_item_data does not call wc_get_product a second time for image.
	 *
	 * @return void
	 */
	public function test_get_item_data_does_not_call_wc_get_product_for_image(): void {
		$mocks = $this->setup_standard_mocks();

		// wc_get_product should be called EXACTLY ONCE (for type-check + price),
		// not a second time for the image.
		Functions\expect( 'wc_get_product' )
			->once()
			->andReturn( $mocks['product'] );

		Functions\when( 'get_post_meta' )->justReturn( '' );

		Block_Data_Helper::get_item_data( 5 );

		$this->addToAssertionCount( 1 );
	}
}
