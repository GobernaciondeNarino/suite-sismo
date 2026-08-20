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
