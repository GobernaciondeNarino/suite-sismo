<?php
/**
 * Ámbitos espaciales y escalas del dominio sísmico.
 *
 * El catálogo global del USGS es completo en Colombia a partir de M≈4,5: el
 * recuadro estricto del departamento aporta pocas decenas de eventos, mientras
 * que la zona de subducción Nazca–Sudamérica (frente al Pacífico nariñense y el
 * norte de Ecuador) es la que gobierna la amenaza sísmica de Nariño. Por eso el
 * plugin declara varios ámbitos: el departamental para la lectura territorial y
 * el regional —por defecto— para la estadística y el pronóstico.
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Regiones {

	/** Ámbito por defecto para estadística y pronóstico. */
	const DEFECTO = 'regional';

	/**
	 * Catálogo de ámbitos espaciales.
	 *
	 * Cada ámbito declara un recuadro (bbox) o un círculo (centro + radio),
	 * traducibles directamente a parámetros del servicio FDSN del USGS.
	 *
	 * @return array<string,array>
	 */
	public static function todos() {
		return array(
			'narino'   => array(
				'nombre'      => 'Departamento de Nariño',
				'descripcion' => 'Recuadro estricto del departamento (64 municipios). Sismicidad sentida dentro del territorio; con el umbral de completitud del catálogo global el número de eventos es bajo.',
				'tipo'        => 'bbox',
				'lat_min'     => 0.35,
				'lat_max'     => 2.70,
				'lon_min'     => -79.10,
				'lon_max'     => -76.85,
			),
			'regional' => array(
				'nombre'      => 'Nariño y zona de subducción vecina',
				'descripcion' => 'Suroccidente de Colombia y norte de Ecuador, incluida la fosa Nazca–Sudamérica frente al Pacífico nariñense. Es el dominio que gobierna la amenaza sísmica y de tsunami del departamento.',
				'tipo'        => 'bbox',
				'lat_min'     => -1.50,
				'lat_max'     => 4.00,
				'lon_min'     => -81.50,
				'lon_max'     => -75.50,
			),
			'radio'    => array(
				'nombre'      => 'Radio de 300 km alrededor de Pasto',
				'descripcion' => 'Círculo centrado en San Juan de Pasto. Útil para leer la sismicidad que puede sentirse en la capital departamental.',
				'tipo'        => 'radio',
				'lat'         => 1.21361,
				'lon'         => -77.28111,
				'radio_km'    => 300,
			),
			'colombia' => array(
				'nombre'      => 'Colombia y área de influencia',
				'descripcion' => 'Territorio nacional y márgenes vecinos. Sirve de referencia comparativa para situar a Nariño dentro de la sismicidad del país.',
				'tipo'        => 'bbox',
				'lat_min'     => -4.50,
				'lat_max'     => 13.50,
				'lon_min'     => -82.00,
				'lon_max'     => -66.00,
			),
		);
	}

	/**
	 * Slug del ámbito por defecto.
	 *
	 * @return string
	 */
	public static function por_defecto() {
		return self::DEFECTO;
	}

	/**
	 * ¿Existe el ámbito?
	 *
	 * @param string $slug Slug del ámbito.
	 * @return bool
	 */
	public static function existe( $slug ) {
		$t = self::todos();
		return isset( $t[ (string) $slug ] );
	}

	/**
	 * Devuelve un ámbito (o el de por defecto si no existe).
	 *
	 * @param string $slug Slug del ámbito.
	 * @return array
	 */
	public static function obtener( $slug ) {
		$t    = self::todos();
		$slug = (string) $slug;
		if ( ! isset( $t[ $slug ] ) ) {
			$slug = self::DEFECTO;
		}
		$a         = $t[ $slug ];
		$a['slug'] = $slug;
		return $a;
	}

	/**
	 * Traduce un ámbito a parámetros del servicio FDSN Event del USGS.
	 *
	 * @param string $slug Slug del ámbito.
	 * @return array Parámetros de consulta (bbox o círculo).
	 */
	public static function parametros_fdsn( $slug ) {
		$a = self::obtener( $slug );

		if ( 'radio' === $a['tipo'] ) {
			return array(
				'latitude'    => $a['lat'],
				'longitude'   => $a['lon'],
				'maxradiuskm' => $a['radio_km'],
			);
		}

		return array(
			'minlatitude'  => $a['lat_min'],
			'maxlatitude'  => $a['lat_max'],
			'minlongitude' => $a['lon_min'],
			'maxlongitude' => $a['lon_max'],
		);
	}

	/**
	 * ¿El epicentro cae dentro del ámbito? (filtro local para los feeds
	 * globales, que llegan sin recorte geográfico).
	 *
	 * @param string $slug Slug del ámbito.
	 * @param float  $lat  Latitud.
	 * @param float  $lon  Longitud.
	 * @return bool
	 */
	public static function contiene( $slug, $lat, $lon ) {
		if ( ! SIS_Security::validar_coordenada( $lat, $lon ) ) {
			return false;
		}
		$a   = self::obtener( $slug );
		$lat = (float) $lat;
		$lon = (float) $lon;

		if ( 'radio' === $a['tipo'] ) {
			$d = SIS_Municipios::distancia_km( $a['lat'], $a['lon'], $lat, $lon );
			return $d <= (float) $a['radio_km'];
		}

		return $lat >= $a['lat_min'] && $lat <= $a['lat_max']
			&& $lon >= $a['lon_min'] && $lon <= $a['lon_max'];
	}

	/**
	 * Lista compacta de ámbitos para el front y el panel de administración.
	 *
	 * @return array[]
	 */
	public static function lista() {
		$out = array();
		foreach ( self::todos() as $slug => $a ) {
			$fila = array(
				'slug'        => $slug,
				'nombre'      => $a['nombre'],
				'descripcion' => $a['descripcion'],
				'tipo'        => $a['tipo'],
			);

			// La geometría viaja al navegador para que los componentes en vivo
			// puedan recortar por su cuenta el feed global del USGS.
			if ( 'radio' === $a['tipo'] ) {
				$fila['lat']      = $a['lat'];
				$fila['lon']      = $a['lon'];
				$fila['radio_km'] = $a['radio_km'];
			} else {
				$fila['lat_min'] = $a['lat_min'];
				$fila['lat_max'] = $a['lat_max'];
				$fila['lon_min'] = $a['lon_min'];
				$fila['lon_max'] = $a['lon_max'];
			}

			$out[] = $fila;
		}
		return $out;
	}

	/* ================================================================= */
	/* Escalas del dominio                                               */
	/* ================================================================= */

	/**
	 * Rangos de profundidad focal (clasificación sismológica estándar).
	 *
	 * @return array[] {clave, nombre, min, max}
	 */
	public static function rangos_profundidad() {
		return array(
			array( 'clave' => 'superficial', 'nombre' => 'Superficial (0–70 km)', 'min' => 0, 'max' => 70 ),
			array( 'clave' => 'intermedio', 'nombre' => 'Intermedio (70–300 km)', 'min' => 70, 'max' => 300 ),
			array( 'clave' => 'profundo', 'nombre' => 'Profundo (más de 300 km)', 'min' => 300, 'max' => 1000 ),
		);
	}

	/**
	 * Clasifica una profundidad focal.
	 *
	 * @param float $km Profundidad en kilómetros.
	 * @return string Nombre legible del rango.
	 */
	public static function clasificar_profundidad( $km ) {
		$km = (float) $km;
		foreach ( self::rangos_profundidad() as $r ) {
			if ( $km >= $r['min'] && $km < $r['max'] ) {
				return $r['nombre'];
			}
		}
		return 'Profundo (más de 300 km)';
	}

	/**
	 * Clases de magnitud (nomenclatura USGS).
	 *
	 * @return array[] {clave, nombre, min, max}
	 */
	public static function clases_magnitud() {
		return array(
			array( 'clave' => 'micro', 'nombre' => 'Micro (menor que 3,0)', 'min' => -1.0, 'max' => 3.0 ),
			array( 'clave' => 'menor', 'nombre' => 'Menor (3,0–3,9)', 'min' => 3.0, 'max' => 4.0 ),
			array( 'clave' => 'ligero', 'nombre' => 'Ligero (4,0–4,9)', 'min' => 4.0, 'max' => 5.0 ),
			array( 'clave' => 'moderado', 'nombre' => 'Moderado (5,0–5,9)', 'min' => 5.0, 'max' => 6.0 ),
			array( 'clave' => 'fuerte', 'nombre' => 'Fuerte (6,0–6,9)', 'min' => 6.0, 'max' => 7.0 ),
			array( 'clave' => 'mayor', 'nombre' => 'Mayor (7,0–7,9)', 'min' => 7.0, 'max' => 8.0 ),
			array( 'clave' => 'grande', 'nombre' => 'Grande (8,0 o más)', 'min' => 8.0, 'max' => 12.0 ),
		);
	}

	/**
	 * Clasifica una magnitud.
	 *
	 * @param float $m Magnitud.
	 * @return string Nombre legible de la clase.
	 */
	public static function clasificar_magnitud( $m ) {
		$m = (float) $m;
		foreach ( self::clases_magnitud() as $c ) {
			if ( $m >= $c['min'] && $m < $c['max'] ) {
				return $c['nombre'];
			}
		}
		return 'Grande (8,0 o más)';
	}

	/**
	 * Nivel semafórico de un sismo, combinando magnitud y profundidad.
	 * Los sismos superficiales se sienten más que los profundos de igual
	 * magnitud; por eso el nivel sube un escalón bajo los 70 km.
	 *
	 * @param float $mag        Magnitud.
	 * @param float $profundidad Profundidad en km.
	 * @return array {clave, etiqueta, color}
	 */
	public static function nivel( $mag, $profundidad = 100 ) {
		$m = (float) $mag;
		$p = (float) $profundidad;

		$escala = 0;                       // 0 bajo · 1 moderado · 2 alto · 3 muy alto
		if ( $m >= 4.5 ) {
			$escala = 1;
		}
		if ( $m >= 5.5 ) {
			$escala = 2;
		}
		if ( $m >= 6.5 ) {
			$escala = 3;
		}
		if ( $p < 70 && $m >= 4.0 && $escala < 3 ) {
			$escala++;                     // superficial: se percibe más.
		}

		$mapa = array(
			0 => array( 'clave' => 'bajo', 'etiqueta' => 'Actividad de fondo', 'color' => '#3EBA6A' ),
			1 => array( 'clave' => 'moderado', 'etiqueta' => 'Perceptible', 'color' => '#FFC53B' ),
			2 => array( 'clave' => 'alto', 'etiqueta' => 'Sismo sensible', 'color' => '#FF7300' ),
			3 => array( 'clave' => 'muy_alto', 'etiqueta' => 'Sismo fuerte', 'color' => '#E74C3C' ),
		);
		return $mapa[ $escala ];
	}
}
