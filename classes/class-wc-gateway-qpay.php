<?php
/**
 * Qpay Payment Gateway
 *
 * Provides a Qpay Payment Gateway.
 *
 * @class 		woocommerce_qpay
 * @package		WooCommerce
 * @category	Payment Gateways
 * @author		QPAY Ecommerce
 *
 *
 * Table Of Contents
 *
 * __construct()
 * init_form_fields()
 * add_testmode_admin_settings_notice()
 * plugin_url()
 * add_currency()
 * add_currency_symbol()
 * is_valid_for_use()
 * admin_options()
 * payment_fields()
 * generate_qpay_form()
 * process_payment()
 * receipt_page()
 * check_itn_request_is_valid()
 * check_itn_response()
 * successful_request()
 * setup_constants()
 * log()
 * validate_signature()
 * validate_ip()
 * validate_response_data()
 * amounts_equal()
 */
class WC_Gateway_Qpay extends WC_Payment_Gateway {

	public $version = '1.2.7';

	public function __construct() {
        global $woocommerce;
        $this->id			= 'qpay';
        $this->method_title = __( 'Qpay', 'woocommerce-gateway-qpay' );
        // Added by Omman
        $this->method_description = __( 'Qatar Payment Gateway Plugin by devomman.', 'woocommerce-gateway-qpay' );
        $this->icon 		= $this->plugin_url() . '/assets/images/icon.png';
        $this->has_fields 	= true;

		// Setup available countries.
		$this->available_countries = array( 'QA' );

		// Setup available currency codes.
		$this->available_currencies = array( 'QAR' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Setup constants.
//		$this->setup_constants();

		// Setup default merchant data.
		$this->gateway_id = $this->settings['gateway_id'];
		$this->secret_key = $this->settings['secret_key'];
		$this->url = 'https://qpayi.com:9100/api/gateway/v1.0';
		$this->title = $this->settings['title'];
		

		// Setup the test data, if in test mode.
		if ( $this->settings['testmode'] == 'yes' ) {
			$this->url = 'https://demomerchant.qpayi.com:9100/api/gateway/v1.0';
		}

		$this->response_url	= add_query_arg( 'wc-api', 'WC_Gateway_Qpay', home_url( '/' ) );

		add_action( 'woocommerce_api_wc_gateway_qpay', array( $this, 'check_itn_response' ) );
		add_action( 'valid-qpay-standard-itn-request', array( $this, 'successful_request' ) );

		/* 1.6.6 */
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );

		/* 2.0.0 */
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'woocommerce_receipt_qpay', array( $this, 'receipt_page' ) );

		// Check if the base currency supports this gateway.
		if ( ! $this->is_valid_for_use() )
			$this->enabled = false;
    }

	/**
     * Initialise Gateway Settings Form Fields
     *
     * @since 1.0.0
     */
    function init_form_fields () {

    	$this->form_fields = array(
    						'enabled' => array(
											'title' => __( 'Enable/Disable', 'woocommerce-gateway-qpay' ),
											'label' => __( 'Enable Qpay', 'woocommerce-gateway-qpay' ),
											'type' => 'checkbox',
											'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', 'woocommerce-gateway-qpay' ),
											'default' => 'yes'
										),
    						'title' => array(
    										'title' => __( 'Title', 'woocommerce-gateway-qpay' ),
    										'type' => 'text',
    										'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-qpay' ),
    										'default' => __( 'Qpay', 'woocommerce-gateway-qpay' ),
    										'desc_tip'    => true,
    									),
							'description' => array(
											'title' => __( 'Description', 'woocommerce-gateway-qpay' ),
											'type' => 'textarea',
											'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-qpay' ),
											'default'     => '',
											'desc_tip'    => true,
										),
							'testmode' => array(
											'title' => __( 'Qpay Sandbox', 'woocommerce-gateway-qpay' ),
											'type' => 'checkbox',
											'description' => __( 'Place the payment gateway in development mode.', 'woocommerce-gateway-qpay' ),
											'default' => 'yes'
										),
							'gateway_id' => array(
											'title' => __( 'Gateway ID', 'woocommerce-gateway-qpay' ),
											'type' => 'text',
											'description' => __( 'This is the Gateway ID, received from Qpay.', 'woocommerce-gateway-qpay' ),
											'default' => ''
										),
							'secret_key' => array(
											'title' => __( 'Secret Key', 'woocommerce-gateway-qpay' ),
											'type' => 'text',
											'description' => __( 'This is the secret key, received from Qpay.', 'woocommerce-gateway-qpay' ),
											'default' => ''
										)

							);

    } // End init_form_fields()


    /**
	 * Get the plugin URL
	 *
	 * @since 1.0.0
	 */
	function plugin_url() {
		if( isset( $this->plugin_url ) )
			return $this->plugin_url;

		if ( is_ssl() ) {
			return $this->plugin_url = str_replace( 'http://', 'https://', WP_PLUGIN_URL ) . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		} else {
			return $this->plugin_url = WP_PLUGIN_URL . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		}
	} // End plugin_url()

    /**
     * is_valid_for_use()
     *
     * Check if this gateway is enabled and available in the base currency being traded with.
     *
     * @since 1.0.0
     */
	function is_valid_for_use() {
		global $woocommerce;

		$is_available = false;

        $user_currency = get_option( 'woocommerce_currency' );

        $is_available_currency = in_array( $user_currency, $this->available_currencies );

		if ( $this->enabled == 'yes' && $this->settings['gateway_id'] != '' && $this->settings['secret_key'] != '' )
			$is_available = true;

        return $is_available;

	} // End is_valid_for_use()

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		// Make sure to empty the log file if not in test mode.
		if ( $this->settings['testmode'] != 'yes' ) {
			$this->log( '' );
			$this->log( '', true );
		}

    	?>
    	<h3><?php _e( 'Qpay', 'woocommerce-gateway-qpay' ); ?></h3>
    	<p><?php printf( __( 'Qpay works by sending the user to %sQpay%s to enter their payment information.', 'woocommerce-gateway-qpay' ), '<a href="https://qpayi.com/">', '</a>' ); ?></p>
        <table class="form-table"><?php
			// Generate the HTML For the settings form.
    		$this->generate_settings_html();
    		?></table>

    	<?php
    } // End admin_options()

    /**
	 * There are no payment fields for Qpay, but we want to show the description if set.
	 *
	 * @since 1.0.0
	 */
    function payment_fields() {
    	if ( isset( $this->settings['description'] ) && ( '' != $this->settings['description'] ) ) {
    		echo wpautop( wptexturize( $this->settings['description'] ) );
    	}
    } // End payment_fields()

	/**
	 * Generate the Qpay button link.
	 *
	 * @since 1.0.0
	 */
    public function generate_qpay_form( $order_id ) {

		global $woocommerce;

		$order = new WC_Order( $order_id );

		// Added By Omman for First Name and Last Name
		$customerName = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ; 

		$shipping_name = explode(' ', $order->shipping_method);
		// Construct variables for post
	    $this->data_to_send = array(
	        // Merchant details
	        'gatewayId' => $this->settings['gateway_id'],
	        'secretKey' => $this->settings['secret_key'],
            'referenceId' => $order->order_key,
//	        'returnUrl' => $this->get_return_url( $order ),
            'returnUrl' => $this->response_url . '&orderId=' . $order->id,
	        //'notify_url' => $this->response_url,

			// Billing details
            'action' => 'capture',
			// Added by devomman $customerName
			'name' => $customerName, 
			'phone' => $order->billing_phone,
			'email' => $order->billing_email,
            'address' => $order->billing_zone_no,
            // 'state' => $order->billing_street_no,
            'city' => $order->billing_building_no,
            'country' => $order->billing_country,

            'mode' => 'LIVE',
            'currency' => 'QAR',
	        'amount' => $order->order_total,
        	'custom_str1' => $order->order_key,
        	// 'custom_str2' => 'WooCommerce/' . $woocommerce->version . '; ' . get_site_url(),
        	'custom_str3' => $order->id,
	    	'description' => sprintf( __( 'New order from %s', 'woocommerce-gateway-qpay' ), get_bloginfo( 'name' ) )
	    

	   	);



		$qpay_args_array = array();

		foreach ($this->data_to_send as $key => $value) {
			$qpay_args_array[] = '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
		}



		return '<form action="' . $this->url . '" method="post" id="qpay_payment_form">
				' . implode('', $qpay_args_array) . '
				
				<input type="submit" class="button-alt" id="submit_qpay_payment_form" value="' . __( 'Pay via Card', 'woocommerce-gateway-qpay' ) . '" /> <br> 
				<a class="button cancel" id="cancel_qpay_payment_form" href="' . $order->get_cancel_order_url() . '">' . __( 'Cancel order &amp; restore cart', 'woocommerce-gateway-qpay' ) . '</a>
				
				<script type="text/javascript">
					jQuery(function(){
						jQuery("body").block(
							{
								message: "<img src=\"' .esc_url( $woocommerce->plugin_url() ). '/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" />' . __( 'Thank you for your order. We are now redirecting you to Qpay to make payment.', 'woocommerce-gateway-qpay' ) . '",
								overlayCSS:
								{
									background: "#fff",
									opacity: 0.6
								},
								css: {
							        padding:        20,
							        textAlign:      "center",
							        color:          "#555",
							        border:         "3px solid #aaa",
							        backgroundColor:"#fff",
							        cursor:         "wait"
							    }
							});
						jQuery( "#submit_qpay_payment_form" ).click();
					});
				</script>
			</form>';






	} // End generate_qpay_form()

	/**
	 * Process the payment and return the result.
	 *
	 * @since 1.0.0
	 */
	function process_payment( $order_id ) {

		$order = new WC_Order( $order_id );

		return array(
			'result' 	=> 'success',
			'redirect'	=> $order->get_checkout_payment_url( true )
		);

	}

	/**
	 * Reciept page.
	 *
	 * Display text and a button to direct the user to Qpay.
	 *
	 * @since 1.0.0
	 */
	function receipt_page( $order ) {
		echo '<p id="thank-qpay-p">' . __( 'Thank you for your order, please click the button below to Pay via Card.', 'woocommerce-gateway-qpay' ) . '</p>';

		echo $this->generate_qpay_form( $order );
	} // End receipt_page()

	/**
	 * Check Qpay ITN validity.
	 *
	 * @param array $data
	 * @since 1.0.0
	 */
	function check_itn_request_is_valid( $data ) {
		global $woocommerce;

		$pfError = false;
		$pfDone = false;

		$sessionid = $data['custom_str1'];
        $transaction_id = $data['pf_payment_id'];
        $vendor_name = get_option( 'blogname' );
        $vendor_url = home_url( '/' );

		$order_id = (int) $data['custom_str3'];
		$order_key = esc_attr( $sessionid );
		$order = new WC_Order( $order_id );

		$data_string = '';
		$data_array = array();

		// Dump the submitted variables and calculate security signature
	    foreach( $data as $key => $val ) {
	    	if( $key != 'signature' ) {
	    		$data_string .= $key .'='. urlencode( $val ) .'&';
	    		$data_array[$key] = $val;
	    	}
	    }

	    // Remove the last '&' from the parameter string
	    $data_string = substr( $data_string, 0, -1 );
	    $signature = md5( $data_string );

		$this->log( "\n" . '----------' . "\n" . 'Qpay ITN call received' );

		// Notify Qpay that information has been received
        if( ! $pfError && ! $pfDone ) {
            header( 'HTTP/1.0 200 OK' );
            flush();
        }

        // Get data sent by Qpay
        if ( ! $pfError && ! $pfDone ) {
        	$this->log( 'Get posted data' );

            $this->log( 'Qpay Data: '. print_r( $data, true ) );

            if ( $data === false ) {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_ACCESS;
            }
        }
        // Get internal order and verify it hasn't already been processed
        if( ! $pfError && ! $pfDone ) {

            $this->log( "Purchase:\n". print_r( $order, true )  );

            // Check if order has already been processed
            if( $order->status == 'completed' ) {
                $this->log( 'Order has already been processed' );
                $pfDone = true;
            }
        }

        // Verify data received
        if( ! $pfError ) {
            $this->log( 'Verify data received' );

            $pfValid = true;

            if( ! $pfValid ) {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_ACCESS;
            }
        }

        // Check data against internal order
        if( ! $pfError && ! $pfDone ) {
            $this->log( 'Check data against internal order' );

            // Check order amount
            if( ! $this->amounts_equal( $data['amount_gross'], $order->order_total ) ) {
                $pfError = true;
                $pfErrMsg = PF_ERR_AMOUNT_MISMATCH;
            }
            // Check session ID
            elseif( strcasecmp( $data['custom_str1'], $order->order_key ) != 0 )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_SESSIONID_MISMATCH;
            }
        }

        // Check status and update order
        if( ! $pfError && ! $pfDone ) {
            $this->log( 'Check status and update order' );

		if ( $order->order_key !== $order_key ) { exit; }

    		switch( $data['payment_status'] ) {
                case 'COMPLETE':
                    $this->log( '- Complete' );

                   // Payment completed
					$order->add_order_note( __( 'Qpay ITN payment completed', 'woocommerce-gateway-qpay' ) );
					$order->payment_complete();
                    break;

    			case 'FAILED':
                    $this->log( '- Failed' );

                    $order->update_status( 'failed', sprintf(__('Payment %s via Qpay ITN.', 'woocommerce-gateway-qpay' ), strtolower( sanitize_text_field( $data['payment_status'] ) ) ) );

        			break;

    			case 'PENDING':
                    $this->log( '- Pending' );

                    // Need to wait for "Completed" before processing
        			$order->update_status( 'pending', sprintf(__('Payment %s via Qpay ITN.', 'woocommerce-gateway-qpay' ), strtolower( sanitize_text_field( $data['payment_status'] ) ) ) );
        			break;

    			default:
                    // If unknown status, do nothing (safest course of action)
    			break;
            }
        }

        // If an error occurred
        if( $pfError ) {
            $this->log( 'Error occurred: '. $pfErrMsg );
        }

        // Close log
        $this->log( '', true );

    	return $pfError;
    } // End check_itn_request_is_valid()

	/**
	 * Check Qpay ITN response.
	 *
	 * @since 1.0.0
	 */
	function check_itn_response() {
		$_POST = stripslashes_deep( $_GET );
        do_action( 'valid-qpay-standard-itn-request', $_POST );
	} // End check_itn_response()

	/**
	 * Successful Payment!
	 *
	 * @since 1.0.0
	 */
	function successful_request( $posted ) {
		if ( ! isset( $posted['referenceId'] )  ) { return false; }

		$order_id = (int) $posted['orderId'];
		$order_key = esc_attr( $posted['referenceId'] );
		$order = new WC_Order( $order_id );

		if ( $order->order_key !== $order_key ) { exit; }

		if ( $order->status !== 'completed' ) {
			// We are here so lets check status and do actions
			switch ( strtolower( $posted['status'] ) ) {
				case 'success' :
					// Payment completed
					$order->add_order_note( __( 'Qpay ITN payment completed', 'woocommerce-gateway-qpay' ) );
					$order->payment_complete();
				break;
				case 'denied' :
				case 'expired' :
				case 'failure' :
				case 'voided' :
					// Failed order
					$order->update_status( 'failed', sprintf(__('Payment %s via Qpay ITN.', 'woocommerce-gateway-qpay' ), strtolower( sanitize_text_field( $posted['payment_status'] ) ) ) );
				break;
				default:
					// Hold order
					$order->update_status( 'on-hold', sprintf(__('Payment %s via Qpay ITN.', 'woocommerce-gateway-qpay' ), strtolower( sanitize_text_field( $posted['payment_status'] ) ) ) );
				break;
			} // End SWITCH Statement

			wp_redirect( $this->get_return_url( $order ) );
			exit;
		} // End IF Statement

		exit;
	}

	/**
	 * Setup constants.
	 *
	 * Setup common values and messages used by the Qpay gateway.
	 *
	 * @since 1.0.0
	 */
	function setup_constants () {
		global $woocommerce;
		//// Create user agent string
		// User agent constituents (for cURL)
//		define( 'PF_SOFTWARE_NAME', 'WooCommerce' );
//		define( 'PF_SOFTWARE_VER', $woocommerce->version );
//		define( 'PF_MODULE_NAME', 'WooCommerce-Qpay-Free' );
//		define( 'PF_MODULE_VER', $this->version );

		// Features
		// - PHP
		$pfFeatures = 'PHP '. phpversion() .';';

		// - cURL
		if( in_array( 'curl', get_loaded_extensions() ) )
		{
		    define( 'PF_CURL', '' );
		    $pfVersion = curl_version();
		    $pfFeatures .= ' curl '. $pfVersion['version'] .';';
		}
		else
		    $pfFeatures .= ' nocurl;';

		// Create user agrent
//		define( 'PF_USER_AGENT', PF_SOFTWARE_NAME .'/'. PF_SOFTWARE_VER .' ('. trim( $pfFeatures ) .') '. PF_MODULE_NAME .'/'. PF_MODULE_VER );

		// General Defines
		define( 'PF_TIMEOUT', 15 );
		define( 'PF_EPSILON', 0.01 );

		// Messages
		    // Error
		define( 'PF_ERR_AMOUNT_MISMATCH', __( 'Amount mismatch', 'woocommerce-gateway-qpay' ) );
		define( 'PF_ERR_BAD_ACCESS', __( 'Bad access of page', 'woocommerce-gateway-qpay' ) );
		define( 'PF_ERR_BAD_SOURCE_IP', __( 'Bad source IP address', 'woocommerce-gateway-qpay' ) );
		define( 'PF_ERR_CONNECT_FAILED', __( 'Failed to connect to Qpay', 'woocommerce-gateway-qpay' ) );
		define( 'PF_ERR_INVALID_SIGNATURE', __( 'Security signature mismatch', 'woocommerce-gateway-qpay' ) );
		define( 'PF_ERR_MERCHANT_ID_MISMATCH', __( 'Merchant ID mismatch', 'woocommerce-gateway-qpay' ) );
		define( 'PF_ERR_NO_SESSION', __( 'No saved session found for ITN transaction', 'woocommerce-gateway-qpay' ) );
		define( 'PF_ERR_ORDER_ID_MISSING_URL', __( 'Order ID not present in URL', 'woocommerce-gateway-qpay' ) );
		define( 'PF_ERR_ORDER_ID_MISMATCH', __( 'Order ID mismatch', 'woocommerce-gateway-qpay' ) );
		define( 'PF_ERR_ORDER_INVALID', __( 'This order ID is invalid', 'woocommerce-gateway-qpay' ) );
		define( 'PF_ERR_ORDER_NUMBER_MISMATCH', __( 'Order Number mismatch', 'woocommerce-gateway-qpay' ) );
		define( 'PF_ERR_ORDER_PROCESSED', __( 'This order has already been processed', 'woocommerce-gateway-qpay' ) );
		define( 'PF_ERR_PDT_FAIL', __( 'PDT query failed', 'woocommerce-gateway-qpay' ) );
		define( 'PF_ERR_PDT_TOKEN_MISSING', __( 'PDT token not present in URL', 'woocommerce-gateway-qpay' ) );
		define( 'PF_ERR_SESSIONID_MISMATCH', __( 'Session ID mismatch', 'woocommerce-gateway-qpay' ) );
		define( 'PF_ERR_UNKNOWN', __( 'Unkown error occurred', 'woocommerce-gateway-qpay' ) );

		    // General
		define( 'PF_MSG_OK', __( 'Payment was successful', 'woocommerce-gateway-qpay' ) );
		define( 'PF_MSG_FAILED', __( 'Payment has failed', 'woocommerce-gateway-qpay' ) );
		define( 'PF_MSG_PENDING',
		    __( 'The payment is pending. Please note, you will receive another Instant', 'woocommerce-gateway-qpay' ).
		    __( ' Transaction Notification when the payment status changes to', 'woocommerce-gateway-qpay' ).
		    __( ' "Completed", or "Failed"', 'woocommerce-gateway-qpay' ) );
	} // End setup_constants()

	/**
	 * log()
	 *
	 * Log system processes.
	 *
	 * @since 1.0.0
	 */

	function log ( $message, $close = false ) {
	//	if ( ( $this->settings['testmode'] != 'yes' && ! is_admin() ) ) { return; }
        error_log( $message );
		static $fh = 0;
		if( $close ) {
            @fclose( $fh );
        } else {
            // If file doesn't exist, create it
            if( !$fh ) {
                $pathinfo = pathinfo( __FILE__ );
                $dir = str_replace( '/classes', '/logs', $pathinfo['dirname'] );
                $fh = @fopen( $dir .'/qpay.log', 'w' );
            }

            // If file was successfully created
            if( $fh ) {
                $line = $message ."\n";

                fwrite( $fh, $line );
            }
        }
	} // End log()

	/**
	/**
	 * amounts_equal()
	 *
	 * Checks to see whether the given amounts are equal using a proper floating
	 * point comparison with an Epsilon which ensures that insignificant decimal
	 * places are ignored in the comparison.
	 *
	 * eg. 100.00 is equal to 100.0001
	 *
	 * @author Jonathan Smit
	 * @param $amount1 Float 1st amount for comparison
	 * @param $amount2 Float 2nd amount for comparison
	 * @since 1.0.0
	 */
	function amounts_equal ( $amount1, $amount2 ) {
		if( abs( floatval( $amount1 ) - floatval( $amount2 ) ) > PF_EPSILON ) {
			return( false );
		} else {
			return( true );
		}
	} // End amounts_equal()

} // End Class