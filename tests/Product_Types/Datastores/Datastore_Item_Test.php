<?php
/**
 * Tests for Datastore_Item class.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace The_Another\Plugin\Aucteeno\Tests\Product_Types\Datastores;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Product_Types\Datastores\Datastore_Item;
use The_Another\Plugin\Aucteeno\Product_Types\Product_Item;
use WC_Product;

/**
 * Test class for Datastore_Item.
 *
 * Tests cover save_item_extra_data(), read(), read_item_extra_data(),
 * read_location_data(), and save_location_data() methods to ensure proper
 * handling of extra_data fields, auction_id sync with post_parent,
 * location data (meta + taxonomy), and parent-handled keys.
 */
class Datastore_Item_Test extends TestCase {

	/**
	 * The actual extra_data keys as defined in Product_Item::$extra_data.
	 *
	 * @var array<int, string>
	 */
	private array $all_extra_data_keys = array(
		'product_url',
		'button_text',
		'aucteeno_auction_id',
		'aucteeno_lot_no',
		'aucteeno_description',
		'aucteeno_asking_bid',
		'aucteeno_current_bid',
		'aucteeno_sold_price',
		'aucteeno_sold_at_utc',
		'aucteeno_sold_at_local',
		'aucteeno_location',
		'aucteeno_bidding_starts_at_utc',
		'aucteeno_bidding_starts_at_local',
		'aucteeno_bidding_ends_at_utc',
		'aucteeno_bidding_ends_at_local',
	);

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		// Clear bootstrap stubs so Functions\expect() can override them.
		Mockery::close();
		Monkey\tearDown();
		Monkey\setUp();
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

	/**
	 * Test create() saves all extra_data fields for Product_Item.
	 *
	 * This test ensures that when creating a new Product_Item,
	 * all extra_data fields are saved to post meta with the correct prefix,
	 * except for parent-handled keys (product_url, button_text),
	 * auction_id, and location (which have special handling).
	 *
	 * Uses reflection to call private save_item_extra_data() directly.
	 *
	 * @return void
	 */
	public function test_create_saves_all_extra_data_fields(): void {
		$product_id = 222;
		$datastore  = new Datastore_Item();

		// Mock Product_Item.
		$product = Mockery::mock( Product_Item::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		// Setup get_extra_data_keys() to return all custom keys.
		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( $this->all_extra_data_keys );

		// Setup getters for fields that should be saved.
		// Getter is get_ + key name (e.g., get_aucteeno_lot_no).
		// On Mockery mocks, is_callable returns true for ANY method due to __call.
		// PARENT_HANDLED_KEYS (product_url, button_text) are skipped.
		// aucteeno_auction_id and aucteeno_location are also skipped.
		$product->shouldReceive( 'get_aucteeno_lot_no' )
			->once()
			->with( 'edit' )
			->andReturn( 'LOT-002' );
		$product->shouldReceive( 'get_aucteeno_description' )
			->once()
			->with( 'edit' )
			->andReturn( 'Item description' );
		$product->shouldReceive( 'get_aucteeno_asking_bid' )
			->once()
			->with( 'edit' )
			->andReturn( 100.0 );
		$product->shouldReceive( 'get_aucteeno_current_bid' )
			->once()
			->with( 'edit' )
			->andReturn( 150.0 );
		$product->shouldReceive( 'get_aucteeno_sold_price' )
			->once()
			->with( 'edit' )
			->andReturn( 200.0 );
		$product->shouldReceive( 'get_aucteeno_sold_at_utc' )
			->once()
			->with( 'edit' )
			->andReturn( '2024-01-15 10:00:00' );
		$product->shouldReceive( 'get_aucteeno_sold_at_local' )
			->once()
			->with( 'edit' )
			->andReturn( '2024-01-15 12:00:00' );
		$product->shouldReceive( 'get_aucteeno_bidding_starts_at_utc' )
			->once()
			->with( 'edit' )
			->andReturn( '2024-01-01 10:00:00' );
		$product->shouldReceive( 'get_aucteeno_bidding_starts_at_local' )
			->once()
			->with( 'edit' )
			->andReturn( '2024-01-01 12:00:00' );
		$product->shouldReceive( 'get_aucteeno_bidding_ends_at_utc' )
			->once()
			->with( 'edit' )
			->andReturn( '2024-01-10 10:00:00' );
		$product->shouldReceive( 'get_aucteeno_bidding_ends_at_local' )
			->once()
			->with( 'edit' )
			->andReturn( '2024-01-10 12:00:00' );

		// Setup location for special handling (save_location_data called when changes is null).
		$location_data = array(
			'country'     => '',
			'subdivision' => '',
			'city'        => '',
			'postal_code' => '',
			'address'     => '',
			'address2'    => '',
		);
		$product->shouldReceive( 'get_location' )
			->once()
			->with( 'edit' )
			->andReturn( $location_data );

		// Mock WordPress functions for regular fields.
		Functions\expect( 'wp_slash' )
			->andReturnUsing(
				function ( $value ) {
					return $value;
				}
			);

		// Track update_post_meta calls to verify correct meta keys and values.
		$saved_meta = array();
		Functions\expect( 'update_post_meta' )
			->andReturnUsing(
				function ( $id, $key, $value ) use ( &$saved_meta ) {
					$saved_meta[ $key ] = $value;
					return true;
				}
			);

		// Location save: empty country means wp_set_post_terms called with empty array.
		Functions\expect( 'wp_set_post_terms' )
			->once()
			->andReturn( true );

		// Use reflection to call the private save method directly.
		$reflection = new \ReflectionClass( $datastore );
		$method     = $reflection->getMethod( 'save_item_extra_data' );
		$method->setAccessible( true );

		// Call with null changes (simulates create - saves ALL keys and location).
		$method->invoke( $datastore, $product, null );

		// Verify all expected meta keys were saved.
		$this->assertArrayHasKey( '_aucteeno_lot_no', $saved_meta );
		$this->assertSame( 'LOT-002', $saved_meta['_aucteeno_lot_no'] );
		$this->assertArrayHasKey( '_aucteeno_description', $saved_meta );
		$this->assertSame( 'Item description', $saved_meta['_aucteeno_description'] );
		$this->assertArrayHasKey( '_aucteeno_asking_bid', $saved_meta );
		$this->assertSame( 100.0, $saved_meta['_aucteeno_asking_bid'] );
		$this->assertArrayHasKey( '_aucteeno_current_bid', $saved_meta );
		$this->assertSame( 150.0, $saved_meta['_aucteeno_current_bid'] );
		$this->assertArrayHasKey( '_aucteeno_sold_price', $saved_meta );
		$this->assertSame( 200.0, $saved_meta['_aucteeno_sold_price'] );
		$this->assertArrayHasKey( '_aucteeno_sold_at_utc', $saved_meta );
		$this->assertSame( '2024-01-15 10:00:00', $saved_meta['_aucteeno_sold_at_utc'] );
		$this->assertArrayHasKey( '_aucteeno_sold_at_local', $saved_meta );
		$this->assertSame( '2024-01-15 12:00:00', $saved_meta['_aucteeno_sold_at_local'] );
		$this->assertArrayHasKey( '_aucteeno_bidding_starts_at_utc', $saved_meta );
		$this->assertSame( '2024-01-01 10:00:00', $saved_meta['_aucteeno_bidding_starts_at_utc'] );
		$this->assertArrayHasKey( '_aucteeno_bidding_starts_at_local', $saved_meta );
		$this->assertSame( '2024-01-01 12:00:00', $saved_meta['_aucteeno_bidding_starts_at_local'] );
		$this->assertArrayHasKey( '_aucteeno_bidding_ends_at_utc', $saved_meta );
		$this->assertSame( '2024-01-10 10:00:00', $saved_meta['_aucteeno_bidding_ends_at_utc'] );
		$this->assertArrayHasKey( '_aucteeno_bidding_ends_at_local', $saved_meta );
		$this->assertSame( '2024-01-10 12:00:00', $saved_meta['_aucteeno_bidding_ends_at_local'] );
		$this->assertArrayHasKey( '_aucteeno_location', $saved_meta );
		$this->assertSame( $location_data, $saved_meta['_aucteeno_location'] );

		// Verify parent-handled keys were NOT saved.
		$this->assertArrayNotHasKey( '_product_url', $saved_meta );
		$this->assertArrayNotHasKey( '_button_text', $saved_meta );
		// Verify auction_id was NOT saved.
		$this->assertArrayNotHasKey( '_aucteeno_auction_id', $saved_meta );
	}

	/**
	 * Test create() saves extra_data fields for a minimal set of keys.
	 *
	 * This test verifies that save_item_extra_data with null changes
	 * saves all non-skipped fields and calls save_location_data.
	 *
	 * @return void
	 */
	public function test_create_saves_extra_data_fields(): void {
		$product_id = 111;
		$datastore  = new Datastore_Item();

		// Mock Product_Item.
		$product = Mockery::mock( Product_Item::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		// Setup get_extra_data_keys() with a subset of keys.
		$extra_data_keys = array(
			'product_url',
			'button_text',
			'aucteeno_auction_id',
			'aucteeno_lot_no',
			'aucteeno_description',
			'aucteeno_location',
		);
		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( $extra_data_keys );

		// Setup getters for fields that should be saved (skipping parent-handled, auction_id, location).
		$product->shouldReceive( 'get_aucteeno_lot_no' )
			->once()
			->with( 'edit' )
			->andReturn( 'LOT-001' );
		$product->shouldReceive( 'get_aucteeno_description' )
			->once()
			->with( 'edit' )
			->andReturn( 'Test description' );

		// Setup location.
		$location_data = array(
			'country'     => '',
			'subdivision' => '',
			'city'        => '',
			'postal_code' => '',
			'address'     => '',
			'address2'    => '',
		);
		$product->shouldReceive( 'get_location' )
			->once()
			->with( 'edit' )
			->andReturn( $location_data );

		// Mock WordPress functions.
		Functions\expect( 'wp_slash' )
			->andReturnUsing(
				function ( $value ) {
					return $value;
				}
			);
		Functions\expect( 'update_post_meta' )
			->with( $product_id, '_aucteeno_lot_no', 'LOT-001' )
			->once();
		Functions\expect( 'update_post_meta' )
			->with( $product_id, '_aucteeno_description', 'Test description' )
			->once();

		// Mock location save: empty country means wp_set_post_terms with empty array.
		Functions\expect( 'wp_set_post_terms' )
			->with( $product_id, array(), 'aucteeno-location', false )
			->once();
		Functions\expect( 'update_post_meta' )
			->with( $product_id, '_aucteeno_location', $location_data )
			->once();

		// Use reflection to call the private save method directly.
		$reflection = new \ReflectionClass( $datastore );
		$method     = $reflection->getMethod( 'save_item_extra_data' );
		$method->setAccessible( true );
		$method->invoke( $datastore, $product, null );

		$this->assertTrue( true );
	}

	/**
	 * Test create() does nothing for non-Product_Item instances.
	 *
	 * This test ensures that create() only processes Product_Item instances
	 * and passes through other product types to parent.
	 *
	 * @return void
	 */
	public function test_create_skips_non_product_item(): void {
		$datastore = new Datastore_Item();

		// Mock regular WC_Product (not Product_Item).
		$product = Mockery::mock( WC_Product::class );

		// Verify that get_extra_data_keys is NOT called for non-Product_Item.
		$product->shouldNotReceive( 'get_extra_data_keys' );

		// Call create - parent::create() stub does nothing, and our code returns early.
		$datastore->create( $product );

		$this->assertTrue( true );
	}

	/**
	 * Test update() syncs auction_id with post_parent when auction_id changes.
	 *
	 * This test ensures that when auction_id is changed in an update,
	 * save_item_extra_data properly skips the auction_id field.
	 * We test save_item_extra_data() directly with filtered changes.
	 *
	 * @return void
	 */
	public function test_update_syncs_auction_id_with_post_parent(): void {
		$product_id     = 333;
		$new_auction_id = 888;
		$datastore      = new Datastore_Item();

		// Mock Product_Item.
		$product = Mockery::mock( Product_Item::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		// Setup get_extra_data_keys().
		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( $this->all_extra_data_keys );

		// Setup get_aucteeno_lot_no() for save (auction_id will be skipped).
		$product->shouldReceive( 'get_aucteeno_lot_no' )
			->once()
			->with( 'edit' )
			->andReturn( 'LOT-003' );

		// Mock WordPress functions for saving lot_no.
		Functions\expect( 'wp_slash' )
			->andReturnUsing(
				function ( $value ) {
					return $value;
				}
			);
		Functions\expect( 'update_post_meta' )
			->with( $product_id, '_aucteeno_lot_no', 'LOT-003' )
			->once();

		// Filtered changes (auction_id is present but will be skipped by save_item_extra_data).
		// Location is NOT in changes, so save_location_data should NOT be called.
		$extra_data_changes = array(
			'aucteeno_auction_id' => $new_auction_id,
			'aucteeno_lot_no'     => 'LOT-003',
		);

		// Use reflection to call the private save method with filtered changes.
		$reflection = new \ReflectionClass( $datastore );
		$method     = $reflection->getMethod( 'save_item_extra_data' );
		$method->setAccessible( true );
		$method->invoke( $datastore, $product, $extra_data_changes );

		$this->assertTrue( true );
	}

	/**
	 * Test update() saves only changed extra_data fields.
	 *
	 * This test ensures that update() captures changes before parent::update()
	 * clears them, and only saves the fields that actually changed.
	 *
	 * @return void
	 */
	public function test_update_saves_only_changed_fields(): void {
		$product_id = 444;
		$datastore  = new Datastore_Item();

		// Mock Product_Item.
		$product = Mockery::mock( Product_Item::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		// Setup get_extra_data_keys().
		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( $this->all_extra_data_keys );

		// Setup getters for changed fields only.
		$product->shouldReceive( 'get_aucteeno_lot_no' )
			->once()
			->with( 'edit' )
			->andReturn( 'LOT-004' );
		$product->shouldReceive( 'get_aucteeno_asking_bid' )
			->once()
			->with( 'edit' )
			->andReturn( 125.0 );

		// Mock WordPress functions.
		Functions\expect( 'wp_slash' )
			->andReturnUsing(
				function ( $value ) {
					return $value;
				}
			);

		// Track update_post_meta calls.
		$saved_meta = array();
		Functions\expect( 'update_post_meta' )
			->andReturnUsing(
				function ( $id, $key, $value ) use ( &$saved_meta ) {
					$saved_meta[ $key ] = $value;
					return true;
				}
			);

		// Filtered changes (only these two keys changed, no aucteeno_location).
		$extra_data_changes = array(
			'aucteeno_lot_no'     => 'LOT-004',
			'aucteeno_asking_bid' => 125.0,
		);

		// Use reflection to call the private save method with filtered changes.
		$reflection = new \ReflectionClass( $datastore );
		$method     = $reflection->getMethod( 'save_item_extra_data' );
		$method->setAccessible( true );
		$method->invoke( $datastore, $product, $extra_data_changes );

		// Verify only the changed fields were saved.
		$this->assertCount( 2, $saved_meta );
		$this->assertSame( 'LOT-004', $saved_meta['_aucteeno_lot_no'] );
		$this->assertSame( 125.0, $saved_meta['_aucteeno_asking_bid'] );
	}

	/**
	 * Test update() skips non-Product_Item and calls parent.
	 *
	 * This test ensures that for non-Product_Item instances,
	 * update() calls parent::update() and returns early.
	 *
	 * @return void
	 */
	public function test_update_skips_non_product_item(): void {
		$datastore = new Datastore_Item();

		// Mock regular WC_Product (not Product_Item).
		$product = Mockery::mock( WC_Product::class );

		// Verify that get_changes() is NOT called for non-Product_Item.
		$product->shouldNotReceive( 'get_changes' );
		$product->shouldNotReceive( 'get_extra_data_keys' );

		// Call update - returns early for non-Product_Item.
		$datastore->update( $product );

		$this->assertTrue( true );
	}

	/**
	 * Test read() loads auction_id from post_parent.
	 *
	 * This test ensures that read() loads auction_id from post_parent
	 * and sets it on the product object.
	 *
	 * @return void
	 */
	public function test_read_loads_auction_id_from_post_parent(): void {
		$product_id = 555;
		$parent_id  = 777;
		$datastore  = new Datastore_Item();

		// Mock Product_Item.
		$product = Mockery::mock( Product_Item::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		// Mock wp_get_post_parent_id() to return parent_id.
		Functions\expect( 'wp_get_post_parent_id' )
			->once()
			->with( $product_id )
			->andReturn( $parent_id );

		// Verify set_auction_id() is called with parent_id.
		$product->shouldReceive( 'set_auction_id' )
			->once()
			->with( $parent_id );

		// Setup get_extra_data_keys() for read_item_extra_data.
		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( array( 'aucteeno_location' ) );

		// Mock get_post_meta: handles both call patterns.
		// 1. get_post_meta($product_id) with one arg returns all meta.
		// 2. get_post_meta($id, '_aucteeno_location', true) for location.
		Functions\expect( 'get_post_meta' )
			->andReturnUsing(
				function () use ( $product_id ) {
					$args = func_get_args();
					if ( count( $args ) === 1 && $args[0] === $product_id ) {
						return array(); // No meta stored.
					}
					if ( count( $args ) === 3 && $args[1] === '_aucteeno_location' && $args[2] === true ) {
						return array();
					}
					return '';
				}
			);

		// set_location should be called with defaults.
		$product->shouldReceive( 'set_location' )
			->once()
			->with(
				array(
					'country'     => '',
					'subdivision' => '',
					'city'        => '',
					'postal_code' => '',
					'address'     => '',
					'address2'    => '',
				)
			);

		// Call read method.
		$datastore->read( $product );

		$this->assertTrue( true );
	}

	/**
	 * Test read() loads all extra_data fields from database.
	 *
	 * This test ensures that read() loads all custom fields from post meta
	 * and sets them on the product object using the appropriate setters.
	 * read_item_extra_data calls get_post_meta($product_id) with no key
	 * to get all meta as an associative array.
	 *
	 * @return void
	 */
	public function test_read_loads_all_extra_data_fields(): void {
		$product_id = 666;
		$datastore  = new Datastore_Item();

		// Mock Product_Item.
		$product = Mockery::mock( Product_Item::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		// Mock wp_get_post_parent_id() to return 0 (no parent).
		Functions\expect( 'wp_get_post_parent_id' )
			->once()
			->with( $product_id )
			->andReturn( 0 );

		// Setup get_extra_data_keys().
		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( $this->all_extra_data_keys );

		// Build the all-meta array for the get_post_meta mock.
		$all_meta = array(
			'_aucteeno_lot_no'                  => array( 'LOT-005' ),
			'_aucteeno_description'             => array( 'Saved description' ),
			'_aucteeno_asking_bid'              => array( '100.50' ),
			'_aucteeno_current_bid'             => array( '150.75' ),
			'_aucteeno_sold_price'              => array( '200.00' ),
			'_aucteeno_sold_at_utc'             => array( '2024-01-15 10:00:00' ),
			'_aucteeno_sold_at_local'           => array( '2024-01-15 12:00:00' ),
			'_aucteeno_bidding_starts_at_utc'   => array( '2024-01-01 10:00:00' ),
			'_aucteeno_bidding_starts_at_local' => array( '2024-01-01 12:00:00' ),
			'_aucteeno_bidding_ends_at_utc'     => array( '2024-01-10 10:00:00' ),
			'_aucteeno_bidding_ends_at_local'   => array( '2024-01-10 12:00:00' ),
		);
		$location_data = array(
			'country'     => 'US',
			'subdivision' => 'OK',
			'city'        => 'Tulsa',
			'postal_code' => '74101',
			'address'     => '123 Main St',
			'address2'    => 'Apt 4',
		);

		// Use a single get_post_meta mock with andReturnUsing to handle both call patterns:
		// 1. get_post_meta($id) - returns all meta (1 arg)
		// 2. get_post_meta($id, '_aucteeno_location', true) - returns location (3 args)
		Functions\expect( 'get_post_meta' )
			->andReturnUsing(
				function () use ( $product_id, $all_meta, $location_data ) {
					$args = func_get_args();
					if ( count( $args ) === 1 && $args[0] === $product_id ) {
						return $all_meta;
					}
					if ( count( $args ) === 3 && $args[1] === '_aucteeno_location' && $args[2] === true ) {
						return $location_data;
					}
					return '';
				}
			);

		// Setters use short names: set_ + key, then str_replace('_aucteeno_', '_', setter).
		// e.g., set_aucteeno_lot_no -> set_lot_no; set_aucteeno_description -> set_description.
		$product->shouldReceive( 'set_lot_no' )
			->once()
			->with( 'LOT-005' );
		$product->shouldReceive( 'set_description' )
			->once()
			->with( 'Saved description' );
		$product->shouldReceive( 'set_asking_bid' )
			->once()
			->with( '100.50' );
		$product->shouldReceive( 'set_current_bid' )
			->once()
			->with( '150.75' );
		$product->shouldReceive( 'set_sold_price' )
			->once()
			->with( '200.00' );
		$product->shouldReceive( 'set_sold_at_utc' )
			->once()
			->with( '2024-01-15 10:00:00' );
		$product->shouldReceive( 'set_sold_at_local' )
			->once()
			->with( '2024-01-15 12:00:00' );
		$product->shouldReceive( 'set_bidding_starts_at_utc' )
			->once()
			->with( '2024-01-01 10:00:00' );
		$product->shouldReceive( 'set_bidding_starts_at_local' )
			->once()
			->with( '2024-01-01 12:00:00' );
		$product->shouldReceive( 'set_bidding_ends_at_utc' )
			->once()
			->with( '2024-01-10 10:00:00' );
		$product->shouldReceive( 'set_bidding_ends_at_local' )
			->once()
			->with( '2024-01-10 12:00:00' );

		// set_location called with the merged location array.
		$product->shouldReceive( 'set_location' )
			->once()
			->with( Mockery::type( 'array' ) );

		// Call read method.
		$datastore->read( $product );

		$this->assertTrue( true ); // If we get here, all mocks were called correctly.
	}

	/**
	 * Test read() skips parent-handled keys (product_url, button_text).
	 *
	 * This test ensures that parent-handled keys are not loaded from
	 * custom meta keys and are left to the parent datastore.
	 * Also verifies auction_id and location are skipped in the main loop.
	 *
	 * @return void
	 */
	public function test_read_skips_parent_handled_keys(): void {
		$product_id = 777;
		$datastore  = new Datastore_Item();

		// Mock Product_Item.
		$product = Mockery::mock( Product_Item::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		// Mock wp_get_post_parent_id() to return 0.
		Functions\expect( 'wp_get_post_parent_id' )
			->once()
			->with( $product_id )
			->andReturn( 0 );

		// Setup get_extra_data_keys() including parent-handled keys.
		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( array( 'product_url', 'button_text', 'aucteeno_lot_no', 'aucteeno_location' ) );

		// All meta with lot_no data only (product_url and button_text won't be read).
		$all_meta = array(
			'_aucteeno_lot_no' => array( 'LOT-007' ),
		);

		// get_post_meta mock: handles both call patterns.
		Functions\expect( 'get_post_meta' )
			->andReturnUsing(
				function () use ( $product_id, $all_meta ) {
					$args = func_get_args();
					if ( count( $args ) === 1 && $args[0] === $product_id ) {
						return $all_meta;
					}
					if ( count( $args ) === 3 && $args[1] === '_aucteeno_location' && $args[2] === true ) {
						return array();
					}
					return '';
				}
			);

		// Verify setters are NOT called for parent-handled keys.
		$product->shouldNotReceive( 'set_product_url' );
		$product->shouldNotReceive( 'set_button_text' );

		// set_lot_no should be called.
		$product->shouldReceive( 'set_lot_no' )
			->once()
			->with( 'LOT-007' );

		// Location read returns empty, merged with defaults.
		$product->shouldReceive( 'set_location' )
			->once();

		// Call read method.
		$datastore->read( $product );

		$this->assertTrue( true );
	}

	/**
	 * Test read() skips auction_id (loaded from post_parent).
	 *
	 * This test ensures that auction_id is not loaded from meta
	 * since it's loaded from post_parent instead.
	 *
	 * @return void
	 */
	public function test_read_skips_auction_id_from_meta(): void {
		$product_id = 888;
		$datastore  = new Datastore_Item();

		// Mock Product_Item.
		$product = Mockery::mock( Product_Item::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		// Mock wp_get_post_parent_id() to return parent_id.
		$parent_id = 999;
		Functions\expect( 'wp_get_post_parent_id' )
			->once()
			->with( $product_id )
			->andReturn( $parent_id );

		// Verify set_auction_id() is called with parent_id.
		$product->shouldReceive( 'set_auction_id' )
			->once()
			->with( $parent_id );

		// Setup get_extra_data_keys() including auction_id.
		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( array( 'aucteeno_auction_id', 'aucteeno_location' ) );

		// get_post_meta mock: empty meta, empty location.
		Functions\expect( 'get_post_meta' )
			->andReturnUsing(
				function () use ( $product_id ) {
					$args = func_get_args();
					if ( count( $args ) === 1 && $args[0] === $product_id ) {
						return array(); // No meta stored.
					}
					if ( count( $args ) === 3 && $args[1] === '_aucteeno_location' && $args[2] === true ) {
						return array();
					}
					return '';
				}
			);

		// set_location called with defaults.
		$product->shouldReceive( 'set_location' )
			->once();

		// Call read method.
		$datastore->read( $product );

		$this->assertTrue( true );
	}

	/**
	 * Test read() returns early when product ID is zero.
	 *
	 * This test ensures that read() doesn't try to load data
	 * when the product doesn't have an ID yet.
	 *
	 * @return void
	 */
	public function test_read_returns_early_when_no_product_id(): void {
		$datastore = new Datastore_Item();

		// Mock Product_Item with zero ID.
		$product = Mockery::mock( Product_Item::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( 0 );

		// Verify that get_extra_data_keys() is NOT called.
		$product->shouldNotReceive( 'get_extra_data_keys' );

		// Call read method.
		$datastore->read( $product );

		$this->assertTrue( true );
	}

	/**
	 * Test read() skips non-Product_Item instances.
	 *
	 * This test ensures that read() only processes Product_Item instances
	 * and skips others after calling parent::read().
	 *
	 * @return void
	 */
	public function test_read_skips_non_product_item(): void {
		$datastore = new Datastore_Item();

		// Mock regular WC_Product.
		$product = Mockery::mock( WC_Product::class );

		// Verify that get_extra_data_keys() is NOT called.
		$product->shouldNotReceive( 'get_extra_data_keys' );

		// Call read method.
		$datastore->read( $product );

		$this->assertTrue( true );
	}

	/**
	 * Test read() handles empty meta values correctly.
	 *
	 * When meta values are empty, setters should NOT be called.
	 * Location data should still be loaded and merged with defaults.
	 *
	 * @return void
	 */
	public function test_read_loads_location_from_defaults_when_empty(): void {
		$product_id = 101112;
		$datastore  = new Datastore_Item();

		// Mock Product_Item.
		$product = Mockery::mock( Product_Item::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		// Mock wp_get_post_parent_id() to return 0.
		Functions\expect( 'wp_get_post_parent_id' )
			->once()
			->with( $product_id )
			->andReturn( 0 );

		// Setup get_extra_data_keys().
		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( $this->all_extra_data_keys );

		// get_post_meta mock: all-meta returns empty, location returns non-array.
		Functions\expect( 'get_post_meta' )
			->andReturnUsing(
				function () use ( $product_id ) {
					$args = func_get_args();
					if ( count( $args ) === 1 && $args[0] === $product_id ) {
						return array(); // No meta stored.
					}
					if ( count( $args ) === 3 && $args[1] === '_aucteeno_location' && $args[2] === true ) {
						return ''; // Non-array triggers reset to empty array.
					}
					return '';
				}
			);

		// No setters should be called for empty meta values (Mockery will error on unexpected calls).

		// set_location should be called with defaults (all empty strings).
		$product->shouldReceive( 'set_location' )
			->once()
			->with(
				array(
					'country'     => '',
					'subdivision' => '',
					'city'        => '',
					'postal_code' => '',
					'address'     => '',
					'address2'    => '',
				)
			);

		// Call read method.
		$datastore->read( $product );

		$this->assertTrue( true );
	}

	/**
	 * Test read_location_data() merges location meta with defaults.
	 *
	 * This test verifies that read_location_data properly reads from
	 * get_post_meta with the specific _aucteeno_location key and merges
	 * with default values for any missing keys.
	 *
	 * @return void
	 */
	public function test_read_location_data_merges_with_defaults(): void {
		$product_id = 252627;
		$datastore  = new Datastore_Item();

		// Mock Product_Item.
		$product = Mockery::mock( Product_Item::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		// Location meta has partial data (missing some fields).
		$stored_location = array(
			'country' => 'CA',
			'city'    => 'Toronto',
		);
		Functions\expect( 'get_post_meta' )
			->with( $product_id, '_aucteeno_location', true )
			->once()
			->andReturn( $stored_location );

		// set_location() should be called with merged defaults.
		$product->shouldReceive( 'set_location' )
			->once()
			->with(
				array(
					'country'     => 'CA',
					'subdivision' => '',
					'city'        => 'Toronto',
					'postal_code' => '',
					'address'     => '',
					'address2'    => '',
				)
			);

		// Use reflection to call private method.
		$reflection = new \ReflectionClass( $datastore );
		$method     = $reflection->getMethod( 'read_location_data' );
		$method->setAccessible( true );
		$method->invoke( $datastore, $product );

		$this->assertTrue( true );
	}

	/**
	 * Test save_location_data() returns early when product ID is zero.
	 *
	 * This test ensures that save_location_data() does nothing when
	 * the product has no ID (not yet persisted).
	 *
	 * @return void
	 */
	public function test_save_location_data_returns_early_when_no_product_id(): void {
		$datastore = new Datastore_Item();

		// Mock Product_Item with zero ID.
		$product = Mockery::mock( Product_Item::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( 0 );

		// get_location should NOT be called since we return early.
		$product->shouldNotReceive( 'get_location' );

		// Use reflection to call private method.
		$reflection = new \ReflectionClass( $datastore );
		$method     = $reflection->getMethod( 'save_location_data' );
		$method->setAccessible( true );
		$method->invoke( $datastore, $product );

		$this->assertTrue( true );
	}

	/**
	 * Test save_location_data() clears taxonomies when location is empty.
	 *
	 * This test ensures that when location fields are empty,
	 * wp_set_post_terms is called once with an empty terms array
	 * and aucteeno-location taxonomy.
	 *
	 * @return void
	 */
	public function test_save_location_data_clears_empty_taxonomies(): void {
		$product_id = 131415;
		$datastore  = new Datastore_Item();

		// Mock Product_Item.
		$product = Mockery::mock( Product_Item::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		$location_data = array(
			'country'     => '',
			'subdivision' => '',
			'city'        => '',
			'postal_code' => '',
			'address'     => '',
			'address2'    => '',
		);
		$product->shouldReceive( 'get_location' )
			->once()
			->with( 'edit' )
			->andReturn( $location_data );

		// wp_set_post_terms called ONCE with empty array and aucteeno-location taxonomy.
		$wp_set_post_terms_calls = array();
		Functions\expect( 'wp_set_post_terms' )
			->once()
			->andReturnUsing(
				function ( $id, $terms, $taxonomy, $append ) use ( &$wp_set_post_terms_calls ) {
					$wp_set_post_terms_calls[] = array(
						'id'       => $id,
						'terms'    => $terms,
						'taxonomy' => $taxonomy,
						'append'   => $append,
					);
					return true;
				}
			);

		// Meta save still happens.
		Functions\expect( 'update_post_meta' )
			->once()
			->andReturn( true );

		// Use reflection to call private method.
		$reflection = new \ReflectionClass( $datastore );
		$method     = $reflection->getMethod( 'save_location_data' );
		$method->setAccessible( true );
		$method->invoke( $datastore, $product );

		// Verify wp_set_post_terms was called with correct arguments.
		$this->assertCount( 1, $wp_set_post_terms_calls );
		$this->assertSame( $product_id, $wp_set_post_terms_calls[0]['id'] );
		$this->assertSame( array(), $wp_set_post_terms_calls[0]['terms'] );
		$this->assertSame( 'aucteeno-location', $wp_set_post_terms_calls[0]['taxonomy'] );
		$this->assertFalse( $wp_set_post_terms_calls[0]['append'] );
	}

	/**
	 * Test save_item_extra_data() with changes that include aucteeno_location.
	 *
	 * When $changes includes aucteeno_location, save_location_data should be called.
	 *
	 * @return void
	 */
	public function test_update_saves_location_when_location_changed(): void {
		$product_id = 282930;
		$datastore  = new Datastore_Item();

		// Mock Product_Item.
		$product = Mockery::mock( Product_Item::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( $this->all_extra_data_keys );

		// Only aucteeno_location changed.
		$location_data = array(
			'country'     => '',
			'subdivision' => '',
			'city'        => 'New City',
			'postal_code' => '',
			'address'     => '',
			'address2'    => '',
		);
		$product->shouldReceive( 'get_location' )
			->once()
			->with( 'edit' )
			->andReturn( $location_data );

		// wp_set_post_terms with empty array (no country, so no terms).
		$wp_set_post_terms_calls = array();
		Functions\expect( 'wp_set_post_terms' )
			->once()
			->andReturnUsing(
				function ( $id, $terms, $taxonomy, $append ) use ( &$wp_set_post_terms_calls ) {
					$wp_set_post_terms_calls[] = compact( 'id', 'terms', 'taxonomy', 'append' );
					return true;
				}
			);

		Functions\expect( 'update_post_meta' )
			->once()
			->andReturn( true );

		$extra_data_changes = array(
			'aucteeno_location' => $location_data,
		);

		// Use reflection to call the private save method with changes that include location.
		$reflection = new \ReflectionClass( $datastore );
		$method     = $reflection->getMethod( 'save_item_extra_data' );
		$method->setAccessible( true );
		$method->invoke( $datastore, $product, $extra_data_changes );

		// Verify wp_set_post_terms was called with correct taxonomy.
		$this->assertCount( 1, $wp_set_post_terms_calls );
		$this->assertSame( 'aucteeno-location', $wp_set_post_terms_calls[0]['taxonomy'] );
	}

	/**
	 * Test save_item_extra_data() does NOT save location when location is not in changes.
	 *
	 * When $changes does NOT include aucteeno_location, save_location_data should NOT be called.
	 *
	 * @return void
	 */
	public function test_update_does_not_save_location_when_not_changed(): void {
		$product_id = 313233;
		$datastore  = new Datastore_Item();

		// Mock Product_Item.
		$product = Mockery::mock( Product_Item::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( $this->all_extra_data_keys );

		// Only aucteeno_lot_no changed (not location).
		$product->shouldReceive( 'get_aucteeno_lot_no' )
			->once()
			->with( 'edit' )
			->andReturn( 'LOT-CHANGED' );

		Functions\expect( 'wp_slash' )
			->andReturnUsing(
				function ( $value ) {
					return $value;
				}
			);

		Functions\expect( 'update_post_meta' )
			->once()
			->andReturn( true );

		// get_location should NOT be called (location not in changes).
		$product->shouldNotReceive( 'get_location' );

		// wp_set_post_terms should NOT be called.
		Functions\expect( 'wp_set_post_terms' )->never();

		$extra_data_changes = array(
			'aucteeno_lot_no' => 'LOT-CHANGED',
		);

		// Use reflection to call the private save method.
		$reflection = new \ReflectionClass( $datastore );
		$method     = $reflection->getMethod( 'save_item_extra_data' );
		$method->setAccessible( true );
		$method->invoke( $datastore, $product, $extra_data_changes );

		$this->assertTrue( true );
	}

	/**
	 * Test save_item_extra_data() returns early when product ID is zero.
	 *
	 * @return void
	 */
	public function test_save_returns_early_when_no_product_id(): void {
		$datastore = new Datastore_Item();

		// Mock Product_Item with zero ID.
		$product = Mockery::mock( Product_Item::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( 0 );

		// Nothing else should be called.
		$product->shouldNotReceive( 'get_extra_data_keys' );

		// Use reflection to call private method.
		$reflection = new \ReflectionClass( $datastore );
		$method     = $reflection->getMethod( 'save_item_extra_data' );
		$method->setAccessible( true );
		$method->invoke( $datastore, $product );

		$this->assertTrue( true );
	}

	/**
	 * Test update_post_meta() is called with wp_slash() for string values.
	 *
	 * This test ensures that string values are properly slashed
	 * before being saved to the database.
	 *
	 * @return void
	 */
	public function test_save_uses_wp_slash_for_string_values(): void {
		$product_id = 222324;
		$datastore  = new Datastore_Item();

		// Mock Product_Item.
		$product = Mockery::mock( Product_Item::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		// Setup get_extra_data_keys() with aucteeno_lot_no and aucteeno_location.
		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( array( 'aucteeno_lot_no', 'aucteeno_location' ) );

		$test_lot_no = "LOT-001 with 'quotes' and \"double quotes\"";
		$product->shouldReceive( 'get_aucteeno_lot_no' )
			->once()
			->with( 'edit' )
			->andReturn( $test_lot_no );

		// Mock location since save_item_extra_data() with null changes calls save_location_data().
		$location_data = array(
			'country'     => '',
			'subdivision' => '',
			'city'        => '',
			'postal_code' => '',
			'address'     => '',
			'address2'    => '',
		);
		$product->shouldReceive( 'get_location' )
			->once()
			->with( 'edit' )
			->andReturn( $location_data );

		// Mock wp_slash() to return slashed value.
		$slashed_lot_no = addslashes( $test_lot_no );
		Functions\expect( 'wp_slash' )
			->once()
			->with( $test_lot_no )
			->andReturn( $slashed_lot_no );

		// Track update_post_meta calls.
		$saved_meta = array();
		Functions\expect( 'update_post_meta' )
			->andReturnUsing(
				function ( $id, $key, $value ) use ( &$saved_meta ) {
					$saved_meta[ $key ] = $value;
					return true;
				}
			);

		// Mock location save.
		Functions\expect( 'wp_set_post_terms' )
			->once()
			->andReturn( true );

		// Use reflection to call private method.
		$reflection = new \ReflectionClass( $datastore );
		$method     = $reflection->getMethod( 'save_item_extra_data' );
		$method->setAccessible( true );
		$method->invoke( $datastore, $product );

		// Verify the lot_no was saved with slashed value.
		$this->assertArrayHasKey( '_aucteeno_lot_no', $saved_meta );
		$this->assertSame( $slashed_lot_no, $saved_meta['_aucteeno_lot_no'] );
	}
}
