<?php
/**
 * Menú del sitio de desarrollo: Inicio, Retos (con Inscribirse dentro) y la página de ejemplo.
 *
 * El plugin no modifica el menú de cada sitio: esto solo configura el entorno
 * Docker local. Si el menú ya existe no lo modifica, para respetar lo editado a mano.
 */

$titulo = 'Menú de retos';

$existente = get_posts(
	array(
		'post_type'   => 'wp_navigation',
		'title'       => $titulo,
		'post_status' => 'publish',
		'numberposts' => 1,
	)
);
if ( $existente ) {
	WP_CLI::success( 'El menú ya existe: no se toca, puede haberse editado.' );
	return;
}

$retos       = (int) get_option( Concursos_Portada::OPCION_PAGINA );
$inscripcion = get_page_by_path( 'inscripcion-retos' );
$ejemplo     = get_page_by_path( 'sample-page' );

$contenido = '<!-- wp:home-link {"label":"Inicio"} /-->' . sprintf(
	'<!-- wp:navigation-submenu {"label":"Retos","type":"page","id":%1$d,"url":"%2$s","kind":"post-type","isTopLevelItem":true} --><!-- wp:navigation-link {"label":"Inscribirse","type":"page","id":%3$d,"url":"%4$s","kind":"post-type","isTopLevelLink":false} /--><!-- /wp:navigation-submenu -->',
	$retos,
	get_permalink( $retos ),
	$inscripcion ? $inscripcion->ID : 0,
	$inscripcion ? get_permalink( $inscripcion ) : home_url( '/' )
);
if ( $ejemplo ) {
	$contenido .= sprintf(
		'<!-- wp:navigation-link {"label":"%1$s","type":"page","id":%2$d,"url":"%3$s","kind":"post-type","isTopLevelLink":true} /-->',
		$ejemplo->post_title,
		$ejemplo->ID,
		get_permalink( $ejemplo )
	);
}

$id = wp_insert_post(
	array(
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
WP_CLI::success( 'Menú creado: ' . $id );
