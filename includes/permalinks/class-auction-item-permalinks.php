<?php
/**
 * Auction Item Permalinks Class
 *
 * Handles custom permalink structures for auction and item product types.
 * Auctions: /{auction_base}/{auction_slug}/
 * Items: /{auction_base}/{auction_slug}/{item_base}/{item_slug}/
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Permalinks;

use TheAnother\Plugin\Aucteeno\Hook_Manager;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Auction;
use TheAnother\Plugin\Aucteeno\Product_Types\Product_Item;
use WP_Post;
use WP_Query;

/**
 * Class Auction_Item_Permalinks
 *
 * Manages rewrite rules, permalink generation, and URL validation
 * for auction and item product types.
 */
class Auction_Item_Permalinks {

	/**
	 * Option name for auction base.
	 */
	public const OPTION_AUCTION_BASE = 'aucteeno_auction_base';

	/**
	 * Option name for item base.
	 */
	public const OPTION_ITEM_BASE = 'aucteeno_item_base';

	/**
	 * Default auction base slug.
	 */
	public const DEFAULT_AUCTION_BASE = 'auction';

	/**
	 * Default item base slug.
	 */
	public const DEFAULT_ITEM_BASE = 'item';

	/**
	 * Query var for auction slug.
	 */
	public const QUERY_VAR_AUCTION_SLUG = 'aucteeno_auction_slug';

	/**
	 * Query var for item slug.
	 */
	public const QUERY_VAR_ITEM_SLUG = 'aucteeno_item_slug';

	/**
	 * Meta key for parent auction ID on items.
	 */
	public const META_KEY_AUCTION_ID = '_aucteeno_auction_id';

	/**
	 * Hook manager instance.
	 *
	 * @var Hook_Manager
	 */
	private Hook_Manager $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param Hook_Manager $hook_manager Hook manager instance.
	 */
	public function __construct( Hook_Manager $hook_manager ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Initialize the permalinks module.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		// Register rewrite rules on init (must run after WP rewrite is set up).
		$this->hook_manager->register_action(
			'init',
			array( $this, 'add_rewrite_rules' ),
			10
		);

		// Register query vars.
		$this->hook_manager->register_filter(
			'query_vars',
			array( $this, 'register_query_vars' )
		);

		// Filter permalink generation for products.
		$this->hook_manager->register_filter(
			'post_type_link',
			array( $this, 'filter_product_permalink' ),
			10,
			2
		);

		// Prevent WordPress from setting 404 prematurely when our query vars are present.
		$this->hook_manager->register_filter(
			'pre_handle_404',
			array( $this, 'prevent_premature_404' ),
			10,
			2
		);

		// Validate item URLs and enforce 404 on mismatch.
		$this->hook_manager->register_action(
			'template_redirect',
			array( $this, 'validate_item_url' ),
			1
		);

		// Disable WooCommerce's canonical redirect for our custom URLs.
		// WooCommerce's wc_product_canonical_redirect runs at priority 5 and redirects
		// if the URL doesn't match the default product permalink structure.
		$this->hook_manager->register_action(
			'template_redirect',
			array( $this, 'disable_wc_canonical_redirect_for_custom_urls' ),
			1 // Run early to disable before WooCommerce's redirect (priority 5).
		);

		// Admin: Add permalink settings fields.
		$this->hook_manager->register_action(
			'admin_init',
			array( $this, 'register_permalink_settings' )
		);

		// Flush rewrite rules when options are updated.
		$this->hook_manager->register_action(
			'update_option_' . self::OPTION_AUCTION_BASE,
			array( $this, 'schedule_flush_rewrite_rules' )
		);
		$this->hook_manager->register_action(
			'update_option_' . self::OPTION_ITEM_BASE,
			array( $this, 'schedule_flush_rewrite_rules' )
		);

		// Hook into permalink settings save to flush rules.
		$this->hook_manager->register_action(
			'load-options-permalink.php',
			array( $this, 'handle_permalink_settings_save' )
		);
	}

	/**
	 * Get the auction base slug.
	 *
	 * @return string Sanitized auction base.
	 */
	public static function get_auction_base(): string {
		$base = get_option( self::OPTION_AUCTION_BASE, self::DEFAULT_AUCTION_BASE );
		$base = self::sanitize_base( $base );
		return ! empty( $base ) ? $base : self::DEFAULT_AUCTION_BASE;
	}

	/**
	 * Get the item base slug.
	 *
	 * @return string Sanitized item base.
	 */
	public static function get_item_base(): string {
		$base = get_option( self::OPTION_ITEM_BASE, self::DEFAULT_ITEM_BASE );
		$base = self::sanitize_base( $base );
		return ! empty( $base ) ? $base : self::DEFAULT_ITEM_BASE;
	}

	/**
	 * Sanitize a URL base segment.
	 *
	 * @param string $base The base to sanitize.
	 * @return string Sanitized base (lowercase, no slashes, slug-like).
	 */
	public static function sanitize_base( string $base ): string {
		// Remove slashes, sanitize as slug.
		$base = trim( $base, '/' );
		$base = sanitize_title( $base );
		return $base;
	}

	/**
	 * Add rewrite rules for auction and item URLs.
	 *
	 * @since 1.0.0
	 */
	public function add_rewrite_rules(): void {
		self::add_rewrite_rules_static();
	}

	/**
	 * Add rewrite rules for auction and item URLs (static version).
	 * Used by activation hook and instance method.
	 *
	 * @since 1.0.0
	 */
	private static function add_rewrite_rules_static(): void {
		$auction_base = self::get_auction_base();
		$item_base    = self::get_item_base();

		// Item URL: /{auction_base}/{auction_slug}/{item_base}/{item_slug}/
		// Must be added BEFORE auction rule (more specific first).
		add_rewrite_rule(
			'^' . preg_quote( $auction_base, '/' ) . '/([^/]+)/' . preg_quote( $item_base, '/' ) . '/([^/]+)/?$',
			'index.php?post_type=product&name=$matches[2]&' . self::QUERY_VAR_AUCTION_SLUG . '=$matches[1]&' . self::QUERY_VAR_ITEM_SLUG . '=$matches[2]',
			'top'
		);

		// Auction URL: /{auction_base}/{auction_slug}/
		add_rewrite_rule(
			'^' . preg_quote( $auction_base, '/' ) . '/([^/]+)/?$',
			'index.php?post_type=product&name=$matches[1]&' . self::QUERY_VAR_AUCTION_SLUG . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Register custom query vars.
	 *
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR_AUCTION_SLUG;
		$vars[] = self::QUERY_VAR_ITEM_SLUG;
		return $vars;
	}

	/**
	 * Filter product permalink to generate custom URLs for auction/item types.
	 *
	 * @param string  $permalink The current permalink.
	 * @param WP_Post $post      The post object.
	 * @return string Modified permalink.
	 */
	public function filter_product_permalink( string $permalink, WP_Post $post ): string {
		// Only handle products.
		if ( 'product' !== $post->post_type ) {
			return $permalink;
		}

		// Get product to check type.
		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return $permalink;
		}

		$product_type = $product->get_type();

		// Handle auction products.
		if ( Product_Auction::PRODUCT_TYPE === $product_type ) {
			return $this->get_auction_permalink( $post );
		}

		// Handle item products - pass original permalink to avoid recursion.
		if ( Product_Item::PRODUCT_TYPE === $product_type ) {
			return $this->get_item_permalink( $post, $product, $permalink );
		}

		return $permalink;
	}

	/**
	 * Generate permalink for an auction product.
	 *
	 * @param WP_Post $post The auction post.
	 * @return string The auction permalink.
	 */
	private function get_auction_permalink( WP_Post $post ): string {
		$auction_base = self::get_auction_base();
		return home_url( user_trailingslashit( $auction_base . '/' . $post->post_name ) );
	}

	/**
	 * Generate permalink for an item product.
	 *
	 * @param WP_Post            $post      The item post.
	 * @param \WC_Product|object $product   The product object.
	 * @param string             $permalink The original permalink (fallback).
	 * @return string The item permalink.
	 */
	private function get_item_permalink( WP_Post $post, $product, string $permalink = '' ): string {
		// Get parent auction ID (prefer post_parent, fallback to meta).
		$parent_auction_id = $post->post_parent;
		if ( ! $parent_auction_id ) {
			$parent_auction_id = (int) get_post_meta( $post->ID, self::META_KEY_AUCTION_ID, true );
		}

		// If no parent, fallback to original permalink (avoid recursive get_permalink call).
		if ( ! $parent_auction_id ) {
			return $permalink ?: home_url( '?p=' . $post->ID );
		}

		// Get parent auction post.
		$parent_auction = get_post( $parent_auction_id );
		if ( ! $parent_auction || 'product' !== $parent_auction->post_type ) {
			// Invalid parent, use original permalink.
			return $permalink ?: home_url( '?p=' . $post->ID );
		}

		// Verify parent is actually an auction type.
		$parent_product = wc_get_product( $parent_auction_id );
		if ( ! $parent_product || Product_Auction::PRODUCT_TYPE !== $parent_product->get_type() ) {
			return $permalink ?: home_url( '?p=' . $post->ID );
		}

		$auction_base = self::get_auction_base();
		$item_base    = self::get_item_base();

		return home_url(
			user_trailingslashit(
				$auction_base . '/' . $parent_auction->post_name . '/' . $item_base . '/' . $post->post_name
			)
		);
	}

	/**
	 * Prevent WordPress from setting 404 prematurely when our custom query vars are present.
	 * This allows our validation to run and handle 404s appropriately.
	 *
	 * @param bool     $preempt Whether to short-circuit default header status handling.
	 * @param WP_Query $query   The WP_Query instance.
	 * @return bool|void True to prevent 404, false/void to allow default handling.
	 */
	public function prevent_premature_404( $preempt, $query ) {
		// Only prevent 404 if we have our custom query vars.
		$auction_slug = $query->get( self::QUERY_VAR_AUCTION_SLUG );
		$item_slug    = $query->get( self::QUERY_VAR_ITEM_SLUG );

		// If we have our query vars, prevent WordPress from setting 404 prematurely.
		// Our validate_item_url() will handle 404s appropriately.
		if ( $auction_slug || $item_slug ) {
			return true;
		}

		return $preempt;
	}

	/**
	 * Validate item URLs and enforce 404 on mismatch.
	 *
	 * Checks:
	 * 1) Post exists and is product type
	 * 2) Product type is "item"
	 * 3) Parent auction exists and is valid auction
	 * 4) Parent auction slug matches URL
	 *
	 * @since 1.0.0
	 */
	public function validate_item_url(): void {
		// Only validate if we have item query vars.
		$auction_slug = get_query_var( self::QUERY_VAR_AUCTION_SLUG );
		$item_slug    = get_query_var( self::QUERY_VAR_ITEM_SLUG );

		// If no item slug, this might be an auction URL - validate that instead.
		if ( $auction_slug && ! $item_slug ) {
			$this->validate_auction_url( $auction_slug );
			return;
		}

		// If neither var is set, not our URL pattern.
		if ( ! $auction_slug || ! $item_slug ) {
			return;
		}

		global $wp_query;

		// Check 1: Find product post by slug using WP_Query.
		$post_query = new WP_Query(
			array(
				'post_type'      => 'product',
				'name'           => $item_slug,
				'posts_per_page' => 1,
				'post_status'    => 'any', // Check all statuses, we'll validate later.
			)
		);

		if ( ! $post_query->have_posts() ) {
			$this->trigger_404();
			return;
		}

		$post    = $post_query->posts[0];
		$post_id = $post->ID;

		// Check 2: Verify post is a product (already checked by query, but double-check).
		if ( 'product' !== $post->post_type ) {
			$this->trigger_404();
			return;
		}

		// Check 3: Verify product type is "item" (only use wc_get_product when needed).
		$product = wc_get_product( $post_id );
		if ( ! $product || Product_Item::PRODUCT_TYPE !== $product->get_type() ) {
			$this->trigger_404();
			return;
		}

		// Check 4: Get parent auction ID (prefer post_parent, fallback to meta).
		$parent_auction_id = $post->post_parent;
		if ( ! $parent_auction_id ) {
			$parent_auction_id = (int) get_post_meta( $post_id, self::META_KEY_AUCTION_ID, true );
		}

		if ( ! $parent_auction_id ) {
			$this->trigger_404();
			return;
		}

		// Check 5: Find parent auction post using WP_Query.
		$parent_query = new WP_Query(
			array(
				'post_type'      => 'product',
				'p'              => $parent_auction_id,
				'posts_per_page' => 1,
				'post_status'    => 'any',
			)
		);

		if ( ! $parent_query->have_posts() ) {
			$this->trigger_404();
			return;
		}

		$parent_post = $parent_query->posts[0];

		// Check 6: Verify parent is a product.
		if ( 'product' !== $parent_post->post_type ) {
			$this->trigger_404();
			return;
		}

		// Check 7: Verify parent slug matches URL slug exactly.
		if ( $parent_post->post_name !== $auction_slug ) {
			// Could redirect here, but PRD prefers 404 for security.
			$this->trigger_404();
			return;
		}

		// Check 8: Verify parent is auction type (only use wc_get_product when needed).
		$parent_product = wc_get_product( $parent_auction_id );
		if ( ! $parent_product || Product_Auction::PRODUCT_TYPE !== $parent_product->get_type() ) {
			$this->trigger_404();
			return;
		}

		// All checks passed - URL is valid.
		// Set up the WordPress query properly so the post is found.
		$wp_query->is_404            = false;
		$wp_query->is_single         = true;
		$wp_query->is_singular       = true;
		$wp_query->queried_object    = $post;
		$wp_query->queried_object_id = $post_id;
		$wp_query->post              = $post;
		$wp_query->posts             = array( $post );
		$wp_query->post_count        = 1;
		$wp_query->found_posts       = 1;
	}

	/**
	 * Validate auction URL (when no item slug present).
	 *
	 * @param string $auction_slug The auction slug from URL.
	 */
	private function validate_auction_url( string $auction_slug ): void {
		// Find product post by slug using WP_Query.
		$post_query = new WP_Query(
			array(
				'post_type'      => 'product',
				'name'           => $auction_slug,
				'posts_per_page' => 1,
				'post_status'    => 'any', // Check all statuses, we'll validate later.
			)
		);

		if ( ! $post_query->have_posts() ) {
			$this->trigger_404();
			return;
		}

		$post    = $post_query->posts[0];
		$post_id = $post->ID;

		// Verify post is a product (already checked by query, but double-check).
		if ( 'product' !== $post->post_type ) {
			$this->trigger_404();
			return;
		}

		// Verify it's an auction type (only use wc_get_product when needed).
		$product = wc_get_product( $post_id );
		if ( ! $product || Product_Auction::PRODUCT_TYPE !== $product->get_type() ) {
			$this->trigger_404();
			return;
		}

		// Valid auction URL - set up the WordPress query properly.
		global $wp_query;
		$wp_query->is_404            = false;
		$wp_query->is_single         = true;
		$wp_query->is_singular       = true;
		$wp_query->queried_object    = $post;
		$wp_query->queried_object_id = $post_id;
		$wp_query->post              = $post;
		$wp_query->posts             = array( $post );
		$wp_query->post_count        = 1;
		$wp_query->found_posts       = 1;
	}

	/**
	 * Trigger a 404 response.
	 */
	private function trigger_404(): void {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Disable WooCommerce's canonical redirect for our custom auction/item URLs.
	 *
	 * WooCommerce's wc_product_canonical_redirect() redirects to the default product
	 * permalink if the current URL doesn't match. Since we use custom URL structures
	 * for auction and item products, we need to disable this redirect for our URLs.
	 *
	 * @since 1.0.0
	 */
	public function disable_wc_canonical_redirect_for_custom_urls(): void {
		// Check if we have our custom query vars (indicating custom URL structure).
		$auction_slug = get_query_var( self::QUERY_VAR_AUCTION_SLUG );
		$item_slug    = get_query_var( self::QUERY_VAR_ITEM_SLUG );

		// If we have either of our custom query vars, disable WooCommerce's redirect.
		if ( $auction_slug || $item_slug ) {
			// Remove WooCommerce's canonical redirect for this request.
			remove_action( 'template_redirect', 'wc_product_canonical_redirect', 5 );
		}
	}

	/**
	 * Register permalink settings on the Settings → Permalinks page.
	 *
	 * @since 1.0.0
	 */
	public function register_permalink_settings(): void {
		// Add settings section.
		add_settings_section(
			'aucteeno_permalink_section',
			__( 'Aucteeno Permalinks', 'aucteeno' ),
			array( $this, 'render_permalink_section' ),
			'permalink'
		);

		// Auction base field.
		add_settings_field(
			self::OPTION_AUCTION_BASE,
			__( 'Auction base', 'aucteeno' ),
			array( $this, 'render_auction_base_field' ),
			'permalink',
			'aucteeno_permalink_section'
		);

		// Item base field.
		add_settings_field(
			self::OPTION_ITEM_BASE,
			__( 'Item base', 'aucteeno' ),
			array( $this, 'render_item_base_field' ),
			'permalink',
			'aucteeno_permalink_section'
		);

		// Register settings (for sanitization).
		register_setting(
			'permalink',
			self::OPTION_AUCTION_BASE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_base' ),
				'default'           => self::DEFAULT_AUCTION_BASE,
			)
		);

		register_setting(
			'permalink',
			self::OPTION_ITEM_BASE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_base' ),
				'default'           => self::DEFAULT_ITEM_BASE,
			)
		);
	}

	/**
	 * Render the permalink section description.
	 */
	public function render_permalink_section(): void {
		echo '<p>' . esc_html__( 'Configure the URL structure for auctions and items.', 'aucteeno' ) . '</p>';
	}

	/**
	 * Render the auction base field.
	 */
	public function render_auction_base_field(): void {
		$value = self::get_auction_base();
		?>
		<input type="text"
			name="<?php echo esc_attr( self::OPTION_AUCTION_BASE ); ?>"
			id="<?php echo esc_attr( self::OPTION_AUCTION_BASE ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="<?php echo esc_attr( self::DEFAULT_AUCTION_BASE ); ?>"
		>
		<p class="description">
			<?php
			printf(
				/* translators: %s: example URL */
				esc_html__( 'Example: %s', 'aucteeno' ),
				'<code>' . esc_html( home_url( $value . '/my-auction/' ) ) . '</code>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render the item base field.
	 */
	public function render_item_base_field(): void {
		$value        = self::get_item_base();
		$auction_base = self::get_auction_base();
		?>
		<input type="text"
			name="<?php echo esc_attr( self::OPTION_ITEM_BASE ); ?>"
			id="<?php echo esc_attr( self::OPTION_ITEM_BASE ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="<?php echo esc_attr( self::DEFAULT_ITEM_BASE ); ?>"
		>
		<p class="description">
			<?php
			printf(
				/* translators: %s: example URL */
				esc_html__( 'Example: %s', 'aucteeno' ),
				'<code>' . esc_html( home_url( $auction_base . '/my-auction/' . $value . '/my-item/' ) ) . '</code>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Handle permalink settings page save.
	 * Manually save our options since they're not in the default options-permalink form.
	 *
	 * @since 1.0.0
	 */
	public function handle_permalink_settings_save(): void {
		// Check if form was submitted.
		if ( ! isset( $_POST['permalink_structure'] ) ) {
			return;
		}

		// Verify nonce (WP core handles this for permalink page).
		check_admin_referer( 'update-permalink' );

		// Save auction base.
		if ( isset( $_POST[ self::OPTION_AUCTION_BASE ] ) ) {
			$auction_base = self::sanitize_base( sanitize_text_field( wp_unslash( $_POST[ self::OPTION_AUCTION_BASE ] ) ) );
			if ( empty( $auction_base ) ) {
				$auction_base = self::DEFAULT_AUCTION_BASE;
			}
			update_option( self::OPTION_AUCTION_BASE, $auction_base );
		}

		// Save item base.
		if ( isset( $_POST[ self::OPTION_ITEM_BASE ] ) ) {
			$item_base = self::sanitize_base( sanitize_text_field( wp_unslash( $_POST[ self::OPTION_ITEM_BASE ] ) ) );
			if ( empty( $item_base ) ) {
				$item_base = self::DEFAULT_ITEM_BASE;
			}
			update_option( self::OPTION_ITEM_BASE, $item_base );
		}

		// Flush rewrite rules (permalink page also does this, but ensure our rules are updated).
		$this->schedule_flush_rewrite_rules();
	}

	/**
	 * Schedule rewrite rules flush.
	 * Uses a transient to ensure flush happens on next init, not immediately.
	 *
	 * @since 1.0.0
	 */
	public function schedule_flush_rewrite_rules(): void {
		// Set transient to trigger flush on next page load.
		set_transient( 'aucteeno_flush_rewrite_rules', '1', 60 );

		// Also hook into shutdown to flush if we're in admin.
		// Use Hook_Manager for this hook registration.
		if ( ! has_action( 'shutdown', array( $this, 'maybe_flush_rewrite_rules' ) ) ) {
			$this->hook_manager->register_action(
				'shutdown',
				array( $this, 'maybe_flush_rewrite_rules' )
			);
		}
	}

	/**
	 * Flush rewrite rules if scheduled.
	 *
	 * @since 1.0.0
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( get_transient( 'aucteeno_flush_rewrite_rules' ) ) {
			delete_transient( 'aucteeno_flush_rewrite_rules' );
			flush_rewrite_rules();
		}
	}

	/**
	 * Flush rewrite rules on plugin activation.
	 * Called from Install class.
	 *
	 * @since 1.0.0
	 */
	public static function activate(): void {
		// Add our rewrite rules first.
		self::add_rewrite_rules_static();
		// Then flush.
		flush_rewrite_rules();
	}

	/**
	 * Flush rewrite rules on plugin deactivation.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
