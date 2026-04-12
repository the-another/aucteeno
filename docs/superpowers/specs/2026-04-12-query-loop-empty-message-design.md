# Query Loop Empty Message — Filter-Aware "No Results" Message

**Date:** 2026-04-12
**Status:** Approved
**Scope:** Server-side render of the Aucteeno Query Loop block (`blocks/query-loop/render.php`)

## Problem

When the query loop block returns zero results and any filters are active (location, search, seller, parent auction), the empty state message is a generic "No auctions found." or "No items found." with no indication of which filters are applied. Users have no context about why results are empty.

## Solution

Replace the hardcoded empty state message with a dynamically composed sentence that includes active filter details, powered by a container-managed service class and a WordPress filter hook for extensibility.

## Design

### New Class: `Query_Loop_Empty_Message`

- **File:** `includes/blocks/class-query-loop-empty-message.php`
- **Namespace:** `The_Another\Plugin\Aucteeno\Blocks`
- **DI:** Constructor receives `Hook_Manager` (standard container pattern)
- **Singleton:** Registered in the Container, no `init()` method needed (passive dependency)

#### Public Method

```php
public function get_message( string $query_type, array $filters ): string
```

**Parameters:**

- `$query_type` — `'auctions'` or `'items'`
- `$filters` — structured associative array of active filter values:

```php
[
    'country'     => string, // 2-letter ISO code or ''
    'subdivision' => string, // 'COUNTRY:STATE' format or ''
    'search'      => string, // search term or ''
    'user_id'     => int,    // seller user ID or 0
    'auction_id'  => int,    // parent auction post ID or 0
]
```

#### Message Composition

1. **Base:** `"No auctions found"` or `"No items found"` (based on `$query_type`)
2. **Append filter fragments in order:**
   - Search: `for term "tractor"`
   - Location: `in Kansas, US` or `in Canada` (resolved via `Location_Helper`)
   - Seller: `by John's Farm Equipment` (resolved via `get_userdata()`)
   - Parent auction: `in Spring 2026 Farm Auction` (resolved via `get_the_title()`)
3. **End with period**
4. **Pass through WordPress filter hook** (see below)

#### Example Outputs

| Active Filters | Message |
|---|---|
| None | `No auctions found.` |
| Search | `No auctions found for term "tractor".` |
| Location (country) | `No auctions found in Canada.` |
| Location (subdivision) | `No items found in Kansas, US.` |
| Search + location + seller | `No auctions found for term "tractor" in Canada by John's Farm Equipment.` |
| Items by auction | `No items found in Spring 2026 Farm Auction.` |
| All filters | `No items found for term "drill" in Kansas, US by John's Farm Equipment in Spring 2026 Farm Auction.` |

#### Location Name Resolution

Uses existing `Location_Helper` methods:
- `Location_Helper::get_country_name( $country_code )` — returns full country name (e.g. `"Canada"`)
- `Location_Helper::get_subdivision_name( $country_code, $subdivision_code )` — returns subdivision name (e.g. `"Kansas"`)
- `Location_Helper::format_smart_location( '', $subdivision, $country )` — returns formatted string (e.g. `"Kansas, US"`)

When only a country is active, use the full country name. When a subdivision is active, use the `format_smart_location()` format (subdivision name + country code).

#### Seller Name Resolution

Uses `get_userdata( $user_id )` to retrieve the seller's `display_name`. Falls back gracefully if the user doesn't exist (omits the seller fragment).

#### Auction Title Resolution

Uses `get_the_title( $auction_id )` to retrieve the parent auction title. Falls back gracefully if the post doesn't exist (omits the auction fragment).

### WordPress Filter Hook

**Hook name:** `aucteeno_query_loop_no_results`

**Signature:**

```php
$message = apply_filters(
    'aucteeno_query_loop_no_results',
    string $message,    // The composed message string
    array  $filters,    // The structured filters array (raw values: codes, IDs)
    string $query_type  // 'auctions' or 'items'
);
```

The `$filters` array passed to the hook contains raw values (ISO codes, user IDs, post IDs), not resolved human-readable names. This allows extension developers to build entirely custom messages from scratch.

**Example usage:**

```php
add_filter( 'aucteeno_query_loop_no_results', function( $message, $filters, $query_type ) {
    if ( ! empty( $filters['search'] ) ) {
        return sprintf( 'Try broadening your search for "%s".', $filters['search'] );
    }
    return $message;
}, 10, 3 );
```

### Container Registration

In `class-aucteeno.php`, a new private method `register_query_loop_empty_message()`:

```php
$this->container->register(
    'query_loop_empty_message',
    function ( Container $c ) {
        return new Blocks\Query_Loop_Empty_Message( $c->get_hook_manager() );
    },
    true // Singleton.
);
```

Called during `start()`. No `->init()` call needed — passive dependency, only invoked when `render.php` requests it.

### Changes to `render.php`

Replace lines 533–547 (the hardcoded empty state) with:

```php
$empty_message_service = Container::get_instance()->get( 'query_loop_empty_message' );
$active_filters = [
    'country'     => $location_country,
    'subdivision' => $location_subdivision,
    'search'      => $search_query,
    'user_id'     => $user_id,
    'auction_id'  => $auction_id,
];
$empty_message = $empty_message_service->get_message( $query_type, $active_filters );
```

Output `esc_html( $empty_message )` inside the existing `<p class="aucteeno-query-loop__empty">` wrapper.

## Out of Scope

- **Editor preview** (`editor.js`): Stays as-is ("No auctions found. Showing placeholder.")
- **Client-side REST responses** (`view.js`): No changes — empty state on pagination/infinite scroll deferred to a future task
- **REST API controller**: No changes

## Files Changed

| File | Change |
|---|---|
| `includes/blocks/class-query-loop-empty-message.php` | **New** — service class |
| `includes/class-aucteeno.php` | Add container registration |
| `blocks/query-loop/render.php` | Replace hardcoded empty state with service call |

## Testing

- Unit test for `Query_Loop_Empty_Message::get_message()` with various filter combinations
- Unit test for filter hook (`aucteeno_query_loop_no_results`) — verify extensions can override the message
- Unit test for graceful fallback when user/post doesn't exist (omit fragment, don't error)
