<?php
/**
 * Salebill_Tab_Visibility Tests
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Helpers;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Helpers\Salebill_Tab_Visibility;

class Salebill_Tab_Visibility_Test extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Real-ish wp_strip_all_tags (mirrors wp-includes/formatting.php).
		Functions\when( 'wp_strip_all_tags' )->alias(
			function ( $text, $remove_breaks = false ) {
				$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
				$text = strip_tags( $text );
				if ( $remove_breaks ) {
					$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
				}
				return trim( $text );
			}
		);
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a product mock with the three text fields.
	 */
	private function product( string $directions = '', string $notice = '', string $bidding = '' ) {
		$product = Mockery::mock();
		$product->shouldReceive( 'get_directions' )->andReturn( $directions );
		$product->shouldReceive( 'get_notice' )->andReturn( $notice );
		$product->shouldReceive( 'get_bidding_notice' )->andReturn( $bidding );
		return $product;
	}

	private function post( string $content ) {
		return (object) array( 'post_content' => $content );
	}

	// ---- is_directions_visible ----

	public function test_directions_visible_with_content(): void {
		$this->assertTrue( Salebill_Tab_Visibility::is_directions_visible( $this->product( '19302 Twp. Rd. 631B' ) ) );
	}

	public function test_directions_hidden_when_empty_or_whitespace(): void {
		$this->assertFalse( Salebill_Tab_Visibility::is_directions_visible( $this->product( '' ) ) );
		$this->assertFalse( Salebill_Tab_Visibility::is_directions_visible( $this->product( "  \n " ) ) );
	}

	// ---- is_notes_visible ----

	public function test_notes_visible_with_notice_only(): void {
		$this->assertTrue( Salebill_Tab_Visibility::is_notes_visible( $this->product( '', 'Cash only.' ) ) );
	}

	public function test_notes_visible_with_bidding_notice_only(): void {
		$this->assertTrue( Salebill_Tab_Visibility::is_notes_visible( $this->product( '', '', 'Register early.' ) ) );
	}

	public function test_notes_hidden_when_both_empty(): void {
		$this->assertFalse( Salebill_Tab_Visibility::is_notes_visible( $this->product( '', ' ', '' ) ) );
	}

	// ---- excerpt_covers_content ----

	public function test_covers_when_excerpt_equals_content(): void {
		Functions\when( 'get_the_excerpt' )->justReturn( 'Short auction description.' );
		$this->assertTrue(
			Salebill_Tab_Visibility::excerpt_covers_content( $this->post( '<p>Short auction description.</p>' ) )
		);
	}

	public function test_not_covered_when_excerpt_truncated_with_more_suffix(): void {
		Functions\when( 'get_the_excerpt' )->justReturn( 'First fifty five words of the content [&hellip;]' );
		$this->assertFalse(
			Salebill_Tab_Visibility::excerpt_covers_content(
				$this->post( '<p>First fifty five words of the content and then a lot more text.</p>' )
			)
		);
	}

	public function test_not_covered_when_manual_excerpt_diverges(): void {
		Functions\when( 'get_the_excerpt' )->justReturn( 'A hand-written summary.' );
		$this->assertFalse(
			Salebill_Tab_Visibility::excerpt_covers_content( $this->post( '<p>The real description text.</p>' ) )
		);
	}

	public function test_media_guard_forces_not_covered(): void {
		// Text normalizes equal on both sides, but content embeds an image.
		Functions\when( 'get_the_excerpt' )->justReturn( 'Look at this.' );
		$this->assertFalse(
			Salebill_Tab_Visibility::excerpt_covers_content(
				$this->post( '<p>Look at this.</p><img src="a.jpg" alt="">' )
			)
		);
	}

	public function test_covers_ignores_entity_and_whitespace_differences(): void {
		Functions\when( 'get_the_excerpt' )->justReturn( 'Tools & more tools' );
		$this->assertTrue(
			Salebill_Tab_Visibility::excerpt_covers_content(
				$this->post( "<p>Tools &amp; more\n\ntools</p>" )
			)
		);
	}

	public function test_strips_filtered_custom_more_string(): void {
		Filters\expectApplied( 'excerpt_more' )->andReturn( ' ...read more' );
		Functions\when( 'get_the_excerpt' )->justReturn( 'Start of the text ...read more' );
		$this->assertFalse(
			Salebill_Tab_Visibility::excerpt_covers_content(
				$this->post( '<p>Start of the text that keeps going well past the excerpt.</p>' )
			)
		);
	}

	public function test_not_covered_with_texturized_excerpt_and_longer_content(): void {
		// Texturized excerpt: curly apostrophe, en-dash, ellipsis, plus a
		// "[&hellip;]" more-suffix — the raw content continues much further
		// and uses plain ASCII punctuation ("'", "--", "...").
		Functions\when( 'get_the_excerpt' )->justReturn( "Owner\u{2019}s dispersal \u{2013} tools\u{2026} [&hellip;]" );
		$this->assertFalse(
			Salebill_Tab_Visibility::excerpt_covers_content(
				$this->post( "<p>Owner's dispersal -- tools... and much more that keeps going.</p>" )
			)
		);
	}

	public function test_covers_texturized_excerpt_equal_to_raw_short_content(): void {
		// Curly apostrophe on the excerpt side, straight apostrophe on the
		// raw content side — normalize_text() must fold them equal.
		Functions\when( 'get_the_excerpt' )->justReturn( "Owner\u{2019}s sale" );
		$this->assertTrue(
			Salebill_Tab_Visibility::excerpt_covers_content( $this->post( "<p>Owner's sale</p>" ) )
		);
	}

	public function test_covers_when_content_genuinely_ends_in_ellipsis(): void {
		// Content legitimately ends in "..." and the excerpt matches exactly
		// (no truncation) — must compare equal before any suffix stripping.
		Functions\when( 'get_the_excerpt' )->justReturn( 'The end...' );
		$this->assertTrue(
			Salebill_Tab_Visibility::excerpt_covers_content( $this->post( '<p>The end...</p>' ) )
		);
	}

	// ---- is_description_visible ----

	public function test_description_hidden_when_content_empty(): void {
		$this->assertFalse(
			Salebill_Tab_Visibility::is_description_visible( $this->product( 'Some directions' ), $this->post( '  ' ) )
		);
	}

	public function test_description_visible_when_other_panel_visible_even_if_covered(): void {
		// Excerpt fully covers content, but Directions is visible.
		Functions\when( 'get_the_excerpt' )->justReturn( 'Short one.' );
		$this->assertTrue(
			Salebill_Tab_Visibility::is_description_visible( $this->product( 'Directions here' ), $this->post( '<p>Short one.</p>' ) )
		);
	}

	public function test_description_hidden_when_alone_and_covered(): void {
		Functions\when( 'get_the_excerpt' )->justReturn( 'Short one.' );
		$this->assertFalse(
			Salebill_Tab_Visibility::is_description_visible( $this->product(), $this->post( '<p>Short one.</p>' ) )
		);
	}

	public function test_description_visible_when_alone_and_longer_than_excerpt(): void {
		Functions\when( 'get_the_excerpt' )->justReturn( 'Long content trimmed [&hellip;]' );
		$this->assertTrue(
			Salebill_Tab_Visibility::is_description_visible(
				$this->product(),
				$this->post( '<p>Long content trimmed nowhere near the whole story.</p>' )
			)
		);
	}
}
