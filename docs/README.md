# Container Pattern Implementation

This directory contains a container pattern implementation for the Aucteeno plugin, following WooCommerce-style patterns.

## Overview

The container pattern provides:
- **Lazy Loading**: Services are only instantiated when accessed
- **Hook Management**: Centralized tracking and deregistration of WordPress hooks
- **Dependency Injection**: Clean dependency management
- **Testability**: Easy to mock and test components

## Files

- `class-container.php` - Main dependency injection container
- `class-hook-manager.php` - WordPress hook registration/deregistration manager
- `EXPLORATION.md` - Detailed exploration of different container patterns
- `EXAMPLE_USAGE.php` - Code examples showing how to use the container
- `README.md` - This file

## Quick Start

### Basic Usage

```php
use The_Another\Plugin\Aucteeno\Container;
use The_Another\Plugin\Aucteeno\Hook_Manager;

// Get container instance
$container = Container::get_instance();

// Register a service (lazy loading)
$container->register(
    'my_service',
    function( Container $c ) {
        return new My_Service( $c->get_hook_manager() );
    },
    true // singleton
);

// Get service (triggers instantiation)
$service = $container->get( 'my_service' );
$service->init();
```

### Using Hook Manager

```php
// In your service class
class My_Service {
    private $hook_manager;

    public function __construct( Hook_Manager $hook_manager ) {
        $this->hook_manager = $hook_manager;
    }

    public function init(): void {
        $this->hook_manager->register_action(
            'some_action',
            [ $this, 'callback' ]
        );
    }

    public function deinit(): void {
        $this->hook_manager->deregister(
            'some_action',
            [ $this, 'callback' ]
        );
    }
}
```

## Migration Guide

### Step 1: Update Service Classes

Change from:
```php
class Meta_Fields_Auction {
    public function init(): void {
        add_action( 'hook', [ $this, 'callback' ] );
    }
}
```

To:
```php
class Meta_Fields_Auction {
    private $hook_manager;

    public function __construct( Hook_Manager $hook_manager ) {
        $this->hook_manager = $hook_manager;
    }

    public function init(): void {
        $this->hook_manager->register_action( 'hook', [ $this, 'callback' ] );
    }

    public function deinit(): void {
        $this->hook_manager->deregister( 'hook', [ $this, 'callback' ] );
    }
}
```

### Step 2: Update Main Plugin Class

Change from:
```php
private function register_admin_fields() {
    $this->meta_fields_auction = new Admin\Meta_Fields_Auction();
    $this->meta_fields_auction->init();
}
```

To:
```php
private function register_admin_fields() {
    $container = Container::get_instance();
    
    $container->register(
        'meta_fields_auction',
        function( Container $c ) {
            return new Admin\Meta_Fields_Auction( $c->get_hook_manager() );
        },
        true
    );
    
    $container->get( 'meta_fields_auction' )->init();
}
```

## Benefits

1. **Lazy Loading**: Services only created when needed
2. **Hook Deregistration**: Can remove hooks for testing or deactivation
3. **Better Testing**: Easy to mock dependencies
4. **WooCommerce-like**: Follows established patterns
5. **Maintainability**: Clear separation of concerns

## See Also

- `EXPLORATION.md` - Detailed comparison of container patterns
- `EXAMPLE_USAGE.php` - Complete code examples

