=== Aucteeno ===
Contributors: theanother
Tags: auction, woocommerce, auction management, bidding, lots
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.3
Stable tag: 1.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Transform WooCommerce into a powerful auction management system with high-performance database tables, REST API, and Gutenberg blocks.

== Description ==

Aucteeno extends WooCommerce to provide comprehensive auction and item (lot) management functionality. It transforms your WooCommerce store into a powerful auction management system with high-performance database optimization, a REST API, and beautiful Gutenberg blocks for displaying auctions and items.

= Key Features =

* **Custom Product Types** - Two specialized product types for auctions and items (lots)
* **High-Performance Storage** - Dedicated database tables for lightning-fast queries
* **REST API** - Full-featured REST API for auction and item management
* **Gutenberg Blocks** - Beautiful, customizable blocks for displaying auctions and items
* **Location Management** - Hierarchical location taxonomy (country → subdivision → city)
* **Bidding Status Tracking** - Automatic status management (publish, scheduled, expired)
* **Parent-Child Relationships** - Items inherit properties from parent auctions
* **Custom Permalinks** - Clean URL structure for auctions and items
* **WooCommerce Integration** - Seamless integration with WooCommerce admin interface

= Custom Product Types =

**Auction Products**
* Bidding start and end times
* Location information
* Terms and conditions
* Custom auction types

**Item/Lot Products**
* Lot number management with smart sorting
* Parent auction relationships
* Inherited bidding status and location
* Item-specific bidding data

= High-Performance Database =

Aucteeno uses dedicated database tables to store denormalized auction and item data, enabling:
* Fast queries without complex joins
* Efficient filtering by status, dates, and locations
* Optimized indexes for common query patterns
* Automatic synchronization with WooCommerce data

= Gutenberg Blocks =

**Available Blocks:**
* Query Loop - Display lists of auctions or items with pagination
* Card - Container for individual auction/item display
* Field Blocks - Individual data fields (title, image, countdown, location, current bid, reserve price, lot number, bidding status)
* Pagination - Navigation controls for query results

= REST API =

Access your auction data programmatically with the REST API:

**Endpoints:**
* `GET/POST/PUT/DELETE /wp-json/aucteeno/v1/auctions`
* `GET/POST/PUT/DELETE /wp-json/aucteeno/v1/items`
* `GET /wp-json/aucteeno/v1/locations`

= Requirements =

* WordPress 6.9 or higher
* WooCommerce 10.4.3 or higher (REQUIRED)
* PHP 8.3 or higher

== Installation ==

= Minimum Requirements =

* WordPress 6.9 or greater
* WooCommerce 10.4.3 or greater
* PHP version 8.3 or greater
* MySQL version 5.7 or greater OR MariaDB version 10.3 or greater

= Automatic Installation =

1. Log in to your WordPress dashboard
2. Navigate to Plugins > Add New
3. Search for "Aucteeno"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin zip file
2. Log in to your WordPress dashboard
3. Navigate to Plugins > Add New > Upload Plugin
4. Choose the zip file and click "Install Now"
5. Activate the plugin through the 'Plugins' menu in WordPress

= After Activation =

1. Ensure WooCommerce is installed and activated
2. Navigate to WooCommerce > Settings > Aucteeno to configure plugin settings
3. Visit Settings > Permalinks to flush rewrite rules (important for custom URLs)
4. Start creating auctions by going to Products > Add New and selecting "Auction" as the product type

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes, Aucteeno is built as a WooCommerce extension and requires WooCommerce 10.4.3 or higher to be installed and activated.

= Can I use this plugin without WooCommerce? =

No, the plugin is entirely WooCommerce-based and will not function without WooCommerce.

= What is the difference between an Auction and an Item? =

An Auction is the parent product type that represents an auction event with bidding times and location. Items (or Lots) are individual products within an auction. Items inherit properties like location and bidding status from their parent auction.

= How do I create an auction? =

1. Go to Products > Add New
2. Enter your auction title and description
3. In the Product Data panel, select "Auction" from the dropdown
4. Fill in the auction-specific fields (bidding times, location, terms)
5. Publish the auction

= How do I add items to an auction? =

1. First, create an auction as described above
2. Go to Products > Add New
3. Enter your item title and description
4. In the Product Data panel, select "Item" from the dropdown
5. In the Aucteeno tab, select the parent auction and enter the lot number
6. Publish the item

= Can items have different bidding times than their parent auction? =

Yes, items can override their parent auction's bidding times if needed. If not specified, items inherit the parent auction's bidding times and status.

= How do I display auctions on my site? =

Use the Gutenberg blocks provided by the plugin:
1. Create or edit a page
2. Add the "Auction Query Loop" block
3. Configure the query settings to filter auctions
4. Add "Card" blocks and field blocks to customize the display
5. Publish the page

= Does the plugin support multiple locations? =

Yes, the plugin includes a hierarchical location taxonomy with support for countries, subdivisions (states/provinces), and cities.

= Is there a REST API? =

Yes, the plugin provides a full REST API at `/wp-json/aucteeno/v1/` for programmatic access to auctions and items.

== Screenshots ==

1. Auction product edit screen with custom fields
2. Item product edit screen with parent auction selection
3. Gutenberg blocks for displaying auctions
4. Location taxonomy management
5. Plugin settings page

== Changelog ==














= 1.8.0 - 2026-07-22 =
* Add: Salebill accordion panels — three server-rendered blocks (`aucteeno/salebill-description`, `aucteeno/salebill-directions`, `aucteeno/salebill-notes`, inserter-hidden) for the WooCommerce Product Details accordion on single auction pages; each returns empty when its panel has nothing to show so WooCommerce removes the whole accordion item natively
* Add: Panel visibility rules (`Salebill_Tab_Visibility`) — Directions/Notes show when their plain-text fields are non-empty; Description shows only when its content is non-empty and either another panel is visible or the page-top excerpt does not already cover the full content (typographic-character folding survives `wptexturize`; media guard included)
* Add: Notes panel renders Auction Notice and Bidding Notice with `h4` sub-headings only when both are present
* Add: `Salebill_Accordion_Open_State` service — opens the first accordion item when none is open (via `WP_HTML_Tag_Processor` on the item's `data-wp-context`) and empties `woocommerce_product_tabs` at priority 999 on auction pages so third-party tabs (Dokan etc.) are not injected, preserving Product JSON-LD structured data
* Chore: Per-request memoization of the auction-page lookup and panel context resolution; all logic is auction-gated so item and other product pages are unaffected
* Chore: Refresh dev tooling locks (WordPress core stub 6.9.5, WooCommerce/WordPress stubs, WPCS) — no runtime dependency changes

= 1.7.3 - 2026-07-15 =
* Fix: Search count query no longer takes tens of seconds on large sites — the count now reads the auction items table directly instead of joining posts.
* Fix: Search count cache stays warm during mass imports; freshness is governed by its 5-minute TTL instead of flushing on every item save.
* Chore: Update npm build toolchain (@wordpress/scripts 28 → 33) and pin transitive dependencies, resolving all GitHub Dependabot security alerts.

= 1.7.2 - 2026-07-02 =
* New: Search modal has a search (magnifier) button — clicking it or pressing Enter opens the configured "View all" results page for the active type.
* New: Arrow keys (up/down) move focus through search results; Enter opens the focused result.
* Improve: Auctions/Items selector restyled as a single segmented control.
* Improve: Accessible focus ring on the search submit button.

= 1.7.1 - 2026-05-01 =
* Fix: Search-block placeholder count now matches what the live search returns. `Search_Count_Provider` previously filtered by `bidding_status IN (10, 20)`, which drifted from the timestamp-based filter the search itself uses (`Database_Items::status_clauses`, `Query_Orderer`) and inflated the count by ~100k on production. Now joins `wp_aucteeno_items` to published posts and counts items whose `bidding_ends_at` is in the future.

= 1.7.0 - 2026-05-01 =
* Add: Aucteeno Search block — WP-style trigger that opens a Jetpack-inspired modal with debounced REST autosearch (Auctions / Items toggle), result rows (thumbnail + title + "City, State, CT" location + bold countdown), recent-searches sidebar, and "View all" link to the configured query-loop page.
* Add: Search_Count_Provider — cached running+upcoming items count for the trigger placeholder ("N items to search from").
* Add: Search_Block_Service — parses the configured "view all" page's aucteeno/query-loop block to inherit perPage/orderBy and invalidates transients on edits.
* Add: REST format=search_row projection on /auctions and /items, routed through the HPS query path so search and image_url honor the listing query.
* Add: query-loop reads ?keyword= (search-block "view all") in addition to ?s= and the WP query var.
* Add: Last-search chip — stores the term + type and pre-fills on next modal open via storage events.
* Refactor: style.css → style.scss, mobile-first using the globalag-wp-theme $break-* breakpoints; theme-tinted via --ats-* custom properties chained through --wp--preset--color--* slugs (accent-4, white, foreground).
* Add: Mobile sheet offsets the WP admin bar via --wp-admin--admin-bar--height; min-height 90dvh on mobile only.
* Add: Search input/buttons use --wp--preset--font-size--small; trigger fixed at 3rem tall, 15rem wide on tablet+, vertically centered placeholder.
* Fix: Placeholder count truncated "5,289" to 5 because sprintf %d parsed up to the comma — now uses str_replace.
* Fix: project_search_row resolves images at "thumbnail" size (was "medium") for the small modal previews.
* Fix: View-all link clears href when hidden so a stale URL can't fire; dynamic label per type ("View all auctions" / "View all items").
* Fix: Modal init handles already-loaded module scripts (DOMContentLoaded already fired); close→trigger.focus no longer reopens the modal via _returningFocus guard.
* Fix: Reopening the modal now re-reads the stored chip on every open instead of consuming it once.

= 1.6.0 - 2026-04-29 =
* Add: `aucteeno/product-details` context block — fetches item data once on single auction/item pages and injects context into inner blocks, eliminating per-block DB queries
* Add: `aucteeno/field-starts-at` and `aucteeno/field-ends-at` blocks — SSR initial value in WordPress timezone, progressively hydrated to visitor's local browser timezone via `Intl.DateTimeFormat`
* Add: configurable state-aware labels for field-starts-at / field-ends-at (upcoming, running, expired)
* Add: `blocks/shared/src/datetime-utils.js` shared formatter (`formatDatetime`, `translateCustomFormat`)
* Add: `align` (wide/full), layout, and full paragraph-equivalent block supports on datetime and location blocks
* Add: dl/dt/dd structure with orientation control for field-location and datetime blocks
* Add: configurable infinite-scroll trigger offset on `aucteeno/query-loop`
* Add: target-date options and customizable labels to `aucteeno/field-countdown` based on bidding status
* Add: timestamp-derived status modifier class to `aucteeno/product-details`
* Refactor: rename product-cta classes to Gutenberg/BEM convention; route bidding through PHP renderer with filter; drop wishlist/loading code, view.js, and webpack entry
* Refactor: drop custom `widthMode`/`fixedWidth` in favour of native Gutenberg layout
* Perf: request-level static cache in `Block_Data_Helper::get_item_data()` with null-miss caching
* Fix: map align* classes to text-align for datetime and location blocks
* Fix: pass is-orientation class to useBlockProps so editor preview reflects orientation
* Fix: use nullish coalescing for label fallbacks so empty string labels are honored
* Style: apply prettier and stylelint formatting fixes across blocks

= 1.5.0 - 2026-04-23 =
* Behavior change: query-loop block and REST endpoints now exclude expired listings by default. Pass `include_expired=1` to opt in.
* New block attribute `includeExpired` (default false) on `aucteeno/query-loop`.
* New REST parameter `include_expired` on /auctions and /items endpoints.
* Operators with archive/results pages must toggle "Include expired listings" ON in the block inspector.

= 1.4.3 - 2026-04-17 =
* Fix: Restore post_type_link filter for query loop permalinks — geo-based URLs now render correctly
* Fix: Use local vendor/bin paths in composer scripts to resolve missing PHPCS standards
* Fix: Update Blocks_Dokan namespace to Blocks_For_Dokan for Context_Detector compatibility
* Refactor: Remove blocks plugin dependency for vendor ID detection, use tanbfd_get_vendor_id()
* Perf: Optimize bidding status count queries and refactor DB classes to instance methods
* Chore: Fix PHPCS alignment violations and re-add lockfile fsevents entry

= 1.4.2 - 2026-04-14 =
* Version bump

= 1.4.1 - 2026-04-14 =
* Optimized bidding status count queries — split OR-based queries into per-status queries leveraging composite indexes
* Dropped wp_posts JOIN for expired counts (settled items) and added wp_cache with 60s TTL
* Converted Database_Items and Database_Auctions from static to instance methods, registered as Container singletons
* Added aucteeno_query_auctions() and aucteeno_query_items() global API functions for cross-plugin/theme use

= 1.4.0 - 2026-04-12 =
* New "Ending Soon" default sort for query loop — combines running and upcoming auctions/items into one group ordered by bidding end date, with expired items following
* Renamed previous "Ending Soon" sort to "By Status & Ending" — groups by bidding status (running, upcoming, expired) with per-status date ordering
* Query_Orderer now supports sort-aware ordering via aucteeno_sort query variable, with separate cache keys per sort mode

= 1.3.0 - 2026-04-12 =
* Enhanced query loop empty state message to include active filter details (search term, location, seller, parent auction) instead of a generic "No auctions/items found."
* Added aucteeno_query_loop_no_results filter hook for extensions to customize the empty state message — receives both raw filter values and pre-resolved display labels
* New Query_Loop_Empty_Message container-managed service for composing filter-aware no-results messages
* Fixed stale location variables leaking into empty message when block is in product-IDs mode

= 1.2.5 - 2026-04-11 =
* Fixed Status_Reconciler calling as_has_scheduled_action() / as_schedule_recurring_action() before Action Scheduler's data store is initialized — these ran during before_woocommerce_init (WP init priority 0) while AS initializes at init priority 1, producing _doing_it_wrong notices on every request
* Tightened the init() late-load guard from did_action('init') to did_action('init') && ! doing_action('init') so the direct call only fires when init has fully completed; the deferred priority-20 callback continues to handle the normal case
* Bumped npm transitive dependencies via npm audit fix (non-force), clearing both critical advisories and most high-severity advisories without changing any direct dependency versions

= 1.2.4 - 2026-04-11 =
* Added Query_Loop_Location_Filter helper class exposing the new aucteeno_query_loop_location filter
* Extension plugins (such as aucteeno-geo-tagging) can hook the filter to override the resolved country and subdivision before they are used to query the HPS tables
* Filter is invoked from the Query Loop block render template inside the ! $has_product_ids guard so explicit product ID queries remain untouched

= 1.2.2 - 2026-04-10 =
* Added "Lot Number" sort option to the Query Loop block when query type is "Items"
* Items sorted by lot number use lot_sort_key ASC from the aucteeno_items HPS table for correct alphanumeric ordering
* REST API sort parameter now accepts lot_number value for both auctions and items endpoints

= 1.2.1 - 2026-04-10 =
* Version bump

= 1.2.1 - 2026-04-10 =
* Added aucteeno_product_context_data filter (per-product) and aucteeno_products_context_data filter (batch, fires once per query) as extension points for enriching block context data
* aucteeno_products_context_data receives the full items array and all post IDs together, enabling a single batch query instead of N per-item lookups
* Added aucteeno_field_image_html filter on the field-image block, allowing plugins to supply fully custom image HTML with a priority chain: filter HTML → attachment image → image_url fallback → placeholder
* field-image block now reads image_url from block context; filters let external plugins inject Nexus CDN or other custom image sources without coupling to the core plugin

= 1.2.0 - 2026-04-10 =
* Performance: replaced per-item wc_get_product() calls in query loop with batch WordPress object cache priming, reducing queries from ~152 to 5–8 per page for 25-item listings
* Added Eager_Loader helper class with batch post meta priming, attachment meta priming, and location taxonomy term loading
* Database_Auctions::query_for_listing() now returns image_id, location_country_term_id, and location_subdivision_term_id fields; permalink built from SQL slug without get_permalink() per item
* Database_Items::query_for_listing() now returns the same new fields; current_bid reads from status-specific meta key (_aucteeno_current_bid / _aucteeno_asking_bid / _aucteeno_sold_price); permalink built from auction and item slugs
* Block_Data_Helper::get_item_data() no longer calls wc_get_product() a second time for image loading; image_id field added to return data
* field-image block reads image_id from block context instead of calling wc_get_product() per item
* field-location block reads pre-loaded term IDs from block context when available, falling back to per-item get_terms() only on single-post pages

= 1.1.0 - 2026-04-10 =
* Added automated bidding status reconciler that runs every 5 minutes via Action Scheduler
* Reconciler detects stale bidding_status values in HPS tables and corrects them based on timestamps
* Supports forward-only transitions: upcoming → running → expired
* Processes up to 500 rows per batch, up to 50 batches per run (auctions first, then items)
* Auction status changes update both the auction-bidding-status taxonomy and the HPS table atomically
* Item status changes update the HPS table only (items do not hold taxonomy terms directly)
* Reconciler is automatically scheduled on activation and unscheduled on deactivation

= 1.0.8 - 2026-03-01 =
* Adds global functions for cross-plugin use

= 1.0.7 - 2026-02-22 =
* Updates Namespaces and class names to Aucteeno for better clarity and consistency

= 1.0.6 - 2026-02-22 =
* Adds specific Git Updater headers

= 1.0.5 - 2026-02-22 =
* Added JavaScript test suite for all 11 Gutenberg blocks (104 tests)
* Extracted countdown utility functions into reusable countdown-utils module
* Extracted location utility functions into reusable location-utils module
* Added @testing-library/react and @testing-library/jest-dom dev dependencies

= 1.0.4 - 2026-02-21 =
* Added product IDs filtering support to Query Loop block, REST API, and HPS database queries (e.g., for wishlists)
* When querying by product IDs, inherited filters (user, location, search, pagination) are now stripped so only the specified products are returned
* Product IDs queries now preserve the original ID order using FIELD() ordering
* Fixed vendor auto-detection to only trigger on actual Dokan store pages, preventing unintended user filtering on search results and shop pages
* Changed countdown block to use viewScriptModule for WordPress script modules API compatibility
* Fixed image retrieval in auctions, items, and block data helper to use WooCommerce product image method instead of post thumbnail, respecting image overrides
* Minor build script cleanup

= 1.0.3 - 2026-01-17 =
* Fixed template loading issues when loading new pages with ajax
* Improved location field generation and add option for `city, state, country` format
* Added option to generate links to location terms from location block
* Improvements of countdown block and fixed
* Some of the field blocks can now be used outside of card block
* Aucteeno Query Loop can now read data about parent ID if loaded on item page
* Added search context to Aucteeno Query Loop block
* Fixed issue where location context was not included in Aucteeno Query Loop block

= 1.0.2 - 2026-01-16 =
* Fix issue with missing blocks, templates and styles

= 1.0.1 - 2026-01-15 =
* Minor styles bug fixes

= 1.0.0 - 2026-01-15 =
* Initial release
* Custom product types for auctions and items
* High-performance database tables
* REST API endpoints
* Gutenberg blocks for auction display
* Location taxonomy
* Bidding status tracking
* Custom permalink structure
* WooCommerce admin integration

== Upgrade Notice ==

= 1.0.0 =
Initial release of Aucteeno. Requires WordPress 6.9+, WooCommerce 10.4.3+, and PHP 8.3+.

== Developer Information ==

= Architecture =

Aucteeno uses a dependency injection container pattern inspired by WooCommerce architecture for clean, testable code.

= Database Tables =

The plugin creates two custom tables:
* `{$wpdb->prefix}aucteeno_auctions` - Denormalized auction data
* `{$wpdb->prefix}aucteeno_items` - Denormalized item data

= Hooks and Filters =

Developers can extend Aucteeno using WordPress hooks and filters. Documentation is available in the plugin source code.

= REST API =

Full REST API documentation is available at `/wp-json/aucteeno/v1/` when the plugin is activated.

= GitHub =

Development happens on GitHub: https://github.com/theanother/aucteeno

= Support =

For support, feature requests, and bug reports, please visit: https://theanother.org/support/

== Privacy Policy ==

Aucteeno does not collect, store, or share any personal data. All auction and item data is stored locally in your WordPress database.
