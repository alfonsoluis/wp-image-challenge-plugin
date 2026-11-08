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

	update();
} )();
