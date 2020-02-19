<?php
/**
 * Storms Framework (http://storms.com.br/)
 *
 * @author    Vinicius Garcia | vinicius.garcia@storms.com.br
 * @copyright (c) Copyright 2012-2016, Storms Websolutions
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
 * WooCommerce Calculo da ST
 * --------------------------
 * Add custom fee to cart automatically
 * @see http://stackoverflow.com/a/40850578/1003020
 * @see http://www.remicorson.com/add-custom-fee-to-woocommerce-cart-dynamically/
 * @see https://docs.woocommerce.com/document/add-a-surcharge-to-cart-and-checkout-uses-fees-api/
 */
function storms_wc_calculost_fomula( WC_Cart $cart ) {
	/** @var wpdb $wpdb */
	global $wpdb;

	if ( is_admin() && !defined( 'DOING_AJAX' ) ) {
		return;
	}

	if ( !isset( $_POST ) ) {
		return;
	}

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
add_action( 'woocommerce_cart_calculate_fees', 'storms_wc_calculost_fomula' );
// add_action( 'woocommerce_before_calculate_totals', 'storms_wc_calculost_fomula' );
