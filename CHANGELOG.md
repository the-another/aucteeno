# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

