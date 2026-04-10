<?php
/**
 * Eager_Loader Tests
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Database;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Database\Eager_Loader;

/**
 * Class Eager_Loader_Test
 */
class Eager_Loader_Test extends TestCase {

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
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
	 * Test that prime_post_meta calls _prime_post_caches with correct parameters.
	 *
	 * @return void
	 */
	public function test_prime_post_meta_calls_prime_post_caches_with_meta_enabled(): void {
		Functions\expect( '_prime_post_caches' )
			->once()
			->with( array( 1, 2 ), false, true );

		Eager_Loader::prime_post_meta( array( 1, 2 ) );

		// Expectation verified by Mockery::close() in tearDown().
		$this->addToAssertionCount( 1 );
	}

	/**
	 * Test that prime_post_meta does nothing for empty IDs.
	 *
	 * @return void
	 */
	public function test_prime_post_meta_does_nothing_for_empty_ids(): void {
		// Brain\Monkey will fail the test if _prime_post_caches is called unexpectedly.
		Eager_Loader::prime_post_meta( array() );
		$this->assertTrue( true ); // Reached without unexpected calls.
	}

	// NOTE: The fallback path in prime_post_meta() (when function_exists('_prime_post_caches')
	// is false) is not unit-tested here. Once Brain\Monkey creates _prime_post_caches via
	// Functions\expect() in the happy-path test, function_exists() returns true for the entire
	// PHP process lifetime — making the fallback path unreachable in subsequent tests.
	// The fallback is also dead code in production since WordPress 6.9+ is required.
	// Correctness can be verified by code inspection.

	/**
	 * Test that prime_images returns post_id to image_id map.
	 *
	 * @return void
	 */
	public function test_prime_images_returns_post_id_to_image_id_map(): void {
		Functions\when( 'get_post_meta' )
			->alias( function ( $id, $key, $single ) {
				return $id === 10 ? '55' : '';
			} );
		Functions\when( '_prime_post_caches' )->justReturn( null );

		$map = Eager_Loader::prime_images( array( 10, 11 ) );

		$this->assertSame( array( 10 => 55, 11 => 0 ), $map );
	}

	/**
	 * Test that prime_images primes attachment caches for found thumbnails.
	 *
	 * @return void
	 */
	public function test_prime_images_primes_attachment_caches_for_found_thumbnails(): void {
		Functions\when( 'get_post_meta' )
			->alias( function ( $id, $key, $single ) {
				return '55';
			} );

		Functions\expect( '_prime_post_caches' )
			->once()
			->with( array( 55 ), false, true );

		Eager_Loader::prime_images( array( 10 ) );

		$this->addToAssertionCount( 1 );
	}

	/**
	 * Test that prime_images skips attachment prime when no thumbnails.
	 *
	 * @return void
	 */
	public function test_prime_images_skips_attachment_prime_when_no_thumbnails(): void {
		Functions\when( 'get_post_meta' )->justReturn( '' );
		// No _prime_post_caches expected — Brain\Monkey will fail if called unexpectedly.

		$map = Eager_Loader::prime_images( array( 10 ) );

		$this->assertSame( array( 10 => 0 ), $map );
	}

	/**
	 * Test that prime_images deduplicates attachment IDs.
	 *
	 * @return void
	 */
	public function test_prime_images_deduplicates_attachment_ids(): void {
		// Two posts share the same thumbnail.
		Functions\when( 'get_post_meta' )->justReturn( '55' );

		Functions\expect( '_prime_post_caches' )
			->once()
			->with( array( 55 ), false, true ); // Deduplicated: [55, 55] → [55]

		Eager_Loader::prime_images( array( 10, 11 ) );

		$this->addToAssertionCount( 1 );
	}

	/**
	 * Test that load_location_terms returns code to term ID map.
	 *
	 * @return void
	 */
	public function test_load_location_terms_returns_code_to_term_id_map(): void {
		$term_us = (object) array( 'term_id' => 42 );
		$term_ks = (object) array( 'term_id' => 43 );

		Functions\expect( 'get_terms' )
			->once()
			->andReturn( array( $term_us, $term_ks ) );

		Functions\when( 'get_term_meta' )
			->alias( function ( $term_id, $key, $single ) {
				$map = array(
					42 => 'US',
					43 => 'US:KS',
				);
				return $map[ $term_id ] ?? '';
			} );

		Functions\when( 'is_wp_error' )->justReturn( false );

		$map = Eager_Loader::load_location_terms( array( 'US', 'US:KS' ) );

		$this->assertSame( array( 'US' => 42, 'US:KS' => 43 ), $map );
	}

	/**
	 * Test that load_location_terms returns empty map and skips query for empty codes.
	 *
	 * @return void
	 */
	public function test_load_location_terms_returns_empty_map_and_skips_query_for_empty_codes(): void {
		Functions\expect( 'get_terms' )->never();

		$map = Eager_Loader::load_location_terms( array() );

		$this->assertSame( array(), $map );
	}

	/**
	 * Test that load_location_terms filters empty strings.
	 *
	 * @return void
	 */
	public function test_load_location_terms_filters_empty_strings(): void {
		Functions\expect( 'get_terms' )->never();

		$map = Eager_Loader::load_location_terms( array( '', '', '' ) );

		$this->assertSame( array(), $map );
	}

	/**
	 * Test that load_location_terms deduplicates codes before querying.
	 *
	 * @return void
	 */
	public function test_load_location_terms_deduplicates_codes_before_querying(): void {
		Functions\when( 'is_wp_error' )->justReturn( false );

		Functions\expect( 'get_terms' )
			->once()
			->andReturnUsing( function ( $args ) {
				// Confirm only one unique code is passed.
				$this->assertSame( array( 'US' ), $args['meta_query'][0]['value'] );
				return array();
			} );

		Eager_Loader::load_location_terms( array( 'US', 'US', 'US' ) );
	}

	/**
	 * Test that load_location_terms returns empty map on wp_error.
	 *
	 * @return void
	 */
	public function test_load_location_terms_returns_empty_map_on_wp_error(): void {
		$error = new \WP_Error( 'invalid_taxonomy', 'Invalid taxonomy.' );

		Functions\expect( 'get_terms' )->once()->andReturn( $error );
		Functions\when( 'is_wp_error' )->justReturn( true );

		$map = Eager_Loader::load_location_terms( array( 'US' ) );

		$this->assertSame( array(), $map );
	}
}
