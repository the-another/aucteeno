<?php
/**
 * Status_Reconciler Tests
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Services;

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use The_Another\Plugin\Aucteeno\Hook_Manager;
use The_Another\Plugin\Aucteeno\Services\Status_Reconciler;

class Status_Reconciler_Test extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	private function make_reconciler(): Status_Reconciler {
		$hook_manager = Mockery::mock( Hook_Manager::class );
		$hook_manager->shouldReceive( 'register_action' )->byDefault();
		return new Status_Reconciler( $hook_manager );
	}

	private function call_private( object $obj, string $method, mixed ...$args ): mixed {
		$ref = new ReflectionClass( $obj );
		$m   = $ref->getMethod( $method );
		$m->setAccessible( true );
		return $m->invoke( $obj, ...$args );
	}

	// --- compute_correct_status ---

	public function test_compute_correct_status_returns_upcoming_when_not_yet_started(): void {
		$future = time() + 3600;
		$result = $this->call_private( $this->make_reconciler(), 'compute_correct_status', $future, $future + 7200 );
		$this->assertSame( 20, $result );
	}

	public function test_compute_correct_status_returns_running_when_in_progress(): void {
		$past   = time() - 3600;
		$future = time() + 3600;
		$result = $this->call_private( $this->make_reconciler(), 'compute_correct_status', $past, $future );
		$this->assertSame( 10, $result );
	}

	public function test_compute_correct_status_returns_expired_when_ended(): void {
		$past   = time() - 3600;
		$result = $this->call_private( $this->make_reconciler(), 'compute_correct_status', $past - 7200, $past );
		$this->assertSame( 30, $result );
	}

	public function test_compute_correct_status_treats_zero_starts_at_as_already_started(): void {
		$future = time() + 3600;
		// starts_at = 0 means already started; ends_at in future = running.
		$result = $this->call_private( $this->make_reconciler(), 'compute_correct_status', 0, $future );
		$this->assertSame( 10, $result );
	}

	// --- bulk_set_bidding_status_term ---

	/**
	 * Test that bulk_set returns false for empty ids.
	 *
	 * @return void
	 */
	public function test_bulk_set_returns_false_for_empty_ids(): void {
		$result = $this->call_private( $this->make_reconciler(), 'bulk_set_bidding_status_term', array(), 10 );
		$this->assertFalse( $result );
	}

	/**
	 * Test that bulk_set returns false when taxonomy has no terms.
	 *
	 * @return void
	 */
	public function test_bulk_set_returns_false_when_taxonomy_has_no_terms(): void {
		$wpdb            = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->term_taxonomy = 'wp_term_taxonomy';
		$GLOBALS['wpdb'] = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$wpdb->shouldReceive( 'get_col' )->once()->andReturn( array() );

		Functions\expect( 'wc_get_logger' )
			->once()
			->andReturn( Mockery::mock( array( 'error' => null ) ) );

		$result = $this->call_private( $this->make_reconciler(), 'bulk_set_bidding_status_term', array( 1 ), 10 );
		$this->assertFalse( $result );
	}

	/**
	 * Test that bulk_set returns false when get_term_by returns false for a valid slug.
	 *
	 * Covers lines ~241-249 in class-status-reconciler.php where number_to_term()
	 * successfully resolves a slug ('running') but get_term_by() returns false
	 * (term exists in mapper but is absent from the DB).
	 *
	 * @return void
	 */
	public function test_bulk_set_returns_false_when_term_not_found(): void {
		$wpdb                = Mockery::mock( 'wpdb' );
		$wpdb->prefix        = 'wp_';
		$wpdb->term_taxonomy = 'wp_term_taxonomy';
		$GLOBALS['wpdb']     = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// No DB writes should happen after get_term_by fails.
		$wpdb->shouldReceive( 'prepare' )->never();

		// Make number_to_term(10) return 'running': get_terms returns one term with slug='running'.
		$term_slug_obj       = new \stdClass();
		$term_slug_obj->slug = 'running';
		Functions\expect( 'get_terms' )
			->once()
			->andReturn( array( $term_slug_obj ) );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		// get_term_by returns false: term slug resolved but not present in DB.
		Functions\expect( 'get_term_by' )
			->once()
			->andReturn( false );

		Functions\expect( 'wc_get_logger' )
			->once()
			->andReturn( Mockery::mock( array( 'error' => null ) ) );

		$rec = $this->make_reconciler();

		// Pre-populate ttids_cache via reflection to bypass the taxonomy fetch step (step 1).
		$ref = new ReflectionClass( $rec );
		$p   = $ref->getProperty( 'ttids_cache' );
		$p->setAccessible( true );
		$p->setValue( $rec, array( 5, 6, 7 ) );

		$result = $this->call_private( $rec, 'bulk_set_bidding_status_term', array( 1 ), 10 );
		$this->assertFalse( $result );
	}

	/**
	 * Test that bulk_set returns true on successful delete and insert.
	 *
	 * @return void
	 */
	public function test_bulk_set_returns_true_on_successful_delete_and_insert(): void {
		$wpdb                     = Mockery::mock( 'wpdb' );
		$wpdb->prefix             = 'wp_';
		$wpdb->term_relationships = 'wp_term_relationships';
		$wpdb->term_taxonomy      = 'wp_term_taxonomy';
		$GLOBALS['wpdb']          = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$term_obj                   = new \stdClass();
		$term_obj->term_taxonomy_id = 7;

		$term_slug_obj       = new \stdClass();
		$term_slug_obj->slug = 'running';

		Functions\expect( 'get_terms' )->once()->andReturn( array( $term_slug_obj ) );
		Functions\expect( 'is_wp_error' )->twice()->andReturn( false );
		Functions\expect( 'get_term_by' )->once()->andReturn( $term_obj );

		$wpdb->shouldReceive( 'prepare' )
			->twice() // once for DELETE, once for INSERT
			->andReturn( 'DELETE_SQL', 'INSERT_SQL' );
		$wpdb->shouldReceive( 'query' )
			->once()->with( 'DELETE_SQL' )->andReturn( 2 );
		$wpdb->shouldReceive( 'query' )
			->once()->with( 'INSERT_SQL' )->andReturn( 3 );

		$rec = $this->make_reconciler();
		$ref = new ReflectionClass( $rec );
		// Pre-populate ttids_cache.
		$p = $ref->getProperty( 'ttids_cache' );
		$p->setAccessible( true );
		$p->setValue( $rec, array( 5, 6, 7 ) );

		$result = $this->call_private( $rec, 'bulk_set_bidding_status_term', array( 1, 2, 3 ), 10 );
		$this->assertTrue( $result );
	}

	// --- process_auction_batch ---

	/**
	 * Test that process_auction_batch updates taxonomy before HPS and calls wp_update_term_count.
	 *
	 * @return void
	 */
	public function test_process_auction_batch_updates_taxonomy_before_hps(): void {
		$wpdb                     = Mockery::mock( 'wpdb' );
		$wpdb->prefix             = 'wp_';
		$wpdb->term_relationships = 'wp_term_relationships';
		$wpdb->term_taxonomy      = 'wp_term_taxonomy';
		$GLOBALS['wpdb']          = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// Two rows: one upcoming→running, one running→expired.
		$past   = time() - 3600;
		$future = time() + 3600;
		$rows   = array(
			array( 'auction_id' => 1, 'bidding_starts_at' => $past,   'bidding_ends_at' => $future, 'bidding_status' => 20 ),
			array( 'auction_id' => 2, 'bidding_starts_at' => $past,   'bidding_ends_at' => $past,   'bidding_status' => 10 ),
		);

		// Stub ttids lookup.
		$wpdb->shouldReceive( 'get_col' )->once()->andReturn( array( 5, 6, 7 ) );

		// Stub taxonomy resolution for both status groups.
		$term_running             = new \stdClass();
		$term_running->term_taxonomy_id = 5;
		$term_expired             = new \stdClass();
		$term_expired->term_taxonomy_id = 7;

		Functions\expect( 'get_term_by' )
			->twice()
			->andReturn( $term_running, $term_expired );

		Functions\expect( 'get_terms' )
			->twice()
			->andReturn(
				array( (object) array( 'slug' => 'running' ) ),
				array( (object) array( 'slug' => 'expired' ) )
			);

		Functions\expect( 'is_wp_error' )
			->zeroOrMoreTimes()
			->andReturn( false );

		// 2 status groups × (1 DELETE + 1 INSERT) = 4 taxonomy prepare/query calls.
		// 2 HPS UPDATE calls (one per status group) = 2 more prepare/query calls.
		// Total: 6 prepare calls and 6 query calls.
		$wpdb->shouldReceive( 'prepare' )->times( 6 )->andReturn( 'SQL' );
		$wpdb->shouldReceive( 'query' )->times( 6 )->andReturn( 1 );

		// Expect term count recomputed once after all taxonomy writes.
		Functions\expect( 'wp_update_term_count' )->once();

		$rec = $this->make_reconciler();
		$this->call_private( $rec, 'process_auction_batch', $rows );
		$this->addToAssertionCount( 1 );
	}

	/**
	 * Test that process_auction_batch skips HPS when taxonomy fails.
	 *
	 * @return void
	 */
	public function test_process_auction_batch_skips_hps_when_taxonomy_fails(): void {
		$wpdb                = Mockery::mock( 'wpdb' );
		$wpdb->prefix        = 'wp_';
		$wpdb->term_taxonomy = 'wp_term_taxonomy';
		$GLOBALS['wpdb']     = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$past = time() - 3600;
		$rows = array(
			array( 'auction_id' => 1, 'bidding_starts_at' => $past, 'bidding_ends_at' => $past, 'bidding_status' => 10 ),
		);

		// Taxonomy fetch returns empty → bulk_set returns false, then process_auction_batch also logs.
		$wpdb->shouldReceive( 'get_col' )->once()->andReturn( array() );
		Functions\expect( 'wc_get_logger' )->twice()->andReturn( Mockery::mock( array( 'error' => null ) ) );

		// HPS update must NOT be called.
		$wpdb->shouldReceive( 'prepare' )->never();
		$wpdb->shouldReceive( 'query' )->never();

		Functions\expect( 'wp_update_term_count' )->never();

		$rec = $this->make_reconciler();
		$this->call_private( $rec, 'process_auction_batch', $rows );
		$this->addToAssertionCount( 1 );
	}

	// --- process_item_batch ---

	/**
	 * Test that process_item_batch updates HPS only with no taxonomy calls.
	 *
	 * @return void
	 */
	public function test_process_item_batch_updates_hps_only_no_taxonomy(): void {
		$wpdb            = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$GLOBALS['wpdb'] = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$past = time() - 3600;
		$rows = array(
			array( 'item_id' => 10, 'bidding_starts_at' => $past, 'bidding_ends_at' => $past, 'bidding_status' => 10 ),
			array( 'item_id' => 11, 'bidding_starts_at' => $past, 'bidding_ends_at' => $past, 'bidding_status' => 10 ),
		);

		// Expect one HPS UPDATE (both IDs grouped into status 30).
		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'UPDATE_SQL' );
		$wpdb->shouldReceive( 'query' )->once()->with( 'UPDATE_SQL' )->andReturn( 2 );

		// Taxonomy methods must NOT be called.
		$wpdb->shouldReceive( 'get_col' )->never();

		$rec = $this->make_reconciler();
		$this->call_private( $rec, 'process_item_batch', $rows );
		$this->addToAssertionCount( 1 );
	}

	// --- run() loop ---

	/**
	 * Test that run() resets caches at start of each invocation.
	 *
	 * @return void
	 */
	public function test_run_resets_caches_at_start_of_each_invocation(): void {
		$wpdb            = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$GLOBALS['wpdb'] = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$rec = $this->make_reconciler();
		$ref = new ReflectionClass( $rec );

		// Pre-populate caches from a "previous run".
		$tc = $ref->getProperty( 'ttids_cache' );
		$tc->setAccessible( true );
		$tc->setValue( $rec, array( 1, 2, 3 ) );

		$tt = $ref->getProperty( 'target_ttid_cache' );
		$tt->setAccessible( true );
		$tt->setValue( $rec, array( 10 => 5, 30 => 7 ) );

		// run() makes two DB round-trips before exiting:
		// 1. Database_Auctions::get_stale() returns [] → phase transitions to items.
		// 2. Database_Items::get_stale() returns [] → loop breaks.
		// Each static get_stale() issues one prepare + one get_results.
		$wpdb->shouldReceive( 'prepare' )->twice()->andReturn( 'PREPARED_SQL' );
		$wpdb->shouldReceive( 'get_results' )->twice()->andReturn( array() );

		$rec->run();

		// After run(), both caches must be reset to their initial state.
		$this->assertNull( $tc->getValue( $rec ) );
		$this->assertSame( array(), $tt->getValue( $rec ) );
	}

	/**
	 * Test that bulk_set returns false when number_to_term returns '' (no slug for given status).
	 *
	 * Covers the branch at class-status-reconciler.php where '' === $slug after
	 * Bidding_Status_Mapper::number_to_term() is called with an unmapped status.
	 *
	 * @return void
	 */
	public function test_bulk_set_returns_false_when_no_slug_for_status(): void {
		$wpdb                = Mockery::mock( 'wpdb' );
		$wpdb->prefix        = 'wp_';
		$wpdb->term_taxonomy = 'wp_term_taxonomy';
		$GLOBALS['wpdb']     = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// No DB writes should happen — method returns before any prepare() call.
		$wpdb->shouldReceive( 'prepare' )->never();

		// get_terms returns empty array so number_to_term(999) returns ''.
		Functions\expect( 'get_terms' )
			->once()
			->andReturn( array() );

		Functions\expect( 'wc_get_logger' )
			->once()
			->andReturn( Mockery::mock( array( 'error' => null ) ) );

		$rec = $this->make_reconciler();

		// Pre-populate ttids_cache via reflection to bypass the taxonomy fetch step (step 1).
		$ref = new ReflectionClass( $rec );
		$p   = $ref->getProperty( 'ttids_cache' );
		$p->setAccessible( true );
		$p->setValue( $rec, array( 5, 6, 7 ) );

		$result = $this->call_private( $rec, 'bulk_set_bidding_status_term', array( 1 ), 999 );
		$this->assertFalse( $result );
	}
}
