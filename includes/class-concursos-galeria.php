<?php
/**
 * Galería de fotos en la página pública de un reto.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Añade las fotos enviadas al contenido de la página de un reto.
 */
class Concursos_Galeria {

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'append' ), 20 );
	}

	/**
	 * Añade la galería tras la descripción del reto que se está viendo.
	 *
	 * @param string $content Contenido.
	 * @return string
	 */
	public static function append( $content ) {
		static $running = false;

		$id = get_the_ID();
		if ( $running || ! is_singular( Concursos_Post_Types::RETO ) || ! $id || get_queried_object_id() !== $id ) {
			return $content;
		}

		$running = true;
		$html    = self::render( $id );
		$running = false;

		return $content . $html;
	}

	/**
	 * Galería de un reto, o el motivo por el que aún no se ve.
	 *
	 * @param int $reto_id Reto.
	 * @return string
	 */
	public static function render( $reto_id ) {
		wp_enqueue_style( 'concursos-public', CONCURSOS_URL . 'assets/css/public.css', array(), CONCURSOS_VERSION );

		$s        = Concursos_Reto::get_settings( $reto_id );
		$entradas = Concursos_Entrada::find( array( 'reto' => $reto_id ) );

		ob_start();
		echo '<section class="concursos-galeria">';
		echo '<h2>' . esc_html__( 'Fotos participantes', 'concursos' ) . '</h2>';

		if ( ! Concursos_Reto::photos_visible( $s ) ) {
			if ( 'envios' === Concursos_Reto::status( $s ) ) {
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
		} elseif ( ! $entradas ) {
			echo '<p>' . esc_html__( 'Todavía no hay fotos.', 'concursos' ) . '</p>';
		} else {
			echo '<ul class="concursos-galeria__rejilla">';
			foreach ( $entradas as $entrada ) {
				$autor = Concursos_Entrada::owner( $entrada->ID );
				printf(
					'<li><figure>%s<figcaption><strong>%s</strong><span>%s</span></figcaption></figure></li>',
					get_the_post_thumbnail( $entrada, 'medium_large', array( 'loading' => 'lazy' ) ),
					esc_html( get_the_title( $entrada ) ),
					esc_html( $autor ? get_the_title( $autor ) : '' )
				);
			}
			echo '</ul>';
		}

		echo '</section>';

		return ob_get_clean();
	}
}
