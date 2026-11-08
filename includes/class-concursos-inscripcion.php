<?php
/**
 * Inscripción pública de participantes, emails y enlace personal.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Formulario [concursos_inscripcion], su procesamiento y los emails a participantes.
 */
class Concursos_Inscripcion {

	const SHORTCODE     = 'concursos_inscripcion';
	const ACTION        = 'concursos_inscribir';
	const RESULT_PARAM  = 'concursos_inscripcion';
	const LINK_PARAM    = 'concursos_envio';
	const FOTO_MAX      = 5242880; // 5 MB.
	const FOTO_EXTS     = array( 'jpg', 'jpeg', 'png', 'webp' );

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'enlaces_al_publicar' ), 10, 4 );
	}

	/**
	 * Retos que aceptan inscripciones: publicados y que no han cerrado los envíos.
	 *
	 * @return array[] Cada uno: post, settings, status.
	 */
	public static function retos_abiertos() {
		return array_values(
			array_filter(
				Concursos_Reto::all( array( 'publish' ) ),
				function ( $reto ) {
					return in_array( $reto['status'], array( 'proximo', 'envios' ), true );
				}
			)
		);
	}

	/**
	 * Mensajes tras enviar el formulario.
	 *
	 * @return array código => array( tipo, texto ).
	 */
	private static function messages() {
		return array(
			'ok'        => array( 'success', __( '¡Gracias! Recibimos tu inscripción. Te escribiremos cuando la aprobemos.', 'concursos' ) ),
			'nombre'    => array( 'error', __( 'Escribe tu nombre.', 'concursos' ) ),
			'email'     => array( 'error', __( 'Escribe un email válido.', 'concursos' ) ),
			'reto'      => array( 'error', __( 'Elige un reto abierto.', 'concursos' ) ),
			'duplicado' => array( 'error', __( 'Ya hay una inscripción con ese email en ese reto.', 'concursos' ) ),
			'foto'      => array( 'error', __( 'La foto debe ser JPG, PNG o WebP de 5 MB como máximo.', 'concursos' ) ),
			'privacidad' => array( 'error', __( 'Debes aceptar el uso de tus datos para inscribirte.', 'concursos' ) ),
			'error'     => array( 'error', __( 'No pudimos guardar tu inscripción. Inténtalo de nuevo.', 'concursos' ) ),
		);
	}

	/**
	 * Pinta el formulario de inscripción.
	 *
	 * @return string
	 */
	public static function shortcode() {
		wp_enqueue_style( 'concursos-public', CONCURSOS_URL . 'assets/css/public.css', array(), CONCURSOS_VERSION );

		$retos    = self::retos_abiertos();
		$messages = self::messages();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo elige qué mensaje mostrar.
		$code = isset( $_GET[ self::RESULT_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT_PARAM ] ) ) : '';

		ob_start();
		?>
		<div class="concursos-inscripcion">
			<?php if ( isset( $messages[ $code ] ) ) : ?>
				<p class="concursos-aviso concursos-aviso--<?php echo esc_attr( $messages[ $code ][0] ); ?>" role="status">
					<?php echo esc_html( $messages[ $code ][1] ); ?>
				</p>
			<?php endif; ?>

			<?php if ( ! $retos ) : ?>
				<p><?php esc_html_e( 'Ahora mismo no hay retos abiertos a inscripción.', 'concursos' ); ?></p>
			<?php elseif ( 'ok' !== $code ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="concursos-form-publico">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<?php wp_nonce_field( self::ACTION ); ?>

					<p class="concursos-hp" aria-hidden="true">
						<label><?php esc_html_e( 'No rellenes este campo', 'concursos' ); ?> <input type="text" name="web" tabindex="-1" autocomplete="off"></label>
					</p>

					<p>
						<label for="concursos-nombre"><?php esc_html_e( 'Nombre', 'concursos' ); ?> *</label>
						<input type="text" id="concursos-nombre" name="nombre" required maxlength="100" autocomplete="name">
					</p>
					<p>
						<label for="concursos-email"><?php esc_html_e( 'Email', 'concursos' ); ?> *</label>
						<input type="email" id="concursos-email" name="email" required autocomplete="email">
					</p>
					<p>
						<label for="concursos-reto"><?php esc_html_e( 'Reto', 'concursos' ); ?> *</label>
						<select id="concursos-reto" name="reto" required>
							<?php if ( count( $retos ) > 1 ) : ?>
								<option value=""><?php esc_html_e( 'Elige un reto', 'concursos' ); ?></option>
							<?php endif; ?>
							<?php foreach ( $retos as $reto ) : ?>
								<option value="<?php echo esc_attr( $reto['post']->ID ); ?>"><?php echo esc_html( get_the_title( $reto['post'] ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="concursos-foto"><?php esc_html_e( 'Foto de perfil (opcional)', 'concursos' ); ?></label>
						<input type="file" id="concursos-foto" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
						<small><?php esc_html_e( 'JPG, PNG o WebP, hasta 5 MB.', 'concursos' ); ?></small>
					</p>
					<p>
						<label for="concursos-instagram"><?php esc_html_e( 'Instagram (opcional)', 'concursos' ); ?></label>
						<input type="text" id="concursos-instagram" name="instagram" maxlength="100" placeholder="@usuario">
					</p>
					<p>
						<label for="concursos-facebook"><?php esc_html_e( 'Facebook (opcional)', 'concursos' ); ?></label>
						<input type="text" id="concursos-facebook" name="facebook" maxlength="100" placeholder="usuario">
					</p>
					<p class="concursos-casilla">
						<label>
							<input type="checkbox" name="privacidad" value="1" required>
							<?php esc_html_e( 'Acepto que se guarden estos datos para gestionar mi participación, y que mi nombre, mi foto y mis redes se muestren junto a mis fotos.', 'concursos' ); ?>
							<?php
							$politica = get_privacy_policy_url();
							if ( $politica ) :
								?>
								<a href="<?php echo esc_url( $politica ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Política de privacidad', 'concursos' ); ?></a>
							<?php endif; ?>
						</label>
					</p>
					<p><button type="submit"><?php esc_html_e( 'Inscribirme', 'concursos' ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Procesa el formulario y vuelve a la página de origen con el resultado.
	 */
	public static function handle() {
		check_admin_referer( self::ACTION );

		$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$done = function ( $code ) use ( $back ) {
			wp_safe_redirect( add_query_arg( self::RESULT_PARAM, $code, $back ) );
			exit;
		};

		// Campo trampa para bots: si viene relleno, se finge éxito sin guardar nada.
		if ( ! empty( $_POST['web'] ) ) {
			$done( 'ok' );
		}

		$nombre    = isset( $_POST['nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['nombre'] ) ) : '';
		$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$reto_id   = isset( $_POST['reto'] ) ? absint( $_POST['reto'] ) : 0;
		$instagram = isset( $_POST['instagram'] ) ? self::handle_name( wp_unslash( $_POST['instagram'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se sanea en handle_name().
		$facebook  = isset( $_POST['facebook'] ) ? self::handle_name( wp_unslash( $_POST['facebook'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se sanea en handle_name().

		if ( '' === $nombre ) {
			$done( 'nombre' );
		}
		if ( ! is_email( $email ) ) {
			$done( 'email' );
		}
		if ( ! in_array( $reto_id, wp_list_pluck( wp_list_pluck( self::retos_abiertos(), 'post' ), 'ID' ), true ) ) {
			$done( 'reto' );
		}
		if ( empty( $_POST['privacidad'] ) ) {
			$done( 'privacidad' );
		}
		if ( Concursos_Participante::exists( $email, $reto_id ) ) {
			$done( 'duplicado' );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se valida abajo y lo maneja media_handle_upload().
		$foto     = isset( $_FILES['foto'] ) ? $_FILES['foto'] : null;
		$has_foto = $foto && ! empty( $foto['name'] );
		if ( $has_foto && ! self::valid_image( $foto, self::FOTO_MAX ) ) {
			$done( 'foto' );
		}

		$id = Concursos_Participante::create(
			array(
				'nombre'    => $nombre,
				'email'     => $email,
				'reto'      => $reto_id,
				'instagram' => $instagram,
				'facebook'  => $facebook,
			)
		);
		if ( is_wp_error( $id ) ) {
			$done( 'error' );
		}

		update_post_meta( $id, Concursos_Participante::META . 'privacidad', time() );

		if ( $has_foto ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$attachment_id = media_handle_upload( 'foto', $id );
			if ( is_wp_error( $attachment_id ) ) {
				wp_delete_post( $id, true );
				$done( 'foto' );
			}
			set_post_thumbnail( $id, $attachment_id );
		}

		self::email_inscripcion( $id );
		$done( 'ok' );
	}

	/**
	 * Comprueba tipo (JPG, PNG o WebP), tamaño y errores de subida de una imagen.
	 *
	 * @param array $file      Entrada de $_FILES.
	 * @param int   $max_bytes Tamaño máximo.
	 * @return bool
	 */
	public static function valid_image( array $file, $max_bytes ) {
		if ( ! isset( $file['error'], $file['size'], $file['name'], $file['tmp_name'] ) || UPLOAD_ERR_OK !== $file['error'] ) {
			return false;
		}
		if ( $file['size'] > $max_bytes ) {
			return false;
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );

		return in_array( strtolower( (string) $check['ext'] ), self::FOTO_EXTS, true );
	}

	/**
	 * Limpia un usuario de red social: sin @ inicial ni URL.
	 *
	 * @param string $value Texto escrito.
	 * @return string
	 */
	public static function handle_name( $value ) {
		$value = trim( sanitize_text_field( $value ) );
		$value = preg_replace( '#^https?://(www\.)?(instagram|facebook)\.com/#i', '', $value );
		$value = ltrim( trim( (string) $value, '/' ), '@' );

		return substr( preg_replace( '/[^A-Za-z0-9._-]/', '', $value ), 0, 60 );
	}

	/**
	 * Aprueba (activa) a un participante. La primera vez le envía su enlace personal.
	 *
	 * @param int $id ID del participante.
	 */
	public static function activar( $id ) {
		Concursos_Participante::set_estado( $id, Concursos_Participante::ACTIVO );

		$p = Concursos_Participante::get( $id );
		if ( ! $p['enlace_enviado'] && self::email_aprobacion( $id ) ) {
			update_post_meta( $id, Concursos_Participante::META . 'enlace_enviado', time() );
		}
	}

	/**
	 * Al publicar un reto, envía su enlace a los participantes activos que no
	 * lo tienen: los copiados al duplicarlo. Cada uno lo recibe una sola vez.
	 *
	 * @param int          $post_id     Post.
	 * @param WP_Post      $post        Post.
	 * @param bool         $update      Si es una actualización.
	 * @param WP_Post|null $post_before Post antes de guardar.
	 */
	public static function enlaces_al_publicar( $post_id, $post, $update, $post_before ) {
		if ( Concursos_Post_Types::RETO !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}
		if ( $post_before && 'publish' === $post_before->post_status ) {
			return;
		}

		$participantes = get_posts(
			array(
				'post_type'      => Concursos_Participante::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => Concursos_Participante::META . 'reto',
						'value' => (int) $post_id,
					),
					array(
						'key'   => Concursos_Participante::META . 'estado',
						'value' => Concursos_Participante::ACTIVO,
					),
				),
			)
		);
		foreach ( $participantes as $id ) {
			self::activar( $id );
		}
	}

	/**
	 * Enlace personal de un participante.
	 *
	 * @param string $token Token en claro.
	 * @return string
	 */
	public static function personal_link( $token ) {
		return add_query_arg( self::LINK_PARAM, $token, home_url( '/' ) );
	}

	/**
	 * Email de confirmación de la inscripción.
	 *
	 * @param int $id ID del participante.
	 * @return bool
	 */
	public static function email_inscripcion( $id ) {
		$p    = Concursos_Participante::get( $id );
		$reto = get_the_title( $p['reto'] );

		return wp_mail(
			$p['email'],
			/* translators: %s: nombre del reto. */
			sprintf( __( 'Recibimos tu inscripción: %s', 'concursos' ), $reto ),
			sprintf(
				/* translators: 1: nombre del participante, 2: nombre del reto, 3: nombre del sitio. */
				__( "Hola %1\$s:\n\nRecibimos tu inscripción en el reto «%2\$s». Te escribiremos cuando la revisemos y la aprobemos.\n\n%3\$s", 'concursos' ),
				$p['nombre'],
				$reto,
				get_bloginfo( 'name' )
			)
		);
	}

	/**
	 * Email de aprobación con el enlace personal para enviar fotos.
	 *
	 * @param int $id ID del participante.
	 * @return bool
	 */
	public static function email_aprobacion( $id ) {
		$p     = Concursos_Participante::get( $id );
		$reto  = get_the_title( $p['reto'] );
		$token = Concursos_Participante::issue_token( $id );

		return wp_mail(
			$p['email'],
			/* translators: %s: nombre del reto. */
			sprintf( __( 'Ya puedes participar en «%s»', 'concursos' ), $reto ),
			sprintf(
				/* translators: 1: nombre del participante, 2: nombre del reto, 3: enlace personal, 4: nombre del sitio. */
				__( "Hola %1\$s:\n\nAprobamos tu inscripción en el reto «%2\$s».\n\nEste es tu enlace personal para enviar tus fotos. No lo compartas: quien lo tenga puede enviar fotos en tu nombre.\n\n%3\$s\n\n%4\$s", 'concursos' ),
				$p['nombre'],
				$reto,
				self::personal_link( $token ),
				get_bloginfo( 'name' )
			)
		);
	}
}
