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
 * API\WC_API_CalculoST class
 * Calculo ST endpoint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Storms_WC_API_Calculo_ST extends \WC_REST_Controller
{

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc-storms/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'calculost';

	/**
	 * Register the routes for customers.
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'set_tabela_calculo_st' ),
				'permission_callback' => array( $this, 'set_tabela_calculo_st_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	//<editor-fold desc="Carregar tabela de calculo da ST">

	/**
	 * Carrega tabela de calculo da ST
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function set_tabela_calculo_st( $request ) {
		global $wpdb;

		$wpdb->show_errors();

		$data = $request->get_json_params();

		$table_name = $wpdb->prefix . 'calculost';

		// Contabilizamos quandos dados estao na base
		$old_count = $wpdb->get_var('SELECT count(*) FROM ' . $table_name);

		// Removemos os dados da tabela de calculo st
		$delete = $wpdb->query('TRUNCATE TABLE ' . $table_name);

		// Inserimos os dados passados pelo ERP
		$mgs = '';
		foreach ($data as $item) {
			$success = $wpdb->insert(
				$table_name,
				array(
					'UfOrigem'          => $item['UfOrigem'],
					'UfDestino'         => $item['UfDestino'],
					'AliqInterestadual' => $item['AliqInterestadual'],
					'AliqInterna'       => $item['AliqInterna'],
					'Mva'               => $item['Mva'],
					'MvaImportado'      => $item['MvaImportado'],
					'AliqImportado'		=> $item['AliqImportado'],
					'Formula'			=> $item['Formula'],
					'Fcp'				=> $item['Fcp'],
				)
			);

			if(($wpdb->last_error !== '') || ! $success) {
				$mgs .= $wpdb->last_result . '\n';
			}

		}

		// Contabilizamos quandos dados estao na base
		$new_count = $wpdb->get_var('SELECT count(*) FROM ' . $table_name);

		$date = new \DateTime();
		$date->setTimezone(new \DateTimeZone('America/Sao_Paulo'));

		$calculost = array(
			'load_status' => empty($mgs), // Se nao houver mensagens, entao tudo ocorreu bem
			'old_count'   => $old_count, // Numero de itens na base de dados antes do carregamento
			'new_count'   => $new_count, // Numero de itens na base de dados do calculo de ST
			'mesages'     => $mgs,
			'load_date'   => $date->format('Y-m-d H:i:s'),
		);

		$calculost = $this->prepare_item_for_response( $calculost, $request );
		$response = rest_ensure_response( $calculost );

		return $response;
	}

	/**
	 * Check whether a given request has permission to use set_tabela_calculo_st
	 *
	 * @param  \WP_REST_Request $request Full details about the request.
	 * @return \WP_Error|boolean
	 */
	public function set_tabela_calculo_st_permissions_check( $request ) {
		if ( ! wc_rest_check_user_permissions( 'create' ) ) {
			return new \WP_Error( 'woocommerce_rest_cannot_create', __( 'Sorry, you are not allowed to create resources.', 'woocommerce' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	//</editor-fold>

	/**
	 * Prepare a shop status request output for response.
	 *
	 * @param string $ping Ping object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response $response Response data.
	 */
	public function prepare_item_for_response( $calculost, $request ) {

		$data = array(
			'load_status' => $calculost['load_status'],
			'old_count'   => $calculost['old_count'],
			'new_count'   => $calculost['new_count'],
			'mesages'     => $calculost['mesages'],
			'load_date'   => wc_rest_prepare_date_response( $calculost['load_date'] ),
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		/**
		 * Filter data returned from the REST API.
		 *
		 * @param WP_REST_Response $response  The response object.
		 * @param string           $ping      Ping object.
		 * @param WP_REST_Request  $request   Request object.
		 */
		return apply_filters( 'woocommerce_rest_prepare_calculost', $response, $calculost, $request );
	}

	/**
	 * Get the CalculoST's schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = [
			'load_status' => array(
				'description' => __( 'Status do carregamento realizado.', 'storms' ),
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'old_count' => array(
				'description' => __( 'Numero de itens na base de dados antes do carregamento.', 'storms' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'new_count' => array(
				'description' => __( 'Numero de itens carregados na base de dados do calculo de ST.', 'storms' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'mesages' => array(
				'description' => __( 'Mensagens do sistema, alertas e avisos de erro.', 'storms' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'load_date' => array(
				'description' => __( 'Data do carregamento da tabela de calculo de ST.', 'storms' ),
				'type'        => 'date-time',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		];

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get the query params for collections.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'context' => array(
				'default' => 'view'
			)
		);
	}
}
