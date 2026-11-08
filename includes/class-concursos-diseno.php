<?php
/**
 * Diseño de cada reto: paleta de color y tamaño de texto.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Opciones preconfiguradas de diseño y su traducción a CSS.
 *
 * Con la opción "tema" no se define nada y el reto hereda los colores del sitio.
 * La tipografía es siempre la del tema.
 */
class Concursos_Diseno {

	const TEMA   = 'tema';
	const NORMAL = 'normal';

	/**
	 * Paletas. El color se concentra en la cabecera, los botones y los acentos;
	 * el fondo se mantiene casi neutro para no competir con las fotos. Todas las
	 * parejas de texto y fondo cumplen contraste AA.
	 *
	 * @return array[]
	 */
	public static function paletas() {
		return array(
			'galeria'   => array(
				'nombre'       => __( 'Galería', 'concursos' ),
				'color'        => '#18181B',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#FAFAFA',
				'suave'        => '#EDEDEF',
				'texto'        => '#18181B',
				'acento'       => '#1D4ED8',
				'sobre_acento' => '#FFFFFF',
			),
			'oceano'    => array(
				'nombre'       => __( 'Océano', 'concursos' ),
				'color'        => '#0B5CAD',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#F7FAFD',
				'suave'        => '#E1ECF8',
				'texto'        => '#0F2540',
				'acento'       => '#B93A0A',
				'sobre_acento' => '#FFFFFF',
			),
			'esmeralda' => array(
				'nombre'       => __( 'Esmeralda', 'concursos' ),
				'color'        => '#04704F',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#F6FBF9',
				'suave'        => '#DDF1E8',
				'texto'        => '#0B2E22',
				'acento'       => '#A34A06',
				'sobre_acento' => '#FFFFFF',
			),
			'fucsia'    => array(
				'nombre'       => __( 'Fucsia', 'concursos' ),
				'color'        => '#B0155A',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#FEF8FB',
				'suave'        => '#FBE4EE',
				'texto'        => '#3B0A22',
				'acento'       => '#4338CA',
				'sobre_acento' => '#FFFFFF',
			),
			'coral'     => array(
				'nombre'       => __( 'Coral', 'concursos' ),
				'color'        => '#B23A0A',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#FEF9F5',
				'suave'        => '#FCE8DC',
				'texto'        => '#3A1A0C',
				'acento'       => '#0B6B64',
				'sobre_acento' => '#FFFFFF',
			),
		);
	}

	/**
	 * Tamaños de texto, como multiplicador del tamaño del tema.
	 *
	 * @return array[]
	 */
	public static function tamanos() {
		return array(
			'pequeno'    => array(
				'nombre' => __( 'Más pequeño', 'concursos' ),
				'escala' => 0.9,
			),
			self::NORMAL => array(
				'nombre' => __( 'Estándar del tema', 'concursos' ),
				'escala' => 1,
			),
			'grande'     => array(
				'nombre' => __( 'Más grande', 'concursos' ),
				'escala' => 1.125,
			),
		);
	}

	/**
	 * Valida las opciones de diseño recibidas.
	 *
	 * @param array $raw Datos sin sanear.
	 * @return array paleta, tamano.
	 */
	public static function sanitize( array $raw ) {
		$paleta = isset( $raw['paleta'] ) ? sanitize_key( $raw['paleta'] ) : '';
		$tamano = isset( $raw['tamano'] ) ? sanitize_key( $raw['tamano'] ) : '';

		return array(
			'paleta' => isset( self::paletas()[ $paleta ] ) ? $paleta : self::TEMA,
			'tamano' => isset( self::tamanos()[ $tamano ] ) ? $tamano : self::NORMAL,
		);
	}

	/**
	 * Variables CSS de un reto, listas para un atributo style.
	 *
	 * @param array $s Configuración del reto.
	 * @return string
	 */
	public static function estilo( array $s ) {
		$vars = array();

		$paletas = self::paletas();
		if ( isset( $paletas[ $s['paleta'] ] ) ) {
			$p    = $paletas[ $s['paleta'] ];
			$vars = array(
				'--c-color'          => $p['color'],
				'--c-sobre'          => $p['sobre'],
				'--c-fondo'          => $p['fondo'],
				'--c-suave'          => $p['suave'],
				'--c-texto'          => $p['texto'],
				'--c-acento'         => $p['acento'],
				'--c-sobre-acento'   => $p['sobre_acento'],
				'--c-cabecera'       => $p['color'],
				'--c-sobre-cabecera' => $p['sobre'],
			);
		}

		$tamanos = self::tamanos();
		if ( self::NORMAL !== $s['tamano'] && isset( $tamanos[ $s['tamano'] ] ) ) {
			$vars['--c-escala'] = $tamanos[ $s['tamano'] ]['escala'];
		}

		$css = '';
		foreach ( $vars as $nombre => $valor ) {
			$css .= $nombre . ':' . $valor . ';';
		}

		return $css;
	}

	/**
	 * Carga la hoja de estilos pública.
	 *
	 * @param array $s Configuración del reto.
	 */
	public static function aplicar( array $s ) {
		wp_enqueue_style( 'concursos-public', CONCURSOS_URL . 'assets/css/public.css', array(), CONCURSOS_VERSION );
	}
}
