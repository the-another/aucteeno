<?php
/**
 * PHPUnit bootstrap file for Aucteeno plugin tests.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

// Load BrainMonkey
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load Mockery
require_once dirname( __DIR__ ) . '/vendor/mockery/mockery/library/helpers.php';

// Set up BrainMonkey
use Brain\Monkey;
use Brain\Monkey\Functions;

Monkey\setUp();

// Define WordPress constants
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}

if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

// Common WordPress function stubs
Functions\when( 'plugin_dir_path' )->alias( function( $file ) {
	return dirname( $file ) . '/';
} );

Functions\when( 'plugin_dir_url' )->alias( function( $file ) {
	return 'http://example.org/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
} );

Functions\when( 'plugin_basename' )->alias( function( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
} );

Functions\when( 'esc_html__' )->returnArg();
Functions\when( 'esc_html_e' )->returnArg();
Functions\when( 'esc_attr__' )->returnArg();
Functions\when( 'esc_attr_e' )->returnArg();
Functions\when( '__' )->returnArg();
Functions\when( '_e' )->returnArg();

// WordPress utility functions - define if not exists.
if ( ! function_exists( 'absint' ) ) {
	/**
	 * Stub absint function.
	 *
	 * @param mixed $value Value to convert.
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Stub sanitize_text_field function.
	 *
	 * @param string $str String to sanitize.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		return $str;
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	/**
	 * Stub sanitize_textarea_field function.
	 *
	 * @param string $str String to sanitize.
	 * @return string
	 */
	function sanitize_textarea_field( $str ) {
		return $str;
	}
}

if ( ! function_exists( 'maybe_unserialize' ) ) {
	/**
	 * Stub maybe_unserialize function.
	 *
	 * @param mixed $original Value to unserialize.
	 * @return mixed
	 */
	function maybe_unserialize( $original ) {
		if ( is_serialized( $original ) ) {
			return unserialize( $original ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		}
		return $original;
	}
}

if ( ! function_exists( 'is_serialized' ) ) {
	/**
	 * Stub is_serialized function.
	 *
	 * @param mixed $data Data to check.
	 * @return bool
	 */
	function is_serialized( $data ) {
		if ( ! is_string( $data ) ) {
			return false;
		}
		$data = trim( $data );
		return ( 'N;' === $data || ( strlen( $data ) > 1 && in_array( $data[0], array( 'a', 'O', 's' ), true ) && ';' === substr( $data, -1 ) ) );
	}
}

// WordPress meta and taxonomy functions - these will be mocked in tests.
// Default implementations are provided here but can be overridden in tests.
Functions\when( 'get_post_meta' )->returnArg( 2 );
Functions\when( 'update_post_meta' )->justReturn( true );
Functions\when( 'wp_set_post_terms' )->justReturn( true );
Functions\when( 'wp_get_post_terms' )->justReturn( array() );
Functions\when( 'wp_slash' )->returnArg();
Functions\when( 'is_wp_error' )->justReturn( false );
Functions\when( 'wp_get_post_parent_id' )->justReturn( 0 );
Functions\when( 'wp_update_post' )->justReturn( true );
Functions\when( 'wp_delete_post' )->justReturn( true );
Functions\when( 'is_admin' )->justReturn( false );
Functions\when( 'register_rest_route' )->justReturn( true );
Functions\when( 'wp_cache_get' )->justReturn( false );
Functions\when( 'wp_cache_set' )->justReturn( true );
Functions\when( 'current_user_can' )->justReturn( true );
Functions\when( 'wc_get_product' )->justReturn( false );
Functions\when( 'get_terms' )->justReturn( array() );
Functions\when( 'get_term' )->justReturn( false );
Functions\when( 'wp_get_attachment_image_src' )->justReturn( false );
Functions\when( 'wp_get_post_terms' )->justReturn( array() );

// Stub WooCommerce classes needed for tests.
if ( ! class_exists( 'WC_Product' ) ) {
	/**
	 * Stub WC_Product class for testing.
	 */
	class WC_Product {
		protected $data_store;
		protected $changes = array();

		public function get_id() {
			return 0;
		}

		public function get_changes() {
			return $this->changes;
		}

		public function get_data_store() {
			return $this->data_store;
		}

		public function get_extra_data_keys() {
			return array();
		}
	}
}

if ( ! class_exists( 'WC_Product_External' ) ) {
	/**
	 * Stub WC_Product_External class for testing.
	 */
	class WC_Product_External extends WC_Product {
	}
}

if ( ! class_exists( 'WC_Data_Store_WP' ) ) {
	/**
	 * Stub WC_Data_Store_WP class for testing.
	 */
	class WC_Data_Store_WP {
	}
}

if ( ! class_exists( 'WC_Object_Data_Store_Interface' ) ) {
	/**
	 * Stub WC_Object_Data_Store_Interface for testing.
	 */
	interface WC_Object_Data_Store_Interface {
	}
}

if ( ! class_exists( 'WC_Product_Data_Store_Interface' ) ) {
	/**
	 * Stub WC_Product_Data_Store_Interface for testing.
	 */
	interface WC_Product_Data_Store_Interface {
	}
}

if ( ! class_exists( 'WC_Product_Data_Store_CPT' ) ) {
	/**
	 * Stub WC_Product_Data_Store_CPT class for testing.
	 */
	class WC_Product_Data_Store_CPT extends WC_Data_Store_WP implements WC_Object_Data_Store_Interface, WC_Product_Data_Store_Interface {
		/**
		 * Create a new product in the database.
		 *
		 * @param WC_Product $product Product object.
		 * @return void
		 */
		public function create( &$product ): void {
			// Stub implementation - does nothing in tests.
		}

		/**
		 * Update product data.
		 *
		 * @param WC_Product $product Product object.
		 * @return void
			*/
		public function update( &$product ): void {
			// Stub implementation - does nothing in tests.
		}

		/**
		 * Read product data.
		 *
		 * @param WC_Product $product Product object.
		 * @return void
			*/
		public function read( &$product ): void {
			// Stub implementation - does nothing in tests.
		}
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Stub WP_Error class for testing.
	 */
	class WP_Error {
		/**
		 * Error code.
		 *
		 * @var string
		 */
		public $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		public $message;

		/**
		 * Error data.
		 *
		 * @var mixed
		 */
		public $data;

		/**
		 * Error messages.
		 *
		 * @var array
		 */
		private $errors = array();

		/**
		 * Constructor.
		 *
		 * @param string|int $code Error code.
		 * @param string     $message Error message.
		 * @param mixed      $data Error data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
			if ( $code && $message ) {
				$this->errors[ $code ] = array( $message );
			}
		}

		/**
		 * Get error code.
		 *
		 * @return string Error code.
		 */
		public function get_error_code(): string {
			return $this->code;
		}

		/**
		 * Get error data.
		 *
		 * @param string $code Error code.
		 * @return mixed Error data.
		 */
		public function get_error_data( $code = '' ) {
			if ( is_array( $this->data ) && isset( $this->data['status'] ) ) {
				return $this->data;
			}
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	/**
	 * Stub WP_Query class for testing.
	 */
	class WP_Query {
		/**
		 * Query variables.
		 *
		 * @var array
		 */
		private $query_vars = array();

		/**
		 * Whether this is a singular query.
		 *
		 * @var bool
		 */
		public $is_singular = false;

		/**
		 * Posts array.
		 *
		 * @var array
		 */
		public $posts = array();

		/**
		 * Max number of pages.
		 *
		 * @var int
		 */
		public $max_num_pages = 0;

		/**
		 * Found posts count.
		 *
		 * @var int
		 */
		public $found_posts = 0;

		/**
		 * Get a query variable.
		 *
		 * @param string $key Query variable key.
		 * @param mixed  $default Default value.
		 * @return mixed Query variable value.
		 */
		public function get( $key, $default = false ) {
			return $this->query_vars[ $key ] ?? $default;
		}

		/**
		 * Set a query variable.
		 *
		 * @param string $key Query variable key.
		 * @param mixed  $value Query variable value.
		 * @return void
		 */
		public function set( $key, $value ): void {
			$this->query_vars[ $key ] = $value;
		}

		/**
		 * Check if this is a singular query.
		 *
		 * @return bool
		 */
		public function is_singular(): bool {
			return $this->is_singular;
		}

		/**
		 * Check if query has posts.
		 *
		 * @return bool
		 */
		public function have_posts(): bool {
			return ! empty( $this->posts );
		}

		/**
		 * Get the current post.
		 *
		 * @return void
		 */
		public function the_post(): void {
			// Stub implementation.
		}
	}
}

// WordPress REST API stubs.
if ( ! class_exists( 'WP_REST_Controller' ) ) {
	/**
	 * Stub WP_REST_Controller class for testing.
	 */
	class WP_REST_Controller {
		/**
		 * Namespace.
		 *
		 * @var string
		 */
		protected $namespace;

		/**
		 * Register routes.
		 *
		 * @return void
		 */
		public function register_routes(): void {
			// Stub implementation.
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Stub WP_REST_Request class for testing.
	 */
	class WP_REST_Request implements \ArrayAccess {
		/**
		 * Request parameters.
		 *
		 * @var array
		 */
		private $params = array();

		/**
		 * Get a parameter.
		 *
		 * @param string $key Parameter key.
		 * @param mixed  $default Default value.
		 * @return mixed Parameter value.
		 */
		public function get_param( $key, $default = null ) {
			return $this->params[ $key ] ?? $default;
		}

		/**
		 * Get JSON parameters.
		 *
		 * @return array JSON parameters.
		 */
		public function get_json_params(): array {
			return $this->params;
		}

		/**
		 * Array access for parameters.
		 *
		 * @param string $key Parameter key.
		 * @return mixed Parameter value.
		 */
		#[\ReturnTypeWillChange]
		public function offsetGet( $key ) {
			return $this->params[ $key ] ?? null;
		}

		/**
		 * Array access for setting parameters.
		 *
		 * @param string $key Parameter key.
		 * @param mixed  $value Parameter value.
		 * @return void
		 */
		#[\ReturnTypeWillChange]
		public function offsetSet( $key, $value ): void {
			$this->params[ $key ] = $value;
		}

		/**
		 * Array access for checking parameters.
		 *
		 * @param string $key Parameter key.
		 * @return bool
		 */
		#[\ReturnTypeWillChange]
		public function offsetExists( $key ): bool {
			return isset( $this->params[ $key ] );
		}

		/**
		 * Array access for unsetting parameters.
		 *
		 * @param string $key Parameter key.
		 * @return void
		 */
		#[\ReturnTypeWillChange]
		public function offsetUnset( $key ): void {
			unset( $this->params[ $key ] );
		}

		/**
		 * Magic method for array access.
		 *
		 * @param string $key Parameter key.
		 * @return mixed Parameter value.
		 */
		public function __get( $key ) {
			return $this->params[ $key ] ?? null;
		}

		/**
		 * Magic method for array access.
		 *
		 * @param string $key Parameter key.
		 * @param mixed  $value Parameter value.
		 * @return void
		 */
		public function __set( $key, $value ): void {
			$this->params[ $key ] = $value;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Stub WP_REST_Response class for testing.
	 */
	class WP_REST_Response {
		/**
		 * Response data.
		 *
		 * @var mixed
		 */
		protected $data;

		/**
		 * Response status.
		 *
		 * @var int
		 */
		protected $status;

		/**
		 * Constructor.
		 *
		 * @param mixed $data Response data.
		 * @param int   $status HTTP status code.
		 */
		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/**
		 * Get response data.
		 *
		 * @return mixed Response data.
		 */
		public function get_data() {
			return $this->data;
		}

		/**
		 * Get response status.
		 *
		 * @return int HTTP status code.
		 */
		public function get_status(): int {
			return $this->status;
		}
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	/**
	 * Stub WP_REST_Server class for testing.
	 */
	class WP_REST_Server {
		/**
		 * Readable method constant.
		 *
		 * @var string
		 */
		const READABLE = 'GET';

		/**
		 * Creatable method constant.
		 *
		 * @var string
		 */
		const CREATABLE = 'POST';

		/**
		 * Editable method constant.
		 *
		 * @var string
		 */
		const EDITABLE = 'PUT';

		/**
		 * Deletable method constant.
		 *
		 * @var string
		 */
		const DELETABLE = 'DELETE';
	}
}

// Repository classes have been removed - no longer needed.

// WooCommerce function stubs.
if ( ! function_exists( 'WC' ) ) {
	/**
	 * Stub WC function for testing.
	 *
	 * @return object Mock WooCommerce instance.
	 */
	function WC() {
		return (object) array();
	}
}

// Tear down BrainMonkey after tests
register_shutdown_function( function() {
	Monkey\tearDown();
} );

