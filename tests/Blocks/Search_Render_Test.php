<?php
/**
 * Tests for the Aucteeno Search block server-side render.
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Container;

class Search_Render_Test extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'AUCTEENO_PLUGIN_DIR' ) ) {
			define( 'AUCTEENO_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
		}

		// Re-stub functions that bootstrap stubs globally; Patchwork restores them
		// after each tearDown() and they throw MissingFunctionExpectations otherwise.
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'esc_attr_e' )->echoArg();
		Functions\when( 'number_format_i18n' )->alias(
			static function ( $n ) {
				return number_format( (float) $n );
			}
		);
		Functions\when( 'rest_url' )->alias(
			static function ( $path = '' ) {
				return 'https://example.com/wp-json/' . $path;
			}
		);
		Functions\when( 'wp_create_nonce' )->justReturn( 'test-nonce' );

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

		$count_provider = Mockery::mock( 'Search_Count_Provider' );
		$count_provider->shouldReceive( 'get_running_upcoming_items_count' )->andReturn( 5289 );

		$block_service = Mockery::mock( 'Search_Block_Service' );
		$block_service->shouldReceive( 'get_page_options' )->andReturn(
			array(
				'perPage' => 25,
				'orderBy' => 'ending_soon',
				'pageUrl' => 'https://example.com/item-search/',
			)
		);

		$container = Container::get_instance();
		$container->set( 'search_count_provider', $count_provider );
		$container->set( 'search_block_service', $block_service );
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Include the block's render.php with the given attributes and capture its output.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered HTML.
	 */
	private function run_render( array $attributes ): string {
		$content = '';
		$block   = (object) array( 'context' => array() );

		ob_start();
		try {
			include dirname( __DIR__, 2 ) . '/blocks/search/render.php';
		} finally {
			$html = ob_get_clean();
		}
		return $html ?? '';
	}

	public function test_emits_rest_root_and_nonce_when_live_results_enabled(): void {
		$html = $this->run_render( array( 'defaultType' => 'items' ) );

		$this->assertStringContainsString( 'data-rest-root=', $html );
		$this->assertStringContainsString( 'data-rest-nonce="test-nonce"', $html );
		$this->assertStringNotContainsString( 'data-disable-live-results', $html );
	}

	public function test_omits_rest_root_and_nonce_when_live_results_disabled(): void {
		$html = $this->run_render(
			array(
				'defaultType'        => 'items',
				'disableLiveResults' => true,
			)
		);

		$this->assertStringContainsString( 'data-disable-live-results="1"', $html );
		$this->assertStringNotContainsString( 'data-rest-nonce', $html );
		$this->assertStringNotContainsString( 'data-rest-root', $html );
	}

	public function test_still_emits_results_page_urls_when_live_results_disabled(): void {
		$html = $this->run_render(
			array(
				'defaultType'        => 'items',
				'disableLiveResults' => true,
			)
		);

		// The redirect target depends on these; they must survive the toggle.
		$this->assertStringContainsString( 'data-items-page-url="https://example.com/item-search/"', $html );
		$this->assertStringContainsString( 'data-auctions-page-url="https://example.com/item-search/"', $html );
	}
}
