<?php
/**
 * Módulo de Apariencia (configurable).
 *
 * Convierte la opción `sis_estilo` en variables CSS `--sis-*` aplicadas al
 * contenedor `.sis` que envuelve cada shortcode. Por defecto todo es
 * minimalista: fondo transparente, sin bordes ni sombras, heredando tipografía
 * y color de la página anfitriona. Solo los colores semánticos del dominio
 * (nivel de magnitud, escala de profundidad) quedan fuera de configuración,
 * porque codifican información y no estilo.
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Estilos {

	const HANDLE = 'sis-estilos';

	public function __construct() {
		// Prioridad 5: registra el estilo antes de que los shortcodes lo encolen (10).
		add_action( 'wp_enqueue_scripts', array( $this, 'registrar' ), 5 );
	}

	/**
	 * Registra la hoja base e inyecta las variables de apariencia.
	 */
	public function registrar() {
		wp_register_style( self::HANDLE, SIS_URL . 'assets/css/estilos.css', array(), SIS_VERSION );
		wp_add_inline_style( self::HANDLE, self::css_global() );
	}

	/**
	 * Configuración de apariencia fusionada con los valores por defecto.
	 *
	 * @return array
	 */
	public static function estilo() {
		$def = SIS_Activator::estilo_por_defecto();
		$cfg = get_option( 'sis_estilo', array() );
		return wp_parse_args( is_array( $cfg ) ? $cfg : array(), $def );
	}

	/**
	 * Bloque CSS con las variables globales bajo `.sis`.
	 *
	 * @return string
	 */
	public static function css_global() {
		$e = self::estilo();

		$borde = ( 'none' === $e['borde'] || '0' === (string) $e['borde'] )
			? 'none'
			: self::sanitizar_css( $e['borde'] ) . ' solid ' . self::sanitizar_css( $e['borde_color'] );

		$vars = array(
			'--sis-fondo'          => self::sanitizar_css( $e['fondo'] ),
			'--sis-texto'          => self::sanitizar_css( $e['texto'] ),
			'--sis-tipografia'     => self::sanitizar_css( $e['tipografia'] ),
			'--sis-acento'         => self::sanitizar_css( $e['acento'] ),
			'--sis-acento-2'       => self::sanitizar_css( $e['acento_2'] ),
			'--sis-acento-tecnico' => self::sanitizar_css( $e['acento_tecnico'] ),
			'--sis-mute'           => self::sanitizar_css( $e['mute'] ),
			'--sis-borde-color'    => self::sanitizar_css( $e['borde_color'] ),
			'--sis-borde'          => $borde,
			'--sis-borde-radio'    => self::sanitizar_css( $e['borde_radio'] ),
			'--sis-sombra'         => self::sanitizar_css( $e['sombra'] ),
			'--sis-ancho-max'      => self::sanitizar_css( $e['ancho_max'] ),
			'--sis-espaciado'      => self::sanitizar_css( $e['espaciado'] ),
		);

		$cuerpo = '';
		foreach ( $vars as $k => $v ) {
			$cuerpo .= $k . ':' . $v . ';';
		}
		return '.sis{' . $cuerpo . '}';
	}

	/**
	 * Estilo en línea de overrides por atributo de shortcode.
	 *
	 * @param array $atts Atributos (fondo, acento, borde, sombra, ancho…).
	 * @return string CSS listo para un atributo style.
	 */
	public static function estilo_inline( $atts ) {
		$map = array(
			'fondo'     => '--sis-fondo',
			'acento'    => '--sis-acento',
			'acento2'   => '--sis-acento-2',
			'tecnico'   => '--sis-acento-tecnico',
			'texto'     => '--sis-texto',
			'sombra'    => '--sis-sombra',
			'ancho'     => '--sis-ancho-max',
			'espaciado' => '--sis-espaciado',
			'radio'     => '--sis-borde-radio',
		);

		$out = '';
		foreach ( $map as $att => $var ) {
			if ( isset( $atts[ $att ] ) && '' !== $atts[ $att ] ) {
				$out .= $var . ':' . self::sanitizar_css( $atts[ $att ] ) . ';';
			}
		}

		if ( isset( $atts['borde'] ) && '' !== $atts['borde'] ) {
			$b    = self::sanitizar_css( $atts['borde'] );
			$out .= '--sis-borde:' . ( ( 'none' === $b || '0' === $b ) ? 'none' : $b . ' solid var(--sis-borde-color,#e5e7eb)' ) . ';';
		}

		return $out;
	}

	/**
	 * Sanea un valor para inserción segura en CSS (anti-inyección).
	 *
	 * Conserva funciones legítimas (rgba(), calc(), var()) y neutraliza las
	 * peligrosas (url(), expression(), image-set(), -moz-binding) además de los
	 * caracteres que permitirían salir del valor o inyectar reglas. La salida
	 * vuelve a escaparse con esc_attr() al imprimirse.
	 *
	 * @param string $v Valor crudo.
	 * @return string
	 */
	public static function sanitizar_css( $v ) {
		$v = (string) $v;
		$v = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $v );
		$v = str_replace( array( ';', '{', '}', '<', '>', '\\', '"', "'", '@', '`' ), '', $v );
		$v = str_replace( array( '/*', '*/' ), '', $v );
		$v = preg_replace( '/(?:url|expression|image-set|-moz-binding)\s*\(/i', '', $v );
		$v = trim( $v );
		return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, 200 ) : substr( $v, 0, 200 );
	}
}
