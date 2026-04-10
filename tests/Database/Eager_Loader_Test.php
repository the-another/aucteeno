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

	/**
	 * Test that prime_post_meta falls back to get_post_meta when _prime_post_caches not available.
	 *
	 * @return void
	 */
	public function test_prime_post_meta_falls_back_to_get_post_meta_when_prime_not_available(): void {
		// Do NOT register _prime_post_caches — function_exists() will return false for it.
		// Only expect get_post_meta to be called as fallback.
		Functions\expect( 'get_post_meta' )
			->twice()
			->with( \Mockery::type( 'int' ) );

		Eager_Loader::prime_post_meta( array( 1, 2 ) );

		// Expectation verified by Mockery::close() in tearDown().
		$this->addToAssertionCount( 1 );
	}

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
}
