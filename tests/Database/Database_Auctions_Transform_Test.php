<?php
/**
 * Database_Auctions transform tests
 *
 * Verifies that query_for_listing() does not call wc_get_product()
 * and produces the expected new item data fields.
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Database;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Database\Database_Auctions;

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

/**
 * Class Database_Auctions_Transform_Test
 */
class Database_Auctions_Transform_Test extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->posts  = 'wp_posts';
		$GLOBALS['wpdb'] = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// Stub SQL methods generically.
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
		$wpdb->shouldReceive( 'get_var' )->andReturn( '1' );
		$wpdb->shouldReceive( 'get_results' )->andReturn( array(
			array(
				'auction_id'           => 10,
				'user_id'              => 1,
				'bidding_status'       => 10,
				'bidding_starts_at'    => 1000,
				'bidding_ends_at'      => 9999,
				'location_country'     => 'US',
				'location_subdivision' => 'US:KS',
				'location_city'        => 'Wichita',
				'post_title'           => 'Test Auction',
				'post_name'            => 'test-auction',
			),
		) );

		// wp_parse_args is not defined in bootstrap — provide it here.
		Functions\when( 'wp_parse_args' )->alias( function ( $args, $defaults ) {
			return array_merge( (array) $defaults, (array) $args );
		} );

		// Re-stub Brain\Monkey-managed functions reset by Monkey\setUp().
		Functions\when( 'wc_get_product' )->justReturn( false );
		Functions\when( '_prime_post_caches' )->justReturn( null );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'wp_get_attachment_image_src' )->justReturn( false );
		Functions\when( 'apply_filters' )->alias( function ( $tag, $value ) {
			return $value;
		} );
		Functions\when( 'get_terms' )->justReturn( array() );
		Functions\when( 'get_term_meta' )->justReturn( '' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_list_pluck' )->justReturn( array() );
		Functions\when( 'update_termmeta_cache' )->justReturn( null );

		// Permalink helpers.
		Functions\when( 'get_option' )->justReturn( 'auction' );
		Functions\when( 'sanitize_title' )->returnArg();
		Functions\when( 'home_url' )->returnArg();
		Functions\when( 'user_trailingslashit' )->returnArg();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_query_for_listing_does_not_call_wc_get_product(): void {
		Functions\expect( 'wc_get_product' )->never();

		$result = Database_Auctions::query_for_listing();

		$this->assertArrayHasKey( 'items', $result );
	}

	public function test_query_for_listing_item_has_image_id_field(): void {
		Functions\when( 'get_post_meta' )
			->alias( function ( $id, $key = null, $single = false ) {
				if ( '_thumbnail_id' === $key ) {
					return '77';
				}
				return '';
			} );

		$result = Database_Auctions::query_for_listing();

		$this->assertArrayHasKey( 'image_id', $result['items'][0] );
		$this->assertSame( 77, $result['items'][0]['image_id'] );
	}

	public function test_query_for_listing_item_has_location_term_id_fields(): void {
		$result = Database_Auctions::query_for_listing();

		$this->assertArrayHasKey( 'location_country_term_id', $result['items'][0] );
		$this->assertArrayHasKey( 'location_subdivision_term_id', $result['items'][0] );
	}

	public function test_query_for_listing_location_term_ids_resolved_from_term_map(): void {
		$term = (object) array( 'term_id' => 42 );

		Functions\when( 'get_terms' )->justReturn( array( $term ) );
		Functions\when( 'wp_list_pluck' )->alias( function ( $list, $field ) {
			return array_column( array_map( 'get_object_vars', $list ), $field );
		} );
		Functions\when( 'update_termmeta_cache' )->justReturn( null );
		Functions\when( 'get_term_meta' )->justReturn( 'US' );

		$result = Database_Auctions::query_for_listing();

		$this->assertSame( 42, $result['items'][0]['location_country_term_id'] );
	}

	public function test_query_for_listing_current_bid_reads_from_price_meta(): void {
		Functions\when( 'get_post_meta' )
			->alias( function ( $id, $key = null, $single = false ) {
				if ( '_price' === $key ) {
					return '499.00';
				}
				return '';
			} );

		$result = Database_Auctions::query_for_listing();

		$this->assertSame( 499.0, $result['items'][0]['current_bid'] );
	}

	public function test_query_for_listing_reserve_price_is_always_zero(): void {
		$result = Database_Auctions::query_for_listing();

		$this->assertSame( 0.0, $result['items'][0]['reserve_price'] );
	}

	public function test_query_for_listing_permalink_built_from_post_name(): void {
		Functions\when( 'home_url' )->alias( function ( $path ) {
			return 'https://example.com' . $path;
		} );
		Functions\when( 'user_trailingslashit' )->alias( function ( $s ) {
			return rtrim( $s, '/' ) . '/';
		} );

		$result = Database_Auctions::query_for_listing();

		$this->assertStringContainsString( 'test-auction', $result['items'][0]['permalink'] );
	}

	public function test_query_for_listing_batch_filter_receives_all_items(): void {
		$captured_ids = null;

		Functions\when( 'apply_filters' )->alias( function ( $tag, $value, ...$extra ) use ( &$captured_ids ) {
			if ( 'aucteeno_products_context_data' === $tag ) {
				$captured_ids = $extra[0] ?? null;
			}
			return $value;
		} );

		Database_Auctions::query_for_listing();

		$this->assertIsArray( $captured_ids );
		$this->assertContains( 10, $captured_ids );
	}

	public function test_query_for_listing_image_url_populated_from_attachment(): void {
		Functions\when( 'get_post_meta' )
			->alias( function ( $id, $key = null, $single = false ) {
				return '_thumbnail_id' === $key ? '77' : '';
			} );
		Functions\when( 'wp_get_attachment_image_src' )->alias( function ( $id, $size ) {
			return array( 'https://example.com/img.jpg', 100, 100, false );
		} );

		$result = Database_Auctions::query_for_listing();

		$this->assertSame( 'https://example.com/img.jpg', $result['items'][0]['image_url'] );
	}
}
