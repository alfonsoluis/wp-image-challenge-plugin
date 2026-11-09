<?php
/**
 * Administración de las fotos enviadas.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lista de fotos en Retos → Fotos enviadas, con filtro por reto.
 */
class Concursos_Entradas_Admin {

	const TYPE = Concursos_Entrada::POST_TYPE;

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
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
	}

	/**
	 * Reto elegido en el filtro.
	 *
	 * @return int
	 */
	private static function selected_reto() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtro de solo lectura.
		return isset( $_GET['concursos_reto'] ) ? absint( $_GET['concursos_reto'] ) : 0;
	}

	/**
	 * Desplegable para filtrar por reto.
	 *
	 * @param string $post_type Tipo de contenido de la lista.
	 */
	public static function filters( $post_type ) {
		if ( self::TYPE !== $post_type ) {
			return;
		}
		$reto = self::selected_reto();
		?>
		<label class="screen-reader-text" for="concursos-filtro-reto"><?php esc_html_e( 'Filter by challenge', 'concursos' ); ?></label>
		<select name="concursos_reto" id="concursos-filtro-reto">
			<option value=""><?php esc_html_e( 'All challenges', 'concursos' ); ?></option>
			<?php foreach ( Concursos_Reto::all() as $r ) : ?>
				<option value="<?php echo esc_attr( $r['post']->ID ); ?>" <?php selected( $reto, $r['post']->ID ); ?>><?php echo esc_html( get_the_title( $r['post'] ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Aplica el filtro a la consulta de la lista.
	 *
	 * @param WP_Query $query Consulta.
	 */
	public static function filter_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || self::TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		$reto = self::selected_reto();
		if ( $reto ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'   => Concursos_Entrada::META . 'reto',
						'value' => $reto,
					),
				)
			);
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
			'cb'                     => $columns['cb'],
			'concursos_foto'         => __( 'Photo', 'concursos' ),
			'title'                  => __( 'Title', 'concursos' ),
			'concursos_participante' => __( 'Participant', 'concursos' ),
			'concursos_reto'         => __( 'Challenge', 'concursos' ),
			'concursos_votos'        => __( 'Votes', 'concursos' ),
			'date'                   => __( 'Submitted', 'concursos' ),
		);
	}

	/**
	 * Contenido de las columnas propias.
	 *
	 * @param string $column Columna.
	 * @param int    $id     Entrada.
	 */
	public static function column( $column, $id ) {
		switch ( $column ) {
			case 'concursos_foto':
				$full = wp_get_attachment_url( get_post_thumbnail_id( $id ) );
				if ( $full ) {
					printf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $full ), get_the_post_thumbnail( $id, array( 80, 80 ), array( 'class' => 'concursos-miniatura' ) ) );
				}
				break;
			case 'concursos_participante':
				$owner = Concursos_Entrada::owner( $id );
				echo ( $owner && get_post( $owner ) ) ? '<a href="' . esc_url( get_edit_post_link( $owner ) ) . '">' . esc_html( get_the_title( $owner ) ) . '</a>' : '—';
				break;
			case 'concursos_reto':
				$reto = (int) get_post_meta( $id, Concursos_Entrada::META . 'reto', true );
				echo $reto ? esc_html( get_the_title( $reto ) ) : '—';
				break;
			case 'concursos_votos':
				echo esc_html( number_format_i18n( Concursos_Votos::count_entrada( $id ) ) );
				break;
		}
	}

	/**
	 * Sin edición en bloque: solo papelera.
	 *
	 * @param array $actions Acciones.
	 * @return array
	 */
	public static function bulk_actions( $actions ) {
		unset( $actions['edit'] );

		return $actions;
	}

	/**
	 * Sin edición rápida en cada fila.
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
}
