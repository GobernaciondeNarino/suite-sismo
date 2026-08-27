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
require SIS_DIR . 'includes/data/class-sis-periodo.php';
require SIS_DIR . 'includes/data/class-sis-views.php';

use GobernacionNarino\Sismos\SIS_Cache;
use GobernacionNarino\Sismos\SIS_Catalogo;
use GobernacionNarino\Sismos\SIS_Amenaza;
use GobernacionNarino\Sismos\SIS_Views;
use GobernacionNarino\Sismos\SIS_Regiones;
use GobernacionNarino\Sismos\SIS_Periodo;

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
/* Las series llegan hasta hoy, no hasta el último sismo registrado    */
/* ------------------------------------------------------------------ */

/*
 * Una gráfica que termina en el último mes con actividad hace creer que los
 * datos se detuvieron ahí. Si el catálogo lleva semanas en calma, el mes en
 * curso debe aparecer igualmente, en cero.
 */
$mensual = SIS_Views::obtener( 'sismos_mensuales', array( 'ambito' => 'regional', 'anios' => 0 ) );
$ultimo  = end( $mensual['data'] );
chk( gmdate( 'Y-m' ) === $ultimo['mes'], 'La serie mensual llega al mes en curso (' . gmdate( 'Y-m' ) . ')' );

$anual     = SIS_Views::obtener( 'sismos_anuales', array( 'ambito' => 'regional', 'anios' => 0 ) );
$ult_anio  = end( $anual['data'] );
chk( gmdate( 'Y' ) === $ult_anio['anio'], 'La serie anual llega al año en curso (' . gmdate( 'Y' ) . ')' );

$energia   = SIS_Views::obtener( 'energia_mensual', array( 'ambito' => 'regional', 'anios' => 0 ) );
$ult_ener  = end( $energia['data'] );
chk( gmdate( 'Y-m' ) === $ult_ener['mes'], 'La serie de energía llega al mes en curso' );

// Y no se inventan meses futuros.
$meses = wp_list_pluck( $mensual['data'], 'mes' );
chk( max( $meses ) <= gmdate( 'Y-m' ), 'Ninguna serie publica meses que aún no han ocurrido' );

/* ------------------------------------------------------------------ */
/* Histórico mensual con tendencia                                     */
/* ------------------------------------------------------------------ */

$hist = SIS_Views::obtener( 'historico_mensual', array( 'ambito' => 'regional', 'anios' => 0 ) );
chk( in_array( 'media_movil_12m', $hist['measures'], true ), 'El histórico mensual publica la media móvil de 12 meses' );
chk( count( $hist['data'] ) === count( $mensual['data'] ), 'El histórico mensual cubre los mismos meses que la serie cruda' );

$fila = $hist['data'][ intdiv( count( $hist['data'] ), 2 ) ];
chk( isset( $fila['sismos'], $fila['media_movil_12m'] ), 'Cada fila trae el conteo y su media móvil' );

// La media móvil suaviza: su recorrido tiene que ser menor que el de la serie.
$crudos = wp_list_pluck( $hist['data'], 'sismos' );
$suaves = wp_list_pluck( $hist['data'], 'media_movil_12m' );
chk( ( max( $suaves ) - min( $suaves ) ) < ( max( $crudos ) - min( $crudos ) ), 'La media móvil tiene menos recorrido que la serie cruda' );
chk( max( $suaves ) <= max( $crudos ), 'La media móvil nunca supera el máximo de la serie' );

/* ------------------------------------------------------------------ */
/* Ámbito mundial: existe, pero no se sincroniza contra el histórico   */
/* ------------------------------------------------------------------ */

chk( SIS_Regiones::existe( 'mundo' ), 'Existe el ámbito «mundo» para la vista global del globo' );
chk( SIS_Regiones::solo_feed( 'mundo' ), 'El ámbito «mundo» se sirve del feed, no del catálogo histórico' );
chk( ! in_array( 'mundo', SIS_Regiones::sincronizables(), true ), 'El ámbito «mundo» queda fuera de la sincronización histórica' );
chk( in_array( 'regional', SIS_Regiones::sincronizables(), true ), 'Los ámbitos acotados sí se sincronizan' );
chk( SIS_Regiones::contiene( 'mundo', 35.5, 140.2 ) && SIS_Regiones::contiene( 'mundo', -33.4, -70.6 ), 'El ámbito «mundo» acepta epicentros de cualquier país' );
chk( ! SIS_Regiones::solo_feed( 'regional' ), 'El ámbito regional conserva su catálogo histórico' );

/* ------------------------------------------------------------------ */
/* Vistas nuevas: datos, tipos y textos                                */
/* ------------------------------------------------------------------ */

$nuevas = array( 'energia_acumulada', 'calendario_sismico', 'hora_del_dia', 'intervalos', 'sismos_sentidos', 'dispersion_mag_prof' );
foreach ( $nuevas as $id ) {
	$v = SIS_Views::obtener( $id, array( 'ambito' => 'regional', 'anios' => 0 ) );
	chk( ! empty( $v['data'] ), "La vista «{$id}» devuelve datos" );
	chk( ! empty( $v['description'] ) && ! empty( $v['analisis'] ) && ! empty( $v['como_funciona'] ), "La vista «{$id}» trae sus tres textos" );
	chk( in_array( $v['default'] ?? SIS_Views::default_tipo( $id ), SIS_Views::compatibles( $v['category'] ), true ), "El tipo por defecto de «{$id}» es compatible con su categoría" );
}

// Toda vista publicada debe poder acompañarse de sus textos: es la promesa de
// las tarjetas del panel.
foreach ( SIS_Views::lista() as $v ) {
	$full = SIS_Views::obtener( $v['id'], array( 'ambito' => 'regional' ) );
	chk( '' !== trim( (string) $full['descripcion_larga'] ) && '' !== trim( (string) $full['analisis'] ) && '' !== trim( (string) $full['como_funciona'] ),
		"«{$v['id']}» puede publicarse con descripción y análisis" );
}

// La hora se calcula en hora de Colombia (UTC−5) y cubre las 24 franjas.
$h = SIS_Views::obtener( 'hora_del_dia', array( 'ambito' => 'regional', 'anios' => 0 ) );
chk( 24 === count( $h['data'] ), 'La vista de horas cubre las 24 franjas del día' );
$total_h = array_sum( wp_list_pluck( $h['data'], 'sismos' ) );
$cat     = SIS_Catalogo::obtener( 'regional' );
chk( $total_h === $cat['total'], 'El reparto por hora suma todos los sismos del catálogo' );

// La media móvil del calendario cubre los mismos meses que la serie mensual.
$cal = SIS_Views::obtener( 'calendario_sismico', array( 'ambito' => 'regional', 'anios' => 0 ) );
$men = SIS_Views::obtener( 'sismos_mensuales', array( 'ambito' => 'regional', 'anios' => 0 ) );
chk( count( $cal['data'] ) === count( $men['data'] ), 'El calendario cubre los mismos meses que la serie mensual' );
chk( 12 === count( $cal['orden'] ), 'El calendario declara el orden de sus doce columnas' );
chk( 'Ene' === $cal['orden'][0] && 'Dic' === $cal['orden'][11], 'Las columnas del calendario van de enero a diciembre' );

// La dispersión publica un punto por sismo, con su id, y declara qué colorea.
$disp = SIS_Views::obtener( 'dispersion_mag_prof', array( 'ambito' => 'regional', 'anios' => 0 ) );
chk( count( $disp['data'] ) === $cat['total'], 'La dispersión dibuja un punto por sismo, sin agregar' );
chk( 'rango' === $disp['series'], 'La dispersión declara el campo que colorea la nube' );
$ids = wp_list_pluck( $disp['data'], 'id' );
chk( count( array_unique( $ids ) ) === count( $ids ), 'Cada punto de la dispersión lleva un id único' );

// Los sismos sentidos nunca pueden superar a los registrados.
$sen = SIS_Views::obtener( 'sismos_sentidos', array( 'ambito' => 'regional', 'anios' => 0 ) );
$coherente = true;
foreach ( $sen['data'] as $f ) {
	if ( $f['sentidos'] > $f['registrados'] ) { $coherente = false; }
}
chk( $coherente, 'Los sismos sentidos nunca superan a los registrados' );

// La energía acumulada solo puede crecer.
$ea      = SIS_Views::obtener( 'energia_acumulada', array( 'ambito' => 'regional', 'anios' => 0 ) );
$crece   = true;
$anterior = -1.0;
foreach ( $ea['data'] as $f ) {
	if ( $f['energia_acumulada_tnt'] < $anterior ) { $crece = false; }
	$anterior = $f['energia_acumulada_tnt'];
}
chk( $crece, 'La curva de energía acumulada nunca decrece' );

/* ------------------------------------------------------------------ */
/* Tipos de gráfico del motor                                          */
/* ------------------------------------------------------------------ */

$tipos = SIS_Views::tipos();
chk( isset( $tipos['plot']['class'] ) && 'Plot' === $tipos['plot']['class'], 'El motor conoce el tipo dispersión (Plot)' );
chk( isset( $tipos['matrix']['class'] ) && 'Matrix' === $tipos['matrix']['class'], 'El motor conoce el tipo matriz de calor (Matrix)' );
chk( array( 'plot' ) === SIS_Views::compatibles( 'dispersion' ), 'Una nube de puntos no se ofrece como barras' );
foreach ( $tipos as $k => $t ) {
	chk( ! empty( $t['class'] ) && ! empty( $t['label'] ), "El tipo «{$k}» declara clase y etiqueta" );
}

/* ------------------------------------------------------------------ */
/* Periodo: normalización, precedencia y lenguaje                      */
/* ------------------------------------------------------------------ */

$anio_actual = (int) gmdate( 'Y' );

// Precedencia: una fecha de calendario gana a una ventana móvil.
$p = SIS_Periodo::normalizar( array( 'anio' => 2020, 'dias' => 15, 'anios' => 30 ) );
chk( 2020 === $p['anio'] && 0 === $p['dias'] && 0 === $p['anios'], 'Un año concreto descarta las ventanas móviles' );

$p = SIS_Periodo::normalizar( array( 'dias' => 30, 'anios' => 5 ) );
chk( 30 === $p['dias'] && 0 === $p['anios'], 'Entre días y años gana la ventana más específica' );

// Un mes suelto se entiende como el mes del año en curso.
$p = SIS_Periodo::normalizar( array( 'mes' => 8 ) );
chk( 8 === $p['mes'] && $anio_actual === $p['anio'], 'Un mes sin año se refiere al año en curso' );

// Valores imposibles no deben colarse.
foreach ( array( 0, 13, -1, 99 ) as $malo ) {
	$p = SIS_Periodo::normalizar( array( 'mes' => $malo, 'anio' => 2024 ) );
	chk( 0 === $p['mes'], "Un mes «{$malo}» se descarta" );
}
foreach ( array( 1500, 1899, $anio_actual + 5, -2026 ) as $malo ) {
	$p = SIS_Periodo::normalizar( array( 'anio' => $malo ) );
	chk( 0 === $p['anio'], "Un año «{$malo}» se descarta" );
}
$p = SIS_Periodo::normalizar( array( 'dias' => 999999 ) );
chk( 20000 === $p['dias'], 'Los días se acotan por arriba' );
$p = SIS_Periodo::normalizar( array( 'anios' => 999 ) );
chk( 60 === $p['anios'], 'Los años se acotan por arriba' );

// Filtros de calendario: rango exacto del mes y del año.
$f = SIS_Periodo::filtros( SIS_Periodo::normalizar( array( 'anio' => 2024, 'mes' => 2 ) ) );
chk( '2024-02-01' === $f['desde'] && '2024-02-29' === $f['hasta'], 'Febrero de un año bisiesto termina el día 29' );
$f = SIS_Periodo::filtros( SIS_Periodo::normalizar( array( 'anio' => 2023, 'mes' => 2 ) ) );
chk( '2023-02-28' === $f['hasta'], 'Febrero de un año normal termina el día 28' );
$f = SIS_Periodo::filtros( SIS_Periodo::normalizar( array( 'anio' => 2021 ) ) );
chk( '2021-01-01' === $f['desde'] && '2021-12-31' === $f['hasta'], 'Un año cubre del 1 de enero al 31 de diciembre' );

// Etiquetas en lenguaje corriente.
$etiquetas = array(
	array( array( 'dias' => 15 ),                'en los últimos 15 días' ),
	array( array( 'dias' => 1 ),                 'en las últimas 24 horas' ),
	array( array( 'dias' => 7 ),                 'en la última semana' ),
	array( array( 'dias' => 30 ),                'en el último mes' ),
	array( array( 'anios' => 8 ),                'en los últimos 8 años' ),
	array( array( 'anios' => 1 ),                'en el último año' ),
	array( array( 'anio' => 2019 ),              'en 2019' ),
	array( array( 'anio' => 2019, 'mes' => 8 ),  'en agosto de 2019' ),
	array( array(),                              'en todo el registro disponible' ),
);
foreach ( $etiquetas as $caso ) {
	$got = SIS_Periodo::etiqueta( SIS_Periodo::normalizar( $caso[0] ) );
	chk( $caso[1] === $got, "«{$caso[1]}» se dice bien" . ( $caso[1] === $got ? '' : " (salió «{$got}»)" ) );
}

// Dos periodos distintos no pueden compartir clave de caché.
$claves = array();
foreach ( array(
	array( 'dias' => 15 ), array( 'dias' => 30 ), array( 'anios' => 8 ),
	array( 'anio' => 2026 ), array( 'anio' => 2026, 'mes' => 8 ), array(),
) as $caso ) {
	$claves[] = SIS_Periodo::clave( SIS_Periodo::normalizar( $caso ) );
}
chk( count( array_unique( $claves ) ) === count( $claves ), 'Cada periodo tiene su propia clave de caché' );

/* ------------------------------------------------------------------ */
/* Los filtros recortan de verdad, y las series respetan el periodo    */
/* ------------------------------------------------------------------ */

$total = count( SIS_Views::eventos( SIS_Views::normalizar_args( array( 'ambito' => 'regional' ) ) ) );
$n5    = count( SIS_Views::eventos( SIS_Views::normalizar_args( array( 'ambito' => 'regional', 'anios' => 5 ) ) ) );
$n30d  = count( SIS_Views::eventos( SIS_Views::normalizar_args( array( 'ambito' => 'regional', 'dias' => 30 ) ) ) );
chk( $n5 < $total && $n30d <= $n5, 'Las ventanas móviles recortan el catálogo de forma coherente' );

// Un mes de calendario devuelve exactamente ese mes.
$abr = SIS_Views::eventos( SIS_Views::normalizar_args( array( 'ambito' => 'regional', 'anio' => 2016, 'mes' => 4 ) ) );
$fuera = 0;
foreach ( $abr as $e ) {
	if ( '2016-04' !== $e['mes'] ) { $fuera++; }
}
chk( count( $abr ) > 0 && 0 === $fuera, 'Un filtro de mes devuelve solo ese mes (' . count( $abr ) . ' sismos, ' . $fuera . ' fuera)' );

// Y la serie mensual de ese filtro tiene una sola fila, no diez años de ceros.
$vm = SIS_Views::obtener( 'sismos_mensuales', array( 'ambito' => 'regional', 'anio' => 2016, 'mes' => 4 ) );
chk( 1 === count( $vm['data'] ) && '2016-04' === $vm['data'][0]['mes'], 'La serie de un mes concreto tiene una sola fila' );

// Un año concreto cubre sus doce meses, aunque alguno esté vacío.
$va = SIS_Views::obtener( 'sismos_mensuales', array( 'ambito' => 'regional', 'anio' => 2021 ) );
chk( 12 === count( $va['data'] ), 'La serie de un año concreto cubre sus doce meses' );
chk( '2021-01' === $va['data'][0]['mes'] && '2021-12' === $va['data'][11]['mes'], 'Esa serie va de enero a diciembre' );

/* ------------------------------------------------------------------ */
/* Ámbito «narino»: nada de lo publicado sale del departamento         */
/* ------------------------------------------------------------------ */

/*
 * Es el punto delicado de cara a la ciudadanía: si el rótulo dice «Nariño»,
 * ni un solo epicentro, ni una sola cifra, ni una sola frase pueden venir de
 * fuera del departamento.
 */
foreach ( array( array(), array( 'anios' => 8 ), array( 'dias' => 3650 ), array( 'anio' => 2019 ) ) as $per ) {
	$args = array_merge( array( 'ambito' => 'narino' ), $per );
	$ev   = SIS_Views::eventos( SIS_Views::normalizar_args( $args ) );
	$mal  = 0;
	$sinm = 0;
	foreach ( $ev as $e ) {
		if ( ! SIS_Regiones::contiene( 'narino', $e['lat'], $e['lon'] ) ) { $mal++; }
		if ( empty( $e['municipio'] ) || empty( $e['en_narino'] ) ) { $sinm++; }
	}
	$etq = SIS_Periodo::etiqueta( SIS_Periodo::normalizar( $per ) );
	chk( 0 === $mal, "Ningún epicentro de «narino» {$etq} cae fuera del departamento" );
	chk( 0 === $sinm, "Todo epicentro de «narino» {$etq} trae municipio y marca departamental" );
}

// El encabezado nombra el departamento y el periodo, y no habla de otra región.
$v = SIS_Views::obtener( 'sismos_mensuales', array( 'ambito' => 'narino', 'anios' => 8 ) );
chk( false !== mb_strpos( $v['encabezado'], 'departamento de Nariño' ), 'El encabezado sitúa al lector en el departamento' );
chk( false !== mb_strpos( $v['encabezado'], 'últimos 8 años' ), 'El encabezado nombra el periodo consultado' );
chk( false === mb_strpos( $v['encabezado'], 'subducción' ) && false === mb_strpos( $v['encabezado'], 'Colombia y área' ), 'El encabezado del departamento no nombra ámbitos más amplios' );
chk( $v['contexto']['sismos'] === count( SIS_Views::eventos( SIS_Views::normalizar_args( array( 'ambito' => 'narino', 'anios' => 8 ) ) ) ), 'El conteo del encabezado coincide con los datos dibujados' );

// Un periodo vacío se explica, no se deja en blanco.
$vac = SIS_Views::obtener( 'sismos_mensuales', array( 'ambito' => 'narino', 'dias' => 1 ) );
if ( empty( $vac['data'] ) ) {
	chk( '' !== $vac['nota_vacia'], 'Un periodo sin sismos publica su explicación' );
	chk( false !== mb_strpos( $vac['nota_vacia'], 'regional' ), 'Esa explicación ofrece ampliar el ámbito' );
	chk( false !== mb_strpos( $vac['encabezado'], 'No se registró' ), 'El encabezado dice con todas las letras que no hubo sismos' );
	chk( false !== mb_strpos( $vac['analisis']['cuantitativo'], 'No hay sismos' ), 'El análisis cuantitativo tampoco inventa cifras' );
}

// Cada ámbito habla de sí mismo.
foreach ( array( 'regional', 'colombia', 'radio' ) as $amb ) {
	$e = SIS_Views::obtener( 'sismos_mensuales', array( 'ambito' => $amb, 'anios' => 5 ) )['encabezado'];
	chk( false === mb_strpos( $e, 'dentro del departamento' ), "El encabezado de «{$amb}» no se hace pasar por el departamento" );
}

/* ------------------------------------------------------------------ */
echo "\n";
if ( $fallos ) {
	echo "RESULTADO: $fallos prueba(s) fallida(s).\n";
	exit( 1 );
}
echo "RESULTADO: todas las pruebas pasaron.\n";
exit( 0 );
