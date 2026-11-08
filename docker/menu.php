<?php
/**
 * Menú del sitio de desarrollo: "Retos", con "Inscribirse" dentro.
 *
 * El plugin no modifica el menú de cada sitio: esto solo configura el entorno
 * Docker local. Se puede repetir sin duplicar el menú.
 */

$titulo    = 'Menú de retos';
$retos       = (int) get_option( Concursos_Portada::OPCION_PAGINA );
$inscripcion = get_page_by_path( 'inscripcion-retos' );
$contenido   = sprintf(
	'<!-- wp:navigation-submenu {"label":"Retos","type":"page","id":%1$d,"url":"%2$s","kind":"post-type","isTopLevelItem":true} --><!-- wp:navigation-link {"label":"Inscribirse","type":"page","id":%3$d,"url":"%4$s","kind":"post-type","isTopLevelLink":false} /--><!-- /wp:navigation-submenu -->',
	$retos,
	get_permalink( $retos ),
	$inscripcion ? $inscripcion->ID : 0,
	$inscripcion ? get_permalink( $inscripcion ) : home_url( '/' )
);

$existente = get_posts(
	array(
		'post_type'   => 'wp_navigation',
		'title'       => $titulo,
		'post_status' => 'publish',
		'numberposts' => 1,
	)
);

$id = wp_insert_post(
	array(
		'ID'           => $existente ? $existente[0]->ID : 0,
		'post_type'    => 'wp_navigation',
		'post_status'  => 'publish',
		'post_title'   => $titulo,
		'post_content' => $contenido,
	),
	true
);

if ( is_wp_error( $id ) ) {
	WP_CLI::error( $id->get_error_message() );
}
WP_CLI::success( 'Menú listo: ' . $id );
