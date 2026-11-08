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
		<label class="screen-reader-text" for="concursos-filtro-reto"><?php esc_html_e( 'Filtrar por reto', 'concursos' ); ?></label>
		<select name="concursos_reto" id="concursos-filtro-reto">
			<option value=""><?php esc_html_e( 'Todos los retos', 'concursos' ); ?></option>
			<?php foreach ( Concursos_Reto::all() as $r ) : ?>
				<option value="<?php echo esc_attr( $r['post']->ID ); ?>" <?php selected( $reto, $r['post']->ID ); ?>><?php echo esc_html( get_the_title( $r['post'] ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<label class="screen-reader-text" for="concursos-filtro-estado"><?php esc_html_e( 'Filtrar por estado', 'concursos' ); ?></label>
		<select name="concursos_estado" id="concursos-filtro-estado">
			<option value=""><?php esc_html_e( 'Todos los estados', 'concursos' ); ?></option>
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
			'concursos_foto'   => __( 'Foto', 'concursos' ),
			'title'            => __( 'Nombre', 'concursos' ),
			'concursos_email'  => __( 'Email', 'concursos' ),
			'concursos_reto'   => __( 'Reto', 'concursos' ),
			'concursos_estado' => __( 'Estado', 'concursos' ),
			'concursos_redes'  => __( 'Redes', 'concursos' ),
			'date'             => __( 'Inscripción', 'concursos' ),
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
					echo '<br><small>' . esc_html__( 'Enlace enviado', 'concursos' ) . '</small>';
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
		if ( $p['instagram'] ) {
			$links[] = sprintf( '<a href="%s" target="_blank" rel="noopener">Instagram</a>', esc_url( 'https://www.instagram.com/' . $p['instagram'] . '/' ) );
		}
		if ( $p['facebook'] ) {
			$links[] = sprintf( '<a href="%s" target="_blank" rel="noopener">Facebook</a>', esc_url( 'https://www.facebook.com/' . $p['facebook'] ) );
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
			'concursos_activar'    => __( 'Activar (aprobar)', 'concursos' ),
			'concursos_desactivar' => __( 'Desactivar', 'concursos' ),
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
		if ( ! in_array( $action, array( 'concursos_activar', 'concursos_desactivar' ), true ) ) {
			return $redirect;
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
		if ( self::TYPE === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'] );
		}

		return $actions;
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
		$n = absint( self::filter_value( 'concursos_n' ) );
		if ( 'activados' === $hecho ) {
			/* translators: %d: número de participantes. */
			$text = sprintf( _n( '%d participante activado. Quien no tenía enlace personal lo recibió por email.', '%d participantes activados. Quienes no tenían enlace personal lo recibieron por email.', $n, 'concursos' ), $n );
		} else {
			/* translators: %d: número de participantes. */
			$text = sprintf( _n( '%d participante desactivado.', '%d participantes desactivados.', $n, 'concursos' ), $n );
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

		return $args;
	}

	/**
	 * Caja con los datos de la inscripción en la pantalla de edición.
	 */
	public static function meta_box() {
		add_meta_box( 'concursos-participante', __( 'Datos de la inscripción', 'concursos' ), array( __CLASS__, 'render_meta_box' ), null, 'normal', 'high' );
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
			<tr><th scope="row"><?php esc_html_e( 'Email', 'concursos' ); ?></th><td><?php echo esc_html( $p['email'] ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Reto', 'concursos' ); ?></th><td><?php echo $p['reto'] ? esc_html( get_the_title( $p['reto'] ) ) : '—'; ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Estado', 'concursos' ); ?></th><td><?php echo esc_html( $labels[ $p['estado'] ] ?? $p['estado'] ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Redes', 'concursos' ); ?></th><td><?php self::social_links( $p ); ?></td></tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Enlace personal', 'concursos' ); ?></th>
				<td>
					<?php
					echo $p['enlace_enviado']
						/* translators: %s: fecha. */
						? esc_html( sprintf( __( 'Enviado el %s', 'concursos' ), Concursos_Reto::format_date( $p['enlace_enviado'] ) ) )
						: esc_html__( 'Se envía al activar al participante.', 'concursos' );
					?>
				</td>
			</tr>
		</table>
		<p class="description"><?php esc_html_e( 'El estado se cambia desde la lista de participantes, con las acciones en bloque.', 'concursos' ); ?></p>
		<?php
	}
}
