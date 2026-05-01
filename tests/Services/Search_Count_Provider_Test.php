<?php
namespace The_Another\Plugin\Aucteeno\Tests\Services;

use The_Another\Plugin\Aucteeno\Services\Search_Count_Provider;
use The_Another\Plugin\Aucteeno\Hook_Manager;
use Brain\Monkey\Functions;
use Brain\Monkey;
use PHPUnit\Framework\TestCase;

class Search_Count_Provider_Test extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_returns_cached_value_when_transient_present(): void {
		Functions\when('get_transient')->justReturn(42);
		$hook_manager = $this->createMock(Hook_Manager::class);
		$provider = new Search_Count_Provider($hook_manager);
		$this->assertSame(42, $provider->get_running_upcoming_items_count(5));
	}

	public function test_queries_db_and_caches_when_transient_missing(): void {
		Functions\when('get_transient')->justReturn(false);
		$set_called = false;
		Functions\when('set_transient')->alias(function ($key, $value, $ttl) use (&$set_called) {
			$set_called = true;
			$this->assertSame('aucteeno_search_count_items_running_upcoming', $key);
			$this->assertSame(123, $value);
			$this->assertSame(300, $ttl);
			return true;
		});

		global $wpdb;
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['get_var'])
			->getMock();
		$wpdb->prefix = 'wp_';
		$wpdb->posts  = 'wp_posts';
		$captured_sql = null;
		$wpdb->method('get_var')->willReturnCallback(function ($sql) use (&$captured_sql) {
			$captured_sql = $sql;
			return '123';
		});

		$hook_manager = $this->createMock(Hook_Manager::class);
		$provider = new Search_Count_Provider($hook_manager);
		$this->assertSame(123, $provider->get_running_upcoming_items_count(5));
		$this->assertTrue($set_called);
		$this->assertStringContainsString('wp_aucteeno_items', $captured_sql);
		$this->assertStringContainsString("post_status = 'publish'", $captured_sql);
		$this->assertStringContainsString('bidding_ends_at > UNIX_TIMESTAMP()', $captured_sql);
	}

	public function test_zero_cache_minutes_skips_transient(): void {
		$get_called = false;
		$set_called = false;
		Functions\when('get_transient')->alias(function () use (&$get_called) {
			$get_called = true;
			return false;
		});
		Functions\when('set_transient')->alias(function () use (&$set_called) {
			$set_called = true;
			return true;
		});

		global $wpdb;
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['get_var'])
			->getMock();
		$wpdb->prefix = 'wp_';
		$wpdb->posts  = 'wp_posts';
		$wpdb->method('get_var')->willReturn('99');

		$hook_manager = $this->createMock(Hook_Manager::class);
		$provider = new Search_Count_Provider($hook_manager);
		$this->assertSame(99, $provider->get_running_upcoming_items_count(0));
		$this->assertFalse($get_called, 'get_transient must be skipped when cache_minutes=0');
		$this->assertFalse($set_called, 'set_transient must be skipped when cache_minutes=0');
	}
}
