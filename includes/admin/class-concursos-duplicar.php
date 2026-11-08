<?php
/**
 * Duplicar un reto para crear una nueva edición.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Crea un borrador con la configuración de un reto, sin fotos ni votos y,
 * si se pide, con sus participantes activos.
 */
class Concursos_Duplicar {

	const PAGE   = 'concursos-duplicar';
	const ACTION = 'concursos_duplicar';
	const NOTICE = 'concursos_duplicado';

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_filter( 'post_row_actions', array( __CLASS__, 'row_action' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_filter( 'removable_query_args', array( __CLASS__, 'removable_args' ) );
	}

	/**
	 * URL de la pantalla de duplicar.
	 *
	 * @param int $id Reto.
	 * @return string
	 */
	public static function url( $id ) {
		return add_query_arg(
			array(
				'page' => self::PAGE,
				'reto' => (int) $id,
			),
			admin_url( 'options.php' )
		);
	}

	/**
	 * Añade "Duplicar" a cada fila de la lista de retos.
	 *
	 * @param array   $actions Acciones.
	 * @param WP_Post $post    Post de la fila.
	 * @return array
	 */
	public static function row_action( $actions, $post ) {
		if ( Concursos_Post_Types::RETO === $post->post_type && current_user_can( 'edit_post', $post->ID ) ) {
			$actions['concursos_duplicar'] = sprintf( '<a href="%s">%s</a>', esc_url( self::url( $post->ID ) ), esc_html__( 'Duplicar', 'concursos' ) );
		}

		return $actions;
	}

	/**
	 * Registra la pantalla, fuera del menú: se llega desde cada reto.
	 */
	public static function menu() {
		add_submenu_page( 'options.php', __( 'Duplicar reto', 'concursos' ), __( 'Duplicar reto', 'concursos' ), 'edit_posts', self::PAGE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Participantes activos de un reto.
	 *
	 * @param int $reto_id Reto.
	 * @return int[]
	 */
	private static function activos( $reto_id ) {
		return get_posts(
			array(
				'post_type'      => Concursos_Participante::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => Concursos_Participante::META . 'reto',
						'value' => (int) $reto_id,
					),
					array(
						'key'   => Concursos_Participante::META . 'estado',
						'value' => Concursos_Participante::ACTIVO,
					),
				),
			)
		);
	}

	/**
	 * ¿Es un reto que el usuario actual puede duplicar?
	 *
	 * @param int $id Reto.
	 * @return bool
	 */
	private static function puede( $id ) {
		return Concursos_Post_Types::RETO === get_post_type( $id ) && current_user_can( 'edit_post', $id );
	}

	/**
	 * Pantalla de confirmación.
	 */
	public static function render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo elige qué reto mostrar.
		$id = isset( $_GET['reto'] ) ? absint( $_GET['reto'] ) : 0;
		if ( ! self::puede( $id ) ) {
			wp_die( esc_html__( 'No puedes duplicar este reto.', 'concursos' ) );
		}
		$activos = count( self::activos( $id ) );
		?>
		<div class="wrap">
			<h1>
				<?php
				/* translators: %s: nombre del reto. */
				echo esc_html( sprintf( __( 'Duplicar «%s»', 'concursos' ), get_the_title( $id ) ) );
				?>
			</h1>
			<p><?php esc_html_e( 'Se crea un borrador con la misma descripción, imagen, reglas, premios, configuración y diseño. Las fechas quedan vacías para que elijas las nuevas. Las fotos y los votos no se copian.', 'concursos' ); ?></p>
			<p><?php esc_html_e( 'Los votantes confirmados pueden votar en el reto nuevo sin hacer nada.', 'concursos' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<input type="hidden" name="reto" value="<?php echo esc_attr( $id ); ?>">
				<?php wp_nonce_field( self::ACTION . '_' . $id ); ?>
				<p>
					<label>
						<input type="checkbox" name="participantes" value="1" <?php disabled( 0, $activos ); ?>>
						<?php
						/* translators: %d: número de participantes activos. */
						echo esc_html( sprintf( _n( 'Copiar también su %d participante activo', 'Copiar también sus %d participantes activos', $activos, 'concursos' ), $activos ) );
						?>
					</label>
				</p>
				<p class="description"><?php esc_html_e( 'Quedarán activos en el reto nuevo y recibirán su enlace personal por email cuando lo publiques, no antes.', 'concursos' ); ?></p>
				<?php submit_button( __( 'Crear la copia', 'concursos' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Crea la copia y abre su edición.
	 */
	public static function handle() {
		$id = isset( $_POST['reto'] ) ? absint( $_POST['reto'] ) : 0;
		check_admin_referer( self::ACTION . '_' . $id );
		if ( ! self::puede( $id ) ) {
			wp_die( esc_html__( 'No puedes duplicar este reto.', 'concursos' ) );
		}

		$original = get_post( $id );
		$nuevo    = wp_insert_post(
			array(
				'post_type'    => Concursos_Post_Types::RETO,
				'post_status'  => 'draft',
				/* translators: %s: nombre del reto. */
				'post_title'   => sprintf( __( '%s (copia)', 'concursos' ), $original->post_title ),
				'post_content' => $original->post_content,
			),
			true
		);
		if ( is_wp_error( $nuevo ) ) {
			wp_die( esc_html( $nuevo->get_error_message() ) );
		}

		$ajustes = Concursos_Reto::get_settings( $id );
		foreach ( Concursos_Reto::FECHAS as $fecha ) {
			$ajustes[ $fecha ] = 0;
		}
		Concursos_Reto::save_settings( $nuevo, $ajustes );

		$imagen = get_post_thumbnail_id( $id );
		if ( $imagen ) {
			set_post_thumbnail( $nuevo, $imagen );
		}

		$copiados = 0;
		if ( ! empty( $_POST['participantes'] ) ) {
			foreach ( self::activos( $id ) as $participante ) {
				$copiados += self::copiar_participante( $participante, $nuevo ) ? 1 : 0;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'post'       => $nuevo,
					'action'     => 'edit',
					self::NOTICE => $copiados,
				),
				admin_url( 'post.php' )
			)
		);
		exit;
	}

	/**
	 * Inscribe a un participante en otro reto, activo y sin enlace enviado.
	 *
	 * @param int $id      Participante.
	 * @param int $reto_id Reto de destino.
	 * @return bool
	 */
	private static function copiar_participante( $id, $reto_id ) {
		$p     = Concursos_Participante::get( $id );
		$copia = Concursos_Participante::create(
			array(
				'nombre'    => $p['nombre'],
				'email'     => $p['email'],
				'reto'      => $reto_id,
				'instagram' => $p['instagram'],
				'facebook'  => $p['facebook'],
			)
		);
		if ( is_wp_error( $copia ) ) {
			return false;
		}

		Concursos_Participante::set_estado( $copia, Concursos_Participante::ACTIVO );
		update_post_meta( $copia, Concursos_Participante::META . 'privacidad', get_post_meta( $id, Concursos_Participante::META . 'privacidad', true ) );
		$foto = get_post_thumbnail_id( $id );
		if ( $foto ) {
			set_post_thumbnail( $copia, $foto );
		}

		return true;
	}

	/**
	 * Aviso en la pantalla de edición del duplicado.
	 */
	public static function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo muestra un aviso.
		if ( ! isset( $_GET[ self::NOTICE ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo muestra un aviso.
		$n = absint( $_GET[ self::NOTICE ] );
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				$n
					/* translators: %d: número de participantes copiados. */
					? sprintf( _n( 'Copia creada con %d participante. Elige las fechas y publícala: entonces recibirá su enlace personal.', 'Copia creada con %d participantes. Elige las fechas y publícala: entonces recibirán su enlace personal.', $n, 'concursos' ), $n )
					: __( 'Copia creada. Elige las fechas y publícala.', 'concursos' )
			)
		);
	}

	/**
	 * Quita el parámetro del aviso de la URL.
	 *
	 * @param string[] $args Parámetros.
	 * @return string[]
	 */
	public static function removable_args( $args ) {
		$args[] = self::NOTICE;

		return $args;
	}
}
