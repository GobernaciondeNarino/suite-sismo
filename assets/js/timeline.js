/* [sismos_timeline] — línea de tiempo de los últimos sismos.

   Recorre el mismo conjunto que dibuja el globo, del más antiguo al más
   reciente, y publica el evento 'sis:sismo' para que el globo enfoque el
   epicentro. Si no hay globo en la página, funciona igual: muestra la ficha
   del sismo seleccionado.

   Se sincroniza en los dos sentidos: al pulsar un sismo en el globo, la línea
   de tiempo se mueve sola. */
(function () {
  'use strict';
  var C = window.SIScore;

  C.ready(function () {
    Array.prototype.forEach.call(document.querySelectorAll('[data-sis-timeline]'), init);
  });

  function init(box) {
    var q = C.consulta(box);
    var limite = parseInt(box.getAttribute('data-limite') || '50', 10);
    var st = { eventos: [], indice: 0, reproduciendo: false, temporizador: null, velocidad: 1400, conjunto: 'local' };

    // Si el globo ya cargó el conjunto, se reaprovecha en vez de volver a
    // pedirlo. Y si el globo cambia de conjunto —al pasar a la vista global—
    // la línea de tiempo lo sigue: recorrer 50 sismos de Nariño mientras el
    // globo dibuja los del planeta sería mentir sobre lo que se está viendo.
    var recibido = false;
    window.addEventListener('sis:sismos-cargados', function (ev) {
      if (!ev.detail || !ev.detail.eventos) { return; }
      recibido = true;
      st.conjunto = ev.detail.conjunto || 'local';
      arrancar(ev.detail.eventos);
    });

    /* Si hay un globo en la página, va a pedir el mismo conjunto: se espera a
       que lo entregue en vez de duplicar la petición. Si no llega —porque el
       globo falló o el navegador no soporta WebGL— se pide igualmente, para
       que la línea de tiempo funcione sola. */
    var hayGlobo = !!document.querySelector('[data-sis-globo]');
    if (hayGlobo) {
      setTimeout(pedir, 4000);
    } else {
      pedir();
    }

    function pedir() {
      if (recibido) { return; }
      C.rest('/eventos', { ambito: q.ambito, limite: limite })
        .then(function (r) {
          if (recibido) { return; }
          recibido = true;
          arrancar(r.eventos || []);
        })
        .catch(function () {
          if (!recibido) { C.error(box, 'No se pudo cargar la línea de tiempo.', function () { init(box); }); }
        });
    }

    function arrancar(eventos) {
      detener(st.play);
      // El globo entrega del más reciente al más antiguo; la línea de tiempo se
      // lee al revés, del pasado al presente.
      st.eventos = eventos.slice(0, limite);
      if (!st.eventos.length) {
        C.quitarSkeleton(box);
        box.appendChild(C.el('p', 'sis-analisis', 'Sin sismos recientes en este ámbito.'));
        return;
      }
      pintar();
      seleccionar(0, false);
    }

    function boton(texto, etiqueta, alPulsar) {
      var b = C.el('button', 'sis-tl__btn');
      b.type = 'button';
      b.textContent = texto;
      b.setAttribute('aria-label', etiqueta);
      b.addEventListener('click', alPulsar);
      return b;
    }

    /* Avanza o retrocede un sismo, sin dar la vuelta: en los extremos se queda
       donde está para que se note que se llegó al principio o al final. */
    function mover(paso) {
      var pos = aControl(st.indice) + paso;
      if (pos < 0 || pos > st.eventos.length - 1) { return; }
      seleccionar(aDatos(pos), true);
    }

    /* El índice del control va del más antiguo (0) al más reciente (n-1);
       el índice de los datos es el inverso. */
    function aDatos(pos) { return st.eventos.length - 1 - pos; }
    function aControl(idx) { return st.eventos.length - 1 - idx; }

    function pintar() {
      C.quitarSkeleton(box);
      box.innerHTML = '';

      var cab = C.el('div', 'sis-tl__cab');

      // Marca institucional: si el archivo no estuviera, la imagen se retira
      // sola y la barra sigue funcionando.
      var logo = box.getAttribute('data-logo');
      if (logo) {
        var img = document.createElement('img');
        img.className = 'sis-tl__logo';
        img.src = logo;
        img.alt = 'Gobernación de Nariño · Secretaría TIC';
        img.onerror = function () { img.parentNode && img.parentNode.removeChild(img); };
        cab.appendChild(img);
      }

      // El título dice cuántos sismos se recorren y de dónde: al pasar el globo
      // a la vista global, la línea de tiempo recorre los del planeta.
      var ambitoTxt = 'mundo' === st.conjunto ? ' del mundo' : '';
      cab.appendChild(C.el('span', 'sis-tl__titulo', 'Últimos ' + st.eventos.length + ' sismos' + ambitoTxt));
      var ficha = C.el('span', 'sis-tl__ficha');
      cab.appendChild(ficha);
      box.appendChild(cab);

      var fila = C.el('div', 'sis-tl__fila');

      var anterior = boton('‹', 'Sismo anterior', function () {
        detener(play);
        mover(-1);
      });
      fila.appendChild(anterior);

      var play = C.el('button', 'sis-tl__btn sis-tl__btn--play');
      play.type = 'button';
      play.setAttribute('aria-label', 'Reproducir la secuencia');
      play.textContent = '▶';
      play.addEventListener('click', function () { alternarReproduccion(play); });
      fila.appendChild(play);

      fila.appendChild(boton('›', 'Sismo siguiente', function () {
        detener(play);
        mover(1);
      }));

      var rango = document.createElement('input');
      rango.type = 'range';
      rango.className = 'sis-tl__rango';
      rango.min = '0';
      rango.max = String(st.eventos.length - 1);
      rango.step = '1';
      rango.value = String(st.eventos.length - 1);
      rango.setAttribute('aria-label', 'Recorrer los sismos, del más antiguo al más reciente');
      rango.addEventListener('input', function () {
        detener(play);
        seleccionar(aDatos(parseInt(rango.value, 10)), true);
      });
      fila.appendChild(rango);

      var vel = document.createElement('select');
      vel.className = 'sis-tl__vel';
      vel.setAttribute('aria-label', 'Velocidad de reproducción');
      [['2200', 'Lento'], ['1400', 'Normal'], ['700', 'Rápido']].forEach(function (o) {
        var op = document.createElement('option');
        op.value = o[0];
        op.textContent = o[1];
        if (o[0] === '1400') { op.selected = true; }
        vel.appendChild(op);
      });
      vel.addEventListener('change', function () {
        st.velocidad = parseInt(vel.value, 10) || 1400;
        // Si está reproduciendo, se reinicia el temporizador con el nuevo ritmo.
        if (st.reproduciendo) { detener(play); alternarReproduccion(play); }
      });
      fila.appendChild(vel);

      box.appendChild(fila);

      // Tira de marcas: una por sismo, coloreada por magnitud.
      var tira = C.el('div', 'sis-tl__tira');
      tira.setAttribute('role', 'group');
      tira.setAttribute('aria-label', 'Sismos del periodo, uno por marca');
      st.eventos.slice().reverse().forEach(function (e, pos) {
        // El botón es el área táctil (alta y uniforme); la barra interior es
        // la que codifica la magnitud con su color y su altura.
        var m = C.el('button', 'sis-tl__marca');
        m.type = 'button';
        var barra = C.el('span', 'sis-tl__barra');
        barra.style.background = C.colorMagnitud(e.mag);
        barra.style.height = Math.round(6 + Math.max(0, e.mag - 2.5) * 5) + 'px';
        m.appendChild(barra);
        m.title = 'M ' + C.num(e.mag, 1) + ' · ' + (e.lugar || '');
        m.setAttribute('aria-label', 'Sismo de magnitud ' + C.num(e.mag, 1) + ' en ' + (e.lugar || ''));
        m.addEventListener('click', function () {
          detener(play);
          rango.value = String(pos);
          seleccionar(aDatos(pos), true);
        });
        tira.appendChild(m);
      });
      box.appendChild(tira);

      box.appendChild(C.el('p', 'sis-fuentes', 'Fuente: U.S. Geological Survey — Earthquake Hazards Program'));

      st.rango = rango;
      st.ficha = ficha;
      st.tira = tira;
      st.play = play;
    }

    function seleccionar(indice, enfocar) {
      st.indice = indice;
      var e = st.eventos[indice];
      if (!e) { return; }

      if (st.ficha) {
        st.ficha.innerHTML = '<strong style="color:' + C.esc(C.colorMagnitud(e.mag)) + '">M ' + C.esc(C.num(e.mag, 1)) + '</strong> · ' +
          C.esc(e.lugar || '') + ' · ' + C.esc((e.fecha || '').slice(0, 16)) + ' UTC · ' +
          C.esc(C.num(e.profundidad, 0)) + ' km';
      }
      if (st.rango) { st.rango.value = String(aControl(indice)); }
      if (st.tira) { marcarActiva(aControl(indice)); }

      window.dispatchEvent(new CustomEvent('sis:sismo', {
        detail: { indice: indice, evento: e, origen: 'timeline', enfocar: enfocar !== false }
      }));
    }

    function alternarReproduccion(play) {
      if (st.reproduciendo) { detener(play); return; }
      st.reproduciendo = true;
      play.textContent = '⏸';
      play.setAttribute('aria-label', 'Pausar la secuencia');
      var pos = aControl(st.indice);
      st.temporizador = setInterval(function () {
        pos = (pos + 1) % st.eventos.length;
        seleccionar(aDatos(pos), true);
      }, st.velocidad);
    }

    function detener(play) {
      st.reproduciendo = false;
      if (st.temporizador) { clearInterval(st.temporizador); st.temporizador = null; }
      if (play) {
        play.textContent = '▶';
        play.setAttribute('aria-label', 'Reproducir la secuencia');
      }
    }

    // El globo también manda: al pulsar un epicentro, la línea de tiempo sigue.
    window.addEventListener('sis:sismo', function (ev) {
      var d = ev.detail || {};
      if (d.origen !== 'globo' || typeof d.indice !== 'number') { return; }
      detener(st.play);
      st.indice = d.indice;
      if (st.rango) { st.rango.value = String(aControl(d.indice)); }
      seleccionarSinEmitir(d.indice);
    });

    function seleccionarSinEmitir(indice) {
      var e = st.eventos[indice];
      if (!e || !st.ficha) { return; }
      st.ficha.innerHTML = '<strong style="color:' + C.esc(C.colorMagnitud(e.mag)) + '">M ' + C.esc(C.num(e.mag, 1)) + '</strong> · ' +
        C.esc(e.lugar || '') + ' · ' + C.esc((e.fecha || '').slice(0, 16)) + ' UTC · ' +
        C.esc(C.num(e.profundidad, 0)) + ' km';
      if (st.tira) { marcarActiva(aControl(indice)); }
    }

    /* Resalta la marca y, si la tira se desplaza (pantallas estrechas), la
       trae al centro para que el recorrido siga siendo visible. */
    function marcarActiva(pos) {
      var activa = null;
      Array.prototype.forEach.call(st.tira.children, function (m, i) {
        var esta = i === pos;
        m.classList.toggle('is-activo', esta);
        if (esta) { activa = m; }
      });
      if (activa && st.tira.scrollWidth > st.tira.clientWidth + 1) {
        var destino = activa.offsetLeft - (st.tira.clientWidth - activa.offsetWidth) / 2;
        st.tira.scrollLeft = Math.max(0, destino);
      }
    }
  }
})();
