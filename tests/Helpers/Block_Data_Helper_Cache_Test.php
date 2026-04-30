<?php
/**
 * Block_Data_Helper request-level cache tests.
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
 * Class Block_Data_Helper_Cache_Test
 */
class Block_Data_Helper_Cache_Test extends TestCase {

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Reset static cache between tests via reflection.
		$ref  = new \ReflectionClass( Block_Data_Helper::class );
		$prop = $ref->getProperty( 'cache' );
		$prop->setAccessible( true );
		$prop->setValue( null, array() );
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test that a second call for the same post ID does not trigger a DB query.
	 *
	 * @return void
	 */
	public function test_second_call_hits_cache_and_skips_db_query(): void {
		$wpdb            = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$GLOBALS['wpdb'] = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$product = Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'get_type' )->andReturn( 'aucteeno-ext-auction' );
		$product->shouldReceive( 'get_price' )->andReturn( '100' );

		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'SQL' );
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn(
			array(
				'id'                   => 5,
				'user_id'              => 1,
				'bidding_status'       => 10,
				'bidding_starts_at'    => 1000,
				'bidding_ends_at'      => 9999,
				'location_country'     => 'US',
				'location_subdivision' => '',
				'location_city'        => '',
			)
		);

		$post             = new \stdClass();
		$post->post_title = 'Cached Auction';

		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/auction/cached/' );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'wp_get_attachment_image_src' )->justReturn( false );
		Functions\when( 'apply_filters' )->alias(
			function ( $tag, $value ) {
				return $value;
			}
		);

		$first  = Block_Data_Helper::get_item_data( 5 );
		$second = Block_Data_Helper::get_item_data( 5 );

		$this->assertNotNull( $first );
		$this->assertSame( $first, $second );
		$this->assertSame( 'Cached Auction', $first['title'] );
	}

	/**
	 * Test that null misses are cached (no repeated queries for non-auction posts).
	 *
	 * @return void
	 */
	public function test_null_results_are_cached(): void {
		Functions\when( 'wc_get_product' )->justReturn( false );

		Block_Data_Helper::get_item_data( 42 );
		Block_Data_Helper::get_item_data( 42 );

		$ref   = new \ReflectionClass( Block_Data_Helper::class );
		$prop  = $ref->getProperty( 'cache' );
		$prop->setAccessible( true );
		$cache = $prop->getValue( null );

		$this->assertArrayHasKey( 42, $cache );
		$this->assertNull( $cache[42] );
	}
}
