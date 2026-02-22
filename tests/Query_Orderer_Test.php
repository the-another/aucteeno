<?php
/**
 * Query Orderer Tests
 *
 * Unit tests for Query_Orderer class.
 *
 * @package Aucteeno
 * @since 2.2.0
 */

namespace TheAnother\Plugin\Aucteeno\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use TheAnother\Plugin\Aucteeno\Database\Query_Orderer;
use WP_Query;

/**
 * Class Query_Orderer_Test
 *
 * Tests for Query_Orderer functionality.
 */
class Query_Orderer_Test extends TestCase {

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
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test that Query_Orderer detects eligible queries.
	 *
	 * @return void
	 */
	public function test_detects_eligible_queries(): void {
		Functions\expect( 'is_admin' )->once()->andReturn( false );
		Functions\expect( 'wp_cache_get' )->andReturn( array( 1, 2, 3 ) );

		$query = new WP_Query();
		$query->set( 'post_type', 'product' );
		$query->set(
			'tax_query',
			array(
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => 'aucteeno-ext-item',
				),
			)
		);

		$orderer = new Query_Orderer();
		$orderer->maybe_apply_custom_ordering( $query );

		$this->assertTrue( $query->get( 'aucteeno_custom_order' ) );
		$this->assertEquals( 'items', $query->get( 'aucteeno_order_type' ) );
	}

	/**
	 * Test that Query_Orderer ignores non-product queries.
	 *
	 * @return void
	 */
	public function test_ignores_non_product_queries(): void {
		$query = new WP_Query();
		$query->set( 'post_type', 'post' );

		$orderer = new Query_Orderer();
		$orderer->maybe_apply_custom_ordering( $query );

		$this->assertFalse( $query->get( 'aucteeno_custom_order' ) );
	}

	/**
	 * Test cache key generation.
	 *
	 * @return void
	 */
	public function test_cache_key_generation(): void {
		$query = new WP_Query();
		$query->set( 'aucteeno_order_type', 'items' );
		$query->set( 'paged', 1 );
		$query->set( 'posts_per_page', 10 );

		$orderer = new Query_Orderer();
		$reflection = new \ReflectionClass( $orderer );
		$method = $reflection->getMethod( 'get_cache_key' );
		$method->setAccessible( true );

		$key1 = $method->invoke( $orderer, $query, 'ids' );
		$key2 = $method->invoke( $orderer, $query, 'ids' );

		// Same query should generate same cache key.
		$this->assertEquals( $key1, $key2 );
	}
}

