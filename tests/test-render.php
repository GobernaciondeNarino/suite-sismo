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
function wp_register_style() {} function wp_register_script() {}
function wp_json_encode( $v ) { return json_encode( $v ); }
function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
function esc_attr_e( $s, $d = null ) { echo esc_attr( $s ); }
$GLOBALS['sis_localizado'] = array();
function wp_localize_script( $handle, $objeto, $datos ) { $GLOBALS['sis_localizado'][ $objeto ] = $datos; }
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
require SIS_DIR . 'includes/data/class-sis-periodo.php';
require SIS_DIR . 'includes/data/class-sis-views.php';
require SIS_DIR . 'includes/class-sis-rest.php';
require SIS_DIR . 'includes/sync/class-sis-sync-usgs.php';
require SIS_DIR . 'includes/sync/class-sis-sync-feed.php';
require SIS_DIR . 'includes/sync/class-sis-sync.php';
require SIS_DIR . 'includes/shortcodes/class-sis-shortcodes.php';

use GobernacionNarino\Sismos\SIS_Shortcodes;
use GobernacionNarino\Sismos\SIS_Regiones;
use GobernacionNarino\Sismos\SIS_Catalogo;
use GobernacionNarino\Sismos\SIS_Sync_Feed;

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

/* ------------------------------------------------------------------ */
/* Globo 3D y línea de tiempo                                          */
/* ------------------------------------------------------------------ */

// El globo publica solo el esqueleto con data-*: los datos llegan por REST.
$globo = $sc->sc_globo( array( 'limite' => '50' ) );

$comprobaciones = array(
	'importmap de three.js'          => false !== strpos( $globo, '<script type="importmap">' ),
	// Las librerías salen del propio plugin: no hay huella de terceros que
	// verificar, pero sí interesa que el módulo se precargue.
	'precarga del módulo local'      => 1 === substr_count( $globo, 'rel="modulepreload"' ) && false !== strpos( $globo, 'assets/vendor/three.module.min.js' ),
	'sin librerías de terceros'      => false === strpos( $globo, 'cdn.jsdelivr.net' ) && false === strpos( $globo, 'unpkg.com' ),
	'gancho data-sis-globo'          => false !== strpos( $globo, 'data-sis-globo' ),
	'lienzo con rol de imagen'       => false !== strpos( $globo, 'sis-globo__lienzo' ) && false !== strpos( $globo, 'role="img"' ),
	'barra de vistas y capas'        => false !== strpos( $globo, 'data-camara="global"' ) && false !== strpos( $globo, 'data-capa="calor"' ),
	'leyenda de magnitud'            => false !== strpos( $globo, 'sis-globo__rampa' ),
	'sin datos incrustados'          => false === strpos( $globo, '"features"' ) && false === strpos( $globo, '<?php' ),
);
foreach ( $comprobaciones as $que => $bien ) {
	printf( "%s  [sismos_globo] %s\n", $bien ? '  ok ' : 'FAIL', $que );
	if ( ! $bien ) { $fallos++; }
}

// El límite se recorta al rango soportado y el ámbito se sanea.
$sc->sc_globo( array( 'limite' => '9000', 'ambito' => 'narino"><script>' ) );
$conf = isset( $GLOBALS['sis_localizado']['SISGLOBO'] ) ? $GLOBALS['sis_localizado']['SISGLOBO'] : array();
$ambito_ok = isset( $conf['ambito'] ) && SIS_Regiones::existe( $conf['ambito'] );
if ( isset( $conf['limite'] ) && 200 === $conf['limite'] && $ambito_ok ) {
	echo "  ok   [sismos_globo] recorta el límite a 200 y descarta un ámbito inválido\n";
} else {
	echo "FAIL  [sismos_globo] no recorta el límite o acepta un ámbito inválido\n";
	$fallos++;
}

// El límite mínimo también se respeta.
$sc->sc_globo( array( 'limite' => '1' ) );
$conf_min = isset( $GLOBALS['sis_localizado']['SISGLOBO'] ) ? $GLOBALS['sis_localizado']['SISGLOBO'] : array();
if ( isset( $conf_min['limite'] ) && 5 === $conf_min['limite'] ) {
	echo "  ok   [sismos_globo] eleva el límite al mínimo de 5\n";
} else {
	echo "FAIL  [sismos_globo] no respeta el límite mínimo\n";
	$fallos++;
}

// Un «alto» arbitrario no debe llegar al atributo style del lienzo.
$sucio = $sc->sc_globo( array( 'alto' => '10px;background:url(javascript:alert(1))' ) );
if ( false !== strpos( $sucio, 'height:70vh' ) && false === stripos( $sucio, 'javascript:' ) ) {
	echo "  ok   [sismos_globo] descarta un «alto» que no sea una medida CSS\n";
} else {
	echo "FAIL  [sismos_globo] acepta un «alto» arbitrario\n";
	$fallos++;
}

// El importmap se imprime una sola vez por página aunque haya varios globos.
$primero = $sc->sc_globo( array() );
if ( false === strpos( $primero, '<script type="importmap">' ) ) {
	echo "  ok   el importmap de three.js se imprime una sola vez por página\n";
} else {
	echo "FAIL  el importmap de three.js se repite en cada globo\n";
	$fallos++;
}

// La barra vive pegada al globo: la marca institucional no se publica salvo
// que alguien la pida.
$tl_def = $sc->sc_timeline( array() );
if ( false !== strpos( $tl_def, 'data-logo=""' ) ) {
	echo "  ok   [sismos_timeline] no publica la marca institucional por defecto\n";
} else {
	echo "FAIL  [sismos_timeline] publica la marca institucional sin que nadie la pida\n";
	$fallos++;
}
$tl_logo = $sc->sc_timeline( array( 'logo' => 'si' ) );
if ( false !== strpos( $tl_logo, 'TIC.png' ) ) {
	echo "  ok   [sismos_timeline logo=\"si\"] sí la publica\n";
} else {
	echo "FAIL  [sismos_timeline logo=\"si\"] no publica la marca\n";
	$fallos++;
}

// La línea de tiempo publica su gancho y su límite recortado.
$tl = $sc->sc_timeline( array( 'limite' => '3' ) );
if ( false !== strpos( $tl, 'data-sis-timeline' ) && false !== strpos( $tl, 'data-limite="5"' ) && false === strpos( $tl, '<?php' ) ) {
	echo "  ok   [sismos_timeline] publica su gancho con el límite recortado\n";
} else {
	echo "FAIL  [sismos_timeline] no publica su gancho o no recorta el límite\n";
	$fallos++;
}

// Con timeline="si" el globo arrastra la línea de tiempo en la misma salida.
$par = $sc->sc_globo( array( 'timeline' => 'si', 'limite' => '25' ) );
if ( false !== strpos( $par, 'data-sis-globo' ) && false !== strpos( $par, 'data-sis-timeline' ) && false !== strpos( $par, 'data-limite="25"' ) ) {
	echo "  ok   [sismos_globo timeline=\"si\"] publica ambos componentes sincronizados\n";
} else {
	echo "FAIL  [sismos_globo timeline=\"si\"] no publica la línea de tiempo\n";
	$fallos++;
}

/* ------------------------------------------------------------------ */
/* Histórico: barras por año + línea mensual con tendencia             */
/* ------------------------------------------------------------------ */

$hist = $sc->sc_historico( array() );

$chequeos_hist = array(
	'publica dos gráficos'          => 2 === substr_count( $hist, 'data-sis-grafico' ),
	'barras de sismos por año'      => false !== strpos( $hist, 'data-view="sismos_anuales"' ) && false !== strpos( $hist, 'data-type="bar"' ),
	'línea del histórico mensual'   => false !== strpos( $hist, 'data-view="historico_mensual"' ) && false !== strpos( $hist, 'data-type="line"' ),
	'recorre todo el catálogo'      => 0 === substr_count( $hist, 'data-anios="5"' ),
	'sin datos incrustados'         => false === strpos( $hist, '"features"' ) && false === strpos( $hist, '<?php' ),
);
foreach ( $chequeos_hist as $que => $bien ) {
	printf( "%s  [sismos_historico] %s\n", $bien ? '  ok ' : 'FAIL', $que );
	if ( ! $bien ) { $fallos++; }
}

// Cada gráfico conserva su propio id, o el segundo pisaría al primero.
if ( preg_match_all( '/<figure id="([^"]+)"/', $hist, $m ) && 2 === count( $m[1] ) && $m[1][0] !== $m[1][1] ) {
	echo "  ok   [sismos_historico] cada gráfico lleva su propio id\n";
} else {
	echo "FAIL  [sismos_historico] los dos gráficos comparten id\n";
	$fallos++;
}

// El título opcional se publica escapado.
$hist_tit = $sc->sc_historico( array( 'titulo' => 'Registro <b>histórico</b>' ) );
if ( false !== strpos( $hist_tit, 'Registro &lt;b&gt;histórico&lt;/b&gt;' ) || false !== strpos( $hist_tit, 'Registro histórico' ) ) {
	echo "  ok   [sismos_historico] el título no inyecta HTML\n";
} else {
	echo "FAIL  [sismos_historico] el título se publica sin sanear\n";
	$fallos++;
}

/* ------------------------------------------------------------------ */
/* Los filtros llegan a todos los componentes                          */
/* ------------------------------------------------------------------ */

/*
 * Cualquier componente que consulte el catálogo tiene que aceptar los cinco
 * atributos de consulta y publicarlos como data-*, porque de ahí los lee el
 * JavaScript para pedir los datos. Si uno se queda fuera, ese componente
 * dibuja otra cosa que el resto de la página.
 */
$componentes = array(
	'sc_grafico', 'sc_estado', 'sc_ultimos', 'sc_mapa', 'sc_estadistica',
	'sc_datos', 'sc_descripcion', 'sc_explicacion',
	'sc_analisis_cualitativo', 'sc_analisis_cuantitativo', 'sc_analisis',
);

$filtro = array( 'ambito' => 'narino', 'dias' => '15' );
foreach ( $componentes as $m ) {
	$html = $sc->$m( $filtro );
	$bien = false !== strpos( $html, 'data-ambito="narino"' ) && false !== strpos( $html, 'data-dias="15"' );
	printf( "%s  [%s] acepta ambito y dias\n", $bien ? '  ok ' : 'FAIL', $m );
	if ( ! $bien ) { $fallos++; }
}

// Los cinco atributos, uno a uno, con el valor que de verdad va a filtrar.
$casos = array(
	array( array( 'ambito' => 'colombia' ),               'data-ambito="colombia"' ),
	array( array( 'dias' => '45' ),                       'data-dias="45"' ),
	array( array( 'anios' => '8' ),                       'data-anios="8"' ),
	array( array( 'anio' => '2019' ),                     'data-anio="2019"' ),
	array( array( 'anio' => '2019', 'mes' => '8' ),       'data-mes="8"' ),
);
foreach ( $casos as $caso ) {
	$html = $sc->sc_grafico( $caso[0] );
	$bien = false !== strpos( $html, $caso[1] );
	printf( "%s  [sismos_grafico %s] publica %s\n", $bien ? '  ok ' : 'FAIL',
		http_build_query( $caso[0], '', ' ' ), $caso[1] );
	if ( ! $bien ) { $fallos++; }
}

/*
 * El data-* que viaja en el HTML es el filtro que de verdad se va a aplicar,
 * no el que escribió quien maquetó: con anio="2020" y dias="15" a la vez, el
 * año manda y los días salen vacíos. Si no fuera así, el atributo prometería
 * un recorte que el servidor no hace.
 */
$mezcla = $sc->sc_grafico( array( 'anio' => '2020', 'dias' => '15', 'anios' => '30' ) );
$coherente = false !== strpos( $mezcla, 'data-anio="2020"' )
	&& false !== strpos( $mezcla, 'data-dias=""' )
	&& false !== strpos( $mezcla, 'data-anios=""' );
printf( "%s  Un año concreto vacía los atributos de ventana móvil\n", $coherente ? '  ok ' : 'FAIL' );
if ( ! $coherente ) { $fallos++; }

// Un valor imposible no se publica.
$sucio = $sc->sc_grafico( array( 'mes' => '99', 'anio' => '1500' ) );
$limpio = false !== strpos( $sucio, 'data-mes=""' ) && false !== strpos( $sucio, 'data-anio=""' );
printf( "%s  Un mes o un año imposibles no llegan al HTML\n", $limpio ? '  ok ' : 'FAIL' );
if ( ! $limpio ) { $fallos++; }

/*
 * Quien ve en redes el boletín del SGC sobre un sismo de magnitud 3 y no lo
 * encuentra aquí concluye que la página está rota. No lo está: el catálogo del
 * USGS no baja de magnitud 4 en Colombia. Los componentes que listan
 * epicentros lo dicen y remiten a la Red Sismológica Nacional.
 */
foreach ( array( 'sc_ultimos', 'sc_estado', 'sc_mapa', 'sc_globo', 'sc_timeline' ) as $m ) {
	$html = $sc->$m( array() );
	$dice = false !== strpos( $html, 'magnitud 4 o mayor' )
		&& false !== strpos( $html, 'sismosgr.sgc.gov.co' );
	printf( "%s  [%s] advierte del umbral de detección del USGS\n", $dice ? '  ok ' : 'FAIL', $m );
	if ( ! $dice ) { $fallos++; }
}

$sin_nota = $sc->sc_ultimos( array( 'nota' => 'no' ) );
$callado  = false === strpos( $sin_nota, 'sismosgr.sgc.gov.co' );
printf( "%s  Con nota=\"no\" el aviso se puede quitar\n", $callado ? '  ok ' : 'FAIL' );
if ( ! $callado ) { $fallos++; }

// El globo con barra de tiempo no repite el aviso ni pierde el periodo: la
// barra tiene que mirar exactamente los mismos sismos que hay sobre el globo.
$con_barra = $sc->sc_globo( array( 'timeline' => 'si', 'dias' => '30', 'ambito' => 'colombia' ) );
$una_vez   = 1 === substr_count( $con_barra, 'sismosgr.sgc.gov.co' );
printf( "%s  El globo con línea de tiempo no repite el aviso\n", $una_vez ? '  ok ' : 'FAIL' );
if ( ! $una_vez ) { $fallos++; }

$mismo = 2 === substr_count( $con_barra, 'data-dias="30"' )
	&& 2 === substr_count( $con_barra, 'data-ambito="colombia"' );
printf( "%s  La línea de tiempo del globo hereda su mismo filtro\n", $mismo ? '  ok ' : 'FAIL' );
if ( ! $mismo ) { $fallos++; }

/*
 * El globo abre mirando el planeta y con los últimos treinta días. Abrirlo
 * encuadrado en Nariño, con los cincuenta sismos más recientes del ámbito
 * —que en el recuadro del departamento pueden remontarse años atrás—, dejaba
 * un planeta casi vacío que se lee como «solo tiembla aquí» y como «no ha
 * pasado nada últimamente». Las dos lecturas son falsas.
 */
$g = $sc->sc_globo( array() );
$abre = false !== strpos( $g, 'data-vista="global"' ) && false !== strpos( $g, 'data-dias="30"' );
printf( "%s  El globo abre en la vista mundial y con los últimos 30 días\n", $abre ? '  ok ' : 'FAIL' );
if ( ! $abre ) { $fallos++; }

$g2 = $sc->sc_globo( array( 'vista' => 'narino', 'anio' => '2019' ) );
$manda = false !== strpos( $g2, 'data-vista="narino"' )
	&& false !== strpos( $g2, 'data-anio="2019"' )
	&& false !== strpos( $g2, 'data-dias=""' );
printf( "%s  La vista y el periodo del globo se pueden cambiar\n", $manda ? '  ok ' : 'FAIL' );
if ( ! $manda ) { $fallos++; }

$g3 = $sc->sc_globo( array( 'vista' => 'inventada' ) );
$sano = false !== strpos( $g3, 'data-vista="global"' );
printf( "%s  Una vista inexistente cae en la mundial\n", $sano ? '  ok ' : 'FAIL' );
if ( ! $sano ) { $fallos++; }

// El conjunto mundial se surte de un feed de resumen de treinta días: con la
// ventana de una semana el Cinturón de Fuego salía a medio dibujar.
$mes = in_array( SIS_Catalogo::FEED_MUNDO, array( '2.5_month', '4.5_month', 'all_month', 'significant_month' ), true );
printf( "%s  El conjunto mundial se surte de un feed de un mes (%s)\n", $mes ? '  ok ' : 'FAIL', SIS_Catalogo::FEED_MUNDO );
if ( ! $mes ) { $fallos++; }

// Y el tope de guardado tiene que admitirlo entero, o se recortarían los más
// antiguos del mes sin decirlo.
$cabe = SIS_Sync_Feed::TOPE_MUNDO >= 2500;
printf( "%s  El tope de guardado admite el mes entero (%d)\n", $cabe ? '  ok ' : 'FAIL', SIS_Sync_Feed::TOPE_MUNDO );
if ( ! $cabe ) { $fallos++; }

/*
 * Con dos mil sismos en pantalla, servir cada uno completo —con municipio de
 * Nariño, subregión, energía en julios y clasificaciones que el globo no
 * dibuja— es más de un megabyte para pintar siete campos. campos=globo
 * entrega solo esos siete.
 */
$rest = new ReflectionClass( 'GobernacionNarino\Sismos\SIS_Rest' );
$adelgazar = $rest->getMethod( 'adelgazar' );
$adelgazar->setAccessible( true );

$semilla = json_decode( file_get_contents( SIS_DIR . 'data/' . SIS_Catalogo::SEMILLA ), true );
$muestra = array_slice( SIS_Catalogo::normalizar( $semilla, array( 'ambito' => 'regional' ) ), -3 );
$ligeros  = $adelgazar->invoke( null, $muestra );

$campos_ok = true;
foreach ( $ligeros as $e ) {
	if ( array_keys( $e ) !== array( 'fecha', 'lat', 'lon', 'lugar', 'mag', 'municipio', 'profundidad' ) ) {
		$campos_ok = false;
	}
}
printf( "%s  campos=globo entrega exactamente los campos que se pintan\n", $campos_ok ? '  ok ' : 'FAIL' );
if ( ! $campos_ok ) { $fallos++; }

$antes   = strlen( wp_json_encode( $muestra ) );
$despues = strlen( wp_json_encode( $ligeros ) );
$aligera = $despues < $antes / 2;
printf( "%s  y pesa menos de la mitad (%d B → %d B)\n", $aligera ? '  ok ' : 'FAIL', $antes, $despues );
if ( ! $aligera ) { $fallos++; }

// Lo que se recorta tiene que ser solo peso, nunca dato que se dibuje: los
// valores de los siete campos deben llegar intactos.
$intactos = true;
foreach ( $muestra as $i => $e ) {
	foreach ( array( 'fecha', 'lat', 'lon', 'lugar', 'mag', 'municipio', 'profundidad' ) as $k ) {
		if ( $e[ $k ] !== $ligeros[ $i ][ $k ] ) { $intactos = false; }
	}
}
printf( "%s  sin alterar ninguno de sus valores\n", $intactos ? '  ok ' : 'FAIL' );
if ( ! $intactos ) { $fallos++; }

echo "\n";
if ( $fallos ) {
	echo "RESULTADO: {$fallos} componente(s) con problemas.\n";
	exit( 1 );
}
echo "RESULTADO: todos los componentes renderizan correctamente.\n";
exit( 0 );
