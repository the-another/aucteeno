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
	 * Database_Auctions instance under test.
	 *
	 * @var Database_Auctions
	 */
	private Database_Auctions $db_auctions;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->db_auctions = new Database_Auctions();
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

		$result = $this->db_auctions->get_stale( 500 );

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

		$result = $this->db_auctions->get_stale( 500 );

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

		$result = $this->db_auctions->update_bidding_status_batch( array( 1, 2, 3 ), 10 );

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

		$result = $this->db_auctions->update_bidding_status_batch( array( 1 ), 30 );

		$this->assertFalse( $result );
	}

	public function test_build_status_filter_excludes_expired_by_default(): void {
		$reflection = new \ReflectionClass( Database_Auctions::class );
		$method     = $reflection->getMethod( 'build_status_filter' );
		$method->setAccessible( true );

		$filter = $method->invoke( null, 'a', false );

		$this->assertStringContainsString( 'a.bidding_status = 10', $filter );
		$this->assertStringContainsString( 'a.bidding_status = 20', $filter );
		$this->assertStringNotContainsString( 'a.bidding_status = 30', $filter );
	}

	public function test_build_status_filter_includes_expired_when_opted_in(): void {
		$reflection = new \ReflectionClass( Database_Auctions::class );
		$method     = $reflection->getMethod( 'build_status_filter' );
		$method->setAccessible( true );

		$filter = $method->invoke( null, 'a', true );

		$this->assertStringContainsString( 'a.bidding_status = 10', $filter );
		$this->assertStringContainsString( 'a.bidding_status = 20', $filter );
		$this->assertStringContainsString( 'a.bidding_status = 30', $filter );
	}

	public function test_query_for_listing_excludes_expired_by_default(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->posts  = 'wp_posts';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		Monkey\Functions\when( 'wp_cache_get' )->justReturn( false );
		Monkey\Functions\when( 'wp_cache_set' )->justReturn( true );
		Monkey\Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Monkey\Functions\when( 'absint' )->alias( fn( $v ) => (int) abs( (int) $v ) );
		Monkey\Functions\when( 'wp_parse_args' )->alias( function ( $args, $defaults ) {
			if ( ! is_array( $args ) ) {
				return $defaults;
			}
			return array_merge( $defaults, $args );
		} );

		// Counts: running + upcoming get_var calls only when include_expired=false.
		// The test asserts the SQL of the data query, not the count behaviour (Task 3).
		// Stub all get_var to return '0' generously to avoid count interference.
		$wpdb->shouldReceive( 'get_var' )->andReturn( '0' );

		$captured_data_sql = null;

		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				function ( $sql, ...$args ) use ( &$captured_data_sql ) {
					// The data query is the one carrying LIMIT %d OFFSET %d.
					if ( str_contains( $sql, 'LIMIT %d OFFSET %d' ) ) {
						$captured_data_sql = $sql;
					}
					return 'PREPARED_SQL';
				}
			);

		$wpdb->shouldReceive( 'get_results' )->andReturn( array() );

		$db_auctions = new Database_Auctions();
		$db_auctions->query_for_listing( array() );  // default args.

		$this->assertNotNull( $captured_data_sql, 'data query prepare() was not called' );
		$this->assertStringContainsString( 'a.bidding_status = 10', $captured_data_sql );
		$this->assertStringContainsString( 'a.bidding_status = 20', $captured_data_sql );
		$this->assertStringNotContainsString( 'a.bidding_status = 30', $captured_data_sql );
	}

	public function test_query_for_listing_includes_expired_when_opted_in(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->posts  = 'wp_posts';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		Monkey\Functions\when( 'wp_cache_get' )->justReturn( false );
		Monkey\Functions\when( 'wp_cache_set' )->justReturn( true );
		Monkey\Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Monkey\Functions\when( 'absint' )->alias( fn( $v ) => (int) abs( (int) $v ) );
		Monkey\Functions\when( 'wp_parse_args' )->alias( function ( $args, $defaults ) {
			if ( ! is_array( $args ) ) {
				return $defaults;
			}
			return array_merge( $defaults, $args );
		} );

		$wpdb->shouldReceive( 'get_var' )->andReturn( '0' );

		$captured_data_sql = null;

		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				function ( $sql, ...$args ) use ( &$captured_data_sql ) {
					if ( str_contains( $sql, 'LIMIT %d OFFSET %d' ) ) {
						$captured_data_sql = $sql;
					}
					return 'PREPARED_SQL';
				}
			);

		$wpdb->shouldReceive( 'get_results' )->andReturn( array() );

		$db_auctions = new Database_Auctions();
		$db_auctions->query_for_listing( array( 'include_expired' => true ) );

		$this->assertNotNull( $captured_data_sql );
		$this->assertStringContainsString( 'a.bidding_status = 30', $captured_data_sql );
	}
}
