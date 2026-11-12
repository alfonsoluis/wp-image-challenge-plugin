<?php
/**
 * Diseño de cada reto: estilo, color de acento y tamaño de texto.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Opciones de diseño y su traducción a los tokens CSS del sistema aislado.
 *
 * Un estilo solo da valores a las variables --pc-* de assets/css/public.css,
 * así que añadir uno nuevo no cambia el HTML. La tipografía es siempre la del tema.
 */
class Concursos_Diseno {

	const TEMA      = 'tema';
	const EDITORIAL = 'editorial';
	const NORMAL    = 'normal';

	/** Páginas que pueden llevar el fondo de iconos. */
	const PAGINAS_FONDO = array( 'portada', 'detalle', 'inscripcion' );

	/**
	 * Estilos disponibles.
	 *
	 * @return string[] clave => nombre.
	 */
	public static function estilos() {
		return array(
			self::TEMA      => __( 'Site theme: your theme colors', 'photo-challenges' ),
			self::EDITORIAL => __( 'Editorial: warm paper background and dark ink', 'photo-challenges' ),
		);
	}

	/**
	 * Colores de acento. Todos cumplen contraste AA como texto sobre blanco y
	 * sobre el fondo editorial, y con texto blanco encima. La clave vacía usa el
	 * color de contraste del tema.
	 *
	 * @return array[] clave => nombre y color.
	 */
	public static function acentos() {
		return array(
			''          => array(
				'nombre' => __( 'Theme color', 'photo-challenges' ),
				'color'  => '',
			),
			'teja'      => array(
				'nombre' => __( 'Terracotta', 'photo-challenges' ),
				'color'  => '#C2410C',
			),
			'azul'      => array(
				'nombre' => __( 'Blue', 'photo-challenges' ),
				'color'  => '#1D4ED8',
			),
			'verde'     => array(
				'nombre' => __( 'Green', 'photo-challenges' ),
				'color'  => '#047857',
			),
			'violeta'   => array(
				'nombre' => __( 'Violet', 'photo-challenges' ),
				'color'  => '#6D28D9',
			),
			'frambuesa' => array(
				'nombre' => __( 'Raspberry', 'photo-challenges' ),
				'color'  => '#BE185D',
			),
			'tinta'     => array(
				'nombre' => __( 'Ink', 'photo-challenges' ),
				'color'  => '#17161A',
			),
		);
	}

	/**
	 * Equivalencia de las paletas de la versión 1.0 con estilo y acento.
	 *
	 * @param string $paleta Paleta antigua.
	 * @return array estilo, acento.
	 */
	public static function desde_paleta( $paleta ) {
		$mapa = array(
			'playa'      => 'verde',
			'brisa'      => 'azul',
			'otono'      => 'teja',
			'oro'        => 'tinta',
			'intensos'   => 'violeta',
			'baya'       => 'frambuesa',
			'naturaleza' => 'verde',
			'azul'       => 'azul',
			'tierra'     => 'teja',
			'naranja'    => 'tinta',
		);

		return isset( $mapa[ $paleta ] )
			? array( self::EDITORIAL, $mapa[ $paleta ] )
			: array( self::TEMA, '' );
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
	 * @return array estilo, acento, tamano, fondo_iconos.
	 */
	public static function sanitize( array $raw ) {
		$estilo = isset( $raw['estilo'] ) ? sanitize_key( $raw['estilo'] ) : '';
		$acento = isset( $raw['acento'] ) ? sanitize_key( $raw['acento'] ) : '';
		$tamano = isset( $raw['tamano'] ) ? sanitize_key( $raw['tamano'] ) : '';
		$fondo  = isset( $raw['fondo_iconos'] ) ? array_map( 'sanitize_key', (array) $raw['fondo_iconos'] ) : array();

		return array(
			'estilo'       => isset( self::estilos()[ $estilo ] ) ? $estilo : self::TEMA,
			'acento'       => isset( self::acentos()[ $acento ] ) ? $acento : '',
			'tamano'       => isset( self::tamanos()[ $tamano ] ) ? $tamano : self::NORMAL,
			'fondo_iconos' => array_values( array_intersect( self::PAGINAS_FONDO, $fondo ) ),
		);
	}

	/**
	 * Atributos del contenedor raíz .pc de un reto: estilo y variables.
	 *
	 * @param array|null $s Configuración del reto, o null para los valores por defecto.
	 * @return string Atributos ya escapados, con un espacio delante.
	 */
	public static function atributos( $s ) {
		$estilo = $s && isset( self::estilos()[ $s['estilo'] ] ) ? $s['estilo'] : self::TEMA;
		$vars   = '';

		$acentos = self::acentos();
		if ( $s && ! empty( $s['acento'] ) && isset( $acentos[ $s['acento'] ] ) ) {
			$vars .= '--pc-acento:' . $acentos[ $s['acento'] ]['color'] . ';';
		}
		$tamanos = self::tamanos();
		if ( $s && self::NORMAL !== $s['tamano'] && isset( $tamanos[ $s['tamano'] ] ) ) {
			$vars .= '--pc-escala:' . $tamanos[ $s['tamano'] ]['escala'] . ';';
		}

		return ' data-pc-estilo="' . esc_attr( $estilo ) . '"' . ( $vars ? ' style="' . esc_attr( $vars ) . '"' : '' );
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
