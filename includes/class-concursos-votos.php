<?php
/**
 * Votos: emitirlos, contarlos y calcular los puestos.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Votos de los votantes confirmados a las fotos de un reto.
 */
class Concursos_Votos {

	const ACTION = 'concursos_votar';

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_for_entrada' ) );
		add_action( 'trashed_post', array( __CLASS__, 'delete_for_entrada' ) );
	}

	/**
	 * Votos de cada foto de un reto.
	 *
	 * @param int $reto_id Reto.
	 * @return int[] entrada => votos (solo las que tienen alguno).
	 */
	public static function counts( $reto_id ) {
		global $wpdb;

		$t    = Concursos_DB::votos();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT entrada_id, COUNT(*) AS n FROM {$t} WHERE reto_id = %d GROUP BY entrada_id", $reto_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$counts = array();
		foreach ( $rows as $row ) {
			$counts[ (int) $row->entrada_id ] = (int) $row->n;
		}

		return $counts;
	}

	/**
	 * Votos de una foto.
	 *
	 * @param int $entrada_id Foto.
	 * @return int
	 */
	public static function count_entrada( $entrada_id ) {
		global $wpdb;

		$t = Concursos_DB::votos();

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE entrada_id = %d", $entrada_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Fotos que ha votado un votante en un reto.
	 *
	 * @param int $votante_id Votante.
	 * @param int $reto_id    Reto.
	 * @return int[]
	 */
	public static function de_votante( $votante_id, $reto_id ) {
		global $wpdb;

		$t = Concursos_DB::votos();

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT entrada_id FROM {$t} WHERE votante_id = %d AND reto_id = %d", $votante_id, $reto_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Reparte los puestos según los votos. Quienes tienen los mismos votos
	 * comparten puesto; las fotos sin votos nunca reciben uno.
	 *
	 * @param int[] $votos   entrada => votos, en el orden en que deben listarse los empates.
	 * @param int   $lugares Puestos con premio (1 o 3).
	 * @return array puestos: lista de { lugar, votos, entradas }; resto: entradas sin puesto.
	 */
	public static function rank( array $votos, $lugares ) {
		$votos   = array_map( 'intval', $votos );
		$niveles = array_values( array_unique( array_values( $votos ) ) );
		rsort( $niveles, SORT_NUMERIC );

		$puestos = array();
		$resto   = array();
		foreach ( $niveles as $n ) {
			$entradas = array_keys( $votos, $n, true );
			if ( $n > 0 && count( $puestos ) < (int) $lugares ) {
				$puestos[] = array(
					'lugar'    => count( $puestos ) + 1,
					'votos'    => $n,
					'entradas' => $entradas,
				);
			} else {
				$resto = array_merge( $resto, $entradas );
			}
		}

		return array(
			'puestos' => $puestos,
			'resto'   => $resto,
		);
	}

	/**
	 * Votos de cada foto dada, con cero para las que no tienen.
	 *
	 * @param int       $reto_id  Reto.
	 * @param WP_Post[] $entradas Fotos, en el orden en que deben listarse los empates.
	 * @return int[] entrada => votos.
	 */
	public static function de_fotos( $reto_id, array $entradas ) {
		$counts = self::counts( $reto_id );
		$votos  = array();
		foreach ( $entradas as $entrada ) {
			$votos[ $entrada->ID ] = isset( $counts[ $entrada->ID ] ) ? $counts[ $entrada->ID ] : 0;
		}

		return $votos;
	}

	/**
	 * Cuántas personas votaron en un reto.
	 *
	 * @param int $reto_id Reto.
	 * @return int
	 */
	public static function votantes_de_reto( $reto_id ) {
		global $wpdb;

		$t = Concursos_DB::votos();

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT votante_id) FROM {$t} WHERE reto_id = %d", $reto_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Nombre de un puesto.
	 *
	 * @param int  $lugar  Puesto (1 a 3).
	 * @param bool $empate Si lo comparten varias fotos.
	 * @return string
	 */
	public static function nombre_lugar( $lugar, $empate ) {
		$nombres = array(
			1 => array( __( 'First place', 'concursos' ), __( 'Tied for first place', 'concursos' ) ),
			2 => array( __( 'Second place', 'concursos' ), __( 'Tied for second place', 'concursos' ) ),
			3 => array( __( 'Third place', 'concursos' ), __( 'Tied for third place', 'concursos' ) ),
		);

		return $nombres[ $lugar ][ $empate ? 1 : 0 ];
	}

	/**
	 * Procesa un voto o su retirada y vuelve a la página del reto.
	 */
	public static function handle() {
		check_admin_referer( self::ACTION );

		$reto    = isset( $_POST['reto'] ) ? absint( $_POST['reto'] ) : 0;
		$entrada = isset( $_POST['entrada'] ) ? absint( $_POST['entrada'] ) : 0;
		$quitar  = isset( $_POST['op'] ) && 'quitar' === $_POST['op'];

		$votante = Concursos_Votantes::actual();
		if ( ! $votante ) {
			Concursos_Votantes::volver( $reto, 'identificate' );
		}

		$s = Concursos_Reto::get_settings( $reto );
		if ( Concursos_Post_Types::RETO !== get_post_type( $reto ) || 'publish' !== get_post_status( $reto ) || 'votacion' !== Concursos_Reto::status( $s ) ) {
			Concursos_Votantes::volver( $reto, 'cerrado' );
		}

		if ( ! Concursos_Reto::permite_email( $s, 'votantes', $votante->email ) ) {
			Concursos_Votantes::volver( $reto, 'privado' );
		}

		$valida = Concursos_Entrada::POST_TYPE === get_post_type( $entrada )
			&& 'publish' === get_post_status( $entrada )
			&& (int) get_post_meta( $entrada, Concursos_Entrada::META . 'reto', true ) === $reto
			&& Concursos_Entrada::se_ve( get_post( $entrada ) );
		if ( ! $valida ) {
			Concursos_Votantes::volver( $reto, 'error' );
		}

		global $wpdb;
		$t = Concursos_DB::votos();

		if ( $quitar ) {
			$wpdb->delete(
				$t,
				array(
					'votante_id' => (int) $votante->id,
					'entrada_id' => $entrada,
				),
				array( '%d', '%d' )
			);
			Concursos_Votantes::volver( $reto, 'quitado' );
		}

		// Comprueba el límite en la misma sentencia que inserta: dos clics simultáneos no lo superan.
		$insertados = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"INSERT IGNORE INTO {$t} (votante_id, reto_id, entrada_id, creado) SELECT %d, %d, %d, %d FROM DUAL WHERE (SELECT COUNT(*) FROM {$t} v WHERE v.votante_id = %d AND v.reto_id = %d) < %d",
				(int) $votante->id,
				$reto,
				$entrada,
				time(),
				(int) $votante->id,
				$reto,
				(int) $s['votos_por_votante']
			)
		);

		if ( $insertados ) {
			Concursos_Votantes::volver( $reto, 'votado' );
		}
		$ya_votada = in_array( $entrada, self::de_votante( (int) $votante->id, $reto ), true );
		Concursos_Votantes::volver( $reto, $ya_votada ? 'votado' : 'limite' );
	}

	/**
	 * Botón para votar una foto o retirar el voto.
	 *
	 * @param int  $reto_id    Reto.
	 * @param int  $entrada_id Foto.
	 * @param bool $votada     Si el votante ya la votó.
	 * @param bool $puede      Si le quedan votos.
	 * @return string HTML.
	 */
	public static function boton( $reto_id, $entrada_id, $votada, $puede ) {
		if ( ! $votada && ! $puede ) {
			return '<button type="button" class="concursos-voto" disabled>' . esc_html__( 'No votes left', 'concursos' ) . '</button>';
		}

		return sprintf(
			'<form method="post" action="%1$s" class="concursos-voto-form"><input type="hidden" name="action" value="%2$s"><input type="hidden" name="_wpnonce" value="%3$s"><input type="hidden" name="reto" value="%4$d"><input type="hidden" name="entrada" value="%5$d"><input type="hidden" name="op" value="%6$s"><button type="submit" class="concursos-voto%7$s">%8$s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( self::ACTION ),
			esc_attr( wp_create_nonce( self::ACTION ) ),
			(int) $reto_id,
			(int) $entrada_id,
			$votada ? 'quitar' : 'votar',
			$votada ? ' concursos-voto--hecho' : '',
			$votada ? esc_html__( 'Remove vote', 'concursos' ) : esc_html__( 'Vote', 'concursos' )
		);
	}

	/**
	 * Al borrar una foto o mandarla a la papelera se borran sus votos.
	 *
	 * @param int $id Post que se borra.
	 */
	public static function delete_for_entrada( $id ) {
		global $wpdb;

		if ( Concursos_Entrada::POST_TYPE === get_post_type( $id ) ) {
			$wpdb->delete( Concursos_DB::votos(), array( 'entrada_id' => (int) $id ), array( '%d' ) );
		}
	}
}
