<?php
/**
 * Seguridad transversal: sanitización de entradas del dominio sísmico,
 * lista blanca de servidores (anti-SSRF), rate-limiting por IP y cifrado en
 * reposo de credenciales opcionales.
 *
 * El USGS no exige clave de API, pero la configuración admite fuentes
 * alternativas (p. ej. un servicio FDSN nacional), y toda URL configurable
 * se valida contra la lista blanca antes de que el servidor la consulte.
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Security {

	/**
	 * Servidores permitidos para las peticiones salientes del servidor.
	 * Cualquier URL configurada fuera de esta lista se rechaza (anti-SSRF).
	 */
	const HOSTS = array(
		'earthquake.usgs.gov',
		'service.iris.edu',
		'www.fdsn.org',
		'sismo.sgc.gov.co',
		'www.sgc.gov.co',
	);

	/* ----------------------------------------------------------------- */
	/* Sanitización de entradas                                          */
	/* ----------------------------------------------------------------- */

	/**
	 * Normaliza el ámbito espacial a uno de los declarados en SIS_Regiones.
	 *
	 * @param string $valor Ámbito solicitado.
	 * @return string Slug válido ('narino' | 'regional' | 'radio' | 'colombia').
	 */
	public static function sanitizar_ambito( $valor ) {
		$valor = sanitize_key( (string) $valor );
		return SIS_Regiones::existe( $valor ) ? $valor : SIS_Regiones::por_defecto();
	}

	/**
	 * Sanitiza una magnitud (0–10) con un decimal.
	 *
	 * @param mixed $valor    Magnitud cruda.
	 * @param float $defecto  Valor por defecto si no es numérica.
	 * @return float
	 */
	public static function sanitizar_magnitud( $valor, $defecto = 0.0 ) {
		if ( ! is_numeric( $valor ) ) {
			return (float) $defecto;
		}
		$m = (float) $valor;
		return round( max( 0.0, min( 10.0, $m ) ), 1 );
	}

	/**
	 * Sanitiza un número de días de ventana temporal.
	 *
	 * @param mixed $valor   Días.
	 * @param int   $defecto Días por defecto.
	 * @param int   $max     Máximo admitido.
	 * @return int
	 */
	public static function sanitizar_dias( $valor, $defecto = 30, $max = 20000 ) {
		$d = is_numeric( $valor ) ? (int) $valor : (int) $defecto;
		return max( 1, min( (int) $max, $d ) );
	}

	/**
	 * Sanitiza un mes en formato AAAA-MM; cae al mes actual si es inválido.
	 *
	 * @param string $valor Mes.
	 * @return string AAAA-MM.
	 */
	public static function sanitizar_mes( $valor ) {
		$valor = sanitize_text_field( (string) $valor );
		if ( preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $valor ) ) {
			return $valor;
		}
		return gmdate( 'Y-m' );
	}

	/**
	 * Sanitiza una fecha AAAA-MM-DD; devuelve '' si no es válida.
	 *
	 * @param string $valor Fecha.
	 * @return string
	 */
	public static function sanitizar_fecha( $valor ) {
		$valor = sanitize_text_field( (string) $valor );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m ) ) {
			return '';
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $valor : '';
	}

	/**
	 * Normaliza un municipio a un DIVIPOLA válido o 'departamento'.
	 *
	 * @param string $valor Código DIVIPOLA o nombre.
	 * @return string DIVIPOLA de 5 dígitos o 'departamento'.
	 */
	public static function sanitizar_divipola( $valor ) {
		$valor = sanitize_text_field( (string) $valor );

		if ( '' === $valor || 0 === strcasecmp( $valor, 'departamento' ) ) {
			return 'departamento';
		}

		if ( preg_match( '/^\d{5}$/', $valor ) ) {
			return SIS_Municipios::existe( $valor ) ? $valor : 'departamento';
		}

		$mun = SIS_Municipios::por_nombre( $valor );
		return $mun ? $mun['divipola'] : 'departamento';
	}

	/* ----------------------------------------------------------------- */
	/* Anti-SSRF                                                         */
	/* ----------------------------------------------------------------- */

	/**
	 * Valida que una URL sea https y apunte a un host de la lista blanca.
	 *
	 * @param string $url URL a consultar desde el servidor.
	 * @return bool
	 */
	public static function url_permitida( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return false;
		}
		$partes = wp_parse_url( $url );
		if ( empty( $partes['scheme'] ) || empty( $partes['host'] ) ) {
			return false;
		}
		if ( 'https' !== strtolower( $partes['scheme'] ) ) {
			return false;
		}
		$host = strtolower( $partes['host'] );
		return in_array( $host, array_map( 'strtolower', self::HOSTS ), true );
	}

	/**
	 * Valida que un par lat/lon sea geográficamente coherente.
	 *
	 * @param float $lat Latitud.
	 * @param float $lon Longitud.
	 * @return bool
	 */
	public static function validar_coordenada( $lat, $lon ) {
		$lat = (float) $lat;
		$lon = (float) $lon;
		return $lat >= -90.0 && $lat <= 90.0 && $lon >= -180.0 && $lon <= 180.0;
	}

	/* ----------------------------------------------------------------- */
	/* Rate-limiting                                                     */
	/* ----------------------------------------------------------------- */

	/**
	 * Limita peticiones por IP usando un contador en transient.
	 *
	 * @param string $clave_base Identificador del recurso protegido.
	 * @param int    $max        Máximo de peticiones por ventana.
	 * @param int    $ventana    Tamaño de la ventana en segundos.
	 * @return bool True si se permite; false si se excedió el límite.
	 */
	public static function rate_limit( $clave_base, $max = 60, $ventana = 60 ) {
		$ip    = self::ip_cliente();
		$clave = 'sis_rl_' . md5( $clave_base . '|' . $ip );
		$n     = (int) get_transient( $clave );

		if ( $n >= (int) $max ) {
			return false;
		}
		set_transient( $clave, $n + 1, (int) $ventana );
		return true;
	}

	/**
	 * Obtiene la IP del cliente de forma segura.
	 *
	 * @return string
	 */
	public static function ip_cliente() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '0.0.0.0';
		$ip = filter_var( $ip, \FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
		return $ip;
	}

	/* ----------------------------------------------------------------- */
	/* Cifrado en reposo de credenciales opcionales                      */
	/* ----------------------------------------------------------------- */

	/**
	 * Cifra un texto con sodium_crypto_secretbox.
	 *
	 * @param string $texto Texto plano.
	 * @return string Paquete base64 (nonce + cifrado) o '' si no se pudo.
	 */
	public static function cifrar( $texto ) {
		$texto = (string) $texto;
		if ( '' === $texto || ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return '';
		}
		try {
			$nonce   = random_bytes( \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cifrado = sodium_crypto_secretbox( $texto, $nonce, self::clave_cifrado() );
			return base64_encode( $nonce . $cifrado ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Descifra un paquete generado por self::cifrar().
	 *
	 * @param string $paquete Paquete base64.
	 * @return string Texto plano o '' si falla.
	 */
	public static function descifrar( $paquete ) {
		$paquete = (string) $paquete;
		if ( '' === $paquete || ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return '';
		}
		$raw = base64_decode( $paquete, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( false === $raw || strlen( $raw ) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$nonce   = substr( $raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cifrado = substr( $raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plano   = sodium_crypto_secretbox_open( $cifrado, $nonce, self::clave_cifrado() );
		return false === $plano ? '' : $plano;
	}

	/**
	 * Deriva la clave de 32 bytes a partir de las sales de wp-config.
	 *
	 * @return string 32 bytes binarios.
	 */
	private static function clave_cifrado() {
		$material = '';
		if ( defined( 'AUTH_KEY' ) ) {
			$material .= \AUTH_KEY;
		}
		if ( defined( 'SECURE_AUTH_SALT' ) ) {
			$material .= \SECURE_AUTH_SALT;
		}
		if ( '' === $material && function_exists( 'wp_salt' ) ) {
			$material = wp_salt( 'secure_auth' );
		}
		return hash( 'sha256', 'sis-cifrado|' . $material, true );
	}
}
