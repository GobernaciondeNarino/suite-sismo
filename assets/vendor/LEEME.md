# Librerías de terceros

Estas librerías se sirven desde el propio plugin y no desde una CDN.

Con CDN, cualquier bloqueador de anuncios, extensión de privacidad o proxy
corporativo que se interponga deja la página sin gráficos: la huella SRI —que
es lo correcto frente a un CDN comprometido— convierte esa interferencia en un
bloqueo total. En un portal público eso no es aceptable. Servirlas desde aquí
además evita que el navegador de quien consulta el sitio haga peticiones a
servidores de terceros.

| Archivo | Versión | Origen | Licencia |
|---|---|---|---|
| `d3plus.min.js` | 4.3.0 | `@d3plus/core@4.3.0/umd/d3plus-core.full.min.js` | MIT |
| `three.module.min.js` | 0.160.0 | `three@0.160.0/build/three.module.min.js` | MIT |
| `three-addons/controls/OrbitControls.js` | 0.160.0 | `three@0.160.0/examples/jsm/controls/OrbitControls.js` | MIT |
| `leaflet.js` · `leaflet.css` · `images/` | 1.9.4 | `leaflet@1.9.4/dist/` | BSD-2-Clause |

## Cómo actualizar

1. Descargue el archivo de la versión nueva desde su paquete npm.
2. Sustituya el archivo aquí, conservando el nombre.
3. Actualice la constante de versión correspondiente en
   `includes/shortcodes/class-sis-shortcodes.php` (`D3PLUS_VERSION`,
   `THREE_VERSION`, `LEAFLET_VERSION`): de ella depende el parámetro `ver` que
   invalida la caché del navegador.
4. Ejecute `php tests/test-seguridad.php` y `php tests/test-render.php`.

Use siempre la build **minificada** cuando exista. La de `three.module.js` sin
minificar pesa 1.243 KB frente a los 655 KB de `three.module.min.js`.
