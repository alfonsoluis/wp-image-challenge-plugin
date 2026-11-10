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

	/** Páginas que pueden llevar el fondo de iconos. */
	const PAGINAS_FONDO = array( 'portada', 'detalle', 'inscripcion' );

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
				'nombre'       => __( 'Beach day', 'photo-challenges' ),
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
				'nombre'       => __( 'Ocean breeze', 'photo-challenges' ),
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
				'nombre'       => __( 'Warm autumn', 'photo-challenges' ),
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
				'nombre'       => __( 'Black and gold', 'photo-challenges' ),
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
				'nombre'       => __( 'Bold hues', 'photo-challenges' ),
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
				'nombre'       => __( 'Bold berry', 'photo-challenges' ),
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
				'nombre'       => __( 'Nature', 'photo-challenges' ),
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
				'nombre'       => __( 'Fresh blue', 'photo-challenges' ),
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
				'nombre'       => __( 'Neutral earth', 'photo-challenges' ),
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
				'nombre'       => __( 'Orange elegance', 'photo-challenges' ),
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
				'nombre' => __( 'Smaller', 'photo-challenges' ),
				'escala' => 0.9,
			),
			self::NORMAL => array(
				'nombre' => __( 'Theme default', 'photo-challenges' ),
				'escala' => 1,
			),
			'grande'     => array(
				'nombre' => __( 'Larger', 'photo-challenges' ),
				'escala' => 1.125,
			),
		);
	}

	/**
	 * Nombres de las páginas que pueden llevar el fondo de iconos.
	 *
	 * @return string[] clave => nombre.
	 */
	public static function paginas_fondo() {
		return array(
			'portada'     => __( 'Challenges page', 'photo-challenges' ),
			'detalle'     => __( 'Challenge details', 'photo-challenges' ),
			'inscripcion' => __( 'Registration page', 'photo-challenges' ),
		);
	}

	/**
	 * Indica si un reto activa el fondo de iconos en una página.
	 *
	 * @param array  $s      Configuración del reto.
	 * @param string $pagina Clave de PAGINAS_FONDO.
	 * @return bool
	 */
	public static function muestra_fondo( array $s, $pagina ) {
		return in_array( $pagina, (array) $s['fondo_iconos'], true );
	}

	/**
	 * Valida las opciones de diseño recibidas.
	 *
	 * @param array $raw Datos sin sanear.
	 * @return array paleta, tamano, fondo_iconos.
	 */
	public static function sanitize( array $raw ) {
		$paleta = isset( $raw['paleta'] ) ? sanitize_key( $raw['paleta'] ) : '';
		$tamano = isset( $raw['tamano'] ) ? sanitize_key( $raw['tamano'] ) : '';
		$fondo  = isset( $raw['fondo_iconos'] ) ? array_map( 'sanitize_key', (array) $raw['fondo_iconos'] ) : array();

		return array(
			'paleta'       => isset( self::paletas()[ $paleta ] ) ? $paleta : self::TEMA,
			'tamano'       => isset( self::tamanos()[ $tamano ] ) ? $tamano : self::NORMAL,
			'fondo_iconos' => array_values( array_intersect( self::PAGINAS_FONDO, $fondo ) ),
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
				'--c-tinta'          => $p['color'],
				'--c-opacidad-cabecera' => self::opacidad_legible( $p['sobre'], $p['color'] ),
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
	 * Opacidad para texto atenuado: 70 % o la mínima que mantenga contraste AA.
	 *
	 * @param string $texto Color del texto.
	 * @param string $fondo Color del fondo.
	 * @return float
	 */
	private static function opacidad_legible( $texto, $fondo ) {
		$t = sscanf( $texto, '#%02x%02x%02x' );
		$f = sscanf( $fondo, '#%02x%02x%02x' );

		for ( $o = 70; $o < 100; $o++ ) {
			$mezcla = array();
			foreach ( $t as $i => $canal ) {
				$mezcla[] = $canal * $o / 100 + $f[ $i ] * ( 100 - $o ) / 100;
			}
			$a = self::luminancia( $mezcla );
			$b = self::luminancia( $f );
			if ( ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 ) >= 4.5 ) {
				return $o / 100;
			}
		}

		return 1;
	}

	/**
	 * Luminancia relativa de un color, según WCAG.
	 *
	 * @param float[] $rgb Canales de 0 a 255.
	 * @return float
	 */
	private static function luminancia( array $rgb ) {
		$lineal = array_map(
			function ( $canal ) {
				$c = $canal / 255;

				return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
			},
			$rgb
		);

		return 0.2126 * $lineal[0] + 0.7152 * $lineal[1] + 0.0722 * $lineal[2];
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
