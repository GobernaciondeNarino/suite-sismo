/* [sismos_ultimos] — lista de los sismos más recientes.
   Combina el catálogo consolidado del servidor con el feed en vivo del USGS
   (frescura ~1 min) y marca los eventos nuevos desde la última lectura. */
(function () {
  'use strict';
  var C = window.SIScore;

  C.ready(function () {
    Array.prototype.forEach.call(document.querySelectorAll('[data-sis-ultimos]'), init);
  });

  function init(box) {
    var q = C.consulta(box);
    var limite = parseInt(box.getAttribute('data-limite') || '10', 10);
    var vivo = box.getAttribute('data-vivo') !== '0';
    var vistos = {};

    cargar();

    function cargar() {
      var p = { ambito: q.ambito, min_mag: q.min_mag, limite: limite };
      if (q.dias) { p.dias = q.dias; }
      C.rest('/eventos', p)
        .then(function (r) {
          pintar(box, r.eventos || [], limite, vistos);
          if (vivo) {
            var tick = function () {
              C.feedVivo('all_day', q.ambito).then(function (f) {
                if (!f.eventos.length) { return; }
                var mezcla = fusionar(r.eventos || [], f.eventos);
                pintar(box, mezcla, limite, vistos);
              }).catch(function () { /* extra opcional */ });
            };
            tick();
            C.cadaMinuto(tick, 2);
          }
        })
        .catch(function () { C.error(box, 'No se pudieron cargar los últimos sismos.', cargar); });
    }
  }

  /** Deduplica por id y ordena del más reciente al más antiguo. */
  function fusionar(a, b) {
    var mapa = {};
    a.concat(b).forEach(function (e) { if (e && e.id) { mapa[e.id] = e; } });
    return Object.keys(mapa).map(function (k) { return mapa[k]; })
      .sort(function (x, y) { return y.ts - x.ts; });
  }

  function pintar(box, eventos, limite, vistos) {
    C.quitarSkeleton(box);
    var prev = box.querySelector('.sis-ultimos__lista');
    if (prev) { prev.parentNode.removeChild(prev); }

    var lista = C.el('ul', 'sis-ultimos__lista');
    lista.setAttribute('aria-live', 'polite');

    if (!eventos.length) {
      lista.appendChild(C.el('li', 'sis-ultimos__vacio', 'Sin sismos registrados en la ventana consultada.'));
    }

    eventos.slice(0, limite).forEach(function (e) {
      var li = C.el('li', 'sis-ultimos__item');
      if (!vistos[e.id]) { li.classList.add('is-nuevo'); }
      vistos[e.id] = true;

      var mag = C.el('span', 'sis-ultimos__mag', C.num(e.mag, 1));
      mag.style.background = C.colorMagnitud(e.mag);
      mag.setAttribute('title', 'Magnitud ' + C.num(e.mag, 1));

      var txt = C.el('div', 'sis-ultimos__txt');
      var lugar = e.url
        ? '<a href="' + C.esc(e.url) + '" target="_blank" rel="noopener noreferrer">' + C.esc(e.lugar || 'Ver detalle') + '</a>'
        : C.esc(e.lugar || '');
      txt.appendChild(C.el('span', 'sis-ultimos__lugar', lugar));
      txt.appendChild(C.el('span', 'sis-ultimos__meta',
        C.esc(C.hace(e.ts)) + ' · ' + C.num(e.profundidad, 0) + ' km de profundidad' +
        (e.tsunami ? ' · <strong>aviso de tsunami</strong>' : '')));

      li.appendChild(mag);
      li.appendChild(txt);
      lista.appendChild(li);
    });

    box.insertBefore(lista, box.firstChild);
  }
})();
