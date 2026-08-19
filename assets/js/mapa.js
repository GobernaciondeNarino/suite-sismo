/* [sismos_mapa] — mapa de epicentros con Leaflet.
   Círculo por sismo: el radio codifica la magnitud y el color la profundidad.
   Opcionalmente dibuja los centroides de los 64 municipios de Nariño. */
(function () {
  'use strict';
  var C = window.SIScore;

  C.ready(function () {
    Array.prototype.forEach.call(document.querySelectorAll('[data-sis-mapa]'), init);
  });

  function init(box) {
    if (!window.L) { C.error(box, 'No se pudo cargar la librería de mapas.'); return; }

    var q = C.consulta(box);
    var lienzo = box.querySelector('.sis-mapa__lienzo');
    var conMunicipios = box.getAttribute('data-municipios') !== '0';
    var zoomFijo = parseInt(box.getAttribute('data-zoom') || '0', 10);

    var a = C.ambito(q.ambito) || {};
    var centro = (a.tipo === 'radio')
      ? [a.lat, a.lon]
      : [((a.lat_min + a.lat_max) / 2) || 1.2, ((a.lon_min + a.lon_max) / 2) || -77.5];

    var mapa = L.map(lienzo, { scrollWheelZoom: false }).setView(centro, zoomFijo || 7);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 12,
      attribution: '© OpenStreetMap · Sismos: USGS'
    }).addTo(mapa);

    if (a.tipo !== 'radio' && !zoomFijo) {
      mapa.fitBounds([[a.lat_min, a.lon_min], [a.lat_max, a.lon_max]]);
    }

    if (conMunicipios) { pintarMunicipios(mapa); }
    cargar();

    function cargar() {
      var p = { ambito: q.ambito, min_mag: q.min_mag, limite: 500 };
      if (q.dias) { p.dias = q.dias; }
      if (q.anios) { p.anios = q.anios; }

      C.rest('/eventos', p)
        .then(function (r) {
          C.quitarSkeleton(box);
          pintarSismos(mapa, r.eventos || []);
          leyenda(mapa);
        })
        .catch(function () { C.error(box, 'No se pudieron cargar los epicentros.', cargar); });
    }
  }

  function pintarSismos(mapa, eventos) {
    var capa = L.layerGroup().addTo(mapa);
    eventos.forEach(function (e) {
      var radio = Math.max(3, Math.pow(2, e.mag) * 0.55);   // escala perceptiva
      var c = L.circleMarker([e.lat, e.lon], {
        radius: radio,
        color: C.colorProfundidad(e.profundidad),
        fillColor: C.colorMagnitud(e.mag),
        fillOpacity: 0.65,
        weight: 1.2
      });
      c.bindPopup(
        '<strong>Magnitud ' + C.num(e.mag, 1) + '</strong><br>' +
        C.esc(e.lugar || '') + '<br>' +
        C.esc(C.fecha(e.fecha)) + '<br>' +
        'Profundidad: ' + C.num(e.profundidad, 0) + ' km' +
        (e.municipio ? '<br>Municipio más cercano: ' + C.esc(e.municipio) : '') +
        (e.url ? '<br><a href="' + C.esc(e.url) + '" target="_blank" rel="noopener noreferrer">Ficha del USGS</a>' : '')
      );
      capa.addLayer(c);
    });
  }

  function pintarMunicipios(mapa) {
    C.rest('/municipios').then(function (r) {
      var capa = L.layerGroup().addTo(mapa);
      (r.municipios || []).forEach(function (m) {
        capa.addLayer(
          L.circleMarker([m.lat, m.lon], {
            radius: 2.5, color: '#003087', weight: 1, fillOpacity: 0.9
          }).bindTooltip(m.nombre + ' · ' + m.subregion)
        );
      });
    }).catch(function () { /* la capa municipal es opcional */ });
  }

  function leyenda(mapa) {
    var ctrl = L.control({ position: 'bottomright' });
    ctrl.onAdd = function () {
      var div = L.DomUtil.create('div', 'sis-mapa__leyenda');
      div.innerHTML =
        '<strong>Magnitud</strong><br>' +
        fila('#0080C3', 'menor que 3') + fila('#3EBA6A', '3,0 – 4,4') +
        fila('#FFC53B', '4,5 – 5,4') + fila('#FF7300', '5,5 – 6,4') +
        fila('#C0392B', '6,5 o más') +
        '<br><strong>Borde: profundidad</strong><br>' +
        fila('#C0392B', 'superficial') + fila('#FF7300', 'intermedia') + fila('#0080C3', 'profunda');
      return div;
    };
    ctrl.addTo(mapa);
  }

  function fila(color, texto) {
    return '<span class="sis-mapa__sw" style="background:' + color + '"></span>' + texto + '<br>';
  }
})();
