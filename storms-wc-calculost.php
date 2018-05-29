<?php
/**
 * Plugin Name: Storms WooCommerce Calculo ST
 * Plugin URI: https://github.com/vinigarcia87/storms-woocommerce-receipt
 * Description: Criação do calculo de imposto ST WooCommerce by Storms
 * Author: Vinicius Garcia
 * Author URI: http://storms.com.br/
 * Version: 1.0.0
 * License: GPLv2 or later
 * Text Domain: wc-storms-calculost
 * Domain Path: languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * WooCommerce Calculo da ST
 * --------------------------
 * Add custom fee to cart automatically
 * @see http://stackoverflow.com/a/40850578/1003020
 * @see http://www.remicorson.com/add-custom-fee-to-woocommerce-cart-dynamically/
 * @see https://docs.woocommerce.com/document/add-a-surcharge-to-cart-and-checkout-uses-fees-api/
 */

// Register Prosoftin API classes
add_action( 'rest_api_init', function() {
    include_once( 'class-storms-wc-api-calculo-st.php' );
    $controller = new Storms_WC_API_Calculo_ST();
    $controller->register_routes();
} );

/**
 * Adicionando campo de consumo para clientes CNPJ
 * Modificando campo de Tipo de Pessoa para ser um radiolist
 * Marcando campo billing_company como required
 *
 * @param $new_fields
 * @return mixed
 */
function storms_st_billing_field( $new_fields ) {
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
                'class'       => array( 'form-row-wide', 'storms-tipo-compra' ),
                'clear'       => true,
                'required'    => true, // No save nos vamos marcar como required, caso o usuario seja uma Pessoa Juridica
                'default'	  => 'is_consumo',
                'options'     => array(
                    'is_consumo' => 'Compra para CONSUMO',
                    'is_revenda' => 'Compra para REVENDA',
                )
            );

            $new_fields['billing_is_contribuinte'] = array(
                'type'        => 'radio',
                'label'       => __( 'Contribuinte?', 'storms' ),
                'class'       => array( 'form-row-wide', 'storms-is-contribuinte' ),
                'clear'       => true,
                'required'    => true,
                'default'	  => 'is_contribuinte',
                'options'     => array(
                    'is_contribuinte'  => 'Contribuinte',
                    'not_contribuinte' => 'Não Contribuinte',
                )
            );
        }
    }

    return $new_fields;
}
add_filter( 'wcbcf_billing_fields', 'storms_st_billing_field' );

/**
 * Return an array of billing fields in order
 */
function storms_wc_array_order_fields() {
    $order = array(
        "billing_first_name",
        "billing_last_name",
        "billing_persontype", 		// ecfb plugin
        "billing_cpf", 				// ecfb plugin
        "billing_rg", 				// ecfb plugin
        "billing_company", 			// ecfb plugin
        "billing_cnpj", 			// ecfb plugin
        "billing_ie", 				// ecfb plugin
        "billing_tipo_compra", 		// DexPeças plugin
        "billing_is_contribuinte",	// DexPeças plugin
        "billing_birthdate", 		// ecfb plugin
        "billing_sex", 				// ecfb plugin
        "billing_country",
        "billing_postcode",
        "billing_address_1",
        "billing_number", 			// ecfb plugin
        "billing_address_2",
        "billing_neighborhood", 	// ecfb plugin
        "billing_city",
        "billing_state",
        "billing_phone",
        "billing_cellphone", 		// ecfb plugin
        "billing_email",
    );

    return $order;
}

/**
 * Reorder billing fields in WooCommerce Checkout
 * @link : http://wordpress.stackexchange.com/a/127490/54025
 */
function storms_wc_checkout_order_fields( $fields ) {

    $order = storms_wc_array_order_fields();

    $ordered_fields = [];
    foreach( $order as $field ) {
        if( isset( $fields["billing"][$field] ) ) {
            $ordered_fields[$field] = $fields["billing"][$field];
        }
    }
    $fields["billing"] = $ordered_fields;

    return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'storms_wc_checkout_order_fields' );

/**
 * Reorder billing fields in WooCommerce Address To Edit
 */
function storms_wc_address_to_edit( $address, $load_address ) {

    $order = storms_wc_array_order_fields();

    $ordered_fields = [];
    if( $load_address == 'billing' ) {
        foreach ( $order as $field ) {
            if ( isset( $address[$field] ) ) {
                $ordered_fields[$field] = $address[$field];
            }
        }
        $address = $ordered_fields;
    }

    return $address;
}
add_filter( 'woocommerce_address_to_edit', 'storms_wc_address_to_edit', 10, 2 );

/**
 * Adicionamos os scripts para gerenciar do calculo da st
 */
function storms_calculost_enqueue_scripts() {
    if( is_checkout() || is_wc_endpoint_url( 'edit-address' ) ) {

        // Esta causando um bug absurdo no endpoint( 'edit-address' )
        wp_deregister_script( 'wc-address-i18n' );

        // Ajustamos os scripts dependentes de wc-address-i18n, para que nao precisem mais dele
        wp_deregister_script( 'wc-cart' );
        wp_enqueue_script( 'wc-cart', plugins_url( 'assets/js/frontend/cart.js', WC_PLUGIN_FILE ), array('jquery', 'wc-country-select'), WC_VERSION, true );
        wp_deregister_script( 'wc-checkout' );
        wp_enqueue_script( 'wc-checkout', plugins_url( 'assets/js/frontend/checkout.js', WC_PLUGIN_FILE ), array('jquery', 'woocommerce', 'wc-country-select'), WC_VERSION, true );

        // Adicionamos o script do calculo da ST
        wp_enqueue_script('storms_calculo_st', StormsFramework\Storms\Helper::get_asset_url('/js/storms-calculo-st.js'), array('jquery'), '1.0', true);
        wp_localize_script('storms_calculo_st', 'storms_calculo_st', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'ajax_nonce' => wp_create_nonce('storms_calculo_st_address_form'),
            'is_checkout_page' => is_checkout() ? 'yes' : 'no'
        ));
    }
}
add_action( 'wp_enqueue_scripts', 'storms_calculost_enqueue_scripts' );

function storms_calculo_st( WC_Cart $cart ) {
    /** @var wpdb $wpdb */
    global $wpdb;

    if ( is_admin() && !defined( 'DOING_AJAX' ) )
        return;

    if ( !isset( $_POST ) )
        return;

    $logger = wc_get_logger();

    $post_data = [];
    if ( isset($_POST['post_data']) ) {
        // 'post_data' esta disponivel quando estamos no formulario de checkout
        wp_parse_str($_POST['post_data'], $post_data);
    } else {
        // Usamos $_POST quando o checkout foi executado de fato, ou seja, quando a Order eh criada
        $post_data = $_POST;
    }

    $user_id = null;
    $person_type = $post_data['billing_persontype'] ?? 1;
    $billing_cnpj = $post_data['billing_cnpj'] ?? '';
    $billing_tipo_compra = $post_data['billing_tipo_compra'] ?? '';
    $billing_ie = $post_data['billing_ie'] ?? '';
    $is_contribuinte = ( isset( $post_data['billing_is_contribuinte'] ) ) ? ( $post_data['billing_is_contribuinte'] == 'is_contribuinte' ) : false;

    // Normalizamos a Inscrição Estadual
    $billing_ie = strtolower( trim( str_replace( '.', '', str_replace( '-', '', $billing_ie ) ) ) );

    $estado_ecomm = strtoupper( WC()->countries->get_base_state() );
    $estado_cliente = strtoupper( WC()->customer->get_shipping_state() );
    $valor_compras = WC()->cart->cart_contents_total;
    $valor_frete = WC()->cart->shipping_total;

    $table_name = $wpdb->prefix . 'calculost';
    $tabela_st = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $table_name . " WHERE UfOrigem = %s AND UfDestino = %s", array( $estado_ecomm, $estado_cliente ) ) );

    // Limpamos o imposto aplicado anteriormente, se houver
    WC()->session->set( 'fees', [] );

    // Se a compra é para Pessoa Juridica, com Inscrição Estadual, para consumo e para o mesmo estado do eComm, não cobramos o imposto
    if( ( $person_type == 2 ) &&
        ( $billing_cnpj != '' ) &&
        ( $billing_ie != 'isento' && is_numeric( $billing_ie ) ) &&
        ( $billing_tipo_compra == 'is_consumo' ) &&
        ( $estado_ecomm == $estado_cliente ) ) {
        return;
    }

    if( empty( $tabela_st ) ) {
        $logger->alert( 'Tabela ST não encontrada para Origem: ' . $estado_ecomm . ' - Destino: ' . $estado_cliente . '.', array( 'source' => 'storms-calculo-st' ) );
        return false;

        //wc_add_notice('Não foi possível encontrar uma base para o cálculo da ST. Por favor, entre em contato conosco.', 'error');
    } else if( $tabela_st[0]->AliqInterna == 0 ) {
        $logger->error( 'Aliquota Interna é igual a 0 (zero) para Origem: ' . $estado_ecomm . ' - Destino: ' . $estado_cliente . '.', array( 'source' => 'storms-calculo-st' ) );
        return false;
    }

    // O cliente deve pagar Imposto caso:
    // O usuario for Pessoa Juridica, possuir um CNPJ e É Contribuinte ( possui Inscrição Estadual )
    if( $person_type == 2 && $billing_cnpj != '' && $billing_ie != 'isento' && is_numeric( $billing_ie ) ) {

        $tipo_imposto = '';
        // Se o cliente marcou que a compra eh para CONSUMO, ele deve o DIFAL (Diferencial Aliquota)
        if( $billing_tipo_compra == 'is_consumo' ) {
            // Se for uma compra interestadual, executaremos uma formula de calculo
            if( $estado_ecomm != $estado_cliente ) {
                $tipo_imposto = 'DIFAL';
            }
        }
        // Se o cliente marcou que a compra eh para REVENDA, ele deve ST (Substituição Tributária)
        else if( $billing_tipo_compra == 'is_revenda' ) {
            $tipo_imposto = 'ST';
        }

        $st_calculado = 0;
        $imposto_label = '';
        $order_base_st = array(); // Array que vi guardar as Base ST calculadas para os produtos
        $peso_total = WC()->cart->get_cart_contents_weight();

        if( $tipo_imposto != '' ) {
            foreach (WC()->cart->get_cart() as $item) {

                //Get product by supplying variation id or product_id
                $product = wc_get_product( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );

                $valor_produto = $item['line_total'];
                $qtde_cart = $item['quantity'];
                $valor_ipi = $product->get_meta( '_valor_ipi' );
                $eh_importado = $product->get_meta( '_eh_importado' );
                $nao_cobrar_st = $product->get_meta( '_nao_cobrar_st' );
                $frete_rateado = ($valor_frete * ($product->get_weight() * $qtde_cart)) / $peso_total;

                $aliqInter = ($eh_importado == 'yes') ? $tabela_st[0]->AliqImportado : $tabela_st[0]->AliqInterestadual;
                $icms_proprio = ((($valor_produto + $frete_rateado) / (1 + ($valor_ipi / 100)))) * ($aliqInter / 100);

                // CM (Carga Média)
                if ( $tipo_imposto == 'ST' && $estado_cliente == 'MT' ) {
                    $imposto_label = 'ST (Substituição Tributária)';

                    // Regra especial para ST de compras para o MT
                    $aliqCargaMedia = 19; // @TODO Este valor hoje eh fixo no codigo, criar rotina para recebe-lo do ERP

                    $prod_frete = ($valor_produto + $frete_rateado);
                    $prod_frete_aliq = $prod_frete * ($aliqCargaMedia / 100);

                    $st_calculado += $prod_frete_aliq;

                    $base_st = ($icms_proprio + $prod_frete_aliq) / ($tabela_st[0]->AliqInterna / 100);

                    // Guardamos a Base ST no array, usando o ID do produto como chave
                    $order_base_st[$product->get_id()] = $base_st;

                } else {
                    // Não calculamos imposto, se o MVA for zero... exceto se for para MT, que calcula carga média como ST
                    if( $tabela_st[0]->Mva != 0 ||  $estado_cliente == 'MT' ) {

                        $mva = ($eh_importado == 'yes') ? $tabela_st[0]->MvaImportado : $tabela_st[0]->Mva;
                        $base_st = ($valor_produto + $frete_rateado) * (($tipo_imposto == 'ST') ? $mva : 1);

                        // Guardamos a Base ST no array, usando o ID do produto como chave
                        $order_base_st[$product->get_id()] = $base_st;

                        // DIFAL (Diferencial Aliquota)
                        if ($tipo_imposto == 'DIFAL') {
                            $imposto_label = 'DIFAL (Diferencial Aliquota)';

                            $aliquota_icms  = $tabela_st[0]->AliqInterna  - $tabela_st[0]->Fcp;
                            $icms_proprio_difal = ($valor_produto + $frete_rateado) * ($aliqInter / 100);

                            // @source Material Prosoftin de 18/01/2018
                            // dbDifalBase = Valor da Base de Cálculo que ser Calculado
                            // dbDifalValor = Valor do ICMS ST que será Calculado                                       $difal
                            // dbFcp = Aliquota do Fundo Combate a pobreza                                              $tabela_st[0]->Fcp
                            // dbBseICMSOrg = Base de cálculo do ICMS Próprio                                           $base_st
                            //  dbVlrICMSOrg = Valor do Icms calculado ( Base do ICMS Normal * Aliquota )               $icms_proprio_difal
                            // dbAlqICMS = Aliquota Interna do ICMS ( do estado Destino )                               $aliquota_icms
                            // dbAlqICMSOrg = Aliquota Interestadual do ICMS ( do Estado de Origem )                    $aliqInter
                            // dbVOper = Valor da Operação : criado na rotina, apoio para recalculo da BASE ST

                            switch( $tabela_st[0]->Formula )
                            {
                                // Default
                                case 0:
                                    //dbDifalBase = dbBseICMSOrg;
                                    //dbDifalValor = FRound(dbBseICMSOrg * ((dbAlqICMS - dbAlqICMSOrg) + dbFcp) / 100, 2);

                                    $difal = ($aliquota_icms - $aliqInter) + $tabela_st[0]->Fcp;
                                    $st_calculado += ($difal > 0) ? round($base_st * ($difal / 100), 2) : 0;

                                    break;

                                // PB / AL
                                case 1:
                                    //dbVOper = dbBseICMSOrg;
                                    //dbBseICMSOrg = FRound((dbVOper - dbVlrICMSOrg) / (1 - ((dbAlqICMS + dbFcp) / 100)), 2);
                                    //dbDifalBase = dbBseICMSOrg;
                                    //dbDifalValor = FRound((dbBseICMSOrg * ((dbAlqICMS) / 100)) - (dbVOper * dbAlqICMSOrg / 100), 2);
                                    //dbDifalValor = dbDifalValor + FRound(dbBseICMSOrg * ((dbFcp / 100)), 2);

                                    $v_oper = $base_st;
                                    $base_st = round(($v_oper - $icms_proprio_difal) / (1 - (($aliquota_icms + $tabela_st[0]->Fcp) / 100)), 2);

                                    // Guardamos a Base ST no array, usando o ID do produto como chave
                                    $order_base_st[$product->get_id()] = $base_st;

                                    $difal = round(($base_st * ($aliquota_icms / 100)) - (($v_oper * $aliqInter) / 100), 2);
                                    $difal += round($base_st * ($tabela_st[0]->Fcp / 100), 2);
                                    $st_calculado += ($difal > 0) ? $difal : 0;

                                    break;

                                // RN
                                case 2:
                                    //dbVOper = dbBseICMSOrg;
                                    //dbDifalBase = dbBseICMSOrg;
                                    //dbDifalValor = FRound((dbBseICMSOrg * ((dbAlqICMS) / 100)) - (dbVOper * dbAlqICMSOrg / 100), 2);
                                    //dbDifalValor = dbDifalValor + FRound(dbBseICMSOrg * ((dbFcp / 100)), 2);

                                    $difal = round(($base_st * ($aliquota_icms / 100)) - (($base_st * $aliqInter) / 100), 2);
                                    $difal += round($base_st * ($tabela_st[0]->Fcp / 100), 2);
                                    $st_calculado += ($difal > 0) ? $difal : 0;

                                    break;

                                // PE
                                case 3:
                                    //dbVOper = dbBseICMSOrg;
                                    //dbBseICMSOrg = FRound((dbVOper - dbVlrICMSOrg) / (1 - ((dbAlqICMS + dbFcp) / 100)), 2);
                                    //dbDifalBase = dbBseICMSOrg;
                                    //dbDifalValor = FRound(dbBseICMSOrg * ((dbAlqICMS - dbAlqICMSOrg)) / 100, 2);
                                    //dbDifalValor = dbDifalValor + FRound(dbBseICMSOrg * ((dbFcp / 100)), 2);

                                    $v_oper = $base_st;
                                    $base_st = round(($v_oper - $icms_proprio_difal) / (1 - (($aliquota_icms + $tabela_st[0]->Fcp) / 100)), 2);

                                    // Guardamos a Base ST no array, usando o ID do produto como chave
                                    $order_base_st[$product->get_id()] = $base_st;

                                    $difal = round($base_st * (($aliquota_icms - $aliqInter) / 100), 2);
                                    $difal += round($base_st * ($tabela_st[0]->Fcp / 100), 2);
                                    $st_calculado += ($difal > 0) ? $difal : 0;

                                    break;

                                // SE
                                case 4:
                                    //dbVOper = dbBseICMSOrg;
                                    //dbBseICMSOrg = FRound(dbBseICMSOrg / (1 - (((dbAlqICMS - dbAlqICMSOrg) + dbFcp) / 100)), 2);
                                    //dbDifalBase = dbBseICMSOrg;
                                    //dbDifalValor = FRound(dbBseICMSOrg * ((dbAlqICMS - dbAlqICMSOrg)) / 100, 2);
                                    //dbDifalValor = dbDifalValor + FRound(dbBseICMSOrg * ((dbFcp / 100)), 2);

                                    $v_oper = $base_st;
                                    $base_st = round($base_st / (1 - (($aliquota_icms - $aliqInter) + $tabela_st[0]->Fcp) / 100), 2);

                                    // Guardamos a Base ST no array, usando o ID do produto como chave
                                    $order_base_st[$product->get_id()] = $base_st;

                                    $difal = round($base_st * (($aliquota_icms - $aliqInter) / 100), 2);
                                    $difal += round($base_st * ($tabela_st[0]->Fcp / 100), 2);
                                    $st_calculado += ($difal > 0) ? $difal : 0;

                                    break;

                                // AL
                                case 5:
                                    //dbVOper = dbBseICMSOrg;
                                    //dbBseICMSOrg = FRound((dbVOper) / (1 - ((dbAlqICMS + dbFcp) / 100)), 2);
                                    //dbDifalBase = dbBseICMSOrg;
                                    //dbDifalValor = FRound(dbBseICMSOrg * ((dbAlqICMS - dbAlqICMSOrg)) / 100, 2);
                                    //dbDifalValor = dbDifalValor + FRound(dbBseICMSOrg * ((dbFcp / 100)), 2);

                                    $v_oper = $base_st;
                                    $base_st = round($base_st / (1 - (($aliquota_icms + $tabela_st[0]->Fcp) / 100)), 2);

                                    // Guardamos a Base ST no array, usando o ID do produto como chave
                                    $order_base_st[$product->get_id()] = $base_st;

                                    $difal = round($base_st * (($aliquota_icms - $aliqInter) / 100), 2);
                                    $difal += round($base_st * ($tabela_st[0]->Fcp / 100), 2);
                                    $st_calculado += ($difal > 0) ? $difal : 0;

                                    break;
                            }

                        } // ST (Substituição Tributária)
                        else if ($tipo_imposto == 'ST') {
                            $imposto_label = 'ST (Substituição Tributária)';

                            // Se o atributo nao foi setado, setamos como 'no', que eh o valor padrao
                            if (!isset($nao_cobrar_st) || empty($nao_cobrar_st)) {
                                $nao_cobrar_st = 'no';
                                update_post_meta($product->get_id(), '_nao_cobrar_st', 'no');
                            }

                            // Se o produto foi marcado para não cobrar ST, entao nao calculamos o imposto para ele
                            if (($tipo_imposto == 'ST') && ($nao_cobrar_st == 'yes')) {
                                continue;
                            }

                            $st_calculado += $base_st * ($tabela_st[0]->AliqInterna / 100) - $icms_proprio;
                        }

                    }
                }
            }
        }

        // Salvamos na Session do WooCommerce, as Base ST calculadas para os produtos
        if( ! empty( $order_base_st ) ) {
            WC()->session->set( 'order_base_st', $order_base_st );
        }

        // Não deixamos o valor da ST passar se ele for igual ou menor que zero, pq isso causa um erro no PagSeguro
        if( isset($st_calculado) && $st_calculado > 0 ) {
            WC()->cart->add_fee($imposto_label, $st_calculado);
        }
    }
}
add_action( 'woocommerce_cart_calculate_fees', 'storms_calculo_st' );
//add_action( 'woocommerce_before_calculate_totals', 'storms_calculo_st' );

/**
 * Grava, junto com o pedido, os dados usados para o calculo da ST, se houver
 *
 * @param int|bool Item ID or false
 * @param WC_Order_Item $item
 * @param int $order_id
 */
function storms_calculost_add_order_fee_meta( $item_id, $item, $order_id ) {
    /** @var wpdb $wpdb */
    global $wpdb;

    if( !is_user_logged_in() )
        return;

    // Queremos trabalhar apenas com as taxas do pedido
    if( ! is_a( $item, 'WC_Order_Item_Fee' ) )
        return;

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
add_action( 'woocommerce_new_order_item', 'storms_calculost_add_order_fee_meta', 10, 3 );

//<editor-fold desc="Campos adicionais de Produto - REST API">

/**
 * Register product's new attributes in WP REST API.
 */
function storms_calculost_register_product_new_attributes_fields() {

    if ( ! function_exists( 'register_rest_field' ) ) {
        return;
    }

    // Campo Valor IPI
    register_rest_field( 'product',
        'valor_ipi',
        array(
            'get_callback'    => 'get_valor_ipi_callback',
            'update_callback' => 'update_valor_ipi_callback',
            'schema'          => array(
                'description' => __( 'Valor do IPI do produto.', 'storms' ),
                'type'        => 'string',
                'context'     => array( 'view', 'edit' ),
            ),
        )
    );

    // Campo É Importado
    register_rest_field( 'product',
        'eh_importado',
        array(
            'get_callback'    => 'get_eh_importado_callback',
            'update_callback' => 'update_eh_importado_callback',
            'schema'          => array(
                'description' => __( 'Indica se o produto é importado.', 'storms' ),
                'type'        => 'bool',
                'context'     => array( 'view', 'edit' ),
            ),
        )
    );

    // Campo Nao Cobrar ST
    register_rest_field( 'product',
        'nao_cobrar_st',
        array(
            'get_callback'    => 'get_nao_cobrar_st_callback',
            'update_callback' => 'update_nao_cobrar_st_callback',
            'schema'          => array(
                'description' => __( 'Indica se para este produto, não devemos cobrar o imposto ST.', 'storms' ),
                'type'        => 'bool',
                'context'     => array( 'view', 'edit' ),
            ),
        )
    );
}
add_action( 'rest_api_init', 'storms_calculost_register_product_new_attributes_fields', 100 );

//</editor-fold>

//<editor-fold desc="Campos adicionais de Produto - Callbacks">

/**
 * Get IPI callback.
 *
 * @param array           $data    Details of current response.
 * @param string          $field   Name of field.
 * @param WP_REST_Request $request Current request.
 *
 * @return string
 */
function get_valor_ipi_callback( $data, $field, $request ) {
    $value = get_post_meta( $data['id'], '_valor_ipi', true );
    if( empty( $value ) ) {
        $value = '0';
        update_post_meta( $data['id'], '_valor_ipi', $value );
    }
    return get_post_meta( $data['id'], '_valor_ipi', true );
}

/**
 * Update IPI callback.
 *
 * @param string  $value  The value of the field.
 * @param WP_Post $object The object from the response.
 *
 * @return bool
 */
function update_valor_ipi_callback( $value, $object ) {
    $value = ( empty( $value ) ) ? '0' : str_replace( ',', '.', $value );
    return update_post_meta( $object->get_id(), '_valor_ipi', $value );
}

/**
 * Get Eh Importado callback.
 *
 * @param array           $data    Details of current response.
 * @param string          $field   Name of field.
 * @param WP_REST_Request $request Current request.
 *
 * @return string
 */
function get_eh_importado_callback( $data, $field, $request ) {
    return ( get_post_meta( $data['id'], '_eh_importado', true ) == 'yes' );
}

/**
 * Update Eh Importado callback.
 *
 * @param string  $value  The value of the field.
 * @param WP_Post $object The object from the response.
 *
 * @return bool
 */
function update_eh_importado_callback( $value, $object ) {
    $value = ( $value == 1 || $value == 'yes' ) ? 'yes' : 'no';
    return update_post_meta( $object->get_id(), '_eh_importado', $value );
}

/**
 * Get Nao Cobrar ST callback.
 *
 * @param array           $data    Details of current response.
 * @param string          $field   Name of field.
 * @param WP_REST_Request $request Current request.
 *
 * @return string
 */
function get_nao_cobrar_st_callback( $data, $field, $request ) {
    return ( get_post_meta( $data['id'], '_nao_cobrar_st', true ) == 'yes' );
}

/**
 * Update Nao Cobrar ST callback.
 *
 * @param string  $value  The value of the field.
 * @param WP_Post $object The object from the response.
 *
 * @return bool
 */
function update_nao_cobrar_st_callback( $value, $object ) {
    $value = ( $value == 1 || $value == 'yes' ) ? 'yes' : 'no';
    return update_post_meta( $object->get_id(), '_nao_cobrar_st', $value );
}

//</editor-fold>

//<editor-fold desc="Campos adicionais de Produto - Backend">

/**
 * Register additional product fields metaboxes.
 */
function storms_calculost_register_product_new_attributes_metabox() {
    global $woocommerce, $post;

    echo '<div class="options_group">';

    // Campo Valor IPI
    woocommerce_wp_text_input(
        array(
            'id'      => '_valor_ipi',
            'label'   => __( 'IPI do Produto', 'storms' ),
            'value'   => get_post_meta( $post->ID, '_valor_ipi', true ),
            'custom_attributes' => array(
                'autocomplete' => 'off'
            ),
            'description' => __( 'Valor do IPI para o produto (em %)', 'storms' ),
        )
    );

    // Campo É Importado
    woocommerce_wp_checkbox(
        array(
            'id'      => '_eh_importado',
            'label'   => __( 'Producto importado?', 'storms' ),
            'value'   => get_post_meta( $post->ID, '_eh_importado', true ),
            'custom_attributes' => array(
                'autocomplete' => 'off'
            ),
            'description' => __( 'Indica se o produto é importado', 'storms' ),
        )
    );

    // Campo Nao Cobrar ST
    woocommerce_wp_checkbox(
        array(
            'id'      => '_nao_cobrar_st',
            'label'   => __( 'Não cobrar ST?', 'storms' ),
            'value'   => get_post_meta( $post->ID, '_nao_cobrar_st', true ),
            'custom_attributes' => array(
                'autocomplete' => 'off'
            ),
            'description' => __( 'Indica se para este produto, não devemos cobrar o imposto ST', 'storms' ),
        )
    );

    echo '</div>';
}
add_action( 'woocommerce_product_options_general_product_data', 'storms_calculost_register_product_new_attributes_metabox' );

// WooCommerce Product Save Fields
function storms_calculost_add_product_new_attributes_fields_save( $post_id ) {
    $_valor_ipi = $_POST['_valor_ipi'] ?? null;
    if( !empty( $_valor_ipi ) ) {
        $_valor_ipi = trim( esc_sql( $_valor_ipi ) );
        $_valor_ipi = str_replace( ',', '.', $_valor_ipi );
        update_post_meta( $post_id, '_valor_ipi', esc_attr( $_valor_ipi ) );
    } else {
        // Salvamos o valor 0, se nenhum IPI foi informado - se foi informado o valor 0, cai aqui tbem
        update_post_meta( $post_id, '_valor_ipi', '0' );
    }

    $_eh_importado = $_POST['_eh_importado'] ?? null;
    if( !empty( $_eh_importado ) ) {
        $_eh_importado = trim( esc_sql( $_eh_importado ) );
        update_post_meta( $post_id, '_eh_importado', esc_attr( $_eh_importado ) );
    } else {
        update_post_meta( $post_id, '_eh_importado', 'no' );
    }

    $_nao_cobrar_st = $_POST['_nao_cobrar_st'] ?? null;
    if( !empty( $_nao_cobrar_st ) ) {
        $_nao_cobrar_st = trim( esc_sql( $_nao_cobrar_st ) );
        update_post_meta( $post_id, '_nao_cobrar_st', esc_attr( $_nao_cobrar_st ) );
    } else {
        update_post_meta( $post_id, '_nao_cobrar_st', 'no' );
    }

}
add_action( 'woocommerce_process_product_meta', 'storms_calculost_add_product_new_attributes_fields_save' );

//</editor-fold>
