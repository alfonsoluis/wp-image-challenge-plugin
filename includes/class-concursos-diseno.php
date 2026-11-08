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
	 * Paletas, tomadas de las más populares de coolors.co. El color principal se
	 * usa en títulos, enlaces y botones, y el acento solo como relleno, porque
	 * muchos acentos son demasiado claros para usarlos como texto. Los fondos
	 * son el tono claro de cada paleta o un tinte suyo. Todas las parejas de
	 * texto y fondo cumplen contraste AA.
	 *
	 * @return array[]
	 */
	public static function paletas() {
		return array(
			// Sunny Beach Day (coolors.co).
			'playa' => array(
				'nombre'       => __( 'Beach day', 'concursos' ),
				'color'        => '#264653',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#FDF9EF',
				'suave'        => '#F9EED5',
				'texto'        => '#264653',
				'acento'       => '#E9C46A',
				'sobre_acento' => '#264653',
			),
			// Summer Ocean Breeze (coolors.co).
			'brisa' => array(
				'nombre'       => __( 'Ocean breeze', 'concursos' ),
				'color'        => '#1D3557',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#FAFDF9',
				'suave'        => '#F1FAEE',
				'texto'        => '#1D3557',
				'acento'       => '#A8DADC',
				'sobre_acento' => '#1D3557',
			),
			// Warm Autumn Glow (coolors.co).
			'otono' => array(
				'nombre'       => __( 'Warm autumn', 'concursos' ),
				'color'        => '#D62828',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#FDFCF8',
				'suave'        => '#FAF8ED',
				'texto'        => '#003049',
				'acento'       => '#FCBF49',
				'sobre_acento' => '#003049',
			),
			// Black & Gold Elegance (coolors.co).
			'oro' => array(
				'nombre'       => __( 'Black and gold', 'concursos' ),
				'color'        => '#000000',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#F5F5F5',
				'suave'        => '#E5E5E5',
				'texto'        => '#14213D',
				'acento'       => '#FCA311',
				'sobre_acento' => '#14213D',
			),
			// Bold Hues (coolors.co).
			'intensos' => array(
				'nombre'       => __( 'Bold hues', 'concursos' ),
				'color'        => '#7209B7',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#F3FBFE',
				'suave'        => '#DFF5FC',
				'texto'        => '#3A0CA3',
				'acento'       => '#4CC9F0',
				'sobre_acento' => '#3A0CA3',
			),
			// Bold Berry (coolors.co).
			'baya' => array(
				'nombre'       => __( 'Bold berry', 'concursos' ),
				'color'        => '#A53860',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#FDF1E6',
				'suave'        => '#F9DBBD',
				'texto'        => '#450920',
				'acento'       => '#FFA5AB',
				'sobre_acento' => '#450920',
			),
			// Nature Harmony (coolors.co).
			'naturaleza' => array(
				'nombre'       => __( 'Nature', 'concursos' ),
				'color'        => '#373D20',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#F9FAF8',
				'suave'        => '#EFF1ED',
				'texto'        => '#373D20',
				'acento'       => '#BCBD8B',
				'sobre_acento' => '#373D20',
			),
			// Sky Blue Freshness (coolors.co).
			'azul' => array(
				'nombre'       => __( 'Fresh blue', 'concursos' ),
				'color'        => '#05668D',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#F7FAFD',
				'suave'        => '#EBF2FA',
				'texto'        => '#05668D',
				'acento'       => '#A5BE00',
				'sobre_acento' => '#12303F',
			),
			// Neutral Earth Tones (coolors.co).
			'tierra' => array(
				'nombre'       => __( 'Neutral earth', 'concursos' ),
				'color'        => '#5E503F',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#F7F3EF',
				'suave'        => '#EAE0D5',
				'texto'        => '#22333B',
				'acento'       => '#C6AC8F',
				'sobre_acento' => '#0A0908',
			),
			// Minimalist Elegance (coolors.co).
			'naranja' => array(
				'nombre'       => __( 'Orange elegance', 'concursos' ),
				'color'        => '#2D3142',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#F8F8F8',
				'suave'        => '#EDEDED',
				'texto'        => '#2D3142',
				'acento'       => '#EF8354',
				'sobre_acento' => '#2D3142',
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
				'nombre' => __( 'Smaller', 'concursos' ),
				'escala' => 0.9,
			),
			self::NORMAL => array(
				'nombre' => __( 'Theme default', 'concursos' ),
				'escala' => 1,
			),
			'grande'     => array(
				'nombre' => __( 'Larger', 'concursos' ),
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
