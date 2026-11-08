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
	 * @param string $imagen URL de la imagen o vacío.
	 * @param string $titulo Título, en HTML ya escapado.
	 * @param string $extra  HTML ya escapado bajo la fase.
	 * @return string
	 */
	public static function cabecera( $status, array $s, $imagen, $titulo, $extra = '' ) {
		$labels = Concursos_Reto::status_labels();

		return sprintf(
			'<header class="concursos-cabecera%1$s"><div class="concursos-cabecera__texto"><p class="concursos-etiqueta">%2$s</p>%3$s<p class="concursos-estado">%4$s</p>%5$s</div>%6$s</header>',
			$imagen ? ' concursos-cabecera--imagen' : '',
			esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : $status ),
			$titulo,
			esc_html( self::frase_estado( $status, $s ) ),
			$extra,
			$imagen ? '<img class="concursos-cabecera__imagen" src="' . esc_url( $imagen ) . '" alt="">' : ''
		);
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
				<?php echo self::cabecera( $status, $s, $imagen, '<h1>' . esc_html( get_the_title( $post ) ) . '</h1>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
				<div class="concursos-reto__descripcion">
					<?php echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound,WordPress.Security.EscapeOutput.OutputNotEscaped -- filtro estándar de WordPress. ?>
				</div>
				<?php if ( $reglas || $premios ) : ?>
					<div class="concursos-reto__info">
						<?php if ( $reglas ) : ?>
							<section>
								<h2><?php esc_html_e( 'Reglas', 'concursos' ); ?></h2>
								<ul>
									<?php foreach ( $reglas as $regla ) : ?>
										<li><?php echo esc_html( $regla ); ?></li>
									<?php endforeach; ?>
								</ul>
							</section>
						<?php endif; ?>
						<?php if ( $premios ) : ?>
							<section>
								<h2><?php esc_html_e( 'Premios', 'concursos' ); ?></h2>
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
			/* translators: %s: fecha. */
			'proximo'    => array( __( 'Empieza el %s.', 'concursos' ), 'inicio' ),
			/* translators: %s: fecha. */
			'envios'     => array( __( 'Recibiendo fotos hasta el %s.', 'concursos' ), 'fin_envios' ),
			/* translators: %s: fecha. */
			'votacion'   => array( __( 'En votación hasta el %s.', 'concursos' ), 'fin_votacion' ),
			/* translators: %s: fecha. */
			'evaluacion' => array( __( 'La votación terminó. Resultados el %s.', 'concursos' ), 'cierre' ),
			/* translators: %s: fecha. */
			'finalizado' => array( __( 'Reto finalizado el %s.', 'concursos' ), 'cierre' ),
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
			get_the_post_thumbnail( $entrada, 'medium_large', array( 'loading' => 'lazy' ) ),
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
		echo '<h2>' . esc_html__( 'Fotos participantes', 'concursos' ) . '</h2>';

		if ( ! Concursos_Reto::photos_visible( $s ) ) {
			if ( 'envios' === $status ) {
				printf(
					'<p>%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: número de fotos, 2: fecha de fin de los envíos. */
							_n( 'Ya se ha enviado %1$d foto. Se mostrarán todas cuando terminen los envíos, el %2$s.', 'Ya se han enviado %1$d fotos. Se mostrarán todas cuando terminen los envíos, el %2$s.', count( $entradas ), 'concursos' ),
							count( $entradas ),
							Concursos_Reto::format_date( $s['fin_envios'] )
						)
					)
				);
			} else {
				echo '<p>' . esc_html__( 'Las fotos se mostrarán cuando empiece el reto.', 'concursos' ) . '</p>';
			}

			return;
		}

		if ( ! $entradas ) {
			echo '<p>' . esc_html__( 'Todavía no hay fotos.', 'concursos' ) . '</p>';

			return;
		}

		$votante = 'votacion' === $status ? Concursos_Votantes::actual() : null;
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
			/* translators: %d: número de votos. */
			return '<span class="concursos-votos">' . esc_html( sprintf( _n( '%d voto', '%d votos', $n, 'concursos' ), $n ) ) . '</span>';
		};

		echo '<h2>' . esc_html__( 'Resultados', 'concursos' ) . '</h2>';

		if ( ! $ranking['puestos'] ) {
			echo '<p>' . esc_html__( 'Este reto terminó sin votos.', 'concursos' ) . '</p>';
		}

		foreach ( $ranking['puestos'] as $puesto ) {
			$lugar  = $puesto['lugar'];
			$premio = isset( $s['premios'][ $lugar - 1 ] ) ? $s['premios'][ $lugar - 1 ] : '';

			printf( '<div class="concursos-puesto concursos-puesto--%d">', (int) $lugar );
			echo '<h3>' . esc_html( Concursos_Votos::nombre_lugar( $lugar, count( $puesto['entradas'] ) > 1 ) ) . '</h3>';
			if ( '' !== $premio ) {
				/* translators: %s: premio. */
				echo '<p class="concursos-premio">' . esc_html( sprintf( __( 'Premio: %s', 'concursos' ), $premio ) ) . '</p>';
			}
			echo '<ul class="concursos-galeria__rejilla">';
			foreach ( $puesto['entradas'] as $id ) {
				echo self::tarjeta( $por_id[ $id ], $etiqueta_votos( $puesto['votos'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
			}
			echo '</ul></div>';
		}

		if ( $ranking['resto'] ) {
			echo '<div class="concursos-resto"><h3>' . esc_html__( 'Resto de participantes', 'concursos' ) . '</h3>';
			echo '<ul class="concursos-galeria__rejilla">';
			foreach ( $ranking['resto'] as $id ) {
				echo self::tarjeta( $por_id[ $id ], $etiqueta_votos( $votos[ $id ] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
			}
			echo '</ul></div>';
		}
	}
}
