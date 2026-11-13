<?php
/**
 * Piezas comunes de las páginas públicas.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Componentes HTML del sistema de diseño (clases pc-*), ya escapados.
 */
class Concursos_Vista {

	/**
	 * Icono SVG de trazo, del color del texto.
	 *
	 * @param string $nombre flecha, subir, medalla o camara.
	 * @param int    $tam    Tamaño en píxeles.
	 * @return string
	 */
	public static function icono( $nombre, $tam = 18 ) {
		$trazos = array(
			'flecha'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'subir'   => '<path d="M12 16V4M7 9l5-5 5 5M4 20h16"/>',
			'medalla' => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14 7 22l5-3 5 3-1.5-8"/>',
			'camara'  => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
		);

		return sprintf(
			'<svg class="pc-icono" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			(int) $tam,
			isset( $trazos[ $nombre ] ) ? $trazos[ $nombre ] : ''
		);
	}

	/**
	 * Botón o enlace con forma de botón.
	 *
	 * @param string $url     Destino.
	 * @param string $texto   Texto.
	 * @param string $variante principal o secundario.
	 * @param string $icono   Icono tras el texto (o delante si es "subir").
	 * @param string $clase   Clases adicionales.
	 * @return string
	 */
	public static function boton( $url, $texto, $variante = 'principal', $icono = '', $clase = '' ) {
		$svg = $icono ? self::icono( $icono ) : '';

		return sprintf(
			'<a class="%1$s" href="%2$s">%3$s%4$s%5$s</a>',
			esc_attr( trim( 'pc-boton pc-boton--' . $variante . ' ' . $clase ) ),
			esc_url( $url ),
			'subir' === $icono ? $svg : '',
			esc_html( $texto ),
			'subir' !== $icono ? $svg : ''
		);
	}

	/**
	 * Chip con la fase del reto.
	 *
	 * @param string $status Fase.
	 * @return string
	 */
	public static function chip( $status ) {
		$labels = Concursos_Reto::status_labels();

		return sprintf(
			'<span class="pc-chip pc-chip--%1$s concursos-etiqueta concursos-etiqueta--%1$s"><span class="pc-chip__punto" aria-hidden="true"></span>%2$s</span>',
			esc_attr( $status ),
			esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : $status )
		);
	}

	/**
	 * Fecha corta: día y mes.
	 *
	 * @param int $ts Fecha.
	 * @return string
	 */
	public static function dia_mes( $ts ) {
		/* translators: short date format with day and month, see https://www.php.net/manual/datetime.format.php */
		return $ts ? wp_date( _x( 'j M', 'short date', 'foncholuis-art-challenge-manager' ), (int) $ts ) : '—';
	}

	/**
	 * Intervalo de fechas corto.
	 *
	 * @param int $desde Inicio.
	 * @param int $hasta Fin.
	 * @return string
	 */
	public static function intervalo( $desde, $hasta ) {
		return self::dia_mes( $desde ) . ' – ' . self::dia_mes( $hasta );
	}

	/**
	 * Calendario de tres tramos: envíos, votación y resultados.
	 *
	 * @param string $status Fase.
	 * @param array  $s      Configuración.
	 * @param bool   $fechas Si se muestran las fechas bajo cada tramo.
	 * @return string
	 */
	public static function calendario( $status, array $s, $fechas = true ) {
		$tramos = array(
			'envios'     => array( __( 'Submissions', 'foncholuis-art-challenge-manager' ), self::intervalo( $s['inicio'], $s['fin_envios'] ) ),
			'votacion'   => array( _x( 'Voting', 'calendar phase', 'foncholuis-art-challenge-manager' ), self::intervalo( $s['fin_envios'], $s['fin_votacion'] ) ),
			'finalizado' => array( __( 'Results', 'foncholuis-art-challenge-manager' ), self::dia_mes( $s['cierre'] ) ),
		);
		$orden  = array( 'proximo' => 0, 'envios' => 1, 'votacion' => 2, 'evaluacion' => 3, 'finalizado' => 4 );
		$actual = isset( $orden[ $status ] ) ? $orden[ $status ] : 0;
		$pasos  = array( 'envios' => 1, 'votacion' => 2, 'finalizado' => 4 );

		$html = '<ol class="pc-calendario">';
		foreach ( $tramos as $clave => $tramo ) {
			$paso   = $pasos[ $clave ];
			$estado = $paso < $actual || ( 'finalizado' === $clave && 4 === $actual ) ? 'hecho' : ( $paso === $actual ? 'ahora' : 'luego' );
			$html  .= sprintf(
				'<li class="pc-calendario__tramo pc-calendario__tramo--%1$s"%2$s><span class="pc-calendario__barra" aria-hidden="true"></span><span class="pc-etiqueta">%3$s</span>%4$s</li>',
				esc_attr( $estado ),
				'ahora' === $estado ? ' aria-current="step"' : '',
				esc_html( 'ahora' === $estado && 'finalizado' !== $clave ? sprintf( /* translators: %s: phase name. */ __( '%s · now', 'foncholuis-art-challenge-manager' ), $tramo[0] ) : $tramo[0] ),
				$fechas ? '<span class="pc-calendario__fecha">' . esc_html( $tramo[1] ) . '</span>' : ''
			);
		}

		return $html . '</ol>';
	}

	/**
	 * Fecha de la próxima etapa y qué ocurre en ella.
	 *
	 * @param string $status Fase.
	 * @param array  $s      Configuración.
	 * @return array|null Fecha y frase, o null si no queda ninguna.
	 */
	private static function proxima( $status, array $s ) {
		$frases = array(
			/* translators: %s: date. */
			'proximo'    => __( 'until the challenge opens · on %s', 'foncholuis-art-challenge-manager' ),
			/* translators: %s: date. */
			'envios'     => __( 'to submit your photo · closes on %s', 'foncholuis-art-challenge-manager' ),
			/* translators: %s: date. */
			'votacion'   => __( 'to vote · voting ends on %s', 'foncholuis-art-challenge-manager' ),
			/* translators: %s: date. */
			'evaluacion' => __( 'until the results · published on %s', 'foncholuis-art-challenge-manager' ),
		);
		$etapa  = Concursos_Reto::proxima_etapa( $status, $s );

		return $etapa ? array( $etapa[1], $frases[ $status ] ) : null;
	}

	/**
	 * Cuenta atrás hasta la próxima etapa.
	 *
	 * @param string $status Fase.
	 * @param array  $s      Configuración.
	 * @return string
	 */
	public static function cuenta( $status, array $s ) {
		$proxima = self::proxima( $status, $s );
		if ( ! $proxima || $proxima[0] <= time() ) {
			return '';
		}

		$falta = $proxima[0] - time();
		$dias  = (int) floor( $falta / DAY_IN_SECONDS );
		$cifra = $dias >= 1
			/* translators: %d: number of days. */
			? sprintf( _n( '%d day', '%d days', $dias, 'foncholuis-art-challenge-manager' ), $dias )
			/* translators: %d: number of hours. */
			: sprintf( _n( '%d hour', '%d hours', max( 1, (int) floor( $falta / HOUR_IN_SECONDS ) ), 'foncholuis-art-challenge-manager' ), max( 1, (int) floor( $falta / HOUR_IN_SECONDS ) ) );

		return sprintf(
			'<div class="pc-cuenta"><p class="pc-cuenta__cifra">%1$s</p><p class="pc-cuenta__texto">%2$s</p></div>',
			esc_html( $cifra ),
			esc_html( sprintf( $proxima[1], Concursos_Reto::format_dia( $proxima[0] ) ) )
		);
	}

	/**
	 * Cabecera de sección: título y dato a la derecha.
	 *
	 * @param string $id     Id del título.
	 * @param string $titulo Título.
	 * @param string $dato   HTML ya escapado a la derecha.
	 * @return string
	 */
	public static function cabecera_seccion( $id, $titulo, $dato = '' ) {
		return sprintf(
			'<div class="pc-seccion__cabecera"><h2 class="pc-seccion__titulo" id="%1$s">%2$s</h2>%3$s</div>',
			esc_attr( $id ),
			esc_html( $titulo ),
			$dato ? '<span class="pc-seccion__dato">' . $dato . '</span>' : ''
		);
	}

	/**
	 * Iniciales de un nombre, para el círculo de la clasificación.
	 *
	 * @param string $nombre Nombre.
	 * @return string
	 */
	public static function iniciales( $nombre ) {
		$partes = preg_split( '/\s+/u', trim( $nombre ) );
		$ini    = '';
		foreach ( array_slice( $partes, 0, 2 ) as $parte ) {
			$ini .= function_exists( 'mb_substr' ) ? mb_substr( $parte, 0, 1 ) : substr( $parte, 0, 1 );
		}

		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $ini ) : strtoupper( $ini );
	}

	/**
	 * Foto ganadora de un reto terminado.
	 *
	 * @param int   $reto_id Reto.
	 * @param array $s       Configuración.
	 * @return WP_Post|null
	 */
	public static function ganadora( $reto_id, array $s ) {
		$entradas = Concursos_Entrada::find( array( 'reto' => $reto_id ) );
		if ( ! $entradas ) {
			return null;
		}
		$ranking = Concursos_Votos::rank( Concursos_Votos::de_fotos( $reto_id, $entradas ), (int) $s['lugares'] );
		if ( empty( $ranking['puestos'][0]['entradas'] ) ) {
			return null;
		}

		return get_post( $ranking['puestos'][0]['entradas'][0] );
	}
}
