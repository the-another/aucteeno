<?php
/**
 * Aucteeno Main Class
 *
 * Main plugin class that acts as a singleton container for dependencies.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno;

use Exception;

/**
 * Class Aucteeno
 *
 * Main plugin class with singleton pattern for dependency access.
 */
class Aucteeno {

	/**
	 * Plugin instance.
	 *
	 * @var Aucteeno|null
	 */
	private static ?Aucteeno $instance = null;

	/**
	 * Container instance.
	 *
	 * @var Container|null
	 */
	private ?Container $container = null;

	/**
	 * Get the plugin instance.
	 *
	 * @return Aucteeno Plugin instance.
	 */
	public static function get_instance(): Aucteeno {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		// Prevent direct instantiation.
	}

	/**
	 * Start the plugin initialization.
	 *
	 * @throws Exception
	 *
	 * @since 1.0.0
	 */
	public function start(): void
    {
		// Initialize container.
		$this->container = Container::get_instance();

		// Initialize database tables.
		Database\Database::maybe_migrate();

		// Register taxonomies on init hook to ensure rewrite object is available.
		add_action( 'init', array( $this, 'register_taxonomies' ), 10 );

		// Register Gutenberg blocks on init hook.
		add_action( 'init', array( $this, 'register_blocks' ), 10 );

		// Register product types.
		$this->register_product_types();

		// Register datastores.
		$this->register_datastores();

		// Initialize admin components.
		$this->register_admin_fields();
		$this->register_settings();

		// Initialize permalinks.
		$this->register_permalinks();

		// Initialize breadcrumbs.
		$this->register_breadcrumbs();

		// Register REST API on rest_api_init hook to ensure REST infrastructure is available.
		add_action( 'rest_api_init', array( $this, 'register_rest_api' ) );

		// Initialize query orderer for custom ordering.
		$this->register_query_orderer();
	}

	/**
	 * Register taxonomies.
	 *
	 * @since 1.0.0
	 */
	public function register_taxonomies(): void
    {
		Taxonomies\Taxonomy_Auction_Type::register();
		Taxonomies\Taxonomy_Location::register();
		Taxonomies\Taxonomy_Auction_Bidding_Status::register();
	}

	/**
	 * Register Gutenberg blocks.
	 *
	 * @since 1.0.0
	 */
	public function register_blocks(): void
	{
		Blocks::register();
	}

    /**
     * Register product types.
     *
     * @throws Exception
     *
     * @since 1.0.0
     */
	private function register_product_types(): void
    {

		// Register Product_Type_Register_Auction with lazy loading.
		$this->container->register(
			'product_type_register_auction',
			function ( Container $c ) {
				return new Product_Types\Product_Type_Register_Auction( $c->get_hook_manager() );
			},
			true // Singleton.
		);

		// Register Product_Type_Register_Item with lazy loading.
		$this->container->register(
			'product_type_register_item',
			function ( Container $c ) {
				return new Product_Types\Product_Type_Register_Item( $c->get_hook_manager() );
			},
			true // Singleton.
		);

		// Initialize product type registers (triggers lazy instantiation).
		$this->container->get( 'product_type_register_auction' )->register();
		$this->container->get( 'product_type_register_item' )->register();
	}

	/**
	 * Register datastores.
	 *
	 * @since 1.0.0
	 */
	private function register_datastores(): void
    {
		$hook_manager = $this->container->get_hook_manager();

		// Register product class filter.
		$hook_manager->register_filter(
			'woocommerce_product_class',
			function ( $classname, $product_type ) {
				if ( Product_Types\Product_Auction::PRODUCT_TYPE === $product_type ) {
					return Product_Types\Product_Auction::class;
				}
				if ( Product_Types\Product_Item::PRODUCT_TYPE === $product_type ) {
					return Product_Types\Product_Item::class;
				}
				return $classname;
			},
			10,
			2
		);

		// Register custom datastores via woocommerce_data_stores filter.
		// WooCommerce resolves datastore keys as 'product-{product_type}'.
		$hook_manager->register_filter(
			'woocommerce_data_stores',
			function ( $stores ) {
				// Map product-aucteeno-ext-auction to our custom datastore class.
				$auction_store_key = 'product-' . Product_Types\Product_Auction::PRODUCT_TYPE;
				$stores[ $auction_store_key ] = Product_Types\Datastores\Datastore_Auction::class;

				// Map product-aucteeno-ext-item to our custom item datastore class.
				$item_store_key = 'product-' . Product_Types\Product_Item::PRODUCT_TYPE;
				$stores[ $item_store_key ] = Product_Types\Datastores\Datastore_Item::class;

				return $stores;
			},
			10,
			1
		);
	}

    /**
     * Register admin fields.
     *
     * @throws Exception
     *
     * @since 1.0.0
     */
	private function register_admin_fields(): void
    {

		// Register Meta_Fields_Auction with lazy loading.
		$this->container->register(
			'meta_fields_auction',
			function ( Container $c ) {
				return new Admin\Custom_Fields_Auction( $c->get_hook_manager() );
			},
			true // Singleton.
		);

		// Register Meta_Fields_Item with lazy loading.
		$this->container->register(
			'meta_fields_item',
			function ( Container $c ) {
				return new Admin\Custom_Fields_Item( $c->get_hook_manager() );
			},
			true // Singleton.
		);

		// Register Item_Parent_Relationship with lazy loading.
		$this->container->register(
			'item_parent_relationship',
			function ( Container $c ) {
				return new Admin\Item_Parent_Relationship( $c->get_hook_manager() );
			},
			true // Singleton.
		);

		// Register Product_Type_Filter with lazy loading.
		$this->container->register(
			'product_type_filter',
			function ( Container $c ) {
				return new Admin\Product_Type_Filter( $c->get_hook_manager() );
			},
			true // Singleton.
		);

		// Register Product_Tab_Aucteeno with lazy loading.
		$this->container->register(
			'product_tab_aucteeno',
			function ( Container $c ) {
				return new Admin\Product_Tab_Aucteeno( $c->get_hook_manager() );
			},
			true // Singleton.
		);

		// Register Disable_Reviews_Comments with lazy loading.
		$this->container->register(
			'disable_reviews_comments',
			function ( Container $c ) {
				return new Admin\Disable_Reviews_Comments( $c->get_hook_manager() );
			},
			true // Singleton.
		);

		// Register HPS_Sync_Handler with lazy loading.
		$this->container->register(
			'hps_sync_handler',
			function ( Container $c ) {
				return new Services\HPS_Sync_Handler( $c->get_hook_manager() );
			},
			true // Singleton.
		);

		// Initialize services (triggers lazy instantiation).
		$this->container->get( 'meta_fields_auction' )->init();
		$this->container->get( 'meta_fields_item' )->init();
		$this->container->get( 'item_parent_relationship' )->init();
		$this->container->get( 'product_type_filter' )->init();
		$this->container->get( 'product_tab_aucteeno' )->init();
		$this->container->get( 'disable_reviews_comments' )->init();
		$this->container->get( 'hps_sync_handler' )->init();
	}

    /**
     * Register settings.
     *
     * @throws Exception
     *
     * @since 1.0.0
     */
	private function register_settings(): void
    {

		// Register Settings with lazy loading.
		$this->container->register(
			'settings',
			function ( Container $c ) {
				return new Admin\Settings( $c->get_hook_manager() );
			},
			true // Singleton.
		);

		// Initialize Settings (triggers lazy instantiation).
		$this->container->get( 'settings' )->init();
	}

    /**
     * Register permalinks.
     *
     * @throws Exception
     *
     * @since 1.0.0
     */
	private function register_permalinks(): void
    {

		// Register Auction_Item_Permalinks with lazy loading.
		$this->container->register(
			'permalinks',
			function ( Container $c ) {
				return new Permalinks\Auction_Item_Permalinks( $c->get_hook_manager() );
			},
			true // Singleton.
		);

		// Initialize Permalinks (triggers lazy instantiation).
		$this->container->get( 'permalinks' )->init();
	}

	/**
	 * Register breadcrumbs.
	 *
	 * @throws Exception
	 *
	 * @since 1.0.0
	 */
	private function register_breadcrumbs(): void
    {

		// Register Breadcrumbs with lazy loading.
		$this->container->register(
			'breadcrumbs',
			function ( Container $c ) {
				return new Breadcrumbs( $c->get_hook_manager() );
			},
			true // Singleton.
		);

		// Initialize Breadcrumbs (triggers lazy instantiation).
		$this->container->get( 'breadcrumbs' )->init();
	}

	/**
	 * Register REST API.
	 *
	 * @since 1.0.0
	 */
	public function register_rest_api(): void
    {
		$rest_controller = new REST_API\REST_Controller();
		$rest_controller->register_routes();
	}

	/**
	 * Register query orderer for custom ordering.
	 *
	 * @since 2.2.0
	 */
	private function register_query_orderer(): void {
		$query_orderer = new Database\Query_Orderer();
		$query_orderer->init();
	}

    /**
     * Get meta fields auction instance.
     *
     * @return Admin\Custom_Fields_Auction|null Meta fields auction instance.
     *
     * @throws Exception
     *
     * @since 1.0.0
     */
	public function get_meta_fields_auction(): ?Admin\Custom_Fields_Auction
    {
        return $this->container?->get('meta_fields_auction');

    }

    /**
     * Get meta fields item instance.
     *
     * @return Admin\Custom_Fields_Item|null Meta fields item instance.
     *
     * @throws Exception
     *
     * @since 1.0.0
     */
	public function get_meta_fields_item(): ?Admin\Custom_Fields_Item
    {
        return $this->container?->get('meta_fields_item');
    }

	/**
	 * Prevent cloning of the instance.
	 */
	private function __clone() {
		// Prevent cloning.
	}

    /**
     * Prevent unserialization of the instance.
     *
     * @throws Exception
     */
	public function __wakeup() {
		throw new Exception( 'Cannot unserialize singleton' );
	}
}
