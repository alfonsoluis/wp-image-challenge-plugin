<?php
/**
 * Elimina todos los datos del plugin al borrarlo desde WordPress.
 *
 * @package FoncholuisArtChallengeManager
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Borra los datos del sitio actual.
 */
function concursos_borrar_datos() {
	global $wpdb;

	// Se consulta directamente porque aquí los tipos y estados propios no están registrados.
	$posts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT ID, post_type FROM %i WHERE post_type IN (%s, %s, %s)',
			$wpdb->posts,
			'concurso_reto',
			'concurso_inscrito',
			'concurso_entrada'
		)
	);

	foreach ( $posts as $post ) {
		// Fotos enviadas y fotos de perfil. La imagen de fondo de un reto es de la biblioteca del sitio y se conserva.
		if ( 'concurso_reto' !== $post->post_type ) {
			$imagen = (int) get_post_meta( $post->ID, '_thumbnail_id', true );
			if ( $imagen ) {
				wp_delete_attachment( $imagen, true );
			}
		}
		wp_delete_post( (int) $post->ID, true );
	}

	// Páginas que creó el plugin. Una página de inscripción que ya existía ("existente") se conserva.
	foreach ( array( 'concursos_pagina_retos', 'concursos_pagina_inscripcion' ) as $opcion ) {
		$pagina = (int) get_option( $opcion );
		if ( $pagina ) {
			wp_delete_post( $pagina, true );
		}
	}

	foreach ( array( 'concursos_votos', 'concursos_votantes' ) as $tabla ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $tabla ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	foreach ( array( 'concursos_db_version', 'concursos_rewrite_version', 'concursos_pagina_retos', 'concursos_pagina_inscripcion', 'concursos_perfiles' ) as $opcion ) {
		delete_option( $opcion );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $concursos_sitio ) {
		switch_to_blog( $concursos_sitio );
		concursos_borrar_datos();
		restore_current_blog();
	}
} else {
	concursos_borrar_datos();
}

delete_metadata( 'user', 0, 'concursos_vista_fotos', '', true );
