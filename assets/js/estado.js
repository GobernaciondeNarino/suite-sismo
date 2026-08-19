/* [sismos_estado] — semáforo de actividad sísmica reciente.
   Pinta primero con la REST interna (dato consolidado del servidor) y, si el
   componente está en modo «vivo», refresca con el feed GeoJSON del USGS, que
   se regenera cada minuto. */
(function () {
  'use strict';
  var C = window.SIScore;

  C.ready(function () {
    Array.prototype.forEach.call(document.querySelectorAll('[data-sis-estado]'), init);
  });

  function init(box) {
    var q = C.consulta(box);
    var compacto = box.getAttribute('data-compacto') === '1';
    var vivo = box.getAttribute('data-vivo') !== '0';

    cargar();

    function cargar() {
      C.rest('/estado', q)
        .then(function (r) {
          pintar(box, r, compacto);
          if (vivo) { refrescarVivo(box, r, q, compacto); }
        })
        .catch(function () {
          C.error(box, 'No se pudo consultar la actividad sísmica.', cargar);
        });
    }
  }

  /** Superpone los sismos del feed en vivo sobre el resumen del servidor. */
  function refrescarVivo(box, base, q, compacto) {
    function tick() {
      C.feedVivo('all_day', q.ambito).then(function (r) {
        if (!r.eventos.length) { return; }
        var ultimo = r.eventos[r.eventos.length - 1];
        if (!base.ultimo || ultimo.ts > base.ultimo.ts) {
          base.ultimo = ultimo;
          base.conteos['24h'] = r.eventos.length;
          base.vivo = true;
          pintar(box, base, compacto);
        }
      }).catch(function () { /* el feed en vivo es un extra: si falla, no molesta */ });
    }
    tick();
    C.cadaMinuto(tick, 2);
  }

  function pintar(box, r, compacto) {
    C.quitarSkeleton(box);
    var prev = box.querySelector('.sis-estado__cuerpo');
    if (prev) { prev.parentNode.removeChild(prev); }

    var cuerpo = C.el('div', 'sis-estado__cuerpo');
    var u = r.ultimo;
    var nivel = r.nivel || { etiqueta: 'Sin datos', color: '#6b7280' };

    var chip = C.el('span', 'sis-chip', C.esc(nivel.etiqueta));
    chip.style.background = nivel.color;

    var cab = C.el('div', 'sis-estado__cab');
    cab.appendChild(chip);
    if (u) {
      var mag = C.el('span', 'sis-estado__mag', C.num(u.mag, 1));
      mag.style.color = C.colorMagnitud(u.mag);
      cab.appendChild(mag);
      cab.appendChild(C.el('span', 'sis-estado__mag-etq', 'magnitud del último sismo'));
    }
    cuerpo.appendChild(cab);

    if (u) {
      var info = C.el('div', 'sis-estado__info');
      info.appendChild(C.el('p', 'sis-estado__lugar', C.esc(u.lugar || 'Región de Nariño')));
      info.appendChild(C.el('p', 'sis-estado__meta',
        C.esc(C.fecha(u.fecha)) + ' · ' + C.esc(C.hace(u.ts)) +
        ' · profundidad ' + C.num(u.profundidad, 0) + ' km'));
      if (u.municipio) {
        info.appendChild(C.el('p', 'sis-estado__meta',
          'Municipio más cercano: ' + C.esc(titulo(u.municipio)) +
          (u.distancia_km ? ' (' + C.num(u.distancia_km, 0) + ' km)' : '')));
      }
      cuerpo.appendChild(info);
    }

    if (!compacto && r.conteos) {
      var grid = C.el('div', 'sis-estado__grid');
      [['24h', 'últimas 24 horas'], ['7d', 'última semana'], ['30d', 'últimos 30 días'], ['365d', 'último año']]
        .forEach(function (par) {
          var celda = C.el('div', 'sis-estado__celda');
          celda.appendChild(C.el('span', 'sis-estado__cifra', C.num(r.conteos[par[0]] || 0)));
          celda.appendChild(C.el('span', 'sis-estado__etq', par[1]));
          grid.appendChild(celda);
        });
      cuerpo.appendChild(grid);
    }

    if (r.narrativa) {
      cuerpo.appendChild(C.el('p', 'sis-analisis', C.esc(r.narrativa)));
    }

    box.insertBefore(cuerpo, box.firstChild);
  }

  function titulo(s) {
    return String(s).toLowerCase().replace(/(^|\s|-)([a-záéíóúüñ])/g, function (m, a, b) { return a + b.toUpperCase(); });
  }
})();
