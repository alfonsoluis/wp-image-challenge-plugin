/* Concursos – muestra u oculta campos según las opciones elegidas. */
( function () {
	'use strict';

	function toggle( selector, visible ) {
		document.querySelectorAll( selector ).forEach( function ( el ) {
			el.style.display = visible ? '' : 'none';
		} );
	}

	function checked( name ) {
		var input = document.querySelector( 'input[name="concursos[' + name + ']"]:checked' );
		return input ? input.value : '';
	}

	function update() {
		toggle( '.concursos-if-tres-lugares', checked( 'lugares' ) === '3' );
		document.querySelectorAll( '.concursos-privado' ).forEach( function ( box ) {
			toggle( '.concursos-if-privado-' + box.dataset.grupo, box.checked );
		} );
	}

	document.addEventListener( 'change', function ( event ) {
		if ( event.target.name === 'concursos[lugares]' || event.target.classList.contains( 'concursos-privado' ) ) {
			update();
		}
	} );


	var votantes = document.getElementById( 'concursos-votantes-form' );
	if ( votantes ) {
		votantes.addEventListener( 'submit', function ( event ) {
			var boton = event.submitter;
			var campo = boton && boton.id === 'doaction2' ? 'action2' : 'action';
			var accion = votantes.querySelector( 'select[name="' + campo + '"]' );
			if ( boton && /^doaction2?$/.test( boton.id ) && accion && accion.value === 'concursos_eliminar' && ! window.confirm( votantes.dataset.confirmar ) ) {
				event.preventDefault();
			}
		} );
	}

	// Stats: el periodo se aplica al elegirlo, y al cambiar de reto se mantiene el scroll.
	var periodo = document.querySelector( 'select[data-autoenviar]' );
	if ( periodo ) {
		periodo.addEventListener( 'change', function () {
			periodo.form.submit();
		} );
	}

	var CLAVE = 'concursos-stats-scroll';
	document.querySelectorAll( '.concursos-stats-reto a' ).forEach( function ( enlace ) {
		enlace.addEventListener( 'click', function () {
			try {
				window.sessionStorage.setItem( CLAVE, String( window.scrollY ) );
			} catch ( e ) {}
		} );
	} );
	if ( document.querySelector( '.concursos-stats-pagina' ) ) {
		try {
			var y = window.sessionStorage.getItem( CLAVE );
			if ( y !== null ) {
				window.sessionStorage.removeItem( CLAVE );
				window.scrollTo( 0, parseInt( y, 10 ) || 0 );
			}
		} catch ( e ) {}
	}

	update();
} )();

/* Concursos – botón para copiar un campo al portapapeles. */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( e ) {
		var boton = e.target.closest( '[data-concursos-copiar]' );
		if ( ! boton ) {
			return;
		}
		var campo = document.getElementById( boton.dataset.concursosCopiar );
		if ( ! campo ) {
			return;
		}
		campo.focus();
		campo.select();
		var hecho = function () {
			boton.textContent = boton.dataset.copiado;
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( campo.value ).then( hecho, function () {
				if ( document.execCommand( 'copy' ) ) {
					hecho();
				}
			} );
		} else if ( document.execCommand( 'copy' ) ) {
			hecho();
		}
	} );
}() );
