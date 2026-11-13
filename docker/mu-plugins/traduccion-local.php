<?php
/**
 * Solo en el entorno de desarrollo: carga la traducción del repositorio, que el
 * ZIP del plugin no incluye (en WordPress.org llega desde translate.wordpress.org).
 *
 * @package Concursos
 */

add_action(
	'init',
	function () {
		$mo = WP_PLUGIN_DIR . '/foncholuis-art-challenge-manager/languages/foncholuis-art-challenge-manager-es_ES.mo';
		if ( preg_match( '/^es(_|$)/', determine_locale() ) && is_readable( $mo ) ) {
			load_textdomain( 'foncholuis-art-challenge-manager', $mo );
		}
	},
	0
);
