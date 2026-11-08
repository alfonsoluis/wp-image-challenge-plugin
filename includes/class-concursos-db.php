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

	const VERSION = '1';
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
				creado bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY voto (votante_id,entrada_id),
				KEY reto_entrada (reto_id,entrada_id)
			) {$charset};"
		);

		update_option( self::OPTION, self::VERSION );
	}
}
