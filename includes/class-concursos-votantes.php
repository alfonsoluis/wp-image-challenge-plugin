<?php
/**
 * Votantes: registro con email confirmado, identidad en el navegador y avisos.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registra votantes, confirma su email y gestiona la suscripción a nuevos retos.
 */
class Concursos_Votantes {

	const COOKIE      = 'concursos_votante';
	const REGISTRO    = 'concursos_votante_registro';
	const SUSCRIPCION = 'concursos_votante_suscripcion';
	const SALIR       = 'concursos_votante_salir';

	const CONFIRM_PARAM = 'concursos_confirmar';
	const BAJA_PARAM    = 'concursos_baja';
	const AVISO_PARAM   = 'concursos_voto';

	const CONFIRM_TTL  = WEEK_IN_SECONDS;
	const REENVIO_MIN  = 5 * MINUTE_IN_SECONDS;
	const COOKIE_VIDA  = YEAR_IN_SECONDS;

	const PENDIENTE  = 'pendiente';
	const CONFIRMADO = 'confirmado';

	/**
	 * Votante identificado en esta petición (false = aún sin calcular).
	 *
	 * @var object|null|false
	 */
	private static $actual = false;

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		foreach ( array( self::REGISTRO => 'handle_registro', self::SUSCRIPCION => 'handle_suscripcion', self::SALIR => 'handle_salir' ) as $action => $method ) {
			add_action( 'admin_post_nopriv_' . $action, array( __CLASS__, $method ) );
			add_action( 'admin_post_' . $action, array( __CLASS__, $method ) );
		}
		add_action( 'template_redirect', array( __CLASS__, 'enlaces' ) );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'avisar_nuevo_reto' ), 10, 4 );
	}

	/**
	 * Firma de un votante para un propósito (sesión del navegador o baja).
	 *
	 * @param object $votante   Votante.
	 * @param string $proposito Propósito.
	 * @return string
	 */
	private static function firma( $votante, $proposito ) {
		return $votante->id . '.' . hash_hmac( 'sha256', $proposito . '|' . $votante->id . '|' . $votante->email, wp_salt( 'auth' ) );
	}

	/**
	 * Comprueba una firma y devuelve su votante.
	 *
	 * @param string $valor     Valor "id.hmac".
	 * @param string $proposito Propósito.
	 * @return object|null
	 */
	private static function desde_firma( $valor, $proposito ) {
		if ( ! preg_match( '/^(\d+)\.[a-f0-9]{64}$/', (string) $valor, $m ) ) {
			return null;
		}
		$votante = self::by_id( (int) $m[1] );

		return ( $votante && hash_equals( self::firma( $votante, $proposito ), $valor ) ) ? $votante : null;
	}

	/**
	 * Votante por ID.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	private static function by_id( $id ) {
		global $wpdb;

		$t = Concursos_DB::votantes();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Votante confirmado de este navegador.
	 *
	 * @return object|null
	 */
	public static function actual() {
		if ( false === self::$actual ) {
			$cookie  = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
			$votante = self::desde_firma( $cookie, 'sesion' );

			self::$actual = ( $votante && self::CONFIRMADO === $votante->estado ) ? $votante : null;
		}

		return self::$actual;
	}

	/**
	 * Vuelve a la página del reto con un aviso.
	 *
	 * @param int    $reto_id Reto.
	 * @param string $code    Aviso.
	 */
	public static function volver( $reto_id, $code ) {
		$url = Concursos_Post_Types::RETO === get_post_type( $reto_id ) ? get_permalink( $reto_id ) : home_url( '/' );
		wp_safe_redirect( add_query_arg( self::AVISO_PARAM, $code, $url ) . '#concursos-votacion' );
		exit;
	}

	/**
	 * Avisos tras una acción de voto.
	 *
	 * @return array código => array( tipo, texto ).
	 */
	private static function avisos() {
		return array(
			'revisa'      => array( 'success', __( 'Te enviamos un email: abre su enlace para confirmar tu dirección y poder votar.', 'concursos' ) ),
			'espera'      => array( 'success', __( 'Ya te enviamos un enlace a ese email hace unos minutos. Revisa tu bandeja de entrada y la carpeta de spam. Si no llega, vuelve a pedirlo en 5 minutos.', 'concursos' ) ),
			'confirmado'  => array( 'success', __( '¡Email confirmado! Ya puedes votar.', 'concursos' ) ),
			'votado'      => array( 'success', __( 'Voto registrado.', 'concursos' ) ),
			'quitado'     => array( 'success', __( 'Voto retirado.', 'concursos' ) ),
			'suscrito'    => array( 'success', __( 'Te avisaremos cuando haya un nuevo reto.', 'concursos' ) ),
			'desuscrito'  => array( 'success', __( 'Ya no recibirás avisos de nuevos retos.', 'concursos' ) ),
			'salida'      => array( 'success', __( 'Has cerrado tu sesión de votante en este navegador.', 'concursos' ) ),
			'email'       => array( 'error', __( 'Escribe un email válido.', 'concursos' ) ),
			'limite'      => array( 'error', __( 'Ya usaste todos tus votos en este reto. Quita uno para votar por otra foto.', 'concursos' ) ),
			'cerrado'     => array( 'error', __( 'La votación de este reto no está abierta.', 'concursos' ) ),
			'identificate' => array( 'error', __( 'Confirma tu email para poder votar.', 'concursos' ) ),
			'error'       => array( 'error', __( 'No pudimos completar la acción. Inténtalo de nuevo.', 'concursos' ) ),
		);
	}

	/**
	 * Aviso de la última acción, si lo hay.
	 *
	 * @return string HTML.
	 */
	public static function aviso_html() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo elige qué mensaje mostrar.
		$code   = isset( $_GET[ self::AVISO_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::AVISO_PARAM ] ) ) : '';
		$avisos = self::avisos();
		if ( ! isset( $avisos[ $code ] ) ) {
			return '';
		}

		return sprintf( '<p class="concursos-aviso concursos-aviso--%s" role="status">%s</p>', esc_attr( $avisos[ $code ][0] ), esc_html( $avisos[ $code ][1] ) );
	}

	/**
	 * Registro de un votante: crea la cuenta si no existe y envía el enlace de confirmación.
	 *
	 * Responde igual exista o no el email, para no revelar quién está registrado:
	 * solo avisa si ese email ya pidió un enlace hace menos de 5 minutos.
	 * Registrarse nunca da de baja, y la suscripción no se activa hasta confirmar el email.
	 */
	public static function handle_registro() {
		check_admin_referer( self::REGISTRO );

		$reto = isset( $_POST['reto'] ) ? absint( $_POST['reto'] ) : 0;

		// Campo trampa para bots: se finge éxito sin hacer nada.
		if ( ! empty( $_POST['web'] ) ) {
			self::volver( $reto, 'revisa' );
		}

		$email = isset( $_POST['email'] ) ? strtolower( sanitize_email( wp_unslash( $_POST['email'] ) ) ) : '';
		if ( ! is_email( $email ) || strlen( $email ) > 190 ) {
			self::volver( $reto, 'email' );
		}

		global $wpdb;
		$t       = Concursos_DB::votantes();
		$votante = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE email = %s", $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $votante ) {
			$wpdb->insert(
				$t,
				array(
					'email'  => $email,
					'estado' => self::PENDIENTE,
					'creado' => time(),
				),
				array( '%s', '%s', '%d' )
			);
			$votante = self::by_id( (int) $wpdb->insert_id );
		}

		// No reenviar si acaba de pedirse: evita usar el formulario para saturar un buzón.
		$emitido = $votante ? (int) $votante->confirm_expira - self::CONFIRM_TTL : 0;
		if ( ! $votante ) {
			self::volver( $reto, 'error' );
		}
		if ( time() - $emitido < self::REENVIO_MIN ) {
			self::volver( $reto, 'espera' );
		}

		self::enviar_confirmacion( $votante, $reto, ! empty( $_POST['suscribir'] ) );
		self::volver( $reto, 'revisa' );
	}

	/**
	 * Emite un enlace de confirmación y lo envía.
	 *
	 * @param object $votante   Votante.
	 * @param int    $reto_id   Reto desde el que se registró.
	 * @param bool   $suscribir Si pidió avisos de nuevos retos.
	 */
	private static function enviar_confirmacion( $votante, $reto_id, $suscribir ) {
		global $wpdb;

		$token = bin2hex( random_bytes( 16 ) );
		$wpdb->update(
			Concursos_DB::votantes(),
			array(
				'confirm_hash'   => hash( 'sha256', $token ),
				'confirm_expira' => time() + self::CONFIRM_TTL,
			),
			array( 'id' => (int) $votante->id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		$args = array(
			self::CONFIRM_PARAM => $token,
			'reto'              => (int) $reto_id,
		);
		if ( $suscribir ) {
			$args['avisos'] = 1;
		}

		wp_mail(
			$votante->email,
			/* translators: %s: nombre del sitio. */
			sprintf( __( 'Confirma tu email para votar en %s', 'concursos' ), get_bloginfo( 'name' ) ),
			sprintf(
				/* translators: 1: nombre del reto, 2: enlace, 3: nombre del sitio. */
				__( "Hola:\n\nAbre este enlace para confirmar tu email y votar en «%1\$s»:\n\n%2\$s\n\nEl enlace caduca en 7 días. Si no fuiste tú, ignora este mensaje.\n\n%3\$s", 'concursos' ),
				get_the_title( $reto_id ),
				add_query_arg( $args, home_url( '/' ) ),
				get_bloginfo( 'name' )
			)
		);
	}

	/**
	 * Atiende los enlaces que llegan por email.
	 */
	public static function enlaces() {
		// phpcs:disable WordPress.Security.NonceVerification -- el token de cada enlace es la credencial.
		if ( isset( $_GET[ self::CONFIRM_PARAM ] ) ) {
			self::confirmar(
				sanitize_key( wp_unslash( $_GET[ self::CONFIRM_PARAM ] ) ),
				isset( $_GET['reto'] ) ? absint( $_GET['reto'] ) : 0,
				! empty( $_GET['avisos'] )
			);
		}
		if ( isset( $_GET[ self::BAJA_PARAM ] ) ) {
			self::baja( sanitize_text_field( wp_unslash( $_GET[ self::BAJA_PARAM ] ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * Confirma un email e identifica a este navegador.
	 *
	 * El enlace sirve varias veces mientras no caduque: los filtros de correo
	 * abren los enlaces antes que la persona y no deben gastarlo.
	 *
	 * @param string $token     Token.
	 * @param int    $reto_id   Reto al que volver.
	 * @param bool   $suscribir Si pidió avisos.
	 */
	private static function confirmar( $token, $reto_id, $suscribir ) {
		global $wpdb;

		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );

		$t       = Concursos_DB::votantes();
		$votante = preg_match( '/^[a-f0-9]{32}$/', $token )
			? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE confirm_hash = %s", hash( 'sha256', $token ) ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			: null;

		if ( ! $votante || (int) $votante->confirm_expira < time() ) {
			Concursos_Envio::render_message( 404, __( 'Enlace no válido', 'concursos' ), __( 'Este enlace no es válido o ha caducado. Vuelve a registrarte con tu email para recibir uno nuevo.', 'concursos' ) );
		}

		$datos = array( 'estado' => self::CONFIRMADO );
		if ( $suscribir ) {
			$datos['suscrito'] = 1;
		}
		$wpdb->update( $t, $datos, array( 'id' => (int) $votante->id ) );

		self::set_cookie( self::firma( $votante, 'sesion' ), time() + self::COOKIE_VIDA );
		self::volver( $reto_id, 'confirmado' );
	}

	/**
	 * Escribe la cookie del votante.
	 *
	 * @param string $value   Valor.
	 * @param int    $expires Caducidad.
	 */
	private static function set_cookie( $value, $expires ) {
		setcookie(
			self::COOKIE,
			$value,
			array(
				'expires'  => $expires,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Activa o desactiva los avisos desde la página del reto.
	 */
	public static function handle_suscripcion() {
		check_admin_referer( self::SUSCRIPCION );

		$reto    = isset( $_POST['reto'] ) ? absint( $_POST['reto'] ) : 0;
		$votante = self::actual();
		if ( ! $votante ) {
			self::volver( $reto, 'identificate' );
		}

		$activar = ! empty( $_POST['suscribir'] );
		self::set_suscrito( $votante, $activar );
		self::volver( $reto, $activar ? 'suscrito' : 'desuscrito' );
	}

	/**
	 * Guarda la suscripción.
	 *
	 * @param object $votante Votante.
	 * @param bool   $activo  Si recibe avisos.
	 */
	private static function set_suscrito( $votante, $activo ) {
		global $wpdb;

		$wpdb->update( Concursos_DB::votantes(), array( 'suscrito' => $activo ? 1 : 0 ), array( 'id' => (int) $votante->id ), array( '%d' ), array( '%d' ) );
	}

	/**
	 * Olvida al votante en este navegador.
	 */
	public static function handle_salir() {
		check_admin_referer( self::SALIR );

		self::set_cookie( '', time() - YEAR_IN_SECONDS );
		self::volver( isset( $_POST['reto'] ) ? absint( $_POST['reto'] ) : 0, 'salida' );
	}

	/**
	 * Enlace de baja de un votante.
	 *
	 * @param object $votante Votante.
	 * @return string
	 */
	private static function enlace_baja( $votante ) {
		return add_query_arg( self::BAJA_PARAM, self::firma( $votante, 'baja' ), home_url( '/' ) );
	}

	/**
	 * Página de baja. Pide un clic para que los filtros de correo, que abren
	 * los enlaces, no den de baja a nadie.
	 *
	 * @param string $valor Firma del enlace.
	 */
	private static function baja( $valor ) {
		nocache_headers();

		$votante = self::desde_firma( $valor, 'baja' );
		if ( ! $votante ) {
			Concursos_Envio::render_message( 404, __( 'Enlace no válido', 'concursos' ), __( 'Este enlace de baja no es válido.', 'concursos' ) );
		}

		$titulo = __( 'Avisos de nuevos retos', 'concursos' );

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			self::set_suscrito( $votante, false );
			Concursos_Envio::render_message( 200, $titulo, __( 'Listo: ya no recibirás avisos de nuevos retos.', 'concursos' ) );
		}

		Concursos_Envio::layout(
			200,
			$titulo,
			sprintf(
				'<h1>%1$s</h1><p>%2$s</p><form method="post"><button type="submit">%3$s</button></form>',
				esc_html( $titulo ),
				/* translators: %s: email del votante. */
				esc_html( sprintf( __( '¿Dejar de enviar avisos de nuevos retos a %s?', 'concursos' ), $votante->email ) ),
				esc_html__( 'Darme de baja', 'concursos' )
			)
		);
	}

	/**
	 * Avisa a los suscritos cuando se publica un reto que no ha terminado.
	 *
	 * Usa este hook, y no el de cambio de estado, porque aquí ya están guardadas las fechas.
	 *
	 * @param int          $post_id     Post.
	 * @param WP_Post      $post        Post.
	 * @param bool         $update      Si es una actualización.
	 * @param WP_Post|null $post_before Post antes de guardar.
	 */
	public static function avisar_nuevo_reto( $post_id, $post, $update, $post_before ) {
		if ( Concursos_Post_Types::RETO !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}
		if ( $post_before && 'publish' === $post_before->post_status ) {
			return;
		}
		if ( 'finalizado' === Concursos_Reto::status( Concursos_Reto::get_settings( $post_id ) ) ) {
			return;
		}

		global $wpdb;
		$t         = Concursos_DB::votantes();
		$suscritos = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE suscrito = 1 AND estado = %s", self::CONFIRMADO ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( $suscritos as $votante ) {
			$baja = self::enlace_baja( $votante );
			wp_mail(
				$votante->email,
				/* translators: %s: nombre del reto. */
				sprintf( __( 'Nuevo reto: %s', 'concursos' ), get_the_title( $post ) ),
				sprintf(
					/* translators: 1: nombre del reto, 2: enlace al reto, 3: enlace de baja, 4: nombre del sitio. */
					__( "Hola:\n\nHay un nuevo reto: «%1\$s».\n\n%2\$s\n\n%4\$s\n\nPara dejar de recibir estos avisos: %3\$s", 'concursos' ),
					get_the_title( $post ),
					get_permalink( $post ),
					$baja,
					get_bloginfo( 'name' )
				),
				array( 'List-Unsubscribe: <' . $baja . '>' )
			);
		}
	}

	/**
	 * Panel de votación: registro para quien no se ha identificado o estado de sus votos.
	 *
	 * @param int   $reto_id Reto.
	 * @param array $s       Configuración del reto.
	 * @return string HTML.
	 */
	public static function panel_html( $reto_id, array $s ) {
		$votante = self::actual();

		ob_start();
		echo '<div class="concursos-panel-voto">';

		if ( ! $votante ) {
			?>
			<h3><?php esc_html_e( 'Registra tu email para votar', 'concursos' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="concursos-form-publico">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::REGISTRO ); ?>">
				<input type="hidden" name="reto" value="<?php echo esc_attr( $reto_id ); ?>">
				<?php wp_nonce_field( self::REGISTRO ); ?>
				<p class="concursos-hp" aria-hidden="true">
					<label><?php esc_html_e( 'No rellenes este campo', 'concursos' ); ?> <input type="text" name="web" tabindex="-1" autocomplete="off"></label>
				</p>
				<p>
					<label for="concursos-votante-email"><?php esc_html_e( 'Email', 'concursos' ); ?></label>
					<input type="email" id="concursos-votante-email" name="email" required autocomplete="email">
				</p>
				<p class="concursos-casilla">
					<label><input type="checkbox" name="suscribir" value="1"> <?php esc_html_e( 'Avisarme por email cuando haya un nuevo reto', 'concursos' ); ?></label>
				</p>
				<p><button type="submit"><?php esc_html_e( 'Enviarme el enlace', 'concursos' ); ?></button></p>
				<p><small><?php esc_html_e( 'Usamos tu email solo para confirmar que eres una persona real y, si lo marcas, para avisarte de nuevos retos. Puedes darte de baja cuando quieras.', 'concursos' ); ?></small></p>
			</form>
			<?php
		} else {
			$usados = count( Concursos_Votos::de_votante( (int) $votante->id, $reto_id ) );
			$total  = (int) $s['votos_por_votante'];
			$quedan = max( 0, $total - $usados );
			?>
			<p>
				<strong>
					<?php
					/* translators: %s: email del votante. */
					echo esc_html( sprintf( __( 'Votas como %s', 'concursos' ), $votante->email ) );
					?>
				</strong><br>
				<?php
				/* translators: 1: votos que quedan, 2: votos en total. */
				echo esc_html( sprintf( __( 'Te quedan %1$d de %2$d votos.', 'concursos' ), $quedan, $total ) );
				?>
			</p>
			<div class="concursos-panel-voto__acciones">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::SUSCRIPCION ); ?>">
					<input type="hidden" name="reto" value="<?php echo esc_attr( $reto_id ); ?>">
					<input type="hidden" name="suscribir" value="<?php echo $votante->suscrito ? '' : '1'; ?>">
					<?php wp_nonce_field( self::SUSCRIPCION, '_wpnonce', false ); ?>
					<button type="submit" class="concursos-enlace"><?php echo $votante->suscrito ? esc_html__( 'Dejar de recibir avisos de nuevos retos', 'concursos' ) : esc_html__( 'Avisarme de nuevos retos', 'concursos' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::SALIR ); ?>">
					<input type="hidden" name="reto" value="<?php echo esc_attr( $reto_id ); ?>">
					<?php wp_nonce_field( self::SALIR, '_wpnonce', false ); ?>
					<button type="submit" class="concursos-enlace"><?php esc_html_e( 'Salir', 'concursos' ); ?></button>
				</form>
			</div>
			<?php
		}

		echo '</div>';

		return ob_get_clean();
	}
}
