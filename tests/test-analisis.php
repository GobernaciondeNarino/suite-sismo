<?php
/**
 * Tests CLI de la lógica pura de análisis (catálogo y estadística retrospectiva).
 *
 * No requiere WordPress: define stubs mínimos de las funciones de WP que usan
 * los métodos puros bajo prueba. Ejecutar con:  php tests/test-analisis.php
 *
 * @package SismosNarino
 */

/*
 * Este archivo vive dentro del plugin y, por tanto, es alcanzable por URL en
 * cualquier instalación de WordPress. Solo debe ejecutarse por línea de
 * comandos: una petición web recibe 403 y no ejecuta nada.
 */
if ( 'cli' !== PHP_SAPI || isset( $_SERVER['REQUEST_METHOD'] ) ) {
	if ( ! headers_sent() ) {
		header( 'Content-Type: text/plain; charset=utf-8' );
		http_response_code( 403 );
	}
	exit( 'Este archivo es una prueba y solo se ejecuta por linea de comandos.' );
}

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
require SIS_DIR . 'includes/analysis/class-sis-texto.php';
require SIS_DIR . 'includes/data/class-sis-periodo.php';
require SIS_DIR . 'includes/data/class-sis-amenaza.php';

use GobernacionNarino\Sismos\SIS_Catalogo;
use GobernacionNarino\Sismos\SIS_Amenaza;
use GobernacionNarino\Sismos\SIS_Estadistica;
use GobernacionNarino\Sismos\SIS_Texto;
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

$crudo = json_decode( file_get_contents( SIS_DIR . 'data/' . SIS_Catalogo::SEMILLA ), true );
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

// El plugin no debe ofrecer ninguna vía para estimar sismos futuros.
$prohibidos = array( 'probabilidad_poisson', 'intervalo_poisson', 'cuantil_chi2', 'cdf_normal', 'cuantil_normal', 'periodo_retorno' );
$existen    = array();
foreach ( $prohibidos as $m ) {
	if ( method_exists( 'GobernacionNarino\\Sismos\\SIS_Estadistica', $m ) ) {
		$existen[] = $m;
	}
}
chk( empty( $existen ), 'La estadística no expone métodos de probabilidad a futuro' . ( $existen ? ' (quedan: ' . implode( ',', $existen ) . ')' : '' ) );
chk( ! class_exists( 'GobernacionNarino\\Sismos\\SIS_Forecast' ), 'No existe ninguna clase de pronóstico cargada' );

$reg = SIS_Estadistica::regresion_lineal( array( 1, 2, 3, 4, 5 ) );
chk( abs( $reg['pendiente'] - 1.0 ) < 1e-9 && $reg['r2'] > 0.999, 'Regresión lineal exacta sobre una recta' );

$resumen = SIS_Estadistica::resumen( $eventos );
chk( ! empty( $resumen['umbrales'] ), 'El resumen calcula la recurrencia observada por umbral' );
chk( $resumen['energia_tnt'] > 0, 'El resumen acumula energía liberada' );

/* ------------------------------------------------------------------ */
seccion( 'Recurrencia observada (retrospectiva)' );

$resumen = SIS_Estadistica::resumen( $eventos );
chk( ! empty( $resumen['umbrales'] ), 'El resumen publica recurrencia por umbral' );

$campos_prohibidos = array( 'probabilidad', 'prob_1_anio', 'esperados', 'esperados_6m', 'periodo_retorno' );
$fuga = array();
foreach ( $resumen['umbrales'] as $u ) {
	foreach ( $campos_prohibidos as $c ) {
		if ( array_key_exists( $c, $u ) ) {
			$fuga[] = $c;
		}
	}
}
chk( empty( $fuga ), 'Ningún umbral expone probabilidades ni valores esperados a futuro' . ( $fuga ? ' (aparecen: ' . implode( ',', array_unique( $fuga ) ) . ')' : '' ) );

$coherente = true;
foreach ( $resumen['umbrales'] as $u ) {
	foreach ( array( 'magnitud', 'observados', 'tasa_anual_obs', 'intervalo_medio' ) as $c ) {
		if ( ! array_key_exists( $c, $u ) ) {
			$coherente = false;
		}
	}
	// Lo observado no puede ser negativo ni superar el catálogo completo.
	if ( $u['observados'] < 0 || $u['observados'] > count( $eventos ) ) {
		$coherente = false;
	}
}
chk( $coherente, 'Cada umbral trae magnitud, observados, tasa anual observada e intervalo medio' );

$decreciente = true;
$antes       = PHP_INT_MAX;
foreach ( $resumen['umbrales'] as $u ) {
	if ( $u['observados'] > $antes ) {
		$decreciente = false;
	}
	$antes = $u['observados'];
}
chk( $decreciente, 'El número de sismos observados decrece al subir la magnitud' );

$m5 = null;
foreach ( $resumen['umbrales'] as $u ) {
	if ( abs( $u['magnitud'] - 5.0 ) < 1e-9 ) {
		$m5 = $u;
	}
}
$reales = count( SIS_Catalogo::filtrar( $eventos, array( 'min_mag' => 5.0 ) ) );
chk( $m5 && $m5['observados'] === $reales, sprintf( 'El conteo de M≥5,0 coincide con el catálogo (%d)', $reales ) );

$inv = SIS_Estadistica::intervalo_recurrencia( 0.5 );
chk( abs( $inv - 2.0 ) < 1e-9, 'El intervalo de recurrencia es el inverso de la tasa anual' );
chk( ! is_finite( SIS_Estadistica::intervalo_recurrencia( 0 ) ), 'Una tasa nula no produce un intervalo finito' );

$txt = SIS_Texto::recurrencia( $resumen );
chk( false !== mb_strpos( $txt, 'promedio' ), 'La narrativa de recurrencia habla de promedios' );
chk( false !== mb_strpos( $txt, 'no un calendario' ) || false !== mb_strpos( $txt, 'no siguen turnos' ), 'La narrativa advierte que no es un calendario' );

/* ------------------------------------------------------------------ */
seccion( 'Marco de amenaza y comunicación del riesgo' );

$glosario = SIS_Amenaza::glosario();
chk( 4 === count( $glosario ), 'El glosario separa los cuatro conceptos del marco USGS' );

$prediccion = null;
foreach ( $glosario as $g ) {
	if ( 'Predicción' === $g['termino'] ) {
		$prediccion = $g;
	}
}
chk( $prediccion && false === $prediccion['es_posible'], 'El glosario declara imposible la predicción' );

$posibles = 0;
foreach ( $glosario as $g ) {
	if ( $g['es_posible'] ) {
		$posibles++;
	}
}
chk( 3 === $posibles, 'Los otros tres conceptos sí son posibles' );

chk( false !== mb_strpos( SIS_Amenaza::descargo(), 'Servicio Geológico Colombiano' ), 'El descargo nombra a la autoridad técnica' );
chk( false !== mb_strpos( SIS_Amenaza::descargo(), 'no se predicen sismos' ), 'El descargo declara que no se predicen sismos' );

chk( count( SIS_Amenaza::fuentes_oficiales() ) >= 6, 'Hay directorio de fuentes oficiales' );
$https = true;
foreach ( SIS_Amenaza::fuentes_oficiales() as $f ) {
	if ( 0 !== strpos( $f['url'], 'https://' ) ) {
		$https = false;
	}
}
chk( $https, 'Todas las fuentes oficiales enlazan por HTTPS' );

chk( count( SIS_Amenaza::senales_falsas() ) >= 5, 'El panel anti-desinformación tiene señales suficientes' );
chk( ! empty( SIS_Amenaza::replicas()['que_hacer'] ), 'La guía de réplicas trae recomendaciones' );

$rep = SIS_Amenaza::replicas();
$sin_cifras = true;
foreach ( array_merge( array( $rep['que_son'], $rep['cuanto_duran'], $rep['donde_mirar'] ), $rep['que_hacer'], $rep['no_haga'] ) as $frase ) {
	if ( preg_match( '/\b\d+\s*%/', $frase ) ) {
		$sin_cifras = false;
	}
}
chk( $sin_cifras, 'La guía de réplicas no publica porcentajes propios' );

chk( ! empty( SIS_Amenaza::contexto_geologico() ), 'Hay contexto geológico del departamento' );
chk( ! empty( SIS_Amenaza::normativa_por_defecto()['norma'] ), 'Hay referencia normativa por defecto' );

/* ------------------------------------------------------------------ */
echo "\n";
if ( $fallos ) {
	echo "RESULTADO: $fallos prueba(s) fallida(s).\n";
	exit( 1 );
}
echo "RESULTADO: todas las pruebas pasaron.\n";
exit( 0 );
