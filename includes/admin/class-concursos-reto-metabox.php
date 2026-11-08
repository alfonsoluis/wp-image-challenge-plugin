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
	}

	/**
	 * Registra las cajas.
	 */
	public static function add() {
		add_meta_box( 'concursos-calendario', __( 'Calendario', 'concursos' ), array( __CLASS__, 'render_calendar' ), null, 'normal', 'high' );
		add_meta_box( 'concursos-reglas', __( 'Reglas', 'concursos' ), array( __CLASS__, 'render_rules' ), null, 'normal', 'high' );
		add_meta_box( 'concursos-votacion', __( 'Participación, votación y premios', 'concursos' ), array( __CLASS__, 'render_voting' ), null, 'normal', 'high' );
		add_meta_box( 'concursos-diseno', __( 'Diseño', 'concursos' ), array( __CLASS__, 'render_design' ), null, 'normal', 'high' );
	}

	/**
	 * Título sobre el editor: el contenido del reto es su descripción.
	 *
	 * @param WP_Post $post Reto.
	 */
	public static function description_heading( $post ) {
		if ( Concursos_Post_Types::RETO === $post->post_type ) {
			echo '<h2 class="concursos-section-title">' . esc_html__( 'Descripción general', 'concursos' ) . '</h2>';
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
			<?php esc_html_e( 'Envíos: del inicio al fin de los envíos. Votación: del fin de los envíos al fin de la votación. Entre el fin de la votación y la publicación de resultados se revisan los votos.', 'concursos' ); ?>
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
		<p><label for="concursos-reglas"><?php esc_html_e( 'Una regla por línea.', 'concursos' ); ?></label></p>
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
				<th scope="row"><?php esc_html_e( 'Visibilidad de las fotos', 'concursos' ); ?></th>
				<td>
					<?php foreach ( $choices['modo'] as $value => $label ) : ?>
						<label class="concursos-radio concursos-radio--block">
							<input type="radio" name="concursos[modo]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $s['modo'], $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="concursos-max-fotos"><?php esc_html_e( 'Fotos por participante', 'concursos' ); ?></label></th>
				<td><input type="number" id="concursos-max-fotos" name="concursos[max_fotos]" min="1" max="20" value="<?php echo esc_attr( $s['max_fotos'] ); ?>" class="small-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="concursos-votos"><?php esc_html_e( 'Votos por votante', 'concursos' ); ?></label></th>
				<td>
					<select id="concursos-votos" name="concursos[votos_por_votante]">
						<?php for ( $i = 1; $i <= 3; $i++ ) : ?>
							<option value="<?php echo esc_attr( $i ); ?>" <?php selected( (int) $s['votos_por_votante'], $i ); ?>>
								<?php
								/* translators: %d: número de fotos por las que se puede votar. */
								echo esc_html( sprintf( _n( '%d foto', 'Hasta %d fotos', $i, 'concursos' ), $i ) );
								?>
							</option>
						<?php endfor; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Ganadores', 'concursos' ); ?></th>
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
				__( 'Premio del 1.er lugar', 'concursos' ),
				__( 'Premio del 2.º lugar', 'concursos' ),
				__( 'Premio del 3.er lugar', 'concursos' ),
			);
			foreach ( $places as $i => $label ) :
				?>
				<tr class="<?php echo $i > 0 ? 'concursos-if-tres-lugares' : ''; ?>">
					<th scope="row"><label for="concursos-premio-<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td>
						<input type="text" id="concursos-premio-<?php echo esc_attr( $i ); ?>" name="concursos[premios][<?php echo esc_attr( $i ); ?>]" value="<?php echo esc_attr( $s['premios'][ $i ] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Opcional', 'concursos' ); ?>">
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Caja de diseño: paleta y tamaño.
	 *
	 * @param WP_Post $post Reto.
	 */
	public static function render_design( $post ) {
		$s = Concursos_Reto::get_settings( $post->ID );
		?>
		<table class="form-table concursos-form">
			<tr>
				<th scope="row"><?php esc_html_e( 'Colores', 'concursos' ); ?></th>
				<td class="concursos-opciones">
					<label class="concursos-opcion">
						<input type="radio" name="concursos[paleta]" value="<?php echo esc_attr( Concursos_Diseno::TEMA ); ?>" <?php checked( $s['paleta'], Concursos_Diseno::TEMA ); ?>>
						<span><?php esc_html_e( 'Los del tema', 'concursos' ); ?></span>
					</label>
					<?php foreach ( Concursos_Diseno::paletas() as $clave => $p ) : ?>
						<label class="concursos-opcion">
							<input type="radio" name="concursos[paleta]" value="<?php echo esc_attr( $clave ); ?>" <?php checked( $s['paleta'], $clave ); ?>>
							<span class="concursos-muestras" aria-hidden="true">
								<?php foreach ( array( 'color', 'acento', 'suave', 'fondo' ) as $tono ) : ?>
									<i style="background:<?php echo esc_attr( $p[ $tono ] ); ?>"></i>
								<?php endforeach; ?>
							</span>
							<span><?php echo esc_html( $p['nombre'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Tamaño del texto', 'concursos' ); ?></th>
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
			<p><strong><?php esc_html_e( 'El reto se guardó, pero revisa su configuración:', 'concursos' ); ?></strong></p>
			<ul class="ul-disc">
				<?php foreach ( (array) $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}
