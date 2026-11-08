<?php
/**
 * Modelo de un participante.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Participantes inscritos en un reto: datos, estado y enlace personal de envío.
 *
 * Cada inscripción es un post privado (no público) con sus datos en metadatos y
 * la foto de perfil como imagen destacada.
 */
class Concursos_Participante {

	const POST_TYPE = 'concurso_inscrito';
	const META      = '_concursos_';

	const PENDIENTE = 'pendiente';
	const ACTIVO    = 'activo';
	const INACTIVO  = 'inactivo';

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Registra el tipo de contenido, dentro del menú "Retos".
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'                  => __( 'Participantes', 'concursos' ),
					'singular_name'         => __( 'Participante', 'concursos' ),
					'menu_name'             => __( 'Participantes', 'concursos' ),
					'all_items'             => __( 'Participantes', 'concursos' ),
					'edit_item'             => __( 'Editar participante', 'concursos' ),
					'search_items'          => __( 'Buscar participantes', 'concursos' ),
					'not_found'             => __( 'No hay participantes.', 'concursos' ),
					'not_found_in_trash'    => __( 'No hay participantes en la papelera.', 'concursos' ),
					'featured_image'        => __( 'Foto de perfil', 'concursos' ),
					'set_featured_image'    => __( 'Elegir foto de perfil', 'concursos' ),
					'remove_featured_image' => __( 'Quitar foto de perfil', 'concursos' ),
					'use_featured_image'    => __( 'Usar como foto de perfil', 'concursos' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=' . Concursos_Post_Types::RETO,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title', 'thumbnail' ),
				// Los participantes llegan por el formulario público, no se crean desde el panel.
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Etiquetas de los estados.
	 *
	 * @return array
	 */
	public static function estado_labels() {
		return array(
			self::PENDIENTE => __( 'Pendiente', 'concursos' ),
			self::ACTIVO    => __( 'Activo', 'concursos' ),
			self::INACTIVO  => __( 'Inactivo', 'concursos' ),
		);
	}

	/**
	 * Datos de un participante.
	 *
	 * @param int $id ID del participante.
	 * @return array
	 */
	public static function get( $id ) {
		$meta = function ( $key ) use ( $id ) {
			return get_post_meta( $id, self::META . $key, true );
		};

		return array(
			'id'             => (int) $id,
			'nombre'         => get_the_title( $id ),
			'email'          => (string) $meta( 'email' ),
			'reto'           => (int) $meta( 'reto' ),
			'estado'         => $meta( 'estado' ) ? $meta( 'estado' ) : self::PENDIENTE,
			'instagram'      => (string) $meta( 'instagram' ),
			'facebook'       => (string) $meta( 'facebook' ),
			'enlace_enviado' => (int) $meta( 'enlace_enviado' ),
		);
	}

	/**
	 * Crea una inscripción pendiente.
	 *
	 * @param array $data nombre, email, reto, instagram, facebook (ya saneados).
	 * @return int|WP_Error ID del participante.
	 */
	public static function create( array $data ) {
		$id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $data['nombre'],
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		update_post_meta( $id, self::META . 'email', strtolower( $data['email'] ) );
		update_post_meta( $id, self::META . 'reto', (int) $data['reto'] );
		update_post_meta( $id, self::META . 'estado', self::PENDIENTE );
		update_post_meta( $id, self::META . 'instagram', $data['instagram'] );
		update_post_meta( $id, self::META . 'facebook', $data['facebook'] );

		return $id;
	}

	/**
	 * Cambia el estado.
	 *
	 * @param int    $id     ID del participante.
	 * @param string $estado pendiente|activo|inactivo.
	 */
	public static function set_estado( $id, $estado ) {
		if ( isset( self::estado_labels()[ $estado ] ) ) {
			update_post_meta( $id, self::META . 'estado', $estado );
		}
	}

	/**
	 * Cuántos participantes tiene un reto.
	 *
	 * @param int    $reto_id Reto.
	 * @param string $estado  Solo los de este estado ('' = todos).
	 * @return int
	 */
	public static function count_for_reto( $reto_id, $estado = '' ) {
		$meta = array(
			array(
				'key'   => self::META . 'reto',
				'value' => (int) $reto_id,
			),
		);
		if ( $estado ) {
			$meta[] = array(
				'key'   => self::META . 'estado',
				'value' => $estado,
			);
		}
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * ¿Ya existe una inscripción con este email en este reto?
	 *
	 * @param string $email   Email.
	 * @param int    $reto_id Reto.
	 * @return bool
	 */
	public static function exists( $email, $reto_id ) {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => self::META . 'email',
						'value' => strtolower( $email ),
					),
					array(
						'key'   => self::META . 'reto',
						'value' => (int) $reto_id,
					),
				),
			)
		);

		return $query->have_posts();
	}

	/**
	 * Genera un enlace personal nuevo. Solo se guarda su hash.
	 *
	 * @param int $id ID del participante.
	 * @return string El token en claro, para enviarlo por email.
	 */
	public static function issue_token( $id ) {
		$token = bin2hex( random_bytes( 16 ) );
		update_post_meta( $id, self::META . 'token_hash', hash( 'sha256', $token ) );

		return $token;
	}

	/**
	 * Busca al participante de un enlace personal.
	 *
	 * @param string $token Token en claro.
	 * @return int ID del participante, o 0.
	 */
	public static function find_by_token( $token ) {
		if ( ! preg_match( '/^[a-f0-9]{32}$/', (string) $token ) ) {
			return 0;
		}

		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META . 'token_hash', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => hash( 'sha256', $token ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return $ids ? (int) $ids[0] : 0;
	}
}
