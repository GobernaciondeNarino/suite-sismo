<?php
/**
 * Textos largos por vista del motor de gráficos.
 *
 * Cada entrada trae tres piezas independientes, pensadas para publicarse por
 * separado con los shortcodes de texto:
 *   · descripcion    — qué muestra el gráfico y de dónde sale el dato.
 *   · analisis       — cómo leerlo e interpretarlo (cualitativo, estable).
 *   · como_funciona  — el método detrás: qué se calcula y con qué supuestos.
 * La parte cuantitativa NO va aquí: se calcula con los datos vigentes en
 * SIS_Views::analisis() para que las cifras nunca queden desactualizadas.
 *
 * @package SismosNarino
 */

defined( 'ABSPATH' ) || exit;

return array(

	'sismos_mensuales'      => array(
		'descripcion'   => 'Número de sismos registrados mes a mes por la red global del USGS dentro del ámbito seleccionado. Cada barra es un mes calendario completo y los meses sin actividad aparecen en cero, porque un mes en calma también es información. La serie parte del catálogo del servicio FDSN Event, que integra las soluciones de las agencias sismológicas que contribuyen al sistema global, y se actualiza cada vez que el plugin sincroniza. Es la vista más directa del pulso sísmico de la región: permite ver rachas, pausas y secuencias de réplicas sin ningún tratamiento estadístico de por medio.',
		'analisis'      => 'La sismicidad no llega repartida con regularidad: alterna meses tranquilos con rachas en las que un sismo principal arrastra decenas de réplicas. Por eso conviene leer la serie por tramos y no mes a mes. Un pico aislado casi siempre delata una secuencia de réplicas y suele ir seguido de un descenso gradual; una subida sostenida durante varios meses es más informativa que cualquier valor puntual. Recuerde además que el catálogo global solo registra de forma completa los sismos por encima de cierta magnitud: los meses «vacíos» no significan quietud del subsuelo, sino ausencia de eventos por encima de ese umbral de detección.',
		'como_funciona' => 'Se cuentan los eventos de tipo terremoto cuyo epicentro cae dentro del ámbito y cuya hora de origen pertenece al mes. Los meses intermedios sin eventos se rellenan con cero para que la serie sea continua y las tendencias no queden falseadas por huecos. Fuente: USGS Earthquake Hazards Program (FDSN Event Web Service).',
	),

	'sismos_anuales'        => array(
		'descripcion'   => 'Conteo anual de sismos en el ámbito seleccionado, desde el inicio de la ventana consultada hasta el año en curso. Sirve de perspectiva larga frente a la vista mensual: al agregar por año se diluye el ruido de las secuencias de réplicas y queda a la vista el nivel de actividad de fondo de la región. El último año suele aparecer incompleto, porque solo acumula los meses transcurridos.',
		'analisis'      => 'Los años con cifras muy por encima del promedio corresponden casi siempre a un gran sismo y su secuencia posterior, no a un cambio permanente del régimen tectónico. La subducción de la placa de Nazca bajo Sudamérica libera energía de forma episódica: décadas de acumulación y momentos de descarga. Un año alto seguido de otros normales es el patrón esperable; lo que merece atención es una tendencia sostenida al alza durante varios años, y aun así conviene contrastarla con la evolución de la red de detección, que también hace subir los conteos al mejorar.',
		'como_funciona' => 'Agrupación por año calendario UTC de la hora de origen de cada evento. No se aplica ninguna corrección por completitud del catálogo, de modo que las cifras de los años más antiguos pueden estar subestimadas si la cobertura instrumental era menor.',
	),

	'magnitud_mensual'      => array(
		'descripcion'   => 'Dos series superpuestas por mes: la magnitud promedio de todos los sismos registrados y la magnitud del mayor de ese mes. Juntas distinguen dos situaciones que el conteo simple confunde: un mes con muchos sismos pequeños y un mes con pocos pero grandes. Es la vista adecuada para responder «¿cómo de fuerte fue lo que ocurrió?» en lugar de solo «¿cuánto ocurrió?».',
		'analisis'      => 'La magnitud media es sorprendentemente estable en el tiempo, porque está dominada por el umbral de detección del catálogo: casi siempre ronda un valor apenas superior a la magnitud de completitud. La serie de máximos, en cambio, es la interesante: sus picos marcan los eventos que la población efectivamente sintió. Cuando la media sube y el máximo no, suele indicar que la red registró menos eventos pequeños ese mes, no que los sismos fueran mayores. La lectura útil es siempre conjunta.',
		'como_funciona' => 'Para cada mes se promedian las magnitudes de todos los eventos y se toma el máximo. Las magnitudes provienen de distintas escalas según el evento (mb, Mw, ML), tal como las publica el USGS; para los tamaños que aquí interesan son comparables entre sí.',
	),

	'energia_mensual'       => array(
		'descripcion'   => 'Energía sísmica irradiada cada mes, expresada en toneladas equivalentes de TNT. Se obtiene sumando la energía de cada sismo del mes según la relación de Hanks y Kanamori, log10(E) = 1,5·M + 4,8, con E en julios. A diferencia del conteo, esta vista pondera cada evento por su tamaño real: un solo sismo de magnitud 6 libera más energía que miles de magnitud 3 juntos.',
		'analisis'      => 'La escala de magnitud es logarítmica, así que la energía es el indicador honesto de «cuánto pasó de verdad». En la práctica la serie está dominada por unos pocos meses: los que tuvieron el sismo más grande. Que un mes con muchos eventos pequeños apenas se note en esta gráfica no es un error, es la física del asunto: cada unidad de magnitud multiplica la energía por unas treinta y dos veces. Conviene mirar esta vista junto a la de conteo mensual, porque cuentan cosas distintas del mismo fenómeno.',
		'como_funciona' => 'Se aplica a cada sismo la relación energía-magnitud de Hanks y Kanamori y se suman los julios del mes, convertidos a toneladas de TNT (1 t de TNT = 4,184·10⁹ J). Es la energía irradiada en ondas sísmicas, no la energía total del proceso de ruptura.',
	),

	'historico_mensual'     => array(
		'descripcion'   => 'Todo el registro disponible mes a mes, con dos series superpuestas: los sismos de cada mes y su media móvil centrada de doce meses. La serie cruda enseña lo que pasó; la media móvil enseña el nivel de fondo, que es lo que cambia despacio. Cubre la ventana completa del catálogo, no los últimos años, porque la pregunta que responde es «¿cómo ha sido esto a lo largo del tiempo?».',
		'analisis'      => 'Mire primero la línea suave. Si se mantiene plana durante años, la región está en su régimen habitual por más que la serie cruda tenga picos: esos picos son casi siempre un sismo principal con sus réplicas, no un cambio de comportamiento. Un desplazamiento sostenido de la media móvil durante varios años sí es un cambio de tasa —o una mejora de la red de detección, que sube los conteos sin que tiemble más—. La distancia entre las dos líneas también informa: cuanto más se separan, más concentrada en episodios está la sismicidad.',
		'como_funciona' => 'Conteo mensual de eventos dentro del ámbito, con los meses sin actividad en cero y la serie extendida hasta el mes en curso. La media móvil promedia una ventana centrada de doce meses; en los extremos de la serie se promedia solo la parte disponible, sin extrapolar meses que el catálogo no cubre. Fuente: USGS Earthquake Hazards Program (FDSN Event Web Service).',
	),

	'energia_acumulada'     => array(
		'descripcion'   => 'Suma acumulada de la energía sísmica liberada, mes a mes, en toneladas equivalentes de TNT. Es la versión energética de la curva acumulada de conteo, y se conoce en sismología como curva de Benioff. Cada punto responde a «cuánta energía había liberado la región hasta esta fecha».',
		'analisis'      => 'La curva sube a escalones, no en rampa. Cada peldaño es un sismo grande: como cada unidad de magnitud multiplica la energía por unas treinta y dos veces, un solo evento de magnitud 6 levanta la curva más que todos los pequeños de la década. Los tramos planos entre escalones son los periodos en que la falla acumula esfuerzo sin liberarlo. Conviene resistir la tentación de leer el siguiente escalón: la curva describe lo ocurrido, y su forma pasada no dice cuándo llegará el próximo salto.',
		'como_funciona' => 'A cada sismo se le aplica la relación energía-magnitud log10(E) = 1,5·M + 4,8, con E en julios; se suman los julios del mes, se convierten a toneladas de TNT (1 t = 4,184·10⁹ J) y se acumulan desde el primer mes del catálogo. La suma se hace sobre la energía, nunca sobre la magnitud: promediar magnitudes mezclaría escalas logarítmicas y falsearía el resultado.',
	),

	'calendario_sismico'    => array(
		'descripcion'   => 'El catálogo entero dispuesto como un calendario: un año por fila, un mes por columna y el color de cada celda según cuántos sismos hubo ese mes. Es la misma información de la serie mensual, ordenada de forma que el ojo compare meses equivalentes de años distintos.',
		'analisis'      => 'Busque manchas, no celdas sueltas. Una fila con varias celdas oscuras seguidas es un año de actividad sostenida; una sola celda muy oscura suele ser un sismo principal con su secuencia de réplicas concentrada en semanas. Lo que no va a encontrar es un patrón por columnas: si la sismicidad tuviera «meses malos», las columnas se verían sistemáticamente más oscuras, y no ocurre. Esa ausencia de patrón estacional es, en sí misma, el resultado más útil de esta vista.',
		'como_funciona' => 'Conteo de eventos por mes calendario UTC, dispuesto en una rejilla año × mes. Los meses sin actividad se dibujan igualmente, en el extremo claro de la escala, y la rejilla llega hasta el mes en curso. El color es una escala secuencial: a más oscuro, más sismos.',
	),

	'hora_del_dia'          => array(
		'descripcion'   => 'Reparto de todos los sismos del catálogo entre las 24 horas del día, en hora de Colombia. Cada barra cuenta los eventos cuya hora de origen cayó en esa franja, sumando todos los años disponibles.',
		'analisis'      => 'Las barras deberían quedar aproximadamente parejas, y eso es exactamente lo que se busca comprobar. Es común oír que los sismos ocurren de madrugada; lo que ocurre de madrugada es que la gente está en silencio y quieta, y por eso los nota más. Las diferencias que se ven entre horas son las que cabe esperar del azar cuando se reparten unos cientos de eventos en 24 casillas. Si una franja destacara de verdad, la explicación estaría en el catálogo —una secuencia de réplicas concentrada en pocas horas— y no en el reloj.',
		'como_funciona' => 'Se toma la hora de origen de cada evento, que el USGS publica en UTC, y se convierte a hora de Colombia restando cinco horas: el país usa UTC−5 todo el año, sin horario de verano, así que el desplazamiento es fijo y no introduce error. Después se cuentan los eventos de cada franja horaria.',
	),

	'intervalos'            => array(
		'descripcion'   => 'Histograma del tiempo transcurrido entre cada sismo y el anterior. Los tramos son desiguales a propósito —de horas a meses—, porque la mayoría de los intervalos son cortos y una escala uniforme dejaría la cola larga en barras invisibles.',
		'analisis'      => 'La forma típica es una barra alta en los intervalos cortos que va cayendo hacia los largos. Esa acumulación en la izquierda es la firma de las réplicas: después de un sismo fuerte llegan muchos en pocas horas o días. Fuera de esas rachas, los intervalos se reparten sin regularidad. La lectura importante es negativa: no existe un intervalo «típico» al que la región vuelva, de modo que contar días desde el último sismo no acerca ni aleja al siguiente. La sismicidad no tiene memoria de calendario.',
		'como_funciona' => 'Se ordenan los eventos por hora de origen y se mide la diferencia con el evento inmediatamente anterior dentro del mismo ámbito y umbral de magnitud. Cambiar el ámbito o la magnitud mínima cambia la serie completa: con un umbral más alto hay menos sismos y los intervalos se alargan.',
	),

	'sismos_sentidos'       => array(
		'descripcion'   => 'Dos series por año: cuántos sismos registraron los instrumentos y cuántos de ellos dejaron al menos un reporte ciudadano en «Did You Feel It?», el sistema de reportes voluntarios del USGS. La diferencia entre ambas barras es la parte de la sismicidad que ocurre sin que nadie la note.',
		'analisis'      => 'La mayoría de los sismos del catálogo no los siente nadie: son pequeños, profundos o mar adentro. Los que sí se sienten son los que importan para la preparación ciudadana. Ahora bien, esta serie mide percepción y conectividad tanto como sismología: un sismo sentido en una zona sin cobertura de internet no genera reportes, y el número de reportes ha crecido con el uso de teléfonos móviles. Léala como un indicador de exposición de la población, no como una medida física del sismo.',
		'como_funciona' => 'Se cuentan los eventos del año y, aparte, los que traen al menos un reporte en el campo «felt» del USGS. No se usa el número de reportes, solo si hubo alguno: el conteo depende de cuánta gente conoce el formulario y tiene con qué llenarlo, así que compararlo entre municipios o años sería engañoso.',
	),

	'dispersion_mag_prof'   => array(
		'descripcion'   => 'Un punto por sismo, con la magnitud en el eje horizontal y la profundidad del hipocentro en el vertical. El color indica el rango de profundidad. A diferencia del diagrama de caja, aquí no hay agregación: se ve cada evento del catálogo tal cual quedó registrado.',
		'analisis'      => 'Lo primero que salta es que los sismos no se reparten de forma uniforme en profundidad: se agrupan en bandas, que corresponden a la geometría de la placa de Nazca hundiéndose bajo Sudamérica. La banda somera, por encima de unos 70 km, es la que más importa para el territorio: a igualdad de magnitud, un sismo somero sacude mucho más fuerte en superficie que uno profundo. Fíjese también en que los eventos mayores no aparecen sistemáticamente a una profundidad concreta; magnitud y profundidad son parámetros independientes del mismo proceso.',
		'como_funciona' => 'Se dibuja cada evento del ámbito y la ventana seleccionados, sin agrupar ni promediar. La profundidad procede de la tercera coordenada del GeoJSON del USGS, en kilómetros. Conviene recordar que la profundidad es el parámetro peor determinado de una solución sísmica: cuando la estación más cercana está más lejos que la profundidad calculada, la incertidumbre crece bastante.',
	),

	'acumulado'             => array(
		'descripcion'   => 'Curva acumulada del número de sismos a lo largo del tiempo: cada punto suma todo lo ocurrido hasta ese mes. Es una de las herramientas clásicas de la sismología estadística, porque convierte los cambios de ritmo en cambios de pendiente, mucho más fáciles de ver a simple vista que en una serie de barras.',
		'analisis'      => 'Una recta significa que la sismicidad ocurre a tasa constante, que es el comportamiento esperado de un proceso de Poisson. Los escalones bruscos delatan secuencias de réplicas concentradas en pocos días. Un cambio duradero de pendiente —la curva se empina o se aplana durante meses— sí sugiere un cambio real en la tasa de actividad; leerlo hacia atrás es legítimo, proyectarlo hacia adelante no lo es. Los tramos planos largos son periodos de calma sísmica por encima del umbral de detección.',
		'como_funciona' => 'Suma acumulada del conteo mensual dentro de la ventana consultada. El origen de la curva es el primer mes disponible del catálogo, no una fecha fija, de modo que al cambiar el ámbito o la ventana la curva se recalcula por completo.',
	),

	'frecuencia_magnitud'   => array(
		'descripcion'   => 'La relación frecuencia-magnitud del catálogo, es decir, cuántos sismos hay por encima de cada magnitud, comparada con el ajuste teórico de la ley de Gutenberg-Richter. Es el gráfico fundamental de la sismología estadística: la base sobre la que se calculan las tasas y los intervalos de recurrencia observados que publica la ficha estadística. Describe lo que el catálogo ya registró; no dice cuándo ocurrirá el próximo.',
		'analisis'      => 'En escala logarítmica los puntos deberían alinearse: por cada sismo de magnitud M ocurren unas 10^b de magnitud M−1, con b habitualmente cercano a 1. La parte baja de la curva se dobla porque el catálogo deja de registrar los sismos más pequeños; el punto donde empieza esa curvatura es la magnitud de completitud, y solo a partir de ella el ajuste es legítimo. En el extremo alto la curva se aparta por otra razón: hay pocos sismos grandes y la estadística se vuelve escasa. Un valor b bajo indica mayor proporción de sismos grandes; uno alto, predominio de los pequeños.',
		'como_funciona' => 'La magnitud de completitud se estima por máxima curvatura con la corrección estándar de +0,2 (Wiemer y Wyss, 2000). El valor b se calcula con el estimador de máxima verosimilitud de Aki (1965) corregido por discretización, y su incertidumbre con la fórmula de Shi y Bolt (1982). El valor a se normaliza a tasa anual sobre la ventana observada.',
	),

	'distribucion_magnitud' => array(
		'descripcion'   => 'Histograma de magnitudes en intervalos de una décima: cuántos sismos hay exactamente de cada tamaño. Es la versión no acumulada de la relación frecuencia-magnitud y la forma más directa de ver dónde deja de ser completo el catálogo.',
		'analisis'      => 'El histograma sube hasta un máximo y luego cae. Ese máximo no es el tamaño «más frecuente» del subsuelo, sino el punto en que la red deja de detectar todo lo que ocurre: por debajo faltan eventos, por encima el registro es fiable. Ese pico es justamente lo que estima el método de máxima curvatura para fijar la magnitud de completitud. A la derecha del pico la caída debe ser aproximadamente exponencial; cualquier acumulación anómala en un valor concreto suele indicar redondeos en la escala de magnitud reportada por alguna agencia.',
		'como_funciona' => 'Conteo por intervalos de 0,1 unidades de magnitud sobre el catálogo filtrado. Se usan las magnitudes tal como las publica el USGS, sin homogeneizar entre escalas.',
	),

	'clases_magnitud'       => array(
		'descripcion'   => 'Reparto del catálogo entre las clases de magnitud que emplea el USGS: micro, menor, ligero, moderado, fuerte, mayor y grande. Traduce la escala numérica a categorías con significado práctico para la ciudadanía y la gestión del riesgo.',
		'analisis'      => 'La abrumadora mayoría de los sismos cae en las clases bajas, y eso es lo normal en cualquier región del planeta. Lo relevante para la gestión del riesgo son las clases altas: los sismos moderados ya pueden causar daños en construcciones vulnerables, y los fuertes o mayores son los que definen la amenaza real del territorio. Conviene recordar que en este catálogo las clases más bajas están sistemáticamente subrepresentadas, porque la red global no detecta todos los sismos pequeños de la región.',
		'como_funciona' => 'Clasificación directa por magnitud según los rangos del USGS. Cada sismo entra en una sola clase.',
	),

	'profundidad'           => array(
		'descripcion'   => 'Reparto de los sismos entre superficiales (menos de 70 km), intermedios (70 a 300 km) y profundos (más de 300 km). En una zona de subducción como la que enfrenta a Nariño, la profundidad no es un detalle técnico: describe la geometría de la placa que se hunde y determina cuánto se siente cada sismo en superficie.',
		'analisis'      => 'Los sismos superficiales son los que causan daños: la energía tiene menos camino que recorrer y llega con más fuerza a la superficie. Los intermedios, asociados a la placa de Nazca ya subducida bajo el continente, se sienten en un área más amplia pero con menor intensidad en el epicentro. La proporción entre unos y otros dibuja la zona de Wadati-Benioff, el plano inclinado que forma la placa al hundirse, y explica por qué dos sismos de la misma magnitud pueden tener consecuencias tan distintas.',
		'como_funciona' => 'Clasificación por la profundidad focal reportada por el USGS. Las profundidades fijadas por convenio (habitualmente 10 km cuando la solución no la resuelve) se cuentan tal cual, lo que puede acumular eventos artificialmente en ese valor.',
	),

	'magnitud_profundidad'  => array(
		'descripcion'   => 'Distribución estadística de las magnitudes dentro de cada rango de profundidad, en formato de caja y bigotes: la caja abarca la mitad central de los datos, la línea marca la mediana y los bigotes muestran el alcance del resto. Permite comparar de un vistazo si los sismos profundos tienden a ser mayores o menores que los superficiales.',
		'analisis'      => 'Las cajas suelen quedar a alturas parecidas, porque el umbral de detección del catálogo recorta por abajo todas las poblaciones por igual. Lo interesante está en los extremos superiores: los sismos de mayor magnitud del dominio se concentran en el rango superficial e intermedio, que es donde ocurre la ruptura de la interfaz de subducción. Una caja muy estrecha indica que ese rango de profundidad solo aporta eventos de tamaño similar; una muy alargada, que combina eventos pequeños y grandes.',
		'como_funciona' => 'Cada sismo aporta un punto a la caja de su rango de profundidad. Los cuartiles y la mediana se calculan sobre todas las magnitudes del grupo, sin agregación previa.',
	),

	'municipios_cercanos'   => array(
		'descripcion'   => 'Los quince municipios de Nariño que con más frecuencia resultan ser el más cercano al epicentro de un sismo del catálogo. Traduce una nube de coordenadas a una lectura territorial: no dice que el sismo ocurriera dentro del municipio, sino que ese fue el punto poblado de referencia más próximo.',
		'analisis'      => 'El ranking está dominado por los municipios del litoral Pacífico, simplemente porque la fuente sísmica principal de la región es la zona de subducción mar adentro. Eso no significa que los municipios andinos estén libres de amenaza: allí actúan fallas corticales que producen sismos más superficiales y, por tanto, potencialmente más dañinos en el entorno inmediato, aunque el catálogo global registre menos por su menor magnitud. Léase esta vista como un mapa de proximidad a la fuente, no como un ranking de riesgo.',
		'como_funciona' => 'Para cada evento se calcula la distancia ortodrómica (fórmula del haversine) entre el epicentro y el centroide de los 64 municipios, y se asigna el más próximo. Se muestran los quince con más asignaciones.',
	),

	'subregiones'           => array(
		'descripcion'   => 'Sismos agrupados por las subregiones de Nariño, según el municipio más cercano a cada epicentro. Ofrece la escala intermedia entre el detalle municipal y el total departamental, que es la que suele usarse para planear la gestión del riesgo.',
		'analisis'      => 'El peso del Pacífico Sur y de las subregiones litorales refleja la geometría de la fuente sísmica: la interfaz de subducción está frente a la costa. Las subregiones andinas aparecen con menos eventos en el catálogo global, pero su exposición es alta por densidad de población y por la vulnerabilidad de la construcción tradicional. La lectura correcta combina esta vista con la de profundidad: una subregión con pocos sismos pero superficiales puede tener más riesgo que otra con muchos pero profundos.',
		'como_funciona' => 'Cada sismo hereda la subregión del municipio más cercano a su epicentro. La agrupación es exhaustiva y excluyente: cada evento cuenta una sola vez.',
	),

	'mayores_sismos'        => array(
		'descripcion'   => 'Los doce sismos de mayor magnitud registrados en la ventana consultada, con su fecha y su localización. Es la vista de referencia histórica: pone nombre y fecha a los eventos que definen la memoria sísmica reciente de la región.',
		'analisis'      => 'Estos son los eventos que marcan la amenaza real del territorio y los que la población recuerda. Comparar el mayor sismo del catálogo con los umbrales de magnitud de la ficha estadística ayuda a dimensionar la amenaza: si un tamaño ya ocurrió en las últimas décadas, no es un escenario hipotético. Conviene recordar que la ventana del catálogo es corta en términos geológicos, y que la región ha producido en el pasado sismos considerablemente mayores que cualquiera de los listados aquí.',
		'como_funciona' => 'Ordenación descendente por magnitud sobre el catálogo filtrado, tomando los doce primeros. La etiqueta combina la fecha de origen (UTC) y la descripción del lugar publicada por el USGS.',
	),

	'recurrencia_historica' => array(
		'descripcion'   => 'Cada cuántos años, en promedio, ocurrió un sismo de cada magnitud o mayor dentro de la ventana consultada. Se obtiene invirtiendo la tasa anual observada en el catálogo del USGS. Es una lectura del pasado: cuenta lo que ya pasó y a qué ritmo, y sirve para dimensionar la amenaza de la región frente a la vida útil de una obra o de un plan.',
		'analisis'      => 'El intervalo medio no es un calendario. Que un tamaño de sismo tenga un intervalo medio de veinte años no significa que ocurra puntualmente cada veinte, ni que después de uno se abra un plazo de seguridad: los sismos no llevan cuenta del tiempo transcurrido. Lo que la cifra permite es comparar magnitudes entre sí y poner la amenaza en escala humana: una edificación pensada para cincuenta años de vida atravesará, en promedio, más de dos intervalos de veinte. Para decisiones de diseño estructural la referencia obligatoria no es esta gráfica, sino el Modelo Nacional de Amenaza Sísmica del SGC y la norma NSR-10.',
		'como_funciona' => 'Se cuentan los sismos por encima de cada umbral en la ventana del catálogo, se divide entre los años cubiertos para obtener la tasa anual observada y se invierte para expresarla como intervalo medio. Los umbrales por debajo de la magnitud de completitud no se publican, porque el catálogo no los registra de forma fiable. No se extrapola ninguna probabilidad hacia el futuro.',
	),
);
