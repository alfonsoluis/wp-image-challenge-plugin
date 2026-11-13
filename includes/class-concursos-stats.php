<?php
/**
 * Cifras de actividad para la pantalla «Stats».
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Series por día de participantes, fotos, votantes y votos, y cifras de cada reto.
 *
 * Los días se cuentan en la zona horaria del sitio. Las fechas de los posts ya
 * están en hora local; las de las tablas propias son marcas de tiempo, que se
 * agrupan en PHP para no depender de la zona horaria de MySQL.
 */
class Concursos_Stats {

	const CACHE     = 'concursos_stats_';
	const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/** A partir de cuántos días una gráfica de barras agrupa por semanas. */
	const DIAS_MAX = 60;

	/**
	 * Periodos del panel.
	 *
	 * @return string[] clave => nombre.
	 */
	public static function periodos() {
		return array(
			'7d'  => __( 'Last 7 days', 'foncholuis-art-challenge-manager' ),
			'30d' => __( 'Last 30 days', 'foncholuis-art-challenge-manager' ),
			'ano' => __( 'This year', 'foncholuis-art-challenge-manager' ),
		);
	}

	/**
	 * Cifras del panel.
	 *
	 * @return string[] clave => nombre.
	 */
	public static function metricas() {
		return array(
			'participantes' => __( 'New participants', 'foncholuis-art-challenge-manager' ),
			'fotos'         => __( 'Photos received', 'foncholuis-art-challenge-manager' ),
			'votantes'      => __( 'New voters', 'foncholuis-art-challenge-manager' ),
			'votos'         => __( 'Votes cast', 'foncholuis-art-challenge-manager' ),
		);
	}

	/**
	 * Días de un periodo y del periodo anterior. Los finales no se incluyen.
	 *
	 * @param string $periodo Clave del periodo.
	 * @return DateTimeImmutable[] desde, hasta, antes_desde, antes_hasta.
	 */
	public static function rango( $periodo ) {
		$hoy   = ( new DateTimeImmutable( 'now', wp_timezone() ) )->setTime( 0, 0 );
		$hasta = $hoy->modify( '+1 day' );

		if ( 'ano' === $periodo ) {
			$desde = $hoy->setDate( (int) $hoy->format( 'Y' ), 1, 1 );

			return array(
				'desde'       => $desde,
				'hasta'       => $hasta,
				'antes_desde' => $desde->modify( '-1 year' ),
				'antes_hasta' => $hasta->modify( '-1 year' ),
			);
		}

		$dias  = '30d' === $periodo ? 30 : 7;
		$desde = $hasta->modify( "-{$dias} days" );

		return array(
			'desde'       => $desde,
			'hasta'       => $hasta,
			'antes_desde' => $desde->modify( "-{$dias} days" ),
			'antes_hasta' => $desde,
		);
	}

	/**
	 * Días entre dos fechas, a cero.
	 *
	 * @param DateTimeImmutable $desde Primer día.
	 * @param DateTimeImmutable $hasta Día siguiente al último.
	 * @return int[] Y-m-d => 0.
	 */
	public static function dias( DateTimeImmutable $desde, DateTimeImmutable $hasta ) {
		$dias = array();
		for ( $dia = $desde; $dia < $hasta; $dia = $dia->modify( '+1 day' ) ) {
			$dias[ $dia->format( 'Y-m-d' ) ] = 0;
		}

		return $dias;
	}

	/**
	 * Cuántos hay de una cifra en cada día.
	 *
	 * @param string            $metrica participantes, fotos, votantes o votos.
	 * @param DateTimeImmutable $desde   Primer día.
	 * @param DateTimeImmutable $hasta   Día siguiente al último.
	 * @param int               $reto_id Solo un reto (fotos y votos), o 0 para todos.
	 * @return int[] Y-m-d => número.
	 */
	public static function serie( $metrica, DateTimeImmutable $desde, DateTimeImmutable $hasta, $reto_id = 0 ) {
		$serie = self::dias( $desde, $hasta );
		foreach ( self::fechas( $metrica, $desde, $hasta, $reto_id ) as $dia ) {
			if ( isset( $serie[ $dia ] ) ) {
				++$serie[ $dia ];
			}
		}

		return $serie;
	}

	/**
	 * Días (Y-m-d, hora del sitio) de cada participante, foto, votante o voto del rango.
	 *
	 * @param string            $metrica participantes, fotos, votantes o votos.
	 * @param DateTimeImmutable $desde   Primer día.
	 * @param DateTimeImmutable $hasta   Día siguiente al último.
	 * @param int               $reto_id Solo un reto, o 0 para todos.
	 * @return string[]
	 */
	private static function fechas( $metrica, DateTimeImmutable $desde, DateTimeImmutable $hasta, $reto_id = 0 ) {
		global $wpdb;

		if ( in_array( $metrica, array( 'participantes', 'fotos' ), true ) ) {
			$tipo = 'fotos' === $metrica ? Concursos_Entrada::POST_TYPE : Concursos_Participante::POST_TYPE;
			if ( $reto_id ) {
				return $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DATE(p.post_date) FROM %i p INNER JOIN %i m ON m.post_id = p.ID AND m.meta_key = %s AND m.meta_value = %d WHERE p.post_type = %s AND p.post_status = 'publish' AND p.post_date >= %s AND p.post_date < %s",
						$wpdb->posts,
						$wpdb->postmeta,
						Concursos_Entrada::META . 'reto',
						(int) $reto_id,
						$tipo,
						$desde->format( 'Y-m-d H:i:s' ),
						$hasta->format( 'Y-m-d H:i:s' )
					)
				);
			}

			return $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DATE(post_date) FROM %i WHERE post_type = %s AND post_status = 'publish' AND post_date >= %s AND post_date < %s",
					$wpdb->posts,
					$tipo,
					$desde->format( 'Y-m-d H:i:s' ),
					$hasta->format( 'Y-m-d H:i:s' )
				)
			);
		}

		if ( 'votantes' === $metrica ) {
			$marcas = $wpdb->get_col( $wpdb->prepare( 'SELECT creado FROM %i WHERE estado = %s AND creado >= %d AND creado < %d', Concursos_DB::votantes(), Concursos_Votantes::CONFIRMADO, $desde->getTimestamp(), $hasta->getTimestamp() ) );
		} elseif ( $reto_id ) {
			$marcas = $wpdb->get_col( $wpdb->prepare( 'SELECT creado FROM %i WHERE reto_id = %d AND creado >= %d AND creado < %d', Concursos_DB::votos(), (int) $reto_id, $desde->getTimestamp(), $hasta->getTimestamp() ) );
		} else {
			$marcas = $wpdb->get_col( $wpdb->prepare( 'SELECT creado FROM %i WHERE creado >= %d AND creado < %d', Concursos_DB::votos(), $desde->getTimestamp(), $hasta->getTimestamp() ) );
		}

		return array_map(
			function ( $marca ) {
				return wp_date( 'Y-m-d', (int) $marca );
			},
			$marcas
		);
	}

	/**
	 * Series, totales y totales del periodo anterior, guardados unos minutos.
	 *
	 * @param string $periodo Clave del periodo.
	 * @return array metrica => serie, total, antes.
	 */
	public static function panel( $periodo ) {
		$guardado = get_transient( self::CACHE . $periodo );
		if ( is_array( $guardado ) ) {
			return $guardado;
		}

		$r     = self::rango( $periodo );
		$datos = array();
		foreach ( array_keys( self::metricas() ) as $metrica ) {
			$serie             = self::serie( $metrica, $r['desde'], $r['hasta'] );
			$datos[ $metrica ] = array(
				'serie' => $serie,
				'total' => array_sum( $serie ),
				'antes' => count( self::fechas( $metrica, $r['antes_desde'], $r['antes_hasta'] ) ),
			);
		}
		set_transient( self::CACHE . $periodo, $datos, self::CACHE_TTL );

		return $datos;
	}

	/**
	 * Borra las cifras guardadas: se llama al crear un voto o una foto.
	 */
	public static function limpiar_cache() {
		foreach ( array_keys( self::periodos() ) as $periodo ) {
			delete_transient( self::CACHE . $periodo );
		}
	}

	/**
	 * Agrupa una serie diaria por semanas si es larga.
	 *
	 * @param int[] $serie Y-m-d => número.
	 * @return array serie (clave => número) y si está agrupada por semanas.
	 */
	public static function agrupar( array $serie ) {
		if ( count( $serie ) <= self::DIAS_MAX ) {
			return array( $serie, false );
		}

		$semanas = array();
		$clave   = '';
		$i       = 0;
		foreach ( $serie as $dia => $n ) {
			if ( 0 === $i % 7 ) {
				$clave             = $dia;
				$semanas[ $clave ] = 0;
			}
			$semanas[ $clave ] += $n;
			++$i;
		}

		return array( $semanas, true );
	}

	/**
	 * Clasificación de un reto: los puestos de Concursos_Votos::rank() y luego el resto.
	 *
	 * @param int   $reto_id Reto.
	 * @param array $s       Configuración.
	 * @return array[] Cada fila: id, puntos, votos, lugar (0 si no tiene) y empate.
	 */
	public static function clasificacion( $reto_id, array $s ) {
		global $wpdb;

		$puntos  = Concursos_Votos::de_fotos( $reto_id, Concursos_Entrada::find( array( 'reto' => $reto_id ) ) );
		$ranking = Concursos_Votos::rank( $puntos, (int) $s['lugares'] );
		$votos   = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT entrada_id, COUNT(*) AS n FROM %i WHERE reto_id = %d GROUP BY entrada_id', Concursos_DB::votos(), (int) $reto_id ) ) as $fila ) {
			$votos[ (int) $fila->entrada_id ] = (int) $fila->n;
		}

		$filas = array();
		foreach ( $ranking['puestos'] as $puesto ) {
			foreach ( $puesto['entradas'] as $id ) {
				$filas[] = array(
					'id'     => (int) $id,
					'puntos' => (int) $puesto['votos'],
					'votos'  => isset( $votos[ $id ] ) ? $votos[ $id ] : 0,
					'lugar'  => (int) $puesto['lugar'],
					'empate' => count( $puesto['entradas'] ) > 1,
				);
			}
		}
		foreach ( $ranking['resto'] as $id ) {
			$filas[] = array(
				'id'     => (int) $id,
				'puntos' => (int) $puntos[ $id ],
				'votos'  => isset( $votos[ $id ] ) ? $votos[ $id ] : 0,
				'lugar'  => 0,
				'empate' => false,
			);
		}

		return $filas;
	}

	/**
	 * Cifras clave de un reto.
	 *
	 * @param int $reto_id Reto.
	 * @return int[] participantes, activos, fotos, votantes, votos y confirmados (votantes confirmados del sitio).
	 */
	public static function cifras_reto( $reto_id ) {
		global $wpdb;

		return array(
			'participantes' => Concursos_Participante::count_for_reto( $reto_id ),
			'activos'       => Concursos_Participante::count_for_reto( $reto_id, Concursos_Participante::ACTIVO ),
			'fotos'         => Concursos_Entrada::count_for_reto( $reto_id ),
			'votantes'      => Concursos_Votos::votantes_de_reto( $reto_id ),
			'votos'         => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE reto_id = %d', Concursos_DB::votos(), (int) $reto_id ) ),
			'confirmados'   => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE estado = %s', Concursos_DB::votantes(), Concursos_Votantes::CONFIRMADO ) ),
		);
	}

	/**
	 * Últimos votos de un reto, con el email del votante.
	 *
	 * @param int $reto_id Reto.
	 * @param int $n       Cuántos.
	 * @return object[] creado, puesto, entrada_id, email.
	 */
	public static function ultimos_votos( $reto_id, $n = 10 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT v.creado, v.puesto, v.entrada_id, t.email FROM %i v INNER JOIN %i t ON t.id = v.votante_id WHERE v.reto_id = %d ORDER BY v.creado DESC, v.id DESC LIMIT %d',
				Concursos_DB::votos(),
				Concursos_DB::votantes(),
				(int) $reto_id,
				(int) $n
			)
		);
	}

	/**
	 * Email enmascarado: primera letra y dominio (l•••@gmail.com).
	 *
	 * @param string $email Email.
	 * @return string
	 */
	public static function enmascarar( $email ) {
		$partes = explode( '@', (string) $email, 2 );
		if ( 2 !== count( $partes ) || '' === $partes[0] ) {
			return '•••';
		}
		$inicial = function_exists( 'mb_substr' ) ? mb_substr( $partes[0], 0, 1 ) : substr( $partes[0], 0, 1 );

		return $inicial . '•••@' . $partes[1];
	}
}
