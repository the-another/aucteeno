<?php
namespace The_Another\Plugin\Aucteeno\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

class Product_Cta_Render_Test extends TestCase {

	/**
	 * The WC_Product mock to be returned by wc_get_product.
	 *
	 * @var \WC_Product|false
	 */
	private $current_product = false;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'AUCTEENO_PLUGIN_DIR' ) ) {
			define( 'AUCTEENO_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
		}

		// Re-stub functions that bootstrap stubbed globally; after each tearDown()
		// Patchwork restores them and they throw MissingFunctionExpectations without re-stub.
		Functions\when( '__' )->returnArg();

		// WP escaping / URL helpers — not defined by bootstrap or Brain\Monkey helpers.
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'add_query_arg' )->alias(
			static function ( $args, $url ) {
				return $url . '?' . http_build_query( $args );
			}
		);
		Functions\when( 'get_block_wrapper_attributes' )->alias(
			static function ( $a = array() ) {
				return 'class="' . ( $a['class'] ?? '' ) . '"';
			}
		);

		// wc_get_product: re-stub per test via $this->current_product.
		$self = $this;
		Functions\when( 'wc_get_product' )->alias(
			static function ( $id ) use ( $self ) {
				return $self->current_product;
			}
		);
	}

	protected function tearDown(): void {
		$this->current_product = false;
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	private function run_render( array $attributes, $product, array $block_context = array() ): string {
		$this->current_product = $product;
		$block                 = (object) array( 'context' => $block_context );

		ob_start();
		try {
			include dirname( __DIR__, 2 ) . '/blocks/product-cta/render.php';
		} finally {
			$html = ob_get_clean();
		}
		return $html ?? '';
	}

	public function test_renders_bidding_button_through_renderer(): void {
		$product = Mockery::mock( \WC_Product::class );
		$product->shouldReceive( 'get_type' )->andReturn( 'aucteeno-ext-item' );
		$product->shouldReceive( 'get_product_url' )->andReturn( 'https://example.com/p/1' );
		$product->shouldReceive( 'get_id' )->andReturn( 1 );

		$html = $this->run_render(
			array(
				'showBiddingButton' => true,
				'biddingButtonText' => 'Bid Now',
				'biddingButtonIcon' => 'none',
				'layout'            => 'horizontal',
				'buttonAlignment'   => 'center',
			),
			$product,
			array( 'aucteeno/item' => array( 'id' => 1 ) )
		);

		$this->assertStringContainsString( 'is-layout-horizontal', $html );
		$this->assertStringContainsString( 'wp-block-aucteeno-product-cta__buttons', $html );
		$this->assertStringContainsString( 'wp-block-aucteeno-product-cta__button is-bidding', $html );
		$this->assertStringContainsString( 'Bid Now', $html );
	}

	public function test_returns_empty_when_bidding_hidden_and_filter_empty(): void {
		$product = Mockery::mock( \WC_Product::class );
		$product->shouldReceive( 'get_type' )->andReturn( 'aucteeno-ext-item' );
		$product->shouldReceive( 'get_product_url' )->andReturn( 'https://example.com/p/1' );
		$product->shouldReceive( 'get_id' )->andReturn( 1 );

		$html = $this->run_render(
			array(
				'showBiddingButton'  => false,
				'showWishlistButton' => false,
				'biddingButtonText'  => 'Bid Now',
				'biddingButtonIcon'  => 'none',
				'layout'             => 'horizontal',
				'buttonAlignment'    => 'center',
			),
			$product,
			array( 'aucteeno/item' => array( 'id' => 1 ) )
		);

		$this->assertSame( '', $html );
	}
}
