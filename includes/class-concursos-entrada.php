<?php
/**
 * Modelo de una entrada (foto enviada a un reto).
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cada entrada es un post con la foto como imagen destacada, ligado a su reto y participante.
 */
class Concursos_Entrada {

	const POST_TYPE = 'concurso_entrada';
	const META      = '_concursos_';

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_photo' ) );
	}

	/**
	 * Registra el tipo de contenido, dentro del menú "Retos".
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Fotos enviadas', 'concursos' ),
					'singular_name'      => __( 'Foto enviada', 'concursos' ),
					'menu_name'          => __( 'Fotos enviadas', 'concursos' ),
					'all_items'          => __( 'Fotos enviadas', 'concursos' ),
					'edit_item'          => __( 'Editar foto enviada', 'concursos' ),
					'search_items'       => __( 'Buscar fotos', 'concursos' ),
					'not_found'          => __( 'No hay fotos enviadas.', 'concursos' ),
					'not_found_in_trash' => __( 'No hay fotos en la papelera.', 'concursos' ),
					'featured_image'     => __( 'Foto', 'concursos' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=' . Concursos_Post_Types::RETO,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title', 'thumbnail' ),
				// Las fotos llegan desde el enlace personal de cada participante.
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Crea una entrada con una foto ya subida a la biblioteca de medios.
	 *
	 * @param array  $participante  Datos del participante (Concursos_Participante::get()).
	 * @param string $titulo        Título opcional.
	 * @param int    $attachment_id Foto.
	 * @return int|WP_Error
	 */
	public static function create( array $participante, $titulo, $attachment_id ) {
		if ( '' === $titulo ) {
			/* translators: %s: nombre del participante. */
			$titulo = sprintf( __( 'Foto de %s', 'concursos' ), $participante['nombre'] );
		}

		$id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $titulo,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		update_post_meta( $id, self::META . 'reto', $participante['reto'] );
		update_post_meta( $id, self::META . 'participante', $participante['id'] );
		set_post_thumbnail( $id, $attachment_id );
		wp_update_post(
			array(
				'ID'          => $attachment_id,
				'post_parent' => $id,
			)
		);

		return $id;
	}

	/**
	 * Entradas publicadas, filtradas por reto y/o participante, de la más antigua a la más reciente.
	 *
	 * @param array $args reto, participante (IDs; 0 = cualquiera).
	 * @return WP_Post[]
	 */
	public static function find( array $args ) {
		$meta = array();
		foreach ( array( 'reto', 'participante' ) as $key ) {
			if ( ! empty( $args[ $key ] ) ) {
				$meta[] = array(
					'key'   => self::META . $key,
					'value' => (int) $args[ $key ],
				);
			}
		}

		$fotos = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		// Las fotos de un participante inactivo se ocultan, sin borrarlas.
		return array_values( array_filter( $fotos, array( __CLASS__, 'se_ve' ) ) );
	}

	/**
	 * ¿Se muestra esta foto? Solo si su participante está activo.
	 *
	 * @param WP_Post $foto Foto.
	 * @return bool
	 */
	public static function se_ve( $foto ) {
		return Concursos_Participante::esta_activo( self::owner( $foto->ID ) );
	}

	/**
	 * Cuántas fotos tiene un reto.
	 *
	 * @param int $reto_id Reto.
	 * @return int
	 */
	public static function count_for_reto( $reto_id ) {
		return count( self::find( array( 'reto' => $reto_id ) ) );
	}

	/**
	 * Participante dueño de una entrada.
	 *
	 * @param int $id Entrada.
	 * @return int
	 */
	public static function owner( $id ) {
		return (int) get_post_meta( $id, self::META . 'participante', true );
	}

	/**
	 * Al borrar definitivamente una entrada, borra también su foto.
	 *
	 * @param int $id Post que se borra.
	 */
	public static function delete_photo( $id ) {
		if ( self::POST_TYPE !== get_post_type( $id ) ) {
			return;
		}
		$attachment_id = get_post_thumbnail_id( $id );
		if ( $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}
}
