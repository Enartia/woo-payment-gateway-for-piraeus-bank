<?php

/**
 * @property int      $pb_PayMerchantId
 * @property int      $pb_AcquirerId
 * @property int      $pb_PosId
 * @property string   $pb_Username
 * @property string   $pb_Password
 * @property string   $pb_ProxyHost
 * @property string   $pb_ProxyPort
 * @property string   $pb_ProxyUsername
 * @property string   $pb_ProxyPassword
 * @property string   $pb_authorize
 * @property int      $pb_installments
 * @property string   $pb_installments_variation
 * @property string   $pb_render_logo
 * @property string   $pb_cardholder_name
 * @property string   $pb_enable_log
 * @property string   $pb_order_note
 * @property string   $notify_url
 * @property int|null $redirect_page_id
 * @noinspection DuplicatedCode
 * @noinspection PhpMissingReturnTypeInspection
 * @noinspection PhpMissingParamTypeInspection
 * @noinspection PhpUnusedParameterInspection
 */
class WC_Piraeusbank_Gateway extends WC_Payment_Gateway {
	protected const PLUGIN_NAMESPACE = 'woo-payment-gateway-for-piraeus-bank';

	public function __construct() {
		global $wpdb;

		$this->id                 = 'piraeusbank_gateway';
		$this->has_fields         = true;
		$this->notify_url         = WC()->api_request_url( 'WC_Piraeusbank_Gateway' );
		$this->method_description = __( 'Piraeus bank Payment Gateway allows you to accept payment through various channels such as Maestro, Mastercard, AMex cards, Diners  and Visa cards On your Woocommerce Powered Site.', self::PLUGIN_NAMESPACE );
		$this->redirect_page_id   = $this->get_option( 'redirect_page_id' );
		$this->method_title       = 'Piraeus bank Gateway';

		// Load the form fields.
		$this->init_form_fields();

		$tableCheck = $wpdb->get_var( "SHOW TABLES LIKE '" . $wpdb->prefix . "piraeusbank_transactions'" );

		if ( $tableCheck !== $wpdb->prefix . 'piraeusbank_transactions' ) {
			$wpdb->query( 'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'piraeusbank_transactions (id int(11) unsigned NOT NULL AUTO_INCREMENT, merch_ref varchar(50) not null, trans_ticket varchar(32) not null , timestamp datetime default null, PRIMARY KEY (id))' );
		}

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->title                     = sanitize_text_field( $this->get_option( 'title' ) );
		$this->description               = sanitize_text_field( $this->get_option( 'description' ) );
		$this->pb_PayMerchantId          = absint( $this->get_option( 'pb_PayMerchantId' ) );
		$this->pb_AcquirerId             = absint( $this->get_option( 'pb_AcquirerId' ) );
		$this->pb_PosId                  = absint( $this->get_option( 'pb_PosId' ) );
		$this->pb_Username               = sanitize_text_field( $this->get_option( 'pb_Username' ) );
		$this->pb_Password               = sanitize_text_field( $this->get_option( 'pb_Password' ) );
		$this->pb_ProxyHost              = $this->get_option( 'pb_ProxyHost' );
		$this->pb_ProxyPort              = $this->get_option( 'pb_ProxyPort' );
		$this->pb_ProxyUsername          = $this->get_option( 'pb_ProxyUsername' );
		$this->pb_ProxyPassword          = $this->get_option( 'pb_ProxyPassword' );
		$this->pb_authorize              = sanitize_text_field( $this->get_option( 'pb_authorize' ) );
		$this->pb_installments           = absint( $this->get_option( 'pb_installments' ) );
		$this->pb_installments_variation = sanitize_text_field( $this->get_option( 'pb_installments_variation' ) );
		$this->pb_render_logo            = sanitize_text_field( $this->get_option( 'pb_render_logo' ) );
		$this->pb_cardholder_name        = sanitize_text_field( $this->get_option( 'pb_cardholder_name' ) );
		$this->pb_enable_log             = sanitize_text_field( $this->get_option( 'pb_enable_log' ) );
		$this->pb_order_note             = sanitize_text_field( $this->get_option( 'pb_order_note' ) );

		//Actions
		add_action( 'woocommerce_receipt_piraeusbank_gateway', [ $this, 'receipt_page' ] );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

		// Payment listener/API hook
		add_action( 'woocommerce_api_wc_piraeusbank_gateway', [ $this, 'check_piraeusbank_response' ] );

		if ( class_exists( "SOAPClient" ) !== true ) {
			add_action( 'admin_notices', [ $this, 'soap_error_notice' ] );
		}

		if ( $this->pb_authorize === "yes" ) {
			add_action( 'admin_notices', [ $this, 'authorize_warning_notice' ] );
		}
		if ( $this->pb_render_logo === "yes" ) {
			$this->icon = apply_filters( 'piraeusbank_icon', plugins_url( '../img/piraeusbank.svg', __FILE__ ) );
		}

		$this->cardholderNameFunctionality();
	}

	public function cardholderNameFunctionality() {
		if ( $this->pb_cardholder_name === 'yes' ) {
			add_filter( 'woocommerce_billing_fields', [ $this, 'custom_override_checkout_fields' ] );
			//add_filter( 'woocommerce_customer_meta_fields', [ $this, 'add_woocommerce_customer_meta_fields' ] );
			add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'my_custom_checkout_field_update_order_meta' ] );

			wc_enqueue_js( '
                    jQuery(function(){
                        jQuery( \'body\' )
                        .on( \'updated_checkout\', function() {
                            usingGateway();
                    
                            jQuery(\'input[name="payment_method"]\').change(function(){
                                usingGateway();
                            });
                        });
                    });

                    function usingGateway(){
                        if(jQuery(\'form[name="checkout"] input[name="payment_method"]:checked\').val() === \'piraeusbank_gateway\'){
                            jQuery("#cardholder_name_field").show();
                            document.getElementById("cardholder_name").scrollIntoView({behavior: "smooth", block: "center", inline: "nearest"});
                        }else{
                            jQuery("#cardholder_name_field").hide();
                        }
                    }  
                ' );
		}
	}

	public function custom_override_checkout_fields( $billing_fields ) {
		$billing_fields['cardholder_name'] = [
			'type'        => 'text',
			'label'       => __( 'Cardholder Name', self::PLUGIN_NAMESPACE ),
			'placeholder' => __( 'Card holder name is optional by Piraeus Bank for validation', self::PLUGIN_NAMESPACE ),
			'required'    => false,
			'class'       => [ 'form-row-wide' ],
			'clear'       => true,
		];

		return $billing_fields;
	}


	public function my_custom_checkout_field_update_order_meta( $order_id ) {
		if ( ! empty( $_POST['cardholder_name'] ) ) {
			update_post_meta( $order_id, 'cardholder_name', sanitize_text_field( $_POST['cardholder_name'] ) );
		}
	}

	public function admin_options() {

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, 'https://api.ipify.org' );
		curl_setopt( $ch, CURLOPT_HEADER, 0 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		$output = curl_exec( $ch );

		curl_close( $ch );

		echo '<h3>' . __( 'Piraeus Bank Gateway', self::PLUGIN_NAMESPACE ) . '</h3>';
		echo '<p>' . __( 'Piraeus Bank Gateway allows you to accept payment through various channels such as Maestro, Mastercard, AMex cards, Diners  and Visa cards.', self::PLUGIN_NAMESPACE ) . '</p>';
		$base_url = $_SERVER['HTTP_HOST'] ?: $_SERVER['SERVER_NAME'];
		// $host = (is_ssl() === true ? 'https://' : 'http://') . $base_url . '/';
		$host = get_bloginfo( 'url' ) . '/';


		echo '<div style="border: 1px dashed #000; display: inline-block; padding: 10px;">';
		echo '<h4>' . __( 'Technical data to be submitted to Piraeus Bank', self::PLUGIN_NAMESPACE ) . '</h4>';
		echo '<p>' . __( 'The data to be submitted to Piraeus Bank(<a href="mailto:epayments@piraeusbank.gr">epayments@piraeusbank.gr</a>) in order to provide the necessary technical info (test/live account) for transactions are as follows', self::PLUGIN_NAMESPACE ) . ':</p>';
		echo '<ul>';
		echo '<li><strong>Website URL:</strong> ' . $host . '</li>';
		echo '<li><strong>Referrer url:</strong> ' . $host . 'checkout/' . ' </li>';
		echo '<li><strong>Success page: </strong>' . $host . ( get_option( 'permalink_structure' ) ? 'wc-api/WC_Piraeusbank_Gateway?peiraeus=success' : '?wc-api=WC_Piraeusbank_Gateway&peiraeus=success' ) . ' </li>';
		echo '<li><strong>Failure page:</strong> ' . $host . ( get_option( 'permalink_structure' ) ? 'wc-api/WC_Piraeusbank_Gateway?peiraeus=fail' : '?wc-api=WC_Piraeusbank_Gateway&peiraeus=fail' ) . ' </li>';
		echo '<li><strong>Backlink page:</strong> ' . $host . ( get_option( 'permalink_structure' ) ? 'wc-api/WC_Piraeusbank_Gateway?peiraeus=cancel' : '?wc-api=WC_Piraeusbank_Gateway&peiraeus=cancel' ) . ' </li>';
		echo '<li><strong>Response method :</strong> GET / POST  (Preferred one: POST)</li>';
		$ip = ! empty( $output ) ? $output : gethostbyname( $base_url );
		echo '<li><strong>Server Ip:</strong> ' . $ip . '</li>';
		echo '</ul>';
		echo '<p style="font-style:italic;">* Σημείωση: Τα urls Success, Failure, Backlink δημιουργούνται αυτόματα απο το plugin μας, ΔΕΝ χρείαζεται να δημιουργήσετε εσείς κάποια  επισπρόσθετη σελίδα</p>';
		echo '</div>';
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';


	}

	public function soap_error_notice() {
		echo '<div class="error notice">';
		echo '<p>' . __( '<strong>SOAP have to be enabled in your Server/Hosting</strong>, it is required for this plugin to work properly!', self::PLUGIN_NAMESPACE ) . '</p>';
		echo '</div>';
	}

	public function authorize_warning_notice() {
		echo '<div class="notice-warning notice">';
		echo '<p>' . __( '<strong>Important Notice:</strong> Piraeus Bank has announced that it will gradually abolish the Preauthorized Payment Service for all merchants, beginning from the ones obtained MIDs from 29/1/2019 onwards.<br /> You are highly recommended to disable the preAuthorized Payment Service as soon as possible.', self::PLUGIN_NAMESPACE ) . '</p>';
		echo '</div>';
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 * */
	public function init_form_fields() {
		$this->form_fields = [
			'enabled'                   => [
				'title'       => __( 'Enable/Disable', self::PLUGIN_NAMESPACE ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Piraeus Bank Gateway', self::PLUGIN_NAMESPACE ),
				'description' => __( 'Enable or disable the gateway.', self::PLUGIN_NAMESPACE ),
				'desc_tip'    => true,
				'default'     => 'yes',
			],
			'title'                     => [
				'title'       => __( 'Title', self::PLUGIN_NAMESPACE ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', self::PLUGIN_NAMESPACE ),
				'desc_tip'    => false,
				'default'     => __( 'Piraeus Bank Gateway', self::PLUGIN_NAMESPACE ),
			],
			'description'               => [
				'title'       => __( 'Description', self::PLUGIN_NAMESPACE ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', self::PLUGIN_NAMESPACE ),
				'default'     => __( 'Pay Via Piraeus Bank: Accepts  Mastercard, Visa cards and etc.', self::PLUGIN_NAMESPACE ),
			],
			'pb_render_logo'            => [
				'title'       => __( 'Display the logo of Piraeus Bank', self::PLUGIN_NAMESPACE ),
				'type'        => 'checkbox',
				'description' => __( 'Enable to display the logo of Piraeus Bank next to the title which the user sees during checkout.', self::PLUGIN_NAMESPACE ),
				'default'     => 'yes',
			],
			'pb_PayMerchantId'          => [
				'title'       => __( 'Piraeus Bank Merchant ID', self::PLUGIN_NAMESPACE ),
				'type'        => 'text',
				'description' => __( 'Enter Your Piraeus Bank Merchant ID', self::PLUGIN_NAMESPACE ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'pb_AcquirerId'             => [
				'title'       => __( 'Piraeus Bank Acquirer ID', self::PLUGIN_NAMESPACE ),
				'type'        => 'text',
				'description' => __( 'Enter Your Piraeus Bank Acquirer ID', self::PLUGIN_NAMESPACE ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'pb_PosId'                  => [
				'title'       => __( 'Piraeus Bank POS ID', self::PLUGIN_NAMESPACE ),
				'type'        => 'text',
				'description' => __( 'Enter your Piraeus Bank POS ID', self::PLUGIN_NAMESPACE ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'pb_Username'               => [
				'title'       => __( 'Piraeus Bank Username', self::PLUGIN_NAMESPACE ),
				'type'        => 'text',
				'description' => __( 'Enter your Piraeus Bank Username', self::PLUGIN_NAMESPACE ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'pb_Password'               => [
				'title'       => __( 'Piraeus Bank Password', self::PLUGIN_NAMESPACE ),
				'type'        => 'password',
				'description' => __( 'Enter your Piraeus Bank Password', self::PLUGIN_NAMESPACE ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'pb_ProxyHost'              => [
				'title'       => __( 'HTTP Proxy Hostname', self::PLUGIN_NAMESPACE ),
				'type'        => 'text',
				'description' => __( 'Used when your server is not behind a static IP. Leave blank for normal HTTP connection.', self::PLUGIN_NAMESPACE ),
				'desc_tip'    => false,
				'default'     => '',
			],
			'pb_ProxyPort'              => [
				'title'       => __( 'HTTP Proxy Port', self::PLUGIN_NAMESPACE ),
				'type'        => 'text',
				'description' => __( 'Used with Proxy Host.', self::PLUGIN_NAMESPACE ),
				'desc_tip'    => false,
				'default'     => '',
			],
			'pb_ProxyUsername'          => [
				'title'       => __( 'HTTP Proxy Login Username', self::PLUGIN_NAMESPACE ),
				'type'        => 'text',
				'description' => __( 'Used with Proxy Host. Leave blank for anonymous connection.', self::PLUGIN_NAMESPACE ),
				'desc_tip'    => false,
				'default'     => '',
			],
			'pb_ProxyPassword'          => [
				'title'       => __( 'HTTP Proxy Login Password', self::PLUGIN_NAMESPACE ),
				'type'        => 'password',
				'description' => __( ' Used with Proxy Host. Leave blank for anonymous connection.', self::PLUGIN_NAMESPACE ),
				'desc_tip'    => false,
				'default'     => '',
			],
			'pb_authorize'              => [
				'title'       => __( 'Pre-Authorize', self::PLUGIN_NAMESPACE ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable to capture preauthorized payments', self::PLUGIN_NAMESPACE ),
				'default'     => 'no',
				'description' => __( '<strong>Important Notice:</strong> Piraeus Bank has announced that it will gradually abolish the Preauthorized Payment Service for all merchants, beginning from the ones obtained MIDs from 29/1/2019 onwards.<br /> Default payment method is Purchase, enable for Pre-Authorized payments. You will then need to accept them from Piraeus Bank AdminTool', self::PLUGIN_NAMESPACE ),
			],
			'redirect_page_id'          => [
				'title'       => __( 'Return page URL <br />(Successful or Failed Transactions)', self::PLUGIN_NAMESPACE ),
				'type'        => 'select',
				'options'     => $this->pb_get_pages( 'Select Page' ),
				'description' => __( 'We recommend you to select the default “Thank You Page”, in order to automatically serve both successful and failed transactions, with the latter also offering the option to try the payment again.<br /> If you select a different page, you will have to handle failed payments yourself by adding custom code.', self::PLUGIN_NAMESPACE ),
				'default'     => - 1,
			],
			'pb_installments'           => [
				'title'       => __( 'Maximum number of installments regardless of the total order amount', self::PLUGIN_NAMESPACE ),
				'type'        => 'select',
				'options'     => $this->pb_get_installments( 'Select Installments' ),
				'description' => __( '1 to 24 Installments,1 for one time payment. You must contact Piraeus Bank first<br /> If you have filled the "Max Number of installments depending on the total order amount", the value of this field will be ignored.', self::PLUGIN_NAMESPACE ),
			],
			'pb_installments_variation' => [
				'title'       => __( 'Maximum number of installments depending on the total order amount', self::PLUGIN_NAMESPACE ),
				'type'        => 'text',
				'description' => __( 'Example 80:2, 160:4, 300:8</br> total order greater or equal to 80 -> allow 2 installments, total order greater or equal to 160 -> allow 4 installments, total order greater or equal to 300 -> allow 8 installments</br> Leave the field blank if you do not want to limit the number of installments depending on the amount of the order.', self::PLUGIN_NAMESPACE ),
			],
			'pb_cardholder_name'        => [
				'title'       => __( 'Enable Cardholder Name Field', self::PLUGIN_NAMESPACE ),
				'type'        => 'checkbox',
				'label'       => __( 'Enabling this field allows customers to insert a cardholder name', self::PLUGIN_NAMESPACE ),
				'default'     => 'yes',
				'description' => __( 'According to Piraeus bank’s technical requirements related to 3D secure and SCA, the cardholder’s name must be sent before the customer is redirected to the bank’s payment environment. If you choose not to show this field, we will automatically send the full name inserted for the order, with the risk of having the bank refusing the transaction due to the validity of this field.', self::PLUGIN_NAMESPACE ),
			],
			'pb_enable_log'             => [
				'title'       => __( 'Enable Debug mode', self::PLUGIN_NAMESPACE ),
				'type'        => 'checkbox',
				'label'       => __( 'Enabling this will log certain information', self::PLUGIN_NAMESPACE ),
				'default'     => 'no',
				'description' => __( 'Enabling this (and the debug mode from your wp-config file) will log information, e.g. bank responses, which will help in debugging issues.', self::PLUGIN_NAMESPACE ),
			],
			'pb_order_note'             => [
				'title'       => __( 'Enable 2nd “payment received” email', self::PLUGIN_NAMESPACE ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable sending Customer order note with transaction details', self::PLUGIN_NAMESPACE ),
				'default'     => 'no',
				'description' => __( 'Enabling this will send an email with the support reference id and transaction id to the customer, after the transaction has been completed (either on success or failure)', self::PLUGIN_NAMESPACE ),
			],

		];
	}

	/**
	 * @param string|bool $title
	 * @param bool        $indent
	 *
	 * @return array
	 */
	public function pb_get_pages( $title = false, $indent = true ) {
		$wp_pages  = get_pages( 'sort_column=menu_order' );
		$page_list = [];
		if ( $title ) {
			$page_list[] = $title;
		}
		foreach ( $wp_pages as $page ) {
			$prefix = '';
			// show indented child pages?
			if ( $indent ) {
				$has_parent = $page->post_parent;
				while ( $has_parent ) {
					$prefix     .= ' - ';
					$next_page  = get_post( $has_parent );
					$has_parent = $next_page->post_parent;
				}
			}
			// add to page list array array
			$page_list[ $page->ID ] = $prefix . $page->post_title;
		}
		$page_list[ - 1 ] = __( 'Thank you page', self::PLUGIN_NAMESPACE );

		return $page_list;
	}

	/**
	 * @param string|bool $title
	 * @param bool        $indent
	 *
	 * @return array
	 */
	public function pb_get_installments( $title = false, $indent = true ) {
		for ( $i = 1; $i <= 24; $i ++ ) {
			$installment_list[ $i ] = $i;
		}

		return $installment_list;
	}

	/**
	 * @return void
	 */
	public function payment_fields() {
		global $woocommerce;

		$amount = 0;

		//get: order or cart total, to compute max installments number.
		if ( absint( get_query_var( 'order-pay' ) ) ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
			$order    = new WC_Order( $order_id );
			$amount   = $order->get_total();
		}
		elseif ( ! $woocommerce->cart->is_empty() ) {
			$amount = $woocommerce->cart->total;
		}

		if ( $description = $this->get_description() ) {
			echo wpautop( wptexturize( $description ) );
		}

		$max_installments       = $this->ab_installments ?? 1;
		$installments_variation = $this->ab_installments_variation ?? [];

		if ( ! empty( $installments_variation ) ) {
			$max_installments   = 1; // initialize the max installments
			$installments_split = explode( ',', $installments_variation );
			foreach ( $installments_split as $value ) {
				$installment = explode( ':', $value );
				if ( ( is_array( $installment ) && count( $installment ) !== 2 ) ||
					 ( ! is_numeric( $installment[0] ) || ! is_numeric( $installment[1] ) ) ) {
					// not valid rule for installments
					continue;
				}

				if ( $amount >= ( $installment[0] ) ) {
					$max_installments = $installment[1];
				}
			}
		}

		if ( $max_installments > 1 ) {
			$doseis_field = '<p class="form-row ">
                    <label for="' . esc_attr( $this->id ) . '-card-doseis">' . __( 'Choose Installments', 'woo-payment-gateway-for-piraeus-bank' ) . ' <span class="required">*</span></label>
                                <select id="' . esc_attr( $this->id ) . '-card-doseis" name="' . esc_attr( $this->id ) . '-card-doseis" class="input-select wc-credit-card-form-card-doseis">
                                ';
			for ( $i = 1; $i <= $max_installments; $i ++ ) {
				$doseis_field .= '<option value="' . $i . '">' . ( $i === 1 ? __( 'Without installments', 'woo-payment-gateway-for-piraeus-bank' ) : $i ) . '</option>';
			}
			$doseis_field .= '</select>
                        </p>'; // <img width="100%" height="100%" style="max-height:100px!important" src="'. plugins_url('img/alpha_cards.png', __FILE__) .'" >

			echo $doseis_field;
		}
	}

	/**
	 * Generate the  Piraeus Payment button link
	 * */
	public function generate_piraeusbank_form( $order_id ) {
		global $wpdb;

		$availableLocales = [
			'en'             => 'en-US',
			'en_US'          => 'en-US',
			'en_AU'          => 'en-US',
			'en_CA'          => 'en-US',
			'en_GB'          => 'en-US',
			'en_NZ'          => 'en-US',
			'en_ZA'          => 'en-US',
			'el'             => 'el-GR',
			'ru_RU'          => 'ru-RU',
			'de_DE'          => 'de-DE',
			'de_DE_formal'   => 'de-DE',
			'de_CH'          => 'de-DE',
			'de_CH_informal' => 'de-DE',
		];

		$lang  = $availableLocales[ get_locale() ] ?? 'en-US';
		$order = new WC_Order( $order_id );

		$requestType   = $this->pb_authorize === "yes" ? '00' : '02';
		$ExpirePreauth = $this->pb_authorize === "yes" ? '30' : '0';

		if ( method_exists( $order, 'get_meta' ) ) {
			$installments = $order->get_meta( '_doseis' );
			if ( $installments === '' ) {
				$installments = 1;
			}
		}
		else {
			$installments = get_post_meta( $order_id, '_doseis', 1 );
		}

		try {
			if ( $this->pb_ProxyHost !== '' ) {
				if ( $this->pb_ProxyUsername !== '' && $this->pb_ProxyPassword !== '' ) {
					$soap = new SoapClient( "https://paycenter.piraeusbank.gr/services/tickets/issuer.asmx?WSDL",
						[
							'proxy_host'     => $this->pb_ProxyHost,
							'proxy_port'     => (int) $this->pb_ProxyPort,
							'proxy_login'    => $this->pb_ProxyUsername,
							'proxy_password' => $this->pb_ProxyPassword,
						]
					);
				}
				else {
					$soap = new SoapClient( "https://paycenter.piraeusbank.gr/services/tickets/issuer.asmx?WSDL",
						[
							'proxy_host' => $this->pb_ProxyHost,
							'proxy_port' => (int) $this->pb_ProxyPort,
						]
					);
				}
			}
			else {
				$soap = new SoapClient( "https://paycenter.piraeusbank.gr/services/tickets/issuer.asmx?WSDL" );
			}

			//initialize new 3DS information
			$BillAddrCity      = mb_substr( $order->get_billing_city(), 0, 50 ); // TODO: add regexp for greek latin and special chars
			$BillAddrCountry   = pb_getCountryNumericCode( $order->get_billing_country() ); // TODO: add regexp for greek latin and special chars
			$BillAddrLine1     = mb_substr( $order->get_billing_address_1(), 0, 50 );
			$BillAddrPostCode  = $order->get_billing_postcode();
			$BillAddrState     = $order->get_billing_state();
			$BillAddrStateCode = pb_validateStateCode( $BillAddrState, $order->get_billing_country() );

			$ShipAddrCity      = mb_substr( ! empty( $order->get_shipping_city() ) ? $order->get_shipping_city() : $order->get_billing_city(), 0, 50 );
			$ShipAddrCountry   = ! empty( $order->get_shipping_country() ) ? pb_getCountryNumericCode( $order->get_shipping_country() ) : $BillAddrCountry;
			$ShipAddrLine1     = mb_substr( ! empty( $order->get_shipping_address_1() ) ? $order->get_shipping_address_1() : $order->get_billing_address_1(), 0, 50 );
			$ShipAddrPostCode  = ! empty( $order->get_shipping_postcode() ) ? $order->get_shipping_postcode() : $BillAddrPostCode;
			$ShipAddrState     = ! empty( $order->get_shipping_state() ) ? $order->get_shipping_state() : $BillAddrState;
			$ShipAddrStateCode = pb_validateStateCode( $ShipAddrState, ! empty( $order->get_shipping_country() ) ? $order->get_shipping_country() : $order->get_billing_country() );
			$Email             = $order->get_billing_email();

			$HomePhone   = pb_validatePhoneNumberAllCountries( $order->get_billing_phone(), $order->get_billing_country() );
			$MobilePhone = pb_validatePhoneNumberAllCountries( $order->get_billing_phone(), $order->get_billing_country() );
			$WorkPhone   = pb_validatePhoneNumberAllCountries( $order->get_billing_phone(), $order->get_billing_country() );

			$name           = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
			$CardholderName = pb_getCardholderName( $order->get_id(), $name, $this->pb_cardholder_name );


			$ticketRequest = [
				'Username'          => $this->pb_Username,
				'Password'          => hash( 'md5', $this->pb_Password ),
				'MerchantId'        => $this->pb_PayMerchantId,
				'PosId'             => $this->pb_PosId,
				'AcquirerId'        => $this->pb_AcquirerId,
				'MerchantReference' => $order_id,
				'RequestType'       => $requestType,
				'ExpirePreauth'     => $ExpirePreauth,
				'Amount'            => $order->get_total(),
				'CurrencyCode'      => '978',
				'Installments'      => $installments,
				'Bnpl'              => '0',
				'Parameters'        => '',
				'BillAddrCity'      => $BillAddrCity,
				'BillAddrCountry'   => $BillAddrCountry,
				'BillAddrLine1'     => $BillAddrLine1,
				'BillAddrPostCode'  => $BillAddrPostCode,
				'BillAddrState'     => $BillAddrStateCode,
				'ShipAddrCity'      => $ShipAddrCity,
				'ShipAddrCountry'   => $ShipAddrCountry,
				'ShipAddrLine1'     => $ShipAddrLine1,
				'ShipAddrPostCode'  => $ShipAddrPostCode,
				'ShipAddrState'     => $ShipAddrStateCode,
				'CardholderName'    => $CardholderName,
				'Email'             => $Email,
				'HomePhone'         => $HomePhone,
				'MobilePhone'       => $MobilePhone,
				'WorkPhone'         => $WorkPhone,
			];

			$xml = [
				'Request' => $ticketRequest,
			];

			/** @noinspection PhpUndefinedMethodInspection */
			$oResult = $soap->IssueNewTicket( $xml );

			if ( $this->pb_enable_log === 'yes' ) {
				error_log( '---- Piraeus Transaction Ticket -----' );
				error_log( print_r( $ticketRequest, true ) );
				error_log( '---- End ofPiraeus Transaction Ticket ----' );
			}

			if ( (int) $oResult->IssueNewTicketResult->ResultCode === 0 ) {
				$wpdb->insert( $wpdb->prefix . 'piraeusbank_transactions', [ 'trans_ticket' => $oResult->IssueNewTicketResult->TranTicket, 'merch_ref' => $order_id, 'timestamp' => current_time( 'mysql', 1 ) ] );

				wc_enqueue_js( '
				$.blockUI({
						message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to Piraeus Bank to make payment.', self::PLUGIN_NAMESPACE ) ) . '",
						baseZ: 99999,
						overlayCSS:
						{
							background: "#fff",
							opacity: 0.6
						},
						css: {
							padding:        "20px",
							zindex:         "9999999",
							textAlign:      "center",
							color:          "#555",
							border:         "3px solid #aaa",
							backgroundColor:"#fff",
							cursor:         "wait",
							lineHeight:		"24px",
						}
					});
	    			jQuery("#submit_pb_payment_form").click();
    			' );

				$LanCode = $lang;

				return '<form action="' . esc_url( "https://paycenter.piraeusbank.gr/redirection/pay.aspx" ) . '" method="post" id="pb_payment_form" target="_top">

						<input type="hidden" id="AcquirerId" name="AcquirerId" value="' . esc_attr( $this->pb_AcquirerId ) . '"/>
						<input type="hidden" id="MerchantId" name="MerchantId" value="' . esc_attr( $this->pb_PayMerchantId ) . '"/>
						<input type="hidden" id="PosID" name="PosID" value="' . esc_attr( $this->pb_PosId ) . '"/>
						<input type="hidden" id="User" name="User" value="' . esc_attr( $this->pb_Username ) . '"/>
						<input type="hidden" id="LanguageCode"  name="LanguageCode" value="' . $LanCode . '"/>
						<input type="hidden" id="MerchantReference" name="MerchantReference"  value="' . esc_attr( $order_id ) . '"/>
					<!-- Button Fallback -->
					<div class="payment_buttons">
						<input type="submit" class="button alt" id="submit_pb_payment_form" value="' . __( 'Pay via Pireaus Bank', self::PLUGIN_NAMESPACE ) . '" /> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', self::PLUGIN_NAMESPACE ) . '</a>

					</div>
					<script type="text/javascript">
					jQuery(".payment_buttons").hide();
					</script>
				</form>';
			}

			echo __( 'An error occured, please contact the Administrator. ', self::PLUGIN_NAMESPACE );
			echo( 'Result code is ' . filter_var( $oResult->IssueNewTicketResult->ResultCode, FILTER_SANITIZE_STRING ) );
			echo( '. : ' . filter_var( $oResult->IssueNewTicketResult->ResultDescription, FILTER_SANITIZE_STRING ) );
			$order->add_order_note( __( 'Error' . filter_var( $oResult->IssueNewTicketResult->ResultCode, FILTER_SANITIZE_STRING ) . ':' . filter_var( $oResult->IssueNewTicketResult->ResultDescription, FILTER_SANITIZE_STRING ), '' ) );
		} catch ( SoapFault $fault ) {
			$order->add_order_note( __( 'Error' . sanitize_text_field( $fault ), '' ) );
			echo __( 'Error' . $fault, '' );
		}

		return '';
	}

	/**
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = new WC_Order( $order_id );

		$key = esc_attr( $this->id ) . '-card-doseis';

		$doseis = isset( $_POST[ $key ] ) ? (int) $_POST[ $key ] : 1;
		if ( $doseis > 0 ) {
			$this->generic_add_meta( $order_id, '_doseis', $doseis );
		}

		return [
			'result'   => 'success',
			'redirect' => add_query_arg( 'order-pay', $order->get_id(), add_query_arg( 'key', $order->get_order_key(), wc_get_page_permalink( 'checkout' ) ) ),
		];
	}

	/**
	 * @param int $order
	 *
	 * @return void
	 */
	public function receipt_page( $order ) {
		echo '<p>' . __( 'Thank you - your order is now pending payment. You should be automatically redirected to Piraeus Paycenter to make payment.', self::PLUGIN_NAMESPACE ) . '</p>';
		echo $this->generate_piraeusbank_form( $order );
	}

	/**
	 * @return void
	 */
	public function check_piraeusbank_response() {
		global $wpdb;

		$pb_message   = [];
		$message      = '';
		$consHashHmac = '';
		$order        = null;

		if ( $this->pb_enable_log === 'yes' ) {
			error_log( '---- Piraeus Response -----' );
			error_log( print_r( $_REQUEST, true ) );
			error_log( '---- End of Piraeus Response ----' );
		}

		if ( isset( $_REQUEST['peiraeus'] ) && ( $_REQUEST['peiraeus'] === 'success' ) ) {
			$ResultCode = (int) filter_var( $_REQUEST['ResultCode'], FILTER_SANITIZE_STRING );
			$order_id   = filter_var( $_REQUEST['MerchantReference'], FILTER_SANITIZE_STRING );
			$order      = new WC_Order( $order_id );

			if ( $ResultCode !== 0 ) {
				$message      = __( 'A technical problem occured. <br />The transaction wasn\'t successful, payment wasn\'t received.', self::PLUGIN_NAMESPACE );
				$message_type = 'error';
				$this->set_message( $order, $message, $message_type );

				wc_add_notice( __( 'Payment error:', self::PLUGIN_NAMESPACE ) . $message, $message_type );
				//Update the order status
				$order->update_status( 'failed' );
				$checkout_url = wc_get_checkout_url();
				wp_redirect( $checkout_url );
				exit;
			}

			$ResponseCode       = filter_var( $_REQUEST['ResponseCode'], FILTER_SANITIZE_STRING );
			$StatusFlag         = filter_var( $_REQUEST['StatusFlag'], FILTER_SANITIZE_STRING );
			$HashKey            = filter_var( $_REQUEST['HashKey'], FILTER_SANITIZE_STRING );
			$SupportReferenceID = absint( $_REQUEST['SupportReferenceID'] );
			$ApprovalCode       = filter_var( $_REQUEST['ApprovalCode'], FILTER_SANITIZE_STRING );
			$Parameters         = filter_var( $_REQUEST['Parameters'], FILTER_SANITIZE_STRING );
			$AuthStatus         = filter_var( $_REQUEST['AuthStatus'], FILTER_SANITIZE_STRING );
			$PackageNo          = absint( $_REQUEST['PackageNo'] );
			$TransactionId      = isset( $_REQUEST['TransactionId'] ) ? absint( $_REQUEST['TransactionId'] ) : '';

			$ttquery = $wpdb->prepare(
				'select trans_ticket from ' . $wpdb->prefix . 'piraeusbank_transactions' . ' where merch_ref = %s',
				[
					$order_id,
				]
			);

			$tt = $wpdb->get_results( $ttquery );

			if ( $this->pb_enable_log === 'yes' ) {
				error_log( '---- ttquery -----' );
				error_log( print_r( [ $ttquery, $tt ], true ) );
				error_log( '---- End of ttquery ----' );
			}

			$hasHashKeyNotMatched = true;

			foreach ( $tt as $transaction ) {
				if ( ! $hasHashKeyNotMatched ) {
					break;
				}

				$transTicket  = $transaction->trans_ticket;
				$stcon        = $transTicket . $this->pb_PosId . $this->pb_AcquirerId . $order_id . $ApprovalCode . $Parameters . $ResponseCode . $SupportReferenceID . $AuthStatus . $PackageNo . $StatusFlag;
				$conHash      = strtoupper( hash( 'sha256', $stcon ) );
				$stconHmac    = $transTicket . ';' . $this->pb_PosId . ';' . $this->pb_AcquirerId . ';' . $order_id . ';' . $ApprovalCode . ';' . $Parameters . ';' . $ResponseCode . ';' . $SupportReferenceID . ';' . $AuthStatus . ';' . $PackageNo . ';' . $StatusFlag;
				$consHashHmac = strtoupper( hash_hmac( 'sha256', $stconHmac, $transTicket ) );

				if ( $consHashHmac !== $HashKey && $conHash !== $HashKey ) {
					continue;
				}

				$hasHashKeyNotMatched = false;
			}

			if ( $hasHashKeyNotMatched ) {
				$message      = __( 'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.', self::PLUGIN_NAMESPACE );
				$message_type = 'error';
				$pb_message   = [ 'message' => $message, 'message_type' => $message_type ];

				$this->generic_add_meta( $order_id, '_piraeusbank_message', $pb_message );
				$this->generic_add_meta( $order_id, '_piraeusbank_message_debug', [ $pb_message, $consHashHmac . '!=' . $HashKey ] );

				$order->update_status( 'failed' );
				$checkout_url = wc_get_checkout_url();
				wp_redirect( $checkout_url );
				exit;
			}

			if ( $ResponseCode == 0 || $ResponseCode == 8 || $ResponseCode == 10 || $ResponseCode == 16 ) {
				$order->payment_complete( $TransactionId );

				//Add admin order note
				$order->add_order_note( __( 'Payment Via Peiraeus Bank<br />Transaction ID: ', self::PLUGIN_NAMESPACE ) . $TransactionId . __( '<br />Support Reference ID: ', self::PLUGIN_NAMESPACE ) . $SupportReferenceID );

				if ( $order->get_status() === 'processing' ) {
					$message = __( 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is currently being processed.', self::PLUGIN_NAMESPACE );

					if ( $this->pb_order_note === 'yes' ) {
						$order->add_order_note( __( 'Payment Received.<br />Your order is currently being processed.<br />We will be shipping your order to you soon.<br />Peiraeus Bank ID: ', self::PLUGIN_NAMESPACE ) . $TransactionId . __( '<br />Support Reference ID: ', self::PLUGIN_NAMESPACE ) . $SupportReferenceID, 1 );
					}
				}
				else if ( $order->get_status() === 'completed' ) {
					$message = __( 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is now complete.', self::PLUGIN_NAMESPACE );

					if ( $this->pb_order_note === 'yes' ) {
						$order->add_order_note( __( 'Payment Received.<br />Your order is now complete.<br />Peiraeus Transaction ID: ', self::PLUGIN_NAMESPACE ) . $TransactionId . __( '<br />Support Reference ID: ', self::PLUGIN_NAMESPACE ) . $SupportReferenceID, 1 );
					}

				}

				$message_type = 'success';

				$pb_message = $this->set_message( $order, $message, $message_type );

				// Empty cart
				WC()->cart->empty_cart();
			}
			else if ( $ResponseCode == 11 ) {
				$message      = __( 'Thank you for shopping with us.<br />Your transaction was previously received.<br />', self::PLUGIN_NAMESPACE );
				$message_type = 'success';

				$pb_message = $this->set_message( $order, $message, $message_type );
			}
			else { //Failed Response codes
				$message      = __( 'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.', self::PLUGIN_NAMESPACE );
				$message_type = 'error';

				$pb_message = $this->set_message( $order, $message, $message_type );

				//Update the order status
				$order->update_status( 'failed' );
			}
		}

		if ( isset( $_REQUEST['peiraeus'], $_REQUEST['MerchantReference'] ) && $_REQUEST['peiraeus'] === 'fail' ) {
			$order_id     = filter_var( $_REQUEST['MerchantReference'], FILTER_SANITIZE_STRING );
			$order        = new WC_Order( $order_id );
			$message      = __( 'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.', self::PLUGIN_NAMESPACE );
			$message_type = 'error';

			$transaction_id = absint( $_REQUEST['SupportReferenceID'] );
			if ( $this->pb_order_note === 'yes' ) {
				//Add Customer Order Note
				$order->add_order_note( $message . '<br />Piraeus Bank Support Reference ID: ' . $transaction_id, 1 );
			}

			//Add Admin Order Note
			$order->add_order_note( $message . '<br />Piraeus Bank Support Reference ID: ' . $transaction_id );


			//Update the order status
			$order->update_status( 'failed' );

			$pb_message = $this->set_message( $order, $message, $message_type );
		}

		if ( isset( $_REQUEST['peiraeus'] ) && ( $_REQUEST['peiraeus'] === 'cancel' ) ) {
			$checkout_url = wc_get_checkout_url();
			wp_redirect( $checkout_url );
			exit;
		}

		if ( $this->redirect_page_id == - 1 && $order !== null ) {
			$redirect_url = $this->get_return_url( $order );
		}
		else {
			$redirect_url = add_query_arg( [ 'msg' => urlencode( $pb_message['message'] ), 'type' => $pb_message['class'] ], ( $this->redirect_page_id === "" || $this->redirect_page_id === 0 ) ? get_site_url() . "/" : get_permalink( $this->redirect_page_id ) );
		}

		wp_redirect( $redirect_url );

		exit;
	}

	/**
	 * @param $orderid
	 * @param $key
	 * @param $value
	 *
	 * @return void
	 */
	public function generic_add_meta( $orderid, $key, $value ) {
		$order = new WC_Order( sanitize_text_field( $orderid ) );
		if ( method_exists( $order, 'add_meta_data' ) && method_exists( $order, 'save_meta_data' ) ) {
			$order->add_meta_data( sanitize_key( $key ), sanitize_text_field( $value ), true );
			$order->save_meta_data();
		}
		else {
			update_post_meta( $orderid, sanitize_key( $key ), sanitize_text_field( $value ) );
		}
	}

	/**
	 * @param WC_Order $order
	 * @param string   $message
	 * @param string   $message_type
	 *
	 * @return array{message: string, message_type: string}
	 */
	public function set_message( WC_Order $order, string $message, string $message_type ) {
		$pb_message = [
			'message'      => $message,
			'message_type' => $message_type,
		];

		$this->generic_add_meta( $order->get_id(), '_piraeusbank_message', $pb_message );
		$this->generic_add_meta( $order->get_id(), '_piraeusbank_message_debug', $pb_message );

		return $pb_message;
	}

	/**
	 * @return void
	 */
	public function validate_fields() {
		$requiredFields = [
			'billing_email'     => 'E-mail address',
			'billing_city'      => 'Billing town/city',
			'billing_country'   => 'Billing country / region',
			'billing_state'     => 'Billing state / county',
			'billing_address_1' => 'Billing street address',
			'billing_postcode'  => 'Billing postcode / ZIP',
		];

		foreach ( $requiredFields as $field => $info ) {
			if ( ! isset( $_POST[ $field ] ) || trim( $_POST[ $field ] ) === '' ) {

				if (defined('REST_REQUEST')) {
					//$parameters = $request->get_query_params();
					//We're inside a rest API request.
					//var_dump($_GET);
					return false;
				}

				wc_add_notice(
					__( $info . ' is a mandatory field!' ),
					'error'
				);
			}
		}
	}

}
