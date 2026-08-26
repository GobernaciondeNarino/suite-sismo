<?php
/**
 * Activación / desactivación del plugin.
 *
 * Crea las tablas (caché durable y auditoría), agenda los dos crones
 * (catálogo cada 12 h, feed cada hora) y siembra las opciones por defecto:
 * configuración de fuentes, apariencia minimalista y parámetros del modelo de
 * pronóstico.
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Activator {

	/** Cron del catálogo histórico (pesado). */
	const HOOK_CRON = 'sis_cron_sync';

	/** Cron del feed de sismicidad reciente (ligero). */
	const HOOK_FEED = 'sis_cron_feed';

	/**
	 * Se ejecuta al activar el plugin.
	 */
	public static function activar() {
		self::crear_tablas();
		self::sembrar_opciones();
		self::agendar_cron();

		update_option( 'sis_version', SIS_VERSION );

		// Primera sincronización inmediata: la página no debería estrenarse vacía.
		if ( ! wp_next_scheduled( self::HOOK_CRON ) ) {
			wp_schedule_single_event( time() + 30, self::HOOK_CRON );
		}

		flush_rewrite_rules();
	}

	/**
	 * Se ejecuta al desactivar el plugin (no borra datos).
	 */
	public static function desactivar() {
		foreach ( array( self::HOOK_CRON, self::HOOK_FEED ) as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
			wp_clear_scheduled_hook( $hook );
		}
		flush_rewrite_rules();
	}

	/**
	 * Crea las tablas del plugin con dbDelta.
	 */
	private static function crear_tablas() {
		global $wpdb;
		require_once \ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset     = $wpdb->get_charset_collate();
		$tabla_cache = $wpdb->prefix . 'sis_cache';
		$tabla_audit = $wpdb->prefix . 'sis_audit';

		$sql_cache = "CREATE TABLE {$tabla_cache} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			clave varchar(191) NOT NULL,
			grupo varchar(64) NOT NULL DEFAULT 'general',
			valor longtext NULL,
			expira int(11) unsigned NOT NULL DEFAULT 0,
			actualizado datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY clave (clave),
			KEY grupo (grupo)
		) {$charset};";

		$sql_audit = "CREATE TABLE {$tabla_audit} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			evento varchar(64) NOT NULL DEFAULT '',
			fuente varchar(64) NOT NULL DEFAULT '',
			resultado varchar(32) NOT NULL DEFAULT '',
			detalle text NULL,
			registros int(11) NOT NULL DEFAULT 0,
			ts datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY evento (evento),
			KEY ts (ts)
		) {$charset};";

		dbDelta( $sql_cache );
		dbDelta( $sql_audit );
	}

	/**
	 * Siembra las opciones por defecto sin sobrescribir si ya existen.
	 */
	private static function sembrar_opciones() {
		add_option( 'sis_api_config', self::config_apis_por_defecto() );
		add_option( 'sis_estilo', self::estilo_por_defecto() );
		add_option( 'sis_amenaza', SIS_Amenaza::normativa_por_defecto() );
	}

	/**
	 * Valores por defecto de la apariencia (minimalismo transparente: los
	 * shortcodes se funden con la página anfitriona).
	 *
	 * @return array
	 */
	public static function estilo_por_defecto() {
		return array(
			'fondo'          => 'transparent',
			'texto'          => 'inherit',
			'tipografia'     => 'inherit',
			'acento'         => '#003087', // azul profundo institucional
			'acento_2'       => '#FFD500', // amarillo institucional
			'acento_tecnico' => '#C0392B', // rojo técnico (magnitud alta)
			'mute'           => '#6b7280',
			'borde'          => 'none',
			'borde_color'    => '#e5e7eb',
			'borde_radio'    => '0',
			'sombra'         => 'none',
			'ancho_max'      => '100%',
			'espaciado'      => '0',
		);
	}

	/**
	 * Configuración por defecto de cada fuente de datos.
	 *
	 * @return array
	 */
	public static function config_apis_por_defecto() {
		return array(
			'usgs_fdsn' => array(
				'nombre'           => 'USGS — FDSN Event Web Service (catálogo histórico)',
				'activa'           => true,
				'capa'             => 'cron',
				'url'              => SIS_Sync_Usgs::URL,
				'dataset_id'       => '',
				'clave'            => '',
				'ambitos'          => array( 'regional', 'narino' ),
				'anios'            => 36,
				'min_mag'          => 2.5,
				'frecuencia'       => 12,
				'ttl'              => 720,   // minutos
				'sslverify'        => true,
				'ultima_sync'      => 0,
				'ultimo_resultado' => '',
			),
			'usgs_feed' => array(
				'nombre'           => 'USGS — feed GeoJSON de resumen (sismicidad reciente)',
				'activa'           => true,
				'capa'             => 'cron + navegador',
				'url'              => SIS_Sync_Feed::BASE . 'all_day.geojson',
				'dataset_id'       => 'all_day',
				'clave'            => '',
				'frecuencia'       => 1,
				'ttl'              => 10,    // minutos
				'sslverify'        => true,
				'ultima_sync'      => 0,
				'ultimo_resultado' => '',
			),
		);
	}

	/**
	 * Migración al actualizar el plugin (sin reactivar): añade a sis_api_config
	 * y a sis_modelo las claves nuevas que aún no estén presentes, sin
	 * sobrescribir lo que el usuario ya configuró.
	 */
	public static function migrar_si_necesario() {
		$guardada = get_option( 'sis_version' );
		if ( SIS_VERSION === $guardada ) {
			return;
		}

		$cambio = false;

		$config = get_option( 'sis_api_config', array() );
		if ( is_array( $config ) ) {
			foreach ( self::config_apis_por_defecto() as $slug => $cfg ) {
				if ( ! isset( $config[ $slug ] ) ) {
					$config[ $slug ] = $cfg;
					$cambio          = true;
					continue;
				}
				foreach ( $cfg as $k => $v ) {
					if ( ! array_key_exists( $k, $config[ $slug ] ) ) {
						$config[ $slug ][ $k ] = $v;
						$cambio                = true;
					}
				}
			}
			if ( $cambio ) {
				update_option( 'sis_api_config', $config );
			}
		}

		$amenaza = get_option( 'sis_amenaza', array() );
		if ( is_array( $amenaza ) ) {
			$nuevo = wp_parse_args( $amenaza, SIS_Amenaza::normativa_por_defecto() );
			if ( $nuevo !== $amenaza ) {
				update_option( 'sis_amenaza', $nuevo );
				$cambio = true;
			}
		}

		// La opción que guardaba el modelo retirado ya no existe: se borra al migrar.
		delete_option( 'sis_modelo' );

		self::agendar_cron();

		if ( $cambio ) {
			SIS_Sync::auditar( 'migracion', 'plugin', 'ok', 0, 'Configuración migrada en la actualización a ' . SIS_VERSION );
		}

		update_option( 'sis_version', SIS_VERSION );
	}

	/**
	 * Agenda los crones: catálogo cada 12 h y feed cada hora.
	 */
	private static function agendar_cron() {
		add_filter( 'cron_schedules', array( SIS_Sync::class, 'intervalos_personalizados' ) );

		if ( ! wp_next_scheduled( self::HOOK_CRON ) ) {
			wp_schedule_event( time() + 60, 'sis_12h', self::HOOK_CRON );
		}
		if ( ! wp_next_scheduled( self::HOOK_FEED ) ) {
			wp_schedule_event( time() + 120, 'sis_1h', self::HOOK_FEED );
		}
	}
}
