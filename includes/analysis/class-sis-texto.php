<?php
/**
 * Generación de texto analítico a partir de los datos (no plantillas huecas).
 *
 * Cada párrafo se calcula con las cifras reales de la vista: máximos, medias,
 * tendencias y participaciones. Así el texto que acompaña a un gráfico cambia
 * cuando cambian los datos, y nunca afirma algo que el dato no respalde.
 *
 * Todos los enunciados son RETROSPECTIVOS: describen lo ocurrido en la ventana
 * consultada. Ninguno anticipa sismos futuros ni sugiere plazos, porque eso
 * sería una predicción y no es posible hacerla.
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Texto {

	/* ================================================================= */
	/* Utilidades de formato                                             */
	/* ================================================================= */

	/**
	 * Número con formato local (es-CO).
	 *
	 * @param float $n        Número.
	 * @param int   $decimales Decimales.
	 * @return string
	 */
	public static function num( $n, $decimales = 0 ) {
		return number_format_i18n( (float) $n, (int) $decimales );
	}

	/**
	 * Porcentaje con un decimal.
	 *
	 * @param float $p Porcentaje 0..100.
	 * @return string
	 */
	public static function pct( $p ) {
		return self::num( $p, 1 ) . '%';
	}

	/**
	 * Une una lista en prosa: «A, B y C».
	 *
	 * @param string[] $items Elementos.
	 * @return string
	 */
	public static function lista( array $items ) {
		$items = array_values( array_filter( $items, 'strlen' ) );
		$n     = count( $items );
		if ( 0 === $n ) {
			return '';
		}
		if ( 1 === $n ) {
			return $items[0];
		}
		$ultimo = array_pop( $items );
		return implode( ', ', $items ) . ' y ' . $ultimo;
	}

	/* ================================================================= */
	/* Análisis genérico de una serie de datos                           */
	/* ================================================================= */

	/**
	 * Análisis cuantitativo genérico de una vista tabular.
	 *
	 * @param array[] $datos    Filas de la vista.
	 * @param string  $dimension Campo categórico/temporal.
	 * @param string  $medida    Campo numérico.
	 * @param array   $opts      {unidad, decimales, etiqueta_dim}.
	 * @return string
	 */
	public static function cuantitativo( array $datos, $dimension, $medida, $opts = array() ) {
		$o = array_merge(
			array(
				'unidad'       => '',
				'decimales'    => 0,
				'etiqueta_dim' => 'categoría',
				'nombre'       => 'el valor',
				// Etiqueta del periodo consultado, para que un resultado vacío
				// diga de qué ventana de tiempo está hablando.
				'periodo'      => '',
			),
			$opts
		);

		if ( empty( $datos ) ) {
			return $o['periodo']
				? 'No hay sismos ' . $o['periodo'] . ' con los que calcular cifras para esta vista.'
				: 'Todavía no hay datos suficientes para cuantificar esta vista.';
		}

		$vals = array();
		foreach ( $datos as $f ) {
			if ( isset( $f[ $medida ] ) && is_numeric( $f[ $medida ] ) ) {
				$vals[] = (float) $f[ $medida ];
			}
		}
		if ( empty( $vals ) ) {
			return 'Todavía no hay datos suficientes para cuantificar esta vista.';
		}

		$total = array_sum( $vals );
		$media = SIS_Estadistica::media( $vals );
		$max   = max( $vals );
		$min   = min( $vals );

		$fila_max = null;
		$fila_min = null;
		foreach ( $datos as $f ) {
			if ( ! isset( $f[ $medida ] ) ) {
				continue;
			}
			if ( null === $fila_max || (float) $f[ $medida ] > (float) $fila_max[ $medida ] ) {
				$fila_max = $f;
			}
			if ( null === $fila_min || (float) $f[ $medida ] < (float) $fila_min[ $medida ] ) {
				$fila_min = $f;
			}
		}

		$u   = $o['unidad'] ? ' ' . $o['unidad'] : '';
		$dec = (int) $o['decimales'];

		$partes = array();
		$partes[] = sprintf(
			'El máximo se alcanza en %s con %s%s, frente a un promedio de %s%s y un mínimo de %s%s.',
			isset( $fila_max[ $dimension ] ) ? $fila_max[ $dimension ] : 'la categoría principal',
			self::num( $max, $dec ),
			$u,
			self::num( $media, $dec ),
			$u,
			self::num( $min, $dec ),
			$u
		);

		if ( $total > 0 && $max > 0 ) {
			$partes[] = sprintf(
				'Esa %s concentra el %s del total acumulado (%s%s en %s registros).',
				$o['etiqueta_dim'],
				self::pct( 100 * $max / $total ),
				self::num( $total, $dec ),
				$u,
				self::num( count( $datos ) )
			);
		}

		// Tendencia solo si la dimensión es temporal (mes/año ordenables).
		if ( in_array( $dimension, array( 'mes', 'anio', 'fecha', 'periodo' ), true ) && count( $vals ) >= 4 ) {
			$reg  = SIS_Estadistica::regresion_lineal( $vals );
			$sube = $reg['pendiente'] > 0;
			$partes[] = sprintf(
				'La tendencia lineal del periodo %s a razón de %s%s por paso (R² = %s), un ajuste %s.',
				$sube ? 'crece' : 'decrece',
				self::num( abs( $reg['pendiente'] ), max( 2, $dec ) ),
				$u,
				self::num( $reg['r2'], 2 ),
				$reg['r2'] >= 0.5 ? 'razonable' : 'débil, así que conviene leerla con cautela'
			);
		}

		return implode( ' ', $partes );
	}

	/* ================================================================= */
	/* Textos del dominio                                                */
	/* ================================================================= */

	/**
	 * Narrativa del estado de actividad reciente ([sismos_estado]).
	 *
	 * @param array $resumen Salida de SIS_Catalogo::resumen().
	 * @return string
	 */
	public static function actividad( $resumen ) {
		if ( empty( $resumen['ultimo'] ) ) {
			return 'No hay sismos registrados en la ventana consultada. Con el umbral de detección del catálogo global, la ausencia de registros es normal en periodos cortos.';
		}

		$u = $resumen['ultimo'];
		$c = $resumen['conteos'];

		$txt = sprintf(
			'El último sismo del ámbito fue de magnitud %s a %s km de profundidad, el %s (hora UTC), con epicentro en %s.',
			self::num( $u['mag'], 1 ),
			self::num( $u['profundidad'], 0 ),
			$u['fecha'],
			$u['lugar'] ? $u['lugar'] : 'la región'
		);

		if ( ! empty( $u['municipio'] ) && null !== $u['distancia_km'] ) {
			$txt .= sprintf(
				' El municipio de Nariño más cercano al epicentro es %s, a unos %s km.',
				self::titulo( $u['municipio'] ),
				self::num( $u['distancia_km'], 0 )
			);
		}

		$txt .= sprintf(
			' En las últimas 24 horas se registraron %s sismos; %s en la última semana y %s en los últimos 30 días.',
			self::num( $c['24h'] ),
			self::num( $c['7d'] ),
			self::num( $c['30d'] )
		);

		if ( ! empty( $resumen['mayor'] ) ) {
			$m = $resumen['mayor'];
			$txt .= sprintf(
				' El mayor sismo del catálogo cargado alcanzó magnitud %s (%s).',
				self::num( $m['mag'], 1 ),
				substr( $m['fecha'], 0, 10 )
			);
		}

		return $txt;
	}

	/**
	 * Narrativa de la recurrencia observada por umbral de magnitud.
	 *
	 * @param array $resumen Salida de SIS_Estadistica::resumen().
	 * @return string
	 */
	public static function recurrencia( $resumen ) {
		if ( empty( $resumen['umbrales'] ) ) {
			return 'El catálogo disponible no alcanza para describir la recurrencia por magnitud en esta ventana.';
		}

		$frases = array();
		foreach ( $resumen['umbrales'] as $u ) {
			if ( $u['observados'] < 1 ) {
				$frases[] = sprintf(
					'de magnitud %s o mayor no se registró ninguno',
					self::num( $u['magnitud'], 1 )
				);
				continue;
			}
			$frases[] = sprintf(
				'de magnitud %s o mayor se registraron %s (uno cada %s años en promedio)',
				self::num( $u['magnitud'], 1 ),
				self::num( $u['observados'] ),
				$u['intervalo_medio'] ? self::num( $u['intervalo_medio'], 1 ) : '—'
			);
		}

		return sprintf(
			'En %s años de catálogo: %s. Son promedios de lo ya ocurrido, no un calendario: los sismos no siguen turnos, y que haya pasado el intervalo medio no hace más probable el siguiente, ni haber tenido uno recientemente lo hace menos probable.',
			self::num( $resumen['anios'], 1 ),
			self::lista( $frases )
		);
	}

	/**
	 * Lectura del ajuste de Gutenberg-Richter en lenguaje claro.
	 *
	 * @param array $gr Salida de SIS_Estadistica::gutenberg_richter().
	 * @return string
	 */
	public static function gutenberg( $gr ) {
		if ( empty( $gr['valido'] ) ) {
			return 'El catálogo disponible no alcanza para ajustar con solvencia la ley de Gutenberg-Richter.';
		}

		$interpretacion = 'un reparto de tamaños típico de una zona tectónicamente activa';
		if ( $gr['b'] < 0.8 ) {
			$interpretacion = 'una proporción de sismos grandes mayor de lo habitual (b bajo)';
		} elseif ( $gr['b'] > 1.2 ) {
			$interpretacion = 'un predominio de sismos pequeños sobre los grandes (b alto)';
		}

		return sprintf(
			'Por cada sismo de magnitud M se registran unas %s veces más de magnitud M−1: el valor b vale %s ± %s, %s. El catálogo es completo a partir de magnitud %s, y sobre ese umbral hay %s sismos en %s años, es decir %s por año.',
			self::num( pow( 10, $gr['b'] ), 1 ),
			self::num( $gr['b'], 2 ),
			self::num( $gr['b_error'], 2 ),
			$interpretacion,
			self::num( $gr['mc'], 1 ),
			self::num( $gr['n'] ),
			self::num( $gr['anios'], 1 ),
			self::num( $gr['tasa_mc'], 1 )
		);
	}

	/**
	 * Convierte un nombre en mayúsculas a formato título (tolerante a tildes).
	 *
	 * @param string $s Texto.
	 * @return string
	 */
	public static function titulo( $s ) {
		$s = (string) $s;
		if ( function_exists( 'mb_convert_case' ) ) {
			return mb_convert_case( mb_strtolower( $s, 'UTF-8' ), MB_CASE_TITLE, 'UTF-8' );
		}
		return ucwords( strtolower( $s ) );
	}

	/**
	 * Advertencia estándar sobre el alcance de los pronósticos.
	 *
	 * @return string
	 */
	public static function advertencia() {
		return 'Ningún método científico predice hoy la fecha, el lugar y la magnitud de un sismo, y no se espera lograrlo en el futuro previsible. Lo que aquí se publica son estadísticas de lo ya ocurrido, calculadas sobre el catálogo del USGS: sirven para entender la amenaza y para prepararse, no para anticipar un evento. La autoridad técnica es el Servicio Geológico Colombiano; el manejo de la emergencia, la UNGRD y el sistema departamental de gestión del riesgo.';
	}
}
