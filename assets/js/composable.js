/* [sismos_filtro] y [sismos_panel] — componentes composables enlazados por
   `grupo`. El filtro publica cambios en el bus (SISGrupo) y el panel muestra
   el detalle del gráfico vigente del grupo. */
(function () {
  'use strict';
  var C = window.SIScore;

  var TIPO_LABEL = {
    bar: 'Barras', stacked_bar: 'Barras apiladas', line: 'Líneas', area: 'Área',
    stacked_area: 'Área apilada', pie: 'Pastel', donut: 'Dona', treemap: 'Treemap',
    box_whisker: 'Caja y bigotes'
  };

  var VENTANAS = [
    { v: '', t: 'Todo el catálogo' },
    { v: '3', t: 'Últimos 3 años' },
    { v: '5', t: 'Últimos 5 años' },
    { v: '10', t: 'Últimos 10 años' },
    { v: '20', t: 'Últimos 20 años' }
  ];

  C.ready(function () {
    Array.prototype.forEach.call(document.querySelectorAll('[data-sis-filtro]'), initFiltro);
    Array.prototype.forEach.call(document.querySelectorAll('[data-sis-panel]'), initPanel);
  });

  /* ---------------- Filtro ---------------- */

  function initFiltro(box) {
    var grupo = box.getAttribute('data-grupo') || 'sismos';
    var control = box.getAttribute('data-control') || 'vista';
    var etiqueta = box.getAttribute('data-etiqueta') || '';

    if (!window.SISGrupo) { return; }
    var estado = window.SISGrupo.init(grupo, {});

    if (control === 'vista') {
      C.rest('/vistas').then(function (r) {
        pintarSelect(box, grupo, control, etiqueta || 'Conjunto de datos',
          (r.vistas || []).map(function (v) { return { v: v.id, t: v.name }; }), estado.view);
      }).catch(function () {
        C.error(box, 'No se pudo cargar el listado de vistas.', function () { initFiltro(box); });
      });
      return;
    }

    if (control === 'ambito') {
      var ambitos = (C.cfg.ambitos || []).map(function (a) { return { v: a.slug, t: a.nombre }; });
      pintarSelect(box, grupo, control, etiqueta || 'Ámbito espacial', ambitos, estado.ambito || C.cfg.ambito);
      return;
    }

    if (control === 'anios') {
      pintarSelect(box, grupo, control, etiqueta || 'Ventana temporal', VENTANAS, estado.anios || '');
      return;
    }

    // control === 'tipo': depende del payload vigente (tipos compatibles).
    window.SISGrupo.onPayload(grupo, function (p) {
      var opciones = ((p && p.compatible) || []).map(function (t) { return { v: t, t: TIPO_LABEL[t] || t }; });
      pintarSelect(box, grupo, 'tipo', etiqueta || 'Tipo de gráfico', opciones, (p.chart && p.chart.key) || '');
    });
  }

  function pintarSelect(box, grupo, clave, etiqueta, opciones, valor) {
    C.quitarSkeleton(box);
    box.innerHTML = '';

    var id = 'sis-f-' + grupo + '-' + clave;
    var lab = C.el('label', 'sis-filtro__label', C.esc(etiqueta));
    lab.setAttribute('for', id);

    var sel = document.createElement('select');
    sel.className = 'sis-filtro__select';
    sel.id = id;

    opciones.forEach(function (o) {
      var op = document.createElement('option');
      op.value = o.v;
      op.textContent = o.t;
      sel.appendChild(op);
    });
    if (valor !== undefined && valor !== null) { sel.value = valor; }

    sel.addEventListener('change', function () {
      var parcial = {};
      parcial[clave === 'vista' ? 'view' : clave] = sel.value;
      // Al cambiar de vista, el tipo anterior puede no ser compatible: se limpia.
      if (clave === 'vista') { parcial.type = ''; }
      window.SISGrupo.set(grupo, parcial);
    });

    box.appendChild(lab);
    box.appendChild(sel);
  }

  /* ---------------- Panel ---------------- */

  function initPanel(box) {
    var grupo = box.getAttribute('data-grupo') || 'sismos';
    if (!window.SISGrupo) { return; }

    window.SISGrupo.onPayload(grupo, function (p) {
      C.quitarSkeleton(box);
      box.innerHTML = '';
      if (!p || !p.view) { return; }

      var v = p.view;
      box.appendChild(C.el('p', 'sis-g__analisis-titulo', C.esc(v.name || '')));
      if (v.descripcion_larga || v.description) {
        box.appendChild(C.el('p', 'sis-g__analisis-desc', C.esc(v.descripcion_larga || v.description)));
      }
      if (v.analisis && v.analisis.cuantitativo) {
        box.appendChild(C.el('p', 'sis-g__analisis-num', C.esc(v.analisis.cuantitativo)));
      }

      var dl = C.el('dl', 'sis-g__dl');
      añadir(dl, 'Tipo', (p.chart && p.chart.label) || '—');
      añadir(dl, 'Ámbito', (v.contexto && v.contexto.ambito_nombre) || '—');
      añadir(dl, 'Filas', String((p.data || []).length));
      box.appendChild(dl);
    });
  }

  function añadir(dl, k, v) {
    dl.appendChild(C.el('dt', null, C.esc(k)));
    dl.appendChild(C.el('dd', null, C.esc(v)));
  }
})();
