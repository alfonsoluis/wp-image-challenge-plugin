<?php
/**
 * Espacio personal del participante: subir y borrar sus fotos.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Página a la que lleva el enlace personal (?concursos_envio=TOKEN).
 *
 * Es una página propia, independiente del tema, para que funcione igual con
 * cualquier tema clásico o de bloques.
 */
class Concursos_Envio {

	const RESULT_PARAM = 'resultado';

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'route' ) );
	}

	/**
	 * Mensajes tras una acción.
	 *
	 * @return array código => array( tipo, texto ).
	 */
	private static function messages() {
		return array(
			'subida'    => array( 'success', __( 'Photo submitted!', 'foncholuis-art-challenge-manager' ) ),
			'eliminada' => array( 'success', __( 'Photo deleted.', 'foncholuis-art-challenge-manager' ) ),
			'cerrado'   => array( 'error', __( 'The submission period is not open.', 'foncholuis-art-challenge-manager' ) ),
			'limite'    => array( 'error', __( 'You have already submitted the maximum number of photos for this challenge.', 'foncholuis-art-challenge-manager' ) ),
			'foto'      => array( 'error', sprintf(
				/* translators: %s: maximum file size, such as 5 MB. */
				__( 'The photo must be a JPG, PNG or WebP of up to %s.', 'foncholuis-art-challenge-manager' ),
				size_format( Concursos_Imagenes::limite() )
			) ),
			'error'     => array( 'error', __( 'We could not complete the action. Please try again.', 'foncholuis-art-challenge-manager' ) ),
		);
	}

	/**
	 * Atiende el enlace personal: identifica al participante, procesa acciones y pinta la página.
	 */
	public static function route() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- el token es la credencial.
		if ( ! isset( $_GET[ Concursos_Inscripcion::LINK_PARAM ] ) ) {
			return;
		}

		// El token va en la URL: que no se filtre a otros sitios ni quede en cachés.
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- el token es la credencial.
		$token = sanitize_text_field( wp_unslash( $_GET[ Concursos_Inscripcion::LINK_PARAM ] ) );
		$id    = Concursos_Participante::find_by_token( $token );
		if ( ! $id ) {
			self::render_message( 404, __( 'Invalid link', 'foncholuis-art-challenge-manager' ), __( 'This link is not valid. Check that you copied all of it.', 'foncholuis-art-challenge-manager' ), self::enlace_reto( 0 ) );
		}

		$p = Concursos_Participante::get( $id );
		if ( '' === self::estado_reto( $p['reto'] ) ) {
			self::render_message( 404, __( 'Challenge not available', 'foncholuis-art-challenge-manager' ), __( 'This challenge is no longer available.', 'foncholuis-art-challenge-manager' ), self::enlace_reto( 0 ) );
		}

		if ( Concursos_Participante::ACTIVO !== $p['estado'] ) {
			self::render_message( 403, __( 'Registration not active', 'foncholuis-art-challenge-manager' ), __( 'Your registration is not active. If you think this is a mistake, contact us.', 'foncholuis-art-challenge-manager' ), self::enlace_reto( $p['reto'] ) );
		}

		if ( ! Concursos_Reto::permite_email( Concursos_Reto::get_settings( $p['reto'] ), 'participantes', $p['email'] ) ) {
			self::render_message( 403, __( 'Private challenge', 'foncholuis-art-challenge-manager' ), Concursos_Reto::mensaje_privado(), self::enlace_reto( $p['reto'] ) );
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) {
			$code = self::handle_post( $p );
			wp_safe_redirect( add_query_arg( self::RESULT_PARAM, $code, Concursos_Inscripcion::personal_link( $token ) ) );
			exit;
		}

		self::render_page( $p );
	}

	/**
	 * Procesa una subida o un borrado.
	 *
	 * @param array $p Participante.
	 * @return string Código de resultado.
	 */
	private static function handle_post( array $p ) {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'concursos_envio_' . $p['id'] ) ) {
			return 'error';
		}

		$reto = Concursos_Reto::get_settings( $p['reto'] );
		if ( 'publish' !== get_post_status( $p['reto'] ) || 'envios' !== Concursos_Reto::status( $reto ) ) {
			return 'cerrado';
		}

		if ( isset( $_POST['eliminar'] ) ) {
			$entrada = absint( $_POST['eliminar'] );
			if ( Concursos_Entrada::POST_TYPE !== get_post_type( $entrada ) || Concursos_Entrada::owner( $entrada ) !== $p['id'] ) {
				return 'error';
			}
			wp_delete_post( $entrada, true );

			return 'eliminada';
		}

		$enviadas = Concursos_Entrada::find(
			array(
				'reto'         => $p['reto'],
				'participante' => $p['id'],
			)
		);
		if ( count( $enviadas ) >= (int) $reto['max_fotos'] ) {
			return 'limite';
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se valida en valid_image() y lo maneja media_handle_upload().
		$foto = isset( $_FILES['foto'] ) ? $_FILES['foto'] : null;
		if ( ! $foto || ! Concursos_Inscripcion::valid_image( $foto, Concursos_Imagenes::limite() ) ) {
			return 'foto';
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = Concursos_Imagenes::subir( 'foto', 0 );
		if ( is_wp_error( $attachment_id ) ) {
			return 'foto';
		}

		$titulo  = isset( $_POST['titulo'] ) ? sanitize_text_field( wp_unslash( $_POST['titulo'] ) ) : '';
		$descripcion = isset( $_POST['descripcion'] ) ? sanitize_textarea_field( wp_unslash( $_POST['descripcion'] ) ) : '';
		$entrada     = Concursos_Entrada::create( $p, mb_substr( $titulo, 0, 100 ), $attachment_id, mb_substr( $descripcion, 0, Concursos_Entrada::DESCRIPCION_MAX ) );
		if ( is_wp_error( $entrada ) ) {
			wp_delete_attachment( $attachment_id, true );

			return 'error';
		}

		return 'subida';
	}

	/**
	 * Pinta el espacio del participante.
	 *
	 * @param array $p Participante.
	 */
	private static function render_page( array $p ) {
		$reto     = Concursos_Reto::get_settings( $p['reto'] );
		$status   = 'publish' === get_post_status( $p['reto'] ) ? Concursos_Reto::status( $reto ) : 'sin_fechas';
		$abierto  = 'envios' === $status;
		$enviadas = Concursos_Entrada::find(
			array(
				'reto'         => $p['reto'],
				'participante' => $p['id'],
			)
		);
		$max      = (int) $reto['max_fotos'];
		$quedan   = max( 0, $max - count( $enviadas ) );
		$messages = self::messages();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo elige qué mensaje mostrar.
		$code  = isset( $_GET[ self::RESULT_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT_PARAM ] ) ) : '';
		$nonce = wp_create_nonce( 'concursos_envio_' . $p['id'] );

		ob_start();
		?>
		<h1>
			<?php
			/* translators: %s: participant name. */
			echo esc_html( sprintf( __( 'Hello, %s', 'foncholuis-art-challenge-manager' ), $p['nombre'] ) );
			?>
		</h1>
		<p class="concursos-reto-nombre"><?php echo esc_html( get_the_title( $p['reto'] ) ); ?></p>

		<?php if ( isset( $messages[ $code ] ) ) : ?>
			<p class="concursos-aviso concursos-aviso--<?php echo esc_attr( $messages[ $code ][0] ); ?>" role="status"><?php echo esc_html( $messages[ $code ][1] ); ?></p>
		<?php endif; ?>

		<p class="concursos-plazo">
			<?php
			if ( $abierto ) {
				/* translators: %s: submission end date. */
				echo esc_html( sprintf( __( 'You can submit photos until %s.', 'foncholuis-art-challenge-manager' ), Concursos_Reto::format_dia( $reto['fin_envios'] ) ) );
			} elseif ( 'proximo' === $status ) {
				/* translators: %s: challenge start date. */
				echo esc_html( sprintf( __( 'Photo submissions start on %s.', 'foncholuis-art-challenge-manager' ), Concursos_Reto::format_dia( $reto['inicio'] ) ) );
			} else {
				// Cerrado: se dice además en qué fase está y su fecha (votación, resultados).
				echo esc_html( trim( __( 'The submission period for this challenge is closed.', 'foncholuis-art-challenge-manager' ) . ' ' . Concursos_Galeria::frase_estado( $status, $reto ) ) );
			}
			?>
		</p>
		<?php echo $abierto ? '' : self::enlace_reto( $p['reto'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>

		<h2>
			<?php
			/* translators: 1: photos submitted, 2: maximum photos. */
			echo esc_html( sprintf( __( 'Your photos (%1$d of %2$d)', 'foncholuis-art-challenge-manager' ), count( $enviadas ), $max ) );
			?>
		</h2>
		<?php if ( $enviadas ) : ?>
			<ul class="concursos-mis-fotos">
				<?php foreach ( $enviadas as $entrada ) : ?>
					<li>
						<?php echo get_the_post_thumbnail( $entrada, 'medium' ); ?>
						<span><?php echo esc_html( get_the_title( $entrada ) ); ?></span>
						<?php if ( $abierto ) : ?>
							<form method="post" data-confirmar="<?php esc_attr_e( 'Delete this photo?', 'foncholuis-art-challenge-manager' ); ?>">
								<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
								<button type="submit" name="eliminar" value="<?php echo esc_attr( $entrada->ID ); ?>" class="concursos-boton-secundario"><?php esc_html_e( 'Delete', 'foncholuis-art-challenge-manager' ); ?></button>
							</form>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'You have not submitted any photos yet.', 'foncholuis-art-challenge-manager' ); ?></p>
		<?php endif; ?>

		<?php if ( $abierto && $quedan > 0 ) : ?>
			<h2><?php esc_html_e( 'Submit a photo', 'foncholuis-art-challenge-manager' ); ?></h2>
			<form method="post" enctype="multipart/form-data" class="concursos-form-publico">
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
				<p>
					<label for="concursos-foto"><?php esc_html_e( 'Photo', 'foncholuis-art-challenge-manager' ); ?> *</label>
					<input type="file" id="concursos-foto" name="foto" required accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
					<small><?php 
						/* translators: %s: maximum file size, such as 5 MB. */
						echo esc_html( sprintf( __( 'JPG, PNG or WebP, up to %s.', 'foncholuis-art-challenge-manager' ), size_format( Concursos_Imagenes::limite() ) ) );
						 ?></small>
				</p>
				<p>
					<label for="concursos-titulo"><?php esc_html_e( 'Title (optional)', 'foncholuis-art-challenge-manager' ); ?></label>
					<input type="text" id="concursos-titulo" name="titulo" maxlength="100">
				</p>
				<p>
					<label for="concursos-descripcion"><?php esc_html_e( 'Description (optional)', 'foncholuis-art-challenge-manager' ); ?></label>
					<textarea id="concursos-descripcion" name="descripcion" rows="3" maxlength="<?php echo esc_attr( Concursos_Entrada::DESCRIPCION_MAX ); ?>"></textarea>
				</p>
				<p><button type="submit"><?php esc_html_e( 'Submit photo', 'foncholuis-art-challenge-manager' ); ?></button></p>
			</form>
		<?php endif; ?>

		<?php
		$rules = Concursos_Reto::rules_list( $reto['reglas'] );
		if ( $rules ) :
			?>
			<h2><?php esc_html_e( 'Rules', 'foncholuis-art-challenge-manager' ); ?></h2>
			<ul>
				<?php foreach ( $rules as $rule ) : ?>
					<li><?php echo esc_html( $rule ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<p class="concursos-privado"><?php esc_html_e( 'This page is personal: do not share its link.', 'foncholuis-art-challenge-manager' ); ?></p>
		<?php
		self::layout( 200, __( 'Your photos', 'foncholuis-art-challenge-manager' ), ob_get_clean(), $reto );
	}

	/**
	 * Pinta un mensaje simple (enlace inválido, inscripción inactiva).
	 *
	 * @param int    $status  Código HTTP.
	 * @param string $title   Título.
	 * @param string $message Mensaje.
	 * @param string $extra   HTML ya escapado bajo el mensaje, como un enlace al reto.
	 */
	public static function render_message( $status, $title, $message, $extra = '' ) {
		self::layout( $status, $title, '<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p>' . $extra );
	}

	/**
	 * Fase de un reto visible en el sitio.
	 *
	 * @param int $reto_id Reto.
	 * @return string Fase, o vacío si no existe o no está publicado.
	 */
	public static function estado_reto( $reto_id ) {
		if ( ! $reto_id || Concursos_Post_Types::RETO !== get_post_type( $reto_id ) || 'publish' !== get_post_status( $reto_id ) ) {
			return '';
		}

		return Concursos_Reto::status( Concursos_Reto::get_settings( $reto_id ) );
	}

	/**
	 * Dirección de la página con todos los retos.
	 *
	 * @return string
	 */
	public static function url_retos() {
		$pagina = (int) get_option( Concursos_Portada::OPCION_PAGINA );
		if ( $pagina && 'publish' === get_post_status( $pagina ) ) {
			return get_permalink( $pagina );
		}
		$archivo = get_post_type_archive_link( Concursos_Post_Types::RETO );

		return $archivo ? $archivo : home_url( '/' );
	}

	/**
	 * Botón para seguir desde un mensaje: al reto, con un texto según su fase,
	 * o a la lista de retos si ya no está disponible.
	 *
	 * @param int $reto_id Reto (0 = ninguno).
	 * @return string HTML.
	 */
	public static function enlace_reto( $reto_id ) {
		$estado = self::estado_reto( $reto_id );
		if ( '' === $estado ) {
			$url   = self::url_retos();
			$texto = __( 'See all challenges', 'foncholuis-art-challenge-manager' );
		} else {
			$url    = get_permalink( $reto_id );
			$textos = array(
				'votacion'   => __( 'See the photos and vote', 'foncholuis-art-challenge-manager' ),
				'finalizado' => __( 'See the results', 'foncholuis-art-challenge-manager' ),
			);
			$texto  = isset( $textos[ $estado ] ) ? $textos[ $estado ] : __( 'See the challenge', 'foncholuis-art-challenge-manager' );
		}

		return sprintf( '<p><a class="concursos-boton" href="%s">%s</a></p>', esc_url( $url ), esc_html( $texto ) );
	}

	/**
	 * Documento HTML completo con los estilos del plugin, y termina la petición.
	 *
	 * @param int    $status Código HTTP.
	 * @param string $title  Título de la página.
	 * @param string $body   HTML ya escapado.
	 * @param array  $s      Configuración del reto, para aplicar su diseño.
	 */
	public static function layout( $status, $title, $body, $s = null ) {
		status_header( $status );
		wp_register_style( 'concursos-public', CONCURSOS_URL . 'assets/css/public.css', array(), CONCURSOS_VERSION );
		wp_register_script( 'concursos-public', CONCURSOS_URL . 'assets/js/public.js', array(), CONCURSOS_VERSION, true );
		if ( $s ) {
			Concursos_Diseno::aplicar( $s );
		}
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $title . ' · ' . get_bloginfo( 'name' ) ); ?></title>
	<?php wp_print_styles( 'concursos-public' ); ?>
</head>
<body class="concursos-pagina concursos-disenado"<?php echo $s ? ' style="' . esc_attr( Concursos_Diseno::estilo( $s ) ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado. ?>>
	<main class="concursos-pagina__contenido">
		<p class="concursos-sitio"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a></p>
		<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado al construirlo. ?>
	</main>
	<?php wp_print_scripts( 'concursos-public' ); ?>
</body>
</html>
		<?php
		exit;
	}
}
