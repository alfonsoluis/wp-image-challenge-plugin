<?php
/**
 * Panel de administración: resumen de retos y columnas de la lista.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pantallas de administración de los retos.
 */
class Concursos_Admin {

	const PANEL = 'concursos-panel';

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		$type = Concursos_Post_Types::RETO;
		add_filter( "manage_{$type}_posts_columns", array( __CLASS__, 'columns' ) );
		add_action( "manage_{$type}_posts_custom_column", array( __CLASS__, 'column' ), 10, 2 );
	}

	/**
	 * Añade el panel como primera opción del menú "Retos".
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Concursos_Post_Types::RETO,
			__( 'Challenges dashboard', 'foncholuis-art-challenge-manager' ),
			__( 'Dashboard', 'foncholuis-art-challenge-manager' ),
			'edit_posts',
			self::PANEL,
			array( __CLASS__, 'render_panel' ),
			0
		);
	}

	/**
	 * Carga CSS y JS solo en las pantallas del plugin.
	 *
	 * @param string $hook Pantalla actual.
	 */
	public static function assets( $hook ) {
		$screen = get_current_screen();
		$types  = array( Concursos_Post_Types::RETO, Concursos_Participante::POST_TYPE, Concursos_Entrada::POST_TYPE );
		$ours   = ( $screen && in_array( $screen->post_type, $types, true ) ) || false !== strpos( $hook, self::PANEL ) || false !== strpos( $hook, Concursos_Votantes_Admin::PAGE ) || false !== strpos( $hook, Concursos_Stats_Admin::PAGE );
		if ( ! $ours ) {
			return;
		}

		wp_enqueue_style( 'concursos-admin', CONCURSOS_URL . 'assets/css/admin.css', array(), CONCURSOS_VERSION );
		wp_enqueue_script( 'concursos-admin', CONCURSOS_URL . 'assets/js/admin.js', array(), CONCURSOS_VERSION, true );
	}

	/**
	 * Pinta el panel de retos.
	 */
	public static function render_panel() {
		$retos  = Concursos_Reto::all();
		$labels = Concursos_Reto::status_labels();
		$counts = array_fill_keys( array_keys( $labels ), 0 );
		foreach ( $retos as $reto ) {
			// Las fases solo cuentan en los retos que se ven en el sitio.
			if ( 'publish' === $reto['post']->post_status ) {
				++$counts[ $reto['status'] ];
			}
		}
		$en_curso = $counts['envios'] + $counts['votacion'] + $counts['evaluacion'];
		$new_url  = admin_url( 'post-new.php?post_type=' . Concursos_Post_Types::RETO );

		$stats = array(
			array( __( 'Challenges', 'foncholuis-art-challenge-manager' ), count( $retos ) ),
			array( __( 'In progress', 'foncholuis-art-challenge-manager' ), $en_curso ),
			array( __( 'Accepting photos', 'foncholuis-art-challenge-manager' ), $counts['envios'] ),
			array( __( 'Voting', 'foncholuis-art-challenge-manager' ), $counts['votacion'] ),
			array( __( 'Upcoming challenges', 'foncholuis-art-challenge-manager' ), $counts['proximo'] ),
			array( __( 'Finished challenges', 'foncholuis-art-challenge-manager' ), $counts['finalizado'] ),
		);
		?>
		<div class="wrap concursos-panel">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Challenges dashboard', 'foncholuis-art-challenge-manager' ); ?></h1>
			<a href="<?php echo esc_url( $new_url ); ?>" class="page-title-action"><?php esc_html_e( 'New challenge', 'foncholuis-art-challenge-manager' ); ?></a>
			<hr class="wp-header-end">

			<div class="concursos-stats">
				<?php foreach ( $stats as $stat ) : ?>
					<div class="concursos-stat">
						<span class="concursos-stat__value"><?php echo esc_html( number_format_i18n( $stat[1] ) ); ?></span>
						<span class="concursos-stat__label"><?php echo esc_html( $stat[0] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( ! $retos ) : ?>
				<div class="concursos-empty">
					<p><?php esc_html_e( 'There are no challenges yet.', 'foncholuis-art-challenge-manager' ); ?></p>
					<a href="<?php echo esc_url( $new_url ); ?>" class="button button-primary button-hero"><?php esc_html_e( 'Create the first challenge', 'foncholuis-art-challenge-manager' ); ?></a>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col" class="column-primary"><?php esc_html_e( 'Challenge', 'foncholuis-art-challenge-manager' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Publication', 'foncholuis-art-challenge-manager' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Phase', 'foncholuis-art-challenge-manager' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Submissions until', 'foncholuis-art-challenge-manager' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Voting until', 'foncholuis-art-challenge-manager' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Results', 'foncholuis-art-challenge-manager' ); ?></th>
							<th scope="col" title="<?php esc_attr_e( 'Active / registered', 'foncholuis-art-challenge-manager' ); ?>"><?php esc_html_e( 'Participants', 'foncholuis-art-challenge-manager' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Photos', 'foncholuis-art-challenge-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $retos as $reto ) : ?>
							<tr>
								<td class="column-primary">
									<strong><a href="<?php echo esc_url( get_edit_post_link( $reto['post']->ID ) ); ?>"><?php echo esc_html( get_the_title( $reto['post'] ) ); ?></a></strong>
									<div class="row-actions"><a href="<?php echo esc_url( Concursos_Duplicar::url( $reto['post']->ID ) ); ?>"><?php esc_html_e( 'Duplicate', 'foncholuis-art-challenge-manager' ); ?></a></div>
								</td>
								<td><?php self::publicacion_badge( $reto['post']->post_status ); ?></td>
								<td>
									<?php
									if ( 'publish' === $reto['post']->post_status ) {
										self::status_badge( $reto['status'] );
									} else {
										echo '—';
									}
									?>
								</td>
								<td><?php echo esc_html( Concursos_Reto::format_date( $reto['settings']['fin_envios'] ) ); ?></td>
								<td><?php echo esc_html( Concursos_Reto::format_date( $reto['settings']['fin_votacion'] ) ); ?></td>
								<td><?php echo esc_html( Concursos_Reto::format_date( $reto['settings']['cierre'] ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Concursos_Participante::POST_TYPE . '&concursos_reto=' . $reto['post']->ID ) ); ?>">
										<?php echo esc_html( Concursos_Participante::count_for_reto( $reto['post']->ID, Concursos_Participante::ACTIVO ) . ' / ' . Concursos_Participante::count_for_reto( $reto['post']->ID ) ); ?>
									</a>
								</td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Concursos_Entrada::POST_TYPE . '&concursos_reto=' . $reto['post']->ID ) ); ?>">
										<?php echo esc_html( number_format_i18n( Concursos_Entrada::count_for_reto( $reto['post']->ID ) ) ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Columnas de la lista de retos.
	 *
	 * @param array $columns Columnas.
	 * @return array
	 */
	public static function columns( $columns ) {
		$date = $columns['date'] ?? null;
		unset( $columns['date'] );

		$columns['concursos_publicacion'] = __( 'Publication', 'foncholuis-art-challenge-manager' );
		$columns['concursos_estado']     = __( 'Phase', 'foncholuis-art-challenge-manager' );
		$columns['concursos_fin_envios'] = __( 'Submissions until', 'foncholuis-art-challenge-manager' );
		if ( $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Contenido de las columnas propias.
	 *
	 * @param string $column  Columna.
	 * @param int    $post_id Reto.
	 */
	public static function column( $column, $post_id ) {
		$s = Concursos_Reto::get_settings( $post_id );

		switch ( $column ) {
			case 'concursos_publicacion':
				self::publicacion_badge( get_post_status( $post_id ) );
				break;
			case 'concursos_estado':
				if ( 'publish' === get_post_status( $post_id ) ) {
					self::status_badge( Concursos_Reto::status( $s ) );
				} else {
					echo '—';
				}
				break;
			case 'concursos_fin_envios':
				echo esc_html( Concursos_Reto::format_date( $s['fin_envios'] ) );
				break;
		}
	}

	/**
	 * Etiqueta con el estado de publicación de un reto.
	 *
	 * @param string $status Estado del post.
	 */
	private static function publicacion_badge( $status ) {
		$labels = array(
			'publish'                       => __( 'Published', 'foncholuis-art-challenge-manager' ),
			'draft'                         => __( 'Draft', 'foncholuis-art-challenge-manager' ),
			'auto-draft'                    => __( 'Draft', 'foncholuis-art-challenge-manager' ),
			'future'                        => __( 'Scheduled', 'foncholuis-art-challenge-manager' ),
			'pending'                       => __( 'Pending', 'foncholuis-art-challenge-manager' ),
			'private'                       => __( 'Private', 'foncholuis-art-challenge-manager' ),
			Concursos_Post_Types::ARCHIVADO => __( 'Archived', 'foncholuis-art-challenge-manager' ),
		);
		printf(
			'<span class="concursos-badge concursos-badge--pub-%s">%s</span>',
			esc_attr( $status ),
			esc_html( $labels[ $status ] ?? $status )
		);
	}

	/**
	 * Etiqueta de estado con color.
	 *
	 * @param string $status Estado.
	 */
	private static function status_badge( $status ) {
		$labels = Concursos_Reto::status_labels();
		printf(
			'<span class="concursos-badge concursos-badge--%s">%s</span>',
			esc_attr( $status ),
			esc_html( $labels[ $status ] ?? $status )
		);
	}
}
