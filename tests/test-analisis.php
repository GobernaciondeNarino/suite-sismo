<?php
/**
 * Tests CLI de la lógica pura de análisis (catálogo, estadística y pronóstico).
 *
 * No requiere WordPress: define stubs mínimos de las funciones de WP que usan
 * los métodos puros bajo prueba. Ejecutar con:  php tests/test-analisis.php
 *
 * @package SismosNarino
 */

error_reporting( E_ALL & ~E_DEPRECATED );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'SIS_DIR' ) ) {
	define( 'SIS_DIR', dirname( __DIR__ ) . '/' );
}

/* --- Stubs mínimos de WordPress --- */
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) {
		return trim( strip_tags( (string) $s ) );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $s ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $s ) {
		return (string) $s;
	}
}
if ( ! function_exists( 'remove_accents' ) ) {
	function remove_accents( $s ) {
		return strtr(
			(string) $s,
			array( 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n' )
		);
	}
}
if ( ! function_exists( 'wp_list_pluck' ) ) {
	function wp_list_pluck( $lista, $campo ) {
		$out = array();
		foreach ( (array) $lista as $f ) {
			if ( is_array( $f ) && isset( $f[ $campo ] ) ) {
				$out[] = $f[ $campo ];
			}
		}
		return $out;
	}
}
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $n, $dec = 0 ) {
		return number_format( (float) $n, (int) $dec, ',', '.' );
	}
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url ) {
		return parse_url( $url );
	}
}

require SIS_DIR . 'includes/class-sis-security.php';
require SIS_DIR . 'includes/data/class-sis-municipios.php';
require SIS_DIR . 'includes/data/class-sis-regiones.php';
require SIS_DIR . 'includes/analysis/class-sis-catalogo.php';
require SIS_DIR . 'includes/analysis/class-sis-estadistica.php';
require SIS_DIR . 'includes/analysis/class-sis-forecast.php';

use GobernacionNarino\Sismos\SIS_Catalogo;
use GobernacionNarino\Sismos\SIS_Estadistica;
use GobernacionNarino\Sismos\SIS_Forecast;
use GobernacionNarino\Sismos\SIS_Municipios;
use GobernacionNarino\Sismos\SIS_Regiones;
use GobernacionNarino\Sismos\SIS_Security;

$fallos = 0;
function chk( $cond, $msg ) {
	global $fallos;
	if ( $cond ) {
		echo "  ok  $msg\n";
	} else {
		echo "FAIL  $msg\n";
		$fallos++;
	}
}
function seccion( $t ) {
	echo "\n== $t ==\n";
}

/* ------------------------------------------------------------------ */
seccion( 'Municipios y ámbitos' );

chk( 64 === count( SIS_Municipios::todos() ), '64 municipios cargados' );
chk( SIS_Municipios::existe( '52001' ), 'DIVIPOLA 52001 (Pasto) existe' );
$mun = SIS_Municipios::por_nombre( 'tumaco' );
chk( $mun && '52835' === $mun['divipola'], 'Búsqueda por nombre tolerante a mayúsculas/tildes' );

$d = SIS_Municipios::distancia_km( 1.2136, -77.2811, 1.7886, -78.7644 ); // Pasto → Tumaco
chk( $d > 150 && $d < 210, sprintf( 'Distancia Pasto–Tumaco plausible (%.1f km)', $d ) );

chk( SIS_Regiones::contiene( 'narino', 1.21, -77.28 ), 'Pasto cae en el ámbito Nariño' );
chk( ! SIS_Regiones::contiene( 'narino', -0.62, -80.94 ), 'Manta (Ecuador) queda fuera del ámbito Nariño' );
chk( SIS_Regiones::contiene( 'regional', -0.62, -80.94 ), 'Manta sí cae en el ámbito regional' );
chk( SIS_Regiones::contiene( 'radio', 1.21, -77.28 ), 'El ámbito de radio contiene su propio centro' );

$p = SIS_Regiones::parametros_fdsn( 'regional' );
chk( isset( $p['minlatitude'], $p['maxlongitude'] ), 'Ámbito bbox se traduce a parámetros FDSN' );
$p = SIS_Regiones::parametros_fdsn( 'radio' );
chk( isset( $p['maxradiuskm'] ), 'Ámbito circular se traduce a maxradiuskm' );

chk( 'regional' === SIS_Security::sanitizar_ambito( 'inventado' ), 'Ámbito desconocido cae al de por defecto' );
chk( ! SIS_Security::url_permitida( 'https://malicioso.example/x' ), 'Host fuera de la lista blanca rechazado' );
chk( SIS_Security::url_permitida( 'https://earthquake.usgs.gov/fdsnws/event/1/query' ), 'Host del USGS permitido' );
chk( ! SIS_Security::url_permitida( 'http://earthquake.usgs.gov/x' ), 'HTTP sin TLS rechazado' );

/* ------------------------------------------------------------------ */
seccion( 'Normalización del catálogo' );

$crudo = json_decode( file_get_contents( SIS_DIR . 'data/catalogo_regional_semilla.json' ), true );
chk( is_array( $crudo ) && ! empty( $crudo['features'] ), 'Semilla local legible' );

$eventos = SIS_Catalogo::normalizar( $crudo, array( 'ambito' => 'regional' ) );
chk( count( $eventos ) > 300, sprintf( 'Eventos normalizados: %d', count( $eventos ) ) );

$e = $eventos[0];
foreach ( array( 'id', 'ts', 'mes', 'mag', 'profundidad', 'lat', 'lon', 'clase', 'rango_profundidad', 'municipio', 'energia_j' ) as $campo ) {
	chk( array_key_exists( $campo, $e ), "El evento normalizado trae el campo «{$campo}»" );
}
chk( $eventos[0]['ts'] <= $eventos[ count( $eventos ) - 1 ]['ts'], 'Los eventos quedan en orden cronológico' );
chk( '' !== $eventos[0]['municipio'], 'Cada evento se asocia al municipio de Nariño más cercano' );

$dup = SIS_Catalogo::fusionar( $eventos, array_slice( $eventos, 0, 20 ) );
chk( count( $dup ) === count( $eventos ), 'La fusión deduplica por id' );

$filtrados = SIS_Catalogo::filtrar( $eventos, array( 'min_mag' => 5.0 ) );
chk( count( $filtrados ) < count( $eventos ), 'El filtro por magnitud reduce el catálogo' );
$ok = true;
foreach ( $filtrados as $x ) {
	if ( $x['mag'] < 5.0 ) {
		$ok = false;
	}
}
chk( $ok, 'El filtro por magnitud no deja eventos por debajo del umbral' );

$mensual = SIS_Catalogo::conteo_mensual( $eventos );
chk( count( $mensual ) > 100, sprintf( 'Serie mensual continua: %d meses', count( $mensual ) ) );
$claves = array_keys( $mensual );
chk( SIS_Catalogo::sumar_meses( $claves[0], 1 ) === $claves[1], 'La serie mensual rellena los meses sin sismos' );
chk( 'ago 2026' === SIS_Catalogo::mes_legible( '2026-08' ), 'Mes legible en español' );

$energia = SIS_Catalogo::energia_joules( 6.0 );
chk( abs( log10( $energia ) - 13.8 ) < 0.001, 'Energía de un M6,0 según Hanks & Kanamori (10^13,8 J)' );

/* ------------------------------------------------------------------ */
seccion( 'Estadística sismológica' );

$mags = wp_list_pluck( $eventos, 'mag' );
$mc   = SIS_Estadistica::magnitud_completitud( $mags );
chk( $mc >= 4.0 && $mc <= 5.5, sprintf( 'Magnitud de completitud plausible: Mc = %.1f', $mc ) );

$b = SIS_Estadistica::valor_b( $mags, $mc );
chk( $b['b'] > 0.5 && $b['b'] < 1.6, sprintf( 'Valor b en el rango tectónico habitual: b = %.2f ± %.2f', $b['b'], $b['error'] ) );

$gr = SIS_Estadistica::gutenberg_richter( $eventos );
chk( $gr['valido'], 'El ajuste de Gutenberg-Richter es válido' );
chk( $gr['anios'] > 5, sprintf( 'Ventana del catálogo: %.1f años', $gr['anios'] ) );
chk( count( $gr['curva'] ) > 10, 'La curva frecuencia-magnitud tiene puntos suficientes' );

$tasa5 = SIS_Estadistica::tasa_anual( $gr['a'], $gr['b'], 5.0 );
$tasa6 = SIS_Estadistica::tasa_anual( $gr['a'], $gr['b'], 6.0 );
chk( $tasa5 > $tasa6, 'La tasa anual decrece al subir la magnitud' );
chk( abs( $tasa5 / $tasa6 - pow( 10, $gr['b'] ) ) < 1e-6, 'La razón entre tasas equivale a 10^b' );

$p1 = SIS_Estadistica::probabilidad_poisson( 1.0, 1.0 );
chk( abs( $p1 - ( 1 - exp( -1 ) ) ) < 1e-9, 'Poisson: P(al menos uno) con λ=1 es 63,2%' );

$iv = SIS_Estadistica::intervalo_poisson( 10, 0.90 );
chk( $iv['min'] < 10 && $iv['max'] > 10, sprintf( 'Intervalo de Poisson al 90%% contiene la media (%.1f–%.1f)', $iv['min'], $iv['max'] ) );
chk( abs( SIS_Estadistica::cuantil_normal( 0.975 ) - 1.959964 ) < 1e-4, 'Cuantil normal z(0,975) = 1,96' );
chk( abs( SIS_Estadistica::cdf_normal( 1.959964 ) - 0.975 ) < 1e-4, 'CDF normal coherente con su inversa' );

$reg = SIS_Estadistica::regresion_lineal( array( 1, 2, 3, 4, 5 ) );
chk( abs( $reg['pendiente'] - 1.0 ) < 1e-9 && $reg['r2'] > 0.999, 'Regresión lineal exacta sobre una recta' );

$resumen = SIS_Estadistica::resumen( $eventos );
chk( ! empty( $resumen['umbrales'] ), 'El resumen calcula periodos de retorno por umbral' );
chk( $resumen['energia_tnt'] > 0, 'El resumen acumula energía liberada' );

/* ------------------------------------------------------------------ */
seccion( 'Pronóstico a 6 meses' );

$pron = SIS_Forecast::pronostico( $eventos, array( 'ambito' => 'regional' ) );
chk( 6 === count( $pron['meses'] ), 'El pronóstico entrega exactamente 6 meses' );
chk( $pron['ventana']['desde'] === SIS_Catalogo::sumar_meses( $pron['base']['mes'], 1 ), 'La ventana arranca en el mes siguiente al último dato' );

$mono = true;
$prev = '';
foreach ( $pron['meses'] as $m ) {
	if ( '' !== $prev && $m['mes'] <= $prev ) {
		$mono = false;
	}
	$prev = $m['mes'];
	if ( $m['banda_min'] > $m['esperados'] || $m['banda_max'] < $m['esperados'] ) {
		$mono = false;
	}
}
chk( $mono, 'Los meses son consecutivos y la banda encierra el valor esperado' );

chk( $pron['total']['esperados'] > 0, sprintf( 'Sismos esperados en 6 meses: %.2f', $pron['total']['esperados'] ) );
chk( ! empty( $pron['umbrales'] ), 'Hay probabilidades por umbral de magnitud' );

$ordenado = true;
$antes    = 101.0;
foreach ( $pron['umbrales'] as $u ) {
	if ( $u['probabilidad'] > $antes + 1e-9 ) {
		$ordenado = false;
	}
	$antes = $u['probabilidad'];
	if ( $u['probabilidad'] < 0 || $u['probabilidad'] > 100 ) {
		$ordenado = false;
	}
}
chk( $ordenado, 'La probabilidad decrece con la magnitud y queda en 0–100%' );

chk( $pron['magnitud_maxima']['modal'] >= $pron['base']['mc'], 'La magnitud máxima esperada supera la de completitud' );
chk( $pron['magnitud_maxima']['p90'] >= $pron['magnitud_maxima']['p50'], 'El percentil 90 del máximo supera a la mediana' );
chk( $pron['magnitud_maxima']['p90'] <= SIS_Forecast::M_MAX_CREIBLE, 'La magnitud máxima respeta el truncamiento del dominio' );
chk( $pron['energia']['tnt'] > 0 && '' !== $pron['energia']['equivalente'], 'La energía esperada trae equivalente divulgativo' );

// Determinismo: mismo catálogo, mismo pronóstico.
$pron2 = SIS_Forecast::pronostico( $eventos, array( 'ambito' => 'regional' ) );
chk( $pron['total']['esperados'] === $pron2['total']['esperados'], 'El pronóstico es determinista (reproducible)' );

// Sensibilidad: el pronóstico DEBE cambiar cuando cambia el catálogo.
$firma_a = SIS_Forecast::firma( $eventos );
$nuevo   = $eventos;
$ultimo  = end( $nuevo );
$sismo   = $ultimo;
$sismo['id']    = 'test_evento_nuevo';
$sismo['ts']    = $ultimo['ts'] + ( 3 * 86400 );
$sismo['fecha'] = gmdate( 'Y-m-d H:i:s', $sismo['ts'] );
$sismo['mes']   = gmdate( 'Y-m', $sismo['ts'] );
$sismo['anio']  = (int) gmdate( 'Y', $sismo['ts'] );
$sismo['mag']   = 6.8;
$sismo['energia_j'] = SIS_Catalogo::energia_joules( 6.8 );
$nuevo[]        = $sismo;
$nuevo          = SIS_Catalogo::ordenar( $nuevo );

$firma_b = SIS_Forecast::firma( $nuevo );
chk( $firma_a !== $firma_b, 'La firma del catálogo cambia al llegar un sismo nuevo (invalida la caché)' );

$pron3 = SIS_Forecast::pronostico( $nuevo, array( 'ambito' => 'regional' ) );
chk( $pron3['total']['esperados'] !== $pron['total']['esperados'], 'El pronóstico varía cuando cambia el catálogo' );
chk( $pron3['replicas']['activo'], 'Un sismo grande reciente activa la componente de réplicas' );
chk( $pron3['total']['esperados'] > $pron['total']['esperados'], 'Tras un sismo fuerte, la tasa esperada sube' );

$rep = $pron3['meses'];
chk( $rep[0]['replicas'] > $rep[5]['replicas'], 'El aporte de réplicas decae mes a mes (ley de Omori)' );

$cmp = SIS_Forecast::comparar( $pron3, $pron );
chk( $cmp['hay_anterior'] && 'sube' === $cmp['sentido'], 'La comparación con el pronóstico anterior detecta el alza' );

// Componentes puros.
$holt = SIS_Forecast::holt_amortiguado( array( 2, 3, 4, 5, 6, 7 ), 6 );
chk( 6 === count( $holt ) && $holt[0] > 6, 'Holt amortiguado proyecta al alza una serie creciente' );
$plano = SIS_Forecast::holt_amortiguado( array( 3, 3, 3, 3, 3, 3 ), 6 );
chk( abs( $plano[5] - 3.0 ) < 0.2, 'Holt amortiguado reproduce una serie constante' );
$neg = SIS_Forecast::holt_amortiguado( array( 9, 7, 5, 3, 1, 0 ), 6 );
chk( min( $neg ) >= 0.0, 'La proyección nunca es negativa' );

$om1 = SIS_Forecast::integral_omori( 10, 0.05, 1.08, 0, 30 );
$om2 = SIS_Forecast::integral_omori( 10, 0.05, 1.08, 30, 60 );
chk( $om1 > $om2, 'La integral de Omori decae con el tiempo' );

$pr = SIS_Forecast::proporcion_sobre( 4.5, 4.5, 1.0 );
chk( abs( $pr - 1.0 ) < 1e-9, 'La proporción sobre Mc es 1' );
chk( SIS_Forecast::proporcion_sobre( SIS_Forecast::M_MAX_CREIBLE, 4.5, 1.0 ) <= 1e-9, 'La GR truncada anula la probabilidad en la magnitud máxima' );

/* ------------------------------------------------------------------ */
echo "\n";
if ( $fallos ) {
	echo "RESULTADO: $fallos prueba(s) fallida(s).\n";
	exit( 1 );
}
echo "RESULTADO: todas las pruebas pasaron.\n";
exit( 0 );
