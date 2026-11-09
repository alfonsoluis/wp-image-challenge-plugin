<?php
/**
 * Plugin Name:       Concursos
 * Plugin URI:        https://github.com/alfonsoluis/wp-image-challenge-plugin
 * Description:       Photo, drawing, design and other art challenges with participants, submissions, voting and results.
 * Version:           0.12.0
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Author:            Alfonso Rodriguez
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       concursos
 * Domain Path:       /languages
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

define( 'CONCURSOS_VERSION', '0.12.0' );
define( 'CONCURSOS_FILE', __FILE__ );
define( 'CONCURSOS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CONCURSOS_URL', plugin_dir_url( __FILE__ ) );

require_once CONCURSOS_PATH . 'includes/class-concursos-db.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-diseno.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-reto.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-post-types.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-participante.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-inscripcion.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-imagenes.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-entrada.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-envio.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-votantes.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-votos.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-galeria.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-plantilla.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-portada.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-perfil.php';

add_action(
	'init',
	function () {
		// Cualquier variante del español (es_MX, es_CO...) usa la traducción incluida.
		if ( preg_match( '/^es(_|$)/', determine_locale() ) ) {
			load_textdomain( 'concursos', CONCURSOS_PATH . 'languages/concursos-es_ES.mo' );
		} else {
			load_plugin_textdomain( 'concursos', false, dirname( plugin_basename( CONCURSOS_FILE ) ) . '/languages' );
		}
	},
	0
);

Concursos_DB::init();
Concursos_Post_Types::init();
Concursos_Participante::init();
Concursos_Inscripcion::init();
Concursos_Entrada::init();
Concursos_Envio::init();
Concursos_Votantes::init();
Concursos_Votos::init();
Concursos_Galeria::init();
Concursos_Portada::init();
Concursos_Perfil::init();

if ( is_admin() ) {
	require_once CONCURSOS_PATH . 'includes/admin/class-concursos-reto-metabox.php';
	require_once CONCURSOS_PATH . 'includes/admin/class-concursos-admin.php';
	require_once CONCURSOS_PATH . 'includes/admin/class-concursos-participantes-admin.php';
	require_once CONCURSOS_PATH . 'includes/admin/class-concursos-entradas-admin.php';
	require_once CONCURSOS_PATH . 'includes/admin/class-concursos-duplicar.php';
	require_once CONCURSOS_PATH . 'includes/admin/class-concursos-votantes-admin.php';

	Concursos_Reto_Metabox::init();
	Concursos_Admin::init();
	Concursos_Participantes_Admin::init();
	Concursos_Entradas_Admin::init();
	Concursos_Duplicar::init();
	Concursos_Votantes_Admin::init();
}

register_activation_hook(
	__FILE__,
	function () {
		Concursos_Post_Types::register();
		Concursos_Participante::register();
		Concursos_Entrada::register();
		Concursos_DB::install();
		Concursos_Perfil::add_rules();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
