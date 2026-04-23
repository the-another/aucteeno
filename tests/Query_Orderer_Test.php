<?php
/**
 * Query Orderer Tests
 *
 * Unit tests for Query_Orderer class.
 *
 * @package Aucteeno
 * @since 2.2.0
 */

namespace The_Another\Plugin\Aucteeno\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Database\Query_Orderer;
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
		Mockery::close();
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

		$orderer    = new Query_Orderer();
		$reflection = new \ReflectionClass( $orderer );
		$method     = $reflection->getMethod( 'get_cache_key' );
		$method->setAccessible( true );

		$key1 = $method->invoke( $orderer, $query, 'ids' );
		$key2 = $method->invoke( $orderer, $query, 'ids' );

		// Same query should generate same cache key.
		$this->assertEquals( $key1, $key2 );
	}

	/**
	 * Test cache key differs between include_expired=true and include_expired=false.
	 *
	 * Ensures cached results for the two modes are stored under distinct keys
	 * and don't bleed into each other.
	 *
	 * @return void
	 */
	public function test_cache_key_differs_by_include_expired(): void {
		$orderer    = new Query_Orderer();
		$reflection = new \ReflectionClass( $orderer );
		$method     = $reflection->getMethod( 'get_cache_key' );
		$method->setAccessible( true );

		$query_with = new WP_Query();
		$query_with->set( 'aucteeno_order_type', 'items' );
		$query_with->set( 'paged', 1 );
		$query_with->set( 'posts_per_page', 10 );
		$query_with->set( 'aucteeno_include_expired', true );

		$query_without = new WP_Query();
		$query_without->set( 'aucteeno_order_type', 'items' );
		$query_without->set( 'paged', 1 );
		$query_without->set( 'posts_per_page', 10 );
		$query_without->set( 'aucteeno_include_expired', false );

		$key_with    = $method->invoke( $orderer, $query_with, 'ids' );
		$key_without = $method->invoke( $orderer, $query_without, 'ids' );

		$this->assertNotEquals( $key_with, $key_without, 'Cache keys must differ when include_expired differs' );
	}

	/**
	 * Test get_ordered_item_ids SQL excludes bidding_status = 30 when include_expired is false.
	 *
	 * Verifies the items 3-group (status_ending_soon) path drops the expired
	 * UNION ALL subquery when aucteeno_include_expired is not set.
	 *
	 * @return void
	 */
	public function test_item_ids_excludes_expired_sql_when_not_requested(): void {
		$wpdb                     = \Mockery::mock( 'wpdb' );
		$wpdb->prefix             = 'wp_';
		$wpdb->posts              = 'wp_posts';
		$wpdb->terms              = 'wp_terms'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$wpdb->term_taxonomy      = 'wp_term_taxonomy'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$wpdb->term_relationships = 'wp_term_relationships'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'absint' )->alias( fn( $v ) => (int) abs( (int) $v ) );

		// get_product_type_ttid: one get_var call returning a TTID.
		$wpdb->shouldReceive( 'get_var' )->andReturn( '42' );

		$captured_sql = null;
		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				function ( $sql, ...$args ) use ( &$captured_sql ) {
					$captured_sql = $sql;
					return 'PREPARED_SQL';
				}
			);
		$wpdb->shouldReceive( 'get_col' )->andReturn( array() );

		$query = new WP_Query();
		$query->set( 'aucteeno_order_type', 'items' );
		$query->set( 'aucteeno_sort', 'status_ending_soon' );
		$query->set( 'aucteeno_include_expired', false );
		$query->set( 'paged', 1 );
		$query->set( 'posts_per_page', 10 );

		$orderer    = new Query_Orderer();
		$reflection = new \ReflectionClass( $orderer );
		$method     = $reflection->getMethod( 'get_ordered_item_ids' );
		$method->setAccessible( true );
		$method->invoke( $orderer, $query );

		$this->assertNotNull( $captured_sql, 'prepare() must have been called' );
		$this->assertStringNotContainsString(
			'bidding_status = 30',
			$captured_sql,
			'SQL must not reference expired status when include_expired is false'
		);
	}

	/**
	 * Test get_ordered_item_ids SQL includes bidding_status = 30 when include_expired is true.
	 *
	 * Verifies the items 3-group (status_ending_soon) path retains the expired
	 * UNION ALL subquery when aucteeno_include_expired is set.
	 *
	 * @return void
	 */
	public function test_item_ids_includes_expired_sql_when_requested(): void {
		$wpdb                     = \Mockery::mock( 'wpdb' );
		$wpdb->prefix             = 'wp_';
		$wpdb->posts              = 'wp_posts';
		$wpdb->terms              = 'wp_terms'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$wpdb->term_taxonomy      = 'wp_term_taxonomy'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$wpdb->term_relationships = 'wp_term_relationships'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'absint' )->alias( fn( $v ) => (int) abs( (int) $v ) );

		$wpdb->shouldReceive( 'get_var' )->andReturn( '42' );

		$captured_sql = null;
		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				function ( $sql, ...$args ) use ( &$captured_sql ) {
					$captured_sql = $sql;
					return 'PREPARED_SQL';
				}
			);
		$wpdb->shouldReceive( 'get_col' )->andReturn( array() );

		$query = new WP_Query();
		$query->set( 'aucteeno_order_type', 'items' );
		$query->set( 'aucteeno_sort', 'status_ending_soon' );
		$query->set( 'aucteeno_include_expired', true );
		$query->set( 'paged', 1 );
		$query->set( 'posts_per_page', 10 );

		$orderer    = new Query_Orderer();
		$reflection = new \ReflectionClass( $orderer );
		$method     = $reflection->getMethod( 'get_ordered_item_ids' );
		$method->setAccessible( true );
		$method->invoke( $orderer, $query );

		$this->assertNotNull( $captured_sql, 'prepare() must have been called' );
		$this->assertStringContainsString(
			'bidding_status = 30',
			$captured_sql,
			'SQL must reference expired status when include_expired is true'
		);
	}

	/**
	 * Test get_ordered_auction_ids SQL excludes bidding_status = 30 when include_expired is false.
	 *
	 * Verifies the auctions 3-group (status_ending_soon) path drops the expired
	 * UNION ALL subquery when aucteeno_include_expired is not set.
	 *
	 * @return void
	 */
	public function test_auction_ids_excludes_expired_sql_when_not_requested(): void {
		$wpdb                     = \Mockery::mock( 'wpdb' );
		$wpdb->prefix             = 'wp_';
		$wpdb->posts              = 'wp_posts';
		$wpdb->terms              = 'wp_terms'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$wpdb->term_taxonomy      = 'wp_term_taxonomy'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$wpdb->term_relationships = 'wp_term_relationships'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'absint' )->alias( fn( $v ) => (int) abs( (int) $v ) );

		$wpdb->shouldReceive( 'get_var' )->andReturn( '42' );

		$captured_sql = null;
		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				function ( $sql, ...$args ) use ( &$captured_sql ) {
					$captured_sql = $sql;
					return 'PREPARED_SQL';
				}
			);
		$wpdb->shouldReceive( 'get_col' )->andReturn( array() );

		$query = new WP_Query();
		$query->set( 'aucteeno_order_type', 'auctions' );
		$query->set( 'aucteeno_sort', 'status_ending_soon' );
		$query->set( 'aucteeno_include_expired', false );
		$query->set( 'paged', 1 );
		$query->set( 'posts_per_page', 10 );

		$orderer    = new Query_Orderer();
		$reflection = new \ReflectionClass( $orderer );
		$method     = $reflection->getMethod( 'get_ordered_auction_ids' );
		$method->setAccessible( true );
		$method->invoke( $orderer, $query );

		$this->assertNotNull( $captured_sql, 'prepare() must have been called' );
		$this->assertStringNotContainsString(
			'bidding_status = 30',
			$captured_sql,
			'SQL must not reference expired status when include_expired is false'
		);
	}

	/**
	 * Test get_ordered_auction_ids SQL includes bidding_status = 30 when include_expired is true.
	 *
	 * Verifies the auctions 3-group (status_ending_soon) path retains the expired
	 * UNION ALL subquery when aucteeno_include_expired is set.
	 *
	 * @return void
	 */
	public function test_auction_ids_includes_expired_sql_when_requested(): void {
		$wpdb                     = \Mockery::mock( 'wpdb' );
		$wpdb->prefix             = 'wp_';
		$wpdb->posts              = 'wp_posts';
		$wpdb->terms              = 'wp_terms'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$wpdb->term_taxonomy      = 'wp_term_taxonomy'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$wpdb->term_relationships = 'wp_term_relationships'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'absint' )->alias( fn( $v ) => (int) abs( (int) $v ) );

		$wpdb->shouldReceive( 'get_var' )->andReturn( '42' );

		$captured_sql = null;
		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				function ( $sql, ...$args ) use ( &$captured_sql ) {
					$captured_sql = $sql;
					return 'PREPARED_SQL';
				}
			);
		$wpdb->shouldReceive( 'get_col' )->andReturn( array() );

		$query = new WP_Query();
		$query->set( 'aucteeno_order_type', 'auctions' );
		$query->set( 'aucteeno_sort', 'status_ending_soon' );
		$query->set( 'aucteeno_include_expired', true );
		$query->set( 'paged', 1 );
		$query->set( 'posts_per_page', 10 );

		$orderer    = new Query_Orderer();
		$reflection = new \ReflectionClass( $orderer );
		$method     = $reflection->getMethod( 'get_ordered_auction_ids' );
		$method->setAccessible( true );
		$method->invoke( $orderer, $query );

		$this->assertNotNull( $captured_sql, 'prepare() must have been called' );
		$this->assertStringContainsString(
			'bidding_status = 30',
			$captured_sql,
			'SQL must reference expired status when include_expired is true'
		);
	}

	/**
	 * Test get_total_count SQL excludes the expired OR-branch when include_expired is false.
	 *
	 * @return void
	 */
	public function test_total_count_excludes_expired_branch_when_not_requested(): void {
		$wpdb                     = \Mockery::mock( 'wpdb' );
		$wpdb->prefix             = 'wp_';
		$wpdb->posts              = 'wp_posts';
		$wpdb->terms              = 'wp_terms'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$wpdb->term_taxonomy      = 'wp_term_taxonomy'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$wpdb->term_relationships = 'wp_term_relationships'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );

		$wpdb->shouldReceive( 'get_var' )
			->andReturnUsing(
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Parameter required by callback signature.
				function ( $prepared ) {
					return '0';
				}
			);

		$captured_sql = null;
		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				function ( $sql, ...$args ) use ( &$captured_sql ) {
					$captured_sql = $sql;
					return 'PREPARED_SQL';
				}
			);

		$query = new WP_Query();
		$query->set( 'aucteeno_order_type', 'items' );
		$query->set( 'aucteeno_include_expired', false );

		$orderer    = new Query_Orderer();
		$reflection = new \ReflectionClass( $orderer );
		$method     = $reflection->getMethod( 'get_total_count' );
		$method->setAccessible( true );
		$method->invoke( $orderer, $query );

		$this->assertNotNull( $captured_sql, 'prepare() must have been called for total count' );
		$this->assertStringNotContainsString(
			'bidding_status = 30',
			$captured_sql,
			'Total count SQL must not reference expired status when include_expired is false'
		);
	}

	/**
	 * Test get_total_count SQL includes the expired OR-branch when include_expired is true.
	 *
	 * @return void
	 */
	public function test_total_count_includes_expired_branch_when_requested(): void {
		$wpdb                     = \Mockery::mock( 'wpdb' );
		$wpdb->prefix             = 'wp_';
		$wpdb->posts              = 'wp_posts';
		$wpdb->terms              = 'wp_terms'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$wpdb->term_taxonomy      = 'wp_term_taxonomy'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$wpdb->term_relationships = 'wp_term_relationships'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );

		$wpdb->shouldReceive( 'get_var' )
			->andReturnUsing(
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Parameter required by callback signature.
				function ( $prepared ) {
					return '0';
				}
			);

		$captured_sql = null;
		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				function ( $sql, ...$args ) use ( &$captured_sql ) {
					$captured_sql = $sql;
					return 'PREPARED_SQL';
				}
			);

		$query = new WP_Query();
		$query->set( 'aucteeno_order_type', 'items' );
		$query->set( 'aucteeno_include_expired', true );

		$orderer    = new Query_Orderer();
		$reflection = new \ReflectionClass( $orderer );
		$method     = $reflection->getMethod( 'get_total_count' );
		$method->setAccessible( true );
		$method->invoke( $orderer, $query );

		$this->assertNotNull( $captured_sql, 'prepare() must have been called for total count' );
		$this->assertStringContainsString(
			'bidding_status = 30',
			$captured_sql,
			'Total count SQL must reference expired status when include_expired is true'
		);
	}
}
