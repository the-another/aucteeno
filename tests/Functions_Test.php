<?php
/**
 * Global Functions Tests
 *
 * Unit tests for aucteeno_format_elapsed() in includes/functions.php - the
 * PHP port of formatElapsed() in blocks/field-countdown/src/countdown-utils.js.
 * Both sides implement the same breakpoints independently (PHP has no way to
 * call into the JS, or vice versa), so each side needs its own direct
 * breakpoint coverage or the two ports can silently drift.
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class Functions_Test extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		require_once dirname( __DIR__ ) . '/includes/functions.php';

		Functions\when( '__' )->returnArg();
		Functions\when( '_n' )->alias(
			static function ( $single, $plural, $number ) {
				return 1 === (int) $number ? $single : $plural;
			}
		);
		Functions\when( 'get_option' )->justReturn( 'F j, Y' );
		Functions\when( 'wp_date' )->alias(
			static function ( $format, $timestamp ) {
				return gmdate( $format, $timestamp );
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_shows_seconds_ago_with_minutes_omitted_when_zero(): void {
		$result = aucteeno_format_elapsed( 45, 0, 'default' );
		$this->assertSame( '45 seconds ago', $result['display_value'] );
		$this->assertFalse( $result['is_showing_date'] );
	}

	public function test_shows_singular_second_ago(): void {
		$result = aucteeno_format_elapsed( 1, 0, 'default' );
		$this->assertSame( '1 second ago', $result['display_value'] );
	}

	public function test_shows_minutes_and_seconds_ago(): void {
		$result = aucteeno_format_elapsed( 125, 0, 'default' );
		$this->assertSame( '2 minutes 5 seconds ago', $result['display_value'] );
		$this->assertFalse( $result['is_showing_date'] );
	}

	public function test_shows_singular_minute_with_plural_seconds_when_zero(): void {
		$result = aucteeno_format_elapsed( 120, 0, 'default' );
		$this->assertSame( '2 minutes 0 seconds ago', $result['display_value'] );
	}

	public function test_boundary_3599_stays_in_the_minutes_seconds_branch(): void {
		$result = aucteeno_format_elapsed( 3599, 0, 'default' );
		$this->assertSame( '59 minutes 59 seconds ago', $result['display_value'] );
		$this->assertFalse( $result['is_showing_date'] );
	}

	public function test_boundary_3600_moves_to_the_hours_branch(): void {
		$result = aucteeno_format_elapsed( 3600, 0, 'default' );
		$this->assertSame( '1 hour ago', $result['display_value'] );
		$this->assertFalse( $result['is_showing_date'] );
	}

	public function test_shows_plural_hours_ago(): void {
		$result = aucteeno_format_elapsed( 7200, 0, 'default' );
		$this->assertSame( '2 hours ago', $result['display_value'] );
	}

	public function test_boundary_86399_stays_in_the_hours_branch(): void {
		$result = aucteeno_format_elapsed( 86399, 0, 'default' );
		$this->assertSame( '23 hours ago', $result['display_value'] );
		$this->assertFalse( $result['is_showing_date'] );
	}

	public function test_boundary_86400_moves_to_the_days_branch(): void {
		$result = aucteeno_format_elapsed( 86400, 0, 'default' );
		$this->assertSame( '1 day ago', $result['display_value'] );
		$this->assertFalse( $result['is_showing_date'] );
	}

	public function test_shows_plural_days_ago(): void {
		$result = aucteeno_format_elapsed( 259200, 0, 'default' );
		$this->assertSame( '3 days ago', $result['display_value'] );
	}

	public function test_boundary_604799_stays_in_the_days_branch(): void {
		$result = aucteeno_format_elapsed( 604799, 0, 'default' );
		$this->assertSame( '6 days ago', $result['display_value'] );
		$this->assertFalse( $result['is_showing_date'] );
	}

	public function test_boundary_604800_moves_to_the_formatted_date_branch(): void {
		$timestamp = 1768996800; // Same fixed timestamp the JS formatElapsed suite uses.
		$result    = aucteeno_format_elapsed( 604800, $timestamp, 'long' );
		$this->assertTrue( $result['is_showing_date'] );
		$this->assertSame( aucteeno_format_date( $timestamp, 'long' ), $result['display_value'] );
	}

	public function test_shows_the_formatted_date_well_past_a_week(): void {
		$timestamp = 1768996800;
		$result    = aucteeno_format_elapsed( 700000, $timestamp, 'long' );
		$this->assertTrue( $result['is_showing_date'] );
		$this->assertSame( aucteeno_format_date( $timestamp, 'long' ), $result['display_value'] );
	}
}
