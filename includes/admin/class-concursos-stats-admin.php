<?php
/**
 * Pantalla «Stats»: actividad de los retos y estado de cada uno.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registra y pinta la pantalla de estadísticas y sus exportaciones CSV.
 */
class Concursos_Stats_Admin {

	const PAGE       = 'concursos-stats';
	const CAPABILITY = Concursos_Votantes_Admin::CAPABILITY;
	const CSV        = 'concursos_stats_csv';

	/** Filtros de la lista de retos. */
	const FILTROS = array( 'todos', 'activos', 'proximos', 'terminados' );

	/**
	 * Engancha los hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_' . self::CSV, array( __CLASS__, 'csv' ) );
	}

	/**
	 * Añade la página al menú "Retos", tras "Votantes".
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Concursos_Post_Types::RETO,
			__( 'Stats', 'foncholuis-art-challenge-manager' ),
			__( 'Stats', 'foncholuis-art-challenge-manager' ),
			self::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/* ---------------------------------------------------------------------
	 * Estado de la página (solo lectura: periodo, filtro, búsqueda y reto).
	 * ------------------------------------------------------------------ */

	/**
	 * Valor de un parámetro de la URL.
	 *
	 * @param string $clave Parámetro.
	 * @return string
	 */
	private static function pedido( $clave ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtros de solo lectura.
		return isset( $_GET[ $clave ] ) ? sanitize_text_field( wp_unslash( $_GET[ $clave ] ) ) : '';
	}

	/**
	 * Periodo, filtro, búsqueda y reto pedidos.
	 *
	 * @return array
	 */
	private static function estado() {
		$periodo = self::pedido( 'periodo' );
		$filtro  = self::pedido( 'filtro' );

		return array(
			'periodo' => isset( Concursos_Stats::periodos()[ $periodo ] ) ? $periodo : '7d',
			'filtro'  => in_array( $filtro, self::FILTROS, true ) ? $filtro : 'todos',
			'buscar'  => self::pedido( 'buscar' ),
			'reto'    => absint( self::pedido( 'reto' ) ),
		);
	}

	/**
	 * URL de la página con el estado actual y los cambios dados.
	 *
	 * @param array $cambios Parámetros que cambian.
	 * @return string
	 */
	private static function url( array $cambios = array() ) {
		$args = array_merge( self::estado(), $cambios );
		if ( '7d' === $args['periodo'] ) {
			unset( $args['periodo'] );
		}
		if ( 'todos' === $args['filtro'] ) {
			unset( $args['filtro'] );
		}
		$args = array_filter( $args );

		return add_query_arg(
			array_merge(
				array(
					'post_type' => Concursos_Post_Types::RETO,
					'page'      => self::PAGE,
				),
				$args
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Grupo de la lista al que pertenece una fase.
	 *
	 * @param string $status Fase.
	 * @return string activos, proximos, terminados o vacío.
	 */
	private static function grupo( $status ) {
		if ( in_array( $status, array( 'envios', 'votacion', 'evaluacion' ), true ) ) {
			return 'activos';
		}
		if ( 'proximo' === $status ) {
			return 'proximos';
		}

		return 'finalizado' === $status ? 'terminados' : '';
	}

	/**
	 * Reto que se muestra si no se elige ninguno: el activo cuya etapa cierra
	 * antes; si no hay, el próximo más cercano; si tampoco, el último terminado.
	 *
	 * @param array[] $retos Retos.
	 * @return array|null
	 */
	private static function reto_por_defecto( array $retos ) {
		$mejor = null;
		$orden = null;
		foreach ( $retos as $reto ) {
			if ( 'publish' !== $reto['post']->post_status ) {
				continue;
			}
			$etapa = Concursos_Reto::proxima_etapa( $reto['status'], $reto['settings'] );
			if ( 'activos' === self::grupo( $reto['status'] ) ) {
				$este = array( 0, $etapa[1] );
			} elseif ( 'proximo' === $reto['status'] ) {
				$este = array( 1, $etapa[1] );
			} elseif ( 'finalizado' === $reto['status'] ) {
				$este = array( 2, -(int) $reto['settings']['cierre'] );
			} else {
				continue;
			}
			if ( null === $orden || $este < $orden ) {
				$orden = $este;
				$mejor = $reto;
			}
		}

		return $mejor ? $mejor : ( $retos ? $retos[0] : null );
	}

	/* ---------------------------------------------------------------------
	 * Textos y piezas pequeñas.
	 * ------------------------------------------------------------------ */

	/**
	 * Día de una marca de tiempo, en la zona del sitio.
	 *
	 * @param int $ts Marca de tiempo.
	 * @return DateTimeImmutable
	 */
	private static function dia( $ts ) {
		return ( new DateTimeImmutable( '@' . (int) $ts ) )->setTimezone( wp_timezone() )->setTime( 0, 0 );
	}

	/**
	 * Día de una clave Y-m-d, en formato corto.
	 *
	 * @param string $clave Y-m-d.
	 * @return string
	 */
	private static function dia_corto( $clave ) {
		$dia = DateTimeImmutable::createFromFormat( '!Y-m-d', $clave, wp_timezone() );

		return $dia ? Concursos_Vista::dia_mes( $dia->getTimestamp() ) : $clave;
	}

	/**
	 * Línea corta con la próxima etapa, para la lista de retos.
	 *
	 * @param array $reto Reto.
	 * @return string
	 */
	private static function linea_corta( array $reto ) {
		$frases = array(
			/* translators: %s: time such as "2 days". */
			'proximo'    => __( 'Opens in %s', 'foncholuis-art-challenge-manager' ),
			/* translators: %s: time such as "2 days". */
			'envios'     => __( 'Submissions close in %s', 'foncholuis-art-challenge-manager' ),
			/* translators: %s: time such as "2 days". */
			'votacion'   => __( 'Voting closes in %s', 'foncholuis-art-challenge-manager' ),
			/* translators: %s: time such as "2 days". */
			'evaluacion' => __( 'Results in %s', 'foncholuis-art-challenge-manager' ),
		);
		$etapa  = Concursos_Reto::proxima_etapa( $reto['status'], $reto['settings'] );
		if ( $etapa && $etapa[1] > time() ) {
			return sprintf( $frases[ $reto['status'] ], human_time_diff( time(), $etapa[1] ) );
		}
		if ( 'finalizado' === $reto['status'] ) {
			/* translators: %s: date. */
			return sprintf( __( 'Finished on %s', 'foncholuis-art-challenge-manager' ), Concursos_Vista::dia_mes( $reto['settings']['cierre'] ) );
		}

		return Concursos_Reto::status_labels()['sin_fechas'];
	}

	/**
	 * Cuánto falta, en días u horas.
	 *
	 * @param int $ts Fecha.
	 * @return string
	 */
	private static function falta( $ts ) {
		// Redondea como human_time_diff(), para que coincida con el aviso y la lista.
		$segundos = (int) $ts - time();
		$dias     = $segundos >= DAY_IN_SECONDS ? (int) round( $segundos / DAY_IN_SECONDS ) : 0;
		if ( $dias >= 1 ) {
			/* translators: %d: number of days. */
			return sprintf( _n( '%d day left', '%d days left', $dias, 'foncholuis-art-challenge-manager' ), $dias );
		}
		$horas = (int) round( $segundos / HOUR_IN_SECONDS );
		if ( $horas >= 1 ) {
			/* translators: %d: number of hours. */
			return sprintf( _n( '%d hour left', '%d hours left', $horas, 'foncholuis-art-challenge-manager' ), $horas );
		}

		return __( 'Less than an hour left', 'foncholuis-art-challenge-manager' );
	}

	/**
	 * Etiqueta de fase.
	 *
	 * @param string $status Fase.
	 * @return string HTML.
	 */
	private static function badge( $status ) {
		$labels = Concursos_Reto::status_labels();

		return sprintf(
			'<span class="concursos-badge concursos-badge--%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : $status )
		);
	}

	/**
	 * Si un reto limita a una lista de emails quién participa o vota.
	 *
	 * @param array $s Configuración.
	 * @return bool
	 */
	private static function es_privado( array $s ) {
		return ! empty( $s['privado_participantes'] ) || ! empty( $s['privado_votantes'] );
	}

	/**
	 * Miniatura de un reto, o un hueco si no tiene.
	 *
	 * @param array $reto  Reto.
	 * @param int   $lado  Lado en píxeles.
	 * @return string HTML.
	 */
	private static function miniatura_reto( array $reto, $lado ) {
		$img = Concursos_Portada::imagen( $reto, 'thumbnail' );

		return $img
			? sprintf( '<img src="%1$s" alt="" width="%2$d" height="%2$d" class="concursos-stats-img" loading="lazy">', esc_url( $img['url'] ), (int) $lado )
			: sprintf( '<span class="concursos-stats-img concursos-stats-img--vacia" style="width:%1$dpx;height:%1$dpx" aria-hidden="true"></span>', (int) $lado );
	}

	/**
	 * Miniatura de una foto.
	 *
	 * @param int $id Foto.
	 * @return string HTML.
	 */
	private static function miniatura_foto( $id ) {
		$img = get_post_thumbnail_id( $id );

		return $img
			? wp_get_attachment_image( $img, array( 48, 48 ), false, array( 'class' => 'concursos-stats-img', 'alt' => '' ) )
			: '<span class="concursos-stats-img concursos-stats-img--vacia" style="width:48px;height:48px" aria-hidden="true"></span>';
	}

	/**
	 * Nombre del autor de una foto.
	 *
	 * @param int $id Foto.
	 * @return string
	 */
	private static function autor( $id ) {
		$autor = Concursos_Entrada::owner( $id );

		return $autor ? get_the_title( $autor ) : '';
	}

	/* ---------------------------------------------------------------------
	 * Gráficas (SVG en línea, sin librerías).
	 * ------------------------------------------------------------------ */

	/**
	 * Texto con los valores de una serie, para lectores de pantalla.
	 *
	 * @param array  $serie   clave Y-m-d => número.
	 * @param bool   $semanas Si cada clave es una semana.
	 * @param string $nombre  Qué se cuenta.
	 * @return string
	 */
	private static function descripcion( array $serie, $semanas, $nombre ) {
		$partes = array();
		foreach ( $serie as $clave => $n ) {
			$partes[] = ( $semanas
				/* translators: %s: date. */
				? sprintf( __( 'week of %s', 'foncholuis-art-challenge-manager' ), self::dia_corto( $clave ) )
				: self::dia_corto( $clave ) ) . ': ' . number_format_i18n( $n );
		}

		return sprintf(
			$semanas
				/* translators: 1: what is counted, 2: list of values. */
				? __( '%1$s per week: %2$s', 'foncholuis-art-challenge-manager' )
				/* translators: 1: what is counted, 2: list of values. */
				: __( '%1$s per day: %2$s', 'foncholuis-art-challenge-manager' ),
			$nombre,
			implode( ', ', $partes )
		);
	}

	/**
	 * Línea pequeña con la evolución de una serie.
	 *
	 * @param array  $serie  Y-m-d => número.
	 * @param string $nombre Qué se cuenta.
	 * @return string HTML.
	 */
	private static function sparkline( array $serie, $nombre ) {
		list( $serie, $semanas ) = Concursos_Stats::agrupar( $serie );
		$valores                 = array_values( $serie );
		$n                       = count( $valores );
		$max                     = max( 1, max( $valores ? $valores : array( 0 ) ) );
		$puntos                  = array();
		foreach ( $valores as $i => $v ) {
			$puntos[] = round( $n > 1 ? $i / ( $n - 1 ) * 100 : 50, 2 ) . ',' . round( 28 - $v / $max * 26, 2 );
		}

		return sprintf(
			'<svg class="concursos-stats-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none" role="img" aria-label="%1$s"><polyline fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" points="%2$s"/></svg>',
			esc_attr( self::descripcion( $serie, $semanas, $nombre ) ),
			esc_attr( implode( ' ', $puntos ) )
		);
	}

	/**
	 * Gráfica de barras de una serie.
	 *
	 * @param array  $serie  Y-m-d => número.
	 * @param string $nombre Qué se cuenta.
	 * @return string HTML.
	 */
	private static function barras( array $serie, $nombre ) {
		if ( ! $serie ) {
			return '';
		}
		list( $serie, $semanas ) = Concursos_Stats::agrupar( $serie );
		$valores                 = array_values( $serie );
		$claves                  = array_keys( $serie );
		$n                       = count( $valores );
		$max                     = max( 1, max( $valores ) );
		$ancho                   = 100 / $n;
		$rects                   = '';
		foreach ( $valores as $i => $v ) {
			$alto   = $v ? max( 1, $v / $max * 38 ) : 0;
			$rects .= sprintf(
				'<rect x="%1$s" y="%2$s" width="%3$s" height="%4$s"><title>%5$s</title></rect>',
				esc_attr( round( $i * $ancho + $ancho * 0.12, 3 ) ),
				esc_attr( round( 40 - $alto, 3 ) ),
				esc_attr( round( $ancho * 0.76, 3 ) ),
				esc_attr( round( $alto, 3 ) ),
				esc_html( self::dia_corto( $claves[ $i ] ) . ': ' . number_format_i18n( $v ) )
			);
		}

		return sprintf(
			'<figure class="concursos-stats-barras"><svg viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label="%1$s">%2$s</svg><figcaption class="concursos-stats-barras__eje"><span>%3$s</span><span>%4$s</span><span>%5$s</span></figcaption></figure>',
			esc_attr( self::descripcion( $serie, $semanas, $nombre ) ),
			$rects, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
			esc_html( self::dia_corto( $claves[0] ) ),
			/* translators: %s: highest value in the chart. */
			esc_html( sprintf( __( 'max. %s', 'foncholuis-art-challenge-manager' ), number_format_i18n( max( $valores ) ) ) ),
			esc_html( self::dia_corto( $claves[ $n - 1 ] ) )
		);
	}

	/**
	 * Serie diaria de fotos o votos de un reto entre dos fechas, hasta hoy como mucho.
	 *
	 * @param string $metrica fotos o votos.
	 * @param int    $reto_id Reto.
	 * @param int    $desde   Marca de tiempo inicial.
	 * @param int    $hasta   Marca de tiempo final.
	 * @return int[]
	 */
	private static function serie_reto( $metrica, $reto_id, $desde, $hasta ) {
		$inicio = self::dia( $desde );
		$fin    = min( self::dia( $hasta )->modify( '+1 day' ), self::dia( time() )->modify( '+1 day' ) );

		return $fin > $inicio ? Concursos_Stats::serie( $metrica, $inicio, $fin, $reto_id ) : array();
	}

	/* ---------------------------------------------------------------------
	 * Página.
	 * ------------------------------------------------------------------ */

	/**
	 * Pinta la página.
	 */
	public static function render() {
		$estado = self::estado();
		$retos  = Concursos_Reto::all();
		$elegido = null;
		foreach ( $retos as $reto ) {
			if ( $reto['post']->ID === $estado['reto'] ) {
				$elegido = $reto;
			}
		}

		$visibles = array_values(
			array_filter(
				$retos,
				function ( $reto ) use ( $estado ) {
					return ( 'todos' === $estado['filtro'] || self::grupo( $reto['status'] ) === $estado['filtro'] )
						&& ( '' === $estado['buscar'] || false !== stripos( get_the_title( $reto['post'] ), $estado['buscar'] ) );
				}
			)
		);
		if ( ! $elegido ) {
			$elegido = self::reto_por_defecto( $visibles ? $visibles : $retos );
		}
		?>
		<div class="wrap concursos-stats-pagina">
			<div class="concursos-stats-cabecera">
				<div>
					<h1 class="wp-heading-inline"><?php esc_html_e( 'Stats', 'foncholuis-art-challenge-manager' ); ?></h1>
					<p class="concursos-stats-subtitulo"><?php esc_html_e( 'Challenge activity', 'foncholuis-art-challenge-manager' ); ?></p>
				</div>
				<?php self::render_periodo( $estado ); ?>
			</div>
			<hr class="wp-header-end">

			<?php
			self::render_aviso( $retos );
			self::render_panel( $estado['periodo'] );
			?>

			<div class="concursos-stats-columnas">
				<?php self::render_lista( $retos, $visibles, $estado, $elegido ); ?>
				<div class="concursos-stats-detalle">
					<?php
					if ( $elegido ) {
						self::render_reto( $elegido );
					} else {
						echo '<div class="postbox"><div class="inside"><p>' . esc_html__( 'There are no challenges yet.', 'foncholuis-art-challenge-manager' ) . '</p></div></div>';
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Selector de periodo y exportación del panel.
	 *
	 * @param array $estado Estado de la página.
	 */
	private static function render_periodo( array $estado ) {
		$csv = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::CSV,
					'tipo'    => 'panel',
					'periodo' => $estado['periodo'],
				),
				admin_url( 'admin-post.php' )
			),
			self::CSV
		);
		?>
		<form method="get" class="concursos-stats-periodo">
			<input type="hidden" name="post_type" value="<?php echo esc_attr( Concursos_Post_Types::RETO ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
			<?php foreach ( array( 'filtro', 'buscar', 'reto' ) as $clave ) : ?>
				<?php if ( $estado[ $clave ] && 'todos' !== $estado[ $clave ] ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $clave ); ?>" value="<?php echo esc_attr( $estado[ $clave ] ); ?>">
				<?php endif; ?>
			<?php endforeach; ?>
			<label for="concursos-stats-periodo"><?php esc_html_e( 'Period', 'foncholuis-art-challenge-manager' ); ?></label>
			<select name="periodo" id="concursos-stats-periodo" data-autoenviar>
				<?php foreach ( Concursos_Stats::periodos() as $clave => $nombre ) : ?>
					<option value="<?php echo esc_attr( $clave ); ?>" <?php selected( $estado['periodo'], $clave ); ?>><?php echo esc_html( $nombre ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button concursos-stats-aplicar"><?php esc_html_e( 'Apply', 'foncholuis-art-challenge-manager' ); ?></button>
			<a class="button" href="<?php echo esc_url( $csv ); ?>"><?php esc_html_e( 'Export CSV', 'foncholuis-art-challenge-manager' ); ?></a>
		</form>
		<?php
	}

	/**
	 * Aviso con los próximos cambios de etapa de los retos publicados.
	 *
	 * @param array[] $retos Retos.
	 */
	private static function render_aviso( array $retos ) {
		$frases  = array(
			/* translators: 1: challenge name, 2: time such as "2 days", 3: date. */
			'proximo'    => __( '“%1$s” opens in %2$s (%3$s).', 'foncholuis-art-challenge-manager' ),
			/* translators: 1: challenge name, 2: time such as "2 days", 3: date. */
			'envios'     => __( '“%1$s” closes submissions in %2$s (%3$s).', 'foncholuis-art-challenge-manager' ),
			/* translators: 1: challenge name, 2: time such as "2 days", 3: date. */
			'votacion'   => __( '“%1$s” closes voting in %2$s (%3$s).', 'foncholuis-art-challenge-manager' ),
			/* translators: 1: challenge name, 2: time such as "2 days", 3: date. */
			'evaluacion' => __( '“%1$s” publishes its results in %2$s (%3$s).', 'foncholuis-art-challenge-manager' ),
		);
		$cambios = array();
		foreach ( $retos as $reto ) {
			$etapa = Concursos_Reto::proxima_etapa( $reto['status'], $reto['settings'] );
			if ( 'publish' === $reto['post']->post_status && $etapa && $etapa[1] > time() ) {
				$cambios[] = array( $etapa[1], sprintf( $frases[ $reto['status'] ], get_the_title( $reto['post'] ), human_time_diff( time(), $etapa[1] ), Concursos_Vista::dia_mes( $etapa[1] ) ) );
			}
		}
		if ( ! $cambios ) {
			return;
		}
		usort(
			$cambios,
			function ( $a, $b ) {
				return $a[0] - $b[0];
			}
		);
		?>
		<div class="notice notice-info concursos-stats-aviso">
			<p><strong><?php esc_html_e( 'Upcoming stage changes', 'foncholuis-art-challenge-manager' ); ?></strong></p>
			<ul>
				<?php foreach ( $cambios as $cambio ) : ?>
					<li><?php echo esc_html( $cambio[1] ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Diferencia con el periodo anterior, con flecha y texto.
	 *
	 * @param int $total Total del periodo.
	 * @param int $antes Total del periodo anterior.
	 * @return string HTML.
	 */
	private static function diferencia( $total, $antes ) {
		$dif = $total - $antes;
		if ( 0 === $dif ) {
			return '<p class="concursos-stats-dif concursos-stats-dif--igual">= ' . esc_html__( 'No change vs previous period', 'foncholuis-art-challenge-manager' ) . '</p>';
		}

		$cifra = ( $dif > 0 ? '+' : '−' ) . number_format_i18n( abs( $dif ) );
		if ( $antes ) {
			$cifra .= ' (' . ( $dif > 0 ? '+' : '−' ) . number_format_i18n( round( abs( $dif ) / $antes * 100 ) ) . '%)';
		}

		return sprintf(
			'<p class="concursos-stats-dif concursos-stats-dif--%1$s"><span aria-hidden="true">%2$s</span> %3$s</p>',
			$dif > 0 ? 'sube' : 'baja',
			$dif > 0 ? '↑' : '↓',
			/* translators: %s: change such as "+12 (+40%)". */
			esc_html( sprintf( $dif > 0 ? __( 'Up %s vs previous period', 'foncholuis-art-challenge-manager' ) : __( 'Down %s vs previous period', 'foncholuis-art-challenge-manager' ), $cifra ) )
		);
	}

	/**
	 * Tarjetas con la actividad del periodo.
	 *
	 * @param string $periodo Periodo.
	 */
	private static function render_panel( $periodo ) {
		$datos = Concursos_Stats::panel( $periodo );
		?>
		<h2 class="screen-reader-text"><?php esc_html_e( 'Activity in the period', 'foncholuis-art-challenge-manager' ); ?></h2>
		<div class="concursos-stats-tarjetas">
			<?php foreach ( Concursos_Stats::metricas() as $clave => $nombre ) : ?>
				<div class="postbox concursos-stats-tarjeta" data-metrica="<?php echo esc_attr( $clave ); ?>">
					<div class="inside">
						<h3 class="concursos-stats-tarjeta__nombre"><?php echo esc_html( $nombre ); ?></h3>
						<p class="concursos-stats-tarjeta__total"><?php echo esc_html( number_format_i18n( $datos[ $clave ]['total'] ) ); ?></p>
						<?php
						echo self::sparkline( $datos[ $clave ]['serie'], $nombre ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
						echo self::diferencia( $datos[ $clave ]['total'], $datos[ $clave ]['antes'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
						?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Lista de retos con buscador y filtros.
	 *
	 * @param array[]    $retos    Todos los retos.
	 * @param array[]    $visibles Retos que pasan el filtro y la búsqueda.
	 * @param array      $estado   Estado de la página.
	 * @param array|null $elegido  Reto mostrado.
	 */
	private static function render_lista( array $retos, array $visibles, array $estado, $elegido ) {
		$nombres = array(
			'todos'      => _x( 'All', 'challenges filter', 'foncholuis-art-challenge-manager' ),
			'activos'    => _x( 'Active', 'challenges filter', 'foncholuis-art-challenge-manager' ),
			'proximos'   => _x( 'Upcoming', 'challenges filter', 'foncholuis-art-challenge-manager' ),
			'terminados' => _x( 'Finished', 'challenges filter', 'foncholuis-art-challenge-manager' ),
		);
		$cuentas = array_fill_keys( self::FILTROS, 0 );
		foreach ( $retos as $reto ) {
			++$cuentas['todos'];
			$grupo = self::grupo( $reto['status'] );
			if ( $grupo ) {
				++$cuentas[ $grupo ];
			}
		}
		?>
		<nav class="concursos-stats-lista" aria-labelledby="concursos-stats-lista-titulo">
			<h2 id="concursos-stats-lista-titulo"><?php esc_html_e( 'Challenges', 'foncholuis-art-challenge-manager' ); ?></h2>
			<form method="get" class="concursos-stats-buscar" role="search">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( Concursos_Post_Types::RETO ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<input type="hidden" name="periodo" value="<?php echo esc_attr( $estado['periodo'] ); ?>">
				<input type="hidden" name="filtro" value="<?php echo esc_attr( $estado['filtro'] ); ?>">
				<label class="screen-reader-text" for="concursos-stats-buscar"><?php esc_html_e( 'Search challenges by title', 'foncholuis-art-challenge-manager' ); ?></label>
				<input type="search" id="concursos-stats-buscar" name="buscar" value="<?php echo esc_attr( $estado['buscar'] ); ?>" placeholder="<?php esc_attr_e( 'Search challenges', 'foncholuis-art-challenge-manager' ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Search', 'foncholuis-art-challenge-manager' ); ?></button>
			</form>
			<ul class="subsubsub">
				<?php foreach ( $nombres as $clave => $nombre ) : ?>
					<li>
						<a href="<?php echo esc_url( self::url( array( 'filtro' => $clave, 'reto' => 0 ) ) ); ?>"<?php echo $clave === $estado['filtro'] ? ' class="current" aria-current="page"' : ''; ?>><?php echo esc_html( $nombre ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( $cuentas[ $clave ] ) ); ?>)</span></a><?php echo 'terminados' !== $clave ? ' |' : ''; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( ! $visibles ) : ?>
				<p class="concursos-stats-lista__vacia"><?php esc_html_e( 'No challenges match.', 'foncholuis-art-challenge-manager' ); ?></p>
			<?php else : ?>
				<ul class="concursos-stats-retos">
					<?php
					foreach ( $visibles as $reto ) :
						$actual = $elegido && $elegido['post']->ID === $reto['post']->ID;
						?>
						<li class="concursos-stats-reto<?php echo $actual ? ' is-actual' : ''; ?>">
							<a href="<?php echo esc_url( self::url( array( 'reto' => $reto['post']->ID ) ) ); ?>"<?php echo $actual ? ' aria-current="page"' : ''; ?>>
								<?php echo self::miniatura_reto( $reto, 40 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
								<span class="concursos-stats-reto__texto">
									<span class="concursos-stats-reto__titulo"><?php echo esc_html( get_the_title( $reto['post'] ) ); ?></span>
									<span class="concursos-stats-reto__etiquetas">
										<?php
										echo self::badge( $reto['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
										if ( 'publish' !== $reto['post']->post_status ) {
											echo ' <span class="concursos-badge">' . esc_html__( 'Not published', 'foncholuis-art-challenge-manager' ) . '</span>';
										}
										if ( self::es_privado( $reto['settings'] ) ) {
											echo ' <span class="dashicons dashicons-lock" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Private', 'foncholuis-art-challenge-manager' ) . '</span>';
										}
										?>
									</span>
									<span class="concursos-stats-reto__linea"><?php echo esc_html( self::linea_corta( $reto ) ); ?></span>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</nav>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Resumen de un reto.
	 * ------------------------------------------------------------------ */

	/**
	 * Resumen del reto elegido.
	 *
	 * @param array $reto Reto.
	 */
	private static function render_reto( array $reto ) {
		$id     = $reto['post']->ID;
		$s      = $reto['settings'];
		$status = $reto['status'];
		$c      = Concursos_Stats::cifras_reto( $id );

		$acciones = array(
			'ver'           => array( get_permalink( $id ), __( 'View challenge', 'foncholuis-art-challenge-manager' ) ),
			'editar'        => array( get_edit_post_link( $id, 'raw' ), __( 'Edit', 'foncholuis-art-challenge-manager' ) ),
			'participantes' => array( admin_url( 'edit.php?post_type=' . Concursos_Participante::POST_TYPE . '&concursos_reto=' . $id ), __( 'Participants', 'foncholuis-art-challenge-manager' ) ),
			'votantes'      => array( admin_url( 'edit.php?post_type=' . Concursos_Post_Types::RETO . '&page=' . Concursos_Votantes_Admin::PAGE . '&concursos_reto=' . $id ), __( 'Voters', 'foncholuis-art-challenge-manager' ) ),
			'fotos'         => array( admin_url( 'edit.php?post_type=' . Concursos_Entrada::POST_TYPE . '&concursos_reto=' . $id ), __( 'Photos', 'foncholuis-art-challenge-manager' ) ),
		);
		$enlaces  = array();
		foreach ( $acciones as $clave => $accion ) {
			$enlaces[] = sprintf( '<span class="%1$s"><a href="%2$s">%3$s</a></span>', esc_attr( $clave ), esc_url( $accion[0] ), esc_html( $accion[1] ) );
		}
		?>
		<section class="concursos-stats-reto-detalle" aria-labelledby="concursos-stats-reto-titulo" data-reto="<?php echo esc_attr( $id ); ?>">
			<div class="concursos-stats-reto-cabecera">
				<?php echo self::miniatura_reto( $reto, 64 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?>
				<div>
					<h2 id="concursos-stats-reto-titulo"><?php echo esc_html( get_the_title( $reto['post'] ) ); ?></h2>
					<p class="concursos-stats-reto__etiquetas">
						<?php
						echo self::badge( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
						if ( 'publish' !== $reto['post']->post_status ) {
							echo ' <span class="concursos-badge">' . esc_html__( 'Not published', 'foncholuis-art-challenge-manager' ) . '</span>';
						}
						if ( self::es_privado( $s ) ) {
							echo ' <span class="concursos-badge concursos-stats-privado"><span class="dashicons dashicons-lock" aria-hidden="true"></span> ' . esc_html__( 'Private · members only', 'foncholuis-art-challenge-manager' ) . '</span>';
						}
						if ( ! empty( $s['anonimo'] ) ) {
							echo ' <span class="concursos-badge">' . esc_html__( 'Anonymous voting', 'foncholuis-art-challenge-manager' ) . '</span>';
						}
						?>
					</p>
					<div class="row-actions concursos-stats-acciones"><?php echo implode( ' | ', $enlaces ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado. ?></div>
				</div>
			</div>

			<?php
			self::render_etapas( $status, $s );
			self::render_cifras( $c );

			if ( 'envios' === $status ) {
				self::render_envios( $id, $s );
			} elseif ( in_array( $status, array( 'votacion', 'evaluacion' ), true ) ) {
				self::render_votacion( $id, $s );
			} elseif ( 'finalizado' === $status ) {
				self::render_final( $id, $s, $c );
			} elseif ( 'proximo' === $status ) {
				self::render_proximo( $s );
			}
			?>
		</section>
		<?php
	}

	/**
	 * Cuenta atrás y línea de etapas.
	 *
	 * @param string $status Fase.
	 * @param array  $s      Configuración.
	 */
	private static function render_etapas( $status, array $s ) {
		if ( 'sin_fechas' === $status ) {
			echo '<div class="postbox"><div class="inside"><p>' . esc_html__( 'This challenge has no dates yet.', 'foncholuis-art-challenge-manager' ) . '</p></div></div>';

			return;
		}

		$frases = array(
			/* translators: %s: date. */
			'proximo'    => __( 'to open the challenge · %s', 'foncholuis-art-challenge-manager' ),
			/* translators: %s: date. */
			'envios'     => __( 'to close submissions · %s', 'foncholuis-art-challenge-manager' ),
			/* translators: %s: date. */
			'votacion'   => __( 'to close voting · %s', 'foncholuis-art-challenge-manager' ),
			/* translators: %s: date. */
			'evaluacion' => __( 'to publish the results · %s', 'foncholuis-art-challenge-manager' ),
		);
		$etapa  = Concursos_Reto::proxima_etapa( $status, $s );
		$ahora  = time();

		$tramos = array(
			array( 'envios', __( 'Submissions', 'foncholuis-art-challenge-manager' ), (int) $s['inicio'], (int) $s['fin_envios'] ),
			array( 'votacion', _x( 'Voting', 'calendar phase', 'foncholuis-art-challenge-manager' ), (int) $s['fin_envios'], (int) $s['fin_votacion'] ),
		);
		if ( (int) $s['cierre'] > (int) $s['fin_votacion'] ) {
			$tramos[] = array( 'evaluacion', __( 'Review', 'foncholuis-art-challenge-manager' ), (int) $s['fin_votacion'], (int) $s['cierre'] );
		}
		$tramos[] = array( 'resultados', __( 'Results', 'foncholuis-art-challenge-manager' ), (int) $s['cierre'], (int) $s['cierre'] );

		$estados = array(
			'hecha'     => _x( 'Done', 'stage state', 'foncholuis-art-challenge-manager' ),
			'ahora'     => _x( 'Now', 'stage state', 'foncholuis-art-challenge-manager' ),
			'pendiente' => _x( 'Upcoming', 'stage state', 'foncholuis-art-challenge-manager' ),
		);
		?>
		<div class="postbox concursos-stats-etapas">
			<div class="postbox-header"><h3 class="hndle"><?php esc_html_e( 'Stages', 'foncholuis-art-challenge-manager' ); ?></h3></div>
			<div class="inside">
				<?php if ( $etapa && $etapa[1] > $ahora ) : ?>
					<p class="concursos-stats-cuenta">
						<strong><?php echo esc_html( self::falta( $etapa[1] ) ); ?></strong>
						<span><?php echo esc_html( sprintf( $frases[ $status ], Concursos_Vista::dia_mes( $etapa[1] ) ) ); ?></span>
					</p>
				<?php elseif ( 'finalizado' === $status ) : ?>
					<p class="concursos-stats-cuenta">
						<strong><?php echo esc_html( Concursos_Reto::status_labels()['finalizado'] ); ?></strong>
						<?php /* translators: %s: date. */ ?>
						<span><?php echo esc_html( sprintf( __( 'results published on %s', 'foncholuis-art-challenge-manager' ), Concursos_Vista::dia_mes( $s['cierre'] ) ) ); ?></span>
					</p>
				<?php endif; ?>
				<ol class="concursos-stats-linea">
					<?php
					foreach ( $tramos as $i => $tramo ) :
						list( $clave, $nombre, $desde, $hasta ) = $tramo;
						if ( 'resultados' === $clave ) {
							$estado = $ahora >= $hasta ? 'hecha' : 'pendiente';
						} else {
							$estado = $ahora >= $hasta ? 'hecha' : ( $ahora >= $desde ? 'ahora' : 'pendiente' );
						}
						// Marca de hoy: dentro de la etapa actual, antes de la primera o en los resultados.
						$hoy = null;
						if ( 'ahora' === $estado ) {
							$hoy = $hasta > $desde ? ( $ahora - $desde ) / ( $hasta - $desde ) * 100 : 0;
						} elseif ( 0 === $i && 'proximo' === $status ) {
							$hoy = 0;
						} elseif ( 'resultados' === $clave && 'finalizado' === $status ) {
							$hoy = 50;
						}
						?>
						<li class="concursos-stats-linea__tramo concursos-stats-linea__tramo--<?php echo esc_attr( $estado ); ?>"<?php echo 'ahora' === $estado ? ' aria-current="step"' : ''; ?>>
							<span class="concursos-stats-linea__barra" aria-hidden="true">
								<?php if ( null !== $hoy ) : ?>
									<span class="concursos-stats-hoy" style="left:<?php echo esc_attr( round( max( 0, min( 100, $hoy ) ), 1 ) ); ?>%"><?php esc_html_e( 'Today', 'foncholuis-art-challenge-manager' ); ?></span>
								<?php endif; ?>
							</span>
							<span class="concursos-stats-linea__nombre"><?php echo esc_html( $nombre ); ?></span>
							<span class="concursos-stats-linea__fechas"><?php echo esc_html( 'resultados' === $clave ? Concursos_Vista::dia_mes( $desde ) : Concursos_Vista::intervalo( $desde, $hasta ) ); ?></span>
							<span class="concursos-stats-linea__estado"><?php echo esc_html( $estados[ $estado ] ); ?><?php echo null !== $hoy ? ' · ' . esc_html__( 'today', 'foncholuis-art-challenge-manager' ) : ''; ?></span>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		</div>
		<?php
	}

	/**
	 * Cuatro cifras clave del reto.
	 *
	 * @param int[] $c Cifras.
	 */
	private static function render_cifras( array $c ) {
		$participacion = $c['confirmados'] ? round( $c['votantes'] / $c['confirmados'] * 100 ) : 0;
		$cifras        = array(
			array( number_format_i18n( $c['activos'] ) . ' / ' . number_format_i18n( $c['participantes'] ), __( 'Active / registered participants', 'foncholuis-art-challenge-manager' ), '' ),
			array( number_format_i18n( $c['fotos'] ), __( 'Photos', 'foncholuis-art-challenge-manager' ), '' ),
			array(
				number_format_i18n( $c['votantes'] ),
				__( 'Voters who voted', 'foncholuis-art-challenge-manager' ),
				/* translators: 1: percentage, 2: number of confirmed voters. */
				$c['confirmados'] ? sprintf( __( '%1$s%% of all %2$s confirmed voters', 'foncholuis-art-challenge-manager' ), number_format_i18n( $participacion ), number_format_i18n( $c['confirmados'] ) ) : '',
			),
			array( number_format_i18n( $c['votos'] ), __( 'Votes', 'foncholuis-art-challenge-manager' ), '' ),
		);
		?>
		<div class="concursos-stats-cifras">
			<?php foreach ( $cifras as $cifra ) : ?>
				<div class="postbox concursos-stats-cifra">
					<div class="inside">
						<p class="concursos-stats-cifra__valor"><?php echo esc_html( $cifra[0] ); ?></p>
						<p class="concursos-stats-cifra__nombre"><?php echo esc_html( $cifra[1] ); ?></p>
						<?php if ( $cifra[2] ) : ?>
							<p class="concursos-stats-cifra__nota"><?php echo esc_html( $cifra[2] ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Caja con título.
	 *
	 * @param string $titulo Título.
	 * @param string $html   Contenido ya escapado.
	 * @param string $nota   Nota junto al título.
	 * @param string $clase  Clase adicional.
	 */
	private static function caja( $titulo, $html, $nota = '', $clase = '' ) {
		printf(
			'<div class="postbox concursos-stats-caja %1$s"><div class="postbox-header"><h3 class="hndle">%2$s%3$s</h3></div><div class="inside">%4$s</div></div>',
			esc_attr( $clase ),
			esc_html( $titulo ),
			$nota ? ' <span class="concursos-stats-nota">' . esc_html( $nota ) . '</span>' : '',
			$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
		);
	}

	/**
	 * Reto en envíos: últimas fotos y fotos por día.
	 *
	 * @param int   $id Reto.
	 * @param array $s  Configuración.
	 */
	private static function render_envios( $id, array $s ) {
		$fotos = array_slice( array_reverse( Concursos_Entrada::find( array( 'reto' => $id ) ) ), 0, 6 );
		if ( $fotos ) {
			$html = '<ul class="concursos-stats-fotos">';
			foreach ( $fotos as $foto ) {
				$html .= sprintf(
					'<li>%1$s<span><strong>%2$s</strong><br>%3$s · %4$s</span></li>',
					self::miniatura_foto( $foto->ID ),
					esc_html( get_the_title( $foto ) ),
					esc_html( self::autor( $foto->ID ) ),
					/* translators: %s: time such as "2 hours". */
					esc_html( sprintf( __( '%s ago', 'foncholuis-art-challenge-manager' ), human_time_diff( (int) get_post_time( 'U', true, $foto ), time() ) ) )
				);
			}
			$html .= '</ul>';
		} else {
			$html = '<p>' . esc_html__( 'No photos yet.', 'foncholuis-art-challenge-manager' ) . '</p>';
		}
		self::caja( __( 'Latest photos received', 'foncholuis-art-challenge-manager' ), $html );

		$nombre = __( 'Photos received', 'foncholuis-art-challenge-manager' );
		self::caja( $nombre, self::barras( self::serie_reto( 'fotos', $id, $s['inicio'], $s['fin_envios'] ), $nombre ) );
	}

	/**
	 * Clasificación en HTML.
	 *
	 * @param array[] $filas Filas de Concursos_Stats::clasificacion().
	 * @param array   $s     Configuración.
	 * @return string
	 */
	private static function ranking( array $filas, array $s ) {
		if ( ! $filas ) {
			return '<p>' . esc_html__( 'No photos yet.', 'foncholuis-art-challenge-manager' ) . '</p>';
		}
		$max  = max( 1, max( wp_list_pluck( $filas, 'puntos' ) ) );
		$html = '<ol class="concursos-stats-ranking">';
		foreach ( $filas as $fila ) {
			$medalla = $fila['lugar']
				? sprintf(
					'<span class="concursos-stats-medalla concursos-stats-medalla--%1$d">%2$s</span>',
					min( 4, (int) $fila['lugar'] ),
					esc_html( Concursos_Votos::ordinal( $fila['lugar'] ) . ( $fila['empate'] ? ' ' . __( '(tie)', 'foncholuis-art-challenge-manager' ) : '' ) )
				)
				: '<span class="concursos-stats-medalla">—</span>';
			$html   .= sprintf(
				'<li data-entrada="%1$d" data-lugar="%2$d">%3$s%4$s<span class="concursos-stats-ranking__foto"><strong>%5$s</strong><br>%6$s</span><span class="concursos-stats-ranking__barra" aria-hidden="true"><span style="width:%7$s%%"></span></span><span class="concursos-stats-ranking__puntos">%8$s</span></li>',
				(int) $fila['id'],
				(int) $fila['lugar'],
				$medalla,
				self::miniatura_foto( $fila['id'] ),
				esc_html( get_the_title( $fila['id'] ) ),
				esc_html( self::autor( $fila['id'] ) ),
				esc_attr( round( $fila['puntos'] / $max * 100, 1 ) ),
				esc_html( Concursos_Votos::etiqueta( $fila['puntos'], $s ) )
			);
		}

		return $html . '</ol>';
	}

	/**
	 * Reto en votación o evaluación: clasificación provisional, últimos votos y votos por día.
	 *
	 * @param int   $id Reto.
	 * @param array $s  Configuración.
	 */
	private static function render_votacion( $id, array $s ) {
		self::caja(
			__( 'Provisional ranking', 'foncholuis-art-challenge-manager' ),
			self::ranking( array_slice( Concursos_Stats::clasificacion( $id, $s ), 0, 5 ), $s ),
			__( 'Only visible in the admin', 'foncholuis-art-challenge-manager' )
		);

		$votos = Concursos_Stats::ultimos_votos( $id );
		if ( $votos ) {
			$html = '<table class="widefat striped concursos-stats-votos"><thead><tr>'
				. '<th scope="col">' . esc_html__( 'When', 'foncholuis-art-challenge-manager' ) . '</th>'
				. '<th scope="col">' . esc_html__( 'Voter', 'foncholuis-art-challenge-manager' ) . '</th>'
				. '<th scope="col">' . esc_html__( 'Place', 'foncholuis-art-challenge-manager' ) . '</th>'
				. '<th scope="col">' . esc_html__( 'Photo', 'foncholuis-art-challenge-manager' ) . '</th>'
				. '</tr></thead><tbody>';
			foreach ( $votos as $voto ) {
				$html .= sprintf(
					'<tr><td><time datetime="%1$s" title="%2$s">%3$s</time></td><td>%4$s</td><td>%5$s</td><td>%6$s</td></tr>',
					esc_attr( wp_date( 'c', (int) $voto->creado ) ),
					esc_attr( Concursos_Reto::format_date( (int) $voto->creado ) ),
					/* translators: %s: time such as "2 hours". */
					esc_html( sprintf( __( '%s ago', 'foncholuis-art-challenge-manager' ), human_time_diff( (int) $voto->creado, time() ) ) ),
					esc_html( Concursos_Stats::enmascarar( $voto->email ) ),
					esc_html( (int) $voto->puesto ? Concursos_Votos::ordinal( (int) $voto->puesto ) : '—' ),
					esc_html( get_the_title( (int) $voto->entrada_id ) )
				);
			}
			$html .= '</tbody></table>';
		} else {
			$html = '<p>' . esc_html__( 'No votes yet.', 'foncholuis-art-challenge-manager' ) . '</p>';
		}
		self::caja( __( 'Latest votes', 'foncholuis-art-challenge-manager' ), $html );

		$nombre = __( 'Votes cast', 'foncholuis-art-challenge-manager' );
		self::caja( $nombre, self::barras( self::serie_reto( 'votos', $id, $s['fin_envios'], $s['fin_votacion'] ), $nombre ) );
	}

	/**
	 * Reto terminado: clasificación final, participación, votos por día y acciones.
	 *
	 * @param int   $id Reto.
	 * @param array $s  Configuración.
	 * @param int[] $c  Cifras.
	 */
	private static function render_final( $id, array $s, array $c ) {
		$csv = wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::CSV,
					'tipo'   => 'resultados',
					'reto'   => $id,
				),
				admin_url( 'admin-post.php' )
			),
			self::CSV
		);
		?>
		<p class="concursos-stats-botones">
			<a class="button button-primary" href="<?php echo esc_url( $csv ); ?>"><?php esc_html_e( 'Export results', 'foncholuis-art-challenge-manager' ); ?></a>
			<a class="button" href="<?php echo esc_url( Concursos_Duplicar::url( $id ) ); ?>"><?php esc_html_e( 'Duplicate challenge', 'foncholuis-art-challenge-manager' ); ?></a>
		</p>
		<?php
		self::caja( __( 'Final ranking', 'foncholuis-art-challenge-manager' ), self::ranking( Concursos_Stats::clasificacion( $id, $s ), $s ) );

		$html = '<p>' . esc_html(
			sprintf(
				/* translators: 1: voters who voted, 2: confirmed voters on the site. */
				__( '%1$s people voted, out of %2$s confirmed voters on the site.', 'foncholuis-art-challenge-manager' ),
				number_format_i18n( $c['votantes'] ),
				number_format_i18n( $c['confirmados'] )
			)
		) . '</p>';
		self::caja( __( 'Final participation', 'foncholuis-art-challenge-manager' ), $html );

		$nombre = __( 'Votes cast', 'foncholuis-art-challenge-manager' );
		self::caja( $nombre, self::barras( self::serie_reto( 'votos', $id, $s['fin_envios'], $s['fin_votacion'] ), $nombre ) );
	}

	/**
	 * Reto próximo: configuración y estado vacío.
	 *
	 * @param array $s Configuración.
	 */
	private static function render_proximo( array $s ) {
		$premios = array_filter( (array) $s['premios'] );
		$filas   = array(
			__( 'Photos per person', 'foncholuis-art-challenge-manager' ) => number_format_i18n( (int) $s['max_fotos'] ),
			__( 'Votes per person', 'foncholuis-art-challenge-manager' )  => number_format_i18n( (int) $s['votos_por_votante'] ),
			__( 'Photo visibility', 'foncholuis-art-challenge-manager' )  => 'abierto' === $s['modo'] ? __( 'Shown as they are submitted', 'foncholuis-art-challenge-manager' ) : __( 'Shown when submissions end', 'foncholuis-art-challenge-manager' ),
			__( 'Anonymous voting', 'foncholuis-art-challenge-manager' )  => ! empty( $s['anonimo'] ) ? __( 'Yes', 'foncholuis-art-challenge-manager' ) : __( 'No', 'foncholuis-art-challenge-manager' ),
			__( 'Prizes', 'foncholuis-art-challenge-manager' )            => $premios ? implode( ' · ', $premios ) : __( 'None', 'foncholuis-art-challenge-manager' ),
		);
		$html    = '<dl class="concursos-stats-config">';
		foreach ( $filas as $nombre => $valor ) {
			$html .= '<dt>' . esc_html( $nombre ) . '</dt><dd>' . esc_html( $valor ) . '</dd>';
		}
		$html .= '</dl>';
		self::caja( __( 'Settings', 'foncholuis-art-challenge-manager' ), $html );

		/* translators: %s: date. */
		echo '<div class="concursos-stats-vacio"><p>' . esc_html( sprintf( __( 'No activity yet. Opens on %s.', 'foncholuis-art-challenge-manager' ), Concursos_Reto::format_dia( $s['inicio'] ) ) ) . '</p></div>';
	}

	/* ---------------------------------------------------------------------
	 * Exportación CSV.
	 * ------------------------------------------------------------------ */

	/**
	 * Línea CSV. Los campos que empiezan como una fórmula se neutralizan para las hojas de cálculo.
	 *
	 * @param array $campos Campos.
	 * @return string
	 */
	private static function linea_csv( array $campos ) {
		$salida = array();
		foreach ( $campos as $campo ) {
			$campo = (string) $campo;
			if ( '' !== $campo && in_array( $campo[0], array( '=', '+', '-', '@' ), true ) && ! is_numeric( $campo ) ) {
				$campo = "'" . $campo;
			}
			$salida[] = '"' . str_replace( '"', '""', $campo ) . '"';
		}

		return implode( ',', $salida ) . "\r\n";
	}

	/**
	 * Descarga un CSV: actividad del periodo o resultados de un reto. Nunca incluye emails.
	 */
	public static function csv() {
		check_admin_referer( self::CSV );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You cannot do this.', 'foncholuis-art-challenge-manager' ), '', array( 'response' => 403 ) );
		}

		$tipo  = isset( $_GET['tipo'] ) ? sanitize_key( wp_unslash( $_GET['tipo'] ) ) : '';
		$filas = array();

		if ( 'resultados' === $tipo ) {
			$reto = isset( $_GET['reto'] ) ? absint( $_GET['reto'] ) : 0;
			if ( Concursos_Post_Types::RETO !== get_post_type( $reto ) ) {
				wp_die( esc_html__( 'Challenge not found.', 'foncholuis-art-challenge-manager' ), '', array( 'response' => 404 ) );
			}
			$s       = Concursos_Reto::get_settings( $reto );
			$nombre  = 'resultados-' . sanitize_title( get_the_title( $reto ) );
			$filas[] = array( __( 'Place', 'foncholuis-art-challenge-manager' ), __( 'Photo', 'foncholuis-art-challenge-manager' ), __( 'Author', 'foncholuis-art-challenge-manager' ), Concursos_Votos::por_puestos( $s ) ? __( 'Points', 'foncholuis-art-challenge-manager' ) : __( 'Votes', 'foncholuis-art-challenge-manager' ), __( 'Number of votes', 'foncholuis-art-challenge-manager' ) );
			foreach ( Concursos_Stats::clasificacion( $reto, $s ) as $fila ) {
				$filas[] = array(
					$fila['lugar'] ? $fila['lugar'] . ( $fila['empate'] ? ' ' . __( '(tie)', 'foncholuis-art-challenge-manager' ) : '' ) : '',
					get_the_title( $fila['id'] ),
					self::autor( $fila['id'] ),
					$fila['puntos'],
					$fila['votos'],
				);
			}
		} else {
			$periodo = isset( $_GET['periodo'] ) ? sanitize_key( wp_unslash( $_GET['periodo'] ) ) : '7d';
			$periodo = isset( Concursos_Stats::periodos()[ $periodo ] ) ? $periodo : '7d';
			$datos   = Concursos_Stats::panel( $periodo );
			$nombre  = 'stats-' . $periodo;
			$filas[] = array_merge( array( __( 'Date', 'foncholuis-art-challenge-manager' ) ), array_values( Concursos_Stats::metricas() ) );
			foreach ( array_keys( $datos['votos']['serie'] ) as $dia ) {
				$fila = array( $dia );
				foreach ( array_keys( Concursos_Stats::metricas() ) as $metrica ) {
					$fila[] = $datos[ $metrica ]['serie'][ $dia ];
				}
				$filas[] = $fila;
			}
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $nombre . '-' . wp_date( 'Y-m-d' ) . '.csv' ) . '"' );
		$csv = "\xEF\xBB\xBF";
		foreach ( $filas as $fila ) {
			$csv .= self::linea_csv( $fila );
		}
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV, no HTML; cada campo va entre comillas.
		exit;
	}
}
