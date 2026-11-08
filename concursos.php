<?php
/**
 * Plugin Name:       Concursos
 * Plugin URI:        https://github.com/alfonsoluis/wp-image-challenge-plugin
 * Description:       Retos y concursos (fotografía, dibujo, diseño...) con participantes, envíos, votación y resultados.
 * Version:           0.3.0
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

define( 'CONCURSOS_VERSION', '0.3.0' );
define( 'CONCURSOS_FILE', __FILE__ );
define( 'CONCURSOS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CONCURSOS_URL', plugin_dir_url( __FILE__ ) );

require_once CONCURSOS_PATH . 'includes/class-concursos-reto.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-post-types.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-participante.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-inscripcion.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-entrada.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-envio.php';
require_once CONCURSOS_PATH . 'includes/class-concursos-galeria.php';

Concursos_Post_Types::init();
Concursos_Participante::init();
Concursos_Inscripcion::init();
Concursos_Entrada::init();
Concursos_Envio::init();
Concursos_Galeria::init();

if ( is_admin() ) {
	require_once CONCURSOS_PATH . 'includes/admin/class-concursos-reto-metabox.php';
	require_once CONCURSOS_PATH . 'includes/admin/class-concursos-admin.php';
	require_once CONCURSOS_PATH . 'includes/admin/class-concursos-participantes-admin.php';
	require_once CONCURSOS_PATH . 'includes/admin/class-concursos-entradas-admin.php';

	Concursos_Reto_Metabox::init();
	Concursos_Admin::init();
	Concursos_Participantes_Admin::init();
	Concursos_Entradas_Admin::init();
}

register_activation_hook(
	__FILE__,
	function () {
		Concursos_Post_Types::register();
		Concursos_Participante::register();
		Concursos_Entrada::register();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
