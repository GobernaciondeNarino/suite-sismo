# USGS — FDSN Event Web Service y feeds GeoJSON

Notas operativas de las dos APIs que alimentan el plugin. Ninguna exige clave, ambas sirven CORS abierto y ambas están en dominio público.

## 1. FDSN Event (catálogo histórico)

```
https://earthquake.usgs.gov/fdsnws/event/1/query?format=geojson
https://earthquake.usgs.gov/fdsnws/event/1/count?format=geojson     ← solo cuenta, sin descargar
```

Parámetros que usa el plugin:

| Parámetro | Uso |
|---|---|
| `format=geojson` | Respuesta GeoJSON nativa (FeatureCollection) |
| `starttime` / `endtime` | Ventana temporal (ISO 8601, UTC) |
| `minmagnitude` | Umbral de magnitud pedido al servicio |
| `minlatitude` · `maxlatitude` · `minlongitude` · `maxlongitude` | Recuadro (ámbitos `narino`, `regional`, `colombia`) |
| `latitude` · `longitude` · `maxradiuskm` | Círculo (ámbito `radio`) |
| `orderby=time-asc` | Orden cronológico ascendente |
| `limit` | Tope de eventos (máximo del servicio: 20 000) |
| `eventtype=earthquake` | Descarta explosiones, derrumbes y ruido |

**Ejemplo real usado por el plugin** (ámbito regional, 36 años, M ≥ 2,5):

```
https://earthquake.usgs.gov/fdsnws/event/1/query?format=geojson
  &starttime=1990-08-19&endtime=2026-08-20&minmagnitude=2.5
  &orderby=time-asc&limit=20000&eventtype=earthquake
  &minlatitude=-1.5&maxlatitude=4&minlongitude=-81.5&maxlongitude=-75.5
```

Campos de `properties` que se consumen: `mag`, `magType`, `place`, `time` (ms desde época, UTC), `type`, `tsunami`, `felt`, `cdi`, `mmi`, `alert`, `status`, `url`. De `geometry.coordinates`: `[lon, lat, profundidad_km]`.

**Notas de completitud.** En el suroccidente colombiano el catálogo global es completo a partir de M≈4,5. Pedir `minmagnitude` por debajo de ese valor es correcto —el plugin estima Mc con los datos descargados— pero no significa que exista registro completo por debajo.

## 2. Feeds de resumen (sismicidad reciente)

```
https://earthquake.usgs.gov/earthquakes/feed/v1.0/summary/{feed}.geojson
```

`{feed}`: `all_hour`, `all_day`, `all_week`, `all_month`, `2.5_day`, `2.5_week`, `2.5_month`, `4.5_week`, `significant_month`.

- Se regeneran **cada minuto** (el campo `metadata.generated` lo confirma).
- Son globales: el plugin los recorta al ámbito, tanto en el servidor como en el navegador.
- CORS abierto: el navegador puede consultarlos directamente, sin proxy y sin cargar el servidor de WordPress. Es la vía de máxima frescura de `[sismos_estado]` y `[sismos_ultimos]`.

## 3. Buenas prácticas aplicadas

- **Una consulta por ámbito y por sincronización**, no por visita: el resultado se cachea en tabla durable.
- **`count` antes de `query`** en el botón «Probar» del panel, para no descargar megabytes solo por comprobar la configuración.
- **Lista blanca de servidores** y HTTPS obligatorio en toda petición saliente.
- **Atribución obligatoria** en cada respuesta de la API abierta y al pie de cada componente.
