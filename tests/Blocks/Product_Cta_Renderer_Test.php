<?php
namespace The_Another\Plugin\Aucteeno\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/blocks/class-product-cta-renderer.php';

use The_Another\Plugin\Aucteeno\Blocks\Product_Cta_Renderer;

class Product_Cta_Renderer_Test extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( 'esc_html' )->returnArg();
        Functions\when( 'esc_attr' )->returnArg();
        Functions\when( 'esc_url' )->returnArg();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_renders_minimal_button_with_required_fields(): void {
        $html = Product_Cta_Renderer::render_button( array(
            'id'   => 'demo',
            'text' => 'Click me',
        ) );

        $this->assertStringContainsString( '<button', $html );
        $this->assertStringContainsString( 'class="wp-block-aucteeno-product-cta__button"', $html );
        $this->assertStringContainsString( '<span class="button-text">Click me</span>', $html );
        $this->assertStringContainsString( '</button>', $html );
    }

    public function test_appends_icon_class_when_icon_position_set(): void {
        $html = Product_Cta_Renderer::render_button( array(
            'id'   => 'demo',
            'text' => 'Hi',
            'icon' => 'before',
        ) );
        $this->assertStringContainsString( 'has-icon-before', $html );
    }

    public function test_extra_classes_appended_after_base_class(): void {
        $html = Product_Cta_Renderer::render_button( array(
            'id'      => 'demo',
            'text'    => 'Hi',
            'classes' => array( 'is-wishlist', 'is-in-wishlist' ),
        ) );
        $this->assertStringContainsString( 'wp-block-aucteeno-product-cta__button is-wishlist is-in-wishlist', $html );
    }

    public function test_attrs_emitted_and_escaped(): void {
        $html = Product_Cta_Renderer::render_button( array(
            'id'    => 'demo',
            'text'  => 'Hi',
            'attrs' => array(
                'data-product-id' => 99,
                'data-action'     => 'add',
                'type'            => 'button',
            ),
        ) );
        $this->assertStringContainsString( 'data-product-id="99"', $html );
        $this->assertStringContainsString( 'data-action="add"', $html );
        $this->assertStringContainsString( 'type="button"', $html );
    }

    public function test_drops_disallowed_attrs(): void {
        $html = Product_Cta_Renderer::render_button( array(
            'id'    => 'demo',
            'text'  => 'Hi',
            'attrs' => array(
                'style'   => 'color:red',
                'onclick' => 'alert(1)',
                'OnFocus' => 'x',
                ''        => 'empty-key',
                'INVALID' => 'caps',
            ),
        ) );
        $this->assertStringNotContainsString( 'style=', $html );
        $this->assertStringNotContainsString( 'onclick', $html );
        $this->assertStringNotContainsString( 'OnFocus', $html );
    }

    public function test_form_wrapper_emits_form_with_hidden_fields(): void {
        $html = Product_Cta_Renderer::render_button( array(
            'id'      => 'bidding',
            'wrapper' => 'form',
            'text'    => 'Bid',
            'classes' => array( 'is-bidding' ),
            'form'    => array(
                'action'        => 'https://example.com/p/123',
                'method'        => 'get',
                'target'        => '_blank',
                'rel'           => 'noopener noreferrer',
                'classes'       => array( 'is-bidding' ),
                'hidden_fields' => array(
                    'utm_source' => 'aucteeno',
                    'utm_medium' => 'syndication',
                ),
            ),
        ) );

        $this->assertStringContainsString( '<form', $html );
        $this->assertStringContainsString( 'action="https://example.com/p/123"', $html );
        $this->assertStringContainsString( 'method="get"', $html );
        $this->assertStringContainsString( 'target="_blank"', $html );
        $this->assertStringContainsString( 'class="wp-block-aucteeno-product-cta__form is-bidding"', $html );
        $this->assertStringContainsString( '<input type="hidden" name="utm_source" value="aucteeno">', $html );
        $this->assertStringContainsString( '<input type="hidden" name="utm_medium" value="syndication">', $html );
        $this->assertStringContainsString( 'wp-block-aucteeno-product-cta__button is-bidding', $html );
        $this->assertStringContainsString( '</form>', $html );
    }

    public function test_collection_sorts_by_priority_ascending(): void {
        $html = Product_Cta_Renderer::render_collection( array(
            array( 'id' => 'a', 'text' => 'A', 'priority' => 30 ),
            array( 'id' => 'b', 'text' => 'B', 'priority' => 10 ),
            array( 'id' => 'c', 'text' => 'C', 'priority' => 20 ),
        ) );
        $this->assertMatchesRegularExpression( '/B.*C.*A/s', $html );
    }

    public function test_collection_dedups_by_id_last_wins(): void {
        $html = Product_Cta_Renderer::render_collection( array(
            array( 'id' => 'demo', 'text' => 'first' ),
            array( 'id' => 'demo', 'text' => 'second' ),
        ) );
        $this->assertStringContainsString( 'second', $html );
        $this->assertStringNotContainsString( 'first', $html );
    }

    public function test_collection_returns_empty_string_for_empty_input(): void {
        $this->assertSame( '', Product_Cta_Renderer::render_collection( array() ) );
    }

    public function test_collection_skips_entries_without_id(): void {
        $html = Product_Cta_Renderer::render_collection( array(
            array( 'text' => 'no-id' ),
            array( 'id' => 'ok', 'text' => 'ok-button' ),
        ) );
        $this->assertStringContainsString( 'ok-button', $html );
        $this->assertStringNotContainsString( 'no-id', $html );
    }
}
