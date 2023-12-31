<?php
/**
 * Handle sellkit admin notice.
 *
 * @since 2.0.6
 *
 * @package JupiterX\Framework\Admin\Notices
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sellkit admin notice class.
 *
 * @since 2.0.6
 *
 * @package JupiterX\Framework\Admin\Notices
 */
class JupiterX_Sellkit_Admin_Notice {
	/**
	 * Current user.
	 *
	 * @var WP_User
	 */
	public $user;

	/**
	 * Meta key.
	 */
	const META_KEY = 'sellkit_install_noctice';

	/**
	 * Constructor.
	 *
	 * @since 2.0.6
	 */
	public function __construct() {
		$this->user = wp_get_current_user();

		add_action( 'admin_notices', [ $this, 'check_plugins' ] );
		add_action( 'wp_ajax_jupiterx_install_sellkit_in_notice', [ $this, 'install_plugins' ] );
		add_action( 'wp_ajax_jupiterx_dismiss_sellkit_notice', [ $this, 'dismiss_notice' ] );
	}

	/**
	 * Check the plugins and conditions to run notice.
	 *
	 * @since 2.0.6
	 */
	public function check_plugins() {
		$sellkit_installed_time = get_option( 'sellkit-installed-time', '' );
		$day                    = 24 * 3600;

		// Escape for old users. they already saw or dismissed this. or it's still there.
		// Do not show notice if user installed sellkit in less than 24 hours.
		if ( ! empty( $sellkit_installed_time ) && time() - $sellkit_installed_time < $day ) {
			return;
		}

		if (
			! function_exists( 'WC' ) ||
			class_exists( 'Sellkit_Pro' ) ||
			! jupiterx_is_pro() ||
			strval( 1 ) === get_user_meta( $this->user->ID, self::META_KEY . '_dismissed', true )
		) {
			return;
		}

		// Show the notice only if Jupiter X Core, Elementor and Sellkit are activated.
		$plugins          = jupiterx_get_inactive_required_plugins();
		$required_plugins = [ 'jupiterx-core', 'sellkit', 'elementor' ];

		foreach ( $plugins as $plugin ) {
			if ( in_array( $plugin['slug'], $required_plugins, true ) ) {
				return;
			}
		}

		$nonce = wp_create_nonce( 'jupiterx_install_sellkit_in_notice_nonce' );

		$this->get_notice( $nonce );
	}

	/**
	 * Fetch data on click.
	 *
	 * @since 2.0.6
	 */
	public function install_plugins() {
		if ( ! current_user_can( 'edit_others_posts' ) || ! current_user_can( 'edit_others_pages' ) ) {
			wp_send_json_error( 'You do not have access to this section', 'jupiterx' );
		}

		$plugins = [
			'sellkit' => [
				'sellkit/sellkit.php',
				'https://downloads.wordpress.org/plugin/sellkit.latest-stable.zip',
			],
			'sellkit-pro' => [
				'sellkit-pro/sellkit-pro.php',
				get_transient( 'jupiterx_sellkit_pro_link' ),
			],
		];

		foreach ( $plugins as $plugin ) {
			$install = null;

			if ( ! $this->check_is_installed( $plugin[0] ) ) {
				$install = $this->install_plugin( $plugin[1] );
			}

			if ( ! is_wp_error( $install ) && $install ) {
				activate_plugin( $plugin[0] );
			}

			if ( $this->check_is_installed( $plugin[0] ) && ! is_plugin_active( $plugin[0] ) ) {
				activate_plugin( $plugin[0] );
			}
		}

		wp_send_json_success();
	}

	/**
	 * Dismiss notice.
	 *
	 * @since 2.0.6
	 * @return void
	 */
	public function dismiss_notice() {
		if ( ! current_user_can( 'edit_others_posts' ) || ! current_user_can( 'edit_others_pages' ) ) {
			wp_send_json_error( 'You do not have access to this section', 'jupiterx' );
		}

		check_ajax_referer( 'jupiterx_install_sellkit_in_notice_nonce' );

		update_user_meta( $this->user->ID, self::META_KEY . '_dismissed', 1 );

		wp_send_json_success();
	}

	/**
	 * Install plugin.
	 *
	 * @param string $plugin_zip download link of the plugin.
	 * @since 2.0.6
	 */
	private function install_plugin( $plugin_zip ) {
		if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		$upgrader  = new Plugin_Upgrader();
		$installed = $upgrader->install( $plugin_zip );

		return $installed;
	}

	/**
	 * Install plugin.
	 *
	 * @param string $base plugin base path.
	 * @since 2.0.6
	 */
	private function check_is_installed( $base ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		if ( ! empty( $all_plugins[ $base ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get Notice.
	 *
	 * @param string $nonce ajax nonce.
	 * @since 2.0.6
	 */
	private function get_notice( $nonce ) {
		$information = [
			'icons' => [
				'title'     => 'circle-exclamation-solid.svg',
				'blue-tick' => 'circle-check-solid.svg',
			],
			'list' => [
				esc_html__( 'Express Checkout', 'jupiterx' ),
				esc_html__( 'Variation Swatches', 'jupiterx' ),
				esc_html__( 'Auto-Populate & Auto-Complete Fields', 'jupiterx' ),
				esc_html__( 'Smart Coupons', 'jupiterx' ),
				esc_html__( 'Checkout Expiry', 'jupiterx' ),
				esc_html__( 'Dynamic Discounts', 'jupiterx' ),
				esc_html__( 'Signup With A Checkbox', 'jupiterx' ),
				esc_html__( 'Checkout Notices (FOMO, BOGO,...)', 'jupiterx' ),
			],
			'unbundled' => [
				'cta' => 'https://getsellkit.com/pricing/?utm_source=pro-plugin-promotion-banner-to-free-users&utm_medium=wp-dashboard&utm_campaign=upgrade-to-pro',
				'btn' => esc_html__( 'Yes, I want to boost sales', 'jupiterx' ),
			],
			'bundled' => [
				'btn' => esc_html__( 'Install Plugin Now', 'jupiterx' ),
			],
		];

		?>
		<div data-nonce="<?php echo esc_attr( $nonce ); ?>" class="sellkit-notice-in-jupiterx notice is-dismissible">
			<p class="sellkit-notice-heading">
				<img src="<?php echo esc_url( JUPITERX_ADMIN_ASSETS_URL . 'images/sellkit-notice/' . $information['icons']['title'] ); ?>" >
				<?php
					echo esc_html__( 'Unlock advanced WooCommerce features with SellKit Pro', 'jupiterx' );
				?>
			</p>
			<div class="sellkit-notice-body">
				<ul>
					<?php
					foreach ( $information['list'] as $item ) {
						$content = sprintf(
							'<li><img src="%1$s">%2$s</li>',
							esc_url( JUPITERX_ADMIN_ASSETS_URL . 'images/sellkit-notice/' . $information['icons']['blue-tick'] ),
							$item
						);

						echo wp_kses_post( $content );
					}
					?>
				</ul>
			</div>
			<div class="sellkit-notice-footer">
				<div class="sellkit-notice-buttons-wrapper">
					<?php
						$text = $information['bundled']['btn'];
						$link = '#';

						if ( ! defined( 'SELLKIT_BUNDLED' ) ) {
							$text = $information['unbundled']['btn'];
							$link = $information['unbundled']['cta'];
						}
					?>
					<a class="button button-primary jupiterx-notice-install-sellkit" href="<?php echo esc_url( $link ); ?>">
						<?php echo esc_html( $text ); ?>
					</a>
					<a class="button jupiterx-dismiss-sellkit-notice" href="#"><?php esc_html_e( 'No, Maybe later', 'jupiterx' ); ?></a>
				</div>
				<span>
					<?php
					if ( defined( 'SELLKIT_BUNDLED' ) ) {
						printf(
							/* translators: The sellkit notice. */
							wp_kses_post( '%1$s %2$s For Jupiter X Users', 'jupiterx' ),
							'<del>' . esc_html__( '$199/year', 'jupiterx' ) . '</del>',
							'<b>100% Free </b> '
						);
					}
					?>
				</span>
			</div>
		</div>
		<?php
	}
}

new JupiterX_Sellkit_Admin_Notice();
