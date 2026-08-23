<?php
/**
 * Tests for the Aucteeno Field Countdown block server-side render.
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class Field_Countdown_Render_Test extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Re-stub functions the bootstrap stubs globally; Patchwork restores them
		// after each tearDown() and they throw MissingFunctionExpectations otherwise.
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'sanitize_html_class' )->alias(
			static function ( $class ) {
				return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
			}
		);
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

		// Serialize every wrapper attribute so tests can assert on presence/absence.
		Functions\when( 'get_block_wrapper_attributes' )->alias(
			static function ( $a = array() ) {
				$out = array();
				foreach ( $a as $key => $value ) {
					$out[] = $key . '="' . $value . '"';
				}
				return implode( ' ', $out );
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Include the block's render.php with the given attributes and item data.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $item_data  Item context data.
	 * @return string Rendered HTML.
	 */
	private function run_render( array $attributes, array $item_data ): string {
		$content = '';
		$block   = (object) array( 'context' => array( 'aucteeno/item' => $item_data ) );

		ob_start();
		try {
			include dirname( __DIR__, 2 ) . '/blocks/field-countdown/render.php';
		} finally {
			$html = ob_get_clean();
		}
		return $html ?? '';
	}

	/**
	 * A running auction: started an hour ago, ends in two hours and one minute.
	 *
	 * The extra minute matters: render.php calls time() a moment after this
	 * helper does, so a flat +7200 can floor to "1 hour" and flake.
	 *
	 * @return array Item data.
	 */
	private function running_item(): array {
		$now = time();
		return array(
			'id'                => 42,
			'bidding_starts_at' => $now - 3600,
			'bidding_ends_at'   => $now + 7260,
		);
	}

	public function test_emits_no_override_attributes_when_no_consumer_is_registered(): void {
		$html = $this->run_render( array(), $this->running_item() );

		$this->assertStringNotContainsString( 'data-override', $html );
		$this->assertStringContainsString( 'aucteeno-field-countdown--running', $html );
		$this->assertStringContainsString( '2 hours', $html );
	}

	public function test_active_override_replaces_the_value_and_the_modifier_class(): void {
		$now = time();
		Filters\expectApplied( 'aucteeno_field_countdown_override' )->andReturn(
			array(
				'value' => 'Closing',
				'from'  => $now - 60,
				'until' => $now + 7260,
				'state' => 'closing',
			)
		);

		$html = $this->run_render( array(), $this->running_item() );

		$this->assertStringContainsString( '>Closing<', $html );
		$this->assertStringContainsString( 'aucteeno-field-countdown--closing', $html );
		$this->assertStringNotContainsString( 'aucteeno-field-countdown--running', $html );
		$this->assertStringContainsString( 'data-override-value="Closing"', $html );
		$this->assertStringContainsString( 'data-override-state="closing"', $html );
	}

	public function test_override_not_yet_active_still_emits_its_window(): void {
		$now = time();
		Filters\expectApplied( 'aucteeno_field_countdown_override' )->andReturn(
			array(
				'value' => 'Closing',
				'from'  => $now + 3600,
				'until' => $now + 7260,
				'state' => 'closing',
			)
		);

		$html = $this->run_render( array(), $this->running_item() );

		// The client must learn the window now so it can apply it on the right
		// tick without a reload.
		$this->assertStringContainsString( 'data-override-value="Closing"', $html );
		$this->assertStringContainsString( 'aucteeno-field-countdown--running', $html );
		$this->assertStringContainsString( '2 hours', $html );
		$this->assertStringNotContainsString( '>Closing<', $html );
	}

	public function test_label_is_unaffected_by_an_active_override(): void {
		$now = time();
		Filters\expectApplied( 'aucteeno_field_countdown_override' )->andReturn(
			array(
				'value' => 'Closing',
				'from'  => $now - 60,
				'until' => $now + 7260,
				'state' => 'closing',
			)
		);

		$html = $this->run_render( array(), $this->running_item() );

		$this->assertStringContainsString(
			'<span class="aucteeno-field-countdown__label">Bidding ends in</span>',
			$html
		);
	}

	public function test_label_is_the_single_label_when_respect_bidding_status_is_off(): void {
		$now = time();
		Filters\expectApplied( 'aucteeno_field_countdown_override' )->andReturn(
			array(
				'value' => 'Closing',
				'from'  => $now - 60,
				'until' => $now + 7260,
				'state' => 'closing',
			)
		);

		$html = $this->run_render(
			array(
				'respectBiddingStatus' => false,
				'label'                => 'Sale status',
			),
			$this->running_item()
		);

		$this->assertStringContainsString(
			'<span class="aucteeno-field-countdown__label">Sale status</span>',
			$html
		);
		$this->assertStringContainsString( '>Closing<', $html );
	}

	public function test_pinned_target_date_ignores_an_active_override(): void {
		$now = time();
		Filters\expectApplied( 'aucteeno_field_countdown_override' )->andReturn(
			array(
				'value' => 'Closing',
				'from'  => $now - 60,
				'until' => $now + 7260,
				'state' => 'closing',
			)
		);

		$html = $this->run_render(
			array( 'targetDate' => 'ends_at' ),
			$this->running_item()
		);

		$this->assertStringNotContainsString( '>Closing<', $html );
		$this->assertStringContainsString( '2 hours', $html );
	}

	public function test_malformed_override_is_rejected(): void {
		$now = time();
		Filters\expectApplied( 'aucteeno_field_countdown_override' )->andReturn(
			array(
				'value' => 'Closing',
				'from'  => $now + 7200,
				'until' => $now - 60,
			)
		);

		$html = $this->run_render( array(), $this->running_item() );

		$this->assertStringNotContainsString( 'data-override', $html );
		$this->assertStringContainsString( '2 hours', $html );
	}

	public function test_override_state_is_sanitised_before_it_reaches_a_class(): void {
		$now = time();
		Filters\expectApplied( 'aucteeno_field_countdown_override' )->andReturn(
			array(
				'value' => 'Closing',
				'from'  => $now - 60,
				'until' => $now + 7260,
				'state' => '"><script>alert(1)</script>',
			)
		);

		$html = $this->run_render( array(), $this->running_item() );

		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_negative_window_bounds_are_rejected_not_made_positive(): void {
		// absint() would fold -5000 to 5000 and accept this window; the JS half
		// rejects it outright. The two must agree or the block flashes on the
		// first tick.
		Filters\expectApplied( 'aucteeno_field_countdown_override' )->andReturn(
			array(
				'value' => 'Closing',
				'from'  => 100,
				'until' => -5000,
			)
		);

		$html = $this->run_render( array(), $this->running_item() );

		$this->assertStringNotContainsString( 'data-override', $html );
		$this->assertStringNotContainsString( '>Closing<', $html );
	}

	public function test_active_override_uses_the_time_label_even_when_the_date_would_have_shown(): void {
		$now  = time();
		$ends = $now + ( 14 * DAY_IN_SECONDS );

		// More than a week out, so without an override the block would render a
		// formatted date and pick the "on" label variant. An override's value is
		// never a date, so the "in" variant is the correct one to follow it.
		//
		// Assert on the label span, not the whole document: every label variant
		// is also emitted as a data-label-* attribute on the wrapper, so a bare
		// substring assertion would pass no matter what the span says.
		Filters\expectApplied( 'aucteeno_field_countdown_override' )->andReturn(
			array(
				'value' => 'Closing',
				'from'  => $now - 60,
				'until' => $ends,
				'state' => 'closing',
			)
		);

		$html = $this->run_render(
			array(),
			array(
				'id'                => 42,
				'bidding_starts_at' => $now - 3600,
				'bidding_ends_at'   => $ends,
			)
		);

		$this->assertStringContainsString( '>Closing<', $html );
		$this->assertStringContainsString(
			'<span class="aucteeno-field-countdown__label">Bidding ends in</span>',
			$html
		);
		$this->assertStringNotContainsString(
			'<span class="aucteeno-field-countdown__label">Bidding ends on</span>',
			$html
		);
	}
}
