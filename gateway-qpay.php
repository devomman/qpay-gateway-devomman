<?php
/**
 * Plugin Name: WooCommerce Qpay Gateway
 * Plugin URI: https://qpayi.com/products/qpay-payment-gateway/
 * Description: Receive payments using the Qatar Qpay payments provider.
 * Author: QPAY Ecommerce
 * Author URI: https://qpayi.com/
 * Version: 1.2.7
 *
 * Copyright (c) 2015 WooThemes
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Required functions
 */
 //  Disable Update By Omman Start
// if ( ! function_exists( 'woothemes_queue_update' ) )
// 	require_once( 'woo-includes/woo-functions.php' );

/**
 * Plugin updates
 */

// woothemes_queue_update( plugin_basename( __FILE__ ), '557bf07293ad916f20c207c6c9cd15ff', '18596' );
 //  Disable Update By Omman End
load_plugin_textdomain( 'woocommerce-gateway-qpay', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );

add_action( 'plugins_loaded', 'woocommerce_qpay_init', 0 );

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */
function woocommerce_qpay_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	require_once( plugin_basename( 'classes/class-wc-gateway-qpay.php' ) );

	add_filter('woocommerce_payment_gateways', 'woocommerce_qpay_add_gateway' );

} // End woocommerce_qpay_init()

/**
 * Add the gateway to WooCommerce
 *
 * @since 1.0.0
 */
function woocommerce_qpay_add_gateway( $methods ) {
	$methods[] = 'WC_Gateway_Qpay';
	return $methods;
} // End woocommerce_qpay_add_gateway()