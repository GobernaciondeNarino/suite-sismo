<?php
/**
 * Desinstalación: borra tablas, opciones, transients y eventos de cron.
 *
 * Se ejecuta SOLO cuando el usuario elimina el plugin desde WordPress.
 * Desactivar el plugin NO borra nada (ver SIS_Activator::desactivar).
 *
 * @package SismosNarino
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// 1) Tablas propias.
$tablas = array( $wpdb->prefix . 'sis_cache', $wpdb->prefix . 'sis_audit' );
foreach ( $tablas as $tabla ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$wpdb->query( "DROP TABLE IF EXISTS {$tabla}" );
}

// 2) Opciones.
foreach ( array( 'sis_api_config', 'sis_estilo', 'sis_version', 'sis_amenaza', 'sis_modelo' ) as $opcion ) {
	delete_option( $opcion );
}

// 3) Transients del plugin (caché de primer nivel y rate-limit).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_sis_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_sis_' ) . '%'
	)
);

// 4) Cron.
foreach ( array( 'sis_cron_sync', 'sis_cron_feed' ) as $hook ) {
	wp_clear_scheduled_hook( $hook );
}
