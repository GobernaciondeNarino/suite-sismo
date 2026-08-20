<?php
/**
 * Test de integración de las vistas del motor de gráficos y del marco de
 * amenaza, sobre una simulación mínima de WordPress (opciones, transients y
 * $wpdb en memoria). Ejecutar con:  php tests/test-vistas.php
 *
 * Verifica que las 14 vistas construyen filas coherentes con sus dimensiones y
 * medidas declaradas, que ninguna publica estimaciones de sismos futuros y que
 * el marco de amenaza queda completo.
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
if ( ! defined( 'SIS_VERSION' ) ) {
	define( 'SIS_VERSION', '1.0.0-test' );
}

/* ------------------------------------------------------------------ */
/* Simulación mínima de WordPress                                     */
/* ------------------------------------------------------------------ */

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

$GLOBALS['sis_opciones']   = array();
$GLOBALS['sis_transients'] = array();

function get_option( $k, $def = false ) {
	return array_key_exists( $k, $GLOBALS['sis_opciones'] ) ? $GLOBALS['sis_opciones'][ $k ] : $def;
}
function update_option( $k, $v ) {
	$GLOBALS['sis_opciones'][ $k ] = $v;
	return true;
}
function add_option( $k, $v ) {
	if ( ! isset( $GLOBALS['sis_opciones'][ $k ] ) ) {
		$GLOBALS['sis_opciones'][ $k ] = $v;
	}
	return true;
}
function get_transient( $k ) {
	if ( ! isset( $GLOBALS['sis_transients'][ $k ] ) ) {
		return false;
	}
	list( $valor, $expira ) = $GLOBALS['sis_transients'][ $k ];
	if ( $expira < time() ) {
		unset( $GLOBALS['sis_transients'][ $k ] );
		return false;
	}
	return $valor;
}
function set_transient( $k, $v, $ttl ) {
	$GLOBALS['sis_transients'][ $k ] = array( $v, time() + (int) $ttl );
	return true;
}
function delete_transient( $k ) {
	unset( $GLOBALS['sis_transients'][ $k ] );
	return true;
}

function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function esc_url_raw( $s ) { return (string) $s; }
function remove_accents( $s ) { return $s; }
function wp_parse_url( $u ) { return parse_url( $u ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function current_time( $t, $gmt = 0 ) { return gmdate( 'Y-m-d H:i:s' ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d, ',', '.' ); }
function wp_list_pluck( $l, $c ) {
	$o = array();
	foreach ( (array) $l as $f ) {
		if ( is_array( $f ) && isset( $f[ $c ] ) ) { $o[] = $f[ $c ]; }
	}
	return $o;
}
function wp_parse_args( $args, $def = array() ) {
	$args = (array) $args;
	return array_merge( $def, $args );
}
function add_query_arg( $args, $url ) {
	$q = http_build_query( $args );
	return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . $q;
}
function add_action() {}
function add_filter() {}
function __( $t, $d = null ) { return $t; }

/* $wpdb en memoria: solo lo que usa SIS_Cache. */
class SIS_WPDB_Fake {
	public $prefix = 'wp_';
	private $filas = array();

	public function prepare( $sql, ...$args ) {
		foreach ( $args as $a ) {
			$sql = preg_replace( '/%s|%d/', is_numeric( $a ) ? (string) $a : "'" . $a . "'", $sql, 1 );
		}
		return $sql;
	}
	private function clave_de( $sql ) {
		return preg_match( "/clave = '([^']*)'/", $sql, $m ) ? $m[1] : '';
	}
	private function grupo_de( $sql ) {
		return preg_match( "/grupo = '([^']*)'/", $sql, $m ) ? $m[1] : '';
	}
	public function get_row( $sql, $tipo = null ) {
		$c = $this->clave_de( $sql );
		return isset( $this->filas[ $c ] ) ? $this->filas[ $c ] : null;
	}
	public function get_var( $sql ) {
		$c = $this->clave_de( $sql );
		if ( ! isset( $this->filas[ $c ] ) ) { return null; }
		return ( false !== strpos( $sql, 'actualizado' ) ) ? $this->filas[ $c ]['actualizado'] : $this->filas[ $c ]['valor'];
	}
	public function get_col( $sql ) {
		$g   = $this->grupo_de( $sql );
		$out = array();
		foreach ( $this->filas as $c => $f ) {
			if ( $f['grupo'] === $g ) { $out[] = $c; }
		}
		return $out;
	}
	public function replace( $tabla, $datos ) {
		$this->filas[ $datos['clave'] ] = $datos;
		return 1;
	}
	public function delete( $tabla, $donde ) {
		if ( isset( $donde['clave'] ) ) {
			unset( $this->filas[ $donde['clave'] ] );
			return 1;
		}
		$n = 0;
		foreach ( $this->filas as $c => $f ) {
			if ( isset( $donde['grupo'] ) && $f['grupo'] === $donde['grupo'] ) {
				unset( $this->filas[ $c ] );
				$n++;
			}
		}
		return $n;
	}
	public function insert( $tabla, $datos ) { return 1; }
}
$GLOBALS['wpdb'] = new SIS_WPDB_Fake();

/* ------------------------------------------------------------------ */

require SIS_DIR . 'includes/class-sis-cache.php';
require SIS_DIR . 'includes/class-sis-security.php';
require SIS_DIR . 'includes/data/class-sis-municipios.php';
require SIS_DIR . 'includes/data/class-sis-regiones.php';
require SIS_DIR . 'includes/analysis/class-sis-catalogo.php';
require SIS_DIR . 'includes/analysis/class-sis-estadistica.php';
require SIS_DIR . 'includes/analysis/class-sis-texto.php';
require SIS_DIR . 'includes/data/class-sis-amenaza.php';
require SIS_DIR . 'includes/data/class-sis-views.php';

use GobernacionNarino\Sismos\SIS_Cache;
use GobernacionNarino\Sismos\SIS_Catalogo;
use GobernacionNarino\Sismos\SIS_Amenaza;
use GobernacionNarino\Sismos\SIS_Views;

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
function seccion( $t ) { echo "\n== $t ==\n"; }

/* ------------------------------------------------------------------ */
seccion( 'Caché de dos niveles' );

SIS_Cache::set( 'prueba', array( 'a' => 1 ), 120, 'test' );
chk( SIS_Cache::get( 'prueba' ) === array( 'a' => 1 ), 'La caché devuelve lo guardado' );
delete_transient( 'sis_prueba' );
chk( SIS_Cache::get( 'prueba' ) === array( 'a' => 1 ), 'Sin transient, la tabla durable responde' );
SIS_Cache::delete( 'prueba' );
chk( null === SIS_Cache::get( 'prueba' ), 'El borrado limpia ambos niveles' );

SIS_Cache::set( 'g1', 1, 120, 'grupoX' );
SIS_Cache::set( 'g2', 2, 120, 'grupoX' );
chk( 2 === SIS_Cache::delete_grupo( 'grupoX' ), 'El borrado por grupo limpia todas sus claves' );

chk( is_array( SIS_Cache::semilla( 'catalogo_regional_semilla.json' ) ), 'La semilla local se carga' );
chk( null === SIS_Cache::semilla( '../../wp-config.php' ), 'La semilla bloquea el traspaso de directorio' );

/* ------------------------------------------------------------------ */
seccion( 'Catálogo desde la semilla (sin red)' );

$cat = SIS_Catalogo::obtener( 'regional' );
chk( 'semilla' === $cat['origen'], 'Sin sincronización previa, el catálogo cae a la semilla' );
chk( $cat['total'] > 300, sprintf( 'Catálogo con %d sismos', $cat['total'] ) );

/* ------------------------------------------------------------------ */
seccion( 'Vistas del motor de gráficos' );

$vistas = SIS_Views::lista();
chk( count( $vistas ) >= 14, sprintf( 'Catálogo de %d vistas', count( $vistas ) ) );

$prohibidas = array( 'pronostico_mensual', 'pronostico_banda', 'pronostico_umbrales', 'periodo_retorno' );
$quedan     = array();
foreach ( $prohibidas as $id ) {
	if ( SIS_Views::existe( $id ) ) {
		$quedan[] = $id;
	}
}
chk( empty( $quedan ), 'No queda ninguna vista de pronóstico' . ( $quedan ? ' (quedan: ' . implode( ',', $quedan ) . ')' : '' ) );

foreach ( $vistas as $meta ) {
	$v = SIS_Views::obtener( $meta['id'], array( 'ambito' => 'regional' ) );

	$ok_filas = is_array( $v['data'] ) && count( $v['data'] ) > 0;
	chk( $ok_filas, sprintf( 'Vista %-22s → %d filas', $meta['id'], count( $v['data'] ) ) );

	if ( ! $ok_filas ) { continue; }

	$fila     = $v['data'][0];
	$campos   = array_merge( $v['dimensions'], $v['measures'] );
	$faltante = array();
	foreach ( $campos as $c ) {
		if ( ! array_key_exists( $c, $fila ) ) { $faltante[] = $c; }
	}
	chk( empty( $faltante ), sprintf( '  %-22s trae todas sus dimensiones y medidas', $meta['id'] ) . ( $faltante ? ' (faltan: ' . implode( ',', $faltante ) . ')' : '' ) );

	// Las medidas deben ser numéricas: D3plus construye escalas con ellas.
	$numericas = true;
	foreach ( $v['measures'] as $m ) {
		if ( isset( $fila[ $m ] ) && ! is_int( $fila[ $m ] ) && ! is_float( $fila[ $m ] ) ) { $numericas = false; }
	}
	chk( $numericas, sprintf( '  %-22s tiene medidas numéricas', $meta['id'] ) );

	chk( '' !== $v['analisis']['cuantitativo'], sprintf( '  %-22s genera análisis cuantitativo', $meta['id'] ) );
	chk( in_array( $meta['default'], $meta['compatibles'], true ), sprintf( '  %-22s tiene un tipo por defecto compatible', $meta['id'] ) );
}

/* ------------------------------------------------------------------ */
seccion( 'Ninguna vista mira hacia el futuro' );

$hoy      = gmdate( 'Y-m' );
$futuras  = array();
$avisadas = 0;

foreach ( $vistas as $meta ) {
	$v = SIS_Views::obtener( $meta['id'], array( 'ambito' => 'regional' ) );

	// 1) Ninguna fila puede referirse a un mes o año posterior al actual.
	foreach ( $v['data'] as $fila ) {
		if ( isset( $fila['mes'] ) && $fila['mes'] > $hoy ) {
			$futuras[] = $meta['id'] . ':' . $fila['mes'];
		}
		if ( isset( $fila['anio'] ) && (int) $fila['anio'] > (int) gmdate( 'Y' ) ) {
			$futuras[] = $meta['id'] . ':' . $fila['anio'];
		}
	}

	// 2) Toda vista publica el aviso de alcance.
	if ( ! empty( $v['aviso'] ) ) {
		$avisadas++;
	}

	// 3) Ningún texto promete o estima sismos futuros.
	$textos = trim( $v['analisis']['descriptivo'] . ' ' . $v['analisis']['cuantitativo'] . ' ' . $v['como_funciona'] );
	if ( preg_match( '/se espera[nr]?\s|se pronostic|probabilidad de que ocurra|en los próximos (seis|6) meses/i', $textos ) ) {
		$futuras[] = $meta['id'] . ':texto';
	}
}

chk( empty( $futuras ), 'Ninguna vista publica datos ni textos de sismos futuros' . ( $futuras ? ' (' . implode( ', ', array_slice( $futuras, 0, 5 ) ) . ')' : '' ) );
chk( count( $vistas ) === $avisadas, 'Todas las vistas llevan el aviso de alcance' );

$vr = SIS_Views::obtener( 'recurrencia_historica', array( 'ambito' => 'regional' ) );
chk( count( $vr['data'] ) > 0, sprintf( 'La vista de recurrencia observada publica %d umbrales', count( $vr['data'] ) ) );
$campos_ok = true;
foreach ( $vr['data'] as $fila ) {
	if ( ! isset( $fila['umbral'], $fila['intervalo_medio'], $fila['observados'] ) ) {
		$campos_ok = false;
	}
	if ( isset( $fila['probabilidad'] ) || isset( $fila['esperados'] ) ) {
		$campos_ok = false;
	}
}
chk( $campos_ok, 'La recurrencia trae observados e intervalo medio, y ninguna probabilidad' );

/* ------------------------------------------------------------------ */
seccion( 'Marco de amenaza servido por la API' );

$ficha = SIS_Amenaza::ficha();
foreach ( array( 'descargo', 'glosario', 'fuentes', 'geologia', 'normativa', 'replicas', 'senales', 'marco_legal' ) as $clave ) {
	chk( ! empty( $ficha[ $clave ] ), "La ficha de amenaza trae «{$clave}»" );
}
chk( false !== mb_strpos( $ficha['marco_legal'], 'Ley 1523' ), 'La ficha cita el marco legal de gestión del riesgo' );

// La normativa se puede editar desde el panel sin tocar código.
update_option( 'sis_amenaza', array( 'aa_pasto' => '0.30' ) );
chk( '0.30' === SIS_Amenaza::normativa()['aa_pasto'], 'La normativa editada en el panel prevalece' );
chk( '' !== SIS_Amenaza::normativa()['norma'], 'Las claves no editadas conservan su valor por defecto' );

/* ------------------------------------------------------------------ */
echo "\n";
if ( $fallos ) {
	echo "RESULTADO: $fallos prueba(s) fallida(s).\n";
	exit( 1 );
}
echo "RESULTADO: todas las pruebas pasaron.\n";
exit( 0 );
