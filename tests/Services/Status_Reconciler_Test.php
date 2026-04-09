<?php
/**
 * Status_Reconciler Tests
 *
 * @package Aucteeno
 */

namespace The_Another\Plugin\Aucteeno\Tests\Services;

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
	 * Test that bulk_set returns false when term not found.
	 *
	 * @return void
	 */
	public function test_bulk_set_returns_false_when_term_not_found(): void {
		$wpdb            = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->term_taxonomy = 'wp_term_taxonomy';
		$GLOBALS['wpdb'] = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// No DB queries should be issued after term lookup fails.
		$wpdb->shouldReceive( 'prepare' )->never();

		Functions\expect( 'get_terms' )->once()->andReturn( array() );
		Functions\expect( 'wc_get_logger' )
			->once()
			->andReturn( Mockery::mock( array( 'error' => null ) ) );

		$rec = $this->make_reconciler();

		// Pre-populate ttids_cache via reflection to bypass the taxonomy fetch step.
		$ref = new ReflectionClass( $rec );
		$p   = $ref->getProperty( 'ttids_cache' );
		$p->setAccessible( true );
		$p->setValue( $rec, array( 5, 6, 7 ) );

		$result = $this->call_private( $rec, 'bulk_set_bidding_status_term', array( 1 ), 999 );
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
		$term_slug_obj->slug = 'publish';

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
}
