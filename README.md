# Sismos Nariño — Análisis Estadístico y Pronóstico Sísmico

Plugin de WordPress que publica el **análisis estadístico de la sismicidad** de Nariño y de la zona de subducción que gobierna su amenaza sísmica, con **datos abiertos del USGS**, gráficos interactivos D3plus y un **pronóstico probabilístico a 6 meses que se recalcula con cada actualización del catálogo**.

> **Gobernación de Nariño · Secretaría TIC, Innovación y Gobierno Abierto**
> Arquitectura hermana de [`suite-oni`](https://github.com/GobernaciondeNarino/suite-oni) (Monitor Ambiental y Fenómeno El Niño): mismo motor de gráficos de 3 capas, misma filosofía de front minimalista, misma disciplina de datos abiertos y resiliencia.

---

## Instalación (Plesk / WordPress)

1. Copie la carpeta del plugin a `wp-content/plugins/sismos-narino/`.
2. Actívelo desde **Plugins → Sismos Nariño**.
3. Al activar se crean las tablas `wp_sis_cache` y `wp_sis_audit`, se siembra la configuración por defecto y se agendan dos crones: catálogo cada 12 h y feed reciente cada hora.
4. Vaya a **Sismos Nariño → Resumen** y pulse **Sincronizar catálogo ahora** para traer el histórico del USGS.

Sin proceso de build: D3plus y Leaflet se cargan por CDN. Requiere WordPress 5.8+ y PHP 7.4+.

> **¿Dónde copio los shortcodes?** En **Sismos Nariño → Elementos** está el catálogo completo de componentes con su descripción, atributos y botón **Copiar**, más la lista de vistas disponibles para `[sismos_grafico]`.

---

## Fuentes de datos

| Fuente | Uso | Frecuencia |
|---|---|---|
| [USGS FDSN Event Web Service](https://earthquake.usgs.gov/fdsnws/event/1/) `…/query?format=geojson` | Motor principal. Catálogo histórico en GeoJSON nativo, sin clave de API, recortado por recuadro o radio | Cron cada 12 h |
| [Feeds GeoJSON de resumen](https://earthquake.usgs.gov/earthquakes/feed/v1.0/summary/) `all_day.geojson` y variantes | Sismicidad reciente. CORS abierto: lo consume el servidor por cron **y** el navegador directamente | Cron cada hora · navegador cada 2 min (el feed se regenera cada ~1 min) |
| DANE / DIVIPOLA | Centroides de los 64 municipios, para asignar cada epicentro al municipio más cercano | Estático |

Los datos del USGS son de **dominio público**; la elaboración de la Gobernación se publica bajo **CC BY 4.0**.

### Ámbitos espaciales

El catálogo global es completo en Colombia a partir de M≈4,5, así que el recuadro estricto del departamento aporta pocas decenas de eventos. Por eso el plugin declara varios ámbitos y usa el **regional** por defecto para estadística y pronóstico:

| `ambito` | Cobertura | Para qué sirve |
|---|---|---|
| `narino` | lat 0,35–2,70 · lon −79,10 a −76,85 | Lectura territorial estricta del departamento |
| `regional` *(por defecto)* | lat −1,50–4,00 · lon −81,50 a −75,50 | Suroccidente de Colombia y norte de Ecuador, incluida la fosa Nazca–Sudamérica: es el dominio que gobierna la amenaza |
| `radio` | 300 km alrededor de Pasto | Sismicidad que puede sentirse en la capital |
| `colombia` | lat −4,50–13,50 · lon −82,00 a −66,00 | Referencia comparativa nacional |

---

## Shortcodes

| Shortcode | Qué publica | Atributos |
|---|---|---|
| `[sismos_estado]` | Semáforo de actividad: último sismo, conteos 24 h / 7 d / 30 d / 1 año, municipio más cercano | `ambito`, `dias`, `min_mag`, `compacto`, `vivo` |
| `[sismos_ultimos]` | Lista de los últimos sismos, con destello al llegar uno nuevo | `ambito`, `limite`, `min_mag`, `vivo` |
| `[sismos_mapa]` | Mapa Leaflet de epicentros (tamaño = magnitud, color = profundidad) + centroides municipales | `ambito`, `anios`, `dias`, `min_mag`, `alto`, `municipios`, `zoom` |
| `[sismos_grafico]` | **Tarjeta de gráfico D3plus con barra de herramientas** (Cómo funciona · Detalle · Compartir · Datos · Imagen PNG · Descarga JSON · Cambiar tipo en vivo) | `view`, `type`, `ambito`, `anios`, `min_mag`, `theme`, `actions`, `legend`, `legend_style`, `legend_pos`, `toolbar`, `alto`, `grupo`, `analisis`, `titulo` |
| `[sismos_pronostico]` | **Ficha del pronóstico a 6 meses**: esperados con banda, evolución mensual, probabilidad por umbral, magnitud máxima esperada y cambio respecto al pronóstico anterior | `ambito`, `modo`, `grafico` |
| `[sismos_estadistica]` | Ficha estadística: Mc, valor b ± error, tasa anual, energía liberada y periodos de retorno | `ambito`, `anios`, `dias`, `min_mag` |
| `[sismos_datos]` | Botones de datos abiertos (JSON / CSV / Ver API) | `recurso`, `ambito`, `anios`, `dias`, `min_mag`, `texto` |
| `[sismos_estado_api]` | Panel público de salud de las fuentes | — |
| `[sismos_descripcion]` · `[sismos_explicacion]` · `[sismos_analisis_cualitativo]` · `[sismos_analisis_cuantitativo]` · `[sismos_prediccion_dato]` · `[sismos_analisis]` | Piezas de texto de una vista, para maquetar el texto aparte de la gráfica | `view`, `ambito`, `anios`, `titulo`, `grupo` |
| `[sismos_filtro]` · `[sismos_panel]` | Filtros y panel de detalle sincronizados con un gráfico por `grupo` | `grupo`, `control`, `etiqueta` |

**Ejemplos**

```
[sismos_estado ambito="narino"]
[sismos_ultimos limite="8"]
[sismos_mapa ambito="regional" anios="5" min_mag="4.5" alto="520px"]

[sismos_grafico view="sismos_mensuales" type="bar"]
[sismos_grafico view="frecuencia_magnitud" type="line" alto="460px"]
[sismos_grafico view="pronostico_mensual" type="line" theme="oscuro"]

[sismos_pronostico ambito="regional" modo="completo"]
[sismos_estadistica ambito="regional" anios="20"]
[sismos_datos recurso="pronostico" texto="Descargue el pronóstico a seis meses"]
```

### Componentes composables (enlazados por `grupo`)

```
[sismos_filtro grupo="sis" control="vista"] [sismos_filtro grupo="sis" control="tipo"]
[sismos_filtro grupo="sis" control="ambito"] [sismos_filtro grupo="sis" control="anios"]
[sismos_grafico grupo="sis" toolbar="no"]
[sismos_panel grupo="sis"]
```

---

## Motor de gráficos D3plus (3 capas)

```
CAPA 1 · PHP        El shortcode emite solo un <figure> con data-* (HTML cacheable, sin datos dentro)
                            ↓ data-view, data-type, data-ambito…
CAPA 2 · grafico.js Hidrata: pide /wp-json/sismos/v1/render?view=…&type=… y cablea toolbar y modales
                            ↓ SISRenderer.render(nodo, payload, opts)
CAPA 3 · renderer.js Elige la clase D3plus, configura ejes, tooltip, leyenda y color, y dibuja el SVG
```

La capa 3 es agnóstica al dominio: solo entiende de dimensiones y medidas. Si D3plus no carga, cae a un SVG propio para que la página nunca se quede sin gráfico.

### Vistas disponibles (`view`)

**Actividad en el tiempo** — `sismos_mensuales`, `sismos_anuales`, `magnitud_mensual`, `energia_mensual`, `acumulado`
**Distribuciones estadísticas** — `frecuencia_magnitud` (Gutenberg-Richter), `distribucion_magnitud`, `clases_magnitud`, `profundidad`, `magnitud_profundidad`
**Lectura territorial** — `municipios_cercanos`, `subregiones`, `mayores_sismos`
**Pronóstico a 6 meses** — `pronostico_mensual`, `pronostico_banda`, `pronostico_umbrales`, `periodo_retorno`

**Tipos** (`type`): `bar`, `stacked_bar`, `line`, `area`, `stacked_area`, `pie`, `donut`, `treemap`, `box_whisker` — restringidos automáticamente a los compatibles con la categoría de cada vista.

Cada vista trae, calculados sobre los datos vigentes: descripción, interpretación cualitativa, **cifras clave** y **párrafo predictivo**. El texto cambia cuando cambian los datos, así que nunca queda desactualizado.

---

## Pronóstico a 6 meses

No se predicen sismos concretos —nadie puede—, sino la **tasa esperada de sismicidad** y la **probabilidad de superar umbrales de magnitud**, con su banda de incertidumbre. El modelo suma tres componentes:

1. **Fondo climatológico** — ley de Gutenberg-Richter ajustada por máxima verosimilitud (Aki, 1965) sobre toda la ventana del catálogo, con Mc por máxima curvatura (Wiemer & Wyss, 2000) e incertidumbre de b por Shi & Bolt (1982).
2. **Estado reciente** — suavizado exponencial con tendencia amortiguada (Holt amortiguado) sobre los conteos mensuales. Su peso decae con el horizonte (w₀·φ^h): manda en el mes 1 y se disuelve hacia el mes 6, donde vuelve a mandar la climatología.
3. **Réplicas** — ley de Omori-Utsu modificada con productividad tipo Reasenberg & Jones (1989), activa solo si hubo un sismo detonante en los últimos 365 días. Decae con el tiempo, así que el pronóstico baja solo a medida que la secuencia se apaga.

De ahí se derivan la probabilidad por umbral (Poisson sobre Gutenberg-Richter **truncada** en M 8,8, la mayor ruptura creíble del dominio), el periodo de retorno, la magnitud máxima esperada (moda, mediana y percentil 90) y la energía esperada.

**Por qué varía con cada actualización.** La caché del pronóstico se indexa por una **firma del catálogo** (nº de eventos + id y hora del último sismo). En cuanto llega un sismo nuevo la firma cambia, la caché falla y el pronóstico se recalcula: cambia el estado reciente, cambia el ajuste de Gutenberg-Richter y, tras un evento importante, entra la componente de réplicas. Cada resultado guarda además el anterior para publicar **cuánto y en qué sentido cambió**.

Todo es determinista y auditable: el mismo catálogo produce siempre el mismo pronóstico, y los datos de entrada se publican en la API abierta. Lógica en `includes/analysis/class-sis-forecast.php`, con pruebas en `tests/`.

> **Alcance.** Estas cifras describen la amenaza y sirven para planear; **no son un aviso de emergencia**. La información oficial la emiten el Servicio Geológico Colombiano (SGC) y la UNGRD.

---

## Datos abiertos (API pública)

```
GET /wp-json/sismos/v1/eventos?ambito=narino&dias=30&formato=json
GET /wp-json/sismos/v1/estadistica?ambito=regional&anios=20
GET /wp-json/sismos/v1/pronostico?ambito=regional
GET /wp-json/sismos/v1/render?view=frecuencia_magnitud&type=line
GET /wp-json/sismos/v1/vistas
GET /wp-json/sismos/v1/ambitos
GET /wp-json/sismos/v1/municipios
GET /wp-json/sismos/v1/estado-apis

GET /wp-json/sismos/v1/abierto/eventos?ambito=narino&anios=10&formato=csv
GET /wp-json/sismos/v1/abierto/estadistica?ambito=regional&formato=csv
GET /wp-json/sismos/v1/abierto/pronostico?ambito=regional&formato=json
```

Todas son públicas y de solo lectura, con rate-limiting por IP (120 peticiones/minuto) y la atribución al USGS incorporada en cada respuesta. El CSV sale con BOM UTF-8 para que Excel lea bien las tildes.

---

## Apariencia

Por defecto todo es **transparente y sin bordes ni sombras**, para fundirse con el tema del sitio. Ajuste el aspecto global en **Sismos Nariño → Apariencia**, o por shortcode:

```
[sismos_estado fondo="#ffffff" borde="1px" radio="8px" sombra="0 1px 4px rgba(0,0,0,.08)"]
[sismos_mapa acento="#003087" ancho="720px"]
```

Atributos de apariencia: `fondo`, `texto`, `acento`, `acento2`, `tecnico`, `borde`, `sombra`, `ancho`, `espaciado`, `radio`.
Los colores del dominio (escala de magnitud y de profundidad) **no** son configurables: codifican información, no estilo.

---

## Arquitectura

```
sismos-narino.php              Arranque, constantes y ciclo de vida
includes/
  class-sis-plugin.php         Orquestador singleton
  class-sis-activator.php      Tablas, cron, opciones y migraciones
  class-sis-cache.php          Caché de dos niveles (transient + tabla durable)
  class-sis-security.php       Sanitización, lista blanca de servidores, rate-limit, cifrado
  class-sis-estilos.php        Variables CSS de apariencia
  class-sis-rest.php           API REST interna y de datos abiertos
  data/
    class-sis-municipios.php   64 municipios (DIVIPOLA + centroides) y distancias
    class-sis-regiones.php     Ámbitos espaciales y escalas del dominio
    class-sis-views.php        Catálogo de vistas del motor de gráficos
    textos-graficos.php        Textos largos por vista
    textos-elementos.php       Catálogo de shortcodes del panel
  analysis/
    class-sis-catalogo.php     Normalización del GeoJSON, filtros y agregaciones
    class-sis-estadistica.php  Mc, valor b, Gutenberg-Richter, Poisson, utilidades numéricas
    class-sis-forecast.php     Pronóstico a 6 meses (fondo + estado reciente + réplicas)
    class-sis-texto.php        Narrativa calculada a partir de los datos
  sync/
    class-sis-sync.php         Orquestador de cron, HTTP resiliente y auditoría
    class-sis-sync-usgs.php    Conector FDSN Event
    class-sis-sync-feed.php    Conector de feeds GeoJSON
  shortcodes/                  Registro y render de los shortcodes
  admin/                       Panel: Resumen, Fuentes, Modelo, Apariencia, Elementos
assets/css                     estilos.css (minimalista) · grafico.css (tarjeta de gráfico)
assets/js                      sis-core · renderer · grafico · grupo · composable ·
                               estado · ultimos · mapa · pronostico · estadistica ·
                               datos · estado-api · admin
data/                          Semilla local del catálogo (resiliencia)
tests/                         Pruebas CLI sin WordPress
```

### Resiliencia

El catálogo se lee en cascada: **caché viva → caché durable expirada → semilla JSON incluida en `data/`**. Si el USGS no responde o el cron aún no ha corrido, la página sigue publicando datos y lo dice (`origen` en la respuesta). Una sincronización que devuelve menos eventos de los ya cacheados no reemplaza al catálogo anterior.

### Seguridad

- Todo endpoint es público de solo lectura, con rate-limiting por IP.
- Las URL configurables se validan contra una **lista blanca de servidores** (USGS, IRIS, SGC) y se exige HTTPS: no hay forma de apuntar el plugin a un host arbitrario (anti-SSRF).
- Los parámetros geográficos de la consulta salen del catálogo de ámbitos, nunca de la entrada del usuario.
- El panel exige `manage_options` y nonce en cada escritura; toda salida se escapa y los valores CSS se sanean contra inyección.

---

## Pruebas

```bash
php tests/test-analisis.php   # catálogo, estadística y pronóstico (lógica pura)
php tests/test-vistas.php     # vistas del motor de gráficos y caché (WordPress simulado)
```

No requieren WordPress: definen los stubs mínimos necesarios. Cubren, entre otros, que la probabilidad decrece con la magnitud, que la banda encierra al valor esperado, que el pronóstico es determinista y que **cambia cuando cambia el catálogo**.

---

## Licencia y atribución

Código bajo **GPL-2.0-or-later**. Datos sísmicos del **U.S. Geological Survey — Earthquake Hazards Program** (dominio público). Cartografía municipal **DANE/DIVIPOLA**. Elaboración de la Gobernación de Nariño bajo **CC BY 4.0**.
