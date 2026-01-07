# Current vs. Container Pattern Comparison

## Current Implementation

### Current Flow

```php
// In class-aucteeno.php
private function register_admin_fields() {
    // Immediate instantiation
    $this->meta_fields_auction = new Admin\Meta_Fields_Auction();
    $this->meta_fields_auction->init(); // Hooks registered immediately
    
    $this->meta_fields_item = new Admin\Meta_Fields_Item();
    $this->meta_fields_item->init(); // Hooks registered immediately
}
```

### Current Meta_Fields_Auction

```php
class Meta_Fields_Auction {
    public function init(): void {
        // Hooks registered directly - no tracking
        add_action( 'woocommerce_product_options_general_product_data', 
                   [ $this, 'add_meta_boxes' ] );
        add_action( 'woocommerce_process_product_meta', 
                   [ $this, 'save_meta_fields' ], 10, 1 );
    }
}
```

### Issues with Current Approach

1. ❌ **No lazy loading**: All classes instantiated immediately
2. ❌ **No hook tracking**: Can't deregister hooks
3. ❌ **Hard to test**: Hooks registered permanently
4. ❌ **No dependency injection**: Direct instantiation
5. ❌ **Tight coupling**: Classes directly instantiated

## Proposed Container Pattern

### Proposed Flow

```php
// In class-aucteeno.php
private function register_admin_fields() {
    $container = Container::get_instance();
    
    // Register factories (lazy - not instantiated yet)
    $container->register(
        'meta_fields_auction',
        function( Container $c ) {
            return new Admin\Meta_Fields_Auction( $c->get_hook_manager() );
        },
        true // singleton
    );
    
    // Only instantiate when accessed
    $container->get( 'meta_fields_auction' )->init();
}
```

### Proposed Meta_Fields_Auction

```php
class Meta_Fields_Auction {
    private $hook_manager;

    public function __construct( Hook_Manager $hook_manager ) {
        $this->hook_manager = $hook_manager;
    }

    public function init(): void {
        // Hooks tracked and can be deregistered
        $this->hook_manager->register_action(
            'woocommerce_product_options_general_product_data',
            [ $this, 'add_meta_boxes' ]
        );
        $this->hook_manager->register_action(
            'woocommerce_process_product_meta',
            [ $this, 'save_meta_fields' ],
            10,
            1
        );
    }

    public function deinit(): void {
        // Can deregister hooks when needed
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

### Benefits of Proposed Approach

1. ✅ **Lazy loading**: Services only created when accessed
2. ✅ **Hook tracking**: All hooks tracked and can be deregistered
3. ✅ **Easy testing**: Can deregister hooks in tests
4. ✅ **Dependency injection**: Clean dependency management
5. ✅ **Loose coupling**: Services accessed through container
6. ✅ **WooCommerce-like**: Follows established patterns

## Side-by-Side Comparison

| Feature | Current | Container Pattern |
|---------|---------|-------------------|
| Instantiation | Immediate | Lazy (on demand) |
| Hook Tracking | None | Full tracking |
| Hook Deregistration | Not possible | Fully supported |
| Testing | Difficult | Easy (can deregister) |
| Dependency Injection | None | Full support |
| Performance | All loaded | Loaded on demand |
| Code Organization | Scattered | Centralized |

## Migration Impact

### Low Impact Changes

- Add `Hook_Manager` parameter to constructors
- Replace `add_action`/`add_filter` with `hook_manager->register_*`
- Add `deinit()` methods for deregistration

### Medium Impact Changes

- Update `Aucteeno::register_admin_fields()` to use container
- Update getter methods to use container

### High Impact Changes

- None! The container pattern is additive and backward compatible

## Testing Example

### Current (Difficult)

```php
// Hooks are registered permanently - hard to test in isolation
public function test_meta_fields() {
    $meta_fields = new Meta_Fields_Auction();
    $meta_fields->init();
    // Hooks are now registered globally - affects other tests
}
```

### With Container (Easy)

```php
// Can deregister hooks after test
public function test_meta_fields() {
    $container = Container::get_instance();
    $meta_fields = $container->get( 'meta_fields_auction' );
    $meta_fields->init();
    
    // Test functionality...
    
    // Clean up
    $meta_fields->deinit();
    // Or deregister all
    $container->deregister_all_hooks();
}
```

## Performance Considerations

### Current
- All services instantiated on plugin load
- All hooks registered immediately
- Higher memory usage upfront

### Container Pattern
- Services instantiated only when accessed
- Hooks registered only when service initialized
- Lower memory usage (lazy loading)
- Better performance for unused features

## Recommendation

**Adopt the Container Pattern** because:

1. ✅ Follows WooCommerce patterns
2. ✅ Enables hook deregistration (your requirement)
3. ✅ Improves testability
4. ✅ Better performance (lazy loading)
5. ✅ Cleaner architecture
6. ✅ Easy migration path

## Next Steps

1. Review the implementation files
2. Test with one class (e.g., `Meta_Fields_Auction`)
3. Gradually migrate other classes
4. Add tests to verify hook deregistration works

