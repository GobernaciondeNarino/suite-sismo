/* [sismos_datos] — botones de datos abiertos: JSON, CSV y enlace a la API. */
(function () {
  'use strict';
  var C = window.SIScore;

  var TITULOS = {
    eventos: 'catálogo de sismos',
    estadistica: 'indicadores estadísticos',
    recurrencia: 'recurrencia observada por magnitud'
  };

  C.ready(function () {
    Array.prototype.forEach.call(document.querySelectorAll('[data-sis-datos]'), init);
  });

  function init(box) {
    var q = C.consulta(box);
    var recurso = box.getAttribute('data-recurso') || 'eventos';
    var texto = box.getAttribute('data-texto') || ('Descargue el ' + (TITULOS[recurso] || recurso));

    C.quitarSkeleton(box);

    var base = C.cfg.rest + '/abierto/' + recurso;
    var qs = ['ambito=' + encodeURIComponent(q.ambito)];
    if (q.anios) { qs.push('anios=' + encodeURIComponent(q.anios)); }
    if (q.dias) { qs.push('dias=' + encodeURIComponent(q.dias)); }
    if (q.min_mag) { qs.push('min_mag=' + encodeURIComponent(q.min_mag)); }
    var sufijo = qs.join('&');

    var wrap = C.el('div', 'sis-datos__cuerpo');
    wrap.appendChild(C.el('p', 'sis-datos__texto', C.esc(texto)));

    var fila = C.el('div', 'sis-datos__botones');
    fila.appendChild(boton(base + '?' + sufijo + '&formato=json', 'JSON'));
    fila.appendChild(boton(base + '?' + sufijo + '&formato=csv', 'CSV'));
    fila.appendChild(boton(base + '?' + sufijo, 'Ver API', true));
    wrap.appendChild(fila);

    wrap.appendChild(C.el('p', 'sis-fuentes',
      'Datos del U.S. Geological Survey (dominio público). Elaboración: Gobernación de Nariño, CC BY 4.0.'));

    box.appendChild(wrap);
  }

  function boton(href, texto, nuevaPestana) {
    var a = C.el('a', 'sis-btn' + (nuevaPestana ? '' : ' sis-btn--primario'), C.esc(texto));
    a.href = href;
    if (nuevaPestana) {
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
    }
    return a;
  }
})();
