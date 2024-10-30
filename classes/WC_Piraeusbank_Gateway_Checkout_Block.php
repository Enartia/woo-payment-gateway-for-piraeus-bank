<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Piraeusbank_Gateway_Checkout_Block extends AbstractPaymentMethodType {
	private $gateway;
	protected $name = 'piraeusbank_gateway';// your payment gateway name
	
	public function initialize() {
		$this->gateway = new WC_Piraeusbank_Gateway();
	}
	
	public function is_active() {
		return $this->gateway->is_available();
	}
	
	public function get_payment_method_script_handles() {
		wp_register_script(
			'gc-blocks-integration',
			plugin_dir_url(__FILE__) . '../assets/js/blocks/checkout.js',
			[
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			],
			null,
			true
		);
		
		if( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'gc-blocks-integration', 'woo-payment-gateway-for-piraeus-bank', dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
			
		}
		return [ 'gc-blocks-integration' ];
	}
	
	public function get_payment_method_data() {
		return [
			'title' => $this->gateway->title,
			'description' => $this->gateway->description,
		];
	}
	
}