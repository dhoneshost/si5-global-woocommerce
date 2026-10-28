/* SI5 Global para WooCommerce - botoes "Testar conexao" e "Buscar dados agora" */
jQuery( function ( $ ) {
	$( '#si5g-testar' ).on( 'click', function () {
		var $saida = $( '#si5g-teste' ).text( SI5G.txt.buscando );
		$.post( SI5G.ajax, { action: 'si5g_testar', nonce: SI5G.nonce, token: $( '#si5g-token' ).val() } )
			.done( function ( r ) { $saida.text( r.data ).css( 'color', r.success ? '#008a20' : '#d63638' ); } )
			.fail( function () { $saida.text( 'Falha na requisição.' ).css( 'color', '#d63638' ); } );
	} );

	$( '#si5g-buscar' ).on( 'click', function () {
		if ( ! window.confirm( SI5G.txt.salvar ) ) {
			return;
		}
		var $b = $( this ).prop( 'disabled', true ), $saida = $( '#si5g-resultado' ).text( SI5G.txt.buscando );
		$.post( SI5G.ajax, { action: 'si5g_buscar', nonce: SI5G.nonce, produto: $b.data( 'produto' ) } )
			.done( function ( r ) {
				$saida.text( r.data );
				if ( r.success ) {
					$saida.append( ' ' + SI5G.txt.recarregar );
					setTimeout( function () { window.location.reload(); }, 1500 );
				} else {
					$b.prop( 'disabled', false );
				}
			} )
			.fail( function () { $saida.text( 'Falha na requisição.' ); $b.prop( 'disabled', false ); } );
	} );
} );
