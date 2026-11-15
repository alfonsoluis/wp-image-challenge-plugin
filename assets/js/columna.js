/* Concursos – columna lateral de un reto que se queda a la vista al hacer scroll.
 * Si es más alta que la ventana, se fija por abajo en lugar de por arriba, para
 * que su parte de abajo (el banner) también se pueda ver. */
( function () {
	'use strict';

	var columna = document.querySelector( '.pc-columna' );
	if ( ! columna ) {
		return;
	}

	function ajustar() {
		var barra = parseInt( getComputedStyle( document.documentElement ).getPropertyValue( '--wp-admin--admin-bar--height' ), 10 ) || 0;
		var arriba = barra + 24;
		var abajo = window.innerHeight - columna.offsetHeight - 24;
		columna.style.setProperty( '--pc-columna-top', Math.min( arriba, abajo ) + 'px' );
	}

	ajustar();
	window.addEventListener( 'resize', ajustar );
	if ( window.ResizeObserver ) {
		new ResizeObserver( ajustar ).observe( columna );
	}
}() );
