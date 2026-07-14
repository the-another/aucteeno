<?php
namespace The_Another\Plugin\Aucteeno\Tests\Services;

use The_Another\Plugin\Aucteeno\Services\Search_Block_Service;
use The_Another\Plugin\Aucteeno\Hook_Manager;
use Brain\Monkey\Functions;
use Brain\Monkey;
use PHPUnit\Framework\TestCase;

class Search_Block_Service_Test extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_page_options_returns_fallbacks_for_id_zero(): void {
		$svc  = $this->make_service();
		$opts = $svc->get_page_options( 0 );
		$this->assertSame( [ 'perPage' => 25, 'orderBy' => 'ending_soon', 'pageUrl' => '' ], $opts );
	}

	public function test_get_page_options_returns_fallbacks_when_post_missing(): void {
		Functions\when( 'get_post' )->justReturn( null );
		$svc  = $this->make_service();
		$opts = $svc->get_page_options( 42 );
		$this->assertSame( '', $opts['pageUrl'] );
		$this->assertSame( 25, $opts['perPage'] );
		$this->assertSame( 'ending_soon', $opts['orderBy'] );
	}

	public function test_get_page_options_returns_fallbacks_for_non_publish_post(): void {
		$post = (object) [ 'post_status' => 'draft', 'post_content' => '', 'post_modified' => '2026-05-01 00:00:00' ];
		Functions\when( 'get_post' )->justReturn( $post );
		$svc = $this->make_service();
		$this->assertSame( '', $svc->get_page_options( 42 )['pageUrl'] );
	}

	public function test_get_page_options_extracts_from_query_loop_block(): void {
		$post = (object) [
			'post_status'   => 'publish',
			'post_content'  => '<!-- wp:aucteeno/query-loop {"perPage":12,"orderBy":"newest"} /-->',
			'post_modified' => '2026-05-01 00:00:00',
		];
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/search-items/' );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'parse_blocks' )->justReturn( [ [
			'blockName'   => 'aucteeno/query-loop',
			'attrs'       => [ 'perPage' => 12, 'orderBy' => 'newest' ],
			'innerBlocks' => [],
		] ] );

		$svc  = $this->make_service();
		$opts = $svc->get_page_options( 42 );
		$this->assertSame( 12, $opts['perPage'] );
		$this->assertSame( 'newest', $opts['orderBy'] );
		$this->assertSame( 'https://example.com/search-items/', $opts['pageUrl'] );
	}

	public function test_get_page_options_falls_back_when_no_query_loop(): void {
		$post = (object) [
			'post_status'   => 'publish',
			'post_content'  => '<!-- wp:paragraph -->Hello<!-- /wp:paragraph -->',
			'post_modified' => '2026-05-01 00:00:00',
		];
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/p/' );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'parse_blocks' )->justReturn( [ [ 'blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [] ] ] );

		$svc  = $this->make_service();
		$opts = $svc->get_page_options( 42 );
		$this->assertSame( 25, $opts['perPage'] );
		$this->assertSame( 'ending_soon', $opts['orderBy'] );
		$this->assertSame( 'https://example.com/p/', $opts['pageUrl'] );
	}

	public function test_get_page_options_returns_cached_value(): void {
		$post = (object) [
			'post_status'   => 'publish',
			'post_content'  => '',
			'post_modified' => '2026-05-01 00:00:00',
		];
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_transient' )->justReturn( [ 'perPage' => 99, 'orderBy' => 'newest', 'pageUrl' => 'https://x/' ] );
		$svc  = $this->make_service();
		$opts = $svc->get_page_options( 42 );
		$this->assertSame( 99, $opts['perPage'] );
		$this->assertSame( 'newest', $opts['orderBy'] );
	}

	public function test_get_page_options_finds_query_loop_in_inner_blocks(): void {
		$post = (object) [
			'post_status'   => 'publish',
			'post_content'  => '',
			'post_modified' => '2026-05-01 00:00:00',
		];
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_permalink' )->justReturn( 'https://x/' );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'parse_blocks' )->justReturn( [ [
			'blockName'   => 'core/group',
			'attrs'       => [],
			'innerBlocks' => [ [
				'blockName'   => 'aucteeno/query-loop',
				'attrs'       => [ 'perPage' => 30, 'orderBy' => 'lot_number' ],
				'innerBlocks' => [],
			] ],
		] ] );

		$svc  = $this->make_service();
		$opts = $svc->get_page_options( 42 );
		$this->assertSame( 30, $opts['perPage'] );
		$this->assertSame( 'lot_number', $opts['orderBy'] );
	}

	public function test_get_page_options_clamps_per_page(): void {
		$post = (object) [
			'post_status'   => 'publish',
			'post_content'  => '',
			'post_modified' => '2026-05-01 00:00:00',
		];
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_permalink' )->justReturn( 'https://x/' );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'parse_blocks' )->justReturn( [ [
			'blockName'   => 'aucteeno/query-loop',
			'attrs'       => [ 'perPage' => 9999, 'orderBy' => 'ending_soon' ],
			'innerBlocks' => [],
		] ] );

		$svc  = $this->make_service();
		$opts = $svc->get_page_options( 42 );
		$this->assertSame( 100, $opts['perPage'] );
	}

	public function test_get_page_options_rejects_invalid_orderby(): void {
		$post = (object) [
			'post_status'   => 'publish',
			'post_content'  => '',
			'post_modified' => '2026-05-01 00:00:00',
		];
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_permalink' )->justReturn( 'https://x/' );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'parse_blocks' )->justReturn( [ [
			'blockName'   => 'aucteeno/query-loop',
			'attrs'       => [ 'orderBy' => 'invalid_garbage' ],
			'innerBlocks' => [],
		] ] );

		$svc  = $this->make_service();
		$opts = $svc->get_page_options( 42 );
		$this->assertSame( 'ending_soon', $opts['orderBy'] );
	}

	public function test_init_registers_required_hooks(): void {
		$hook_manager = $this->createMock( Hook_Manager::class );
		$registered   = [];
		$hook_manager->method( 'register_action' )->willReturnCallback( function ( ...$args ) use ( &$registered ) {
			$registered[] = $args[0];
		} );
		$svc = new Search_Block_Service( $hook_manager );
		$svc->init();

		$this->assertContains( 'save_post_page', $registered );
		// No per-save count invalidation: mass Action Scheduler imports save
		// items in bulk, which kept the count transient permanently cold and
		// exposed visitors to the expensive count query. TTL handles freshness.
		$this->assertNotContains( 'save_post_aucteeno-ext-item', $registered );
		$this->assertNotContains( 'save_post_aucteeno-ext-auction', $registered );
		$this->assertNotContains( 'trashed_aucteeno-ext-item', $registered );
		$this->assertNotContains( 'trashed_aucteeno-ext-auction', $registered );
	}

	public function test_on_page_save_deletes_transient(): void {
		$deleted_key = null;
		Functions\when( 'delete_transient' )->alias( function ( $key ) use ( &$deleted_key ) {
			$deleted_key = $key;
			return true;
		} );

		$svc = $this->make_service();
		$svc->on_page_save( 42 );
		$this->assertSame( 'aucteeno_search_pageopts_42', $deleted_key );
	}

	private function make_service(): Search_Block_Service {
		return new Search_Block_Service( $this->createMock( Hook_Manager::class ) );
	}
}
