/* Panel de administración: acciones asíncronas (sincronizar, recalcular,
   probar) y copia de shortcodes al portapapeles. */
(function () {
  'use strict';

  var CFG = window.SISAdmin || { ajax: '', nonce: '' };

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-sis-accion],[data-sis-copiar]') : null;
    if (!btn) { return; }

    if (btn.hasAttribute('data-sis-copiar')) {
      copiar(btn, btn.getAttribute('data-sis-copiar'));
      return;
    }

    e.preventDefault();
    var accion = btn.getAttribute('data-sis-accion');
    var estado = estadoDe(btn);
    var original = btn.textContent;

    btn.disabled = true;
    btn.textContent = 'Trabajando…';
    if (estado) { estado.textContent = ''; }

    var cuerpo = 'action=sis_' + encodeURIComponent(accion) + '&nonce=' + encodeURIComponent(CFG.nonce);
    if (btn.getAttribute('data-fuente')) {
      cuerpo += '&fuente=' + encodeURIComponent(btn.getAttribute('data-fuente'));
    }

    fetch(CFG.ajax, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: cuerpo
    })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        var msg = (j && j.data && j.data.mensaje) ? j.data.mensaje : 'Sin respuesta.';
        if (j && j.data && j.data.detalle) { msg += ' — ' + j.data.detalle; }
        if (estado) { estado.textContent = msg; }
        if (accion === 'sincronizar') {
          setTimeout(function () { location.reload(); }, 1500);
        }
      })
      .catch(function () {
        if (estado) { estado.textContent = 'No se pudo completar la acción.'; }
      })
      .then(function () {
        btn.disabled = false;
        btn.textContent = original;
      });
  });

  /* Pestañas de un formulario, conmutadas en el navegador.

     No van por URL como las de la pantalla de elementos: aquí hay campos, y
     recargar para cambiar de pestaña perdería lo escrito y obligaría a guardar
     tres veces lo que es un solo ajuste.

     El marcado sale del servidor con todos los paneles visibles y la barra de
     pestañas oculta, así que sin JavaScript se ven los tres grupos seguidos y
     la pantalla se guarda igual. Esto solo la enciende. */
  function pestanas(caja) {
    var barra = caja.querySelector('.sis-tabs');
    var tabs = [].slice.call(caja.querySelectorAll('[role="tab"]'));
    var paneles = [].slice.call(caja.querySelectorAll('[role="tabpanel"]'));
    if (!barra || tabs.length < 2 || tabs.length !== paneles.length) { return; }

    barra.hidden = false;

    function mostrar(i, mover) {
      tabs.forEach(function (t, j) {
        var activa = i === j;
        t.classList.toggle('nav-tab-active', activa);
        t.setAttribute('aria-selected', activa ? 'true' : 'false');
        // Solo la pestaña activa entra en el orden de tabulación: dentro de un
        // grupo de pestañas se navega con las flechas, no con el tabulador.
        t.tabIndex = activa ? 0 : -1;
        paneles[j].hidden = !activa;
      });
      if (mover) { tabs[i].focus(); }
    }

    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { mostrar(i, false); });
      t.addEventListener('keydown', function (ev) {
        var paso = ev.key === 'ArrowRight' ? 1 : (ev.key === 'ArrowLeft' ? -1 : 0);
        if (ev.key === 'Home') { ev.preventDefault(); mostrar(0, true); return; }
        if (ev.key === 'End') { ev.preventDefault(); mostrar(tabs.length - 1, true); return; }
        if (!paso) { return; }
        ev.preventDefault();
        mostrar((i + paso + tabs.length) % tabs.length, true);
      });
    });

    mostrar(0, false);

    /* Si un campo inválido queda en una pestaña cerrada, el navegador no puede
       enseñarlo y el formulario parece no responder al guardar. Se abre la
       pestaña que lo contiene antes de que el navegador lo señale. */
    caja.addEventListener('invalid', function (ev) {
      var panel = ev.target.closest ? ev.target.closest('[role="tabpanel"]') : null;
      var i = paneles.indexOf(panel);
      if (i >= 0) { mostrar(i, false); }
    }, true);
  }

  ready(function () {
    [].slice.call(document.querySelectorAll('[data-sis-tabs]')).forEach(pestanas);
  });

  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
  }

  function estadoDe(btn) {
    var fila = btn.closest('td') || btn.closest('p') || btn.parentNode;
    return fila ? fila.querySelector('.sis-admin-estado') : null;
  }

  function copiar(btn, texto) {
    var ok = function () {
      var original = btn.textContent;
      btn.textContent = 'Copiado';
      setTimeout(function () { btn.textContent = original; }, 1400);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(texto).then(ok).catch(function () {});
      return;
    }
    try {
      var ta = document.createElement('textarea');
      ta.value = texto;
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
