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

  /* Iconos en SVG, no en caracteres.

     La barra usaba «‹ ▶ ⏸ ›». Son tipografía, no iconos, y eso trae tres
     problemas: «‹» y «›» son comillas angulares —finas y pequeñas— que no se
     leen como «paso atrás» y «paso adelante»; «⏸» tiene presentación de
     emoji en Windows y Android, así que el botón de pausa salía en color
     mientras el resto de la barra es monocromo; y todos dependen de que la
     fuente del tema los traiga, cosa que no siempre pasa.

     Estos van dibujados, heredan el color del botón con currentColor y se
     escalan con él. «Anterior» y «siguiente» llevan la barra del salto: dicen
     «al sismo de al lado», no «desplázate». */
  var ICONOS = {
    anterior: '<path d="M15.5 5.5 8 12l7.5 6.5" /><path d="M6.5 5.5v13" />',
    siguiente: '<path d="M8.5 5.5 16 12l-7.5 6.5" /><path d="M17.5 5.5v13" />',
    // El triángulo va desplazado a la derecha a propósito: su masa está en la
    // base, así que centrado por geometría se ve corrido hacia la izquierda.
    play: '<path d="M9 5.5v13L19.5 12z" fill="currentColor" stroke="none" stroke-linejoin="round" />',
    pausa: '<path d="M9 5.5v13" /><path d="M15 5.5v13" />'
  };

  function icono(nombre) {
    var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    s.setAttribute('viewBox', '0 0 24 24');
    s.setAttribute('class', 'sis-ico');
    s.setAttribute('aria-hidden', 'true');
    s.setAttribute('focusable', 'false');
    // El trazo se define aquí y no en la hoja de estilos para que el icono
    // siga siendo correcto si alguien reutiliza el componente sin ella.
    s.setAttribute('fill', 'none');
    s.setAttribute('stroke', 'currentColor');
    s.setAttribute('stroke-width', '2');
    s.setAttribute('stroke-linecap', 'round');
    s.setAttribute('stroke-linejoin', 'round');
    s.innerHTML = ICONOS[nombre] || '';
    return s;
  }

  /* Pone un icono en un botón, reemplazando el que hubiera. */
  function ponerIcono(btn, nombre) {
    while (btn.firstChild) { btn.removeChild(btn.firstChild); }
    btn.appendChild(icono(nombre));
  }

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
      // El periodo del shortcode viaja con la petición: sin él, la barra
      // recorrería los últimos sismos sin más mientras el resto de la página
      // muestra la ventana que pidió quien maquetó.
      C.rest('/eventos', C.conPeriodo({ ambito: q.ambito, limite: limite }, q))
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

    function boton(nombreIcono, etiqueta, alPulsar) {
      var b = C.el('button', 'sis-tl__btn');
      b.type = 'button';
      ponerIcono(b, nombreIcono);
      // El icono es decorativo y el botón no lleva texto, así que el nombre
      // accesible tiene que venir de aquí. El title da la misma pista al ratón.
      b.setAttribute('aria-label', etiqueta);
      b.title = etiqueta;
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

      /* La barra se redibuja entera en cada repintado, así que el aviso del
         umbral —que lo escribe PHP— hay que apartarlo antes de vaciar la caja
         y devolverlo al final. Si no, desaparece en cuanto llegan los datos,
         que es justo cuando hace falta. */
      var nota = box.querySelector('.sis-nota--umbral');
      box.innerHTML = '';

      var cab = C.el('div', 'sis-tl__cab');

      // Marca institucional, desactivada por defecto: si el archivo no
      // estuviera, la imagen se retira sola y la barra sigue funcionando.
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

      var anterior = boton('anterior', 'Sismo anterior', function () {
        detener(play);
        mover(-1);
      });
      fila.appendChild(anterior);

      /* El botón de reproducción es de dos estados, así que se anuncia como
         tal: aria-pressed dice si está sonando y el nombre accesible cambia
         con él —«Reproducir» cuando está parado, «Pausar» cuando corre—, que
         es lo que lee un lector de pantalla. La clase is-playing es solo para
         que la hoja de estilos pueda distinguirlos. */
      var play = C.el('button', 'sis-tl__btn sis-tl__btn--play');
      play.type = 'button';
      play.setAttribute('aria-pressed', 'false');
      ponerIcono(play, 'play');
      play.setAttribute('aria-label', 'Reproducir la secuencia');
      play.title = 'Reproducir la secuencia';
      play.addEventListener('click', function () { alternarReproduccion(play); });
      fila.appendChild(play);

      fila.appendChild(boton('siguiente', 'Sismo siguiente', function () {
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

      /* El selector de velocidad va dentro de su etiqueta. Suelto, un menú que
         dice «Normal» junto a un botón de reproducción no se entiende hasta
         que se despliega; y el aria-label solo servía a quien usa lector de
         pantalla, no a quien mira. En pantalla estrecha la palabra se oculta y
         queda el menú, que ahí sí es evidente por vecindad. */
      var etqVel = C.el('label', 'sis-tl__velocidad');
      etqVel.appendChild(C.el('span', 'sis-tl__velocidad-txt', 'Velocidad'));

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
      etqVel.appendChild(vel);
      fila.appendChild(etqVel);

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

      if (nota) { box.appendChild(nota); }
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
      marcarPlay(play, true);
      var pos = aControl(st.indice);
      st.temporizador = setInterval(function () {
        pos = (pos + 1) % st.eventos.length;
        seleccionar(aDatos(pos), true);
      }, st.velocidad);
    }

    function detener(play) {
      st.reproduciendo = false;
      if (st.temporizador) { clearInterval(st.temporizador); st.temporizador = null; }
      if (play) { marcarPlay(play, false); }
    }

    /* Icono, etiqueta y estado del botón de reproducción, en un solo sitio:
       tenerlos repartidos entre arrancar y detener es como se acaba con un
       botón que dibuja «pausa» y sigue diciéndose «Reproducir». */
    function marcarPlay(play, sonando) {
      var etq = sonando ? 'Pausar la secuencia' : 'Reproducir la secuencia';
      ponerIcono(play, sonando ? 'pausa' : 'play');
      play.setAttribute('aria-label', etq);
      play.setAttribute('aria-pressed', sonando ? 'true' : 'false');
      play.title = etq;
      play.classList.toggle('is-playing', !!sonando);
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
