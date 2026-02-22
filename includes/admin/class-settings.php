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

		// Register default general tab panel action.
		$this->hook_manager->register_action(
			'aucteeno_settings_tab_panel_general',
			array( $this, 'render_general_tab_panel' )
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

		// Get tabs from filter.
		$tabs = apply_filters( 'aucteeno_settings_tabs', array( 'general' => __( 'General', 'aucteeno' ) ) );

		// Get active tab from query parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab parameter is used for display only, not for data modification.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		if ( empty( $active_tab ) || ! isset( $tabs[ $active_tab ] ) ) {
			$active_tab = array_key_first( $tabs );
		}

		$page_url = admin_url( 'options-general.php?page=aucteeno-settings' );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( count( $tabs ) > 1 ) : ?>
				<nav class="nav-tab-wrapper">
					<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_key, $page_url ) ); ?>" 
							class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
							<?php echo esc_html( $tab_label ); ?>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'aucteeno_settings' );
				do_settings_sections( 'aucteeno_settings' );
				?>

			<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
				<div class="aucteeno-settings-tab-panel aucteeno-settings-tab-panel-<?php echo esc_attr( $tab_key ); ?> <?php echo $active_tab === $tab_key ? 'active' : ''; ?>" 
					<?php echo $active_tab !== $tab_key ? 'style="display: none;"' : ''; ?>>
					<?php
					// Fire action hook for this tab.
					do_action( "aucteeno_settings_tab_panel_{$tab_key}" );
					?>
				</div>
			<?php endforeach; ?>

			<?php
			// Allow tabs to specify if they need the submit button (default: true).
			$show_submit_button = apply_filters( "aucteeno_settings_tab_show_submit_{$active_tab}", true );
			if ( $show_submit_button ) {
				submit_button();
			}
			?>
		</form>
		</div>
		<?php
	}

	/**
	 * Render general tab panel content.
	 *
	 * @return void
	 */
	public function render_general_tab_panel(): void {
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

