<?php
/**
 * Plugin Name: Storms WooCommerce Calculo ST
 * Plugin URI: https://github.com/vinigarcia87/storms-woocommerce-calculost
 * Description: Criação do calculo de imposto ST no WooCommerce
 * Author: Storms Websolutions - Vinicius Garcia
 * Author URI: http://storms.com.br/
 * Version: 1.0
 * License: GPLv2 or later
 *
 * WC requires at least: 3.9.2
 * WC tested up to: 3.9.2
 *
 * Text Domain: storms
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Criamos as tabelas necessarias para o plugin
 */
function storms_wc_calculost_install() {
	include __DIR__ . '/install.php';
}

register_activation_hook( __FILE__, 'storms_wc_calculost_install' );

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	include __DIR__ . '/storms-wc-calculost-backend.php';

	/**
	 * Register Prosoftin API classes
	 */
	function storms_wc_calculost_start_rest_api() {
		include_once __DIR__ . '/class-storms-wc-calculo-st-api.php';

		$controller = new Storms_WC_Calculo_ST_API();
		$controller->register_routes();

		include_once __DIR__ . '/class-storms-wc-calculo-st-order-api.php';
		(new Storms_WC_Calculo_ST_Order_API())->register();
	}
	add_action( 'rest_api_init', 'storms_wc_calculost_start_rest_api' );
	include __DIR__ . '/storms-wc-calculost-products-additional-fields.php';
	include __DIR__ . '/storms-wc-calculost-billing-address-additional-fields.php';

	include __DIR__ . '/storms-wc-calculost-frontend.php';
	include __DIR__ . '/storms-wc-calculost-formula.php';

}
