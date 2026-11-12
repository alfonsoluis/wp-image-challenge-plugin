<?php
/**
 * Modelo de un reto: configuración, validación y estado.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lee, guarda y valida la configuración de un reto y calcula su estado.
 */
class Concursos_Reto {

	const META_PREFIX = '_concursos_';

	/** Fechas del ciclo de un reto, en orden. */
	const FECHAS = array( 'inicio', 'fin_envios', 'fin_votacion', 'cierre' );

	/**
	 * Todos los retos con su configuración y estado, del más reciente al más antiguo.
	 *
	 * @param string[] $post_status Estados de publicación a incluir.
	 * @return array[] Cada uno: post, settings, status.
	 */
	public static function all( array $post_status = array( 'publish', 'future', 'draft', 'pending', 'private', Concursos_Post_Types::ARCHIVADO ) ) {
		$posts = get_posts(
			array(
				'post_type'      => Concursos_Post_Types::RETO,
				'post_status'    => $post_status,
				'posts_per_page' => -1,
				'orderby'        => 'meta_value_num',
				'meta_key'       => self::META_PREFIX . 'inicio', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'order'          => 'DESC',
			)
		);

		$retos = array();
		foreach ( $posts as $post ) {
			$settings = self::get_settings( $post->ID );
			$retos[]  = array(
				'post'     => $post,
				'settings' => $settings,
				'status'   => self::status( $settings ),
			);
		}

		return $retos;
	}

	/**
	 * Valores por defecto de la configuración.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'modo'              => 'abierto',
			'inicio'            => 0,
			'fin_envios'        => 0,
			'fin_votacion'      => 0,
			'cierre'            => 0,
			'votos_por_votante' => 1,
			'lugares'           => 1,
			'max_fotos'         => 1,
			'anonimo'           => 0,
			'premios'           => array( '', '', '' ),
			'reglas'            => '',
			'privado_participantes' => 0,
			'emails_participantes'  => '',
			'privado_votantes'      => 0,
			'emails_votantes'       => '',
			'estilo'            => Concursos_Diseno::TEMA,
			'acento'            => '',
			'tamano'            => Concursos_Diseno::NORMAL,
			'fondo_iconos'      => Concursos_Diseno::PAGINAS_FONDO,
		);
	}

	/**
	 * Etiquetas de las opciones de selección.
	 *
	 * @return array
	 */
	public static function choices() {
		return array(
			'modo'       => array(
				'abierto' => __( 'Open: photos are shown as they are submitted', 'photo-challenges' ),
				'cerrado' => __( 'Closed: photos are shown when submissions end', 'photo-challenges' ),
			),
			'lugares'    => array(
				1 => __( 'A single winner', 'photo-challenges' ),
				3 => __( 'Three places', 'photo-challenges' ),
			),
		);
	}

	/**
	 * Lee la configuración guardada de un reto.
	 *
	 * @param int $post_id ID del reto.
	 * @return array
	 */
	public static function get_settings( $post_id ) {
		$settings = self::defaults();
		foreach ( $settings as $key => $default ) {
			if ( metadata_exists( 'post', $post_id, self::META_PREFIX . $key ) ) {
				$settings[ $key ] = get_post_meta( $post_id, self::META_PREFIX . $key, true );
			}
		}
		$settings['premios'] = array_pad( array_values( (array) $settings['premios'] ), 3, '' );

		return $settings;
	}

	/**
	 * Guarda una configuración ya saneada.
	 *
	 * @param int   $post_id  ID del reto.
	 * @param array $settings Configuración.
	 */
	public static function save_settings( $post_id, array $settings ) {
		foreach ( self::defaults() as $key => $default ) {
			if ( array_key_exists( $key, $settings ) ) {
				update_post_meta( $post_id, self::META_PREFIX . $key, $settings[ $key ] );
			}
		}
	}

	/**
	 * Sanea la configuración que llega del formulario.
	 *
	 * @param array $raw Datos sin sanear (ya sin barras).
	 * @return array
	 */
	public static function sanitize( array $raw ) {
		$choices = self::choices();
		$s       = self::defaults();

		foreach ( array( 'modo' ) as $key ) {
			$value = isset( $raw[ $key ] ) ? sanitize_key( $raw[ $key ] ) : '';
			if ( isset( $choices[ $key ][ $value ] ) ) {
				$s[ $key ] = $value;
			}
		}

		foreach ( self::FECHAS as $key ) {
			$s[ $key ] = isset( $raw[ $key ] ) ? self::input_to_timestamp( sanitize_text_field( $raw[ $key ] ) ) : 0;
		}

		$s['votos_por_votante'] = isset( $raw['votos_por_votante'] ) ? min( 3, max( 1, absint( $raw['votos_por_votante'] ) ) ) : 1;
		$s['lugares']           = ( isset( $raw['lugares'] ) && 3 === absint( $raw['lugares'] ) ) ? 3 : 1;
		$s['max_fotos']         = isset( $raw['max_fotos'] ) ? min( 20, max( 1, absint( $raw['max_fotos'] ) ) ) : 1;
		$s['anonimo']           = empty( $raw['anonimo'] ) ? 0 : 1;

		$premios = isset( $raw['premios'] ) ? (array) $raw['premios'] : array();
		for ( $i = 0; $i < 3; $i++ ) {
			$s['premios'][ $i ] = isset( $premios[ $i ] ) ? sanitize_text_field( $premios[ $i ] ) : '';
		}

		$s['reglas'] = isset( $raw['reglas'] ) ? sanitize_textarea_field( $raw['reglas'] ) : '';

		foreach ( array( 'participantes', 'votantes' ) as $grupo ) {
			$s[ 'privado_' . $grupo ] = empty( $raw[ 'privado_' . $grupo ] ) ? 0 : 1;
			$s[ 'emails_' . $grupo ]  = implode( ', ', self::emails_validos( isset( $raw[ 'emails_' . $grupo ] ) ? (string) $raw[ 'emails_' . $grupo ] : '' ) );
		}

		return array_merge( $s, Concursos_Diseno::sanitize( $raw ) );
	}

	/**
	 * Comprueba que la configuración tenga sentido.
	 *
	 * @param array $s Configuración saneada.
	 * @return string[] Mensajes de error (vacío si es válida).
	 */
	public static function validate( array $s ) {
		$errors = array();
		$labels = self::date_labels();

		foreach ( self::FECHAS as $key ) {
			if ( ! $s[ $key ] ) {
				/* translators: %s: date name. */
				$errors[] = sprintf( __( 'Missing date: %s.', 'photo-challenges' ), $labels[ $key ] );
			}
		}
		if ( ! empty( $s['privado_participantes'] ) && '' === $s['emails_participantes'] ) {
			$errors[] = __( 'Participation is private but the list of emails is empty: nobody will be able to register.', 'photo-challenges' );
		}
		if ( ! empty( $s['privado_votantes'] ) && '' === $s['emails_votantes'] ) {
			$errors[] = __( 'Voting is private but the list of emails is empty: nobody will be able to vote.', 'photo-challenges' );
		}
		if ( $errors ) {
			return $errors;
		}

		for ( $i = 1; $i < count( self::FECHAS ); $i++ ) {
			$prev = self::FECHAS[ $i - 1 ];
			$curr = self::FECHAS[ $i ];
			if ( $s[ $curr ] < $s[ $prev ] ) {
				/* translators: 1: later date, 2: earlier date. */
				$errors[] = sprintf( __( '“%1$s” cannot be before “%2$s”.', 'photo-challenges' ), $labels[ $curr ], $labels[ $prev ] );
			}
		}

		return $errors;
	}

	/**
	 * Si hay que ocultar el título y el autor de las fotos: en un reto con
	 * votación anónima, desde que se ven las fotos hasta que termina la votación.
	 *
	 * @param array       $s      Configuración del reto.
	 * @param string|null $status Fase, si ya se conoce.
	 * @return bool
	 */
	public static function oculta_autoria( array $s, $status = null ) {
		if ( empty( $s['anonimo'] ) ) {
			return false;
		}

		return in_array( null === $status ? self::status( $s ) : $status, array( 'envios', 'votacion' ), true );
	}

	/**
	 * Separa una lista de emails escrita a mano.
	 *
	 * @param string $texto Emails separados por comas (también valen espacios, punto y coma o saltos de línea).
	 * @return string[]
	 */
	private static function partir_emails( $texto ) {
		return array_values( array_unique( array_filter( array_map( 'strtolower', preg_split( '/[\s,;]+/', trim( $texto ) ) ) ) ) );
	}

	/**
	 * Direcciones válidas de una lista.
	 *
	 * @param string $texto Lista.
	 * @return string[]
	 */
	public static function emails_validos( $texto ) {
		return array_values( array_filter( array_map( 'sanitize_email', self::partir_emails( $texto ) ), 'is_email' ) );
	}

	/**
	 * Direcciones no válidas de una lista, para avisar al guardar.
	 *
	 * @param string $texto Lista.
	 * @return string[]
	 */
	public static function emails_invalidos( $texto ) {
		return array_values(
			array_filter(
				self::partir_emails( $texto ),
				function ( $email ) {
					return ! is_email( sanitize_email( $email ) );
				}
			)
		);
	}

	/**
	 * ¿Puede este email participar (o votar) en el reto?
	 *
	 * @param array  $s     Configuración.
	 * @param string $grupo participantes|votantes.
	 * @param string $email Email.
	 * @return bool
	 */
	public static function permite_email( array $s, $grupo, $email ) {
		return empty( $s[ 'privado_' . $grupo ] ) || in_array( strtolower( $email ), self::emails_validos( $s[ 'emails_' . $grupo ] ), true );
	}

	/**
	 * Mensaje para quien no está en la lista de un reto privado.
	 *
	 * @return string
	 */
	public static function mensaje_privado() {
		return __( 'This challenge is private. Contact the organizers for more information.', 'photo-challenges' );
	}

	/**
	 * Estado del reto según sus fechas.
	 *
	 * @param array    $s   Configuración.
	 * @param int|null $now Momento de referencia (por defecto, ahora).
	 * @return string sin_fechas|proximo|envios|votacion|evaluacion|finalizado
	 */
	public static function status( array $s, $now = null ) {
		$now = null === $now ? time() : (int) $now;

		foreach ( self::FECHAS as $key ) {
			if ( empty( $s[ $key ] ) ) {
				return 'sin_fechas';
			}
		}
		if ( $now < $s['inicio'] ) {
			return 'proximo';
		}
		if ( $now < $s['fin_envios'] ) {
			return 'envios';
		}
		if ( $now < $s['fin_votacion'] ) {
			return 'votacion';
		}
		if ( $now < $s['cierre'] ) {
			return 'evaluacion';
		}

		return 'finalizado';
	}

	/**
	 * ¿Se muestran ya las fotos al público?
	 *
	 * Abierto: desde que empiezan los envíos. Cerrado: desde que terminan.
	 *
	 * @param array $s Configuración.
	 * @return bool
	 */
	public static function photos_visible( array $s ) {
		$visible = array( 'votacion', 'evaluacion', 'finalizado' );
		if ( 'abierto' === $s['modo'] ) {
			$visible[] = 'envios';
		}

		return in_array( self::status( $s ), $visible, true );
	}

	/**
	 * Etiquetas de los estados.
	 *
	 * @return array
	 */
	public static function status_labels() {
		return array(
			'sin_fechas' => __( 'No dates', 'photo-challenges' ),
			'proximo'    => __( 'Upcoming', 'photo-challenges' ),
			'envios'     => __( 'Accepting photos', 'photo-challenges' ),
			'votacion'   => __( 'Voting', 'photo-challenges' ),
			'evaluacion' => __( 'Under review', 'photo-challenges' ),
			'finalizado' => __( 'Finished', 'photo-challenges' ),
		);
	}

	/**
	 * Etiquetas de las fechas.
	 *
	 * @return array
	 */
	public static function date_labels() {
		return array(
			'inicio'       => __( 'Challenge start', 'photo-challenges' ),
			'fin_envios'   => __( 'End of submissions', 'photo-challenges' ),
			'fin_votacion' => __( 'End of voting', 'photo-challenges' ),
			'cierre'       => __( 'Publication of results', 'photo-challenges' ),
		);
	}

	/**
	 * Las reglas como lista: una por línea.
	 *
	 * @param string $reglas Texto de las reglas.
	 * @return string[]
	 */
	public static function rules_list( $reglas ) {
		return array_values( array_filter( array_map( 'trim', explode( "\n", (string) $reglas ) ) ) );
	}

	/**
	 * Convierte el valor de un campo datetime-local (hora del sitio) a timestamp.
	 *
	 * @param string $value Valor "Y-m-d\TH:i".
	 * @return int 0 si no es válido.
	 */
	public static function input_to_timestamp( $value ) {
		$date = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $value, wp_timezone() );

		return $date ? $date->getTimestamp() : 0;
	}

	/**
	 * Convierte un timestamp al valor de un campo datetime-local.
	 *
	 * @param int $timestamp Fecha.
	 * @return string
	 */
	public static function timestamp_to_input( $timestamp ) {
		return $timestamp ? wp_date( 'Y-m-d\TH:i', (int) $timestamp ) : '';
	}

	/**
	 * Formatea solo el día, para las páginas públicas.
	 *
	 * @param int $timestamp Fecha.
	 * @return string
	 */
	public static function format_dia( $timestamp ) {
		return $timestamp ? wp_date( get_option( 'date_format' ), (int) $timestamp ) : '—';
	}

	/**
	 * Formatea una fecha para mostrarla.
	 *
	 * @param int $timestamp Fecha.
	 * @return string
	 */
	public static function format_date( $timestamp ) {
		if ( ! $timestamp ) {
			return '—';
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $timestamp );
	}
}
