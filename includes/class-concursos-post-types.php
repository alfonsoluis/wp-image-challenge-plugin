<?php
/**
 * Registro de tipos de contenido.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registra el tipo de contenido de los retos.
 */
class Concursos_Post_Types {

	const RETO      = 'concurso_reto';
	const ARCHIVADO = 'concurso_archivado';

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'estado_archivado' ), 10, 2 );
		add_action( 'after_setup_theme', array( __CLASS__, 'thumbnail_support' ), 20 );
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'use_block_editor' ), 10, 2 );
	}

	/**
	 * Registra el tipo de contenido "reto".
	 */
	public static function register() {
		self::register_estado();

		$labels = array(
			'name'               => __( 'Challenges', 'concursos' ),
			'singular_name'      => __( 'Challenge', 'concursos' ),
			'menu_name'          => __( 'Challenges', 'concursos' ),
			'add_new'            => __( 'New challenge', 'concursos' ),
			'add_new_item'       => __( 'Create new challenge', 'concursos' ),
			'edit_item'          => __( 'Edit challenge', 'concursos' ),
			'new_item'           => __( 'New challenge', 'concursos' ),
			'view_item'          => __( 'View challenge', 'concursos' ),
			'search_items'       => __( 'Search challenges', 'concursos' ),
			'not_found'          => __( 'No challenges.', 'concursos' ),
			'not_found_in_trash' => __( 'No challenges in the trash.', 'concursos' ),
			'all_items'          => __( 'All challenges', 'concursos' ),
			'featured_image'     => __( 'Background image', 'concursos' ),
			'set_featured_image' => __( 'Choose background image', 'concursos' ),
			'remove_featured_image' => __( 'Remove background image', 'concursos' ),
			'use_featured_image' => __( 'Use as background image', 'concursos' ),
		);

		register_post_type(
			self::RETO,
			array(
				'labels'        => $labels,
				'public'        => true,
				'has_archive'   => 'retos',
				'rewrite'       => array(
					'slug'       => 'retos',
					'with_front' => false,
				),
				'menu_icon'     => 'dashicons-awards',
				'menu_position' => 26,
				'supports'      => array( 'title', 'editor', 'thumbnail' ),
				'show_in_rest'  => true,
			)
		);
	}

	/**
	 * Registra el estado "archivado": como un borrador, no se ve en el sitio.
	 */
	public static function register_estado() {
		register_post_status(
			self::ARCHIVADO,
			array(
				'label'                     => __( 'Archived', 'concursos' ),
				'public'                    => false,
				'protected'                 => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of archived challenges. */
				'label_count'               => _n_noop( 'Archived <span class="count">(%s)</span>', 'Archived <span class="count">(%s)</span>', 'concursos' ),
			)
		);
	}

	/**
	 * Quita de la lista de retos los estados que ya muestra su columna.
	 *
	 * @param string[] $states Estados.
	 * @param WP_Post  $post   Post.
	 * @return string[]
	 */
	public static function estado_archivado( $states, $post ) {
		// La columna Publicación ya lo indica: no se repite junto al título.
		if ( self::RETO === $post->post_type ) {
			unset( $states['draft'], $states['pending'], $states['scheduled'] );
		}

		return $states;
	}

	/**
	 * Garantiza la imagen destacada (imagen de fondo) aunque el tema no la declare.
	 */
	public static function thumbnail_support() {
		if ( ! current_theme_supports( 'post-thumbnails' ) ) {
			add_theme_support( 'post-thumbnails', array( self::RETO ) );
		}
	}

	/**
	 * Los retos se editan con el editor clásico: el formulario de datos es lo principal.
	 *
	 * @param bool   $use_block_editor Valor actual.
	 * @param string $post_type        Tipo de contenido.
	 * @return bool
	 */
	public static function use_block_editor( $use_block_editor, $post_type ) {
		return self::RETO === $post_type ? false : $use_block_editor;
	}
}
