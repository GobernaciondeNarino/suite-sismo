<?php
/**
 * API REST del plugin (namespace sismos/v1).
 *
 * Dos familias de rutas:
 *  · internas — alimentan los shortcodes del front (estado, eventos,
 *    estadística, amenaza y el /render del motor de gráficos);
 *  · abiertas — datos abiertos para ciudadanía, academia e investigación, en
 *    JSON o CSV, con la atribución al USGS incorporada en la respuesta.
 *
 * Todas son públicas y de solo lectura, con rate-limiting por IP. No se exige
 * nonce a propósito: un nonce caducado servido desde la caché de página
 * rompería la lectura a visitantes anónimos.
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Rest {

	const NS = 'sismos/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'registrar_rutas' ) );
	}

	/**
	 * Registra todas las rutas.
	 */
	public function registrar_rutas() {
		$publico = array( $this, 'permiso_publico' );

		register_rest_route( self::NS, '/estado', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'ruta_estado' ),
			'permission_callback' => $publico,
			'args'                => $this->args_comunes(),
		) );

		register_rest_route( self::NS, '/eventos', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'ruta_eventos' ),
			'permission_callback' => $publico,
			'args'                => $this->args_comunes(),
		) );

		register_rest_route( self::NS, '/estadistica', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'ruta_estadistica' ),
			'permission_callback' => $publico,
			'args'                => $this->args_comunes(),
		) );

		register_rest_route( self::NS, '/amenaza', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'ruta_amenaza' ),
			'permission_callback' => $publico,
		) );

		register_rest_route( self::NS, '/vistas', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'ruta_vistas' ),
			'permission_callback' => $publico,
		) );

		register_rest_route( self::NS, '/render', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'ruta_render' ),
			'permission_callback' => $publico,
			'args'                => array_merge(
				$this->args_comunes(),
				array(
					'view' => array( 'type' => 'string', 'required' => true ),
					'type' => array( 'type' => 'string' ),
				)
			),
		) );

		register_rest_route( self::NS, '/ambitos', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'ruta_ambitos' ),
			'permission_callback' => $publico,
		) );

		register_rest_route( self::NS, '/municipios', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'ruta_municipios' ),
			'permission_callback' => $publico,
		) );

		register_rest_route( self::NS, '/estado-apis', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'ruta_estado_apis' ),
			'permission_callback' => $publico,
		) );

		// --- Datos abiertos (JSON/CSV) ---
		foreach ( array( 'eventos', 'estadistica', 'recurrencia' ) as $recurso ) {
			register_rest_route( self::NS, '/abierto/' . $recurso, array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'ruta_abierta' ),
				'permission_callback' => $publico,
				'args'                => array_merge(
					$this->args_comunes(),
					array(
						'formato' => array( 'type' => 'string', 'default' => 'json' ),
						'recurso' => array( 'type' => 'string', 'default' => $recurso ),
					)
				),
			) );
		}
	}

	/**
	 * Argumentos comunes de consulta.
	 *
	 * @return array
	 */
	private function args_comunes() {
		return array(
			'ambito'  => array( 'type' => 'string' ),
			'dias'    => array( 'type' => 'integer' ),
			'anios'   => array( 'type' => 'integer' ),
			'min_mag' => array( 'type' => 'number' ),
			'limite'  => array( 'type' => 'integer' ),
		);
	}

	/**
	 * Permiso público con rate-limiting (protege al servidor, no al dato).
	 *
	 * @return bool|\WP_Error
	 */
	public function permiso_publico() {
		if ( ! SIS_Security::rate_limit( 'sis_rest', 120, 60 ) ) {
			return new \WP_Error( 'sis_rate_limit', 'Demasiadas peticiones. Intente de nuevo en un minuto.', array( 'status' => 429 ) );
		}
		return true;
	}

	/* ================================================================= */
	/* Utilidades de petición                                            */
	/* ================================================================= */

	/**
	 * Normaliza los parámetros de una petición.
	 *
	 * @param \WP_REST_Request $req Petición.
	 * @return array
	 */
	private function parametros( $req ) {
		$ambito = SIS_Security::sanitizar_ambito( $req->get_param( 'ambito' ) );

		$dias = $req->get_param( 'dias' );
		$dias = ( null === $dias || '' === $dias ) ? 0 : SIS_Security::sanitizar_dias( $dias, 0, 20000 );

		$anios = $req->get_param( 'anios' );
		$anios = ( null === $anios || '' === $anios ) ? 0 : max( 0, min( 60, (int) $anios ) );

		$limite = $req->get_param( 'limite' );
		$limite = ( null === $limite || '' === $limite ) ? 0 : max( 0, min( 5000, (int) $limite ) );

		return array(
			'ambito'  => $ambito,
			'dias'    => $dias,
			'anios'   => $anios,
			'min_mag' => SIS_Security::sanitizar_magnitud( $req->get_param( 'min_mag' ), 0.0 ),
			'limite'  => $limite,
		);
	}

	/**
	 * Catálogo filtrado según los parámetros de la petición.
	 *
	 * @param array $p Parámetros normalizados.
	 * @return array {eventos, catalogo}
	 */
	private function catalogo( $p ) {
		$catalogo = SIS_Catalogo::obtener( $p['ambito'] );
		$filtros  = array();

		if ( $p['dias'] > 0 ) {
			$filtros['dias'] = $p['dias'];
		} elseif ( $p['anios'] > 0 ) {
			$filtros['dias'] = (int) round( $p['anios'] * 365.25 );
		}
		if ( $p['min_mag'] > 0 ) {
			$filtros['min_mag'] = $p['min_mag'];
		}
		if ( $p['limite'] > 0 ) {
			$filtros['limite'] = $p['limite'];
		}

		$eventos = $filtros ? SIS_Catalogo::filtrar( $catalogo['eventos'], $filtros ) : $catalogo['eventos'];

		return array( 'eventos' => $eventos, 'catalogo' => $catalogo );
	}

	/**
	 * Bloque de metadatos y atribución que acompaña a toda respuesta.
	 *
	 * @param array $p   Parámetros.
	 * @param array $cat Catálogo.
	 * @return array
	 */
	private function meta( $p, $cat ) {
		$ambito = SIS_Regiones::obtener( $p['ambito'] );
		return array(
			'ambito'        => $p['ambito'],
			'ambito_nombre' => $ambito['nombre'],
			'consulta'      => $p,
			'actualizado'   => $cat['actualizado'],
			'origen'        => $cat['origen'],
			'fuente'        => 'U.S. Geological Survey — Earthquake Hazards Program (dominio público)',
			'licencia'      => 'Datos del USGS en dominio público. Elaboración: Gobernación de Nariño (CC BY 4.0).',
			'generado'      => gmdate( 'c' ),
		);
	}

	/* ================================================================= */
	/* Rutas internas                                                    */
	/* ================================================================= */

	/**
	 * Estado de la actividad reciente (semáforo y tarjetas).
	 *
	 * @param \WP_REST_Request $req Petición.
	 * @return \WP_REST_Response
	 */
	public function ruta_estado( $req ) {
		$p   = $this->parametros( $req );
		$c   = $this->catalogo( $p );
		$res = SIS_Catalogo::resumen( $c['eventos'], $p['ambito'] );

		$res['narrativa'] = SIS_Texto::actividad( $res );
		$res['meta']      = $this->meta( $p, $c['catalogo'] );

		return rest_ensure_response( $res );
	}

	/**
	 * Catálogo de eventos (los más recientes primero).
	 *
	 * @param \WP_REST_Request $req Petición.
	 * @return \WP_REST_Response
	 */
	public function ruta_eventos( $req ) {
		$p = $this->parametros( $req );
		if ( 0 === $p['limite'] ) {
			$p['limite'] = 500;
		}
		$c = $this->catalogo( $p );

		$eventos = array_reverse( $c['eventos'] );

		return rest_ensure_response( array(
			'total'   => count( $eventos ),
			'eventos' => $eventos,
			'meta'    => $this->meta( $p, $c['catalogo'] ),
		) );
	}

	/**
	 * Retrato estadístico del catálogo (Gutenberg-Richter, energía, umbrales).
	 *
	 * @param \WP_REST_Request $req Petición.
	 * @return \WP_REST_Response
	 */
	public function ruta_estadistica( $req ) {
		$p = $this->parametros( $req );
		$c = $this->catalogo( $p );

		$resumen                = SIS_Estadistica::resumen( $c['eventos'] );
		$resumen['narrativa']   = SIS_Texto::gutenberg( $resumen['gutenberg'] );
		$resumen['recurrencia'] = SIS_Texto::recurrencia( $resumen );
		$resumen['aviso']       = SIS_Texto::advertencia();
		$resumen['meta']        = $this->meta( $p, $c['catalogo'] );

		return rest_ensure_response( $resumen );
	}

	/**
	 * Marco de amenaza y comunicación del riesgo: glosario, fuentes oficiales,
	 * contexto geológico, normativa y guía post-sismo.
	 *
	 * Deliberadamente NO devuelve probabilidades de sismos futuros: la amenaza
	 * probabilística oficial se consulta en el Modelo Nacional de Amenaza
	 * Sísmica del SGC, al que esta respuesta enlaza.
	 *
	 * @return \WP_REST_Response
	 */
	public function ruta_amenaza() {
		return rest_ensure_response( SIS_Amenaza::ficha() );
	}

	/**
	 * Catálogo de vistas del motor de gráficos.
	 *
	 * @return \WP_REST_Response
	 */
	public function ruta_vistas() {
		return rest_ensure_response( array( 'vistas' => SIS_Views::lista() ) );
	}

	/**
	 * Ámbitos espaciales disponibles.
	 *
	 * @return \WP_REST_Response
	 */
	public function ruta_ambitos() {
		return rest_ensure_response( array(
			'ambitos'     => SIS_Regiones::lista(),
			'por_defecto' => SIS_Regiones::por_defecto(),
		) );
	}

	/**
	 * Municipios de Nariño (centroides) para mapas y selectores.
	 *
	 * @return \WP_REST_Response
	 */
	public function ruta_municipios() {
		return rest_ensure_response( array(
			'total'       => count( SIS_Municipios::todos() ),
			'municipios'  => SIS_Municipios::todos(),
			'subregiones' => SIS_Municipios::subregiones(),
		) );
	}

	/**
	 * Panel público de salud de las fuentes.
	 *
	 * @return \WP_REST_Response
	 */
	public function ruta_estado_apis() {
		return rest_ensure_response( array(
			'fuentes'  => SIS_Sync::estado(),
			'generado' => gmdate( 'c' ),
		) );
	}

	/**
	 * Payload del motor de gráficos: {chart, view, data, compatible}.
	 * Valida la vista contra la lista blanca y restringe el tipo a los
	 * compatibles con su categoría.
	 *
	 * @param \WP_REST_Request $req Petición.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function ruta_render( $req ) {
		$view_id = sanitize_key( (string) $req->get_param( 'view' ) );
		if ( '' === $view_id || ! SIS_Views::existe( $view_id ) ) {
			return new \WP_Error( 'sis_vista', 'Vista no encontrada.', array( 'status' => 404 ) );
		}

		$p    = $this->parametros( $req );
		$view = SIS_Views::obtener( $view_id, array(
			'ambito'  => $p['ambito'],
			'anios'   => $p['anios'],
			'min_mag' => $p['min_mag'],
		) );

		$compatibles = SIS_Views::compatibles( $view['category'] );
		$tipo        = sanitize_key( (string) $req->get_param( 'type' ) );
		if ( '' === $tipo || ! in_array( $tipo, $compatibles, true ) ) {
			$tipo = SIS_Views::default_tipo( $view_id );
		}

		$tipos        = SIS_Views::tipos();
		$chart        = isset( $tipos[ $tipo ] ) ? $tipos[ $tipo ] : $tipos['bar'];
		$chart['key'] = $tipo;

		return rest_ensure_response( array(
			'chart'      => $chart,
			'view'       => array(
				'id'                => $view['id'],
				'name'              => $view['name'],
				'description'       => $view['description'],
				'descripcion_larga' => $view['descripcion_larga'],
				'category'          => $view['category'],
				'dimensions'        => $view['dimensions'],
				'measures'          => $view['measures'],
				'analisis'          => $view['analisis'],
				'como_funciona'     => $view['como_funciona'],
				'aviso'             => $view['aviso'],
				'heatmap'           => $view['heatmap'],
				'contexto'          => $view['contexto'],
			),
			'data'       => $view['data'],
			'mapping'    => array( 'links' => array() ),
			'compatible' => $compatibles,
		) );
	}

	/* ================================================================= */
	/* Datos abiertos                                                    */
	/* ================================================================= */

	/**
	 * Recurso abierto en JSON o CSV.
	 *
	 * @param \WP_REST_Request $req Petición.
	 * @return \WP_REST_Response
	 */
	public function ruta_abierta( $req ) {
		$recurso = sanitize_key( (string) $req->get_param( 'recurso' ) );
		$formato = sanitize_key( (string) $req->get_param( 'formato' ) );
		$formato = in_array( $formato, array( 'json', 'csv' ), true ) ? $formato : 'json';

		$p = $this->parametros( $req );
		$c = $this->catalogo( $p );

		switch ( $recurso ) {
			case 'estadistica':
				$datos = SIS_Estadistica::resumen( $c['eventos'] );
				$filas = $this->filas_estadistica( $datos );
				break;

			case 'recurrencia':
				$resumen = SIS_Estadistica::resumen( $c['eventos'] );
				$datos   = array( 'umbrales' => $resumen['umbrales'], 'gutenberg' => $resumen['gutenberg'] );
				$filas   = $resumen['umbrales'];
				break;

			case 'eventos':
			default:
				$recurso = 'eventos';
				$datos   = array( 'eventos' => $c['eventos'] );
				$filas   = $c['eventos'];
				break;
		}

		if ( 'csv' === $formato ) {
			$this->servir_csv( $filas, 'sismos-narino-' . $recurso . '-' . $p['ambito'] );
		}

		return rest_ensure_response( array(
			'recurso' => $recurso,
			'datos'   => $datos,
			'meta'    => $this->meta( $p, $c['catalogo'] ),
		) );
	}

	/**
	 * Aplana el resumen estadístico a filas tabulares.
	 *
	 * @param array $r Resumen.
	 * @return array[]
	 */
	private function filas_estadistica( $r ) {
		$filas = array(
			array( 'indicador' => 'Sismos en la ventana', 'valor' => $r['n'], 'unidad' => 'sismos' ),
			array( 'indicador' => 'Años cubiertos', 'valor' => $r['anios'], 'unidad' => 'años' ),
			array( 'indicador' => 'Magnitud de completitud (Mc)', 'valor' => $r['gutenberg']['mc'], 'unidad' => 'magnitud' ),
			array( 'indicador' => 'Valor b', 'valor' => $r['gutenberg']['b'], 'unidad' => '' ),
			array( 'indicador' => 'Error del valor b', 'valor' => $r['gutenberg']['b_error'], 'unidad' => '' ),
			array( 'indicador' => 'Valor a (anual)', 'valor' => $r['gutenberg']['a'], 'unidad' => '' ),
			array( 'indicador' => 'Tasa anual sobre Mc', 'valor' => $r['gutenberg']['tasa_mc'], 'unidad' => 'sismos/año' ),
			array( 'indicador' => 'Magnitud máxima observada', 'valor' => $r['magnitud']['max'], 'unidad' => 'magnitud' ),
			array( 'indicador' => 'Profundidad mediana', 'valor' => $r['profundidad']['mediana'], 'unidad' => 'km' ),
			array( 'indicador' => 'Energía liberada', 'valor' => round( $r['energia_tnt'], 2 ), 'unidad' => 't TNT' ),
		);

		foreach ( $r['umbrales'] as $u ) {
			$filas[] = array(
				'indicador' => 'Sismos observados M ≥ ' . $u['magnitud'],
				'valor'     => $u['observados'],
				'unidad'    => 'sismos',
			);
			$filas[] = array(
				'indicador' => 'Intervalo medio observado M ≥ ' . $u['magnitud'],
				'valor'     => $u['intervalo_medio'],
				'unidad'    => 'años',
			);
		}

		return $filas;
	}

	/**
	 * Sirve un CSV descargable y termina la petición.
	 *
	 * @param array[] $filas  Filas.
	 * @param string  $nombre Nombre de archivo sin extensión.
	 */
	private function servir_csv( $filas, $nombre ) {
		$nombre = sanitize_file_name( $nombre );

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $nombre . '.csv"' );
			header( 'X-Content-Type-Options: nosniff' );
		}

		$salida = fopen( 'php://output', 'w' );

		// BOM UTF-8: Excel en Windows lo necesita para leer las tildes.
		echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput

		if ( ! empty( $filas ) ) {
			$columnas = array_keys( (array) reset( $filas ) );
			fputcsv( $salida, $columnas );
			foreach ( $filas as $f ) {
				$linea = array();
				foreach ( $columnas as $c ) {
					$v       = isset( $f[ $c ] ) ? $f[ $c ] : '';
					$linea[] = is_scalar( $v ) ? $v : wp_json_encode( $v );
				}
				fputcsv( $salida, $linea );
			}
		}

		fclose( $salida ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}
