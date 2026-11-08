/* Concursos – abre una foto en grande con los datos de su autor y su reto. */
( function () {
	'use strict';

	var textos = window.concursosModal || {};
	var modal, imagen, avatar, titulo, autor, reto;

	function crear() {
		modal = document.createElement( 'dialog' );
		modal.className = 'concursos-modal';
		modal.innerHTML =
			'<button type="button" class="concursos-modal__cerrar">&times;</button>' +
			'<div class="concursos-modal__marco"><img alt=""></div>' +
			'<div class="concursos-modal__pie">' +
				'<div class="concursos-modal__autor"><img alt=""><div><strong></strong><a></a></div></div>' +
				'<div class="concursos-modal__reto"><small></small><a></a></div>' +
			'</div>';

		imagen = modal.querySelector( '.concursos-modal__marco img' );
		avatar = modal.querySelector( '.concursos-modal__autor img' );
		titulo = modal.querySelector( '.concursos-modal__autor strong' );
		autor = modal.querySelector( '.concursos-modal__autor a' );
		reto = modal.querySelector( '.concursos-modal__reto a' );

		var cerrar = modal.querySelector( '.concursos-modal__cerrar' );
		cerrar.setAttribute( 'aria-label', textos.cerrar || 'Close' );
		cerrar.addEventListener( 'click', function () {
			modal.close();
		} );
		modal.querySelector( '.concursos-modal__reto small' ).textContent = textos.reto || 'Challenge';

		// Un clic en el propio diálogo solo puede venir de fuera de su contenido.
		modal.addEventListener( 'click', function ( event ) {
			if ( event.target === modal ) {
				modal.close();
			}
		} );
		modal.addEventListener( 'close', function () {
			imagen.removeAttribute( 'src' );
		} );
	}

	function abrir( boton ) {
		if ( ! modal ) {
			crear();
		}

		var datos = boton.dataset;

		imagen.src = datos.grande;
		imagen.alt = datos.titulo;
		titulo.textContent = datos.titulo;
		autor.textContent = datos.autor;
		autor.href = datos.autorUrl;
		reto.textContent = datos.reto;
		reto.href = datos.retoUrl;
		avatar.hidden = ! datos.avatar;
		if ( datos.avatar ) {
			avatar.src = datos.avatar;
		}

		// Dentro del reto, para heredar sus colores.
		( boton.closest( '.concursos-disenado' ) || document.body ).appendChild( modal );
		modal.showModal();
	}

	document.addEventListener( 'click', function ( event ) {
		var boton = event.target.closest( '.concursos-foto' );
		if ( boton ) {
			abrir( boton );
		}
	} );
} )();
