/**
 * Banner de retos en modo aleatorio: el servidor imprime todas las variantes
 * disponibles y aquí se muestra una al azar en cada visita, aunque la página
 * venga de la caché. Sin JavaScript se ve la que eligió el servidor.
 */
( function () {
	document.querySelectorAll( '[data-pc-banner-aleatorio]' ).forEach( function ( grupo ) {
		var variantes = grupo.querySelectorAll( '.pc-banner' );
		if ( variantes.length < 2 ) {
			return;
		}

		var elegida = Math.floor( Math.random() * variantes.length );
		variantes.forEach( function ( variante, i ) {
			variante.hidden = i !== elegida;
		} );
		grupo.setAttribute( 'data-pc-banner-modo', variantes[ elegida ].getAttribute( 'data-pc-modo' ) || '' );
	} );
} )();
