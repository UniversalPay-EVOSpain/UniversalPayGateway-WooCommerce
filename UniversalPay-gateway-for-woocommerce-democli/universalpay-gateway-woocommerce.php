<?php
/*
Plugin Name: UniversalPay Gateway for WooCommerce
Plugin URI: https://www.universalpay.es/
Description: This plugins allows to users to include UniversalPay at payment method
Author: jbartolome
Version: 1.2.0
Author URI: https://www.universalpay.es/
*/

add_action( 'plugins_loaded', 'universalpay_plugins_loaded' );
add_action( 'init', 'universalpay_inicio' );

function universalpay_inicio() {
	load_plugin_textdomain( "universalpay_gw_woo", false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

function universalpay_plugins_loaded() {
	if ( !class_exists( 'WC_Payment_Gateway' ) ) exit;

	include_once ('class-wc-universalpay-gateway.php');
	
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_gateway_universalpay_gateway' );
}

function woocommerce_add_gateway_universalpay_gateway($methods) {
	$methods[] = 'WC_universalpay_Gateway';
	return $methods;
}

function universalpay_enqueue($hook) {
	if ( 'woocommerce_page_wc-settings' != $hook ) {
        return;
    }

    wp_enqueue_script( 'jquery-validate', plugin_dir_url( __FILE__ ) . 'js/jquery.validate.min.js', array( "jquery" ), "1.13.1", true );
}
add_action( 'admin_enqueue_scripts', 'universalpay_enqueue' );


