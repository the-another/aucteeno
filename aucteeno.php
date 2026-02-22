<?php
/**
 * Plugin Name: Aucteeno
 * Plugin URI: https://theanother.org/plugin/aucteeno/
 * Description: Custom WooCommerce plugin for auction and item management with high-performance database tables and REST API for auction and item management.
 * Version: 1.0.6
 * Author: The Another
 * Author URI: https://theanother.org
 * Requires at least: 6.9
 * Requires PHP: 8.3
 * Text Domain: aucteeno
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: https://github.com/the-another/aucteeno
 * Primary Branch: master
 * Release Asset: true
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno;

// Exit if accessed directly.
use Exception;
use TheAnother\Plugin\Aucteeno\Installer\Install;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'AUCTEENO_VERSION', '1.0.6' );
define( 'AUCTEENO_PLUGIN_FILE', __FILE__ );
define( 'AUCTEENO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AUCTEENO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AUCTEENO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Minimum PHP version check.
if ( version_compare( PHP_VERSION, '8.3', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			// Use plain string to avoid triggering early textdomain loading.
			?>
			<div class="notice notice-error">
				<p><?php echo esc_html( 'Aucteeno requires PHP 8.3 or higher. Please upgrade your PHP version.' ); ?></p>
			</div>
			<?php
		}
	);
	return;
}

// Minimum WordPress version check.
if ( version_compare( get_bloginfo( 'version' ), '6.9', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			// Use plain string to avoid triggering early textdomain loading.
			?>
			<div class="notice notice-error">
				<p><?php echo esc_html( 'Aucteeno requires WordPress 6.9 or higher. Please upgrade WordPress.' ); ?></p>
			</div>
			<?php
		}
	);
	return;
}

// Load autoloader.
require_once AUCTEENO_PLUGIN_DIR . 'vendor/autoload.php';

// Load plugin textdomain on init hook with early priority (required for WordPress 6.7.0+).
// This ensures translations are loaded before any translation functions are called.
add_action(
	'init',
	function () {
		load_plugin_textdomain(
			'aucteeno',
			false,
			dirname( AUCTEENO_PLUGIN_BASENAME ) . '/languages'
		);
	},
	1 // Early priority to load translations as soon as init fires.
);

// Hook into WooCommerce initialization.
// Note: We ensure translations are loaded on init before this callback executes.
add_action(
	'before_woocommerce_init',
	function () {
		try {
			Aucteeno::get_instance()->start();
		} catch ( Exception $e ) {
			// Use plain string for error title to avoid translation issues during fatal errors.
			wp_die(
				esc_html( $e->getMessage() ),
				'Aucteeno Error',
				array( 'response' => 500 )
			);
		}
	}
);

// Register activation hook for database table creation and rewrite rules.
register_activation_hook(
	AUCTEENO_PLUGIN_FILE,
	function () {
		Install::run();
	}
);

// Register deactivation hook to flush rewrite rules.
register_deactivation_hook(
	AUCTEENO_PLUGIN_FILE,
	function () {
		Permalinks\Auction_Item_Permalinks::deactivate();
	}
);


