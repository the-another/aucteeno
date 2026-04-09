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
}
