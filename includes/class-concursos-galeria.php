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
			$datos[] = sprintf( __( 'By %s', 'photo-challenges' ), $autor );
		}
		$datos[] = wp_date( get_option( 'date_format' ), (int) $s['cierre'] );

		$etiquetas = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'names' ) );
		if ( $etiquetas && ! is_wp_error( $etiquetas ) ) {
			/* translators: %s: list of tags. */
			$datos[] = sprintf( __( 'Tags: %s', 'photo-challenges' ), implode( ', ', $etiquetas ) );
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

		return '<nav class="pc-navegacion concursos-navegacion" aria-label="' . esc_attr__( 'Challenge navigation', 'photo-challenges' ) . '">'
			. ( $anterior ? $enlace( $anterior, 'anterior', 'prev', __( 'Previous challenge', 'photo-challenges' ) ) : '' )
			. ( $siguiente ? $enlace( $siguiente, 'siguiente', 'next', __( 'Next challenge', 'photo-challenges' ) ) : '' )
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
			$html .= '<p class="pc-acciones"><a class="pc-boton pc-boton--principal pc-boton--ancho concursos-boton" href="' . esc_url( $inscripcion ) . '">' . Concursos_Vista::icono( 'subir' ) . esc_html__( 'Join this challenge', 'photo-challenges' ) . '</a></p>';
		}
		if ( 'envios' === $status ) {
			$html .= '<p class="pc-nota"><strong>' . esc_html__( 'Already registered?', 'photo-challenges' ) . '</strong> ' . esc_html__( 'Use the link we emailed you to upload or delete your photos.', 'photo-challenges' ) . '</p>';
		}
		if ( 'votacion' === $status ) {
			$html .= '<p class="pc-acciones">' . Concursos_Vista::boton( '#concursos-votacion', __( 'See the photos and vote', 'photo-challenges' ), 'principal', 'flecha', 'pc-boton--ancho' ) . '</p>';
			if ( Concursos_Votos::por_puestos( $s ) ) {
				$html .= '<p class="pc-nota">' . esc_html( 3 === (int) $s['votos_por_votante'] ? __( 'Choose your 1st, 2nd and 3rd: they are worth 3, 2 and 1 points.', 'photo-challenges' ) : __( 'Choose your 1st and 2nd: they are worth 3 and 2 points.', 'photo-challenges' ) ) . '</p>';
			}
		}
		if ( 'finalizado' === $status ) {
			$siguiente = self::siguiente_abierto( $post->ID );
			if ( $siguiente ) {
				$imagen = Concursos_Portada::imagen( $siguiente, 'thumbnail' );
				$html  .= sprintf(
					'<a class="pc-siguiente" href="%1$s">%2$s<span class="pc-siguiente__texto"><span class="pc-texto-suave">%3$s</span><strong>%4$s</strong></span>%5$s</a>',
					esc_url( get_permalink( $siguiente['post'] ) ),
					$imagen ? '<img src="' . esc_url( $imagen['url'] ) . '" alt="" width="48" height="48">' : '',
					esc_html( 'envios' === $siguiente['status'] ? __( 'Next open challenge', 'photo-challenges' ) : __( 'Next challenge', 'photo-challenges' ) ),
					esc_html( get_the_title( $siguiente['post'] ) . ' · ' . Concursos_Galeria::frase_estado( $siguiente['status'], $siguiente['settings'] ) ),
					Concursos_Vista::icono( 'flecha' )
				);
			}
		}

		return '<aside class="pc-lateral" aria-label="' . esc_attr__( 'Challenge schedule', 'photo-challenges' ) . '">' . $html . '</aside>';
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
			sprintf( _n( '%d photo submitted', '%d photos submitted', $fotos, 'photo-challenges' ), $fotos ),
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
		<div class="pc-contenedor concursos-reto<?php echo Concursos_Diseno::muestra_fondo( $s, 'detalle' ) ? ' concursos-con-fondo' : ''; ?>">
			<nav class="pc-ruta" aria-label="<?php esc_attr_e( 'Breadcrumb', 'photo-challenges' ); ?>">
				<a href="<?php echo esc_url( $portada ); ?>"><?php esc_html_e( 'Challenges', 'photo-challenges' ); ?></a>
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
					<?php if ( $reglas ) : ?>
						<section class="pc-bloque" id="reglas" aria-labelledby="pc-reglas">
							<h2 class="pc-bloque__titulo" id="pc-reglas"><?php esc_html_e( 'Rules', 'photo-challenges' ); ?></h2>
							<ul class="pc-reglas">
								<?php foreach ( $reglas as $regla ) : ?>
									<li><?php echo esc_html( $regla ); ?></li>
								<?php endforeach; ?>
							</ul>
						</section>
					<?php endif; ?>
					<?php if ( $premios ) : ?>
						<section class="pc-bloque"<?php echo $reglas ? '' : ' id="reglas"'; ?> aria-labelledby="pc-premios">
							<h2 class="pc-bloque__titulo" id="pc-premios"><?php esc_html_e( 'Prizes', 'photo-challenges' ); ?></h2>
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
			if ( 'votacion' === $status ) {
				echo Concursos_Votantes::panel_html( $reto_id, $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
			}
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
			'proximo'    => array( __( 'Starts on %s.', 'photo-challenges' ), 'inicio' ),
			/* translators: %s: date. */
			'envios'     => array( __( 'Accepting photos until %s.', 'photo-challenges' ), 'fin_envios' ),
			/* translators: %s: date. */
			'votacion'   => array( __( 'Voting until %s.', 'photo-challenges' ), 'fin_votacion' ),
			/* translators: %s: date. */
			'evaluacion' => array( __( 'Voting has ended. Results on %s.', 'photo-challenges' ), 'cierre' ),
			/* translators: %s: date. */
			'finalizado' => array( __( 'Finished on %s.', 'photo-challenges' ), 'cierre' ),
		);

		return isset( $frases[ $status ] ) ? sprintf( $frases[ $status ][0], Concursos_Reto::format_dia( $s[ $frases[ $status ][1] ] ) ) : '';
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
				esc_html( sprintf( __( 'No. %s', 'photo-challenges' ), str_pad( (string) $numero, 2, '0', STR_PAD_LEFT ) ) ),
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
			$nota = sprintf( __( 'Shown as they are submitted · voting starts on %s', 'photo-challenges' ), Concursos_Reto::format_dia( $s['fin_envios'] ) );
		} elseif ( $entradas ) {
			/* translators: %d: number of photos. */
			$nota = sprintf( _n( '%d photo', '%d photos', count( $entradas ), 'photo-challenges' ), count( $entradas ) );
		}
		echo Concursos_Vista::cabecera_seccion( 'pc-fotos', 'votacion' === $status ? __( 'Photos in the vote', 'photo-challenges' ) : __( 'Submitted entries', 'photo-challenges' ), esc_html( $nota ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.

		if ( ! Concursos_Reto::photos_visible( $s ) ) {
			if ( 'envios' === $status ) {
				printf(
					'<p class="pc-vacio">%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: number of photos, 2: submission end date. */
							_n( '%1$d photo has been submitted so far. All of them will be shown when submissions end, on %2$s.', '%1$d photos have been submitted so far. All of them will be shown when submissions end, on %2$s.', count( $entradas ), 'photo-challenges' ),
							count( $entradas ),
							Concursos_Reto::format_dia( $s['fin_envios'] )
						)
					)
				);
			} else {
				echo '<p class="pc-vacio">' . esc_html__( 'Photos will be shown when the challenge starts.', 'photo-challenges' ) . '</p>';
			}

			return;
		}

		if ( ! $entradas ) {
			echo '<p class="pc-vacio">' . esc_html__( 'There are no photos yet.', 'photo-challenges' ) . '</p>';

			return;
		}

		$votante = 'votacion' === $status ? Concursos_Votantes::actual() : null;
		if ( $votante && ! Concursos_Reto::permite_email( $s, 'votantes', $votante->email ) ) {
			$votante = null;
		}
		$mios    = $votante ? Concursos_Votos::puestos_de_votante( (int) $votante->id, $reto_id ) : array();
		$anonima = Concursos_Reto::oculta_autoria( $s, $status );

		if ( $anonima ) {
			echo '<p class="pc-aviso-suave">' . esc_html__( 'Anonymous voting: the title and author of each photo will be shown when voting ends.', 'photo-challenges' ) . '</p>';
		}

		echo '<ul class="pc-mosaico concursos-galeria__rejilla">';
		foreach ( $entradas as $i => $entrada ) {
			$boton = $votante ? Concursos_Votos::botones( $reto_id, $s, $entrada->ID, $mios ) : '';
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

		echo Concursos_Vista::cabecera_seccion( 'pc-ganadores', 1 === (int) $s['lugares'] ? __( 'Winner', 'photo-challenges' ) : __( 'Winners', 'photo-challenges' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.

		if ( ! $ranking['puestos'] ) {
			echo '<p class="pc-vacio">' . esc_html__( 'This challenge ended without votes.', 'photo-challenges' ) . '</p>';
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
			echo '<div class="pc-resto concursos-resto"><h3 class="pc-resto__titulo">' . esc_html__( 'Other participants', 'photo-challenges' ) . '</h3>';
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
			'' !== $premio ? '<span class="pc-ganadora__premio concursos-premio">' . esc_html( sprintf( __( 'Prize: %s', 'photo-challenges' ), $premio ) ) . '</span>' : ''
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
		$escala        = Concursos_Votos::por_puestos( $s ) ? __( '1st = 3 pts, 2nd = 2 pts, 3rd = 1 pt', 'photo-challenges' ) : '';
		/* translators: %d: number of voters. */
		$dato = sprintf( _n( '%d voter took part', '%d voters took part', $participantes, 'photo-challenges' ), $participantes ) . ( $escala ? ' · ' . $escala : '' );
		?>
		<section class="pc-seccion concursos-resumen" aria-labelledby="pc-resumen">
			<?php echo Concursos_Vista::cabecera_seccion( 'pc-resumen', __( 'Challenge ranking', 'photo-challenges' ), esc_html( $dato ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
			<div class="pc-tabla-marco">
				<table class="pc-tabla">
					<thead>
						<tr>
							<th scope="col" class="pc-tabla__puesto"><?php esc_html_e( 'Place', 'photo-challenges' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Photo', 'photo-challenges' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Participant', 'photo-challenges' ); ?></th>
							<th scope="col" class="pc-tabla__num concursos-tabla__num"><?php echo Concursos_Votos::por_puestos( $s ) ? esc_html__( 'Points', 'photo-challenges' ) : esc_html__( 'Votes', 'photo-challenges' ); ?></th>
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
										<span class="pc-etiqueta"><?php esc_html_e( '(tie)', 'photo-challenges' ); ?></span>
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
								<th scope="row" colspan="3"><?php esc_html_e( 'Total', 'photo-challenges' ); ?></th>
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
