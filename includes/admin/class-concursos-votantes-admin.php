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
			'creado' => __( 'Fecha de registro', 'concursos' ),
			'email'  => __( 'Email', 'concursos' ),
			'estado' => __( 'Estado', 'concursos' ),
			'votos'  => __( 'Votos', 'concursos' ),
			'retos'  => __( 'Retos en los que votó', 'concursos' ),
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

		$where = '1=1';
		$args  = array();
		if ( '' !== $busqueda ) {
			$where  = 't.email LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $busqueda ) . '%';
		}

		// phpcs:disable WordPress.DB.PreparedSQL -- nombres de tabla y orden salen de valores fijos; los datos van preparados.
		$sql_total = "SELECT COUNT(*) FROM {$votantes} t WHERE {$where}";
		$total     = (int) $wpdb->get_var( $args ? $wpdb->prepare( $sql_total, $args ) : $sql_total );

		$pagina = $this->get_pagenum();
		$items  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.id, t.email, t.estado, t.creado, COUNT(v.id) AS votos FROM {$votantes} t LEFT JOIN {$votos} v ON v.votante_id = t.id WHERE {$where} GROUP BY t.id ORDER BY {$orden} {$sentido}, t.id DESC LIMIT %d OFFSET %d",
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
	 * Mensaje sin resultados.
	 */
	public function no_items() {
		esc_html_e( 'Todavía no hay votantes.', 'concursos' );
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
					$confirmado ? esc_html__( 'Confirmado', 'concursos' ) : esc_html__( 'Sin confirmar', 'concursos' )
				);
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
				/* translators: %d: votos en un reto ya borrado. */
				$retos[] = esc_html( sprintf( __( 'Reto borrado (%d)', 'concursos' ), $n ) );
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
		add_submenu_page(
			'edit.php?post_type=' . Concursos_Post_Types::RETO,
			__( 'Votantes', 'concursos' ),
			__( 'Votantes', 'concursos' ),
			self::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Pinta la página.
	 */
	public static function render() {
		$tabla = new Concursos_Votantes_Tabla();
		$tabla->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Votantes', 'concursos' ); ?></h1>
			<form method="get">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( Concursos_Post_Types::RETO ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<?php
				$tabla->search_box( __( 'Buscar por email', 'concursos' ), 'concursos-votantes' );
				$tabla->display();
				?>
			</form>
		</div>
		<?php
	}
}
