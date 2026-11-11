<?php
/**
 * Subida de fotos de concurso: tamaño máximo, formato y sin original.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Guarda cada foto como una copia WebP de hasta 2048 px y descarta el original.
 *
 * Solo afecta a las fotos subidas por el plugin, con filtros activos durante
 * esa subida: el resto de la biblioteca de medios no cambia.
 */
class Concursos_Imagenes {

	/** Tamaño máximo de una foto subida: 5 MB. */
	const MAX_BYTES = 5242880;

	/** Lado mayor de la copia guardada. */
	const MAX_PX = 2048;

	/** Calidad de la copia WebP (de 0 a 100). */
	const CALIDAD = 82;

	/**
	 * Tamaño máximo real: el del plugin o el del servidor, si es menor.
	 *
	 * @return int Bytes.
	 */
	public static function limite() {
		return min( self::MAX_BYTES, (int) wp_max_upload_size() );
	}

	/**
	 * Sube una foto y deja solo su copia comprimida.
	 *
	 * @param string $campo  Nombre del campo del formulario.
	 * @param int    $padre  Post al que se asocia (0 = ninguno).
	 * @return int|WP_Error ID del adjunto.
	 */
	public static function subir( $campo, $padre ) {
		self::filtros( true );
		$id = media_handle_upload( $campo, $padre );
		self::filtros( false );

		if ( is_wp_error( $id ) ) {
			return $id;
		}
		self::descartar_original( $id );

		return $id;
	}

	/**
	 * Activa o quita los filtros de la subida.
	 *
	 * @param bool $activos Si se activan.
	 */
	private static function filtros( $activos ) {
		$accion = $activos ? 'add_filter' : 'remove_filter';
		$accion( 'big_image_size_threshold', array( __CLASS__, 'umbral' ), 999, 4 );
		$accion( 'wp_editor_set_quality', array( __CLASS__, 'calidad' ), 999, 2 );
		$accion( 'image_editor_output_format', array( __CLASS__, 'formato' ), 999, 1 );
		$accion( 'intermediate_image_sizes_advanced', array( __CLASS__, 'tamanos' ), 999, 2 );
		$accion( 'wp_handle_upload_prefilter', array( __CLASS__, 'nombre_aleatorio' ) );
	}

	/**
	 * Da a cada archivo subido un nombre aleatorio: el nombre original puede
	 * delatar a su autor, por ejemplo en una votación anónima.
	 *
	 * @param array $file Archivo subido.
	 * @return array
	 */
	public static function nombre_aleatorio( $file ) {
		$ext          = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
		$file['name'] = 'foto-' . strtolower( wp_generate_password( 12, false ) ) . ( $ext ? '.' . $ext : '' );

		return $file;
	}

	/**
	 * @return int Lado mayor a partir del cual WordPress reduce la foto.
	 */
	public static function umbral() {
		return self::MAX_PX;
	}

	/**
	 * @return int Calidad de compresión.
	 */
	public static function calidad() {
		return self::CALIDAD;
	}

	/**
	 * @return string[] JPEG y PNG se convierten a WebP.
	 */
	public static function formato() {
		return array(
			'image/jpeg' => 'image/webp',
			'image/png'  => 'image/webp',
		);
	}

	/**
	 * Solo los tamaños que usan las páginas del plugin.
	 *
	 * @param array $sizes Tamaños registrados.
	 * @return array
	 */
	public static function tamanos( $sizes ) {
		return array_intersect_key( $sizes, array_flip( array( 'thumbnail', 'medium_large' ) ) );
	}

	/**
	 * Borra el original que WordPress guarda al reducir una foto grande.
	 *
	 * @param int $id Adjunto.
	 */
	private static function descartar_original( $id ) {
		$original = wp_get_original_image_path( $id );
		if ( $original && $original !== get_attached_file( $id ) && file_exists( $original ) ) {
			wp_delete_file( $original );
		}

		$meta = wp_get_attachment_metadata( $id );
		if ( is_array( $meta ) && isset( $meta['original_image'] ) ) {
			unset( $meta['original_image'] );
			wp_update_attachment_metadata( $id, $meta );
		}
		delete_post_meta( $id, '_wp_attachment_backup_sizes' );

		// WordPress conserva el tipo del archivo subido aunque la copia sea WebP.
		$tipo = wp_check_filetype( get_attached_file( $id ) );
		if ( $tipo['type'] && get_post_mime_type( $id ) !== $tipo['type'] ) {
			wp_update_post(
				array(
					'ID'             => $id,
					'post_mime_type' => $tipo['type'],
				)
			);
		}
	}
}
