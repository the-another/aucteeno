<?php
/**
 * Database_Items SQL structure Tests
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Database;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Database\Database_Items;

// Define WordPress constants if not already defined.
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

/**
 * Class Database_Items_SQL_Test
 *
 * Tests that SQL queries built by Database_Items contain the auction_post_name
 * column and the LEFT JOIN on the auctions posts table.
 */
class Database_Items_SQL_Test extends TestCase {

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);
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
	 * Test that query_for_listing_newest SQL contains auction_post_name column.
	 *
	 * @return void
	 */
	public function test_query_for_listing_newest_contains_auction_post_name(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->posts  = 'wp_posts';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		$sql_captured = null;

		// Count query (no placeholders, uses get_var directly).
		$wpdb->shouldReceive( 'get_var' )->once()->andReturn( '0' );

		// Main query prepare — capture the SQL.
		$wpdb->shouldReceive( 'prepare' )
			->once()
			->with(
				Mockery::on(
					function ( $sql ) use ( &$sql_captured ) {
						$sql_captured = $sql;
						return true;
					}
				),
				Mockery::any()
			)
			->andReturn( 'PREPARED_SQL' );

		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );

		Database_Items::query_for_listing( array( 'sort' => 'newest' ) );

		$this->assertNotNull( $sql_captured, 'prepare() was not called' );
		$this->assertStringContainsString(
			'auction_post_name',
			$sql_captured,
			'SQL should contain auction_post_name alias'
		);
		$this->assertStringContainsString(
			'LEFT JOIN',
			$sql_captured,
			'SQL should contain LEFT JOIN for auction posts'
		);
	}

	/**
	 * Test that query_status_group SQL contains auction_post_name column.
	 *
	 * Triggered via query_for_listing() with default sort (ending_soon / by_status path).
	 *
	 * @return void
	 */
	public function test_query_status_group_contains_auction_post_name(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->posts  = 'wp_posts';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		$sql_captured = null;

		// get_status_counts uses prepare (has no WHERE values for default args, skips prepare)
		// then get_results for count results.
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					array(
						'bidding_status' => '10',
						'cnt'            => '1',
					),
				)
			);

		// query_status_group calls prepare for the main query — capture SQL.
		$wpdb->shouldReceive( 'prepare' )
			->once()
			->with(
				Mockery::on(
					function ( $sql ) use ( &$sql_captured ) {
						$sql_captured = $sql;
						return true;
					}
				),
				Mockery::any()
			)
			->andReturn( 'PREPARED_SQL' );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		Database_Items::query_for_listing( array( 'sort' => 'ending_soon' ) );

		$this->assertNotNull( $sql_captured, 'prepare() was not called for query_status_group' );
		$this->assertStringContainsString(
			'auction_post_name',
			$sql_captured,
			'SQL should contain auction_post_name alias'
		);
		$this->assertStringContainsString(
			'LEFT JOIN',
			$sql_captured,
			'SQL should contain LEFT JOIN for auction posts'
		);
	}
}
