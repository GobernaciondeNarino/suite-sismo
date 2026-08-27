<?php
/**
 * Marco de amenaza sísmica y comunicación responsable del riesgo.
 *
 * Este plugin NO pronostica sismos. La predicción determinística —decir cuándo,
 * dónde y con qué magnitud ocurrirá un sismo— no es posible con la ciencia
 * disponible, y así lo afirman el USGS y el Servicio Geológico Colombiano
 * (SGC). Lo que aquí se publica es CONOCIMIENTO DEL RIESGO, el primero de los
 * tres procesos de la Ley 1523 de 2012: amenaza, estadística histórica,
 * contexto geológico y preparación ciudadana, siempre citando y enlazando a la
 * autoridad técnica.
 *
 * Esta clase reúne los enlaces oficiales, el contexto geológico del
 * departamento y el glosario que separa cuatro conceptos que el público suele
 * confundir: alerta temprana, pronóstico, probabilidad de largo plazo y
 * predicción.
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Amenaza {

	/**
	 * Boletín de sismos recientes de la Red Sismológica Nacional.
	 *
	 * Es la fuente que sí publica los sismos de magnitud 2 y 3 que el catálogo
	 * mundial del USGS no alcanza a registrar en Colombia, así que varios
	 * componentes remiten a ella. Vive aquí, y no repetida en cada uno, para
	 * que el día que el SGC cambie la dirección solo haya un sitio que tocar.
	 */
	const URL_SISMOS_RECIENTES = 'https://sismosgr.sgc.gov.co/sismosrecientes/';

	/**
	 * Descargo institucional obligatorio. Acompaña a todo componente que
	 * hable de amenaza, recurrencia o réplicas.
	 *
	 * @return string
	 */
	public static function descargo() {
		return 'Esta plataforma reúne información oficial con fines educativos y de conocimiento del riesgo. La autoridad técnica en materia sísmica y volcánica es el Servicio Geológico Colombiano (SGC); el manejo de la emergencia corresponde a la UNGRD y al sistema departamental y municipal de gestión del riesgo. Aquí no se predicen sismos: la ciencia no permite anticipar la fecha, el lugar ni la magnitud de un sismo futuro.';
	}

	/**
	 * Enlaces oficiales a los que remite la plataforma.
	 *
	 * @return array[] {clave, nombre, url, descripcion, entidad}
	 */
	public static function fuentes_oficiales() {
		return array(
			array(
				'clave'       => 'sgc_amenaza',
				'entidad'     => 'Servicio Geológico Colombiano',
				'nombre'      => 'Sistema de consulta de la amenaza sísmica',
				'url'         => 'https://amenazasismica.sgc.gov.co/',
				'descripcion' => 'Modelo Nacional de Amenaza Sísmica (SGC – Fundación GEM, 2020). Consulta la probabilidad de excedencia de aceleraciones del terreno para distintos periodos de retorno. Es la referencia oficial de amenaza para cualquier municipio del país.',
			),
			array(
				'clave'       => 'sgc_recientes',
				'entidad'     => 'Servicio Geológico Colombiano',
				'nombre'      => 'Sismos recientes',
				'url'         => self::URL_SISMOS_RECIENTES,
				'descripcion' => 'Sismos registrados por la Red Sismológica Nacional en los últimos días, con la solución oficial para Colombia.',
			),
			array(
				'clave'       => 'sgc_sentidos',
				'entidad'     => 'Servicio Geológico Colombiano',
				'nombre'      => 'Sismos sentidos (reporte ciudadano)',
				'url'         => 'https://sismosentido.sgc.gov.co/',
				'descripcion' => 'Formulario para reportar si usted sintió un sismo. Los reportes ciudadanos alimentan los mapas de intensidad percibida.',
			),
			array(
				'clave'       => 'sgc_catalogo',
				'entidad'     => 'Servicio Geológico Colombiano',
				'nombre'      => 'Catálogo Sísmico Integrado',
				'url'         => 'https://sismicidad.sgc.gov.co/',
				'descripcion' => 'Sismicidad instrumental e histórica de Colombia, con registros desde 1610. Cubre magnitudes menores que el catálogo global y es la referencia para sismicidad cortical.',
			),
			array(
				'clave'       => 'ovsp',
				'entidad'     => 'SGC — Observatorio Vulcanológico y Sismológico de Pasto',
				'nombre'      => 'Vulcanismo de Nariño (Galeras y otros)',
				'url'         => 'https://www2.sgc.gov.co/sgc/volcanes/VolcanGaleras/',
				'descripcion' => 'Monitoreo de Galeras, Chiles-Cerro Negro, Cumbal, Azufral y Doña Juana: niveles de actividad, boletines y alertas. Nariño es el departamento con más volcanes activos del país.',
			),
			array(
				'clave'       => 'ungrd',
				'entidad'     => 'UNGRD',
				'nombre'      => 'Unidad Nacional para la Gestión del Riesgo de Desastres',
				'url'         => 'https://portal.gestiondelriesgo.gov.co/',
				'descripcion' => 'Coordinación nacional del manejo de desastres, guías de preparación y simulacro nacional.',
			),
			array(
				'clave'       => 'dagrd_narino',
				'entidad'     => 'Gobernación de Nariño',
				'nombre'      => 'Gestión del Riesgo de Desastres — Nariño',
				'url'         => 'https://narino.gov.co/',
				'descripcion' => 'Plan Departamental de Gestión del Riesgo y coordinación con los consejos municipales.',
			),
			array(
				'clave'       => 'usgs',
				'entidad'     => 'U.S. Geological Survey',
				'nombre'      => 'Earthquake Hazards Program',
				'url'         => 'https://earthquake.usgs.gov/earthquakes/map/',
				'descripcion' => 'Catálogo global que alimenta las estadísticas de esta plataforma. Dominio público, cobertura regional completa desde magnitud 4,5 aproximadamente.',
			),
		);
	}

	/**
	 * Capas oficiales de amenaza sísmica del SGC, servidas por WMS.
	 *
	 * Son el Modelo Nacional de Amenaza Sísmica (SGC – Universidad Nacional):
	 * aceleración horizontal máxima en roca para distintos periodos de retorno.
	 * La plataforma las MUESTRA con su atribución; no las recalcula ni deriva
	 * de ellas ninguna cifra propia.
	 *
	 * El servicio publica EPSG:4326 y CRS:84, no Web Mercator. Leaflet resuelve
	 * la reproyección al pedir las teselas en EPSG:4326; a la latitud de Nariño
	 * (0°–3° N) la diferencia frente a Mercator es inferior al 0,05 %.
	 *
	 * @return array[] {clave, periodo, nombre, excedencia, url, capa}
	 */
	public static function capas_wms() {
		$base = 'https://srvags.sgc.gov.co/arcgis/services/Amenaza_Sismica/Mapa_Amenaza_Sismica_Nacional_PGA';

		$periodos = array(
			'75'   => array( 'excedencia' => '50 %', 'nombre' => 'Amenaza sísmica — periodo de retorno de 75 años' ),
			'225'  => array( 'excedencia' => '20 %', 'nombre' => 'Amenaza sísmica — periodo de retorno de 225 años' ),
			'475'  => array( 'excedencia' => '10 %', 'nombre' => 'Amenaza sísmica — periodo de retorno de 475 años' ),
			'975'  => array( 'excedencia' => '5 %', 'nombre' => 'Amenaza sísmica — periodo de retorno de 975 años' ),
			'2475' => array( 'excedencia' => '2 %', 'nombre' => 'Amenaza sísmica — periodo de retorno de 2.475 años' ),
		);

		$out = array();
		foreach ( $periodos as $periodo => $meta ) {
			$out[] = array(
				'clave'      => 'pga_' . $periodo,
				'periodo'    => (int) $periodo,
				'nombre'     => $meta['nombre'],
				'excedencia' => $meta['excedencia'],
				'etiqueta'   => sprintf(
					'%s de probabilidad de excedencia en 50 años',
					$meta['excedencia']
				),
				'url'        => $base . $periodo . '/MapServer/WMSServer',
				'capa'       => '0',
				'atribucion' => 'Amenaza: Servicio Geológico Colombiano — Modelo Nacional de Amenaza Sísmica',
			);
		}
		return $out;
	}

	/**
	 * Periodo de retorno por defecto de la capa de amenaza.
	 * 475 años (10 % de excedencia en 50 años) es el nivel de diseño que usa
	 * la norma sismo resistente colombiana.
	 *
	 * @return int
	 */
	public static function periodo_defecto() {
		return 475;
	}

	/**
	 * Capa de amenaza correspondiente a un periodo de retorno.
	 *
	 * @param int|string $periodo Periodo de retorno en años.
	 * @return array
	 */
	public static function capa_wms( $periodo ) {
		$periodo = (int) $periodo;
		foreach ( self::capas_wms() as $capa ) {
			if ( $capa['periodo'] === $periodo ) {
				return $capa;
			}
		}
		return self::capa_wms( self::periodo_defecto() );
	}

	/**
	 * Glosario que separa cuatro conceptos que suelen confundirse (marco USGS).
	 *
	 * @return array[] {termino, definicion, ejemplo, es_posible}
	 */
	public static function glosario() {
		return array(
			array(
				'termino'    => 'Alerta temprana',
				'es_posible' => true,
				'definicion' => 'No anticipa nada: detecta un sismo que YA empezó y avisa a las zonas que las ondas todavía no alcanzan. Da entre unos segundos y algunas decenas de segundos para protegerse.',
				'ejemplo'    => 'ShakeAlert en la costa oeste de Estados Unidos; SASMEX en México; el sistema japonés. Colombia no cuenta hoy con un sistema público de alerta sísmica temprana.',
			),
			array(
				'termino'    => 'Pronóstico (forecast)',
				'es_posible' => true,
				'definicion' => 'Probabilidad de que ocurra cierto número de sismos de determinada magnitud en un periodo. El caso más maduro es el pronóstico de réplicas después de un sismo fuerte.',
				'ejemplo'    => 'El USGS y GeoNet (Nueva Zelanda) publican pronósticos de réplicas. En Colombia, el SGC no emite este producto: por eso esta plataforma no lo genera ni lo estima por su cuenta.',
			),
			array(
				'termino'    => 'Probabilidad de largo plazo',
				'es_posible' => true,
				'definicion' => 'Los mapas de amenaza sísmica: qué tan intenso puede llegar a ser el movimiento del suelo en un sitio y con qué probabilidad de excedencia en un periodo largo, típicamente 50 años.',
				'ejemplo'    => 'El Modelo Nacional de Amenaza Sísmica del SGC. Es la base de la norma sismo resistente y de la planeación territorial.',
			),
			array(
				'termino'    => 'Predicción',
				'es_posible' => false,
				'definicion' => 'Afirmar la fecha y hora, el lugar y la magnitud de un sismo que aún no ha ocurrido. No es posible: ninguna metodología científica lo logra hoy, y no se espera lograrlo en el futuro previsible.',
				'ejemplo'    => 'Toda «predicción» que circule con fecha y hora exactas es falsa, sin excepción, incluso si viene con sellos o logotipos de apariencia oficial.',
			),
		);
	}

	/**
	 * Contexto geológico de la amenaza en Nariño.
	 *
	 * @return array[] {titulo, texto, enlace}
	 */
	public static function contexto_geologico() {
		return array(
			array(
				'titulo' => 'Subducción frente al Pacífico',
				'texto'  => 'La placa de Nazca —y la microplaca de Malpelo en su extremo nororiental— se hunde bajo la placa Sudamericana a lo largo de la fosa Colombia–Ecuador, frente a la costa nariñense, a una velocidad del orden de 58 mm por año. Esa convergencia es la fuente de los sismos más grandes que puede producir la región, incluidos los capaces de generar tsunami.',
				'enlace' => '',
			),
			array(
				'titulo' => 'Fallas activas continentales',
				'texto'  => 'Tierra adentro actúan los sistemas de fallas de Romeral y del Cauca, entre otros. Producen sismos de magnitud menor que los de subducción pero mucho más superficiales y cercanos a las cabeceras municipales, por lo que pueden causar daños importantes en su entorno inmediato.',
				'enlace' => '',
			),
			array(
				'titulo' => 'Sismicidad volcánica',
				'texto'  => 'Nariño es el departamento con más volcanes activos del país: Galeras, Chiles–Cerro Negro, Cumbal, Azufral y Doña Juana. Su sismicidad es de otra naturaleza —fracturamiento y movimiento de fluidos dentro del edificio volcánico— y la vigila el Observatorio Vulcanológico y Sismológico de Pasto con sus propios niveles de actividad.',
				'enlace' => 'https://www2.sgc.gov.co/sgc/volcanes/VolcanGaleras/',
			),
			array(
				'titulo' => 'Amenaza por tsunami en el litoral',
				'texto'  => 'El 12 de diciembre de 1979, un sismo de magnitud cercana a 8,2 frente a Tumaco generó un tsunami que causó la mayor parte de las víctimas del evento y golpeó sobre todo a Nariño. A raíz de ese desastre se creó en 1982 el Comité Técnico Nacional de Alerta por Tsunami. Los municipios costeros deben conocer sus rutas de evacuación vertical y horizontal.',
				'enlace' => '',
			),
			array(
				'titulo' => 'Por qué la profundidad importa',
				'texto'  => 'Dos sismos de la misma magnitud no se sienten igual. Los superficiales (menos de 70 km) concentran la energía cerca de la superficie y son los que causan daños; los intermedios y profundos, típicos de la placa que se hunde, se sienten en un área más amplia pero con menor intensidad. El sismo de magnitud 7,4 del 10 de agosto de 2026 en San José del Palmar (Chocó) ocurrió a unos 110 km de profundidad y se sintió en buena parte del país.',
				'enlace' => 'https://earthquake.usgs.gov/earthquakes/eventpage/us6000tjl2',
			),
		);
	}

	/**
	 * Referencia normativa (NSR-10) y su estado de vigencia.
	 *
	 * Los coeficientes se guardan como opción editable: la fuente canónica es
	 * el Apéndice A-4 y la Tabla A.2.3-2 de la NSR-10, y deben verificarse
	 * contra el texto de la norma antes de publicarse como dato oficial.
	 *
	 * @return array
	 */
	public static function normativa() {
		$cfg = function_exists( 'get_option' ) ? get_option( 'sis_amenaza', array() ) : array();
		return wp_parse_args( is_array( $cfg ) ? $cfg : array(), self::normativa_por_defecto() );
	}

	/**
	 * Valores por defecto de la referencia normativa (editables en el panel).
	 *
	 * @return array
	 */
	public static function normativa_por_defecto() {
		return array(
			'norma'          => 'NSR-10 (Reglamento Colombiano de Construcción Sismo Resistente, Decreto 926 de 2010)',
			'vigencia'       => 'Vigente. La actualización propuesta por la Asociación Colombiana de Ingeniería Sísmica (AIS 100-24) aún no ha sido adoptada por decreto: verifique antes de publicar cifras como definitivas.',
			'zona_pasto'     => 'Amenaza sísmica alta',
			'aa_pasto'       => '0.25',
			'av_pasto'       => '0.25',
			'aa_pacifico'    => '0.50',
			'av_pacifico'    => '0.40',
			'nota'           => 'Aa es la aceleración pico efectiva de diseño y Av la que controla la respuesta en el rango de velocidades; ambas corresponden a un 10 % de probabilidad de excedencia en 50 años. Los valores por municipio deben tomarse del Apéndice A-4 de la NSR-10 o consultarse en el sistema de amenaza del SGC.',
			'microzonificacion' => 'El SGC y el municipio de Pasto adelantan la microzonificación sísmica de la ciudad y de los corregimientos de Mapachico, Morasurco y Genoy. Cuando se publique, sus mapas serán la referencia de mayor detalle para Pasto.',
		);
	}

	/**
	 * Señales para reconocer una «predicción» falsa. Es el contenido del panel
	 * anti-desinformación, que replica los desmentidos del SGC y del IDEAM.
	 *
	 * @return array[] {senal, explicacion}
	 */
	public static function senales_falsas() {
		return array(
			array(
				'senal'       => 'Anuncia fecha y hora exactas',
				'explicacion' => 'Ninguna entidad científica del mundo puede hacerlo. Una fecha concreta es, por sí sola, prueba de que el mensaje es falso.',
			),
			array(
				'senal'       => 'Circula por WhatsApp o redes con un sello o logotipo institucional',
				'explicacion' => 'Los sellos se copian y hoy también se generan con inteligencia artificial. Verifique siempre en los canales oficiales del SGC; si no está publicado allí, no es oficial.',
			),
			array(
				'senal'       => 'Habla de «alineaciones planetarias», «clima sísmico», nubes, dolores de cabeza o comportamiento animal',
				'explicacion' => 'No existe evidencia científica que relacione ninguno de esos fenómenos con la ocurrencia de sismos. El USGS los descarta de forma explícita.',
			),
			array(
				'senal'       => 'Es tan general que siempre acierta',
				'explicacion' => '«Habrá un sismo fuerte en el Pacífico en los próximos meses» no es una predicción: en una zona activa siempre habrá algún sismo que encaje con un enunciado así.',
			),
			array(
				'senal'       => 'Anuncia una réplica concreta después de un sismo fuerte',
				'explicacion' => 'Tras el sismo del 10 de agosto de 2026 circularon cadenas y comunicados falsos anunciando réplicas con fecha y hora. El SGC, el IDEAM y las autoridades ecuatorianas los desmintieron: las réplicas se cuentan después de ocurridas, no se anuncian antes.',
			),
			array(
				'senal'       => 'Incluye videos o imágenes de destrucción que no corresponden al evento',
				'explicacion' => 'Tras los sismos grandes circulan videos de otros desastres y material generado con inteligencia artificial. Contraste siempre con medios y entidades oficiales antes de compartir.',
			),
		);
	}

	/**
	 * Qué hacer y qué esperar después de un sismo fuerte.
	 *
	 * Información educativa FIJA: no incluye cifras ni probabilidades propias.
	 * Cuando ocurra un evento, el componente remite al boletín oficial del SGC.
	 *
	 * @return array
	 */
	public static function replicas() {
		return array(
			'que_son'      => 'Una réplica es un sismo que ocurre después de otro mayor y en la misma zona, mientras la corteza reacomoda los esfuerzos de la ruptura. No son un fenómeno anormal: son parte esperada del proceso.',
			'cuanto_duran' => 'Una secuencia de réplicas puede durar días, semanas o meses, y su frecuencia disminuye con el tiempo. Las primeras horas concentran la mayor cantidad. Que haya réplicas no significa que se aproxime un sismo mayor, y que dejen de sentirse tampoco garantiza que hayan terminado.',
			'que_hacer'    => array(
				'Revise su vivienda antes de volver a entrar: si hay grietas nuevas en muros o columnas, no ingrese y repórtelo al consejo municipal de gestión del riesgo.',
				'Aléjese de fachadas, cornisas, vidrios y estructuras dañadas: las réplicas derriban lo que quedó debilitado.',
				'Mantenga a mano el kit de emergencia y la ruta de evacuación acordada en familia.',
				'Si está en el litoral y el sismo fue largo o muy fuerte, no espere a ninguna alerta: diríjase de inmediato a la zona alta más cercana.',
				'Use el celular para mensajes cortos y libere las líneas para la atención de la emergencia.',
			),
			'no_haga'      => array(
				'No comparta «predicciones» de réplicas: son falsas y entorpecen la respuesta.',
				'No difunda cifras de víctimas sin fuente oficial.',
				'No regrese a edificaciones evacuadas hasta que sean revisadas.',
			),
			'donde_mirar'  => 'El conteo real de réplicas y su magnitud los publica el SGC en sus boletines y en el visor de sismos recientes, después de que ocurren.',
		);
	}

	/**
	 * Bloque completo para la API y los componentes.
	 *
	 * @return array
	 */
	public static function ficha() {
		return array(
			'descargo'    => self::descargo(),
			'glosario'    => self::glosario(),
			'fuentes'     => self::fuentes_oficiales(),
			'geologia'    => self::contexto_geologico(),
			'normativa'   => self::normativa(),
			'replicas'    => self::replicas(),
			'senales'     => self::senales_falsas(),
			'capas_wms'   => self::capas_wms(),
			'marco_legal' => 'Ley 1523 de 2012: la gestión del riesgo se compone de conocimiento del riesgo, reducción del riesgo y manejo de desastres. Esta plataforma corresponde al primero de esos procesos.',
			'generado'    => gmdate( 'c' ),
		);
	}
}
