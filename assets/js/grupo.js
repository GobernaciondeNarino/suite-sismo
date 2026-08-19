/* Bus de estado por grupo: sincroniza gráficos, filtros y paneles que
   comparten el atributo `grupo`. Un filtro publica un cambio y todos los
   componentes del grupo se re-renderizan. Sin dependencias. */
(function () {
  'use strict';

  var grupos = {};   // id → {estado, subs[], payloadSubs[], payload}

  function crear(id) {
    if (!grupos[id]) {
      grupos[id] = { estado: {}, subs: [], payloadSubs: [], payload: null };
    }
    return grupos[id];
  }

  /** Registra un componente y devuelve el estado vigente del grupo. */
  function init(id, estadoInicial) {
    var g = crear(id);
    Object.keys(estadoInicial || {}).forEach(function (k) {
      if (g.estado[k] === undefined || g.estado[k] === '' || g.estado[k] === null) {
        g.estado[k] = estadoInicial[k];
      }
    });
    return g.estado;
  }

  /** Cambia parte del estado y notifica a los suscriptores. */
  function set(id, parcial) {
    var g = crear(id);
    var cambio = false;
    Object.keys(parcial || {}).forEach(function (k) {
      if (g.estado[k] !== parcial[k]) { g.estado[k] = parcial[k]; cambio = true; }
    });
    if (cambio) {
      g.subs.forEach(function (fn) {
        try { fn(g.estado); } catch (e) { /* un suscriptor roto no tumba al resto */ }
      });
    }
    return g.estado;
  }

  /** Escucha cambios de estado del grupo. */
  function subscribe(id, fn) {
    var g = crear(id);
    g.subs.push(fn);
    return function () {
      var i = g.subs.indexOf(fn);
      if (i >= 0) { g.subs.splice(i, 1); }
    };
  }

  /** Publica el último payload renderizado (lo emite el gráfico del grupo). */
  function payload(id, p) {
    var g = crear(id);
    g.payload = p;
    g.payloadSubs.forEach(function (fn) {
      try { fn(p); } catch (e) { /* idem */ }
    });
  }

  /** Escucha payloads; si ya hay uno, lo entrega de inmediato. */
  function onPayload(id, fn) {
    var g = crear(id);
    g.payloadSubs.push(fn);
    if (g.payload) {
      try { fn(g.payload); } catch (e) { /* idem */ }
    }
    return function () {
      var i = g.payloadSubs.indexOf(fn);
      if (i >= 0) { g.payloadSubs.splice(i, 1); }
    };
  }

  function estado(id) { return crear(id).estado; }

  window.SISGrupo = {
    init: init,
    set: set,
    subscribe: subscribe,
    payload: payload,
    onPayload: onPayload,
    estado: estado
  };
})();
