
jQuery( function( $ ) {

	/**
	 * Adiciona funcionalidade para setar Inscricao Estadual como ISENTO
	 */
	$(document).ready(function() {

		var checkbox_isento  = '<div class="checkbox isento-checkbox">';
			checkbox_isento += '	<label style="margin-bottom: 0;">';
			checkbox_isento += '		<input type="checkbox" id="mark_isento">&nbsp;Isento';
			checkbox_isento += '	</label>';
			checkbox_isento += '</div>';

		$(checkbox_isento).appendTo('label[for="billing_ie"]');

		var $billing_ie = $('#billing_ie');
		var $mark_isento = $('#mark_isento');

		if( $billing_ie.length > 0 &&
			'ISENTO' === $billing_ie.val().toUpperCase() ) {
			$mark_isento.attr('checked', true);
			$billing_ie.attr('readonly','readonly');
			$billing_ie.trigger('change');
		}

		$mark_isento.on('click', function() {
			if( $(this).is(':checked') ) {
				$billing_ie.val('ISENTO');
				$billing_ie.attr('readonly','readonly');
			} else {
				$billing_ie.val('');
				$billing_ie.removeAttr('readonly');
			}
			$billing_ie.trigger('change');
		});

		$( '#billing_tipo_compra_is_consumo, #billing_tipo_compra_is_revenda' ).on('change', function() {
			var person_type = $('#billing_persontype').val();
			var ie = $( '#billing_ie' ).val().replace(/\.|-/g, '').toLowerCase();

			if( person_type == 2 && ie != '' && $.isNumeric( ie ) && $( '#billing_tipo_compra_is_consumo' ).is( ':checked' ) ) {
				$('.storms-is-contribuinte').show();
			} else {
				$('.storms-is-contribuinte').hide();
			}
		});

	});

	/**
	 * Adiciona a funcionalidade de exibir  as opçoes de tipo de compra
	 * Estas opçoes aparecem quando o cliente eh uma pessoa juridica e possui CNPJ e Inscriçao Estadual (nao eh isento)
	 */

	var all_st_fields  = '#billing_persontype, #billing_cpf, #billing_cnpj, ';
		all_st_fields += '#billing_ie, #billing_tipo_compra_is_consumo, #billing_tipo_compra_is_revenda, ';
		all_st_fields += '#select2-chosen-1, #billing_is_contribuinte_is_contribuinte, #billing_is_contribuinte_not_contribuinte';

	$(document).on('change', all_st_fields, function() {
		var $checkout_form = jQuery('form.checkout');
		$( document.body ).trigger( 'update_checkout' );
	});

	var st_msg_box = '#st-msg-box';

	var pessoa_fisica_fields = '#billing_cpf_field, #billing_rg_field';
	var pessoa_juridica_fields = '#billing_company_field, #billing_cnpj_field, #billing_ie_field';
	var tipo_compra_fields = '.storms-tipo-compra';
	var is_contribuinte_fields = '.storms-is-contribuinte';

	var hide_all_fields  = [ pessoa_fisica_fields, pessoa_juridica_fields, tipo_compra_fields, is_contribuinte_fields ].join( ', ' );
	$( hide_all_fields ).hide();
	$(st_msg_box).remove();

	$( '#billing_persontype, #billing_ie' ).on( 'change', function () {
		var current = $('#billing_persontype').val();

		$( hide_all_fields ).hide();
		$(st_msg_box).remove();

		// Pessoa Física
		if ( '1' === current ) {
			$( pessoa_fisica_fields ).show();
		}

		// Pessoa Jurídica
		if ( '2' === current ) {
			$( pessoa_juridica_fields ).show();

			var ie = $( '#billing_ie' ).val().replace(/\.|-/g, '').toLowerCase();
			if( ie != '' && $.isNumeric( ie ) ) {
				$( tipo_compra_fields ).show();

				// Se for uma compra para consumo, precisamos perguntar se o cliente eh contribuinte
				if( $( '#billing_tipo_compra_is_consumo' ).is( ':checked' ) ) {
					$( is_contribuinte_fields ).show();
				}

				// Mensagem que sera exibida, para alertar o cliente sobre o imposto
				var msg = 'Sua compras estarão sujeitas a cobrança de imposto ICMS-ST ou ICMS-DIFAL.';
				if( 'yes' === storms_calculo_st.is_checkout_page ) {
					msg = 'Sua compra está sujeita a cobrança de imposto ICMS-ST ou ICMS-DIFAL.<br>Salve suas informações para ver o valor real do imposto.';
				}

				var html_alert  = '<div id="' + st_msg_box.replace( '#', '' ) + '" class="woocommerce-info">';
					html_alert += '	<span class="fa fa-info-circle" aria-hidden="true"></span>&nbsp;<strong>Atenção!</strong><br>' + msg;
					html_alert += '</div>';

				// Colocamos a mensagem apos o campo
				$( tipo_compra_fields ).before( html_alert );
			} else {
				$( tipo_compra_fields + ', ' + is_contribuinte_fields ).hide();

				$( st_msg_box ).remove();
			}
		}
	}).change();
});
