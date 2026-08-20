# Seguridad

Plugin **Sismos Nariño** · Gobernación de Nariño, Secretaría TIC.

## Cómo reportar una vulnerabilidad

Escriba a **hosting@narino.gov.co** con el asunto «Vulnerabilidad — sismos-suite», describiendo el problema y cómo reproducirlo. No abra un issue público para fallos de seguridad. Se acusa recibo en un plazo de cinco días hábiles.

---

## Superficie de ataque

### Puertas públicas (sin autenticación)

| Puerta | Qué expone | Defensas |
|---|---|---|
| `GET /wp-json/sismos/v1/*` — 10 rutas de solo lectura | Catálogo sísmico, estadística, marco de amenaza, payload de gráficos, datos abiertos en JSON y CSV | Límite de 120 peticiones por minuto y por IP; parámetros acotados (ámbito de una lista blanca, magnitud 0–10, días ≤ 20.000, límite ≤ 5.000); respuestas pesadas cacheadas con clave derivada de la firma del catálogo; grupo de caché podado a 200 entradas |
| 20 shortcodes | HTML publicado en páginas del sitio | Los atributos se filtran con `sanitize_key`, expresiones regulares o listas blancas; toda salida pasa por `esc_html`, `esc_attr` o `esc_url`; los valores de apariencia se sanean contra inyección en CSS |
| Archivos del plugin accesibles por URL | Cualquier `.php` bajo `wp-content/plugins/` | Todos declaran `defined( 'ABSPATH' ) || exit;`; las pruebas exigen además SAPI de línea de comandos y responden 403 a una petición web; cada directorio tiene su `index.php` silenciador |

Las rutas REST **no** exigen nonce, y es deliberado: son públicas y de solo lectura, y un nonce caducado servido desde la caché de página devolvería 403 a los visitantes anónimos. La protección es el límite por IP y el coste acotado de cada respuesta.

### Puertas autenticadas (panel)

| Puerta | Defensas |
|---|---|
| `admin_post_sis_guardar_fuentes`, `…_amenaza`, `…_estilo` | `current_user_can( 'manage_options' )` + `check_admin_referer` |
| `wp_ajax_sis_sincronizar`, `wp_ajax_sis_probar` | `current_user_can( 'manage_options' )` + `check_ajax_referer` |

### Peticiones salientes del servidor

El plugin consulta servicios externos por cron y bajo demanda del administrador. Toda URL pasa por `SIS_Security::url_permitida()`, que exige **HTTPS** y un host de la lista blanca (`SIS_Security::HOSTS`: USGS y servicios del SGC). Los parámetros geográficos se construyen desde el catálogo de ámbitos, nunca desde la entrada del usuario. Ni el visitante ni un atributo de shortcode pueden influir en qué host consulta el servidor.

---

## Medidas aplicadas

- **Anti-SSRF**: lista blanca de hosts, HTTPS obligatorio, doble validación (al guardar la configuración y antes de cada petición). Se rechazan redes internas, direcciones de metadatos de nube, esquemas `file:`/`gopher:` y hosts con sufijo engañoso.
- **Inyección de fórmulas en hojas de cálculo**: las celdas del CSV que empiezan por `=`, `+`, `-`, `@`, tabulador o retorno de carro se prefijan con un apóstrofo. El campo de lugar es texto libre del proveedor.
- **XSS**: escape en servidor de toda salida; en el navegador, el contenido dinámico se inserta escapado y las URL que llegan del feed externo se validan contra `^https?://` para que un feed alterado no pueda colar `javascript:` en un enlace.
- **Inyección en CSS**: el saneador elimina `;`, `{`, `}`, comillas, comentarios, `url()`, `expression()`, `@import` y los esquemas `javascript:`, `vbscript:` y `data:`.
- **Integridad de dependencias (SRI)**: D3plus y Leaflet se cargan por CDN con `integrity` y `crossorigin`. Si el archivo servido cambia un byte, el navegador lo rechaza; el motor de gráficos cae entonces a su renderizador SVG propio.
- **Amplificación de carga**: las rutas que recorren el catálogo y ajustan Gutenberg-Richter se cachean 5–15 minutos con clave dependiente de la firma del catálogo, y el grupo de caché se poda para que nadie pueda inflar la tabla variando parámetros.
- **Divulgación de información**: el panel público de fuentes publica solo el veredicto y el conteo de registros; el detalle técnico de un error (URL consultada, respuesta cruda) queda en el panel de administración.
- **Cifrado en reposo**: las credenciales opcionales de fuentes se guardan cifradas con `sodium_crypto_secretbox`, con clave derivada de las sales de `wp-config.php`. El USGS no exige credencial.

## Verificación

```bash
php tests/test-seguridad.php
```

Comprueba, entre otras cosas, que todo archivo PHP bloquea la ejecución directa, que la lista blanca rechaza redes internas y hosts engañosos, que el saneador de CSS neutraliza los vectores conocidos, que un atributo de shortcode con `<script>` no crea ningún nodo ejecutable y que las celdas de CSV con fórmulas se neutralizan.

En cada pull request se ejecuta además `anthropics/claude-code-security-review` (ver `.github/workflows/security-review.yml`).

---

## Lista de verificación para quien opera el sitio

1. **Secretos**: añada `ANTHROPIC_API_KEY` en *Settings → Secrets and variables → Actions*. Sin ese secreto los dos flujos de trabajo no se ejecutan.
2. **Pull requests externos**: en *Settings → Actions → General*, deje activada «Require approval for all external contributors». La acción de revisión de seguridad no está endurecida contra inyección de prompt y no debe correr sobre código de terceros sin revisión previa.
3. **Cron**: si el sitio tiene poco tráfico, considere reemplazar WP-Cron por un cron del sistema (`DISABLE_WP_CRON`), para que la sincronización no dependa de las visitas.
4. **Caché de página**: las rutas REST devuelven datos públicos; puede cachearlas en el CDN. No cachee `/wp-admin` ni `admin-ajax.php`.
5. **Límite por IP**: el contador usa `REMOTE_ADDR`. Si el sitio está detrás de un CDN o proxy inverso, todos los visitantes comparten la IP del borde: aplique el límite también en el borde, y no confíe en `X-Forwarded-For` dentro de PHP porque es falsificable.
6. **Actualizaciones**: al subir de versión las librerías de CDN hay que recalcular las huellas SRI de `SIS_Shortcodes::SRI` y `SRI_CSS` descargando el archivo exacto de la nueva versión.
