<?php
/**
 * Envoltorio de las páginas públicas propias, con la cabecera y el pie del tema.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pinta una página completa con el tema activo, sea de bloques o clásico.
 */
class Concursos_Plantilla {

	/**
	 * Pinta la página y termina la petición.
	 *
	 * @param string $titulo Título del documento.
	 * @param string $html   Contenido ya escapado.
	 */
	public static function pagina( $titulo, $html ) {
		wp_enqueue_style( 'concursos-public', CONCURSOS_URL . 'assets/css/public.css', array(), CONCURSOS_VERSION );
		status_header( 200 );

		add_filter(
			'pre_get_document_title',
			function () use ( $titulo ) {
				return $titulo . ' · ' . get_bloginfo( 'name' );
			}
		);

		$main = '<main class="concursos-publico">' . $html . '</main>';

		if ( wp_is_block_theme() ) {
			self::con_bloques( $main );
		} else {
			get_header();
			echo $main; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
			get_footer();
		}
		exit;
	}

	/**
	 * Documento para temas de bloques. La cabecera y el pie se generan antes de
	 * wp_head() para que los estilos de sus bloques se impriman a tiempo.
	 *
	 * @param string $main Contenido.
	 */
	private static function con_bloques( $main ) {
		ob_start();
		block_header_area();
		$header = ob_get_clean();

		ob_start();
		block_footer_area();
		$footer = ob_get_clean();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php if ( ! current_theme_supports( 'title-tag' ) ) : // Los temas de bloques solo la reciben en la plantilla de WordPress. ?>
		<title><?php echo esc_html( wp_get_document_title() ); ?></title>
	<?php endif; ?>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
	<header class="wp-block-template-part"><?php echo $header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bloques del tema. ?></header>
	<?php echo $main; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
	<footer class="wp-block-template-part"><?php echo $footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bloques del tema. ?></footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
		<?php
	}
}
