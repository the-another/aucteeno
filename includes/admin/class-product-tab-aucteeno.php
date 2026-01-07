<?php
/**
 * Product Tab Aucteeno Class
 *
 * Handles the Aucteeno product data tab registration and panel rendering.
 *
 * This class provides a flexible tab system for WooCommerce product edit pages,
 * allowing developers to add custom tabs and content panels within the Aucteeno
 * product data tab. The system uses WordPress hooks and filters for extensibility.
 *
 * ## Architecture Overview
 *
 * The tab system consists of three main components:
 * 1. **Main Tab Registration**: Adds the "Aucteeno" tab to WooCommerce product data tabs
 * 2. **Sub-Tab System**: Creates nested tabs within the Aucteeno panel (Link, Location, Times, Details)
 * 3. **Content Rendering**: Uses action hooks to render content for each sub-tab
 *
 * ## How It Works
 *
 * 1. The main "Aucteeno" tab is registered via `woocommerce_product_data_tabs` filter
 * 2. When the tab is clicked, `render_product_panel()` is called via `woocommerce_product_data_panels` action
 * 3. The panel renders a list of sub-tabs (configurable via `aucteeno_product_panel_tabs` filter)
 * 4. Each sub-tab panel triggers a dynamic action hook: `aucteeno_product_tab_{$tab_key}`
 * 5. Developers hook into these action hooks to render their custom content
 *
 * ## Integration Examples
 *
 * ### Example 1: Adding Content to an Existing Tab
 *
 * To add fields to the existing "Link" tab:
 *
 * ```php
 * add_action( 'aucteeno_product_tab_link', function( $post_id, $post_type ) {
 *     $product = wc_get_product( $post_id );
 *     if ( ! $product || $product->get_type() !== 'aucteeno-ext-auction' ) {
 *         return;
 *     }
 *
 *     echo '<div class="options_group">';
 *     woocommerce_wp_text_input( array(
 *         'id'    => '_custom_link_field',
 *         'label' => __( 'Custom Link Field', 'your-textdomain' ),
 *         'value' => get_post_meta( $post_id, '_custom_link_field', true ),
 *     ) );
 *     echo '</div>';
 * }, 10, 2 );
 * ```
 *
 * ### Example 2: Adding a New Custom Tab
 *
 * To add a completely new tab called "Custom Settings":
 *
 * ```php
 * // Step 1: Register the new tab
 * add_filter( 'aucteeno_product_panel_tabs', function( $tabs, $post_id, $post_type ) {
 *     $tabs['custom_settings'] = __( 'Custom Settings', 'your-textdomain' );
 *     return $tabs;
 * }, 10, 3 );
 *
 * // Step 2: Render content for the new tab
 * add_action( 'aucteeno_product_tab_custom_settings', function( $post_id, $post_type ) {
 *     $product = wc_get_product( $post_id );
 *     if ( ! $product ) {
 *         return;
 *     }
 *
 *     echo '<div class="options_group">';
 *     woocommerce_wp_textarea_input( array(
 *         'id'    => '_custom_settings',
 *         'label' => __( 'Custom Settings', 'your-textdomain' ),
 *         'value' => get_post_meta( $post_id, '_custom_settings', true ),
 *     ) );
 *     echo '</div>';
 * }, 10, 2 );
 *
 * // Step 3: Save the data (if needed)
 * add_action( 'woocommerce_process_product_meta', function( $post_id ) {
 *     if ( isset( $_POST['_custom_settings'] ) ) {
 *         update_post_meta(
 *             $post_id,
 *             '_custom_settings',
 *             sanitize_textarea_field( wp_unslash( $_POST['_custom_settings'] ) )
 *         );
 *     }
 * } );
 * ```
 *
 * ### Example 3: Modifying Tab Order
 *
 * To change the order of tabs (e.g., put "Details" first):
 *
 * ```php
 * add_filter( 'aucteeno_product_panel_tabs', function( $tabs, $post_id, $post_type ) {
 *     // Reorder tabs by creating a new array in desired order
 *     $reordered = array(
 *         'details'  => $tabs['details'] ?? __( 'Details', 'aucteeno' ),
 *         'link'     => $tabs['link'] ?? __( 'Link', 'aucteeno' ),
 *         'location' => $tabs['location'] ?? __( 'Location', 'aucteeno' ),
 *         'times'    => $tabs['times'] ?? __( 'Times', 'aucteeno' ),
 *     );
 *     return $reordered;
 * }, 10, 3 );
 * ```
 *
 * ### Example 4: Removing a Default Tab
 *
 * To remove the "Times" tab:
 *
 * ```php
 * add_filter( 'aucteeno_product_panel_tabs', function( $tabs, $post_id, $post_type ) {
 *     unset( $tabs['times'] );
 *     return $tabs;
 * }, 10, 3 );
 * ```
 *
 * ### Example 5: Conditional Tab Display
 *
 * To show a tab only for specific product types:
 *
 * ```php
 * add_filter( 'aucteeno_product_panel_tabs', function( $tabs, $post_id, $post_type ) {
 *     $product = wc_get_product( $post_id );
 *     if ( $product && $product->get_type() === 'aucteeno-ext-auction' ) {
 *         $tabs['auction_specific'] = __( 'Auction Specific', 'your-textdomain' );
 *     }
 *     return $tabs;
 * }, 10, 3 );
 * ```
 *
 * ### Example 6: Complete Integration (Class-Based)
 *
 * For a more structured approach using a class:
 *
 * ```php
 * class My_Custom_Tab_Integration {
 *     public function __construct() {
 *         add_filter( 'aucteeno_product_panel_tabs', array( $this, 'add_tab' ), 10, 3 );
 *         add_action( 'aucteeno_product_tab_my_custom', array( $this, 'render_tab' ), 10, 2 );
 *         add_action( 'woocommerce_process_product_meta', array( $this, 'save_tab' ), 10, 1 );
 *     }
 *
 *     public function add_tab( $tabs, $post_id, $post_type ) {
 *         $tabs['my_custom'] = __( 'My Custom Tab', 'your-textdomain' );
 *         return $tabs;
 *     }
 *
 *     public function render_tab( $post_id, $post_type ) {
 *         $value = get_post_meta( $post_id, '_my_custom_field', true );
 *         echo '<div class="options_group">';
 *         woocommerce_wp_text_input( array(
 *             'id'    => '_my_custom_field',
 *             'label' => __( 'Custom Field', 'your-textdomain' ),
 *             'value' => $value,
 *         ) );
 *         echo '</div>';
 *     }
 *
 *     public function save_tab( $post_id ) {
 *         if ( isset( $_POST['_my_custom_field'] ) ) {
 *             update_post_meta(
 *                 $post_id,
 *                 '_my_custom_field',
 *                 sanitize_text_field( wp_unslash( $_POST['_my_custom_field'] ) )
 *             );
 *         }
 *     }
 * }
 * new My_Custom_Tab_Integration();
 * ```
 *
 * ## Available Filters
 *
 * ### `aucteeno_product_panel_tabs`
 * Filter the list of sub-tabs within the Aucteeno panel.
 *
 * **Parameters:**
 * - `$tabs` (array): Associative array of tab_key => tab_label pairs
 * - `$post_id` (int): Current product post ID
 * - `$post_type` (string): Current post type (usually 'product')
 *
 * **Return:** Array of tab_key => tab_label pairs
 *
 * **Example:**
 * ```php
 * add_filter( 'aucteeno_product_panel_tabs', function( $tabs, $post_id, $post_type ) {
 *     $tabs['my_tab'] = __( 'My Tab', 'textdomain' );
 *     return $tabs;
 * }, 10, 3 );
 * ```
 *
 * ### `aucteeno_product_tab_classes`
 * Filter the CSS classes that control when the main Aucteeno tab is visible.
 *
 * **Parameters:**
 * - `$classes` (array): Array of CSS class names (e.g., 'show_if_aucteeno-ext-auction')
 *
 * **Return:** Array of CSS class names
 *
 * **Example:**
 * ```php
 * add_filter( 'aucteeno_product_tab_classes', function( $classes ) {
 *     $classes[] = 'show_if_my-custom-product-type';
 *     return $classes;
 * } );
 * ```
 *
 * ## Available Action Hooks
 *
 * ### `aucteeno_product_tab_{$tab_key}`
 * Dynamic action hook fired when rendering content for a specific sub-tab.
 *
 * **Parameters:**
 * - `$post_id` (int): Current product post ID
 * - `$post_type` (string): Current post type (usually 'product')
 *
 * **Hook Name Pattern:** `aucteeno_product_tab_{tab_key}`
 *
 * **Example:**
 * ```php
 * // For a tab with key 'link', the hook is: aucteeno_product_tab_link
 * add_action( 'aucteeno_product_tab_link', function( $post_id, $post_type ) {
 *     // Render tab content here
 * }, 10, 2 );
 * ```
 *
 * ## Default Tabs
 *
 * The system includes four default tabs:
 * - `link`: Link tab (Auction URL, Button Text)
 * - `location`: Location tab (Address, City, Postal Code, etc.)
 * - `times`: Times tab (Bidding start/end times, Status)
 * - `details`: Details tab (Notices, Directions, Terms & Conditions)
 *
 * These tabs are rendered by `Meta_Fields_Auction` and `Meta_Fields_Item` classes.
 * See `includes/admin/class-meta-fields-auction.php` and `includes/admin/class-meta-fields-item.php`
 * for reference implementations.
 *
 * ## CSS Classes and Styling
 *
 * The tab system uses the following CSS classes:
 * - `.aucteeno-tabs-wrapper`: Container for all tabs
 * - `.aucteeno-tabs`: List of tab links
 * - `.aucteeno-tab`: Individual tab item
 * - `.aucteeno-tab-link`: Tab link anchor
 * - `.aucteeno-tab-panel`: Content panel for each tab
 * - `.active`: Applied to active tab and panel
 *
 * Styles are enqueued automatically via `enqueue_assets()` method.
 * Custom styles can be added by enqueuing additional stylesheets with higher priority.
 *
 * ## JavaScript Behavior
 *
 * Tab switching is handled by `assets/js/admin-tabs.js`. The script:
 * - Handles click events on tab links
 * - Manages active states
 * - Supports hash navigation (e.g., `#aucteeno-tab-panel-link`)
 * - Re-initializes on WooCommerce product type changes
 *
 * Custom JavaScript can hook into tab events if needed.
 *
 * ## Best Practices
 *
 * 1. **Always check product type** before rendering content:
 *   ```php
 *   $product = wc_get_product( $post_id );
 *   if ( ! $product || $product->get_type() !== 'your-product-type' ) {
 *       return;
 *   }
 *   ```
 *
 * 2. **Use WooCommerce field functions** for consistent styling:
 *   - `woocommerce_wp_text_input()`
 *   - `woocommerce_wp_textarea_input()`
 *   - `woocommerce_wp_select()`
 *   - `woocommerce_wp_checkbox()`
 *
 * 3. **Wrap fields in `.options_group` div** for proper spacing:
 *   ```php
 *   echo '<div class="options_group">';
 *   // Your fields here
 *   echo '</div>';
 *   ```
 *
 * 4. **Sanitize and validate** all input when saving:
 *   ```php
 *   if ( isset( $_POST['_my_field'] ) ) {
 *       update_post_meta(
 *           $post_id,
 *           '_my_field',
 *           sanitize_text_field( wp_unslash( $_POST['_my_field'] ) )
 *       );
 *   }
 *   ```
 *
 * 5. **Use proper priority** when hooking into filters/actions:
 *   - Default tabs use priority 10
 *   - Use priority < 10 to run before defaults
 *   - Use priority > 10 to run after defaults
 *
 * ## Troubleshooting
 *
 * ### Tab not showing
 * - Check that the tab is added via `aucteeno_product_panel_tabs` filter
 * - Verify the main Aucteeno tab is visible (check product type matches `show_if_*` classes)
 * - Ensure JavaScript is loaded (check browser console for errors)
 *
 * ### Content not rendering
 * - Verify the action hook name matches: `aucteeno_product_tab_{$tab_key}`
 * - Check that the hook is registered before `woocommerce_product_data_panels` fires
 * - Ensure the callback function accepts 2 parameters: `$post_id` and `$post_type`
 *
 * ### Styling issues
 * - Check that `admin-tabs.css` is enqueued
 * - Verify CSS classes match the expected structure
 * - Use browser dev tools to inspect element classes
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Admin;

use TheAnother\Plugin\Aucteeno\Hook_Manager;

/**
 * Class Product_Tab_Aucteeno
 *
 * Manages the Aucteeno product data tab and provides a flexible sub-tab system
 * for organizing product meta fields and custom content.
 *
 * This class handles:
 * - Registration of the main Aucteeno tab in WooCommerce product edit pages
 * - Rendering of the tab panel with nested sub-tabs
 * - Enqueuing of CSS and JavaScript assets for tab functionality
 * - Providing extensible hooks for developers to add custom tabs and content
 */
class Product_Tab_Aucteeno {

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
	 * Initialize tab registration.
	 *
	 * Registers all necessary WordPress hooks for the Aucteeno product tab:
	 * - Adds the main tab to WooCommerce product data tabs
	 * - Renders the tab panel content
	 * - Enqueues CSS and JavaScript assets
	 *
	 * This method should be called during plugin initialization, typically
	 * from the main plugin class or a service container.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->hook_manager->register_filter(
			'woocommerce_product_data_tabs',
			array( $this, 'add_product_tab' )
		);
		$this->hook_manager->register_action(
			'woocommerce_product_data_panels',
			array( $this, 'render_product_panel' )
		);
		$this->hook_manager->register_action(
			'admin_enqueue_scripts',
			array( $this, 'enqueue_assets' )
		);
	}

	/**
	 * Enqueue admin assets for tabs.
	 *
	 * Enqueues CSS and JavaScript files required for the tab functionality.
	 * Only loads on product edit pages (post.php and post-new.php) to avoid
	 * unnecessary asset loading on other admin pages.
	 *
	 * Assets enqueued:
	 * - `admin-tabs.css`: Styles for tab navigation and panels
	 * - `admin-tabs.js`: JavaScript for tab switching and interaction
	 *
	 * @param string $hook Current admin page hook (e.g., 'post.php', 'post-new.php').
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		global $post_type;

		// Only enqueue on product edit pages.
		if ( 'product' !== $post_type || 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$css_url = AUCTEENO_PLUGIN_URL . 'assets/css/admin-tabs.css';
		$js_url  = AUCTEENO_PLUGIN_URL . 'assets/js/admin-tabs.js';

		wp_enqueue_style(
			'aucteeno-admin-tabs',
			$css_url,
			array(),
			AUCTEENO_VERSION
		);

		wp_enqueue_script(
			'aucteeno-admin-tabs',
			$js_url,
			array( 'jquery' ),
			AUCTEENO_VERSION,
			true
		);

		// Enqueue Select2 (WooCommerce includes it, but we'll ensure it's loaded).
		// WooCommerce uses 'select2' handle for Select2.
		if ( class_exists( 'WooCommerce' ) ) {
			wp_enqueue_script( 'select2' );
			wp_enqueue_style( 'select2' );
		} else {
			// Fallback: Use WordPress core Select2 if WooCommerce is not available.
			// WordPress doesn't include Select2 by default, so we'll rely on WooCommerce.
			// If needed, we could enqueue a CDN version here.
		}

		// Enqueue auction search JavaScript.
		$auction_search_js_url = AUCTEENO_PLUGIN_URL . 'assets/js/admin-auction-search.js';
		wp_enqueue_script(
			'aucteeno-admin-auction-search',
			$auction_search_js_url,
			array( 'jquery', 'select2' ),
			AUCTEENO_VERSION,
			true
		);

		// Enqueue location fields JavaScript.
		$location_fields_js_url = AUCTEENO_PLUGIN_URL . 'assets/js/admin-location-fields.js';
		wp_enqueue_script(
			'aucteeno-admin-location-fields',
			$location_fields_js_url,
			array( 'jquery' ),
			AUCTEENO_VERSION,
			true
		);

		// Localize script with states data.
		$all_states = WC()->countries->get_states();
		wp_localize_script(
			'aucteeno-admin-location-fields',
			'aucteenoLocationFields',
			array(
				'states' => $all_states,
			)
		);
	}

	/**
	 * Get tab CSS classes.
	 *
	 * Returns the CSS classes that control when the Aucteeno tab should be shown.
	 * These classes follow WooCommerce's pattern (e.g., 'show_if_aucteeno-ext-auction')
	 * and are used to conditionally display the tab based on product type.
	 *
	 * Default classes:
	 * - `show_if_aucteeno-ext-auction`: Show tab for auction products
	 * - `show_if_{Product_Item::PRODUCT_TYPE}`: Show tab for item products
	 *
	 * Developers can modify these classes via the `aucteeno_product_tab_classes` filter
	 * to add support for additional product types or customize visibility logic.
	 *
	 * @return array<string> Array of CSS class names that control tab visibility.
	 */
	public function get_tab_classes(): array {
		$default_classes = array(
			'show_if_aucteeno-ext-auction',
			'show_if_' . \TheAnother\Plugin\Aucteeno\Product_Types\Product_Item::PRODUCT_TYPE,
		);

		/**
		 * Filter the CSS classes for the Aucteeno product tab.
		 *
		 * Allows additional plugins to customize when the tab should be displayed
		 * by adding or removing CSS classes.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string> $classes Array of CSS class names.
		 */
		$classes = apply_filters( 'aucteeno_product_tab_classes', $default_classes );

		// Ensure we always return an array.
		if ( ! is_array( $classes ) ) {
			return $default_classes;
		}

		return $classes;
	}

	/**
	 * Add Aucteeno product tab.
	 *
	 * Adds the main "Aucteeno" tab to WooCommerce's product data tabs.
	 * This is called via the `woocommerce_product_data_tabs` filter.
	 *
	 * Tab configuration:
	 * - Label: "Aucteeno" (translatable)
	 * - Target: `aucteeno_product_data` (ID of the panel div)
	 * - Classes: Dynamic based on product type (via `get_tab_classes()`)
	 * - Priority: 25 (appears after standard WooCommerce tabs)
	 *
	 * @param array<string, mixed> $tabs Existing WooCommerce product data tabs.
	 * @return array<string, mixed> Modified tabs array with Aucteeno tab added.
	 */
	public function add_product_tab( array $tabs ): array {
		$tabs['aucteeno'] = array(
			'label'    => __( 'Aucteeno', 'aucteeno' ),
			'target'   => 'aucteeno_product_data',
			'class'    => $this->get_tab_classes(),
			'priority' => 25,
		);

		return $tabs;
	}

	/**
	 * Get default tabs.
	 *
	 * Returns the default set of sub-tabs that appear within the Aucteeno panel.
	 * These tabs can be modified, reordered, or extended via the
	 * `aucteeno_product_panel_tabs` filter.
	 *
	 * Default tabs:
	 * - `link`: Link tab (Auction/Item URL, Button Text)
	 * - `location`: Location tab (Address, City, Postal Code, etc.)
	 * - `times`: Times tab (Bidding start/end times, Status)
	 * - `details`: Details tab (Notices, Directions, Terms & Conditions)
	 *
	 * @return array<string, string> Associative array of tab_key => tab_label pairs.
	 */
	private function get_default_tabs(): array {
		return array(
			'link'     => __( 'Link', 'aucteeno' ),
			'location' => __( 'Location', 'aucteeno' ),
			'times'    => __( 'Times', 'aucteeno' ),
			'details'  => __( 'Details', 'aucteeno' ),
		);
	}

	/**
	 * Render product panel.
	 *
	 * Renders the main Aucteeno product data panel with nested sub-tabs.
	 * This method is called via the `woocommerce_product_data_panels` action.
	 *
	 * The panel structure:
	 * 1. Container div with ID `aucteeno_product_data`
	 * 2. Tab navigation list (ul.aucteeno-tabs)
	 * 3. Tab panels (div.aucteeno-tab-panel) for each tab
	 * 4. Dynamic action hooks (`aucteeno_product_tab_{$tab_key}`) for content rendering
	 *
	 * The first tab in the array is set as active by default. Tab switching
	 * is handled by JavaScript (admin-tabs.js).
	 *
	 * Developers can:
	 * - Modify tabs via `aucteeno_product_panel_tabs` filter
	 * - Add content via `aucteeno_product_tab_{$tab_key}` action hooks
	 *
	 * @return void
	 */
	public function render_product_panel(): void {
		global $post;

		$post_id   = $post ? $post->ID : 0;
		$post_type = $post ? $post->post_type : '';

		// Get default tabs and allow filtering.
		$default_tabs = $this->get_default_tabs();
		$tabs         = apply_filters( 'aucteeno_product_panel_tabs', $default_tabs, $post_id, $post_type );

		// Validate tabs array.
		if ( ! is_array( $tabs ) || empty( $tabs ) ) {
			$tabs = $default_tabs;
		}

		// Get first tab key as default active tab.
		$active_tab = array_key_first( $tabs );

		$aucteeno_product_data_classes = array(
			'aucteeno-product-data',
			'panel',
			'woocommerce_options_panel',
		);
		$aucteeno_product_data_classes = array_merge( $aucteeno_product_data_classes, $this->get_tab_classes() );
		$aucteeno_product_data_classes_string = implode( ' ', $aucteeno_product_data_classes );
		?>
		<div id="aucteeno_product_data" class="<?php echo esc_attr( $aucteeno_product_data_classes_string ); ?>">
			<div class="aucteeno-tabs-wrapper">
				<ul class="aucteeno-tabs" role="tablist">
					<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
						<?php
						$tab_id     = 'aucteeno-tab-' . esc_attr( $tab_key );
						$panel_id   = 'aucteeno-tab-panel-' . esc_attr( $tab_key );
						$is_active  = $tab_key === $active_tab ? 'active' : '';
						$aria_selected = $tab_key === $active_tab ? 'true' : 'false';
						?>
						<li class="aucteeno-tab <?php echo esc_attr( $is_active ); ?>">
							<a 
								href="#<?php echo esc_attr( $panel_id ); ?>"
								id="<?php echo esc_attr( $tab_id ); ?>"
								class="aucteeno-tab-link"
								role="tab"
								aria-selected="<?php echo esc_attr( $aria_selected ); ?>"
								aria-controls="<?php echo esc_attr( $panel_id ); ?>"
								data-tab="<?php echo esc_attr( $tab_key ); ?>"
							>
								<?php echo esc_html( $tab_label ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<?php
					$panel_id    = 'aucteeno-tab-panel-' . esc_attr( $tab_key );
					$is_active   = $tab_key === $active_tab ? 'active' : '';
					$display     = $tab_key === $active_tab ? 'block' : 'none';
					?>
					<div 
						id="<?php echo esc_attr( $panel_id ); ?>"
						class="aucteeno-tab-panel <?php echo esc_attr( $is_active ); ?>"
						role="tabpanel"
						aria-labelledby="aucteeno-tab-<?php echo esc_attr( $tab_key ); ?>"
						style="display: <?php echo esc_attr( $display ); ?>;"
					>
						<?php
						// Call action hook for this tab.
						do_action( 'aucteeno_product_tab_' . $tab_key, $post_id, $post_type );
						?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}

