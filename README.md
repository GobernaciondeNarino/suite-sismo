# Sismos Nariño — Análisis Estadístico y Amenaza Sísmica

Plugin de WordPress que publica el **análisis estadístico de la sismicidad** de Nariño y de la zona de subducción que gobierna su amenaza, con **datos abiertos del USGS**, gráficos interactivos D3plus, mapa de epicentros y un módulo de **amenaza y preparación** construido sobre fuentes oficiales.

> **Este plugin no pronostica sismos.** La predicción de un sismo —fecha, lugar y magnitud— no es posible, y el pronóstico probabilístico de réplicas es competencia del Servicio Geológico Colombiano, que hoy no lo emite. La plataforma se sitúa en el proceso de **conocimiento del riesgo** de la Ley 1523 de 2012: informa, educa y remite a la autoridad técnica. El razonamiento completo está en [`docs/marco-comunicacion-riesgo.md`](docs/marco-comunicacion-riesgo.md).

> **Gobernación de Nariño · Secretaría TIC, Innovación y Gobierno Abierto**
> Arquitectura hermana de [`suite-oni`](https://github.com/GobernaciondeNarino/suite-oni): mismo motor de gráficos de 3 capas, mismo front minimalista, misma disciplina de datos abiertos y resiliencia.

---

## Instalación (Plesk / WordPress)

1. Copie la carpeta del plugin a `wp-content/plugins/sismos-narino/`.
2. Actívelo desde **Plugins → Sismos Nariño**.
3. Al activar se crean las tablas `wp_sis_cache` y `wp_sis_audit`, se siembra la configuración por defecto y se agendan dos crones: catálogo cada 12 h y feed reciente cada hora.
4. Vaya a **Sismos Nariño → Resumen** y pulse **Sincronizar catálogo ahora**.
5. Revise **Sismos Nariño → Amenaza y normativa** y verifique los coeficientes de la NSR-10 contra el texto oficial antes de publicarlos.

Sin proceso de build: D3plus y Leaflet se cargan por CDN. Requiere WordPress 5.8+ y PHP 7.4+.

> **¿Dónde copio los shortcodes?** En **Sismos Nariño → Elementos** está el catálogo completo con su descripción, atributos y botón **Copiar**, más la lista de vistas para `[sismos_grafico]`.

---

## Fuentes de datos

| Fuente | Uso | Frecuencia |
|---|---|---|
| [USGS FDSN Event Web Service](https://earthquake.usgs.gov/fdsnws/event/1/) | Motor principal. Catálogo histórico en GeoJSON nativo, sin clave de API, recortado por recuadro o radio | Cron cada 12 h |
| [Feeds GeoJSON de resumen](https://earthquake.usgs.gov/earthquakes/feed/v1.0/summary/) | Sismicidad reciente. CORS abierto: lo consume el servidor por cron **y** el navegador directamente | Cron cada hora · navegador cada 2 min (el feed se regenera cada ~1 min) |
| [SGC](https://amenazasismica.sgc.gov.co/) — amenaza, sismos recientes, sismos sentidos, catálogo integrado, OVSP | Autoridad técnica: la plataforma **enlaza**, no replica ni recalcula | Enlace permanente |
| DANE / DIVIPOLA | Centroides de los 64 municipios, para asignar cada epicentro al municipio más cercano | Estático |

Los datos del USGS son de **dominio público**; la elaboración de la Gobernación se publica bajo **CC BY 4.0**.

### Ámbitos espaciales

El catálogo global es completo en Colombia a partir de M≈4,5, así que el recuadro estricto del departamento aporta pocas decenas de eventos. Por eso el plugin declara cuatro ámbitos y usa el **regional** por defecto para la estadística:

| `ambito` | Cobertura | Para qué sirve |
|---|---|---|
| `narino` | lat 0,35–2,70 · lon −79,10 a −76,85 | Lectura territorial estricta del departamento |
| `regional` *(por defecto)* | lat −1,50–4,00 · lon −81,50 a −75,50 | Suroccidente de Colombia y norte de Ecuador, incluida la fosa Nazca–Sudamérica |
| `radio` | 300 km alrededor de Pasto | Sismicidad que puede sentirse en la capital |
| `colombia` | lat −4,50–13,50 · lon −82,00 a −66,00 | Referencia comparativa nacional |

---

## Shortcodes

### Datos y estadística

| Shortcode | Qué publica | Atributos |
|---|---|---|
| `[sismos_estado]` | Semáforo de actividad: último sismo, conteos 24 h / 7 d / 30 d / 1 año, municipio más cercano | `ambito`, `dias`, `min_mag`, `compacto`, `vivo` |
| `[sismos_ultimos]` | Lista de los últimos sismos, con destello al llegar uno nuevo | `ambito`, `limite`, `min_mag`, `vivo` |
| `[sismos_mapa]` | Mapa Leaflet de epicentros (tamaño = magnitud, color = profundidad) + centroides municipales | `ambito`, `anios`, `dias`, `min_mag`, `alto`, `municipios`, `zoom` |
| `[sismos_grafico]` | **Tarjeta de gráfico D3plus con barra de herramientas** (Cómo funciona · Detalle · Compartir · Datos · Imagen PNG · Descarga JSON · Cambiar tipo en vivo) | `view`, `type`, `ambito`, `anios`, `min_mag`, `theme`, `actions`, `legend`, `legend_style`, `legend_pos`, `toolbar`, `alto`, `grupo`, `analisis`, `titulo` |
| `[sismos_estadistica]` | Ficha estadística: Mc, valor b ± error, energía liberada y recurrencia observada por magnitud | `ambito`, `anios`, `dias`, `min_mag` |
| `[sismos_datos]` | Botones de datos abiertos (JSON / CSV / Ver API) | `recurso`, `ambito`, `anios`, `dias`, `min_mag`, `texto` |
| `[sismos_estado_api]` | Panel público de salud de las fuentes | — |
| `[sismos_descripcion]` · `[sismos_explicacion]` · `[sismos_analisis_cualitativo]` · `[sismos_analisis_cuantitativo]` · `[sismos_analisis]` | Piezas de texto de una vista, para maquetarlas aparte de la gráfica | `view`, `ambito`, `anios`, `titulo`, `grupo` |
| `[sismos_filtro]` · `[sismos_panel]` | Filtros y panel de detalle sincronizados con un gráfico por `grupo` | `grupo`, `control`, `etiqueta` |

### Amenaza y preparación

| Shortcode | Qué publica | Atributos |
|---|---|---|
| `[sismos_amenaza]` | De dónde viene la amenaza —subducción, fallas activas, vulcanismo, tsunami—, referencia normativa NSR-10 y enlace al sistema de consulta del SGC | `titulo`, `normativa`, `fuentes` |
| `[sismos_glosario]` | Alerta temprana ≠ pronóstico ≠ probabilidad de largo plazo ≠ predicción, con marca clara de cuál no es posible | `titulo` |
| `[sismos_preparacion]` | Qué hacer antes, durante y después; kit de emergencia; organización comunitaria | `seccion`, `titulo` |
| `[sismos_replicas]` | Qué son las réplicas, cuánto duran y qué hacer. Texto fijo, sin cifras propias, con enlace al boletín del SGC | `titulo` |
| `[sismos_desinformacion]` | Cómo reconocer una «predicción» falsa: fechas exactas, sellos copiados, alineaciones planetarias, anuncios de réplicas | `titulo` |
| `[sismos_fuentes_oficiales]` | Directorio del SGC, OVSP, UNGRD y USGS con el descargo institucional | `titulo` |

**Ejemplos**

```
[sismos_estado ambito="narino"]
[sismos_ultimos limite="8"]
[sismos_mapa ambito="regional" anios="5" min_mag="4.5" alto="520px"]

[sismos_grafico view="sismos_mensuales" type="bar"]
[sismos_grafico view="frecuencia_magnitud" type="line" alto="460px"]
[sismos_grafico view="recurrencia_historica" type="bar"]

[sismos_amenaza]
[sismos_glosario]
[sismos_preparacion seccion="kit"]
[sismos_desinformacion]
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
**Recurrencia** — `recurrencia_historica`

**Tipos** (`type`): `bar`, `stacked_bar`, `line`, `area`, `stacked_area`, `pie`, `donut`, `treemap`, `box_whisker` — restringidos automáticamente a los compatibles con la categoría de cada vista.

Cada vista trae, calculados sobre los datos vigentes: descripción, interpretación cualitativa, **cifras clave** y el aviso de alcance. Todos los enunciados van en pasado: describen lo ocurrido, nunca anticipan.

---

## Qué se publica y qué no

**Sí** — estadística retrospectiva del catálogo, contexto de amenaza, glosario, guía post-sismo, panel anti-desinformación, preparación ciudadana y enlaces oficiales.

**No** — predicciones, probabilidades propias de ocurrencia futura, pronósticos de réplicas, cuentas regresivas, «el próximo gran sismo» ni nada que sugiera que la plataforma avisará antes de un sismo.

La **recurrencia observada** que sí se publica (cuántos sismos de cada magnitud hubo y cada cuántos años ocurrió uno en promedio) es estadística del pasado, y cada texto que la acompaña advierte que no es un calendario: los sismos no llevan cuenta del tiempo transcurrido.

Esta frontera está protegida por una prueba automática: [`tests/test-sin-pronostico.php`](tests/test-sin-pronostico.php) falla si reaparecen clases, vistas, rutas o textos de pronóstico.

---

## Datos abiertos (API pública)

```
GET /wp-json/sismos/v1/eventos?ambito=narino&dias=30
GET /wp-json/sismos/v1/estadistica?ambito=regional&anios=20
GET /wp-json/sismos/v1/amenaza
GET /wp-json/sismos/v1/render?view=frecuencia_magnitud&type=line
GET /wp-json/sismos/v1/vistas
GET /wp-json/sismos/v1/ambitos
GET /wp-json/sismos/v1/municipios
GET /wp-json/sismos/v1/estado-apis

GET /wp-json/sismos/v1/abierto/eventos?ambito=narino&anios=10&formato=csv
GET /wp-json/sismos/v1/abierto/estadistica?ambito=regional&formato=csv
GET /wp-json/sismos/v1/abierto/recurrencia?ambito=regional&formato=json
```

Todas son públicas y de solo lectura, con rate-limiting por IP (120 peticiones/minuto) y la atribución al USGS incorporada. El CSV sale con BOM UTF-8 para que Excel lea bien las tildes. `/amenaza` devuelve el glosario, las fuentes oficiales, el contexto geológico, la normativa y la guía post-sismo, para que otras plataformas del departamento reutilicen el mismo marco.

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
    class-sis-amenaza.php      Marco de amenaza: glosario, fuentes oficiales, geología, normativa
    class-sis-views.php        Catálogo de vistas del motor de gráficos
    textos-graficos.php        Textos largos por vista
    textos-preparacion.php     Guía ciudadana antes/durante/después
    textos-elementos.php       Catálogo de shortcodes del panel
  analysis/
    class-sis-catalogo.php     Normalización del GeoJSON, filtros y agregaciones
    class-sis-estadistica.php  Mc, valor b, Gutenberg-Richter, recurrencia observada
    class-sis-texto.php        Narrativa calculada a partir de los datos
  sync/
    class-sis-sync.php         Orquestador de cron, HTTP resiliente y auditoría
    class-sis-sync-usgs.php    Conector FDSN Event
    class-sis-sync-feed.php    Conector de feeds GeoJSON
  shortcodes/                  Registro y render de los shortcodes
  admin/                       Panel: Resumen, Fuentes, Amenaza, Apariencia, Elementos
assets/css                     estilos.css (minimalista) · grafico.css (tarjeta de gráfico)
assets/js                      sis-core · renderer · grafico · grupo · composable ·
                               estado · ultimos · mapa · estadistica · datos ·
                               estado-api · admin
data/                          Semilla local del catálogo (resiliencia)
docs/                          Marco de comunicación del riesgo y metodología estadística
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
php tests/test-analisis.php        # catálogo, estadística y marco de amenaza (lógica pura)
php tests/test-vistas.php          # vistas del motor de gráficos y caché (WordPress simulado)
php tests/test-render.php          # render de los componentes de amenaza y preparación
php tests/test-sin-pronostico.php  # salvaguarda: el plugin no debe pronosticar sismos
```

No requieren WordPress: definen los stubs mínimos necesarios. Comprueban, entre otras cosas, que ninguna vista publica meses futuros, que todas llevan el aviso de alcance, que la estadística no expone métodos de probabilidad hacia adelante y que el glosario declara imposible la predicción.

---

## Licencia y atribución

Código bajo **GPL-2.0-or-later**. Datos sísmicos del **U.S. Geological Survey — Earthquake Hazards Program** (dominio público). Cartografía municipal **DANE/DIVIPOLA**. Marco de amenaza y contenidos de preparación basados en el **Servicio Geológico Colombiano**, la **UNGRD** y la **Cruz Roja Colombiana**. Elaboración de la Gobernación de Nariño bajo **CC BY 4.0**.

La autoridad técnica en materia sísmica y volcánica es el **Servicio Geológico Colombiano**.
