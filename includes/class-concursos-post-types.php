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

	const RETO = 'concurso_reto';

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'after_setup_theme', array( __CLASS__, 'thumbnail_support' ), 20 );
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'use_block_editor' ), 10, 2 );
	}

	/**
	 * Registra el tipo de contenido "reto".
	 */
	public static function register() {
		$labels = array(
			'name'               => __( 'Retos', 'concursos' ),
			'singular_name'      => __( 'Reto', 'concursos' ),
			'menu_name'          => __( 'Retos', 'concursos' ),
			'add_new'            => __( 'Nuevo reto', 'concursos' ),
			'add_new_item'       => __( 'Crear nuevo reto', 'concursos' ),
			'edit_item'          => __( 'Editar reto', 'concursos' ),
			'new_item'           => __( 'Nuevo reto', 'concursos' ),
			'view_item'          => __( 'Ver reto', 'concursos' ),
			'search_items'       => __( 'Buscar retos', 'concursos' ),
			'not_found'          => __( 'No hay retos.', 'concursos' ),
			'not_found_in_trash' => __( 'No hay retos en la papelera.', 'concursos' ),
			'all_items'          => __( 'Todos los retos', 'concursos' ),
			'featured_image'     => __( 'Imagen de fondo', 'concursos' ),
			'set_featured_image' => __( 'Elegir imagen de fondo', 'concursos' ),
			'remove_featured_image' => __( 'Quitar imagen de fondo', 'concursos' ),
			'use_featured_image' => __( 'Usar como imagen de fondo', 'concursos' ),
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
