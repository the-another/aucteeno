<?php
/**
 * Database_Auctions Tests
 *
 * Unit tests for Database_Auctions class.
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Database;

use Brain\Monkey;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Database\Database_Auctions;

// Define WordPress constants if not already defined.
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

/**
 * Class Database_Auctions_Test
 *
 * Tests for Database_Auctions functionality.
 */
class Database_Auctions_Test extends TestCase {

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
	 * Test that get_stale returns stale auction rows.
	 *
	 * @return void
	 */
	public function test_get_stale_returns_stale_auction_rows(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		$expected = array(
			array(
				'auction_id'        => 1,
				'bidding_starts_at' => 1000,
				'bidding_ends_at'   => 9999,
				'bidding_status'    => 20,
			),
			array(
				'auction_id'        => 2,
				'bidding_starts_at' => 500,
				'bidding_ends_at'   => 600,
				'bidding_status'    => 10,
			),
		);

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'PREPARED_SQL' );
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->with( 'PREPARED_SQL', ARRAY_A )
			->andReturn( $expected );

		$result = Database_Auctions::get_stale( 500 );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test that get_stale returns empty array when no stale rows exist.
	 *
	 * @return void
	 */
	public function test_get_stale_returns_empty_array_when_no_stale_rows(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'PREPARED_SQL' );
		$wpdb->shouldReceive( 'get_results' )->once()->with( 'PREPARED_SQL', ARRAY_A )->andReturn( array() );

		$result = Database_Auctions::get_stale( 500 );

		$this->assertSame( array(), $result );
	}

	/**
	 * Test that update_bidding_status_batch issues single update.
	 *
	 * @return void
	 */
	public function test_update_bidding_status_batch_issues_single_update(): void {
		$wpdb            = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$GLOBALS['wpdb'] = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'UPDATE_SQL' );
		$wpdb->shouldReceive( 'query' )
			->once()
			->with( 'UPDATE_SQL' )
			->andReturn( 3 ); // 3 rows affected

		$result = Database_Auctions::update_bidding_status_batch( array( 1, 2, 3 ), 10 );

		$this->assertTrue( $result );
	}

	/**
	 * Test that update_bidding_status_batch returns false on db error.
	 *
	 * @return void
	 */
	public function test_update_bidding_status_batch_returns_false_on_db_error(): void {
		$wpdb            = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$GLOBALS['wpdb'] = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'UPDATE_SQL' );
		$wpdb->shouldReceive( 'query' )->once()->andReturn( false );

		$result = Database_Auctions::update_bidding_status_batch( array( 1 ), 30 );

		$this->assertFalse( $result );
	}
}
