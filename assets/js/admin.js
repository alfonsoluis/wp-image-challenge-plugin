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
	}

	document.addEventListener( 'change', function ( event ) {
		if ( event.target.name === 'concursos[lugares]' ) {
			update();
		}
	} );

	update();
} )();
