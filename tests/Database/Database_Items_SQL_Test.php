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
	 * Triggered via query_for_listing() with status_ending_soon sort (3-group / by_status path).
	 * Returns both status=10 (running) and status=20 (upcoming) rows from the count query
	 * so that query_status_group() is invoked for two different branches. Because the
	 * SELECT/JOIN template is shared across all branches (only the timestamp_condition
	 * differs), both captured SQL strings must contain auction_post_name and LEFT JOIN.
	 *
	 * @return void
	 */
	public function test_query_status_group_contains_auction_post_name(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->posts  = 'wp_posts';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		$captured_sqls = array();

		// get_status_counts skips prepare (no WHERE values for default args) and calls
		// get_results directly.  Return two status rows so the running (10) and upcoming
		// (20) branches both fire, each issuing their own prepare() + get_results() call.
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					array(
						'bidding_status' => '10',
						'cnt'            => '1',
					),
					array(
						'bidding_status' => '20',
						'cnt'            => '1',
					),
				)
			);

		// query_status_group calls prepare for each status branch — capture both SQLs.
		$wpdb->shouldReceive( 'prepare' )
			->twice()
			->with(
				Mockery::on(
					function ( $sql ) use ( &$captured_sqls ) {
						$captured_sqls[] = $sql;
						return true;
					}
				),
				Mockery::any()
			)
			->andReturn( 'PREPARED_SQL' );

		// Two get_results calls: one per status group query.
		$wpdb->shouldReceive( 'get_results' )
			->twice()
			->andReturn( array() );

		Database_Items::query_for_listing( array( 'sort' => 'status_ending_soon' ) );

		$this->assertCount( 2, $captured_sqls, 'prepare() should be called once per status branch' );

		foreach ( $captured_sqls as $index => $sql ) {
			$branch = 0 === $index ? 'status-10 (running)' : 'status-20 (upcoming)';
			$this->assertStringContainsString(
				'auction_post_name',
				$sql,
				"SQL for $branch branch should contain auction_post_name alias"
			);
			$this->assertStringContainsString(
				'LEFT JOIN',
				$sql,
				"SQL for $branch branch should contain LEFT JOIN for auction posts"
			);
		}
	}

	/**
	 * Test that ending_soon sort uses the 2-group path (query_combined_status_group).
	 *
	 * The ending_soon sort dispatches to query_for_listing_ending_soon() which calls
	 * query_combined_status_group() for active items (status 10 + 20 in one query).
	 * With default args (no filters), the flow is:
	 *   1. get_status_counts: get_results (no prepare — $where_values is empty)
	 *   2. query_combined_status_group: prepare + get_results
	 *
	 * The captured SQL should contain both status conditions in a single query.
	 *
	 * @return void
	 */
	public function test_ending_soon_uses_combined_status_group(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->posts  = 'wp_posts';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		$sql_captured = null;

		// get_status_counts: calls get_results directly (no prepare, $where_values empty).
		// Return active items so query_combined_status_group fires.
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					array(
						'bidding_status' => '10',
						'cnt'            => '2',
					),
					array(
						'bidding_status' => '20',
						'cnt'            => '1',
					),
				)
			);

		// query_combined_status_group: prepare called once with the combined SQL.
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

		// query_combined_status_group: get_results for the combined query.
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		Database_Items::query_for_listing( array( 'sort' => 'ending_soon' ) );

		$this->assertNotNull( $sql_captured, 'prepare() was not called for combined status group' );
		$this->assertStringContainsString(
			'bidding_status = %d',
			$sql_captured,
			'SQL should contain bidding_status placeholder'
		);
		// The combined query should have both status 10 and 20 conditions in one query
		// via OR-joined conditions.
		$this->assertStringContainsString(
			'OR',
			$sql_captured,
			'Combined status group SQL should contain OR to join multiple status conditions'
		);
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
