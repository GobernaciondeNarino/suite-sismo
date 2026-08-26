<?php
/**
 * Test de la pantalla «Elementos» del panel.
 *
 * Esa pantalla es el catálogo que usa quien maqueta el sitio: si una gráfica
 * no aparece en ninguna pestaña, o aparece sin sus shortcodes de texto, deja
 * de existir en la práctica aunque el motor la calcule. La prueba comprueba
 * que las cuatro pestañas se pintan, que cada elemento cae en una y solo una,
 * y que toda vista publica sus cuatro shortcodes listos para copiar.
 *
 * Ejecutar con:  php tests/test-panel.php
 *
 * @package SismosNarino
 */

if ( 'cli' !== PHP_SAPI || isset( $_SERVER['REQUEST_METHOD'] ) ) {
	if ( ! headers_sent() ) {
		header( 'Content-Type: text/plain; charset=utf-8' );
		http_response_code( 403 );
	}
	exit( 'Este archivo es una prueba y solo se ejecuta por linea de comandos.' );
}

error_reporting( E_ALL & ~E_DEPRECATED );
define( 'ABSPATH', 1 );
define( 'SIS_DIR', dirname( __DIR__ ) . '/' );
define( 'SIS_URL', 'https://example.test/wp-content/plugins/sismos-narino/' );
define( 'SIS_VERSION', '0.0-test' );
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }

/* Simulación mínima de WordPress. */
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return esc_html( $s ); }
function esc_url_raw( $s ) { return (string) $s; }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
function esc_attr_e( $s, $d = null ) { echo esc_attr( $s ); }
function esc_textarea( $s ) { return esc_html( $s ); }
function __( $s, $d = null ) { return $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function remove_accents( $s ) { return $s; }
function wp_unslash( $s ) { return $s; }
function wp_parse_url( $u ) { return parse_url( $u ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_list_pluck( $l, $c ) { $o = array(); foreach ( (array) $l as $f ) { if ( isset( $f[ $c ] ) ) { $o[] = $f[ $c ]; } } return $o; }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d, ',', '.' ); }
function current_time( $t, $gmt = 0 ) { return gmdate( 'Y-m-d H:i:s' ); }
function add_query_arg( $a, $u ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . http_build_query( $a ); }
function get_option( $k, $def = false ) { return $def; }
function update_option( $k, $v ) { return true; }
function add_action() {} function add_filter() {} function add_shortcode() {}
function current_user_can( $c ) { return true; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function rest_url( $p = '' ) { return 'https://example.test/wp-json/' . $p; }
function wp_enqueue_style() {} function wp_enqueue_script() {} function wp_localize_script() {}
function wp_register_style() {} function wp_register_script() {}
function wp_create_nonce( $a ) { return 'nonce'; }
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $t ) { return true; }
function delete_transient( $k ) { return true; }

$GLOBALS['wpdb'] = new class {
	public $prefix = 'wp_';
	public function get_var() { return null; }
	public function get_row() { return null; }
	public function get_results() { return array(); }
	public function query() { return 0; }
	public function replace() { return 1; }
	public function delete() { return 1; }
	public function prepare( $q ) { return $q; }
	public function esc_like( $s ) { return $s; }
};

require SIS_DIR . 'includes/class-sis-cache.php';
require SIS_DIR . 'includes/class-sis-security.php';
require SIS_DIR . 'includes/class-sis-estilos.php';
require SIS_DIR . 'includes/class-sis-activator.php';
require SIS_DIR . 'includes/data/class-sis-municipios.php';
require SIS_DIR . 'includes/data/class-sis-regiones.php';
require SIS_DIR . 'includes/data/class-sis-amenaza.php';
require SIS_DIR . 'includes/analysis/class-sis-catalogo.php';
require SIS_DIR . 'includes/analysis/class-sis-estadistica.php';
require SIS_DIR . 'includes/analysis/class-sis-texto.php';
require SIS_DIR . 'includes/data/class-sis-views.php';
require SIS_DIR . 'includes/class-sis-rest.php';
require SIS_DIR . 'includes/sync/class-sis-sync-usgs.php';
require SIS_DIR . 'includes/sync/class-sis-sync-feed.php';
require SIS_DIR . 'includes/sync/class-sis-sync.php';
require SIS_DIR . 'includes/admin/class-sis-admin.php';

use GobernacionNarino\Sismos\SIS_Admin;
use GobernacionNarino\Sismos\SIS_Views;

$fallos = 0;
function chk( $cond, $msg ) {
	global $fallos;
	if ( $cond ) {
		echo "  ok  {$msg}\n";
		return;
	}
	echo "FAIL  {$msg}\n";
	$fallos++;
}
function seccion( $t ) { echo "\n== {$t} ==\n"; }

$admin  = new SIS_Admin();
$reflex = new ReflectionClass( 'GobernacionNarino\Sismos\SIS_Admin' );
$render = $reflex->getMethod( 'pantalla_elementos' );

$mp = $reflex->getMethod( 'pestanas' );
$mp->setAccessible( true );
$pestanas = $mp->invoke( null );

$mv = $reflex->getMethod( 'pestana_vistas' );
$mv->setAccessible( true );
$mapa_vistas = $mv->invoke( null );

/* ------------------------------------------------------------------ */
seccion( 'Pestañas' );

chk( 4 === count( $pestanas ), 'La pantalla declara cuatro pestañas' );
foreach ( array( 'graficas', 'historicas', 'globo', 'texto' ) as $slug ) {
	chk( isset( $pestanas[ $slug ] ), "Existe la pestaña «{$slug}»" );
}

$html = array();
foreach ( array_keys( $pestanas ) as $slug ) {
	$_GET['tab'] = $slug;
	ob_start();
	$render->invoke( $admin );
	$html[ $slug ] = ob_get_clean();
}

foreach ( $html as $slug => $h ) {
	// «nav-tab-wrapper» también empieza por nav-tab: se cuentan los enlaces.
	chk( 4 === substr_count( $h, '<a class="nav-tab' ), "La pestaña «{$slug}» pinta la barra completa" );
	chk( 1 === substr_count( $h, 'nav-tab-active' ), "La pestaña «{$slug}» marca una sola activa" );
	chk( false !== strpos( $h, 'tab=' . $slug . '"' ) , "La pestaña «{$slug}» enlaza a sí misma" );
	chk( false === strpos( $h, '<?php' ), "La pestaña «{$slug}» no filtra PHP sin ejecutar" );
}

// Una pestaña inventada no debe romper nada: cae en la primera.
$_GET['tab'] = 'inexistente";><script>';
ob_start();
$render->invoke( $admin );
$sucio = ob_get_clean();
chk( false === strpos( $sucio, '<script>' ), 'Una pestaña inventada no inyecta HTML' );
chk( false !== strpos( $sucio, 'tab=graficas" aria-current' ) || false !== strpos( $sucio, 'nav-tab-active' ), 'Una pestaña inventada cae en la primera' );

/* ------------------------------------------------------------------ */
seccion( 'Reparto de los componentes' );

$elementos = include SIS_DIR . 'includes/data/textos-elementos.php';
$sin_grupo = array();
$conteo    = array();
foreach ( $elementos as $el ) {
	if ( empty( $el['grupo'] ) ) {
		$sin_grupo[] = $el['shortcode'];
		continue;
	}
	$conteo[ $el['grupo'] ] = ( isset( $conteo[ $el['grupo'] ] ) ? $conteo[ $el['grupo'] ] : 0 ) + 1;
}
chk( ! $sin_grupo, 'Todo componente declara su pestaña' . ( $sin_grupo ? ': ' . implode( ', ', $sin_grupo ) : '' ) );
foreach ( array_keys( $conteo ) as $g ) {
	chk( isset( $pestanas[ $g ] ), "El grupo «{$g}» corresponde a una pestaña real" );
}
chk( count( $elementos ) === array_sum( $conteo ), 'Cada componente cae en una y solo una pestaña' );

/* ------------------------------------------------------------------ */
seccion( 'Tarjetas por gráfica' );

$vistas = SIS_Views::lista();
$tarjetas_totales = substr_count( $html['graficas'], 'class="sis-card"' ) + substr_count( $html['historicas'], 'class="sis-card"' );
chk( count( $vistas ) === $tarjetas_totales, 'Hay una tarjeta por vista, ni más ni menos (' . count( $vistas ) . ')' );

chk( 0 === substr_count( $html['globo'], 'class="sis-card"' ), 'La pestaña del globo no inventa tarjetas de gráfica' );
chk( 0 === substr_count( $html['texto'], 'class="sis-card"' ), 'La pestaña de información no inventa tarjetas de gráfica' );

// Cada vista, en su pestaña, con sus cuatro shortcodes y el combinado.
$todo = $html['graficas'] . $html['historicas'];
foreach ( $vistas as $v ) {
	$id = $v['id'];
	$esperados = array(
		'[sismos_grafico view="' . $id . '" type="' . $v['default'] . '"]',
		'[sismos_descripcion view="' . $id . '"]',
		'[sismos_analisis_cualitativo view="' . $id . '"]',
		'[sismos_analisis_cuantitativo view="' . $id . '"]',
		'[sismos_grafico view="' . $id . '" analisis="ambos"]',
	);
	$faltan = array();
	foreach ( $esperados as $sc ) {
		if ( false === strpos( $todo, esc_attr( $sc ) ) ) { $faltan[] = $sc; }
	}
	chk( ! $faltan, "«{$id}» publica sus cinco shortcodes copiables" . ( $faltan ? ' — falta ' . $faltan[0] : '' ) );
}

// El mapa de pestañas no puede citar vistas que ya no existen.
$ids = wp_list_pluck( $vistas, 'id' );
$huerfanas = array_diff( array_keys( $mapa_vistas ), $ids );
chk( ! $huerfanas, 'El reparto de vistas no cita vistas inexistentes' . ( $huerfanas ? ': ' . implode( ', ', $huerfanas ) : '' ) );

/* ------------------------------------------------------------------ */
echo "\n";
if ( $fallos ) {
	echo "RESULTADO: {$fallos} comprobación(es) del panel fallida(s).\n";
	exit( 1 );
}
echo "RESULTADO: la pantalla de elementos publica todo el catálogo.\n";
exit( 0 );
