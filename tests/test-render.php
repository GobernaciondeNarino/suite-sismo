<?php
/**
 * Test de render de los componentes estáticos de amenaza y preparación.
 *
 * Estos shortcodes se renderizan en PHP (no por JS) porque publican textos
 * fijos: así el HTML queda cacheable, indexable y accesible sin JavaScript.
 * La prueba levanta una simulación mínima de WordPress, invoca cada shortcode
 * y comprueba que devuelve HTML válido, completo y sin PHP sin ejecutar.
 *
 * Ejecutar con:  php tests/test-render.php
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
define( 'ABSPATH', 1 );
define( 'SIS_DIR', dirname( __DIR__ ) . '/' );
define( 'SIS_URL', 'https://example.test/wp-content/plugins/sismos-narino/' );
define( 'SIS_VERSION', '2.0.0' );

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
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
function wp_rand() { return 12345; }
function shortcode_atts( $pairs, $atts, $sc = '' ) { $atts = (array) $atts; $out = array(); foreach ( $pairs as $n => $d ) { $out[ $n ] = array_key_exists( $n, $atts ) ? $atts[ $n ] : $d; } return $out; }
function add_action() {} function add_filter() {} function add_shortcode() {}
function wp_enqueue_style() {} function wp_enqueue_script() {}
function wp_register_style() {} function wp_register_script() {} function wp_localize_script() {}
function rest_url( $p = '' ) { return 'https://example.test/wp-json/' . $p; }

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
require SIS_DIR . 'includes/shortcodes/class-sis-shortcodes.php';

use GobernacionNarino\Sismos\SIS_Shortcodes;

$sc = new SIS_Shortcodes();
$fallos = 0;

foreach ( array( 'sc_amenaza', 'sc_glosario', 'sc_preparacion', 'sc_replicas', 'sc_desinformacion', 'sc_fuentes_oficiales' ) as $metodo ) {
	$html = $sc->$metodo( array() );

	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$ok_html = $doc->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>' );
	// libxml usa un parser HTML4: desconoce las etiquetas de HTML5 y avisa de
	// «Tag section invalid». Ese aviso no indica un problema real.
	$errores = array_values( array_filter( libxml_get_errors(), static function ( $e ) {
		return false === strpos( $e->message, 'Tag section invalid' );
	} ) );
	libxml_clear_errors();

	$largo   = strlen( $html );
	$abiertas = substr_count( $html, '<section' );
	$cerradas = substr_count( $html, '</section>' );
	$sin_php  = ( false === strpos( $html, '<?php' ) );

	$bien = $ok_html && $largo > 400 && 1 === $abiertas && 1 === $cerradas && $sin_php && empty( $errores );
	printf( "%s  %-22s %6d bytes · secciones %d/%d · HTML %s\n", $bien ? '  ok ' : 'FAIL', $metodo, $largo, $abiertas, $cerradas, $errores ? 'con avisos' : 'válido' );
	if ( ! $bien ) { $fallos++; foreach ( array_slice( $errores, 0, 3 ) as $e ) { echo '      ' . trim( $e->message ) . "\n"; } }
}

// El atributo «seccion» debe recortar el contenido publicado.
$kit   = strip_tags( $sc->sc_preparacion( array( 'seccion' => 'kit' ) ) );
$todas = strip_tags( $sc->sc_preparacion( array() ) );
if ( false !== mb_strpos( $kit, 'Kit de emergencia' ) && false === mb_strpos( $kit, 'Durante' ) && strlen( $todas ) > strlen( $kit ) ) {
	echo "  ok   el atributo «seccion» recorta la guía de preparación\n";
} else {
	echo "FAIL  el atributo «seccion» no recorta la guía\n";
	$fallos++;
}

// El descargo institucional debe viajar en los componentes de amenaza.
$amenaza = strip_tags( $sc->sc_amenaza( array() ) );
if ( false !== mb_strpos( $amenaza, 'Servicio Geológico Colombiano' ) && false !== mb_strpos( $amenaza, 'no se predicen sismos' ) ) {
	echo "  ok   [sismos_amenaza] publica el descargo institucional\n";
} else {
	echo "FAIL  [sismos_amenaza] no publica el descargo institucional\n";
	$fallos++;
}

// El glosario debe marcar la predicción como imposible.
$glosario = strip_tags( $sc->sc_glosario( array() ) );
if ( false !== mb_strpos( $glosario, 'No es posible' ) ) {
	echo "  ok   [sismos_glosario] marca la predicción como imposible\n";
} else {
	echo "FAIL  [sismos_glosario] no marca la predicción como imposible\n";
	$fallos++;
}

echo "\n";
if ( $fallos ) {
	echo "RESULTADO: {$fallos} componente(s) con problemas.\n";
	exit( 1 );
}
echo "RESULTADO: todos los componentes renderizan correctamente.\n";
exit( 0 );
