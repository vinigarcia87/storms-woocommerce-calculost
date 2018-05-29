
jQuery( function( $ ) {

	/**
	 * Adiciona funcionalidade para setar Inscricao Estadual como ISENTO
	 */
	$(document).ready(function() {

		var checkbox_isento  = '<div class="checkbox isento-checkbox">';
			checkbox_isento += '	<label>';
			checkbox_isento += '		<input type="checkbox" id="mark_isento">&nbsp;Isento';
			checkbox_isento += '	</label>';
			checkbox_isento += '</div>';

		//checkbox_isento = '<span class="pull-right"><input type="checkbox" id="mark_isento">&nbsp;Isento</span>';

		$(checkbox_isento).appendTo('label[for="billing_ie"]');

		var $billing_ie = $('#billing_ie');
		var $mark_isento = $('#mark_isento');

		if( $billing_ie.length > 0 &&
			$billing_ie.val().toUpperCase() == 'ISENTO' ) {
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

			if( person_type == 2 &&
				ie != '' && $.isNumeric( ie ) &&
				$( '#billing_tipo_compra_is_consumo' ).is( ':checked' ) ) {
				$('.storms-is-contribuinte').show();
			} else {
				$( '#billing_is_contribuinte_is_contribuinte' ).attr( 'checked', true );
				$( '#billing_is_contribuinte_not_contribuinte' ).attr( 'checked', true );
				$('.storms-is-contribuinte').hide();
			}
		});

	});

	var all_st_fields  = '#billing_persontype, #billing_cpf, #billing_cnpj, ';
		all_st_fields += '#billing_ie, #billing_tipo_compra_is_consumo, #billing_tipo_compra_is_revenda, ';
		all_st_fields += '#select2-chosen-1, #billing_is_contribuinte_is_contribuinte, #billing_is_contribuinte_not_contribuinte';
	$(document).on('change', all_st_fields, function() {
		var $checkout_form = jQuery('form.checkout');
		$( document.body ).trigger( 'update_checkout' );
	});

	$( '#billing_persontype, #billing_ie' ).on( 'change', function () {
		var current = $('#billing_persontype').val();

		$( '#billing_cpf_field' ).hide();
		$( '#billing_rg_field' ).hide();
		$( '#billing_company_field' ).hide();
		$( '#billing_cnpj_field' ).hide();
		$( '#billing_ie_field' ).hide();
		$( '.storms-tipo-compra' ).hide();
		$( '.storms-is-contribuinte' ).hide();
		$('#alerta-st').remove();

		if ( '1' === current ) {
			$( '#billing_tipo_compra_is_consumo' ).attr('checked', false);
			$( '#billing_tipo_compra_is_revenda' ).attr('checked', false);

			$( '#billing_cpf_field' ).show();
			$( '#billing_rg_field' ).show();
		}

		if ( '2' === current ) {
			$( '#billing_company_field' ).show();
			$( '#billing_cnpj_field' ).show();
			$( '#billing_ie_field' ).show();

			var ie = $( '#billing_ie' ).val().replace(/\.|-/g, '').toLowerCase();
			if( ie != '' && $.isNumeric( ie ) ) {
				$('.storms-tipo-compra').show();

				if( $( '#billing_tipo_compra_is_consumo' ).is( ':checked' ) ) {
					$('.storms-is-contribuinte').show();
				}

				var msg = '';
				if( storms_calculo_st.is_checkout_page == 'yes' ) {
					msg = 'Sua compra está sujeita a cobrança de imposto ICMS-ST ou ICMS-DIFAL.<br>Salve suas informações para ver o valor real do imposto.';
				} else {
					msg = 'Sua compras estarão sujeitas a cobrança de imposto ICMS-ST ou ICMS-DIFAL.';
				}

				var html_alert  = '<div id="alerta-st" class="row">';
					html_alert += '	<div class="col-sm-12">';
					html_alert += '		<div class="alert alert-warning">';
					html_alert += '			<span class="fa fa-info-circle" aria-hidden="true"></span>&nbsp;<strong>Atenção!</strong>&nbsp;&nbsp;' + msg;
					html_alert += '		</div>';
					html_alert += '	</div>';
					html_alert += '</div>';


				$('.storms-is-contribuinte').after(html_alert);
			} else {
				$('.storms-tipo-compra').hide();
				$( '.storms-is-contribuinte' ).hide();

				$('#alerta-st').remove();
			}
		}
	}).change();
});
