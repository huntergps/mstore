<?php
/**
 * WooCommerce Payment Gateway Framework
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the plugin to newer
 * versions in the future. If you wish to customize the plugin for your
 * needs please refer to http://www.skyverge.com
 *
 * @package   SkyVerge/WooCommerce/Payment-Gateway/Apple-Pay
 * @author    SkyVerge
 * @copyright Copyright (c) 2013-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\Plugin_Framework;

defined( 'ABSPATH' ) or exit;

if ( ! class_exists( '\\WC_Braintree\\Plugin_Framework\\SV_WC_Payment_Gateway_Apple_Pay_Frontend' ) ) :

/**
 * Sets up the Apple Pay front-end functionality.
 *
 * @since 4.7.0
 */
class SV_WC_Payment_Gateway_Apple_Pay_Frontend {


	/** @var SV_WC_Payment_Gateway_Plugin $plugin the gateway plugin instance */
	protected $plugin;

	/** @var SV_WC_Payment_Gateway_Apple_Pay $handler the Apple Pay handler instance */
	protected $handler;

	/** @var SV_WC_Payment_Gateway $gateway the gateway instance */
	protected $gateway;


	/**
	 * Constructs the class.
	 *
	 * @since 4.7.0
	 *
	 * @param SV_WC_Payment_Gateway_Plugin $plugin the gateway plugin instance
	 * @param SV_WC_Payment_Gateway_Apple_Pay $handler the Apple Pay handler instance
	 */
	public function __construct( SV_WC_Payment_Gateway_Plugin $plugin, SV_WC_Payment_Gateway_Apple_Pay $handler ) {

		$this->plugin = $plugin;

		$this->handler = $handler;

		$this->gateway = $this->get_handler()->get_processing_gateway();

		if ( $this->get_handler()->is_available() ) {

			add_action( 'wp', array( $this, 'init' ) );

			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}
	}


	/**
	 * Initializes the scripts and hooks.
	 *
	 * @since 4.7.0
	 */
	public function init() {

		$locations = $this->get_display_locations();

		if ( is_product() && in_array( 'product', $locations, true ) ) {
			$this->init_product();
		} else if ( is_cart() && in_array( 'cart', $locations, true ) ) {
			$this->init_cart();
		} else if ( is_checkout() && in_array( 'checkout', $locations, true ) ) {
			$this->init_checkout();
		}
	}


	/**
	 * Gets the configured display locations.
	 *
	 * @since 4.7.0
	 *
	 * @return array
	 */
	protected function get_display_locations() {

		return get_option( 'sv_wc_apple_pay_display_locations', array() );
	}


	/**
	 * Enqueues the scripts.
	 *
	 * @since 4.7.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_style( 'sv-wc-apple-pay', $this->get_plugin()->get_payment_gateway_framework_assets_url() . '/css/frontend/sv-wc-payment-gateway-apple-pay.css', array(), $this->get_plugin()->get_version() ); // TODO: min

		wp_enqueue_script( 'sv-wc-apple-pay', $this->get_plugin()->get_payment_gateway_framework_assets_url() . '/js/frontend/sv-wc-payment-gateway-apple-pay.min.js', array( 'jquery' ), $this->get_plugin()->get_version(), true );
	}


	/**
	 * Gets the general JS handler params.
	 *
	 * @since 5.3.0
	 *
	 * @return array
	 */
	protected function get_js_handler_params() {

		/**
		 * Filters the Apple Pay JS handler params.
		 *
		 * @since 4.7.0
		 *
		 * @param array $params the JS params
		 */
		return (array) apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_apple_pay_js_handler_params', array(
			'gateway_id'               => $this->get_gateway()->get_id(),
			'gateway_id_dasherized'    => $this->get_gateway()->get_id_dasherized(),
			'merchant_id'              => $this->get_handler()->get_merchant_id(),
			'ajax_url'                 => admin_url( 'admin-ajax.php' ),
			'validate_nonce'           => wp_create_nonce( 'wc_' . $this->get_gateway()->get_id() . '_apple_pay_validate_merchant' ),
			'recalculate_totals_nonce' => wp_create_nonce( 'wc_' . $this->get_gateway()->get_id() . '_apple_pay_recalculate_totals' ),
			'process_nonce'            => wp_create_nonce( 'wc_' . $this->get_gateway()->get_id() . '_apple_pay_process_payment' ),
			'generic_error'            => __( 'An error occurred, please try again or try an alternate form of payment', 'woocommerce-gateway-paypal-powered-by-braintree' ),
		) );
	}


	/**
	 * Enqueues an Apple Pay JS handler.
	 *
	 * @since 5.3.0
	 *
	 * @param array $args handler arguments
	 * @param string $object_name JS object name
	 * @param string $handler_name handler class name
	 */
	protected function enqueue_js_handler( array $args, $object_name = '', $handler_name = '' ) {

		if ( ! $object_name ) {
			$object_name = 'wc_' . $this->get_gateway()->get_id() . '_apple_pay_handler';
		}

		if ( ! $handler_name ) {
			$handler_name = $this->get_js_handler_name();
		}

		$args = array_merge( $args, $this->get_js_handler_params() );

		wc_enqueue_js(
			sprintf(
				'window.%1$s = new %2$s(%3$s); window.%1$s.init()',
				esc_attr( $object_name ),
				esc_attr( $handler_name ),
				json_encode( $args )
			)
		);
	}


	/**
	 * Gets the JS handler class name.
	 *
	 * Concrete implementations can override this with their own handler.
	 *
	 * @since 5.3.0
	 *
	 * @return string
	 */
	protected function get_js_handler_name() {

		return 'SV_WC_Apple_Pay_Handler';
	}


	/**
	 * Renders an Apple Pay button.
	 *
	 * @since 4.7.0
	 */
	public function render_button() {

		$button_text = '';
		$classes     = array(
			'sv-wc-apple-pay-button',
		);

		switch ( $this->get_handler()->get_button_style() ) {

			case 'black':
				$classes[] = 'apple-pay-button-black';
			break;

			case 'white':
				$classes[] = 'apple-pay-button-white';
			break;

			case 'white-with-line':
				$classes[] = 'apple-pay-button-white-with-line';
			break;
		}

		// if on the single product page, add some text
		if ( is_product() ) {
			$classes[]   = 'apple-pay-button-buy-now';
			$button_text = __( 'Buy with', 'woocommerce-gateway-paypal-powered-by-braintree' );
		}

		if ( $button_text ) {
			$classes[] = 'apple-pay-button-with-text';
		}

		echo '<button class="' . implode( ' ', array_map( 'sanitize_html_class', $classes ) ) . '" lang="' . esc_attr( substr( get_locale(), 0, 2 ) ) . '">';

			if ( $button_text ) {
				echo '<span class="text">' . esc_html( $button_text ) . '</span><span class="logo"></span>';
			}

		echo '</button>';
	}


	/**
	 * Initializes Apple Pay on the single product page.
	 *
	 * @since 4.7.0
	 */
	public function init_product() {

		$product = wc_get_product( get_the_ID() );

		if ( ! $product ) {
			return;
		}

		$this->enqueue_js_handler( $this->get_product_js_handler_args( $product ) );

		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_button' ) );
	}


	/**
	 * Gets the args passed to the product JS handler.
	 *
	 * @since 5.3.0
	 *
	 * @param \WC_Product $product product object
	 * @return array
	 */
	protected function get_product_js_handler_args( \WC_Product $product ) {

		$args = array();

		try {

			$payment_request = $this->get_handler()->get_product_payment_request( $product );

			$args['payment_request'] = $payment_request;

		} catch ( SV_WC_Payment_Gateway_Exception $e ) {

			$this->get_handler()->log( 'Could not initialize Apple Pay. ' . $e->getMessage() );
		}

		/**
		 * Filters the Apple Pay product handler args.
		 *
		 * @since 4.7.0
		 * @deprecated 5.3.0
		 *
		 * @param array $args JS handler arguments
		 */
		$args = (array) apply_filters( 'sv_wc_apple_pay_product_handler_args', $args );

		/**
		 * Filters the gateway Apple Pay cart handler args.
		 *
		 * @since 5.3.0
		 *
		 * @param array $args JS handler arguments
		 * @param \WC_Product $product product object
		 */
		return (array) apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_apple_pay_product_js_handler_args', $args, $product );
	}


	/** Cart functionality ****************************************************/


	/**
	 * Initializes Apple Pay on the cart page.
	 *
	 * @since 4.7.0
	 */
	public function init_cart() {

		// bail if the cart is missing or empty
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$this->enqueue_js_handler( $this->get_cart_js_handler_args( WC()->cart ) );

		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'render_button' ) );
	}


	/**
	 * Gets the args passed to the cart JS handler.
	 *
	 * @since 5.3.0
	 *
	 * @param \WC_Cart $cart cart object
	 * @return array
	 */
	protected function get_cart_js_handler_args( \WC_Cart $cart ) {

		$args = array();

		try {

			$payment_request = $this->get_handler()->get_cart_payment_request( $cart );

			$args['payment_request'] = $payment_request;

		} catch ( SV_WC_Payment_Gateway_Exception $e ) {

			$args['payment_request'] = false;
		}

		/**
		 * Filters the Apple Pay cart handler args.
		 *
		 * @since 4.7.0
		 * @deprecated 5.3.0
		 *
		 * @param array $args JS handler arguments
		 */
		$args = (array) apply_filters( 'sv_wc_apple_pay_cart_handler_args', $args );

		/**
		 * Filters the gateway Apple Pay cart handler args.
		 *
		 * @since 5.3.0
		 *
		 * @param array $args JS handler arguments
		 * @param \WC_Cart $cart cart object
		 */
		return (array) apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_apple_pay_cart_js_handler_args', $args, $cart );
	}


	/** Checkout functionality ************************************************/


	/**
	 * Initializes Apple Pay on the checkout page.
	 *
	 * @since 4.7.0
	 */
	public function init_checkout() {

		$this->enqueue_js_handler( $this->get_checkout_js_handler_args() );

		if ( $this->get_plugin()->is_plugin_active( 'woocommerce-checkout-add-ons.php' ) ) {
			add_action( 'woocommerce_review_order_before_payment', array( $this, 'render_button' ) );
		} else {
			add_action( 'woocommerce_before_checkout_form', array( $this, 'render_checkout_button' ), 15 );
		}
	}


	/**
	 * Gets the args passed to the checkout JS handler.
	 *
	 * @since 5.3.0
	 *
	 * @return array
	 */
	protected function get_checkout_js_handler_args() {

		/**
		 * Filters the Apple Pay checkout handler args.
		 *
		 * @since 4.7.0
		 * @deprecated 5.3.0
		 *
		 * @param array $args JS handler arguments
		 */
		$args = (array) apply_filters( 'sv_wc_apple_pay_checkout_handler_args', array() );

		/**
		 * Filters the gateway Apple Pay checkout handler args.
		 *
		 * @since 5.3.0
		 *
		 * @param array $args JS handler arguments
		 */
		return (array) apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_apple_pay_checkout_js_handler_args', $args );
	}


	/**
	 * Renders the Apple Pay button for checkout.
	 *
	 * @since 4.7.0
	 */
	public function render_checkout_button() {

		?>

		<div class="sv-wc-apply-pay-checkout">

			<?php $this->render_button(); ?>

			<span class="divider">
				<?php /** translators: "or" as in "Pay with Apple Pay [or] regular checkout" */
				esc_html_e( 'or', 'woocommerce-gateway-paypal-powered-by-braintree' ); ?>
			</span>

		</div>

		<?php
	}


	/**
	 * Gets the gateway instance.
	 *
	 * @since 4.7.0
	 *
	 * @return SV_WC_Payment_Gateway
	 */
	protected function get_gateway() {

		return $this->gateway;
	}


	/**
	 * Gets the gateway plugin instance.
	 *
	 * @since 4.7.0
	 *
	 * @return SV_WC_Payment_Gateway_Plugin
	 */
	protected function get_plugin() {

		return $this->plugin;
	}

	/**
	 * Gets the Apple Pay handler instance.
	 *
	 * @since 4.7.0
	 *
	 * @return SV_WC_Payment_Gateway_Apple_Pay
	 */
	protected function get_handler() {

		return $this->handler;
	}


}

endif;
