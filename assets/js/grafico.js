/* [sismos_grafico] — hidratador (capa 2 del motor de gráficos).
   Lee los data-* del <figure>, pide /render a la REST, entrega el payload al
   renderer y cablea la barra de herramientas (Cómo funciona, Detalle,
   Compartir, Datos, Imagen PNG, Descarga JSON y Cambiar tipo en vivo) y los
   modales. También hidrata los bloques de texto [sismos_analisis] y afines.
   Toda salida se escapa antes de entrar al DOM. */
(function () {
  'use strict';
  var C = window.SIScore;

  var TIPO_LABEL = {
    bar: 'Barras', stacked_bar: 'Barras apiladas', line: 'Líneas', area: 'Área',
    stacked_area: 'Área apilada', pie: 'Pastel', donut: 'Dona', treemap: 'Treemap',
    box_whisker: 'Caja y bigotes'
  };
  var ICON = {
    explicacion: 'editor-help', detalle: 'info-outline', compartir: 'share',
    datos: 'editor-table', imagen: 'format-image', descarga: 'download', cambiar: 'image-rotate'
  };
  var LABEL = {
    explicacion: 'Cómo funciona', detalle: 'Detalle', compartir: 'Compartir',
    datos: 'Datos', imagen: 'Imagen', descarga: 'Descarga', cambiar: 'Cambiar'
  };
  var DEFAULT_ACTIONS = ['explicacion', 'detalle', 'compartir', 'datos', 'imagen', 'descarga', 'cambiar'];

  C.ready(function () {
    Array.prototype.forEach.call(document.querySelectorAll('[data-sis-grafico]'), init);
    Array.prototype.forEach.call(document.querySelectorAll('[data-sis-analisis]'), initAnalisis);
  });

  /* ================= Gráfico ================= */

  function init(fig) {
    var chartEl = fig.querySelector('.sis-g__chart');
    var titleEl = fig.querySelector('.sis-g__title');
    if (!chartEl) { return; }

    var q = C.consulta(fig);
    var st = {
      view: fig.getAttribute('data-view') || 'sismos_mensuales',
      type: fig.getAttribute('data-type') || '',
      ambito: q.ambito,
      anios: q.anios,
      dias: q.dias,
      anio: q.anio,
      mes: q.mes,
      min_mag: q.min_mag,
      legend: fig.getAttribute('data-legend') !== '0',
      legendStyle: fig.getAttribute('data-legend-style') || 'text',
      legendPos: fig.getAttribute('data-legend-pos') || 'bottom',
      analisis: fig.getAttribute('data-analisis') || 'no',
      toolbar: fig.getAttribute('data-toolbar') !== '0',
      actions: parseActions(fig.getAttribute('data-actions')),
      grupo: fig.getAttribute('data-grupo') || '',
      payload: null,
      viz: null
    };

    if (st.grupo && window.SISGrupo) {
      // El grupo sincroniza también el periodo: si un filtro cambia los días
      // o el año, el gráfico y su análisis tienen que hablar de lo mismo.
      var CAMPOS = ['view', 'type', 'ambito', 'anios', 'dias', 'anio', 'mes', 'min_mag'];
      var inicial = {};
      CAMPOS.forEach(function (k) { inicial[k] = st[k]; });

      var e0 = window.SISGrupo.init(st.grupo, inicial);
      CAMPOS.forEach(function (k) { if (e0[k] !== undefined && e0[k] !== '') { st[k] = e0[k]; } });

      window.SISGrupo.subscribe(st.grupo, function (e) {
        var cambio = CAMPOS.some(function (k) { return e[k] !== undefined && e[k] !== st[k]; });
        CAMPOS.forEach(function (k) { if (e[k] !== undefined) { st[k] = e[k]; } });
        if (cambio) { cargar(fig, chartEl, titleEl, st); }
      });
    }

    cargar(fig, chartEl, titleEl, st);
  }

  function parseActions(s) {
    if (!s) { return DEFAULT_ACTIONS.slice(); }
    var arr = String(s).split(',').map(function (x) { return x.trim(); })
      .filter(function (x) { return DEFAULT_ACTIONS.indexOf(x) >= 0; });
    return arr.length ? arr : DEFAULT_ACTIONS.slice();
  }

  function params(st) {
    return {
      view: st.view, type: st.type, ambito: st.ambito,
      anios: st.anios, dias: st.dias, anio: st.anio, mes: st.mes,
      min_mag: st.min_mag
    };
  }

  function cargar(fig, chartEl, titleEl, st) {
    C.rest('/render', params(st))
      .then(function (p) {
        st.payload = p;
        st.type = (p.chart && p.chart.key) || st.type;
        var nombre = (p.view && p.view.name) || 'Gráfico';
        if (titleEl && !titleEl.getAttribute('data-fijo')) { titleEl.textContent = nombre; }

        chartEl.setAttribute('role', 'img');
        chartEl.setAttribute('aria-label', nombre + (p.view && p.view.description ? '. ' + p.view.description : ''));

        C.quitarSkeleton(fig);
        if (st.toolbar) { construirToolbar(fig, chartEl, titleEl, st); }
        dibujar(fig, chartEl, st);
        pintarAnalisis(fig, p, st.analisis);

        if (st.grupo && window.SISGrupo) { window.SISGrupo.payload(st.grupo, p); }
      })
      .catch(function () {
        C.error(fig, 'No se pudo cargar el gráfico.', function () { cargar(fig, chartEl, titleEl, st); });
      });
  }

  /* Libera la instancia de D3plus anterior. destroy() existe desde la v4;
     con una versión que no lo tenga, simplemente no hay nada que soltar. */
  function soltar(st) {
    if (st.viz && typeof st.viz.destroy === 'function') {
      try { st.viz.destroy(); } catch (e) { /* una instancia ya desmontada no debe romper el redibujo */ }
    }
    st.viz = null;
  }

  function dibujar(fig, chartEl, st) {
    try {
      if (!window.SISRenderer) { throw new Error('renderer'); }

      // Cada cambio de tipo o de filtro redibuja la tarjeta. Sin soltar la
      // instancia anterior, su ResizeObserver y sus escuchadores globales
      // sobreviven al nodo que ya no existe: en una página con varios
      // gráficos y filtros, eso se acumula visita tras visita.
      soltar(st);

      st.viz = window.SISRenderer.render(chartEl, st.payload, {
        legend: st.legend, legendStyle: st.legendStyle, legendPos: st.legendPos
      });
      fig.setAttribute('data-type', st.type);
      var sel = fig.querySelector('.sis-g__swap select');
      if (sel) { sel.value = st.type; }
    } catch (e) {
      chartEl.innerHTML = '<p class="sis-g__analisis-desc" style="padding:12px">No se pudo dibujar el gráfico. Verifique la conexión con la CDN de D3plus.</p>';
    }
  }

  function reRender(fig, chartEl, st, nuevoTipo) {
    chartEl.classList.add('is-loading');
    var p = params(st);
    p.type = nuevoTipo;
    C.rest('/render', p)
      .then(function (r) {
        st.payload = r;
        st.type = (r.chart && r.chart.key) || nuevoTipo;
        dibujar(fig, chartEl, st);
        chartEl.classList.remove('is-loading');
      })
      .catch(function () { chartEl.classList.remove('is-loading'); });
  }

  /* ================= Bloques de texto ================= */

  function initAnalisis(box) {
    var q = C.consulta(box);
    var st = {
      view: box.getAttribute('data-view') || 'sismos_mensuales',
      modo: box.getAttribute('data-modo') || 'ambos',
      titulo: box.getAttribute('data-titulo') || '',
      grupo: box.getAttribute('data-grupo') || '',
      ambito: q.ambito, anios: q.anios, dias: q.dias, anio: q.anio, mes: q.mes,
      min_mag: q.min_mag
    };

    var pintado = false;
    function render(p) {
      if (!p) { return; }
      pintado = true;
      pintarBloque(box, p, st.modo, st.titulo);
    }
    function propio() {
      C.rest('/render', {
        view: st.view, ambito: st.ambito,
        anios: st.anios, dias: st.dias, anio: st.anio, mes: st.mes,
        min_mag: st.min_mag
      })
        .then(render)
        .catch(function () {
          if (!pintado) { C.error(box, 'No se pudo cargar el análisis.', function () { pintado = false; initAnalisis(box); }); }
        });
    }

    if (st.grupo && window.SISGrupo) {
      var e0 = window.SISGrupo.init(st.grupo, {
        view: st.view, ambito: st.ambito,
        anios: st.anios, dias: st.dias, anio: st.anio, mes: st.mes
      });
      ['view', 'ambito', 'anios', 'dias', 'anio', 'mes'].forEach(function (k) {
        if (e0[k] !== undefined && e0[k] !== '') { st[k] = e0[k]; }
      });
      window.SISGrupo.onPayload(st.grupo, function (p) { render(p); });
      setTimeout(function () { if (!pintado) { propio(); } }, 1200);
      return;
    }

    propio();
  }

  function pintarBloque(box, p, modo, titulo) {
    var v = (p && p.view) || {};
    var a = v.analisis || null;
    C.quitarSkeleton(box);
    box.innerHTML = '';

    if (titulo) { box.appendChild(C.el('p', 'sis-g__analisis-titulo', C.esc(titulo))); }
    else if (v.name) { box.appendChild(C.el('p', 'sis-g__analisis-titulo', C.esc(v.name))); }

    /* Antes de cualquier texto, dónde y cuándo. Los párrafos explicativos son
       fijos: sin esta línea, el mismo texto valdría para «Nariño en 15 días» y
       para «Colombia en treinta años», que es justo lo que no puede pasar
       cuando se informa a la ciudadanía. */
    marco(box, v);

    var algo = false;
    if (modo === 'descripcion' && (v.descripcion_larga || v.description)) {
      box.appendChild(C.el('p', 'sis-g__analisis-desc', C.esc(v.descripcion_larga || v.description)));
      algo = true;
    }
    if (modo === 'como_funciona' && v.como_funciona) {
      box.appendChild(C.el('p', 'sis-g__analisis-desc', C.esc(v.como_funciona)));
      algo = true;
    }
    if ((modo === 'ambos' || modo === 'descriptivo') && a && a.descriptivo) {
      box.appendChild(C.el('p', 'sis-g__analisis-desc', C.esc(a.descriptivo)));
      algo = true;
    }
    if ((modo === 'ambos' || modo === 'cuantitativo') && a && a.cuantitativo) {
      box.appendChild(C.el('p', 'sis-g__analisis-num', C.esc(a.cuantitativo)));
      algo = true;
    }
    if (!algo) { box.appendChild(C.el('p', 'sis-g__analisis-desc', 'Sin contenido disponible para esta vista.')); }
  }

  /* Encabezado de ámbito y periodo, más la nota que explica un resultado
     vacío. Los dos los calcula el servidor, que es quien filtró. */
  function marco(destino, v) {
    if (v.encabezado) {
      destino.appendChild(C.el('p', 'sis-g__marco', C.esc(v.encabezado)));
    }
    if (v.nota_vacia) {
      destino.appendChild(C.el('p', 'sis-g__vacio', C.esc(v.nota_vacia)));
    }
  }

  function pintarAnalisis(fig, p, modo) {
    var prev = fig.querySelector('.sis-g__analisis');
    if (prev) { prev.parentNode.removeChild(prev); }
    if (modo === 'no') { return; }

    var v = (p && p.view) || {};
    var a = v.analisis || null;
    if (!a && !v.nota_vacia) { return; }
    a = a || {};

    var box = C.el('div', 'sis-g__analisis');
    /* Si la vista quedó sin datos, el propio gráfico ya publicó el encabezado
       y la explicación en su lugar: repetirlos aquí sería decir dos veces lo
       mismo en la misma tarjeta. Con datos, el encabezado sí encabeza. */
    var hayDatos = !!(p && p.data && p.data.length);
    if (hayDatos) { marco(box, v); }
    if ((modo === 'ambos' || modo === 'descriptivo') && a.descriptivo) {
      box.appendChild(C.el('p', 'sis-g__analisis-desc', C.esc(a.descriptivo)));
    }
    if ((modo === 'ambos' || modo === 'cuantitativo') && a.cuantitativo) {
      box.appendChild(C.el('p', 'sis-g__analisis-num', C.esc(a.cuantitativo)));
    }
    if (!box.childNodes.length) { return; }

    var pie = fig.querySelector('.sis-fuentes');
    if (pie) { fig.insertBefore(box, pie); } else { fig.appendChild(box); }
  }

  /* ================= Barra de herramientas ================= */

  function construirToolbar(fig, chartEl, titleEl, st) {
    if (fig.querySelector('.sis-g__toolbar')) { return; }

    var bar = C.el('div', 'sis-g__toolbar');
    bar.setAttribute('role', 'toolbar');
    bar.setAttribute('aria-label', 'Acciones del gráfico');

    st.actions.forEach(function (a) {
      if (a === 'cambiar') { bar.appendChild(swap(fig, chartEl, st)); return; }
      var b = C.el('button', 'sis-g__action');
      b.type = 'button';
      b.setAttribute('data-accion', a);
      b.innerHTML = '<span class="dashicons dashicons-' + ICON[a] + '" aria-hidden="true"></span><span class="sis-g__action-txt">' + C.esc(LABEL[a]) + '</span>';
      bar.appendChild(b);
    });

    bar.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('.sis-g__action') : null;
      if (!b) { return; }
      var a = b.getAttribute('data-accion');
      if (a === 'explicacion') { openModal(fig, '¿Cómo funciona este gráfico?', explicacionNodo(st.payload)); }
      else if (a === 'detalle') { openModal(fig, 'Detalle del gráfico', detalleNodo(st.payload)); }
      else if (a === 'datos') { openModal(fig, 'Datos de la vista', C.tablaScroll(tablaNodo(st.payload))); }
      else if (a === 'imagen') { exportarPNG(chartEl, st); }
      else if (a === 'descarga') { descargarJSON(st.payload, st); }
      else if (a === 'compartir') { compartir(fig, b); }
    });

    if (titleEl && titleEl.nextSibling) { fig.insertBefore(bar, titleEl.nextSibling); }
    else { fig.insertBefore(bar, chartEl); }
  }

  function swap(fig, chartEl, st) {
    var wrap = C.el('div', 'sis-g__swap');
    wrap.innerHTML = '<span class="dashicons dashicons-' + ICON.cambiar + '" aria-hidden="true"></span>' +
      '<span class="sis-g__action-txt">Cambiar</span><span class="sis-g__caret" aria-hidden="true">▾</span>';

    var sel = document.createElement('select');
    sel.setAttribute('aria-label', 'Cambiar tipo de gráfico');
    ((st.payload && st.payload.compatible) || []).forEach(function (t) {
      var o = document.createElement('option');
      o.value = t;
      o.textContent = TIPO_LABEL[t] || t;
      sel.appendChild(o);
    });
    sel.value = st.type;
    sel.addEventListener('change', function () {
      if (st.grupo && window.SISGrupo) { window.SISGrupo.set(st.grupo, { type: sel.value }); }
      else { reRender(fig, chartEl, st, sel.value); }
    });

    wrap.appendChild(sel);
    return wrap;
  }

  /* ================= Modales ================= */

  function getModal(fig) {
    var m = fig.querySelector('.sis-g__modal');
    if (m) { return m; }
    m = C.el('div', 'sis-g__modal');
    m.setAttribute('hidden', '');
    m.innerHTML = '<div class="sis-g__modal-back" data-cerrar="1"></div>' +
      '<div class="sis-g__modal-panel" role="dialog" aria-modal="true">' +
      '<div class="sis-g__modal-head"><strong class="sis-g__modal-title"></strong>' +
      '<button type="button" class="sis-g__modal-x" aria-label="Cerrar" data-cerrar="1">×</button></div>' +
      '<div class="sis-g__modal-body"></div></div>';
    fig.appendChild(m);
    m.addEventListener('click', function (e) {
      if (e.target.getAttribute('data-cerrar')) { m.setAttribute('hidden', ''); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { m.setAttribute('hidden', ''); }
    });
    return m;
  }

  function openModal(fig, titulo, nodo) {
    var m = getModal(fig);
    m.querySelector('.sis-g__modal-title').textContent = titulo;
    var body = m.querySelector('.sis-g__modal-body');
    body.innerHTML = '';
    body.appendChild(nodo);
    m.removeAttribute('hidden');
  }

  function explicacionNodo(p) {
    var v = (p && p.view) || {};
    var wrap = C.el('div');
    var txt = v.como_funciona || v.descripcion_larga || v.description || 'Sin explicación disponible para esta vista.';
    wrap.appendChild(C.el('p', 'sis-g__analisis-desc', C.esc(txt)));
    if (v.aviso) {
      wrap.appendChild(C.el('p', 'sis-g__aviso', C.esc(v.aviso)));
    }
    return wrap;
  }

  function detalleNodo(p) {
    var v = (p && p.view) || {};
    var ctx = v.contexto || {};
    var dl = C.el('dl', 'sis-g__dl');
    par(dl, 'Tipo de gráfico', (p && p.chart && p.chart.label) || '—');
    par(dl, 'Categoría', v.category || '—');
    par(dl, 'Ámbito', ctx.ambito_nombre || '—');
    par(dl, 'Ventana', ctx.anios ? ctx.anios + ' años' : 'todo el catálogo');
    par(dl, 'Magnitud mínima', ctx.min_mag ? String(ctx.min_mag) : 'sin filtro');
    par(dl, 'Dimensiones', (v.dimensions || []).join(', ') || '—');
    par(dl, 'Medidas', (v.measures || []).join(', ') || '—');
    par(dl, 'Filas', String((p && p.data) ? p.data.length : 0));
    if (v.description) { par(dl, 'Descripción', v.description); }
    return dl;
  }

  function par(dl, k, val) {
    dl.appendChild(C.el('dt', null, C.esc(k)));
    dl.appendChild(C.el('dd', null, C.esc(val)));
  }

  function tablaNodo(p) {
    var v = (p && p.view) || {};
    var cols = (v.dimensions || []).concat(v.measures || []);
    var data = (p && p.data) || [];
    var tabla = C.el('table', 'sis-g__tabla');

    var thead = C.el('thead'), trh = C.el('tr');
    cols.forEach(function (c) {
      trh.appendChild(C.el('th', null, C.esc(window.SISRenderer ? window.SISRenderer.etiqueta(c) : c)));
    });
    thead.appendChild(trh);
    tabla.appendChild(thead);

    var tbody = C.el('tbody');
    data.forEach(function (row) {
      var tr = C.el('tr');
      cols.forEach(function (c) {
        var val = row[c];
        var txt = (typeof val === 'number') ? C.num(val, (Math.round(val) === val ? 0 : 2)) : (val === null || val === undefined ? '' : String(val));
        tr.appendChild(C.el('td', null, C.esc(txt)));
      });
      tbody.appendChild(tr);
    });
    tabla.appendChild(tbody);
    return tabla;
  }

  /* ================= Exportar / compartir ================= */

  function nombre(st) {
    return 'sismos-' + (st.view || 'grafico') + '-' + (st.ambito || '') + '-' + (st.type || '');
  }

  function exportarPNG(chartEl, st) {
    var svg = chartEl.querySelector('svg');
    if (!svg) { return; }
    var rect = svg.getBoundingClientRect();
    var w = Math.max(320, Math.round(rect.width || svg.clientWidth || 800));
    var h = Math.max(240, Math.round(rect.height || svg.clientHeight || 500));

    var clone = svg.cloneNode(true);
    clone.setAttribute('width', w);
    clone.setAttribute('height', h);
    var xml = new XMLSerializer().serializeToString(clone);
    var url = URL.createObjectURL(new Blob([xml], { type: 'image/svg+xml;charset=utf-8' }));

    var img = new Image();
    img.onload = function () {
      try {
        var s = 2, cv = document.createElement('canvas');
        cv.width = w * s; cv.height = h * s;
        var ctx = cv.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, cv.width, cv.height);
        ctx.drawImage(img, 0, 0, cv.width, cv.height);
        URL.revokeObjectURL(url);
        cv.toBlob(function (blob) {
          if (blob) { descargar(blob, nombre(st) + '.png'); }
          else { descargarTexto(xml, nombre(st) + '.svg', 'image/svg+xml'); }
        });
      } catch (e) {
        URL.revokeObjectURL(url);
        descargarTexto(xml, nombre(st) + '.svg', 'image/svg+xml');
      }
    };
    img.onerror = function () {
      URL.revokeObjectURL(url);
      descargarTexto(xml, nombre(st) + '.svg', 'image/svg+xml');
    };
    img.src = url;
  }

  function descargarJSON(p, st) {
    var payload = {
      view: (p && p.view) || {},
      data: (p && p.data) || [],
      fuente: 'U.S. Geological Survey — Earthquake Hazards Program (dominio público)'
    };
    descargarTexto(JSON.stringify(payload, null, 2), nombre(st) + '.json', 'application/json');
  }

  function descargarTexto(txt, name, type) {
    descargar(new Blob([txt], { type: type + ';charset=utf-8' }), name);
  }

  function descargar(blob, name) {
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = name;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
  }

  function compartir(fig, btn) {
    var url = location.href.split('#')[0] + '#' + fig.id;
    var ok = function () {
      var t = btn.querySelector('.sis-g__action-txt');
      if (!t) { return; }
      var o = t.textContent;
      t.textContent = 'URL copiada';
      btn.classList.add('is-success');
      setTimeout(function () { t.textContent = o; btn.classList.remove('is-success'); }, 1600);
    };
    if (navigator.share) { navigator.share({ title: document.title, url: url }).catch(function () {}); return; }
    if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(url).then(ok).catch(function () {}); return; }
    try {
      var ta = document.createElement('textarea');
      ta.value = url;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      ok();
    } catch (e) { /* sin portapapeles disponible */ }
  }
})();
