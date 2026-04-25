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
        $this->assertStringContainsString( 'class="globalag-cta-button"', $html );
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
            'classes' => array( 'wishlist-button', 'in-wishlist' ),
        ) );
        $this->assertStringContainsString( 'globalag-cta-button wishlist-button in-wishlist', $html );
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
            'classes' => array( 'bidding-button' ),
            'form'    => array(
                'action'        => 'https://example.com/p/123',
                'method'        => 'get',
                'target'        => '_blank',
                'rel'           => 'noopener noreferrer',
                'classes'       => array( 'bidding-form' ),
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
        $this->assertStringContainsString( 'class="globalag-cta-form bidding-form"', $html );
        $this->assertStringContainsString( '<input type="hidden" name="utm_source" value="aucteeno">', $html );
        $this->assertStringContainsString( '<input type="hidden" name="utm_medium" value="syndication">', $html );
        $this->assertStringContainsString( 'globalag-cta-button bidding-button', $html );
        $this->assertStringContainsString( '</form>', $html );
    }
}
