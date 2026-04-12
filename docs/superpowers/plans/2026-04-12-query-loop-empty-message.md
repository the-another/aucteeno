# Query Loop Empty Message Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hardcoded "No auctions/items found." empty state in the query loop block with a dynamically composed message that includes active filter details (search, location, seller, parent auction), powered by a container-managed service and a `aucteeno_query_loop_no_results` filter hook.

**Architecture:** New `Query_Loop_Empty_Message` class registered as a singleton in the DI Container. Its `get_message()` method composes a human-readable sentence from pre-resolved filter labels and passes it through a WordPress filter hook. Location names are resolved in `render.php` via `Location_Helper::format_smart_location()` before being passed to the service — this keeps WooCommerce dependencies out of the service and makes it fully testable with Brain Monkey.

**Tech Stack:** PHP 8.3+, WordPress, WooCommerce, Brain Monkey (tests), PHPUnit

**Spec:** `docs/superpowers/specs/2026-04-12-query-loop-empty-message-design.md`

---

## File Structure

| File | Action | Responsibility |
|---|---|---|
| `includes/blocks/class-query-loop-empty-message.php` | Create | Service class — composes filter-aware empty message, applies WP filter hook |
| `tests/Blocks/Query_Loop_Empty_Message_Test.php` | Create | Unit tests for message composition, filter hook, edge cases |
| `includes/class-aucteeno.php` | Modify | Register service in container, call from `start()` |
| `blocks/query-loop/render.php` | Modify | Resolve filter labels, replace hardcoded empty state with service call |

---

## Chunk 1: Service Class and Tests

### Task 1: Create feature branch

**Files:** None

- [ ] **Step 1: Create and checkout feature branch**

```bash
cd /Volumes/DevExtreme/Aucteeno/WordPress/globalag/wp-content/plugins/aucteeno
git checkout -b feature/query-loop-empty-message
```

---

### Task 2: Write failing tests for `Query_Loop_Empty_Message`

**Files:**
- Create: `tests/Blocks/Query_Loop_Empty_Message_Test.php`

The test file follows the same pattern as `tests/Query_Loop_Location_Filter_Test.php` — uses Brain Monkey for mocking WordPress functions, PHPUnit TestCase.

**Important testing note:** The `WC()` function is defined as a real PHP function in `tests/bootstrap.php` (line 626), which means Brain Monkey's `Functions\when('WC')` cannot override it. To avoid this issue, the service class accepts pre-resolved display strings (location label, seller name, auction title) instead of raw codes/IDs. This keeps all WooCommerce dependencies in `render.php` where they naturally belong, and makes the service fully testable.

- [ ] **Step 1: Create test directory**

```bash
mkdir -p /Volumes/DevExtreme/Aucteeno/WordPress/globalag/wp-content/plugins/aucteeno/tests/Blocks
```

- [ ] **Step 2: Write the test file**

Create `tests/Blocks/Query_Loop_Empty_Message_Test.php`:

```php
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
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
cd /Volumes/DevExtreme/Aucteeno/WordPress/globalag/wp-content/plugins/aucteeno
./vendor/bin/phpunit tests/Blocks/Query_Loop_Empty_Message_Test.php
```

Expected: FAIL — `Query_Loop_Empty_Message` class does not exist yet.

- [ ] **Step 4: Commit failing tests**

```bash
git add tests/Blocks/Query_Loop_Empty_Message_Test.php
git commit -m "test: add failing tests for Query_Loop_Empty_Message service"
```

---

### Task 3: Implement `Query_Loop_Empty_Message` class

**Files:**
- Create: `includes/blocks/class-query-loop-empty-message.php`

The service receives pre-resolved display labels (`location_label`, `seller_name`, `auction_title`) alongside the raw filter values (`country`, `subdivision`, `user_id`, `auction_id`). It uses the labels for message composition and passes the full filters array (including raw values) to the hook, giving extension developers access to both.

- [ ] **Step 1: Create the service class**

Create `includes/blocks/class-query-loop-empty-message.php`:

```php
<?php
/**
 * Query Loop Empty Message service.
 *
 * Composes a filter-aware "no results" message for the query loop block
 * and passes it through the aucteeno_query_loop_no_results filter.
 *
 * @package Aucteeno
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno\Blocks;

use The_Another\Plugin\Aucteeno\Hook_Manager;

/**
 * Class Query_Loop_Empty_Message
 *
 * Container-managed service that builds the empty state message
 * for the query loop block, including active filter details.
 */
final class Query_Loop_Empty_Message {

	/**
	 * Hook manager instance.
	 *
	 * @var Hook_Manager
	 */
	private Hook_Manager $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param Hook_Manager $hook_manager Hook manager instance.
	 */
	public function __construct( Hook_Manager $hook_manager ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Build a filter-aware empty state message.
	 *
	 * Composes a human-readable sentence from the query type and active filters,
	 * then passes it through the aucteeno_query_loop_no_results filter hook.
	 *
	 * The $filters array should contain both raw values (for the hook) and
	 * pre-resolved display labels (for message composition):
	 *
	 * Raw values (passed to hook for extension developers):
	 * - 'country'     => string  2-letter ISO code or ''
	 * - 'subdivision' => string  'COUNTRY:STATE' format or ''
	 * - 'search'      => string  Search term or ''
	 * - 'user_id'     => int     Seller user ID or 0
	 * - 'auction_id'  => int     Parent auction post ID or 0
	 *
	 * Display labels (used for message composition):
	 * - 'location_label' => string  Pre-resolved location name (e.g. "Canada", "Kansas, US") or ''
	 * - 'seller_name'    => string  Seller display name or ''
	 * - 'auction_title'  => string  Parent auction title or ''
	 *
	 * @param string $query_type 'auctions' or 'items'.
	 * @param array  $filters    Active filter values and display labels.
	 * @return string The composed message.
	 */
	public function get_message( string $query_type, array $filters ): string {
		// Base message.
		$base = 'auctions' === $query_type
			? __( 'No auctions found', 'aucteeno' )
			: __( 'No items found', 'aucteeno' );

		// Collect fragments.
		$fragments = array();

		// Search fragment.
		$search = trim( $filters['search'] ?? '' );
		if ( '' !== $search ) {
			/* translators: %s: search term */
			$fragments[] = sprintf( __( 'for term "%s"', 'aucteeno' ), $search );
		}

		// Location fragment (from pre-resolved label).
		$location_label = trim( $filters['location_label'] ?? '' );
		if ( '' !== $location_label ) {
			/* translators: %s: location name (e.g. "Canada" or "Kansas, US") */
			$fragments[] = sprintf( __( 'in %s', 'aucteeno' ), $location_label );
		}

		// Seller fragment (from pre-resolved name).
		$seller_name = trim( $filters['seller_name'] ?? '' );
		if ( '' !== $seller_name ) {
			/* translators: %s: seller display name */
			$fragments[] = sprintf( __( 'by %s', 'aucteeno' ), $seller_name );
		}

		// Parent auction fragment (from pre-resolved title).
		$auction_title = trim( $filters['auction_title'] ?? '' );
		if ( '' !== $auction_title ) {
			/* translators: %s: parent auction title */
			$fragments[] = sprintf( __( 'within %s', 'aucteeno' ), $auction_title );
		}

		// Compose sentence.
		$message = $base;
		if ( ! empty( $fragments ) ) {
			$message .= ' ' . implode( ' ', $fragments );
		}
		$message .= '.';

		/**
		 * Filters the "no results" message for the Aucteeno Query Loop block.
		 *
		 * Fires after the message is composed from active filters, before output.
		 * The $filters array contains both raw values (ISO codes, user IDs, post IDs)
		 * and pre-resolved display labels.
		 *
		 * @param string $message    The composed message string.
		 * @param array  $filters    The structured filters array.
		 * @param string $query_type 'auctions' or 'items'.
		 */
		return apply_filters( 'aucteeno_query_loop_no_results', $message, $filters, $query_type );
	}
}
```

- [ ] **Step 2: Regenerate autoloader**

The new class uses classmap autoloading. Regenerate so PHPUnit can find it:

```bash
cd /Volumes/DevExtreme/Aucteeno/WordPress/globalag/wp-content/plugins/aucteeno
composer dump-autoload
```

- [ ] **Step 3: Run tests to verify they pass**

```bash
cd /Volumes/DevExtreme/Aucteeno/WordPress/globalag/wp-content/plugins/aucteeno
./vendor/bin/phpunit tests/Blocks/Query_Loop_Empty_Message_Test.php
```

Expected: All tests PASS.

- [ ] **Step 4: Run full test suite to check for regressions**

```bash
cd /Volumes/DevExtreme/Aucteeno/WordPress/globalag/wp-content/plugins/aucteeno
./vendor/bin/phpunit
```

Expected: All tests PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/blocks/class-query-loop-empty-message.php tests/Blocks/Query_Loop_Empty_Message_Test.php
git commit -m "feat: add Query_Loop_Empty_Message service with filter-aware no-results message

Composes a detailed empty state message including active search, location,
seller, and parent auction filters. Passes through aucteeno_query_loop_no_results
filter hook for extensibility."
```

---

## Chunk 2: Container Registration and render.php Integration

### Task 4: Register service in the Container

**Files:**
- Modify: `includes/class-aucteeno.php`

- [ ] **Step 1: Add private registration method**

In `includes/class-aucteeno.php`, add a new private method after `register_status_reconciler()` (around line 379):

```php
/**
 * Register the query loop empty message service.
 *
 * @since TBD
 */
private function register_query_loop_empty_message(): void {
	$this->container->register(
		'query_loop_empty_message',
		function ( Container $c ) {
			return new Blocks\Query_Loop_Empty_Message( $c->get_hook_manager() );
		},
		true // Singleton.
	);
}
```

- [ ] **Step 2: Call the registration method in `start()`**

In the `start()` method (around line 98, after `$this->register_status_reconciler();`), add:

```php
// Register query loop empty message service.
$this->register_query_loop_empty_message();
```

- [ ] **Step 3: Run full test suite**

```bash
cd /Volumes/DevExtreme/Aucteeno/WordPress/globalag/wp-content/plugins/aucteeno
./vendor/bin/phpunit
```

Expected: All tests PASS.

- [ ] **Step 4: Commit**

```bash
git add includes/class-aucteeno.php
git commit -m "feat: register Query_Loop_Empty_Message in DI container"
```

---

### Task 5: Update `render.php` to use the service

**Files:**
- Modify: `blocks/query-loop/render.php`

- [ ] **Step 1: Add Container import**

At the top of `blocks/query-loop/render.php`, after the existing `use` statements (line 21), add:

```php
use The_Another\Plugin\Aucteeno\Container;
use The_Another\Plugin\Aucteeno\Helpers\Location_Helper;
```

Note: `Location_Helper` may already be imported — check first. If not, add it.

- [ ] **Step 2: Reset location vars in `$has_product_ids` path**

In the `$has_product_ids` block (around line 235), after the existing resets (`$user_id = 0;`, `$auction_id = 0;`, etc.), add:

```php
$location_country     = '';
$location_subdivision = '';
```

This prevents stale location values from appearing in the empty message when the block is in product-IDs mode.

- [ ] **Step 3: Replace the hardcoded empty state**

Replace the empty state block at lines 533–548:

```php
} else {
	// Empty state.
	?>
	<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<p class="aucteeno-query-loop__empty">
			<?php
			if ( 'auctions' === $query_type ) {
				esc_html_e( 'No auctions found.', 'aucteeno' );
			} else {
				esc_html_e( 'No items found.', 'aucteeno' );
			}
			?>
		</p>
	</div>
	<?php
}
```

With:

```php
} else {
	// Empty state — compose filter-aware message via service.
	// Resolve display labels from raw filter values.
	$location_label = '';
	if ( '' !== $location_country ) {
		$location_label = Location_Helper::format_smart_location( '', $location_subdivision, $location_country );
	}

	$seller_name = '';
	if ( $user_id > 0 ) {
		$seller_user = get_userdata( $user_id );
		if ( $seller_user && ! empty( $seller_user->display_name ) ) {
			$seller_name = $seller_user->display_name;
		}
	}

	$auction_title = '';
	if ( $auction_id > 0 ) {
		$auction_title = get_the_title( $auction_id );
	}

	$empty_message_service = Container::get_instance()->get( 'query_loop_empty_message' );
	$active_filters        = array(
		'country'        => $location_country,
		'subdivision'    => $location_subdivision,
		'search'         => $search_query,
		'user_id'        => $user_id,
		'auction_id'     => $auction_id,
		'location_label' => $location_label,
		'seller_name'    => $seller_name,
		'auction_title'  => $auction_title,
	);
	$empty_message = $empty_message_service->get_message( $query_type, $active_filters );
	?>
	<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<p class="aucteeno-query-loop__empty">
			<?php echo esc_html( $empty_message ); ?>
		</p>
	</div>
	<?php
}
```

- [ ] **Step 4: Run full test suite**

```bash
cd /Volumes/DevExtreme/Aucteeno/WordPress/globalag/wp-content/plugins/aucteeno
./vendor/bin/phpunit
```

Expected: All tests PASS.

- [ ] **Step 5: Run PHPCS linter**

```bash
cd /Volumes/DevExtreme/Aucteeno/WordPress/globalag/wp-content/plugins/aucteeno
make lint
```

Expected: No new violations.

- [ ] **Step 6: Commit**

```bash
git add blocks/query-loop/render.php
git commit -m "feat: use Query_Loop_Empty_Message service in query loop empty state

Replaces hardcoded 'No auctions/items found.' with a dynamically composed
message that includes active filter details (search, location, seller, auction).
Resolves display labels from raw codes/IDs before passing to the service."
```

---

### Task 6: Manual verification

- [ ] **Step 1: Build JS assets** (no JS changes, but ensure build is clean)

```bash
cd /Volumes/DevExtreme/Aucteeno/WordPress/globalag/wp-content/plugins/aucteeno
npm run build
```

- [ ] **Step 2: Test in browser**

Open the WordPress site in a browser and verify these scenarios:

1. Query loop with no filters and no results → shows `"No auctions found."` or `"No items found."`
2. Query loop with search filter `?s=nonexistent` → shows `"No auctions found for term "nonexistent"."`
3. Query loop on a location archive page with no results → shows message with location name
4. Query loop on a seller/vendor page with no results → shows message with seller name
5. Items query loop on an auction page with no results → shows message with auction title
6. Multiple filters active → shows combined sentence in correct order

- [ ] **Step 3: Final commit if any fixes needed**

Only if manual testing revealed issues that needed fixing.
