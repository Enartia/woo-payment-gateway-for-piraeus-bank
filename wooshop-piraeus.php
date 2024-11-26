<?php
/*
  Plugin Name: Piraeus Bank WooCommerce Payment Gateway
  Plugin URI: https://www.papaki.com
  Description: Piraeus Bank Payment Gateway allows you to accept payment through various channels such as Maestro, Mastercard, AMex cards, Diners  and Visa cards On your Woocommerce Powered Site.
  Version: 3.0.1
  Author: Papaki
  Author URI: https://www.papaki.com
  License: GPL-3.0+
  License URI: http://www.gnu.org/licenses/gpl-3.0.txt
  WC tested up to: 9.4.2
  Text Domain: woo-payment-gateway-for-piraeus-bank
  Domain Path: /languages
*/
/*
Based on original plugin "Piraeus Bank Greece Payment Gateway for WooCommerce" by emspace.gr [https://wordpress.org/plugins/woo-payment-gateway-piraeus-bank-greece/]
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'classes/Application.php';

new Application( plugin_basename( __FILE__ ) );
