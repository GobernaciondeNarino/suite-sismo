/* [sismos_estadistica] — ficha estadística del catálogo: completitud, valor b,
   energía liberada y periodos de retorno por magnitud. */
(function () {
  'use strict';
  var C = window.SIScore;

  C.ready(function () {
    Array.prototype.forEach.call(document.querySelectorAll('[data-sis-estadistica]'), init);
  });

  function init(box) {
    var q = C.consulta(box);
    cargar();

    function cargar() {
      C.rest('/estadistica', q)
        .then(function (r) { pintar(box, r); })
        .catch(function () { C.error(box, 'No se pudo calcular la estadística del catálogo.', cargar); });
    }
  }

  function pintar(box, r) {
    C.quitarSkeleton(box);
    var prev = box.querySelector('.sis-est__cuerpo');
    if (prev) { prev.parentNode.removeChild(prev); }

    var cuerpo = C.el('div', 'sis-est__cuerpo');
    var gr = r.gutenberg || {};

    var grid = C.el('div', 'sis-est__grid');
    tarjeta(grid, C.num(r.n), 'sismos en el catálogo');
    tarjeta(grid, C.num(r.anios, 1), 'años de registro');
    tarjeta(grid, C.num(gr.mc, 1), 'magnitud de completitud');
    tarjeta(grid, C.num(gr.b, 2), 'valor b (± ' + C.num(gr.b_error, 2) + ')');
    tarjeta(grid, C.num(gr.tasa_mc, 1), 'sismos/año sobre Mc');
    tarjeta(grid, C.num(r.magnitud ? r.magnitud.max : 0, 1), 'mayor magnitud registrada');
    cuerpo.appendChild(grid);

    if (r.umbrales && r.umbrales.length) {
      cuerpo.appendChild(C.el('h4', 'sis-pron__h', 'Periodo de retorno por magnitud'));
      var t = C.el('table', 'sis-g__tabla');
      var th = C.el('thead');
      th.innerHTML = '<tr><th>Magnitud</th><th>Tasa anual</th><th>Periodo de retorno</th><th>Probabilidad en 1 año</th></tr>';
      t.appendChild(th);
      var tb = C.el('tbody');
      r.umbrales.forEach(function (u) {
        var tr = C.el('tr');
        tr.appendChild(C.el('td', null, 'M ≥ ' + C.num(u.magnitud, 1)));
        tr.appendChild(C.el('td', null, C.num(u.tasa_anual, 3) + ' /año'));
        tr.appendChild(C.el('td', null, u.periodo_retorno ? C.num(u.periodo_retorno, 1) + ' años' : '—'));
        tr.appendChild(C.el('td', null, C.num(u.prob_1_anio, 1) + '%'));
        tb.appendChild(tr);
      });
      t.appendChild(tb);
      cuerpo.appendChild(t);
    }

    if (r.energia_tnt) {
      cuerpo.appendChild(C.el('p', 'sis-analisis',
        'Energía liberada en la ventana: <strong>' + C.num(r.energia_tnt, 0) +
        '</strong> toneladas equivalentes de TNT.'));
    }
    if (r.narrativa) {
      cuerpo.appendChild(C.el('p', 'sis-analisis', C.esc(r.narrativa)));
    }

    box.insertBefore(cuerpo, box.firstChild);
  }

  function tarjeta(grid, cifra, etiqueta) {
    var d = C.el('div', 'sis-est__celda');
    d.appendChild(C.el('span', 'sis-est__cifra', C.esc(cifra)));
    d.appendChild(C.el('span', 'sis-est__etq', C.esc(etiqueta)));
    grid.appendChild(d);
  }
})();
