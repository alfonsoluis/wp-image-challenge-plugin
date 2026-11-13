<?php
/**
 * Formulario de configuración de un reto.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Meta boxes de la pantalla de edición de un reto.
 */
class Concursos_Reto_Metabox {

	const NONCE = 'concursos_reto_nonce';

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_action( 'add_meta_boxes_' . Concursos_Post_Types::RETO, array( __CLASS__, 'add' ) );
		add_action( 'save_post_' . Concursos_Post_Types::RETO, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'edit_form_after_title', array( __CLASS__, 'description_heading' ) );
		add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'accion_archivar' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'archivar_al_guardar' ), 10, 2 );
	}

	/**
	 * Opción de archivar en la caja de publicación.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function accion_archivar( $post ) {
		if ( Concursos_Post_Types::RETO !== $post->post_type ) {
			return;
		}

		echo '<div class="misc-pub-section">';
		if ( Concursos_Post_Types::ARCHIVADO === $post->post_status ) {
			echo '<input type="hidden" name="concursos_archivar" value="1">';
			echo '<strong>' . esc_html__( 'Archived:', 'foncholuis-art-challenge-manager' ) . '</strong> ' . esc_html__( 'this challenge is hidden from the site. Publish it to show it.', 'foncholuis-art-challenge-manager' );
		} else {
			echo '<button type="submit" class="button-link" name="concursos_archivar" value="1">' . esc_html__( 'Archive this challenge', 'foncholuis-art-challenge-manager' ) . '</button>';
			echo '<p class="description">' . esc_html__( 'Archived challenges are hidden from the site and send no notifications.', 'foncholuis-art-challenge-manager' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Guarda el reto como archivado si se pidió, salvo que se esté publicando.
	 *
	 * @param array $data    Datos del post.
	 * @param array $postarr Datos recibidos.
	 * @return array
	 */
	public static function archivar_al_guardar( $data, $postarr ) {
		if ( Concursos_Post_Types::RETO !== $data['post_type'] || empty( $_POST['concursos_archivar'] ) || isset( $_POST['publish'] ) ) {
			return $data;
		}
		$nonce = isset( $_POST[ self::NONCE ] ) ? sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ) : '';
		if ( wp_verify_nonce( $nonce, 'concursos_save_reto' ) && current_user_can( 'edit_post', (int) $postarr['ID'] ) ) {
			$data['post_status'] = Concursos_Post_Types::ARCHIVADO;
		}

		return $data;
	}

	/**
	 * Registra las cajas.
	 */
	public static function add() {
		add_meta_box( 'concursos-calendario', __( 'Schedule', 'foncholuis-art-challenge-manager' ), array( __CLASS__, 'render_calendar' ), null, 'normal', 'high' );
		add_meta_box( 'concursos-reglas', __( 'Rules', 'foncholuis-art-challenge-manager' ), array( __CLASS__, 'render_rules' ), null, 'normal', 'high' );
		add_meta_box( 'concursos-votacion', __( 'Participation, voting and prizes', 'foncholuis-art-challenge-manager' ), array( __CLASS__, 'render_voting' ), null, 'normal', 'high' );
		add_meta_box( 'concursos-diseno', __( 'Design', 'foncholuis-art-challenge-manager' ), array( __CLASS__, 'render_design' ), null, 'normal', 'high' );
	}

	/**
	 * Título sobre el editor: el contenido del reto es su descripción.
	 *
	 * @param WP_Post $post Reto.
	 */
	public static function description_heading( $post ) {
		if ( Concursos_Post_Types::RETO === $post->post_type ) {
			echo '<h2 class="concursos-section-title">' . esc_html__( 'General description', 'foncholuis-art-challenge-manager' ) . '</h2>';
		}
	}

	/**
	 * Caja del calendario: tipo, recurrencia y fechas.
	 *
	 * @param WP_Post $post Reto.
	 */
	public static function render_calendar( $post ) {
		$s      = Concursos_Reto::get_settings( $post->ID );
		$labels = Concursos_Reto::date_labels();

		wp_nonce_field( 'concursos_save_reto', self::NONCE );
		?>
		<table class="form-table concursos-form">
			<?php foreach ( Concursos_Reto::FECHAS as $key ) : ?>
				<tr>
					<th scope="row"><label for="concursos-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $labels[ $key ] ); ?></label></th>
					<td>
						<input type="datetime-local" id="concursos-<?php echo esc_attr( $key ); ?>" name="concursos[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( Concursos_Reto::timestamp_to_input( $s[ $key ] ) ); ?>">
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<p class="description">
			<?php esc_html_e( 'Submissions: from the start until the end of submissions. Voting: from the end of submissions until the end of voting. Votes are reviewed between the end of voting and the publication of results.', 'foncholuis-art-challenge-manager' ); ?>
		</p>
		<?php
	}

	/**
	 * Caja de reglas.
	 *
	 * @param WP_Post $post Reto.
	 */
	public static function render_rules( $post ) {
		$s = Concursos_Reto::get_settings( $post->ID );
		?>
		<p><label for="concursos-reglas"><?php esc_html_e( 'One rule per line.', 'foncholuis-art-challenge-manager' ); ?></label></p>
		<textarea id="concursos-reglas" name="concursos[reglas]" rows="6" class="large-text"><?php echo esc_textarea( $s['reglas'] ); ?></textarea>
		<?php
	}

	/**
	 * Caja de participación, votación y premios.
	 *
	 * @param WP_Post $post Reto.
	 */
	public static function render_voting( $post ) {
		$s       = Concursos_Reto::get_settings( $post->ID );
		$choices = Concursos_Reto::choices();
		?>
		<table class="form-table concursos-form">
			<tr>
				<th scope="row"><?php esc_html_e( 'Photo visibility', 'foncholuis-art-challenge-manager' ); ?></th>
				<td>
					<?php foreach ( $choices['modo'] as $value => $label ) : ?>
						<label class="concursos-radio concursos-radio--block">
							<input type="radio" name="concursos[modo]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $s['modo'], $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
			<?php
			$accesos = array(
				'participantes' => array( __( 'Who can participate', 'foncholuis-art-challenge-manager' ), __( 'Private: only the emails on this list can register', 'foncholuis-art-challenge-manager' ) ),
				'votantes'      => array( __( 'Who can vote', 'foncholuis-art-challenge-manager' ), __( 'Private: only the emails on this list can vote', 'foncholuis-art-challenge-manager' ) ),
			);
			foreach ( $accesos as $grupo => $textos ) :
				// En un reto nuevo la participación es privada por defecto; votar, no.
				$privado = 'auto-draft' === $post->post_status ? 'participantes' === $grupo : ! empty( $s[ 'privado_' . $grupo ] );
				?>
				<tr>
					<th scope="row"><?php echo esc_html( $textos[0] ); ?></th>
					<td>
						<label class="concursos-radio--block">
							<input type="checkbox" class="concursos-privado" data-grupo="<?php echo esc_attr( $grupo ); ?>" name="concursos[privado_<?php echo esc_attr( $grupo ); ?>]" value="1" <?php checked( $privado ); ?>>
							<?php echo esc_html( $textos[1] ); ?>
						</label>
						<div class="concursos-if-privado-<?php echo esc_attr( $grupo ); ?>">
							<textarea name="concursos[emails_<?php echo esc_attr( $grupo ); ?>]" rows="3" class="large-text" aria-label="<?php echo esc_attr( $textos[0] ); ?>"><?php echo esc_textarea( $s[ 'emails_' . $grupo ] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Email addresses separated by commas.', 'foncholuis-art-challenge-manager' ); ?></p>
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"><label for="concursos-max-fotos"><?php esc_html_e( 'Photos per participant', 'foncholuis-art-challenge-manager' ); ?></label></th>
				<td><input type="number" id="concursos-max-fotos" name="concursos[max_fotos]" min="1" max="20" value="<?php echo esc_attr( $s['max_fotos'] ); ?>" class="small-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="concursos-votos"><?php esc_html_e( 'Votes per voter', 'foncholuis-art-challenge-manager' ); ?></label></th>
				<td>
					<select id="concursos-votos" name="concursos[votos_por_votante]">
						<?php for ( $i = 1; $i <= 3; $i++ ) : ?>
							<option value="<?php echo esc_attr( $i ); ?>" <?php selected( (int) $s['votos_por_votante'], $i ); ?>>
								<?php
								/* translators: %d: number of photos a voter can choose. */
								echo esc_html( sprintf( _n( '%d photo only', 'Up to %d photos', $i, 'foncholuis-art-challenge-manager' ), $i ) );
								?>
							</option>
						<?php endfor; ?>
					</select>
					<p class="description"><?php esc_html_e( 'With more than one vote, each voter chooses a 1st, 2nd and 3rd, worth 3, 2 and 1 points.', 'foncholuis-art-challenge-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Anonymous voting', 'foncholuis-art-challenge-manager' ); ?></th>
				<td>
					<label class="concursos-radio--block">
						<input type="checkbox" name="concursos[anonimo]" value="1" <?php checked( ! empty( $s['anonimo'] ) ); ?>>
						<?php esc_html_e( 'Hide the title and author of each photo until voting ends', 'foncholuis-art-challenge-manager' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Winners', 'foncholuis-art-challenge-manager' ); ?></th>
				<td>
					<?php foreach ( $choices['lugares'] as $value => $label ) : ?>
						<label class="concursos-radio">
							<input type="radio" name="concursos[lugares]" value="<?php echo esc_attr( $value ); ?>" <?php checked( (int) $s['lugares'], $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
			<?php
			$places = array(
				__( '1st place prize', 'foncholuis-art-challenge-manager' ),
				__( '2nd place prize', 'foncholuis-art-challenge-manager' ),
				__( '3rd place prize', 'foncholuis-art-challenge-manager' ),
			);
			foreach ( $places as $i => $label ) :
				?>
				<tr class="<?php echo $i > 0 ? 'concursos-if-tres-lugares' : ''; ?>">
					<th scope="row"><label for="concursos-premio-<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td>
						<input type="text" id="concursos-premio-<?php echo esc_attr( $i ); ?>" name="concursos[premios][<?php echo esc_attr( $i ); ?>]" value="<?php echo esc_attr( $s['premios'][ $i ] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Optional', 'foncholuis-art-challenge-manager' ); ?>">
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Caja de diseño: estilo, acento y tamaño.
	 *
	 * @param WP_Post $post Reto.
	 */
	public static function render_design( $post ) {
		$s = Concursos_Reto::get_settings( $post->ID );
		?>
		<table class="form-table concursos-form">
			<tr>
				<th scope="row"><?php esc_html_e( 'Style', 'foncholuis-art-challenge-manager' ); ?></th>
				<td class="concursos-opciones">
					<?php foreach ( Concursos_Diseno::estilos() as $clave => $nombre ) : ?>
						<label class="concursos-opcion">
							<input type="radio" name="concursos[estilo]" value="<?php echo esc_attr( $clave ); ?>" <?php checked( $s['estilo'], $clave ); ?>>
							<span><?php echo esc_html( $nombre ); ?></span>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Accent color', 'foncholuis-art-challenge-manager' ); ?></th>
				<td class="concursos-opciones">
					<?php foreach ( Concursos_Diseno::acentos() as $clave => $a ) : ?>
						<label class="concursos-opcion">
							<input type="radio" name="concursos[acento]" value="<?php echo esc_attr( $clave ); ?>" <?php checked( $s['acento'], $clave ); ?>>
							<?php if ( $a['color'] ) : ?>
								<span class="concursos-muestras" aria-hidden="true"><i style="background:<?php echo esc_attr( $a['color'] ); ?>"></i></span>
							<?php endif; ?>
							<span><?php echo esc_html( $a['nombre'] ); ?></span>
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Used for the current phase, highlights and votes. Typography always comes from your theme.', 'foncholuis-art-challenge-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Text size', 'foncholuis-art-challenge-manager' ); ?></th>
				<td>
					<?php foreach ( Concursos_Diseno::tamanos() as $clave => $t ) : ?>
						<label class="concursos-radio">
							<input type="radio" name="concursos[tamano]" value="<?php echo esc_attr( $clave ); ?>" <?php checked( $s['tamano'], $clave ); ?>>
							<?php echo esc_html( $t['nombre'] ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Guarda la configuración del reto.
	 *
	 * @param int     $post_id ID del reto.
	 * @param WP_Post $post    Reto.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), 'concursos_save_reto' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se sanea en Concursos_Reto::sanitize().
		$raw      = isset( $_POST['concursos'] ) ? (array) wp_unslash( $_POST['concursos'] ) : array();
		$settings = Concursos_Reto::sanitize( $raw );
		Concursos_Reto::save_settings( $post_id, $settings );

		$errors = Concursos_Reto::validate( $settings );
		foreach ( array( 'participantes', 'votantes' ) as $grupo ) {
			$invalidos = Concursos_Reto::emails_invalidos( isset( $raw[ 'emails_' . $grupo ] ) ? (string) $raw[ 'emails_' . $grupo ] : '' );
			if ( $invalidos ) {
				/* translators: %s: list of invalid email addresses. */
				$errors[] = sprintf( __( 'These addresses are not valid and were ignored: %s', 'foncholuis-art-challenge-manager' ), implode( ', ', $invalidos ) );
			}
		}
		if ( $errors && 'auto-draft' !== $post->post_status ) {
			set_transient( 'concursos_errores_' . get_current_user_id(), $errors, 60 );
		}
	}

	/**
	 * Muestra los problemas de configuración tras guardar.
	 */
	public static function notices() {
		$key    = 'concursos_errores_' . get_current_user_id();
		$errors = get_transient( $key );
		if ( ! $errors ) {
			return;
		}
		delete_transient( $key );
		?>
		<div class="notice notice-warning is-dismissible">
			<p><strong><?php esc_html_e( 'The challenge was saved, but check its settings:', 'foncholuis-art-challenge-manager' ); ?></strong></p>
			<ul class="ul-disc">
				<?php foreach ( (array) $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}
