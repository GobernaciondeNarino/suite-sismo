<?php
/**
 * Pruebas de seguridad de las defensas del plugin.
 *
 * Comprueban las medidas que protegen las «puertas» del plugin: qué puede
 * invocar un visitante anónimo, qué puede alcanzar el servidor cuando sale a
 * la red, cómo se escapa la salida y qué se publica de la instalación.
 *
 * Ejecutar con:  php tests/test-seguridad.php
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

define( 'ABSPATH', __DIR__ . '/' );
define( 'SIS_DIR', dirname( __DIR__ ) . '/' );
define( 'SIS_URL', '/' );
define( 'SIS_VERSION', '2.0.0-test' );

/* Stubs mínimos de WordPress. */
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function esc_url_raw( $s ) { return (string) $s; }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_textarea( $s ) { return esc_html( $s ); }
function __( $s, $d = null ) { return $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function remove_accents( $s ) { return $s; }
function wp_parse_url( $u ) { return parse_url( $u ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function wp_list_pluck( $l, $c ) { $o = array(); foreach ( (array) $l as $f ) { if ( isset( $f[ $c ] ) ) { $o[] = $f[ $c ]; } } return $o; }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d, ',', '.' ); }
function get_option( $k, $def = false ) { return $def; }
function wp_rand() { return 1; }
function shortcode_atts( $p, $a, $sc = '' ) { $a = (array) $a; $o = array(); foreach ( $p as $n => $d ) { $o[ $n ] = array_key_exists( $n, $a ) ? $a[ $n ] : $d; } return $o; }
function add_action() {} function add_filter() {} function add_shortcode() {}
function wp_enqueue_style() {} function wp_enqueue_script() {}
function wp_register_style() {} function wp_register_script() {} function wp_localize_script() {}
function rest_url( $p = '' ) { return '/wp-json/' . $p; }
function add_query_arg( $args, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }

foreach ( array(
	'includes/class-sis-security.php', 'includes/class-sis-estilos.php', 'includes/class-sis-activator.php',
	'includes/data/class-sis-municipios.php', 'includes/data/class-sis-regiones.php', 'includes/data/class-sis-amenaza.php',
	'includes/analysis/class-sis-catalogo.php', 'includes/analysis/class-sis-estadistica.php', 'includes/analysis/class-sis-texto.php',
	'includes/data/class-sis-views.php', 'includes/class-sis-rest.php',
	'includes/sync/class-sis-sync-usgs.php', 'includes/sync/class-sis-sync-feed.php', 'includes/sync/class-sis-sync.php',
	'includes/shortcodes/class-sis-shortcodes.php',
) as $f ) {
	require SIS_DIR . $f;
}

use GobernacionNarino\Sismos\SIS_Estilos;
use GobernacionNarino\Sismos\SIS_Security;
use GobernacionNarino\Sismos\SIS_Shortcodes;
use GobernacionNarino\Sismos\SIS_Sync_Usgs;

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
seccion( 'Acceso directo por URL' );

$sin_guarda = array();
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( SIS_DIR, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $archivo ) {
	$ruta = $archivo->getPathname();
	if ( false !== strpos( $ruta, '/.git/' ) || false !== strpos( $ruta, '/.claude/' ) ) {
		continue;
	}
	if ( ! preg_match( '/\.php$/', $ruta ) ) {
		continue;
	}
	$contenido = file_get_contents( $ruta );
	$protegido = false !== strpos( $contenido, "defined( 'ABSPATH' ) || exit" )
		|| false !== strpos( $contenido, "defined( 'WP_UNINSTALL_PLUGIN' ) || exit" )
		|| false !== strpos( $contenido, "'cli' !== PHP_SAPI" );
	if ( ! $protegido ) {
		$sin_guarda[] = ltrim( str_replace( SIS_DIR, '', $ruta ), '/' );
	}
}
chk( empty( $sin_guarda ), 'Todo archivo PHP bloquea la ejecución directa' . ( $sin_guarda ? ' (falta en: ' . implode( ', ', $sin_guarda ) . ')' : '' ) );

$dirs = array( 'includes', 'includes/data', 'includes/analysis', 'includes/sync', 'includes/admin', 'includes/shortcodes', 'assets', 'assets/js', 'assets/css', 'data', 'tests' );
$faltan = array();
foreach ( $dirs as $d ) {
	if ( ! file_exists( SIS_DIR . $d . '/index.php' ) ) {
		$faltan[] = $d;
	}
}
chk( empty( $faltan ), 'Cada directorio tiene index.php silenciador' . ( $faltan ? ' (falta en: ' . implode( ', ', $faltan ) . ')' : '' ) );

/* ------------------------------------------------------------------ */
seccion( 'Peticiones salientes del servidor (SSRF)' );

$permitidas = array(
	'https://earthquake.usgs.gov/fdsnws/event/1/query',
	'https://srvags.sgc.gov.co/x',
);
$bloqueadas = array(
	'http://earthquake.usgs.gov/x',                       // sin TLS
	'https://earthquake.usgs.gov.evil.tld/x',             // sufijo engañoso
	'https://evil.tld/?x=earthquake.usgs.gov',            // host en la query
	'https://127.0.0.1/latest/meta-data/',                // red interna
	'https://169.254.169.254/latest/meta-data/',          // metadatos de nube
	'file:///etc/passwd',
	'gopher://127.0.0.1:11211/',
	'//earthquake.usgs.gov/x',
	'https://user@evil.tld:443/x',
);
$ok = true;
foreach ( $permitidas as $u ) {
	if ( ! SIS_Security::url_permitida( $u ) ) { $ok = false; echo "      rechazó una URL legítima: $u\n"; }
}
chk( $ok, 'Las URL de los servicios oficiales pasan la lista blanca' );

$ok = true;
foreach ( $bloqueadas as $u ) {
	if ( SIS_Security::url_permitida( $u ) ) { $ok = false; echo "      ACEPTÓ una URL que debía bloquear: $u\n"; }
}
chk( $ok, 'Se rechazan hosts ajenos, redes internas y esquemas peligrosos' );

// La URL de consulta se construye desde el catálogo de ámbitos, no de la entrada.
$url = SIS_Sync_Usgs::construir_url( SIS_Sync_Usgs::URL, '"><script>', 36, 2.5 );
chk( SIS_Security::url_permitida( $url ), 'La URL construida sigue apuntando al servicio permitido' );
chk( false === strpos( $url, '<' ) && false === strpos( $url, '"' ), 'La URL construida no arrastra caracteres de la entrada' );

/* ------------------------------------------------------------------ */
seccion( 'Saneamiento de entradas' );

chk( 'regional' === SIS_Security::sanitizar_ambito( '../../etc/passwd' ), 'Un ámbito inventado cae al de por defecto' );
chk( 10.0 === SIS_Security::sanitizar_magnitud( 999 ), 'La magnitud se acota por arriba' );
chk( 0.0 === SIS_Security::sanitizar_magnitud( -5 ), 'La magnitud se acota por abajo' );
chk( 20000 === SIS_Security::sanitizar_dias( 99999999 ), 'Los días se acotan al máximo' );
chk( '' === SIS_Security::sanitizar_fecha( '2026-13-45' ), 'Una fecha imposible se descarta' );
chk( 'departamento' === SIS_Security::sanitizar_divipola( '99999' ), 'Un DIVIPOLA inexistente cae al agregado departamental' );

/* ------------------------------------------------------------------ */
seccion( 'Salida a CSS y a HTML' );

$payloads = array(
	'red;} body{display:none',
	'url(javascript:alert(1))',
	'expression(alert(1))',
	'"><script>alert(1)</script>',
	"red' onload='alert(1)",
	'var(--x); @import "//evil.tld"',
	"red\n\r;color:blue",
);
$ok = true;
foreach ( $payloads as $x ) {
	$limpio = SIS_Estilos::sanitizar_css( $x );
	if ( preg_match( '/[;{}<>"\']|url\s*\(|expression|@import|javascript:|data:/i', $limpio ) ) {
		$ok = false;
		echo "      quedó peligroso: $limpio\n";
	}
}
chk( $ok, 'El saneador de CSS neutraliza cierres de regla, funciones y esquemas' );

$sc   = new SIS_Shortcodes();
$html = $sc->sc_grafico( array(
	'view'   => '"><script>alert(1)</script>',
	'titulo' => '</figcaption><img src=x onerror=alert(1)>',
	'fondo'  => 'red;} body{x:y',
	'alto'   => '420px" onload="alert(1)',
) );

$doc = new DOMDocument();
libxml_use_internal_errors( true );
$doc->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>' );
libxml_clear_errors();

$manejadores = 0;
foreach ( $doc->getElementsByTagName( '*' ) as $n ) {
	foreach ( $n->attributes as $a ) {
		if ( 0 === stripos( $a->name, 'on' ) ) {
			$manejadores++;
		}
	}
}
chk( 0 === $doc->getElementsByTagName( 'script' )->length, 'Un atributo con <script> no crea un script' );
chk( 0 === $doc->getElementsByTagName( 'img' )->length, 'Un atributo con <img> no crea una imagen' );
chk( 0 === $manejadores, 'No se cuela ningún manejador on*' );
chk( false !== strpos( $html, '--sis-g-alto:420px' ), 'Una altura con comillas cae al valor por defecto' );

/* ------------------------------------------------------------------ */
seccion( 'Exportación a CSV' );

$rest = new ReflectionClass( 'GobernacionNarino\Sismos\SIS_Rest' );
$m    = $rest->getMethod( 'celda_csv' );
$m->setAccessible( true );
$inst = $rest->newInstanceWithoutConstructor();

$formulas = array( '=1+1', '+1', '-1+1', '@SUM(A1)', "\t=1", "\r=1", '=cmd|\' /C calc\'!A0' );
$ok = true;
foreach ( $formulas as $f ) {
	$salida = $m->invoke( $inst, $f );
	if ( 0 !== strpos( $salida, "'" ) ) {
		$ok = false;
		echo "      sin neutralizar: " . var_export( $f, true ) . " → " . var_export( $salida, true ) . "\n";
	}
}
chk( $ok, 'Las celdas que empiezan por =, +, -, @, tabulador o retorno se neutralizan' );
chk( '46 km NW of Manta' === $m->invoke( $inst, '46 km NW of Manta' ), 'Un valor normal no se altera' );

/* ------------------------------------------------------------------ */
seccion( 'Integridad de las librerías por CDN' );

foreach ( SIS_Shortcodes::SRI as $handle => $hash ) {
	chk( (bool) preg_match( '/^sha(256|384|512)-[A-Za-z0-9+\/=]{20,}$/', $hash ), "El script «{$handle}» declara huella SRI válida" );
}
foreach ( SIS_Shortcodes::SRI_CSS as $handle => $hash ) {
	chk( (bool) preg_match( '/^sha(256|384|512)-[A-Za-z0-9+\/=]{20,}$/', $hash ), "La hoja «{$handle}» declara huella SRI válida" );
}

$tag  = '<script src="https://cdn.jsdelivr.net/npm/d3plus@2.0.0/build/d3plus.full.min.js" id="d3plus-js"></script>';
$sale = $sc->integridad_script( $tag, 'd3plus' );
chk( false !== strpos( $sale, 'integrity="sha384-' ) && false !== strpos( $sale, 'crossorigin="anonymous"' ), 'El filtro inyecta integrity y crossorigin' );
chk( $sc->integridad_script( $tag, 'otro-handle' ) === $tag, 'Los scripts propios no se tocan' );

foreach ( SIS_Shortcodes::THREE_SRI as $ruta => $hash ) {
	chk( (bool) preg_match( '/^sha(256|384|512)-[A-Za-z0-9+\/=]{20,}$/', $hash ), "El módulo «{$ruta}» declara huella SRI válida" );
}

// Un módulo ES no acepta el atributo integrity en su etiqueta <script>: la
// huella viaja en el <link rel="modulepreload"> que el importmap imprime antes.
$mimp = ( new ReflectionClass( 'GobernacionNarino\Sismos\SIS_Shortcodes' ) )->getMethod( 'importmap_three' );
$mimp->setAccessible( true );
$importmap = $mimp->invoke( $sc );
chk( count( SIS_Shortcodes::THREE_SRI ) === substr_count( $importmap, 'rel="modulepreload"' ), 'Cada módulo de three.js se precarga con su huella' );
chk( count( SIS_Shortcodes::THREE_SRI ) === substr_count( $importmap, 'integrity="sha384-' ), 'Todas las precargas llevan integrity' );
chk( count( SIS_Shortcodes::THREE_SRI ) === substr_count( $importmap, 'crossorigin="anonymous"' ), 'Todas las precargas llevan crossorigin' );
chk( false !== strpos( $importmap, '<script type="importmap">' ), 'El importmap se publica antes del módulo' );
chk( 2 === substr_count( $importmap, 'https://cdn.jsdelivr.net/npm/three@' . SIS_Shortcodes::THREE_VERSION . '/' ), 'Los módulos se piden a la versión fijada de three.js' );

$modulo = $sc->marcar_modulo( '<script src="https://example.test/globo.js" id="sis-globo-js"></script>', 'sis-globo', 'https://example.test/globo.js' );
chk( false !== strpos( $modulo, 'type="module"' ), 'El globo se carga como módulo ES' );
chk( $sc->marcar_modulo( $tag, 'otro-handle', 'x' ) === $tag, 'Los demás scripts no se convierten en módulo' );

/* ------------------------------------------------------------------ */
seccion( 'Información que se publica de la instalación' );

$reflex = new ReflectionClass( 'GobernacionNarino\Sismos\SIS_Sync' );
$rp     = $reflex->getMethod( 'resultado_publico' );
$rp->setAccessible( true );

chk( 'ERROR' === $rp->invoke( null, 'ERROR · 0 reg · 12 ms · HTTP 500 Connection refused to 10.0.0.5' ), 'Un error no publica el detalle interno' );
chk( 'OK · 1144 registros' === $rp->invoke( null, 'OK · 1144 reg · 2410 ms' ), 'Un resultado correcto publica solo el conteo' );
chk( '' === $rp->invoke( null, '' ), 'Sin sincronización previa no se inventa nada' );

/* ------------------------------------------------------------------ */
echo "\n";
if ( $fallos ) {
	echo "RESULTADO: $fallos comprobación(es) de seguridad fallida(s).\n";
	exit( 1 );
}
echo "RESULTADO: todas las comprobaciones de seguridad pasaron.\n";
exit( 0 );
