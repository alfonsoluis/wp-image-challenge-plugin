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
			Concursos_Plantilla::pagina( get_the_title( $post ), self::pagina( $post ), 'concursos-publico--reto' );
		}
	}

	/**
	 * Cabecera de un reto: panel de color con sus datos junto a su imagen. La usan
	 * la página del reto y la portada. El texto va sobre color sólido, no sobre la
	 * foto, para que su contraste no dependa de la imagen.
	 *
	 * @param string $status Estado.
	 * @param array  $s      Configuración.
	 * @param array  $imagen url, ancho y alto de la imagen, o null.
	 * @param string $titulo Título, en HTML ya escapado.
	 * @param string $extra  HTML ya escapado bajo la fase.
	 * @return string
	 */
	public static function cabecera( $status, array $s, $imagen, $titulo, $extra = '' ) {
		$labels = Concursos_Reto::status_labels();

		return sprintf(
			'<header class="concursos-cabecera%1$s"><div class="concursos-cabecera__texto"><p class="concursos-etiqueta concursos-xs">%2$s</p>%3$s<p class="concursos-estado concursos-sm">%4$s</p>%5$s</div>%6$s</header>',
			$imagen ? ' concursos-cabecera--imagen' : '',
			esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : $status ),
			$titulo,
			esc_html( self::frase_estado( $status, $s ) ),
			$extra,
			$imagen ? '<div class="concursos-cabecera__media"><img class="concursos-cabecera__imagen" src="' . esc_url( $imagen['url'] ) . '" alt=""></div>' : ''
		);
	}

	/**
	 * Línea con el autor, la fecha de finalización y las etiquetas de un reto.
	 *
	 * @param WP_Post $post Reto.
	 * @param array   $s    Configuración.
	 * @return string
	 */
	private static function ficha( $post, array $s ) {
		$datos = array();

		$autor = get_the_author_meta( 'display_name', $post->post_author );
		if ( $autor ) {
			/* translators: %s: name of the user who created the challenge. */
			$datos[] = sprintf( __( 'By %s', 'concursos' ), $autor );
		}
		$datos[] = wp_date( get_option( 'date_format' ), (int) $s['cierre'] );

		$etiquetas = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'names' ) );
		if ( $etiquetas && ! is_wp_error( $etiquetas ) ) {
			/* translators: %s: list of tags. */
			$datos[] = sprintf( __( 'Tags: %s', 'concursos' ), implode( ', ', $etiquetas ) );
		}

		return '<p class="concursos-ficha concursos-sm">' . esc_html( implode( ' · ', $datos ) ) . '</p>';
	}

	/**
	 * Enlace para inscribirse en un reto mientras acepta inscripciones.
	 *
	 * @param int    $reto_id Reto.
	 * @param string $status  Estado.
	 * @return string
	 */
	private static function boton_inscripcion( $reto_id, $status ) {
		$url = in_array( $status, array( 'proximo', 'envios' ), true ) ? Concursos_Inscripcion::url( $reto_id ) : '';

		return $url ? '<p><a class="concursos-boton" href="' . esc_url( $url ) . '">' . esc_html__( 'Join this challenge', 'concursos' ) . '</a></p>' : '';
	}

	/**
	 * Página completa de un reto, con su diseño.
	 *
	 * @param WP_Post $post Reto.
	 * @return string
	 */
	private static function pagina( $post ) {
		$s      = Concursos_Reto::get_settings( $post->ID );
		$status = Concursos_Reto::status( $s );
		$imagen = Concursos_Portada::imagen(
			array(
				'post'     => $post,
				'settings' => $s,
			),
			'large'
		);
		$reglas = Concursos_Reto::rules_list( $s['reglas'] );
		$premios = array();
		for ( $i = 0; $i < (int) $s['lugares']; $i++ ) {
			if ( '' !== $s['premios'][ $i ] ) {
				$premios[ $i + 1 ] = $s['premios'][ $i ];
			}
		}

		Concursos_Diseno::aplicar( $s );

		ob_start();
		?>
		<div class="concursos-reto concursos-disenado" style="<?php echo esc_attr( Concursos_Diseno::estilo( $s ) ); ?>">
			<div class="concursos-reto__interior">
				<?php echo self::cabecera( $status, $s, $imagen, '<h1>' . esc_html( get_the_title( $post ) ) . '</h1>', self::boton_inscripcion( $post->ID, $status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
				<?php echo 'finalizado' === $status ? self::ficha( $post, $s ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
				<div class="concursos-reto__descripcion">
					<?php echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound,WordPress.Security.EscapeOutput.OutputNotEscaped -- filtro estándar de WordPress. ?>
				</div>
				<?php if ( $reglas || $premios ) : ?>
					<div class="concursos-reto__info">
						<?php if ( $reglas ) : ?>
							<section>
								<h2><?php esc_html_e( 'Rules', 'concursos' ); ?></h2>
								<ul>
									<?php foreach ( $reglas as $regla ) : ?>
										<li><?php echo esc_html( $regla ); ?></li>
									<?php endforeach; ?>
								</ul>
							</section>
						<?php endif; ?>
						<?php if ( $premios ) : ?>
							<section>
								<h2><?php esc_html_e( 'Prizes', 'concursos' ); ?></h2>
								<ul>
									<?php foreach ( $premios as $lugar => $premio ) : ?>
										<li><strong><?php echo esc_html( Concursos_Votos::nombre_lugar( $lugar, false ) ); ?>:</strong> <?php echo esc_html( $premio ); ?></li>
									<?php endforeach; ?>
								</ul>
							</section>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<?php echo self::render( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
			</div>
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
		echo '<section class="concursos-galeria" id="concursos-votacion">';
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
			'proximo'    => array( __( 'Starts on %s.', 'concursos' ), 'inicio' ),
			/* translators: %s: date. */
			'envios'     => array( __( 'Accepting photos until %s.', 'concursos' ), 'fin_envios' ),
			/* translators: %s: date. */
			'votacion'   => array( __( 'Voting until %s.', 'concursos' ), 'fin_votacion' ),
			/* translators: %s: date. */
			'evaluacion' => array( __( 'Voting has ended. Results on %s.', 'concursos' ), 'cierre' ),
			/* translators: %s: date. */
			'finalizado' => array( __( 'Challenge finished on %s.', 'concursos' ), 'cierre' ),
		);

		return isset( $frases[ $status ] ) ? sprintf( $frases[ $status ][0], Concursos_Reto::format_date( $s[ $frases[ $status ][1] ] ) ) : '';
	}

	/**
	 * Tarjeta de una foto.
	 *
	 * @param WP_Post $entrada Foto.
	 * @param string  $extra   HTML ya escapado bajo los datos.
	 * @return string
	 */
	private static function tarjeta( $entrada, $extra = '' ) {
		$autor = Concursos_Entrada::owner( $entrada->ID );

		return sprintf(
			'<li><figure>%s<figcaption><strong>%s</strong><span>%s</span>%s</figcaption></figure></li>',
			Concursos_Entrada::miniatura( $entrada ),
			esc_html( get_the_title( $entrada ) ),
			$autor ? sprintf( '<a href="%s">%s</a>', esc_url( Concursos_Perfil::url( $autor ) ), esc_html( get_the_title( $autor ) ) ) : '',
			$extra
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
		echo '<h2>' . esc_html__( 'Submitted entries', 'concursos' ) . '</h2>';

		if ( ! Concursos_Reto::photos_visible( $s ) ) {
			if ( 'envios' === $status ) {
				printf(
					'<p>%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: number of photos, 2: submission end date. */
							_n( '%1$d photo has been submitted so far. All of them will be shown when submissions end, on %2$s.', '%1$d photos have been submitted so far. All of them will be shown when submissions end, on %2$s.', count( $entradas ), 'concursos' ),
							count( $entradas ),
							Concursos_Reto::format_date( $s['fin_envios'] )
						)
					)
				);
			} else {
				echo '<p>' . esc_html__( 'Photos will be shown when the challenge starts.', 'concursos' ) . '</p>';
			}

			return;
		}

		if ( ! $entradas ) {
			echo '<p>' . esc_html__( 'There are no photos yet.', 'concursos' ) . '</p>';

			return;
		}

		$votante = 'votacion' === $status ? Concursos_Votantes::actual() : null;
		if ( $votante && ! Concursos_Reto::permite_email( $s, 'votantes', $votante->email ) ) {
			$votante = null;
		}
		$mios    = $votante ? Concursos_Votos::de_votante( (int) $votante->id, $reto_id ) : array();
		$puede   = count( $mios ) < (int) $s['votos_por_votante'];

		echo '<ul class="concursos-galeria__rejilla">';
		foreach ( $entradas as $entrada ) {
			$boton = $votante ? Concursos_Votos::boton( $reto_id, $entrada->ID, in_array( $entrada->ID, $mios, true ), $puede ) : '';
			echo self::tarjeta( $entrada, $boton ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
		}
		echo '</ul>';
	}

	/**
	 * Resultados finales: puestos (con empates en igualdad) y resto de fotos.
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

		$etiqueta_votos = function ( $n ) {
			/* translators: %d: number of votes. */
			return '<span class="concursos-votos">' . esc_html( sprintf( _n( '%d vote', '%d votes', $n, 'concursos' ), $n ) ) . '</span>';
		};

		echo '<h2>' . esc_html__( 'Results', 'concursos' ) . '</h2>';

		if ( ! $ranking['puestos'] ) {
			echo '<p>' . esc_html__( 'This challenge ended without votes.', 'concursos' ) . '</p>';
		}

		foreach ( $ranking['puestos'] as $puesto ) {
			$lugar  = $puesto['lugar'];
			$premio = isset( $s['premios'][ $lugar - 1 ] ) ? $s['premios'][ $lugar - 1 ] : '';

			printf( '<div class="concursos-puesto concursos-puesto--%d">', (int) $lugar );
			echo '<h3>' . esc_html( Concursos_Votos::nombre_lugar( $lugar, count( $puesto['entradas'] ) > 1 ) ) . '</h3>';
			if ( '' !== $premio ) {
				/* translators: %s: prize. */
				echo '<p class="concursos-premio">' . esc_html( sprintf( __( 'Prize: %s', 'concursos' ), $premio ) ) . '</p>';
			}
			echo '<ul class="concursos-galeria__rejilla">';
			foreach ( $puesto['entradas'] as $id ) {
				echo self::tarjeta( $por_id[ $id ], $etiqueta_votos( $puesto['votos'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
			}
			echo '</ul></div>';
		}

		if ( $ranking['resto'] ) {
			echo '<div class="concursos-resto"><h3>' . esc_html__( 'Other participants', 'concursos' ) . '</h3>';
			echo '<ul class="concursos-galeria__rejilla">';
			foreach ( $ranking['resto'] as $id ) {
				echo self::tarjeta( $por_id[ $id ], $etiqueta_votos( $votos[ $id ] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
			}
			echo '</ul></div>';
		}

		self::resumen( $reto_id, $ranking, $votos, $por_id );
		echo Concursos_Clasificacion::html( (int) wp_date( 'Y', (int) $s['cierre'] ), (int) $s['cierre'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
	}

	/**
	 * Tabla final con los votos de cada foto.
	 *
	 * @param int       $reto_id Reto.
	 * @param array     $ranking Puestos y resto.
	 * @param int[]     $votos   entrada => votos.
	 * @param WP_Post[] $por_id  entrada => foto.
	 */
	private static function resumen( $reto_id, array $ranking, array $votos, array $por_id ) {
		$total = array_sum( $votos );
		if ( ! $total ) {
			return;
		}

		$filas = array();
		foreach ( $ranking['puestos'] as $puesto ) {
			$empate = count( $puesto['entradas'] ) > 1;
			foreach ( $puesto['entradas'] as $id ) {
				$filas[] = array( $id, number_format_i18n( $puesto['lugar'] ) . ( $empate ? ' ' . __( '(tie)', 'concursos' ) : '' ) );
			}
		}
		foreach ( $ranking['resto'] as $id ) {
			$filas[] = array( $id, '—' );
		}

		$participantes = Concursos_Votos::votantes_de_reto( $reto_id );
		?>
		<section class="concursos-resumen">
			<h2><?php esc_html_e( 'Vote summary', 'concursos' ); ?></h2>
			<p class="concursos-sm">
				<?php
				/* translators: %d: number of voters. */
				echo esc_html( sprintf( _n( '%d voter took part.', '%d voters took part.', $participantes, 'concursos' ), $participantes ) );
				?>
			</p>
			<div class="concursos-tabla-marco">
				<table class="concursos-tabla concursos-sm">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Place', 'concursos' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Photo', 'concursos' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Participant', 'concursos' ); ?></th>
							<th scope="col" class="concursos-tabla__num"><?php esc_html_e( 'Votes', 'concursos' ); ?></th>
							<th scope="col" class="concursos-tabla__num">%</th>
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
								<td><?php echo esc_html( $fila[1] ); ?></td>
								<td>
									<div class="concursos-tabla__foto">
										<?php echo Concursos_Entrada::miniatura( $foto, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
										<span><?php echo esc_html( get_the_title( $foto ) ); ?></span>
									</div>
								</td>
								<td><a href="<?php echo esc_url( Concursos_Perfil::url( $autor ) ); ?>"><?php echo esc_html( get_the_title( $autor ) ); ?></a></td>
								<td class="concursos-tabla__num"><?php echo esc_html( number_format_i18n( $n ) ); ?></td>
								<td class="concursos-tabla__num"><?php echo esc_html( number_format_i18n( $n / $total * 100, 1 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<th scope="row" colspan="3"><?php esc_html_e( 'Total', 'concursos' ); ?></th>
							<td class="concursos-tabla__num"><?php echo esc_html( number_format_i18n( $total ) ); ?></td>
							<td class="concursos-tabla__num"><?php echo esc_html( number_format_i18n( 100, 1 ) ); ?></td>
						</tr>
					</tfoot>
				</table>
			</div>
		</section>
		<?php
	}
}
