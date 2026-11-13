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
	const FOTO_EXTS     = array( 'jpg', 'jpeg', 'png', 'webp' );
	const OPCION_PAGINA = 'concursos_pagina_inscripcion';

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'enlaces_al_publicar' ), 10, 4 );
		add_action( 'init', array( __CLASS__, 'asegurar_pagina' ), 21 );
		add_filter( 'the_title', array( __CLASS__, 'sin_titulo' ), 10, 2 );
		add_filter( 'body_class', array( __CLASS__, 'clase_pagina' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'estado_pagina' ), 10, 2 );
	}

	/**
	 * Crea una vez la página de inscripción, con el shortcode, salvo que ya haya
	 * una. Si después se borra, no se vuelve a crear.
	 *
	 * La opción guarda el ID de la página creada, o "existente" si se usó una
	 * que ya había (esa no se borra al desinstalar).
	 */
	public static function asegurar_pagina() {
		if ( get_option( self::OPCION_PAGINA ) ) {
			return;
		}
		if ( self::url() ) {
			update_option( self::OPCION_PAGINA, 'existente' );

			return;
		}

		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Challenge registration', 'foncholuis-art-challenge-manager' ),
				'post_content' => '<!-- wp:shortcode -->[' . self::SHORTCODE . ']<!-- /wp:shortcode -->',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( self::OPCION_PAGINA, (int) $id );
		}
	}

	/**
	 * Quita el título de la página con el formulario, que ya se explica solo.
	 * Solo afecta al título principal de esa página; los menús y la pestaña del
	 * navegador lo conservan.
	 *
	 * @param string $titulo  Título.
	 * @param int    $post_id Entrada.
	 * @return string
	 */
	public static function sin_titulo( $titulo, $post_id = 0 ) {
		if ( is_admin() || ! is_page() || ! in_the_loop() || (int) $post_id !== get_queried_object_id() ) {
			return $titulo;
		}
		$post = get_post( $post_id );

		return $post && has_shortcode( $post->post_content, self::SHORTCODE ) ? '' : $titulo;
	}

	/**
	 * Clase en el body de la página con el formulario, para recoger el hueco
	 * que deja el título quitado.
	 *
	 * @param string[] $clases Clases del body.
	 * @return string[]
	 */
	public static function clase_pagina( $clases ) {
		$post = get_post();
		if ( is_page() && $post && has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			$clases[] = 'concursos-pagina-inscripcion';
		}

		return $clases;
	}

	/**
	 * Marca en la lista de páginas las que tienen el formulario de inscripción.
	 *
	 * @param string[] $states Estados.
	 * @param WP_Post  $post   Post.
	 * @return string[]
	 */
	public static function estado_pagina( $states, $post ) {
		if ( 'page' === $post->post_type && has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			$states['concursos_inscripcion'] = __( 'Registration page', 'foncholuis-art-challenge-manager' );
		}

		return $states;
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
	 * URL de la página con el formulario de inscripción.
	 *
	 * @param int $reto_id Reto que debe aparecer elegido (0 = ninguno).
	 * @return string Vacío si ninguna página tiene el shortcode.
	 */
	public static function url( $reto_id = 0 ) {
		static $pagina = null;

		if ( null === $pagina ) {
			$pagina   = 0;
			$paginas = get_posts(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					's'           => self::SHORTCODE,
					'numberposts' => 10,
				)
			);
			foreach ( $paginas as $candidata ) {
				if ( has_shortcode( $candidata->post_content, self::SHORTCODE ) ) {
					$pagina = $candidata->ID;
					break;
				}
			}
		}
		if ( ! $pagina ) {
			return '';
		}

		return $reto_id ? add_query_arg( 'reto', (int) $reto_id, get_permalink( $pagina ) ) : get_permalink( $pagina );
	}

	/**
	 * Mensajes tras enviar el formulario.
	 *
	 * @return array código => array( tipo, texto ).
	 */
	private static function messages() {
		return array(
			'ok'        => array( 'success', __( 'Thank you! We received your registration. We will write to you when it is approved.', 'foncholuis-art-challenge-manager' ) ),
			'nombre'    => array( 'error', __( 'Enter your name.', 'foncholuis-art-challenge-manager' ) ),
			'email'     => array( 'error', __( 'Enter a valid email address.', 'foncholuis-art-challenge-manager' ) ),
			'reto'      => array( 'error', __( 'Choose an open challenge.', 'foncholuis-art-challenge-manager' ) ),
			'duplicado' => array( 'error', __( 'That email is already registered for that challenge.', 'foncholuis-art-challenge-manager' ) ),
			'foto'      => array( 'error', sprintf(
				/* translators: %s: maximum file size, such as 5 MB. */
				__( 'The photo must be a JPG, PNG or WebP of up to %s.', 'foncholuis-art-challenge-manager' ),
				size_format( Concursos_Imagenes::limite() )
			) ),
			'privacidad' => array( 'error', __( 'You must accept the use of your data to register.', 'foncholuis-art-challenge-manager' ) ),
			'privado'    => array( 'error', Concursos_Reto::mensaje_privado() ),
			'error'     => array( 'error', __( 'We could not save your registration. Please try again.', 'foncholuis-art-challenge-manager' ) ),
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo preselecciona una opción.
		$elegido = isset( $_GET['reto'] ) ? absint( $_GET['reto'] ) : 0;

		$referencia = Concursos_Portada::reto_referencia();
		foreach ( $retos as $reto ) {
			if ( $reto['post']->ID === $elegido ) {
				$referencia = $reto;
			}
		}
		if ( ! $elegido && 1 === count( $retos ) ) {
			$elegido = $retos[0]['post']->ID;
		}

		ob_start();
		?>
		<div class="pc alignwide concursos-inscripcion"<?php echo Concursos_Diseno::atributos( $referencia ? $referencia['settings'] : null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado. ?>>
			<div class="pc-inscripcion">
				<div class="pc-inscripcion__intro">
					<p class="pc-antetitulo"><?php esc_html_e( 'Registration', 'foncholuis-art-challenge-manager' ); ?></p>
					<h2 class="pc-inscripcion__titulo"><?php esc_html_e( 'Take part with your photos', 'foncholuis-art-challenge-manager' ); ?></h2>
					<p class="pc-texto-suave"><?php esc_html_e( 'Choose the challenge and tell us who you are. Once your registration is approved, we will email you a personal link to upload your photos.', 'foncholuis-art-challenge-manager' ); ?></p>
				</div>

				<?php if ( isset( $messages[ $code ] ) ) : ?>
					<p class="pc-aviso concursos-aviso concursos-aviso--<?php echo esc_attr( $messages[ $code ][0] ); ?>" role="status">
						<?php echo esc_html( $messages[ $code ][1] ); ?>
					</p>
				<?php endif; ?>

				<?php if ( ! $retos ) : ?>
					<p class="pc-vacio"><?php esc_html_e( 'There are no challenges open for registration right now.', 'foncholuis-art-challenge-manager' ); ?></p>
				<?php elseif ( 'ok' !== $code ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="pc-formulario concursos-form-publico">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
						<?php wp_nonce_field( self::ACTION ); ?>

						<p class="concursos-hp" aria-hidden="true">
							<label><?php esc_html_e( 'Do not fill in this field', 'foncholuis-art-challenge-manager' ); ?> <input type="text" name="web" tabindex="-1" autocomplete="off"></label>
						</p>

						<fieldset class="pc-paso">
							<legend class="pc-paso__titulo"><span class="pc-paso__numero" aria-hidden="true">01</span><?php esc_html_e( 'Choose the challenge', 'foncholuis-art-challenge-manager' ); ?></legend>
							<div class="pc-opciones-reto">
								<?php
								foreach ( $retos as $reto ) :
									$imagen = Concursos_Portada::imagen( $reto, 'thumbnail' );
									$campo  = 'concursos-reto-' . $reto['post']->ID;
									?>
									<label class="pc-opcion-reto" for="<?php echo esc_attr( $campo ); ?>">
										<input type="radio" id="<?php echo esc_attr( $campo ); ?>" name="reto" value="<?php echo esc_attr( $reto['post']->ID ); ?>" required <?php checked( $elegido, $reto['post']->ID ); ?>>
										<?php if ( $imagen ) : ?>
											<img src="<?php echo esc_url( $imagen['url'] ); ?>" alt="" width="56" height="56">
										<?php else : ?>
											<span class="pc-opcion-reto__fecha" aria-hidden="true"><span><?php echo esc_html( wp_date( 'd', (int) $reto['settings']['inicio'] ) ); ?></span><span class="pc-etiqueta"><?php echo esc_html( wp_date( 'M', (int) $reto['settings']['inicio'] ) ); ?></span></span>
										<?php endif; ?>
										<span class="pc-opcion-reto__texto">
											<strong><?php echo esc_html( get_the_title( $reto['post'] ) ); ?></strong>
											<span class="pc-texto-suave"><?php echo esc_html( Concursos_Galeria::frase_estado( $reto['status'], $reto['settings'] ) ); ?></span>
										</span>
									</label>
								<?php endforeach; ?>
							</div>
						</fieldset>

						<fieldset class="pc-paso">
							<legend class="pc-paso__titulo"><span class="pc-paso__numero" aria-hidden="true">02</span><?php esc_html_e( 'About you', 'foncholuis-art-challenge-manager' ); ?></legend>
							<div class="pc-campos">
								<p class="pc-campo">
									<label for="concursos-nombre"><?php esc_html_e( 'Name', 'foncholuis-art-challenge-manager' ); ?> <span aria-hidden="true">*</span></label>
									<input type="text" id="concursos-nombre" name="nombre" required maxlength="100" autocomplete="name">
								</p>
								<p class="pc-campo">
									<label for="concursos-email"><?php esc_html_e( 'Email', 'foncholuis-art-challenge-manager' ); ?> <span aria-hidden="true">*</span></label>
									<input type="email" id="concursos-email" name="email" required autocomplete="email" aria-describedby="concursos-email-ayuda">
									<span class="pc-campo__ayuda" id="concursos-email-ayuda"><?php esc_html_e( 'Never published. We use it to send you the link for your photos.', 'foncholuis-art-challenge-manager' ); ?></span>
								</p>
								<p class="pc-campo">
									<label for="concursos-instagram"><?php esc_html_e( 'Instagram', 'foncholuis-art-challenge-manager' ); ?> <span class="pc-campo__opcional"><?php esc_html_e( '(optional)', 'foncholuis-art-challenge-manager' ); ?></span></label>
									<input type="text" id="concursos-instagram" name="instagram" maxlength="100" placeholder="@usuario">
								</p>
								<p class="pc-campo">
									<label for="concursos-facebook"><?php esc_html_e( 'Facebook', 'foncholuis-art-challenge-manager' ); ?> <span class="pc-campo__opcional"><?php esc_html_e( '(optional)', 'foncholuis-art-challenge-manager' ); ?></span></label>
									<input type="text" id="concursos-facebook" name="facebook" maxlength="100" placeholder="usuario">
								</p>
								<p class="pc-campo pc-campo--ancho">
									<label for="concursos-foto"><?php esc_html_e( 'Profile photo', 'foncholuis-art-challenge-manager' ); ?> <span class="pc-campo__opcional"><?php esc_html_e( '(optional)', 'foncholuis-art-challenge-manager' ); ?></span></label>
									<input type="file" id="concursos-foto" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="concursos-foto-ayuda">
									<span class="pc-campo__ayuda" id="concursos-foto-ayuda">
										<?php
										/* translators: %s: maximum file size, such as 5 MB. */
										echo esc_html( sprintf( __( 'JPG, PNG or WebP, up to %s.', 'foncholuis-art-challenge-manager' ), size_format( Concursos_Imagenes::limite() ) ) );
										?>
									</span>
								</p>
							</div>
						</fieldset>

						<fieldset class="pc-paso">
							<legend class="pc-paso__titulo"><span class="pc-paso__numero" aria-hidden="true">03</span><?php esc_html_e( 'Confirm', 'foncholuis-art-challenge-manager' ); ?></legend>
							<p class="pc-casilla concursos-casilla">
								<input type="checkbox" id="concursos-privacidad" name="privacidad" value="1" required>
								<label for="concursos-privacidad">
									<?php esc_html_e( 'I agree that this data is stored to manage my participation, and that my name, photo and social networks are shown next to my photos.', 'foncholuis-art-challenge-manager' ); ?>
									<?php
									$politica = get_privacy_policy_url();
									if ( $politica ) :
										?>
										<a href="<?php echo esc_url( $politica ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy policy', 'foncholuis-art-challenge-manager' ); ?></a>
									<?php endif; ?>
								</label>
							</p>
							<p class="pc-acciones"><button type="submit" class="pc-boton pc-boton--principal"><?php esc_html_e( 'Register', 'foncholuis-art-challenge-manager' ); ?><?php echo Concursos_Vista::icono( 'flecha' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fijo. ?></button></p>
						</fieldset>
					</form>
				<?php endif; ?>
			</div>
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
		if ( ! Concursos_Reto::permite_email( Concursos_Reto::get_settings( $reto_id ), 'participantes', $email ) ) {
			$done( 'privado' );
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
		if ( $has_foto && ! self::valid_image( $foto, Concursos_Imagenes::limite() ) ) {
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

			$attachment_id = Concursos_Imagenes::subir( 'foto', $id );
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
			/* translators: %s: challenge name. */
			sprintf( __( 'We received your registration: %s', 'foncholuis-art-challenge-manager' ), $reto ),
			sprintf(
				/* translators: 1: participant name, 2: challenge name, 3: site name. */
				__( "Hello %1\$s,\n\nWe received your registration for the challenge “%2\$s”. We will write to you once we have reviewed and approved it.\n\n%3\$s", 'foncholuis-art-challenge-manager' ),
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
			/* translators: %s: challenge name. */
			sprintf( __( 'You can now take part in “%s”', 'foncholuis-art-challenge-manager' ), $reto ),
			sprintf(
				/* translators: 1: participant name, 2: challenge name, 3: personal link, 4: site name. */
				__( "Hello %1\$s,\n\nWe approved your registration for the challenge “%2\$s”.\n\nThis is your personal link to submit your photos. Do not share it: anyone who has it can submit photos in your name.\n\n%3\$s\n\n%4\$s", 'foncholuis-art-challenge-manager' ),
				$p['nombre'],
				$reto,
				self::personal_link( $token ),
				( Concursos_Reto::permite_email( Concursos_Reto::get_settings( $p['reto'] ), 'votantes', $p['email'] )
					? __( 'With this email you can also vote when voting opens: open this link once on each device where you want to vote.', 'foncholuis-art-challenge-manager' ) . "\n\n"
					: '' ) . get_bloginfo( 'name' )
			)
		);
	}
}
