<?php

require_once 'Checkout_Block.php';
require_once __DIR__ . '/../functions.php';

class Application {
	private $entrypoint_path;

	public function __construct( $entrypoint ) {
		add_action( 'plugins_loaded', [ $this, 'init' ], 0 );
		add_action( 'init', [ $this, 'load_languages' ] );
		add_filter( 'woocommerce_states', 'piraeus_woocommerce_states' );

		$this->entrypoint_path = $entrypoint;

		add_action( 'before_woocommerce_init', [ $this, 'declare_transactions' ] );

		$checkout_block = new Checkout_Block( $entrypoint );

		$checkout_block->init();
	}

	public function init() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		require_once 'WC_Piraeusbank_Gateway.php';

		//See functions.php; move these?
		add_action( 'wp', 'piraeusbank_message' );
		add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_piraeusbank_gateway' );
		add_filter( 'plugin_action_links', 'piraeusbank_plugin_action_links', 10, 2 );
	}

	public function load_languages() {
		load_plugin_textdomain( 'woo-payment-gateway-for-piraeus-bank', false, dirname( plugin_basename( __FILE__ ) ) . '/../languages/' );
	}

	/**
	 * Custom function to declare compatibility with piraeusbank_transactions feature
	 */
	public function declare_transactions() {
		global $wpdb;

		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( $wpdb->prefix . 'piraeusbank_transactions', $this->entrypoint_path, true );
		}
	}
}
