/* [sismos_pronostico] — ficha del pronóstico probabilístico a 6 meses.
   Muestra el resumen, la evolución mes a mes con su banda de incertidumbre,
   las probabilidades por umbral de magnitud, el cambio respecto al pronóstico
   anterior y la advertencia sobre el alcance del método. El gráfico es SVG
   propio (sin dependencias) para que la ficha sea autónoma. */
(function () {
  'use strict';
  var C = window.SIScore;

  C.ready(function () {
    Array.prototype.forEach.call(document.querySelectorAll('[data-sis-pronostico]'), init);
  });

  function init(box) {
    var q = C.consulta(box);
    var modo = box.getAttribute('data-modo') || 'completo';
    var conGrafico = box.getAttribute('data-grafico') !== '0';

    cargar();

    function cargar() {
      C.rest('/pronostico', { ambito: q.ambito })
        .then(function (p) { pintar(box, p, modo, conGrafico); })
        .catch(function () { C.error(box, 'No se pudo calcular el pronóstico.', cargar); });
    }
  }

  function pintar(box, p, modo, conGrafico) {
    C.quitarSkeleton(box);
    var prev = box.querySelector('.sis-pron__cuerpo');
    if (prev) { prev.parentNode.removeChild(prev); }

    var cuerpo = C.el('div', 'sis-pron__cuerpo');
    var n = p.narrativa || {};

    if (!p.meses || !p.meses.length) {
      cuerpo.appendChild(C.el('p', 'sis-analisis', C.esc(p.mensaje || 'Sin pronóstico disponible todavía.')));
      box.insertBefore(cuerpo, box.firstChild);
      return;
    }

    /* Cabecera: ventana y cifra principal. */
    var cab = C.el('div', 'sis-pron__cab');
    cab.appendChild(C.el('span', 'sis-pron__etq', 'Pronóstico a ' + p.horizonte + ' meses'));
    cab.appendChild(C.el('span', 'sis-pron__ventana',
      C.esc(mesLegible(p.ventana.desde) + ' – ' + mesLegible(p.ventana.hasta))));
    cuerpo.appendChild(cab);

    var cifra = C.el('div', 'sis-pron__cifra');
    cifra.appendChild(C.el('span', 'sis-pron__num', C.num(p.total.esperados, 1)));
    cifra.appendChild(C.el('span', 'sis-pron__num-etq',
      'sismos esperados de magnitud ' + C.num(p.base.mc, 1) + ' o mayor' +
      ' (rango probable ' + C.num(p.total.banda_min, 1) + '–' + C.num(p.total.banda_max, 1) + ')'));
    cuerpo.appendChild(cifra);

    if (p.comparacion && p.comparacion.hay_anterior) {
      var cambio = C.el('p', 'sis-pron__cambio sis-pron__cambio--' + C.esc(p.comparacion.sentido), C.esc(p.comparacion.texto));
      cuerpo.appendChild(cambio);
    }

    if (modo === 'resumen') {
      cuerpo.appendChild(C.el('p', 'sis-analisis', C.esc(n.resumen || '')));
      cuerpo.appendChild(nota(p));
      box.insertBefore(cuerpo, box.firstChild);
      return;
    }

    /* Gráfico de la evolución esperada con banda. */
    if (conGrafico) {
      var g = C.el('div', 'sis-pron__grafico');
      g.appendChild(grafico(p));
      cuerpo.appendChild(g);
    }

    /* Umbrales de magnitud. */
    if (modo !== 'metodo' && p.umbrales && p.umbrales.length) {
      cuerpo.appendChild(C.el('h4', 'sis-pron__h', 'Probabilidad por umbral de magnitud'));
      cuerpo.appendChild(tablaUmbrales(p.umbrales));
    }

    /* Magnitud máxima esperada. */
    if (p.magnitud_maxima && p.magnitud_maxima.modal) {
      cuerpo.appendChild(C.el('p', 'sis-pron__mmax',
        'Sismo más grande esperado en la ventana: magnitud <strong>' + C.num(p.magnitud_maxima.modal, 1) +
        '</strong> · mediana ' + C.num(p.magnitud_maxima.p50, 1) +
        ' · percentil 90 ' + C.num(p.magnitud_maxima.p90, 1)));
    }

    if (modo === 'umbrales') {
      cuerpo.appendChild(nota(p));
      box.insertBefore(cuerpo, box.firstChild);
      return;
    }

    /* Método y limitaciones. */
    if (n.resumen) { cuerpo.appendChild(C.el('p', 'sis-analisis', C.esc(n.resumen))); }
    if (n.probabilidades) { cuerpo.appendChild(C.el('p', 'sis-analisis', C.esc(n.probabilidades))); }
    if (n.metodo) { cuerpo.appendChild(C.el('p', 'sis-analisis sis-mute-line', C.esc(n.metodo))); }

    if (p.base) {
      var dl = C.el('dl', 'sis-g__dl');
      par(dl, 'Último dato del catálogo', p.base.ultimo_evento + ' UTC');
      par(dl, 'Sismos usados', C.num(p.base.n_completos) + ' de ' + C.num(p.base.n_catalogo));
      par(dl, 'Magnitud de completitud', C.num(p.base.mc, 1));
      par(dl, 'Valor b', C.num(p.base.b, 2) + ' ± ' + C.num(p.base.b_error, 2));
      par(dl, 'Tasa anual observada', C.num(p.base.tasa_anual_mc, 1) + ' sismos/año');
      par(dl, 'Ventana del catálogo', C.num(p.base.anios_catalogo, 1) + ' años');
      cuerpo.appendChild(dl);
    }

    cuerpo.appendChild(nota(p));
    box.insertBefore(cuerpo, box.firstChild);
  }

  function nota(p) {
    return C.el('p', 'sis-pron__aviso', C.esc(p.limitaciones || ''));
  }

  function par(dl, k, v) {
    dl.appendChild(C.el('dt', null, C.esc(k)));
    dl.appendChild(C.el('dd', null, C.esc(v)));
  }

  function tablaUmbrales(umbrales) {
    var t = C.el('table', 'sis-g__tabla');
    var thead = C.el('thead');
    thead.innerHTML = '<tr><th>Magnitud</th><th>Probabilidad (6 meses)</th><th>Esperados</th><th>Periodo de retorno</th></tr>';
    t.appendChild(thead);

    var tb = C.el('tbody');
    umbrales.forEach(function (u) {
      var tr = C.el('tr');
      tr.appendChild(C.el('td', null, 'M ≥ ' + C.num(u.magnitud, 1)));

      var td = C.el('td');
      var barra = C.el('span', 'sis-pron__barra');
      var relleno = C.el('span', 'sis-pron__barra-in');
      relleno.style.width = Math.max(1, Math.min(100, u.probabilidad)) + '%';
      relleno.style.background = C.colorMagnitud(u.magnitud);
      barra.appendChild(relleno);
      td.appendChild(barra);
      td.appendChild(C.el('span', 'sis-pron__pct', C.num(u.probabilidad, 1) + '%'));
      tr.appendChild(td);

      tr.appendChild(C.el('td', null, C.num(u.esperados_6m, 2)));
      tr.appendChild(C.el('td', null, u.periodo_retorno ? C.num(u.periodo_retorno, 1) + ' años' : '—'));
      tb.appendChild(tr);
    });
    t.appendChild(tb);
    return t;
  }

  /** SVG propio: observado (12 meses) + esperado con banda de incertidumbre. */
  function grafico(p) {
    var NS = 'http://www.w3.org/2000/svg';
    var obs = (p.observado || []).slice(-12);
    var pron = p.meses || [];
    var W = 680, H = 240, m = { t: 14, r: 14, b: 34, l: 38 };
    var iw = W - m.l - m.r, ih = H - m.t - m.b;

    var puntos = obs.map(function (o) { return { x: o.mes, y: o.sismos, tipo: 'obs' }; })
      .concat(pron.map(function (f) { return { x: f.mes, y: f.esperados, min: f.banda_min, max: f.banda_max, tipo: 'pron' }; }));

    var maxv = 1;
    puntos.forEach(function (d) { maxv = Math.max(maxv, d.y, d.max || 0); });

    var n = puntos.length;
    var X = function (i) { return m.l + (n <= 1 ? iw / 2 : iw * i / (n - 1)); };
    var Y = function (v) { return m.t + ih - ih * (v / maxv); };

    var svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    svg.setAttribute('class', 'sis-grafico');
    svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', 'Sismos observados y esperados por mes');

    function nodo(tag, attrs) {
      var e = document.createElementNS(NS, tag);
      Object.keys(attrs).forEach(function (k) { e.setAttribute(k, attrs[k]); });
      return e;
    }

    // Ejes de referencia.
    svg.appendChild(nodo('line', { x1: m.l, y1: Y(0), x2: m.l + iw, y2: Y(0), stroke: '#e5e7eb' }));
    [0, maxv / 2, maxv].forEach(function (v) {
      var t = nodo('text', { x: m.l - 6, y: Y(v) + 3, 'font-size': 10, fill: '#9aa0aa', 'text-anchor': 'end' });
      t.textContent = C.num(v, v < 10 ? 1 : 0);
      svg.appendChild(t);
    });

    // Banda de incertidumbre del tramo pronosticado.
    var iPron = obs.length;
    if (pron.length) {
      var d = 'M' + X(iPron) + ',' + Y(pron[0].banda_max);
      for (var i = 0; i < pron.length; i++) { d += 'L' + X(iPron + i) + ',' + Y(pron[i].banda_max); }
      for (var j = pron.length - 1; j >= 0; j--) { d += 'L' + X(iPron + j) + ',' + Y(pron[j].banda_min); }
      d += 'Z';
      svg.appendChild(nodo('path', { d: d, fill: '#0080C3', opacity: 0.14 }));
    }

    // Línea observada.
    var dObs = '';
    obs.forEach(function (o, i) { dObs += (dObs ? 'L' : 'M') + X(i) + ',' + Y(o.sismos); });
    if (dObs) {
      svg.appendChild(nodo('path', { d: dObs, fill: 'none', stroke: '#003087', 'stroke-width': 2.2, 'stroke-linejoin': 'round' }));
    }

    // Línea pronosticada (arranca en el último observado: sin cortes).
    var dPron = '';
    if (obs.length) { dPron = 'M' + X(obs.length - 1) + ',' + Y(obs[obs.length - 1].sismos); }
    pron.forEach(function (f, i) { dPron += (dPron ? 'L' : 'M') + X(iPron + i) + ',' + Y(f.esperados); });
    if (dPron) {
      svg.appendChild(nodo('path', {
        d: dPron, fill: 'none', stroke: '#FF7300', 'stroke-width': 2.2,
        'stroke-dasharray': '6 4', 'stroke-linejoin': 'round'
      }));
    }

    // Etiquetas de mes (una de cada tres para que respiren).
    puntos.forEach(function (d, i) {
      if (i % 3 !== 0) { return; }
      var t = nodo('text', { x: X(i), y: H - 16, 'font-size': 9, fill: '#9aa0aa', 'text-anchor': 'middle' });
      t.textContent = mesLegible(d.x);
      svg.appendChild(t);
    });

    // Leyenda mínima.
    var lg = nodo('text', { x: m.l, y: H - 3, 'font-size': 9, fill: '#6b7280' });
    lg.textContent = 'Línea continua: observado · Línea punteada y banda: pronóstico con intervalo al 90%';
    svg.appendChild(lg);

    return svg;
  }

  function mesLegible(mes) {
    var nombres = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var m = /^(\d{4})-(\d{2})$/.exec(String(mes));
    if (!m) { return String(mes); }
    return nombres[Math.max(0, Math.min(11, parseInt(m[2], 10) - 1))] + ' ' + m[1];
  }
})();
