<?php
/**
 * Estadística sismológica RETROSPECTIVA: completitud, ley de Gutenberg-Richter,
 * tasas observadas, intervalos medios de recurrencia y utilidades numéricas.
 *
 * Alcance deliberado: todo lo que se calcula aquí describe lo que YA ocurrió en
 * la ventana consultada. El plugin no estima probabilidades de sismos futuros
 * —eso sería un pronóstico, competencia del Servicio Geológico Colombiano, que
 * hoy no emite ese producto—. La amenaza de largo plazo se consulta en el
 * Modelo Nacional de Amenaza Sísmica del SGC, al que la plataforma enlaza.
 *
 * Todos los métodos son puros y auditables (sin estado, sin aleatoriedad):
 * dado el mismo catálogo devuelven el mismo resultado, de modo que cualquier
 * cifra publicada puede reproducirse con el JSON de datos abiertos.
 *
 * Métodos implementados y su referencia:
 *  · Magnitud de completitud Mc — máxima curvatura (Wiemer & Wyss, 2000) con
 *    la corrección estándar de +0,2 unidades.
 *  · Valor b — estimador de máxima verosimilitud de Aki (1965) con corrección
 *    de discretización (Utsu, 1966) e incertidumbre de Shi & Bolt (1982).
 *  · Valor a — normalizado a tasa anual sobre la ventana observada.
 *  · Intervalo medio de recurrencia — inverso de la tasa anual observada, leído
 *    siempre como promedio del pasado, nunca como calendario del futuro.
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Estadistica {

	/** Paso de discretización de magnitudes del catálogo USGS. */
	const PASO_MAG = 0.1;

	/* ================================================================= */
	/* Completitud del catálogo                                          */
	/* ================================================================= */

	/**
	 * Magnitud de completitud por máxima curvatura (MAXC) + corrección.
	 *
	 * La MAXC toma la magnitud con mayor frecuencia no acumulada (el punto en
	 * que el catálogo deja de registrar todo) y le suma 0,2 para compensar el
	 * sesgo conocido del método.
	 *
	 * @param float[] $magnitudes Magnitudes del catálogo.
	 * @param float   $correccion Corrección aditiva (por defecto 0,2).
	 * @return float Mc redondeada al paso de magnitud.
	 */
	public static function magnitud_completitud( array $magnitudes, $correccion = 0.2 ) {
		if ( count( $magnitudes ) < 20 ) {
			// Muestra pequeña: no hay curva que leer; se usa el mínimo observado.
			return count( $magnitudes ) ? round( min( $magnitudes ), 1 ) : 0.0;
		}

		$hist = array();
		foreach ( $magnitudes as $m ) {
			$b            = (string) number_format( round( (float) $m, 1 ), 1, '.', '' );
			$hist[ $b ]   = isset( $hist[ $b ] ) ? $hist[ $b ] + 1 : 1;
		}

		$maxc = null;
		$n    = -1;
		foreach ( $hist as $mag => $cnt ) {
			if ( $cnt > $n ) {
				$n    = $cnt;
				$maxc = (float) $mag;
			}
		}

		return round( (float) $maxc + (float) $correccion, 1 );
	}

	/* ================================================================= */
	/* Ley de Gutenberg-Richter                                          */
	/* ================================================================= */

	/**
	 * Valor b por máxima verosimilitud (Aki, 1965; corrección de Utsu).
	 *
	 *   b = log10(e) / ( M̄ − (Mc − ΔM/2) )
	 *
	 * @param float[] $magnitudes Magnitudes ≥ Mc.
	 * @param float   $mc         Magnitud de completitud.
	 * @param float   $paso       Paso de discretización ΔM.
	 * @return array {b, error, n, media}
	 */
	public static function valor_b( array $magnitudes, $mc, $paso = self::PASO_MAG ) {
		$sel = array();
		foreach ( $magnitudes as $m ) {
			if ( (float) $m >= (float) $mc - 1e-9 ) {
				$sel[] = (float) $m;
			}
		}

		$n = count( $sel );
		if ( $n < 10 ) {
			return array( 'b' => 0.0, 'error' => 0.0, 'n' => $n, 'media' => 0.0 );
		}

		$media = array_sum( $sel ) / $n;
		$den   = $media - ( (float) $mc - ( (float) $paso / 2 ) );
		if ( $den <= 0.0 ) {
			return array( 'b' => 0.0, 'error' => 0.0, 'n' => $n, 'media' => round( $media, 3 ) );
		}

		$b = log10( M_E ) / $den;

		// Incertidumbre de Shi & Bolt (1982).
		$var = 0.0;
		foreach ( $sel as $m ) {
			$var += ( $m - $media ) * ( $m - $media );
		}
		$error = 0.0;
		if ( $n > 1 ) {
			$error = 2.30 * $b * $b * sqrt( $var / ( $n * ( $n - 1 ) ) );
		}

		return array(
			'b'     => round( $b, 3 ),
			'error' => round( $error, 3 ),
			'n'     => $n,
			'media' => round( $media, 3 ),
		);
	}

	/**
	 * Valor a normalizado a tasa ANUAL:  a = log10(N/años) + b·Mc.
	 *
	 * @param int   $n     Nº de eventos con M ≥ Mc.
	 * @param float $b     Valor b.
	 * @param float $mc    Magnitud de completitud.
	 * @param float $anios Años cubiertos por el catálogo.
	 * @return float
	 */
	public static function valor_a( $n, $b, $mc, $anios ) {
		$n     = (int) $n;
		$anios = (float) $anios;
		if ( $n <= 0 || $anios <= 0 ) {
			return 0.0;
		}
		return round( log10( $n / $anios ) + ( (float) $b * (float) $mc ), 4 );
	}

	/**
	 * Tasa anual esperada de eventos con M ≥ m según Gutenberg-Richter:
	 * log10(N) = a − b·m.
	 *
	 * @param float $a Valor a (anual).
	 * @param float $b Valor b.
	 * @param float $m Magnitud umbral.
	 * @return float Eventos por año.
	 */
	public static function tasa_anual( $a, $b, $m ) {
		return pow( 10, (float) $a - ( (float) $b * (float) $m ) );
	}

	/**
	 * Intervalo medio de recurrencia (años) entre sismos con M ≥ m, calculado
	 * como el inverso de la tasa anual OBSERVADA en la ventana del catálogo.
	 *
	 * Es un promedio del pasado: no implica periodicidad ni plazo de seguridad
	 * tras un evento. Los sismos no llevan cuenta del tiempo transcurrido.
	 *
	 * @param float $tasa_anual Tasa anual observada.
	 * @return float Años (INF si la tasa es nula).
	 */
	public static function intervalo_recurrencia( $tasa_anual ) {
		$t = (float) $tasa_anual;
		return $t > 0 ? ( 1.0 / $t ) : INF;
	}

	/**
	 * Ajuste completo de Gutenberg-Richter sobre un catálogo.
	 *
	 * @param array[] $eventos Eventos normalizados.
	 * @param array   $opts    {mc (float|null), paso}.
	 * @return array {mc, b, b_error, a, n, anios, tasa_mc, curva[]}
	 */
	public static function gutenberg_richter( array $eventos, $opts = array() ) {
		$o = array_merge( array( 'mc' => null, 'paso' => self::PASO_MAG ), $opts );

		$mags = array();
		foreach ( $eventos as $e ) {
			$mags[] = (float) $e['mag'];
		}

		if ( count( $mags ) < 10 ) {
			return array(
				'mc'       => 0.0,
				'b'        => 0.0,
				'b_error'  => 0.0,
				'a'        => 0.0,
				'n'        => count( $mags ),
				'anios'    => 0.0,
				'tasa_mc'  => 0.0,
				'curva'    => array(),
				'valido'   => false,
				'mensaje'  => 'Catálogo insuficiente para ajustar la ley de Gutenberg-Richter (se requieren al menos 10 sismos).',
			);
		}

		$mc = ( null !== $o['mc'] ) ? (float) $o['mc'] : self::magnitud_completitud( $mags );

		// Si Mc deja menos de 30 eventos, se relaja un escalón para no ajustar
		// sobre una muestra ridícula (honestidad estadística).
		$sobre_mc = array_filter( $mags, static function ( $m ) use ( $mc ) {
			return $m >= $mc - 1e-9;
		} );
		while ( count( $sobre_mc ) < 30 && $mc > min( $mags ) ) {
			$mc      -= 0.1;
			$mc       = round( $mc, 1 );
			$sobre_mc = array_filter( $mags, static function ( $m ) use ( $mc ) {
				return $m >= $mc - 1e-9;
			} );
		}

		$eventos_mc = SIS_Catalogo::filtrar( $eventos, array( 'min_mag' => $mc ) );
		$anios      = SIS_Catalogo::anios_cubiertos( $eventos_mc );
		$b          = self::valor_b( $mags, $mc, $o['paso'] );
		$n          = $b['n'];
		$a          = self::valor_a( $n, $b['b'], $mc, $anios );

		return array(
			'mc'      => round( $mc, 1 ),
			'b'       => $b['b'],
			'b_error' => $b['error'],
			'a'       => $a,
			'n'       => $n,
			'anios'   => round( $anios, 2 ),
			'tasa_mc' => $anios > 0 ? round( $n / $anios, 3 ) : 0.0,
			'curva'   => self::curva_gr( $mags, $mc, $a, $b['b'], $anios ),
			'valido'  => ( $n >= 10 && $b['b'] > 0 && $anios > 0 ),
			'mensaje' => '',
		);
	}

	/**
	 * Curva frecuencia-magnitud: nº acumulado observado vs. ajuste GR.
	 *
	 * @param float[] $magnitudes Todas las magnitudes.
	 * @param float   $mc         Magnitud de completitud.
	 * @param float   $a          Valor a (anual).
	 * @param float   $b          Valor b.
	 * @param float   $anios      Años cubiertos.
	 * @return array[] {magnitud, observados, ajuste, tasa_anual}
	 */
	public static function curva_gr( array $magnitudes, $mc, $a, $b, $anios ) {
		if ( empty( $magnitudes ) ) {
			return array();
		}
		$min = floor( min( $magnitudes ) * 10 ) / 10;
		$max = ceil( max( $magnitudes ) * 10 ) / 10;

		$curva = array();
		for ( $m = $min; $m <= $max + 1e-9; $m += 0.1 ) {
			$m   = round( $m, 1 );
			$obs = 0;
			foreach ( $magnitudes as $x ) {
				if ( (float) $x >= $m - 1e-9 ) {
					$obs++;
				}
			}
			if ( 0 === $obs ) {
				continue;
			}
			$tasa    = ( $b > 0 ) ? self::tasa_anual( $a, $b, $m ) : 0.0;
			$curva[] = array(
				'magnitud'   => $m,
				'observados' => $obs,
				'ajuste'     => ( $anios > 0 ) ? round( $tasa * $anios, 2 ) : 0.0,
				'tasa_anual' => round( $tasa, 4 ),
				'completo'   => ( $m >= $mc ) ? 1 : 0,
			);
		}
		return $curva;
	}

	/* ================================================================= */
	/* Utilidades numéricas                                              */
	/* ================================================================= */

	/**
	 * Media aritmética.
	 *
	 * @param float[] $x Valores.
	 * @return float
	 */
	public static function media( array $x ) {
		$n = count( $x );
		return $n ? array_sum( $x ) / $n : 0.0;
	}

	/**
	 * Desviación estándar muestral.
	 *
	 * @param float[] $x Valores.
	 * @return float
	 */
	public static function desviacion( array $x ) {
		$n = count( $x );
		if ( $n < 2 ) {
			return 0.0;
		}
		$m = self::media( $x );
		$s = 0.0;
		foreach ( $x as $v ) {
			$s += ( $v - $m ) * ( $v - $m );
		}
		return sqrt( $s / ( $n - 1 ) );
	}

	/**
	 * Percentil por interpolación lineal (0..100).
	 *
	 * @param float[] $x Valores (se ordenan internamente).
	 * @param float   $p Percentil.
	 * @return float
	 */
	public static function percentil( array $x, $p ) {
		if ( empty( $x ) ) {
			return 0.0;
		}
		sort( $x );
		$n = count( $x );
		if ( 1 === $n ) {
			return (float) $x[0];
		}
		$pos  = ( max( 0.0, min( 100.0, (float) $p ) ) / 100 ) * ( $n - 1 );
		$bajo = (int) floor( $pos );
		$alto = (int) ceil( $pos );
		if ( $bajo === $alto ) {
			return (float) $x[ $bajo ];
		}
		return (float) $x[ $bajo ] + ( $pos - $bajo ) * ( (float) $x[ $alto ] - (float) $x[ $bajo ] );
	}

	/**
	 * Regresión lineal por mínimos cuadrados sobre y (x = 0,1,2,…).
	 *
	 * @param float[] $y Valores en orden cronológico.
	 * @return array {pendiente, intercepto, r2, n}
	 */
	public static function regresion_lineal( array $y ) {
		$y = array_values( array_map( 'floatval', $y ) );
		$n = count( $y );
		if ( $n < 2 ) {
			return array( 'pendiente' => 0.0, 'intercepto' => $n ? $y[0] : 0.0, 'r2' => 0.0, 'n' => $n );
		}

		$sx = 0.0;
		$sy = 0.0;
		$sxx = 0.0;
		$sxy = 0.0;
		for ( $i = 0; $i < $n; $i++ ) {
			$sx  += $i;
			$sy  += $y[ $i ];
			$sxx += $i * $i;
			$sxy += $i * $y[ $i ];
		}
		$den = ( $n * $sxx ) - ( $sx * $sx );
		if ( 0.0 === $den ) {
			return array( 'pendiente' => 0.0, 'intercepto' => $sy / $n, 'r2' => 0.0, 'n' => $n );
		}
		$b = ( ( $n * $sxy ) - ( $sx * $sy ) ) / $den;
		$a = ( $sy - ( $b * $sx ) ) / $n;

		$media  = $sy / $n;
		$ss_tot = 0.0;
		$ss_res = 0.0;
		for ( $i = 0; $i < $n; $i++ ) {
			$est     = $a + ( $b * $i );
			$ss_res += ( $y[ $i ] - $est ) * ( $y[ $i ] - $est );
			$ss_tot += ( $y[ $i ] - $media ) * ( $y[ $i ] - $media );
		}
		$r2 = ( $ss_tot > 0 ) ? max( 0.0, 1.0 - ( $ss_res / $ss_tot ) ) : 0.0;

		return array(
			'pendiente'  => $b,
			'intercepto' => $a,
			'r2'         => round( $r2, 3 ),
			'n'          => $n,
		);
	}

	/**
	 * Media móvil centrada.
	 *
	 * @param float[] $serie   Serie.
	 * @param int     $ventana Ancho de ventana.
	 * @return float[]
	 */
	public static function media_movil( array $serie, $ventana = 3 ) {
		$ventana = max( 1, (int) $ventana );
		$n       = count( $serie );
		$out     = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$ini  = max( 0, $i - intdiv( $ventana, 2 ) );
			$fin  = min( $n - 1, $i + intdiv( $ventana, 2 ) );
			$suma = 0.0;
			$cnt  = 0;
			for ( $j = $ini; $j <= $fin; $j++ ) {
				$suma += (float) $serie[ $j ];
				$cnt++;
			}
			$out[] = $cnt > 0 ? round( $suma / $cnt, 3 ) : 0.0;
		}
		return $out;
	}

	/* ================================================================= */
	/* Resumen estadístico de alto nivel                                 */
	/* ================================================================= */

	/**
	 * Retrato estadístico completo de un catálogo, listo para publicar.
	 *
	 * @param array[] $eventos Eventos normalizados.
	 * @param array   $opts    {umbrales: float[]}.
	 * @return array
	 */
	public static function resumen( array $eventos, $opts = array() ) {
		$o = array_merge( array( 'umbrales' => array( 5.0, 5.5, 6.0, 6.5, 7.0 ) ), $opts );

		$gr    = self::gutenberg_richter( $eventos );
		$mags  = wp_list_pluck( $eventos, 'mag' );
		$profs = wp_list_pluck( $eventos, 'profundidad' );

		// Recurrencia observada por umbral: cuántos ocurrieron de verdad en la
		// ventana, a qué ritmo anual y cada cuánto en promedio. Nada de esto
		// mira hacia adelante.
		$umbrales = array();
		if ( $gr['valido'] ) {
			foreach ( $o['umbrales'] as $m ) {
				$m     = (float) $m;
				$n_obs = count( SIS_Catalogo::filtrar( $eventos, array( 'min_mag' => $m ) ) );
				$tasa  = self::tasa_anual( $gr['a'], $gr['b'], $m );
				$tasa_obs = ( $gr['anios'] > 0 ) ? $n_obs / $gr['anios'] : 0.0;

				$umbrales[] = array(
					'magnitud'         => $m,
					'observados'       => $n_obs,
					'tasa_anual_obs'   => round( $tasa_obs, 4 ),
					'tasa_anual_ajuste'=> round( $tasa, 4 ),
					'intervalo_medio'  => is_finite( self::intervalo_recurrencia( $tasa ) ) ? round( self::intervalo_recurrencia( $tasa ), 1 ) : null,
				);
			}
		}

		$energia_total = 0.0;
		foreach ( $eventos as $e ) {
			$energia_total += isset( $e['energia_j'] ) ? (float) $e['energia_j'] : SIS_Catalogo::energia_joules( $e['mag'] );
		}

		return array(
			'n'              => count( $eventos ),
			'anios'          => $gr['anios'],
			'gutenberg'      => $gr,
			'umbrales'       => $umbrales,
			'magnitud'       => array(
				'min'     => $mags ? round( min( $mags ), 1 ) : 0.0,
				'max'     => $mags ? round( max( $mags ), 1 ) : 0.0,
				'media'   => round( self::media( $mags ), 2 ),
				'mediana' => round( self::percentil( $mags, 50 ), 2 ),
				'p90'     => round( self::percentil( $mags, 90 ), 2 ),
			),
			'profundidad'    => array(
				'min'     => $profs ? round( min( $profs ), 1 ) : 0.0,
				'max'     => $profs ? round( max( $profs ), 1 ) : 0.0,
				'media'   => round( self::media( $profs ), 1 ),
				'mediana' => round( self::percentil( $profs, 50 ), 1 ),
			),
			'energia_julios' => $energia_total,
			'energia_tnt'    => SIS_Catalogo::toneladas_tnt( $energia_total ),
			'generado'       => gmdate( 'c' ),
		);
	}
}
