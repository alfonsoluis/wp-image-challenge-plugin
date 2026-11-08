<?php
/**
 * Datos de demostración: un reto abierto con participantes y fotos.
 *
 * Uso desde la raíz del repositorio:
 *   make demo                    # reto en fase de envíos
 *   make demo FASE=votacion      # mismos datos, en fase de votación
 *   make demo FASE=finalizado    # en fase de resultados
 *
 * Cada ejecución borra la demostración anterior antes de crearla.
 * Solo lo usa el entorno Docker local: no forma parte del plugin.
 */

global $wpdb;

require_once ABSPATH . 'wp-admin/includes/image.php';

$fase   = getenv( 'CONCURSOS_FASE' ) ? getenv( 'CONCURSOS_FASE' ) : 'envios';
$fechas = array(
	'envios'     => array( '-2 days', '+5 days', '+8 days', '+10 days' ),
	'votacion'   => array( '-12 days', '-7 days', '+5 days', '+8 days' ),
	'finalizado' => array( '-20 days', '-14 days', '-7 days', '-2 days' ),
);
if ( ! isset( $fechas[ $fase ] ) ) {
	WP_CLI::error( 'FASE debe ser envios, votacion o finalizado.' );
}

/**
 * Imagen de prueba: degradado con círculos de color, distinta según la semilla.
 *
 * @param int $seed Semilla.
 * @return string JPEG.
 */
function concursos_demo_imagen( $seed ) {
	mt_srand( $seed );
	$w  = 1200;
	$h  = 800;
	$im = imagecreatetruecolor( $w, $h );
	$a  = array( mt_rand( 30, 220 ), mt_rand( 30, 220 ), mt_rand( 30, 220 ) );
	$b  = array( mt_rand( 30, 220 ), mt_rand( 30, 220 ), mt_rand( 30, 220 ) );
	for ( $y = 0; $y < $h; $y++ ) {
		$t = $y / $h;
		$c = imagecolorallocate( $im, (int) ( $a[0] + ( $b[0] - $a[0] ) * $t ), (int) ( $a[1] + ( $b[1] - $a[1] ) * $t ), (int) ( $a[2] + ( $b[2] - $a[2] ) * $t ) );
		imageline( $im, 0, $y, $w, $y, $c );
	}
	for ( $i = 0; $i < 6; $i++ ) {
		$col = imagecolorallocatealpha( $im, mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ), 70 );
		imagefilledellipse( $im, mt_rand( 0, $w ), mt_rand( 0, $h ), mt_rand( 120, 420 ), mt_rand( 120, 420 ), $col );
	}
	ob_start();
	imagejpeg( $im, null, 85 );
	$data = ob_get_clean();
	imagedestroy( $im );

	return $data;
}

/**
 * Sube una imagen a la biblioteca de medios.
 *
 * @param string $jpeg   Contenido.
 * @param string $titulo Título.
 * @return int ID del adjunto.
 */
function concursos_demo_adjunto( $jpeg, $titulo ) {
	$file = wp_upload_bits( sanitize_file_name( $titulo ) . '-' . wp_generate_password( 6, false ) . '.jpg', null, $jpeg );
	$id   = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $titulo,
			'post_status'    => 'inherit',
		),
		$file['file']
	);
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file['file'] ) );
	update_post_meta( $id, '_concursos_demo', '1' );

	return $id;
}

// 1. Borrar la demostración anterior (las fotos se borran con sus entradas).
$anterior = get_posts(
	array(
		'post_type'   => array( Concursos_Post_Types::RETO, Concursos_Participante::POST_TYPE, Concursos_Entrada::POST_TYPE, 'attachment' ),
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => '_concursos_demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'  => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	)
);
foreach ( $anterior as $id ) {
	wp_delete_post( $id, true );
}
$tabla_votantes = Concursos_DB::votantes();
$tabla_votos    = Concursos_DB::votos();
$wpdb->query( "DELETE v FROM {$tabla_votos} v JOIN {$tabla_votantes} t ON t.id = v.votante_id WHERE t.email LIKE 'votante-demo-%'" );
$wpdb->query( "DELETE FROM {$tabla_votantes} WHERE email LIKE 'votante-demo-%'" );

// 2. El reto.
$f    = array_map( 'strtotime', $fechas[ $fase ] );
$reto = wp_insert_post(
	array(
		'post_type'    => Concursos_Post_Types::RETO,
		'post_status'  => 'publish',
		'post_title'   => 'Reto de prueba: luz y sombra',
		'post_content' => "Un reto para practicar el contraste entre luz y sombra. Puede ser una escena urbana, un retrato o un objeto cotidiano.\n\nEs un reto de demostración del entorno de desarrollo.",
	),
	true
);
if ( is_wp_error( $reto ) ) {
	WP_CLI::error( $reto->get_error_message() );
}
Concursos_Reto::save_settings(
	$reto,
	array_merge(
		Concursos_Reto::defaults(),
		array(
			'tipo'              => 'unico',
			'modo'              => 'abierto',
			'inicio'            => $f[0],
			'fin_envios'        => $f[1],
			'fin_votacion'      => $f[2],
			'cierre'            => $f[3],
			'votos_por_votante' => 3,
			'lugares'           => 3,
			'max_fotos'         => 2,
			'premios'           => array( 'Un cuadro de 50 € para la foto ganadora', 'Un vale de 25 €', 'Un libro de fotografía' ),
			'reglas'            => "Foto propia, sin marcas de agua ni firmas.\nFormato libre: horizontal o vertical.\nSe admiten retoques básicos de luz y color.",
		)
	)
);
update_post_meta( $reto, '_concursos_demo', '1' );

// 3. Participantes activos, con sus fotos.
$personas = array(
	array( 'Marta López', '@martalopez.foto', 2 ),
	array( 'Javier Ruiz', '', 1 ),
	array( 'Lucía Fernández', 'lucia.fotos', 2 ),
	array( 'Pablo Sánchez', '', 1 ),
	array( 'Carmen Díaz', '@carmen.d', 2 ),
	array( 'Diego Moreno', '', 1 ),
);
$titulos  = array(
	'Callejón al atardecer', 'Reflejo en el charco', 'Sombras de la plaza', 'Ventana con luz',
	'Árbol contra la niebla', 'Mercado de mañana', 'Puerta azul', 'Bicicleta bajo la lluvia',
	'Escalera de hierro', 'Retrato junto a la ventana', 'Muelle al amanecer', 'Calle sin nadie',
);
$cuenta   = 0;
$fotos    = array();
foreach ( $personas as $n => $persona ) {
	$id = Concursos_Participante::create(
		array(
			'nombre'    => $persona[0],
			'email'     => 'participante' . ( $n + 1 ) . '@example.com',
			'reto'      => $reto,
			'instagram' => $persona[1],
			'facebook'  => '',
		)
	);
	update_post_meta( $id, '_concursos_demo', '1' );
	Concursos_Participante::set_estado( $id, Concursos_Participante::ACTIVO );

	for ( $k = 0; $k < $persona[2]; $k++ ) {
		$titulo  = $titulos[ $cuenta % count( $titulos ) ];
		$adjunto = concursos_demo_adjunto( concursos_demo_imagen( $cuenta + 7 ), $titulo );
		$entrada = Concursos_Entrada::create( Concursos_Participante::get( $id ), $titulo, $adjunto );
		update_post_meta( $entrada, '_concursos_demo', '1' );
		$fotos[] = $entrada;
		++$cuenta;
	}
}

// 4. En la fase de resultados: votos con empates en el primer y en el tercer lugar.
if ( 'finalizado' === $fase ) {
	$reparto = array( 6, 6, 4, 3, 3, 2, 1, 0, 0 );
	$ids     = array();
	for ( $v = 1; $v <= 12; $v++ ) {
		$wpdb->insert(
			$tabla_votantes,
			array(
				'email'  => 'votante-demo-' . $v . '@example.com',
				'estado' => Concursos_Votantes::CONFIRMADO,
				'creado' => time(),
			)
		);
		$ids[] = (int) $wpdb->insert_id;
	}
	$turno = 0;
	foreach ( $fotos as $i => $entrada ) {
		for ( $n = 0; $n < $reparto[ $i ]; $n++ ) {
			$wpdb->insert(
				$tabla_votos,
				array(
					'votante_id' => $ids[ $turno++ % count( $ids ) ],
					'reto_id'    => $reto,
					'entrada_id' => $entrada,
					'creado'     => time(),
				)
			);
		}
	}
}

WP_CLI::success( sprintf( 'Reto #%d listo en fase "%s" con %d participantes y %d fotos: %s', $reto, $fase, count( $personas ), $cuenta, get_permalink( $reto ) ) );
