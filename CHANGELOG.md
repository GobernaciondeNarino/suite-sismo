# Registro de cambios

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
