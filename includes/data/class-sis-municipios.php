<?php
/**
 * Tabla maestra de los 64 municipios de Nariño (cartografía DANE / DIVIPOLA).
 *
 * Coordenadas del centroide municipal redondeadas a 5 decimales. Se usa como
 * lista blanca de seguridad y para asignar cada epicentro al municipio más
 * cercano (distancia ortodrómica), de modo que la sismicidad regional pueda
 * leerse en clave territorial.
 *
 * Datos reutilizados del plugin hermano «Monitor Ambiental y Fenómeno El Niño
 * — Nariño» (misma entidad, misma licencia GPL-2.0-or-later).
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Municipios {

	/** @var array|null Caché en memoria de la lista. */
	private static $lista = null;

	/** @var array|null Índice rápido divipola → fila. */
	private static $indice = null;

	/** Radio medio terrestre en kilómetros (WGS-84). */
	const RADIO_TIERRA_KM = 6371.0088;

	/**
	 * Subregiones del litoral Pacífico (relevantes para el riesgo de tsunami
	 * y para la sismicidad de subducción frente a la costa).
	 *
	 * @return string[]
	 */
	public static function litoral_subregiones() {
		return array( 'Sanquianga', 'Pacífico Sur', 'Telembí', 'Pie de Monte Costero' );
	}

	/**
	 * Devuelve los 64 municipios.
	 *
	 * @return array[]
	 */
	public static function todos() {
		if ( null !== self::$lista ) {
			return self::$lista;
		}

		self::$lista = array(
			array( 'divipola' => '52019', 'nombre' => 'ALBÁN', 'lat' => 1.46985, 'lon' => -77.06881, 'subregion' => 'Río Mayo' ),
			array( 'divipola' => '52022', 'nombre' => 'ALDANA', 'lat' => 0.91343, 'lon' => -77.69539, 'subregion' => 'Ex-Provincia de Obando' ),
			array( 'divipola' => '52036', 'nombre' => 'ANCUYA', 'lat' => 1.24525, 'lon' => -77.53116, 'subregion' => 'Occidente' ),
			array( 'divipola' => '52051', 'nombre' => 'ARBOLEDA', 'lat' => 1.48005, 'lon' => -77.12985, 'subregion' => 'Juanambú' ),
			array( 'divipola' => '52079', 'nombre' => 'BARBACOAS', 'lat' => 1.44564, 'lon' => -78.15621, 'subregion' => 'Telembí' ),
			array( 'divipola' => '52083', 'nombre' => 'BELÉN', 'lat' => 1.59076, 'lon' => -77.04290, 'subregion' => 'Río Mayo' ),
			array( 'divipola' => '52110', 'nombre' => 'BUESACO', 'lat' => 1.31522, 'lon' => -77.11637, 'subregion' => 'Juanambú' ),
			array( 'divipola' => '52240', 'nombre' => 'CHACHAGÜÍ', 'lat' => 1.38650, 'lon' => -77.26969, 'subregion' => 'Centro' ),
			array( 'divipola' => '52203', 'nombre' => 'COLÓN', 'lat' => 1.63633, 'lon' => -77.04732, 'subregion' => 'Río Mayo' ),
			array( 'divipola' => '52207', 'nombre' => 'CONSACÁ', 'lat' => 1.20907, 'lon' => -77.44064, 'subregion' => 'Occidente' ),
			array( 'divipola' => '52210', 'nombre' => 'CONTADERO', 'lat' => 0.93267, 'lon' => -77.52809, 'subregion' => 'Ex-Provincia de Obando' ),
			array( 'divipola' => '52224', 'nombre' => 'CUASPUD CARLOSAMA', 'lat' => 0.87543, 'lon' => -77.73592, 'subregion' => 'Ex-Provincia de Obando' ),
			array( 'divipola' => '52227', 'nombre' => 'CUMBAL', 'lat' => 0.94422, 'lon' => -77.95958, 'subregion' => 'Ex-Provincia de Obando' ),
			array( 'divipola' => '52233', 'nombre' => 'CUMBITARA', 'lat' => 1.72559, 'lon' => -77.59282, 'subregion' => 'Cordillera' ),
			array( 'divipola' => '52215', 'nombre' => 'CÓRDOBA', 'lat' => 0.77080, 'lon' => -77.36033, 'subregion' => 'Ex-Provincia de Obando' ),
			array( 'divipola' => '52250', 'nombre' => 'EL CHARCO', 'lat' => 2.18315, 'lon' => -77.79574, 'subregion' => 'Sanquianga' ),
			array( 'divipola' => '52254', 'nombre' => 'EL PEÑOL', 'lat' => 1.51228, 'lon' => -77.43051, 'subregion' => 'Cordillera' ),
			array( 'divipola' => '52256', 'nombre' => 'EL ROSARIO', 'lat' => 1.84509, 'lon' => -77.43826, 'subregion' => 'Cordillera' ),
			array( 'divipola' => '52258', 'nombre' => 'EL TABLÓN DE GÓMEZ', 'lat' => 1.40943, 'lon' => -76.98527, 'subregion' => 'Juanambú' ),
			array( 'divipola' => '52260', 'nombre' => 'EL TAMBO', 'lat' => 1.43026, 'lon' => -77.38312, 'subregion' => 'Frontera Pacífica' ),
			array( 'divipola' => '52520', 'nombre' => 'FRANCISCO PIZARRO', 'lat' => 2.08853, 'lon' => -78.59193, 'subregion' => 'Pacífico Sur' ),
			array( 'divipola' => '52287', 'nombre' => 'FUNES', 'lat' => 0.91422, 'lon' => -77.32843, 'subregion' => 'Ex-Provincia de Obando' ),
			array( 'divipola' => '52317', 'nombre' => 'GUACHUCAL', 'lat' => 0.97504, 'lon' => -77.73759, 'subregion' => 'Ex-Provincia de Obando' ),
			array( 'divipola' => '52320', 'nombre' => 'GUAITARILLA', 'lat' => 1.15137, 'lon' => -77.53011, 'subregion' => 'Sabana' ),
			array( 'divipola' => '52323', 'nombre' => 'GUALMATÁN', 'lat' => 0.92864, 'lon' => -77.58262, 'subregion' => 'Ex-Provincia de Obando' ),
			array( 'divipola' => '52352', 'nombre' => 'ILES', 'lat' => 0.98053, 'lon' => -77.51866, 'subregion' => 'Ex-Provincia de Obando' ),
			array( 'divipola' => '52354', 'nombre' => 'IMUÉS', 'lat' => 1.07288, 'lon' => -77.50151, 'subregion' => 'Sabana' ),
			array( 'divipola' => '52356', 'nombre' => 'IPIALES', 'lat' => 0.55861, 'lon' => -77.37036, 'subregion' => 'Ex-Provincia de Obando' ),
			array( 'divipola' => '52378', 'nombre' => 'LA CRUZ', 'lat' => 1.58418, 'lon' => -76.92335, 'subregion' => 'Río Mayo' ),
			array( 'divipola' => '52381', 'nombre' => 'LA FLORIDA', 'lat' => 1.33393, 'lon' => -77.38823, 'subregion' => 'Centro' ),
			array( 'divipola' => '52385', 'nombre' => 'LA LLANADA', 'lat' => 1.55401, 'lon' => -77.70317, 'subregion' => 'Abades-La Llanada' ),
			array( 'divipola' => '52390', 'nombre' => 'LA TOLA', 'lat' => 2.41931, 'lon' => -78.20991, 'subregion' => 'Sanquianga' ),
			array( 'divipola' => '52399', 'nombre' => 'LA UNIÓN', 'lat' => 1.61970, 'lon' => -77.14285, 'subregion' => 'Río Mayo' ),
			array( 'divipola' => '52405', 'nombre' => 'LEIVA', 'lat' => 1.93898, 'lon' => -77.31194, 'subregion' => 'Cordillera' ),
			array( 'divipola' => '52411', 'nombre' => 'LINARES', 'lat' => 1.39517, 'lon' => -77.52094, 'subregion' => 'Occidente' ),
			array( 'divipola' => '52418', 'nombre' => 'LOS ANDES', 'lat' => 1.67260, 'lon' => -77.71054, 'subregion' => 'Abades-La Llanada' ),
			array( 'divipola' => '52427', 'nombre' => 'MAGÜÍ', 'lat' => 1.90686, 'lon' => -78.04474, 'subregion' => 'Telembí' ),
			array( 'divipola' => '52435', 'nombre' => 'MALLAMA', 'lat' => 1.15595, 'lon' => -77.84665, 'subregion' => 'Centro-Occidente / Abades' ),
			array( 'divipola' => '52473', 'nombre' => 'MOSQUERA', 'lat' => 2.44249, 'lon' => -78.43883, 'subregion' => 'Sanquianga' ),
			array( 'divipola' => '52480', 'nombre' => 'NARIÑO', 'lat' => 1.28086, 'lon' => -77.35389, 'subregion' => 'Centro' ),
			array( 'divipola' => '52490', 'nombre' => 'OLAYA HERRERA', 'lat' => 2.28989, 'lon' => -78.29472, 'subregion' => 'Sanquianga' ),
			array( 'divipola' => '52506', 'nombre' => 'OSPINA', 'lat' => 1.02982, 'lon' => -77.55235, 'subregion' => 'Sabana' ),
			array( 'divipola' => '52001', 'nombre' => 'PASTO', 'lat' => 1.08361, 'lon' => -77.20610, 'subregion' => 'Centro' ),
			array( 'divipola' => '52540', 'nombre' => 'POLICARPA', 'lat' => 1.73535, 'lon' => -77.48134, 'subregion' => 'Cordillera' ),
			array( 'divipola' => '52560', 'nombre' => 'POTOSÍ', 'lat' => 0.72268, 'lon' => -77.42481, 'subregion' => 'Ex-Provincia de Obando' ),
			array( 'divipola' => '52565', 'nombre' => 'PROVIDENCIA', 'lat' => 1.23286, 'lon' => -77.59844, 'subregion' => 'Centro-Occidente / Abades' ),
			array( 'divipola' => '52573', 'nombre' => 'PUERRES', 'lat' => 0.82652, 'lon' => -77.32225, 'subregion' => 'Ex-Provincia de Obando' ),
			array( 'divipola' => '52585', 'nombre' => 'PUPIALES', 'lat' => 0.91677, 'lon' => -77.63337, 'subregion' => 'Ex-Provincia de Obando' ),
			array( 'divipola' => '52612', 'nombre' => 'RICAURTE', 'lat' => 1.20276, 'lon' => -78.04765, 'subregion' => 'Pie de Monte Costero' ),
			array( 'divipola' => '52621', 'nombre' => 'ROBERTO PAYÁN', 'lat' => 1.89758, 'lon' => -78.38112, 'subregion' => 'Telembí' ),
			array( 'divipola' => '52678', 'nombre' => 'SAMANIEGO', 'lat' => 1.43056, 'lon' => -77.69180, 'subregion' => 'Centro-Occidente / Abades' ),
			array( 'divipola' => '52835', 'nombre' => 'SAN ANDRÉS DE TUMACO', 'lat' => 1.63610, 'lon' => -78.61391, 'subregion' => 'Pacífico Sur' ),
			array( 'divipola' => '52685', 'nombre' => 'SAN BERNARDO', 'lat' => 1.52978, 'lon' => -77.02071, 'subregion' => 'Juanambú' ),
			array( 'divipola' => '52687', 'nombre' => 'SAN LORENZO', 'lat' => 1.54214, 'lon' => -77.21873, 'subregion' => 'Juanambú' ),
			array( 'divipola' => '52693', 'nombre' => 'SAN PABLO', 'lat' => 1.68158, 'lon' => -76.97528, 'subregion' => 'Río Mayo' ),
			array( 'divipola' => '52694', 'nombre' => 'SAN PEDRO DE CARTAGO', 'lat' => 1.53682, 'lon' => -77.10140, 'subregion' => 'Juanambú' ),
			array( 'divipola' => '52683', 'nombre' => 'SANDONÁ', 'lat' => 1.28811, 'lon' => -77.45670, 'subregion' => 'Occidente' ),
			array( 'divipola' => '52696', 'nombre' => 'SANTA BÁRBARA', 'lat' => 2.30216, 'lon' => -77.87437, 'subregion' => 'Sanquianga' ),
			array( 'divipola' => '52699', 'nombre' => 'SANTACRUZ', 'lat' => 1.28518, 'lon' => -77.74457, 'subregion' => 'Centro-Occidente / Abades' ),
			array( 'divipola' => '52720', 'nombre' => 'SAPUYES', 'lat' => 1.03619, 'lon' => -77.68045, 'subregion' => 'Sabana' ),
			array( 'divipola' => '52786', 'nombre' => 'TAMINANGO', 'lat' => 1.59166, 'lon' => -77.32525, 'subregion' => 'Cordillera' ),
			array( 'divipola' => '52788', 'nombre' => 'TANGUA', 'lat' => 1.06408, 'lon' => -77.35063, 'subregion' => 'Centro' ),
			array( 'divipola' => '52838', 'nombre' => 'TÚQUERRES', 'lat' => 1.13444, 'lon' => -77.63073, 'subregion' => 'Sabana' ),
			array( 'divipola' => '52885', 'nombre' => 'YACUANQUER', 'lat' => 1.12555, 'lon' => -77.42468, 'subregion' => 'Centro' ),
		);

		return self::$lista;
	}

	/**
	 * Construye el índice divipola → fila.
	 */
	private static function indexar() {
		if ( null !== self::$indice ) {
			return;
		}
		self::$indice = array();
		foreach ( self::todos() as $m ) {
			self::$indice[ $m['divipola'] ] = $m;
		}
	}

	/**
	 * ¿Existe el código DIVIPOLA?
	 *
	 * @param string $divipola Código de 5 dígitos.
	 * @return bool
	 */
	public static function existe( $divipola ) {
		self::indexar();
		return isset( self::$indice[ (string) $divipola ] );
	}

	/**
	 * Municipio por código DIVIPOLA.
	 *
	 * @param string $divipola Código de 5 dígitos.
	 * @return array|null
	 */
	public static function por_divipola( $divipola ) {
		self::indexar();
		$k = (string) $divipola;
		return isset( self::$indice[ $k ] ) ? self::$indice[ $k ] : null;
	}

	/**
	 * Municipio por nombre (tolerante a tildes, mayúsculas y espacios).
	 *
	 * @param string $nombre Nombre del municipio.
	 * @return array|null
	 */
	public static function por_nombre( $nombre ) {
		$buscado = self::clave_nombre( $nombre );
		if ( '' === $buscado ) {
			return null;
		}

		// 1) Coincidencia exacta.
		foreach ( self::todos() as $m ) {
			if ( self::clave_nombre( $m['nombre'] ) === $buscado ) {
				return $m;
			}
		}

		// 2) Coincidencia parcial inequívoca: los nombres oficiales del DANE
		// incluyen prefijos que la gente omite («San Andrés de Tumaco» →
		// «Tumaco»). Solo se acepta si no hay ambigüedad.
		$candidatos = array();
		foreach ( self::todos() as $m ) {
			if ( false !== strpos( self::clave_nombre( $m['nombre'] ), $buscado ) ) {
				$candidatos[] = $m;
			}
		}

		return ( 1 === count( $candidatos ) ) ? $candidatos[0] : null;
	}

	/**
	 * Lista de códigos DIVIPOLA.
	 *
	 * @return string[]
	 */
	public static function codigos() {
		return wp_list_pluck( self::todos(), 'divipola' );
	}

	/**
	 * Lista de subregiones únicas, ordenadas alfabéticamente.
	 *
	 * @return string[]
	 */
	public static function subregiones() {
		$s = array_values( array_unique( wp_list_pluck( self::todos(), 'subregion' ) ) );
		sort( $s );
		return $s;
	}

	/**
	 * ¿La subregión es de litoral Pacífico?
	 *
	 * @param string $subregion Subregión.
	 * @return bool
	 */
	public static function es_litoral( $subregion ) {
		return in_array( (string) $subregion, self::litoral_subregiones(), true );
	}

	/**
	 * Distancia ortodrómica (haversine) entre dos puntos, en kilómetros.
	 *
	 * @param float $lat1 Latitud 1.
	 * @param float $lon1 Longitud 1.
	 * @param float $lat2 Latitud 2.
	 * @param float $lon2 Longitud 2.
	 * @return float Kilómetros.
	 */
	public static function distancia_km( $lat1, $lon1, $lat2, $lon2 ) {
		$f1 = deg2rad( (float) $lat1 );
		$f2 = deg2rad( (float) $lat2 );
		$df = $f2 - $f1;
		$dl = deg2rad( (float) $lon2 - (float) $lon1 );

		$a = sin( $df / 2 ) * sin( $df / 2 ) +
			cos( $f1 ) * cos( $f2 ) * sin( $dl / 2 ) * sin( $dl / 2 );
		$c = 2 * atan2( sqrt( $a ), sqrt( max( 0.0, 1 - $a ) ) );

		return self::RADIO_TIERRA_KM * $c;
	}

	/**
	 * Municipio de Nariño más cercano a un epicentro.
	 *
	 * @param float    $lat     Latitud del epicentro.
	 * @param float    $lon     Longitud del epicentro.
	 * @param float    $max_km  Distancia máxima admitida (0 = sin límite).
	 * @return array|null {divipola, nombre, subregion, lat, lon, distancia_km}
	 */
	public static function mas_cercano( $lat, $lon, $max_km = 0 ) {
		if ( ! SIS_Security::validar_coordenada( $lat, $lon ) ) {
			return null;
		}

		$mejor  = null;
		$mejord = INF;
		foreach ( self::todos() as $m ) {
			$d = self::distancia_km( $lat, $lon, $m['lat'], $m['lon'] );
			if ( $d < $mejord ) {
				$mejord = $d;
				$mejor  = $m;
			}
		}

		if ( null === $mejor ) {
			return null;
		}
		if ( $max_km > 0 && $mejord > (float) $max_km ) {
			return null;
		}

		$mejor['distancia_km'] = round( $mejord, 1 );
		return $mejor;
	}

	/**
	 * Clave normalizada de nombre (sin tildes, mayúsculas, sin dobles espacios).
	 *
	 * @param string $n Nombre.
	 * @return string
	 */
	private static function clave_nombre( $n ) {
		$n = (string) $n;
		$n = function_exists( 'remove_accents' ) ? remove_accents( $n ) : $n;
		$n = strtoupper( trim( preg_replace( '/\s+/', ' ', $n ) ) );
		return $n;
	}
}
