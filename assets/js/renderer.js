/* Renderer genérico D3plus (capa 3 del motor de gráficos).
   Recibe un payload {chart, view, data, compatible} y dibuja un SVG
   interactivo: color POR SERIE (no por punto), ejes con título, tooltip con
   cabecera y cuerpo, y leyenda interactiva. Si D3plus no está disponible cae a
   un SVG propio, de modo que la página nunca se queda sin gráfico.
   API: window.SISRenderer.render(nodo, payload, opts) → instancia d3plus. */
(function () {
  'use strict';

  /* Paleta categórica institucional. */
  var PALETTE = [
    '#003087', '#0080C3', '#3EBA6A', '#FFC53B', '#FF7300', '#C0392B',
    '#844E80', '#1ABC9C', '#2ECC71', '#3498DB', '#F1C40F', '#34495E'
  ];

  /* Rampa de magnitud: frío (poco) → cálido (mucho). */
  var HEAT = ['#0080C3', '#1ABC9C', '#3EBA6A', '#FFC53B', '#FF7300', '#C0392B'];

  function hexLerp(c1, c2, t) {
    function h(c) { c = c.replace('#', ''); return [parseInt(c.slice(0, 2), 16), parseInt(c.slice(2, 4), 16), parseInt(c.slice(4, 6), 16)]; }
    var a = h(c1), b = h(c2);
    return 'rgb(' + Math.round(a[0] + (b[0] - a[0]) * t) + ',' + Math.round(a[1] + (b[1] - a[1]) * t) + ',' + Math.round(a[2] + (b[2] - a[2]) * t) + ')';
  }
  function heatColor(t) {
    t = Math.max(0, Math.min(1, t));
    var n = HEAT.length - 1, i = Math.min(n - 1, Math.floor(t * n));
    return hexLerp(HEAT[i], HEAT[i + 1], (t * n) - i);
  }
  /* Colorea cada dato por su VALOR sobre el rango de la medida. */
  function colorPorValor(data, campo) {
    var min = Infinity, max = -Infinity;
    data.forEach(function (r) {
      var v = +r[campo];
      if (!isNaN(v)) { if (v < min) { min = v; } if (v > max) { max = v; } }
    });
    var span = (max - min) || 1;
    return function (d) { var v = +d[campo]; return isNaN(v) ? HEAT[0] : heatColor((v - min) / span); };
  }

  /* Mapa estable grupo→color: colorear POR SERIE mantiene la leyenda limpia. */
  function colorPorGrupo(data, campo) {
    var grupos = [];
    data.forEach(function (r) { var g = r[campo]; if (grupos.indexOf(g) < 0) { grupos.push(g); } });
    var cmap = {};
    grupos.forEach(function (g, i) { cmap[g] = PALETTE[i % PALETTE.length]; });
    return function (d) { return cmap[d[campo]] || PALETTE[0]; };
  }

  /* Etiquetas legibles del dominio sísmico. */
  var ETIQUETAS = {
    mes: 'Mes', anio: 'Año', fecha: 'Fecha', dia: 'Día',
    sismos: 'Número de sismos', acumulado: 'Sismos acumulados',
    magnitud: 'Magnitud', magnitud_media: 'Magnitud media', magnitud_maxima: 'Magnitud máxima',
    observados: 'Sismos observados (acumulado)', ajuste: 'Ajuste Gutenberg-Richter',
    energia_tnt: 'Energía (toneladas de TNT)', profundidad: 'Profundidad (km)',
    rango_profundidad: 'Rango de profundidad', clase: 'Clase de magnitud',
    municipio: 'Municipio más cercano', subregion: 'Subregión', evento: 'Sismo',
    umbral: 'Umbral de magnitud', intervalo_medio: 'Intervalo medio observado (años)',
    observados: 'Sismos observados', tasa_anual_obs: 'Tasa anual observada',
    serie: 'Serie', _value: 'Valor', _metric: 'Serie'
  };
  function etiqueta(campo, fallback) {
    if (ETIQUETAS[campo]) { return ETIQUETAS[campo]; }
    if (!campo) { return fallback || ''; }
    return String(campo).charAt(0).toUpperCase() + String(campo).slice(1).replace(/_/g, ' ');
  }

  function isDerived(name) { return /^(total|pct_|banda_)/i.test(String(name)); }

  function reshapeWideToLong(data, dims, measures) {
    var keep = (measures || []).filter(function (m) { return !isDerived(m); });
    var out = [];
    data.forEach(function (r) {
      keep.forEach(function (m) {
        var row = { _metric: etiqueta(m), _value: +r[m] || 0 };
        (dims || []).forEach(function (d) { row[d] = r[d]; });
        out.push(row);
      });
    });
    return out;
  }

  /* Formato ancho→largo cuando hay varias medidas en una vista temporal:
     así cada medida se dibuja como una serie con su color y su leyenda. */
  function multiSerie(data, dims, measures) {
    var out = [];
    data.forEach(function (r) {
      measures.forEach(function (m) {
        var row = { _metric: etiqueta(m), _value: +r[m] };
        dims.forEach(function (d) { row[d] = r[d]; });
        if (!isNaN(row._value)) { out.push(row); }
      });
    });
    return out;
  }

  function call(viz, metodo, arg) {
    if (viz && typeof viz[metodo] === 'function') { viz[metodo](arg); }
    return viz;
  }

  function render(node, payload, opts) {
    opts = opts || {};
    var datos = (payload && payload.data) || [];
    if (node && !datos.length) { return vacio(node, payload); }
    if (!window.d3plus) { return fallbackSVG(node, payload); }
    try {
      return renderD3plus(node, payload, opts);
    } catch (e) {
      return fallbackSVG(node, payload);
    }
  }

  function renderD3plus(node, payload, opts) {
    var chart = payload.chart || {};
    var view = payload.view || {};
    var data = payload.data || [];
    var dims = view.dimensions || [];
    var measures = view.measures || [];
    var key = chart.key;

    var Cls = window.d3plus[chart.class];
    if (typeof Cls !== 'function') { throw new Error('Clase d3plus desconocida: ' + chart.class); }
    if (node) { node.innerHTML = ''; }

    var plotData = data.slice();
    var grupo = dims[0], xField = dims[0], yField = measures[0], stacked = false;

    if (key === 'stacked_bar' || key === 'stacked_area') {
      plotData = reshapeWideToLong(data, dims, measures);
      grupo = '_metric';
      yField = '_value';
      stacked = (key === 'stacked_bar');
    } else if (key === 'line' || key === 'area' || key === 'bar') {
      if (dims.length > 1 && dims[1]) {
        // La segunda dimensión ES la serie (p. ej. observado vs pronóstico).
        grupo = dims[1];
      } else if (measures.length > 1) {
        // Varias medidas sobre una dimensión: una serie por medida.
        plotData = multiSerie(data, dims, measures);
        grupo = '_metric';
        yField = '_value';
      } else {
        // Una sola dimensión y una medida: TODOS los puntos son UNA serie.
        // Agrupar por X convertiría cada punto en su propio grupo y la línea
        // no llegaría a dibujarse.
        grupo = '_serie';
        var nombreSerie = view.name || etiqueta(yField);
        plotData = data.map(function (r) {
          var o = {};
          for (var kk in r) { if (Object.prototype.hasOwnProperty.call(r, kk)) { o[kk] = r[kk]; } }
          o._serie = nombreSerie;
          return o;
        });
      }
    }

    /* Los dos tipos que no agregan necesitan una clave por fila: la dispersión
       una por punto y la matriz una por celda. Se calculan aquí para que la
       vista solo tenga que declarar sus dimensiones. */
    var serie = view.series || dims[0];
    if (key === 'plot') {
      xField = dims[0];
      yField = measures[0];
      plotData = data.map(function (r, i) {
        var o = copiar(r);
        o._id = r.id !== undefined ? String(r.id) : ('p' + i);
        if (!o[serie]) { o[serie] = view.name || 'Sismos'; }
        return o;
      });
    } else if (key === 'matrix') {
      yField = measures[0];
      plotData = data.map(function (r) {
        var o = copiar(r);
        o._celda = String(r[dims[0]]) + '|' + String(r[dims[1]]);
        return o;
      });
    }

    var viz = new Cls().select(node).data(plotData);
    call(viz, 'detectResize', true);
    call(viz, 'legend', opts.legend !== false);
    call(viz, 'legendPosition', opts.legendPos || 'bottom');
    call(viz, 'locale', 'es-CO');

    // Color: por VALOR en las vistas de magnitud marcadas como mapa de calor;
    // por SERIE en el resto.
    var esValor = ['bar', 'treemap', 'box_whisker'].indexOf(key) >= 0;
    if (view.heatmap && esValor && grupo !== '_metric') {
      call(viz, 'color', colorPorValor(plotData, yField));
      call(viz, 'legend', false); // el color codifica magnitud, no categorías
    } else {
      call(viz, 'color', colorPorGrupo(plotData, grupo));
    }
    if (opts.legendStyle === 'icons') { call(viz, 'legendConfig', { label: false }); }

    switch (key) {
      case 'bar':
        viz.groupBy(grupo).x(xField).y(yField);
        call(viz, 'discrete', 'x');
        if (stacked) { call(viz, 'stacked', true); }
        break;
      case 'stacked_bar':
        viz.groupBy(['_metric', dims[0]]).x(dims[0]).y('_value');
        call(viz, 'stacked', true);
        call(viz, 'discrete', 'x');
        break;
      case 'line':
      case 'area':
        viz.groupBy(grupo).x(xField).y(yField);
        break;
      case 'stacked_area':
        viz.groupBy('_metric').x(dims[0]).y('_value');
        break;
      case 'pie':
      case 'donut':
        viz.groupBy(dims[0]).value(measures[0]);
        break;
      case 'treemap':
        viz.groupBy([dims[0]]).sum(measures[0]);
        break;
      case 'box_whisker':
        // En D3plus v4 BoxWhisker hereda de Plot: se configura con x/y como
        // cualquier gráfico cartesiano. El .value() de la v2 ya no existe y
        // lanzaba, con lo que la tarjeta caía al SVG de reserva.
        viz.groupBy(dims[0]).x(dims[0]).y(measures[0]);
        call(viz, 'discrete', 'x');
        break;
      case 'plot':
        // Nube de puntos: cada fila es un sismo, no una categoría. D3plus
        // agrega por groupBy, así que sin una clave única por fila los 600
        // puntos se colapsarían en uno por serie. El primer nivel del grupo
        // es la serie que colorea y alimenta la leyenda; el segundo, el id.
        viz.groupBy([serie, '_id']).x(xField).y(yField);
        // El color va por la serie, no por el grupo compuesto: si no, cada
        // punto estrenaría color propio y la leyenda quedaría ilegible.
        call(viz, 'color', serie);
        call(viz, 'shapeConfig', { Line: { strokeWidth: 0 }, label: false });
        break;
      case 'matrix':
        // La celda necesita clave propia: agrupar solo por fila o por columna
        // sumaría la franja entera en un solo cuadro.
        viz.groupBy('_celda');
        call(viz, 'row', dims[0]);
        call(viz, 'column', dims[1]);
        if (view.orden && view.orden.length) { call(viz, 'columnConfig', { domain: view.orden }); }
        call(viz, 'color', colorPorValor(plotData, yField));
        call(viz, 'shapeConfig', { label: false });
        call(viz, 'legend', false);
        break;
      default:
        viz.groupBy(grupo).x(xField).y(yField);
    }

    /* En los gráficos cartesianos, D3plus v4 estampa el nombre de la serie
       dentro de cada forma. Con una sola serie eso repite el mismo texto en
       todas las barras y tapa el dato; la leyenda ya dice de qué serie se
       trata. En pastel, dona y treemap la etiqueta sí es la información
       principal, así que allí se conserva. */
    if (['bar', 'stacked_bar', 'line', 'area', 'stacked_area', 'box_whisker'].indexOf(key) >= 0) {
      call(viz, 'shapeConfig', { label: false });
    }

    /* Ejes y tooltip: siempre con título y cuerpo, nunca vacíos. */
    var dimX = dims[0];
    function tbodyRico() {
      var t = [];
      if (dimX) { t.push([etiqueta(dimX), function (r) { return r[dimX] !== undefined ? r[dimX] : ''; }]); }
      if (grupo && grupo !== dimX && grupo !== '_serie') {
        t.push([grupo === '_metric' ? 'Serie' : etiqueta(grupo), function (r) { return r[grupo] !== undefined ? r[grupo] : ''; }]);
      }
      var ms = (yField === '_value') ? ['_value'] : measures;
      ms.forEach(function (m) { t.push([etiqueta(m), function (r) { return fmt(r[m]); }]); });
      return t;
    }

    if (['bar', 'stacked_bar', 'line', 'area', 'stacked_area', 'box_whisker'].indexOf(key) >= 0) {
      call(viz, 'xConfig', { title: etiqueta(dimX) });
      call(viz, 'yConfig', { title: etiqueta(yField === '_value' ? (measures[0] || '_value') : yField) });
      call(viz, 'tooltipConfig', {
        title: function (d) { return String(d[grupo] !== undefined ? d[grupo] : (d[dimX] !== undefined ? d[dimX] : '')); },
        tbody: tbodyRico()
      });
    } else {
      call(viz, 'tooltipConfig', {
        title: function (d) { return String(d[dims[0]] !== undefined ? d[dims[0]] : ''); },
        tbody: (function () {
          var t = [];
          measures.forEach(function (m) { t.push([etiqueta(m), function (r) { return fmt(r[m]); }]); });
          return t;
        })()
      });
    }

    viz.render();
    return viz;
  }

  /* Copia superficial de una fila: los tipos que añaden campos calculados no
     deben mutar el payload que la tarjeta guarda para el modal de datos. */
  function copiar(r) {
    var o = {};
    for (var k in r) { if (Object.prototype.hasOwnProperty.call(r, k)) { o[k] = r[k]; } }
    return o;
  }

  function fmt(v) {
    if (typeof v !== 'number') { return v === null || v === undefined ? '' : String(v); }
    try { return v.toLocaleString('es-CO', { maximumFractionDigits: 2 }); } catch (e) { return String(v); }
  }

  /* ---------- Estado «sin datos» ---------- */
  function vacio(node, payload) {
    if (!node) { return null; }
    node.innerHTML = '';
    var v = (payload && payload.view) || {};
    var p = document.createElement('p');
    p.className = 'sis-g__analisis-desc';
    p.style.cssText = 'padding:18px;text-align:center;font-size:.9rem';
    p.textContent = 'Aún no hay datos para «' + (v.name || 'esta vista') + '» en el ámbito consultado. Sincroniza la fuente USGS en Sismos Nariño → Fuentes o amplía la ventana de años.';
    node.appendChild(p);
    return null;
  }

  /* ---------- Fallback SVG (si D3plus no carga) ---------- */
  var SVGNS = 'http://www.w3.org/2000/svg';
  function svgEl(name, attrs) {
    var e = document.createElementNS(SVGNS, name);
    for (var k in attrs) { if (Object.prototype.hasOwnProperty.call(attrs, k)) { e.setAttribute(k, attrs[k]); } }
    return e;
  }

  function fallbackSVG(node, payload) {
    if (!node) { return null; }
    var view = (payload && payload.view) || {};
    var chart = (payload && payload.chart) || {};
    var data = (payload && payload.data) || [];
    var dim = (view.dimensions || [])[0];
    var med = (view.measures || [])[0];
    if (!data.length || !med) { return vacio(node, payload); }
    node.innerHTML = '';

    var esLinea = ['line', 'area', 'stacked_area'].indexOf(chart.key) >= 0 || view.category === 'temporal';
    var W = 720, H = 340, m = { t: 16, r: 16, b: 46, l: 48 };
    var iw = W - m.l - m.r, ih = H - m.t - m.b;
    var svg = svgEl('svg', {
      viewBox: '0 0 ' + W + ' ' + H, 'class': 'sis-grafico',
      preserveAspectRatio: 'xMidYMid meet', role: 'img', 'aria-label': view.name || 'Gráfico'
    });

    var vals = data.map(function (r) { return +r[med] || 0; });
    var maxv = Math.max.apply(null, vals.concat([0]));
    var minv = Math.min.apply(null, vals.concat([0]));
    if (maxv === minv) { maxv = minv + 1; }
    function yy(v) { return m.t + ih - ((v - minv) / (maxv - minv)) * ih; }
    var base = (0 >= minv && 0 <= maxv) ? 0 : minv;
    svg.appendChild(svgEl('line', { x1: m.l, y1: yy(base), x2: m.l + iw, y2: yy(base), stroke: '#e5e7eb', 'stroke-width': 1 }));

    var n = data.length, color = PALETTE[0];
    function px(i) { return esLinea ? (m.l + (n > 1 ? (i / (n - 1)) * iw : iw / 2)) : (m.l + (iw / n) * (i + 0.5)); }

    if (esLinea) {
      var pts = data.map(function (r, i) { return px(i) + ',' + yy(+r[med] || 0); });
      svg.appendChild(svgEl('polyline', { points: pts.join(' '), fill: 'none', stroke: color, 'stroke-width': 2.4, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }));
      data.forEach(function (r, i) {
        var c = svgEl('circle', { cx: px(i), cy: yy(+r[med] || 0), r: 2.6, fill: color });
        var t = svgEl('title'); t.textContent = (r[dim] !== undefined ? r[dim] + ': ' : '') + fmt(+r[med]);
        c.appendChild(t);
        svg.appendChild(c);
      });
    } else {
      var bw = (iw / n) * 0.7;
      data.forEach(function (r, i) {
        var v = +r[med] || 0, x0 = m.l + (iw / n) * (i + 0.15);
        var ya = yy(Math.max(0, v)), yb = yy(Math.min(0, v));
        var rect = svgEl('rect', { x: x0, y: Math.min(ya, yb), width: bw, height: Math.max(1, Math.abs(yb - ya)), fill: color, rx: 2 });
        var t = svgEl('title'); t.textContent = (r[dim] !== undefined ? r[dim] + ': ' : '') + fmt(v);
        rect.appendChild(t);
        svg.appendChild(rect);
      });
    }

    var paso = Math.max(1, Math.ceil(n / 8));
    data.forEach(function (r, i) {
      if (i % paso !== 0) { return; }
      var t = svgEl('text', { x: px(i), y: H - 26, 'text-anchor': 'middle', 'font-size': 9, fill: '#9aa0aa' });
      t.textContent = String(r[dim] !== undefined ? r[dim] : '');
      svg.appendChild(t);
    });
    var nota = svgEl('text', { x: m.l, y: H - 8, 'font-size': 9, fill: '#9aa0aa' });
    nota.textContent = (view.name || '') + ' · vista simple (D3plus no disponible)';
    svg.appendChild(nota);

    node.appendChild(svg);
    return null;
  }

  window.SISRenderer = { render: render, PALETTE: PALETTE, etiqueta: etiqueta, colorPorValor: colorPorValor };
})();
