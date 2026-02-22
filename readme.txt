=== Aucteeno ===
Contributors: theanother
Tags: auction, woocommerce, auction management, bidding, lots
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.3
Stable tag: 1.0.5
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
