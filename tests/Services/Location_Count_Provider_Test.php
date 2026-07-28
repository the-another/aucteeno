<?php
namespace The_Another\Plugin\Aucteeno\Tests\Services;

use The_Another\Plugin\Aucteeno\Services\Location_Count_Provider;
use The_Another\Plugin\Aucteeno\Hook_Manager;
use Brain\Monkey\Functions;
use Brain\Monkey;
use PHPUnit\Framework\TestCase;

class Location_Count_Provider_Test extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a $wpdb double that records the SQL it is handed.
	 *
	 * @param mixed $var_return     Value get_var() returns.
	 * @param mixed $results_return Value get_results() returns.
	 * @return object
	 */
	private function wpdb_spy($var_return, $results_return = []) {
		$wpdb = $this->getMockBuilder(\stdClass::class)
			->addMethods(['get_var', 'get_results', 'prepare'])
			->getMock();
		$wpdb->prefix = 'wp_';
		$wpdb->posts  = 'wp_posts';
		$wpdb->captured_sql = null;

		// Mirror wpdb::prepare closely enough to assert on the final SQL.
		$wpdb->method('prepare')->willReturnCallback(function ($sql, $args) {
			foreach ((array) $args as $arg) {
				$sql = preg_replace('/%[ds]/', "'" . $arg . "'", $sql, 1);
			}
			return $sql;
		});
		$wpdb->method('get_var')->willReturnCallback(function ($sql) use ($wpdb, $var_return) {
			$wpdb->captured_sql = $sql;
			return $var_return;
		});
		$wpdb->method('get_results')->willReturnCallback(function ($sql) use ($wpdb, $results_return) {
			$wpdb->captured_sql = $sql;
			return $results_return;
		});

		return $wpdb;
	}

	private function provider(): Location_Count_Provider {
		return new Location_Count_Provider($this->createMock(Hook_Manager::class));
	}

	public function test_active_auctions_count_returns_cached_value(): void {
		Functions\when('get_transient')->justReturn(17);
		$this->assertSame(17, $this->provider()->get_active_auctions_count('US'));
	}

	public function test_active_auctions_count_queries_country_without_posts_join(): void {
		Functions\when('get_transient')->justReturn(false);
		Functions\when('set_transient')->justReturn(true);

		global $wpdb;
		$wpdb = $this->wpdb_spy('8');

		$this->assertSame(8, $this->provider()->get_active_auctions_count('US'));

		$this->assertStringContainsString('wp_aucteeno_auctions', $wpdb->captured_sql);
		$this->assertStringContainsString("a.location_country = 'US'", $wpdb->captured_sql);
		$this->assertStringContainsString('bidding_ends_at > UNIX_TIMESTAMP()', $wpdb->captured_sql);
		$this->assertStringNotContainsString('location_subdivision', $wpdb->captured_sql);
		// No wp_posts JOIN: trashed/deleted auctions are already gone from the
		// HPS table, so the JOIN only excluded drafts while forcing a full
		// wp_posts index scan on every location archive render.
		$this->assertStringNotContainsString('JOIN', $wpdb->captured_sql);
		$this->assertStringNotContainsString('wp_posts', $wpdb->captured_sql);
	}

	public function test_active_auctions_count_adds_subdivision_filter(): void {
		Functions\when('get_transient')->justReturn(false);
		Functions\when('set_transient')->justReturn(true);

		global $wpdb;
		$wpdb = $this->wpdb_spy('3');

		$this->assertSame(3, $this->provider()->get_active_auctions_count('US', 'US:NY'));
		$this->assertStringContainsString("a.location_subdivision = 'US:NY'", $wpdb->captured_sql);
	}

	public function test_active_auctions_count_short_circuits_on_empty_country(): void {
		$get_called = false;
		Functions\when('get_transient')->alias(function () use (&$get_called) {
			$get_called = true;
			return false;
		});

		$this->assertSame(0, $this->provider()->get_active_auctions_count(''));
		$this->assertFalse($get_called, 'An empty country must not reach the cache or the DB');
	}

	public function test_active_auctions_count_caches_per_location(): void {
		Functions\when('get_transient')->justReturn(false);
		$keys = [];
		Functions\when('set_transient')->alias(function ($key, $value, $ttl) use (&$keys) {
			$keys[] = $key;
			$this->assertSame(300, $ttl);
			return true;
		});

		global $wpdb;
		$wpdb = $this->wpdb_spy('1');

		$provider = $this->provider();
		$provider->get_active_auctions_count('US');
		$provider->get_active_auctions_count('CA');
		$provider->get_active_auctions_count('US', 'US:NY');

		$this->assertCount(3, $keys);
		$this->assertCount(3, array_unique($keys), 'Each location must cache under its own key');
		foreach ($keys as $key) {
			$this->assertStringStartsWith('aucteeno_count_active_auctions_', $key);
		}
	}

	public function test_item_counts_by_location_returns_cached_value(): void {
		$cached = [['location_country' => 'US', 'location_subdivision' => '', 'item_count' => 5]];
		Functions\when('get_transient')->justReturn($cached);
		$this->assertSame($cached, $this->provider()->get_item_counts_by_location());
	}

	public function test_item_counts_by_location_groups_and_normalises_rows(): void {
		Functions\when('get_transient')->justReturn(false);
		$stored = null;
		Functions\when('set_transient')->alias(function ($key, $value, $ttl) use (&$stored) {
			$stored = $value;
			$this->assertSame('aucteeno_count_items_by_location', $key);
			$this->assertSame(300, $ttl);
			return true;
		});

		global $wpdb;
		$wpdb = $this->wpdb_spy(null, [
			['location_country' => 'US', 'location_subdivision' => 'US:NY', 'item_count' => '42'],
			['location_country' => 'CA', 'location_subdivision' => '', 'item_count' => '7'],
		]);

		$result = $this->provider()->get_item_counts_by_location();

		$this->assertSame(
			[
				['location_country' => 'US', 'location_subdivision' => 'US:NY', 'item_count' => 42],
				['location_country' => 'CA', 'location_subdivision' => '', 'item_count' => 7],
			],
			$result
		);
		$this->assertSame($result, $stored);

		$this->assertStringContainsString('wp_aucteeno_items', $wpdb->captured_sql);
		$this->assertStringContainsString('GROUP BY location_country, location_subdivision', $wpdb->captured_sql);
		$this->assertStringContainsString('bidding_status IN (10, 20)', $wpdb->captured_sql);
		$this->assertStringNotContainsString('JOIN', $wpdb->captured_sql);
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
		$wpdb = $this->wpdb_spy('4');

		$this->assertSame(4, $this->provider()->get_active_auctions_count('US', '', 0));
		$this->assertFalse($get_called, 'get_transient must be skipped when cache_minutes=0');
		$this->assertFalse($set_called, 'set_transient must be skipped when cache_minutes=0');
	}
}
