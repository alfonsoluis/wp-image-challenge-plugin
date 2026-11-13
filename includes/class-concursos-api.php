<?php
/**
 * Configuración de los retos en la REST API.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Añade el campo "concursos" a /wp/v2/concurso_reto para leer y cambiar la
 * configuración de un reto con un usuario que pueda editarlo (por ejemplo, con
 * una contraseña de aplicación). Solo se muestra en el contexto "edit", porque
 * incluye las listas de emails de los retos privados.
 */
class Concursos_API {

	const CAMPO = 'concursos';

	/**
	 * Registra los ganchos.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'registrar' ) );
	}

	/**
	 * Registra el campo.
	 */
	public static function registrar() {
		register_rest_field(
			Concursos_Post_Types::RETO,
			self::CAMPO,
			array(
				'get_callback'    => array( __CLASS__, 'leer' ),
				'update_callback' => array( __CLASS__, 'guardar' ),
				'schema'          => array(
					'description' => __( 'Challenge settings. Dates use the site time zone, in the format YYYY-MM-DDTHH:MM.', 'foncholuis-art-challenge-manager' ),
					'type'        => 'object',
					'context'     => array( 'edit' ),
					'properties'  => array(
						'modo'                  => array( 'type' => 'string', 'enum' => array( 'abierto', 'cerrado' ) ),
						'inicio'                => array( 'type' => 'string' ),
						'fin_envios'            => array( 'type' => 'string' ),
						'fin_votacion'          => array( 'type' => 'string' ),
						'cierre'                => array( 'type' => 'string' ),
						'votos_por_votante'     => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 3 ),
						'lugares'               => array( 'type' => 'integer', 'enum' => array( 1, 3 ) ),
						'max_fotos'             => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 20 ),
						'anonimo'               => array( 'type' => 'boolean' ),
						'premios'               => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'maxItems' => 3 ),
						'reglas'                => array( 'type' => 'string' ),
						'privado_participantes' => array( 'type' => 'boolean' ),
						'emails_participantes'  => array( 'type' => 'string' ),
						'privado_votantes'      => array( 'type' => 'boolean' ),
						'emails_votantes'       => array( 'type' => 'string' ),
						'estilo'                => array( 'type' => 'string', 'enum' => array_keys( Concursos_Diseno::estilos() ) ),
						'acento'                => array( 'type' => 'string', 'enum' => array_map( 'strval', array_keys( Concursos_Diseno::acentos() ) ) ),
						'tamano'                => array( 'type' => 'string', 'enum' => array_keys( Concursos_Diseno::tamanos() ) ),
						'fase'                  => array( 'type' => 'string', 'readonly' => true ),
					),
				),
			)
		);
	}

	/**
	 * Configuración de un reto tal como la espera el formulario, con las fechas legibles.
	 *
	 * @param int $post_id ID del reto.
	 * @return array
	 */
	private static function como_formulario( $post_id ) {
		$s = Concursos_Reto::get_settings( $post_id );
		foreach ( Concursos_Reto::FECHAS as $key ) {
			$s[ $key ] = Concursos_Reto::timestamp_to_input( $s[ $key ] );
		}

		return $s;
	}

	/**
	 * Devuelve la configuración de un reto.
	 *
	 * @param array $post Reto preparado por la API.
	 * @return array
	 */
	public static function leer( $post ) {
		$s = self::como_formulario( $post['id'] );
		foreach ( array( 'votos_por_votante', 'lugares', 'max_fotos' ) as $key ) {
			$s[ $key ] = (int) $s[ $key ];
		}
		foreach ( array( 'anonimo', 'privado_participantes', 'privado_votantes' ) as $key ) {
			$s[ $key ] = (bool) $s[ $key ];
		}
		$s['fase']         = Concursos_Reto::status( Concursos_Reto::get_settings( $post['id'] ) );

		return $s;
	}

	/**
	 * Cambia la configuración de un reto. Los valores que no se envían se conservan.
	 *
	 * @param array   $valores Valores recibidos.
	 * @param WP_Post $post    Reto.
	 * @return true|WP_Error
	 */
	public static function guardar( $valores, $post ) {
		$raw = array_merge( self::como_formulario( $post->ID ), (array) $valores );
		unset( $raw['fase'] );

		$settings = Concursos_Reto::sanitize( $raw );
		$errores  = Concursos_Reto::validate( $settings );
		foreach ( array( 'participantes', 'votantes' ) as $grupo ) {
			$invalidos = Concursos_Reto::emails_invalidos( (string) $raw[ 'emails_' . $grupo ] );
			if ( $invalidos ) {
				/* translators: %s: list of invalid email addresses. */
				$errores[] = sprintf( __( 'These addresses are not valid and were ignored: %s', 'foncholuis-art-challenge-manager' ), implode( ', ', $invalidos ) );
			}
		}
		if ( $errores ) {
			return new WP_Error( 'concursos_ajustes_invalidos', implode( ' ', array_unique( $errores ) ), array( 'status' => 400 ) );
		}

		Concursos_Reto::save_settings( $post->ID, $settings );

		return true;
	}
}
