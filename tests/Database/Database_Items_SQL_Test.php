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
	 * Database_Items instance under test.
	 *
	 * @var Database_Items
	 */
	private Database_Items $db_items;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->db_items = new Database_Items();

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

		// get_status_counts: two get_var calls (running + upcoming) only.
		// With include_expired=false (the default), the expired branch is skipped.
		$wpdb->shouldReceive( 'get_var' )->times( 2 )->andReturn( '0' );
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

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

		$this->db_items->query_for_listing( array( 'sort' => 'newest' ) );

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

		// get_status_counts: two get_var calls (running + upcoming).
		// Return running=1, upcoming=1 so both branches fire.
		// With include_expired defaulting to false, no expired get_var is issued.
		$wpdb->shouldReceive( 'get_var' )
			->times( 2 )
			->andReturn( '1', '1' );
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

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

		$this->db_items->query_for_listing( array( 'sort' => 'status_ending_soon' ) );

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

		// get_status_counts: two get_var calls (running + upcoming).
		// Return running=2, upcoming=1 so active branch fires.
		$wpdb->shouldReceive( 'get_var' )
			->times( 2 )
			->andReturn( '2', '1' );
		// get_expired_count: cache miss, then one get_var for the count.
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		$wpdb->shouldReceive( 'get_var' )->once()->andReturn( '0' );

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

		$this->db_items->query_for_listing( array( 'sort' => 'ending_soon' ) );

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

	/**
	 * Build status filter excludes expired status by default.
	 *
	 * @return void
	 */
	public function test_build_status_filter_excludes_expired_by_default(): void {
		$reflection = new \ReflectionClass( Database_Items::class );
		$method     = $reflection->getMethod( 'build_status_filter' );
		$method->setAccessible( true );

		$filter = $method->invoke( null, 'i', false );

		$this->assertStringContainsString( 'i.bidding_status = 10', $filter );
		$this->assertStringContainsString( 'i.bidding_status = 20', $filter );
		$this->assertStringNotContainsString( 'i.bidding_status = 30', $filter );
	}

	/**
	 * Build status filter includes expired status when opted in.
	 *
	 * @return void
	 */
	public function test_build_status_filter_includes_expired_when_opted_in(): void {
		$reflection = new \ReflectionClass( Database_Items::class );
		$method     = $reflection->getMethod( 'build_status_filter' );
		$method->setAccessible( true );

		$filter = $method->invoke( null, 'i', true );

		$this->assertStringContainsString( 'i.bidding_status = 10', $filter );
		$this->assertStringContainsString( 'i.bidding_status = 20', $filter );
		$this->assertStringContainsString( 'i.bidding_status = 30', $filter );
	}

	/**
	 * Verify get_status_counts skips the expired get_var call when include_expired is false.
	 *
	 * Asserts exactly 2 get_var invocations (running + upcoming); the expired
	 * branch must NOT fire, so Mockery will fail if a third call occurs.
	 *
	 * @return void
	 */
	public function test_get_status_counts_skips_expired_when_excluded(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->posts  = 'wp_posts';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		// Running + upcoming = 2 calls; expired count must NOT fire.
		$wpdb->shouldReceive( 'get_var' )->times( 2 )->andReturn( '0' );

		$reflection = new \ReflectionClass( Database_Items::class );
		$method     = $reflection->getMethod( 'get_status_counts' );
		$method->setAccessible( true );

		$counts = $method->invoke( $this->db_items, 'wp_aucteeno_items', '1=1', array(), false );

		$this->assertSame( 0, $counts['running'] );
		$this->assertSame( 0, $counts['upcoming'] );
		$this->assertSame( 0, $counts['expired'] );
	}

	/**
	 * Verify get_status_counts runs the expired get_var call when include_expired is true.
	 *
	 * Asserts exactly 3 get_var invocations (running + upcoming + expired).
	 *
	 * @return void
	 */
	public function test_get_status_counts_runs_expired_when_opted_in(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->posts  = 'wp_posts';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		// Running + upcoming + expired = 3 calls.
		$wpdb->shouldReceive( 'get_var' )->times( 3 )->andReturn( '0' );

		$reflection = new \ReflectionClass( Database_Items::class );
		$method     = $reflection->getMethod( 'get_status_counts' );
		$method->setAccessible( true );

		$counts = $method->invoke( $this->db_items, 'wp_aucteeno_items', '1=1', array(), true );

		$this->assertSame( 0, $counts['running'] );
		$this->assertSame( 0, $counts['upcoming'] );
		$this->assertSame( 0, $counts['expired'] );
	}

	/**
	 * Verify that query_for_listing_by_lot honors include_expired=false.
	 *
	 * With include_expired=false the data SQL must include status 10 and 20
	 * branches but must NOT include status 30 (expired).
	 *
	 * @return void
	 */
	public function test_by_lot_honors_include_expired(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->posts  = 'wp_posts';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'absint' )->alias( fn( $v ) => (int) abs( (int) $v ) );
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);

		$wpdb->shouldReceive( 'get_var' )->andReturn( '0' );

		$captured_data_sql = null;
		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				function ( $sql, ...$args ) use ( &$captured_data_sql ) {
					if ( str_contains( $sql, 'LIMIT %d OFFSET %d' ) ) {
						$captured_data_sql = $sql;
					}
					return 'PREPARED_SQL';
				}
			);
		$wpdb->shouldReceive( 'get_results' )->andReturn( array() );

		$this->db_items->query_for_listing(
			array(
				'sort'            => 'lot_number',
				'include_expired' => false,
			)
		);

		$this->assertNotNull( $captured_data_sql );
		$this->assertStringContainsString( 'i.bidding_status = 10', $captured_data_sql );
		$this->assertStringContainsString( 'i.bidding_status = 20', $captured_data_sql );
		$this->assertStringNotContainsString( 'i.bidding_status = 30', $captured_data_sql );
	}

	/**
	 * Verify that query_for_listing_by_status skips the expired group SQL when include_expired is false.
	 *
	 * When include_expired=false no SQL containing `i.bidding_status = 30` should ever
	 * be passed to prepare(); expired group must be fully skipped.
	 *
	 * @return void
	 */
	public function test_by_status_skips_expired_group_when_excluded(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->posts  = 'wp_posts';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'absint' )->alias( fn( $v ) => (int) abs( (int) $v ) );
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);

		// Counts: running + upcoming = 2 get_var (expired skipped via get_status_counts).
		$wpdb->shouldReceive( 'get_var' )->andReturn( '5' );  // 5 each so groups have rows.

		$captured_sqls = array();
		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				function ( $sql, ...$args ) use ( &$captured_sqls ) {
					$captured_sqls[] = $sql;
					return 'PREPARED_SQL';
				}
			);
		$wpdb->shouldReceive( 'get_results' )->andReturn( array() );

		$this->db_items->query_for_listing(
			array(
				'sort'            => 'status_ending_soon',
				'include_expired' => false,
			)
		);

		$sql_blob = implode( "\n---\n", $captured_sqls );
		$this->assertStringNotContainsString( 'i.bidding_status = 30', $sql_blob, 'expired group must not query when excluded' );
	}

	/**
	 * Verify that query_for_listing_by_status includes the expired group SQL when include_expired is true.
	 *
	 * Per_page=15 with running=5 + upcoming=5 = 10 active items means the query spills
	 * into the expired group, so `i.bidding_ends_at <= UNIX_TIMESTAMP()` must appear in prepare() SQL.
	 *
	 * @return void
	 */
	public function test_by_status_includes_expired_group_when_opted_in(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->posts  = 'wp_posts';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'absint' )->alias( fn( $v ) => (int) abs( (int) $v ) );
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);

		$wpdb->shouldReceive( 'get_var' )->andReturn( '5' );

		$captured_sqls = array();
		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				function ( $sql, ...$args ) use ( &$captured_sqls ) {
					$captured_sqls[] = $sql;
					return 'PREPARED_SQL';
				}
			);
		$wpdb->shouldReceive( 'get_results' )->andReturn( array() );

		// per_page large enough to walk into the expired group (running=5 + upcoming=5 = 10; per_page=15 spills into expired).
		$this->db_items->query_for_listing(
			array(
				'sort'            => 'status_ending_soon',
				'per_page'        => 15,
				'include_expired' => true,
			)
		);

		$sql_blob = implode( "\n---\n", $captured_sqls );
		// The expired group SQL carries a unique timestamp condition; the status placeholder
		// is %d (not literal 30) since prepare() captures the raw template.
		$this->assertStringContainsString( 'i.bidding_ends_at <= UNIX_TIMESTAMP()', $sql_blob, 'expired group must query when opted in' );
	}

	/**
	 * Verify that query_for_listing threads include_expired through to private query methods.
	 *
	 * @return void
	 */
	public function test_dispatcher_threads_include_expired_to_private_methods(): void {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->posts  = 'wp_posts';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'absint' )->alias( fn( $v ) => (int) abs( (int) $v ) );
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);

		$wpdb->shouldReceive( 'get_var' )->andReturn( '0' );

		$captured_data_sql = null;

		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				function ( $sql, ...$args ) use ( &$captured_data_sql ) {
					if ( str_contains( $sql, 'LIMIT %d OFFSET %d' ) ) {
						$captured_data_sql = $sql;
					}
					return 'PREPARED_SQL';
				}
			);

		$wpdb->shouldReceive( 'get_results' )->andReturn( array() );

		$this->db_items->query_for_listing(
			array(
				'sort'            => 'newest',
				'include_expired' => true,
			)
		);

		$this->assertNotNull( $captured_data_sql );
		$this->assertStringContainsString( 'i.bidding_status = 30', $captured_data_sql );
	}
}
