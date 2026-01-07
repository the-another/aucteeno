<?php
/**
 * Example Usage of Container Pattern
 *
 * This file demonstrates how to use the Container and Hook_Manager
 * to refactor the existing Aucteeno classes.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno;

/**
 * EXAMPLE 1: Modified Meta_Fields_Auction with Hook Manager
 *
 * This shows how Meta_Fields_Auction would be refactored to use Hook_Manager.
 */
class Meta_Fields_Auction_Example {
	/**
	 * Hook manager instance.
	 *
	 * @var Hook_Manager
	 */
	private $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param Hook_Manager $hook_manager Hook manager instance.
	 */
	public function __construct( Hook_Manager $hook_manager ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Initialize meta fields and register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->hook_manager->register_action(
			'woocommerce_product_options_general_product_data',
			array( $this, 'add_meta_boxes' )
		);
		$this->hook_manager->register_action(
			'woocommerce_process_product_meta',
			array( $this, 'save_meta_fields' ),
			10,
			1
		);
	}

	/**
	 * Deregister all hooks registered by this class.
	 *
	 * @return void
	 */
	public function deinit(): void {
		$this->hook_manager->deregister(
			'woocommerce_product_options_general_product_data',
			array( $this, 'add_meta_boxes' )
		);
		$this->hook_manager->deregister(
			'woocommerce_process_product_meta',
			array( $this, 'save_meta_fields' ),
			10
		);
	}

	/**
	 * Add meta boxes for auction products.
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		// Implementation here...
	}

	/**
	 * Save meta fields.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_meta_fields( int $post_id ): void {
		// Implementation here...
	}
}

/**
 * EXAMPLE 2: Updated Aucteeno class using Container
 *
 * This shows how the main Aucteeno class would use the Container.
 */
class Aucteeno_Example {
	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Get the plugin instance.
	 *
	 * @return Aucteeno_Example Plugin instance.
	 */
	public static function get_instance(): Aucteeno_Example {
		// Singleton implementation...
	}

	/**
	 * Start the plugin initialization.
	 *
	 * @return void
	 */
	public function start(): void {
		$this->container = Container::get_instance();

		// Initialize database tables.
		Database\Database::maybe_migrate();

		// Register post statuses.
		$this->register_post_statuses();

		// Register taxonomies.
		$this->register_taxonomies();

		// Register product types.
		$this->register_product_types();

		// Register datastores.
		$this->register_datastores();

		// Initialize admin components using container.
		$this->register_admin_fields();

		// Register settings.
		$this->register_settings();

		// Register REST API.
		$this->register_rest_api();
	}

	/**
	 * Register admin fields using container.
	 *
	 * @return void
	 */
	private function register_admin_fields(): void {
		// Register services with lazy loading.
		$this->container->register(
			'meta_fields_auction',
			function ( Container $c ) {
				return new Admin\Custom_Fields_Auction( $c->get_hook_manager() );
			},
			true // Singleton
		);

		$this->container->register(
			'meta_fields_item',
			function ( Container $c ) {
				return new Admin\Custom_Fields_Item( $c->get_hook_manager() );
			},
			true // Singleton
		);

		// Initialize services (triggers lazy loading).
		$this->container->get( 'meta_fields_auction' )->init();
		$this->container->get( 'meta_fields_item' )->init();

		// Static classes can still use hook manager.
		$hook_manager = $this->container->get_hook_manager();
		Admin\Item_Parent_Relationship::init( $hook_manager );
		Admin\Product_Type_Filter::init( $hook_manager );
	}

	/**
	 * Get meta fields auction instance.
	 *
	 * @return Admin\Custom_Fields_Auction|null Meta fields auction instance.
	 */
	public function get_meta_fields_auction() {
		try {
			return $this->container->get( 'meta_fields_auction' );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Get meta fields item instance.
	 *
	 * @return Admin\Custom_Fields_Item|null Meta fields item instance.
	 */
	public function get_meta_fields_item() {
		try {
			return $this->container->get( 'meta_fields_item' );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Deregister all hooks (useful for testing or deactivation).
	 *
	 * @return void
	 */
	public function deregister_all_hooks(): void {
		$this->container->deregister_all_hooks();
	}
}

/**
 * EXAMPLE 3: Testing Scenario
 *
 * This shows how the container pattern makes testing easier.
 */
class Container_Testing_Example {
	/**
	 * Test that hooks can be deregistered.
	 *
	 * @return void
	 */
	public function test_hook_deregistration(): void {
		$container    = Container::get_instance();
		$hook_manager = $container->get_hook_manager();

		// Register a test hook.
		$hook_manager->register_action( 'test_hook', array( $this, 'test_callback' ) );

		// Verify hook is registered.
		$hooks = $hook_manager->get_registered_hooks();
		// Assert hook exists...

		// Deregister all hooks.
		$hook_manager->deregister_all();

		// Verify hook is removed.
		$hooks_after = $hook_manager->get_registered_hooks();
		// Assert hook is gone...
	}

	/**
	 * Test callback.
	 *
	 * @return void
	 */
	public function test_callback(): void {
		// Test implementation...
	}
}

/**
 * EXAMPLE 4: Static Class Migration
 *
 * For classes that use static methods, you can pass Hook_Manager.
 */
class Item_Parent_Relationship_Example {
	/**
	 * Hook manager instance.
	 *
	 * @var Hook_Manager|null
	 */
	private static $hook_manager = null;

	/**
	 * Initialize parent relationship handling.
	 *
	 * @param Hook_Manager $hook_manager Optional hook manager.
	 * @return void
	 */
	public static function init( ?Hook_Manager $hook_manager = null ): void {
		if ( null === $hook_manager ) {
			$container    = Container::get_instance();
			$hook_manager = $container->get_hook_manager();
		}

		self::$hook_manager = $hook_manager;

		// Sync when post is saved.
		$hook_manager->register_action(
			'save_post',
			array( __CLASS__, 'sync_parent_relationship' ),
			10,
			2
		);

		// Validate parent on save.
		$hook_manager->register_action(
			'save_post',
			array( __CLASS__, 'validate_parent_required' ),
			5,
			2
		);
	}

	/**
	 * Deregister hooks.
	 *
	 * @return void
	 */
	public static function deinit(): void {
		if ( null === self::$hook_manager ) {
			return;
		}

		self::$hook_manager->deregister(
			'save_post',
			array( __CLASS__, 'sync_parent_relationship' ),
			10
		);
		self::$hook_manager->deregister(
			'save_post',
			array( __CLASS__, 'validate_parent_required' ),
			5
		);
	}

	/**
	 * Sync parent relationship.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function sync_parent_relationship( int $post_id, $post ): void {
		// Implementation...
	}

	/**
	 * Validate parent required.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function validate_parent_required( int $post_id, $post ): void {
		// Implementation...
	}
}
