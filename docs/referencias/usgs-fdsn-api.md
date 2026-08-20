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

---

# Anexo: servicios de amenaza del Servicio Geológico Colombiano

La capa de amenaza que superpone `[sismos_mapa]` viene del **Modelo Nacional de Amenaza Sísmica** (SGC – Universidad Nacional), servido por ArcGIS Server con extensión WMS.

```
https://srvags.sgc.gov.co/arcgis/services/Amenaza_Sismica/Mapa_Amenaza_Sismica_Nacional_PGA{periodo}/MapServer/WMSServer
```

`{periodo}`: `75`, `225`, `475`, `975`, `2475` — periodo de retorno en años. La capa `0` («Valor Amenaza») es la aceleración horizontal máxima en roca.

| Periodo de retorno | Probabilidad de excedencia en 50 años | Uso habitual |
|---|---|---|
| 75 años | 50 % | Escenarios frecuentes |
| 225 años | 20 % | — |
| **475 años** | **10 %** | **Nivel de diseño de la NSR-10** |
| 975 años | 5 % | Edificaciones indispensables |
| 2.475 años | 2 % | Verificación de colapso |

**Detalles operativos comprobados contra el servicio:**

- Soporta `WMSServer` y `WFSServer`; el catálogo REST vive en `srvags.sgc.gov.co/arcgis/rest/services/Amenaza_Sismica/`.
- CRS disponibles: `CRS:84`, `EPSG:4326` y `EPSG:4686`. **No publica Web Mercator (EPSG:3857)**, que es la proyección por defecto de Leaflet: por eso la capa se pide en `EPSG:4326` y Leaflet reproyecta. A la latitud de Nariño (0°–3° N) la diferencia entre Mercator y coordenadas geográficas es inferior al 0,05 %.
- Con WMS 1.3.0 y `EPSG:4326` el orden de ejes es **lat, lon**. Una petición con el orden invertido devuelve una imagen prácticamente vacía en lugar de un error, así que conviene verificar el tamaño de la respuesta al depurar. Leaflet aplica el orden correcto de forma automática.
- El servicio no es teselado (`MapServer` dinámico): responde a `GetMap` por extensión.
- Atribución obligatoria: «Servicio Geológico Colombiano (anteriormente INGEOMINAS), Universidad Nacional de Colombia».

**La plataforma muestra esta capa, no la recalcula.** Cualquier cifra de amenaza que se cite debe consultarse en el sistema oficial: <https://amenazasismica.sgc.gov.co/>.
