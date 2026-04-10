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
}
