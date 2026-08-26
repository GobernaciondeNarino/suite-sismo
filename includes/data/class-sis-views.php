<?php
/**
 * Registro de «vistas» para el motor de gráficos D3plus ([sismos_grafico]).
 *
 * Contrato vista→payload (motor de 3 capas): cada vista declara dimensiones
 * (campos categóricos o temporales), medidas (campos numéricos) y las filas de
 * datos, que se construyen sobre el catálogo sísmico normalizado. El renderer
 * del navegador es 100% genérico: no sabe nada de sismología, solo de
 * dimensiones y medidas.
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Views {

	/** @var array Memoria de catálogos ya resueltos en esta petición. */
	private static $memo = array();

	/**
	 * Catálogo de tipos de gráfico → clase D3plus + etiqueta.
	 * (D3plus v3 no tiene StackedBarChart: se usa BarChart + .stacked(true).)
	 *
	 * @return array<string,array{class:string,label:string}>
	 */
	public static function tipos() {
		return array(
			'bar'          => array( 'class' => 'BarChart', 'label' => 'Barras' ),
			'stacked_bar'  => array( 'class' => 'BarChart', 'label' => 'Barras apiladas' ),
			'line'         => array( 'class' => 'LinePlot', 'label' => 'Líneas' ),
			'area'         => array( 'class' => 'AreaPlot', 'label' => 'Área' ),
			'stacked_area' => array( 'class' => 'StackedArea', 'label' => 'Área apilada' ),
			'pie'          => array( 'class' => 'Pie', 'label' => 'Pastel' ),
			'donut'        => array( 'class' => 'Donut', 'label' => 'Dona' ),
			'treemap'      => array( 'class' => 'Treemap', 'label' => 'Treemap' ),
			'box_whisker'  => array( 'class' => 'BoxWhisker', 'label' => 'Caja y bigotes' ),
		);
	}

	/**
	 * Tipos compatibles según la categoría de la vista.
	 *
	 * @param string $category Categoría de la vista.
	 * @return string[]
	 */
	public static function compatibles( $category ) {
		switch ( $category ) {
			case 'temporal':
				return array( 'line', 'area', 'bar', 'stacked_area' );
			case 'statistical':
				return array( 'bar', 'line', 'area', 'box_whisker' );
			case 'distribucion':
				return array( 'box_whisker', 'bar' );
			case 'categorical':
			default:
				return array( 'bar', 'pie', 'donut', 'treemap', 'stacked_bar' );
		}
	}

	/* ================================================================= */
	/* Registro de vistas                                                */
	/* ================================================================= */

	/**
	 * Metadatos de las vistas disponibles.
	 *
	 * @return array<string,array>
	 */
	private static function registro() {
		return array(
			/* --- Actividad en el tiempo --- */
			'sismos_mensuales'      => array(
				'name'        => 'Sismos por mes',
				'description' => 'Número de sismos registrados cada mes en el ámbito seleccionado.',
				'category'    => 'temporal',
				'dimensions'  => array( 'mes' ),
				'measures'    => array( 'sismos' ),
				'default'     => 'bar',
				'heatmap'     => true,
			),
			'sismos_anuales'        => array(
				'name'        => 'Sismos por año',
				'description' => 'Conteo anual de sismos: muestra si la actividad de un año se sale de lo habitual.',
				'category'    => 'statistical',
				'dimensions'  => array( 'anio' ),
				'measures'    => array( 'sismos' ),
				'default'     => 'bar',
				'heatmap'     => true,
			),
			'magnitud_mensual'      => array(
				'name'        => 'Magnitud media y máxima por mes',
				'description' => 'Promedio y techo de magnitud mes a mes; separa los meses de muchos sismos pequeños de los de pocos pero grandes.',
				'category'    => 'temporal',
				'dimensions'  => array( 'mes' ),
				'measures'    => array( 'magnitud_media', 'magnitud_maxima' ),
				'default'     => 'line',
			),
			'energia_mensual'       => array(
				'name'        => 'Energía liberada por mes',
				'description' => 'Energía sísmica irradiada cada mes, en toneladas equivalentes de TNT (relación de Hanks & Kanamori).',
				'category'    => 'temporal',
				'dimensions'  => array( 'mes' ),
				'measures'    => array( 'energia_tnt' ),
				'default'     => 'area',
				'heatmap'     => true,
			),
			'historico_mensual'     => array(
				'name'        => 'Histórico mensual con tendencia',
				'description' => 'Serie mensual completa del catálogo con su media móvil de doce meses, que separa la tendencia de fondo del ruido de las secuencias de réplicas.',
				'category'    => 'temporal',
				'dimensions'  => array( 'mes' ),
				'measures'    => array( 'sismos', 'media_movil_12m' ),
				'default'     => 'line',
			),
			'acumulado'             => array(
				'name'        => 'Sismos acumulados en el tiempo',
				'description' => 'Curva acumulada de sismos: los tramos más empinados señalan periodos de actividad más intensa.',
				'category'    => 'temporal',
				'dimensions'  => array( 'mes' ),
				'measures'    => array( 'acumulado' ),
				'default'     => 'line',
			),

			/* --- Distribuciones estadísticas --- */
			'frecuencia_magnitud'   => array(
				'name'        => 'Ley de Gutenberg-Richter (frecuencia-magnitud)',
				'description' => 'Número acumulado de sismos por encima de cada magnitud, junto al ajuste teórico de Gutenberg-Richter.',
				'category'    => 'statistical',
				'dimensions'  => array( 'magnitud' ),
				'measures'    => array( 'observados', 'ajuste' ),
				'default'     => 'line',
			),
			'distribucion_magnitud' => array(
				'name'        => 'Distribución de magnitudes',
				'description' => 'Histograma de magnitudes: cuántos sismos hay de cada tamaño en el catálogo.',
				'category'    => 'statistical',
				'dimensions'  => array( 'magnitud' ),
				'measures'    => array( 'sismos' ),
				'default'     => 'bar',
				'heatmap'     => true,
			),
			'clases_magnitud'       => array(
				'name'        => 'Sismos por clase de magnitud',
				'description' => 'Reparto del catálogo entre micro, menores, ligeros, moderados, fuertes y mayores.',
				'category'    => 'categorical',
				'dimensions'  => array( 'clase' ),
				'measures'    => array( 'sismos' ),
				'default'     => 'donut',
			),
			'profundidad'           => array(
				'name'        => 'Sismos por rango de profundidad',
				'description' => 'Superficiales, intermedios y profundos: la profundidad decide cuánto se siente un sismo en superficie.',
				'category'    => 'categorical',
				'dimensions'  => array( 'rango_profundidad' ),
				'measures'    => array( 'sismos' ),
				'default'     => 'donut',
			),
			'magnitud_profundidad'  => array(
				'name'        => 'Magnitud según la profundidad',
				'description' => 'Distribución de magnitudes dentro de cada rango de profundidad (caja y bigotes).',
				'category'    => 'distribucion',
				'dimensions'  => array( 'rango_profundidad' ),
				'measures'    => array( 'magnitud' ),
				'default'     => 'box_whisker',
			),

			/* --- Lectura territorial --- */
			'municipios_cercanos'   => array(
				'name'        => 'Municipios de Nariño más próximos a los epicentros',
				'description' => 'Los 15 municipios que con más frecuencia resultan ser el más cercano al epicentro.',
				'category'    => 'categorical',
				'dimensions'  => array( 'municipio' ),
				'measures'    => array( 'sismos' ),
				'default'     => 'bar',
				'heatmap'     => true,
			),
			'subregiones'           => array(
				'name'        => 'Sismicidad por subregión de Nariño',
				'description' => 'Sismos asignados a cada subregión del departamento por cercanía al epicentro.',
				'category'    => 'categorical',
				'dimensions'  => array( 'subregion' ),
				'measures'    => array( 'sismos' ),
				'default'     => 'treemap',
				'heatmap'     => true,
			),
			'mayores_sismos'        => array(
				'name'        => 'Sismos de mayor magnitud del catálogo',
				'description' => 'Los 12 sismos más grandes registrados en la ventana consultada.',
				'category'    => 'categorical',
				'dimensions'  => array( 'evento' ),
				'measures'    => array( 'magnitud' ),
				'default'     => 'bar',
				'heatmap'     => true,
			),

			/* --- Recurrencia observada (estadística retrospectiva) --- */
			'recurrencia_historica' => array(
				'name'        => 'Recurrencia observada por magnitud',
				'description' => 'Cuántos sismos de cada magnitud o mayor se registraron y cada cuántos años ocurrió uno en promedio, dentro de la ventana consultada.',
				'category'    => 'categorical',
				'dimensions'  => array( 'umbral' ),
				'measures'    => array( 'intervalo_medio' ),
				'default'     => 'bar',
			),
		);
	}

	/* ================================================================= */
	/* API pública del registro                                          */
	/* ================================================================= */

	/**
	 * Lista compacta de vistas (para el panel y la REST).
	 *
	 * @return array[]
	 */
	public static function lista() {
		$out = array();
		foreach ( self::registro() as $id => $m ) {
			$out[] = array(
				'id'          => $id,
				'name'        => $m['name'],
				'description' => $m['description'],
				'category'    => $m['category'],
				'default'     => $m['default'],
				'compatibles' => self::compatibles( $m['category'] ),
			);
		}
		return $out;
	}

	/**
	 * ¿Existe la vista?
	 *
	 * @param string $id Id de vista.
	 * @return bool
	 */
	public static function existe( $id ) {
		$r = self::registro();
		return isset( $r[ $id ] );
	}

	/**
	 * Tipo de gráfico por defecto de una vista.
	 *
	 * @param string $id Id de vista.
	 * @return string
	 */
	public static function default_tipo( $id ) {
		$r = self::registro();
		return isset( $r[ $id ] ) ? $r[ $id ]['default'] : 'bar';
	}

	/**
	 * Devuelve la vista completa: metadatos + filas + textos calculados.
	 *
	 * @param string $id   Id de vista.
	 * @param array  $args {ambito, anios, min_mag}.
	 * @return array|null
	 */
	public static function obtener( $id, $args = array() ) {
		$r = self::registro();
		if ( ! isset( $r[ $id ] ) ) {
			return null;
		}

		$m     = $r[ $id ];
		$args  = self::normalizar_args( $args );
		$datos = self::datos( $id, $args );

		return array(
			'id'                => $id,
			'name'              => $m['name'],
			'description'       => $m['description'],
			'descripcion_larga' => self::descripcion_larga( $id ),
			'category'          => $m['category'],
			'dimensions'        => $m['dimensions'],
			'measures'          => $m['measures'],
			'data'              => $datos,
			'analisis'          => self::analisis( $id, $datos, $args ),
			'como_funciona'     => self::como_funciona( $id ),
			'aviso'             => SIS_Texto::advertencia(),
			'heatmap'           => ! empty( $m['heatmap'] ),
			'contexto'          => array(
				'ambito'        => $args['ambito'],
				'ambito_nombre' => SIS_Regiones::obtener( $args['ambito'] )['nombre'],
				'anios'         => $args['anios'],
				'min_mag'       => $args['min_mag'],
			),
		);
	}

	/**
	 * Normaliza y acota los argumentos de consulta de una vista.
	 *
	 * @param array $args Argumentos crudos.
	 * @return array
	 */
	public static function normalizar_args( $args ) {
		$a = array_merge(
			array(
				'ambito'  => SIS_Regiones::por_defecto(),
				'anios'   => 0,     // 0 = toda la ventana disponible.
				'min_mag' => 0.0,
			),
			is_array( $args ) ? $args : array()
		);

		return array(
			'ambito'  => SIS_Security::sanitizar_ambito( $a['ambito'] ),
			'anios'   => max( 0, min( 60, (int) $a['anios'] ) ),
			'min_mag' => SIS_Security::sanitizar_magnitud( $a['min_mag'], 0.0 ),
		);
	}

	/* ================================================================= */
	/* Acceso al catálogo con memoria por petición                       */
	/* ================================================================= */

	/**
	 * Eventos del catálogo ya filtrados según los argumentos de la vista.
	 *
	 * @param array $args Argumentos normalizados.
	 * @return array[]
	 */
	public static function eventos( $args ) {
		$clave = $args['ambito'] . '|' . $args['anios'] . '|' . $args['min_mag'];
		if ( isset( self::$memo[ $clave ] ) ) {
			return self::$memo[ $clave ];
		}

		$catalogo = SIS_Catalogo::obtener( $args['ambito'] );
		$filtros  = array();

		if ( $args['anios'] > 0 ) {
			$filtros['dias'] = (int) round( $args['anios'] * 365.25 );
		}
		if ( $args['min_mag'] > 0 ) {
			$filtros['min_mag'] = $args['min_mag'];
		}

		$ev = $filtros ? SIS_Catalogo::filtrar( $catalogo['eventos'], $filtros ) : $catalogo['eventos'];

		self::$memo[ $clave ] = $ev;
		return $ev;
	}

	/* ================================================================= */
	/* Constructores de datos por vista                                  */
	/* ================================================================= */

	/**
	 * Filas de datos de una vista.
	 *
	 * @param string $id   Id de vista.
	 * @param array  $args Argumentos normalizados.
	 * @return array[]
	 */
	public static function datos( $id, $args ) {
		$eventos = self::eventos( $args );

		switch ( $id ) {
			case 'sismos_mensuales':
				return self::filas_mensuales( $eventos );

			case 'sismos_anuales':
				$out = array();
				foreach ( SIS_Catalogo::conteo_anual( $eventos ) as $anio => $n ) {
					$out[] = array( 'anio' => (string) $anio, 'sismos' => (int) $n );
				}
				return $out;

			case 'magnitud_mensual':
				return self::filas_magnitud_mensual( $eventos );

			case 'energia_mensual':
				$out = array();
				foreach ( SIS_Catalogo::energia_mensual( $eventos ) as $mes => $v ) {
					$out[] = array(
						'mes'         => $mes,
						'energia_tnt' => round( $v['tnt'], 2 ),
						'sismos'      => (int) $v['n'],
					);
				}
				return $out;

			case 'historico_mensual':
				return self::filas_historico_mensual( $eventos );

			case 'acumulado':
				$out  = array();
				$acum = 0;
				foreach ( SIS_Catalogo::conteo_mensual( $eventos ) as $mes => $n ) {
					$acum += (int) $n;
					$out[] = array( 'mes' => $mes, 'acumulado' => $acum, 'sismos' => (int) $n );
				}
				return $out;

			case 'frecuencia_magnitud':
				$gr  = SIS_Estadistica::gutenberg_richter( $eventos );
				$out = array();
				foreach ( $gr['curva'] as $p ) {
					$out[] = array(
						'magnitud'   => number_format( $p['magnitud'], 1, '.', '' ),
						'observados' => (int) $p['observados'],
						'ajuste'     => (float) $p['ajuste'],
					);
				}
				return $out;

			case 'distribucion_magnitud':
				$out = array();
				foreach ( SIS_Catalogo::histograma_magnitud( $eventos ) as $mag => $n ) {
					$out[] = array( 'magnitud' => (string) $mag, 'sismos' => (int) $n );
				}
				return $out;

			case 'clases_magnitud':
				return self::filas_agrupadas( $eventos, 'clase', 'clase' );

			case 'profundidad':
				return self::filas_agrupadas( $eventos, 'rango_profundidad', 'rango_profundidad' );

			case 'magnitud_profundidad':
				$out = array();
				foreach ( $eventos as $e ) {
					$out[] = array(
						'rango_profundidad' => $e['rango_profundidad'],
						'magnitud'          => (float) $e['mag'],
					);
				}
				return $out;

			case 'municipios_cercanos':
				$filas = self::filas_agrupadas( $eventos, 'municipio', 'municipio' );
				return array_slice( $filas, 0, 15 );

			case 'subregiones':
				return self::filas_agrupadas( $eventos, 'subregion', 'subregion' );

			case 'mayores_sismos':
				return self::filas_mayores( $eventos );

			case 'recurrencia_historica':
				return self::filas_recurrencia( $args );
		}

		return array();
	}

	/**
	 * Conteo mensual como filas del motor de gráficos.
	 *
	 * @param array[] $eventos Eventos.
	 * @return array[]
	 */
	private static function filas_mensuales( array $eventos ) {
		$out = array();
		foreach ( SIS_Catalogo::conteo_mensual( $eventos ) as $mes => $n ) {
			$out[] = array( 'mes' => $mes, 'sismos' => (int) $n );
		}
		return $out;
	}

	/**
	 * Serie mensual con media móvil centrada de doce meses.
	 *
	 * La serie cruda de un catálogo sísmico es muy ruidosa: un sismo principal
	 * arrastra decenas de réplicas y dispara un mes entero. La media móvil de
	 * doce meses promedia ese ruido y deja ver el nivel de fondo, que es lo
	 * que de verdad cambia despacio.
	 *
	 * @param array[] $eventos Eventos.
	 * @return array[]
	 */
	private static function filas_historico_mensual( array $eventos ) {
		$serie = SIS_Catalogo::conteo_mensual( $eventos );
		$meses = array_keys( $serie );
		$vals  = array_values( $serie );
		$n     = count( $vals );

		$out = array();
		for ( $i = 0; $i < $n; $i++ ) {
			// Ventana centrada; en los extremos se promedia lo que hay, sin
			// inventar meses fuera del catálogo.
			$desde = max( 0, $i - 6 );
			$hasta = min( $n - 1, $i + 5 );
			$suma  = 0;
			for ( $j = $desde; $j <= $hasta; $j++ ) {
				$suma += $vals[ $j ];
			}
			$out[] = array(
				'mes'             => $meses[ $i ],
				'sismos'          => (int) $vals[ $i ],
				'media_movil_12m' => round( $suma / ( $hasta - $desde + 1 ), 2 ),
			);
		}
		return $out;
	}

	/**
	 * Magnitud media y máxima por mes.
	 *
	 * @param array[] $eventos Eventos.
	 * @return array[]
	 */
	private static function filas_magnitud_mensual( array $eventos ) {
		$acum = array();
		foreach ( $eventos as $e ) {
			$m = $e['mes'];
			if ( ! isset( $acum[ $m ] ) ) {
				$acum[ $m ] = array();
			}
			$acum[ $m ][] = (float) $e['mag'];
		}
		ksort( $acum );

		$out = array();
		foreach ( $acum as $mes => $mags ) {
			$out[] = array(
				'mes'              => $mes,
				'magnitud_media'   => round( SIS_Estadistica::media( $mags ), 2 ),
				'magnitud_maxima'  => round( max( $mags ), 1 ),
				'sismos'           => count( $mags ),
			);
		}
		return $out;
	}

	/**
	 * Agrupación simple por un campo del evento.
	 *
	 * @param array[] $eventos Eventos.
	 * @param string  $campo   Campo a agrupar.
	 * @param string  $salida  Nombre de la dimensión de salida.
	 * @return array[]
	 */
	private static function filas_agrupadas( array $eventos, $campo, $salida ) {
		$out = array();
		foreach ( SIS_Catalogo::agrupar( $eventos, $campo ) as $k => $n ) {
			$etiqueta = ( 'municipio' === $campo && '' !== $k && 'Sin dato' !== $k )
				? self::titulo( $k )
				: $k;
			$out[] = array( $salida => $etiqueta, 'sismos' => (int) $n );
		}
		return $out;
	}

	/**
	 * Los sismos de mayor magnitud del catálogo.
	 *
	 * @param array[] $eventos Eventos.
	 * @param int     $limite  Cuántos.
	 * @return array[]
	 */
	private static function filas_mayores( array $eventos, $limite = 12 ) {
		$copia = $eventos;
		usort(
			$copia,
			static function ( $a, $b ) {
				if ( $a['mag'] === $b['mag'] ) {
					return 0;
				}
				return ( $a['mag'] > $b['mag'] ) ? -1 : 1;
			}
		);

		$out = array();
		foreach ( array_slice( $copia, 0, $limite ) as $e ) {
			$out[] = array(
				'evento'      => substr( $e['fecha'], 0, 10 ) . ' · ' . self::corto( $e['lugar'] ),
				'magnitud'    => (float) $e['mag'],
				'profundidad' => (float) $e['profundidad'],
			);
		}
		return $out;
	}

	/**
	 * Recurrencia observada por umbral de magnitud.
	 *
	 * Cada fila responde a «cuántos hubo» y «cada cuánto, en promedio», sobre
	 * la ventana consultada. Es estadística del pasado, no una proyección.
	 *
	 * @param array $args Argumentos normalizados.
	 * @return array[]
	 */
	private static function filas_recurrencia( $args ) {
		$resumen = SIS_Estadistica::resumen( self::eventos( $args ) );
		if ( empty( $resumen['umbrales'] ) ) {
			return array();
		}

		$out = array();
		foreach ( $resumen['umbrales'] as $u ) {
			if ( null === $u['intervalo_medio'] ) {
				continue;
			}
			$out[] = array(
				'umbral'          => 'M ≥ ' . number_format( (float) $u['magnitud'], 1, ',', '.' ),
				'intervalo_medio' => (float) $u['intervalo_medio'],
				'observados'      => (int) $u['observados'],
				'tasa_anual_obs'  => (float) $u['tasa_anual_obs'],
			);
		}
		return $out;
	}

	/* ================================================================= */
	/* Textos por vista                                                  */
	/* ================================================================= */

	/**
	 * Textos largos (descripción y análisis) cargados de textos-graficos.php.
	 *
	 * @return array<string,array>
	 */
	private static function textos_largos() {
		static $t = null;
		if ( null === $t ) {
			$ruta = SIS_DIR . 'includes/data/textos-graficos.php';
			$t    = is_readable( $ruta ) ? include $ruta : array();
			if ( ! is_array( $t ) ) {
				$t = array();
			}
		}
		return $t;
	}

	/**
	 * Descripción larga de una vista.
	 *
	 * @param string $id Id de vista.
	 * @return string
	 */
	public static function descripcion_larga( $id ) {
		$t = self::textos_largos();
		return isset( $t[ $id ]['descripcion'] ) ? $t[ $id ]['descripcion'] : '';
	}

	/**
	 * Explicación «¿Cómo funciona?» de una vista.
	 *
	 * @param string $id Id de vista.
	 * @return string
	 */
	public static function como_funciona( $id ) {
		$t = self::textos_largos();
		return isset( $t[ $id ]['como_funciona'] ) ? $t[ $id ]['como_funciona'] : '';
	}

	/**
	 * Análisis {descriptivo, cuantitativo} calculado sobre los datos reales.
	 *
	 * @param string  $id    Id de vista.
	 * @param array[] $datos Filas.
	 * @param array   $args  Argumentos.
	 * @return array
	 */
	public static function analisis( $id, $datos, $args ) {
		$t           = self::textos_largos();
		$descriptivo = isset( $t[ $id ]['analisis'] ) ? $t[ $id ]['analisis'] : '';

		return array(
			'descriptivo'  => $descriptivo,
			'cuantitativo' => self::cuantitativo( $id, $datos, $args ),
		);
	}

	/**
	 * Párrafo cuantitativo específico de cada vista.
	 *
	 * @param string  $id    Id de vista.
	 * @param array[] $datos Filas.
	 * @param array   $args  Argumentos.
	 * @return string
	 */
	private static function cuantitativo( $id, $datos, $args ) {
		if ( empty( $datos ) ) {
			return 'Todavía no hay datos para esta vista en el ámbito y la ventana seleccionados.';
		}

		switch ( $id ) {
			case 'frecuencia_magnitud':
				$gr = SIS_Estadistica::gutenberg_richter( self::eventos( $args ) );
				return SIS_Texto::gutenberg( $gr );

			case 'recurrencia_historica':
				return SIS_Texto::recurrencia( SIS_Estadistica::resumen( self::eventos( $args ) ) );

			case 'magnitud_profundidad':
				return self::cuantitativo_profundidad( $datos );

			case 'energia_mensual':
				return SIS_Texto::cuantitativo( $datos, 'mes', 'energia_tnt', array( 'unidad' => 't de TNT', 'decimales' => 0, 'etiqueta_dim' => 'mes' ) );

			case 'magnitud_mensual':
				return SIS_Texto::cuantitativo( $datos, 'mes', 'magnitud_maxima', array( 'decimales' => 1, 'etiqueta_dim' => 'mes' ) );

			case 'acumulado':
				$ultimo = end( $datos );
				$meses  = count( $datos );
				return sprintf(
					'La curva acumula %s sismos en %s meses, un promedio de %s por mes. Los tramos con mayor pendiente corresponden a los periodos de actividad más intensa.',
					SIS_Texto::num( $ultimo['acumulado'] ),
					SIS_Texto::num( $meses ),
					SIS_Texto::num( $meses > 0 ? $ultimo['acumulado'] / $meses : 0, 2 )
				);

			case 'mayores_sismos':
				return SIS_Texto::cuantitativo( $datos, 'evento', 'magnitud', array( 'decimales' => 1, 'etiqueta_dim' => 'sismo' ) );

			case 'municipios_cercanos':
			case 'subregiones':
			case 'clases_magnitud':
			case 'profundidad':
				$dim = array_keys( $datos[0] );
				return SIS_Texto::cuantitativo( $datos, $dim[0], 'sismos', array( 'unidad' => 'sismos', 'etiqueta_dim' => 'categoría' ) );

			case 'distribucion_magnitud':
				return SIS_Texto::cuantitativo( $datos, 'magnitud', 'sismos', array( 'unidad' => 'sismos', 'etiqueta_dim' => 'magnitud' ) );

			case 'sismos_anuales':
				return SIS_Texto::cuantitativo( $datos, 'anio', 'sismos', array( 'unidad' => 'sismos', 'etiqueta_dim' => 'año' ) );

			case 'sismos_mensuales':
			case 'historico_mensual':
			default:
				return SIS_Texto::cuantitativo( $datos, 'mes', 'sismos', array( 'unidad' => 'sismos', 'etiqueta_dim' => 'mes' ) );
		}
	}

	/**
	 * Cuantitativo de la vista de caja y bigotes (magnitud por profundidad).
	 *
	 * @param array[] $datos Filas evento a evento.
	 * @return string
	 */
	private static function cuantitativo_profundidad( array $datos ) {
		$grupos = array();
		foreach ( $datos as $f ) {
			$g = $f['rango_profundidad'];
			if ( ! isset( $grupos[ $g ] ) ) {
				$grupos[ $g ] = array();
			}
			$grupos[ $g ][] = (float) $f['magnitud'];
		}

		$frases = array();
		foreach ( $grupos as $g => $mags ) {
			$frases[] = sprintf(
				'%s: %s sismos, mediana %s y máximo %s',
				$g,
				SIS_Texto::num( count( $mags ) ),
				SIS_Texto::num( SIS_Estadistica::percentil( $mags, 50 ), 1 ),
				SIS_Texto::num( max( $mags ), 1 )
			);
		}

		return 'Por rango de profundidad — ' . SIS_Texto::lista( $frases ) . '. La profundidad importa tanto como la magnitud: un sismo superficial se percibe mucho más que uno profundo del mismo tamaño.';
	}

	/* ================================================================= */
	/* Utilidades                                                        */
	/* ================================================================= */

	/**
	 * Convierte un nombre en mayúsculas a formato título.
	 *
	 * @param string $s Texto.
	 * @return string
	 */
	private static function titulo( $s ) {
		$s = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $s, 'UTF-8' ) : strtolower( (string) $s );
		return function_exists( 'mb_convert_case' ) ? mb_convert_case( $s, MB_CASE_TITLE, 'UTF-8' ) : ucwords( $s );
	}

	/**
	 * Recorta un texto largo de lugar para que quepa como etiqueta.
	 *
	 * @param string $s   Texto.
	 * @param int    $max Longitud máxima.
	 * @return string
	 */
	private static function corto( $s, $max = 38 ) {
		$s = (string) $s;
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $s, 'UTF-8' ) > $max ) {
			return mb_substr( $s, 0, $max - 1, 'UTF-8' ) . '…';
		}
		return $s;
	}
}
