<?php
/**
 * Importa retos anteriores al plugin, ya terminados, con sus participantes,
 * sus fotos (adjuntos que ya están en la biblioteca de medios) y los puntos
 * que deciden el podio.
 *
 * Uso, con WP-CLI en el servidor:
 *   wp eval-file importar_retos.php datos.json emails.txt [publicar]
 *
 * datos.json:
 *   { "personas": { "Nombre": { "instagram": "usuario" } },
 *     "retos": [ { "clave": "afs-2025-10", "titulo": "…", "mes": "2025-10",
 *                  "descripcion": "<p>…</p>", "imagen": 271, "max_fotos": 2,
 *                  "fotos": [ { "persona": "Nombre", "adjunto": 257, "puntos": 3 } ] } ] }
 *
 * emails.txt: una línea «Nombre | email» por persona. Quien no tenga email
 * recibe uno que nunca recibe correo (nombre@sin-email.invalid), el mismo en
 * todos sus retos para que su perfil y la clasificación los unan.
 *
 * Cada reto se crea en borrador (o publicado con «publicar») con el aviso a
 * los suscritos desactivado y marcado como ya enviado: nunca manda emails.
 * Si un reto con la misma clave ya existe, no se toca.
 *
 * @package Concursos
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Concursos_Reto' ) ) {
	WP_CLI::error( 'El plugin no está activo.' );
}

$args     = isset( $args ) ? (array) $args : array();
$publicar = in_array( 'publicar', $args, true );
$args     = array_values( array_diff( $args, array( 'publicar' ) ) );
if ( count( $args ) < 1 ) {
	WP_CLI::error( 'Uso: wp eval-file importar_retos.php datos.json [emails.txt] [publicar]' );
}

$datos = json_decode( (string) file_get_contents( $args[0] ), true );
if ( ! is_array( $datos ) || empty( $datos['retos'] ) ) {
	WP_CLI::error( 'No se pudo leer ' . $args[0] );
}

$emails = array();
if ( isset( $args[1] ) ) {
	foreach ( file( $args[1], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $linea ) {
		$linea = trim( $linea );
		if ( '' === $linea || '#' === $linea[0] || false === strpos( $linea, '|' ) ) {
			continue;
		}
		list( $nombre, $email ) = array_map( 'trim', explode( '|', $linea, 2 ) );
		if ( is_email( $email ) ) {
			$emails[ $nombre ] = strtolower( $email );
		}
	}
}

$email_de = function ( $nombre ) use ( $emails ) {
	return isset( $emails[ $nombre ] ) ? $emails[ $nombre ] : sanitize_title( $nombre ) . '@sin-email.invalid';
};

$zona = wp_timezone();

foreach ( $datos['retos'] as $r ) {
	$existente = get_posts(
		array(
			'post_type'      => Concursos_Post_Types::RETO,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_concursos_importado', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $r['clave'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( $existente ) {
		WP_CLI::log( sprintf( '= %s ya existe (#%d), no se toca.', $r['titulo'], $existente[0] ) );
		continue;
	}

	// Fechas: del 1 al 20 recibe fotos, después se vota y el último día se publican los resultados.
	$mes    = new DateTimeImmutable( $r['mes'] . '-01 00:00', $zona );
	$ultimo = $mes->modify( 'last day of this month' )->setTime( 20, 0 );
	$s      = Concursos_Reto::sanitize(
		array(
			'modo'              => 'abierto',
			'inicio'            => $mes->format( 'Y-m-d\TH:i' ),
			'fin_envios'        => $mes->setDate( (int) $mes->format( 'Y' ), (int) $mes->format( 'm' ), 21 )->format( 'Y-m-d\TH:i' ),
			'fin_votacion'      => $ultimo->format( 'Y-m-d\TH:i' ),
			'cierre'            => $ultimo->format( 'Y-m-d\TH:i' ),
			'votos_por_votante' => 3,
			'lugares'           => 3,
			'max_fotos'         => isset( $r['max_fotos'] ) ? (int) $r['max_fotos'] : 2,
			'avisar'            => 0,
		)
	);
	$errores = Concursos_Reto::validate( $s );
	if ( $errores ) {
		WP_CLI::error( $r['titulo'] . ': ' . implode( ' ', $errores ) );
	}

	$fecha   = $ultimo->format( 'Y-m-d H:i:s' );
	$reto_id = wp_insert_post(
		array(
			'post_type'     => Concursos_Post_Types::RETO,
			'post_status'   => 'draft',
			'post_title'    => $r['titulo'],
			'post_content'  => isset( $r['descripcion'] ) ? $r['descripcion'] : '',
			'post_date'     => $fecha,
			'post_date_gmt' => get_gmt_from_date( $fecha ),
			'edit_date'     => true,
		),
		true
	);
	if ( is_wp_error( $reto_id ) ) {
		WP_CLI::error( $reto_id->get_error_message() );
	}
	Concursos_Reto::save_settings( $reto_id, $s );
	update_post_meta( $reto_id, '_concursos_avisado', time() );
	update_post_meta( $reto_id, '_concursos_importado', $r['clave'] );
	if ( ! empty( $r['imagen'] ) ) {
		set_post_thumbnail( $reto_id, (int) $r['imagen'] );
	}

	// Un participante por persona, aprobado, y sus fotos con los puntos del podio.
	$participantes = array();
	$fotos         = 0;
	foreach ( $r['fotos'] as $f ) {
		$nombre = $f['persona'];
		if ( ! isset( $participantes[ $nombre ] ) ) {
			$persona = isset( $datos['personas'][ $nombre ] ) ? $datos['personas'][ $nombre ] : array();
			$pid     = Concursos_Participante::create(
				array(
					'nombre'    => $nombre,
					'email'     => $email_de( $nombre ),
					'reto'      => $reto_id,
					'instagram' => isset( $persona['instagram'] ) ? $persona['instagram'] : '',
					'facebook'  => '',
				)
			);
			if ( is_wp_error( $pid ) ) {
				WP_CLI::error( $pid->get_error_message() );
			}
			Concursos_Participante::set_estado( $pid, Concursos_Participante::ACTIVO );
			// Como si ya tuviera su enlace: al publicar el reto no se le envía nada.
			update_post_meta( $pid, Concursos_Participante::META . 'enlace_enviado', time() );
			$participantes[ $nombre ] = array(
				'id'     => $pid,
				'reto'   => $reto_id,
				'nombre' => $nombre,
			);
		}

		if ( ! wp_attachment_is_image( (int) $f['adjunto'] ) ) {
			WP_CLI::warning( sprintf( '%s: el adjunto #%d no es una imagen, se salta.', $r['titulo'], $f['adjunto'] ) );
			continue;
		}
		$eid = Concursos_Entrada::create( $participantes[ $nombre ], 'Foto de ' . $nombre, (int) $f['adjunto'] );
		if ( is_wp_error( $eid ) ) {
			WP_CLI::error( $eid->get_error_message() );
		}
		if ( ! empty( $f['puntos'] ) ) {
			update_post_meta( $eid, Concursos_Votos::META_IMPORTADOS, (int) $f['puntos'] );
		}
		++$fotos;
	}

	if ( $publicar ) {
		wp_update_post(
			array(
				'ID'          => $reto_id,
				'post_status' => 'publish',
			)
		);
	}

	$ranking = Concursos_Votos::rank( Concursos_Votos::de_fotos( $reto_id, Concursos_Entrada::find( array( 'reto' => $reto_id ) ) ), 3 );
	$podio   = array();
	foreach ( $ranking['puestos'] as $p ) {
		$podio[] = $p['lugar'] . '.º ' . implode( ' y ', array_map( 'get_the_title', array_map( 'Concursos_Entrada::owner', $p['entradas'] ) ) );
	}
	WP_CLI::success( sprintf( '%s (#%d, %s): %d participantes, %d fotos. %s', $r['titulo'], $reto_id, get_post_status( $reto_id ), count( $participantes ), $fotos, implode( ' · ', $podio ) ) );
}
