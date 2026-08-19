<?php
/**
 * Pronóstico sísmico probabilístico a 6 meses.
 *
 * NO predice sismos concretos (nadie puede hacerlo). Lo que estima es la
 * TASA ESPERADA de actividad y la PROBABILIDAD de superar ciertos umbrales de
 * magnitud en la ventana de los próximos seis meses, con su banda de
 * incertidumbre. El resultado se recalcula con cada actualización del catálogo
 * y varía con ella: cambia el estado reciente (tendencia amortiguada), cambia
 * el ajuste de Gutenberg-Richter y, si acaba de ocurrir un sismo importante,
 * entra en juego la componente de réplicas.
 *
 * Modelo (tres componentes que se suman en la tasa mensual λ):
 *
 *  1. FONDO CLIMATOLÓGICO — tasa anual de la ley de Gutenberg-Richter ajustada
 *     sobre toda la ventana completa del catálogo, repartida por mes.
 *  2. ESTADO RECIENTE — suavizado exponencial con tendencia amortiguada (Holt
 *     amortiguado) sobre los conteos mensuales recientes. Su peso decae con el
 *     horizonte (w_h = w₀·φ^h): manda en el mes 1 y se disuelve hacia el mes 6,
 *     donde vuelve a mandar la climatología. Es la reversión a la media.
 *  3. RÉPLICAS — ley de Omori-Utsu modificada con productividad de tipo
 *     Reasenberg & Jones (1989). Solo se activa si hubo un sismo relevante en
 *     los últimos 365 días; decae con el tiempo, por lo que el pronóstico baja
 *     solo a medida que la secuencia se apaga.
 *
 * De la tasa esperada se derivan, con Poisson y Gutenberg-Richter truncada,
 * la probabilidad por umbral de magnitud, el periodo de retorno, la magnitud
 * máxima esperada y la energía esperada.
 *
 * Todo es determinista: mismo catálogo → mismo pronóstico (reproducible).
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Forecast {

	/** Horizonte del pronóstico, en meses. */
	const HORIZONTE = 6;

	/** Magnitud máxima creíble del dominio (truncamiento de Gutenberg-Richter).
	 *  Referencia: terremoto de Esmeraldas–Tumaco de 1906 (Mw≈8,8), el mayor
	 *  registrado en la zona de subducción que enfrenta a Nariño. */
	const M_MAX_CREIBLE = 8.8;

	/** Parámetros genéricos de la secuencia de réplicas (Reasenberg & Jones). */
	const RJ_A = -1.67;
	const RJ_P = 1.08;
	const RJ_C = 0.05;   // días

	/* ================================================================= */
	/* Punto de entrada con caché                                        */
	/* ================================================================= */

	/**
	 * Pronóstico vigente de un ámbito (con caché invalidada por los datos).
	 *
	 * La clave de caché incluye la firma del catálogo (nº de eventos + último
	 * id + última marca de tiempo): en cuanto llega un sismo nuevo, la firma
	 * cambia, la caché falla y el pronóstico se recalcula. Es el mecanismo por
	 * el que «el pronóstico se actualiza con los datos» sin intervención.
	 *
	 * @param string $ambito Slug del ámbito.
	 * @param array  $opts   Opciones del modelo.
	 * @return array
	 */
	public static function obtener( $ambito = '', $opts = array() ) {
		if ( '' === $ambito ) {
			$cfg    = self::opciones_modelo();
			$ambito = $cfg['ambito'];
		}
		$ambito   = SIS_Security::sanitizar_ambito( $ambito );
		$catalogo = SIS_Catalogo::obtener( $ambito );
		$eventos  = $catalogo['eventos'];

		$firma = self::firma( $eventos );
		$clave = 'pronostico_' . $ambito . '_' . $firma;

		$cacheado = SIS_Cache::get( $clave );
		if ( is_array( $cacheado ) && ! empty( $cacheado['meses'] ) ) {
			return $cacheado;
		}

		$previo = SIS_Cache::get_durable( 'pronostico_previo_' . $ambito );
		$fin = ! empty( $catalogo['actualizado'] ) ? (int) strtotime( $catalogo['actualizado'] . ' UTC' ) : 0;
		$p   = self::pronostico(
			$eventos,
			array_merge(
				self::opciones_modelo(),
				array( 'ambito' => $ambito, 'fin_cobertura' => $fin ),
				$opts
			)
		);

		$p['catalogo'] = array(
			'origen'      => $catalogo['origen'],
			'fuente'      => $catalogo['fuente'],
			'actualizado' => $catalogo['actualizado'],
			'firma'       => $firma,
		);
		$p['comparacion'] = self::comparar( $p, is_array( $previo ) ? $previo : null );

		// 12 h de vida: si el catálogo cambia antes, la firma ya lo invalida.
		SIS_Cache::set( $clave, $p, 12 * 3600, 'pronostico' );
		SIS_Cache::set( 'pronostico_previo_' . $ambito, $p, 180 * 86400, 'pronostico_previo' );

		return $p;
	}

	/**
	 * Firma corta del catálogo: cambia en cuanto cambian los datos.
	 *
	 * @param array[] $eventos Eventos ordenados.
	 * @return string
	 */
	public static function firma( array $eventos ) {
		$n = count( $eventos );
		if ( 0 === $n ) {
			return 'vacio';
		}
		$ultimo = $eventos[ $n - 1 ];
		return substr( md5( $n . '|' . $ultimo['id'] . '|' . $ultimo['ts'] ), 0, 12 );
	}

	/**
	 * Parámetros por defecto del modelo.
	 *
	 * Viven aquí (y no en el activador) porque son parte del método: el
	 * activador se limita a sembrarlos como opción editable desde el panel.
	 *
	 * @return array
	 */
	public static function modelo_por_defecto() {
		return array(
			'ambito'          => SIS_Regiones::por_defecto(),
			'horizonte'       => self::HORIZONTE,
			'confianza'       => 0.90,
			'alfa'            => 0.35,   // suavizado del nivel (Holt)
			'beta'            => 0.12,   // suavizado de la tendencia
			'phi'             => 0.85,   // amortiguamiento de la tendencia
			'peso0'           => 0.70,   // peso inicial del estado reciente
			'phi_peso'        => 0.75,   // decaimiento del peso con el horizonte
			'meses_recientes' => 60,     // cola de la serie mensual usada
			'umbrales'        => '5.0, 5.5, 6.0, 6.5, 7.0',
		);
	}

	/**
	 * Parámetros del modelo configurados en el panel (opción sis_modelo),
	 * saneados y acotados a rangos con sentido estadístico.
	 *
	 * @return array
	 */
	public static function opciones_modelo() {
		$def = self::modelo_por_defecto();
		$cfg = function_exists( 'get_option' ) ? get_option( 'sis_modelo', array() ) : array();
		$cfg = wp_parse_args( is_array( $cfg ) ? $cfg : array(), $def );

		$acotar = static function ( $v, $min, $max, $def ) {
			$v = is_numeric( $v ) ? (float) $v : (float) $def;
			return max( $min, min( $max, $v ) );
		};

		// Los umbrales llegan como texto editable: «5.0, 5.5, 6.0».
		$umbrales = array();
		foreach ( preg_split( '/[,;\s]+/', (string) $cfg['umbrales'] ) as $u ) {
			if ( is_numeric( $u ) ) {
				$umbrales[] = round( (float) $u, 1 );
			}
		}
		if ( empty( $umbrales ) ) {
			$umbrales = array( 5.0, 5.5, 6.0, 6.5, 7.0 );
		}
		sort( $umbrales );

		return array(
			'ambito'          => SIS_Security::sanitizar_ambito( $cfg['ambito'] ),
			'horizonte'       => (int) $acotar( $cfg['horizonte'], 1, 24, 6 ),
			'confianza'       => $acotar( $cfg['confianza'], 0.5, 0.99, 0.90 ),
			'alfa'            => $acotar( $cfg['alfa'], 0.01, 1.0, 0.35 ),
			'beta'            => $acotar( $cfg['beta'], 0.0, 1.0, 0.12 ),
			'phi'             => $acotar( $cfg['phi'], 0.1, 1.0, 0.85 ),
			'peso0'           => $acotar( $cfg['peso0'], 0.0, 1.0, 0.70 ),
			'phi_peso'        => $acotar( $cfg['phi_peso'], 0.1, 1.0, 0.75 ),
			'meses_recientes' => (int) $acotar( $cfg['meses_recientes'], 12, 600, 60 ),
			'umbrales'        => array_values( array_unique( $umbrales ) ),
		);
	}

	/* ================================================================= */
	/* Modelo                                                            */
	/* ================================================================= */

	/**
	 * Calcula el pronóstico a 6 meses sobre un catálogo dado.
	 *
	 * @param array[] $eventos Eventos normalizados y ordenados.
	 * @param array   $opts    {horizonte, confianza, umbrales, ambito,
	 *                          alfa, beta, phi, peso0, phi_peso, meses_recientes}.
	 * @return array
	 */
	public static function pronostico( array $eventos, $opts = array() ) {
		$o = array_merge(
			array(
				'horizonte'       => self::HORIZONTE,
				'confianza'       => 0.90,
				'umbrales'        => array( 4.5, 5.0, 5.5, 6.0, 6.5, 7.0 ),
				'ambito'          => SIS_Regiones::por_defecto(),
				'alfa'            => 0.35,  // suavizado del nivel
				'beta'            => 0.12,  // suavizado de la tendencia
				'phi'             => 0.85,  // amortiguamiento de la tendencia
				'peso0'           => 0.70,  // peso inicial del estado reciente
				'phi_peso'        => 0.75,  // decaimiento del peso con el horizonte
				'meses_recientes' => 60,    // cola de la serie mensual usada
				'fin_cobertura'   => 0,     // hasta cuándo se sabe que el catálogo está al día
			),
			$opts
		);

		$h  = max( 1, min( 24, (int) $o['horizonte'] ) );
		$gr = SIS_Estadistica::gutenberg_richter( $eventos );

		if ( ! $gr['valido'] ) {
			return self::vacio( $o, $gr );
		}

		$mc = (float) $gr['mc'];
		$b  = (float) $gr['b'];

		// Catálogo completo (M ≥ Mc): es el único estadísticamente utilizable.
		$completos = SIS_Catalogo::filtrar( $eventos, array( 'min_mag' => $mc ) );
		$serie     = SIS_Catalogo::conteo_mensual( $completos, (int) $o['meses_recientes'] );

		if ( count( $serie ) < 12 ) {
			return self::vacio( $o, $gr );
		}

		$ultimo_ts = $completos ? (int) $completos[ count( $completos ) - 1 ]['ts'] : time();

		// El mes base es el último mes COMPLETO que el catálogo cubre, no el
		// último mes con sismos: los meses en calma son observaciones (ceros)
		// y deben entrar en la serie, o el pronóstico arrancaría en el pasado.
		$serie     = self::rellenar_hasta( $serie, self::mes_base( $eventos, (int) $o['fin_cobertura'] ), (int) $o['meses_recientes'] );
		$meses_obs = array_keys( $serie );
		$valores   = array_values( $serie );
		$mes_base  = end( $meses_obs );

		// 1) Fondo climatológico.
		$lambda_base = (float) $gr['tasa_mc'] / 12.0;

		// 2) Estado reciente (Holt amortiguado).
		$holt = self::holt_amortiguado( $valores, $h, $o['alfa'], $o['beta'], $o['phi'] );

		// 3) Réplicas de un sismo relevante reciente.
		$replicas = self::componente_replicas( $eventos, $mc, $b, $mes_base, $h );

		$meses      = array();
		$total_base = 0.0;
		$total_rec  = 0.0;
		$total_rep  = 0.0;

		for ( $i = 1; $i <= $h; $i++ ) {
			$mes  = SIS_Catalogo::sumar_meses( $mes_base, $i );
			$peso = (float) $o['peso0'] * pow( (float) $o['phi_peso'], $i - 1 );
			$peso = max( 0.0, min( 1.0, $peso ) );

			// Suelo del estado reciente: una racha en calma baja la expectativa,
			// pero una tasa exactamente nula no es creíble en una zona activa.
			$rec = max( 0.0, isset( $holt[ $i - 1 ] ) ? (float) $holt[ $i - 1 ] : $lambda_base );
			$rec = max( $rec, 0.10 * $lambda_base );
			$rep = isset( $replicas['meses'][ $i - 1 ] ) ? (float) $replicas['meses'][ $i - 1 ] : 0.0;

			$lambda = ( $peso * $rec ) + ( ( 1 - $peso ) * $lambda_base ) + $rep;
			$lambda = max( 0.0, $lambda );

			$banda = SIS_Estadistica::intervalo_poisson( $lambda, $o['confianza'] );

			// Probabilidad mensual de al menos un sismo M≥5,5 (umbral sensible
			// para la población: es donde empiezan los daños en la región).
			$lambda_55 = $lambda * pow( 10, -1 * $b * ( 5.5 - $mc ) );

			$meses[] = array(
				'mes'          => $mes,
				'mes_legible'  => SIS_Catalogo::mes_legible( $mes ),
				'horizonte'    => $i,
				'esperados'    => round( $lambda, 2 ),
				'banda_min'    => $banda['min'],
				'banda_max'    => $banda['max'],
				'peso_reciente'=> round( $peso, 3 ),
				'fondo'        => round( $lambda_base, 3 ),
				'reciente'     => round( $rec, 3 ),
				'replicas'     => round( $rep, 3 ),
				'prob_m55'     => round( 100 * SIS_Estadistica::probabilidad_poisson( $lambda_55, 1.0 ), 1 ),
			);

			$total_base += ( 1 - $peso ) * $lambda_base;
			$total_rec  += $peso * $rec;
			$total_rep  += $rep;
		}

		$n_total = $total_base + $total_rec + $total_rep;
		$banda_t = SIS_Estadistica::intervalo_poisson( $n_total, $o['confianza'] );

		// Probabilidad por umbral de magnitud (Gutenberg-Richter truncada).
		// El primer umbral es siempre Mc: es la tasa que el catálogo mide de
		// verdad, y sirve de referencia para leer las demás.
		$lista_umbrales = array( $mc );
		foreach ( $o['umbrales'] as $m ) {
			if ( (float) $m > $mc + 1e-9 ) {
				$lista_umbrales[] = (float) $m;
			}
		}
		$lista_umbrales = array_values( array_unique( $lista_umbrales ) );
		sort( $lista_umbrales );

		$umbrales = array();
		foreach ( $lista_umbrales as $m ) {
			$m = (float) $m;
			if ( $m < $mc ) {
				continue;
			}
			$n_m  = $n_total * self::proporcion_sobre( $m, $mc, $b );
			$prob = SIS_Estadistica::probabilidad_poisson( $n_m, 1.0 );
			$tasa_anual = $n_m * ( 12.0 / $h );

			$umbrales[] = array(
				'magnitud'        => $m,
				'clase'           => SIS_Regiones::clasificar_magnitud( $m ),
				'esperados_6m'    => round( $n_m, 3 ),
				'probabilidad'    => round( 100 * $prob, 1 ),
				'tasa_anual'      => round( $tasa_anual, 3 ),
				'periodo_retorno' => $tasa_anual > 0 ? round( 1 / $tasa_anual, 1 ) : null,
			);
		}

		$mmax = self::magnitud_maxima_esperada( $n_total, $mc, $b );

		$resultado = array(
			'ambito'      => $o['ambito'],
			'generado'    => gmdate( 'c' ),
			'horizonte'   => $h,
			'ventana'     => array(
				'desde' => SIS_Catalogo::sumar_meses( $mes_base, 1 ),
				'hasta' => SIS_Catalogo::sumar_meses( $mes_base, $h ),
			),
			'base'        => array(
				'mes'             => $mes_base,
				'ultimo_evento'   => gmdate( 'Y-m-d H:i', $ultimo_ts ),
				'n_catalogo'      => count( $eventos ),
				'n_completos'     => count( $completos ),
				'meses_serie'     => count( $serie ),
				'mc'              => $mc,
				'b'               => $b,
				'b_error'         => $gr['b_error'],
				'a'               => $gr['a'],
				'tasa_anual_mc'   => $gr['tasa_mc'],
				'anios_catalogo'  => $gr['anios'],
			),
			'meses'       => $meses,
			'total'       => array(
				'esperados'  => round( $n_total, 2 ),
				'banda_min'  => $banda_t['min'],
				'banda_max'  => $banda_t['max'],
				'confianza'  => (float) $o['confianza'],
				'aporte'     => array(
					'fondo'    => round( $total_base, 2 ),
					'reciente' => round( $total_rec, 2 ),
					'replicas' => round( $total_rep, 2 ),
				),
			),
			'umbrales'    => $umbrales,
			'magnitud_maxima' => $mmax,
			'energia'     => self::energia_esperada( $n_total, $mc, $b ),
			'replicas'    => $replicas['info'],
			'observado'   => self::serie_observada( $serie ),
			'metodo'      => self::metodo( $o, $gr ),
			'limitaciones'=> 'Estas cifras son una estimación estadística de la TASA de sismicidad, no la predicción de un sismo concreto: la sismología no permite anticipar fecha, hora ni lugar exactos. Los avisos oficiales corresponden al Servicio Geológico Colombiano (SGC) y a la UNGRD.',
		);

		return $resultado;
	}

	/**
	 * Último mes COMPLETO cubierto por el catálogo.
	 *
	 * Se toma la cobertura efectiva —el más reciente entre el último sismo
	 * registrado y la marca de la última sincronización— y se retrocede al mes
	 * anterior si ese mes todavía está en curso: un mes a medias no es una
	 * observación comparable con los demás.
	 *
	 * @param array[] $eventos       Catálogo completo.
	 * @param int     $fin_cobertura Marca de tiempo de la última sincronización (0 = desconocida).
	 * @return string AAAA-MM
	 */
	public static function mes_base( array $eventos, $fin_cobertura = 0 ) {
		$fin = (int) $fin_cobertura;
		if ( $eventos ) {
			$fin = max( $fin, (int) $eventos[ count( $eventos ) - 1 ]['ts'] );
		}
		if ( $fin <= 0 ) {
			$fin = time();
		}
		$fin = min( $fin, time() ); // nunca se pronostica desde el futuro.

		$dia         = (int) gmdate( 'j', $fin );
		$dias_del_mes = (int) gmdate( 't', $fin );
		$mes         = gmdate( 'Y-m', $fin );

		return ( $dia >= $dias_del_mes ) ? $mes : SIS_Catalogo::sumar_meses( $mes, -1 );
	}

	/**
	 * Rellena con ceros la serie mensual hasta el mes indicado.
	 *
	 * @param array<string,int> $serie Serie mes → conteo.
	 * @param string            $hasta Mes final AAAA-MM.
	 * @param int               $tope  Nº máximo de meses a conservar.
	 * @return array<string,int>
	 */
	public static function rellenar_hasta( array $serie, $hasta, $tope = 60 ) {
		if ( empty( $serie ) ) {
			return $serie;
		}

		$meses = array_keys( $serie );
		$mes   = end( $meses );
		$n     = 0;

		while ( $mes < $hasta && $n < 120 ) {
			$mes           = SIS_Catalogo::sumar_meses( $mes, 1 );
			$serie[ $mes ] = isset( $serie[ $mes ] ) ? $serie[ $mes ] : 0;
			$n++;
		}

		if ( $tope > 0 && count( $serie ) > $tope ) {
			$serie = array_slice( $serie, -1 * (int) $tope, null, true );
		}

		return $serie;
	}

	/* ================================================================= */
	/* Componentes del modelo                                            */
	/* ================================================================= */

	/**
	 * Suavizado exponencial con tendencia amortiguada (Holt amortiguado).
	 *
	 *   nivel_t     = α·y_t + (1−α)·(nivel_{t−1} + φ·tend_{t−1})
	 *   tendencia_t = β·(nivel_t − nivel_{t−1}) + (1−β)·φ·tend_{t−1}
	 *   ŷ_{t+h}     = nivel_t + (φ + φ² + … + φ^h)·tend_t
	 *
	 * @param float[] $serie Conteos mensuales en orden cronológico.
	 * @param int     $h     Horizonte (meses).
	 * @param float   $alfa  Suavizado del nivel.
	 * @param float   $beta  Suavizado de la tendencia.
	 * @param float   $phi   Amortiguamiento.
	 * @return float[] Predicción para h pasos (no negativa).
	 */
	public static function holt_amortiguado( array $serie, $h, $alfa = 0.35, $beta = 0.12, $phi = 0.85 ) {
		$serie = array_values( array_map( 'floatval', $serie ) );
		$n     = count( $serie );
		if ( 0 === $n ) {
			return array_fill( 0, max( 1, (int) $h ), 0.0 );
		}
		if ( 1 === $n ) {
			return array_fill( 0, max( 1, (int) $h ), max( 0.0, $serie[0] ) );
		}

		$nivel = $serie[0];
		$tend  = $serie[1] - $serie[0];

		for ( $t = 1; $t < $n; $t++ ) {
			$nivel_prev = $nivel;
			$nivel      = $alfa * $serie[ $t ] + ( 1 - $alfa ) * ( $nivel_prev + $phi * $tend );
			$tend       = $beta * ( $nivel - $nivel_prev ) + ( 1 - $beta ) * $phi * $tend;
		}

		$out   = array();
		$acum  = 0.0;
		for ( $i = 1; $i <= max( 1, (int) $h ); $i++ ) {
			$acum += pow( $phi, $i );
			$out[] = max( 0.0, $nivel + $acum * $tend );
		}
		return $out;
	}

	/**
	 * Componente de réplicas: ley de Omori-Utsu modificada con productividad
	 * tipo Reasenberg & Jones.
	 *
	 *   λ(t) = 10^(a + b·(Mm − Mc)) / (t + c)^p     [réplicas por día]
	 *
	 * Se integra analíticamente sobre cada mes futuro. Solo se activa con un
	 * sismo «detonante» M ≥ Mc + 1,0 ocurrido en los últimos 365 días.
	 *
	 * @param array[] $eventos  Catálogo completo.
	 * @param float   $mc       Magnitud de completitud.
	 * @param float   $b        Valor b.
	 * @param string  $mes_base Último mes observado (AAAA-MM).
	 * @param int     $h        Horizonte en meses.
	 * @return array {meses: float[], info: array}
	 */
	public static function componente_replicas( array $eventos, $mc, $b, $mes_base, $h ) {
		$vacio = array(
			'meses' => array_fill( 0, max( 1, (int) $h ), 0.0 ),
			'info'  => array(
				'activo'  => false,
				'evento'  => null,
				'esperados_6m' => 0.0,
				'nota'    => 'Sin secuencia de réplicas activa: no hay sismos detonantes recientes de magnitud suficiente.',
			),
		);

		$umbral_detonante = (float) $mc + 1.0;
		$desde            = time() - ( 365 * 86400 );

		$detonante = null;
		foreach ( $eventos as $e ) {
			if ( $e['ts'] >= $desde && $e['mag'] >= $umbral_detonante ) {
				if ( null === $detonante || $e['mag'] > $detonante['mag'] ) {
					$detonante = $e;
				}
			}
		}
		if ( null === $detonante ) {
			return $vacio;
		}

		$k = pow( 10, self::RJ_A + ( (float) $b * ( $detonante['mag'] - (float) $mc ) ) );
		$p = self::RJ_P;
		$c = self::RJ_C;

		// Días transcurridos desde el detonante hasta el fin del mes base.
		$fin_base = (int) strtotime( $mes_base . '-01 00:00:00 UTC' );
		$fin_base = (int) strtotime( '+1 month', $fin_base );
		$t0       = max( 0.0, ( $fin_base - (int) $detonante['ts'] ) / 86400 );

		$meses = array();
		$suma  = 0.0;
		$t_ini = $t0;
		for ( $i = 1; $i <= max( 1, (int) $h ); $i++ ) {
			$mes    = SIS_Catalogo::sumar_meses( $mes_base, $i );
			$dias   = (int) gmdate( 't', (int) strtotime( $mes . '-01 00:00:00 UTC' ) );
			$t_fin  = $t_ini + $dias;
			$n_mes  = self::integral_omori( $k, $c, $p, $t_ini, $t_fin );
			$meses[] = round( $n_mes, 4 );
			$suma   += $n_mes;
			$t_ini   = $t_fin;
		}

		return array(
			'meses' => $meses,
			'info'  => array(
				'activo'       => true,
				'evento'       => array(
					'id'    => $detonante['id'],
					'fecha' => $detonante['fecha'],
					'mag'   => $detonante['mag'],
					'lugar' => $detonante['lugar'],
				),
				'dias_transcurridos' => round( $t0, 1 ),
				'parametros'   => array( 'K' => round( $k, 4 ), 'p' => $p, 'c' => $c ),
				'esperados_6m' => round( $suma, 2 ),
				'nota'         => 'Secuencia activa tras el sismo de magnitud ' . number_format_i18n( $detonante['mag'], 1 ) . '. La tasa de réplicas decae con el tiempo (ley de Omori-Utsu), por lo que el aporte disminuye mes a mes.',
			),
		);
	}

	/**
	 * Integral analítica de la ley de Omori-Utsu entre dos instantes (días).
	 *
	 *   ∫ K/(t+c)^p dt = K·[(t+c)^(1−p)]/(1−p)      (p ≠ 1)
	 *
	 * @param float $k  Productividad.
	 * @param float $c  Constante temporal (días).
	 * @param float $p  Exponente de decaimiento.
	 * @param float $t1 Inicio (días desde el detonante).
	 * @param float $t2 Fin (días).
	 * @return float Nº esperado de réplicas M ≥ Mc en el intervalo.
	 */
	public static function integral_omori( $k, $c, $p, $t1, $t2 ) {
		$t1 = max( 0.0, (float) $t1 );
		$t2 = max( $t1, (float) $t2 );
		if ( abs( (float) $p - 1.0 ) < 1e-6 ) {
			return (float) $k * ( log( $t2 + $c ) - log( $t1 + $c ) );
		}
		$e = 1.0 - (float) $p;
		return (float) $k * ( pow( $t2 + $c, $e ) - pow( $t1 + $c, $e ) ) / $e;
	}

	/**
	 * Proporción de eventos con M ≥ m dentro del total con M ≥ Mc, según
	 * Gutenberg-Richter TRUNCADA en la magnitud máxima creíble del dominio.
	 *
	 * Sin truncar, la ley asigna probabilidad no nula a magnitudes físicamente
	 * imposibles; el truncamiento respeta el tamaño máximo de ruptura que la
	 * zona de subducción puede producir.
	 *
	 * @param float $m  Magnitud umbral.
	 * @param float $mc Magnitud de completitud.
	 * @param float $b  Valor b.
	 * @return float 0..1
	 */
	public static function proporcion_sobre( $m, $mc, $b ) {
		$m    = (float) $m;
		$mc   = (float) $mc;
		$b    = (float) $b;
		$mmax = self::M_MAX_CREIBLE;

		if ( $b <= 0 || $m <= $mc ) {
			return 1.0;
		}
		if ( $m >= $mmax ) {
			return 0.0;
		}

		$num = pow( 10, -1 * $b * ( $m - $mc ) ) - pow( 10, -1 * $b * ( $mmax - $mc ) );
		$den = 1.0 - pow( 10, -1 * $b * ( $mmax - $mc ) );

		return $den > 0 ? max( 0.0, min( 1.0, $num / $den ) ) : 0.0;
	}

	/**
	 * Magnitud máxima esperada en la ventana: moda y percentiles de la
	 * distribución del máximo (valor extremo de una Poisson-GR).
	 *
	 *   P(Mmax < m) = exp( −N·P(M ≥ m) )
	 *
	 * @param float $n_total Nº esperado de eventos M ≥ Mc en la ventana.
	 * @param float $mc      Magnitud de completitud.
	 * @param float $b       Valor b.
	 * @return array {modal, p50, p90, prob_supera_modal}
	 */
	public static function magnitud_maxima_esperada( $n_total, $mc, $b ) {
		$n = (float) $n_total;
		$b = (float) $b;
		if ( $n <= 0 || $b <= 0 ) {
			return array( 'modal' => null, 'p50' => null, 'p90' => null );
		}

		// m tal que N·P(M≥m) = q  →  se resuelve por bisección sobre la GR truncada.
		$resolver = function ( $q ) use ( $n, $mc, $b ) {
			$lo = (float) $mc;
			$hi = self::M_MAX_CREIBLE;
			for ( $i = 0; $i < 60; $i++ ) {
				$mid = ( $lo + $hi ) / 2;
				$val = $n * self::proporcion_sobre( $mid, $mc, $b );
				if ( $val > $q ) {
					$lo = $mid;
				} else {
					$hi = $mid;
				}
			}
			return round( ( $lo + $hi ) / 2, 1 );
		};

		return array(
			'modal' => $resolver( 1.0 ),               // se espera ~1 evento de este tamaño
			'p50'   => $resolver( -1 * log( 0.5 ) ),   // mediana del máximo
			'p90'   => $resolver( -1 * log( 0.9 ) ),   // superado solo el 10% de las veces
			'nota'  => 'La magnitud modal es la que cabe esperar «una vez» en la ventana; el percentil 90 es el techo que solo se supera en uno de cada diez escenarios.',
		);
	}

	/**
	 * Energía sísmica esperada en la ventana (julios y equivalente TNT).
	 * Se integra la ley GR truncada por intervalos de 0,1 de magnitud.
	 *
	 * @param float $n_total Nº esperado de eventos M ≥ Mc.
	 * @param float $mc      Magnitud de completitud.
	 * @param float $b       Valor b.
	 * @return array {julios, tnt, equivalente}
	 */
	public static function energia_esperada( $n_total, $mc, $b ) {
		$n = (float) $n_total;
		if ( $n <= 0 || (float) $b <= 0 ) {
			return array( 'julios' => 0.0, 'tnt' => 0.0, 'equivalente' => '' );
		}

		$total = 0.0;
		for ( $m = (float) $mc; $m < self::M_MAX_CREIBLE; $m += 0.1 ) {
			$p_bin  = self::proporcion_sobre( $m, $mc, $b ) - self::proporcion_sobre( $m + 0.1, $mc, $b );
			$total += $n * $p_bin * SIS_Catalogo::energia_joules( $m + 0.05 );
		}

		$tnt = SIS_Catalogo::toneladas_tnt( $total );

		return array(
			'julios'      => $total,
			'tnt'         => $tnt,
			'equivalente' => self::equivalente_energia( $tnt ),
		);
	}

	/**
	 * Traduce toneladas de TNT a una referencia comprensible.
	 *
	 * @param float $tnt Toneladas de TNT.
	 * @return string
	 */
	public static function equivalente_energia( $tnt ) {
		$tnt = (float) $tnt;
		if ( $tnt <= 0 ) {
			return '';
		}
		if ( $tnt < 1000 ) {
			return number_format_i18n( $tnt, 0 ) . ' toneladas de TNT';
		}
		if ( $tnt < 15000 ) {
			return number_format_i18n( $tnt / 1000, 1 ) . ' kilotones de TNT';
		}
		// 15 kt ≈ energía de la bomba de Hiroshima (referencia divulgativa habitual).
		return number_format_i18n( $tnt / 15000, 1 ) . ' veces la energía de una bomba de 15 kilotones';
	}

	/* ================================================================= */
	/* Apoyo                                                             */
	/* ================================================================= */

	/**
	 * Serie observada reciente, formateada para graficar junto al pronóstico.
	 *
	 * @param array<string,int> $serie mes → conteo.
	 * @return array[]
	 */
	private static function serie_observada( array $serie ) {
		$out = array();
		foreach ( $serie as $mes => $n ) {
			$out[] = array(
				'mes'         => $mes,
				'mes_legible' => SIS_Catalogo::mes_legible( $mes ),
				'sismos'      => (int) $n,
			);
		}
		return $out;
	}

	/**
	 * Compara el pronóstico nuevo con el anterior para comunicar el cambio.
	 *
	 * @param array      $nuevo   Pronóstico recién calculado.
	 * @param array|null $anterior Pronóstico previo cacheado.
	 * @return array
	 */
	public static function comparar( array $nuevo, $anterior ) {
		if ( ! is_array( $anterior ) || empty( $anterior['total']['esperados'] ) ) {
			return array(
				'hay_anterior' => false,
				'texto'        => 'Primer pronóstico calculado con este catálogo; a partir de ahora cada actualización mostrará cómo cambia.',
			);
		}

		$de  = (float) $anterior['total']['esperados'];
		$a   = (float) $nuevo['total']['esperados'];
		$dif = $a - $de;
		$pct = $de > 0 ? ( 100 * $dif / $de ) : 0.0;

		$p_ant = self::prob_umbral( $anterior, 6.0 );
		$p_new = self::prob_umbral( $nuevo, 6.0 );

		$sentido = 'se mantiene';
		if ( $pct > 5 ) {
			$sentido = 'sube';
		} elseif ( $pct < -5 ) {
			$sentido = 'baja';
		}

		return array(
			'hay_anterior'      => true,
			'anterior_generado' => isset( $anterior['generado'] ) ? $anterior['generado'] : '',
			'anterior_base'     => isset( $anterior['base']['mes'] ) ? $anterior['base']['mes'] : '',
			'delta_esperados'   => round( $dif, 2 ),
			'delta_pct'         => round( $pct, 1 ),
			'delta_prob_m6'     => ( null !== $p_ant && null !== $p_new ) ? round( $p_new - $p_ant, 1 ) : null,
			'sentido'           => $sentido,
			'texto'             => sprintf(
				'Respecto al pronóstico anterior, el número esperado de sismos %s un %s%% (de %s a %s en seis meses).',
				$sentido,
				number_format_i18n( abs( $pct ), 1 ),
				number_format_i18n( $de, 2 ),
				number_format_i18n( $a, 2 )
			),
		);
	}

	/**
	 * Probabilidad publicada para un umbral concreto de un pronóstico.
	 *
	 * @param array $p        Pronóstico.
	 * @param float $magnitud Umbral.
	 * @return float|null Porcentaje.
	 */
	public static function prob_umbral( $p, $magnitud ) {
		if ( empty( $p['umbrales'] ) ) {
			return null;
		}
		foreach ( $p['umbrales'] as $u ) {
			if ( abs( (float) $u['magnitud'] - (float) $magnitud ) < 1e-6 ) {
				return (float) $u['probabilidad'];
			}
		}
		return null;
	}

	/**
	 * Metadatos del método (para el modal «¿Cómo funciona?» y la auditoría).
	 *
	 * @param array $o  Opciones efectivas.
	 * @param array $gr Ajuste de Gutenberg-Richter.
	 * @return array
	 */
	private static function metodo( $o, $gr ) {
		return array(
			'nombre'      => 'Tasa de sismicidad con fondo Gutenberg-Richter, estado reciente amortiguado y réplicas de Omori-Utsu',
			'componentes' => array(
				'fondo'    => sprintf( 'Ley de Gutenberg-Richter ajustada por máxima verosimilitud (Aki, 1965) sobre %s años de catálogo: Mc = %s, b = %s ± %s, a = %s.', number_format_i18n( $gr['anios'], 1 ), number_format_i18n( $gr['mc'], 1 ), number_format_i18n( $gr['b'], 2 ), number_format_i18n( $gr['b_error'], 2 ), number_format_i18n( $gr['a'], 2 ) ),
				'reciente' => sprintf( 'Suavizado exponencial con tendencia amortiguada (α=%s, β=%s, φ=%s) sobre los conteos mensuales; su peso decae con el horizonte (w₀=%s, φ_w=%s) hasta revertir al fondo.', $o['alfa'], $o['beta'], $o['phi'], $o['peso0'], $o['phi_peso'] ),
				'replicas' => sprintf( 'Ley de Omori-Utsu modificada con productividad de Reasenberg & Jones (a=%s, p=%s, c=%s días), activa solo tras un sismo detonante del último año.', self::RJ_A, self::RJ_P, self::RJ_C ),
				'umbrales' => sprintf( 'Probabilidades de Poisson sobre la Gutenberg-Richter truncada en M=%s (mayor ruptura creíble del dominio, referencia 1906).', self::M_MAX_CREIBLE ),
				'banda'    => sprintf( 'Intervalo de predicción de Poisson al %s%% mediante la relación exacta con la chi-cuadrado.', number_format_i18n( 100 * $o['confianza'], 0 ) ),
			),
			'fuente'      => 'USGS Earthquake Hazards Program — FDSN Event Web Service (dominio público)',
			'reproducible'=> 'Determinista: el mismo catálogo produce siempre el mismo pronóstico. Los datos de entrada se publican en la API abierta del plugin.',
		);
	}

	/**
	 * Estructura vacía coherente cuando no hay catálogo suficiente.
	 *
	 * @param array $o  Opciones.
	 * @param array $gr Ajuste GR (posiblemente inválido).
	 * @return array
	 */
	private static function vacio( $o, $gr ) {
		return array(
			'ambito'      => $o['ambito'],
			'generado'    => gmdate( 'c' ),
			'horizonte'   => (int) $o['horizonte'],
			'valido'      => false,
			'meses'       => array(),
			'umbrales'    => array(),
			'total'       => array( 'esperados' => 0, 'banda_min' => 0, 'banda_max' => 0 ),
			'base'        => array( 'mc' => $gr['mc'], 'b' => $gr['b'], 'n_catalogo' => $gr['n'] ),
			'mensaje'     => 'Todavía no hay catálogo suficiente para pronosticar en este ámbito. Sincroniza la fuente USGS FDSN desde Sismos Nariño → Fuentes o amplía la ventana de años.',
			'limitaciones'=> 'El pronóstico exige al menos 12 meses de catálogo completo y 30 sismos por encima de la magnitud de completitud.',
		);
	}
}
