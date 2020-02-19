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

//<editor-fold desc="Campos adicionais de Produto - REST API">

/**
 * Register product's new attributes in WP REST API.
 */
function storms_wc_calculost_register_product_new_attributes_fields() {

	if ( ! function_exists( 'register_rest_field' ) ) {
		return;
	}

	// Campo Valor IPI
	register_rest_field( 'product',
		'valor_ipi',
		array(
			'get_callback'    => 'storms_wc_calculost_get_valor_ipi_callback',
			'update_callback' => 'storms_wc_calculost_update_valor_ipi_callback',
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
			'get_callback'    => 'storms_wc_calculost_get_eh_importado_callback',
			'update_callback' => 'storms_wc_calculost_update_eh_importado_callback',
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
			'get_callback'    => 'storms_wc_calculost_get_nao_cobrar_st_callback',
			'update_callback' => 'storms_wc_calculost_update_nao_cobrar_st_callback',
			'schema'          => array(
				'description' => __( 'Indica se para este produto, não devemos cobrar o imposto ST.', 'storms' ),
				'type'        => 'bool',
				'context'     => array( 'view', 'edit' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'storms_wc_calculost_register_product_new_attributes_fields', 100 );

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
function storms_wc_calculost_get_valor_ipi_callback( $data, $field, $request ) {
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
function storms_wc_calculost_update_valor_ipi_callback( $value, $object ) {
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
function storms_wc_calculost_get_eh_importado_callback( $data, $field, $request ) {
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
function storms_wc_calculost_update_eh_importado_callback( $value, $object ) {
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
function storms_wc_calculost_get_nao_cobrar_st_callback( $data, $field, $request ) {
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
function storms_wc_calculost_update_nao_cobrar_st_callback( $value, $object ) {
	$value = ( $value == 1 || $value == 'yes' ) ? 'yes' : 'no';
	return update_post_meta( $object->get_id(), '_nao_cobrar_st', $value );
}

//</editor-fold>

//<editor-fold desc="Campos adicionais de Produto - Backend">

/**
 * Register additional product fields metaboxes.
 */
function storms_wc_calculost_register_product_new_attributes_metabox() {
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
add_action( 'woocommerce_product_options_general_product_data', 'storms_wc_calculost_register_product_new_attributes_metabox' );

// WooCommerce Product Save Fields
function storms_wc_calculost_add_product_new_attributes_fields_save( $post_id ) {
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
add_action( 'woocommerce_process_product_meta', 'storms_wc_calculost_add_product_new_attributes_fields_save' );

//</editor-fold>
