<?php
/**
 * Conector USGS — FDSN Event Web Service (catálogo histórico).
 *
 * Motor principal del plugin: descarga el catálogo sísmico en GeoJSON nativo,
 * sin clave de API, recortado por recuadro o radio al ámbito solicitado. Cada
 * ámbito configurado se cachea por separado, porque la estadística y el
 * pronóstico dependen del dominio espacial elegido.
 *
 * Documentación del servicio: https://earthquake.usgs.gov/fdsnws/event/1/
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Sync_Usgs {

	/** Punto final por defecto. */
	const URL = 'https://earthquake.usgs.gov/fdsnws/event/1/query';

	/** Tope de eventos por consulta admitido por el servicio. */
	const LIMITE = 20000;

	/**
	 * Sincroniza el catálogo de todos los ámbitos configurados.
	 *
	 * @param array $cfg Configuración de la fuente.
	 * @return array {ok, registros, mensaje}.
	 */
	public static function sincronizar( $cfg ) {
		$url_base = ! empty( $cfg['url'] ) ? $cfg['url'] : self::URL;
		$ssl      = isset( $cfg['sslverify'] ) ? (bool) $cfg['sslverify'] : true;
		$ttl      = isset( $cfg['ttl'] ) ? (int) $cfg['ttl'] * 60 : 43200;
		$anios    = isset( $cfg['anios'] ) ? max( 1, min( 60, (int) $cfg['anios'] ) ) : 36;
		$min_mag  = isset( $cfg['min_mag'] ) ? SIS_Security::sanitizar_magnitud( $cfg['min_mag'], 2.5 ) : 2.5;

		$ambitos = isset( $cfg['ambitos'] ) && is_array( $cfg['ambitos'] ) && $cfg['ambitos']
			? $cfg['ambitos']
			: array( 'regional', 'narino' );

		// Los ámbitos que se sirven del feed —el planeta entero— no se piden al
		// catálogo histórico: serían millones de eventos y el servicio corta la
		// respuesta.
		$ambitos = array_values( array_filter( $ambitos, static function ( $a ) {
			return ! SIS_Regiones::solo_feed( $a );
		} ) );
		if ( ! $ambitos ) {
			$ambitos = array( SIS_Regiones::por_defecto() );
		}

		$total    = 0;
		$mensajes = array();
		$fallos   = 0;

		foreach ( $ambitos as $ambito ) {
			$r = self::sincronizar_ambito( $ambito, $url_base, $ssl, $ttl, $anios, $min_mag );
			if ( $r['ok'] ) {
				$total     += $r['registros'];
				$mensajes[] = $r['mensaje'];
			} else {
				$fallos++;
				$mensajes[] = $r['mensaje'];
			}
		}

		return array(
			'ok'        => ( $fallos < count( $ambitos ) ),
			'registros' => $total,
			'mensaje'   => implode( ' · ', $mensajes ),
		);
	}

	/**
	 * Descarga y cachea el catálogo de un ámbito.
	 *
	 * @param string $ambito   Slug del ámbito.
	 * @param string $url_base Punto final FDSN.
	 * @param bool   $ssl      Verificación TLS.
	 * @param int    $ttl      Vida de la caché en segundos.
	 * @param int    $anios    Años de historia solicitados.
	 * @param float  $min_mag  Magnitud mínima.
	 * @return array {ok, registros, mensaje}
	 */
	public static function sincronizar_ambito( $ambito, $url_base, $ssl, $ttl, $anios, $min_mag ) {
		$ambito = SIS_Security::sanitizar_ambito( $ambito );
		$url    = self::construir_url( $url_base, $ambito, $anios, $min_mag );

		$r = SIS_Sync::http_get( $url, $ssl );
		if ( ! $r['ok'] ) {
			return array(
				'ok'        => false,
				'registros' => 0,
				'mensaje'   => $ambito . ': HTTP ' . $r['codigo'] . ' ' . $r['error'],
			);
		}

		$json = json_decode( $r['cuerpo'], true );
		if ( ! is_array( $json ) || ! isset( $json['features'] ) ) {
			return array(
				'ok'        => false,
				'registros' => 0,
				'mensaje'   => $ambito . ': respuesta sin GeoJSON válido',
			);
		}

		$eventos = SIS_Catalogo::normalizar( $json, array( 'ambito' => $ambito ) );
		if ( count( $eventos ) < 5 ) {
			// Muy pocos eventos: se conserva lo que hubiera antes en la caché
			// durable para no degradar la página con una respuesta anómala.
			$previo = SIS_Cache::get_durable( SIS_Catalogo::clave( $ambito ) );
			if ( is_array( $previo ) && count( $previo['eventos'] ) > count( $eventos ) ) {
				return array(
					'ok'        => false,
					'registros' => count( $eventos ),
					'mensaje'   => $ambito . ': respuesta con menos eventos de los ya cacheados; se conserva el catálogo anterior',
				);
			}
		}

		$payload = array(
			'eventos'     => $eventos,
			'ambito'      => $ambito,
			'consulta'    => $url,
			'anios'       => $anios,
			'min_mag'     => $min_mag,
			'actualizado' => current_time( 'mysql', true ),
			'fuente'      => 'USGS Earthquake Hazards Program — FDSN Event',
		);

		SIS_Cache::set( SIS_Catalogo::clave( $ambito ), $payload, $ttl, 'catalogo' );

		return array(
			'ok'        => true,
			'registros' => count( $eventos ),
			'mensaje'   => $ambito . ': ' . count( $eventos ) . ' sismos',
		);
	}

	/**
	 * Construye la URL de consulta FDSN para un ámbito.
	 *
	 * Los parámetros geográficos salen de SIS_Regiones (recuadro o radio), de
	 * modo que ninguna entrada del usuario llega cruda a la URL.
	 *
	 * @param string $url_base Punto final.
	 * @param string $ambito   Slug del ámbito.
	 * @param int    $anios    Años de historia.
	 * @param float  $min_mag  Magnitud mínima.
	 * @return string
	 */
	public static function construir_url( $url_base, $ambito, $anios, $min_mag ) {
		$args = array_merge(
			array(
				'format'       => 'geojson',
				'starttime'    => gmdate( 'Y-m-d', time() - (int) round( $anios * 365.25 * 86400 ) ),
				'endtime'      => gmdate( 'Y-m-d', time() + 86400 ),
				'minmagnitude' => number_format( (float) $min_mag, 1, '.', '' ),
				// Del más reciente hacia atrás. Si la consulta llegara al tope
				// del servicio, lo que se pierde es la cola antigua y no los
				// sismos de esta semana, que es lo que la gente viene a mirar.
				// El catálogo se reordena al normalizar, así que el orden de
				// llegada no afecta a nada más.
				'orderby'      => 'time',
				'limit'        => self::LIMITE,
				'eventtype'    => 'earthquake',
			),
			SIS_Regiones::parametros_fdsn( $ambito )
		);

		return add_query_arg( array_map( 'strval', $args ), $url_base );
	}

	/**
	 * Consulta el número de eventos que devolvería una configuración, sin
	 * descargar el catálogo (útil para el botón «Probar» del panel).
	 *
	 * @param array $cfg Configuración de la fuente.
	 * @param string $ambito Ámbito a probar.
	 * @return array {ok, total, mensaje}
	 */
	public static function contar( $cfg, $ambito = '' ) {
		$url_base = ! empty( $cfg['url'] ) ? $cfg['url'] : self::URL;
		$url_base = str_replace( '/query', '/count', $url_base );
		$anios    = isset( $cfg['anios'] ) ? (int) $cfg['anios'] : 36;
		$min_mag  = isset( $cfg['min_mag'] ) ? (float) $cfg['min_mag'] : 2.5;
		$ssl      = isset( $cfg['sslverify'] ) ? (bool) $cfg['sslverify'] : true;

		$url = self::construir_url( $url_base, SIS_Security::sanitizar_ambito( $ambito ), $anios, $min_mag );
		$r   = SIS_Sync::http_get( $url, $ssl );

		if ( ! $r['ok'] ) {
			return array( 'ok' => false, 'total' => 0, 'mensaje' => 'HTTP ' . $r['codigo'] . ' ' . $r['error'] );
		}

		$json = json_decode( $r['cuerpo'], true );
		$n    = isset( $json['count'] ) ? (int) $json['count'] : 0;

		return array(
			'ok'      => true,
			'total'   => $n,
			'mensaje' => $n . ' sismos disponibles con esta configuración',
		);
	}
}
