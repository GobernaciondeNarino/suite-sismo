<?php
/**
 * Estadística sismológica: completitud, ley de Gutenberg-Richter, tasas,
 * periodos de retorno y utilidades numéricas.
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
 *  · Probabilidad de ocurrencia — proceso de Poisson homogéneo.
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
	 * Periodo de retorno (años) de un evento con M ≥ m.
	 *
	 * @param float $tasa_anual Tasa anual.
	 * @return float Años (INF si la tasa es nula).
	 */
	public static function periodo_retorno( $tasa_anual ) {
		$t = (float) $tasa_anual;
		return $t > 0 ? ( 1.0 / $t ) : INF;
	}

	/**
	 * Probabilidad de al menos un evento en un intervalo (Poisson):
	 * P = 1 − e^(−λ·t).
	 *
	 * @param float $lambda Tasa (mismas unidades que t).
	 * @param float $t      Duración del intervalo.
	 * @return float 0..1
	 */
	public static function probabilidad_poisson( $lambda, $t = 1.0 ) {
		$x = (float) $lambda * (float) $t;
		if ( $x <= 0 ) {
			return 0.0;
		}
		return 1.0 - exp( -1 * $x );
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

	/**
	 * Función de distribución acumulada de la normal estándar N(0,1).
	 * Aproximación de Abramowitz & Stegun 7.1.26 (error < 7,5·10⁻⁸).
	 *
	 * @param float $z Valor tipificado.
	 * @return float 0..1
	 */
	public static function cdf_normal( $z ) {
		$z   = (float) $z;
		$x   = abs( $z ) / sqrt( 2.0 );
		$t   = 1.0 / ( 1.0 + 0.3275911 * $x );
		$y   = 1.0 - ( ( ( ( ( 1.061405429 * $t - 1.453152027 ) * $t ) + 1.421413741 ) * $t - 0.284496736 ) * $t + 0.254829592 ) * $t * exp( - $x * $x );
		$erf = ( $z >= 0 ) ? $y : -$y;
		return 0.5 * ( 1.0 + $erf );
	}

	/**
	 * Cuantil de la normal estándar (inversa de la CDF).
	 * Aproximación racional de Acklam / Moro, error < 1,15·10⁻⁹.
	 *
	 * @param float $p Probabilidad (0,1).
	 * @return float z
	 */
	public static function cuantil_normal( $p ) {
		$p = (float) $p;
		if ( $p <= 0.0 ) {
			return -INF;
		}
		if ( $p >= 1.0 ) {
			return INF;
		}

		$a = array( -3.969683028665376e+01, 2.209460984245205e+02, -2.759285104469687e+02, 1.383577518672690e+02, -3.066479806614716e+01, 2.506628277459239e+00 );
		$b = array( -5.447609879822406e+01, 1.615858368580409e+02, -1.556989798598866e+02, 6.680131188771972e+01, -1.328068155288572e+01 );
		$c = array( -7.784894002430293e-03, -3.223964580411365e-01, -2.400758277161838e+00, -2.549732539343734e+00, 4.374664141464968e+00, 2.938163982698783e+00 );
		$d = array( 7.784695709041462e-03, 3.224671290700398e-01, 2.445134137142996e+00, 3.754408661907416e+00 );

		$plow  = 0.02425;
		$phigh = 1 - $plow;

		if ( $p < $plow ) {
			$q = sqrt( -2 * log( $p ) );
			return ( ( ( ( ( $c[0] * $q + $c[1] ) * $q + $c[2] ) * $q + $c[3] ) * $q + $c[4] ) * $q + $c[5] ) /
				( ( ( ( $d[0] * $q + $d[1] ) * $q + $d[2] ) * $q + $d[3] ) * $q + 1 );
		}
		if ( $p > $phigh ) {
			$q = sqrt( -2 * log( 1 - $p ) );
			return -( ( ( ( ( $c[0] * $q + $c[1] ) * $q + $c[2] ) * $q + $c[3] ) * $q + $c[4] ) * $q + $c[5] ) /
				( ( ( ( $d[0] * $q + $d[1] ) * $q + $d[2] ) * $q + $d[3] ) * $q + 1 );
		}

		$q = $p - 0.5;
		$r = $q * $q;
		return ( ( ( ( ( $a[0] * $r + $a[1] ) * $r + $a[2] ) * $r + $a[3] ) * $r + $a[4] ) * $r + $a[5] ) * $q /
			( ( ( ( ( $b[0] * $r + $b[1] ) * $r + $b[2] ) * $r + $b[3] ) * $r + $b[4] ) * $r + 1 );
	}

	/**
	 * Cuantil de la chi-cuadrado por la aproximación de Wilson-Hilferty.
	 * Se usa para construir intervalos exactos de Poisson.
	 *
	 * @param float $p  Probabilidad acumulada (0,1).
	 * @param float $gl Grados de libertad.
	 * @return float
	 */
	public static function cuantil_chi2( $p, $gl ) {
		$gl = (float) $gl;
		if ( $gl <= 0 ) {
			return 0.0;
		}
		$z = self::cuantil_normal( $p );
		$x = 1.0 - ( 2.0 / ( 9.0 * $gl ) ) + $z * sqrt( 2.0 / ( 9.0 * $gl ) );
		return max( 0.0, $gl * $x * $x * $x );
	}

	/**
	 * Intervalo de predicción de una Poisson de media λ.
	 *
	 * Usa la relación exacta entre Poisson y chi-cuadrado:
	 *   límite inferior = ½·χ²(α/2; 2λ)      límite superior = ½·χ²(1−α/2; 2λ+2)
	 *
	 * @param float $lambda   Media esperada.
	 * @param float $confianza Nivel de confianza (0..1), por defecto 0,90.
	 * @return array {min, max}
	 */
	public static function intervalo_poisson( $lambda, $confianza = 0.90 ) {
		$lambda = max( 0.0, (float) $lambda );
		$alfa   = 1.0 - max( 0.5, min( 0.999, (float) $confianza ) );

		if ( $lambda <= 0.0 ) {
			return array( 'min' => 0.0, 'max' => 0.0 );
		}

		$min = 0.5 * self::cuantil_chi2( $alfa / 2, 2 * $lambda );
		$max = 0.5 * self::cuantil_chi2( 1 - ( $alfa / 2 ), 2 * $lambda + 2 );

		return array(
			'min' => round( max( 0.0, $min ), 2 ),
			'max' => round( max( $min, $max ), 2 ),
		);
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

		$umbrales = array();
		if ( $gr['valido'] ) {
			foreach ( $o['umbrales'] as $m ) {
				$tasa = self::tasa_anual( $gr['a'], $gr['b'], (float) $m );
				$umbrales[] = array(
					'magnitud'        => (float) $m,
					'tasa_anual'      => round( $tasa, 4 ),
					'periodo_retorno' => is_finite( self::periodo_retorno( $tasa ) ) ? round( self::periodo_retorno( $tasa ), 1 ) : null,
					'prob_1_anio'     => round( 100 * self::probabilidad_poisson( $tasa, 1.0 ), 1 ),
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
