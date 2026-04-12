<?php
/**
 * Tests for the Query_Loop_Empty_Message service.
 *
 * @package The_Another\Plugin\Aucteeno\Tests\Blocks
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Blocks\Query_Loop_Empty_Message;
use The_Another\Plugin\Aucteeno\Hook_Manager;

final class Query_Loop_Empty_Message_Test extends TestCase {

	private Query_Loop_Empty_Message $service;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$hook_manager  = $this->createMock( Hook_Manager::class );
		$this->service = new Query_Loop_Empty_Message( $hook_manager );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Helper: stub apply_filters to pass through unchanged.
	 */
	private function stub_apply_filters_passthrough(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn( string $tag, $value ) => $value
		);
	}

	/**
	 * Helper: build a filters array with defaults.
	 *
	 * @param array $overrides Key-value pairs to override defaults.
	 * @return array Complete filters array.
	 */
	private function make_filters( array $overrides = array() ): array {
		return array_merge(
			array(
				'country'        => '',
				'subdivision'    => '',
				'search'         => '',
				'user_id'        => 0,
				'auction_id'     => 0,
				'location_label' => '',
				'seller_name'    => '',
				'auction_title'  => '',
			),
			$overrides
		);
	}

	// --- Base messages (no filters active) ---

	public function test_returns_base_message_for_auctions_with_no_filters(): void {
		$this->stub_apply_filters_passthrough();

		$result = $this->service->get_message( 'auctions', $this->make_filters() );

		$this->assertSame( 'No auctions found.', $result );
	}

	public function test_returns_base_message_for_items_with_no_filters(): void {
		$this->stub_apply_filters_passthrough();

		$result = $this->service->get_message( 'items', $this->make_filters() );

		$this->assertSame( 'No items found.', $result );
	}

	// --- Search filter ---

	public function test_includes_search_term_in_message(): void {
		$this->stub_apply_filters_passthrough();

		$result = $this->service->get_message(
			'auctions',
			$this->make_filters( array( 'search' => 'tractor' ) )
		);

		$this->assertSame( 'No auctions found for term "tractor".', $result );
	}

	// --- Location filter (country only) ---

	public function test_includes_location_label_country_only(): void {
		$this->stub_apply_filters_passthrough();

		$result = $this->service->get_message(
			'auctions',
			$this->make_filters( array(
				'country'        => 'CA',
				'location_label' => 'Canada',
			) )
		);

		$this->assertSame( 'No auctions found in Canada.', $result );
	}

	// --- Location filter (subdivision) ---

	public function test_includes_location_label_with_subdivision(): void {
		$this->stub_apply_filters_passthrough();

		$result = $this->service->get_message(
			'items',
			$this->make_filters( array(
				'country'        => 'US',
				'subdivision'    => 'US:KS',
				'location_label' => 'Kansas, US',
			) )
		);

		$this->assertSame( 'No items found in Kansas, US.', $result );
	}

	public function test_omits_location_when_label_empty(): void {
		$this->stub_apply_filters_passthrough();

		$result = $this->service->get_message(
			'auctions',
			$this->make_filters( array(
				'country'        => 'XX',
				'location_label' => '',
			) )
		);

		$this->assertSame( 'No auctions found.', $result );
	}

	// --- Seller filter ---

	public function test_includes_seller_name_in_message(): void {
		$this->stub_apply_filters_passthrough();

		$result = $this->service->get_message(
			'auctions',
			$this->make_filters( array(
				'user_id'     => 42,
				'seller_name' => "John's Farm Equipment",
			) )
		);

		$this->assertSame( "No auctions found by John's Farm Equipment.", $result );
	}

	public function test_omits_seller_when_name_empty(): void {
		$this->stub_apply_filters_passthrough();

		$result = $this->service->get_message(
			'auctions',
			$this->make_filters( array(
				'user_id'     => 999,
				'seller_name' => '',
			) )
		);

		$this->assertSame( 'No auctions found.', $result );
	}

	// --- Parent auction filter ---

	public function test_includes_auction_title_in_message(): void {
		$this->stub_apply_filters_passthrough();

		$result = $this->service->get_message(
			'items',
			$this->make_filters( array(
				'auction_id'    => 100,
				'auction_title' => 'Spring 2026 Farm Auction',
			) )
		);

		$this->assertSame( 'No items found within Spring 2026 Farm Auction.', $result );
	}

	public function test_omits_auction_when_title_empty(): void {
		$this->stub_apply_filters_passthrough();

		$result = $this->service->get_message(
			'items',
			$this->make_filters( array(
				'auction_id'    => 100,
				'auction_title' => '',
			) )
		);

		$this->assertSame( 'No items found.', $result );
	}

	// --- Multiple filters combined ---

	public function test_combines_all_filters_in_correct_order(): void {
		$this->stub_apply_filters_passthrough();

		$result = $this->service->get_message(
			'items',
			$this->make_filters( array(
				'country'        => 'US',
				'subdivision'    => 'US:KS',
				'search'         => 'drill',
				'user_id'        => 42,
				'auction_id'     => 100,
				'location_label' => 'Kansas, US',
				'seller_name'    => "John's Farm Equipment",
				'auction_title'  => 'Spring 2026 Farm Auction',
			) )
		);

		$this->assertSame(
			"No items found for term \"drill\" in Kansas, US by John's Farm Equipment within Spring 2026 Farm Auction.",
			$result
		);
	}

	public function test_combines_search_location_and_seller(): void {
		$this->stub_apply_filters_passthrough();

		$result = $this->service->get_message(
			'auctions',
			$this->make_filters( array(
				'country'        => 'CA',
				'search'         => 'tractor',
				'user_id'        => 42,
				'location_label' => 'Canada',
				'seller_name'    => "John's Farm Equipment",
			) )
		);

		$this->assertSame(
			"No auctions found for term \"tractor\" in Canada by John's Farm Equipment.",
			$result
		);
	}

	// --- Filter hook ---

	public function test_passes_message_through_filter_hook(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( string $tag, $value, ...$args ) {
				if ( 'aucteeno_query_loop_no_results' === $tag ) {
					return 'Custom override message.';
				}
				return $value;
			}
		);

		$result = $this->service->get_message( 'auctions', $this->make_filters() );

		$this->assertSame( 'Custom override message.', $result );
	}

	public function test_filter_hook_receives_filters_and_query_type(): void {
		$captured = null;
		Functions\when( 'apply_filters' )->alias(
			static function ( string $tag, $value, ...$args ) use ( &$captured ) {
				if ( 'aucteeno_query_loop_no_results' === $tag ) {
					$captured = array(
						'message'    => $value,
						'filters'    => $args[0],
						'query_type' => $args[1],
					);
				}
				return $value;
			}
		);

		$filters = $this->make_filters( array(
			'country'        => 'CA',
			'search'         => 'test',
			'user_id'        => 5,
			'location_label' => 'Canada',
			'seller_name'    => 'Test Seller',
		) );

		$this->service->get_message( 'items', $filters );

		$this->assertNotNull( $captured );
		$this->assertSame( $filters, $captured['filters'] );
		$this->assertSame( 'items', $captured['query_type'] );
	}
}
