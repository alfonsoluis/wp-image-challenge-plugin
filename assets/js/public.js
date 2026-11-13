/* Concursos – abre una foto en grande con los datos de su autor y su reto. */
( function () {
	'use strict';

	var textos = window.concursosModal || {};
	var modal, imagen, descripcion, avatar, titulo, autor, reto;

	function crear() {
		modal = document.createElement( 'dialog' );
		modal.className = 'concursos-modal';
		modal.innerHTML =
			'<button type="button" class="concursos-modal__cerrar">&times;</button>' +
			'<div class="concursos-modal__marco"><div class="concursos-modal__foto"><img alt=""><p class="concursos-modal__descripcion concursos-sm" hidden></p></div></div>' +
			'<div class="concursos-modal__pie">' +
				'<div class="concursos-modal__autor"><img alt=""><div><strong></strong><a></a></div></div>' +
				'<div class="concursos-modal__reto"><small></small><a></a></div>' +
			'</div>';

		imagen = modal.querySelector( '.concursos-modal__marco img' );
		descripcion = modal.querySelector( '.concursos-modal__descripcion' );
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
		descripcion.textContent = datos.descripcion || '';
		descripcion.hidden = ! datos.descripcion;
		titulo.textContent = datos.titulo;
		autor.textContent = datos.autor;
		if ( datos.autorUrl ) {
			autor.href = datos.autorUrl;
		} else {
			autor.removeAttribute( 'href' );
		}
		// En una votación anónima no hay título ni autor que mostrar.
		modal.querySelector( '.concursos-modal__autor' ).hidden = ! datos.titulo && ! datos.autor;
		reto.textContent = datos.reto;
		reto.href = datos.retoUrl;
		avatar.hidden = ! datos.avatar;
		if ( datos.avatar ) {
			avatar.src = datos.avatar;
		}

		// Dentro del contenedor .pc, para heredar sus estilos y colores.
		( boton.closest( '.pc' ) || document.body ).appendChild( modal );
		modal.showModal();
	}

	// Pide confirmación antes de enviar los formularios que la necesitan.
	document.addEventListener( 'submit', function ( event ) {
		var aviso = event.target.dataset && event.target.dataset.confirmar;
		if ( aviso && ! window.confirm( aviso ) ) {
			event.preventDefault();
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		var boton = event.target.closest( '.concursos-foto' );
		if ( boton ) {
			abrir( boton );
		}
	} );
} )();

/* Concursos – votar por puestos: se eligen en la página y se guardan con «Confirmar votos». */
( function () {
	'use strict';

	var form = document.getElementById( 'concursos-podio' );
	if ( ! form ) {
		return;
	}

	var campos = Array.prototype.slice.call( form.querySelectorAll( 'input[name^="puestos["]' ) );
	var confirmar = form.querySelector( '.pc-barra-podio__confirmar' );
	var estado = form.querySelector( '.pc-barra-podio__estado' );

	function campo( puesto ) {
		return form.querySelector( 'input[name="puestos[' + puesto + ']"]' );
	}

	function cambiado() {
		return campos.some( function ( c ) {
			return c.value !== c.getAttribute( 'data-guardado' );
		} );
	}

	function pintar() {
		var puestoDe = {};
		campos.forEach( function ( c, i ) {
			if ( c.value ) {
				puestoDe[ c.value ] = i + 1;
			}
		} );

		form.querySelectorAll( '.pc-foto--podio' ).forEach( function ( foto ) {
			var id = foto.getAttribute( 'data-entrada' );
			var puesto = puestoDe[ id ] || 0;
			foto.classList.remove( 'is-puesto-1', 'is-puesto-2', 'is-puesto-3' );
			if ( puesto ) {
				foto.classList.add( 'is-puesto-' + puesto );
			}
			var boton = puesto ? foto.querySelector( '.pc-puesto--' + puesto ) : null;
			foto.querySelector( '.pc-foto__sello' ).textContent = boton ? boton.textContent : '';
			foto.querySelectorAll( '.pc-puesto' ).forEach( function ( b ) {
				b.setAttribute( 'aria-pressed', b === boton ? 'true' : 'false' );
			} );
		} );

		form.querySelectorAll( '.pc-barra-podio__hueco' ).forEach( function ( hueco ) {
			var id = campo( hueco.getAttribute( 'data-puesto' ) ).value;
			var foto = id ? form.querySelector( '.pc-foto--podio[data-entrada="' + id + '"]' ) : null;
			var img = hueco.querySelector( '.pc-barra-podio__img' );
			var miniatura = foto ? foto.getAttribute( 'data-miniatura' ) : '';
			hueco.classList.toggle( 'is-vacio', ! foto );
			img.innerHTML = '';
			if ( miniatura ) {
				var el = document.createElement( 'img' );
				el.src = miniatura;
				el.alt = '';
				img.appendChild( el );
			}
			hueco.querySelector( '.pc-barra-podio__titulo' ).textContent = foto ? foto.getAttribute( 'data-nombre' ) : form.getAttribute( 'data-sin-elegir' );
		} );

		var hayCambios = cambiado();
		var hayVotos = campos.some( function ( c ) {
			return c.getAttribute( 'data-guardado' );
		} );
		confirmar.disabled = ! hayCambios;
		estado.textContent = hayCambios ? form.getAttribute( 'data-cambios' ) : ( hayVotos ? form.getAttribute( 'data-guardado' ) : '' );
	}

	form.addEventListener( 'click', function ( event ) {
		var boton = event.target.closest( '.pc-puesto' );
		if ( ! boton ) {
			return;
		}
		event.preventDefault();

		// El puesto pasa a esta foto, que pierde el que tuviera; si ya lo tenía, se quita.
		var partes = boton.value.split( ':' );
		var actual = campo( partes[ 1 ] );
		if ( actual.value === partes[ 0 ] ) {
			actual.value = '';
		} else {
			campos.forEach( function ( c ) {
				if ( c.value === partes[ 0 ] ) {
					c.value = '';
				}
			} );
			actual.value = partes[ 0 ];
		}
		pintar();
	} );

	pintar();
} )();
