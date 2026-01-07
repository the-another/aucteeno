# Container Pattern Exploration for Aucteeno

This document explores different container patterns that would allow:
1. Lazy registration of classes (only instantiate when needed)
2. Hook registration/deregistration capabilities
3. Following WooCommerce-style patterns

## Current State

Currently, the `Aucteeno` class acts as a simple singleton container that:
- Instantiates all classes immediately in `start()`
- Calls `init()` methods that register hooks permanently
- No way to deregister hooks once registered

## Option 1: Simple Service Container with Hook Tracking

This is the most lightweight approach, similar to WooCommerce's early container patterns.

### Features:
- Lazy instantiation (only create when accessed)
- Hook tracking for deregistration
- Simple getter/setter interface

### Implementation:

```php
namespace TheAnother\Plugin\Aucteeno;

class Container {
    private static $instance = null;
    private $services = [];
    private $hooks = []; // Track registered hooks

    public static function get_instance(): Container {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get( string $key ) {
        if ( ! isset( $this->services[ $key ] ) ) {
            $this->services[ $key ] = $this->create( $key );
        }
        return $this->services[ $key ];
    }

    public function has( string $key ): bool {
        return isset( $this->services[ $key ] );
    }

    public function register( string $key, callable $factory ): void {
        $this->services[ $key ] = $factory;
    }

    private function create( string $key ) {
        // Factory pattern for lazy instantiation
        if ( isset( $this->services[ $key ] ) && is_callable( $this->services[ $key ] ) ) {
            return call_user_func( $this->services[ $key ] );
        }
        throw new \Exception( "Service {$key} not found" );
    }

    public function track_hook( string $hook, string $callback, int $priority = 10 ): void {
        $this->hooks[] = [
            'hook' => $hook,
            'callback' => $callback,
            'priority' => $priority,
        ];
    }

    public function deregister_all_hooks(): void {
        foreach ( $this->hooks as $hook_data ) {
            remove_action( $hook_data['hook'], $hook_data['callback'], $hook_data['priority'] );
        }
        $this->hooks = [];
    }
}
```

### Usage:

```php
// In class-aucteeno.php
private function register_admin_fields() {
    $container = Container::get_instance();
    
    // Register factories (lazy)
    $container->register( 'meta_fields_auction', function() {
        return new Admin\Meta_Fields_Auction();
    });
    
    $container->register( 'meta_fields_item', function() {
        return new Admin\Meta_Fields_Item();
    });
    
    // Initialize when needed
    $container->get( 'meta_fields_auction' )->init();
    $container->get( 'meta_fields_item' )->init();
}
```

## Option 2: Service Container with Hook Manager

More sophisticated approach with dedicated hook management.

### Features:
- Separate hook manager
- Per-service hook tracking
- Can deregister individual services

### Implementation:

```php
namespace TheAnother\Plugin\Aucteeno;

class Hook_Manager {
    private $registered_hooks = [];

    public function register( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
        add_action( $hook, $callback, $priority, $accepted_args );
        
        $this->registered_hooks[] = [
            'hook' => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        ];
    }

    public function deregister( string $hook, callable $callback, int $priority = 10 ): bool {
        $removed = remove_action( $hook, $callback, $priority );
        
        if ( $removed ) {
            $this->registered_hooks = array_filter(
                $this->registered_hooks,
                function( $hook_data ) use ( $hook, $callback, $priority ) {
                    return ! (
                        $hook_data['hook'] === $hook &&
                        $hook_data['callback'] === $callback &&
                        $hook_data['priority'] === $priority
                    );
                }
            );
        }
        
        return $removed;
    }

    public function deregister_all(): void {
        foreach ( $this->registered_hooks as $hook_data ) {
            remove_action(
                $hook_data['hook'],
                $hook_data['callback'],
                $hook_data['priority']
            );
        }
        $this->registered_hooks = [];
    }

    public function get_registered_hooks(): array {
        return $this->registered_hooks;
    }
}

class Container {
    private static $instance = null;
    private $services = [];
    private $hook_manager;

    private function __construct() {
        $this->hook_manager = new Hook_Manager();
    }

    public static function get_instance(): Container {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get( string $key ) {
        if ( ! isset( $this->services[ $key ] ) ) {
            $this->services[ $key ] = $this->create( $key );
        }
        return $this->services[ $key ];
    }

    public function register( string $key, callable $factory ): void {
        $this->services[ $key ] = $factory;
    }

    public function get_hook_manager(): Hook_Manager {
        return $this->hook_manager;
    }

    private function create( string $key ) {
        if ( isset( $this->services[ $key ] ) && is_callable( $this->services[ $key ] ) ) {
            return call_user_func( $this->services[ $key ], $this );
        }
        throw new \Exception( "Service {$key} not found" );
    }
}
```

### Modified Meta_Fields_Auction:

```php
class Meta_Fields_Auction {
    private $hook_manager;

    public function __construct( Hook_Manager $hook_manager ) {
        $this->hook_manager = $hook_manager;
    }

    public function init(): void {
        $this->hook_manager->register(
            'woocommerce_product_options_general_product_data',
            [ $this, 'add_meta_boxes' ]
        );
        $this->hook_manager->register(
            'woocommerce_process_product_meta',
            [ $this, 'save_meta_fields' ],
            10,
            1
        );
    }

    public function deinit(): void {
        $this->hook_manager->deregister(
            'woocommerce_product_options_general_product_data',
            [ $this, 'add_meta_boxes' ]
        );
        $this->hook_manager->deregister(
            'woocommerce_process_product_meta',
            [ $this, 'save_meta_fields' ],
            10
        );
    }
}
```

## Option 3: PSR-11 Compliant Container (WooCommerce Style)

WooCommerce uses a PSR-11 compliant container. This is the most professional approach.

### Features:
- PSR-11 interface compliance
- Service definitions
- Dependency injection
- Hook lifecycle management

### Implementation:

```php
namespace TheAnother\Plugin\Aucteeno;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;

class Container_Exception extends \Exception implements ContainerExceptionInterface {}
class Service_Not_Found_Exception extends \Exception implements NotFoundExceptionInterface {}

class Container implements ContainerInterface {
    private static $instance = null;
    private $services = [];
    private $factories = [];
    private $singletons = [];
    private $hook_manager;

    private function __construct() {
        $this->hook_manager = new Hook_Manager();
    }

    public static function get_instance(): Container {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get( string $id ) {
        if ( $this->has( $id ) ) {
            // Return singleton if already instantiated
            if ( isset( $this->singletons[ $id ] ) ) {
                return $this->singletons[ $id ];
            }

            // Create from factory
            if ( isset( $this->factories[ $id ] ) ) {
                $instance = call_user_func( $this->factories[ $id ], $this );
                
                // Store as singleton if marked
                if ( isset( $this->services[ $id ]['singleton'] ) && $this->services[ $id ]['singleton'] ) {
                    $this->singletons[ $id ] = $instance;
                }
                
                return $instance;
            }

            // Direct service
            return $this->services[ $id ];
        }

        throw new Service_Not_Found_Exception( "Service {$id} not found" );
    }

    public function has( string $id ): bool {
        return isset( $this->services[ $id ] ) || isset( $this->factories[ $id ] );
    }

    public function register( string $id, callable $factory, bool $singleton = true ): void {
        $this->factories[ $id ] = $factory;
        $this->services[ $id ] = [ 'singleton' => $singleton ];
    }

    public function get_hook_manager(): Hook_Manager {
        return $this->hook_manager;
    }
}
```

## Option 4: Service Provider Pattern (Most Flexible)

This pattern separates service registration from the container itself.

### Features:
- Service providers for modular registration
- Lazy loading
- Easy to extend
- Clean separation of concerns

### Implementation:

```php
namespace TheAnother\Plugin\Aucteeno;

interface Service_Provider_Interface {
    public function register( Container $container ): void;
    public function boot( Container $container ): void;
}

class Admin_Service_Provider implements Service_Provider_Interface {
    public function register( Container $container ): void {
        $container->register( 'meta_fields_auction', function( Container $c ) {
            return new Admin\Custom_Fields_Auction( $c->get_hook_manager() );
        }, true );

        $container->register( 'meta_fields_item', function( Container $c ) {
            return new Admin\Custom_Fields_Item( $c->get_hook_manager() );
        }, true );
    }

    public function boot( Container $container ): void {
        // Initialize services
        $container->get( 'meta_fields_auction' )->init();
        $container->get( 'meta_fields_item' )->init();
    }
}

// In class-aucteeno.php
private function register_service_providers(): void {
    $container = Container::get_instance();
    
    $providers = [
        new Admin_Service_Provider(),
        // Add more providers as needed
    ];

    foreach ( $providers as $provider ) {
        $provider->register( $container );
    }

    foreach ( $providers as $provider ) {
        $provider->boot( $container );
    }
}
```

## Recommendation

For Aucteeno, I recommend **Option 2 (Service Container with Hook Manager)** because:

1. **Balance**: Not too simple, not too complex
2. **Hook Management**: Explicit tracking and deregistration
3. **Lazy Loading**: Services only created when accessed
4. **Testability**: Easy to mock and test
5. **WooCommerce-like**: Follows similar patterns to WooCommerce

### Migration Path:

1. Create `Container` and `Hook_Manager` classes
2. Update `Meta_Fields_Auction` and `Meta_Fields_Item` to accept `Hook_Manager`
3. Update `Aucteeno::register_admin_fields()` to use container
4. Add `deinit()` methods to classes for deregistration
5. Gradually migrate other components

### Benefits:

- Can deregister hooks when needed (e.g., during testing, plugin deactivation)
- Lazy loading improves performance
- Better testability
- Clear separation of concerns
- Easy to extend

