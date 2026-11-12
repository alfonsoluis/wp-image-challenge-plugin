<?php
/**
 * Perfil público de un participante (/participante/nombre-xxxxxxxxxx/).
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reúne todas las inscripciones de una persona, identificada por su email, en una página.
 *
 * La URL lleva un resumen del email con la sal del sitio: une las inscripciones
 * de la misma persona sin publicar su dirección ni permitir comprobarla.
 */
class Concursos_Perfil {

	/** Opción de los ajustes que activa los perfiles públicos. */
	const OPCION = 'concursos_perfiles';

	const QUERY         = 'concursos_perfil';
	const HASH_LEN      = 10;
	const RULES_VERSION = '1';
	const RULES_OPTION  = 'concursos_rewrite_version';

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rules' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'route' ) );
	}

	/**
	 * Primer tramo de la URL de los perfiles.
	 *
	 * @return string
	 */
	public static function base_url() {
		return Concursos_Post_Types::sitio_en_espanol() ? 'participante' : 'participant';
	}

	/**
	 * Registra la URL del perfil.
	 */
	public static function add_rules() {
		add_rewrite_rule( '^' . self::base_url() . '/([a-z0-9-]+)/?$', 'index.php?' . self::QUERY . '=$matches[1]', 'top' );
	}

	/**
	 * Regenera las reglas una vez tras actualizar el plugin.
	 */
	public static function maybe_flush() {
		// Incluye las URL actuales: si cambia el idioma del sitio, cambian las reglas.
		$version = self::RULES_VERSION . '-' . Concursos_Post_Types::base_url();
		if ( get_option( self::RULES_OPTION ) !== $version ) {
			flush_rewrite_rules( false );
			update_option( self::RULES_OPTION, $version );
		}
	}

	/**
	 * Declara la variable de consulta.
	 *
	 * @param string[] $vars Variables.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::QUERY;

		return $vars;
	}

	/**
	 * Sal del resumen: solo hexadecimal, para usarla también en SQL.
	 *
	 * @return string
	 */
	private static function sal() {
		return md5( wp_salt( 'auth' ) );
	}

	/**
	 * Si los perfiles públicos están activados en los ajustes del plugin.
	 *
	 * @return bool
	 */
	public static function activos() {
		return '1' === (string) get_option( self::OPCION, '1' );
	}

	/**
	 * Nombre de un participante, enlazado a su perfil si los perfiles están activados.
	 *
	 * @param int $id Participante.
	 * @return string HTML.
	 */
	public static function enlace( $id ) {
		$url = self::url( $id );

		return $url
			? sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( get_the_title( $id ) ) )
			: esc_html( get_the_title( $id ) );
	}

	/**
	 * URL del perfil de un participante.
	 *
	 * @param int $id Participante.
	 * @return string Vacío si los perfiles están desactivados.
	 */
	public static function url( $id ) {
		if ( ! self::activos() ) {
			return '';
		}

		$email = (string) get_post_meta( $id, Concursos_Participante::META . 'email', true );
		$slug  = trim( sanitize_title( get_the_title( $id ) ) . '-' . substr( md5( self::sal() . $email ), 0, self::HASH_LEN ), '-' );

		if ( get_option( 'permalink_structure' ) ) {
			return home_url( '/' . self::base_url() . '/' . $slug . '/' );
		}

		return add_query_arg( self::QUERY, $slug, home_url( '/' ) );
	}

	/**
	 * Inscripciones activas de una persona, de la más reciente a la más antigua.
	 *
	 * @param string $hash Resumen del email.
	 * @return int[]
	 */
	private static function inscripciones( $hash ) {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s AND LEFT(MD5(CONCAT(%s, pm.meta_value)), %d) = %s ORDER BY p.post_date DESC, p.ID DESC",
				Concursos_Participante::META . 'email',
				Concursos_Participante::POST_TYPE,
				self::sal(),
				self::HASH_LEN,
				$hash
			)
		);

		return array_values( array_filter( array_map( 'intval', $ids ), array( 'Concursos_Participante', 'esta_activo' ) ) );
	}

	/**
	 * Atiende la URL del perfil.
	 */
	public static function route() {
		$slug = (string) get_query_var( self::QUERY );
		if ( '' === $slug ) {
			return;
		}

		$inscripciones = ( self::activos() && preg_match( '/([a-f0-9]{' . self::HASH_LEN . '})$/', $slug, $m ) ) ? self::inscripciones( $m[1] ) : array();
		if ( ! $inscripciones ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();

			return;
		}

		$referencia = Concursos_Portada::reto_referencia();
		Concursos_Plantilla::pagina( get_the_title( $inscripciones[0] ), self::render( $inscripciones ), 'concursos-publico--perfil', $referencia ? $referencia['settings'] : null );
	}

	/**
	 * Primer puesto obtenido por unas fotos en un reto terminado.
	 *
	 * @param int       $reto_id Reto.
	 * @param array     $s       Configuración.
	 * @param WP_Post[] $mias    Fotos de la persona.
	 * @return array|null lugar, empate, premio.
	 */
	private static function puesto( $reto_id, array $s, array $mias ) {
		$ids     = wp_list_pluck( $mias, 'ID' );
		$todas   = Concursos_Entrada::find( array( 'reto' => $reto_id ) );
		$ranking = Concursos_Votos::rank( Concursos_Votos::de_fotos( $reto_id, $todas ), (int) $s['lugares'] );

		foreach ( $ranking['puestos'] as $puesto ) {
			if ( array_intersect( $ids, $puesto['entradas'] ) ) {
				return array(
					'lugar'  => $puesto['lugar'],
					'empate' => count( $puesto['entradas'] ) > 1,
					'premio' => isset( $s['premios'][ $puesto['lugar'] - 1 ] ) ? $s['premios'][ $puesto['lugar'] - 1 ] : '',
				);
			}
		}

		return null;
	}

	/**
	 * Contenido del perfil.
	 *
	 * @param int[] $inscripciones Inscripciones activas.
	 * @return string
	 */
	private static function render( array $inscripciones ) {
		$foto  = '';
		$redes = array();
		foreach ( $inscripciones as $id ) {
			if ( ! $foto && has_post_thumbnail( $id ) ) {
				$foto = get_the_post_thumbnail( $id, 'medium', array( 'class' => 'pc-perfil__foto concursos-perfil__foto' ) );
			}
			if ( ! $redes ) {
				$redes = Concursos_Participante::redes( Concursos_Participante::get( $id ) );
			}
		}

		$nombre = get_the_title( $inscripciones[0] );
		$filas  = array();
		$fotos  = 0;
		$puestos = 0;

		foreach ( $inscripciones as $id ) {
			$reto_id = (int) get_post_meta( $id, Concursos_Participante::META . 'reto', true );
			if ( 'publish' !== get_post_status( $reto_id ) ) {
				continue;
			}
			$s      = Concursos_Reto::get_settings( $reto_id );
			$status = Concursos_Reto::status( $s );
			// En un reto cerrado, las fotos no pueden verse aquí antes que en el reto,
			// y en una votación anónima no pueden delatar a su autor.
			$mias = ( Concursos_Reto::photos_visible( $s ) && ! Concursos_Reto::oculta_autoria( $s, $status ) )
				? Concursos_Entrada::find(
					array(
						'reto'         => $reto_id,
						'participante' => $id,
					)
				)
				: array();
			$puesto = ( 'finalizado' === $status && $mias ) ? self::puesto( $reto_id, $s, $mias ) : null;

			$fotos  += count( $mias );
			$puestos += $puesto ? 1 : 0;
			$filas[] = compact( 'reto_id', 'status', 'mias', 'puesto' );
		}

		// Mejor resultado: el puesto más alto, para destacarlo.
		$logro = null;
		foreach ( $filas as $fila ) {
			if ( $fila['puesto'] && ( ! $logro || $fila['puesto']['lugar'] < $logro['puesto']['lugar'] ) ) {
				$logro = $fila;
			}
		}
		$pagina  = (int) get_option( Concursos_Portada::OPCION_PAGINA );
		$portada = $pagina && 'publish' === get_post_status( $pagina ) ? get_permalink( $pagina ) : get_post_type_archive_link( Concursos_Post_Types::RETO );

		ob_start();
		?>
		<div class="pc-contenedor">
			<nav class="pc-ruta" aria-label="<?php esc_attr_e( 'Breadcrumb', 'photo-challenges' ); ?>">
				<a href="<?php echo esc_url( $portada ); ?>"><?php esc_html_e( 'Challenges', 'photo-challenges' ); ?></a>
				<span aria-hidden="true">/</span>
				<span aria-current="page"><?php echo esc_html( $nombre ); ?></span>
			</nav>
			<header class="pc-perfil concursos-perfil">
				<div class="pc-perfil__persona">
					<?php if ( $foto ) : ?>
						<?php echo $foto; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML de WordPress. ?>
					<?php else : ?>
						<span class="pc-perfil__foto pc-perfil__foto--vacia concursos-perfil__foto--vacia" aria-hidden="true"><?php echo esc_html( Concursos_Vista::iniciales( $nombre ) ); ?></span>
					<?php endif; ?>
					<div class="pc-perfil__texto">
						<p class="pc-antetitulo"><?php esc_html_e( 'Participant', 'photo-challenges' ); ?></p>
						<h1 class="pc-perfil__nombre"><?php echo esc_html( $nombre ); ?></h1>
						<?php if ( $redes ) : ?>
							<p class="pc-acciones concursos-perfil__redes">
								<?php foreach ( $redes as $red => $url ) : ?>
									<a class="pc-boton pc-boton--secundario pc-boton--pequeno" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $red ); ?></a>
								<?php endforeach; ?>
							</p>
						<?php endif; ?>
					</div>
				</div>
				<dl class="pc-cifras concursos-perfil__cifras">
					<div><dt class="pc-etiqueta"><?php esc_html_e( 'Challenges', 'photo-challenges' ); ?></dt><dd><?php echo esc_html( number_format_i18n( count( $filas ) ) ); ?></dd></div>
					<div><dt class="pc-etiqueta"><?php esc_html_e( 'Photos', 'photo-challenges' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $fotos ) ); ?></dd></div>
					<div><dt class="pc-etiqueta"><?php esc_html_e( 'Places', 'photo-challenges' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $puestos ) ); ?></dd></div>
				</dl>
			</header>

			<?php
			if ( $logro ) :
				$imagen = get_the_post_thumbnail_url( $logro['mias'][0], 'large' );
				?>
				<section class="pc-logro" aria-labelledby="pc-logro">
					<?php if ( $imagen ) : ?>
						<span class="pc-logro__media"><img src="<?php echo esc_url( $imagen ); ?>" alt=""></span>
					<?php endif; ?>
					<div class="pc-logro__texto">
						<span class="pc-sello pc-sello--<?php echo esc_attr( (string) min( 3, $logro['puesto']['lugar'] ) ); ?>"><?php echo Concursos_Vista::icono( 'medalla', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fijo. ?><?php echo esc_html( Concursos_Votos::nombre_lugar( $logro['puesto']['lugar'], $logro['puesto']['empate'] ) ); ?></span>
						<h2 class="pc-logro__titulo" id="pc-logro"><?php echo esc_html( get_the_title( $logro['mias'][0] ) ); ?></h2>
						<p>
							<?php esc_html_e( 'Challenge:', 'photo-challenges' ); ?>
							<a href="<?php echo esc_url( get_permalink( $logro['reto_id'] ) ); ?>"><?php echo esc_html( get_the_title( $logro['reto_id'] ) ); ?></a><?php
							if ( '' !== $logro['puesto']['premio'] ) {
								/* translators: %s: prize. */
								echo '. ' . esc_html( sprintf( __( 'Prize: %s', 'photo-challenges' ), $logro['puesto']['premio'] ) );
							}
							?>
						</p>
					</div>
				</section>
			<?php endif; ?>

			<section class="pc-seccion" aria-labelledby="pc-historial">
				<?php echo Concursos_Vista::cabecera_seccion( 'pc-historial', __( 'Challenges they took part in', 'photo-challenges' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
				<?php foreach ( $filas as $fila ) : ?>
					<article class="pc-historial concursos-perfil__reto">
						<div class="pc-historial__datos">
							<span class="pc-etiqueta"><?php echo esc_html( Concursos_Reto::format_dia( Concursos_Reto::get_settings( $fila['reto_id'] )['cierre'] ) ); ?></span>
							<h3 class="pc-historial__titulo"><a href="<?php echo esc_url( get_permalink( $fila['reto_id'] ) ); ?>"><?php echo esc_html( get_the_title( $fila['reto_id'] ) ); ?></a></h3>
							<?php echo Concursos_Vista::chip( $fila['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
							<?php if ( $fila['puesto'] ) : ?>
								<span class="pc-resultado concursos-perfil__puesto"><span class="pc-medalla pc-medalla--<?php echo esc_attr( (string) min( 3, $fila['puesto']['lugar'] ) ); ?>" aria-hidden="true"></span><?php echo esc_html( Concursos_Votos::nombre_lugar( $fila['puesto']['lugar'], $fila['puesto']['empate'] ) ); ?></span>
								<?php if ( '' !== $fila['puesto']['premio'] ) : ?>
									<span class="pc-texto-suave concursos-premio">
										<?php
										/* translators: %s: prize. */
										echo esc_html( sprintf( __( 'Prize: %s', 'photo-challenges' ), $fila['puesto']['premio'] ) );
										?>
									</span>
								<?php endif; ?>
							<?php endif; ?>
						</div>
						<?php if ( $fila['mias'] ) : ?>
							<ul class="pc-fotos-perfil concursos-galeria__rejilla">
								<?php foreach ( $fila['mias'] as $entrada ) : ?>
									<li class="pc-foto">
										<figure>
											<?php echo Concursos_Entrada::miniatura( $entrada ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
											<figcaption class="pc-foto__pie"><span class="pc-foto__titulo"><?php echo esc_html( get_the_title( $entrada ) ); ?></span></figcaption>
										</figure>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="pc-texto-suave pc-historial__vacio"><?php esc_html_e( 'Their photos in this challenge cannot be shown yet.', 'photo-challenges' ); ?></p>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}
}
