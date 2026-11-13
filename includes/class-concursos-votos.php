<?php
/**
 * Votos: emitirlos, contarlos y calcular los puestos.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Votos de los votantes confirmados a las fotos de un reto.
 *
 * Con un solo voto por votante, cada voto vale 1 punto. Con más, cada votante
 * reparte puestos: el 1.º vale 3 puntos, el 2.º 2 y el 3.º 1. Los votos sin
 * puesto (puesto 0) valen 1 punto.
 */
class Concursos_Votos {

	const ACTION = 'concursos_votar';

	/** Puntos de cada voto según su puesto, en SQL. */
	const PUNTOS_SQL = 'CASE puesto WHEN 1 THEN 3 WHEN 2 THEN 2 ELSE 1 END';

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
	 * Si un reto se vota por puestos: cuando cada votante tiene más de un voto.
	 *
	 * @param array $s Configuración del reto.
	 * @return bool
	 */
	public static function por_puestos( array $s ) {
		return (int) $s['votos_por_votante'] > 1;
	}

	/**
	 * Puntos de un puesto.
	 *
	 * @param int $puesto Puesto (1 a 3).
	 * @return int
	 */
	public static function puntos_de( $puesto ) {
		return max( 1, 4 - (int) $puesto );
	}

	/**
	 * Nombre corto de un puesto: 1.º, 2.º, 3.º.
	 *
	 * @param int $puesto Puesto.
	 * @return string
	 */
	public static function ordinal( $puesto ) {
		$ordinales = array(
			1 => __( '1st', 'foncholuis-art-challenge-manager' ),
			2 => __( '2nd', 'foncholuis-art-challenge-manager' ),
			3 => __( '3rd', 'foncholuis-art-challenge-manager' ),
		);

		return isset( $ordinales[ $puesto ] ) ? $ordinales[ $puesto ] : (string) $puesto;
	}

	/**
	 * Texto con los puntos o los votos de una foto, según cómo se vota el reto.
	 *
	 * @param int   $n Puntos.
	 * @param array $s Configuración del reto.
	 * @return string
	 */
	public static function etiqueta( $n, array $s ) {
		return self::por_puestos( $s )
			/* translators: %d: number of points. */
			? sprintf( _n( '%d point', '%d points', $n, 'foncholuis-art-challenge-manager' ), $n )
			/* translators: %d: number of votes. */
			: sprintf( _n( '%d vote', '%d votes', $n, 'foncholuis-art-challenge-manager' ), $n );
	}

	/**
	 * Puntos de cada foto de un reto.
	 *
	 * @param int $reto_id Reto.
	 * @return int[] entrada => puntos (solo las que tienen alguno).
	 */
	public static function counts( $reto_id ) {
		global $wpdb;

		$t    = Concursos_DB::votos();
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT entrada_id, SUM(' . self::PUNTOS_SQL . ') AS n FROM %i WHERE reto_id = %d GROUP BY entrada_id', $t, $reto_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- PUNTOS_SQL es una constante.

		$counts = array();
		foreach ( $rows as $row ) {
			$counts[ (int) $row->entrada_id ] = (int) $row->n;
		}

		return $counts;
	}

	/**
	 * Puntos de una foto.
	 *
	 * @param int $entrada_id Foto.
	 * @return int
	 */
	public static function count_entrada( $entrada_id ) {
		global $wpdb;

		$t = Concursos_DB::votos();

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(' . self::PUNTOS_SQL . '), 0) FROM %i WHERE entrada_id = %d', $t, $entrada_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- PUNTOS_SQL es una constante.
	}

	/**
	 * Puesto que un votante ha dado a cada foto de un reto.
	 *
	 * @param int $votante_id Votante.
	 * @param int $reto_id    Reto.
	 * @return int[] entrada => puesto (0 si se votó sin puesto).
	 */
	public static function puestos_de_votante( $votante_id, $reto_id ) {
		global $wpdb;

		$t    = Concursos_DB::votos();
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT entrada_id, puesto FROM %i WHERE votante_id = %d AND reto_id = %d', $t, $votante_id, $reto_id ) );

		$puestos = array();
		foreach ( $rows as $row ) {
			$puestos[ (int) $row->entrada_id ] = (int) $row->puesto;
		}

		return $puestos;
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

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT entrada_id FROM %i WHERE votante_id = %d AND reto_id = %d', $t, $votante_id, $reto_id ) ) );
	}

	/**
	 * Reparte los puestos según los puntos. Quienes tienen los mismos puntos
	 * comparten puesto; las fotos sin votos nunca reciben uno.
	 *
	 * @param int[] $votos   entrada => puntos, en el orden en que deben listarse los empates.
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
	 * Puntos de cada foto dada, con cero para las que no tienen.
	 *
	 * @param int       $reto_id  Reto.
	 * @param WP_Post[] $entradas Fotos, en el orden en que deben listarse los empates.
	 * @return int[] entrada => puntos.
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

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT votante_id) FROM %i WHERE reto_id = %d', $t, $reto_id ) );
	}

	/**
	 * Si una foto es del propio votante: el email de quien la envió es el suyo.
	 *
	 * @param int    $entrada_id Foto.
	 * @param object $votante    Votante.
	 * @return bool
	 */
	public static function es_propia( $entrada_id, $votante ) {
		$autor = Concursos_Entrada::owner( $entrada_id );
		$email = $autor ? (string) get_post_meta( $autor, Concursos_Participante::META . 'email', true ) : '';

		return '' !== $email && strtolower( $email ) === strtolower( (string) $votante->email );
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
			1 => array( __( 'First place', 'foncholuis-art-challenge-manager' ), __( 'Tied for first place', 'foncholuis-art-challenge-manager' ) ),
			2 => array( __( 'Second place', 'foncholuis-art-challenge-manager' ), __( 'Tied for second place', 'foncholuis-art-challenge-manager' ) ),
			3 => array( __( 'Third place', 'foncholuis-art-challenge-manager' ), __( 'Tied for third place', 'foncholuis-art-challenge-manager' ) ),
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
		if ( self::es_propia( $entrada, $votante ) ) {
			Concursos_Votantes::volver( $reto, 'propia' );
		}

		global $wpdb;
		$t = Concursos_DB::votos();

		// Votación por puestos: el puesto pasa a esta foto, y la foto pierde el que tuviera.
		if ( ! $quitar && self::por_puestos( $s ) ) {
			$puesto = isset( $_POST['puesto'] ) ? absint( $_POST['puesto'] ) : 0;
			if ( $puesto < 1 || $puesto > (int) $s['votos_por_votante'] ) {
				Concursos_Votantes::volver( $reto, 'error' );
			}
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE votante_id = %d AND reto_id = %d AND ( puesto = %d OR entrada_id = %d )', $t, (int) $votante->id, $reto, $puesto, $entrada ) );
			$wpdb->insert(
				$t,
				array(
					'votante_id' => (int) $votante->id,
					'reto_id'    => $reto,
					'entrada_id' => $entrada,
					'puesto'     => $puesto,
					'creado'     => time(),
				),
				array( '%d', '%d', '%d', '%d', '%d' )
			);
			Concursos_Stats::limpiar_cache();
			Concursos_Votantes::volver( $reto, 'votado' );
		}

		if ( $quitar ) {
			$wpdb->delete(
				$t,
				array(
					'votante_id' => (int) $votante->id,
					'entrada_id' => $entrada,
				),
				array( '%d', '%d' )
			);
			Concursos_Stats::limpiar_cache();
			Concursos_Votantes::volver( $reto, 'quitado' );
		}

		// Comprueba el límite en la misma sentencia que inserta: dos clics simultáneos no lo superan.
		$insertados = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (votante_id, reto_id, entrada_id, creado) SELECT %d, %d, %d, %d FROM DUAL WHERE (SELECT COUNT(*) FROM %i v WHERE v.votante_id = %d AND v.reto_id = %d) < %d',
				$t,
				(int) $votante->id,
				$reto,
				$entrada,
				time(),
				$t,
				(int) $votante->id,
				$reto,
				(int) $s['votos_por_votante']
			)
		);

		if ( $insertados ) {
			Concursos_Stats::limpiar_cache();
			Concursos_Votantes::volver( $reto, 'votado' );
		}
		$ya_votada = in_array( $entrada, self::de_votante( (int) $votante->id, $reto ), true );
		Concursos_Votantes::volver( $reto, $ya_votada ? 'votado' : 'limite' );
	}

	/**
	 * Botones de voto de una foto: un solo botón, o uno por puesto.
	 *
	 * @param int   $reto_id    Reto.
	 * @param array $s          Configuración del reto.
	 * @param int   $entrada_id Foto.
	 * @param int[] $mios       entrada => puesto que ya dio el votante.
	 * @return string HTML.
	 */
	public static function botones( $reto_id, array $s, $entrada_id, array $mios ) {
		$total = (int) $s['votos_por_votante'];
		if ( ! self::por_puestos( $s ) ) {
			return self::boton( $reto_id, $entrada_id, isset( $mios[ $entrada_id ] ), count( $mios ) < $total );
		}

		$campos = sprintf(
			'<input type="hidden" name="action" value="%1$s"><input type="hidden" name="_wpnonce" value="%2$s"><input type="hidden" name="reto" value="%3$d"><input type="hidden" name="entrada" value="%4$d">',
			esc_attr( self::ACTION ),
			esc_attr( wp_create_nonce( self::ACTION ) ),
			(int) $reto_id,
			(int) $entrada_id
		);

		// Foto con puesto: lo muestra y deja quitarlo.
		if ( ! empty( $mios[ $entrada_id ] ) ) {
			$puesto = $mios[ $entrada_id ];

			return sprintf(
				'<form method="post" action="%1$s" class="concursos-voto-form concursos-puestos">%2$s<input type="hidden" name="op" value="quitar"><span class="concursos-puestos__mio">%3$s</span><button type="submit" class="concursos-voto concursos-voto--quitar">%4$s</button></form>',
				esc_url( admin_url( 'admin-post.php' ) ),
				$campos, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
				/* translators: 1: place such as 1st, 2: number of points. */
				esc_html( sprintf( _n( 'Your %1$s · %2$d point', 'Your %1$s · %2$d points', self::puntos_de( $puesto ), 'foncholuis-art-challenge-manager' ), self::ordinal( $puesto ), self::puntos_de( $puesto ) ) ),
				esc_html__( 'Remove vote', 'foncholuis-art-challenge-manager' )
			);
		}

		$botones = '';
		$usados  = array_values( $mios );
		for ( $p = 1; $p <= $total; $p++ ) {
			$usado    = in_array( $p, $usados, true );
			$botones .= sprintf(
				'<button type="submit" name="puesto" value="%1$d" class="concursos-voto%2$s" aria-label="%3$s" title="%3$s">%4$s</button>',
				$p,
				$usado ? ' concursos-voto--usado' : '',
				esc_attr(
					$usado
						/* translators: %s: place such as 1st. */
						? sprintf( __( 'Move your %s to this photo', 'foncholuis-art-challenge-manager' ), self::ordinal( $p ) )
						/* translators: 1: place such as 1st, 2: number of points. */
						: sprintf( _n( 'Give this photo your %1$s (%2$d point)', 'Give this photo your %1$s (%2$d points)', self::puntos_de( $p ), 'foncholuis-art-challenge-manager' ), self::ordinal( $p ), self::puntos_de( $p ) )
				),
				esc_html( self::ordinal( $p ) )
			);
		}

		return sprintf(
			'<form method="post" action="%1$s" class="concursos-voto-form concursos-puestos">%2$s<input type="hidden" name="op" value="votar"><span class="concursos-puestos__texto">%3$s</span>%4$s</form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			$campos, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
			esc_html__( 'Vote:', 'foncholuis-art-challenge-manager' ),
			$botones // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
		);
	}

	/**
	 * Botón para votar una foto o retirar el voto, cuando cada votante tiene un solo voto.
	 *
	 * @param int  $reto_id    Reto.
	 * @param int  $entrada_id Foto.
	 * @param bool $votada     Si el votante ya la votó.
	 * @param bool $puede      Si le quedan votos.
	 * @return string HTML.
	 */
	public static function boton( $reto_id, $entrada_id, $votada, $puede ) {
		if ( ! $votada && ! $puede ) {
			return '<button type="button" class="concursos-voto" disabled>' . esc_html__( 'No votes left', 'foncholuis-art-challenge-manager' ) . '</button>';
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
			$votada ? esc_html__( 'Remove vote', 'foncholuis-art-challenge-manager' ) : esc_html__( 'Vote', 'foncholuis-art-challenge-manager' )
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
