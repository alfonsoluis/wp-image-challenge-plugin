<?php
/**
 * Página de administración de votantes.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Tabla de votantes: fecha, email, estado, votos y retos en los que votó.
 */
class Concursos_Votantes_Tabla extends WP_List_Table {

	const POR_PAGINA = 30;

	/**
	 * Votos por reto de los votantes de la página: votante => reto => votos.
	 *
	 * @var array
	 */
	private $por_reto = array();

	/**
	 * Configura la tabla.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'votante',
				'plural'   => 'votantes',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columnas.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'     => '<input type="checkbox">',
			'creado' => __( 'Registration date', 'photo-challenges' ),
			'email'  => __( 'Email', 'photo-challenges' ),
			'estado' => __( 'Status', 'photo-challenges' ),
			'avisos' => __( 'Notifications', 'photo-challenges' ),
			'votos'  => __( 'Votes', 'photo-challenges' ),
			'retos'  => __( 'Challenges voted in', 'photo-challenges' ),
		);
	}

	/**
	 * Columnas ordenables.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'creado' => array( 'creado', true ),
			'email'  => array( 'email', false ),
			'votos'  => array( 'votos', true ),
		);
	}

	/**
	 * Carga la página actual de votantes.
	 */
	public function prepare_items() {
		global $wpdb;

		$votantes = Concursos_DB::votantes();
		$votos    = Concursos_DB::votos();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- filtros de solo lectura.
		$busqueda = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$pedido   = isset( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : 'creado';
		$sentido  = isset( $_REQUEST['order'] ) && 'asc' === strtolower( sanitize_key( $_REQUEST['order'] ) ) ? 'ASC' : 'DESC';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$columnas = array(
			'creado' => 't.creado',
			'email'  => 't.email',
			'votos'  => 'votos',
		);
		$orden    = isset( $columnas[ $pedido ] ) ? $columnas[ $pedido ] : 't.creado';

		$condiciones = array( '1=1' );
		$args        = array();
		if ( '' !== $busqueda ) {
			$condiciones[] = 't.email LIKE %s';
			$args[]        = '%' . $wpdb->esc_like( $busqueda ) . '%';
		}
		if ( self::filtro_reto() ) {
			$condiciones[] = "EXISTS (SELECT 1 FROM {$votos} x WHERE x.votante_id = t.id AND x.reto_id = %d)";
			$args[]        = self::filtro_reto();
		}
		$desde = self::filtro_fecha( 'desde', '00:00:00' );
		$hasta = self::filtro_fecha( 'hasta', '23:59:59' );
		if ( $desde ) {
			$condiciones[] = 't.creado >= %d';
			$args[]        = $desde;
		}
		if ( $hasta ) {
			$condiciones[] = 't.creado <= %d';
			$args[]        = $hasta;
		}
		$where = implode( ' AND ', $condiciones );

		// phpcs:disable WordPress.DB.PreparedSQL -- nombres de tabla y orden salen de valores fijos; los datos van preparados.
		$sql_total = "SELECT COUNT(*) FROM {$votantes} t WHERE {$where}";
		$total     = (int) $wpdb->get_var( $args ? $wpdb->prepare( $sql_total, $args ) : $sql_total );

		$pagina = $this->get_pagenum();
		$items  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.id, t.email, t.estado, t.suscrito, t.creado, COUNT(v.id) AS votos FROM {$votantes} t LEFT JOIN {$votos} v ON v.votante_id = t.id WHERE {$where} GROUP BY t.id ORDER BY {$orden} {$sentido}, t.id DESC LIMIT %d OFFSET %d",
				array_merge( $args, array( self::POR_PAGINA, ( $pagina - 1 ) * self::POR_PAGINA ) )
			)
		);

		$ids = wp_list_pluck( $items, 'id' );
		if ( $ids ) {
			$lista = implode( ',', array_map( 'intval', $ids ) );
			foreach ( $wpdb->get_results( "SELECT votante_id, reto_id, COUNT(*) AS n FROM {$votos} WHERE votante_id IN ({$lista}) GROUP BY votante_id, reto_id ORDER BY reto_id DESC" ) as $fila ) {
				$this->por_reto[ (int) $fila->votante_id ][ (int) $fila->reto_id ] = (int) $fila->n;
			}
		}
		// phpcs:enable WordPress.DB.PreparedSQL

		$this->items           = $items;
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'email' );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => self::POR_PAGINA,
			)
		);
	}

	/**
	 * Reto elegido en el filtro.
	 *
	 * @return int
	 */
	private static function filtro_reto() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtro de solo lectura.
		return isset( $_REQUEST['concursos_reto'] ) ? absint( $_REQUEST['concursos_reto'] ) : 0;
	}

	/**
	 * Valor escrito en un campo de fecha.
	 *
	 * @param string $campo desde|hasta.
	 * @return string Fecha AAAA-MM-DD o vacío.
	 */
	private static function fecha_escrita( $campo ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtro de solo lectura.
		$valor = isset( $_REQUEST[ $campo ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $campo ] ) ) : '';

		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $valor ) ? $valor : '';
	}

	/**
	 * Marca de tiempo de un campo de fecha, en la zona horaria del sitio.
	 *
	 * @param string $campo desde|hasta.
	 * @param string $hora  Hora del día.
	 * @return int 0 si no hay fecha válida.
	 */
	private static function filtro_fecha( $campo, $hora ) {
		$fecha = self::fecha_escrita( $campo );
		$dia   = $fecha ? DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $fecha . ' ' . $hora, wp_timezone() ) : false;

		return $dia ? $dia->getTimestamp() : 0;
	}

	/**
	 * Filtros sobre la tabla.
	 *
	 * @param string $which Barra superior o inferior.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$reto = self::filtro_reto();
		?>
		<div class="alignleft actions concursos-filtros">
			<label class="screen-reader-text" for="concursos-filtro-reto"><?php esc_html_e( 'Filter by challenge', 'photo-challenges' ); ?></label>
			<select name="concursos_reto" id="concursos-filtro-reto">
				<option value=""><?php esc_html_e( 'All challenges', 'photo-challenges' ); ?></option>
				<?php foreach ( Concursos_Reto::all() as $r ) : ?>
					<option value="<?php echo esc_attr( $r['post']->ID ); ?>" <?php selected( $reto, $r['post']->ID ); ?>><?php echo esc_html( get_the_title( $r['post'] ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<label><?php esc_html_e( 'From', 'photo-challenges' ); ?> <input type="date" name="desde" value="<?php echo esc_attr( self::fecha_escrita( 'desde' ) ); ?>"></label>
			<label><?php esc_html_e( 'To', 'photo-challenges' ); ?> <input type="date" name="hasta" value="<?php echo esc_attr( self::fecha_escrita( 'hasta' ) ); ?>"></label>
			<?php submit_button( __( 'Filter', 'photo-challenges' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Acciones en bloque.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'concursos_baja'     => __( 'Unsubscribe from notifications', 'photo-challenges' ),
			'concursos_eliminar' => __( 'Delete', 'photo-challenges' ),
		);
	}

	/**
	 * Casilla de cada fila.
	 *
	 * @param object $item Votante.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="votante[]" value="%d">', (int) $item->id );
	}

	/**
	 * Mensaje sin resultados.
	 */
	public function no_items() {
		esc_html_e( 'There are no voters yet.', 'photo-challenges' );
	}

	/**
	 * Contenido de cada columna.
	 *
	 * @param object $item   Votante.
	 * @param string $column Columna.
	 * @return string
	 */
	protected function column_default( $item, $column ) {
		switch ( $column ) {
			case 'creado':
				return esc_html( Concursos_Reto::format_date( (int) $item->creado ) );
			case 'email':
				return '<strong>' . esc_html( $item->email ) . '</strong>';
			case 'estado':
				$confirmado = Concursos_Votantes::CONFIRMADO === $item->estado;

				return sprintf(
					'<span class="concursos-badge concursos-badge--%s">%s</span>',
					esc_attr( $item->estado ),
					$confirmado ? esc_html__( 'Confirmed', 'photo-challenges' ) : esc_html__( 'Unconfirmed', 'photo-challenges' )
				);
			case 'avisos':
				return $item->suscrito ? esc_html__( 'Yes', 'photo-challenges' ) : esc_html__( 'No', 'photo-challenges' );
			case 'votos':
				return esc_html( number_format_i18n( (int) $item->votos ) );
			case 'retos':
				return $this->retos( (int) $item->id );
		}

		return '';
	}

	/**
	 * Retos en los que votó, con sus votos en cada uno.
	 *
	 * @param int $id Votante.
	 * @return string
	 */
	private function retos( $id ) {
		if ( empty( $this->por_reto[ $id ] ) ) {
			return '—';
		}

		$retos = array();
		foreach ( $this->por_reto[ $id ] as $reto => $n ) {
			$titulo = get_the_title( $reto );
			$enlace = get_edit_post_link( $reto );
			if ( ! $titulo ) {
				/* translators: %d: votes in a deleted challenge. */
				$retos[] = esc_html( sprintf( __( 'Deleted challenge (%d)', 'photo-challenges' ), $n ) );
				continue;
			}
			$retos[] = sprintf(
				'%1$s (%2$s)',
				$enlace ? '<a href="' . esc_url( $enlace ) . '">' . esc_html( $titulo ) . '</a>' : esc_html( $titulo ),
				esc_html( number_format_i18n( $n ) )
			);
		}

		return implode( '<br>', $retos );
	}
}

/**
 * Registra y pinta la página de votantes.
 */
class Concursos_Votantes_Admin {

	const PAGE       = 'concursos-votantes';
	const CAPABILITY = 'edit_others_posts';

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
	}

	/**
	 * Añade la página al menú "Retos".
	 */
	public static function menu() {
		$hook = add_submenu_page(
			'edit.php?post_type=' . Concursos_Post_Types::RETO,
			__( 'Voters', 'photo-challenges' ),
			__( 'Voters', 'photo-challenges' ),
			self::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render' )
		);
		add_action( 'load-' . $hook, array( __CLASS__, 'procesar' ) );
		add_filter( 'removable_query_args', array( __CLASS__, 'removable_args' ) );
	}

	/**
	 * Quita de la URL los parámetros del aviso.
	 *
	 * @param string[] $args Parámetros.
	 * @return string[]
	 */
	public static function removable_args( $args ) {
		$args[] = 'concursos_hecho';
		$args[] = 'concursos_n';

		return $args;
	}

	/**
	 * Ejecuta una acción en bloque antes de pintar la página.
	 */
	public static function procesar() {
		$tabla  = new Concursos_Votantes_Tabla();
		$accion = $tabla->current_action();
		if ( ! in_array( $accion, array( 'concursos_baja', 'concursos_eliminar' ), true ) ) {
			return;
		}

		check_admin_referer( 'bulk-votantes' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You cannot do this.', 'photo-challenges' ) );
		}

		global $wpdb;
		$ids = isset( $_REQUEST['votante'] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_REQUEST['votante'] ) ) ) : array();
		$n   = count( $ids );
		if ( $ids ) {
			$lista = implode( ',', $ids );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombres de tabla fijos e IDs enteros.
			if ( 'concursos_eliminar' === $accion ) {
				$wpdb->query( 'DELETE FROM ' . Concursos_DB::votos() . " WHERE votante_id IN ({$lista})" );
				$wpdb->query( 'DELETE FROM ' . Concursos_DB::votantes() . " WHERE id IN ({$lista})" );
			} else {
				$wpdb->query( 'UPDATE ' . Concursos_DB::votantes() . " SET suscrito = 0 WHERE id IN ({$lista})" );
			}
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$url = remove_query_arg( array( 'action', 'action2', 'votante', '_wpnonce', '_wp_http_referer', 'filter_action' ) );
		wp_safe_redirect(
			add_query_arg(
				array(
					'concursos_hecho' => 'concursos_eliminar' === $accion ? 'eliminados' : 'bajas',
					'concursos_n'     => $n,
				),
				$url
			)
		);
		exit;
	}

	/**
	 * Aviso tras una acción en bloque.
	 */
	private static function aviso() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- solo muestra un aviso.
		$hecho = isset( $_GET['concursos_hecho'] ) ? sanitize_key( wp_unslash( $_GET['concursos_hecho'] ) ) : '';
		$n     = isset( $_GET['concursos_n'] ) ? absint( $_GET['concursos_n'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( 'eliminados' === $hecho ) {
			/* translators: %d: number of voters. */
			$texto = sprintf( _n( '%d voter and their votes deleted.', '%d voters and their votes deleted.', $n, 'photo-challenges' ), $n );
		} elseif ( 'bajas' === $hecho ) {
			/* translators: %d: number of voters. */
			$texto = sprintf( _n( '%d voter will no longer receive notifications.', '%d voters will no longer receive notifications.', $n, 'photo-challenges' ), $n );
		} else {
			return;
		}
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $texto ) );
	}

	/**
	 * Pinta la página.
	 */
	public static function render() {
		$tabla = new Concursos_Votantes_Tabla();
		$tabla->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Voters', 'photo-challenges' ); ?></h1>
			<?php self::aviso(); ?>
			<form method="get" id="concursos-votantes-form" data-confirmar="<?php esc_attr_e( 'Delete the selected voters and all their votes? Results may change and this cannot be undone.', 'photo-challenges' ); ?>">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( Concursos_Post_Types::RETO ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<?php
				$tabla->search_box( __( 'Search by email', 'photo-challenges' ), 'concursos-votantes' );
				$tabla->display();
				?>
			</form>
		</div>
		<?php
	}
}
