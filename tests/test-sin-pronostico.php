<?php
/**
 * Garantía de alcance: el plugin NO debe pronosticar sismos.
 *
 * Este test recorre el código publicado y falla si reaparece cualquier rastro
 * del módulo de pronóstico —clases, vistas, rutas, shortcodes o vocabulario de
 * predicción—. Es una salvaguarda deliberada: la decisión de no pronosticar es
 * institucional, no una preferencia de implementación, y debe sobrevivir a
 * futuras contribuciones.
 *
 * Fundamento: la predicción determinística de sismos no es posible (USGS, SGC),
 * y el pronóstico probabilístico de réplicas es competencia del Servicio
 * Geológico Colombiano, que hoy no emite ese producto. Una entidad territorial
 * puede informar y educar citando la fuente oficial, no generar estimaciones
 * propias (Ley 1523 de 2012, proceso de conocimiento del riesgo).
 *
 * Ejecutar con:  php tests/test-sin-pronostico.php
 *
 * @package SismosNarino
 */

error_reporting( E_ALL & ~E_DEPRECATED );

$raiz = dirname( __DIR__ );

/** Identificadores que no deben existir en el código publicado. */
$identificadores = array(
	'SIS_Forecast',
	'class-sis-forecast.php',
	'probabilidad_poisson',
	'intervalo_poisson',
	'holt_amortiguado',
	'componente_replicas',
	'integral_omori',
	'magnitud_maxima_esperada',
	'pronostico_mensual',
	'pronostico_umbrales',
	'sismos_pronostico',
	'sis_guardar_modelo',
	'ajax_recalcular',
);

/**
 * Frases que anunciarían sismos futuros de cara al público.
 *
 * Se acotan a la publicación de una CIFRA asociada a un evento futuro: definir
 * qué es un pronóstico —como hace el glosario— es contenido educativo válido y
 * no debe disparar la salvaguarda.
 */
$frases = array(
	'/sismos esperados/i',
	'/se espera(?:n|rá)?\s+(?:al menos\s+)?\d/i',
	// La probabilidad de EXCEDENCIA en 50 años es la etiqueta oficial de los
	// mapas de amenaza del SGC: es legítima y la plataforma solo la muestra.
	'/probabilidad(?![^.]{0,40}excedencia)[^.]{0,80}\d+(?:[.,]\d+)?\s*%/iu',
	'/\d+(?:[.,]\d+)?\s*%[^.]{0,80}de que ocurra/iu',
	'/\d+(?:[.,]\d+)?\s*%(?![^.]{0,40}excedencia)[^.]{0,60}(?:próximos|siguiente[ns]?)\s+(?:días|semanas|meses|años)/iu',
	'/en los próximos (?:seis|6) meses (?:se|hay|ocurrir)/i',
	'/pronóstico a \d+ meses/i',
);

/**
 * Archivos que sí pueden nombrar el pronóstico, porque documentan por qué no se
 * hace o comprueban precisamente su ausencia.
 */
$exentos = array(
	'tests/test-sin-pronostico.php',
	'tests/test-analisis.php',
	'tests/test-vistas.php',
	'docs/marco-comunicacion-riesgo.md',
	'README.md',
	'CHANGELOG.md',
);

$archivos = array();
$iterador = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $raiz, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterador as $archivo ) {
	$ruta = $archivo->getPathname();
	if ( false !== strpos( $ruta, '/.git/' ) || false !== strpos( $ruta, '/node_modules/' ) ) {
		continue;
	}
	if ( ! preg_match( '/\.(php|js|css|md)$/', $ruta ) ) {
		continue;
	}
	$rel = ltrim( str_replace( $raiz, '', $ruta ), '/' );
	if ( in_array( $rel, $exentos, true ) ) {
		continue;
	}
	$archivos[ $rel ] = file_get_contents( $ruta );
}

echo "Archivos revisados: " . count( $archivos ) . "\n\n";

$fallos = 0;

foreach ( $identificadores as $id ) {
	$donde = array();
	foreach ( $archivos as $rel => $contenido ) {
		if ( false !== strpos( $contenido, $id ) ) {
			$donde[] = $rel;
		}
	}
	if ( empty( $donde ) ) {
		echo "  ok  sin rastro de «{$id}»\n";
	} else {
		echo "FAIL  reaparece «{$id}» en: " . implode( ', ', $donde ) . "\n";
		$fallos++;
	}
}

foreach ( $frases as $patron ) {
	$donde = array();
	foreach ( $archivos as $rel => $contenido ) {
		if ( preg_match( $patron, $contenido ) ) {
			$donde[] = $rel;
		}
	}
	if ( empty( $donde ) ) {
		echo "  ok  ningún texto público coincide con {$patron}\n";
	} else {
		echo "FAIL  texto de pronóstico {$patron} en: " . implode( ', ', $donde ) . "\n";
		$fallos++;
	}
}

// El descargo institucional debe estar presente y ser localizable.
$amenaza = isset( $archivos['includes/data/class-sis-amenaza.php'] ) ? $archivos['includes/data/class-sis-amenaza.php'] : '';
if ( false !== strpos( $amenaza, 'Servicio Geológico Colombiano' ) && false !== strpos( $amenaza, 'no se predicen sismos' ) ) {
	echo "  ok  el descargo institucional sigue publicado\n";
} else {
	echo "FAIL  falta el descargo institucional en SIS_Amenaza\n";
	$fallos++;
}

echo "\n";
if ( $fallos ) {
	echo "RESULTADO: {$fallos} comprobación(es) fallida(s). El plugin no debe pronosticar sismos.\n";
	exit( 1 );
}
echo "RESULTADO: el plugin no contiene módulo de pronóstico.\n";
exit( 0 );
