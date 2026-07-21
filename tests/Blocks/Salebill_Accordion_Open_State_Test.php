<?php
/**
 * Salebill_Accordion_Open_State Tests
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Blocks\Salebill_Accordion_Open_State;
use The_Another\Plugin\Aucteeno\Hook_Manager;

// Load the real WP_HTML_Tag_Processor from the vendored WordPress core.
$aucteeno_wp_core = dirname( __DIR__, 2 ) . '/vendor/johnpbloch/wordpress-core/wp-includes/';
require_once $aucteeno_wp_core . 'compat.php';
require_once $aucteeno_wp_core . 'class-wp-token-map.php';
require_once $aucteeno_wp_core . 'utf8.php';
require_once $aucteeno_wp_core . 'kses.php';
require_once $aucteeno_wp_core . 'html-api/html5-named-character-references.php';
require_once $aucteeno_wp_core . 'html-api/class-wp-html-decoder.php';
require_once $aucteeno_wp_core . 'html-api/class-wp-html-attribute-token.php';
require_once $aucteeno_wp_core . 'html-api/class-wp-html-span.php';
require_once $aucteeno_wp_core . 'html-api/class-wp-html-text-replacement.php';
require_once $aucteeno_wp_core . 'html-api/class-wp-html-tag-processor.php';

/**
 * Tests for the Salebill_Accordion_Open_State service.
 *
 * @package Aucteeno
 */
class Salebill_Accordion_Open_State_Test extends TestCase {

	/**
	 * Service under test.
	 *
	 * @var Salebill_Accordion_Open_State
	 */
	private Salebill_Accordion_Open_State $service;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'wp_json_encode' )->alias(
			function ( $data, $flags = 0 ) {
				return json_encode( $data, $flags ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			}
		);

		$hook_manager  = Mockery::mock( Hook_Manager::class );
		$this->service = new Salebill_Accordion_Open_State( $hook_manager );
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
	 * Make is_singular/get_the_ID/wc_get_product resolve to an auction (or not).
	 *
	 * @param bool   $yes  Whether the current request should resolve as a product page.
	 * @param string $type Product type to report from get_type().
	 */
	private function on_auction_page( bool $yes = true, string $type = 'aucteeno-ext-auction' ): void {
		Functions\when( 'is_singular' )->justReturn( $yes );
		Functions\when( 'get_the_ID' )->justReturn( 42 );

		$product = Mockery::mock();
		$product->shouldReceive( 'get_type' )->andReturn( $type );
		Functions\when( 'wc_get_product' )->justReturn( $yes ? $product : false );
	}

	/**
	 * Build a parsed_block fixture matching the Product Details accordion.
	 *
	 * @param bool $is_descendant Whether the block metadata should carry the
	 *                            isDescendantOfProductDetails flag.
	 * @return array
	 */
	private function parsed_block( bool $is_descendant = true ): array {
		if ( ! $is_descendant ) {
			return array();
		}

		return array(
			'attrs' => array(
				'metadata' => array(
					'isDescendantOfProductDetails' => true,
				),
			),
		);
	}

	/**
	 * Build accordion-group HTML with items whose contexts carry openByDefault flags.
	 *
	 * @param bool[] $open_flags One entry per item.
	 */
	private function group_html( array $open_flags ): string {
		$html = '<div class="wp-block-woocommerce-accordion-group">';
		foreach ( $open_flags as $i => $open ) {
			$context = json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
				array(
					'id'            => 'item-' . $i,
					'openByDefault' => $open,
				),
				JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
			);
			$html   .= '<div class="wp-block-woocommerce-accordion-item" data-wp-context=\'' . $context . '\'>'
				. '<h3 class="wp-block-woocommerce-accordion-header accordion-item__heading">Item ' . $i . '</h3>'
				. '</div>';
		}

		return $html . '</div>';
	}

	/**
	 * Opens the first item when no item is already open.
	 *
	 * @return void
	 */
	public function test_opens_first_item_when_none_open(): void {
		$this->on_auction_page();
		$input  = $this->group_html( array( false, false ) );
		$result = $this->service->maybe_open_first_item( $input, $this->parsed_block() );

		$this->assertNotSame( $input, $result );

		// Decode both item contexts from the result (the service re-encodes
		// with JSON_HEX_* flags, so string-matching on quotes would be brittle).
		$processor = new \WP_HTML_Tag_Processor( $result );
		$processor->next_tag( array( 'class_name' => 'wp-block-woocommerce-accordion-item' ) );
		$first_context = json_decode( (string) $processor->get_attribute( 'data-wp-context' ), true );
		$processor->next_tag( array( 'class_name' => 'wp-block-woocommerce-accordion-item' ) );
		$second_context = json_decode( (string) $processor->get_attribute( 'data-wp-context' ), true );

		$this->assertTrue( $first_context['openByDefault'] );
		$this->assertSame( 'item-0', $first_context['id'] );
		$this->assertFalse( $second_context['openByDefault'] );
	}

	/**
	 * Leaves the markup untouched when an item is already open.
	 *
	 * @return void
	 */
	public function test_no_change_when_an_item_is_already_open(): void {
		$this->on_auction_page();
		$input = $this->group_html( array( true, false ) );

		$this->assertSame( $input, $this->service->maybe_open_first_item( $input, $this->parsed_block() ) );
	}

	/**
	 * Leaves the markup untouched when the group has no items.
	 *
	 * @return void
	 */
	public function test_no_change_when_group_has_no_items(): void {
		$this->on_auction_page();
		$input = '<div class="wp-block-woocommerce-accordion-group"></div>';

		$this->assertSame( $input, $this->service->maybe_open_first_item( $input, $this->parsed_block() ) );
	}

	/**
	 * Leaves the markup untouched off product pages.
	 *
	 * @return void
	 */
	public function test_no_change_off_product_pages(): void {
		$this->on_auction_page( false );
		$input = $this->group_html( array( false ) );

		$this->assertSame( $input, $this->service->maybe_open_first_item( $input, $this->parsed_block() ) );
	}

	/**
	 * Leaves the markup untouched for non-auction products.
	 *
	 * @return void
	 */
	public function test_no_change_for_non_auction_products(): void {
		$this->on_auction_page( true, 'simple' );
		$input = $this->group_html( array( false ) );

		$this->assertSame( $input, $this->service->maybe_open_first_item( $input, $this->parsed_block() ) );
	}

	/**
	 * Leaves the markup untouched for an accordion-group that is not a
	 * descendant of the Product Details block (e.g. one a seller embeds
	 * directly in their post_content).
	 *
	 * @return void
	 */
	public function test_no_change_when_not_descendant_of_product_details(): void {
		$this->on_auction_page();
		$input = $this->group_html( array( false, false ) );

		$this->assertSame( $input, $this->service->maybe_open_first_item( $input, $this->parsed_block( false ) ) );
	}

	/**
	 * Forces the compatibility layer off on auction pages.
	 *
	 * @return void
	 */
	public function test_compatibility_layer_disabled_on_auction_pages(): void {
		$this->on_auction_page();

		$this->assertTrue( $this->service->disable_compatibility_layer_for_auctions( false ) );
	}

	/**
	 * Passes the incoming value through elsewhere.
	 *
	 * @return void
	 */
	public function test_compatibility_layer_passthrough_elsewhere(): void {
		$this->on_auction_page( false );

		$this->assertFalse( $this->service->disable_compatibility_layer_for_auctions( false ) );
		$this->assertTrue( $this->service->disable_compatibility_layer_for_auctions( true ) );
	}
}
