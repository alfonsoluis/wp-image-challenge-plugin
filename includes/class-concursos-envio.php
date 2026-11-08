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

	const FOTO_MAX    = 10485760; // 10 MB.
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
			'subida'    => array( 'success', __( '¡Foto enviada!', 'concursos' ) ),
			'eliminada' => array( 'success', __( 'Foto eliminada.', 'concursos' ) ),
			'cerrado'   => array( 'error', __( 'El periodo de envíos no está abierto.', 'concursos' ) ),
			'limite'    => array( 'error', __( 'Ya enviaste el máximo de fotos de este reto.', 'concursos' ) ),
			'foto'      => array( 'error', __( 'La foto debe ser JPG, PNG o WebP de 10 MB como máximo.', 'concursos' ) ),
			'error'     => array( 'error', __( 'No pudimos completar la acción. Inténtalo de nuevo.', 'concursos' ) ),
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
			self::render_message( 404, __( 'Enlace no válido', 'concursos' ), __( 'Este enlace no es válido. Revisa que lo hayas copiado completo.', 'concursos' ) );
		}

		$p = Concursos_Participante::get( $id );
		if ( Concursos_Participante::ACTIVO !== $p['estado'] ) {
			self::render_message( 403, __( 'Inscripción no activa', 'concursos' ), __( 'Tu inscripción no está activa. Si crees que es un error, escríbenos.', 'concursos' ) );
		}

		if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
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
		if ( ! $foto || ! Concursos_Inscripcion::valid_image( $foto, self::FOTO_MAX ) ) {
			return 'foto';
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_upload( 'foto', 0 );
		if ( is_wp_error( $attachment_id ) ) {
			return 'foto';
		}

		$titulo  = isset( $_POST['titulo'] ) ? sanitize_text_field( wp_unslash( $_POST['titulo'] ) ) : '';
		$entrada = Concursos_Entrada::create( $p, mb_substr( $titulo, 0, 100 ), $attachment_id );
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
			/* translators: %s: nombre del participante. */
			echo esc_html( sprintf( __( 'Hola, %s', 'concursos' ), $p['nombre'] ) );
			?>
		</h1>
		<p class="concursos-reto-nombre"><?php echo esc_html( get_the_title( $p['reto'] ) ); ?></p>

		<?php if ( isset( $messages[ $code ] ) ) : ?>
			<p class="concursos-aviso concursos-aviso--<?php echo esc_attr( $messages[ $code ][0] ); ?>" role="status"><?php echo esc_html( $messages[ $code ][1] ); ?></p>
		<?php endif; ?>

		<p class="concursos-plazo">
			<?php
			if ( $abierto ) {
				/* translators: %s: fecha de fin de los envíos. */
				echo esc_html( sprintf( __( 'Puedes enviar fotos hasta el %s.', 'concursos' ), Concursos_Reto::format_date( $reto['fin_envios'] ) ) );
			} elseif ( 'proximo' === $status ) {
				/* translators: %s: fecha de inicio del reto. */
				echo esc_html( sprintf( __( 'El envío de fotos empieza el %s.', 'concursos' ), Concursos_Reto::format_date( $reto['inicio'] ) ) );
			} else {
				esc_html_e( 'El periodo de envíos de este reto está cerrado.', 'concursos' );
			}
			?>
		</p>

		<h2>
			<?php
			/* translators: 1: fotos enviadas, 2: máximo de fotos. */
			echo esc_html( sprintf( __( 'Tus fotos (%1$d de %2$d)', 'concursos' ), count( $enviadas ), $max ) );
			?>
		</h2>
		<?php if ( $enviadas ) : ?>
			<ul class="concursos-mis-fotos">
				<?php foreach ( $enviadas as $entrada ) : ?>
					<li>
						<?php echo get_the_post_thumbnail( $entrada, 'medium' ); ?>
						<span><?php echo esc_html( get_the_title( $entrada ) ); ?></span>
						<?php if ( $abierto ) : ?>
							<form method="post" onsubmit="return confirm('<?php echo esc_js( __( '¿Eliminar esta foto?', 'concursos' ) ); ?>');">
								<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
								<button type="submit" name="eliminar" value="<?php echo esc_attr( $entrada->ID ); ?>" class="concursos-boton-secundario"><?php esc_html_e( 'Eliminar', 'concursos' ); ?></button>
							</form>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'Todavía no has enviado fotos.', 'concursos' ); ?></p>
		<?php endif; ?>

		<?php if ( $abierto && $quedan > 0 ) : ?>
			<h2><?php esc_html_e( 'Enviar una foto', 'concursos' ); ?></h2>
			<form method="post" enctype="multipart/form-data" class="concursos-form-publico">
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
				<p>
					<label for="concursos-foto"><?php esc_html_e( 'Foto', 'concursos' ); ?> *</label>
					<input type="file" id="concursos-foto" name="foto" required accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
					<small><?php esc_html_e( 'JPG, PNG o WebP, hasta 10 MB.', 'concursos' ); ?></small>
				</p>
				<p>
					<label for="concursos-titulo"><?php esc_html_e( 'Título (opcional)', 'concursos' ); ?></label>
					<input type="text" id="concursos-titulo" name="titulo" maxlength="100">
				</p>
				<p><button type="submit"><?php esc_html_e( 'Enviar foto', 'concursos' ); ?></button></p>
			</form>
		<?php endif; ?>

		<?php
		$rules = Concursos_Reto::rules_list( $reto['reglas'] );
		if ( $rules ) :
			?>
			<h2><?php esc_html_e( 'Reglas', 'concursos' ); ?></h2>
			<ul>
				<?php foreach ( $rules as $rule ) : ?>
					<li><?php echo esc_html( $rule ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<p class="concursos-privado"><?php esc_html_e( 'Esta página es personal: no compartas su enlace.', 'concursos' ); ?></p>
		<?php
		self::layout( 200, __( 'Tus fotos', 'concursos' ), ob_get_clean(), $reto );
	}

	/**
	 * Pinta un mensaje simple (enlace inválido, inscripción inactiva).
	 *
	 * @param int    $status  Código HTTP.
	 * @param string $title   Título.
	 * @param string $message Mensaje.
	 */
	public static function render_message( $status, $title, $message ) {
		self::layout( $status, $title, '<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p>' );
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
</body>
</html>
		<?php
		exit;
	}
}
