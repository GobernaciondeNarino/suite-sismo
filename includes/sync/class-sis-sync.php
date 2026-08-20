<?php
/**
 * Orquestador de sincronización (Capa 1 — servidor / WP-Cron).
 *
 * Recorre las fuentes activas, delega en cada conector, actualiza el estado en
 * la configuración y registra cada sincronización en wp_sis_audit. Expone
 * además el GET HTTP resiliente que usan los conectores, con la lista blanca
 * de servidores aplicada antes de salir a la red.
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Sync {

	/** Mapa slug → clase conectora. */
	const FUENTES = array(
		'usgs_fdsn' => 'SIS_Sync_Usgs',
		'usgs_feed' => 'SIS_Sync_Feed',
	);

	public function __construct() {
		add_action( SIS_Activator::HOOK_CRON, array( $this, 'ejecutar' ) );
		add_action( SIS_Activator::HOOK_FEED, array( $this, 'ejecutar_feed' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'intervalos_personalizados' ) );
	}

	/**
	 * Intervalos de cron propios (1, 6 y 12 horas), etiquetados en WP-Cron.
	 * Estático para poder registrarlo también durante la activación.
	 *
	 * @param array $programas Programas existentes.
	 * @return array
	 */
	public static function intervalos_personalizados( $programas ) {
		$programas['sis_1h']  = array(
			'interval' => 3600,
			'display'  => __( 'Cada hora (Sismos Nariño)', 'sismos-narino' ),
		);
		$programas['sis_6h']  = array(
			'interval' => 6 * 3600,
			'display'  => __( 'Cada 6 horas (Sismos Nariño)', 'sismos-narino' ),
		);
		$programas['sis_12h'] = array(
			'interval' => 12 * 3600,
			'display'  => __( 'Cada 12 horas (Sismos Nariño)', 'sismos-narino' ),
		);
		return $programas;
	}

	/**
	 * Callback del cron principal: sincroniza el catálogo histórico.
	 */
	public function ejecutar() {
		$config = get_option( 'sis_api_config', array() );
		foreach ( self::FUENTES as $slug => $clase ) {
			if ( 'usgs_feed' === $slug ) {
				continue; // el feed tiene su propio cron, mucho más frecuente.
			}
			if ( empty( $config[ $slug ] ) || empty( $config[ $slug ]['activa'] ) ) {
				continue;
			}
			$this->ejecutar_fuente( $slug );
		}
	}

	/**
	 * Callback del cron rápido: refresca el feed de sismos recientes.
	 */
	public function ejecutar_feed() {
		$config = get_option( 'sis_api_config', array() );
		if ( ! empty( $config['usgs_feed']['activa'] ) ) {
			$this->ejecutar_fuente( 'usgs_feed' );
		}
	}

	/**
	 * Sincroniza una fuente concreta y actualiza su estado.
	 *
	 * @param string $slug Slug de la fuente.
	 * @return array {ok, registros, mensaje, latencia_ms}.
	 */
	public function ejecutar_fuente( $slug ) {
		$config = get_option( 'sis_api_config', array() );
		if ( empty( $config[ $slug ] ) || ! isset( self::FUENTES[ $slug ] ) ) {
			return array(
				'ok'          => false,
				'registros'   => 0,
				'mensaje'     => 'Fuente desconocida',
				'latencia_ms' => 0,
			);
		}

		$clase = __NAMESPACE__ . '\\' . self::FUENTES[ $slug ];
		$cfg   = $config[ $slug ];

		// Descifra la credencial opcional (el USGS no la exige).
		if ( ! empty( $cfg['clave'] ) ) {
			$cfg['clave_plana'] = SIS_Security::descifrar( $cfg['clave'] );
		}

		$t0  = microtime( true );
		$res = call_user_func( array( $clase, 'sincronizar' ), $cfg );
		$ms  = (int) round( ( microtime( true ) - $t0 ) * 1000 );

		if ( ! is_array( $res ) ) {
			$res = array( 'ok' => false, 'registros' => 0, 'mensaje' => 'Respuesta inválida del conector' );
		}
		$res = wp_parse_args( $res, array( 'ok' => false, 'registros' => 0, 'mensaje' => '' ) );

		$config[ $slug ]['ultima_sync']      = time();
		$config[ $slug ]['ultimo_resultado'] = ( $res['ok'] ? 'OK' : 'ERROR' ) . ' · ' . (int) $res['registros'] . ' reg · ' . $ms . ' ms';
		update_option( 'sis_api_config', $config );

		self::auditar( 'sync', $slug, $res['ok'] ? 'ok' : 'error', (int) $res['registros'], $res['mensaje'] );

		$res['latencia_ms'] = $ms;
		return $res;
	}

	/**
	 * GET HTTP resiliente para los conectores, con lista blanca de servidores.
	 *
	 * @param string $url       URL absoluta https.
	 * @param bool   $sslverify Verificar certificado.
	 * @param array  $args      Argumentos extra de wp_remote_get.
	 * @return array {ok, codigo, cuerpo, error}.
	 */
	public static function http_get( $url, $sslverify = true, $args = array() ) {
		if ( ! SIS_Security::url_permitida( $url ) ) {
			return array(
				'ok'     => false,
				'codigo' => 0,
				'cuerpo' => '',
				'error'  => 'URL no permitida: el servidor no está en la lista blanca del plugin.',
			);
		}

		$def  = array(
			'timeout'     => 30,
			'sslverify'   => (bool) $sslverify,
			'redirection' => 3,
			'headers'     => array( 'Accept' => 'application/json, */*' ),
			'user-agent'  => 'SismosNarino/' . ( defined( 'SIS_VERSION' ) ? SIS_VERSION : '1.0' ) . ' (+https://gobiernoabierto.narino.gov.co)',
		);
		$args = array_merge( $def, $args );
		$resp = wp_remote_get( $url, $args );

		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'codigo' => 0, 'cuerpo' => '', 'error' => $resp->get_error_message() );
		}

		$codigo = (int) wp_remote_retrieve_response_code( $resp );
		return array(
			'ok'     => ( $codigo >= 200 && $codigo < 300 ),
			'codigo' => $codigo,
			'cuerpo' => wp_remote_retrieve_body( $resp ),
			'error'  => '',
		);
	}

	/**
	 * Registra un evento en la tabla de auditoría (timestamp UTC).
	 *
	 * @param string $evento    Tipo de evento.
	 * @param string $fuente    Fuente.
	 * @param string $resultado ok|error|...
	 * @param int    $registros Nº de registros.
	 * @param string $detalle   Detalle.
	 */
	public static function auditar( $evento, $fuente, $resultado, $registros = 0, $detalle = '' ) {
		global $wpdb;
		$tabla = $wpdb->prefix . 'sis_audit';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$tabla,
			array(
				'evento'    => substr( (string) $evento, 0, 64 ),
				'fuente'    => substr( (string) $fuente, 0, 64 ),
				'resultado' => substr( (string) $resultado, 0, 32 ),
				'detalle'   => substr( (string) $detalle, 0, 1000 ),
				'registros' => (int) $registros,
				'ts'        => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Estado de salud de las fuentes, para el panel público y el admin.
	 *
	 * En modo público se recorta el detalle: el mensaje de error de una fuente
	 * puede incluir la URL consultada o la respuesta cruda del proveedor, y eso
	 * no aporta nada al visitante mientras sí describe la instalación.
	 *
	 * @param bool $publico Recortar la información sensible.
	 * @return array[]
	 */
	public static function estado( $publico = false ) {
		$config = get_option( 'sis_api_config', array() );
		$out    = array();

		foreach ( self::FUENTES as $slug => $clase ) {
			$cfg = isset( $config[ $slug ] ) ? $config[ $slug ] : array();
			$ult = isset( $cfg['ultima_sync'] ) ? (int) $cfg['ultima_sync'] : 0;

			$resultado = isset( $cfg['ultimo_resultado'] ) ? $cfg['ultimo_resultado'] : '';
			if ( $publico ) {
				$resultado = self::resultado_publico( $resultado );
			}

			$out[] = array(
				'slug'             => $slug,
				'nombre'           => isset( $cfg['nombre'] ) ? $cfg['nombre'] : $slug,
				'activa'           => ! empty( $cfg['activa'] ),
				'capa'             => isset( $cfg['capa'] ) ? $cfg['capa'] : 'cron',
				'ultima_sync'      => $ult ? gmdate( 'c', $ult ) : '',
				'hace_horas'       => $ult ? round( ( time() - $ult ) / 3600, 1 ) : null,
				'ultimo_resultado' => $resultado,
				'salud'            => self::salud( $cfg ),
			);
		}

		return $out;
	}

	/**
	 * Reduce el resultado de la última sincronización a lo publicable:
	 * el veredicto y el número de registros, sin detalle técnico.
	 *
	 * @param string $resultado Cadena guardada en la configuración.
	 * @return string
	 */
	private static function resultado_publico( $resultado ) {
		if ( '' === $resultado ) {
			return '';
		}
		if ( 0 === strpos( $resultado, 'ERROR' ) ) {
			return 'ERROR';
		}
		return preg_match( '/^OK · (\d+) reg/u', $resultado, $m )
			? 'OK · ' . (int) $m[1] . ' registros'
			: 'OK';
	}

	/**
	 * Semáforo de salud de una fuente.
	 *
	 * @param array $cfg Configuración de la fuente.
	 * @return string ok | atrasada | error | inactiva
	 */
	private static function salud( $cfg ) {
		if ( empty( $cfg['activa'] ) ) {
			return 'inactiva';
		}
		if ( empty( $cfg['ultima_sync'] ) ) {
			return 'sin_datos';
		}
		if ( ! empty( $cfg['ultimo_resultado'] ) && 0 === strpos( $cfg['ultimo_resultado'], 'ERROR' ) ) {
			return 'error';
		}

		$horas   = ( time() - (int) $cfg['ultima_sync'] ) / 3600;
		$umbral  = isset( $cfg['frecuencia'] ) ? max( 1, (int) $cfg['frecuencia'] ) : 12;
		return ( $horas > ( $umbral * 3 ) ) ? 'atrasada' : 'ok';
	}
}
