/* Núcleo compartido de los shortcodes de Sismos Nariño.
   Expone window.SIScore: peticiones a la REST interna, consumo directo del
   feed GeoJSON del USGS (frescura ~1 min), utilidades de formato en es-CO y
   manejo de skeleton/errores. Sin dependencias externas. */
(function () {
  'use strict';

  var CFG = window.SIS || { rest: '', feed: '', ambito: 'regional', ambitos: [] };

  /* ---------------- Peticiones ---------------- */

  /** GET a la REST interna del plugin.
      No se envía X-WP-Nonce a propósito: los endpoints son públicos de solo
      lectura y un nonce caducado servido desde la caché de página devolvería
      403 a los visitantes anónimos. */
  function rest(path, params) {
    var url = CFG.rest + path;
    if (params) {
      var q = Object.keys(params)
        .filter(function (k) { return params[k] !== undefined && params[k] !== null && params[k] !== ''; })
        .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); })
        .join('&');
      if (q) { url += (url.indexOf('?') >= 0 ? '&' : '?') + q; }
    }
    return fetch(url).then(function (r) {
      if (!r.ok) { throw new Error('HTTP ' + r.status); }
      return r.json();
    });
  }

  /** GET a una URL pública externa (feeds del USGS, con CORS abierto). */
  function externo(url) {
    return fetch(url).then(function (r) {
      if (!r.ok) { throw new Error('HTTP ' + r.status); }
      return r.json();
    });
  }

  /* ---------------- Ámbitos y feed en vivo ---------------- */

  /** Definición de un ámbito espacial (con su geometría). */
  function ambito(slug) {
    var lista = CFG.ambitos || [];
    for (var i = 0; i < lista.length; i++) {
      if (lista[i].slug === slug) { return lista[i]; }
    }
    return lista.length ? lista[0] : null;
  }

  /** Distancia ortodrómica en kilómetros (haversine). */
  function distanciaKm(lat1, lon1, lat2, lon2) {
    var R = 6371.0088, rad = Math.PI / 180;
    var f1 = lat1 * rad, f2 = lat2 * rad;
    var df = (lat2 - lat1) * rad, dl = (lon2 - lon1) * rad;
    var a = Math.sin(df / 2) * Math.sin(df / 2) +
      Math.cos(f1) * Math.cos(f2) * Math.sin(dl / 2) * Math.sin(dl / 2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(Math.max(0, 1 - a)));
  }

  /** ¿El epicentro cae dentro del ámbito? */
  function dentro(slug, lat, lon) {
    var a = ambito(slug);
    if (!a) { return true; }
    if (a.tipo === 'radio') { return distanciaKm(a.lat, a.lon, lat, lon) <= a.radio_km; }
    return lat >= a.lat_min && lat <= a.lat_max && lon >= a.lon_min && lon <= a.lon_max;
  }

  /** Acepta una URL solo si es http o https; en cualquier otro caso la anula. */
  function urlSegura(u) {
    if (typeof u !== 'string') { return ''; }
    return /^https?:\/\//i.test(u.trim()) ? u.trim() : '';
  }

  /** Normaliza un feature del USGS al mismo esquema que usa el backend. */
  function normalizar(f) {
    if (!f || !f.properties || !f.geometry || !f.geometry.coordinates) { return null; }
    var p = f.properties, c = f.geometry.coordinates;
    if (p.mag === null || p.mag === undefined) { return null; }
    if (p.type && p.type !== 'earthquake') { return null; }
    var ts = Math.round((p.time || 0) / 1000);
    if (!ts) { return null; }
    return {
      id: f.id,
      ts: ts,
      fecha: new Date(ts * 1000).toISOString().replace('T', ' ').slice(0, 19),
      mag: Math.round(p.mag * 10) / 10,
      tipo_mag: p.magType || '',
      profundidad: c[2] === null ? 0 : Math.round(c[2] * 10) / 10,
      lat: c[1],
      lon: c[0],
      lugar: p.place || '',
      // Solo http(s): un feed alterado no debe poder colar javascript: en un
      // enlace que el visitante va a pulsar.
      url: urlSegura(p.url),
      tsunami: p.tsunami ? 1 : 0,
      reportes: p.felt || 0,
      intensidad: p.cdi || p.mmi || 0,
      alerta: p.alert || ''
    };
  }

  /** Lee un feed de resumen del USGS y lo recorta al ámbito.
      Los feeds se regeneran cada minuto: es la vía de máxima frescura. */
  function feedVivo(nombre, slug) {
    var url = (CFG.feed || 'https://earthquake.usgs.gov/earthquakes/feed/v1.0/summary/') + (nombre || 'all_day') + '.geojson';
    return externo(url).then(function (json) {
      var out = [];
      (json.features || []).forEach(function (f) {
        var e = normalizar(f);
        if (e && dentro(slug || CFG.ambito, e.lat, e.lon)) { out.push(e); }
      });
      out.sort(function (a, b) { return a.ts - b.ts; });
      return { eventos: out, generado: json.metadata ? json.metadata.generated : 0, globales: (json.features || []).length };
    });
  }

  /* ---------------- Formato ---------------- */

  /** Número con formato es-CO. */
  function num(v, dec) {
    var d = dec === undefined ? 0 : dec;
    try {
      return Number(v).toLocaleString('es-CO', { minimumFractionDigits: d, maximumFractionDigits: d });
    } catch (e) { return String(v); }
  }

  /** Fecha ISO/UTC a texto legible en español. */
  function fecha(iso) {
    if (!iso) { return ''; }
    var d = new Date(String(iso).replace(' ', 'T') + (String(iso).length <= 19 ? 'Z' : ''));
    if (isNaN(d.getTime())) { return String(iso); }
    try {
      return d.toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' });
    } catch (e) { return d.toISOString().slice(0, 16).replace('T', ' '); }
  }

  /** «hace 3 horas» a partir de una marca de tiempo en segundos. */
  function hace(ts) {
    var s = Math.max(0, Math.floor(Date.now() / 1000) - Number(ts));
    if (s < 90) { return 'hace ' + s + ' segundos'; }
    var m = Math.floor(s / 60);
    if (m < 90) { return 'hace ' + m + ' minutos'; }
    var h = Math.floor(m / 60);
    if (h < 48) { return 'hace ' + h + ' horas'; }
    return 'hace ' + Math.floor(h / 24) + ' días';
  }

  /** Color por magnitud (escala técnica, no configurable). */
  function colorMagnitud(m) {
    m = Number(m);
    if (m >= 6.5) { return '#C0392B'; }
    if (m >= 5.5) { return '#FF7300'; }
    if (m >= 4.5) { return '#FFC53B'; }
    if (m >= 3.0) { return '#3EBA6A'; }
    return '#0080C3';
  }

  /** Color por profundidad focal. */
  function colorProfundidad(km) {
    km = Number(km);
    if (km < 70) { return '#C0392B'; }
    if (km < 300) { return '#FF7300'; }
    return '#0080C3';
  }

  /* ---------------- DOM ---------------- */

  function el(tag, cls, html) {
    var e = document.createElement(tag);
    if (cls) { e.className = cls; }
    if (html !== null && html !== undefined) { e.innerHTML = html; }
    return e;
  }

  function esc(s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /** Envuelve una tabla en un contenedor desplazable en horizontal.
      Sin esto, una tabla de cuatro o cinco columnas empuja el ancho de la
      página entera en móviles y rompe el layout del tema anfitrión. */
  function tablaScroll(tabla) {
    var caja = el('div', 'sis-tabla-scroll');
    caja.setAttribute('role', 'region');
    caja.setAttribute('tabindex', '0');
    caja.setAttribute('aria-label', 'Tabla de datos, desplazable en horizontal');
    caja.appendChild(tabla);
    return caja;
  }

  function quitarSkeleton(cont) {
    var s = cont.querySelector('.sis-skeleton');
    if (s) { s.parentNode.removeChild(s); }
  }

  /** Error elegante con botón de reintento. */
  function error(cont, msg, reintentar) {
    quitarSkeleton(cont);
    var prev = cont.querySelector('.sis-error');
    if (prev) { prev.parentNode.removeChild(prev); }
    var pista = (typeof navigator !== 'undefined' && navigator.onLine === false)
      ? '<span class="sis-mute-line">Parece que no hay conexión a internet.</span>' : '';
    var box = el('div', 'sis-error', '<p>' + esc(msg || 'No se pudieron cargar los datos. Intente de nuevo.') + '</p>' + pista);
    box.setAttribute('role', 'alert');
    var b = el('button', 'sis-btn', 'Reintentar');
    b.type = 'button';
    b.addEventListener('click', function () {
      if (box.parentNode) { box.parentNode.removeChild(box); }
      if (typeof reintentar === 'function') { reintentar(); }
    });
    box.appendChild(b);
    cont.insertBefore(box, cont.firstChild);
  }

  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
  }

  /** Lee los data-* de consulta comunes a todos los shortcodes. */
  /* Los cinco atributos de consulta que publica cualquier componente. El
     periodo ya viene normalizado desde PHP —solo llega lleno el campo que de
     verdad va a filtrar—, así que aquí basta con reenviarlo tal cual. */
  function consulta(node) {
    return {
      ambito: node.getAttribute('data-ambito') || CFG.ambito,
      anios: node.getAttribute('data-anios') || '',
      dias: node.getAttribute('data-dias') || '',
      anio: node.getAttribute('data-anio') || '',
      mes: node.getAttribute('data-mes') || '',
      min_mag: node.getAttribute('data-min-mag') || ''
    };
  }

  /* Los campos de periodo que estén llenos, listos para una petición REST.
     Se filtran los vacíos para que la URL —y con ella la clave de caché del
     servidor— no cambie por un parámetro que no filtra nada. */
  function periodo(q) {
    var out = {};
    ['dias', 'anio', 'mes', 'anios'].forEach(function (k) {
      if (q[k]) { out[k] = q[k]; }
    });
    return out;
  }

  /** Mezcla superficial: base + periodo, sin tocar la base. */
  function conPeriodo(base, q) {
    var out = {};
    for (var k in base) { if (Object.prototype.hasOwnProperty.call(base, k)) { out[k] = base[k]; } }
    var p = periodo(q);
    for (var j in p) { if (Object.prototype.hasOwnProperty.call(p, j)) { out[j] = p[j]; } }
    return out;
  }

  /** Refresco periódico que se detiene si la pestaña no está visible. */
  function cadaMinuto(fn, minutos) {
    var ms = Math.max(1, minutos || 1) * 60000;
    var t = setInterval(function () {
      if (document.visibilityState === 'visible') { fn(); }
    }, ms);
    return function () { clearInterval(t); };
  }

  window.SIScore = {
    cfg: CFG,
    rest: rest,
    periodo: periodo,
    conPeriodo: conPeriodo,
    externo: externo,
    feedVivo: feedVivo,
    ambito: ambito,
    dentro: dentro,
    distanciaKm: distanciaKm,
    num: num,
    fecha: fecha,
    hace: hace,
    colorMagnitud: colorMagnitud,
    colorProfundidad: colorProfundidad,
    el: el,
    esc: esc,
    tablaScroll: tablaScroll,
    quitarSkeleton: quitarSkeleton,
    error: error,
    ready: ready,
    consulta: consulta,
    urlSegura: urlSegura,
    cadaMinuto: cadaMinuto
  };
})();
