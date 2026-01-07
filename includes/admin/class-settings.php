<?php
/**
 * Plugin Settings Class
 *
 * Handles plugin settings page and general terms and conditions.
 *
 * @package Aucteeno
 * @since 1.0.0
 */

namespace TheAnother\Plugin\Aucteeno\Admin;

use TheAnother\Plugin\Aucteeno\Hook_Manager;

/**
 * Class Settings
 *
 * Manages plugin settings.
 */
class Settings {

	/**
	 * Option name for general terms and conditions.
	 *
	 * @var string
	 */
	private const OPTION_TERMS = 'aucteeno_general_terms_conditions';

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
	 * Initialize settings.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->hook_manager->register_action(
			'admin_menu',
			array( $this, 'add_settings_page' )
		);
		$this->hook_manager->register_action(
			'admin_init',
			array( $this, 'register_settings' )
		);
	}

	/**
	 * Add settings page to admin menu.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'Aucteeno Settings', 'aucteeno' ),
			__( 'Aucteeno', 'aucteeno' ),
			'manage_options',
			'aucteeno-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'aucteeno_settings',
			self::OPTION_TERMS,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'default'           => '',
			)
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'aucteeno_settings' );
				do_settings_sections( 'aucteeno_settings' );
				?>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( self::OPTION_TERMS ); ?>">
								<?php esc_html_e( 'General Terms and Conditions', 'aucteeno' ); ?>
							</label>
						</th>
						<td>
							<?php
							wp_editor(
								get_option( self::OPTION_TERMS, '' ),
								self::OPTION_TERMS,
								array(
									'textarea_name' => self::OPTION_TERMS,
									'textarea_rows' => 10,
								)
							);
							?>
							<p class="description">
								<?php esc_html_e( 'General terms and conditions that apply to all auctions. Can be overridden per auction.', 'aucteeno' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Get general terms and conditions.
	 *
	 * @return string Terms and conditions text.
	 */
	public function get_general_terms(): string {
		return get_option( self::OPTION_TERMS, '' );
	}

	/**
	 * Get terms and conditions for an auction (with fallback).
	 *
	 * @param int $auction_id Auction post ID.
	 * @return string Terms and conditions text.
	 */
	public function get_auction_terms( int $auction_id ): string {
		$product = wc_get_product( $auction_id );
		if ( $product && method_exists( $product, 'get_terms_conditions' ) ) {
			$auction_terms = $product->get_terms_conditions();
			if ( ! empty( $auction_terms ) ) {
				return $auction_terms;
			}
		}

		return $this->get_general_terms();
	}
}

