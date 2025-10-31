<?php

namespace Papaki\PiraeusBank\WooCommerce;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {

    final class WC_Piraeusbank_Gateway_Checkout_Block extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
        private $gateway;
        protected $name = 'piraeusbank_gateway';

        // your payment gateway name

        public function initialize() {
            $this->gateway = new WC_Piraeusbank_Gateway();
        }

        public function is_active() {
            return $this->gateway->is_available();
        }

        public function get_payment_method_script_handles() {
            $handle = $this->name . '_gc-blocks-integration';

            wp_register_script(
                $handle,
                plugin_dir_url( __FILE__ ) . '../assets/js/blocks/checkout.js',
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

            if ( function_exists( 'wp_set_script_translations' ) ) {
                wp_set_script_translations( $handle, 'woo-payment-gateway-for-piraeus-bank', dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

            }
            return [ $handle ];
        }

        public function get_payment_method_data() {
            $data = [
                'title'       => $this->gateway->title,
                'description' => $this->gateway->description,
            ];

            if ( ! empty( $this->gateway->icon ) ) {
                $data['icon'] = $this->gateway->icon;
            }

            return $data;
        }
    }

} else {

    final class WC_Piraeusbank_Gateway_Checkout_Block {
        public function __construct() {
            // Do nothing - blocks not supported
        }
    }

}
