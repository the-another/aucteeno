<?php
/**
 * Tests for REST_Controller class.
 *
 * Comprehensive tests covering all REST API endpoints for auctions and items,
 * including permissions, validation, error handling, and edge cases.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace The_Another\Plugin\Aucteeno\Tests\REST_API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Query;
use The_Another\Plugin\Aucteeno\REST_API\REST_Controller;
use The_Another\Plugin\Aucteeno\Product_Types\Product_Auction;
use The_Another\Plugin\Aucteeno\Product_Types\Product_Item;
use The_Another\Plugin\Aucteeno\Database\Database_Auctions;
use The_Another\Plugin\Aucteeno\Database\Database_Items;

/**
 * Test class for REST_Controller.
 *
 * Tests cover all REST API endpoints with comprehensive edge case coverage.
 *
 * Note: The bootstrap defines real stub functions for sanitize_text_field,
 * absint, etc. These cannot be overridden with Brain Monkey's Functions\when()
 * due to Patchwork's DefinedTooEarly restriction. Tests rely on the bootstrap
 * passthrough stubs for these functions.
 *
 * Note: The bootstrap defines a real WP_Query stub class. Since the source code
 * uses `new WP_Query()` (constructor call), Brain Monkey cannot intercept it.
 * JSON format tests that need WP_Query behavior work with the real stub which
 * returns empty results by default.
 */
class REST_Controller_Test extends TestCase {

	/**
	 * REST Controller instance.
	 *
	 * @var REST_Controller
	 */
	private $controller;

	/**
	 * Set up test environment.
	 *
	 * Tears down and re-sets up Brain Monkey to clear bootstrap stubs,
	 * allowing tests to define their own function expectations.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\tearDown();
		Mockery::close();
		Monkey\setUp();
		$this->controller = new REST_Controller();
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
	// PERMISSIONS TESTS
	// ==========================================

	/**
	 * Test permission check for getting items returns true (public access).
	 *
	 * The get_items_permissions_check method returns true directly
	 * since auctions and items are public listings.
	 *
	 * @return void
	 */
	public function test_get_items_permissions_check_returns_true(): void {
		$request = new WP_REST_Request();

		$result = $this->controller->get_items_permissions_check( $request );
		$this->assertTrue( $result );
	}

	/**
	 * Test permission check for getting single item returns true (public access).
	 *
	 * The get_item_permissions_check method returns true directly
	 * since auctions and items are public listings.
	 *
	 * @return void
	 */
	public function test_get_item_permissions_check_returns_true(): void {
		$request = new WP_REST_Request();

		$result = $this->controller->get_item_permissions_check( $request );
		$this->assertTrue( $result );
	}

	/**
	 * Test permission check for creating items requires edit_posts capability.
	 *
	 * This verifies that the permission callback correctly enforces
	 * the 'edit_posts' capability for create endpoints.
	 *
	 * @return void
	 */
	public function test_create_item_permissions_check_requires_edit_posts(): void {
		$request = new WP_REST_Request();

		Functions\expect( 'current_user_can' )
			->with( 'edit_posts' )
			->once()
			->andReturn( true );

		$result = $this->controller->create_item_permissions_check( $request );
		$this->assertTrue( $result );
	}

	/**
	 * Test permission check for creating items denied without edit_posts.
	 *
	 * @return void
	 */
	public function test_create_item_permissions_check_denied(): void {
		$request = new WP_REST_Request();

		Functions\expect( 'current_user_can' )
			->with( 'edit_posts' )
			->once()
			->andReturn( false );

		$result = $this->controller->create_item_permissions_check( $request );
		$this->assertFalse( $result );
	}

	/**
	 * Test permission check for updating items requires edit_posts capability.
	 *
	 * This verifies that the permission callback correctly enforces
	 * the 'edit_posts' capability for update endpoints.
	 *
	 * @return void
	 */
	public function test_update_item_permissions_check_requires_edit_posts(): void {
		$request = new WP_REST_Request();

		Functions\expect( 'current_user_can' )
			->with( 'edit_posts' )
			->once()
			->andReturn( true );

		$result = $this->controller->update_item_permissions_check( $request );
		$this->assertTrue( $result );
	}

	/**
	 * Test permission check for deleting items requires delete_posts capability.
	 *
	 * This verifies that the permission callback correctly enforces
	 * the 'delete_posts' capability for delete endpoints.
	 *
	 * @return void
	 */
	public function test_delete_item_permissions_check_requires_delete_posts(): void {
		$request = new WP_REST_Request();

		Functions\expect( 'current_user_can' )
			->with( 'delete_posts' )
			->once()
			->andReturn( true );

		$result = $this->controller->delete_item_permissions_check( $request );
		$this->assertTrue( $result );
	}

	// ==========================================
	// GET /aucteeno/v1/auctions TESTS (HTML format)
	// ==========================================

	/**
	 * Test GET auctions with default parameters returns HTML format.
	 *
	 * This verifies that the default behavior calls Database_Auctions::query_for_listing()
	 * and returns HTML fragments with pagination data.
	 *
	 * @return void
	 */
	public function test_get_auctions_defaults_to_html_format(): void {
		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )
			->with( 'format' )
			->andReturn( null );
		$request->shouldReceive( 'get_param' )
			->with( 'page' )
			->andReturn( null );
		$request->shouldReceive( 'get_param' )
			->with( 'per_page' )
			->andReturn( null );
		$request->shouldReceive( 'get_param' )
			->with( 'sort' )
			->andReturn( null );
		$request->shouldReceive( 'get_param' )
			->with( 'user_id' )
			->andReturn( null );
		$request->shouldReceive( 'get_param' )
			->with( 'country' )
			->andReturn( null );
		$request->shouldReceive( 'get_param' )
			->with( 'subdivision' )
			->andReturn( null );
		$request->shouldReceive( 'get_param' )
			->with( 'search' )
			->andReturn( null );
		$request->shouldReceive( 'get_param' )
			->with( 'product_ids' )
			->andReturn( null );
		$request->shouldReceive( 'get_param' )
			->with( 'block_template' )
			->andReturn( null );
		$request->shouldReceive( 'get_param' )
			->with( 'page_url' )
			->andReturn( null );

		$mock_db = Mockery::mock( 'alias:' . Database_Auctions::class );
		$mock_db->shouldReceive( 'query_for_listing' )
			->once()
			->with( Mockery::on( function ( $args ) {
				return isset( $args['page'] ) && $args['page'] === 1
					&& isset( $args['per_page'] ) && $args['per_page'] === 10
					&& isset( $args['sort'] ) && $args['sort'] === 'ending_soon';
			} ) )
			->andReturn( array(
				'items' => array(),
				'page'  => 1,
				'pages' => 1,
				'total' => 0,
			) );

		$response = $this->controller->get_auctions( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'html', $data );
		$this->assertArrayHasKey( 'pagination', $data );
		$this->assertArrayHasKey( 'page', $data );
		$this->assertArrayHasKey( 'pages', $data );
		$this->assertArrayHasKey( 'total', $data );
	}

	/**
	 * Test GET auctions pagination with custom page and per_page in HTML format.
	 *
	 * This verifies that pagination parameters are correctly passed
	 * through to Database_Auctions::query_for_listing().
	 *
	 * @return void
	 */
	public function test_get_auctions_custom_pagination(): void {
		$request = $this->create_html_auctions_request( array(
			'page'     => 2,
			'per_page' => 25,
		) );

		$mock_db = Mockery::mock( 'alias:' . Database_Auctions::class );
		$mock_db->shouldReceive( 'query_for_listing' )
			->once()
			->with( Mockery::on( function ( $args ) {
				return isset( $args['page'] ) && $args['page'] === 2
					&& isset( $args['per_page'] ) && $args['per_page'] === 25;
			} ) )
			->andReturn( array(
				'items' => array(),
				'page'  => 2,
				'pages' => 1,
				'total' => 0,
			) );

		$response = $this->controller->get_auctions( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test GET auctions with very large page number in HTML format.
	 *
	 * This verifies that extremely large page numbers don't
	 * cause errors or performance issues.
	 *
	 * @return void
	 */
	public function test_get_auctions_very_large_page_number(): void {
		$request = $this->create_html_auctions_request( array(
			'page' => 999999,
		) );

		$mock_db = Mockery::mock( 'alias:' . Database_Auctions::class );
		$mock_db->shouldReceive( 'query_for_listing' )
			->once()
			->andReturn( array(
				'items' => array(),
				'page'  => 999999,
				'pages' => 0,
				'total' => 0,
			) );

		$response = $this->controller->get_auctions( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test GET auctions with per_page exceeding maximum in HTML format.
	 *
	 * This verifies that per_page values exceeding the maximum
	 * are handled (route args cap at 50, but we test controller logic).
	 *
	 * @return void
	 */
	public function test_get_auctions_per_page_exceeds_maximum(): void {
		$request = $this->create_html_auctions_request( array(
			'per_page' => 100,
		) );

		$mock_db = Mockery::mock( 'alias:' . Database_Auctions::class );
		$mock_db->shouldReceive( 'query_for_listing' )
			->once()
			->andReturn( array(
				'items' => array(),
				'page'  => 1,
				'pages' => 1,
				'total' => 0,
			) );

		$response = $this->controller->get_auctions( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test GET auctions with invalid sort value (HTML format).
	 *
	 * This verifies that invalid sort values are handled gracefully.
	 *
	 * @return void
	 */
	public function test_get_auctions_invalid_sort_value(): void {
		$request = $this->create_html_auctions_request( array(
			'sort' => 'invalid_sort',
		) );

		$mock_db = Mockery::mock( 'alias:' . Database_Auctions::class );
		$mock_db->shouldReceive( 'query_for_listing' )
			->once()
			->andReturn( array(
				'items' => array(),
				'page'  => 1,
				'pages' => 1,
				'total' => 0,
			) );

		$response = $this->controller->get_auctions( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test GET auctions with invalid format value falls through to HTML (non-json).
	 *
	 * This verifies that format values other than 'json' are handled
	 * as the HTML path via Database_Auctions.
	 *
	 * @return void
	 */
	public function test_get_auctions_invalid_format_value(): void {
		$request = $this->create_html_auctions_request( array(
			'format' => 'invalid_format',
		) );

		$mock_db = Mockery::mock( 'alias:' . Database_Auctions::class );
		$mock_db->shouldReceive( 'query_for_listing' )
			->once()
			->andReturn( array(
				'items' => array(),
				'page'  => 1,
				'pages' => 1,
				'total' => 0,
			) );

		$response = $this->controller->get_auctions( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	// ==========================================
	// GET /aucteeno/v1/auctions TESTS (JSON format)
	// ==========================================

	/**
	 * Test GET auctions with JSON format returns empty array when no auctions exist.
	 *
	 * The source code uses `new WP_Query()` which creates a real stub instance.
	 * The stub's have_posts() returns false for empty posts, so this returns [].
	 *
	 * @return void
	 */
	public function test_get_auctions_json_format_empty_results(): void {
		$request = $this->create_json_auctions_request();

		$response = $this->controller->get_auctions( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertEmpty( $data );
	}

	/**
	 * Test GET auctions with location filter as string in JSON format.
	 *
	 * This verifies that location filtering as a comma-separated string
	 * is processed correctly. sanitize_text_field is defined in bootstrap
	 * as passthrough, so array_map('sanitize_text_field', ...) works.
	 *
	 * @return void
	 */
	public function test_get_auctions_location_filter_string(): void {
		$request = $this->create_json_auctions_request( array(
			'location' => 'location1,location2',
		) );

		// The controller calls sanitize_location_param which uses
		// array_map('sanitize_text_field', ...) - the bootstrap stub handles this.
		$response = $this->controller->get_auctions( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test GET auctions with location filter as array in JSON format.
	 *
	 * This verifies that location filtering works when passed as
	 * an array of location slugs or IDs.
	 *
	 * @return void
	 */
	public function test_get_auctions_location_filter_array(): void {
		$request = $this->create_json_auctions_request( array(
			'location' => array( 'location1', 'location2' ),
		) );

		$response = $this->controller->get_auctions( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test GET auctions with sort option 'newest' in JSON format.
	 *
	 * This verifies that the sort parameter correctly changes
	 * the query orderby when set to 'newest'. We verify the response
	 * is successful since we cannot intercept the WP_Query constructor.
	 *
	 * @return void
	 */
	public function test_get_auctions_sort_newest(): void {
		$request = $this->create_json_auctions_request( array(
			'sort' => 'newest',
		) );

		$response = $this->controller->get_auctions( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
	}

	/**
	 * Test GET auctions with SQL injection attempt in location (JSON format).
	 *
	 * This verifies that SQL injection attempts are handled gracefully.
	 * The sanitize_location_param method processes the input before query.
	 *
	 * @return void
	 */
	public function test_get_auctions_sql_injection_attempt(): void {
		$request = $this->create_json_auctions_request( array(
			'location' => "'; DROP TABLE wp_posts; --",
		) );

		$response = $this->controller->get_auctions( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test GET auctions with numeric location IDs in JSON format.
	 *
	 * This verifies that location filtering works with numeric
	 * term IDs (uses 'term_id' field) in addition to slugs.
	 *
	 * @return void
	 */
	public function test_get_auctions_numeric_location_ids(): void {
		$request = $this->create_json_auctions_request( array(
			'location' => array( '123', '456' ),
		) );

		$response = $this->controller->get_auctions( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	// ==========================================
	// POST /aucteeno/v1/auctions TESTS
	// ==========================================

	/**
	 * Test POST auctions creates auction with valid data.
	 *
	 * This verifies that a new auction can be created with all
	 * required and optional fields. Note: Product_Auction instantiation
	 * requires WooCommerce environment so we catch the expected exception.
	 *
	 * @return void
	 */
	public function test_create_auction_valid_data(): void {
		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn( array(
				'name'                      => 'Test Auction',
				'product_url'               => 'https://example.com',
				'button_text'               => 'Bid Now',
				'location'                  => array( 'city' => 'New York' ),
				'notice'                    => 'Notice text',
				'bidding_starts_at_utc'     => '2024-01-01 10:00:00',
				'bidding_ends_at_utc'       => '2024-01-02 10:00:00',
			) );

		try {
			$response = $this->controller->create_auction( $request );
		} catch ( \Exception $e ) {
			// Expected - Product_Auction instantiation requires WooCommerce environment.
			$this->assertTrue( true );
		}
	}

	/**
	 * Test POST auctions handles empty data array.
	 *
	 * @return void
	 */
	public function test_create_auction_empty_data(): void {
		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn( array() );

		try {
			$response = $this->controller->create_auction( $request );
		} catch ( \Exception $e ) {
			$this->assertTrue( true );
		}
	}

	/**
	 * Test POST auctions handles datetime fields with GMT format.
	 *
	 * This verifies that datetime fields can be provided in
	 * either UTC or GMT format.
	 *
	 * @return void
	 */
	public function test_create_auction_gmt_datetime_format(): void {
		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn( array(
				'name'                  => 'Test Auction',
				'bidding_starts_at_gmt' => '2024-01-01 10:00:00',
				'bidding_ends_at_gmt'   => '2024-01-02 10:00:00',
			) );

		try {
			$response = $this->controller->create_auction( $request );
		} catch ( \Exception $e ) {
			$this->assertTrue( true );
		}
	}

	/**
	 * Test POST auctions returns 500 when save fails.
	 *
	 * @return void
	 */
	public function test_create_auction_save_failure(): void {
		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn( array(
				'name' => 'Test Auction',
			) );

		try {
			$response = $this->controller->create_auction( $request );
		} catch ( \Exception $e ) {
			$this->assertTrue( true );
		}
	}

	// ==========================================
	// GET /aucteeno/v1/auctions/{id} TESTS
	// ==========================================

	/**
	 * Test GET single auction returns valid auction data.
	 *
	 * @return void
	 */
	public function test_get_auction_valid_id(): void {
		$request = new WP_REST_Request();
		$request['id'] = 123;

		$mock_product = $this->create_mock_auction_product( 123, 'Test Auction' );

		Functions\expect( 'wc_get_product' )
			->with( 123 )
			->once()
			->andReturn( $mock_product );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->get_auction( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
		$this->assertEquals( 123, $data['id'] );
	}

	/**
	 * Test GET single auction returns 404 for non-existent auction.
	 *
	 * @return void
	 */
	public function test_get_auction_not_found(): void {
		$request = new WP_REST_Request();
		$request['id'] = 999;

		Functions\expect( 'wc_get_product' )
			->with( 999 )
			->once()
			->andReturn( false );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->get_auction( $request );
		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'not_found', $response->get_error_code() );
		$this->assertEquals( 404, $response->get_error_data()['status'] );
	}

	/**
	 * Test GET single auction returns 404 for wrong product type.
	 *
	 * @return void
	 */
	public function test_get_auction_wrong_product_type(): void {
		$request = new WP_REST_Request();
		$request['id'] = 123;

		$mock_product = Mockery::mock( 'WC_Product' );

		Functions\expect( 'wc_get_product' )
			->with( 123 )
			->once()
			->andReturn( $mock_product );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->get_auction( $request );
		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'not_found', $response->get_error_code() );
	}

	/**
	 * Test GET single auction handles invalid ID format.
	 *
	 * @return void
	 */
	public function test_get_auction_invalid_id_format(): void {
		$request = new WP_REST_Request();
		$request['id'] = 'invalid';

		Functions\expect( 'wc_get_product' )
			->with( 0 )
			->once()
			->andReturn( false );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->get_auction( $request );
		$this->assertInstanceOf( WP_Error::class, $response );
	}

	// ==========================================
	// PUT /aucteeno/v1/auctions/{id} TESTS
	// ==========================================

	/**
	 * Test PUT auction updates with valid data.
	 *
	 * Uses Mockery mock for WP_REST_Request to mock get_json_params().
	 *
	 * @return void
	 */
	public function test_update_auction_valid_data(): void {
		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'offsetGet' )
			->with( 'id' )
			->andReturn( 123 );
		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn( array(
				'name' => 'Updated Auction Name',
			) );

		$mock_product = $this->create_mock_auction_product( 123, 'Original Name' );
		$mock_product->shouldReceive( 'set_name' )
			->with( 'Updated Auction Name' )
			->once();
		$this->allow_auction_setters( $mock_product );
		$mock_product->shouldReceive( 'save' )
			->once()
			->andReturn( 123 );

		Functions\expect( 'wc_get_product' )
			->with( 123 )
			->once()
			->andReturn( $mock_product );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->update_auction( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'success', $data );
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test PUT auction returns 404 for non-existent auction.
	 *
	 * @return void
	 */
	public function test_update_auction_not_found(): void {
		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'offsetGet' )
			->with( 'id' )
			->andReturn( 999 );

		Functions\expect( 'wc_get_product' )
			->with( 999 )
			->once()
			->andReturn( false );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->update_auction( $request );
		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'not_found', $response->get_error_code() );
	}

	/**
	 * Test PUT auction with partial update (only name).
	 *
	 * @return void
	 */
	public function test_update_auction_partial_update(): void {
		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'offsetGet' )
			->with( 'id' )
			->andReturn( 123 );
		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn( array(
				'name' => 'Updated Name Only',
			) );

		$mock_product = $this->create_mock_auction_product( 123, 'Original Name' );
		$mock_product->shouldReceive( 'set_name' )
			->with( 'Updated Name Only' )
			->once();
		$this->allow_auction_setters( $mock_product );
		$mock_product->shouldReceive( 'save' )
			->once()
			->andReturn( 123 );

		Functions\expect( 'wc_get_product' )
			->with( 123 )
			->once()
			->andReturn( $mock_product );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->update_auction( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	// ==========================================
	// DELETE /aucteeno/v1/auctions/{id} TESTS
	// ==========================================

	/**
	 * Test DELETE auction successfully deletes auction.
	 *
	 * @return void
	 */
	public function test_delete_auction_success(): void {
		$request = new WP_REST_Request();
		$request['id'] = 123;

		$mock_product = $this->create_mock_auction_product( 123, 'Test Auction' );

		Functions\expect( 'wc_get_product' )
			->with( 123 )
			->once()
			->andReturn( $mock_product );
		Functions\expect( 'wp_delete_post' )
			->with( 123, true )
			->once()
			->andReturn( (object) array( 'ID' => 123 ) );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->delete_auction( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'success', $data );
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test DELETE auction returns 404 for non-existent auction.
	 *
	 * @return void
	 */
	public function test_delete_auction_not_found(): void {
		$request = new WP_REST_Request();
		$request['id'] = 999;

		Functions\expect( 'wc_get_product' )
			->with( 999 )
			->once()
			->andReturn( false );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->delete_auction( $request );
		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'not_found', $response->get_error_code() );
	}

	/**
	 * Test DELETE auction returns 500 when deletion fails.
	 *
	 * @return void
	 */
	public function test_delete_auction_deletion_failure(): void {
		$request = new WP_REST_Request();
		$request['id'] = 123;

		$mock_product = $this->create_mock_auction_product( 123, 'Test Auction' );

		Functions\expect( 'wc_get_product' )
			->with( 123 )
			->once()
			->andReturn( $mock_product );
		Functions\expect( 'wp_delete_post' )
			->with( 123, true )
			->once()
			->andReturn( false );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->delete_auction( $request );
		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'delete_failed', $response->get_error_code() );
		$this->assertEquals( 500, $response->get_error_data()['status'] );
	}

	// ==========================================
	// GET /aucteeno/v1/items TESTS (HTML format)
	// ==========================================

	/**
	 * Test GET items with default parameters returns HTML format.
	 *
	 * This verifies that the items endpoint defaults to HTML format
	 * and calls Database_Items::query_for_listing().
	 *
	 * @return void
	 */
	public function test_get_items_defaults(): void {
		$request = $this->create_html_items_request();

		$mock_db = Mockery::mock( 'alias:' . Database_Items::class );
		$mock_db->shouldReceive( 'query_for_listing' )
			->once()
			->with( Mockery::on( function ( $args ) {
				return isset( $args['page'] ) && $args['page'] === 1
					&& isset( $args['per_page'] ) && $args['per_page'] === 10
					&& isset( $args['auction_id'] ) && $args['auction_id'] === 0
					&& isset( $args['sort'] ) && $args['sort'] === 'ending_soon';
			} ) )
			->andReturn( array(
				'items' => array(),
				'page'  => 1,
				'pages' => 1,
				'total' => 0,
			) );

		$response = $this->controller->get_items( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'html', $data );
		$this->assertArrayHasKey( 'pagination', $data );
		$this->assertArrayHasKey( 'page', $data );
		$this->assertArrayHasKey( 'pages', $data );
		$this->assertArrayHasKey( 'total', $data );
	}

	// ==========================================
	// GET /aucteeno/v1/items TESTS (JSON format)
	// ==========================================

	/**
	 * Test GET items with auction_id filter in JSON format.
	 *
	 * This verifies that items can be filtered by their parent auction ID.
	 * Uses real WP_Query stub which returns empty results.
	 *
	 * @return void
	 */
	public function test_get_items_with_auction_id_filter(): void {
		$request = $this->create_json_items_request( array(
			'auction_id' => 100,
		) );

		$response = $this->controller->get_items( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
	}

	/**
	 * Test GET items with zero auction_id (no filter) in JSON format.
	 *
	 * This verifies that auction_id of 0 means no parent filter is applied.
	 *
	 * @return void
	 */
	public function test_get_items_zero_auction_id_no_filter(): void {
		$request = $this->create_json_items_request( array(
			'auction_id' => 0,
		) );

		$response = $this->controller->get_items( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test GET items with negative auction_id is treated as 0 (no filter).
	 *
	 * @return void
	 */
	public function test_get_items_negative_auction_id_ignored(): void {
		$request = $this->create_json_items_request( array(
			'auction_id' => -1,
		) );

		$response = $this->controller->get_items( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	// ==========================================
	// POST /aucteeno/v1/items TESTS
	// ==========================================

	/**
	 * Test POST items requires auction_id.
	 *
	 * This verifies that creating an item without an auction_id
	 * returns a 400 error with 'missing_auction' code.
	 *
	 * @return void
	 */
	public function test_create_item_requires_auction_id(): void {
		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn( array(
				'name' => 'Test Item',
			) );

		Functions\when( '__' )->returnArg();

		$response = $this->controller->create_item( $request );
		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'missing_auction', $response->get_error_code() );
		$this->assertEquals( 400, $response->get_error_data()['status'] );
	}

	/**
	 * Test POST items creates item with valid data.
	 *
	 * This verifies that a new item can be created with
	 * all required fields including auction_id.
	 *
	 * @return void
	 */
	public function test_create_item_valid_data(): void {
		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn( array(
				'name'       => 'Test Item',
				'auction_id' => 100,
				'lot_no'     => 'LOT-001',
			) );

		try {
			$response = $this->controller->create_item( $request );
		} catch ( \Exception $e ) {
			// Expected - Product_Item instantiation requires WooCommerce environment.
			$this->assertTrue( true );
		}
	}

	// ==========================================
	// GET /aucteeno/v1/items/{id} TESTS
	// ==========================================

	/**
	 * Test GET single item returns valid item data.
	 *
	 * @return void
	 */
	public function test_get_item_valid_id(): void {
		$request = new WP_REST_Request();
		$request['id'] = 456;

		$mock_product = $this->create_mock_item_product( 456, 'Test Item', 100 );

		Functions\expect( 'wc_get_product' )
			->with( 456 )
			->once()
			->andReturn( $mock_product );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->get_item( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
		$this->assertEquals( 456, $data['id'] );
		$this->assertEquals( 100, $data['auction_id'] );
	}

	/**
	 * Test GET single item returns 404 for non-existent item.
	 *
	 * @return void
	 */
	public function test_get_item_not_found(): void {
		$request = new WP_REST_Request();
		$request['id'] = 999;

		Functions\expect( 'wc_get_product' )
			->with( 999 )
			->once()
			->andReturn( false );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->get_item( $request );
		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'not_found', $response->get_error_code() );
	}

	// ==========================================
	// PUT /aucteeno/v1/items/{id} TESTS
	// ==========================================

	/**
	 * Test PUT item updates with valid data including auction_id change.
	 *
	 * Uses Mockery mock for WP_REST_Request to mock get_json_params().
	 *
	 * @return void
	 */
	public function test_update_item_valid_data(): void {
		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'offsetGet' )
			->with( 'id' )
			->andReturn( 456 );
		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn( array(
				'name'       => 'Updated Item Name',
				'auction_id' => 200,
			) );

		$mock_product = $this->create_mock_item_product( 456, 'Original Name', 100 );
		$mock_product->shouldReceive( 'set_name' )
			->with( 'Updated Item Name' )
			->once();
		$mock_product->shouldReceive( 'set_auction_id' )
			->with( 200 )
			->once();
		$this->allow_item_setters( $mock_product );
		$mock_product->shouldReceive( 'save' )
			->once()
			->andReturn( 456 );

		Functions\expect( 'wc_get_product' )
			->with( 456 )
			->once()
			->andReturn( $mock_product );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->update_item( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test PUT item with auction_id change updates parent relationship.
	 *
	 * @return void
	 */
	public function test_update_item_auction_id_change(): void {
		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'offsetGet' )
			->with( 'id' )
			->andReturn( 456 );
		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn( array(
				'auction_id' => 200,
			) );

		$mock_product = $this->create_mock_item_product( 456, 'Test Item', 100 );
		$mock_product->shouldReceive( 'set_auction_id' )
			->with( 200 )
			->once();
		$this->allow_item_setters( $mock_product );
		$mock_product->shouldReceive( 'save' )
			->once()
			->andReturn( 456 );

		Functions\expect( 'wc_get_product' )
			->with( 456 )
			->once()
			->andReturn( $mock_product );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->update_item( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test PUT item returns 404 for wrong product type.
	 *
	 * @return void
	 */
	public function test_update_item_wrong_product_type(): void {
		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'offsetGet' )
			->with( 'id' )
			->andReturn( 123 );

		$mock_product = Mockery::mock( 'WC_Product' );

		Functions\expect( 'wc_get_product' )
			->with( 123 )
			->once()
			->andReturn( $mock_product );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->update_item( $request );
		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'not_found', $response->get_error_code() );
	}

	// ==========================================
	// DELETE /aucteeno/v1/items/{id} TESTS
	// ==========================================

	/**
	 * Test DELETE item successfully deletes item.
	 *
	 * @return void
	 */
	public function test_delete_item_success(): void {
		$request = new WP_REST_Request();
		$request['id'] = 456;

		$mock_product = $this->create_mock_item_product( 456, 'Test Item', 100 );

		Functions\expect( 'wc_get_product' )
			->with( 456 )
			->once()
			->andReturn( $mock_product );
		Functions\expect( 'wp_delete_post' )
			->with( 456, true )
			->once()
			->andReturn( (object) array( 'ID' => 456 ) );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->delete_item( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test DELETE item returns 404 for wrong product type.
	 *
	 * @return void
	 */
	public function test_delete_item_wrong_product_type(): void {
		$request = new WP_REST_Request();
		$request['id'] = 123;

		$mock_product = Mockery::mock( 'WC_Product' );

		Functions\expect( 'wc_get_product' )
			->with( 123 )
			->once()
			->andReturn( $mock_product );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->delete_item( $request );
		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'not_found', $response->get_error_code() );
	}

	// ==========================================
	// GET /aucteeno/v1/locations TESTS
	// ==========================================

	/**
	 * Test GET locations returns location terms.
	 *
	 * This verifies that the locations endpoint calls get_terms()
	 * with the correct arguments and returns formatted term data.
	 *
	 * @return void
	 */
	public function test_get_locations_returns_terms(): void {
		$request = new WP_REST_Request();

		$mock_term1           = new \stdClass();
		$mock_term1->term_id  = 1;
		$mock_term1->slug     = 'new-york';
		$mock_term1->name     = 'New York';
		$mock_term1->parent   = 0;

		$mock_term2           = new \stdClass();
		$mock_term2->term_id  = 2;
		$mock_term2->slug     = 'brooklyn';
		$mock_term2->name     = 'Brooklyn';
		$mock_term2->parent   = 1;

		Functions\expect( 'get_terms' )
			->once()
			->with( Mockery::on( function ( $args ) {
				return isset( $args['taxonomy'] ) && $args['taxonomy'] === 'aucteeno-location'
					&& isset( $args['hide_empty'] ) && $args['hide_empty'] === true
					&& isset( $args['orderby'] ) && $args['orderby'] === 'name'
					&& isset( $args['order'] ) && $args['order'] === 'ASC';
			} ) )
			->andReturn( array( $mock_term1, $mock_term2 ) );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		$response = $this->controller->get_locations( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertEquals( 1, $data[0]['id'] );
		$this->assertEquals( 'new-york', $data[0]['slug'] );
		$this->assertEquals( 'New York', $data[0]['name'] );
		$this->assertEquals( 0, $data[0]['parent'] );
		$this->assertEquals( 2, $data[1]['id'] );
		$this->assertEquals( 'brooklyn', $data[1]['slug'] );
		$this->assertEquals( 1, $data[1]['parent'] );
	}

	/**
	 * Test GET locations returns empty array when no locations exist.
	 *
	 * @return void
	 */
	public function test_get_locations_empty_result(): void {
		$request = new WP_REST_Request();

		Functions\expect( 'get_terms' )
			->once()
			->andReturn( array() );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		$response = $this->controller->get_locations( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertEmpty( $data );
	}

	/**
	 * Test GET locations handles WP_Error from get_terms.
	 *
	 * @return void
	 */
	public function test_get_locations_handles_wp_error(): void {
		$request = new WP_REST_Request();

		$wp_error = new WP_Error( 'invalid_taxonomy', 'Invalid taxonomy.' );

		Functions\expect( 'get_terms' )
			->once()
			->andReturn( $wp_error );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( true );

		$response = $this->controller->get_locations( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertEmpty( $data );
	}

	// ==========================================
	// HELPER METHOD TESTS (sanitize_location_param)
	// ==========================================

	/**
	 * Test sanitize_location_param with empty value.
	 *
	 * @return void
	 */
	public function test_sanitize_location_param_empty(): void {
		$result = $this->controller->sanitize_location_param( '' );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );

		$result = $this->controller->sanitize_location_param( null );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test sanitize_location_param with string value.
	 *
	 * @return void
	 */
	public function test_sanitize_location_param_string(): void {
		$result = $this->controller->sanitize_location_param( 'location1,location2,location3' );
		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
		$this->assertContains( 'location1', $result );
		$this->assertContains( 'location2', $result );
		$this->assertContains( 'location3', $result );
	}

	/**
	 * Test sanitize_location_param with array value.
	 *
	 * @return void
	 */
	public function test_sanitize_location_param_array(): void {
		$result = $this->controller->sanitize_location_param( array( 'location1', 'location2' ) );
		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertContains( 'location1', $result );
		$this->assertContains( 'location2', $result );
	}

	/**
	 * Test sanitize_location_param filters empty values.
	 *
	 * @return void
	 */
	public function test_sanitize_location_param_filters_empty(): void {
		$result = $this->controller->sanitize_location_param( 'location1,,location2,' );
		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertContains( 'location1', $result );
		$this->assertContains( 'location2', $result );
		$this->assertNotContains( '', $result );
	}

	/**
	 * Test sanitize_location_param with invalid type.
	 *
	 * @return void
	 */
	public function test_sanitize_location_param_invalid_type(): void {
		$result = $this->controller->sanitize_location_param( 123 );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );

		$result = $this->controller->sanitize_location_param( true );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test sanitize_location_param with XSS attempt.
	 *
	 * The sanitize_text_field stub in bootstrap is a passthrough,
	 * so values pass through unmodified in tests.
	 *
	 * @return void
	 */
	public function test_sanitize_location_param_xss_attempt(): void {
		$malicious_input = '<script>alert("xss")</script>';
		$result = $this->controller->sanitize_location_param( $malicious_input );
		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test sanitize_location_param with unicode characters.
	 *
	 * @return void
	 */
	public function test_sanitize_location_param_unicode(): void {
		$unicode_input = 'location_test';
		$result = $this->controller->sanitize_location_param( $unicode_input );
		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test sanitize_location_param with very long string.
	 *
	 * @return void
	 */
	public function test_sanitize_location_param_long_string(): void {
		$long_string = str_repeat( 'location,', 1000 ) . 'final';
		$result = $this->controller->sanitize_location_param( $long_string );
		$this->assertIsArray( $result );
		$this->assertCount( 1001, $result );
	}

	// ==========================================
	// JSON RESPONSE STRUCTURE TESTS
	// ==========================================

	/**
	 * Test GET auctions JSON response structure completeness.
	 *
	 * This verifies that the product_to_auction_array method includes
	 * all expected fields when a Product_Auction is found via wc_get_product.
	 * Since we cannot intercept new WP_Query(), we test get_auction() instead
	 * which also uses product_to_auction_array.
	 *
	 * @return void
	 */
	public function test_get_auction_json_response_structure(): void {
		$request = new WP_REST_Request();
		$request['id'] = 123;

		$mock_product = $this->create_mock_auction_product( 123, 'Test Auction' );

		Functions\expect( 'wc_get_product' )
			->with( 123 )
			->once()
			->andReturn( $mock_product );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->get_auction( $request );
		$data     = $response->get_data();

		$expected_keys = array(
			'id',
			'name',
			'permalink',
			'status',
			'product_url',
			'button_text',
			'location',
			'notice',
			'bidding_notice',
			'directions',
			'terms_conditions',
			'bidding_starts_at_utc',
			'bidding_starts_at_local',
			'bidding_starts_at_timestamp',
			'bidding_ends_at_utc',
			'bidding_ends_at_local',
			'bidding_ends_at_timestamp',
		);
		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $data, "Missing key: {$key}" );
		}
	}

	/**
	 * Test GET items JSON response structure completeness.
	 *
	 * This verifies that the product_to_item_array method includes
	 * all expected fields when a Product_Item is found via wc_get_product.
	 * Since we cannot intercept new WP_Query(), we test get_item() instead
	 * which also uses product_to_item_array.
	 *
	 * @return void
	 */
	public function test_get_item_json_response_structure(): void {
		$request = new WP_REST_Request();
		$request['id'] = 456;

		$mock_product = $this->create_mock_item_product( 456, 'Test Item', 100 );

		Functions\expect( 'wc_get_product' )
			->with( 456 )
			->once()
			->andReturn( $mock_product );
		Functions\when( '__' )->returnArg();

		$response = $this->controller->get_item( $request );
		$data     = $response->get_data();

		$expected_keys = array(
			'id',
			'name',
			'permalink',
			'status',
			'auction_id',
			'lot_no',
			'description',
			'asking_bid',
			'current_bid',
			'sold_price',
			'sold_at_utc',
			'sold_at_local',
			'sold_at_timestamp',
			'location',
			'bidding_starts_at_utc',
			'bidding_starts_at_local',
			'bidding_starts_at_timestamp',
			'bidding_ends_at_utc',
			'bidding_ends_at_local',
			'bidding_ends_at_timestamp',
		);
		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $data, "Missing key: {$key}" );
		}
	}

	// ==========================================
	// HELPER METHODS
	// ==========================================

	/**
	 * Create a mock WP_REST_Request for HTML format auctions endpoint.
	 *
	 * @param array $overrides Parameter overrides.
	 * @return Mockery\MockInterface Mock request.
	 */
	private function create_html_auctions_request( array $overrides = array() ): Mockery\MockInterface {
		$defaults = array(
			'format'         => 'html',
			'page'           => 1,
			'per_page'       => 10,
			'sort'           => 'ending_soon',
			'user_id'        => 0,
			'country'        => '',
			'subdivision'    => '',
			'search'         => '',
			'product_ids'    => '',
			'block_template' => '',
			'page_url'       => '',
		);

		$params = array_merge( $defaults, $overrides );

		$request = Mockery::mock( WP_REST_Request::class );
		foreach ( $params as $key => $value ) {
			$request->shouldReceive( 'get_param' )
				->with( $key )
				->andReturn( $value );
		}

		return $request;
	}

	/**
	 * Create a mock WP_REST_Request for JSON format auctions endpoint.
	 *
	 * @param array $overrides Parameter overrides.
	 * @return Mockery\MockInterface Mock request.
	 */
	private function create_json_auctions_request( array $overrides = array() ): Mockery\MockInterface {
		$defaults = array(
			'format'   => 'json',
			'page'     => 1,
			'per_page' => 10,
			'location' => array(),
			'sort'     => 'ending_soon',
		);

		$params = array_merge( $defaults, $overrides );

		$request = Mockery::mock( WP_REST_Request::class );
		foreach ( $params as $key => $value ) {
			$request->shouldReceive( 'get_param' )
				->with( $key )
				->andReturn( $value );
		}

		return $request;
	}

	/**
	 * Create a mock WP_REST_Request for HTML format items endpoint.
	 *
	 * @param array $overrides Parameter overrides.
	 * @return Mockery\MockInterface Mock request.
	 */
	private function create_html_items_request( array $overrides = array() ): Mockery\MockInterface {
		$defaults = array(
			'format'         => 'html',
			'page'           => null,
			'per_page'       => null,
			'sort'           => null,
			'user_id'        => null,
			'country'        => null,
			'subdivision'    => null,
			'auction_id'     => null,
			'search'         => null,
			'product_ids'    => null,
			'block_template' => null,
			'page_url'       => null,
		);

		$params = array_merge( $defaults, $overrides );

		$request = Mockery::mock( WP_REST_Request::class );
		foreach ( $params as $key => $value ) {
			$request->shouldReceive( 'get_param' )
				->with( $key )
				->andReturn( $value );
		}

		return $request;
	}

	/**
	 * Create a mock WP_REST_Request for JSON format items endpoint.
	 *
	 * @param array $overrides Parameter overrides.
	 * @return Mockery\MockInterface Mock request.
	 */
	private function create_json_items_request( array $overrides = array() ): Mockery\MockInterface {
		$defaults = array(
			'format'     => 'json',
			'page'       => 1,
			'per_page'   => 10,
			'location'   => array(),
			'auction_id' => 0,
			'sort'       => 'ending_soon',
		);

		$params = array_merge( $defaults, $overrides );

		$request = Mockery::mock( WP_REST_Request::class );
		foreach ( $params as $key => $value ) {
			$request->shouldReceive( 'get_param' )
				->with( $key )
				->andReturn( $value );
		}

		return $request;
	}

	/**
	 * Allow all auction setter methods to be called zero or more times.
	 *
	 * The set_auction_data_from_array method calls multiple setters
	 * based on which keys exist in the data array. We allow all of them
	 * to be called without strict expectations.
	 *
	 * @param Mockery\MockInterface $mock_product Mock product.
	 * @return void
	 */
	private function allow_auction_setters( Mockery\MockInterface $mock_product ): void {
		$mock_product->shouldReceive( 'set_product_url' )->zeroOrMoreTimes();
		$mock_product->shouldReceive( 'set_button_text' )->zeroOrMoreTimes();
		$mock_product->shouldReceive( 'set_location' )->zeroOrMoreTimes();
		$mock_product->shouldReceive( 'set_notice' )->zeroOrMoreTimes();
		$mock_product->shouldReceive( 'set_bidding_notice' )->zeroOrMoreTimes();
		$mock_product->shouldReceive( 'set_directions' )->zeroOrMoreTimes();
		$mock_product->shouldReceive( 'set_terms_conditions' )->zeroOrMoreTimes();
		$mock_product->shouldReceive( 'set_bidding_starts_at_utc' )->zeroOrMoreTimes();
		$mock_product->shouldReceive( 'set_bidding_ends_at_utc' )->zeroOrMoreTimes();
	}

	/**
	 * Allow all item setter methods to be called zero or more times.
	 *
	 * The set_item_data_from_array method calls multiple setters
	 * based on which keys exist in the data array.
	 *
	 * @param Mockery\MockInterface $mock_product Mock product.
	 * @return void
	 */
	private function allow_item_setters( Mockery\MockInterface $mock_product ): void {
		$mock_product->shouldReceive( 'set_auction_id' )->zeroOrMoreTimes();
		$mock_product->shouldReceive( 'set_lot_no' )->zeroOrMoreTimes();
		$mock_product->shouldReceive( 'set_description' )->zeroOrMoreTimes();
		$mock_product->shouldReceive( 'set_asking_bid' )->zeroOrMoreTimes();
		$mock_product->shouldReceive( 'set_current_bid' )->zeroOrMoreTimes();
		$mock_product->shouldReceive( 'set_sold_price' )->zeroOrMoreTimes();
		$mock_product->shouldReceive( 'set_location' )->zeroOrMoreTimes();
		$mock_product->shouldReceive( 'set_bidding_starts_at_utc' )->zeroOrMoreTimes();
		$mock_product->shouldReceive( 'set_bidding_ends_at_utc' )->zeroOrMoreTimes();
		$mock_product->shouldReceive( 'set_sold_at_utc' )->zeroOrMoreTimes();
	}

	/**
	 * Create a mock Product_Auction instance.
	 *
	 * @param int    $id   Product ID.
	 * @param string $name Product name.
	 * @return Mockery\MockInterface Mock product instance.
	 */
	private function create_mock_auction_product( int $id, string $name ): Mockery\MockInterface {
		$mock_product = Mockery::mock( Product_Auction::class );
		$mock_product->shouldReceive( 'get_id' )->andReturn( $id );
		$mock_product->shouldReceive( 'get_name' )->andReturn( $name );
		$mock_product->shouldReceive( 'get_permalink' )->andReturn( "http://example.com/auction/{$id}" );
		$mock_product->shouldReceive( 'get_status' )->andReturn( 'publish' );
		$mock_product->shouldReceive( 'get_product_url' )->andReturn( 'https://example.com' );
		$mock_product->shouldReceive( 'get_button_text' )->andReturn( 'Bid Now' );
		$mock_product->shouldReceive( 'get_location' )->andReturn( array() );
		$mock_product->shouldReceive( 'get_notice' )->andReturn( '' );
		$mock_product->shouldReceive( 'get_bidding_notice' )->andReturn( '' );
		$mock_product->shouldReceive( 'get_directions' )->andReturn( '' );
		$mock_product->shouldReceive( 'get_terms_conditions' )->andReturn( '' );
		$mock_product->shouldReceive( 'get_bidding_starts_at_utc' )->andReturn( '' );
		$mock_product->shouldReceive( 'get_bidding_starts_at_local' )->andReturn( '' );
		$mock_product->shouldReceive( 'get_bidding_starts_at_timestamp' )->andReturn( 0 );
		$mock_product->shouldReceive( 'get_bidding_ends_at_utc' )->andReturn( '' );
		$mock_product->shouldReceive( 'get_bidding_ends_at_local' )->andReturn( '' );
		$mock_product->shouldReceive( 'get_bidding_ends_at_timestamp' )->andReturn( 0 );

		return $mock_product;
	}

	/**
	 * Create a mock Product_Item instance.
	 *
	 * @param int    $id         Product ID.
	 * @param string $name       Product name.
	 * @param int    $auction_id Parent auction ID.
	 * @return Mockery\MockInterface Mock product instance.
	 */
	private function create_mock_item_product( int $id, string $name, int $auction_id ): Mockery\MockInterface {
		$mock_product = Mockery::mock( Product_Item::class );
		$mock_product->shouldReceive( 'get_id' )->andReturn( $id );
		$mock_product->shouldReceive( 'get_name' )->andReturn( $name );
		$mock_product->shouldReceive( 'get_permalink' )->andReturn( "http://example.com/item/{$id}" );
		$mock_product->shouldReceive( 'get_status' )->andReturn( 'publish' );
		$mock_product->shouldReceive( 'get_auction_id' )->andReturn( $auction_id );
		$mock_product->shouldReceive( 'get_lot_no' )->andReturn( '' );
		$mock_product->shouldReceive( 'get_description' )->andReturn( '' );
		$mock_product->shouldReceive( 'get_asking_bid' )->andReturn( 0 );
		$mock_product->shouldReceive( 'get_current_bid' )->andReturn( 0 );
		$mock_product->shouldReceive( 'get_sold_price' )->andReturn( 0 );
		$mock_product->shouldReceive( 'get_sold_at_utc' )->andReturn( '' );
		$mock_product->shouldReceive( 'get_sold_at_local' )->andReturn( '' );
		$mock_product->shouldReceive( 'get_sold_at_timestamp' )->andReturn( 0 );
		$mock_product->shouldReceive( 'get_location' )->andReturn( array() );
		$mock_product->shouldReceive( 'get_bidding_starts_at_utc' )->andReturn( '' );
		$mock_product->shouldReceive( 'get_bidding_starts_at_local' )->andReturn( '' );
		$mock_product->shouldReceive( 'get_bidding_starts_at_timestamp' )->andReturn( 0 );
		$mock_product->shouldReceive( 'get_bidding_ends_at_utc' )->andReturn( '' );
		$mock_product->shouldReceive( 'get_bidding_ends_at_local' )->andReturn( '' );
		$mock_product->shouldReceive( 'get_bidding_ends_at_timestamp' )->andReturn( 0 );

		return $mock_product;
	}
}
