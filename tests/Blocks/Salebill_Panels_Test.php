<?php
/**
 * Salebill_Panels Tests
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Blocks\Salebill_Panels;

/**
 * Tests for the Salebill_Panels renderer.
 *
 * @package Aucteeno
 */
class Salebill_Panels_Test extends TestCase {

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Reset static cache between tests to avoid cross-test state contamination.
		$ref  = new \ReflectionClass( Salebill_Panels::class );
		$prop = $ref->getProperty( 'context_cache' );
		$prop->setAccessible( true );
		$prop->setValue( null, array() );

		Functions\when( 'wpautop' )->alias(
			function ( $text ) {
				return '<p>' . trim( (string) $text ) . '</p>';
			}
		);
		Functions\when( 'esc_html' )->alias(
			function ( $text ) {
				return htmlspecialchars( (string) $text, ENT_QUOTES );
			}
		);
		Functions\when( 'esc_html__' )->alias(
			function ( $text ) {
				return htmlspecialchars( (string) $text, ENT_QUOTES );
			}
		);
		Functions\when( 'wp_strip_all_tags' )->alias(
			function ( $text, $remove_breaks = false ) {
				$text = strip_tags( (string) $text );
				if ( $remove_breaks ) {
					$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
				}
				return trim( $text );
			}
		);
	}

	/**
	 * Tear down test fixtures.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Point get_post()/wc_get_product() at an auction with the given fields.
	 *
	 * @param string $content    Post content.
	 * @param string $directions Product directions field.
	 * @param string $notice     Product notice field.
	 * @param string $bidding    Product bidding notice field.
	 * @param string $type       Product type.
	 *
	 * @return void
	 */
	private function mock_auction(
		string $content = '',
		string $directions = '',
		string $notice = '',
		string $bidding = '',
		string $type = 'aucteeno-ext-auction'
	): void {
		$post = (object) array(
			'ID'           => 42,
			'post_content' => $content,
		);
		Functions\when( 'get_post' )->justReturn( $post );

		$product = Mockery::mock();
		$product->shouldReceive( 'get_type' )->andReturn( $type );
		$product->shouldReceive( 'get_directions' )->andReturn( $directions );
		$product->shouldReceive( 'get_notice' )->andReturn( $notice );
		$product->shouldReceive( 'get_bidding_notice' )->andReturn( $bidding );
		Functions\when( 'wc_get_product' )->justReturn( $product );
	}

	// ---- gating ----

	/**
	 * Tests that all panels are empty for non-auction products.
	 *
	 * @return void
	 */
	public function test_all_panels_empty_for_non_auction_product(): void {
		$this->mock_auction( 'Content', 'Dir', 'Note', 'Bid', 'simple' );

		$this->assertSame( '', Salebill_Panels::render_description() );
		$this->assertSame( '', Salebill_Panels::render_directions() );
		$this->assertSame( '', Salebill_Panels::render_notes() );
	}

	/**
	 * Tests that all panels are empty when there's no post.
	 *
	 * @return void
	 */
	public function test_all_panels_empty_without_post(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$this->assertSame( '', Salebill_Panels::render_description() );
		$this->assertSame( '', Salebill_Panels::render_directions() );
		$this->assertSame( '', Salebill_Panels::render_notes() );
	}

	// ---- description ----

	/**
	 * Tests that description renders content through the_content filter.
	 *
	 * @return void
	 */
	public function test_description_renders_content_through_the_content_filter(): void {
		$this->mock_auction( '<p>Full salebill text.</p>', 'Directions present' );

		// Brain Monkey passes the value through apply_filters() by default.
		$this->assertSame( '<p>Full salebill text.</p>', Salebill_Panels::render_description() );
	}

	/**
	 * Tests that description is empty when hidden by visibility rule.
	 *
	 * @return void
	 */
	public function test_description_empty_when_hidden_by_visibility_rule(): void {
		$this->mock_auction( '<p>Short one.</p>' ); // No directions/notes.
		Functions\when( 'get_the_excerpt' )->justReturn( 'Short one.' );

		$this->assertSame( '', Salebill_Panels::render_description() );
	}

	// ---- directions ----

	/**
	 * Tests that directions are escaped and wrapped.
	 *
	 * @return void
	 */
	public function test_directions_escaped_and_wrapped(): void {
		$this->mock_auction( '', 'North on Hwy 63 <watch signs>' );

		$this->assertSame(
			'<p>North on Hwy 63 &lt;watch signs&gt;</p>',
			Salebill_Panels::render_directions()
		);
	}

	/**
	 * Tests that directions are empty when field is blank.
	 *
	 * @return void
	 */
	public function test_directions_empty_when_field_blank(): void {
		$this->mock_auction( 'Content', "  \n" );

		$this->assertSame( '', Salebill_Panels::render_directions() );
	}

	// ---- notes ----

	/**
	 * Tests that notes with both fields get subheadings.
	 *
	 * @return void
	 */
	public function test_notes_with_both_fields_gets_subheadings(): void {
		$this->mock_auction( '', '', 'General note.', 'Bidding note.' );

		$this->assertSame(
			'<h4>Auction Notice</h4><p>General note.</p><h4>Bidding Notice</h4><p>Bidding note.</p>',
			Salebill_Panels::render_notes()
		);
	}

	/**
	 * Tests that notes with a single field has no subheading.
	 *
	 * @return void
	 */
	public function test_notes_with_single_field_has_no_subheading(): void {
		$this->mock_auction( '', '', 'General note only.' );

		$this->assertSame( '<p>General note only.</p>', Salebill_Panels::render_notes() );
	}

	/**
	 * Tests that notes are empty when both fields are blank.
	 *
	 * @return void
	 */
	public function test_notes_empty_when_both_fields_blank(): void {
		$this->mock_auction( 'Content' );

		$this->assertSame( '', Salebill_Panels::render_notes() );
	}
}
