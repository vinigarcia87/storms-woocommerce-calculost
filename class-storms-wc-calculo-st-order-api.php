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
 * Storms_WC_Calculo_ST_Order_API class
 * Adiciona os campos de Calculo ST as Orders do WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Storms_WC_Calculo_ST_Order_API
{
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_calculo_st_order_meta_fields' ), 100 );
	}

	/**
	 * Register Calculo ST order meta fields in WP REST API.
	 */
	public function register_calculo_st_order_meta_fields() {
		if( ! function_exists( 'register_rest_field' ) ) {
			return;
		}

		// Register Fee meta Data do REST API
		register_rest_field( 'shop_order',
			'calculo_st',
			array(
				'get_callback'    => array( $this, 'get_calculo_st_callback' ),
				'update_callback' => array( $this, 'update_calculo_st_callback' ),
				'schema'          => array(
					'description' => __( 'Valores de cálculo da ST', 'storms' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
				),
			)
		);
	}

	//<editor-fold desc="Fee Meta Fields">

	/**
	 * Get transaction id callback.
	 *
	 * @param array           $data    Details of current response.
	 * @param string          $field   Name of field.
	 * @param WP_REST_Request $request Current request.
	 * @return string|null
	 * @throws Exception
	 */
	public function get_calculo_st_callback( $data, $field, $request ) {
		/** @var $order \WC_Order */
		$order = wc_get_order($data['id']);

		$line_items_fee = $order->get_items( 'fee' );

		$items_fee = array();
		foreach ( $line_items_fee as $item_id => $item ) {
			$items_fee[] = array(
				'id'                => $item_id,
				'UfOrigem'          => wc_get_order_item_meta( $item_id, 'UfOrigem', true ),
				'UfDestino'         => wc_get_order_item_meta( $item_id, 'UfDestino', true ),
				'AliqInterestadual' => wc_get_order_item_meta( $item_id, 'AliqInterna', true ),
				'AliqInterna'       => wc_get_order_item_meta( $item_id, 'AliqInterestadual', true ),
				'Mva'               => wc_get_order_item_meta( $item_id, 'Mva', true ),
				'AliqImportado'     => wc_get_order_item_meta( $item_id, 'AliqImportado', true ),
				'MvaImportado'      => wc_get_order_item_meta( $item_id, 'MvaImportado', true ),
				'Formula'      		=> wc_get_order_item_meta( $item_id, 'Formula', true ),
				'Fcp'      			=> wc_get_order_item_meta( $item_id, 'Fcp', true ),
				//BaseSt             => wc_get_order_item_meta( $item_id, 'basest', true ),
			);
		}

		return ( ! empty( $items_fee ) ) ? $items_fee[0] : null;
	}

	/**
	 * Update fee meta fields callback.
	 *
	 * @param string  $value  The value of the field.
	 * @param WP_Post $object The object from the response.
	 *
	 * @return bool
	 */
	public function update_calculo_st_callback( $value, $object ) {
		if ( ! $value || ! is_string( $value ) ) {
			return false;
		}

		// We don't let the ERP change this!
		return false;
	}

	//</editor-fold>
}
