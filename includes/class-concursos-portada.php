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

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'route' ) );
	}

	/**
	 * Atiende el archivo de retos.
	 */
	public static function route() {
		if ( is_post_type_archive( Concursos_Post_Types::RETO ) && ! is_feed() ) {
			Concursos_Plantilla::pagina( __( 'Retos', 'concursos' ), self::render() );
		}
	}

	/**
	 * Contenido de la portada.
	 *
	 * @return string
	 */
	private static function render() {
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

		// Destacado: el reto en curso más reciente o, si no hay, el próximo en empezar.
		$destacado = $en_curso ? array_shift( $en_curso ) : array_pop( $proximos );
		$otros     = array_merge( $en_curso, array_reverse( (array) $proximos ) );

		ob_start();
		echo '<h1 class="concursos-publico__titulo">' . esc_html__( 'Retos', 'concursos' ) . '</h1>';

		if ( ! $destacado && ! $pasados ) {
			echo '<p>' . esc_html__( 'Todavía no hay retos publicados.', 'concursos' ) . '</p>';
		}
		if ( $destacado ) {
			self::destacado( $destacado );
		}
		if ( $otros ) {
			echo '<h2>' . esc_html__( 'Otros retos en curso y próximos', 'concursos' ) . '</h2>';
			self::lista( $otros );
		}
		if ( $pasados ) {
			echo '<h2>' . esc_html__( 'Retos anteriores', 'concursos' ) . '</h2>';
			self::lista( $pasados );
		}

		return ob_get_clean();
	}

	/**
	 * Imagen de un reto: la de fondo o, si no tiene, su primera foto ya visible.
	 *
	 * @param array  $reto Reto.
	 * @param string $size Tamaño.
	 * @return string URL o vacío.
	 */
	private static function imagen( array $reto, $size ) {
		$url = get_the_post_thumbnail_url( $reto['post'], $size );
		if ( $url ) {
			return $url;
		}
		if ( Concursos_Reto::photos_visible( $reto['settings'] ) ) {
			$fotos = Concursos_Entrada::find( array( 'reto' => $reto['post']->ID ) );
			if ( $fotos ) {
				return (string) get_the_post_thumbnail_url( $fotos[0], $size );
			}
		}

		return '';
	}

	/**
	 * Reto destacado.
	 *
	 * @param array $reto Reto.
	 */
	private static function destacado( array $reto ) {
		$labels = Concursos_Reto::status_labels();
		$imagen = self::imagen( $reto, 'full' );
		$texto  = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $reto['post']->post_content ) ), 40 );
		$fotos  = count( Concursos_Entrada::find( array( 'reto' => $reto['post']->ID ) ) );
		$estilo = $imagen ? ' style="background-image:url(' . esc_url( $imagen ) . ')"' : '';
		$accion = 'votacion' === $reto['status'] ? __( 'Ver las fotos y votar', 'concursos' ) : __( 'Ver el reto', 'concursos' );
		?>
		<section class="concursos-destacado"<?php echo $estilo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- URL escapada arriba. ?>>
			<div class="concursos-destacado__contenido">
				<p class="concursos-etiqueta concursos-etiqueta--<?php echo esc_attr( $reto['status'] ); ?>"><?php echo esc_html( $labels[ $reto['status'] ] ); ?></p>
				<h2><a href="<?php echo esc_url( get_permalink( $reto['post'] ) ); ?>"><?php echo esc_html( get_the_title( $reto['post'] ) ); ?></a></h2>
				<?php if ( $texto ) : ?>
					<p><?php echo esc_html( $texto ); ?></p>
				<?php endif; ?>
				<p class="concursos-destacado__datos">
					<?php
					echo esc_html( Concursos_Galeria::frase_estado( $reto['status'], $reto['settings'] ) );
					echo ' ';
					/* translators: %d: número de fotos. */
					echo esc_html( sprintf( _n( '%d foto enviada.', '%d fotos enviadas.', $fotos, 'concursos' ), $fotos ) );
					?>
				</p>
				<p><a class="concursos-boton" href="<?php echo esc_url( get_permalink( $reto['post'] ) ); ?>"><?php echo esc_html( $accion ); ?></a></p>
			</div>
		</section>
		<?php
	}

	/**
	 * Lista compacta de retos.
	 *
	 * @param array[] $retos Retos.
	 */
	private static function lista( array $retos ) {
		$labels = Concursos_Reto::status_labels();

		echo '<ul class="concursos-lista-retos">';
		foreach ( $retos as $reto ) {
			$imagen = self::imagen( $reto, 'medium_large' );
			?>
			<li>
				<a href="<?php echo esc_url( get_permalink( $reto['post'] ) ); ?>">
					<?php if ( $imagen ) : ?>
						<img src="<?php echo esc_url( $imagen ); ?>" alt="" loading="lazy">
					<?php else : ?>
						<span class="concursos-lista-retos__vacia" aria-hidden="true"></span>
					<?php endif; ?>
					<strong><?php echo esc_html( get_the_title( $reto['post'] ) ); ?></strong>
				</a>
				<span class="concursos-etiqueta concursos-etiqueta--<?php echo esc_attr( $reto['status'] ); ?>"><?php echo esc_html( $labels[ $reto['status'] ] ); ?></span>
				<small><?php echo esc_html( Concursos_Galeria::frase_estado( $reto['status'], $reto['settings'] ) ); ?></small>
			</li>
			<?php
		}
		echo '</ul>';
	}
}
