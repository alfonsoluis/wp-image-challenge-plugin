<?php
/**
 * Clasificación general anual de participantes.
 *
 * @package Concursos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Suma los votos recibidos por cada persona en los retos terminados de un año.
 *
 * Un voto vale un punto. La persona se identifica por su email, como en su
 * perfil, y los empates comparten posición.
 */
class Concursos_Clasificacion {

	/**
	 * Calcula la clasificación.
	 *
	 * @param int      $anio  Año en que terminaron los retos.
	 * @param int|null $hasta Contar solo los retos cerrados hasta esta fecha.
	 * @return array[] Cada fila: lugar, empate, nombre, url, retos, puntos.
	 */
	public static function calcular( $anio, $hasta = null ) {
		$filas = array_values(
			array_filter(
				self::personas( $anio, $hasta ),
				function ( $persona ) {
					return $persona['puntos'] > 0;
				}
			)
		);
		usort(
			$filas,
			function ( $a, $b ) {
				return $b['puntos'] <=> $a['puntos'] ?: strcmp( $a['nombre'], $b['nombre'] );
			}
		);

		$cuantos = array_count_values( wp_list_pluck( $filas, 'puntos' ) );
		$lugar   = 0;
		$previo  = null;
		foreach ( $filas as $i => $fila ) {
			if ( $fila['puntos'] !== $previo ) {
				++$lugar;
				$previo = $fila['puntos'];
			}
			$filas[ $i ]['lugar']  = $lugar;
			$filas[ $i ]['empate'] = $cuantos[ $fila['puntos'] ] > 1;
			$filas[ $i ]['retos']  = count( $fila['retos'] );
		}

		return $filas;
	}

	/**
	 * Quienes participaron en los retos del año sin conseguir puntos, por nombre.
	 *
	 * @param int      $anio  Año en que terminaron los retos.
	 * @param int|null $hasta Contar solo los retos cerrados hasta esta fecha.
	 * @return array[] Cada fila: nombre, url.
	 */
	public static function sin_puntos( $anio, $hasta = null ) {
		$filas = array_values(
			array_filter(
				self::personas( $anio, $hasta ),
				function ( $persona ) {
					return 0 === $persona['puntos'];
				}
			)
		);
		usort(
			$filas,
			function ( $a, $b ) {
				return strcmp( remove_accents( $a['nombre'] ), remove_accents( $b['nombre'] ) );
			}
		);

		return $filas;
	}

	/**
	 * Todas las personas que participaron en los retos terminados del año, con sus puntos.
	 *
	 * @param int      $anio  Año.
	 * @param int|null $hasta Fecha de cierre máxima.
	 * @return array[] email => nombre, url, retos (reto => true), puntos.
	 */
	private static function personas( $anio, $hasta ) {
		$personas = array();

		// Los retos llegan del más reciente al más antiguo: el nombre más nuevo es el que queda.
		foreach ( Concursos_Reto::all( array( 'publish' ) ) as $reto ) {
			$cierre = (int) $reto['settings']['cierre'];
			if ( 'finalizado' !== $reto['status'] || (int) wp_date( 'Y', $cierre ) !== (int) $anio || ( null !== $hasta && $cierre > $hasta ) ) {
				continue;
			}

			$fotos = Concursos_Entrada::find( array( 'reto' => $reto['post']->ID ) );
			$votos = Concursos_Votos::de_fotos( $reto['post']->ID, $fotos );
			foreach ( $fotos as $foto ) {
				$autor = Concursos_Entrada::owner( $foto->ID );
				$email = (string) get_post_meta( $autor, Concursos_Participante::META . 'email', true );
				if ( ! isset( $personas[ $email ] ) ) {
					$personas[ $email ] = array(
						'nombre' => get_the_title( $autor ),
						'url'    => Concursos_Perfil::url( $autor ),
						'retos'  => array(),
						'puntos' => 0,
					);
				}
				$personas[ $email ]['puntos']                       += (int) $votos[ $foto->ID ];
				$personas[ $email ]['retos'][ $reto['post']->ID ] = true;
			}
		}

		return $personas;
	}

	/**
	 * Tabla de la clasificación.
	 *
	 * @param int      $anio  Año.
	 * @param int|null $hasta Fecha de cierre de un reto, para verla tras ese reto.
	 * @return string Vacío si todavía no hay puntos.
	 */
	public static function html( $anio, $hasta = null ) {
		$filas = self::calcular( $anio, $hasta );
		if ( ! $filas ) {
			return '';
		}

		$titulo = null === $hasta
			/* translators: %d: year. */
			? sprintf( __( 'General ranking %d', 'foncholuis-art-challenge-manager' ), $anio )
			/* translators: %d: year. */
			: sprintf( __( 'General ranking %d after this challenge', 'foncholuis-art-challenge-manager' ), $anio );

		ob_start();
		?>
		<section class="pc-seccion pc-clasificacion concursos-clasificacion" id="clasificacion" aria-labelledby="pc-clasificacion">
			<div class="pc-clasificacion__intro">
				<h2 class="pc-seccion__titulo" id="pc-clasificacion"><?php echo esc_html( $titulo ); ?></h2>
				<p class="pc-texto-suave"><?php esc_html_e( 'Points are added up across the challenges finished during the year. With several votes per voter, a 1st is worth 3 points, a 2nd 2 and a 3rd 1; with a single vote, each vote is worth 1 point.', 'foncholuis-art-challenge-manager' ); ?></p>
			</div>
			<div class="pc-clasificacion__datos">
			<div class="pc-tabla-marco">
				<table class="pc-tabla">
					<thead>
						<tr>
							<th scope="col" class="pc-tabla__puesto"><?php esc_html_e( 'Place', 'foncholuis-art-challenge-manager' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Participant', 'foncholuis-art-challenge-manager' ); ?></th>
							<th scope="col" class="pc-tabla__num concursos-tabla__num"><?php esc_html_e( 'Challenges', 'foncholuis-art-challenge-manager' ); ?></th>
							<th scope="col" class="pc-tabla__num concursos-tabla__num"><?php esc_html_e( 'Points', 'foncholuis-art-challenge-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $filas as $fila ) : ?>
							<tr>
								<td class="pc-tabla__puesto">
									<?php echo esc_html( number_format_i18n( $fila['lugar'] ) ); ?>
									<?php if ( $fila['empate'] ) : ?>
										<span class="pc-etiqueta"><?php esc_html_e( '(tie)', 'foncholuis-art-challenge-manager' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<span class="pc-persona">
										<span class="pc-persona__ini" aria-hidden="true"><?php echo esc_html( Concursos_Vista::iniciales( $fila['nombre'] ) ); ?></span>
										<?php if ( $fila['url'] ) : ?>
											<a href="<?php echo esc_url( $fila['url'] ); ?>"><?php echo esc_html( $fila['nombre'] ); ?></a>
										<?php else : ?>
											<span><?php echo esc_html( $fila['nombre'] ); ?></span>
										<?php endif; ?>
									</span>
								</td>
								<td class="pc-tabla__num pc-texto-suave concursos-tabla__num"><?php echo esc_html( number_format_i18n( $fila['retos'] ) ); ?></td>
								<td class="pc-tabla__num pc-tabla__fuerte concursos-tabla__num"><?php echo esc_html( number_format_i18n( $fila['puntos'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
			$sin_puntos = self::sin_puntos( $anio, $hasta );
			if ( $sin_puntos ) :
				?>
				<div class="pc-participaron">
					<h3 class="pc-participaron__titulo"><?php esc_html_e( 'Also took part', 'foncholuis-art-challenge-manager' ); ?></h3>
					<ul class="pc-participaron__personas">
						<?php foreach ( $sin_puntos as $persona ) : ?>
							<li class="pc-persona">
								<span class="pc-persona__ini" aria-hidden="true"><?php echo esc_html( Concursos_Vista::iniciales( $persona['nombre'] ) ); ?></span>
								<?php if ( $persona['url'] ) : ?>
									<a href="<?php echo esc_url( $persona['url'] ); ?>"><?php echo esc_html( $persona['nombre'] ); ?></a>
								<?php else : ?>
									<span><?php echo esc_html( $persona['nombre'] ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}
}
