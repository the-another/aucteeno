<?php
/**
 * Tests for Datastore_Auction class.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Tests\Product_Types\Datastores;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use TheAnother\Plugin\Aucteeno\Product_Types\Datastores\Datastore_Auction;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Auction;
use WC_Product;
use WC_Product_Data_Store_CPT;

/**
 * Test class for Datastore_Auction.
 *
 * Tests cover create(), update(), and read() methods to ensure proper
 * handling of extra_data fields, location data (meta + taxonomy), and
 * parent-handled keys.
 */
class Datastore_Auction_Test extends TestCase {

	/**
	 * The actual extra_data keys as defined in Product_Auction::$extra_data.
	 *
	 * @var array<int, string>
	 */
	private array $all_extra_data_keys = array(
		'product_url',
		'button_text',
		'aucteeno_location',
		'aucteeno_notice',
		'aucteeno_bidding_notice',
		'aucteeno_directions',
		'aucteeno_terms_conditions',
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
	 * Test create() saves all extra_data fields for Product_Auction.
	 *
	 * This test ensures that when creating a new Product_Auction,
	 * all extra_data fields are saved to post meta with the correct prefix,
	 * except for location (which has special handling).
	 *
	 * Uses reflection to call private save_auction_extra_data() directly.
	 *
	 * @return void
	 */
	public function test_create_saves_all_extra_data_fields(): void {
		$product_id = 123;
		$datastore  = new Datastore_Auction();

		// Mock Product_Auction with all extra_data fields.
		$product = Mockery::mock( Product_Auction::class );

		// Setup get_id() to return product_id (after parent::create() would set it).
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		// Setup get_extra_data_keys() to return all custom keys.
		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( $this->all_extra_data_keys );

		// Setup getters for all fields that should be saved.
		// Getters are: get_ + key name (e.g., get_product_url, get_aucteeno_notice).
		// On Mockery mocks, is_callable returns true for ANY method due to __call.
		$product->shouldReceive( 'get_product_url' )
			->once()
			->with( 'edit' )
			->andReturn( 'https://example.com/auction' );
		$product->shouldReceive( 'get_button_text' )
			->once()
			->with( 'edit' )
			->andReturn( 'Bid Now' );
		$product->shouldReceive( 'get_aucteeno_notice' )
			->once()
			->with( 'edit' )
			->andReturn( 'Test notice' );
		$product->shouldReceive( 'get_aucteeno_bidding_notice' )
			->once()
			->with( 'edit' )
			->andReturn( 'Test bidding notice' );
		$product->shouldReceive( 'get_aucteeno_directions' )
			->once()
			->with( 'edit' )
			->andReturn( 'Test directions' );
		$product->shouldReceive( 'get_aucteeno_terms_conditions' )
			->once()
			->with( 'edit' )
			->andReturn( 'Test terms' );
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
			->andReturn( '2024-01-02 10:00:00' );
		$product->shouldReceive( 'get_aucteeno_bidding_ends_at_local' )
			->once()
			->with( 'edit' )
			->andReturn( '2024-01-02 12:00:00' );

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
		$method     = $reflection->getMethod( 'save_auction_extra_data' );
		$method->setAccessible( true );

		// Call with null changes (simulates create - saves ALL keys and location).
		$method->invoke( $datastore, $product, null );

		// Verify all expected meta keys were saved.
		$this->assertArrayHasKey( '_product_url', $saved_meta );
		$this->assertSame( 'https://example.com/auction', $saved_meta['_product_url'] );
		$this->assertArrayHasKey( '_button_text', $saved_meta );
		$this->assertSame( 'Bid Now', $saved_meta['_button_text'] );
		$this->assertArrayHasKey( '_aucteeno_notice', $saved_meta );
		$this->assertSame( 'Test notice', $saved_meta['_aucteeno_notice'] );
		$this->assertArrayHasKey( '_aucteeno_bidding_notice', $saved_meta );
		$this->assertSame( 'Test bidding notice', $saved_meta['_aucteeno_bidding_notice'] );
		$this->assertArrayHasKey( '_aucteeno_directions', $saved_meta );
		$this->assertSame( 'Test directions', $saved_meta['_aucteeno_directions'] );
		$this->assertArrayHasKey( '_aucteeno_terms_conditions', $saved_meta );
		$this->assertSame( 'Test terms', $saved_meta['_aucteeno_terms_conditions'] );
		$this->assertArrayHasKey( '_aucteeno_bidding_starts_at_utc', $saved_meta );
		$this->assertSame( '2024-01-01 10:00:00', $saved_meta['_aucteeno_bidding_starts_at_utc'] );
		$this->assertArrayHasKey( '_aucteeno_bidding_starts_at_local', $saved_meta );
		$this->assertSame( '2024-01-01 12:00:00', $saved_meta['_aucteeno_bidding_starts_at_local'] );
		$this->assertArrayHasKey( '_aucteeno_bidding_ends_at_utc', $saved_meta );
		$this->assertSame( '2024-01-02 10:00:00', $saved_meta['_aucteeno_bidding_ends_at_utc'] );
		$this->assertArrayHasKey( '_aucteeno_bidding_ends_at_local', $saved_meta );
		$this->assertSame( '2024-01-02 12:00:00', $saved_meta['_aucteeno_bidding_ends_at_local'] );
		$this->assertArrayHasKey( '_aucteeno_location', $saved_meta );
		$this->assertSame( $location_data, $saved_meta['_aucteeno_location'] );
	}

	/**
	 * Test create() does nothing for non-Product_Auction instances.
	 *
	 * This test ensures that create() only processes Product_Auction instances
	 * and passes through other product types to parent.
	 *
	 * @return void
	 */
	public function test_create_skips_non_product_auction(): void {
		$datastore = new Datastore_Auction();

		// Mock regular WC_Product (not Product_Auction).
		$product = Mockery::mock( WC_Product::class );

		// Verify that get_extra_data_keys is NOT called for non-Product_Auction.
		$product->shouldNotReceive( 'get_extra_data_keys' );

		// Call create - parent::create() stub does nothing, and our code returns early.
		$datastore->create( $product );

		$this->assertTrue( true );
	}

	/**
	 * Test update() saves only changed extra_data fields.
	 *
	 * This test ensures that update() captures changes before parent::update()
	 * clears them, and only saves the fields that actually changed.
	 *
	 * Uses reflection to call save_auction_extra_data() directly with filtered changes.
	 *
	 * @return void
	 */
	public function test_update_saves_only_changed_fields(): void {
		$product_id = 456;
		$datastore  = new Datastore_Auction();

		// Mock Product_Auction.
		$product = Mockery::mock( Product_Auction::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		// Setup get_extra_data_keys().
		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( $this->all_extra_data_keys );

		// Setup getters for changed fields only.
		$product->shouldReceive( 'get_aucteeno_notice' )
			->once()
			->with( 'edit' )
			->andReturn( 'Updated notice' );
		$product->shouldReceive( 'get_aucteeno_bidding_starts_at_utc' )
			->once()
			->with( 'edit' )
			->andReturn( '2024-02-01 10:00:00' );

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
			'aucteeno_notice'                => 'Updated notice',
			'aucteeno_bidding_starts_at_utc' => '2024-02-01 10:00:00',
		);

		// Use reflection to call the private save method with filtered changes.
		$reflection = new \ReflectionClass( $datastore );
		$method     = $reflection->getMethod( 'save_auction_extra_data' );
		$method->setAccessible( true );
		$method->invoke( $datastore, $product, $extra_data_changes );

		// Verify only the changed fields were saved.
		$this->assertCount( 2, $saved_meta );
		$this->assertSame( 'Updated notice', $saved_meta['_aucteeno_notice'] );
		$this->assertSame( '2024-02-01 10:00:00', $saved_meta['_aucteeno_bidding_starts_at_utc'] );
	}

	/**
	 * Test update() skips non-Product_Auction and calls parent.
	 *
	 * This test ensures that for non-Product_Auction instances,
	 * update() returns early without processing.
	 *
	 * @return void
	 */
	public function test_update_skips_non_product_auction(): void {
		$datastore = new Datastore_Auction();

		// Mock regular WC_Product (not Product_Auction).
		$product = Mockery::mock( WC_Product::class );

		// Verify that get_changes() is NOT called for non-Product_Auction.
		$product->shouldNotReceive( 'get_changes' );
		$product->shouldNotReceive( 'get_extra_data_keys' );

		// Call update - returns early for non-Product_Auction.
		$datastore->update( $product );

		$this->assertTrue( true );
	}

	/**
	 * Test read() loads all extra_data fields from database.
	 *
	 * This test ensures that read() loads all custom fields from post meta
	 * and sets them on the product object using the appropriate setters.
	 * read_auction_extra_data calls get_post_meta($product_id) with no key
	 * to get all meta as an associative array.
	 *
	 * @return void
	 */
	public function test_read_loads_all_extra_data_fields(): void {
		$product_id = 789;
		$datastore  = new Datastore_Auction();

		// Mock Product_Auction.
		$product = Mockery::mock( Product_Auction::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		// Setup get_extra_data_keys().
		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( $this->all_extra_data_keys );

		// Build the all-meta array and location data for the get_post_meta mock.
		$all_meta = array(
			'_product_url'                      => array( 'https://example.com/auction' ),
			'_button_text'                      => array( 'Bid Now' ),
			'_aucteeno_notice'                  => array( 'Saved notice' ),
			'_aucteeno_bidding_notice'          => array( 'Saved bidding notice' ),
			'_aucteeno_directions'              => array( 'Saved directions' ),
			'_aucteeno_terms_conditions'        => array( 'Saved terms' ),
			'_aucteeno_bidding_starts_at_utc'   => array( '2024-01-01 10:00:00' ),
			'_aucteeno_bidding_starts_at_local' => array( '2024-01-01 12:00:00' ),
			'_aucteeno_bidding_ends_at_utc'     => array( '2024-01-02 10:00:00' ),
			'_aucteeno_bidding_ends_at_local'   => array( '2024-01-02 12:00:00' ),
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
		// e.g., set_product_url stays set_product_url; set_aucteeno_notice -> set_notice.
		$product->shouldReceive( 'set_product_url' )
			->once()
			->with( 'https://example.com/auction' );
		$product->shouldReceive( 'set_button_text' )
			->once()
			->with( 'Bid Now' );
		$product->shouldReceive( 'set_notice' )
			->once()
			->with( 'Saved notice' );
		$product->shouldReceive( 'set_bidding_notice' )
			->once()
			->with( 'Saved bidding notice' );
		$product->shouldReceive( 'set_directions' )
			->once()
			->with( 'Saved directions' );
		$product->shouldReceive( 'set_terms_conditions' )
			->once()
			->with( 'Saved terms' );
		$product->shouldReceive( 'set_bidding_starts_at_utc' )
			->once()
			->with( '2024-01-01 10:00:00' );
		$product->shouldReceive( 'set_bidding_starts_at_local' )
			->once()
			->with( '2024-01-01 12:00:00' );
		$product->shouldReceive( 'set_bidding_ends_at_utc' )
			->once()
			->with( '2024-01-02 10:00:00' );
		$product->shouldReceive( 'set_bidding_ends_at_local' )
			->once()
			->with( '2024-01-02 12:00:00' );

		// set_location called with the merged location array.
		$product->shouldReceive( 'set_location' )
			->once()
			->with( Mockery::type( 'array' ) );

		// Call read method.
		$datastore->read( $product );

		$this->assertTrue( true ); // If we get here, all mocks were called correctly.
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
		$datastore  = new Datastore_Auction();

		// Mock Product_Auction.
		$product = Mockery::mock( Product_Auction::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

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
	 * Test read() processes all extra_data keys including product_url and button_text.
	 *
	 * product_url and button_text are NOT parent-handled in the datastore -
	 * they ARE saved/read by the datastore like other extra_data keys.
	 * Only aucteeno_location is skipped (special handling).
	 *
	 * @return void
	 */
	public function test_read_skips_parent_handled_keys(): void {
		$product_id = 131415;
		$datastore  = new Datastore_Auction();

		// Mock Product_Auction.
		$product = Mockery::mock( Product_Auction::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		// Setup get_extra_data_keys() with a subset of keys.
		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( array( 'product_url', 'button_text', 'aucteeno_location', 'aucteeno_notice' ) );

		// All meta including product_url and button_text.
		$all_meta = array(
			'_product_url'     => array( 'https://example.com' ),
			'_button_text'     => array( 'Click' ),
			'_aucteeno_notice' => array( 'A notice' ),
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

		// Setters should be called for all non-location keys.
		$product->shouldReceive( 'set_product_url' )
			->once()
			->with( 'https://example.com' );
		$product->shouldReceive( 'set_button_text' )
			->once()
			->with( 'Click' );
		$product->shouldReceive( 'set_notice' )
			->once()
			->with( 'A notice' );

		// Location read returns empty, merged with defaults.
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
		$datastore = new Datastore_Auction();

		// Mock Product_Auction with zero ID.
		$product = Mockery::mock( Product_Auction::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( 0 );

		// Verify that get_extra_data_keys() is NOT called.
		$product->shouldNotReceive( 'get_extra_data_keys' );

		// Call read method.
		$datastore->read( $product );

		$this->assertTrue( true );
	}

	/**
	 * Test read() skips non-Product_Auction instances.
	 *
	 * This test ensures that read() only processes Product_Auction instances
	 * and skips others after calling parent::read().
	 *
	 * @return void
	 */
	public function test_read_skips_non_product_auction(): void {
		$datastore = new Datastore_Auction();

		// Mock regular WC_Product.
		$product = Mockery::mock( WC_Product::class );

		// Verify that get_extra_data_keys() is NOT called.
		$product->shouldNotReceive( 'get_extra_data_keys' );

		// Call read method.
		$datastore->read( $product );

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
		$datastore = new Datastore_Auction();

		// Mock Product_Auction with zero ID.
		$product = Mockery::mock( Product_Auction::class );
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
		$product_id = 192021;
		$datastore  = new Datastore_Auction();

		// Mock Product_Auction.
		$product = Mockery::mock( Product_Auction::class );
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
	 * Test update_post_meta() is called with wp_slash() for string values.
	 *
	 * This test ensures that string values are properly slashed
	 * before being saved to the database.
	 *
	 * @return void
	 */
	public function test_save_uses_wp_slash_for_string_values(): void {
		$product_id = 222324;
		$datastore  = new Datastore_Auction();

		// Mock Product_Auction.
		$product = Mockery::mock( Product_Auction::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		// Setup get_extra_data_keys() with aucteeno_notice and aucteeno_location.
		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( array( 'aucteeno_notice', 'aucteeno_location' ) );

		$test_notice = "Test notice with 'quotes' and \"double quotes\"";
		$product->shouldReceive( 'get_aucteeno_notice' )
			->once()
			->with( 'edit' )
			->andReturn( $test_notice );

		// Mock location since save_auction_extra_data() with null changes calls save_location_data().
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
		$slashed_notice = addslashes( $test_notice );
		Functions\expect( 'wp_slash' )
			->once()
			->with( $test_notice )
			->andReturn( $slashed_notice );

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
		$method     = $reflection->getMethod( 'save_auction_extra_data' );
		$method->setAccessible( true );
		$method->invoke( $datastore, $product );

		// Verify the notice was saved with slashed value.
		$this->assertArrayHasKey( '_aucteeno_notice', $saved_meta );
		$this->assertSame( $slashed_notice, $saved_meta['_aucteeno_notice'] );
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
		$datastore  = new Datastore_Auction();

		// Mock Product_Auction.
		$product = Mockery::mock( Product_Auction::class );
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
	 * Test save_auction_extra_data() with changes that include aucteeno_location.
	 *
	 * When $changes includes aucteeno_location, save_location_data should be called.
	 *
	 * @return void
	 */
	public function test_update_saves_location_when_location_changed(): void {
		$product_id = 282930;
		$datastore  = new Datastore_Auction();

		// Mock Product_Auction.
		$product = Mockery::mock( Product_Auction::class );
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
		$method     = $reflection->getMethod( 'save_auction_extra_data' );
		$method->setAccessible( true );
		$method->invoke( $datastore, $product, $extra_data_changes );

		// Verify wp_set_post_terms was called with correct taxonomy.
		$this->assertCount( 1, $wp_set_post_terms_calls );
		$this->assertSame( 'aucteeno-location', $wp_set_post_terms_calls[0]['taxonomy'] );
	}

	/**
	 * Test save_auction_extra_data() does NOT save location when location is not in changes.
	 *
	 * When $changes does NOT include aucteeno_location, save_location_data should NOT be called.
	 *
	 * @return void
	 */
	public function test_update_does_not_save_location_when_not_changed(): void {
		$product_id = 313233;
		$datastore  = new Datastore_Auction();

		// Mock Product_Auction.
		$product = Mockery::mock( Product_Auction::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( $product_id );

		$product->shouldReceive( 'get_extra_data_keys' )
			->once()
			->andReturn( $this->all_extra_data_keys );

		// Only aucteeno_notice changed (not location).
		$product->shouldReceive( 'get_aucteeno_notice' )
			->once()
			->with( 'edit' )
			->andReturn( 'Changed notice' );

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
			'aucteeno_notice' => 'Changed notice',
		);

		// Use reflection to call the private save method.
		$reflection = new \ReflectionClass( $datastore );
		$method     = $reflection->getMethod( 'save_auction_extra_data' );
		$method->setAccessible( true );
		$method->invoke( $datastore, $product, $extra_data_changes );

		$this->assertTrue( true );
	}

	/**
	 * Test save_auction_extra_data() returns early when product ID is zero.
	 *
	 * @return void
	 */
	public function test_save_returns_early_when_no_product_id(): void {
		$datastore = new Datastore_Auction();

		// Mock Product_Auction with zero ID.
		$product = Mockery::mock( Product_Auction::class );
		$product->shouldReceive( 'get_id' )
			->andReturn( 0 );

		// Nothing else should be called.
		$product->shouldNotReceive( 'get_extra_data_keys' );

		// Use reflection to call private method.
		$reflection = new \ReflectionClass( $datastore );
		$method     = $reflection->getMethod( 'save_auction_extra_data' );
		$method->setAccessible( true );
		$method->invoke( $datastore, $product );

		$this->assertTrue( true );
	}
}
