# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.4.1

### Changed

- Optimized bidding status count queries — split OR-based queries into per-status queries leveraging composite indexes (`idx_running_*`, `idx_upcoming_*`, `idx_expired_*`).
- Dropped `wp_posts` JOIN for expired counts (settled items) and added `wp_cache` with 60s TTL.
- Converted `Database_Items` and `Database_Auctions` from static to instance methods, registered as Container singletons.

### Added

- `aucteeno_query_auctions()` and `aucteeno_query_items()` global API functions for cross-plugin/theme use via `function_exists()`.

## 1.4.0

### Added

- New "Ending Soon" default sort for query loop — combines running and upcoming auctions/items into one group ordered by bidding end date, with expired items following.
- "By Status & Ending" sort option (`status_ending_soon`) — the previous default behavior, now available as an explicit choice. Groups by bidding status (running, upcoming, expired) with per-status date ordering.
- `query_combined_status_group` helper in `Database_Items` for querying multiple bidding statuses in a single SQL query.
- Sort-aware `Query_Orderer` — reads `aucteeno_sort` query variable to select between 2-group and 3-group UNION ALL patterns, with separate cache keys per sort mode.

## 1.3.0

### Added

- Filter-aware empty state message for the query loop block — when no results are found with active filters (search, location, seller, parent auction), the message now includes those filter details instead of a generic "No auctions/items found."
- `aucteeno_query_loop_no_results` filter hook for extensions to customize the empty state message. Receives both raw filter values (codes, IDs) and pre-resolved display labels.
- `Query_Loop_Empty_Message` container-managed service class for composing the no-results message.

### Fixed

- Stale location variables leaking into the empty state message when the query loop block is in product-IDs mode.

## 1.2.3

### Added

- New `Query_Loop_Location_Filter` helper class exposing the `aucteeno_query_loop_location` filter. Extension plugins can hook the filter to override the resolved country and subdivision before they are used to query the HPS tables. The helper is called from `blocks/query-loop/render.php` inside a `! $has_product_ids` guard. Consumed by the `aucteeno-geo-tagging` extension.

## [1.0.0] - 2025-12-30

### Added
- Initial release
- Custom WooCommerce product types: Auction and Item
- Custom database tables for auctions and items with high-performance indexing
- Service and repository architecture with WPDB and HPO implementations
- REST API endpoints for auctions and items (GET, POST, PUT, DELETE)
- Custom post statuses: schedule and expire
- Custom taxonomies: auction-type, country, and subdivision
- Admin UI for managing auction and item products
- Plugin settings page with general terms and conditions
- Parent-child relationship between items and auctions
- Dynamic price display for items based on auction status
- Database migration system using WordPress dbDelta
- Docker-based development environment
- Testing infrastructure with PHPUnit, BrainMonkey, and Mockery
- Linting with PHPCS (WordPress and VIP standards)

