<?php
/**
 * Database_Items stale-query Tests
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Database;

use Brain\Monkey;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Database\Database_Items;

// Define WordPress constants if not already defined.
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

/**
 * Class Database_Items_Stale_Test
 *
 * Tests for Database_Items stale-query functionality.
 */
class Database_Items_Stale_Test extends TestCase {

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
	 * Test that get_stale selects item_id column.
	 *
	 * @return void
	 */
	public function test_get_stale_selects_item_id_column(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		$expected = array(
			array(
				'item_id'           => 10,
				'bidding_starts_at' => 100,
				'bidding_ends_at'   => 999,
				'bidding_status'    => 20,
			),
		);

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->with( Mockery::on( fn( $sql ) => str_contains( $sql, 'item_id' ) && ! str_contains( $sql, 'auction_id' ) ), Mockery::any() )
			->andReturn( 'PREPARED_SQL' );
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( $expected );

		$result = Database_Items::get_stale( 500 );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test that update_bidding_status_batch uses item_id column.
	 *
	 * @return void
	 */
	public function test_update_bidding_status_batch_uses_item_id_column(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->with( Mockery::on( fn( $sql ) => str_contains( $sql, 'item_id' ) ), Mockery::any() )
			->andReturn( 'UPDATE_SQL' );
		$wpdb->shouldReceive( 'query' )->once()->andReturn( 2 );

		$result = Database_Items::update_bidding_status_batch( array( 10, 11 ), 30 );

		$this->assertTrue( $result );
	}
}
