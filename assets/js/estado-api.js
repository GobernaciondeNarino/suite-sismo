/* [sismos_estado_api] — panel público de salud de las fuentes de datos. */
(function () {
  'use strict';
  var C = window.SIScore;

  var ETIQUETAS = {
    ok: ['Al día', '#3EBA6A'],
    atrasada: ['Atrasada', '#FFC53B'],
    error: ['Con error', '#C0392B'],
    inactiva: ['Desactivada', '#6b7280'],
    sin_datos: ['Sin sincronizar', '#0080C3']
  };

  C.ready(function () {
    Array.prototype.forEach.call(document.querySelectorAll('[data-sis-estado-api]'), init);
  });

  function init(box) {
    cargar();

    function cargar() {
      C.rest('/estado-apis')
        .then(function (r) { pintar(box, r.fuentes || []); })
        .catch(function () { C.error(box, 'No se pudo consultar el estado de las fuentes.', cargar); });
    }
  }

  function pintar(box, fuentes) {
    C.quitarSkeleton(box);
    box.innerHTML = '';

    var tabla = C.el('table', 'sis-g__tabla');
    var th = C.el('thead');
    th.innerHTML = '<tr><th>Fuente</th><th>Capa</th><th>Estado</th><th>Última sincronización</th><th>Resultado</th></tr>';
    tabla.appendChild(th);

    var tb = C.el('tbody');
    fuentes.forEach(function (f) {
      var e = ETIQUETAS[f.salud] || ETIQUETAS.sin_datos;
      var tr = C.el('tr');
      tr.appendChild(C.el('td', null, C.esc(f.nombre)));
      tr.appendChild(C.el('td', null, C.esc(f.capa)));

      var td = C.el('td');
      var chip = C.el('span', 'sis-chip', C.esc(e[0]));
      chip.style.background = e[1];
      td.appendChild(chip);
      tr.appendChild(td);

      tr.appendChild(C.el('td', null, f.ultima_sync ? C.esc(C.fecha(f.ultima_sync)) : '—'));
      tr.appendChild(C.el('td', null, C.esc(f.ultimo_resultado || '—')));
      tb.appendChild(tr);
    });

    tabla.appendChild(tb);
    box.appendChild(tabla);
    box.appendChild(C.el('p', 'sis-fuentes',
      'Servicios consultados: USGS FDSN Event Web Service y feeds GeoJSON de resumen.'));
  }
})();
