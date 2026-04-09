<?php
/**
 * Database_Auctions Tests
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Database;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Database\Database_Auctions;

// Define WordPress constants if not already defined.
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

class Database_Auctions_Test extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_get_stale_returns_stale_auction_rows(): void {
        $wpdb             = Mockery::mock( 'wpdb' );
        $wpdb->prefix     = 'wp_';
        $GLOBALS['wpdb']  = $wpdb;

        $expected = [
            [ 'auction_id' => 1, 'bidding_starts_at' => 1000, 'bidding_ends_at' => 9999, 'bidding_status' => 20 ],
            [ 'auction_id' => 2, 'bidding_starts_at' => 500,  'bidding_ends_at' => 600,  'bidding_status' => 10 ],
        ];

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

    public function test_get_stale_returns_empty_array_when_no_stale_rows(): void {
        $wpdb            = Mockery::mock( 'wpdb' );
        $wpdb->prefix    = 'wp_';
        $GLOBALS['wpdb'] = $wpdb;

        $wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'PREPARED_SQL' );
        $wpdb->shouldReceive( 'get_results' )->once()->andReturn( [] );

        $result = Database_Auctions::get_stale( 500 );

        $this->assertSame( [], $result );
    }
}
