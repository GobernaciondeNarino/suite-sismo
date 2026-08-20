<?php
/**
 * Caché de dos niveles: transient (rápido) + tabla durable wp_sis_cache.
 *
 * El catálogo sísmico sincronizado por cron sobrevive al vaciado de
 * transients gracias a la tabla; además la semilla JSON de data/ actúa como
 * último recurso cuando el USGS no responde (principio de resiliencia: la
 * página nunca debe quedarse en blanco).
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Cache {

	/** Prefijo de transients. */
	const PREFIJO = 'sis_';

	/**
	 * Lee un valor de caché. Primero transient, luego tabla durable.
	 *
	 * @param string $clave Clave lógica (sin prefijo).
	 * @return mixed|null   Valor decodificado o null si no existe / expiró.
	 */
	public static function get( $clave ) {
		$clave = self::normalizar( $clave );

		$t = get_transient( self::PREFIJO . $clave );
		if ( false !== $t ) {
			return $t;
		}

		global $wpdb;
		$tabla = $wpdb->prefix . 'sis_cache';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$fila = $wpdb->get_row(
			$wpdb->prepare( "SELECT valor, expira FROM {$tabla} WHERE clave = %s", $clave ),
			\ARRAY_A
		);

		if ( ! $fila ) {
			return null;
		}

		if ( (int) $fila['expira'] > 0 && (int) $fila['expira'] < time() ) {
			return null; // expirado (la fila permanece como fallback durable).
		}

		$valor = json_decode( $fila['valor'], true );

		// Re-ceba el transient para acelerar siguientes lecturas.
		$ttl = max( 60, (int) $fila['expira'] - time() );
		set_transient( self::PREFIJO . $clave, $valor, $ttl );

		return $valor;
	}

	/**
	 * Lee el valor durable ignorando expiración (fallback de resiliencia).
	 *
	 * @param string $clave Clave lógica.
	 * @return mixed|null
	 */
	public static function get_durable( $clave ) {
		global $wpdb;
		$clave = self::normalizar( $clave );
		$tabla = $wpdb->prefix . 'sis_cache';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$valor = $wpdb->get_var( $wpdb->prepare( "SELECT valor FROM {$tabla} WHERE clave = %s", $clave ) );
		return null === $valor ? null : json_decode( $valor, true );
	}

	/**
	 * Marca temporal (UTC, mysql) de la última escritura de una clave.
	 *
	 * @param string $clave Clave lógica.
	 * @return string|null
	 */
	public static function actualizado( $clave ) {
		global $wpdb;
		$clave = self::normalizar( $clave );
		$tabla = $wpdb->prefix . 'sis_cache';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_var( $wpdb->prepare( "SELECT actualizado FROM {$tabla} WHERE clave = %s", $clave ) );
	}

	/**
	 * Guarda un valor en ambos niveles.
	 *
	 * @param string $clave        Clave lógica.
	 * @param mixed  $valor        Valor serializable a JSON.
	 * @param int    $ttl_segundos Vida en segundos.
	 * @param string $grupo        Grupo lógico (auditoría/limpieza).
	 * @return bool
	 */
	public static function set( $clave, $valor, $ttl_segundos = 3600, $grupo = 'general' ) {
		$clave = self::normalizar( $clave );
		$ttl   = max( 60, (int) $ttl_segundos );

		set_transient( self::PREFIJO . $clave, $valor, $ttl );

		global $wpdb;
		$tabla = $wpdb->prefix . 'sis_cache';
		$json  = wp_json_encode( $valor );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$res = $wpdb->replace(
			$tabla,
			array(
				'clave'       => $clave,
				'grupo'       => $grupo,
				'valor'       => $json,
				'expira'      => time() + $ttl,
				'actualizado' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%s' )
		);

		return false !== $res;
	}

	/**
	 * Elimina una clave de ambos niveles.
	 *
	 * @param string $clave Clave lógica.
	 */
	public static function delete( $clave ) {
		$clave = self::normalizar( $clave );
		delete_transient( self::PREFIJO . $clave );
		global $wpdb;
		$tabla = $wpdb->prefix . 'sis_cache';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $tabla, array( 'clave' => $clave ), array( '%s' ) );
	}

	/**
	 * Borra todas las claves de un grupo (p. ej. al recalcular el pronóstico).
	 *
	 * @param string $grupo Grupo lógico.
	 * @return int Filas borradas.
	 */
	public static function delete_grupo( $grupo ) {
		global $wpdb;
		$tabla = $wpdb->prefix . 'sis_cache';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$claves = $wpdb->get_col( $wpdb->prepare( "SELECT clave FROM {$tabla} WHERE grupo = %s", $grupo ) );
		foreach ( (array) $claves as $c ) {
			delete_transient( self::PREFIJO . $c );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->delete( $tabla, array( 'grupo' => $grupo ), array( '%s' ) );
	}

	/**
	 * Acota el tamaño de un grupo de caché borrando las entradas más antiguas.
	 *
	 * Las respuestas de la API se cachean por combinación de parámetros. Como
	 * los parámetros los elige quien llama, sin un tope alguien podría crear
	 * miles de filas variando la consulta: esto lo impide.
	 *
	 * @param string $grupo Grupo lógico.
	 * @param int    $max   Nº máximo de entradas que se conservan.
	 * @return int Filas borradas.
	 */
	public static function podar_grupo( $grupo, $max = 200 ) {
		global $wpdb;
		$tabla = $wpdb->prefix . 'sis_cache';
		$max   = max( 10, (int) $max );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$sobran = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$tabla} WHERE grupo = %s", $grupo )
		) - $max;

		if ( $sobran <= 0 ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$claves = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT clave FROM {$tabla} WHERE grupo = %s ORDER BY actualizado ASC LIMIT %d",
				$grupo,
				$sobran
			)
		);

		$n = 0;
		foreach ( (array) $claves as $c ) {
			self::delete( $c );
			$n++;
		}
		return $n;
	}

	/**
	 * Carga una semilla JSON de la carpeta data/ (último fallback).
	 *
	 * @param string $archivo Nombre de archivo dentro de data/.
	 * @return mixed|null
	 */
	public static function semilla( $archivo ) {
		$ruta = SIS_DIR . 'data/' . ltrim( $archivo, '/\\' );
		// Evita traspaso de directorio.
		$ruta_real = realpath( $ruta );
		$base_real = realpath( SIS_DIR . 'data' );
		if ( ! $ruta_real || ! $base_real || 0 !== strpos( $ruta_real, $base_real ) ) {
			return null;
		}
		$contenido = file_get_contents( $ruta_real ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $contenido ) {
			return null;
		}
		return json_decode( $contenido, true );
	}

	/**
	 * Normaliza la clave a 160 chars seguros (la columna admite 191).
	 *
	 * @param string $clave Clave cruda.
	 * @return string
	 */
	private static function normalizar( $clave ) {
		$clave = preg_replace( '/[^a-z0-9_\-:.]/i', '_', (string) $clave );
		return substr( $clave, 0, 160 );
	}
}
