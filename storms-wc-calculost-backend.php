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

/**
 * Adicionando campo de 'is consumo' para clientes CNPJ
 * Modificando campo de 'Tipo de Pessoa' para ser um radiolist
 * Marcando campo 'billing_company' como required
 *
 * @param $new_fields
 * @return mixed
 */
function storms_wc_calculost_billing_field( $new_fields ) {
    // Get plugin "WooCommerce Extra Checkout Fields fro Brazil" settings.
    $settings = get_option( 'wcbcf_settings' );

    if ( 0 != $settings['person_type'] ) {

        if ( 1 == $settings['person_type'] || 3 == $settings['person_type'] ) {
            $new_fields['billing_company']['required'] = true;
        } else {
            $new_fields['billing_company']['required'] = false;
        }

        if ( 1 == $settings['person_type'] || 3 == $settings['person_type'] ) {
            $new_fields['billing_tipo_compra'] = array(
                'type'        => 'radio',
                'label'       => __( 'Qual é o motivo da compra realizada?', 'storms' ),
                'class'       => array( 'form-check-inline' ),
				'custom_attributes' => array(
					'external_div_class' => 'storms-tipo-compra',
				),
                'clear'       => true,
                'required'    => true, // No save nos vamos marcar como required, caso o usuario seja uma Pessoa Juridica
                'default'	  => 'is_consumo',
                'options'     => array(
                    'is_consumo' => 'Compra para CONSUMO',
                    'is_revenda' => 'Compra para REVENDA',
                ),
				'priority'	  => 28,
            );

            $new_fields['billing_is_contribuinte'] = array(
                'type'        => 'radio',
                'label'       => __( 'Contribuinte?', 'storms' ),
                'class'       => array( 'form-check-inline' ),
				'custom_attributes' => array(
					'external_div_class' => 'storms-is-contribuinte',
				),
                'clear'       => true,
                'required'    => true,
                'default'	  => 'is_contribuinte',
                'options'     => array(
                    'is_contribuinte'  => 'Contribuinte',
                    'not_contribuinte' => 'Não Contribuinte',
                ),
				'priority'	  => 29,
            );
        }
    }

    return $new_fields;
}
add_filter( 'wcbcf_billing_fields', 'storms_wc_calculost_billing_field' );

/**
 * Ordenamos os campos de billing, para colocar os novos campos no local desejado
 * Return an array of billing fields in order
 */
function storms_wc_calculost_order_billing_fields() {
    $order = array(
		"billing_first_name" 		=> 10,
		"billing_last_name" 		=> 20,
		"billing_email" 			=> 30,

		"billing_phone" 			=> 40,
		"billing_cellphone" 		=> 50,	// ecfb plugin
		"billing_birthdate" 		=> 60,	// ecfb plugin
		"billing_sex" 				=> 70,	// ecfb plugin

		"billing_persontype" 		=> 80,	// ecfb plugin

		"billing_cpf" 				=> 90,	// ecfb plugin
		"billing_rg"				=> 100,	// ecfb plugin

		"billing_company" 			=> 110,
		"billing_cnpj" 				=> 120,	// ecfb plugin
		"billing_ie" 				=> 130,	// ecfb plugin
		"billing_tipo_compra" 		=> 140,	// storms plugin
		"billing_is_contribuinte" 	=> 150,	// storms plugin

		"billing_country" 			=> 160,
		"billing_postcode" 			=> 170,
		"billing_address_1" 		=> 180,
		"billing_number" 			=> 190,	// ecfb plugin
		"billing_address_2" 		=> 200,
		"billing_neighborhood" 		=> 210,	// ecfb plugin
		"billing_city" 				=> 220,
		"billing_state" 			=> 230,
    );

    return $order;
}

/**
 * Reorder billing fields in WooCommerce Checkout
 * @link : http://wordpress.stackexchange.com/a/127490/54025
 */
function storms_wc_calculost_checkout_order_fields( $fields ) {

    $order = storms_wc_calculost_order_billing_fields();

    foreach( $order as $field => $priority ) {
		if( isset( $fields["billing"][$field] ) ) {
			$fields["billing"][$field]['priority'] = $priority;
        }
    }

    return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'storms_wc_calculost_checkout_order_fields' );

/**
 * Reorder billing fields in WooCommerce Address To Edit
 */
function storms_wc_calculost_address_to_edit( $address, $load_address ) {

    $order = storms_wc_calculost_order_billing_fields();

    if( $load_address == 'billing' ) {
        foreach( $order as $field => $priority ) {
            if( isset( $address[$field] ) ) {
				$address[$field]['priority'] = $priority;
            }
        }
    }

    return $address;
}
add_filter( 'woocommerce_address_to_edit', 'storms_wc_calculost_address_to_edit', 10, 2 );

/**
 * Grava, junto com o pedido, os dados usados para o calculo da ST, se houver
 *
 * @param int|bool Item ID or false
 * @param WC_Order_Item $item
 * @param int $order_id
 */
function storms_wc_calculost_add_order_fee_meta( $item_id, $item, $order_id ) {
	/** @var wpdb $wpdb */
	global $wpdb;

	if( !is_user_logged_in() )
		return;

	// Queremos trabalhar apenas com as taxas do pedido
	if( ! is_a( $item, 'WC_Order_Item_Fee' ) ) {
		return;
	}

	$user_id = get_current_user_id();
	$person_type = get_user_meta($user_id, 'billing_persontype', true);
	$billing_cnpj = get_user_meta($user_id, 'billing_cnpj', true);
	$billing_ie = get_user_meta($user_id, 'billing_ie', true);
	$estado_cliente = get_user_meta($user_id, 'shipping_state', true);
	$billing_tipo_compra = get_user_meta($user_id, 'billing_tipo_compra', true);
	$estado_ecomm = WC()->countries->get_base_state();
	$is_contribuinte = ( get_user_meta($user_id, 'billing_is_contribuinte', true) == 'is_contribuinte' );

	// Normalizamos a Inscrição Estadual
	$billing_ie = strtolower( trim( str_replace( '.', '', str_replace( '-', '', $billing_ie ) ) ) );

	// Se a compra é para Pessoa Juridica, com Inscrição Estadual e para o mesmo estado do eComm, não cobramos o imposto
	if( ( $person_type == 2 ) &&
		( $billing_cnpj != '' ) &&
		( $billing_ie != 'isento' && is_numeric( $billing_ie ) ) &&
		( $billing_tipo_compra == 'is_consumo' ) &&
		( $estado_ecomm == $estado_cliente ) ) {
		return;
	}

	$table_name = $wpdb->prefix . 'calculost';
	$tabela_st = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $table_name . " WHERE UfOrigem = %s AND UfDestino = %s", array( $estado_ecomm, $estado_cliente) ) );

	if( !empty( $tabela_st ) ) {
		if ($person_type == 2 && $billing_cnpj != '' && $billing_ie != 'isento' && is_numeric($billing_ie)) {
			$tipo_imposto = '';
			// Se o cliente marcou que a compra eh para CONSUMO, ele deve o DIFAL (Diferencial Aliquota)
			if( $billing_tipo_compra == 'is_consumo' ) {
				// Para compras para consumo, temos DIFAL apenas se "É Contribuinte"
				if( $is_contribuinte ) {
					$tipo_imposto = 'DIFAL';
				}
			}
			// Se o cliente marcou que a compra eh para REVENDA, ele deve ST (Substituição Tributária)
			else if( $billing_tipo_compra == 'is_revenda' ) {
				$tipo_imposto = 'ST';
			}
			wc_add_order_item_meta( $item_id, 'tipo_imposto', $tipo_imposto );

			wc_add_order_item_meta( $item_id, 'UfOrigem', $tabela_st[0]->UfOrigem );
			wc_add_order_item_meta( $item_id, 'UfDestino', $tabela_st[0]->UfDestino );
			wc_add_order_item_meta( $item_id, 'AliqInterna', $tabela_st[0]->AliqInterna );
			wc_add_order_item_meta( $item_id, 'AliqInterestadual', $tabela_st[0]->AliqInterestadual );
			wc_add_order_item_meta( $item_id, 'Mva', $tabela_st[0]->Mva );
			wc_add_order_item_meta( $item_id, 'AliqImportado', $tabela_st[0]->AliqImportado );
			wc_add_order_item_meta( $item_id, 'MvaImportado', $tabela_st[0]->MvaImportado );
			wc_add_order_item_meta( $item_id, 'Formula', $tabela_st[0]->Formula );
			wc_add_order_item_meta( $item_id, 'Fcp', $tabela_st[0]->Fcp );

			$order = new WC_Order( $order_id );

			// Recuperamos as Base ST calculadas
			$order_base_st = WC()->session->get( 'order_base_st' );

			$items = $order->get_items();
			foreach( $items as $line_item_id => $item ) {
				//Get product by supplying variation id or product_id
				$product = wc_get_product( $item->get_product_id() );
				$nao_cobrar_st = get_post_meta( $product->get_id(), '_nao_cobrar_st', true );

				// Se o produto foi marcado para não cobrar ST, entao nao calculamos o imposto para ele
				if( ( $tipo_imposto == 'ST' ) && ( $nao_cobrar_st == 'yes' ) ) {
					continue;
				}

				if( isset( $order_base_st[ $product->get_id() ] ) ) {
					// Salvamos o valor da Base ST do produto
					$base_st =  $order_base_st[ $product->get_id() ];
					wc_add_order_item_meta( $line_item_id, '_base_st', $base_st );
				}
			}

			//wc_add_order_item_meta( $item_id, 'base_st', <<VALOR_TOTAL_ACUMULADO>> );
		}
	}
}
add_action( 'woocommerce_new_order_item', 'storms_wc_calculost_add_order_fee_meta', 10, 3 );
