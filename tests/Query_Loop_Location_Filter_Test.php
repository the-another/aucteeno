<?php
/**
 * Tests for the Query_Loop_Location_Filter helper.
 *
 * @package The_Another\Plugin\Aucteeno\Tests
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Blocks\Query_Loop_Location_Filter;

final class Query_Loop_Location_Filter_Test extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_returns_input_unchanged_when_no_filter_hooked(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn( string $tag, $value ) => $value
		);

		$result = Query_Loop_Location_Filter::apply( array( 'CA', 'CA:ON' ), array( 'queryType' => 'auctions' ), new \stdClass() );

		$this->assertSame( array( 'CA', 'CA:ON' ), $result );
	}

	public function test_passes_country_subdivision_attributes_and_block_to_filter(): void {
		$captured = null;
		Functions\when( 'apply_filters' )->alias(
			static function ( string $tag, $value, ...$args ) use ( &$captured ) {
				if ( 'aucteeno_query_loop_location' === $tag ) {
					$captured = array( 'value' => $value, 'args' => $args );
				}
				return $value;
			}
		);

		$block = new \stdClass();
		Query_Loop_Location_Filter::apply( array( 'US', 'US:KS' ), array( 'queryType' => 'items' ), $block );

		$this->assertNotNull( $captured );
		$this->assertSame( array( 'US', 'US:KS' ), $captured['value'] );
		$this->assertSame( array( 'queryType' => 'items' ), $captured['args'][0] );
		$this->assertSame( $block, $captured['args'][1] );
	}

	public function test_accepts_valid_filter_return(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn( string $tag, $value ) => array( 'US', 'US:KS' )
		);

		$result = Query_Loop_Location_Filter::apply( array( 'CA', 'CA:ON' ), array(), new \stdClass() );

		$this->assertSame( array( 'US', 'US:KS' ), $result );
	}

	public function test_ignores_non_array_filter_return(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn( string $tag, $value ) => 'not an array'
		);

		$result = Query_Loop_Location_Filter::apply( array( 'CA', 'CA:ON' ), array(), new \stdClass() );

		$this->assertSame( array( 'CA', 'CA:ON' ), $result );
	}

	public function test_ignores_wrong_length_filter_return(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn( string $tag, $value ) => array( 'US' )
		);

		$result = Query_Loop_Location_Filter::apply( array( 'CA', 'CA:ON' ), array(), new \stdClass() );

		$this->assertSame( array( 'CA', 'CA:ON' ), $result );
	}

	public function test_accepts_partial_return_valid_country_null_subdivision(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn( string $tag, $value ) => array( 'US', null )
		);

		$result = Query_Loop_Location_Filter::apply( array( 'CA', 'CA:ON' ), array(), new \stdClass() );

		$this->assertSame( array( 'US', 'CA:ON' ), $result, 'Valid country should apply, null subdivision should fall through' );
	}

	public function test_rejects_non_string_element_individually(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn( string $tag, $value ) => array( 123, 'US:KS' )
		);

		$result = Query_Loop_Location_Filter::apply( array( 'CA', 'CA:ON' ), array(), new \stdClass() );

		$this->assertSame( array( 'CA', 'US:KS' ), $result, 'Non-string country rejected; valid subdivision applied' );
	}

	public function test_render_php_wraps_helper_call_in_has_product_ids_guard(): void {
		$render_path = dirname( __DIR__ ) . '/blocks/query-loop/render.php';
		$this->assertFileExists( $render_path );
		$source = file_get_contents( $render_path );
		$this->assertIsString( $source );

		// Find the offset of the helper call.
		$call_offset = strpos( $source, 'Query_Loop_Location_Filter::apply' );
		$this->assertNotFalse( $call_offset, 'render.php must call Query_Loop_Location_Filter::apply' );

		// Find the nearest preceding `if ( ! $has_product_ids )` opening.
		$guard_offset = strrpos( substr( $source, 0, $call_offset ), 'if ( ! $has_product_ids )' );
		$this->assertNotFalse( $guard_offset, 'Helper call must be preceded by if ( ! $has_product_ids )' );

		// Between the guard and the call, there must be no closing `}` at the outer level.
		$between = substr( $source, $guard_offset, $call_offset - $guard_offset );
		$this->assertStringContainsString( '{', $between, 'Guard block must be opened before helper call' );

		// Brace balance between guard and call must be >= 1 (i.e., we're still inside the guard).
		$opens  = substr_count( $between, '{' );
		$closes = substr_count( $between, '}' );
		$this->assertGreaterThan(
			$closes,
			$opens,
			'Helper call must be inside the ! $has_product_ids block (open braces > close braces between guard and call)'
		);
	}
}
