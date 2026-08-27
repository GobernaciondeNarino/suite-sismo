<?php
/**
 * Periodo consultado: de atributos del shortcode a filtros y a lenguaje.
 *
 * Los componentes se publican con atributos de ventana temporal —«dias»,
 * «anio», «anios», «mes»— y esos mismos atributos tienen que llegar a tres
 * sitios: al filtro que recorta el catálogo, al texto que explica qué se está
 * mirando y a la clave de caché. Antes cada capa los interpretaba por su
 * cuenta y era fácil que el gráfico dijera una cosa y su descripción otra.
 *
 * Aquí se normalizan una sola vez y se devuelven en las tres formas.
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Periodo {

	/** Meses en español, para la etiqueta legible. */
	const MESES = array(
		1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
		5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
		9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
	);

	/** Primer año con catálogo utilizable; antes no hay cobertura instrumental. */
	const ANIO_MIN = 1990;

	/**
	 * Normaliza los atributos de ventana temporal.
	 *
	 * Precedencia deliberada: una fecha de calendario —«anio», «mes»— gana
	 * sobre una ventana móvil —«dias», «anios»—, porque quien escribe
	 * anio="2026" está pidiendo ese año concreto y no «los últimos N». Entre
	 * las dos móviles gana «dias», que es la más específica.
	 *
	 * @param array $args Atributos crudos.
	 * @return array {dias, anio, mes, anios} ya acotados.
	 */
	public static function normalizar( $args ) {
		$a = is_array( $args ) ? $args : array();

		$leer = static function ( $clave ) use ( $a ) {
			return isset( $a[ $clave ] ) && '' !== $a[ $clave ] && null !== $a[ $clave ] ? $a[ $clave ] : '';
		};

		$anio = '' !== $leer( 'anio' ) ? (int) $leer( 'anio' ) : 0;
		if ( $anio < self::ANIO_MIN || $anio > ( (int) gmdate( 'Y' ) ) ) {
			$anio = 0;
		}

		$mes = '' !== $leer( 'mes' ) ? (int) $leer( 'mes' ) : 0;
		if ( $mes < 1 || $mes > 12 ) {
			$mes = 0;
		}

		$dias  = '' !== $leer( 'dias' ) ? max( 0, min( 20000, (int) $leer( 'dias' ) ) ) : 0;
		$anios = '' !== $leer( 'anios' ) ? max( 0, min( 60, (int) $leer( 'anios' ) ) ) : 0;

		// Un mes suelto necesita año: se entiende como el mes de este año.
		if ( $mes && ! $anio ) {
			$anio = (int) gmdate( 'Y' );
		}

		// Con fecha de calendario, las ventanas móviles no pintan nada.
		if ( $anio ) {
			$dias  = 0;
			$anios = 0;
		} elseif ( $dias ) {
			$anios = 0;
		}

		return compact( 'dias', 'anio', 'mes', 'anios' );
	}

	/**
	 * Filtros para SIS_Catalogo::filtrar().
	 *
	 * @param array $p Periodo ya normalizado.
	 * @return array
	 */
	public static function filtros( array $p ) {
		if ( $p['anio'] && $p['mes'] ) {
			$ultimo = (int) gmdate( 't', gmmktime( 0, 0, 0, $p['mes'], 1, $p['anio'] ) );
			return array(
				'desde' => sprintf( '%04d-%02d-01', $p['anio'], $p['mes'] ),
				'hasta' => sprintf( '%04d-%02d-%02d', $p['anio'], $p['mes'], $ultimo ),
			);
		}
		if ( $p['anio'] ) {
			return array(
				'desde' => sprintf( '%04d-01-01', $p['anio'] ),
				'hasta' => sprintf( '%04d-12-31', $p['anio'] ),
			);
		}
		if ( $p['dias'] ) {
			return array( 'dias' => $p['dias'] );
		}
		if ( $p['anios'] ) {
			return array( 'dias' => (int) round( $p['anios'] * 365.25 ) );
		}
		return array();
	}

	/**
	 * ¿El periodo recorta algo, o es todo el registro?
	 *
	 * @param array $p Periodo normalizado.
	 * @return bool
	 */
	public static function acotado( array $p ) {
		return (bool) ( $p['dias'] || $p['anio'] || $p['anios'] );
	}

	/**
	 * Etiqueta en lenguaje corriente: «en los últimos 15 días», «en agosto de
	 * 2026», «en 2026», «en los últimos 8 años», «en todo el registro».
	 *
	 * @param array $p          Periodo normalizado.
	 * @param bool  $con_prefijo Si se antepone «en».
	 * @return string
	 */
	public static function etiqueta( array $p, $con_prefijo = true ) {
		$pref = $con_prefijo ? 'en ' : '';

		if ( $p['anio'] && $p['mes'] ) {
			return $pref . self::MESES[ $p['mes'] ] . ' de ' . $p['anio'];
		}
		if ( $p['anio'] ) {
			return $pref . $p['anio'];
		}
		if ( $p['dias'] ) {
			if ( 1 === $p['dias'] ) {
				return $pref . 'las últimas 24 horas';
			}
			if ( 7 === $p['dias'] ) {
				return $pref . 'la última semana';
			}
			if ( 30 === $p['dias'] || 31 === $p['dias'] ) {
				return $pref . 'el último mes';
			}
			if ( 365 === $p['dias'] || 366 === $p['dias'] ) {
				return $pref . 'el último año';
			}
			return $pref . 'los últimos ' . SIS_Texto::num( $p['dias'] ) . ' días';
		}
		if ( $p['anios'] ) {
			return 1 === $p['anios']
				? $pref . 'el último año'
				: $pref . 'los últimos ' . SIS_Texto::num( $p['anios'] ) . ' años';
		}
		return $pref . 'todo el registro disponible';
	}

	/**
	 * Fechas reales que abarca el periodo, para publicarlas junto al dato.
	 *
	 * @param array $p Periodo normalizado.
	 * @return array {desde, hasta} en AAAA-MM-DD, vacías si no aplica.
	 */
	public static function rango( array $p ) {
		$f = self::filtros( $p );
		if ( isset( $f['desde'], $f['hasta'] ) ) {
			return array( 'desde' => $f['desde'], 'hasta' => $f['hasta'] );
		}
		if ( isset( $f['dias'] ) ) {
			return array(
				'desde' => gmdate( 'Y-m-d', time() - ( (int) $f['dias'] * 86400 ) ),
				'hasta' => gmdate( 'Y-m-d' ),
			);
		}
		return array( 'desde' => '', 'hasta' => '' );
	}

	/**
	 * Hasta dónde debe llegar una serie temporal en este periodo.
	 *
	 * Las series se rellenan con ceros hasta el presente, porque un mes sin
	 * sismos es información. Pero si el periodo es una fecha de calendario
	 * cerrada —abril de 2016— rellenar hasta hoy añadiría diez años de ceros
	 * que nadie pidió: la serie tiene que terminar donde termina el filtro.
	 *
	 * @param array $p Periodo normalizado.
	 * @return array {mes: AAAA-MM, anio: int} topes de la serie.
	 */
	public static function topes( array $p ) {
		$r = self::rango( $p );

		// Con una fecha de calendario, la serie empieza y termina donde lo hace
		// el filtro: pedir 2026 y ver la serie arrancar en febrero —porque
		// enero no tuvo sismos— hace parecer que enero no existe. Enero en cero
		// es la respuesta correcta.
		if ( '' !== $r['hasta'] && $p['anio'] ) {
			return array(
				'mes'      => substr( $r['hasta'], 0, 7 ),
				'anio'     => (int) substr( $r['hasta'], 0, 4 ),
				'piso_mes' => substr( $r['desde'], 0, 7 ),
			);
		}
		return array( 'mes' => gmdate( 'Y-m' ), 'anio' => (int) gmdate( 'Y' ), 'piso_mes' => '' );
	}

	/**
	 * Clave estable del periodo, para memorias y cachés.
	 *
	 * @param array $p Periodo normalizado.
	 * @return string
	 */
	public static function clave( array $p ) {
		return $p['dias'] . '|' . $p['anio'] . '|' . $p['mes'] . '|' . $p['anios'];
	}

	/**
	 * Frase que sitúa al lector: qué territorio, qué periodo y cuántos sismos.
	 *
	 * Es la línea que encabeza descripciones y análisis. Sin ella, un mismo
	 * texto sirve para «Nariño en 15 días» y para «Colombia en 30 años», que es
	 * justo lo que no debe pasar cuando se informa a la ciudadanía.
	 *
	 * @param string $ambito Ámbito espacial.
	 * @param array  $p      Periodo normalizado.
	 * @param int    $n      Número de sismos que quedaron tras filtrar.
	 * @param float  $min_mag Magnitud mínima aplicada.
	 * @return string
	 */
	public static function encabezado( $ambito, array $p, $n, $min_mag = 0.0 ) {
		$region = SIS_Regiones::obtener( $ambito );
		// Los nombres de ámbito son nombres propios («Nariño y zona de
		// subducción vecina»): se publican tal cual, sin tocarles la mayúscula.
		$donde  = 'narino' === $ambito
			? 'dentro del departamento de Nariño'
			: 'en ' . $region['nombre'];

		$cuando = self::etiqueta( $p );
		$umbral = $min_mag > 0 ? ' de magnitud ' . SIS_Texto::num( $min_mag, 1 ) . ' o mayor' : '';

		if ( 0 === (int) $n ) {
			return 'No se registró ningún sismo' . $umbral . ' ' . $donde . ' ' . $cuando . '.';
		}
		if ( 1 === (int) $n ) {
			return 'Se registró 1 sismo' . $umbral . ' ' . $donde . ' ' . $cuando . '.';
		}
		return 'Se registraron ' . SIS_Texto::num( $n ) . ' sismos' . $umbral . ' ' . $donde . ' ' . $cuando . '.';
	}

	/**
	 * Nota que acompaña a un resultado vacío.
	 *
	 * Un cero no es un fallo: en el recuadro estricto del departamento el
	 * catálogo global registra unos pocos sismos al año, así que quince días
	 * sin actividad es lo esperable. Decirlo evita que se lea como «la página
	 * está rota» o, peor, como «aquí no tiembla».
	 *
	 * @param string $ambito Ámbito consultado.
	 * @return string
	 */
	public static function nota_vacia( $ambito ) {
		if ( 'narino' === $ambito ) {
			return 'Un periodo sin sismos es lo habitual dentro del recuadro del departamento: la red global registra allí unos pocos eventos al año. '
				. 'Que no aparezcan sismos no significa que no exista amenaza, sino que en esos días no hubo ninguno por encima del umbral de detección. '
				. 'Para ver la sismicidad que gobierna la amenaza del departamento —la zona de subducción frente al Pacífico— amplíe el ámbito a «regional».';
		}
		return 'Un periodo sin sismos registrados no significa ausencia de amenaza: significa que en esos días no ocurrió ninguno por encima del umbral de detección del catálogo. '
			. 'Pruebe con una ventana más amplia.';
	}
}
