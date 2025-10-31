<?php

namespace Papaki\PiraeusBank\WooCommerce;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Application {
    private $entrypoint_path;

    public function __construct( $entrypoint ) {
        $this->entrypoint_path = $entrypoint;

        $this->init();

        add_action( 'init', [ $this, 'load_languages' ] );
        add_filter( 'woocommerce_states', [ $this, 'piraeus_woocommerce_states' ] );
        add_action( 'before_woocommerce_init', [ $this, 'declare_transactions' ] );

        $checkout_block = new Checkout_Block( $entrypoint );
        $checkout_block->init();
    }

    public function init() {
        add_action( 'wp', [ $this, 'piraeusbank_message' ] );
        add_filter( 'woocommerce_payment_gateways', [ $this, 'woocommerce_add_piraeusbank_gateway' ] );
        add_filter( 'plugin_action_links', [ $this, 'piraeusbank_plugin_action_links' ], 10, 2 );
    }

    public function load_languages() {
        load_plugin_textdomain( 'woo-payment-gateway-for-piraeus-bank', false, dirname( plugin_basename( __FILE__ ) ) . '/../languages/' );
    }

    public function piraeusbank_message() {
        $order_id = absint( get_query_var( 'order-received' ) );
        $order    = new \WC_Order( $order_id );
        if ( method_exists( $order, 'get_payment_method' ) ) {
            $payment_method = $order->get_payment_method();
        } else {
            $payment_method = $order->payment_method;
        }

        if ( is_order_received_page() && ( 'piraeusbank_gateway' == $payment_method ) ) {

            $piraeusbank_message = '';
            if ( method_exists( $order, 'get_meta' ) ) {
                $piraeusbank_message = $order->get_meta( '_piraeusbank_message', true );
            } else {
                $piraeusbank_message = get_post_meta( $order_id, '_piraeusbank_message', true );
            }
            if ( ! empty( $piraeusbank_message ) ) {
                $message      = $piraeusbank_message['message'];
                $message_type = $piraeusbank_message['message_type'];
                if ( method_exists( $order, 'delete_meta_data' ) ) {
                    $order->delete_meta_data( '_piraeusbank_message' );
                    $order->save_meta_data();
                } else {
                    delete_post_meta( $order_id, '_piraeusbank_message' );
                }

                wc_add_notice( $message, $message_type );
            }
        }
    }

    public function woocommerce_add_piraeusbank_gateway( $methods ) {
        $methods[] = '\Papaki\PiraeusBank\WooCommerce\WC_Piraeusbank_Gateway';
        return $methods;
    }

    public function piraeusbank_plugin_action_links( $links, $file ) {
        static $this_plugin;

        if ( ! $this_plugin ) {
            $this_plugin = plugin_basename( __FILE__ );
        }

        if ( $file == $this_plugin ) {
            $settings_link = '<a href="' . get_bloginfo( 'wpurl' ) . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=WC_Piraeusbank_Gateway">Settings</a>';
            array_unshift( $links, $settings_link );
        }
        return $links;
    }

    public function piraeus_woocommerce_states( $states ) {
        $states['CY'] = array(
            '04' => __( 'Ammochostos', 'woo-payment-gateway-for-piraeus-bank' ),
            '06' => __( 'Keryneia', 'woo-payment-gateway-for-piraeus-bank' ),
            '03' => __( 'Larnaka', 'woo-payment-gateway-for-piraeus-bank' ),
            '01' => __( 'Lefkosia', 'woo-payment-gateway-for-piraeus-bank' ),
            '02' => __( 'Lemesos', 'woo-payment-gateway-for-piraeus-bank' ),
            '05' => __( 'Pafos', 'woo-payment-gateway-for-piraeus-bank' ),
        );
        $states['DE'] = array(
            'BW' => __( 'Baden-Württemberg', 'woo-payment-gateway-for-piraeus-bank' ),
            'BY' => __( 'Bayern', 'woo-payment-gateway-for-piraeus-bank' ),
            'BE' => __( 'Berlin', 'woo-payment-gateway-for-piraeus-bank' ),
            'BB' => __( 'Brandenburg', 'woo-payment-gateway-for-piraeus-bank' ),
            'HB' => __( 'Bremen', 'woo-payment-gateway-for-piraeus-bank' ),
            'HH' => __( 'Hamburg', 'woo-payment-gateway-for-piraeus-bank' ),
            'HE' => __( 'Hessen', 'woo-payment-gateway-for-piraeus-bank' ),
            'MV' => __( 'Mecklenburg-Vorpommern', 'woo-payment-gateway-for-piraeus-bank' ),
            'NI' => __( 'Niedersachsen', 'woo-payment-gateway-for-piraeus-bank' ),
            'NW' => __( 'Nordrhein-Westfalen', 'woo-payment-gateway-for-piraeus-bank' ),
            'RP' => __( 'Rheinland-Pfalz', 'woo-payment-gateway-for-piraeus-bank' ),
            'SL' => __( 'Saarland', 'woo-payment-gateway-for-piraeus-bank' ),
            'SN' => __( 'Sachsen', 'woo-payment-gateway-for-piraeus-bank' ),
            'ST' => __( 'Sachsen-Anhalt', 'woo-payment-gateway-for-piraeus-bank' ),
            'SH' => __( 'Schleswig-Holstein', 'woo-payment-gateway-for-piraeus-bank' ),
            'TH' => __( 'Thüringen', 'woo-payment-gateway-for-piraeus-bank' ),
        );
        // __('Piraeus Bank Gateway', 'woo-payment-gateway-for-piraeus-bank')
        return $states;
    }

    /**
     * Custom function to declare compatibility with piraeusbank_transactions feature
     */
    public function declare_transactions() {
        global $wpdb;

        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( $wpdb->prefix . 'piraeusbank_transactions', $this->entrypoint_path, true );
        }
    }
}
