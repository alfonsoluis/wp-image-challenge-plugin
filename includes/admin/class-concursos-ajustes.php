<?php
/**
 * Ajustes generales del plugin.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Página "Ajustes" del menú Retos.
 */
class Concursos_Ajustes {

	const PAGE  = 'concursos-ajustes';
	const GRUPO = 'concursos_ajustes';

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'registrar' ) );
	}

	/**
	 * Añade la página al final del menú "Retos".
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Concursos_Post_Types::RETO,
			__( 'Photo Challenges settings', 'photo-challenges' ),
			__( 'Settings', 'photo-challenges' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Registra los ajustes y sus campos.
	 */
	public static function registrar() {
		register_setting(
			self::GRUPO,
			Concursos_Perfil::OPCION,
			array(
				'type'              => 'string',
				'default'           => '1',
				'sanitize_callback' => array( __CLASS__, 'sanear_casilla' ),
			)
		);

		add_settings_section( 'concursos_publico', __( 'Public pages', 'photo-challenges' ), '__return_false', self::PAGE );
		add_settings_field(
			Concursos_Perfil::OPCION,
			__( 'Participant profiles', 'photo-challenges' ),
			array( __CLASS__, 'campo_perfiles' ),
			self::PAGE,
			'concursos_publico'
		);
	}

	/**
	 * Convierte una casilla en '1' o '0'.
	 *
	 * @param mixed $valor Valor recibido.
	 * @return string
	 */
	public static function sanear_casilla( $valor ) {
		return empty( $valor ) ? '0' : '1';
	}

	/**
	 * Casilla de los perfiles públicos.
	 */
	public static function campo_perfiles() {
		?>
		<input type="hidden" name="<?php echo esc_attr( Concursos_Perfil::OPCION ); ?>" value="0">
		<label>
			<input type="checkbox" name="<?php echo esc_attr( Concursos_Perfil::OPCION ); ?>" value="1" <?php checked( Concursos_Perfil::activos() ); ?>>
			<?php esc_html_e( 'Show a public profile page for each participant', 'photo-challenges' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'When turned off, profile pages no longer exist and participant names are shown everywhere without a link.', 'photo-challenges' ); ?></p>
		<?php
	}

	/**
	 * Pinta la página.
	 */
	public static function render() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Photo Challenges settings', 'photo-challenges' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::GRUPO );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
