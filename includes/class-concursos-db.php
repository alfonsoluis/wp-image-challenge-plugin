<?php
/**
 * Tablas propias del plugin.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Crea y actualiza las tablas de votantes y votos.
 */
class Concursos_DB {

	const VERSION = '3';
	const OPTION  = 'concursos_db_version';

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );
	}

	/**
	 * Nombre de la tabla de votantes.
	 *
	 * @return string
	 */
	public static function votantes() {
		global $wpdb;

		return $wpdb->prefix . 'concursos_votantes';
	}

	/**
	 * Nombre de la tabla de votos.
	 *
	 * @return string
	 */
	public static function votos() {
		global $wpdb;

		return $wpdb->prefix . 'concursos_votos';
	}

	/**
	 * Instala las tablas si la versión guardada no coincide.
	 */
	public static function maybe_upgrade() {
		if ( self::VERSION !== get_option( self::OPTION ) ) {
			self::install();
		}
	}

	/**
	 * Pasa las paletas de la versión 1.0 a estilo y color de acento y borra el
	 * antiguo fondo de iconos.
	 */
	private static function migrar_paletas() {
		$metas = get_posts(
			array(
				'post_type'      => Concursos_Post_Types::RETO,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => Concursos_Reto::META_PREFIX . 'paleta', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);
		foreach ( $metas as $id ) {
			list( $estilo, $acento ) = Concursos_Diseno::desde_paleta( get_post_meta( $id, Concursos_Reto::META_PREFIX . 'paleta', true ) );
			update_post_meta( $id, Concursos_Reto::META_PREFIX . 'estilo', $estilo );
			update_post_meta( $id, Concursos_Reto::META_PREFIX . 'acento', $acento );
			delete_post_meta( $id, Concursos_Reto::META_PREFIX . 'paleta' );
		}
		delete_post_meta_by_key( Concursos_Reto::META_PREFIX . 'fondo_iconos' );
	}

	/**
	 * Crea o actualiza las tablas.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset  = $wpdb->get_charset_collate();
		$votantes = self::votantes();
		$votos    = self::votos();

		dbDelta(
			"CREATE TABLE {$votantes} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				email varchar(190) NOT NULL,
				estado varchar(20) NOT NULL DEFAULT 'pendiente',
				confirm_hash char(64) NOT NULL DEFAULT '',
				confirm_expira bigint(20) unsigned NOT NULL DEFAULT 0,
				suscrito tinyint(1) NOT NULL DEFAULT 0,
				creado bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY email (email),
				KEY confirm_hash (confirm_hash)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$votos} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				votante_id bigint(20) unsigned NOT NULL,
				reto_id bigint(20) unsigned NOT NULL,
				entrada_id bigint(20) unsigned NOT NULL,
				puesto tinyint(3) unsigned NOT NULL DEFAULT 0,
				creado bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY voto (votante_id,entrada_id),
				KEY reto_entrada (reto_id,entrada_id),
				KEY votante_reto (votante_id,reto_id)
			) {$charset};"
		);

		self::migrar_paletas();
		update_option( self::OPTION, self::VERSION );
	}
}
