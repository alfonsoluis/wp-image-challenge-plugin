<?php
/**
 * Plugin Name: Mailpit (solo desarrollo)
 * Description: Envía todos los emails de WordPress al buzón de pruebas Mailpit (http://localhost:8025).
 *
 * Solo existe en el entorno Docker local; no forma parte del plugin Concursos.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'phpmailer_init',
	function ( $phpmailer ) {
		$phpmailer->isSMTP();
		$phpmailer->Host        = 'mailpit';
		$phpmailer->Port        = 1025;
		$phpmailer->SMTPAuth    = false;
		$phpmailer->SMTPAutoTLS = false;
	}
);

// PHPMailer rechaza el remitente por defecto en local (wordpress@localhost): sin dominio válido.
add_filter( 'wp_mail_from', fn() => 'concursos@example.com' );
add_filter( 'wp_mail_from_name', fn() => get_bloginfo( 'name' ) );
