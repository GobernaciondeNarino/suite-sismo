# Registro de cambios

## 2.8.0 — 2026-08-26

### Filtros de territorio y periodo en todos los componentes

Los atributos de consulta eran dispares: unos componentes aceptaban `dias`, otros `anios`, y cada capa —shortcode, REST, vistas— los interpretaba por su cuenta. Ahora hay un vocabulario único de cinco atributos que entiende cualquier componente que consulte el catálogo:

- `ambito` — territorio (`narino`, `regional`, `radio`, `colombia`).
- `dias` — ventana móvil de N días desde hoy.
- `anios` — ventana móvil de N años desde hoy.
- `anio` — **nuevo**: año de calendario completo.
- `mes` — **nuevo**: mes de calendario (1–12); sin `anio`, el del año en curso.

La precedencia es deliberada y está probada: una fecha de calendario manda sobre una ventana móvil, porque quien escribe `anio="2026"` pide ese año y no «los últimos N»; entre días y años gana `dias`. Los atributos descartados no se aplican **ni viajan en el HTML**, para que el `data-*` de la página no prometa un recorte que el servidor no hace.

Todo esto vive en una clase nueva, `SIS_Periodo`, que normaliza una sola vez y devuelve las tres formas que hacían falta: los filtros para recortar el catálogo, la etiqueta en lenguaje corriente y la clave de caché.

### Los textos ahora dicen qué se está mirando

Las descripciones y los análisis son textos fijos: el mismo párrafo servía para «Nariño en 15 días» y para «Colombia en treinta años». Al informar a la ciudadanía eso no puede pasar.

- Cada gráfica y cada bloque de texto encabeza con una línea calculada: **«Se registraron 32 sismos dentro del departamento de Nariño en los últimos 8 años.»**
- El análisis cuantitativo enmarca sus cifras en el periodo consultado y, si no hay con qué calcular, lo dice nombrando el filtro en vez de un «no hay datos» a secas.
- Con `ambito="narino"` el encabezado dice «dentro del departamento de Nariño» y nunca nombra ámbitos más amplios. Hay pruebas que recorren todos los eventos publicados y comprueban que ni uno cae fuera del recuadro del departamento, en cuatro periodos distintos.

### Un periodo sin sismos ya se explica

Dentro del recuadro estricto del departamento la red global registra unos pocos sismos al año, así que una ventana de quince días sale vacía la mayor parte del tiempo. Antes eso era un gráfico en blanco con un mensaje que mandaba a tocar la configuración.

Ahora la tarjeta publica qué significa el cero: que en esos días no hubo ningún sismo por encima del umbral de detección, que eso no significa ausencia de amenaza, y que la sismicidad que gobierna la amenaza del departamento se ve ampliando el ámbito a «regional».

### Valores por defecto del panel

- Pestaña **Gráficas**: `ambito="narino" dias="15"`.
- Pestaña **Visualizaciones históricas**: `ambito="narino" anios="8"`.

Los cinco shortcodes de cada tarjeta salen ya con ese filtro, y la pantalla avisa de que una ventana corta dentro del departamento puede quedarse sin datos. Se añade además una ayuda plegable con los cinco atributos, su significado y cómo se combinan.

### Correcciones

- **La caché de `/render` no distinguía periodos.** Su clave llevaba ámbito, años y magnitud, pero no los días, el año ni el mes: dos filtros distintos compartían respuesta y se servían datos de uno bajo el rótulo del otro.
- **Un filtro de calendario rellenaba la serie hasta hoy.** Pedir abril de 2016 devolvía 125 filas —ese mes y diez años de ceros— porque el relleno de meses vacíos, correcto para una ventana móvil, no sabía que el periodo estaba cerrado. Ahora la serie empieza y termina donde lo hace el filtro, y un año concreto cubre sus doce meses aunque enero esté vacío.
- Los nombres de ámbito salían en minúscula en los encabezados («en nariño y zona de subducción vecina»).

## 2.7.0 — 2026-08-26

### El planeta vuelve a la fotografía por satélite

La textura vectorial de 2.6.0 era ligera y correcta, pero la de un globo terráqueo fotográfico se ve mejor y es lo que pedía el plugin de referencia. Se adopta, con dos diferencias respecto a él:

- **La imagen viaja con el plugin.** `assets/img/planeta/tierra.jpg` es Blue Marble a 4096×2048 (1,4 MB). El plugin de referencia la pide a un CDN; aquí se sirve desde el propio sitio, así que ningún bloqueador puede dejar el planeta liso y el navegador de quien consulta la página no habla con terceros.
- **Hay una versión ligera.** `tierra-ligera.jpg` son 1600×800 y 239 KB. La elige el propio globo cuando la pantalla es pequeña, la memoria del equipo es escasa, la conexión es 2G/3G o el navegador pide ahorro de datos. A ese tamaño no se distingue de la grande y son 1,2 MB menos.

Ese criterio es deliberadamente distinto del que ajusta partículas y estrellas: allí manda el procesador; aquí, lo que está en juego es la descarga, así que mandan la pantalla y la red.

El atributo `textura` queda con tres valores: `foto` (por defecto), `mapa` —la Tierra dibujada en el navegador desde la costa mundial, 54 KB, para quien priorice el peso— y `no` —solo retícula—. `si` se sigue aceptando como sinónimo de `foto`.

Medido en Chromium: **2.298 KB en escritorio y 1.110 KB en móvil**, con el lienzo en 1,4 s. El mapa vectorial sigue siendo el respaldo si la fotografía no llega.

### Correcciones

- **La fotografía no llegaba a verse.** Los dos caminos de textura —foto y mapa vectorial— se lanzaban a la vez y el que terminaba último pisaba al otro. El mapa gana casi siempre la carrera porque es más pequeño, así que la foto se aplicaba y desaparecía. Ahora son excluyentes: el mapa solo entra si no hay foto o si la foto falla.
- La línea de tiempo deja de publicar la marca institucional: vive pegada al globo, compite con el dato y ya está en la cabecera del sitio. Quien la quiera la pide con `logo="si"`.

## 2.6.0 — 2026-08-26

### El planeta vuelve a tener geografía

En 2.5.0 se desactivó la fotografía por satélite del globo porque pesaba 1,4 MB y se pedía a un tercero. El planeta quedó legible pero sin continentes, y sin ellos cuesta situar dónde está temblando.

El plugin de referencia (`suite-oni`) resuelve esto con la misma imagen por CDN más tres capas adicionales —especular, relieve y luces nocturnas—, con un respaldo de seis elipses dibujadas a mano si todo falla. Aquí se hizo distinto:

- **La Tierra se dibuja en el navegador** a partir de la costa mundial que viaja con el plugin: `data/mundo_tierra.topo.json`, **54 KB** de TopoJSON, veinticinco veces menos que la fotografía y sin salir del sitio.
- El decodificador de TopoJSON son veinte líneas dentro de `globo.js` —arcos cuantizados y en incrementos, más un `transform`—, así que no entra ninguna librería nueva.
- Al dibujarla nosotros se eligen los colores: un océano en degradado y una tierra oscura y desaturada dejan que los epicentros, que son el dato, dominen la escena. Una fotografía a plena luz competiría con ellos.
- La textura llega **en dos tiempos**: el planeta con retícula ya está en pantalla y se sustituye en cuanto la costa termina de descargarse. Nunca hay un hueco esperando a una imagen.
- El casquete antártico se rellena hasta el polo: la cartografía se detiene en los 85,6° S y sin ese relleno el globo enseñaba una costura.

El atributo `textura` pasa a tener tres valores: `mapa` (por defecto), `foto` —la imagen por satélite, única descarga externa del plugin— y `no` —solo retícula—. El antiguo `textura="si"` se sigue aceptando con el sentido de `foto`, para no romper las páginas ya publicadas.

Medido en Chromium con servidor concurrente: el lienzo sigue apareciendo en **1,05 s** y generar la textura cuesta **19 ms**.

## 2.5.0 — 2026-08-26

### Las librerías dejan de venir de una CDN

Un reporte desde el sitio en producción mostró la consola bloqueando D3plus: `Failed to find a valid digest in the 'integrity' attribute … The resource has been blocked`, junto a un `net::ERR_BLOCKED_BY_CLIENT`. Las huellas SRI declaradas eran correctas —se verificaron contra jsDelivr byte a byte—, así que lo que llegó al navegador no era lo que el CDN sirve: un bloqueador, una extensión de privacidad o un proxy se interpuso.

Ese es el problema de fondo de una huella SRI sobre un tercero: hace exactamente lo que debe —rechazar lo que no coincide— y el resultado es una página sin gráficos. En un portal público no es aceptable.

- **D3plus, Leaflet y three.js se sirven ahora desde `assets/vendor/`**, dentro del plugin. No hay CDN, no hay huella que pueda fallar y el navegador de quien consulta el sitio no hace peticiones a servidores ajenos.
- Se retiran las constantes `SRI`, `SRI_CSS` y `THREE_SRI` y los filtros que inyectaban `integrity`. La prueba de seguridad comprueba ahora algo más fuerte: que **ningún componente carga recursos de terceros**.
- `assets/vendor/LEEME.md` documenta origen, versión, licencia y procedimiento de actualización de cada librería.

### El globo pasa de 2,7 MB a 0,7 MB

- **three.js se cargaba sin minificar**: 1.243 KB. La build `three.module.min.js` son 655 KB y es idéntica en funcionamiento. Casi la mitad del peso del globo era eso.
- **La textura fotográfica del planeta (1,4 MB) deja de cargarse por defecto.** Es decorativa: el globo dibuja su propio planeta con retícula y los sismos se leen igual sobre él. Quien la quiera la pide con `textura="si"` y asume la descarga. Es el único recurso externo que el plugin puede llegar a pedir.
- **Cartografía simplificada para el globo**: a la escala del globo el detalle de la cartografía municipal completa no llega ni a un píxel. Las versiones `*_globo.geojson` pesan 46 KB y 30 KB frente a 346 KB y 73 KB, sin diferencia visible. El mapa Leaflet sigue usando la original, donde el detalle sí importa.
- **Una petición REST menos**: el globo y la línea de tiempo pedían el mismo conjunto a la vez. Ahora, si hay un globo en la página, la línea de tiempo espera a que lo entregue; si no llega —globo caído o sin WebGL— lo pide igualmente.

Medido en Chromium con el globo y la línea de tiempo en la misma página: **917 KB en 11 peticiones, ninguna a terceros, con el lienzo dibujado en 1,1 s**.

### Correcciones

- Nueva comprobación de que el importmap emite rutas resolubles. Un import map descarta en silencio cualquier valor que no sea una URL absoluta o empiece por `/`, `./` o `../`, y el globo se queda sin three.js con un error críptico («blocked by a null value»).

## 2.4.0 — 2026-08-26

### Motor de gráficos: migración a D3plus v4

La v3 dejaba `window.d3plus` vacío en el navegador y ningún gráfico llegaba a pintarse; por eso el plugin seguía en la v2. La v4 publica un bundle UMD que sí puebla el global, verificado en Chromium con los nueve tipos que usa el motor.

- D3plus pasa de la 2.0.0 a la **4.3.0**, con la versión fijada y huella SRI nueva.
- El hidratador llama a `destroy()` sobre la instancia anterior antes de redibujar. Cada cambio de tipo o de filtro dejaba vivos el `ResizeObserver` y los escuchadores del gráfico que ya no existía; en una página con varios gráficos y filtros eso se acumulaba visita tras visita.
- **`BoxWhisker` dejó de caer al SVG de reserva.** En la v4 hereda de `Plot` y se configura con `x`/`y`; el `value()` de la v2 ya no existe y lanzaba en cada render.
- La v4 estampa el nombre de la serie dentro de cada forma: con una sola serie eso repetía el mismo texto en todas las barras y tapaba el dato. Se suprime en los gráficos cartesianos y se conserva en pastel, dona y treemap, donde la etiqueta es la información principal.

### Cinco gráficas nuevas y dos tipos nuevos

- **Dispersión magnitud–profundidad** (`dispersion_mag_prof`, tipo `plot`): un punto por sismo, sin agregar, coloreado por rango de profundidad. Deja ver las bandas de la placa de Nazca hundiéndose bajo Sudamérica.
- **Calendario sísmico** (`calendario_sismico`, tipo `matrix`): un año por fila, un mes por columna. Las rachas aparecen como manchas y la ausencia de patrón por columnas responde, con datos, a la idea de que hay «meses malos».
- **Energía acumulada** (`energia_acumulada`): la curva de Benioff. Sube a escalones porque cada unidad de magnitud multiplica la energía por unas treinta y dos veces.
- **Sismos por hora del día** (`hora_del_dia`): reparto entre las 24 franjas en hora de Colombia. Responde a la creencia de que los sismos ocurren de madrugada.
- **Días entre un sismo y el siguiente** (`intervalos`): histograma de tramos desiguales que muestra que la sismicidad no llega a intervalos regulares.
- **Sismos con reportes de la población** (`sismos_sentidos`): registrados frente a sentidos, año a año.

El motor sube de 9 a 11 tipos de gráfico y de 15 a 21 vistas. Cada vista nueva trae sus tres textos —descripción, interpretación y cómo se calcula—, igual que las anteriores.

### Panel «Elementos»: pestañas y una tarjeta por gráfica

- El catálogo se reparte en cuatro pestañas: **Gráficas**, **Visualizaciones históricas**, **Globo y mapa** e **Información**. Cada componente declara a cuál pertenece y aparece en una sola.
- Cada gráfica tiene su tarjeta con los **cinco shortcodes listos para copiar**: la gráfica, su descripción, su análisis cualitativo, sus cifras y la versión con todo junto. Antes había que recordar la sintaxis y el identificador de la vista.
- La tarjeta muestra también el tipo por defecto y los tipos alternativos de esa gráfica.
- Nueva hoja `assets/css/admin.css`, que solo se carga en las pantallas del plugin. Las pestañas de WordPress flotan y en un móvil empujaban el ancho de la página: ahora se desplazan dentro de su caja, y lo mismo hace la tabla de componentes.
- Nuevo `tests/test-panel.php` (54 comprobaciones): las cuatro pestañas se pintan, cada componente cae en una sola, hay una tarjeta por vista —ni más ni menos— y toda vista publica sus cinco shortcodes.

### Correcciones

- Tres textos de gráficos seguían remitiendo al módulo de pronóstico retirado en 2.0.0. Reescritos.

## 2.3.0 — 2026-08-26

### Las series ya no se detienen en el último sismo

Una gráfica que termina en el último mes con actividad hace creer que los datos se detuvieron ahí. Con el catálogo regional en calma desde mediados de agosto, la serie mensual acababa en julio y la anual podía acabar en un año anterior, aunque el USGS sí tuviera datos posteriores.

- El conteo mensual, la serie anual y la energía mensual se extienden **hasta el mes y el año en curso**. Un mes en cero es información —«no tembló»—; un mes que falta es ambiguo.
- La consulta al FDSN pasa a pedir los eventos **del más reciente hacia atrás**. Si alguna vez llegara al tope del servicio, lo que se pierde es la cola antigua y no los sismos de esta semana. El catálogo se reordena al normalizar, así que nada más cambia.
- Semilla local regenerada contra el servicio: 664 eventos de magnitud 4,0+ desde 2005 hasta la fecha de publicación. Es el respaldo que se usa mientras el cron no ha corrido.

### Histórico en dos lecturas

- Nuevo `[sismos_historico]`: publica juntas las dos gráficas que responden a «¿cómo ha sido esto a lo largo del tiempo?» —barras de sismos por año y línea mensual con tendencia—, lado a lado en escritorio y apiladas en móvil.
- Nueva vista `historico_mensual`: la serie mensual completa con su **media móvil centrada de doce meses**. La serie cruda de un catálogo sísmico es muy ruidosa —un sismo principal arrastra decenas de réplicas y dispara un mes entero—; la media móvil deja ver el nivel de fondo, que es lo que cambia despacio. En los extremos se promedia solo la parte disponible, sin extrapolar meses que el catálogo no cubre.

### El globo ya muestra el mundo

Pulsar «Global» solo alejaba la cámara: el planeta quedaba con los sismos del ámbito publicado y el resto vacío, como si en el mundo no temblara.

- Nuevo ámbito `mundo`, servido del **feed de resumen del USGS** (magnitud 2,5+ de la última semana). No se sincroniza contra el catálogo histórico —serían millones de eventos— y por eso queda fuera de la lista de ámbitos sincronizables.
- El globo carga ese conjunto **solo al pulsar «Global»**, y lo guarda: una página que nunca usa esa vista no paga la descarga, y volver a «Zona sísmica» no vuelve a pedir nada.
- La línea de tiempo sigue al globo cuando cambia de conjunto y lo dice en su título: recorrer sismos de Nariño mientras el globo dibuja los del planeta sería mentir sobre lo que se está viendo.

### Correcciones

- Tres textos y tres comentarios seguían nombrando el módulo de pronóstico retirado en 2.0.0. La salvaguarda `tests/test-sin-pronostico.php` no los veía porque solo buscaba la cifra («a 6 meses») y no la palabra escrita («a seis meses»); ahora cubre ambas y detecta cualquier pronóstico presentado como propio del plugin.

## 2.2.0 — 2026-08-24

### Globo 3D de sismos recientes

Recreación del globo del plugin de referencia (`suite-oni`) con datos sísmicos y una codificación propia del dominio.

- Nuevo `[sismos_globo]`: globo terráqueo WebGL con los últimos sismos (50 por defecto, 5–200). Cada epicentro dibuja una **línea radial hacia afuera** cuya altura codifica la magnitud y cuyo color sigue la rampa de calor de la suite (3,0 azul → 4,0 verde → 5,0 ámbar → 6,0 naranja → 7,0+ rojo), y una **línea punteada hacia adentro** proporcional a la profundidad del hipocentro.
- **Mapa de calor sobre la esfera**: un campo de partículas aditivas alrededor de cada epicentro, con intensidad proporcional a la magnitud; donde los sismos se agrupan, los campos se suman y forman la mancha caliente.
- Vistas de cámara —Global, Zona sísmica y Nariño— con encuadre calculado a partir del centroide de los datos, no de una posición fija: si la sismicidad se desplaza, la cámara la sigue.
- Capa de profundidad en **modo radiografía** (el planeta se vuelve traslúcido para ver las líneas que entran) y contorno municipal de Nariño sobre la esfera.
- Selección por clic o teclado sobre el epicentro, con anillo pulsante, cintillo `aria-live` y vuelo suave de cámara.
- Nuevo `[sismos_timeline]`: barra de recorrido con botones de sismo anterior y siguiente, reproducción a tres velocidades (lento, normal, rápido), deslizador y una tira con una marca por sismo coloreada por magnitud. Se sincroniza en ambos sentidos con el globo de la misma página mediante eventos `sis:sismo` y `sis:sismos-cargados`. También se publica junto al globo con `timeline="si"`.
- La barra lleva la marca institucional de la Secretaría TIC, como en el plugin de referencia; se retira sola si el archivo no está y puede desactivarse con `logo="no"`.
- El globo consume la ruta REST existente `/eventos`; no incrusta datos en el HTML ni añade rutas nuevas.
- Cartografía de Nariño (departamento y municipios) servida en GeoJSON desde `data/`, tomada del plugin de referencia.

### Responsive del globo y de la línea de tiempo

Auditoría en Chromium a 320, 360, 414, 768, 1024 y 1440 px, con emulación táctil por debajo de 768.

- Los ocho controles del globo ocupaban tres filas en un móvil y tapaban un tercio de la escena: pasan a una sola fila que se desplaza en horizontal.
- La leyenda de magnitud se solapaba con el cintillo del último sismo; ahora se sitúa bajo la barra de controles.
- La tira de marcas de la línea de tiempo (una por sismo) ensanchaba la página entera hasta 510 px en una pantalla de 320: se convierte en una región desplazable y la marca activa se centra sola.
- Cada marca es ahora un área de toque de 44 px en dispositivos táctiles, con una barra interior que sigue codificando la magnitud por color y altura; el deslizador también mide 44 px.
- El encuadre de la cámara tiene en cuenta la forma del lienzo: el campo de visión vertical es fijo, así que en un móvil vertical manda el ancho. Sin ese ajuste el mismo conjunto de sismos se veía bien en un escritorio y diminuto en un teléfono. El encuadre se recalcula al girar el dispositivo, sin mover la cámara por su cuenta.
- La rotación automática deja de estar activa por defecto y se detiene al pedir una vista o al elegir un sismo: giraba y perdía de vista la zona que acababa de encuadrar.

### Rendimiento y resiliencia del globo

- Calidad automática: en equipos modestos o pantallas pequeñas se reduce el número de partículas, se limita el `devicePixelRatio` y se desactivan las estrellas.
- El bucle de animación se detiene cuando el globo sale de la ventana (`IntersectionObserver`) y se redimensiona con `ResizeObserver`.
- Si la textura del planeta no carga, se dibuja un planeta propio con retícula. Si el navegador no soporta WebGL, se publica un aviso y el resto de la página sigue funcionando.

### Seguridad

- Three.js se carga por CDN con la versión fijada y huella SRI. Como un módulo ES no admite `integrity` en su etiqueta `<script>`, la huella viaja en `<link rel="modulepreload">` impreso junto al `importmap`, que se emite una sola vez por página.
- Los atributos del globo se acotan en servidor: límite a 5–200, `alto` solo si es una medida CSS válida, ámbito contra la lista de regiones y calidad contra lista blanca.
- `tests/test-seguridad.php` cubre las huellas de los módulos, el importmap y el filtro que marca el globo como módulo; `tests/test-render.php` cubre el HTML publicado por ambos componentes nuevos.

### Documentación

- `README.md` y la pantalla **Elementos** del panel documentan los dos shortcodes nuevos, sus atributos y su ejemplo.

## 2.1.0 — 2026-08-20

### Responsive

Auditoría en Chromium a 320, 360, 414, 768, 1024 y 1440 px. Antes, la página entera se desplazaba en horizontal en móviles: una tabla de cuatro o cinco columnas empujaba el ancho del documento hasta 488 px en una pantalla de 320.

- Las tablas de datos se envuelven en una región desplazable (`role="region"`, enfocable por teclado) en la ficha estadística, el panel de fuentes y el modal de datos del gráfico.
- El selector de los filtros ya no impone 260 px fijos.
- La altura del gráfico viaja como variable CSS y se acota al 62 % del alto visible en móvil, en vez de fijarse en línea.
- En dispositivos táctiles los controles miden 44 px con 8 px de separación, y los enlaces de las listas de fuentes 32 px.
- Nombres de lugar y URL largas ya pueden partirse; el panel del modal usa `dvh` para que la barra del navegador móvil no lo recorte.

### Seguridad

- Los archivos de prueba dejan de ser ejecutables por URL: exigen línea de comandos y responden 403 a una petición web.
- Las celdas del CSV que empiezan por `=`, `+`, `-`, `@`, tabulador o retorno de carro se neutralizan (inyección de fórmulas).
- Las URL que llegan del feed del USGS se validan contra `^https?://` antes de convertirse en enlaces.
- `/estado`, `/estadistica` y `/render` se cachean con clave derivada de la firma del catálogo, y el grupo de caché se poda a 200 entradas: dejan de servir como amplificador de carga sin permitir inflar la tabla variando parámetros.
- D3plus y Leaflet se cargan con `integrity` y `crossorigin` (SRI verificado sobre los archivos servidos).
- El saneador de CSS elimina además los esquemas `javascript:`, `vbscript:` y `data:`.
- El panel público de fuentes ya no publica el detalle técnico de los errores.
- Cada directorio suma su `index.php` silenciador y la lista blanca anti-SSRF incorpora los hosts de los servicios del SGC que ya usa el mapa.
- Nuevo `tests/test-seguridad.php` y nuevo `SECURITY.md` con la superficie de ataque y la guía de operación.

### Herramientas

- Skill `ui-ux-pro-max` instalada en `.claude/skills/` y registrada en `skills-lock.json`, tras revisar que sus scripts solo hacen búsqueda local sobre JSON.
- Flujos de trabajo `claude.yml` y `security-review.yml`, con las acciones fijadas por SHA de commit.

## 2.0.0 — 2026-08-19

### Retirado: el módulo de pronóstico

La versión 1.0.0 incluía un pronóstico probabilístico de sismicidad a seis meses (fondo de Gutenberg-Richter + estado reciente amortiguado + réplicas de Omori-Utsu). **Se retira por completo.**

El motivo no es técnico sino institucional: la predicción determinística de sismos no es posible, y el pronóstico probabilístico —incluido el de réplicas— es competencia del Servicio Geológico Colombiano, que hoy no emite ese producto. Una entidad territorial puede informar y educar citando la fuente oficial; no puede erigirse en fuente de estimaciones propias. El razonamiento completo, con sus fundamentos y sus umbrales de revisión, está en [`docs/marco-comunicacion-riesgo.md`](docs/marco-comunicacion-riesgo.md).

**Eliminado**

- Clase `SIS_Forecast` y `assets/js/pronostico.js`.
- Vistas `pronostico_mensual`, `pronostico_banda`, `pronostico_umbrales` y `periodo_retorno`.
- Shortcodes `[sismos_pronostico]` y `[sismos_prediccion_dato]`; modo `prediccion` de `[sismos_analisis]`.
- Rutas REST `/pronostico` y `/abierto/pronostico`.
- Pantalla de administración «Modelo de pronóstico», su opción `sis_modelo` y la acción de recálculo.
- Métodos de probabilidad hacia adelante en `SIS_Estadistica` (`probabilidad_poisson`, `intervalo_poisson`, `cuantil_chi2`, `cuantil_normal`, `cdf_normal`).

**Reencuadrado**

- El periodo de retorno pasa a ser **recurrencia observada** (`recurrencia_historica`): cuántos sismos de cada magnitud hubo y cada cuántos años ocurrió uno en promedio, siempre con la advertencia de que es un promedio del pasado y no un calendario.
- La ficha estadística publica observados, tasa anual observada e intervalo medio, en lugar de probabilidades a un año.
- Todos los textos calculados se enuncian en pasado y toda vista incorpora el aviso de alcance.

### Añadido: amenaza y preparación

- Clase `SIS_Amenaza`: descargo institucional, glosario de los cuatro conceptos (alerta temprana, pronóstico, probabilidad de largo plazo y predicción), directorio de fuentes oficiales, contexto geológico del departamento, referencia normativa NSR-10 y guía post-sismo.
- Shortcodes `[sismos_amenaza]`, `[sismos_glosario]`, `[sismos_preparacion]`, `[sismos_replicas]`, `[sismos_desinformacion]` y `[sismos_fuentes_oficiales]`, renderizados en PHP (HTML cacheable, accesible sin JavaScript).
- Contenido de preparación ciudadana en `includes/data/textos-preparacion.php`: antes, durante, después, kit de emergencia y organización comunitaria.
- Ruta REST `/amenaza` y recurso abierto `/abierto/recurrencia`.
- Capa oficial de amenaza del SGC superpuesta en `[sismos_mapa]` (WMS del Modelo Nacional de Amenaza Sísmica, cinco periodos de retorno, control de capas y atribución). Atributos nuevos `amenaza` y `periodo`.
- Pantalla de administración «Amenaza y normativa» con la opción `sis_amenaza`, editable sin tocar código y con aviso de verificación contra el texto de la NSR-10.

### Salvaguarda

- Nuevo `tests/test-sin-pronostico.php`: recorre el código publicado y falla si reaparecen identificadores del módulo retirado o frases que anuncien sismos futuros con cifras.
- `tests/test-analisis.php` y `tests/test-vistas.php` comprueban que ninguna vista publica meses futuros, que todas llevan el aviso de alcance y que la estadística no expone probabilidad hacia adelante.

### Migración

Al actualizar, `SIS_Activator::migrar_si_necesario()` siembra `sis_amenaza` y borra `sis_modelo`. Las páginas que usen los shortcodes retirados dejarán de mostrarlos: sustitúyalos por `[sismos_amenaza]`, `[sismos_glosario]` o `[sismos_estadistica]` según el caso.

## 1.0.0 — 2026-08-19

Versión inicial: catálogo USGS (FDSN Event + feeds GeoJSON), motor de gráficos D3plus de tres capas, mapa de epicentros, estadística sismológica, API de datos abiertos, panel de administración y semilla local de resiliencia.
