<?php
/**
 * Catálogo sísmico: normalización, filtrado y agregaciones.
 *
 * Traduce el GeoJSON del USGS (FDSN Event y feeds de resumen) a filas planas
 * y homogéneas que alimentan tanto la estadística como el motor de gráficos.
 * Es la única capa que conoce el formato del proveedor: de aquí en adelante
 * todo el plugin trabaja con el mismo esquema de evento.
 *
 * Resiliencia: si la caché durable está vacía (primer arranque o USGS caído),
 * cae a la semilla JSON de data/, de modo que la página nunca queda en blanco.
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Catalogo {

	/** Prefijo de la clave de caché del catálogo histórico por ámbito. */
	const CLAVE = 'catalogo';

	/** Semilla de respaldo incluida en el plugin. */
	/** Feed de resumen que alimenta la vista global del globo. */
	const FEED_MUNDO = '2.5_week';

	const SEMILLA = 'catalogo_regional_semilla.json';

	/* ================================================================= */
	/* Normalización                                                     */
	/* ================================================================= */

	/**
	 * Convierte un FeatureCollection GeoJSON del USGS en filas normalizadas.
	 *
	 * @param array|string $geojson  Estructura decodificada o JSON crudo.
	 * @param array        $opts     {ambito, solo_terremotos, asignar_municipio}.
	 * @return array[] Filas de evento en orden cronológico ascendente.
	 */
	public static function normalizar( $geojson, $opts = array() ) {
		$o = array_merge(
			array(
				'ambito'            => '',      // '' = sin recorte geográfico adicional.
				'solo_terremotos'   => true,    // descarta explosiones, derrumbes, ruido.
				'asignar_municipio' => true,
			),
			$opts
		);

		if ( is_string( $geojson ) ) {
			$geojson = json_decode( $geojson, true );
		}
		if ( ! is_array( $geojson ) || empty( $geojson['features'] ) || ! is_array( $geojson['features'] ) ) {
			return array();
		}

		$filas = array();
		foreach ( $geojson['features'] as $f ) {
			$fila = self::normalizar_feature( $f, $o );
			if ( null !== $fila ) {
				$filas[ $fila['id'] ] = $fila; // el id deduplica entre feed y catálogo.
			}
		}

		return self::ordenar( array_values( $filas ) );
	}

	/**
	 * Normaliza un único feature del USGS.
	 *
	 * @param array $f Feature GeoJSON.
	 * @param array $o Opciones ya fusionadas.
	 * @return array|null
	 */
	private static function normalizar_feature( $f, $o ) {
		if ( empty( $f['properties'] ) || empty( $f['geometry']['coordinates'] ) ) {
			return null;
		}
		$p = $f['properties'];
		$c = $f['geometry']['coordinates'];

		// Magnitud ausente (evento en revisión): no es utilizable en estadística.
		if ( ! isset( $p['mag'] ) || null === $p['mag'] || ! is_numeric( $p['mag'] ) ) {
			return null;
		}
		if ( ! empty( $o['solo_terremotos'] ) && isset( $p['type'] ) && 'earthquake' !== $p['type'] ) {
			return null;
		}

		$lon = isset( $c[0] ) ? (float) $c[0] : null;
		$lat = isset( $c[1] ) ? (float) $c[1] : null;
		$pro = isset( $c[2] ) && null !== $c[2] ? (float) $c[2] : 0.0;

		if ( null === $lat || null === $lon || ! SIS_Security::validar_coordenada( $lat, $lon ) ) {
			return null;
		}
		if ( ! empty( $o['ambito'] ) && ! SIS_Regiones::contiene( $o['ambito'], $lat, $lon ) ) {
			return null;
		}

		// El USGS entrega el tiempo de origen en milisegundos desde época UTC.
		$ts = isset( $p['time'] ) ? (int) round( ( (float) $p['time'] ) / 1000 ) : 0;
		if ( $ts <= 0 ) {
			return null;
		}

		$mag = round( (float) $p['mag'], 1 );
		$id  = isset( $f['id'] ) ? sanitize_text_field( (string) $f['id'] ) : ( 'sis' . $ts . '_' . str_replace( array( '.', '-' ), '', (string) $lat ) );

		$fila = array(
			'id'                => $id,
			'ts'                => $ts,
			'fecha'             => gmdate( 'Y-m-d H:i:s', $ts ),
			'dia'               => gmdate( 'Y-m-d', $ts ),
			'mes'               => gmdate( 'Y-m', $ts ),
			'anio'              => (int) gmdate( 'Y', $ts ),
			'mag'               => $mag,
			'tipo_mag'          => isset( $p['magType'] ) ? sanitize_text_field( (string) $p['magType'] ) : '',
			'profundidad'       => round( $pro, 1 ),
			'lat'               => round( $lat, 4 ),
			'lon'               => round( $lon, 4 ),
			'lugar'             => isset( $p['place'] ) ? sanitize_text_field( (string) $p['place'] ) : '',
			'url'               => isset( $p['url'] ) ? esc_url_raw( (string) $p['url'] ) : '',
			'tsunami'           => ! empty( $p['tsunami'] ) ? 1 : 0,
			'reportes'          => isset( $p['felt'] ) && null !== $p['felt'] ? (int) $p['felt'] : 0,
			'intensidad'        => isset( $p['cdi'] ) && null !== $p['cdi'] ? (float) $p['cdi'] : ( isset( $p['mmi'] ) && null !== $p['mmi'] ? (float) $p['mmi'] : 0.0 ),
			'alerta'            => isset( $p['alert'] ) && $p['alert'] ? sanitize_key( (string) $p['alert'] ) : '',
			'estado'            => isset( $p['status'] ) ? sanitize_key( (string) $p['status'] ) : '',
			'clase'             => SIS_Regiones::clasificar_magnitud( $mag ),
			'rango_profundidad' => SIS_Regiones::clasificar_profundidad( $pro ),
			'energia_j'         => self::energia_joules( $mag ),
		);

		// Municipio de Nariño más cercano (para la lectura territorial).
		$fila['municipio']    = '';
		$fila['divipola']     = '';
		$fila['subregion']    = '';
		$fila['distancia_km'] = null;
		if ( ! empty( $o['asignar_municipio'] ) ) {
			$mun = SIS_Municipios::mas_cercano( $lat, $lon );
			if ( $mun ) {
				$fila['municipio']    = $mun['nombre'];
				$fila['divipola']     = $mun['divipola'];
				$fila['subregion']    = $mun['subregion'];
				$fila['distancia_km'] = $mun['distancia_km'];
			}
		}
		$fila['en_narino'] = SIS_Regiones::contiene( 'narino', $lat, $lon ) ? 1 : 0;

		return $fila;
	}

	/**
	 * Energía sísmica irradiada, en julios (relación de Hanks & Kanamori):
	 * log10(E) = 1,5·M + 4,8.
	 *
	 * @param float $mag Magnitud.
	 * @return float Julios.
	 */
	public static function energia_joules( $mag ) {
		return pow( 10, 1.5 * (float) $mag + 4.8 );
	}

	/**
	 * Equivalente en toneladas de TNT (1 t TNT = 4,184·10⁹ J).
	 *
	 * @param float $julios Energía en julios.
	 * @return float Toneladas de TNT.
	 */
	public static function toneladas_tnt( $julios ) {
		return (float) $julios / 4.184e9;
	}

	/* ================================================================= */
	/* Acceso al catálogo                                                */
	/* ================================================================= */

	/**
	 * Clave de caché del catálogo de un ámbito.
	 *
	 * @param string $ambito Slug del ámbito.
	 * @return string
	 */
	public static function clave( $ambito ) {
		return self::CLAVE . '_' . SIS_Security::sanitizar_ambito( $ambito );
	}

	/**
	 * Devuelve el catálogo vigente de un ámbito, ya normalizado y ordenado.
	 *
	 * Estrategia de resiliencia (en cascada):
	 *   1. caché viva (transient o tabla durable dentro de su TTL),
	 *   2. caché durable expirada (dato viejo, pero dato),
	 *   3. semilla JSON incluida en data/ recortada al ámbito.
	 *
	 * @param string $ambito Slug del ámbito.
	 * @return array {eventos, fuente, actualizado, ambito}
	 */
	public static function obtener( $ambito = '' ) {
		$ambito = SIS_Security::sanitizar_ambito( $ambito );

		if ( SIS_Regiones::solo_feed( $ambito ) ) {
			return self::obtener_mundo( $ambito );
		}

		$clave = self::clave( $ambito );

		$payload = SIS_Cache::get( $clave );
		$origen  = 'cache';

		if ( ! is_array( $payload ) || empty( $payload['eventos'] ) ) {
			$payload = SIS_Cache::get_durable( $clave );
			$origen  = 'cache_durable';
		}

		if ( ! is_array( $payload ) || empty( $payload['eventos'] ) ) {
			$semilla = SIS_Cache::semilla( self::SEMILLA );
			$eventos = is_array( $semilla ) ? self::normalizar( $semilla, array( 'ambito' => $ambito ) ) : array();
			$payload = array(
				'eventos'     => $eventos,
				'actualizado' => isset( $semilla['generado'] ) ? $semilla['generado'] : '',
				'fuente'      => 'Semilla local (USGS)',
			);
			$origen  = 'semilla';
		}

		$eventos = isset( $payload['eventos'] ) && is_array( $payload['eventos'] ) ? $payload['eventos'] : array();

		// Mezcla los eventos recientes del feed en vivo (si los hay), para que
		// la estadística incluya lo ocurrido desde la última sincronización.
		$eventos = self::fusionar( $eventos, self::eventos_feed( $ambito ) );

		return array(
			'ambito'      => $ambito,
			'eventos'     => $eventos,
			'total'       => count( $eventos ),
			'origen'      => $origen,
			'actualizado' => isset( $payload['actualizado'] ) ? $payload['actualizado'] : '',
			'fuente'      => isset( $payload['fuente'] ) ? $payload['fuente'] : 'USGS Earthquake Hazards Program',
		);
	}

	/**
	 * Catálogo de un ámbito que se sirve del feed de resumen (el planeta).
	 *
	 * No hay catálogo histórico ni semilla que valgan para todo el mundo: la
	 * fuente es el feed del USGS, que trae la sismicidad reciente y se
	 * regenera cada minuto. Si el cron todavía no lo ha traído, se pide una
	 * vez y se cachea; así la vista global funciona desde la primera visita
	 * sin convertir la ruta pública en un amplificador de peticiones.
	 *
	 * @param string $ambito Ámbito solicitado.
	 * @return array
	 */
	private static function obtener_mundo( $ambito ) {
		$payload = SIS_Cache::get( 'feed_mundo' );
		$origen  = 'feed';

		if ( ! is_array( $payload ) || empty( $payload['eventos'] ) ) {
			$r = SIS_Sync_Feed::sincronizar( array( 'dataset_id' => self::FEED_MUNDO ) );
			if ( ! empty( $r['ok'] ) ) {
				$payload = SIS_Cache::get( 'feed_mundo' );
				$origen  = 'feed_en_vivo';
			}
		}

		$eventos = is_array( $payload ) && ! empty( $payload['eventos'] ) ? $payload['eventos'] : array();

		return array(
			'ambito'      => $ambito,
			'eventos'     => $eventos,
			'total'       => count( $eventos ),
			'origen'      => $eventos ? $origen : 'vacio',
			'actualizado' => isset( $payload['actualizado'] ) ? $payload['actualizado'] : '',
			'fuente'      => isset( $payload['fuente'] ) ? $payload['fuente'] : 'USGS — feed GeoJSON de resumen',
		);
	}

	/**
	 * Firma corta del catálogo: cambia en cuanto cambian los datos.
	 *
	 * Sirve para construir claves de caché que se invalidan solas cuando llega
	 * un sismo nuevo, sin tener que vaciar nada a mano.
	 *
	 * @param array[] $eventos Eventos ordenados.
	 * @return string
	 */
	public static function firma( array $eventos ) {
		$n = count( $eventos );
		if ( 0 === $n ) {
			return 'vacio';
		}
		$ultimo = $eventos[ $n - 1 ];
		return substr( md5( $n . '|' . $ultimo['id'] . '|' . $ultimo['ts'] ), 0, 12 );
	}

	/**
	 * Eventos recientes cacheados del feed de resumen, recortados al ámbito.
	 *
	 * @param string $ambito Slug del ámbito.
	 * @return array[]
	 */
	public static function eventos_feed( $ambito ) {
		$feed = SIS_Cache::get( 'feed' );
		if ( ! is_array( $feed ) || empty( $feed['eventos'] ) ) {
			return array();
		}
		$out = array();
		foreach ( $feed['eventos'] as $e ) {
			if ( isset( $e['lat'], $e['lon'] ) && SIS_Regiones::contiene( $ambito, $e['lat'], $e['lon'] ) ) {
				$out[] = $e;
			}
		}
		return $out;
	}

	/**
	 * Fusiona dos listas de eventos deduplicando por id y ordenando.
	 *
	 * @param array[] $a Lista base.
	 * @param array[] $b Lista a incorporar (gana en caso de repetición).
	 * @return array[]
	 */
	public static function fusionar( array $a, array $b ) {
		$mapa = array();
		foreach ( $a as $e ) {
			if ( isset( $e['id'] ) ) {
				$mapa[ $e['id'] ] = $e;
			}
		}
		foreach ( $b as $e ) {
			if ( isset( $e['id'] ) ) {
				$mapa[ $e['id'] ] = $e;
			}
		}
		return self::ordenar( array_values( $mapa ) );
	}

	/**
	 * Ordena cronológicamente (ascendente) por marca de tiempo.
	 *
	 * @param array[] $eventos Eventos.
	 * @return array[]
	 */
	public static function ordenar( array $eventos ) {
		usort(
			$eventos,
			static function ( $x, $y ) {
				$a = isset( $x['ts'] ) ? (int) $x['ts'] : 0;
				$b = isset( $y['ts'] ) ? (int) $y['ts'] : 0;
				if ( $a === $b ) {
					return 0;
				}
				return ( $a < $b ) ? -1 : 1;
			}
		);
		return $eventos;
	}

	/* ================================================================= */
	/* Filtros                                                           */
	/* ================================================================= */

	/**
	 * Filtra una lista de eventos.
	 *
	 * @param array[] $eventos Eventos normalizados.
	 * @param array   $args    {min_mag, max_mag, desde, hasta, dias, min_prof,
	 *                          max_prof, solo_narino, limite}.
	 * @return array[]
	 */
	public static function filtrar( array $eventos, $args = array() ) {
		$a = array_merge(
			array(
				'min_mag'     => null,
				'max_mag'     => null,
				'desde'       => '',   // AAAA-MM-DD
				'hasta'       => '',   // AAAA-MM-DD
				'dias'        => 0,    // ventana móvil hacia atrás desde ahora.
				'min_prof'    => null,
				'max_prof'    => null,
				'solo_narino' => false,
				'limite'      => 0,
			),
			$args
		);

		$ts_desde = 0;
		$ts_hasta = 0;
		if ( $a['dias'] > 0 ) {
			$ts_desde = time() - ( (int) $a['dias'] * 86400 );
		}
		if ( ! empty( $a['desde'] ) ) {
			$ts_desde = max( $ts_desde, (int) strtotime( $a['desde'] . ' 00:00:00 UTC' ) );
		}
		if ( ! empty( $a['hasta'] ) ) {
			$ts_hasta = (int) strtotime( $a['hasta'] . ' 23:59:59 UTC' );
		}

		$out = array();
		foreach ( $eventos as $e ) {
			if ( null !== $a['min_mag'] && $e['mag'] < (float) $a['min_mag'] ) {
				continue;
			}
			if ( null !== $a['max_mag'] && $e['mag'] > (float) $a['max_mag'] ) {
				continue;
			}
			if ( $ts_desde > 0 && $e['ts'] < $ts_desde ) {
				continue;
			}
			if ( $ts_hasta > 0 && $e['ts'] > $ts_hasta ) {
				continue;
			}
			if ( null !== $a['min_prof'] && $e['profundidad'] < (float) $a['min_prof'] ) {
				continue;
			}
			if ( null !== $a['max_prof'] && $e['profundidad'] >= (float) $a['max_prof'] ) {
				continue;
			}
			if ( ! empty( $a['solo_narino'] ) && empty( $e['en_narino'] ) ) {
				continue;
			}
			$out[] = $e;
		}

		if ( $a['limite'] > 0 && count( $out ) > (int) $a['limite'] ) {
			$out = array_slice( $out, -1 * (int) $a['limite'] );
		}

		return $out;
	}

	/* ================================================================= */
	/* Agregaciones                                                      */
	/* ================================================================= */

	/**
	 * Conteo de eventos por mes calendario, rellenando los meses sin sismos.
	 *
	 * @param array[] $eventos Eventos (orden cualquiera).
	 * @param int     $meses   Nº de meses hacia atrás desde el último dato (0 = todos).
	 * @return array<string,int> mes AAAA-MM → conteo.
	 */
	public static function conteo_mensual( array $eventos, $meses = 0 ) {
		if ( empty( $eventos ) ) {
			return array();
		}

		$conteo = array();
		$min    = null;
		$max    = null;
		foreach ( $eventos as $e ) {
			$m = $e['mes'];
			if ( ! isset( $conteo[ $m ] ) ) {
				$conteo[ $m ] = 0;
			}
			$conteo[ $m ]++;
			if ( null === $min || $m < $min ) {
				$min = $m;
			}
			if ( null === $max || $m > $max ) {
				$max = $m;
			}
		}

		// La serie llega hasta el mes en curso, no hasta el último mes con
		// actividad: si el catálogo lleva semanas sin sismos, una gráfica que
		// termina en el último evento parece decir que los datos se detuvieron
		// ahí. Un mes en cero es información —«no hubo sismos»—; un mes que
		// falta es ambiguo.
		$hoy = gmdate( 'Y-m' );
		if ( $max < $hoy ) {
			$max = $hoy;
		}

		// Rellena los meses intermedios sin actividad (un cero es información).
		$serie = array();
		$mes   = $min;
		$tope  = 0;
		while ( $mes <= $max && $tope < 1200 ) {
			$serie[ $mes ] = isset( $conteo[ $mes ] ) ? $conteo[ $mes ] : 0;
			$mes           = self::sumar_meses( $mes, 1 );
			$tope++;
		}

		if ( $meses > 0 && count( $serie ) > $meses ) {
			$serie = array_slice( $serie, -1 * (int) $meses, null, true );
		}

		return $serie;
	}

	/**
	 * Conteo de eventos por año calendario.
	 *
	 * @param array[] $eventos Eventos.
	 * @return array<int,int> año → conteo.
	 */
	public static function conteo_anual( array $eventos ) {
		$conteo = array();
		foreach ( $eventos as $e ) {
			$a = (int) $e['anio'];
			if ( ! isset( $conteo[ $a ] ) ) {
				$conteo[ $a ] = 0;
			}
			$conteo[ $a ]++;
		}
		if ( ! $conteo ) {
			return array();
		}

		// Igual que la serie mensual: se completa hasta el año en curso, aunque
		// todavía no haya registrado sismos. El año en curso siempre aparece,
		// aunque sea con una barra en cero.
		$min = min( array_keys( $conteo ) );
		$max = max( max( array_keys( $conteo ) ), (int) gmdate( 'Y' ) );

		$out = array();
		for ( $a = $min; $a <= $max; $a++ ) {
			$out[ $a ] = isset( $conteo[ $a ] ) ? $conteo[ $a ] : 0;
		}
		return $out;
	}

	/**
	 * Histograma de magnitudes en pasos de 0,1.
	 *
	 * @param array[] $eventos Eventos.
	 * @param float   $paso    Ancho del intervalo.
	 * @return array<string,int> magnitud → conteo.
	 */
	public static function histograma_magnitud( array $eventos, $paso = 0.1 ) {
		$paso = (float) $paso > 0 ? (float) $paso : 0.1;
		$out  = array();
		foreach ( $eventos as $e ) {
			$b            = (string) number_format( floor( $e['mag'] / $paso ) * $paso, 1, '.', '' );
			$out[ $b ]    = isset( $out[ $b ] ) ? $out[ $b ] + 1 : 1;
		}
		ksort( $out, SORT_NUMERIC );
		return $out;
	}

	/**
	 * Agrupa por una clave textual del evento (municipio, subregión, clase…).
	 *
	 * @param array[] $eventos Eventos.
	 * @param string  $campo   Campo del evento.
	 * @return array<string,int>
	 */
	public static function agrupar( array $eventos, $campo ) {
		$out = array();
		foreach ( $eventos as $e ) {
			$k = isset( $e[ $campo ] ) && '' !== $e[ $campo ] ? (string) $e[ $campo ] : 'Sin dato';
			$out[ $k ] = isset( $out[ $k ] ) ? $out[ $k ] + 1 : 1;
		}
		arsort( $out );
		return $out;
	}

	/**
	 * Energía liberada por mes (julios) y su equivalente en toneladas de TNT.
	 *
	 * @param array[] $eventos Eventos.
	 * @return array<string,array{julios:float,tnt:float,n:int}>
	 */
	public static function energia_mensual( array $eventos ) {
		$acum = array();
		foreach ( $eventos as $e ) {
			$m = $e['mes'];
			if ( ! isset( $acum[ $m ] ) ) {
				$acum[ $m ] = array( 'julios' => 0.0, 'tnt' => 0.0, 'n' => 0 );
			}
			$j                     = isset( $e['energia_j'] ) ? (float) $e['energia_j'] : self::energia_joules( $e['mag'] );
			$acum[ $m ]['julios'] += $j;
			$acum[ $m ]['n']++;
		}

		// Se monta sobre el mismo raíl de meses que el conteo —que llega hasta
		// el mes en curso—: un mes sin sismos liberó cero energía, y eso se
		// dibuja, no se omite.
		$out = array();
		foreach ( self::conteo_mensual( $eventos ) as $m => $n ) {
			$v            = isset( $acum[ $m ] ) ? $acum[ $m ] : array( 'julios' => 0.0, 'tnt' => 0.0, 'n' => 0 );
			$v['tnt']     = self::toneladas_tnt( $v['julios'] );
			$out[ $m ]    = $v;
		}
		return $out;
	}

	/* ================================================================= */
	/* Resumen de actividad                                              */
	/* ================================================================= */

	/**
	 * Panorama de actividad reciente para el semáforo y las tarjetas.
	 *
	 * @param array[] $eventos Eventos normalizados (ordenados).
	 * @param string  $ambito  Ámbito de referencia (informativo).
	 * @return array
	 */
	public static function resumen( array $eventos, $ambito = '' ) {
		$ahora    = time();
		$ventanas = array(
			'24h'  => 86400,
			'7d'   => 7 * 86400,
			'30d'  => 30 * 86400,
			'365d' => 365 * 86400,
		);

		$conteos = array_fill_keys( array_keys( $ventanas ), 0 );
		$maximos = array_fill_keys( array_keys( $ventanas ), 0.0 );

		foreach ( $eventos as $e ) {
			foreach ( $ventanas as $k => $seg ) {
				if ( $e['ts'] >= $ahora - $seg ) {
					$conteos[ $k ]++;
					if ( $e['mag'] > $maximos[ $k ] ) {
						$maximos[ $k ] = $e['mag'];
					}
				}
			}
		}

		$ultimo = null;
		$mayor  = null;
		foreach ( $eventos as $e ) {
			if ( null === $ultimo || $e['ts'] > $ultimo['ts'] ) {
				$ultimo = $e;
			}
			if ( null === $mayor || $e['mag'] > $mayor['mag'] ) {
				$mayor = $e;
			}
		}

		$nivel = $ultimo
			? SIS_Regiones::nivel( $ultimo['mag'], $ultimo['profundidad'] )
			: array( 'clave' => 'bajo', 'etiqueta' => 'Sin datos recientes', 'color' => '#6b7280' );

		// Magnitud máxima esperable de fondo: sirve para contextualizar el
		// semáforo (¿lo de hoy es normal para esta zona?).
		return array(
			'ambito'       => $ambito,
			'total'        => count( $eventos ),
			'conteos'      => $conteos,
			'max_mag'      => $maximos,
			'ultimo'       => $ultimo,
			'mayor'        => $mayor,
			'nivel'        => $nivel,
			'en_narino'    => count( self::filtrar( $eventos, array( 'solo_narino' => true ) ) ),
			'generado'     => gmdate( 'c' ),
		);
	}

	/* ================================================================= */
	/* Utilidades de calendario                                          */
	/* ================================================================= */

	/**
	 * Suma (o resta) meses a un AAAA-MM y devuelve AAAA-MM.
	 *
	 * @param string $mes   AAAA-MM.
	 * @param int    $delta Meses a sumar (puede ser negativo).
	 * @return string
	 */
	public static function sumar_meses( $mes, $delta ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})$/', (string) $mes, $m ) ) {
			return (string) $mes;
		}
		$total = ( (int) $m[1] ) * 12 + ( (int) $m[2] - 1 ) + (int) $delta;
		$anio  = intdiv( $total, 12 );
		$mm    = ( $total % 12 ) + 1;
		return sprintf( '%04d-%02d', $anio, $mm );
	}

	/**
	 * Nombre legible de un mes AAAA-MM en español (p. ej. «ago 2026»).
	 *
	 * @param string $mes AAAA-MM.
	 * @return string
	 */
	public static function mes_legible( $mes ) {
		$nombres = array( 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic' );
		if ( ! preg_match( '/^(\d{4})-(\d{2})$/', (string) $mes, $m ) ) {
			return (string) $mes;
		}
		$i = max( 1, min( 12, (int) $m[2] ) ) - 1;
		return $nombres[ $i ] . ' ' . $m[1];
	}

	/**
	 * Años decimales cubiertos por una lista de eventos.
	 *
	 * @param array[] $eventos Eventos ordenados.
	 * @return float
	 */
	public static function anios_cubiertos( array $eventos ) {
		if ( count( $eventos ) < 2 ) {
			return 0.0;
		}
		$primero = $eventos[0]['ts'];
		$ultimo  = $eventos[ count( $eventos ) - 1 ]['ts'];
		return max( 0.0, ( $ultimo - $primero ) / ( 365.25 * 86400 ) );
	}
}
