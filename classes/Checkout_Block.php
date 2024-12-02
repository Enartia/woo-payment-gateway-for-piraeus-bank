<?php

require_once __DIR__ . '/../functions.php';

class Checkout_Block {
	protected const PLUGIN_NAMESPACE = 'woo-payment-gateway-for-piraeus-bank';
	private $entrypoint_path;
	
	public function __construct( $entrypoint ) {
		$this->entrypoint_path = $entrypoint;
	}
	
	public function init() {
		add_action( 'before_woocommerce_init', [ $this, 'declare_cart_checkout_blocks_compatibility' ] );
		add_action( 'woocommerce_blocks_loaded', [ $this, 'woo_register_order_approval_payment_method_type' ] );
		add_action( 'woocommerce_init', [ $this, 'woo_checkout_block_additional_fields' ] );
		add_action( 'woocommerce_set_additional_field_value', [ $this, 'set_additional_field_value' ], 10, 4 );
	}
	
	public function woo_checkout_block_additional_fields() {
		$gateway = new WC_Piraeusbank_Gateway();
		
		$pb_cardholder_name = $gateway->get_option( 'pb_cardholder_name' );
		
		if ( $pb_cardholder_name !== 'yes' ) {
			return;
		}
		
		woocommerce_register_additional_checkout_field(
			[
				'id'         => self::PLUGIN_NAMESPACE . '/card-holder',
				'label'      => __( 'Cardholder Name', self::PLUGIN_NAMESPACE ),
				'location'   => 'order',
				'required'   => false,
				'attributes' => [
					'autocomplete' => 'card-holder',
					'data-custom'  => 'custom data',
				],
			],
		);
	}
	
	/**
	 * Custom function to declare compatibility with cart_checkout_blocks feature
	 */
	public function declare_cart_checkout_blocks_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', $this->entrypoint_path, true );
		}
	}
	
	/**
	 * Custom function to register a payment method type
	 */
	public function woo_register_order_approval_payment_method_type() {
		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}
		
		require_once plugin_dir_path( __FILE__ ) . '/WC_Piraeusbank_Gateway_Checkout_Block.php';
		
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				// Register an instance of WC_Phonepe_Blocks
				$payment_method_registry->register( new WC_Piraeusbank_Gateway_Checkout_Block );
			}
		);
	}
	
	public function set_additional_field_value( $key, $value, $group, $wc_object ) {
		if ( self::PLUGIN_NAMESPACE . '/card-holder' !== $key ) {
			return;
		}
		
		update_post_meta( $wc_object->get_id(), 'cardholder_name', $value );
	}
}