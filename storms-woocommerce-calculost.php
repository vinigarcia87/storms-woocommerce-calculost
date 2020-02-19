<?php
/**
 * Plugin Name: Storms WooCommerce Calculo ST
 * Plugin URI: https://github.com/vinigarcia87/storms-woocommerce-calculost
 * Description: Criação do calculo de imposto ST WooCommerce by Storms
 * Author: Vinicius Garcia
 * Author URI: http://storms.com.br/
 * Version: 1.0.0
 * License: GPLv2 or later
 * Text Domain: storms_wc_calculost
 * Domain Path: languages/
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


include __DIR__ . '/storms-wc-calculost-backend.php';
include __DIR__ . '/storms-wc-calculost-products-additional-fields.php';
include __DIR__ . '/storms-wc-calculost-frontend.php';
include __DIR__ . '/storms-wc-calculost-formula.php';
