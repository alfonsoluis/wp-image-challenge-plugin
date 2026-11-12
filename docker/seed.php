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
	'votacion'   => array( '-4 days', '-1 hour', '+5 days', '+8 days' ),
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
	$medidas = array( array( 1200, 800 ), array( 800, 1200 ), array( 1200, 1200 ), array( 1600, 800 ), array( 1200, 900 ) );
	$w       = $medidas[ $seed % count( $medidas ) ][0];
	$h       = $medidas[ $seed % count( $medidas ) ][1];

	// Fotos reales de muestra (licencia Unsplash), guardadas en disco tras la
	// primera descarga para que las pruebas no dependan de la red; sin conexión, un degradado.
	$cache = WP_CONTENT_DIR . '/uploads/concursos-demo-cache';
	$copia = sprintf( '%s/%d-%dx%d.jpg', $cache, $seed, $w, $h );
	if ( is_readable( $copia ) ) {
		return file_get_contents( $copia ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
	static $sin_red = false;
	$respuesta      = $sin_red ? null : wp_remote_get( sprintf( 'https://picsum.photos/seed/concursos%d/%d/%d', $seed, $w, $h ), array( 'timeout' => 8 ) );
	$sin_red        = $sin_red || is_wp_error( $respuesta );
	if ( $respuesta && 200 === wp_remote_retrieve_response_code( $respuesta ) && 0 === strpos( wp_remote_retrieve_body( $respuesta ), "\xFF\xD8" ) ) {
		wp_mkdir_p( $cache );
		file_put_contents( $copia, wp_remote_retrieve_body( $respuesta ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return wp_remote_retrieve_body( $respuesta );
	}

	mt_srand( $seed );
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

/**
 * Crea un reto de demostración.
 *
 * @param string $titulo    Título.
 * @param string $contenido Descripción.
 * @param int[]  $f         Las cuatro fechas.
 * @param array  $extra     Configuración adicional.
 * @return int
 */
function concursos_demo_reto( $titulo, $contenido, array $f, array $extra ) {
	$reto = wp_insert_post(
		array(
			'post_type'    => Concursos_Post_Types::RETO,
			'post_status'  => 'publish',
			'post_title'   => $titulo,
			'post_content' => $contenido,
			'post_author'  => 1,
			'tags_input'   => array( 'demostración', 'fotografía' ),
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
				'modo'         => 'abierto',
				'inicio'       => $f[0],
				'fin_envios'   => $f[1],
				'fin_votacion' => $f[2],
				'cierre'       => $f[3],
			),
			$extra
		)
	);
	update_post_meta( $reto, '_concursos_demo', '1' );

	return $reto;
}

/**
 * Inscribe a una persona en un reto, activa, con sus fotos.
 *
 * @param int    $reto    Reto.
 * @param int    $n       Número de la persona (fija su email, y por tanto su perfil).
 * @param string $nombre  Nombre.
 * @param string $red     Instagram.
 * @param int    $cuantas Fotos.
 * @return int[] Fotos creadas.
 */
function concursos_demo_participante( $reto, $n, $nombre, $red, $cuantas ) {
	static $cuenta = 0;
	$titulos = array(
		'Callejón al atardecer', 'Reflejo en el charco', 'Sombras de la plaza', 'Ventana con luz',
		'Árbol contra la niebla', 'Mercado de mañana', 'Puerta azul', 'Bicicleta bajo la lluvia',
		'Escalera de hierro', 'Retrato junto a la ventana', 'Muelle al amanecer', 'Calle sin nadie',
	);

	$id = Concursos_Participante::create(
		array(
			'nombre'    => $nombre,
			'email'     => 'participante' . ( $n + 1 ) . '@example.com',
			'reto'      => $reto,
			'instagram' => Concursos_Inscripcion::handle_name( $red ),
			'facebook'  => '',
		)
	);
	update_post_meta( $id, '_concursos_demo', '1' );
	Concursos_Participante::set_estado( $id, Concursos_Participante::ACTIVO );

	$fotos = array();
	for ( $k = 0; $k < $cuantas; $k++ ) {
		$titulo  = $titulos[ $cuenta % count( $titulos ) ];
		$adjunto = concursos_demo_adjunto( concursos_demo_imagen( $cuenta + 7 ), $titulo );
		$descripcion = 'Tomada a primera hora de la mañana, buscando el contraste entre la luz directa y las sombras largas. Sin más edición que un ajuste suave de exposición.';
		$entrada     = Concursos_Entrada::create( Concursos_Participante::get( $id ), $titulo, $adjunto, $descripcion );
		update_post_meta( $entrada, '_concursos_demo', '1' );
		$fotos[] = $entrada;
		++$cuenta;
	}

	return $fotos;
}

// 2. Votantes de ejemplo, para los retos con resultados.
$votantes = array();
for ( $v = 1; $v <= 12; $v++ ) {
	$wpdb->insert(
		$tabla_votantes,
		array(
			'email'  => 'votante-demo-' . $v . '@example.com',
			'estado' => Concursos_Votantes::CONFIRMADO,
			'creado' => time(),
		)
	);
	$votantes[] = (int) $wpdb->insert_id;
}

/**
 * Reparte votos entre fotos.
 *
 * @param int   $reto     Reto.
 * @param int[] $fotos    Fotos.
 * @param int[] $reparto  Votos de cada foto.
 * @param int[] $votantes Votantes.
 */
function concursos_demo_votos( $reto, array $fotos, array $reparto, array $votantes ) {
	global $wpdb;

	$turno = 0;
	foreach ( $fotos as $i => $entrada ) {
		for ( $n = 0; $n < $reparto[ $i ]; $n++ ) {
			$wpdb->insert(
				Concursos_DB::votos(),
				array(
					'votante_id' => $votantes[ $turno++ % count( $votantes ) ],
					'reto_id'    => $reto,
					'entrada_id' => $entrada,
					'creado'     => time(),
				)
			);
		}
	}
}

// 3. El reto principal, con participantes activos y sus fotos.
$reto     = concursos_demo_reto(
	'Reto de prueba: luz y sombra',
	"Un reto para practicar el contraste entre luz y sombra. Puede ser una escena urbana, un retrato o un objeto cotidiano.\n\nEs un reto de demostración del entorno de desarrollo.",
	array_map( 'strtotime', $fechas[ $fase ] ),
	array(
		'votos_por_votante' => 3,
		'lugares'           => 3,
		'max_fotos'         => 2,
		'premios'           => array( 'Un cuadro de 50 € para la foto ganadora', 'Un vale de 25 €', 'Un libro de fotografía' ),
		'estilo'            => 'editorial',
		'acento'            => 'teja',
		'reglas'            => "Foto propia, sin marcas de agua ni firmas.\nFormato libre: horizontal o vertical.\nSe admiten retoques básicos de luz y color.",
	)
);
$personas = array(
	array( 'Marta López', '@martalopez.foto', 2 ),
	array( 'Javier Ruiz', '', 1 ),
	array( 'Lucía Fernández', 'lucia.fotos', 2 ),
	array( 'Pablo Sánchez', '', 1 ),
	array( 'Carmen Díaz', '@carmen.d', 2 ),
	array( 'Diego Moreno', '', 1 ),
);
$fotos    = array();
foreach ( $personas as $n => $persona ) {
	$fotos = array_merge( $fotos, concursos_demo_participante( $reto, $n, $persona[0], $persona[1], $persona[2] ) );
}

// En la fase de resultados: votos con empates en el primer y en el tercer lugar.
if ( 'finalizado' === $fase ) {
	concursos_demo_votos( $reto, $fotos, array( 6, 6, 4, 3, 3, 2, 1, 0, 0 ), $votantes );
}

// 4. Retos anteriores, para la portada y los perfiles.
$anteriores = array(
	array( 'Reto anterior: reflejos', array( '-60 days', '-50 days', '-45 days', '-40 days' ), 3, array( 'Una suscripción de fotografía', 'Un trípode', 'Un libro de fotografía' ), array( 0, 1, 2, 3 ), array( 5, 3, 3, 1 ), array( 'editorial', 'verde' ), 'grande' ),
	array( 'Reto anterior: calles vacías', array( '-100 days', '-90 days', '-85 days', '-80 days' ), 1, array( 'Una mochila para cámara', '', '' ), array( 0, 4, 5 ), array( 2, 4, 0 ), array( 'tema', 'azul' ), 'pequeno' ),
);
foreach ( $anteriores as $datos ) {
	$pasado = concursos_demo_reto(
		$datos[0],
		'Un reto ya terminado, de demostración.',
		array_map( 'strtotime', $datos[1] ),
		array(
			'votos_por_votante' => 3,
			'lugares'           => $datos[2],
			'premios'           => $datos[3],
			'estilo'            => $datos[6][0],
			'acento'            => $datos[6][1],
			'tamano'            => $datos[7],
		)
	);
	$suyas  = array();
	foreach ( $datos[4] as $n ) {
		$suyas = array_merge( $suyas, concursos_demo_participante( $pasado, $n, $personas[ $n ][0], $personas[ $n ][1], 1 ) );
	}
	concursos_demo_votos( $pasado, $suyas, $datos[5], $votantes );
}

WP_CLI::success( sprintf( 'Reto #%d listo en fase "%s" con %d participantes y %d fotos: %s', $reto, $fase, count( $personas ), count( $fotos ), get_permalink( $reto ) ) );
