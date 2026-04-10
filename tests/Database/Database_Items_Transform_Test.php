<?php
/**
 * Database_Items transform tests
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Database;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Database\Database_Items;

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

/**
 * Class Database_Items_Transform_Test
 */
class Database_Items_Transform_Test extends TestCase {

    private \Mockery\MockInterface $wpdb;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        $this->wpdb         = Mockery::mock( 'wpdb' );
        $this->wpdb->prefix = 'wp_';
        $this->wpdb->posts  = 'wp_posts';
        $GLOBALS['wpdb'] = $this->wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

        Functions\when( 'wp_parse_args' )->alias( function ( $args, $defaults ) {
            return array_merge( (array) $defaults, (array) $args );
        } );

        Functions\when( '_prime_post_caches' )->justReturn( null );
        Functions\when( 'get_post_meta' )->justReturn( '' );
        Functions\when( 'wp_get_attachment_image_src' )->justReturn( false );
        Functions\when( 'get_terms' )->justReturn( array() );
        Functions\when( 'get_term_meta' )->justReturn( '' );
        Functions\when( 'is_wp_error' )->justReturn( false );
        Functions\when( 'wp_list_pluck' )->justReturn( array() );
        Functions\when( 'update_termmeta_cache' )->justReturn( null );
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

    private function stub_wpdb_for_single_item( array $row ): void {
        $this->wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
        $this->wpdb->shouldReceive( 'get_var' )->andReturn( '1' );
        $this->wpdb->shouldReceive( 'get_results' )->andReturn( array( $row ) );
    }

    private function base_item_row( array $overrides = array() ): array {
        return array_merge(
            array(
                'item_id'              => 20,
                'auction_id'           => 10,
                'user_id'              => 1,
                'bidding_status'       => 10,
                'bidding_starts_at'    => 1000,
                'bidding_ends_at'      => 9999,
                'lot_no'               => 'LOT-001',
                'lot_sort_key'         => 1,
                'location_country'     => 'US',
                'location_subdivision' => 'US:KS',
                'location_city'        => 'Wichita',
                'post_title'           => 'Test Item',
                'post_name'            => 'test-item',
                'auction_post_name'    => 'test-auction',
            ),
            $overrides
        );
    }

    public function test_query_for_listing_does_not_call_wc_get_product(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row() );
        Functions\expect( 'wc_get_product' )->never();

        Database_Items::query_for_listing();
        $this->addToAssertionCount( 1 );
    }

    public function test_query_for_listing_item_has_image_id_field(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row() );
        Functions\when( 'get_post_meta' )
            ->alias( function ( $id, $key = null, $single = false ) {
                return '_thumbnail_id' === $key ? '99' : '';
            } );

        $result = Database_Items::query_for_listing();

        $this->assertArrayHasKey( 'image_id', $result['items'][0] );
        $this->assertSame( 99, $result['items'][0]['image_id'] );
    }

    public function test_current_bid_uses_current_bid_meta_when_running(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row( array( 'bidding_status' => 10 ) ) );
        Functions\when( 'get_post_meta' )
            ->alias( function ( $id, $key = null, $single = false ) {
                return '_aucteeno_current_bid' === $key ? '250.00' : '';
            } );

        $result = Database_Items::query_for_listing();

        $this->assertSame( 250.0, $result['items'][0]['current_bid'] );
    }

    public function test_current_bid_uses_asking_bid_meta_when_upcoming(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row( array( 'bidding_status' => 20 ) ) );
        Functions\when( 'get_post_meta' )
            ->alias( function ( $id, $key = null, $single = false ) {
                return '_aucteeno_asking_bid' === $key ? '100.00' : '';
            } );

        $result = Database_Items::query_for_listing();

        $this->assertSame( 100.0, $result['items'][0]['current_bid'] );
    }

    public function test_current_bid_uses_sold_price_meta_when_expired(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row( array( 'bidding_status' => 30 ) ) );
        Functions\when( 'get_post_meta' )
            ->alias( function ( $id, $key = null, $single = false ) {
                return '_aucteeno_sold_price' === $key ? '350.00' : '';
            } );

        $result = Database_Items::query_for_listing();

        $this->assertSame( 350.0, $result['items'][0]['current_bid'] );
    }

    public function test_reserve_price_is_always_zero(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row() );

        $result = Database_Items::query_for_listing();

        $this->assertSame( 0.0, $result['items'][0]['reserve_price'] );
    }

    public function test_permalink_built_from_auction_and_item_slugs(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row() );
        Functions\when( 'home_url' )->alias( function ( $path ) {
            return 'https://example.com' . $path;
        } );
        Functions\when( 'user_trailingslashit' )->alias( function ( $s ) {
            return rtrim( $s, '/' ) . '/';
        } );
        Functions\when( 'get_option' )
            ->alias( function ( $key, $default = '' ) {
                if ( 'aucteeno_auction_base' === $key ) return 'auction';
                if ( 'aucteeno_item_base' === $key ) return 'item';
                return $default;
            } );

        $result = Database_Items::query_for_listing();

        $this->assertStringContainsString( 'test-auction', $result['items'][0]['permalink'] );
        $this->assertStringContainsString( 'test-item', $result['items'][0]['permalink'] );
    }

    public function test_permalink_falls_back_for_orphaned_items(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row( array( 'auction_post_name' => null ) ) );
        Functions\expect( 'get_permalink' )
            ->once()
            ->with( 20 )
            ->andReturn( 'https://example.com/?p=20' );

        $result = Database_Items::query_for_listing();

        $this->assertSame( 'https://example.com/?p=20', $result['items'][0]['permalink'] );
    }

    public function test_query_for_listing_item_has_location_term_id_fields(): void {
        $this->stub_wpdb_for_single_item( $this->base_item_row() );

        $result = Database_Items::query_for_listing();

        $this->assertArrayHasKey( 'location_country_term_id', $result['items'][0] );
        $this->assertArrayHasKey( 'location_subdivision_term_id', $result['items'][0] );
    }

    public function test_query_for_listing_by_status_does_not_call_wc_get_product(): void {
        $this->wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
        $this->wpdb->shouldReceive( 'get_var' )->andReturn( '1' );
        $this->wpdb->shouldReceive( 'get_results' )->andReturn( array( $this->base_item_row() ) );

        Functions\expect( 'wc_get_product' )->never();

        Database_Items::query_for_listing( array( 'sort' => 'status' ) );
        $this->addToAssertionCount( 1 );
    }

    public function test_query_for_listing_by_status_item_has_image_id_field(): void {
        $this->wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
        $this->wpdb->shouldReceive( 'get_var' )->andReturn( '1' );
        $this->wpdb->shouldReceive( 'get_results' )->andReturn( array(
            $this->base_item_row( array( 'bidding_status' => 10 ) ),
        ) );

        Functions\when( 'get_post_meta' )
            ->alias( function ( $id, $key = null, $single = false ) {
                return '_thumbnail_id' === $key ? '99' : '';
            } );

        $result = Database_Items::query_for_listing( array( 'sort' => 'status' ) );

        $this->assertArrayHasKey( 'image_id', $result['items'][0] );
        $this->assertSame( 99, $result['items'][0]['image_id'] );
    }
}
