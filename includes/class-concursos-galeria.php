<?php
/**
 * Bloque público de un reto: estado, votación, fotos y resultados.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Añade al contenido de la página de un reto lo que corresponde a su fase.
 */
class Concursos_Galeria {

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'route' ) );
	}

	/**
	 * Atiende la página de un reto.
	 */
	public static function route() {
		if ( is_singular( Concursos_Post_Types::RETO ) && ! is_feed() ) {
			$post = get_queried_object();
			Concursos_Plantilla::pagina( get_the_title( $post ), self::pagina( $post ), 'concursos-publico--reto', Concursos_Reto::get_settings( $post->ID ) );
		}
	}

	/**
	 * Línea con el autor, la fecha de finalización y las etiquetas de un reto.
	 *
	 * @param WP_Post $post Reto.
	 * @param array   $s    Configuración.
	 * @return string Texto sin escapar.
	 */
	private static function ficha( $post, array $s ) {
		$datos = array();

		$autor = get_the_author_meta( 'display_name', $post->post_author );
		if ( $autor ) {
			/* translators: %s: name of the user who created the challenge. */
			$datos[] = sprintf( __( 'By %s', 'foncholuis-art-challenge-manager' ), $autor );
		}
		$datos[] = wp_date( get_option( 'date_format' ), (int) $s['cierre'] );

		$etiquetas = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'names' ) );
		if ( $etiquetas && ! is_wp_error( $etiquetas ) ) {
			/* translators: %s: list of tags. */
			$datos[] = sprintf( __( 'Tags: %s', 'foncholuis-art-challenge-manager' ), implode( ', ', $etiquetas ) );
		}

		return implode( ' · ', $datos );
	}

	/**
	 * Enlaces al reto anterior y al siguiente, por fecha de inicio. Solo cuenta
	 * los retos publicados, como la portada.
	 *
	 * @param WP_Post $post Reto.
	 * @return string
	 */
	private static function navegacion( $post ) {
		$retos = array_values(
			array_filter(
				Concursos_Reto::all( array( 'publish' ) ),
				function ( $reto ) {
					return 'sin_fechas' !== $reto['status'];
				}
			)
		);
		$ids   = array_map(
			function ( $reto ) {
				return $reto['post']->ID;
			},
			$retos
		);
		$pos   = array_search( $post->ID, $ids, true );
		if ( false === $pos ) {
			return '';
		}

		// La lista va del más reciente al más antiguo.
		$anterior  = isset( $retos[ $pos + 1 ] ) ? $retos[ $pos + 1 ]['post'] : null;
		$siguiente = $pos > 0 ? $retos[ $pos - 1 ]['post'] : null;
		if ( ! $anterior && ! $siguiente ) {
			return '';
		}

		$enlace = function ( $reto, $clase, $rel, $etiqueta ) {
			return sprintf(
				'<a class="pc-navegacion__enlace pc-navegacion__enlace--%1$s" rel="%2$s" href="%3$s"><span class="pc-etiqueta">%4$s</span><strong>%5$s</strong></a>',
				$clase,
				$rel,
				esc_url( get_permalink( $reto ) ),
				esc_html( $etiqueta ),
				esc_html( get_the_title( $reto ) )
			);
		};

		return '<nav class="pc-navegacion concursos-navegacion" aria-label="' . esc_attr__( 'Challenge navigation', 'foncholuis-art-challenge-manager' ) . '">'
			. ( $anterior ? $enlace( $anterior, 'anterior', 'prev', __( 'Previous challenge', 'foncholuis-art-challenge-manager' ) ) : '' )
			. ( $siguiente ? $enlace( $siguiente, 'siguiente', 'next', __( 'Next challenge', 'foncholuis-art-challenge-manager' ) ) : '' )
			. '</nav>';
	}

	/**
	 * Siguiente reto en el que se puede participar, distinto del actual.
	 *
	 * @param int $reto_id Reto actual.
	 * @return array|null
	 */
	private static function siguiente_abierto( $reto_id ) {
		$candidatos = array();
		foreach ( Concursos_Reto::all( array( 'publish' ) ) as $reto ) {
			if ( $reto['post']->ID !== $reto_id && in_array( $reto['status'], array( 'envios', 'proximo' ), true ) ) {
				$candidatos[] = $reto;
			}
		}

		return $candidatos ? end( $candidatos ) : null;
	}

	/**
	 * Columna lateral del reto: calendario, cuenta atrás y acción de la fase.
	 *
	 * @param WP_Post $post   Reto.
	 * @param array   $s      Configuración.
	 * @param string  $status Fase.
	 * @return string
	 */
	private static function lateral( $post, array $s, $status ) {
		$html = Concursos_Vista::calendario( $status, $s ) . Concursos_Vista::cuenta( $status, $s );

		$inscripcion = in_array( $status, array( 'proximo', 'envios' ), true ) ? Concursos_Inscripcion::url( $post->ID ) : '';
		if ( $inscripcion ) {
			$html .= '<p class="pc-acciones"><a class="pc-boton pc-boton--principal pc-boton--ancho concursos-boton" href="' . esc_url( $inscripcion ) . '">' . Concursos_Vista::icono( 'subir' ) . esc_html__( 'Join this challenge', 'foncholuis-art-challenge-manager' ) . '</a></p>';
		}
		if ( 'envios' === $status ) {
			$html .= '<p class="pc-nota"><strong>' . esc_html__( 'Already registered?', 'foncholuis-art-challenge-manager' ) . '</strong> ' . esc_html__( 'Use the link we emailed you to upload or delete your photos.', 'foncholuis-art-challenge-manager' ) . '</p>';
		}
		if ( 'votacion' === $status ) {
			$html .= Concursos_Votantes::panel_html( $post->ID, $s );
		}
		if ( 'finalizado' === $status ) {
			$siguiente = self::siguiente_abierto( $post->ID );
			if ( $siguiente ) {
				$imagen = Concursos_Portada::imagen( $siguiente, 'thumbnail' );
				$html  .= sprintf(
					'<a class="pc-siguiente" href="%1$s">%2$s<span class="pc-siguiente__texto"><span class="pc-texto-suave">%3$s</span><strong>%4$s</strong></span>%5$s</a>',
					esc_url( get_permalink( $siguiente['post'] ) ),
					$imagen ? '<img src="' . esc_url( $imagen['url'] ) . '" alt="" width="48" height="48">' : '',
					esc_html( 'envios' === $siguiente['status'] ? __( 'Next open challenge', 'foncholuis-art-challenge-manager' ) : __( 'Next challenge', 'foncholuis-art-challenge-manager' ) ),
					esc_html( get_the_title( $siguiente['post'] ) . ' · ' . Concursos_Galeria::frase_estado( $siguiente['status'], $siguiente['settings'] ) ),
					Concursos_Vista::icono( 'flecha' )
				);
			}
		}

		return '<aside class="pc-lateral" aria-label="' . esc_attr__( 'Challenge schedule', 'foncholuis-art-challenge-manager' ) . '">' . $html . '</aside>';
	}

	/**
	 * Página completa de un reto, con su diseño.
	 *
	 * @param WP_Post $post Reto.
	 * @return string
	 */
	private static function pagina( $post ) {
		$s       = Concursos_Reto::get_settings( $post->ID );
		$status  = Concursos_Reto::status( $s );
		$imagen  = 'finalizado' === $status ? null : Concursos_Portada::imagen(
			array(
				'post'     => $post,
				'settings' => $s,
			),
			'full'
		);
		$reglas  = Concursos_Reto::rules_list( $s['reglas'] );
		$premios = array();
		for ( $i = 0; $i < (int) $s['lugares']; $i++ ) {
			if ( '' !== $s['premios'][ $i ] ) {
				$premios[ $i + 1 ] = $s['premios'][ $i ];
			}
		}
		$fotos = Concursos_Entrada::count_for_reto( $post->ID );
		$datos = array(
			Concursos_Vista::intervalo( $s['inicio'], $s['cierre'] ) . ' ' . wp_date( 'Y', (int) $s['cierre'] ),
			/* translators: %d: number of photos. */
			sprintf( _n( '%d photo submitted', '%d photos submitted', $fotos, 'foncholuis-art-challenge-manager' ), $fotos ),
		);
		if ( 'finalizado' === $status ) {
			$datos = array( self::ficha( $post, $s ), $datos[1] );
		}
		$pagina  = (int) get_option( Concursos_Portada::OPCION_PAGINA );
		$portada = $pagina && 'publish' === get_post_status( $pagina ) ? get_permalink( $pagina ) : '';
		$portada = $portada ? $portada : get_post_type_archive_link( Concursos_Post_Types::RETO );

		Concursos_Diseno::aplicar( $s );

		ob_start();
		?>
		<div class="pc-contenedor concursos-reto">
			<nav class="pc-ruta" aria-label="<?php esc_attr_e( 'Breadcrumb', 'foncholuis-art-challenge-manager' ); ?>">
				<a href="<?php echo esc_url( $portada ); ?>"><?php esc_html_e( 'Challenges', 'foncholuis-art-challenge-manager' ); ?></a>
				<span aria-hidden="true">/</span>
				<span aria-current="page"><?php echo esc_html( get_the_title( $post ) ); ?></span>
			</nav>
			<header class="pc-cabecera-reto">
				<div class="pc-cabecera-reto__titulo">
					<?php echo Concursos_Vista::chip( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
					<h1 class="pc-titulo-reto"><?php echo esc_html( get_the_title( $post ) ); ?></h1>
				</div>
				<p class="pc-datos"><?php echo esc_html( implode( ' · ', $datos ) ); ?></p>
			</header>
			<?php if ( $imagen ) : ?>
				<figure class="pc-portada"><img src="<?php echo esc_url( $imagen['url'] ); ?>" width="<?php echo esc_attr( $imagen['ancho'] ); ?>" height="<?php echo esc_attr( $imagen['alto'] ); ?>" alt=""></figure>
			<?php endif; ?>
			<div class="pc-cuerpo">
				<div class="pc-cuerpo__principal">
					<div class="pc-descripcion">
						<?php echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound,WordPress.Security.EscapeOutput.OutputNotEscaped -- filtro estándar de WordPress. ?>
					</div>
					<?php echo 'votacion' === $status ? self::como_se_vota( $s ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
					<?php if ( $reglas ) : ?>
						<section class="pc-bloque" id="reglas" aria-labelledby="pc-reglas">
							<h2 class="pc-bloque__titulo" id="pc-reglas"><?php esc_html_e( 'Rules', 'foncholuis-art-challenge-manager' ); ?></h2>
							<ul class="pc-reglas">
								<?php foreach ( $reglas as $regla ) : ?>
									<li><?php echo esc_html( $regla ); ?></li>
								<?php endforeach; ?>
							</ul>
						</section>
					<?php endif; ?>
					<?php if ( $premios ) : ?>
						<section class="pc-bloque"<?php echo $reglas ? '' : ' id="reglas"'; ?> aria-labelledby="pc-premios">
							<h2 class="pc-bloque__titulo" id="pc-premios"><?php esc_html_e( 'Prizes', 'foncholuis-art-challenge-manager' ); ?></h2>
							<ul class="pc-premios">
								<?php foreach ( $premios as $lugar => $premio ) : ?>
									<li class="pc-premio"><span class="pc-premio__lugar"><?php echo esc_html( Concursos_Votos::ordinal( $lugar ) ); ?></span><span><?php echo esc_html( $premio ); ?></span></li>
								<?php endforeach; ?>
							</ul>
						</section>
					<?php endif; ?>
				</div>
				<?php echo self::lateral( $post, $s, $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
			</div>
			<?php echo self::render( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
			<?php echo self::navegacion( $post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Bloque público completo de un reto.
	 *
	 * @param int $reto_id Reto.
	 * @return string
	 */
	public static function render( $reto_id ) {
		wp_enqueue_style( 'concursos-public', CONCURSOS_URL . 'assets/css/public.css', array(), CONCURSOS_VERSION );

		$s        = Concursos_Reto::get_settings( $reto_id );
		$status   = Concursos_Reto::status( $s );
		$entradas = Concursos_Entrada::find( array( 'reto' => $reto_id ) );

		ob_start();
		echo '<section class="pc-seccion concursos-galeria" id="concursos-votacion">';
		echo Concursos_Votantes::aviso_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.

		if ( 'finalizado' === $status && $entradas ) {
			self::resultados( $reto_id, $s, $entradas );
		} else {
			self::fotos( $reto_id, $s, $status, $entradas );
		}

		echo '</section>';

		return ob_get_clean();
	}

	/**
	 * Frase con la fase del reto y su próxima fecha.
	 *
	 * @param string $status Estado.
	 * @param array  $s      Configuración.
	 * @return string
	 */
	public static function frase_estado( $status, array $s ) {
		$frases = array(
			/* translators: %s: date. */
			'proximo'    => array( __( 'Starts on %s.', 'foncholuis-art-challenge-manager' ), 'inicio' ),
			/* translators: %s: date. */
			'envios'     => array( __( 'Accepting photos until %s.', 'foncholuis-art-challenge-manager' ), 'fin_envios' ),
			/* translators: %s: date. */
			'votacion'   => array( __( 'Voting until %s.', 'foncholuis-art-challenge-manager' ), 'fin_votacion' ),
			/* translators: %s: date. */
			'evaluacion' => array( __( 'Voting has ended. Results on %s.', 'foncholuis-art-challenge-manager' ), 'cierre' ),
			/* translators: %s: date. */
			'finalizado' => array( __( 'Finished on %s.', 'foncholuis-art-challenge-manager' ), 'cierre' ),
		);

		return isset( $frases[ $status ] ) ? sprintf( $frases[ $status ][0], Concursos_Reto::format_dia( $s[ $frases[ $status ][1] ] ) ) : '';
	}

	/**
	 * Recuadro «Cómo se vota»: lo que vale cada puesto.
	 *
	 * @param array $s Configuración.
	 * @return string
	 */
	private static function como_se_vota( array $s ) {
		if ( ! Concursos_Votos::por_puestos( $s ) ) {
			return '';
		}
		$textos = array(
			1 => __( 'Your favourite photo', 'foncholuis-art-challenge-manager' ),
			2 => __( 'The next one', 'foncholuis-art-challenge-manager' ),
			3 => __( 'Completes your podium', 'foncholuis-art-challenge-manager' ),
		);
		$html   = '';
		for ( $p = 1; $p <= (int) $s['votos_por_votante']; $p++ ) {
			$puntos = Concursos_Votos::puntos_de( $p );
			$html  .= sprintf(
				'<li class="pc-como-votar__puesto"><span class="pc-medalla-grande pc-medalla-grande--%1$d" aria-hidden="true">%2$s</span><span><strong>%3$s</strong><br><span class="pc-texto-suave">%4$s</span></span></li>',
				$p,
				esc_html( Concursos_Votos::ordinal( $p ) ),
				/* translators: %d: number of points. */
				esc_html( sprintf( _n( '%d point', '%d points', $puntos, 'foncholuis-art-challenge-manager' ), $puntos ) ),
				esc_html( $textos[ $p ] )
			);
		}

		return sprintf(
			'<section class="pc-bloque pc-como-votar" aria-labelledby="pc-como-votar"><h2 class="pc-bloque__titulo" id="pc-como-votar">%1$s</h2><ol class="pc-como-votar__puestos">%2$s</ol><p class="pc-nota">%3$s</p></section>',
			esc_html__( 'How to vote', 'foncholuis-art-challenge-manager' ),
			$html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
			esc_html__( 'Each place goes to one photo only: if you give it to another, the first one loses it. The photo with the most points wins.', 'foncholuis-art-challenge-manager' )
		);
	}

	/**
	 * Mosaico para votar por puestos: botones sobre cada foto y una barra con el
	 * podio del votante, que se guarda entero con «Confirmar votos». Sin
	 * JavaScript, cada botón envía el formulario y guarda ese cambio.
	 *
	 * @param int       $reto_id  Reto.
	 * @param array     $s        Configuración.
	 * @param WP_Post[] $entradas Fotos.
	 * @param object    $votante  Votante.
	 * @param int[]     $mios     entrada => puesto ya guardado.
	 * @param bool      $anonima  Si se ocultan título y autor.
	 */
	private static function podio( $reto_id, array $s, array $entradas, $votante, array $mios, $anonima ) {
		$total   = (int) $s['votos_por_votante'];
		$guardado = array();
		foreach ( $mios as $entrada => $puesto ) {
			if ( $puesto >= 1 && $puesto <= $total ) {
				$guardado[ $puesto ] = (int) $entrada;
			}
		}
		$datos = array();
		foreach ( $entradas as $i => $entrada ) {
			$numero                  = str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT );
			$datos[ $entrada->ID ] = array(
				/* translators: %s: photo number, such as 01. */
				'nombre'     => $anonima ? sprintf( __( 'No. %s', 'foncholuis-art-challenge-manager' ), $numero ) : get_the_title( $entrada ),
				'miniatura'  => (string) wp_get_attachment_image_url( get_post_thumbnail_id( $entrada ), 'thumbnail' ),
			);
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="concursos-voto-form concursos-podio" id="concursos-podio"
			data-sin-elegir="<?php esc_attr_e( 'Not chosen', 'foncholuis-art-challenge-manager' ); ?>"
			data-guardado="<?php esc_attr_e( 'Your votes are saved. You can change them until voting ends.', 'foncholuis-art-challenge-manager' ); ?>"
			data-cambios="<?php esc_attr_e( 'Unsaved changes', 'foncholuis-art-challenge-manager' ); ?>"><input type="hidden" name="action" value="<?php echo esc_attr( Concursos_Votos::ACTION ); ?>"><input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( Concursos_Votos::ACTION ) ); ?>">
			<input type="hidden" name="reto" value="<?php echo esc_attr( $reto_id ); ?>">
			<input type="hidden" name="op" value="podio">
			<?php for ( $p = 1; $p <= $total; $p++ ) : ?>
				<input type="hidden" name="puestos[<?php echo esc_attr( $p ); ?>]" value="<?php echo esc_attr( isset( $guardado[ $p ] ) ? $guardado[ $p ] : '' ); ?>" data-guardado="<?php echo esc_attr( isset( $guardado[ $p ] ) ? $guardado[ $p ] : '' ); ?>">
			<?php endfor; ?>

			<ul class="pc-mosaico concursos-galeria__rejilla">
				<?php
				foreach ( $entradas as $i => $entrada ) :
					$id     = $entrada->ID;
					$puesto = (int) array_search( $id, $guardado, true );
					$propia = Concursos_Votos::es_propia( $id, $votante );
					$autor  = Concursos_Entrada::owner( $id );
					?>
					<li class="pc-foto pc-foto--podio<?php echo $puesto ? ' is-puesto-' . esc_attr( $puesto ) : ''; ?>" data-entrada="<?php echo esc_attr( $id ); ?>" data-nombre="<?php echo esc_attr( $datos[ $id ]['nombre'] ); ?>" data-miniatura="<?php echo esc_url( $datos[ $id ]['miniatura'] ); ?>">
						<figure>
							<div class="pc-foto__media">
								<?php echo Concursos_Entrada::miniatura( $entrada, 'large', $anonima ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
								<span class="pc-foto__sello" aria-hidden="true"><?php echo $puesto ? esc_html( Concursos_Votos::ordinal( $puesto ) ) : ''; ?></span>
								<?php if ( $propia ) : ?>
									<span class="pc-puestos pc-puestos--propia concursos-propia"><?php esc_html_e( 'Your photo', 'foncholuis-art-challenge-manager' ); ?></span>
								<?php else : ?>
									<?php /* translators: %s: photo title or number. */ ?>
									<div class="pc-puestos" role="group" aria-label="<?php echo esc_attr( sprintf( __( 'Give a place to %s', 'foncholuis-art-challenge-manager' ), $datos[ $id ]['nombre'] ) ); ?>">
										<?php for ( $p = 1; $p <= $total; $p++ ) : ?>
											<button type="submit" name="elegir" value="<?php echo esc_attr( $id . ':' . $p ); ?>" class="pc-puesto pc-puesto--<?php echo esc_attr( $p ); ?>" aria-pressed="<?php echo $puesto === $p ? 'true' : 'false'; ?>"><?php echo esc_html( Concursos_Votos::ordinal( $p ) ); ?></button>
										<?php endfor; ?>
									</div>
								<?php endif; ?>
							</div>
							<figcaption class="pc-foto__pie">
								<span class="pc-foto__titulo"><?php echo esc_html( $datos[ $id ]['nombre'] ); ?></span>
								<?php if ( ! $anonima && $autor ) : ?>
									<span class="pc-foto__autor"><?php echo Concursos_Perfil::enlace( $autor ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?></span>
								<?php endif; ?>
							</figcaption>
						</figure>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="pc-barra-podio">
				<ol class="pc-barra-podio__huecos">
					<?php
					for ( $p = 1; $p <= $total; $p++ ) :
						$elegida = isset( $guardado[ $p ], $datos[ $guardado[ $p ] ] ) ? $datos[ $guardado[ $p ] ] : null;
						$puntos  = Concursos_Votos::puntos_de( $p );
						?>
						<li class="pc-barra-podio__hueco<?php echo $elegida ? '' : ' is-vacio'; ?>" data-puesto="<?php echo esc_attr( $p ); ?>">
							<span class="pc-barra-podio__img"><?php echo $elegida && $elegida['miniatura'] ? '<img src="' . esc_url( $elegida['miniatura'] ) . '" alt="">' : ''; ?></span>
							<span class="pc-barra-podio__texto">
								<?php /* translators: 1: place such as 1st, 2: number of points. */ ?>
								<span class="pc-barra-podio__puesto"><span class="pc-punto-medalla pc-punto-medalla--<?php echo esc_attr( $p ); ?>" aria-hidden="true"></span><?php echo esc_html( sprintf( _n( '%1$s · %2$d pt', '%1$s · %2$d pts', $puntos, 'foncholuis-art-challenge-manager' ), Concursos_Votos::ordinal( $p ), $puntos ) ); ?></span>
								<span class="pc-barra-podio__titulo"><?php echo esc_html( $elegida ? $elegida['nombre'] : __( 'Not chosen', 'foncholuis-art-challenge-manager' ) ); ?></span>
							</span>
						</li>
					<?php endfor; ?>
				</ol>
				<p class="pc-barra-podio__estado" role="status"><?php echo $guardado ? esc_html__( 'Your votes are saved. You can change them until voting ends.', 'foncholuis-art-challenge-manager' ) : ''; ?></p>
				<button type="submit" class="pc-barra-podio__confirmar"><?php esc_html_e( 'Confirm votes', 'foncholuis-art-challenge-manager' ); ?></button>
			</div>
		</form>
		<?php
	}

		/**
	 * Tarjeta de una foto en el mosaico.
	 *
	 * @param WP_Post $entrada Foto.
	 * @param string  $extra   HTML ya escapado bajo los datos.
	 * @param bool    $anonima Si se ocultan su título y su autor.
	 * @param int     $numero  Número de la foto en una votación anónima.
	 * @return string
	 */
	private static function tarjeta( $entrada, $extra = '', $anonima = false, $numero = 0 ) {
		if ( $anonima ) {
			return sprintf(
				'<li class="pc-foto"><figure>%1$s<figcaption class="pc-foto__pie"><span class="pc-foto__titulo">%2$s</span></figcaption>%3$s</figure></li>',
				Concursos_Entrada::miniatura( $entrada, 'large', true ),
				/* translators: %s: photo number, such as 01. */
				esc_html( sprintf( __( 'No. %s', 'foncholuis-art-challenge-manager' ), str_pad( (string) $numero, 2, '0', STR_PAD_LEFT ) ) ),
				$extra ? '<div class="pc-foto__voto">' . $extra . '</div>' : ''
			);
		}

		$autor = Concursos_Entrada::owner( $entrada->ID );

		return sprintf(
			'<li class="pc-foto"><figure>%1$s<figcaption class="pc-foto__pie"><span class="pc-foto__titulo">%2$s</span><span class="pc-foto__autor">%3$s</span></figcaption>%4$s</figure></li>',
			Concursos_Entrada::miniatura( $entrada, 'large' ),
			esc_html( get_the_title( $entrada ) ),
			$autor ? Concursos_Perfil::enlace( $autor ) : '',
			$extra ? '<div class="pc-foto__voto">' . $extra . '</div>' : ''
		);
	}

	/**
	 * Fotos del reto, o el motivo por el que aún no se ven.
	 *
	 * @param int       $reto_id  Reto.
	 * @param array     $s        Configuración.
	 * @param string    $status   Estado.
	 * @param WP_Post[] $entradas Fotos.
	 */
	private static function fotos( $reto_id, array $s, $status, array $entradas ) {
		$nota = '';
		if ( 'envios' === $status && Concursos_Reto::photos_visible( $s ) ) {
			/* translators: %s: date. */
			$nota = sprintf( __( 'Shown as they are submitted · voting starts on %s', 'foncholuis-art-challenge-manager' ), Concursos_Reto::format_dia( $s['fin_envios'] ) );
		} elseif ( $entradas ) {
			/* translators: %d: number of photos. */
			$nota = sprintf( _n( '%d photo', '%d photos', count( $entradas ), 'foncholuis-art-challenge-manager' ), count( $entradas ) );
		}
		echo Concursos_Vista::cabecera_seccion( 'pc-fotos', 'votacion' === $status ? __( 'Photos in the vote', 'foncholuis-art-challenge-manager' ) : __( 'Submitted entries', 'foncholuis-art-challenge-manager' ), esc_html( $nota ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.

		if ( ! Concursos_Reto::photos_visible( $s ) ) {
			if ( 'envios' === $status ) {
				printf(
					'<p class="pc-vacio">%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: number of photos, 2: submission end date. */
							_n( '%1$d photo has been submitted so far. All of them will be shown when submissions end, on %2$s.', '%1$d photos have been submitted so far. All of them will be shown when submissions end, on %2$s.', count( $entradas ), 'foncholuis-art-challenge-manager' ),
							count( $entradas ),
							Concursos_Reto::format_dia( $s['fin_envios'] )
						)
					)
				);
			} else {
				echo '<p class="pc-vacio">' . esc_html__( 'Photos will be shown when the challenge starts.', 'foncholuis-art-challenge-manager' ) . '</p>';
			}

			return;
		}

		if ( ! $entradas ) {
			echo '<p class="pc-vacio">' . esc_html__( 'There are no photos yet.', 'foncholuis-art-challenge-manager' ) . '</p>';

			return;
		}

		$votante = 'votacion' === $status ? Concursos_Votantes::actual() : null;
		if ( $votante && ! Concursos_Reto::permite_email( $s, 'votantes', $votante->email ) ) {
			$votante = null;
		}
		$mios    = $votante ? Concursos_Votos::puestos_de_votante( (int) $votante->id, $reto_id ) : array();
		$anonima = Concursos_Reto::oculta_autoria( $s, $status );

		if ( $anonima ) {
			echo '<p class="pc-aviso-suave">' . esc_html__( 'Anonymous voting: the title and author of each photo will be shown when voting ends.', 'foncholuis-art-challenge-manager' ) . '</p>';
		}

		if ( $votante && Concursos_Votos::por_puestos( $s ) ) {
			self::podio( $reto_id, $s, $entradas, $votante, $mios, $anonima );

			return;
		}

		echo '<ul class="pc-mosaico concursos-galeria__rejilla">';
		foreach ( $entradas as $i => $entrada ) {
			$boton = '';
			if ( $votante ) {
				// En su propia foto, quien vota no ve botones: solo que es suya.
				$boton = Concursos_Votos::es_propia( $entrada->ID, $votante )
					? '<span class="pc-etiqueta concursos-propia">' . esc_html__( 'Your photo', 'foncholuis-art-challenge-manager' ) . '</span>'
					: Concursos_Votos::botones( $reto_id, $s, $entrada->ID, $mios );
			}
			echo self::tarjeta( $entrada, $boton, $anonima, $i + 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
		}
		echo '</ul>';
	}

	/**
	 * Resultados finales: podio (con empates en igualdad) y clasificación del reto.
	 *
	 * @param int       $reto_id  Reto.
	 * @param array     $s        Configuración.
	 * @param WP_Post[] $entradas Fotos.
	 */
	private static function resultados( $reto_id, array $s, array $entradas ) {
		$por_id = array();
		foreach ( $entradas as $entrada ) {
			$por_id[ $entrada->ID ] = $entrada;
		}
		$votos   = Concursos_Votos::de_fotos( $reto_id, $entradas );
		$ranking = Concursos_Votos::rank( $votos, (int) $s['lugares'] );

		echo Concursos_Vista::cabecera_seccion( 'pc-ganadores', 1 === (int) $s['lugares'] ? __( 'Winner', 'foncholuis-art-challenge-manager' ) : __( 'Winners', 'foncholuis-art-challenge-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.

		if ( ! $ranking['puestos'] ) {
			echo '<p class="pc-vacio">' . esc_html__( 'This challenge ended without votes.', 'foncholuis-art-challenge-manager' ) . '</p>';
		} else {
			$primero = array_shift( $ranking['puestos'] );
			echo '<div class="pc-podio">';
			echo '<div class="pc-podio__primero">';
			foreach ( $primero['entradas'] as $id ) {
				echo self::ganadora( $por_id[ $id ], $primero, $s, count( $primero['entradas'] ) > 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
			}
			echo '</div>';
			if ( $ranking['puestos'] ) {
				echo '<div class="pc-podio__resto">';
				foreach ( $ranking['puestos'] as $puesto ) {
					$lugar  = $puesto['lugar'];
					$premio = isset( $s['premios'][ $lugar - 1 ] ) ? $s['premios'][ $lugar - 1 ] : '';
					printf(
						'<div class="pc-podio__puesto concursos-puesto concursos-puesto--%1$d"><h3 class="pc-podio__lugar"><span class="pc-medalla pc-medalla--%1$d" aria-hidden="true"></span>%2$s</h3>',
						(int) $lugar,
						esc_html( Concursos_Votos::nombre_lugar( $lugar, count( $puesto['entradas'] ) > 1 ) . ( '' !== $premio ? ' · ' . $premio : '' ) )
					);
					foreach ( $puesto['entradas'] as $id ) {
						$autor = Concursos_Entrada::owner( $id );
						printf(
							'<figure class="pc-podio__foto">%1$s<figcaption><span class="pc-foto__titulo">%2$s</span><span class="pc-foto__autor">%3$s</span><span class="pc-etiqueta concursos-votos">%4$s</span></figcaption></figure>',
							Concursos_Entrada::miniatura( $por_id[ $id ], 'medium_large' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
							esc_html( get_the_title( $id ) ),
							$autor ? Concursos_Perfil::enlace( $autor ) : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
							esc_html( Concursos_Votos::etiqueta( $puesto['votos'], $s ) )
						);
					}
					echo '</div>';
				}
				echo '</div>';
			}
			echo '</div>';
			array_unshift( $ranking['puestos'], $primero );
		}

		if ( $ranking['resto'] ) {
			echo '<div class="pc-resto concursos-resto"><h3 class="pc-resto__titulo">' . esc_html__( 'Other participants', 'foncholuis-art-challenge-manager' ) . '</h3>';
			echo '<ul class="pc-mosaico concursos-galeria__rejilla">';
			foreach ( $ranking['resto'] as $id ) {
				echo self::tarjeta( $por_id[ $id ], '<span class="pc-etiqueta concursos-votos">' . esc_html( Concursos_Votos::etiqueta( $votos[ $id ], $s ) ) . '</span>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
			}
			echo '</ul></div>';
		}

		self::resumen( $reto_id, $ranking, $votos, $por_id, $s );
		echo Concursos_Clasificacion::html( (int) wp_date( 'Y', (int) $s['cierre'] ), (int) $s['cierre'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
	}

	/**
	 * Foto en primer lugar, en grande.
	 *
	 * @param WP_Post $foto   Foto.
	 * @param array   $puesto Puesto.
	 * @param array   $s      Configuración.
	 * @param bool    $empate Si comparte el primer lugar.
	 * @return string
	 */
	private static function ganadora( $foto, array $puesto, array $s, $empate ) {
		$autor  = Concursos_Entrada::owner( $foto->ID );
		$premio = isset( $s['premios'][0] ) ? $s['premios'][0] : '';

		return sprintf(
			'<figure class="pc-ganadora concursos-puesto concursos-puesto--1"><div class="pc-ganadora__media">%1$s<span class="pc-sello pc-sello--oro">%2$s%3$s</span></div><figcaption class="pc-ganadora__pie"><div><span class="pc-ganadora__titulo">%4$s</span><span class="pc-foto__autor">%5$s</span></div><div class="pc-ganadora__datos"><span class="pc-etiqueta concursos-votos">%6$s</span>%7$s</div></figcaption></figure>',
			Concursos_Entrada::miniatura( $foto, 'full' ),
			Concursos_Vista::icono( 'medalla', 16 ),
			esc_html( Concursos_Votos::nombre_lugar( 1, $empate ) ),
			esc_html( get_the_title( $foto ) ),
			$autor ? Concursos_Perfil::enlace( $autor ) : '',
			esc_html( Concursos_Votos::etiqueta( $puesto['votos'], $s ) ),
			/* translators: %s: prize. */
			'' !== $premio ? '<span class="pc-ganadora__premio concursos-premio">' . esc_html( sprintf( __( 'Prize: %s', 'foncholuis-art-challenge-manager' ), $premio ) ) . '</span>' : ''
		);
	}

	/**
	 * Clasificación del reto: todas las fotos con sus votos o puntos.
	 *
	 * @param int       $reto_id Reto.
	 * @param array     $ranking Puestos y resto.
	 * @param int[]     $votos   entrada => votos.
	 * @param WP_Post[] $por_id  entrada => foto.
	 * @param array     $s       Configuración del reto.
	 */
	private static function resumen( $reto_id, array $ranking, array $votos, array $por_id, array $s ) {
		$total = array_sum( $votos );

		$filas = array();
		foreach ( $ranking['puestos'] as $puesto ) {
			$empate = count( $puesto['entradas'] ) > 1;
			foreach ( $puesto['entradas'] as $id ) {
				$filas[] = array( $id, number_format_i18n( $puesto['lugar'] ), $empate );
			}
		}
		foreach ( $ranking['resto'] as $id ) {
			$filas[] = array( $id, '—', false );
		}

		$participantes = Concursos_Votos::votantes_de_reto( $reto_id );
		$escala        = Concursos_Votos::por_puestos( $s ) ? __( '1st = 3 pts, 2nd = 2 pts, 3rd = 1 pt', 'foncholuis-art-challenge-manager' ) : '';
		/* translators: %d: number of voters. */
		$dato = sprintf( _n( '%d voter took part', '%d voters took part', $participantes, 'foncholuis-art-challenge-manager' ), $participantes ) . ( $escala ? ' · ' . $escala : '' );
		?>
		<section class="pc-seccion concursos-resumen" aria-labelledby="pc-resumen">
			<?php echo Concursos_Vista::cabecera_seccion( 'pc-resumen', __( 'Challenge ranking', 'foncholuis-art-challenge-manager' ), esc_html( $dato ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
			<div class="pc-tabla-marco">
				<table class="pc-tabla">
					<thead>
						<tr>
							<th scope="col" class="pc-tabla__puesto"><?php esc_html_e( 'Place', 'foncholuis-art-challenge-manager' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Photo', 'foncholuis-art-challenge-manager' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Participant', 'foncholuis-art-challenge-manager' ); ?></th>
							<th scope="col" class="pc-tabla__num concursos-tabla__num"><?php echo Concursos_Votos::por_puestos( $s ) ? esc_html__( 'Points', 'foncholuis-art-challenge-manager' ) : esc_html__( 'Votes', 'foncholuis-art-challenge-manager' ); ?></th>
							<th scope="col" class="pc-tabla__num concursos-tabla__num">%</th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $filas as $fila ) :
							$foto  = $por_id[ $fila[0] ];
							$autor = Concursos_Entrada::owner( $foto->ID );
							$n     = $votos[ $fila[0] ];
							?>
							<tr>
								<td class="pc-tabla__puesto">
									<?php echo esc_html( $fila[1] ); ?>
									<?php if ( $fila[2] ) : ?>
										<span class="pc-etiqueta"><?php esc_html_e( '(tie)', 'foncholuis-art-challenge-manager' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<div class="pc-tabla__foto">
										<?php echo Concursos_Entrada::miniatura( $foto, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
										<span class="pc-foto__titulo"><?php echo esc_html( get_the_title( $foto ) ); ?></span>
									</div>
								</td>
								<td class="pc-texto-suave"><?php echo $autor ? Concursos_Perfil::enlace( $autor ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?></td>
								<td class="pc-tabla__num pc-tabla__fuerte concursos-tabla__num"><?php echo esc_html( number_format_i18n( $n ) ); ?></td>
								<td class="pc-tabla__num pc-texto-suave concursos-tabla__num"><?php echo esc_html( $total ? number_format_i18n( $n / $total * 100, 1 ) : '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<?php if ( $total ) : ?>
						<tfoot>
							<tr>
								<th scope="row" colspan="3"><?php esc_html_e( 'Total', 'foncholuis-art-challenge-manager' ); ?></th>
								<td class="pc-tabla__num concursos-tabla__num"><?php echo esc_html( number_format_i18n( $total ) ); ?></td>
								<td class="pc-tabla__num concursos-tabla__num"><?php echo esc_html( number_format_i18n( 100, 1 ) ); ?></td>
							</tr>
						</tfoot>
					<?php endif; ?>
				</table>
			</div>
		</section>
		<?php
	}
}
