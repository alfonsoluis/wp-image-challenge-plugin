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
		add_action( 'trashed_post', array( __CLASS__, 'quitar_votos_si_participante' ) );
		add_filter( 'wp_untrash_post_status', array( __CLASS__, 'estado_al_restaurar' ), 10, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'quitar_votos_si_participante' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'borrar_archivos' ) );
	}

	/**
	 * Participantes y fotos vuelven de la papelera como estaban, no como borrador.
	 *
	 * @param string $new_status      Estado propuesto por WordPress.
	 * @param int    $id              Post.
	 * @param string $previous_status Estado antes de la papelera.
	 * @return string
	 */
	public static function estado_al_restaurar( $new_status, $id, $previous_status ) {
		return in_array( get_post_type( $id ), array( self::POST_TYPE, Concursos_Entrada::POST_TYPE ), true ) ? $previous_status : $new_status;
	}

	/**
	 * Al borrar definitivamente a un participante se borran sus fotos y, si
	 * ninguna otra inscripción la comparte, su foto de perfil.
	 *
	 * @param int $id Post que se borra.
	 */
	public static function borrar_archivos( $id ) {
		if ( self::POST_TYPE !== get_post_type( $id ) ) {
			return;
		}

		$fotos = get_posts(
			array(
				'post_type'      => Concursos_Entrada::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => Concursos_Entrada::META . 'participante', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => (int) $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		foreach ( $fotos as $foto ) {
			wp_delete_post( $foto, true );
		}

		$avatar = get_post_thumbnail_id( $id );
		if ( ! $avatar ) {
			return;
		}
		$compartida = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 2,
				'fields'         => 'ids',
				'meta_key'       => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => (int) $avatar, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( ! array_diff( $compartida, array( (int) $id ) ) ) {
			wp_delete_attachment( $avatar, true );
		}
	}

	/**
	 * Al mandar a la papelera o borrar un participante, se quitan sus votos.
	 *
	 * @param int $id Post.
	 */
	public static function quitar_votos_si_participante( $id ) {
		if ( self::POST_TYPE === get_post_type( $id ) ) {
			self::quitar_votos( $id );
		}
	}

	/**
	 * Registra el tipo de contenido, dentro del menú "Retos".
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'                  => __( 'Participants', 'photo-challenges' ),
					'singular_name'         => __( 'Participant', 'photo-challenges' ),
					'menu_name'             => __( 'Participants', 'photo-challenges' ),
					'all_items'             => __( 'Participants', 'photo-challenges' ),
					'edit_item'             => __( 'Edit participant', 'photo-challenges' ),
					'search_items'          => __( 'Search participants', 'photo-challenges' ),
					'not_found'             => __( 'No participants.', 'photo-challenges' ),
					'not_found_in_trash'    => __( 'No participants in the trash.', 'photo-challenges' ),
					'featured_image'        => __( 'Profile photo', 'photo-challenges' ),
					'set_featured_image'    => __( 'Choose profile photo', 'photo-challenges' ),
					'remove_featured_image' => __( 'Remove profile photo', 'photo-challenges' ),
					'use_featured_image'    => __( 'Use as profile photo', 'photo-challenges' ),
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
			self::PENDIENTE => __( 'Pending', 'photo-challenges' ),
			self::ACTIVO    => __( 'Active', 'photo-challenges' ),
			self::INACTIVO  => __( 'Inactive', 'photo-challenges' ),
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
	 * Enlaces a las redes de un participante.
	 *
	 * @param array $p Participante.
	 * @return array nombre de la red => URL.
	 */
	public static function redes( array $p ) {
		$redes = array();
		if ( $p['instagram'] ) {
			$redes['Instagram'] = 'https://www.instagram.com/' . rawurlencode( $p['instagram'] ) . '/';
		}
		if ( $p['facebook'] ) {
			$redes['Facebook'] = 'https://www.facebook.com/' . rawurlencode( $p['facebook'] );
		}

		return $redes;
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
		if ( self::INACTIVO === $estado ) {
			self::quitar_votos( $id );
		}
	}

	/**
	 * ¿Sus fotos se ven y cuentan? Solo si el participante existe, está publicado y activo.
	 *
	 * @param int $id ID del participante.
	 * @return bool
	 */
	public static function esta_activo( $id ) {
		$post = $id ? get_post( $id ) : null;

		return $post && self::POST_TYPE === $post->post_type && 'publish' === $post->post_status && self::ACTIVO === get_post_meta( $id, self::META . 'estado', true );
	}

	/**
	 * Borra los votos a las fotos de un participante. Sus fotos siguen guardadas,
	 * pero al reactivarlo los votos no vuelven.
	 *
	 * @param int $id ID del participante.
	 */
	public static function quitar_votos( $id ) {
		global $wpdb;

		$fotos = get_posts(
			array(
				'post_type'      => Concursos_Entrada::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => Concursos_Entrada::META . 'participante', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => (int) $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		foreach ( $fotos as $foto ) {
			$wpdb->delete( Concursos_DB::votos(), array( 'entrada_id' => (int) $foto ), array( '%d' ) );
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
