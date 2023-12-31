<?php
namespace JupiterX_Core\Raven\Modules\Shopping_Cart;

defined( 'ABSPATH' ) || die();

use JupiterX_Core\Raven\Base\Module_base;
use Elementor\Plugin as Elementor;
use ElementorPro\Modules\ThemeBuilder\Module as elementorProModule;

class Module extends Module_Base {

	public static function is_active() {
		return function_exists( 'WC' ) && defined( 'JUPITERX_VERSION' ) && defined( 'JUPITERX_API' );
	}

	public function get_widgets() {
		return [ 'shopping-cart' ];
	}

	public function __construct() {
		parent::__construct();

		// update cart count using ajax while adding items from cart using add to cart button or removing them from quick cart.
		add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'menu_cart_fragments' ] );
		add_action( 'wc_ajax_raven_shopping_cart_single_insert_to_cart', [ $this, 'ajax_add_to_cart' ] );
	}

	public function ajax_add_to_cart() {
		check_ajax_referer( 'jupiterx-core-raven', 'nonce' );

		$product_id   = filter_input( INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT );
		$quantity     = filter_input( INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_INT );
		$variation_id = filter_input( INPUT_POST, 'variation_id', FILTER_SANITIZE_NUMBER_INT );
		$variations   = filter_input( INPUT_POST, 'variations', FILTER_DEFAULT );

		if ( empty( $product_id ) ) {
			return;
		}

		$product_id        = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $product_id ) );
		$quantity          = empty( $quantity ) ? 1 : wc_stock_amount( $quantity );
		$variation_id      = ! empty( $variation_id ) ? absint( $variation_id ) : 0;
		$variations        = ! empty( $variations ) ? array_map( 'sanitize_text_field', json_decode( stripslashes( $variations ), true ) ) : [];
		$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations );
		$product_status    = get_post_status( $product_id );

		if ( empty( $variations ) && $passed_validation && false !== WC()->cart->add_to_cart( $product_id, $quantity, $variation_id ) && 'publish' === $product_status ) {
			do_action( 'woocommerce_ajax_added_to_cart', $product_id );

			if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
				wc_add_to_cart_message( [ $product_id => $quantity ], true );
			}

			\WC_AJAX::get_refreshed_fragments();

			wp_send_json_success();
		}

		if ( ! empty( $variations ) ) {
			\WC_AJAX::get_refreshed_fragments();

			wp_send_json_success();
		}

		wp_send_json( [
			'error' => true,
			'product_url' => apply_filters( 'woocommerce_cart_redirect_after_error', get_permalink( $product_id ), $product_id ),
		] );
	}

	public static function render_mini_cart() {
		ob_start();

		jupiterx_core()->load_files(
			[
				'extensions/raven/includes/modules/shopping-cart/template/mini-cart',
			]
		);

		$template_path = jupiterx_core()->plugin_dir() . 'extensions/raven/includes/modules/shopping-cart/template';

		get_template_part( $template_path, 'mini-cart', [] );

		return ob_get_clean();
	}

	public function menu_cart_fragments( $fragments ) {
		$has_raven_shopping_cart = filter_input( INPUT_POST, 'raven_shopping_cart', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( empty( $has_raven_shopping_cart ) ) {
			return $fragments;
		}

		$has_cart = is_a( WC()->cart, 'WC_Cart' );

		$fragments['div.widget_shopping_cart_content'] = '<div class="widget_shopping_cart_content">' . self::render_mini_cart() . '</div>';

		if ( ! $has_cart ) {
			return $fragments;
		}

		$product_count = WC()->cart->get_cart_contents_count();

		ob_start();
		?>
		<span class="raven-shopping-cart-count"><?php echo $product_count; ?></span>
		<?php
		$cart_count_html = ob_get_clean();

		if ( ! empty( $cart_count_html ) ) {
			$fragments['body:not(.elementor-editor-active) div.elementor-element.elementor-widget.elementor-widget-raven-shopping-cart .raven-shopping-cart-count'] = $cart_count_html;
		}

		return $fragments;
	}
}
