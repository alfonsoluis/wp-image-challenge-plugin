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
	 * URL del perfil de un participante.
	 *
	 * @param int $id Participante.
	 * @return string
	 */
	public static function url( $id ) {
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

		$inscripciones = preg_match( '/([a-f0-9]{' . self::HASH_LEN . '})$/', $slug, $m ) ? self::inscripciones( $m[1] ) : array();
		if ( ! $inscripciones ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();

			return;
		}

		Concursos_Plantilla::pagina( get_the_title( $inscripciones[0] ), self::render( $inscripciones ) );
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
				$foto = get_the_post_thumbnail( $id, 'medium', array( 'class' => 'concursos-perfil__foto' ) );
			}
			if ( ! $redes ) {
				$redes = Concursos_Participante::redes( Concursos_Participante::get( $id ) );
			}
		}

		$nombre = get_the_title( $inscripciones[0] );
		$labels = Concursos_Reto::status_labels();
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
			// En un reto cerrado, las fotos no pueden verse aquí antes que en el reto.
			$mias = Concursos_Reto::photos_visible( $s )
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

		ob_start();
		?>
		<header class="concursos-perfil">
			<?php if ( $foto ) : ?>
				<?php echo $foto; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML de WordPress. ?>
			<?php else : ?>
				<span class="concursos-perfil__foto concursos-perfil__foto--vacia" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $nombre, 0, 1 ) ) ); ?></span>
			<?php endif; ?>
			<div>
				<h1><?php echo esc_html( $nombre ); ?></h1>
				<p class="concursos-perfil__cifras">
					<?php
					echo esc_html(
						implode(
							' · ',
							array(
								/* translators: %d: number of challenges. */
								sprintf( _n( '%d challenge', '%d challenges', count( $filas ), 'photo-challenges' ), count( $filas ) ),
								/* translators: %d: number of photos. */
								sprintf( _n( '%d photo', '%d photos', $fotos, 'photo-challenges' ), $fotos ),
								/* translators: %d: number of places. */
								sprintf( _n( '%d place', '%d places', $puestos, 'photo-challenges' ), $puestos ),
							)
						)
					);
					?>
				</p>
				<?php if ( $redes ) : ?>
					<p class="concursos-perfil__redes">
						<?php foreach ( $redes as $red => $url ) : ?>
							<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $red ); ?></a>
						<?php endforeach; ?>
					</p>
				<?php endif; ?>
			</div>
		</header>

		<?php foreach ( $filas as $fila ) : ?>
			<section class="concursos-perfil__reto">
				<h2><a href="<?php echo esc_url( get_permalink( $fila['reto_id'] ) ); ?>"><?php echo esc_html( get_the_title( $fila['reto_id'] ) ); ?></a></h2>
				<p>
					<span class="concursos-etiqueta"><?php echo esc_html( $labels[ $fila['status'] ] ); ?></span>
					<?php if ( $fila['puesto'] ) : ?>
						<strong class="concursos-perfil__puesto">
							<?php echo esc_html( Concursos_Votos::nombre_lugar( $fila['puesto']['lugar'], $fila['puesto']['empate'] ) ); ?>
						</strong>
					<?php endif; ?>
				</p>
				<?php if ( $fila['puesto'] && '' !== $fila['puesto']['premio'] ) : ?>
					<p class="concursos-premio">
						<?php
						/* translators: %s: prize. */
						echo esc_html( sprintf( __( 'Prize: %s', 'photo-challenges' ), $fila['puesto']['premio'] ) );
						?>
					</p>
				<?php endif; ?>
				<?php if ( $fila['mias'] ) : ?>
					<ul class="concursos-galeria__rejilla">
						<?php foreach ( $fila['mias'] as $entrada ) : ?>
							<li>
								<figure>
									<?php echo Concursos_Entrada::miniatura( $entrada ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
									<figcaption><strong><?php echo esc_html( get_the_title( $entrada ) ); ?></strong></figcaption>
								</figure>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p><?php esc_html_e( 'Their photos in this challenge cannot be shown yet.', 'photo-challenges' ); ?></p>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
		<?php
		return ob_get_clean();
	}
}
