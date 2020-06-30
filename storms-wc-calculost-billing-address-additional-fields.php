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

//<editor-fold desc="Campos adicionais no Billing Address do Customer - REST API">

/**
 * Add extra fields in customers response.
 *
 * @param WP_REST_Response $response  The response object.
 * @param WP_User          $customer  User object used to create response.
 * @return WP_REST_Response
 */
function storms_dex_customers_response( $response, $customer ) {
	// Billing fields.
	$response->data['billing']['tipo_compra'] = $customer->billing_tipo_compra;
	$response->data['billing']['is_contribuinte'] = ( $customer->billing_is_contribuinte == 'is_contribuinte' );

	return $response;
}
add_filter( 'woocommerce_rest_prepare_customer', 'storms_dex_customers_response', 100, 2 );

/**
 * Add extra fields in orders response.
 *
 * @param WP_REST_Response $response The response object.
 * @param WC_Order         $order    Order object.
 * @return WP_REST_Response
 * @throws Exception
 */
function storms_dex_orders_response( $response, $order ) {
	// Billing fields.
	$response->data['billing']['tipo_compra'] = $order->get_meta( '_billing_tipo_compra' );
	$response->data['billing']['is_contribuinte'] = ( $order->get_meta( '_billing_is_contribuinte' ) == 'is_contribuinte' );

	// Base ST por produto
	$line_items = $order->get_items( 'line_item' );
	foreach ( $line_items as $key => $item ) {
		$base_st = wc_get_order_item_meta( $item->get_id(), '_base_st', true );
		if( $base_st == '' ) {
			$base_st = 0;
			wc_add_order_item_meta( $item->get_id(), '_base_st', 0 );
		}

		if( isset( $response->data['line_items'] ) ) {
			foreach ($response->data['line_items'] as $k => $it) {
				if ($it['id'] == $key) {
					$response->data['line_items'][$k]['base_st'] = $base_st;
					break;
				}
			}
		}
	}

	return $response;
}
add_filter( 'woocommerce_rest_prepare_shop_order_object', 'storms_dex_orders_response', 100, 2 );

/**
 * Addresses schema.
 *
 * @param array $schema Default schema properties.
 * @return array
 */
function storms_dex_addresses_schema( $properties ) {
	$properties['billing']['properties']['tipo_compra'] = array(
		'description' => __( 'Motivo da compra realizada.', 'storms' ),
		'type'        => 'string',
		'context'     => array( 'view', 'edit' ),
	);

	$properties['billing']['properties']['is_contribuinte'] = array(
		'description' => __( 'Indica se o cliente é contribuinte.', 'storms' ),
		'type'        => 'bool',
		'context'     => array( 'view', 'edit' ),
	);

	return $properties;
}
add_filter( 'woocommerce_rest_customer_schema', 'storms_dex_addresses_schema', 100 );
add_filter( 'woocommerce_rest_shop_order_schema', 'storms_dex_addresses_schema', 100 );

//</editor-fold>

