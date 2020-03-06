<?php
/**
 * Storms Framework (http://storms.com.br/)
 *
 * @author    Vinicius Garcia | vinicius.garcia@storms.com.br
 * @copyright (c) Copyright 2012-2020, Storms Websolutions
 * @license   GPLv2 - GNU General Public License v2 or later (http://www.gnu.org/licenses/gpl-2.0.html)
 * @package   Storms
 * @version   1.0.0
 *
 * Calculo ST Backend
 * Calculo ST backend modifications
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adicionamos os scripts para gerenciar do calculo da ST
 */
function storms_wc_calculost_enqueue_scripts() {
	if( is_checkout() || is_wc_endpoint_url( 'edit-address' ) ) {

		/*/
		// Esta causando um bug absurdo no endpoint( 'edit-address' )
		wp_deregister_script( 'wc-address-i18n' );

		// Ajustamos os scripts dependentes de wc-address-i18n, para que nao precisem mais dele
		wp_deregister_script( 'wc-cart' );
		wp_enqueue_script( 'wc-cart', plugins_url( 'assets/js/frontend/cart.js', WC_PLUGIN_FILE ), array('jquery', 'wc-country-select'), WC_VERSION, true );
		wp_deregister_script( 'wc-checkout' );
		wp_enqueue_script( 'wc-checkout', plugins_url( 'assets/js/frontend/checkout.js', WC_PLUGIN_FILE ), array('jquery', 'woocommerce', 'wc-country-select'), WC_VERSION, true );
		/**/

		// Adicionamos o script do calculo da ST
		wp_enqueue_script( 'storms_calculo_st', plugin_dir_url( __FILE__ ) . 'assets/js/storms-calculo-st.js', array('jquery'), '1.0.0', true );
		wp_localize_script( 'storms_calculo_st', 'storms_calculo_st', array(
			'ajax_url' 			=> admin_url( 'admin-ajax.php' ),
			'ajax_nonce' 		=> wp_create_nonce( 'storms_calculo_st_address_form' ),
			'is_checkout_page' 	=> is_checkout() ? 'yes' : 'no'
		) );
	}
}
add_action( 'wp_enqueue_scripts', 'storms_wc_calculost_enqueue_scripts' );
