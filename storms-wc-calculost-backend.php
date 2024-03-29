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
    	// Company is not required, unless we are using person_type = 'Pessoa Juridica'
		$new_fields['billing_company']['required'] = false;
        if ( 1 == $settings['person_type'] || 3 == $settings['person_type'] ) {

            $new_fields['billing_tipo_compra'] = array(
                'type'        => 'radio',
                'label'       => __( 'Qual o motivo da sua compra?', 'storms' ),
                'class'       => array( 'form-check-inline' ),
				'label_class' => array(), // array( 'sr-only' ),
				'custom_attributes' => array(
					'external_div_class' => 'storms-tipo-compra',
				),
                'clear'       => true,
                'required'    => true, // No save nos vamos marcar como required, caso o usuario seja uma Pessoa Juridica
                'default'	  => 'is_consumo',
                'options'     => array(
                    'is_revenda' => 'Compra para REVENDA',
					'is_consumo' => 'Compra para CONSUMO',
                ),
				'priority'	  => 28,
            );

            $new_fields['billing_is_contribuinte'] = array(
                'type'        => 'radio',
                'label'       => __( 'Contribuinte?', 'storms' ),
                'class'       => array( 'form-check-inline' ),
				'label_class' => array( 'sr-only' ),
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

            // All custom fields are displayed as required
			$new_fields['billing_persontype']['required'] = true;
			$new_fields['billing_cpf']['required'] = true;
			$new_fields['billing_cnpj']['required'] = true;
			$new_fields['billing_ie']['required'] = true;
			$new_fields['billing_company']['required'] = true;
			$new_fields['billing_tipo_compra']['required'] = true;
			$new_fields['billing_is_contribuinte']['required'] = true;
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
 * Reorder billing fields in WooCommerce Checkout and WooCommerce My Account Billing Form
 * @link : http://wordpress.stackexchange.com/a/127490/54025
 */
function storms_wc_calculost_checkout_billing_order_fields( $fields ) {

    $order = storms_wc_calculost_order_billing_fields();

    foreach( $order as $field => $priority ) {
		if( is_checkout() ) {
			if( isset( $fields["billing"][$field] ) ) {
				$fields["billing"][$field]['priority'] = $priority;
			}
		} elseif( is_account_page() ) {
			if( isset( $fields[$field] ) ) {
				$fields[$field]['priority'] = $priority;
			}
		}
    }

    return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'storms_wc_calculost_checkout_billing_order_fields', 20 );
add_filter( 'woocommerce_billing_fields', 'storms_wc_calculost_checkout_billing_order_fields', 20 );

/**
 * Grava, junto com o pedido, os dados usados para o calculo da ST, se houver
 *
 * @param $item_id
 * @param $item
 * @param $order_id
 * @throws Exception
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

	// Normalizamos a Inscrição Estadual - strip leading spaces, '.', '-' and '/'
	$billing_ie = strtolower( trim( str_replace( '.', '', str_replace( '-', '', str_replace( '/', '', $billing_ie ) ) ) ) );

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

/**
 * Desabilita a validaçao de checkout criada pelo plugin woocommerce-extra-checkout-fields-for-brazil
 * Vamos substituir pela nossa propria validaçao
 * @return bool
 */
function storms_wc_calculost_wcbcf_disable_checkout_validation() {
	return true;
}
add_filter( 'wcbcf_disable_checkout_validation', 'storms_wc_calculost_wcbcf_disable_checkout_validation' );

/**
 * Desabilitamos temporariamente os campos required no checkout, pois vamos validar-los nos mesmos em outro momento
 *
 * @param $fields
 * @return mixed
 */
function storms_wc_calculost_checkout_billing_fields_validation( $fields ) {

	$billing_persontype = isset( $_POST['billing_persontype'] ) ? intval( $_POST['billing_persontype'] ) : 0;

	if( is_checkout() ) {
		// If customer is 'Pessoa Fisica', we won't validate 'Pessoa Juridica' Fields
		unset( $fields['billing']['billing_persontype']['required'] );
		unset( $fields['billing']['billing_cnpj']['required'] );
		unset( $fields['billing']['billing_ie']['required'] );
		unset( $fields['billing']['billing_company']['required'] );
		unset( $fields['billing']['billing_tipo_compra']['required'] );
		unset( $fields['billing']['billing_is_contribuinte']['required'] );

		// If customer is 'Pessoa Juridica', we won't validate 'Pessoa Fisica' Fields
		unset( $fields['billing']['billing_cpf']['required'] );
		if( isset( $fields['billing']['billing_rg'] ) ) {
			unset( $fields['billing']['billing_rg']['required'] );
		}
	} elseif( is_account_page() ) {
		// If customer is 'Pessoa Fisica', we won't validate 'Pessoa Juridica' Fields
		unset( $fields['billing_persontype']['required'] );
		unset( $fields['billing_cnpj']['required'] );
		unset( $fields['billing_ie']['required'] );
		unset( $fields['billing_company']['required'] );
		unset( $fields['billing_tipo_compra']['required'] );
		unset( $fields['billing_is_contribuinte']['required'] );

		// If customer is 'Pessoa Juridica', we won't validate 'Pessoa Fisica' Fields
		unset( $fields['billing_cpf']['required'] );
		if( isset( $fields['billing_rg'] ) ) {
			unset( $fields['billing_rg']['required'] );
		}
	}

	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'storms_wc_calculost_checkout_billing_fields_validation' );

/**
 * Validaçao dos campos customizados ao salvar o endereço do cliente na pagina de Checkout
 *
 * @param $fields
 * @param WP_Error $errors
 */
function storms_wc_calculost_validate_custom_fields( $fields, $errors ) {

	$errors_list = storms_validate_calculost_custom_fields( $fields );

	foreach( $errors_list as $error ) {
		$errors->add( $error['code'], $error['message'], $error['data'] );
	}
}
add_action( 'woocommerce_after_checkout_validation', 'storms_wc_calculost_validate_custom_fields', 10, 2 );

/**
 * Validaçao dos campos customizados ao salvar o endereço do cliente na pagina de Minha Conta
 *
 * @param int         $user_id User ID being saved.
 * @param string      $load_address Type of address e.g. billing or shipping.
 * @param array       $address The address fields.
 * @param WC_Customer $customer The customer object being saved. @since 3.6.0
 */
function dexpecas_woocommerce_after_save_address_validation( $user_id, $load_address, $address, $customer ) {

	$billing_persontype = intval( $customer->get_meta( 'billing_persontype' ) );

	$non_required_fields = [];
	if( 1 === $billing_persontype ) {
		// If customer is 'Pessoa Fisica', we won't validate 'Pessoa Juridica' Fields
		$non_required_fields = [ 'billing_persontype', 'billing_cnpj', 'billing_ie', 'billing_company', 'billing_tipo_compra', 'billing_is_contribuinte' ];

	} elseif( 2 === $billing_persontype ) {
		// If customer is 'Pessoa Juridica', we won't validate 'Pessoa Fisica' Fields
		$non_required_fields = [ 'billing_cpf' ];
	}

	$notice_errors = [];
	$notices = wc_get_notices();
	foreach( $notices['error'] as $notice ) {
		if( ! in_array( $notice['data']['id'], $non_required_fields ) ) {
			$notice_errors[] = $notice;
		}
	}
	$notices['error'] = $notice_errors;
	wc_set_notices( $notices );

	// Aplicar a validaçao dos campos customizados
	$fields = [
		'billing_persontype'		=> $customer->get_meta( 'billing_persontype' ),

		'billing_country' 			=> $customer->get_billing_country(),
		'billing_cpf'     			=> $customer->get_meta( 'billing_cpf' ),
		'billing_rg'				=> $customer->get_meta( 'billing_rg' ),

		'billing_cnpj'				=> $customer->get_meta( 'billing_cnpj' ),
		'billing_ie'				=> $customer->get_meta( 'billing_ie' ),
		'billing_company'			=> $customer->get_billing_company(),
		'billing_tipo_compra'		=> $customer->get_meta( 'billing_tipo_compra' ),
		'billing_is_contribuinte'	=> $customer->get_meta( 'billing_is_contribuinte' ),
	];

	$errors_list = storms_validate_calculost_custom_fields( $fields );

	foreach( $errors_list as $error ) {
		wc_add_notice( $error['message'], $error['code'], $error['data'] );
	}
}
add_action( 'woocommerce_after_save_address_validation', 'dexpecas_woocommerce_after_save_address_validation', 10, 4 );

/**
 * Validaçao dos campos customizados para o Calculo ST
 *
 * @param $fields
 * @return array
 */
function storms_validate_calculost_custom_fields( $fields ) {
	$errors = [];

	$billing_persontype = intval( $fields['billing_persontype'] );

	$settings           = get_option( 'wcbcf_settings' );
	$person_type        = intval( $settings['person_type'] ); // 1: Pessoa Física e Pessoa Jurídica; 2: Pessoa Física apenas; 3: Pessoa Jurídica apenas;
	$only_brazil        = isset( $settings['only_brazil'] ) ? true : false;

	$field_labels = [
		'billing_persontype'		=> __( 'Tipo de Pessoa', 'storms' ),

		'billing_cpf'     			=> __( 'CPF', 'storms' ),
		'billing_rg'				=> __( 'RG', 'storms' ),

		'billing_cnpj'				=> __( 'CNPJ', 'storms' ),
		'billing_ie'				=> __( 'Inscrição Estadual', 'storms' ), // __( 'State Registration', 'woocommerce-extra-checkout-fields-for-brazil' )
		'billing_company'			=> __( 'Billing Company', 'woocommerce' ),
		'billing_tipo_compra'		=> __( 'Motivo da compra', 'storms' ),
		'billing_is_contribuinte'	=> __( 'Contribuinte', 'storms' ),
	];

	if ( $only_brazil && 'BR' !== $fields['billing_country'] || 0 === $person_type ) {
		return [];
	}

	if( 0 === $billing_persontype && 1 === $person_type ) {

		$key = 'billing_persontype';
		$field_label = sprintf( _x( 'Billing %s', 'checkout-validation', 'woocommerce' ), $field_labels[$key] );
		$errors[] = [
			'code' 	  => 'required-field',
			'message' => apply_filters('woocommerce_checkout_required_field_notice', sprintf(__('%s is a required field.', 'woocommerce'), '<strong>' . esc_html($field_label) . '</strong>'), $field_label),
			'data'    => [ 'id' => $key ]
		];

	} else {

		// If customer is 'Pessoa Fisica'
		if( ( 1 === $person_type && 1 === $billing_persontype ) || 2 === $person_type ) {

			$required_fields = [ 'billing_cpf' ];

			// RG can be not required
			if( isset( $settings['rg'] ) ) {
				$required_fields[] = 'billing_rg';
			}

			// Fields CPF and RG are required
			foreach( $required_fields as $key ) {
				$field_label = sprintf( _x( 'Billing %s', 'checkout-validation', 'woocommerce' ), $field_labels[$key] );
				if( empty( $fields[$key] ) ) {
					$errors[] = [
						'code' 	  => 'required-field',
						'message' => apply_filters('woocommerce_checkout_required_field_notice', sprintf(__('%s is a required field.', 'woocommerce'), '<strong>' . esc_html($field_label) . '</strong>'), $field_label),
						'data'    => [ 'id' => $key ]
					];
				}
			}

			$key = 'billing_cpf';
			if( ! empty( $fields[$key] ) && isset( $settings['validate_cpf'] ) && ! Extra_Checkout_Fields_For_Brazil_Formatting::is_cpf( $fields[$key] ) ) {
				$errors[] = [
					'code' 	  => 'validation',
					'message' => sprintf(__('%1$s não é um CPF válido.', 'storms'), '<strong>' . esc_html($field_label) . '</strong>'),
					'data'    => [ 'id' => $key ]
				];
			}
		}
		// If customer is 'Pessoa Juridica'
		if( ( 1 === $person_type && 2 === $billing_persontype ) || 3 === $person_type ) {

			// Fields CNPJ, IE, Company, Tipo Compra and Is Contribuinte are required
			$required_fields = [ 'billing_cnpj', 'billing_ie', 'billing_company', 'billing_tipo_compra', 'billing_is_contribuinte' ];

			foreach( $required_fields as $key ) {
				$field_label = sprintf( _x( 'Billing %s', 'checkout-validation', 'woocommerce' ), $field_labels[$key] );
				if( empty( $fields[$key] ) ) {
					$errors[] = [
						'code' 	  => 'required-field',
						'message' => apply_filters('woocommerce_checkout_required_field_notice', sprintf(__('%s is a required field.', 'woocommerce'), '<strong>' . esc_html($field_label) . '</strong>'), $field_label),
						'data'    => [ 'id' => $key ]
					];
				}
			}

			$key = 'billing_cnpj';
			if( ! empty( $fields[$key] ) && isset( $settings['validate_cnpj'] ) && ! Extra_Checkout_Fields_For_Brazil_Formatting::is_cnpj( wp_unslash( $fields[$key] ) ) ) {
				$errors[] = [
					'code' 	  => 'validation',
					'message' => sprintf(__('%1$s não é um CNPJ válido.', 'storms'), '<strong>' . esc_html($field_label) . '</strong>'),
					'data'    => [ 'id' => $key ]
				];
			}
		}
	}

	return $errors;
}

/**
 * Mostramos os campos adicionais do Calculo ST
 * Nas informações do cliente, dentro do pedido no admin
 *
 * @param $order
 */
function storms_wc_calculost_admin_order_data_after_billing_address( $order ) {

	// Get plugin settings.
	$settings    = get_option( 'wcbcf_settings' );
	$person_type = intval( $settings['person_type'] );

	if( ( ( 2 === intval( $order->get_meta( '_billing_persontype' ) ) && 1 === $person_type ) || 3 === $person_type ) &&
		( $order->get_meta( '_billing_ie' ) != '' && strtolower( $order->get_meta( '_billing_ie' ) ) != 'isento' ) ) {
		echo '<strong>' . esc_html__( 'Tipo da Compra', 'storms' ) .': </strong>' . esc_html( $order->get_meta( '_billing_tipo_compra' ) == 'is_revenda' ? 'Revenda' : 'Consumo' ) . '<br>';

		if( $order->get_meta( '_billing_tipo_compra' ) == 'is_consumo' ) {
			echo '<strong>' . esc_html__('É Contribuinte?', 'storms') . ' </strong>' . esc_html($order->get_meta('_billing_is_contribuinte') == 'is_contribuinte' ? 'Sim' : 'Não') . '<br>';
		}
	}
}
add_action( 'woocommerce_admin_order_data_after_billing_address', 'storms_wc_calculost_admin_order_data_after_billing_address', 20 );

/**
 * Definimos os valores default para pessoa fisica e pessoa juridica isento
 * pois nestes casos, os campos de tipo de compra e contribuinte nao abrem para seleçao
 *
 * - Pessoa Fisica - Tipo Venda: Consumo / Contribuinte
 * - Pessoa Juridica Insc. Est. ISENTO - Tipo Venda: Consumo / Não Contribuinte
 *
 * @param $order
 * @param $data
 */
function storms_wc_calculost_default_values_for_tipo_compra( $order, $data ) {

	// Pessoa Fisica - Tipo Venda: Consumo / Contribuinte
	if( '1' === $data['billing_persontype'] ) {
		$order->update_meta_data( '_billing_tipo_compra', 'is_consumo' ); // is_consumo / is_revenda
		$order->update_meta_data( '_billing_is_contribuinte', 'is_contribuinte' ); // is_contribuinte / not_contribuinte
	}

	// Pessoa Juridica Insc. Est. ISENTO - Tipo Venda: Consumo / Não Contribuinte
	if( '2' === $data['billing_persontype'] && 'isento' === strtolower( $data['billing_ie'] ) ) {
		$order->update_meta_data( '_billing_tipo_compra', 'is_consumo' ); // is_consumo / is_revenda
		$order->update_meta_data( '_billing_is_contribuinte', 'not_contribuinte' ); // is_contribuinte / not_contribuinte
	}

}
add_action( 'woocommerce_checkout_create_order', 'storms_wc_calculost_default_values_for_tipo_compra', 10, 2 );
