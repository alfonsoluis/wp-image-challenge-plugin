<?php
/**
 * Administración de participantes: lista, filtros y acciones en bloque.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lista de participantes en Retos → Participantes.
 */
class Concursos_Participantes_Admin {

	const TYPE = Concursos_Participante::POST_TYPE;

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		$t = self::TYPE;
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_query' ) );
		add_filter( "manage_{$t}_posts_columns", array( __CLASS__, 'columns' ) );
		add_action( "manage_{$t}_posts_custom_column", array( __CLASS__, 'column' ), 10, 2 );
		add_filter( "bulk_actions-edit-{$t}", array( __CLASS__, 'bulk_actions' ) );
		add_filter( "handle_bulk_actions-edit-{$t}", array( __CLASS__, 'handle_bulk' ), 10, 3 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_post_concursos_aprobar', array( __CLASS__, 'aprobar' ) );
		add_action( 'admin_post_concursos_reenviar', array( __CLASS__, 'reenviar' ) );
		add_action( 'admin_post_concursos_copiar_enlace', array( __CLASS__, 'copiar_enlace' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'add_meta_boxes_' . $t, array( __CLASS__, 'meta_box' ) );
		add_filter( 'removable_query_args', array( __CLASS__, 'removable_args' ) );
	}

	/**
	 * Valor de un filtro de la lista.
	 *
	 * @param string $name Parámetro GET.
	 * @return string
	 */
	private static function filter_value( $name ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtro de solo lectura.
		return isset( $_GET[ $name ] ) ? sanitize_key( wp_unslash( $_GET[ $name ] ) ) : '';
	}

	/**
	 * Desplegables para filtrar por reto y estado.
	 *
	 * @param string $post_type Tipo de contenido de la lista.
	 */
	public static function filters( $post_type ) {
		if ( self::TYPE !== $post_type ) {
			return;
		}
		$reto   = absint( self::filter_value( 'concursos_reto' ) );
		$estado = self::filter_value( 'concursos_estado' );
		?>
		<label class="screen-reader-text" for="concursos-filtro-reto"><?php esc_html_e( 'Filter by challenge', 'foncholuis-art-challenge-manager' ); ?></label>
		<select name="concursos_reto" id="concursos-filtro-reto">
			<option value=""><?php esc_html_e( 'All challenges', 'foncholuis-art-challenge-manager' ); ?></option>
			<?php foreach ( Concursos_Reto::all() as $r ) : ?>
				<option value="<?php echo esc_attr( $r['post']->ID ); ?>" <?php selected( $reto, $r['post']->ID ); ?>><?php echo esc_html( get_the_title( $r['post'] ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<label class="screen-reader-text" for="concursos-filtro-estado"><?php esc_html_e( 'Filter by status', 'foncholuis-art-challenge-manager' ); ?></label>
		<select name="concursos_estado" id="concursos-filtro-estado">
			<option value=""><?php esc_html_e( 'All statuses', 'foncholuis-art-challenge-manager' ); ?></option>
			<?php foreach ( Concursos_Participante::estado_labels() as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $estado, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Aplica los filtros a la consulta de la lista.
	 *
	 * @param WP_Query $query Consulta.
	 */
	public static function filter_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || self::TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$clauses = array();
		$reto    = absint( self::filter_value( 'concursos_reto' ) );
		$estado  = self::filter_value( 'concursos_estado' );
		if ( $reto ) {
			$clauses[] = array(
				'key'   => Concursos_Participante::META . 'reto',
				'value' => $reto,
			);
		}
		if ( isset( Concursos_Participante::estado_labels()[ $estado ] ) ) {
			$clauses[] = array(
				'key'   => Concursos_Participante::META . 'estado',
				'value' => $estado,
			);
		}
		if ( $clauses ) {
			$query->set( 'meta_query', array_merge( array( 'relation' => 'AND' ), $clauses ) );
		}
	}

	/**
	 * Columnas de la lista.
	 *
	 * @param array $columns Columnas por defecto.
	 * @return array
	 */
	public static function columns( $columns ) {
		return array(
			'cb'               => $columns['cb'],
			'concursos_foto'   => __( 'Photo', 'foncholuis-art-challenge-manager' ),
			'title'            => __( 'Name', 'foncholuis-art-challenge-manager' ),
			'concursos_email'  => __( 'Email', 'foncholuis-art-challenge-manager' ),
			'concursos_reto'   => __( 'Challenge', 'foncholuis-art-challenge-manager' ),
			'concursos_estado' => __( 'Status', 'foncholuis-art-challenge-manager' ),
			'concursos_redes'  => __( 'Social networks', 'foncholuis-art-challenge-manager' ),
			'date'             => __( 'Registration', 'foncholuis-art-challenge-manager' ),
		);
	}

	/**
	 * Contenido de las columnas propias.
	 *
	 * @param string $column Columna.
	 * @param int    $id     Participante.
	 */
	public static function column( $column, $id ) {
		$p = Concursos_Participante::get( $id );

		switch ( $column ) {
			case 'concursos_foto':
				echo has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, array( 40, 40 ), array( 'class' => 'concursos-avatar' ) ) : '<span class="concursos-avatar concursos-avatar--vacio" aria-hidden="true"></span>';
				break;
			case 'concursos_email':
				printf( '<a href="mailto:%1$s">%2$s</a>', esc_attr( $p['email'] ), esc_html( $p['email'] ) );
				break;
			case 'concursos_reto':
				echo $p['reto'] ? esc_html( get_the_title( $p['reto'] ) ) : '—';
				break;
			case 'concursos_estado':
				$labels = Concursos_Participante::estado_labels();
				printf( '<span class="concursos-badge concursos-badge--%1$s">%2$s</span>', esc_attr( $p['estado'] ), esc_html( $labels[ $p['estado'] ] ?? $p['estado'] ) );
				if ( $p['enlace_enviado'] ) {
					echo '<br><small>' . esc_html__( 'Link sent', 'foncholuis-art-challenge-manager' ) . '</small>';
				}
				break;
			case 'concursos_redes':
				self::social_links( $p );
				break;
		}
	}

	/**
	 * Enlaces a las redes de un participante.
	 *
	 * @param array $p Participante.
	 */
	private static function social_links( array $p ) {
		$links = array();
		foreach ( Concursos_Participante::redes( $p ) as $red => $url ) {
			$links[] = sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $url ), esc_html( $red ) );
		}
		echo $links ? wp_kses_post( implode( ' · ', $links ) ) : '—';
	}

	/**
	 * Acciones en bloque: activar y desactivar, antes de las de WordPress.
	 *
	 * @param array $actions Acciones por defecto.
	 * @return array
	 */
	public static function bulk_actions( $actions ) {
		unset( $actions['edit'] );

		return array(
			'concursos_activar'    => __( 'Activate (approve)', 'foncholuis-art-challenge-manager' ),
			'concursos_desactivar' => __( 'Deactivate', 'foncholuis-art-challenge-manager' ),
			'concursos_reenviar'   => __( 'Resend personal link', 'foncholuis-art-challenge-manager' ),
		) + $actions;
	}

	/**
	 * Ejecuta las acciones en bloque propias.
	 *
	 * @param string $redirect URL de vuelta.
	 * @param string $action   Acción.
	 * @param int[]  $ids      Participantes seleccionados.
	 * @return string
	 */
	public static function handle_bulk( $redirect, $action, $ids ) {
		if ( ! in_array( $action, array( 'concursos_activar', 'concursos_desactivar', 'concursos_reenviar' ), true ) ) {
			return $redirect;
		}

		if ( 'concursos_reenviar' === $action ) {
			$count = 0;
			foreach ( array_map( 'absint', (array) $ids ) as $id ) {
				if ( current_user_can( 'edit_post', $id ) && self::TYPE === get_post_type( $id ) && Concursos_Inscripcion::reenviar_enlace( $id ) ) {
					++$count;
				}
			}

			return add_query_arg(
				array(
					'concursos_hecho'   => 'reenviados',
					'concursos_n'       => $count,
					'concursos_saltados' => count( (array) $ids ) - $count,
				),
				$redirect
			);
		}

		$count = 0;
		foreach ( array_map( 'absint', (array) $ids ) as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) || self::TYPE !== get_post_type( $id ) ) {
				continue;
			}
			if ( 'concursos_activar' === $action ) {
				Concursos_Inscripcion::activar( $id );
			} else {
				Concursos_Participante::set_estado( $id, Concursos_Participante::INACTIVO );
			}
			++$count;
		}

		return add_query_arg(
			array(
				'concursos_hecho' => 'concursos_activar' === $action ? 'activados' : 'desactivados',
				'concursos_n'     => $count,
			),
			$redirect
		);
	}

	/**
	 * Quita "Edición rápida" de cada fila: los datos no se editan desde ahí.
	 *
	 * @param array   $actions Acciones de la fila.
	 * @param WP_Post $post    Post de la fila.
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( self::TYPE !== $post->post_type ) {
			return $actions;
		}
		unset( $actions['inline hide-if-no-js'] );

		// Aprobar con un clic a quien está pendiente, o reactivar a quien se desactivó.
		if ( 'trash' !== $post->post_status && ! Concursos_Participante::esta_activo( $post->ID ) ) {
			$p       = Concursos_Participante::get( $post->ID );
			$url     = wp_nonce_url( admin_url( 'admin-post.php?action=concursos_aprobar&post=' . $post->ID ), 'concursos_aprobar_' . $post->ID );
			$actions = array(
				'concursos_aprobar' => sprintf(
					'<a href="%s">%s</a>',
					esc_url( $url ),
					Concursos_Participante::PENDIENTE === $p['estado'] ? esc_html__( 'Approve', 'foncholuis-art-challenge-manager' ) : esc_html__( 'Activate', 'foncholuis-art-challenge-manager' )
				),
			) + $actions;
		}

		if ( 'trash' !== $post->post_status && Concursos_Inscripcion::puede_reenviar( $post->ID ) ) {
			$actions['concursos_reenviar'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=concursos_reenviar&post=' . $post->ID ), 'concursos_reenviar_' . $post->ID ) ),
				esc_html__( 'Resend link', 'foncholuis-art-challenge-manager' )
			);
			$actions['concursos_copiar_enlace'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=concursos_copiar_enlace&post=' . $post->ID ), 'concursos_copiar_enlace_' . $post->ID ) ),
				esc_html__( 'Copy link', 'foncholuis-art-challenge-manager' )
			);
		}

		// Solo los participantes activos tienen perfil público, si los perfiles están activados.
		if ( Concursos_Perfil::activos() && Concursos_Participante::esta_activo( $post->ID ) ) {
			$perfil  = array(
				'concursos_perfil' => sprintf(
					'<a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( Concursos_Perfil::url( $post->ID ) ),
					esc_html__( 'View profile', 'foncholuis-art-challenge-manager' )
				),
			);
			$actions = array_slice( $actions, 0, 1, true ) + $perfil + array_slice( $actions, 1, null, true );
		}

		return $actions;
	}

	/**
	 * Aprueba a un participante desde el enlace de su fila.
	 */
	public static function aprobar() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- el nonce lleva el ID y se comprueba justo debajo.
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'concursos_aprobar_' . $id );
		if ( ! current_user_can( 'edit_post', $id ) || self::TYPE !== get_post_type( $id ) ) {
			wp_die( esc_html__( 'You cannot do this.', 'foncholuis-art-challenge-manager' ) );
		}

		Concursos_Inscripcion::activar( $id );

		$volver = wp_get_referer();
		if ( ! $volver ) {
			$volver = admin_url( 'edit.php?post_type=' . self::TYPE );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'concursos_hecho' => 'activados',
					'concursos_n'     => 1,
				),
				remove_query_arg( array( 'concursos_hecho', 'concursos_n' ), $volver )
			)
		);
		exit;
	}

	/**
	 * Reenvía el enlace personal desde el enlace de su fila.
	 */
	public static function reenviar() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- el nonce lleva el ID y se comprueba justo debajo.
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'concursos_reenviar_' . $id );
		if ( ! current_user_can( 'edit_post', $id ) || self::TYPE !== get_post_type( $id ) ) {
			wp_die( esc_html__( 'You cannot do this.', 'foncholuis-art-challenge-manager' ) );
		}
		$enviado = Concursos_Inscripcion::reenviar_enlace( $id );

		$volver = wp_get_referer();
		if ( ! $volver ) {
			$volver = admin_url( 'edit.php?post_type=' . self::TYPE );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'concursos_hecho'    => 'reenviados',
					'concursos_n'        => $enviado ? 1 : 0,
					'concursos_saltados' => $enviado ? 0 : 1,
				),
				remove_query_arg( array( 'concursos_hecho', 'concursos_n', 'concursos_saltados' ), $volver )
			)
		);
		exit;
	}

	/**
	 * Genera un enlace personal nuevo y vuelve a la lista para mostrarlo y copiarlo.
	 * El enlace no viaja en la URL: se guarda unos minutos solo para este usuario.
	 */
	public static function copiar_enlace() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- el nonce lleva el ID y se comprueba justo debajo.
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'concursos_copiar_enlace_' . $id );
		if ( ! current_user_can( 'edit_post', $id ) || self::TYPE !== get_post_type( $id ) ) {
			wp_die( esc_html__( 'You cannot do this.', 'foncholuis-art-challenge-manager' ) );
		}
		$enlace = Concursos_Inscripcion::enlace_nuevo( $id );
		if ( $enlace ) {
			set_transient(
				self::clave_enlace(),
				array(
					'id'     => $id,
					'enlace' => $enlace,
				),
				5 * MINUTE_IN_SECONDS
			);
		}

		$volver = wp_get_referer();
		if ( ! $volver ) {
			$volver = admin_url( 'edit.php?post_type=' . self::TYPE );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'concursos_hecho'    => $enlace ? 'enlace' : 'reenviados',
					'concursos_n'        => 0,
					'concursos_saltados' => $enlace ? 0 : 1,
				),
				remove_query_arg( array( 'concursos_hecho', 'concursos_n', 'concursos_saltados' ), $volver )
			)
		);
		exit;
	}

	/**
	 * Transitorio con el último enlace generado por el usuario actual.
	 *
	 * @return string
	 */
	private static function clave_enlace() {
		return 'concursos_enlace_' . get_current_user_id();
	}

	/**
	 * Aviso con el enlace recién generado, para copiarlo. Se muestra una sola vez.
	 */
	private static function aviso_enlace() {
		$datos = get_transient( self::clave_enlace() );
		delete_transient( self::clave_enlace() );
		if ( ! is_array( $datos ) || empty( $datos['enlace'] ) ) {
			return;
		}
		$p = Concursos_Participante::get( $datos['id'] );
		?>
		<div class="notice notice-success concursos-copiar-enlace">
			<p>
				<?php
				/* translators: %s: participant name. */
				echo esc_html( sprintf( __( 'New personal link for %s. Send it only to this person: the previous link no longer works.', 'foncholuis-art-challenge-manager' ), $p['nombre'] ) );
				?>
			</p>
			<p>
				<label class="screen-reader-text" for="concursos-enlace"><?php esc_html_e( 'Personal link', 'foncholuis-art-challenge-manager' ); ?></label>
				<input type="text" id="concursos-enlace" class="large-text code" value="<?php echo esc_attr( $datos['enlace'] ); ?>" readonly>
			</p>
			<p>
				<button type="button" class="button button-primary" data-concursos-copiar="concursos-enlace" data-copiado="<?php esc_attr_e( 'Copied', 'foncholuis-art-challenge-manager' ); ?>"><?php esc_html_e( 'Copy to clipboard', 'foncholuis-art-challenge-manager' ); ?></button>
				<span class="description"><?php esc_html_e( 'This link is only shown now. If you lose it, generate another one.', 'foncholuis-art-challenge-manager' ); ?></span>
			</p>
		</div>
		<?php
	}

	/**
	 * Aviso tras una acción en bloque.
	 */
	public static function notices() {
		$screen = get_current_screen();
		$hecho  = self::filter_value( 'concursos_hecho' );
		if ( ! $screen || 'edit-' . self::TYPE !== $screen->id || ! $hecho ) {
			return;
		}
		if ( 'enlace' === $hecho ) {
			self::aviso_enlace();

			return;
		}
		$n = absint( self::filter_value( 'concursos_n' ) );
		if ( 'reenviados' === $hecho ) {
			$saltados = absint( self::filter_value( 'concursos_saltados' ) );
			/* translators: %d: number of participants. */
			$text = sprintf( _n( 'Personal link resent to %d participant. The previous link no longer works.', 'Personal link resent to %d participants. Their previous links no longer work.', $n, 'foncholuis-art-challenge-manager' ), $n );
			if ( $saltados ) {
				/* translators: %d: number of participants. */
				$text .= ' ' . sprintf( _n( '%d was skipped: only active participants, or pending ones in a challenge that approves on email confirmation, get a link, and only until voting ends.', '%d were skipped: only active participants, or pending ones in a challenge that approves on email confirmation, get a link, and only until voting ends.', $saltados, 'foncholuis-art-challenge-manager' ), $saltados );
			}
			printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $n ? 'notice-success' : 'notice-warning' ), esc_html( $text ) );

			return;
		}
		if ( 'activados' === $hecho ) {
			/* translators: %d: number of participants. */
			$text = sprintf( _n( '%d participant activated. Anyone without a personal link received it by email.', '%d participants activated. Anyone without a personal link received it by email.', $n, 'foncholuis-art-challenge-manager' ), $n );
		} else {
			/* translators: %d: number of participants. */
			$text = sprintf( _n( '%d participant deactivated: their photos are hidden and their votes have been deleted.', '%d participants deactivated: their photos are hidden and their votes have been deleted.', $n, 'foncholuis-art-challenge-manager' ), $n );
		}
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $text ) );
	}

	/**
	 * Quita los parámetros del aviso de la URL tras mostrarlo.
	 *
	 * @param string[] $args Parámetros removibles.
	 * @return string[]
	 */
	public static function removable_args( $args ) {
		$args[] = 'concursos_hecho';
		$args[] = 'concursos_n';
		$args[] = 'concursos_saltados';

		return $args;
	}

	/**
	 * Caja con los datos de la inscripción en la pantalla de edición.
	 */
	public static function meta_box() {
		add_meta_box( 'concursos-participante', __( 'Registration details', 'foncholuis-art-challenge-manager' ), array( __CLASS__, 'render_meta_box' ), null, 'normal', 'high' );
	}

	/**
	 * Pinta los datos de la inscripción.
	 *
	 * @param WP_Post $post Participante.
	 */
	public static function render_meta_box( $post ) {
		$p      = Concursos_Participante::get( $post->ID );
		$labels = Concursos_Participante::estado_labels();
		?>
		<table class="form-table">
			<tr><th scope="row"><?php esc_html_e( 'Email', 'foncholuis-art-challenge-manager' ); ?></th><td><?php echo esc_html( $p['email'] ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Challenge', 'foncholuis-art-challenge-manager' ); ?></th><td><?php echo $p['reto'] ? esc_html( get_the_title( $p['reto'] ) ) : '—'; ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Status', 'foncholuis-art-challenge-manager' ); ?></th><td><?php echo esc_html( $labels[ $p['estado'] ] ?? $p['estado'] ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Social networks', 'foncholuis-art-challenge-manager' ); ?></th><td><?php self::social_links( $p ); ?></td></tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Personal link', 'foncholuis-art-challenge-manager' ); ?></th>
				<td>
					<?php
					echo $p['enlace_enviado']
						/* translators: %s: date. */
						? esc_html( sprintf( __( 'Sent on %s', 'foncholuis-art-challenge-manager' ), Concursos_Reto::format_date( $p['enlace_enviado'] ) ) )
						: esc_html__( 'Sent when the participant is activated.', 'foncholuis-art-challenge-manager' );
					?>
				</td>
			</tr>
		</table>
		<p class="description"><?php esc_html_e( 'The status is changed from the participant list, using the bulk actions.', 'foncholuis-art-challenge-manager' ); ?></p>
		<?php
	}
}
