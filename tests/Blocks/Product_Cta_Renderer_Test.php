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
}
