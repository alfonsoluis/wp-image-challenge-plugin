<?php
/**
 * Portada pública de retos (/retos/).
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Muestra el reto en curso más reciente de forma destacada y el resto en listas.
 */
class Concursos_Portada {

	const OPCION_PAGINA = 'concursos_pagina_retos';

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'route' ) );
		add_action( 'init', array( __CLASS__, 'asegurar_pagina' ), 20 );
		add_filter( 'display_post_states', array( __CLASS__, 'estado_pagina' ), 10, 2 );
	}

	/**
	 * Crea una vez la página "Retos", para poder elegirla en el menú del sitio
	 * como cualquier otra. Si después se borra, no se vuelve a crear.
	 */
	public static function asegurar_pagina() {
		if ( get_option( self::OPCION_PAGINA ) ) {
			return;
		}

		$pagina = get_page_by_path( Concursos_Post_Types::base_url() );
		$id     = $pagina ? $pagina->ID : wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => __( 'Challenges', 'photo-challenges' ),
				'post_name'   => Concursos_Post_Types::base_url(),
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( self::OPCION_PAGINA, (int) $id );
		}
	}

	/**
	 * Marca la página en la lista de páginas del panel.
	 *
	 * @param string[] $states Estados.
	 * @param WP_Post  $post   Post.
	 * @return string[]
	 */
	public static function estado_pagina( $states, $post ) {
		if ( (int) get_option( self::OPCION_PAGINA ) === $post->ID ) {
			$states['concursos_retos'] = __( 'Challenges page', 'photo-challenges' );
		}

		return $states;
	}

	/**
	 * Atiende el archivo de retos.
	 */
	public static function route() {
		$pagina = (int) get_option( self::OPCION_PAGINA );
		if ( ( is_post_type_archive( Concursos_Post_Types::RETO ) || ( $pagina && is_page( $pagina ) ) ) && ! is_feed() ) {
			$referencia = self::reto_referencia();
			Concursos_Plantilla::pagina( __( 'Challenges', 'photo-challenges' ), self::render(), 'concursos-publico--portada', $referencia ? $referencia['settings'] : null );
		}
	}

	/**
	 * Retos publicados, agrupados por momento.
	 *
	 * @return array[] En curso, próximos y pasados.
	 */
	private static function clasificar() {
		$en_curso = array();
		$proximos = array();
		$pasados  = array();
		foreach ( Concursos_Reto::all( array( 'publish' ) ) as $reto ) {
			if ( in_array( $reto['status'], array( 'envios', 'votacion', 'evaluacion' ), true ) ) {
				$en_curso[] = $reto;
			} elseif ( 'proximo' === $reto['status'] ) {
				$proximos[] = $reto;
			} elseif ( 'finalizado' === $reto['status'] ) {
				$pasados[] = $reto;
			}
		}

		return array( $en_curso, $proximos, $pasados );
	}

	/**
	 * Reto que decide el diseño de las páginas comunes: el destacado o, si no
	 * hay, el último terminado.
	 *
	 * @return array|null
	 */
	public static function reto_referencia() {
		list( $en_curso, $proximos, $pasados ) = self::clasificar();

		$destacado = $en_curso ? array_shift( $en_curso ) : array_pop( $proximos );
		if ( $destacado ) {
			return $destacado;
		}

		return $pasados ? $pasados[0] : null;
	}

	/**
	 * Contenido de la portada.
	 *
	 * @return string
	 */
	private static function render() {
		list( $en_curso, $proximos, $pasados ) = self::clasificar();

		// Destacado: el reto en curso más reciente o, si no hay, el próximo en empezar.
		$destacado = $en_curso ? array_shift( $en_curso ) : array_pop( $proximos );
		$abiertos  = array();
		$activos   = array();
		foreach ( $en_curso as $reto ) {
			if ( 'envios' === $reto['status'] ) {
				$abiertos[] = $reto;
			} else {
				$activos[] = $reto;
			}
		}
		$proximos = array_reverse( (array) $proximos );

		ob_start();
		echo '<div class="pc-contenedor">';
		self::hero();

		if ( ! $destacado && ! $pasados ) {
			echo '<p class="pc-vacio">' . esc_html__( 'No challenges have been published yet.', 'photo-challenges' ) . '</p>';
		}
		if ( $destacado ) {
			self::destacado( $destacado );
		}
		if ( $abiertos ) {
			self::seccion_tarjetas( 'pc-abiertos', __( 'Open for entries', 'photo-challenges' ), $abiertos );
		}
		if ( $activos ) {
			self::seccion_tarjetas( 'pc-activos', __( 'Also in progress', 'photo-challenges' ), $activos );
		}
		if ( $proximos ) {
			self::seccion_proximos( $proximos );
		}
		if ( $pasados ) {
			self::seccion_terminados( $pasados );
		}
		echo Concursos_Clasificacion::html( (int) wp_date( 'Y' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * Presentación de la página, con el botón para inscribirse.
	 */
	private static function hero() {
		$inscripcion = Concursos_Inscripcion::url();
		?>
		<section class="pc-hero">
			<div class="pc-hero__texto">
				<p class="pc-antetitulo">
					<?php
					/* translators: %d: year. */
					echo esc_html( sprintf( __( 'Photo challenges · %d', 'photo-challenges' ), (int) wp_date( 'Y' ) ) );
					?>
				</p>
				<h1 class="pc-hero__titulo"><?php esc_html_e( 'One theme. One photo.', 'photo-challenges' ); ?> <em><?php esc_html_e( 'Your eye.', 'photo-challenges' ); ?></em></h1>
			</div>
			<div class="pc-hero__lateral">
				<p class="pc-hero__entrada"><?php esc_html_e( 'Take part with your photos, vote for the community’s favourites and find out who won each challenge.', 'photo-challenges' ); ?></p>
				<?php if ( $inscripcion && Concursos_Inscripcion::retos_abiertos() ) : ?>
					<p class="pc-acciones"><a class="pc-boton pc-boton--principal concursos-boton" href="<?php echo esc_url( $inscripcion ); ?>"><?php esc_html_e( 'Join a challenge', 'photo-challenges' ); ?><?php echo Concursos_Vista::icono( 'flecha' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fijo. ?></a></p>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Texto del botón principal de un reto, según su fase.
	 *
	 * @param string $status Fase.
	 * @return string
	 */
	private static function accion( $status ) {
		return 'votacion' === $status ? __( 'See the photos and vote', 'photo-challenges' ) : ( 'envios' === $status ? __( 'Take part in the challenge', 'photo-challenges' ) : __( 'See the challenge', 'photo-challenges' ) );
	}

	/**
	 * Reto destacado.
	 *
	 * @param array $reto Reto.
	 */
	private static function destacado( array $reto ) {
		$s      = $reto['settings'];
		$texto  = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $reto['post']->post_content ) ), 32 );
		$fotos  = count( Concursos_Entrada::find( array( 'reto' => $reto['post']->ID ) ) );
		$url    = get_permalink( $reto['post'] );
		$imagen = self::imagen( $reto, 'large' );
		$datos  = array(
			/* translators: %d: number of photos. */
			sprintf( _n( '%d photo submitted', '%d photos submitted', $fotos, 'photo-challenges' ), $fotos ),
		);
		if ( 'votacion' === $reto['status'] && Concursos_Votos::por_puestos( $s ) ) {
			$datos[] = 3 === (int) $s['votos_por_votante'] ? __( 'choose your 1st, 2nd and 3rd', 'photo-challenges' ) : __( 'choose your 1st and 2nd', 'photo-challenges' );
		}

		Concursos_Diseno::aplicar( $s );
		?>
		<section class="pc-destacado concursos-destacado" aria-labelledby="pc-destacado-titulo"<?php echo Concursos_Diseno::atributos( $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado. ?>>
			<a class="pc-destacado__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
				<?php if ( $imagen ) : ?>
					<img src="<?php echo esc_url( $imagen['url'] ); ?>" width="<?php echo esc_attr( $imagen['ancho'] ); ?>" height="<?php echo esc_attr( $imagen['alto'] ); ?>" alt="">
				<?php else : ?>
					<span class="pc-media-vacia"><?php echo Concursos_Vista::icono( 'camara', 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fijo. ?></span>
				<?php endif; ?>
			</a>
			<div class="pc-destacado__texto">
				<?php echo Concursos_Vista::chip( $reto['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
				<div>
					<h2 class="pc-destacado__titulo" id="pc-destacado-titulo"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( get_the_title( $reto['post'] ) ); ?></a></h2>
					<?php if ( $texto ) : ?>
						<p class="pc-texto-suave"><?php echo esc_html( $texto ); ?></p>
					<?php endif; ?>
				</div>
				<?php echo Concursos_Vista::calendario( $reto['status'], $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
				<p class="pc-datos concursos-destacado__datos"><?php echo esc_html( implode( ' · ', $datos ) ); ?></p>
				<p class="pc-acciones">
					<?php
					echo Concursos_Vista::boton( $url, self::accion( $reto['status'] ), 'principal', 'flecha' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
					if ( Concursos_Reto::rules_list( $s['reglas'] ) || array_filter( $s['premios'] ) ) {
						echo Concursos_Vista::boton( $url . '#reglas', __( 'Rules and prizes', 'photo-challenges' ), 'secundario' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
					}
					?>
				</p>
			</div>
		</section>
		<?php
	}

	/**
	 * Sección con tarjetas horizontales de retos en curso.
	 *
	 * @param string  $id     Id del título.
	 * @param string  $titulo Título.
	 * @param array[] $retos  Retos.
	 */
	private static function seccion_tarjetas( $id, $titulo, array $retos ) {
		echo '<section class="pc-seccion" aria-labelledby="' . esc_attr( $id ) . '">';
		/* translators: %d: number of challenges. */
		echo Concursos_Vista::cabecera_seccion( $id, $titulo, esc_html( sprintf( _n( '%d challenge', '%d challenges', count( $retos ), 'photo-challenges' ), count( $retos ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
		echo '<div class="pc-lista">';
		foreach ( $retos as $reto ) {
			$imagen = self::imagen( $reto, 'large' );
			$texto  = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $reto['post']->post_content ) ), 28 );
			$fotos  = count( Concursos_Entrada::find( array( 'reto' => $reto['post']->ID ) ) );
			?>
			<a class="pc-tarjeta" href="<?php echo esc_url( get_permalink( $reto['post'] ) ); ?>"<?php echo Concursos_Diseno::atributos( $reto['settings'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado. ?>>
				<span class="pc-tarjeta__media">
					<?php if ( $imagen ) : ?>
						<img src="<?php echo esc_url( $imagen['url'] ); ?>" width="<?php echo esc_attr( $imagen['ancho'] ); ?>" height="<?php echo esc_attr( $imagen['alto'] ); ?>" alt="" loading="lazy">
					<?php else : ?>
						<span class="pc-media-vacia"><?php echo Concursos_Vista::icono( 'camara', 40 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fijo. ?></span>
					<?php endif; ?>
				</span>
				<span class="pc-tarjeta__texto">
					<?php echo Concursos_Vista::chip( $reto['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
					<span class="pc-tarjeta__titulo"><?php echo esc_html( get_the_title( $reto['post'] ) ); ?></span>
					<?php if ( $texto ) : ?>
						<span class="pc-texto-suave"><?php echo esc_html( $texto ); ?></span>
					<?php endif; ?>
					<span class="pc-datos">
						<?php
						/* translators: %d: number of photos. */
						echo esc_html( Concursos_Galeria::frase_estado( $reto['status'], $reto['settings'] ) . ' · ' . sprintf( _n( '%d photo submitted', '%d photos submitted', $fotos, 'photo-challenges' ), $fotos ) );
						?>
					</span>
					<span class="pc-boton pc-boton--principal"><?php echo esc_html( self::accion( $reto['status'] ) ); ?><?php echo Concursos_Vista::icono( 'flecha' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fijo. ?></span>
				</span>
			</a>
			<?php
		}
		echo '</div></section>';
	}

	/**
	 * Retos próximos, con su fecha de inicio destacada.
	 *
	 * @param array[] $retos Retos, del primero en empezar al último.
	 */
	private static function seccion_proximos( array $retos ) {
		echo '<section class="pc-seccion" aria-labelledby="pc-proximos">';
		/* translators: %d: number of challenges. */
		echo Concursos_Vista::cabecera_seccion( 'pc-proximos', __( 'Coming soon', 'photo-challenges' ), esc_html( sprintf( _n( '%d challenge', '%d challenges', count( $retos ), 'photo-challenges' ), count( $retos ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
		echo '<ul class="pc-proximos">';
		foreach ( $retos as $reto ) {
			$inicio = (int) $reto['settings']['inicio'];
			$texto  = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $reto['post']->post_content ) ), 24 );
			?>
			<li class="pc-proximo">
				<span class="pc-proximo__fecha" aria-hidden="true"><span class="pc-proximo__dia"><?php echo esc_html( wp_date( 'd', $inicio ) ); ?></span><span class="pc-etiqueta"><?php echo esc_html( wp_date( 'M', $inicio ) ); ?></span></span>
				<span class="pc-proximo__texto">
					<a class="pc-proximo__titulo" href="<?php echo esc_url( get_permalink( $reto['post'] ) ); ?>"><?php echo esc_html( get_the_title( $reto['post'] ) ); ?></a>
					<?php if ( $texto ) : ?>
						<span class="pc-texto-suave"><?php echo esc_html( $texto ); ?></span>
					<?php endif; ?>
				</span>
				<span class="pc-chip pc-chip--proximo">
					<?php
					/* translators: %s: short date. */
					echo esc_html( sprintf( __( 'Opens %s', 'photo-challenges' ), Concursos_Vista::dia_mes( $inicio ) ) );
					?>
				</span>
			</li>
			<?php
		}
		echo '</ul></section>';
	}

	/**
	 * Retos terminados, con su foto ganadora.
	 *
	 * @param array[] $retos Retos.
	 */
	private static function seccion_terminados( array $retos ) {
		echo '<section class="pc-seccion" aria-labelledby="pc-terminados">';
		echo Concursos_Vista::cabecera_seccion( 'pc-terminados', __( 'Past challenges', 'photo-challenges' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
		echo '<div class="pc-rejilla">';
		foreach ( $retos as $reto ) {
			$ganadora = Concursos_Vista::ganadora( $reto['post']->ID, $reto['settings'] );
			$autor    = $ganadora ? Concursos_Entrada::owner( $ganadora->ID ) : 0;
			$src      = $ganadora ? wp_get_attachment_image_src( get_post_thumbnail_id( $ganadora ), 'medium_large' ) : false;
			$imagen   = $src ? array( 'url' => $src[0], 'ancho' => (int) $src[1], 'alto' => (int) $src[2] ) : self::imagen( $reto, 'medium_large' );
			?>
			<a class="pc-terminado" href="<?php echo esc_url( get_permalink( $reto['post'] ) ); ?>">
				<span class="pc-terminado__media">
					<?php if ( $imagen ) : ?>
						<img src="<?php echo esc_url( $imagen['url'] ); ?>" width="<?php echo esc_attr( $imagen['ancho'] ); ?>" height="<?php echo esc_attr( $imagen['alto'] ); ?>" alt="" loading="lazy">
					<?php else : ?>
						<span class="pc-media-vacia"><?php echo Concursos_Vista::icono( 'camara', 40 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fijo. ?></span>
					<?php endif; ?>
					<?php if ( $ganadora ) : ?>
						<span class="pc-sello"><?php echo Concursos_Vista::icono( 'medalla', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fijo. ?><?php esc_html_e( 'Winning photo', 'photo-challenges' ); ?></span>
					<?php endif; ?>
				</span>
				<span class="pc-etiqueta">
					<?php
					/* translators: %s: date. */
					echo esc_html( sprintf( __( 'Finished · %s', 'photo-challenges' ), Concursos_Reto::format_dia( $reto['settings']['cierre'] ) ) );
					?>
				</span>
				<span class="pc-terminado__titulo"><?php echo esc_html( get_the_title( $reto['post'] ) ); ?></span>
				<?php if ( $ganadora ) : ?>
					<span class="pc-texto-suave">
						<?php
						/* translators: 1: photo title, 2: author name. */
						echo esc_html( sprintf( __( '“%1$s” by %2$s', 'photo-challenges' ), get_the_title( $ganadora ), $autor ? get_the_title( $autor ) : '' ) );
						?>
					</span>
				<?php endif; ?>
			</a>
			<?php
		}
		echo '</div></section>';
	}

	/**
	 * Imagen de un reto: la de fondo o, si no tiene, su primera foto ya visible.
	 *
	 * @param array  $reto Reto.
	 * @param string $size Tamaño.
	 * @return array|null url, ancho y alto.
	 */
	public static function imagen( array $reto, $size ) {
		$id = get_post_thumbnail_id( $reto['post'] );
		if ( ! $id && Concursos_Reto::photos_visible( $reto['settings'] ) ) {
			$fotos = Concursos_Entrada::find( array( 'reto' => $reto['post']->ID ) );
			$id    = $fotos ? get_post_thumbnail_id( $fotos[0] ) : 0;
		}
		$src = $id ? wp_get_attachment_image_src( $id, $size ) : false;

		return $src
			? array(
				'url'   => $src[0],
				'ancho' => (int) $src[1],
				'alto'  => (int) $src[2],
			)
			: null;
	}
}
