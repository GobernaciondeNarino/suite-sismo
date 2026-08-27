<?php
/**
 * Conector USGS — feeds GeoJSON de resumen (sismicidad reciente).
 *
 * Los feeds de resumen se regeneran cada minuto y sirven con CORS abierto, así
 * que se consumen por dos vías complementarias:
 *   · desde el servidor (este conector, por cron) para que la estadística y el
 *     pronóstico incorporen lo ocurrido desde la última sincronización pesada;
 *   · directamente desde el navegador en los componentes en vivo, para lograr
 *     frescura de ~1 minuto sin castigar al servidor.
 *
 * Feeds: https://earthquake.usgs.gov/earthquakes/feed/v1.0/summary/
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Sync_Feed {

	/** Base de los feeds de resumen. */
	const BASE = 'https://earthquake.usgs.gov/earthquakes/feed/v1.0/summary/';

	/*
	 * Tope de eventos que se guardan del feed sin recortar (vista global).
	 *
	 * El feed de un mes con magnitud 2,5 o mayor ronda los 2 200 sismos, así
	 * que el tope los admite enteros: recortarlo por debajo dejaría fuera los
	 * más antiguos del mes sin decirlo. Sigue siendo un tope, no una promesa:
	 * si un enjambre dispara el feed, se conservan los más recientes.
	 */
	const TOPE_MUNDO = 4000;

	/**
	 * Feeds admitidos (lista blanca: evita construir URLs arbitrarias).
	 *
	 * @return array<string,string>
	 */
	public static function feeds() {
		return array(
			'all_hour'     => 'Todos los sismos de la última hora',
			'all_day'      => 'Todos los sismos del último día',
			'all_week'     => 'Todos los sismos de la última semana',
			'all_month'    => 'Todos los sismos del último mes',
			'2.5_day'      => 'Magnitud 2,5+ del último día',
			'2.5_week'     => 'Magnitud 2,5+ de la última semana',
			'2.5_month'    => 'Magnitud 2,5+ del último mes',
			'4.5_week'     => 'Magnitud 4,5+ de la última semana',
			'significant_month' => 'Sismos significativos del último mes',
		);
	}

	/**
	 * URL de un feed validada contra la lista blanca.
	 *
	 * @param string $slug Slug del feed.
	 * @return string
	 */
	public static function url( $slug ) {
		$feeds = self::feeds();
		$slug  = (string) $slug;
		if ( ! isset( $feeds[ $slug ] ) ) {
			$slug = 'all_day';
		}
		return self::BASE . $slug . '.geojson';
	}

	/**
	 * Descarga el feed configurado y cachea los eventos del área de interés.
	 *
	 * @param array $cfg Configuración de la fuente.
	 * @return array {ok, registros, mensaje}
	 */
	public static function sincronizar( $cfg ) {
		$slug = ! empty( $cfg['dataset_id'] ) ? $cfg['dataset_id'] : 'all_day';
		$ssl  = isset( $cfg['sslverify'] ) ? (bool) $cfg['sslverify'] : true;
		$ttl  = isset( $cfg['ttl'] ) ? max( 1, (int) $cfg['ttl'] ) * 60 : 600;
		$url  = self::url( $slug );

		$r = SIS_Sync::http_get( $url, $ssl );
		if ( ! $r['ok'] ) {
			return array( 'ok' => false, 'registros' => 0, 'mensaje' => 'HTTP ' . $r['codigo'] . ' ' . $r['error'] );
		}

		$json = json_decode( $r['cuerpo'], true );
		if ( ! is_array( $json ) || ! isset( $json['features'] ) ) {
			return array( 'ok' => false, 'registros' => 0, 'mensaje' => 'Feed sin GeoJSON válido' );
		}

		// El feed es global: se recorta al ámbito más amplio del plugin para no
		// guardar miles de eventos irrelevantes.
		$eventos = SIS_Catalogo::normalizar( $json, array( 'ambito' => 'colombia' ) );

		$payload = array(
			'eventos'     => $eventos,
			'feed'        => $slug,
			'consulta'    => $url,
			'globales'    => count( $json['features'] ),
			'actualizado' => current_time( 'mysql', true ),
			'fuente'      => 'USGS — feed GeoJSON de resumen (' . $slug . ')',
		);

		SIS_Cache::set( 'feed', $payload, $ttl, 'feed' );

		// Además se guarda el feed sin recortar, que es lo que alimenta la
		// vista global del globo. Se limita a los más recientes para que la
		// respuesta no crezca sin control cuando el feed trae un enjambre.
		/*
		 * Sin municipio: el campo guarda el municipio de Nariño más cercano, y
		 * para un sismo en Japón eso es un dato absurdo —el globo llegaría a
		 * poner «Cerca de Tumaco» a nueve mil kilómetros— además del grueso
		 * del coste de normalizar dos mil eventos, que son dos mil barridos
		 * sobre los sesenta y cuatro municipios.
		 */
		$mundo = SIS_Catalogo::normalizar( $json, array(
			'ambito'            => 'mundo',
			'asignar_municipio' => false,
		) );
		if ( count( $mundo ) > self::TOPE_MUNDO ) {
			$mundo = array_slice( $mundo, -1 * self::TOPE_MUNDO );
		}
		SIS_Cache::set(
			'feed_mundo',
			array(
				'eventos'     => $mundo,
				'feed'        => $slug,
				'consulta'    => $url,
				'actualizado' => current_time( 'mysql', true ),
				'fuente'      => 'USGS — feed GeoJSON de resumen (' . $slug . ')',
			),
			$ttl,
			'feed'
		);

		return array(
			'ok'        => true,
			'registros' => count( $eventos ),
			'mensaje'   => count( $eventos ) . ' sismos en el área (de ' . count( $json['features'] ) . ' globales)',
		);
	}
}
