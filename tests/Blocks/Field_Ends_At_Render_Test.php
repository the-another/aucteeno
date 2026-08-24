<?php
/**
 * Tests for the Aucteeno Field Ends At block server-side render.
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class Field_Ends_At_Render_Test extends TestCase {

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
			include dirname( __DIR__, 2 ) . '/blocks/field-ends-at/render.php';
		} finally {
			$html = ob_get_clean();
		}
		return $html ?? '';
	}

	/**
	 * An upcoming auction: starts in an hour, ends in two.
	 *
	 * @return array Item data.
	 */
	private function upcoming_item(): array {
		$now = time();
		return array(
			'id'                => 42,
			'bidding_starts_at' => $now + 3600,
			'bidding_ends_at'   => $now + 7200,
		);
	}

	/**
	 * A running auction: started an hour ago, ends in two hours and one minute.
	 *
	 * The extra minute matters: render.php calls time() a moment after this
	 * helper does, so a flat +7200 could in theory land exactly on the
	 * boundary and flake.
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

	/**
	 * An expired auction: started two hours ago, ended a minute ago.
	 *
	 * The minute-old end matters for the same reason as running_item()'s extra
	 * minute: it keeps the state firmly on one side of the boundary.
	 *
	 * @return array Item data.
	 */
	private function expired_item(): array {
		$now = time();
		return array(
			'id'                => 42,
			'bidding_starts_at' => $now - 7200,
			'bidding_ends_at'   => $now - 60,
		);
	}

	// --- Default labels, no filter registered ---

	public function test_default_label_for_upcoming_state_with_no_filter(): void {
		$html = $this->run_render( array(), $this->upcoming_item() );

		$this->assertStringContainsString(
			'<dt class="wp-block-aucteeno-field-ends-at__label">Bidding closes at</dt>',
			$html
		);
	}

	public function test_default_label_for_running_state_with_no_filter(): void {
		$html = $this->run_render( array(), $this->running_item() );

		$this->assertStringContainsString(
			'<dt class="wp-block-aucteeno-field-ends-at__label">Bidding closes at</dt>',
			$html
		);
	}

	public function test_default_label_for_expired_state_with_no_filter(): void {
		$html = $this->run_render( array(), $this->expired_item() );

		$this->assertStringContainsString(
			'<dt class="wp-block-aucteeno-field-ends-at__label">Bidding closed at</dt>',
			$html
		);
	}

	// --- Filter registered ---

	public function test_registered_filter_replaces_the_label(): void {
		Filters\expectApplied( 'aucteeno_field_ends_at_label' )->andReturn( 'Bidding started closing' );

		$html = $this->run_render( array(), $this->running_item() );

		$this->assertStringContainsString(
			'<dt class="wp-block-aucteeno-field-ends-at__label">Bidding started closing</dt>',
			$html
		);
	}

	public function test_filter_receives_the_resolved_label_and_context_for_running_state(): void {
		$item = $this->running_item();

		Filters\expectApplied( 'aucteeno_field_ends_at_label' )
			->once()
			->with( 'Bidding closes at', $item, array(), 'running' )
			->andReturn( 'Bidding started closing' );

		$html = $this->run_render( array(), $item );

		$this->assertStringContainsString(
			'<dt class="wp-block-aucteeno-field-ends-at__label">Bidding started closing</dt>',
			$html
		);
	}

	public function test_filter_still_applies_when_respect_bidding_status_is_false(): void {
		$item       = $this->running_item();
		$attributes = array(
			'respectBiddingStatus' => false,
			'label'                => 'Sale status',
		);

		Filters\expectApplied( 'aucteeno_field_ends_at_label' )
			->once()
			->with( 'Sale status', $item, $attributes, 'running' )
			->andReturn( 'Overridden label' );

		$html = $this->run_render( $attributes, $item );

		$this->assertStringContainsString(
			'<dt class="wp-block-aucteeno-field-ends-at__label">Overridden label</dt>',
			$html
		);
	}

	// --- Non-scalar filter return ---

	public function test_array_filter_return_is_discarded_in_favour_of_the_computed_label(): void {
		Filters\expectApplied( 'aucteeno_field_ends_at_label' )->andReturn( array( 'unexpected' ) );

		$html = $this->run_render( array(), $this->running_item() );

		$this->assertStringContainsString(
			'<dt class="wp-block-aucteeno-field-ends-at__label">Bidding closes at</dt>',
			$html
		);
		$this->assertStringNotContainsString( 'Array', $html );
	}

	public function test_non_stringable_object_filter_return_does_not_fatal(): void {
		// Without the is_scalar() guard, render.php would do (string) $filtered
		// on a plain \stdClass — that is an uncaught Error ("Object of class
		// stdClass could not be converted to string"), not merely a wrong
		// value. This test fatals rather than merely fails if the guard is
		// removed, which is exactly what justifies having it: a filter
		// returning a bad object must not take the page down.
		Filters\expectApplied( 'aucteeno_field_ends_at_label' )->andReturn( new \stdClass() );

		$html = $this->run_render( array(), $this->running_item() );

		$this->assertStringContainsString(
			'<dt class="wp-block-aucteeno-field-ends-at__label">Bidding closes at</dt>',
			$html
		);
	}

	public function test_scalar_filter_return_is_accepted_and_stringified(): void {
		// The guard must not over-reject: scalars other than strings (int,
		// float, bool) are legitimate, safely castable return values.
		Filters\expectApplied( 'aucteeno_field_ends_at_label' )->andReturn( 42 );

		$html = $this->run_render( array(), $this->running_item() );

		$this->assertStringContainsString(
			'<dt class="wp-block-aucteeno-field-ends-at__label">42</dt>',
			$html
		);
	}

	// --- showLabel: false ---

	public function test_show_label_false_renders_no_label_element_without_filter(): void {
		$html = $this->run_render( array( 'showLabel' => false ), $this->running_item() );

		$this->assertStringNotContainsString( 'wp-block-aucteeno-field-ends-at__label', $html );
	}

	public function test_show_label_false_renders_no_label_element_with_filter_registered(): void {
		Filters\expectApplied( 'aucteeno_field_ends_at_label' )->andReturn( 'Should not appear' );

		$html = $this->run_render( array( 'showLabel' => false ), $this->running_item() );

		$this->assertStringNotContainsString( 'wp-block-aucteeno-field-ends-at__label', $html );
		$this->assertStringNotContainsString( 'Should not appear', $html );
	}

	// --- Escaping ---

	public function test_filter_output_is_escaped_before_reaching_the_label(): void {
		// The suite stubs esc_html() as a pass-through, so escaping is invisible
		// to every other test here. Use the real thing for this one: the label
		// is consumer-supplied via the filter and lands inside the <dt> element.
		Functions\when( 'esc_html' )->alias(
			static function ( $text ) {
				return htmlspecialchars( (string) $text, ENT_QUOTES );
			}
		);

		Filters\expectApplied( 'aucteeno_field_ends_at_label' )->andReturn( 'Closing" onmouseover="x' );

		$html = $this->run_render( array(), $this->running_item() );

		$this->assertStringNotContainsString( 'onmouseover="x', $html );
		$this->assertStringContainsString(
			'<dt class="wp-block-aucteeno-field-ends-at__label">Closing&quot; onmouseover=&quot;x</dt>',
			$html
		);
	}

	// --- Value filter ---

	/**
	 * The default-format date string, computed the same way render.php does
	 * from the stubbed get_option() and wp_date() in setUp().
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string Expected formatted date string.
	 */
	private function expected_default_formatted_date( int $timestamp ): string {
		return gmdate( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	public function test_value_filter_replaces_the_rendered_time_text(): void {
		Filters\expectApplied( 'aucteeno_field_ends_at_value' )->andReturn( 'Overridden value' );

		$html = $this->run_render( array(), $this->running_item() );

		$this->assertStringContainsString( '>Overridden value</time>', $html );
	}

	public function test_value_filter_receives_the_formatted_date_and_context(): void {
		$item = $this->running_item();

		Filters\expectApplied( 'aucteeno_field_ends_at_value' )
			->once()
			->with( $this->expected_default_formatted_date( $item['bidding_ends_at'] ), $item, array(), 'running' )
			->andReturn( 'Overridden value' );

		$html = $this->run_render( array(), $item );

		$this->assertStringContainsString( '>Overridden value</time>', $html );
	}

	public function test_non_scalar_value_filter_return_does_not_fatal(): void {
		// Without the is_scalar() guard, render.php would do (string) $filtered
		// on a plain \stdClass — an uncaught Error, not merely a wrong value.
		Filters\expectApplied( 'aucteeno_field_ends_at_value' )->andReturn( new \stdClass() );

		$item = $this->running_item();
		$html = $this->run_render( array(), $item );

		$expected = $this->expected_default_formatted_date( $item['bidding_ends_at'] );
		$this->assertStringContainsString( '>' . $expected . '</time>', $html );
	}

	public function test_with_no_value_filter_output_is_unchanged(): void {
		$item = $this->running_item();
		$html = $this->run_render( array(), $item );

		$expected = $this->expected_default_formatted_date( $item['bidding_ends_at'] );
		$this->assertStringContainsString( '>' . $expected . '</time>', $html );
	}

	public function test_array_value_filter_return_is_discarded_in_favour_of_the_formatted_date(): void {
		Filters\expectApplied( 'aucteeno_field_ends_at_value' )->andReturn( array( 'unexpected' ) );

		$item     = $this->running_item();
		$html     = $this->run_render( array(), $item );
		$expected = $this->expected_default_formatted_date( $item['bidding_ends_at'] );

		$this->assertStringContainsString( '>' . $expected . '</time>', $html );
		$this->assertStringNotContainsString( 'Array', $html );
	}

	public function test_scalar_value_filter_return_is_accepted_and_stringified(): void {
		Filters\expectApplied( 'aucteeno_field_ends_at_value' )->andReturn( 42 );

		$html = $this->run_render( array(), $this->running_item() );

		$this->assertStringContainsString( '>42</time>', $html );
	}

	// --- Hydration opt-out (the value filter must survive page load) ---

	public function test_a_value_that_actually_changes_opts_the_time_element_out_of_hydration(): void {
		// Without data-aucteeno-datetime-hydrated="true", view.js's hydrate()
		// would overwrite this filtered value with the plain local-time date
		// a few hundred milliseconds after paint - the exact load-flash this
		// filter exists to let a consumer avoid.
		Filters\expectApplied( 'aucteeno_field_ends_at_value' )->andReturn( 'Overridden value' );

		$html = $this->run_render( array(), $this->running_item() );

		$this->assertStringContainsString( 'data-aucteeno-datetime-hydrated="true"', $html );
	}

	public function test_no_value_filter_does_not_opt_out_of_hydration(): void {
		$html = $this->run_render( array(), $this->running_item() );

		$this->assertStringNotContainsString( 'data-aucteeno-datetime-hydrated', $html );
	}

	public function test_a_filter_returning_the_same_string_does_not_opt_out_of_hydration(): void {
		// A filter that runs but leaves the value unchanged must not pay the
		// "never hydrates" cost - only an actual change opts an element out.
		$item     = $this->running_item();
		$expected = $this->expected_default_formatted_date( $item['bidding_ends_at'] );

		Filters\expectApplied( 'aucteeno_field_ends_at_value' )->andReturn( $expected );

		$html = $this->run_render( array(), $item );

		$this->assertStringNotContainsString( 'data-aucteeno-datetime-hydrated', $html );
	}

	public function test_a_non_scalar_filter_return_does_not_opt_out_of_hydration(): void {
		// The value falls back to the computed date unchanged, so hydration
		// must proceed exactly as if no filter had run at all.
		Filters\expectApplied( 'aucteeno_field_ends_at_value' )->andReturn( array( 'unexpected' ) );

		$html = $this->run_render( array(), $this->running_item() );

		$this->assertStringNotContainsString( 'data-aucteeno-datetime-hydrated', $html );
	}
}
