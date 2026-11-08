<?php
/**
 * Diseño de cada reto: paleta de color, tipografía y tamaño de texto.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Opciones preconfiguradas de diseño y su traducción a CSS.
 *
 * Con la opción "tema" no se define nada y el reto hereda el aspecto del sitio.
 * Las fuentes van incluidas en el plugin: no se cargan de servicios externos.
 */
class Concursos_Diseno {

	const TEMA   = 'tema';
	const NORMAL = 'normal';

	/**
	 * Paletas. Cada una combina un color principal con un acento armónico y
	 * fondos claros, con contraste AA en todas las parejas de texto y fondo.
	 *
	 * @return array[]
	 */
	public static function paletas() {
		return array(
			'bosque'    => array(
				'nombre'       => __( 'Bosque', 'concursos' ),
				'color'        => '#1F5C4A',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#F6F8F4',
				'suave'        => '#E3EDE6',
				'texto'        => '#1C2B26',
				'acento'       => '#9A5A0B',
				'sobre_acento' => '#FFFFFF',
			),
			'oceano'    => array(
				'nombre'       => __( 'Océano', 'concursos' ),
				'color'        => '#1B4F8F',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#F4F7FB',
				'suave'        => '#DFE9F5',
				'texto'        => '#17263A',
				'acento'       => '#B83C0B',
				'sobre_acento' => '#FFFFFF',
			),
			'ciruela'   => array(
				'nombre'       => __( 'Ciruela', 'concursos' ),
				'color'        => '#6B2D6E',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#FAF6FA',
				'suave'        => '#EFE3EF',
				'texto'        => '#2C1B2D',
				'acento'       => '#0F6E66',
				'sobre_acento' => '#FFFFFF',
			),
			'terracota' => array(
				'nombre'       => __( 'Terracota', 'concursos' ),
				'color'        => '#A23F27',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#FBF6F2',
				'suave'        => '#F3E4DA',
				'texto'        => '#33211A',
				'acento'       => '#1F6670',
				'sobre_acento' => '#FFFFFF',
			),
			'grafito'   => array(
				'nombre'       => __( 'Grafito', 'concursos' ),
				'color'        => '#2F3542',
				'sobre'        => '#FFFFFF',
				'fondo'        => '#F6F6F7',
				'suave'        => '#E6E7EA',
				'texto'        => '#1E2229',
				'acento'       => '#B5175A',
				'sobre_acento' => '#FFFFFF',
			),
		);
	}

	/**
	 * Combinaciones de títulos y texto.
	 *
	 * @return array[]
	 */
	public static function tipografias() {
		return array(
			'editorial' => array(
				'nombre'  => __( 'Editorial', 'concursos' ),
				'titulos' => array( 'Playfair Display', 'serif', 'playfair-display', '400 900' ),
				'texto'   => array( 'Source Sans 3', 'sans-serif', 'source-sans-3', '200 900' ),
			),
			'moderna'   => array(
				'nombre'  => __( 'Moderna', 'concursos' ),
				'titulos' => array( 'Space Grotesk', 'sans-serif', 'space-grotesk', '300 700' ),
				'texto'   => array( 'Inter', 'sans-serif', 'inter', '100 900' ),
			),
			'calida'    => array(
				'nombre'  => __( 'Cálida', 'concursos' ),
				'titulos' => array( 'Fraunces', 'serif', 'fraunces', '100 900' ),
				'texto'   => array( 'Nunito Sans', 'sans-serif', 'nunito-sans', '200 1000' ),
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
	 * @return array paleta, tipografia, tamano.
	 */
	public static function sanitize( array $raw ) {
		$paleta     = isset( $raw['paleta'] ) ? sanitize_key( $raw['paleta'] ) : '';
		$tipografia = isset( $raw['tipografia'] ) ? sanitize_key( $raw['tipografia'] ) : '';
		$tamano     = isset( $raw['tamano'] ) ? sanitize_key( $raw['tamano'] ) : '';

		return array(
			'paleta'     => isset( self::paletas()[ $paleta ] ) ? $paleta : self::TEMA,
			'tipografia' => isset( self::tipografias()[ $tipografia ] ) ? $tipografia : self::TEMA,
			'tamano'     => isset( self::tamanos()[ $tamano ] ) ? $tamano : self::NORMAL,
		);
	}

	/**
	 * Nombre de una familia con su genérica de respaldo.
	 *
	 * @param array $fuente Fuente.
	 * @return string
	 */
	public static function familia( array $fuente ) {
		return '"' . $fuente[0] . '", ' . $fuente[1];
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

		$tipografias = self::tipografias();
		if ( isset( $tipografias[ $s['tipografia'] ] ) ) {
			$t                          = $tipografias[ $s['tipografia'] ];
			$vars['--c-fuente-titulos'] = self::familia( $t['titulos'] );
			$vars['--c-fuente-texto']   = self::familia( $t['texto'] );
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
	 * Reglas @font-face de una combinación.
	 *
	 * @param string $tipografia Clave de la combinación.
	 * @return string
	 */
	public static function css_fuentes( $tipografia ) {
		$tipografias = self::tipografias();
		if ( ! isset( $tipografias[ $tipografia ] ) ) {
			return '';
		}

		$css = '';
		foreach ( array( 'titulos', 'texto' ) as $parte ) {
			$f    = $tipografias[ $tipografia ][ $parte ];
			$css .= sprintf(
				'@font-face{font-family:"%1$s";src:url("%2$s") format("woff2");font-weight:%3$s;font-style:normal;font-display:swap;}',
				$f[0],
				esc_url_raw( CONCURSOS_URL . 'assets/fonts/' . $f[2] . '.woff2' ),
				$f[3]
			);
		}

		return $css;
	}

	/**
	 * Prepara la hoja de estilos pública con las fuentes que usa un reto.
	 *
	 * @param array $s Configuración del reto.
	 */
	public static function aplicar( array $s ) {
		static $cargadas = array();

		wp_enqueue_style( 'concursos-public', CONCURSOS_URL . 'assets/css/public.css', array(), CONCURSOS_VERSION );

		$css = self::css_fuentes( $s['tipografia'] );
		if ( $css && empty( $cargadas[ $s['tipografia'] ] ) ) {
			wp_add_inline_style( 'concursos-public', $css );
			$cargadas[ $s['tipografia'] ] = true;
		}
	}
}
