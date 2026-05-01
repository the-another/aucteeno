<?php
/**
 * Tests for REST_Controller::project_search_row() — format=search_row projection.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace The_Another\Plugin\Aucteeno\Tests\REST_API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use The_Another\Plugin\Aucteeno\REST_API\REST_Controller;

/**
 * Test class for format=search_row projection in REST_Controller.
 *
 * Tests are written against the private project_search_row() helper via
 * ReflectionMethod — this keeps them fast and free of WP_Query/WP_REST_Request
 * orchestration while exercising exactly the logic that matters.
 */
class REST_Controller_Search_Test extends TestCase {

	/**
	 * REST Controller instance.
	 *
	 * @var REST_Controller
	 */
	private REST_Controller $controller;

	/**
	 * Reflected project_search_row method (made accessible).
	 *
	 * @var ReflectionMethod
	 */
	private ReflectionMethod $method;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\tearDown();
		Mockery::close();
		Monkey\setUp();
		$this->controller = new REST_Controller();
		$this->method     = new ReflectionMethod( REST_Controller::class, 'project_search_row' );
		$this->method->setAccessible( true );
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	// ==========================================
	// project_search_row() TESTS
	// ==========================================

	/**
	 * Test format=search_row projects items/auctions to the compact search-row shape.
	 *
	 * Verifies that the projection produces exactly the five keys
	 * { id, title, image_url, ends_at, permalink } and no others.
	 *
	 * @return void
	 */
	public function test_format_search_row_projects_items_to_compact_shape(): void {
		$row = array(
			'id'                        => 42,
			'name'                      => 'Test Auction',
			'permalink'                 => 'https://example.com/auction/test/',
			'status'                    => 'publish',
			'bidding_ends_at_timestamp' => 1800000000,
			'bidding_starts_at_utc'     => '2026-01-01T00:00:00Z',
			'location'                  => 'New York',
		);

		Functions\when( 'get_post_thumbnail_id' )
			->justReturn( 7 );

		Functions\when( 'wp_get_attachment_image_url' )
			->justReturn( 'https://example.com/wp-content/uploads/thumb.jpg' );

		$result = $this->method->invoke( $this->controller, $row );

		// Exactly these five keys — nothing else.
		$keys = array_keys( $result );
		sort( $keys );
		$this->assertSame( array( 'ends_at', 'id', 'image_url', 'permalink', 'title' ), $keys );

		// Values are correctly mapped.
		$this->assertSame( 42, $result['id'] );
		$this->assertSame( 'Test Auction', $result['title'] );
		$this->assertSame( 'https://example.com/wp-content/uploads/thumb.jpg', $result['image_url'] );
		$this->assertSame( 1800000000, $result['ends_at'] );
		$this->assertSame( 'https://example.com/auction/test/', $result['permalink'] );
	}

	/**
	 * Test format=search_row falls back to WooCommerce placeholder when no thumbnail.
	 *
	 * When get_post_thumbnail_id() returns 0, the helper must call
	 * wc_placeholder_img_src('thumbnail') and use its return as image_url.
	 *
	 * @return void
	 */
	public function test_format_search_row_uses_wc_placeholder_when_no_thumbnail(): void {
		$row = array(
			'id'                        => 99,
			'name'                      => 'No Image Item',
			'permalink'                 => 'https://example.com/auction/no-image/',
			'bidding_ends_at_timestamp' => 1900000000,
		);

		Functions\when( 'get_post_thumbnail_id' )
			->justReturn( 0 );

		Functions\when( 'wc_placeholder_img_src' )
			->justReturn( 'https://example.com/woocommerce-placeholder.png' );

		$result = $this->method->invoke( $this->controller, $row );

		$this->assertSame( 'https://example.com/woocommerce-placeholder.png', $result['image_url'] );
	}

	/**
	 * Test format=search_row also falls back to placeholder when image URL is empty string.
	 *
	 * wp_get_attachment_image_url() can return an empty string even when a
	 * thumbnail ID exists (e.g. attachment deleted). In that case the helper
	 * must still call wc_placeholder_img_src().
	 *
	 * @return void
	 */
	public function test_format_search_row_uses_placeholder_when_image_url_is_empty(): void {
		$row = array(
			'id'                        => 55,
			'name'                      => 'Broken Thumb',
			'permalink'                 => 'https://example.com/auction/broken/',
			'bidding_ends_at_timestamp' => 0,
		);

		Functions\when( 'get_post_thumbnail_id' )
			->justReturn( 12 );

		Functions\when( 'wp_get_attachment_image_url' )
			->justReturn( '' );

		Functions\when( 'wc_placeholder_img_src' )
			->justReturn( 'https://example.com/woocommerce-placeholder.png' );

		$result = $this->method->invoke( $this->controller, $row );

		$this->assertSame( 'https://example.com/woocommerce-placeholder.png', $result['image_url'] );
	}

	/**
	 * Test format=search_row compact shape for auction rows (same helper, same fields).
	 *
	 * product_to_auction_array() and product_to_item_array() both produce
	 * arrays with 'id', 'name', 'permalink', and 'bidding_ends_at_timestamp'.
	 * This test verifies the projection works equally well for auction rows.
	 *
	 * @return void
	 */
	public function test_format_search_row_handles_auctions_route_too(): void {
		$auction_row = array(
			'id'                        => 201,
			'name'                      => 'Spring Farm Auction',
			'permalink'                 => 'https://example.com/auction/spring-farm/',
			'status'                    => 'publish',
			'bidding_ends_at_timestamp' => 1750000000,
			'location'                  => 'Iowa, US',
			'notice'                    => 'Preview day Friday',
		);

		Functions\when( 'get_post_thumbnail_id' )
			->justReturn( 33 );

		Functions\when( 'wp_get_attachment_image_url' )
			->justReturn( 'https://example.com/wp-content/uploads/farm.jpg' );

		$result = $this->method->invoke( $this->controller, $auction_row );

		// Five keys, same as items.
		$keys = array_keys( $result );
		sort( $keys );
		$this->assertSame( array( 'ends_at', 'id', 'image_url', 'permalink', 'title' ), $keys );

		$this->assertSame( 201, $result['id'] );
		$this->assertSame( 'Spring Farm Auction', $result['title'] );
		$this->assertSame( 'https://example.com/wp-content/uploads/farm.jpg', $result['image_url'] );
		$this->assertSame( 1750000000, $result['ends_at'] );
		$this->assertSame( 'https://example.com/auction/spring-farm/', $result['permalink'] );
	}

	/**
	 * Test that id=0 rows skip get_post_thumbnail_id and fall through to placeholder.
	 *
	 * When the row's id is 0 (guard against invalid data), thumbnail lookup
	 * is skipped and the placeholder is used instead.
	 *
	 * @return void
	 */
	public function test_format_search_row_skips_thumbnail_lookup_for_zero_id(): void {
		$row = array(
			'id'                        => 0,
			'name'                      => 'Ghost Row',
			'permalink'                 => '',
			'bidding_ends_at_timestamp' => 0,
		);

		// get_post_thumbnail_id must NOT be called when id is 0.
		Functions\expect( 'get_post_thumbnail_id' )->never();

		Functions\when( 'wc_placeholder_img_src' )
			->justReturn( 'https://example.com/placeholder.png' );

		$result = $this->method->invoke( $this->controller, $row );

		$this->assertSame( 0, $result['id'] );
		$this->assertSame( 'https://example.com/placeholder.png', $result['image_url'] );
	}
}
