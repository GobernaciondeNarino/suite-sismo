<?php
/**
 * Catálogo de shortcodes para la pantalla «Elementos» del panel.
 *
 * Cada entrada describe qué publica el componente, qué atributos admite y un
 * ejemplo listo para copiar y pegar en cualquier página, entrada o widget.
 *
 * @package SismosNarino
 */

defined( 'ABSPATH' ) || exit;

return array(

	array(
		'shortcode' => '[sismos_estado]',
		'titulo'    => 'Estado de la actividad sísmica',
		'que_hace'  => 'Semáforo con el último sismo registrado (magnitud, profundidad, lugar y municipio más cercano) y los conteos de las últimas 24 horas, semana, mes y año. Con «vivo» activado se refresca desde el feed del USGS cada dos minutos.',
		'atributos' => 'ambito · dias · min_mag · compacto (si|no) · vivo (si|no)',
		'ejemplo'   => '[sismos_estado ambito="narino" vivo="si"]',
	),

	array(
		'shortcode' => '[sismos_ultimos]',
		'titulo'    => 'Últimos sismos',
		'que_hace'  => 'Lista de los sismos más recientes con magnitud, antigüedad, profundidad y enlace a la ficha del USGS. Los eventos nuevos se destacan al llegar.',
		'atributos' => 'ambito · dias · min_mag · limite · vivo (si|no)',
		'ejemplo'   => '[sismos_ultimos limite="8" ambito="regional"]',
	),

	array(
		'shortcode' => '[sismos_mapa]',
		'titulo'    => 'Mapa de epicentros',
		'que_hace'  => 'Mapa Leaflet con un círculo por sismo: el tamaño codifica la magnitud y el color la profundidad. Puede superponer los centroides de los 64 municipios de Nariño.',
		'atributos' => 'ambito · dias · anios · min_mag · alto · municipios (si|no) · zoom',
		'ejemplo'   => '[sismos_mapa ambito="regional" anios="5" min_mag="4.5" alto="520px"]',
	),

	array(
		'shortcode' => '[sismos_grafico]',
		'titulo'    => 'Gráfico estadístico (motor D3plus)',
		'que_hace'  => 'Tarjeta de gráfico con barra de herramientas: Cómo funciona, Detalle, Compartir, Datos, Imagen PNG, Descarga JSON y cambio de tipo en vivo. Elija el conjunto de datos con «view» y el tipo con «type».',
		'atributos' => 'view · type · ambito · anios · min_mag · theme (claro|oscuro) · actions · legend · legend_style · legend_pos · toolbar · alto · grupo · analisis · titulo',
		'ejemplo'   => '[sismos_grafico view="frecuencia_magnitud" type="line" alto="460px"]',
	),

	array(
		'shortcode' => '[sismos_pronostico]',
		'titulo'    => 'Pronóstico a 6 meses',
		'que_hace'  => 'Ficha completa del pronóstico probabilístico: sismos esperados con su banda, evolución mes a mes, probabilidad por umbral de magnitud, magnitud máxima esperada, comparación con el pronóstico anterior y advertencia metodológica.',
		'atributos' => 'ambito · modo (completo|resumen|umbrales|metodo) · grafico (si|no)',
		'ejemplo'   => '[sismos_pronostico ambito="regional" modo="completo"]',
	),

	array(
		'shortcode' => '[sismos_estadistica]',
		'titulo'    => 'Ficha estadística del catálogo',
		'que_hace'  => 'Indicadores clave: número de sismos, años de registro, magnitud de completitud, valor b con su error, tasa anual, mayor magnitud registrada, energía liberada y periodos de retorno por magnitud.',
		'atributos' => 'ambito · anios · dias · min_mag',
		'ejemplo'   => '[sismos_estadistica ambito="regional" anios="20"]',
	),

	array(
		'shortcode' => '[sismos_datos]',
		'titulo'    => 'Datos abiertos',
		'que_hace'  => 'Botones de descarga en JSON y CSV y enlace directo a la API pública del plugin, con la atribución al USGS incorporada.',
		'atributos' => 'recurso (eventos|estadistica|pronostico) · ambito · anios · dias · min_mag · texto',
		'ejemplo'   => '[sismos_datos recurso="pronostico" texto="Descargue el pronóstico a seis meses"]',
	),

	array(
		'shortcode' => '[sismos_estado_api]',
		'titulo'    => 'Estado de las fuentes',
		'que_hace'  => 'Panel público de transparencia: qué fuentes están activas, cuándo se sincronizaron por última vez y con qué resultado.',
		'atributos' => '—',
		'ejemplo'   => '[sismos_estado_api]',
	),

	array(
		'shortcode' => '[sismos_descripcion]',
		'titulo'    => 'Descripción de una vista',
		'que_hace'  => 'Publica solo el texto que explica qué muestra el gráfico, para maquetarlo aparte de la gráfica.',
		'atributos' => 'view · ambito · anios · titulo · grupo',
		'ejemplo'   => '[sismos_descripcion view="pronostico_mensual"]',
	),

	array(
		'shortcode' => '[sismos_explicacion]',
		'titulo'    => 'Cómo funciona una vista',
		'que_hace'  => 'Publica solo la explicación metodológica: qué se calcula, con qué fuente y bajo qué supuestos.',
		'atributos' => 'view · ambito · anios · titulo · grupo',
		'ejemplo'   => '[sismos_explicacion view="frecuencia_magnitud"]',
	),

	array(
		'shortcode' => '[sismos_analisis_cualitativo]',
		'titulo'    => 'Interpretación de una vista',
		'que_hace'  => 'Publica solo el párrafo de interpretación: cómo leer el gráfico y qué errores evitar.',
		'atributos' => 'view · ambito · anios · titulo · grupo',
		'ejemplo'   => '[sismos_analisis_cualitativo view="sismos_mensuales"]',
	),

	array(
		'shortcode' => '[sismos_analisis_cuantitativo]',
		'titulo'    => 'Cifras clave de una vista',
		'que_hace'  => 'Publica solo las cifras calculadas con los datos vigentes (máximos, promedios, participación y tendencia). Cambia cuando cambian los datos.',
		'atributos' => 'view · ambito · anios · titulo · grupo',
		'ejemplo'   => '[sismos_analisis_cuantitativo view="energia_mensual"]',
	),

	array(
		'shortcode' => '[sismos_prediccion_dato]',
		'titulo'    => 'Párrafo predictivo de una vista',
		'que_hace'  => 'Publica el pronóstico vigente redactado en prosa, con su método y su advertencia. Se recalcula con cada actualización del catálogo.',
		'atributos' => 'view · ambito · titulo · grupo',
		'ejemplo'   => '[sismos_prediccion_dato view="pronostico_mensual"]',
	),

	array(
		'shortcode' => '[sismos_analisis]',
		'titulo'    => 'Análisis completo de una vista',
		'que_hace'  => 'Interpretación y cifras clave juntas, sin la gráfica.',
		'atributos' => 'view · modo (ambos|descriptivo|cuantitativo|descripcion|como_funciona|prediccion) · ambito · anios · titulo · grupo',
		'ejemplo'   => '[sismos_analisis view="profundidad" modo="ambos"]',
	),

	array(
		'shortcode' => '[sismos_filtro] + [sismos_panel]',
		'titulo'    => 'Componentes composables',
		'que_hace'  => 'Separe el gráfico, sus filtros y el panel de detalles en shortcodes distintos y enlácelos con el mismo atributo «grupo»: al cambiar un filtro, el gráfico del grupo se vuelve a dibujar.',
		'atributos' => 'grupo · control (vista|tipo|ambito|anios) · etiqueta',
		'ejemplo'   => '[sismos_filtro grupo="sis" control="vista"] [sismos_filtro grupo="sis" control="tipo"] [sismos_grafico grupo="sis"] [sismos_panel grupo="sis"]',
	),
);
