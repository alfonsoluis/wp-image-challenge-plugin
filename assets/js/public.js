/* Concursos – abre una foto en grande con los datos de su autor y su reto, con flechas
   para pasar a la anterior o la siguiente y, en una votación, con sus puestos y la barra. */
( function () {
	'use strict';

	var textos = window.concursosModal || {};
	var modal, imagen, descripcion, avatar, titulo, autor, reto, pie, votar, barraModal, anterior, siguiente;
	var actual = null;

	function crear() {
		modal = document.createElement( 'dialog' );
		modal.className = 'concursos-modal';
		modal.innerHTML =
			'<button type="button" class="concursos-modal__cerrar">&times;</button>' +
			'<div class="concursos-modal__marco">' +
				'<button type="button" class="concursos-modal__flecha concursos-modal__flecha--anterior">&#8249;</button>' +
				'<div class="concursos-modal__foto"><img alt=""><p class="concursos-modal__descripcion concursos-sm" hidden></p><div class="concursos-modal__votar" hidden></div></div>' +
				'<button type="button" class="concursos-modal__flecha concursos-modal__flecha--siguiente">&#8250;</button>' +
			'</div>' +
			'<div class="concursos-modal__pie">' +
				'<div class="concursos-modal__autor"><img alt=""><div><strong></strong><a></a></div></div>' +
				'<div class="concursos-modal__reto"><small></small><a></a></div>' +
			'</div>' +
			'<div class="pc-barra-podio concursos-modal__barra" hidden></div>';

		imagen = modal.querySelector( '.concursos-modal__marco img' );
		descripcion = modal.querySelector( '.concursos-modal__descripcion' );
		avatar = modal.querySelector( '.concursos-modal__autor img' );
		titulo = modal.querySelector( '.concursos-modal__autor strong' );
		autor = modal.querySelector( '.concursos-modal__autor a' );
		reto = modal.querySelector( '.concursos-modal__reto a' );
		pie = modal.querySelector( '.concursos-modal__pie' );
		votar = modal.querySelector( '.concursos-modal__votar' );
		barraModal = modal.querySelector( '.concursos-modal__barra' );
		anterior = modal.querySelector( '.concursos-modal__flecha--anterior' );
		siguiente = modal.querySelector( '.concursos-modal__flecha--siguiente' );
		anterior.setAttribute( 'aria-label', textos.anterior || 'Previous photo' );
		siguiente.setAttribute( 'aria-label', textos.siguiente || 'Next photo' );
		anterior.addEventListener( 'click', function () {
			ir( -1 );
		} );
		siguiente.addEventListener( 'click', function () {
			ir( 1 );
		} );

		// Votar desde la foto ampliada: se pulsa el botón real de la rejilla, que ya guarda el puesto.
		votar.addEventListener( 'click', function ( event ) {
			var boton = event.target.closest( '.pc-puesto' );
			var real = boton && document.querySelector( '#concursos-podio .pc-puesto[value="' + boton.value + '"]' );
			if ( real ) {
				event.preventDefault();
				real.click();
			}
		} );
		barraModal.addEventListener( 'click', function ( event ) {
			var hueco = event.target.closest( '.pc-barra-podio__hueco' );
			var id = hueco && hueco.getAttribute( 'data-entrada' );
			var li = id ? document.querySelector( '#concursos-podio .pc-foto--podio[data-entrada="' + id + '"]' ) : null;
			var boton = li ? li.querySelector( '.concursos-foto' ) : null;
			if ( boton ) {
				mostrar( boton );
			}
		} );
		modal.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'ArrowLeft' ) {
				ir( -1 );
			} else if ( event.key === 'ArrowRight' ) {
				ir( 1 );
			}
		} );

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
			actual = null;
		} );
	}

	// Las fotos entre las que se puede pasar: las de la misma lista que la abierta.
	function fotos() {
		var lista = actual.closest( 'ul, ol' ) || actual.parentNode;
		return Array.prototype.slice.call( lista.querySelectorAll( '.concursos-foto' ) );
	}

	function ir( paso ) {
		if ( ! actual ) {
			return;
		}
		var todas = fotos();
		if ( todas.length > 1 ) {
			mostrar( todas[ ( todas.indexOf( actual ) + paso + todas.length ) % todas.length ] );
		}
	}

	// Puestos de esta foto y barra con la elección actual, copiados de la rejilla de votación.
	function sincronizarVoto() {
		if ( ! modal ) {
			return;
		}
		var li = actual ? actual.closest( '.pc-foto--podio' ) : null;
		var form = li ? document.getElementById( 'concursos-podio' ) : null;
		var grupo = li ? li.querySelector( '.pc-puestos' ) : null;
		var barra = form ? form.querySelector( '.pc-barra-podio' ) : null;

		if ( ! grupo || ! barra ) {
			votar.hidden = true;
			barraModal.hidden = true;
			pie.hidden = false;
			modal.classList.remove( 'is-votando', 'con-pie' );
			return;
		}

		if ( votar.getAttribute( 'data-entrada' ) !== li.getAttribute( 'data-entrada' ) ) {
			votar.innerHTML = grupo.outerHTML;
			votar.setAttribute( 'data-entrada', li.getAttribute( 'data-entrada' ) );
		}
		votar.querySelectorAll( '.pc-puesto' ).forEach( function ( boton ) {
			var real = grupo.querySelector( '.pc-puesto[value="' + boton.value + '"]' );
			boton.setAttribute( 'aria-pressed', real ? real.getAttribute( 'aria-pressed' ) : 'false' );
		} );

		barraModal.innerHTML = barra.innerHTML;
		var confirmar = barraModal.querySelector( '.pc-barra-podio__confirmar' );
		if ( confirmar ) {
			confirmar.setAttribute( 'form', 'concursos-podio' );
		}

		votar.hidden = false;
		barraModal.hidden = false;
		// Votando ya se sabe en qué reto se está: el pie solo queda si hay autor que mostrar.
		pie.hidden = modal.querySelector( '.concursos-modal__autor' ).hidden;
		modal.classList.add( 'is-votando' );
		modal.classList.toggle( 'con-pie', ! pie.hidden );
	}

	document.addEventListener( 'concursos:podio', sincronizarVoto );

	function abrir( boton ) {
		if ( ! modal ) {
			crear();
		}
		mostrar( boton );

		// Dentro del contenedor .pc, para heredar sus estilos y colores.
		( boton.closest( '.pc' ) || document.body ).appendChild( modal );
		if ( ! modal.open ) {
			modal.showModal();
		}
	}

	function mostrar( boton ) {
		actual = boton;
		var datos = boton.dataset;
		var hayVarias = fotos().length > 1;
		anterior.hidden = ! hayVarias;
		siguiente.hidden = ! hayVarias;

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

		sincronizarVoto();
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
			hueco.setAttribute( 'data-entrada', foto ? id : '' );
			if ( foto ) {
				hueco.setAttribute( 'tabindex', '0' );
				hueco.setAttribute( 'role', 'button' );
			} else {
				hueco.removeAttribute( 'tabindex' );
				hueco.removeAttribute( 'role' );
			}
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

		document.dispatchEvent( new CustomEvent( 'concursos:podio' ) );
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

	// Pulsar una miniatura de la barra lleva hasta esa foto en la rejilla.
	function irAFoto( hueco ) {
		var id = hueco.getAttribute( 'data-entrada' );
		var foto = id ? form.querySelector( '.pc-foto--podio[data-entrada="' + id + '"]' ) : null;
		if ( ! foto ) {
			return;
		}
		var quieta = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		foto.scrollIntoView( { block: 'center', behavior: quieta ? 'auto' : 'smooth' } );
		foto.classList.add( 'is-resaltada' );
		window.setTimeout( function () {
			foto.classList.remove( 'is-resaltada' );
		}, 1600 );
	}

	form.addEventListener( 'click', function ( event ) {
		var hueco = event.target.closest( '.pc-barra-podio__hueco' );
		if ( hueco ) {
			irAFoto( hueco );
		}
	} );
	form.addEventListener( 'keydown', function ( event ) {
		var hueco = event.target.closest && event.target.closest( '.pc-barra-podio__hueco' );
		if ( hueco && ( event.key === 'Enter' || event.key === ' ' ) ) {
			event.preventDefault();
			irAFoto( hueco );
		}
	} );

	/*
	 * La barra es «sticky», pero algunos temas ponen overflow:hidden en un contenedor de la
	 * página y entonces no se queda pegada abajo. En ese caso se flota con position:fixed
	 * mientras su sitio natural quede por debajo de la ventana.
	 */
	var barra = form.querySelector( '.pc-barra-podio' );
	if ( ! barra ) {
		return;
	}
	var hueco = document.createElement( 'div' );
	hueco.className = 'pc-barra-podio__reserva';
	hueco.hidden = true;
	barra.parentNode.insertBefore( hueco, barra );
	var pendiente = false;

	function colocar() {
		pendiente = false;
		var flota = barra.classList.contains( 'is-flotante' );
		var natural = ( flota ? hueco : barra ).getBoundingClientRect();
		var margen = parseFloat( window.getComputedStyle( barra ).bottom ) || 0;
		var marco = form.getBoundingClientRect();
		var debeFlotar = natural.bottom > window.innerHeight - margen && marco.top < window.innerHeight - barra.offsetHeight;

		if ( debeFlotar ) {
			if ( ! flota ) {
				hueco.style.height = barra.offsetHeight + 'px';
				hueco.hidden = false;
				barra.classList.add( 'is-flotante' );
			}
			barra.style.left = marco.left + 'px';
			barra.style.width = marco.width + 'px';
		} else if ( flota ) {
			barra.classList.remove( 'is-flotante' );
			barra.style.left = '';
			barra.style.width = '';
			hueco.hidden = true;
		}
	}

	function pedir() {
		if ( ! pendiente ) {
			pendiente = true;
			window.requestAnimationFrame( colocar );
		}
	}

	window.addEventListener( 'scroll', pedir, { passive: true } );
	window.addEventListener( 'resize', pedir );
	colocar();
} )();
