<?php
/**
 * Banner de retos: shortcode [concursos_banner] y bloque «Banner de retos».
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Banner horizontal que invita a participar en un reto abierto, a votar en uno
 * en votación o a ver los retos pasados. En modo aleatorio se imprimen todas
 * las variantes disponibles y assets/js/banner.js muestra una al azar en cada
 * visita, así que funciona también con la página en caché.
 */
class Concursos_Banner {

	const SHORTCODE = 'concursos_banner';
	const BLOQUE    = 'foncholuis/banner-retos';
	const MODOS     = array( 'aleatorio', 'participar', 'votar', 'pasados' );
	const ESTILOS   = array( 'reto', 'tema', 'editorial' );

	/**
	 * Registra los ganchos.
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'registrar' ) );
		add_filter( 'wp_get_attachment_image', array( __CLASS__, 'sin_sizes_auto' ), 20 );
		add_filter( 'wp_content_img_tag', array( __CLASS__, 'sin_sizes_auto' ), 20 );
	}

	/**
	 * Registra estilos, scripts y el bloque.
	 */
	public static function registrar() {
		wp_register_style( 'concursos-public', CONCURSOS_URL . 'assets/css/public.css', array(), CONCURSOS_VERSION );
		wp_register_script(
			'concursos-banner',
			CONCURSOS_URL . 'assets/js/banner.js',
			array(),
			CONCURSOS_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_register_script(
			'concursos-banner-editor',
			CONCURSOS_URL . 'assets/js/banner-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
			CONCURSOS_VERSION,
			true
		);
		wp_add_inline_script( 'concursos-banner-editor', 'window.concursosBanner = ' . wp_json_encode( self::textos_editor() ) . ';', 'before' );

		register_block_type(
			CONCURSOS_PATH . 'blocks/banner',
			array( 'render_callback' => array( __CLASS__, 'bloque' ) )
		);
	}

	/**
	 * Textos del panel del bloque, traducidos en PHP.
	 *
	 * @return array
	 */
	private static function textos_editor() {
		return array(
			'ajustes'   => __( 'Banner settings', 'foncholuis-art-challenge-manager' ),
			'modo'      => __( 'What to show', 'foncholuis-art-challenge-manager' ),
			'ayudaModo' => __( 'If there is nothing to show for the chosen option, another available one is shown.', 'foncholuis-art-challenge-manager' ),
			'modos'     => array(
				array(
					'value' => 'aleatorio',
					'label' => __( 'Random: a different one on each visit', 'foncholuis-art-challenge-manager' ),
				),
				array(
					'value' => 'participar',
					'label' => __( 'Open challenge: invite to take part', 'foncholuis-art-challenge-manager' ),
				),
				array(
					'value' => 'votar',
					'label' => __( 'Challenge in voting: invite to vote', 'foncholuis-art-challenge-manager' ),
				),
				array(
					'value' => 'pasados',
					'label' => __( 'Past challenges: winning photos', 'foncholuis-art-challenge-manager' ),
				),
			),
			'estilo'    => __( 'Style', 'foncholuis-art-challenge-manager' ),
			'estilos'   => array(
				array(
					'value' => 'reto',
					'label' => __( 'The style of the challenge shown', 'foncholuis-art-challenge-manager' ),
				),
				array(
					'value' => 'tema',
					'label' => __( 'Site theme', 'foncholuis-art-challenge-manager' ),
				),
				array(
					'value' => 'editorial',
					'label' => __( 'Editorial', 'foncholuis-art-challenge-manager' ),
				),
			),
		);
	}

	/**
	 * Shortcode: [concursos_banner modo="aleatorio|participar|votar|pasados" estilo="reto|tema|editorial"].
	 *
	 * @param array|string $atts Atributos.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'modo'   => 'aleatorio',
				'estilo' => 'reto',
			),
			$atts,
			self::SHORTCODE
		);

		return self::render( (string) $atts['modo'], (string) $atts['estilo'] );
	}

	/**
	 * Bloque dinámico.
	 *
	 * @param array $attributes Atributos del bloque.
	 * @return string
	 */
	public static function bloque( $attributes ) {
		$modo   = isset( $attributes['modo'] ) ? (string) $attributes['modo'] : 'aleatorio';
		$estilo = isset( $attributes['estilo'] ) ? (string) $attributes['estilo'] : 'reto';

		return '<div ' . get_block_wrapper_attributes() . '>' . self::render( $modo, $estilo ) . '</div>';
	}

	/**
	 * HTML del banner, ya escapado.
	 *
	 * @param string $modo   aleatorio, participar, votar o pasados.
	 * @param string $estilo reto, tema o editorial.
	 * @return string
	 */
	public static function render( $modo, $estilo = 'reto' ) {
		$modo   = in_array( $modo, self::MODOS, true ) ? $modo : 'aleatorio';
		$estilo = in_array( $estilo, self::ESTILOS, true ) ? $estilo : 'reto';

		$variantes = self::variantes( $estilo );
		if ( 'aleatorio' !== $modo && isset( $variantes[ $modo ] ) ) {
			$elegidas = array( $modo => $variantes[ $modo ] );
		} elseif ( $variantes ) {
			// Modo aleatorio, o el modo fijado no tiene nada que mostrar: todas las disponibles.
			$elegidas = $variantes;
		} else {
			$elegidas = array( 'general' => self::general( $estilo ) );
		}

		// El servidor ya baraja: sin JavaScript (o sin caché) también cambia.
		$claves = array_keys( $elegidas );
		shuffle( $claves );

		wp_enqueue_style( 'concursos-public' );
		$aleatorio = count( $claves ) > 1;
		if ( $aleatorio ) {
			wp_enqueue_script( 'concursos-banner' );
		}

		$html = '<div class="concursos-banner"' . ( $aleatorio ? ' data-pc-banner-aleatorio' : '' ) . ' data-pc-banner-modo="' . esc_attr( $claves[0] ) . '">';
		foreach ( $claves as $i => $clave ) {
			$html .= 0 === $i ? $elegidas[ $clave ] : preg_replace( '/^<aside /', '<aside hidden ', $elegidas[ $clave ], 1 );
		}
		$html .= '</div>';

		return wp_kses( $html, Concursos_Vista::html_permitido() );
	}

	/**
	 * Variantes con contenido, por modo.
	 *
	 * @param string $estilo Estilo pedido.
	 * @return string[]
	 */
	private static function variantes( $estilo ) {
		$retos     = self::retos();
		$variantes = array();

		$abierto = self::preferir_publico( $retos['envios'], 'privado_participantes' );
		if ( $abierto ) {
			$variantes['participar'] = self::participar( $abierto, $estilo );
		}
		$votacion = self::preferir_publico( $retos['votacion'], 'privado_votantes' );
		if ( $votacion ) {
			$variantes['votar'] = self::votar( $votacion, $estilo );
		}
		if ( $retos['finalizado'] ) {
			$variantes['pasados'] = self::pasados( $retos['finalizado'], $estilo );
		}

		return $variantes;
	}

	/**
	 * Retos publicados por fase, del más reciente al más antiguo.
	 *
	 * @return array[]
	 */
	private static function retos() {
		$grupos = array(
			'proximo'    => array(),
			'envios'     => array(),
			'votacion'   => array(),
			'finalizado' => array(),
		);
		foreach ( Concursos_Reto::all( array( 'publish' ) ) as $reto ) {
			if ( isset( $grupos[ $reto['status'] ] ) ) {
				$grupos[ $reto['status'] ][] = $reto;
			}
		}

		return $grupos;
	}

	/**
	 * El reto más reciente abierto a todos o, si no hay, el más reciente privado.
	 *
	 * @param array[] $retos  Retos de una fase.
	 * @param string  $ajuste privado_participantes o privado_votantes.
	 * @return array|null
	 */
	private static function preferir_publico( array $retos, $ajuste ) {
		foreach ( $retos as $reto ) {
			if ( empty( $reto['settings'][ $ajuste ] ) ) {
				return $reto;
			}
		}

		return $retos ? $retos[0] : null;
	}

	/**
	 * Atributos de diseño del contenedor de una variante.
	 *
	 * @param array|null $reto   Reto que decide el estilo.
	 * @param string     $estilo Estilo pedido.
	 * @return string
	 */
	private static function atributos( $reto, $estilo ) {
		$s = $reto ? $reto['settings'] : Concursos_Reto::defaults();
		if ( 'reto' !== $estilo ) {
			$s['estilo'] = $estilo;
		}

		return Concursos_Diseno::atributos( $s );
	}

	/**
	 * Apertura de una variante.
	 *
	 * @param string     $modo   Modo.
	 * @param array|null $reto   Reto.
	 * @param string     $estilo Estilo.
	 * @param string     $extra  Clases adicionales.
	 * @return string
	 */
	private static function abrir( $modo, $reto, $estilo, $extra = '' ) {
		return sprintf(
			'<aside class="%1$s" data-pc-modo="%2$s" aria-label="%3$s"%4$s>',
			esc_attr( trim( 'pc pc-banner pc-banner--' . $modo . ' ' . $extra ) ),
			esc_attr( $modo ),
			esc_attr__( 'Challenges', 'foncholuis-art-challenge-manager' ),
			self::atributos( $reto, $estilo )
		);
	}

	/**
	 * Tres puntos de podio (oro, plata y bronce) que firman el banner.
	 *
	 * @return string
	 */
	private static function podio() {
		return '<span class="pc-banner__podio" aria-hidden="true"><span></span><span></span><span></span></span>';
	}

	/**
	 * Días o horas que faltan hasta una fecha.
	 *
	 * @param int $ts Fecha.
	 * @return string
	 */
	private static function falta( $ts ) {
		$falta = (int) $ts - time();
		if ( $falta <= 0 ) {
			return '';
		}
		$dias = (int) floor( $falta / DAY_IN_SECONDS );
		if ( $dias >= 1 ) {
			/* translators: %d: number of days. */
			return sprintf( _n( '%d day left', '%d days left', $dias, 'foncholuis-art-challenge-manager' ), $dias );
		}
		$horas = max( 1, (int) floor( $falta / HOUR_IN_SECONDS ) );

		/* translators: %d: number of hours. */
		return sprintf( _n( '%d hour left', '%d hours left', $horas, 'foncholuis-art-challenge-manager' ), $horas );
	}

	/**
	 * Imagen de un adjunto, con srcset.
	 *
	 * @param int    $id  Adjunto.
	 * @param string $alt Texto alternativo.
	 * @return string
	 */
	private static function img( $id, $alt ) {
		return $id ? wp_get_attachment_image(
			$id,
			'large',
			false,
			array(
				'class'    => 'pc-banner__img',
				'alt'      => $alt,
				'loading'  => 'lazy',
				'decoding' => 'async',
				'sizes'    => '(max-width: 600px) 100vw, 640px',
			)
		) : '';
	}

	/**
	 * Quita «auto» del atributo sizes de las imágenes del banner. WordPress lo
	 * añade a las imágenes diferidas, y entonces el navegador elige la copia por
	 * el ancho de la caja; aquí la imagen se recorta con object-fit y necesita
	 * más resolución (por ejemplo, en una celda alta del mosaico).
	 *
	 * @param string $html Etiqueta img.
	 * @return string
	 */
	public static function sin_sizes_auto( $html ) {
		if ( false === strpos( $html, 'pc-banner__img' ) ) {
			return $html;
		}

		return preg_replace( '/\bsizes="auto,\s*/', 'sizes="', $html );
	}

	/**
	 * Adjunto que representa a un reto: su imagen de fondo o, si no tiene, su primera foto visible.
	 *
	 * @param array $reto Reto.
	 * @return int
	 */
	private static function imagen_reto( array $reto ) {
		$id = (int) get_post_thumbnail_id( $reto['post'] );
		if ( ! $id && Concursos_Reto::photos_visible( $reto['settings'] ) ) {
			$fotos = Concursos_Entrada::find( array( 'reto' => $reto['post']->ID ) );
			$id    = $fotos ? (int) get_post_thumbnail_id( $fotos[0] ) : 0;
		}

		return $id;
	}

	/**
	 * Chip «reto privado».
	 *
	 * @return string
	 */
	private static function chip_privado() {
		return '<span class="pc-chip pc-banner__privado">' . esc_html__( 'Private challenge', 'foncholuis-art-challenge-manager' ) . '</span>';
	}

	/**
	 * Variante «participar»: el reto abierto más reciente, con su imagen.
	 *
	 * @param array  $reto   Reto en envíos.
	 * @param string $estilo Estilo.
	 * @return string
	 */
	private static function participar( array $reto, $estilo ) {
		$s       = $reto['settings'];
		$titulo  = get_the_title( $reto['post'] );
		$resumen = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $reto['post']->post_content ) ), 22, '…' );
		$imagen  = self::img( self::imagen_reto( $reto ), $titulo );
		$dato    = sprintf(
			/* translators: %s: date. */
			__( 'Submissions until %s', 'foncholuis-art-challenge-manager' ),
			Concursos_Reto::format_dia( $s['fin_envios'] )
		);
		$falta = self::falta( $s['fin_envios'] );

		$html  = self::abrir( 'participar', $reto, $estilo, $imagen ? '' : 'pc-banner--sin-imagen' );
		$html .= '<div class="pc-banner__texto">';
		$html .= '<div class="pc-banner__chips">' . Concursos_Vista::chip( 'envios' ) . ( empty( $s['privado_participantes'] ) ? '' : self::chip_privado() ) . '</div>';
		$html .= '<h2 class="pc-banner__titulo">' . esc_html( $titulo ) . '</h2>';
		if ( $resumen ) {
			$html .= '<p class="pc-banner__resumen">' . esc_html( $resumen ) . '</p>';
		}
		$html .= '<div class="pc-banner__accion">';
		$html .= Concursos_Vista::boton( get_permalink( $reto['post'] ), __( 'Take part', 'foncholuis-art-challenge-manager' ), 'principal', 'subir' );
		$html .= '<p class="pc-banner__dato">' . esc_html( $dato ) . ( $falta ? ' · <strong>' . esc_html( $falta ) . '</strong>' : '' ) . '</p>';
		$html .= '</div></div>';
		if ( $imagen ) {
			$html .= '<div class="pc-banner__media">' . $imagen . '</div>';
		}

		return $html . '</aside>';
	}

	/**
	 * Variante «votar»: mosaico de fotos del reto en votación.
	 *
	 * @param array  $reto   Reto en votación.
	 * @param string $estilo Estilo.
	 * @return string
	 */
	private static function votar( array $reto, $estilo ) {
		$s      = $reto['settings'];
		$titulo = get_the_title( $reto['post'] );
		$fotos  = Concursos_Entrada::find( array( 'reto' => $reto['post']->ID ) );
		$total  = count( $fotos );
		shuffle( $fotos );

		$mosaico = '';
		foreach ( array_slice( $fotos, 0, 3 ) as $foto ) {
			$img = self::img( (int) get_post_thumbnail_id( $foto ), '' );
			if ( $img ) {
				$mosaico .= '<span class="pc-banner__celda">' . $img . '</span>';
			}
		}
		$piezas = substr_count( $mosaico, 'pc-banner__celda' );

		$lead = Concursos_Votos::por_puestos( $s )
			/* translators: %d: number of photos. */
			? sprintf( _n( 'Pick your 1st, 2nd and 3rd place among %d photo.', 'Pick your 1st, 2nd and 3rd place among %d photos.', $total, 'foncholuis-art-challenge-manager' ), $total )
			/* translators: %d: number of photos. */
			: sprintf( _n( 'Choose your favourite among %d photo.', 'Choose your favourite among %d photos.', $total, 'foncholuis-art-challenge-manager' ), $total );
		$dato  = sprintf(
			/* translators: %s: date. */
			__( 'Voting until %s', 'foncholuis-art-challenge-manager' ),
			Concursos_Reto::format_dia( $s['fin_votacion'] )
		);
		$falta = self::falta( $s['fin_votacion'] );

		$html = self::abrir( 'votar', $reto, $estilo, $mosaico ? '' : 'pc-banner--sin-imagen' );
		if ( $mosaico ) {
			$html .= '<div class="pc-banner__media pc-banner__mosaico pc-banner__mosaico--' . (int) $piezas . '" aria-hidden="true">' . $mosaico . '</div>';
		}
		$html .= '<div class="pc-banner__texto">';
		$html .= '<div class="pc-banner__chips">' . Concursos_Vista::chip( 'votacion' ) . ( empty( $s['privado_votantes'] ) ? '' : self::chip_privado() ) . '</div>';
		/* translators: %s: challenge name. */
		$html .= '<h2 class="pc-banner__titulo">' . esc_html( sprintf( __( 'Vote in “%s”', 'foncholuis-art-challenge-manager' ), $titulo ) ) . '</h2>';
		$html .= '<p class="pc-banner__resumen">' . esc_html( $lead ) . '</p>';
		$html .= '<div class="pc-banner__accion">';
		$html .= Concursos_Vista::boton( get_permalink( $reto['post'] ) . '#pc-fotos', __( 'See the photos and vote', 'foncholuis-art-challenge-manager' ), 'principal', 'flecha' );
		$html .= '<p class="pc-banner__dato">' . esc_html( $dato ) . ( $falta ? ' · <strong>' . esc_html( $falta ) . '</strong>' : '' ) . '</p>';
		$html .= '</div></div>';

		return $html . '</aside>';
	}

	/**
	 * Variante «pasados»: la foto ganadora del último reto terminado, sobre fondo oscuro.
	 *
	 * @param array[] $pasados Retos terminados, del más reciente al más antiguo.
	 * @param string  $estilo  Estilo.
	 * @return string
	 */
	private static function pasados( array $pasados, $estilo ) {
		$ultimo   = $pasados[0];
		$titulo   = get_the_title( $ultimo['post'] );
		$ganadora = Concursos_Vista::ganadora( $ultimo['post']->ID, $ultimo['settings'] );
		$autor    = $ganadora ? Concursos_Entrada::owner( $ganadora->ID ) : 0;
		$foto_id  = $ganadora ? (int) get_post_thumbnail_id( $ganadora ) : self::imagen_reto( $ultimo );
		$imagen   = self::img(
			$foto_id,
			$ganadora && $autor
				/* translators: 1: photo title, 2: author name. */
				? sprintf( __( '“%1$s” by %2$s', 'foncholuis-art-challenge-manager' ), get_the_title( $ganadora ), get_the_title( $autor ) )
				: $titulo
		);

		$lead = $ganadora && $autor
			/* translators: 1: challenge name, 2: author name. */
			? sprintf( __( 'Latest: “%1$s”, won by %2$s.', 'foncholuis-art-challenge-manager' ), $titulo, get_the_title( $autor ) )
			/* translators: %s: challenge name. */
			: sprintf( __( 'Latest: “%s”.', 'foncholuis-art-challenge-manager' ), $titulo );
		/* translators: %d: number of finished challenges. */
		$dato = sprintf( _n( '%d finished challenge', '%d finished challenges', count( $pasados ), 'foncholuis-art-challenge-manager' ), count( $pasados ) );

		$html  = self::abrir( 'pasados', $ultimo, $estilo, $imagen ? '' : 'pc-banner--sin-imagen' );
		$html .= '<div class="pc-banner__texto">';
		$html .= '<p class="pc-banner__antetitulo pc-etiqueta">' . self::podio() . esc_html__( 'Past challenges', 'foncholuis-art-challenge-manager' ) . '</p>';
		$html .= '<h2 class="pc-banner__titulo">' . esc_html__( 'Discover the winning photos', 'foncholuis-art-challenge-manager' ) . '</h2>';
		$html .= '<p class="pc-banner__resumen">' . esc_html( $lead ) . '</p>';
		$html .= '<div class="pc-banner__accion">';
		$html .= Concursos_Vista::boton( self::url_portada() . '#pc-terminados', __( 'See past challenges', 'foncholuis-art-challenge-manager' ), 'principal', 'flecha' );
		$html .= '<p class="pc-banner__dato">' . esc_html( $dato ) . '</p>';
		$html .= '</div></div>';
		if ( $imagen ) {
			$html .= '<div class="pc-banner__media">' . $imagen;
			if ( $ganadora ) {
				$html .= '<span class="pc-sello pc-sello--oro">' . Concursos_Vista::icono( 'medalla', 14 ) . esc_html__( 'Winning photo', 'foncholuis-art-challenge-manager' ) . '</span>';
			}
			$html .= '</div>';
		}

		return $html . '</aside>';
	}

	/**
	 * Variante general, cuando no hay nada que destacar: invita a ver la página de retos.
	 *
	 * @param string $estilo Estilo.
	 * @return string
	 */
	private static function general( $estilo ) {
		$retos   = self::retos();
		$proximo = $retos['proximo'] ? end( $retos['proximo'] ) : null; // El que empieza antes.
		$imagen  = $proximo ? self::img( self::imagen_reto( $proximo ), get_the_title( $proximo['post'] ) ) : '';

		$html  = self::abrir( 'general', $proximo, $estilo, $imagen ? '' : 'pc-banner--sin-imagen' );
		$html .= '<div class="pc-banner__texto">';
		$html .= '<p class="pc-banner__antetitulo pc-etiqueta">' . self::podio() . esc_html__( 'Challenges', 'foncholuis-art-challenge-manager' ) . '</p>';
		$html .= '<h2 class="pc-banner__titulo">' . esc_html__( 'One theme. One photo.', 'foncholuis-art-challenge-manager' ) . ' <em>' . esc_html__( 'Your vision.', 'foncholuis-art-challenge-manager' ) . '</em></h2>';
		$html .= '<div class="pc-banner__accion">';
		$html .= Concursos_Vista::boton( self::url_portada(), __( 'See the challenges', 'foncholuis-art-challenge-manager' ), 'principal', 'flecha' );
		if ( $proximo ) {
			$html .= '<p class="pc-banner__dato">' . esc_html(
				sprintf(
					/* translators: 1: challenge name, 2: date. */
					__( 'Next: “%1$s”, opens on %2$s', 'foncholuis-art-challenge-manager' ),
					get_the_title( $proximo['post'] ),
					Concursos_Reto::format_dia( $proximo['settings']['inicio'] )
				)
			) . '</p>';
		}
		$html .= '</div></div>';
		if ( $imagen ) {
			$html .= '<div class="pc-banner__media">' . $imagen . '</div>';
		}

		return $html . '</aside>';
	}

	/**
	 * Dirección de la página de retos.
	 *
	 * @return string
	 */
	private static function url_portada() {
		$pagina = (int) get_option( Concursos_Portada::OPCION_PAGINA );
		$url    = $pagina && 'publish' === get_post_status( $pagina ) ? get_permalink( $pagina ) : '';

		return $url ? $url : (string) get_post_type_archive_link( Concursos_Post_Types::RETO );
	}
}
