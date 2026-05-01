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
 * ReflectionMethod. The helper now consumes HPS-shaped rows
 * (id, title, image_url, bidding_ends_at, permalink) directly.
 */
class REST_Controller_Search_Test extends TestCase {

	private REST_Controller $controller;
	private ReflectionMethod $method;

	protected function setUp(): void {
		parent::setUp();
		Monkey\tearDown();
		Mockery::close();
		Monkey\setUp();
		// Default to no attachment so the projection falls back to the row's image_url.
		// Individual tests can override with their own Functions\when() expectation.
		Functions\when( 'wp_get_attachment_image_src' )->justReturn( false );
		$this->controller = new REST_Controller();
		$this->method     = new ReflectionMethod( REST_Controller::class, 'project_search_row' );
		$this->method->setAccessible( true );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	public function test_projects_hps_row_to_compact_shape(): void {
		$row = array(
			'id'              => 42,
			'title'           => 'Test Auction',
			'permalink'       => 'https://example.com/auction/test/',
			'image_url'       => 'https://example.com/wp-content/uploads/thumb.jpg',
			'image_id'        => 7,
			'bidding_ends_at' => 1800000000,
			'bidding_status'  => 10,
		);

		$result = $this->method->invoke( $this->controller, $row );

		$keys = array_keys( $result );
		sort( $keys );
		$this->assertSame( array( 'ends_at', 'id', 'image_url', 'location', 'permalink', 'title' ), $keys );

		$this->assertSame( 42, $result['id'] );
		$this->assertSame( 'Test Auction', $result['title'] );
		$this->assertSame( 'https://example.com/wp-content/uploads/thumb.jpg', $result['image_url'] );
		$this->assertSame( 1800000000, $result['ends_at'] );
		$this->assertSame( 'https://example.com/auction/test/', $result['permalink'] );
	}

	public function test_falls_back_to_wc_placeholder_when_image_url_empty(): void {
		$row = array(
			'id'              => 99,
			'title'           => 'No Image Item',
			'permalink'       => 'https://example.com/item/no-image/',
			'image_url'       => '',
			'bidding_ends_at' => 1900000000,
		);

		Functions\when( 'wc_placeholder_img_src' )
			->justReturn( 'https://example.com/woocommerce-placeholder.png' );

		$result = $this->method->invoke( $this->controller, $row );

		$this->assertSame( 'https://example.com/woocommerce-placeholder.png', $result['image_url'] );
	}

	public function test_falls_back_to_wc_placeholder_when_image_url_missing(): void {
		$row = array(
			'id'              => 100,
			'title'           => 'Missing Image Field',
			'permalink'       => 'https://example.com/item/no-key/',
			'bidding_ends_at' => 0,
		);

		Functions\when( 'wc_placeholder_img_src' )
			->justReturn( 'https://example.com/wc-placeholder.png' );

		$result = $this->method->invoke( $this->controller, $row );

		$this->assertSame( 'https://example.com/wc-placeholder.png', $result['image_url'] );
	}

	public function test_handles_auction_rows_identically(): void {
		$auction_row = array(
			'id'              => 201,
			'title'           => 'Spring Farm Auction',
			'permalink'       => 'https://example.com/auction/spring-farm/',
			'image_url'       => 'https://example.com/wp-content/uploads/farm.jpg',
			'bidding_ends_at' => 1750000000,
			'location_city'   => 'Iowa, US',
		);

		$result = $this->method->invoke( $this->controller, $auction_row );

		$keys = array_keys( $result );
		sort( $keys );
		$this->assertSame( array( 'ends_at', 'id', 'image_url', 'location', 'permalink', 'title' ), $keys );

		$this->assertSame( 201, $result['id'] );
		$this->assertSame( 'Spring Farm Auction', $result['title'] );
		$this->assertSame( 'https://example.com/wp-content/uploads/farm.jpg', $result['image_url'] );
		$this->assertSame( 1750000000, $result['ends_at'] );
	}

	public function test_handles_zero_id_row(): void {
		$row = array(
			'id'              => 0,
			'title'           => 'Ghost Row',
			'permalink'       => '',
			'image_url'       => '',
			'bidding_ends_at' => 0,
		);

		Functions\when( 'wc_placeholder_img_src' )
			->justReturn( 'https://example.com/placeholder.png' );

		$result = $this->method->invoke( $this->controller, $row );

		$this->assertSame( 0, $result['id'] );
		$this->assertSame( 'https://example.com/placeholder.png', $result['image_url'] );
	}
}
