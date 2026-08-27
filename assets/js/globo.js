/* [sismos_globo] — Globo 3D de la sismicidad reciente (Three.js 0.160, módulo ES).

   Dibuja los últimos sismos del ámbito sobre un planeta que se puede girar y
   acercar. Cada sismo aporta DOS líneas sobre el mismo epicentro:

     · hacia AFUERA, la magnitud: la longitud crece con la magnitud y el color
       sigue el mapa de calor (azul → verde → amarillo → naranja → rojo);
     · hacia ADENTRO, la profundidad: el segmento se hunde hacia el centro del
       planeta en proporción a la profundidad focal, de modo que la nube de
       segmentos dibuja el plano de la placa que subduce.

   Alrededor de los epicentros se siembra un campo de partículas cuya intensidad
   acumula la energía de los sismos cercanos: es el mapa de calor propiamente
   dicho, y comparte escala de color con las líneas.

   Todo el dato viene de la REST del propio plugin (/eventos). El globo se
   sincroniza con [sismos_timeline] mediante el evento 'sis:sismo'.

   Rendimiento: pausa fuera del viewport, modo ligero en equipos modestos y
   respeto por prefers-reduced-motion. */

import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

var CFG = window.SISGLOBO || {
  rest: '', ambito: 'regional', limite: 50, autorotar: true,
  calidad: 'auto', textura: '', texturaLigera: '', mundo: '', geojson: '', geojsonDepto: ''
};

/* Lee del contenedor los data-* de consulta que publica el shortcode.

   El globo es un módulo ES y no depende de sis-core, así que repite aquí la
   lectura —son seis atributos— en vez de arrastrar el núcleo entero. El
   periodo ya viene normalizado desde PHP: de dias, anio, mes y anios solo
   llega lleno el que de verdad va a filtrar, y los demás vienen vacíos. */
function _consulta(cont) {
  var lee = function (n) { return (cont.getAttribute('data-' + n) || '').trim(); };

  var periodo = {};
  ['dias', 'anio', 'mes', 'anios'].forEach(function (k) {
    var v = lee(k);
    if (v) { periodo[k] = v; }
  });
  var mag = lee('min-mag');
  if (mag) { periodo.min_mag = mag; }

  var limite = parseInt(lee('limite'), 10);

  return {
    ambito: lee('ambito') || CFG.ambito || 'regional',
    limite: limite > 0 ? limite : (CFG.limite || 50),
    periodo: periodo,
    vista: lee('vista') || 'global'
  };
}

/* Cuántos sismos se piden para la vista global. El feed de resumen del USGS
   trae unos dos mil de magnitud 2,5 o mayor en un mes: el tope los admite
   todos para que el Cinturón de Fuego se vea completo. El peso se controla
   por otro lado —la respuesta viaja adelgazada a los siete campos que se
   pintan, y el campo de calor tiene presupuesto propio de partículas—, no
   recortando el mes que se pidió. */
var LIMITE_MUNDO = 2500;

/* Escala de color del mapa de calor, compartida con las líneas y la leyenda.
   Dominio fijo (magnitud 3 a 7) para que un color signifique lo mismo hoy y
   dentro de un mes, aunque cambie el conjunto de sismos cargado. */
var HEAT = [
  { m: 3.0, c: 0x0080C3 },
  { m: 4.0, c: 0x3EBA6A },
  { m: 5.0, c: 0xFFC53B },
  { m: 6.0, c: 0xFF7300 },
  { m: 7.0, c: 0xC0392B }
];
var _hc1 = new THREE.Color(), _hc2 = new THREE.Color(), _hcOut = new THREE.Color();

function colorPorMagnitud(mag) {
  var m = Math.max(HEAT[0].m, Math.min(HEAT[HEAT.length - 1].m, Number(mag) || 0));
  for (var i = 0; i < HEAT.length - 1; i++) {
    if (m <= HEAT[i + 1].m) {
      var t = (m - HEAT[i].m) / (HEAT[i + 1].m - HEAT[i].m);
      _hc1.setHex(HEAT[i].c);
      _hc2.setHex(HEAT[i + 1].c);
      return _hcOut.lerpColors(_hc1, _hc2, t);
    }
  }
  return _hcOut.setHex(HEAT[HEAT.length - 1].c);
}

/* Coordenadas geográficas → punto en la esfera. */
function latLngAVector3(lat, lng, radio) {
  radio = radio === undefined ? 1 : radio;
  var phi = (90 - lat) * Math.PI / 180;
  var theta = (lng + 180) * Math.PI / 180;
  return new THREE.Vector3(
    -radio * Math.sin(phi) * Math.cos(theta),
    radio * Math.cos(phi),
    radio * Math.sin(phi) * Math.sin(theta)
  );
}

/* Distancia ortodrómica aproximada en kilómetros (para el campo de calor). */
function distanciaKm(lat1, lon1, lat2, lon2) {
  var R = 6371.0088, rad = Math.PI / 180;
  var f1 = lat1 * rad, f2 = lat2 * rad;
  var df = (lat2 - lat1) * rad, dl = (lon2 - lon1) * rad;
  var a = Math.sin(df / 2) * Math.sin(df / 2) + Math.cos(f1) * Math.cos(f2) * Math.sin(dl / 2) * Math.sin(dl / 2);
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(Math.max(0, 1 - a)));
}

/* Qué fotografía del planeta descargar.

   No se decide con esLigero(), que mira el procesador para ajustar partículas
   y estrellas: aquí lo que está en juego son 1,2 MB de descarga, así que manda
   el tamaño de la pantalla y la conexión. Un portátil de cuatro núcleos con
   pantalla grande merece la textura buena; un teléfono en 3G, no. */
function fotoLigera() {
  if (CFG.calidad === 'alta') { return false; }
  if (CFG.calidad === 'ligera') { return true; }
  var red = navigator.connection || {};
  if (red.saveData) { return true; }
  if (/(^|-)2g|3g/.test(red.effectiveType || '')) { return true; }
  if ((navigator.deviceMemory || 8) <= 4) { return true; }
  return Math.max(window.innerWidth, window.innerHeight) < 900;
}

function esLigero() {
  if (CFG.calidad === 'ligera') { return true; }
  if (CFG.calidad === 'alta') { return false; }
  var nucleos = navigator.hardwareConcurrency || 4;
  var memoria = navigator.deviceMemory || 4;
  return nucleos <= 4 || memoria <= 4 || window.innerWidth < 700;
}

function esc(s) {
  return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}

function num(v, dec) {
  try { return Number(v).toLocaleString('es-CO', { minimumFractionDigits: dec || 0, maximumFractionDigits: dec || 0 }); }
  catch (e) { return String(v); }
}

/* Textura de punto suave: partículas redondas en vez de cuadradas. */
var _texPunto = null;
function texturaPunto() {
  if (_texPunto) { return _texPunto; }
  var c = document.createElement('canvas');
  c.width = 64; c.height = 64;
  var ctx = c.getContext('2d');
  var g = ctx.createRadialGradient(32, 32, 0, 32, 32, 32);
  g.addColorStop(0, 'rgba(255,255,255,1)');
  g.addColorStop(0.35, 'rgba(255,255,255,0.5)');
  g.addColorStop(1, 'rgba(255,255,255,0)');
  ctx.fillStyle = g;
  ctx.beginPath();
  ctx.arc(32, 32, 32, 0, Math.PI * 2);
  ctx.fill();
  _texPunto = new THREE.CanvasTexture(c);
  return _texPunto;
}

/* Planeta de respaldo dibujado en un canvas: océano en degradado con retícula
   de meridianos y paralelos. Se usa si la textura remota no carga, para que el
   componente nunca dependa de un tercero para funcionar. */
function texturaProcedural() {
  var c = document.createElement('canvas');
  c.width = 1024; c.height = 512;
  var ctx = c.getContext('2d');

  var g = ctx.createLinearGradient(0, 0, 0, 512);
  g.addColorStop(0, '#0b2135');
  g.addColorStop(0.5, '#123c5c');
  g.addColorStop(1, '#0b2135');
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, 1024, 512);

  ctx.strokeStyle = 'rgba(255,255,255,0.10)';
  ctx.lineWidth = 1;
  for (var lng = 0; lng <= 1024; lng += 1024 / 12) {
    ctx.beginPath(); ctx.moveTo(lng, 0); ctx.lineTo(lng, 512); ctx.stroke();
  }
  for (var lat = 0; lat <= 512; lat += 512 / 6) {
    ctx.beginPath(); ctx.moveTo(0, lat); ctx.lineTo(1024, lat); ctx.stroke();
  }
  ctx.strokeStyle = 'rgba(255,255,255,0.24)';
  ctx.beginPath(); ctx.moveTo(0, 256); ctx.lineTo(1024, 256); ctx.stroke();

  return new THREE.CanvasTexture(c);
}

/* ---------------- textura del planeta ----------------

   La Tierra se dibuja en el navegador a partir de la costa mundial que viaja
   con el plugin (TopoJSON de 54 KB), no de una fotografía por satélite de
   1,4 MB pedida a un tercero. Además de pesar veinticinco veces menos, permite
   elegir los colores: un planeta oscuro y de bajo contraste deja que los
   epicentros —que son el dato— dominen la escena.

   TopoJSON guarda los arcos como enteros cuantizados y en incrementos: cada
   par es la diferencia con el punto anterior, y el «transform» los devuelve a
   grados. Decodificarlo son veinte líneas y ahorra traer una librería. */

function arcosTopo(topo) {
  var t = topo.transform || { scale: [1, 1], translate: [0, 0] };
  return topo.arcs.map(function (arco) {
    var x = 0, y = 0;
    return arco.map(function (d) {
      x += d[0];
      y += d[1];
      return [x * t.scale[0] + t.translate[0], y * t.scale[1] + t.translate[1]];
    });
  });
}

/* Un anillo se describe como una lista de índices de arco; el índice negativo
   significa «este arco, del revés» y se codifica como ~i. */
function anilloTopo(indices, arcos) {
  var pts = [];
  indices.forEach(function (i) {
    var a = i < 0 ? arcos[~i].slice().reverse() : arcos[i];
    // El último punto de un arco es el primero del siguiente: se omite para no
    // repetirlo en la unión.
    pts = pts.concat(pts.length ? a.slice(1) : a);
  });
  return pts;
}

function poligonosTopo(topo, nombre) {
  var obj = (topo.objects || {})[nombre];
  if (!obj) { return []; }
  var arcos = arcosTopo(topo);
  var geoms = obj.type === 'GeometryCollection' ? obj.geometries : [obj];
  var salida = [];
  geoms.forEach(function (g) {
    if (g.type === 'Polygon') { salida.push(g.arcs.map(function (r) { return anilloTopo(r, arcos); })); }
    if (g.type === 'MultiPolygon') {
      g.arcs.forEach(function (p) { salida.push(p.map(function (r) { return anilloTopo(r, arcos); })); });
    }
  });
  return salida;
}

/* Lienzo equirectangular: la longitud es la x y la latitud la y, sin más
   proyección, que es justo lo que espera una esfera de three.js. */
function texturaMundo(topo, ligero) {
  var W = ligero ? 2048 : 4096;
  var H = W / 2;
  var c = document.createElement('canvas');
  c.width = W; c.height = H;
  var ctx = c.getContext('2d');

  var mar = ctx.createLinearGradient(0, 0, 0, H);
  mar.addColorStop(0, '#0a1d2e');
  mar.addColorStop(0.28, '#123f60');
  mar.addColorStop(0.5, '#15507a');
  mar.addColorStop(0.72, '#123f60');
  mar.addColorStop(1, '#0a1d2e');
  ctx.fillStyle = mar;
  ctx.fillRect(0, 0, W, H);

  var x = function (lon) { return (lon + 180) / 360 * W; };
  var y = function (lat) { return (90 - lat) / 180 * H; };

  /* La cartografía mundial se detiene en el borde sur de la Antártida —unos
     85,6° S— porque más abajo no hay costa que describir. Sin esto, el casquete
     queda como una franja de océano y el planeta enseña una costura. */
  var t = topo.transform || { translate: [-180, -90] };
  var borde = y(t.translate[1]);
  if (borde < H) {
    ctx.fillStyle = '#2e4a41';
    ctx.fillRect(0, borde, W, H - borde);
  }

  var polis = poligonosTopo(topo, 'land');
  ctx.fillStyle = '#2e4a41';
  ctx.strokeStyle = 'rgba(150, 200, 180, 0.30)';
  ctx.lineWidth = Math.max(1, W / 2048);

  polis.forEach(function (anillos) {
    ctx.beginPath();
    anillos.forEach(function (anillo) {
      anillo.forEach(function (p, i) {
        var px = x(p[0]), py = y(p[1]);
        if (i === 0) { ctx.moveTo(px, py); } else { ctx.lineTo(px, py); }
      });
      ctx.closePath();
    });
    // «evenodd» hace que los anillos interiores —lagos— queden como agua.
    ctx.fill('evenodd');
    ctx.stroke();
  });

  retícula(ctx, W, H);

  var tex = new THREE.CanvasTexture(c);
  if ('SRGBColorSpace' in THREE) { tex.colorSpace = THREE.SRGBColorSpace; }
  tex.anisotropy = 4;
  return tex;
}

/* Meridianos y paralelos, tenues: dan sensación de esfera sin competir con los
   epicentros. Se dibujan sobre la tierra y sobre el mar por igual. */
function retícula(ctx, W, H) {
  ctx.strokeStyle = 'rgba(255,255,255,0.07)';
  ctx.lineWidth = Math.max(1, W / 2048);
  var paso = W / 12;
  for (var lng = 0; lng <= W; lng += paso) {
    ctx.beginPath(); ctx.moveTo(lng, 0); ctx.lineTo(lng, H); ctx.stroke();
  }
  for (var lat = 0; lat <= H; lat += H / 6) {
    ctx.beginPath(); ctx.moveTo(0, lat); ctx.lineTo(W, lat); ctx.stroke();
  }
  ctx.strokeStyle = 'rgba(255,255,255,0.16)';
  ctx.beginPath(); ctx.moveTo(0, H / 2); ctx.lineTo(W, H / 2); ctx.stroke();
}

/* Anillos exteriores de un feature GeoJSON, en pares [lng, lat]. */
function anillosDeFeature(feat) {
  var g = feat.geometry || {};
  if (g.type === 'Polygon') { return [g.coordinates[0]]; }
  if (g.type === 'MultiPolygon') { return g.coordinates.map(function (p) { return p[0]; }); }
  return [];
}

/* ================================================================= */

class GloboSismico {

  constructor(cont) {
    this.cont = cont;
    // Los sobrepuestos —tooltip incluido— se posicionan contra la escena, que
    // es el marco del planeta; el componente además lleva textos al pie.
    this.escenaHTML = cont.querySelector('.sis-globo__escena') || cont;
    this.lienzo = cont.querySelector('.sis-globo__lienzo') || cont;
    this.ligero = esLigero();
    this.reducido = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    this.visible = true;
    this.eventos = [];
    this.seleccionado = -1;
    this._camTransicion = null;
    this._t = 0;

    this.capas = { sismos: true, calor: true, municipios: true, profundidad: true };

    /* El ámbito y el periodo se leen del propio contenedor, no de la
       configuración global: SISGLOBO es una sola variable por documento, así
       que con dos globos en una página el segundo se llevaba los ajustes del
       primero, y el periodo del shortcode —dias, anio, mes, anios— no llegaba
       nunca a la petición. Los data-* ya vienen normalizados desde PHP. */
    this.consulta = _consulta(cont);

    this._escena();
    this._planeta();
    this._estrellas();
    this._grupoDatos();
    this._interaccion();
    this._controlesUI();
    this._sincronizacion();
    this._cargar();
    this._loop();
  }

  _ancho() { return this.lienzo.clientWidth || 480; }

  _alto() {
    var h = this.lienzo.clientHeight || 0;
    if (h > 120) { return h; }
    return Math.max(320, Math.round(this._ancho() * 0.62));
  }

  /* ---------------- escena ---------------- */

  _escena() {
    var self = this;

    this.escena = new THREE.Scene();
    this.escena.background = new THREE.Color(0x00080f);

    this.camara = new THREE.PerspectiveCamera(45, this._ancho() / this._alto(), 0.1, 100);
    /* Encuadre de la vista mundial, que es con la que abre el globo: el
       planeta tiene que llenar el marco sin que las astas más altas se salgan
       por el borde. Se calcula, no se fija: el campo de visión vertical es de
       45°, así que la distancia que encuadra bien un lienzo apaisado deja el
       planeta cortado en uno vertical. */
    this.camDefault = new THREE.Vector3(0, 0, 4);
    // La vista de los datos se calcula al cargarlos; hasta entonces, el centro
    // del ámbito sirve de aproximación.
    this.camDatos = latLngAVector3(0.5, -80, 2.9);
    this.camNarino = latLngAVector3(1.3, -77.5, 2.1);
    this._encuadrarMundo();
    this.camara.position.copy(this.camDatos);

    this.renderer = new THREE.WebGLRenderer({ antialias: !this.ligero, alpha: false });
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, this.ligero ? 1 : 2));
    this.renderer.setSize(this._ancho(), this._alto());
    this.lienzo.appendChild(this.renderer.domElement);

    this.escena.add(new THREE.AmbientLight(0xffffff, 0.42));
    this.escena.add(new THREE.HemisphereLight(0xbfd4ff, 0x101c28, 0.35));
    var sol = new THREE.DirectionalLight(0xffffff, 1.15);
    sol.position.set(4, 2.5, 4.5);
    this.escena.add(sol);

    this.controles = new OrbitControls(this.camara, this.renderer.domElement);
    this.controles.enableDamping = true;
    this.controles.dampingFactor = 0.07;
    this.controles.rotateSpeed = 0.55;
    this.controles.minDistance = 1.35;
    this.controles.maxDistance = 8;
    this.controles.autoRotate = !!CFG.autorotar && !this.reducido;
    this.controles.autoRotateSpeed = 0.32;

    window.addEventListener('resize', function () { self.redimensionar(); });
    if ('ResizeObserver' in window) {
      new ResizeObserver(function () { self.redimensionar(); }).observe(this.lienzo);
    }
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (es) { self.visible = es[0].isIntersecting; }, { threshold: 0.05 })
        .observe(this.lienzo);
    }
  }

  redimensionar() {
    if (!this.renderer) { return; }
    var a = this._ancho(), h = this._alto();
    this.camara.aspect = a / h;
    this.camara.updateProjectionMatrix();
    this.renderer.setSize(a, h);

    // Al cambiar la forma del lienzo (girar el teléfono, redimensionar la
    // ventana) el encuadre de la nube ya no sirve: se recalcula para la
    // próxima vez que se pida la vista «Zona sísmica». La cámara no se mueve
    // sola: mover el punto de vista sin que nadie lo pida desorienta.
    if (this.centroDatos) {
      this.camDatos = this.centroDatos.clone().multiplyScalar(this._distanciaEncuadre());
    }
    this._encuadrarMundo();
  }

  /* ---------------- planeta y atmósfera ---------------- */

  _planeta() {
    var self = this;
    var seg = this.ligero ? 48 : 72;

    this.globo = new THREE.Mesh(
      new THREE.SphereGeometry(1, seg, seg),
      new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 1, metalness: 0, map: texturaProcedural() })
    );
    this.escena.add(this.globo);

    /* La Tierra llega en dos tiempos: el planeta con retícula ya está en
       pantalla y se sustituye cuando su textura termina de cargar, así que
       nunca hay un hueco esperando a una imagen.

       Los dos caminos son excluyentes a propósito. Si se lanzaran a la vez
       competirían, y el que terminara último pisaría al otro: el mapa
       vectorial gana casi siempre la carrera —es más pequeño— y la fotografía
       no llegaba a verse nunca. El mapa queda como respaldo si la foto falla. */
    var foto = fotoLigera() && CFG.texturaLigera ? CFG.texturaLigera : CFG.textura;

    if (foto) {
      new THREE.TextureLoader().load(
        foto,
        function (tex) {
          tex.colorSpace = THREE.SRGBColorSpace;
          tex.anisotropy = 4;
          self._aplicarTextura(tex);
        },
        undefined,
        function () { self._texturaMapa(); }
      );
    } else {
      this._texturaMapa();
    }

    // Atmósfera: halo fresnel dibujado por la cara interna de una esfera mayor.
    this.atmosfera = new THREE.Mesh(
      new THREE.SphereGeometry(1.045, seg, seg),
      new THREE.ShaderMaterial({
        transparent: true,
        side: THREE.BackSide,
        blending: THREE.AdditiveBlending,
        uniforms: { uColor: { value: new THREE.Color(0x4fa8e0) } },
        vertexShader: [
          'varying vec3 vNormal;',
          'void main() {',
          '  vNormal = normalize(normalMatrix * normal);',
          '  gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);',
          '}'
        ].join('\n'),
        fragmentShader: [
          'uniform vec3 uColor;',
          'varying vec3 vNormal;',
          'void main() {',
          '  float intensidad = pow(0.62 - dot(vNormal, vec3(0.0, 0.0, 1.0)), 2.4);',
          '  gl_FragColor = vec4(uColor, 1.0) * intensidad;',
          '}'
        ].join('\n')
      })
    );
    this.escena.add(this.atmosfera);
  }

  /* Tierra dibujada en el navegador desde la costa mundial del propio sitio. */
  _texturaMapa() {
    var self = this;
    if (!CFG.mundo) { return; }
    fetch(CFG.mundo)
      .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
      .then(function (topo) { self._aplicarTextura(texturaMundo(topo, self.ligero)); })
      .catch(function () { /* se conserva el planeta con retícula */ });
  }

  /* Sustituye el mapa del planeta liberando el anterior: una textura de 4096
     píxeles ocupa memoria de GPU y el navegador no la recoge solo. */
  _aplicarTextura(tex) {
    if (!this.globo) { return; }
    var vieja = this.globo.material.map;
    this.globo.material.map = tex;
    this.globo.material.needsUpdate = true;
    if (vieja && vieja !== tex) { vieja.dispose(); }
  }

  _estrellas() {
    var n = this.ligero ? 700 : 1600;
    var pos = new Float32Array(n * 3);
    for (var i = 0; i < n; i++) {
      var v = new THREE.Vector3(Math.random() - 0.5, Math.random() - 0.5, Math.random() - 0.5)
        .normalize()
        .multiplyScalar(18 + Math.random() * 22);
      pos[i * 3] = v.x; pos[i * 3 + 1] = v.y; pos[i * 3 + 2] = v.z;
    }
    var geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    this.escena.add(new THREE.Points(geo, new THREE.PointsMaterial({
      size: 0.09, color: 0xbcd4f0, transparent: true, opacity: 0.65, depthWrite: false, sizeAttenuation: true
    })));
  }

  _grupoDatos() {
    this.gSismos = new THREE.Group();
    this.gCalor = new THREE.Group();
    this.gMapa = new THREE.Group();
    this.escena.add(this.gSismos);
    this.escena.add(this.gCalor);
    this.escena.add(this.gMapa);
  }

  /* ---------------- datos ---------------- */

  _cargar() {
    var self = this;

    this.conjuntos = { local: null, mundo: null };
    this.conjunto = 'local';

    if (CFG.geojsonDepto) { this._pintarContorno(CFG.geojsonDepto, 0xFFD500, 0.9, 1.004); }
    if (CFG.geojson) { this._pintarContorno(CFG.geojson, 0x8fb3d9, 0.42, 1.002); }

    /* Un planeta que se abre mostrando cincuenta puntos sobre Nariño y el
       resto del mundo vacío hace creer que solo tiembla aquí. Por eso la
       vista de partida es la mundial: el globo abre con la sismicidad del
       planeta del periodo pedido, y las vistas «Zona sísmica» y «Nariño»
       cambian a los sismos del ámbito publicado en el shortcode. */
    if ('global' === this.consulta.vista) {
      this._arrancarEnMundo();
      return;
    }

    this._cargarLocal()
      .then(function () {
        self._aplicarConjunto('local', true);
        self._quitarSkeleton();
      })
      .catch(function () { self._error('No se pudieron cargar los sismos del globo.'); });
  }

  /* Pide el conjunto del ámbito del shortcode y lo guarda, sin pintarlo:
     quién se dibuja lo decide _aplicarConjunto. Separar las dos cosas es lo
     que permite precargarlo en segundo plano mientras se mira el mundo. */
  _cargarLocal() {
    var self = this;
    if (this.conjuntos.local) { return Promise.resolve(this.conjuntos.local); }
    if (this._pidiendoLocal) { return this._pidiendoLocal; }

    this._pidiendoLocal = this._pedirEventos(this.consulta.ambito, this.consulta.limite, this.consulta.periodo)
      .then(function (eventos) {
        self.conjuntos.local = eventos;
        self._pidiendoLocal = null;
        return eventos;
      })
      .catch(function (e) {
        self._pidiendoLocal = null;
        throw e;
      });

    return this._pidiendoLocal;
  }

  /* Arranque en la vista mundial. El conjunto del ámbito se pide después, en
     segundo plano: es pequeño y así los botones «Zona sísmica» y «Nariño»
     responden al instante cuando alguien los pulsa. */
  _arrancarEnMundo() {
    var self = this;

    this._marcarVista('global');
    this.camara.position.copy(this.camDefault);
    this.controles.update();

    this._cargarMundo()
      .then(function () {
        self._aplicarConjunto('mundo', false);
        self._quitarSkeleton();
        // Precarga silenciosa: si falla, no se dice nada. El botón que la
        // necesite volverá a pedirla y entonces sí avisará.
        self._cargarLocal().catch(function () {});
      })
      .catch(function () {
        // Si el feed mundial no llega, el globo no se queda en blanco: cae al
        // ámbito del shortcode, que se sirve del catálogo local.
        self._cargarLocal()
          .then(function () {
            self._aplicarConjunto('local', true);
            self._quitarSkeleton();
          })
          .catch(function () { self._error('No se pudieron cargar los sismos del globo.'); });
      });
  }

  /* Deja marcado en la botonera qué vista está activa. */
  _marcarVista(vista) {
    var btn = this.cont.querySelector('[data-camara="' + vista + '"]');
    if (!btn) { return; }
    this.cont.querySelectorAll('[data-camara]').forEach(function (x) {
      x.classList.toggle('is-activo', x === btn);
    });
  }

  /* Una petición al catálogo del plugin. Devuelve del más reciente al más
     antiguo, que es el orden en que lo entrega la REST. */
  _pedirEventos(ambito, limite, periodo) {
    // campos=globo entrega solo los siete campos que se dibujan. Con un mes de
    // sismicidad mundial la diferencia es de un megabyte a poco más de
    // trescientos kilobytes.
    var qs = 'ambito=' + encodeURIComponent(ambito) + '&limite=' + encodeURIComponent(limite) + '&campos=globo';
    var p = periodo || {};
    Object.keys(p).forEach(function (k) {
      if (p[k]) { qs += '&' + k + '=' + encodeURIComponent(p[k]); }
    });

    return fetch(CFG.rest + '/eventos?' + qs)
      .then(function (r) {
        if (!r.ok) { throw new Error('HTTP ' + r.status); }
        return r.json();
      })
      .then(function (j) { return (j.eventos || []).slice(0, limite); });
  }

  /* Cambia el conjunto pintado: 'local' es el ámbito publicado en el
     shortcode; 'mundo' es la sismicidad reciente del planeta, que solo se
     pide cuando alguien pulsa «Global» —así una página que nunca usa esa
     vista no paga la descarga. */
  _aplicarConjunto(nombre, encuadrar) {
    var eventos = this.conjuntos[nombre];
    if (!eventos) { return; }

    this.conjunto = nombre;
    this.eventos = eventos;
    this.seleccionado = -1;

    if (encuadrar) { this._encuadrarDatos(); }
    this._pintarSismos();
    this._pintarCalor();
    this._cintilloEvento(0);
    this._emitirCargados();
  }

  /* Carga perezosa del conjunto mundial, con aviso mientras llega. */
  _cargarMundo() {
    var self = this;
    if (this.conjuntos.mundo) { return Promise.resolve(this.conjuntos.mundo); }
    if (this._pidiendoMundo) { return this._pidiendoMundo; }

    this._cintilloTexto('Cargando la sismicidad reciente del mundo…');
    this._pidiendoMundo = this._pedirEventos('mundo', LIMITE_MUNDO, this.consulta.periodo)
      .then(function (eventos) {
        self.conjuntos.mundo = eventos;
        self._pidiendoMundo = null;
        return eventos;
      })
      .catch(function (e) {
        self._pidiendoMundo = null;
        self._cintilloTexto('No se pudo cargar la sismicidad mundial.');
        throw e;
      });

    return this._pidiendoMundo;
  }

  /* Encuadre inicial: el globo debe abrirse mirando donde están los sismos,
     no a un punto genérico del planeta. Se promedia la dirección de los
     epicentros (media vectorial, que no sufre con el meridiano 180°) y se
     coloca la cámara sobre ella, a una distancia que abarca la nube. */
  _encuadrarDatos() {
    if (!this.eventos.length) { return; }

    var centro = new THREE.Vector3();
    var maxSep = 0;
    var self = this;

    this.eventos.forEach(function (e) { centro.add(latLngAVector3(e.lat, e.lon, 1)); });
    if (centro.lengthSq() < 1e-6) { return; }
    centro.normalize();

    this.eventos.forEach(function (e) {
      var d = centro.angleTo(latLngAVector3(e.lat, e.lon, 1));
      if (d > maxSep) { maxSep = d; }
    });

    this.centroDatos = centro.clone();
    this.sepDatos = maxSep;

    this.camDatos = centro.clone().multiplyScalar(this._distanciaEncuadre());
    this._volarA(this.camDatos);

    this._marcarVista('sismos');
    void self;
  }

  /* Encuadre de la vista mundial: el planeta entero, con sus astas, dentro del
     marco. El radio a cubrir es 1 —la esfera— más el asta más larga que puede
     dibujarse, y la distancia que hace que una esfera de ese radio toque los
     bordes es radio / sen(semiángulo). El semiángulo que manda es el menor de
     los dos: en un lienzo apaisado es el vertical, y en uno vertical —un
     móvil— el horizontal. */
  _encuadrarMundo() {
    var RADIO = 1.38; // 1,001 de la superficie + 0,37 del asta de una M8.
    var mitadV = (this.camara.fov / 2) * Math.PI / 180;
    var mitadH = Math.atan(Math.tan(mitadV) * (this.camara.aspect || 1));
    var d = Math.max(2.6, Math.min(6, RADIO / Math.sin(Math.min(mitadV, mitadH))));

    // Una pizca de inclinación: de frente y a la altura del ecuador, la Tierra
    // se lee como un disco.
    this.camDefault = new THREE.Vector3(0, 0.26, 0.966).normalize().multiplyScalar(d);
  }

  /* Distancia de cámara que deja toda la nube dentro del encuadre.

     El campo de visión vertical es fijo (45°), así que el límite real cambia
     con la forma del lienzo: en uno apaisado manda la altura y en uno vertical
     —un móvil— manda el ancho, mucho más estrecho. Si se ignora, el mismo
     conjunto de sismos se ve bien en un escritorio y diminuto en un teléfono. */
  _distanciaEncuadre() {
    var sep = Math.max(this.sepDatos || 0, 0.06);
    var mitadV = (this.camara.fov / 2) * Math.PI / 180;
    var mitadH = Math.atan(Math.tan(mitadV) * (this.camara.aspect || 1));
    var mitad = Math.min(mitadV, mitadH) * 0.72; // 28 % de aire alrededor.
    var dist = Math.cos(sep) + Math.sin(sep) / Math.tan(mitad);
    return Math.max(1.75, Math.min(4.0, dist));
  }

  /* Contorno geográfico como líneas sobre la esfera. */
  _pintarContorno(url, color, opacidad, radio) {
    var self = this;
    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (geo) {
        var puntos = [];
        (geo.features || []).forEach(function (f) {
          anillosDeFeature(f).forEach(function (anillo) {
            for (var i = 0; i < anillo.length - 1; i++) {
              puntos.push(latLngAVector3(anillo[i][1], anillo[i][0], radio));
              puntos.push(latLngAVector3(anillo[i + 1][1], anillo[i + 1][0], radio));
            }
          });
        });
        if (!puntos.length) { return; }
        var linea = new THREE.LineSegments(
          new THREE.BufferGeometry().setFromPoints(puntos),
          new THREE.LineBasicMaterial({ color: color, transparent: true, opacity: opacidad, depthWrite: false })
        );
        self.gMapa.add(linea);
      })
      .catch(function () { /* la cartografía es un adorno: si falla, el globo sigue */ });
  }

  /* Longitud del asta exterior según la magnitud, y del segmento interior
     según la profundidad focal. */
  _alturaMagnitud(mag) {
    var m = Math.max(2.5, Math.min(8, Number(mag) || 0));
    return 0.03 + Math.pow((m - 2.5) / 5.5, 1.25) * 0.34;
  }

  _hundimiento(prof) {
    var p = Math.max(0, Math.min(300, Number(prof) || 0));
    return (p / 300) * 0.22;
  }

  _pintarSismos() {
    var self = this;
    while (this.gSismos.children.length) { this.gSismos.remove(this.gSismos.children[0]); }
    if (!this.eventos.length) { return; }

    var n = this.eventos.length;
    var posMag = new Float32Array(n * 6), colMag = new Float32Array(n * 6);
    var posProf = new Float32Array(n * 6), colProf = new Float32Array(n * 6);
    var posBase = new Float32Array(n * 3), colBase = new Float32Array(n * 3), tamBase = new Float32Array(n);

    this.eventos.forEach(function (e, i) {
      var base = latLngAVector3(e.lat, e.lon, 1.001);
      var alto = self._alturaMagnitud(e.mag);
      var hondo = self._hundimiento(e.profundidad);
      var punta = latLngAVector3(e.lat, e.lon, 1.001 + alto);
      var fondo = latLngAVector3(e.lat, e.lon, Math.max(0.2, 1 - hondo));
      var c = colorPorMagnitud(e.mag);

      // Asta de magnitud: del suelo hacia afuera.
      posMag[i * 6] = base.x; posMag[i * 6 + 1] = base.y; posMag[i * 6 + 2] = base.z;
      posMag[i * 6 + 3] = punta.x; posMag[i * 6 + 4] = punta.y; posMag[i * 6 + 5] = punta.z;
      // El color se atenúa en la base y satura en la punta: la línea "arde".
      colMag[i * 6] = c.r * 0.55; colMag[i * 6 + 1] = c.g * 0.55; colMag[i * 6 + 2] = c.b * 0.55;
      colMag[i * 6 + 3] = c.r; colMag[i * 6 + 4] = c.g; colMag[i * 6 + 5] = c.b;

      // Segmento de profundidad: del suelo hacia el interior.
      posProf[i * 6] = base.x; posProf[i * 6 + 1] = base.y; posProf[i * 6 + 2] = base.z;
      posProf[i * 6 + 3] = fondo.x; posProf[i * 6 + 4] = fondo.y; posProf[i * 6 + 5] = fondo.z;
      colProf[i * 6] = c.r * 0.75; colProf[i * 6 + 1] = c.g * 0.75; colProf[i * 6 + 2] = c.b * 0.75;
      colProf[i * 6 + 3] = c.r * 0.12; colProf[i * 6 + 4] = c.g * 0.12; colProf[i * 6 + 5] = c.b * 0.12;

      posBase[i * 3] = base.x; posBase[i * 3 + 1] = base.y; posBase[i * 3 + 2] = base.z;
      colBase[i * 3] = c.r; colBase[i * 3 + 1] = c.g; colBase[i * 3 + 2] = c.b;
      tamBase[i] = 0.045 + Math.max(0, (e.mag - 3)) * 0.022;
    });

    var geoMag = new THREE.BufferGeometry();
    geoMag.setAttribute('position', new THREE.BufferAttribute(posMag, 3));
    geoMag.setAttribute('color', new THREE.BufferAttribute(colMag, 3));
    this.lineasMag = new THREE.LineSegments(geoMag, new THREE.LineBasicMaterial({
      vertexColors: true, transparent: true, opacity: 0.8, depthWrite: false, blending: THREE.AdditiveBlending
    }));
    this.gSismos.add(this.lineasMag);

    var geoProf = new THREE.BufferGeometry();
    geoProf.setAttribute('position', new THREE.BufferAttribute(posProf, 3));
    geoProf.setAttribute('color', new THREE.BufferAttribute(colProf, 3));
    // El segmento de profundidad va POR DENTRO del planeta: para que se vea,
    // la capa lo dibuja discontinuo y vuelve el planeta translúcido, como una
    // radiografía en la que se distingue el plano de la placa que subduce.
    this.lineasProf = new THREE.LineSegments(geoProf, new THREE.LineDashedMaterial({
      vertexColors: true, transparent: true, opacity: 0.85,
      dashSize: 0.016, gapSize: 0.012, depthWrite: false
    }));
    this.lineasProf.computeLineDistances();
    this.gSismos.add(this.lineasProf);
    this._modoRadiografia(this.capas.profundidad);

    var geoBase = new THREE.BufferGeometry();
    geoBase.setAttribute('position', new THREE.BufferAttribute(posBase, 3));
    geoBase.setAttribute('color', new THREE.BufferAttribute(colBase, 3));
    geoBase.setAttribute('size', new THREE.BufferAttribute(tamBase, 1));
    this.puntos = new THREE.Points(geoBase, new THREE.PointsMaterial({
      // Mezcla normal, no aditiva: 50 epicentros juntos deben leerse como
      // puntos de colores distintos, no como una mancha blanca.
      size: 0.032, map: texturaPunto(), vertexColors: true, transparent: true,
      opacity: 0.95, depthWrite: false, sizeAttenuation: true
    }));
    this.gSismos.add(this.puntos);

    // Anillo que late sobre el sismo seleccionado (por defecto, el más reciente).
    this.anillo = new THREE.Mesh(
      new THREE.RingGeometry(0.020, 0.028, 40),
      new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.7, side: THREE.DoubleSide, depthWrite: false })
    );
    this.gSismos.add(this.anillo);
    this._situarAnillo(0);
  }

  /* Planeta translúcido para ver los segmentos de profundidad por dentro. */
  _modoRadiografia(activo) {
    if (!this.globo) { return; }
    this.globo.material.transparent = !!activo;
    this.globo.material.opacity = activo ? 0.55 : 1;
    this.globo.material.needsUpdate = true;
  }

  _situarAnillo(indice) {
    if (!this.anillo || !this.eventos.length) { return; }
    var e = this.eventos[Math.max(0, Math.min(this.eventos.length - 1, indice))];
    var v = latLngAVector3(e.lat, e.lon, 1.006);
    this.anillo.position.copy(v);
    this.anillo.lookAt(v.clone().multiplyScalar(2));
    this.anillo.material.color.copy(colorPorMagnitud(e.mag));
    this.seleccionado = indice;
  }

  /* Campo de calor: partículas sembradas alrededor de los epicentros cuya
     intensidad acumula la energía de los sismos cercanos. Comparte la escala
     de color con las líneas, así que una zona roja es una zona donde se
     liberó más energía. */
  _pintarCalor() {
    while (this.gCalor.children.length) { this.gCalor.remove(this.gCalor.children[0]); }
    if (!this.eventos.length) { return; }

    /* La mezcla aditiva suma el color de cada partícula: con demasiadas
       superpuestas el racimo se quema en blanco y deja de leerse la escala.

       El presupuesto es del campo entero, no de cada sismo: con cincuenta
       epicentros cada uno recibe las dieciséis partículas de siempre, y con
       los dos mil de un mes del planeta se reparten las mismas doce mil. Así
       el coste del bucle por fotograma —que recorre partícula a partícula—
       no depende de cuántos sismos se estén dibujando. */
    var PRESUPUESTO = this.ligero ? 6000 : 12000;
    var porSismo = Math.max(1, Math.min(this.ligero ? 10 : 16,
      Math.round(PRESUPUESTO / this.eventos.length)));
    var n = this.eventos.length * porSismo;
    var pos = new Float32Array(n * 3), col = new Float32Array(n * 3);
    this._calorPts = [];

    var k = 0;
    for (var i = 0; i < this.eventos.length; i++) {
      var e = this.eventos[i];
      // Radio de dispersión: los sismos grandes se sienten más lejos.
      var radioGrados = 0.35 + Math.max(0, e.mag - 3) * 0.55;

      for (var j = 0; j < porSismo; j++) {
        // Dispersión gaussiana aproximada (suma de uniformes) alrededor del epicentro.
        var rr = ((Math.random() + Math.random() + Math.random()) / 3 - 0.5) * 2;
        var ang = Math.random() * Math.PI * 2;
        var lat = e.lat + rr * radioGrados * Math.sin(ang);
        var lng = e.lon + rr * radioGrados * Math.cos(ang) / Math.max(0.2, Math.cos(e.lat * Math.PI / 180));

        var d = distanciaKm(e.lat, e.lon, lat, lng);
        var caida = Math.exp(-d / (40 + Math.max(0, e.mag - 3) * 90));
        // El factor de normalización compensa el número de partículas por
        // sismo: la suma de la nube tiende al mismo brillo con 14 que con 26.
        var intensidad = Math.max(0.05, Math.min(1, caida)) * (13 / porSismo);

        // El color depende solo de la magnitud, que no cambia: se resuelve
        // aquí y se guarda. El bucle de respiración, que corre sesenta veces
        // por segundo, se queda en tres multiplicaciones por partícula.
        var c = colorPorMagnitud(e.mag);

        var p = {
          lat: lat, lng: lng, fase: Math.random() * 6.28, intensidad: intensidad,
          r: c.r, g: c.g, b: c.b
        };
        this._calorPts.push(p);

        var v = latLngAVector3(lat, lng, 1.008);
        pos[k * 3] = v.x; pos[k * 3 + 1] = v.y; pos[k * 3 + 2] = v.z;

        col[k * 3] = c.r * intensidad;
        col[k * 3 + 1] = c.g * intensidad;
        col[k * 3 + 2] = c.b * intensidad;
        k++;
      }
    }

    var geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    geo.setAttribute('color', new THREE.BufferAttribute(col, 3));
    this.calor = new THREE.Points(geo, new THREE.PointsMaterial({
      size: this.ligero ? 0.05 : 0.062, map: texturaPunto(), vertexColors: true,
      transparent: true, opacity: 0.07, depthWrite: false, blending: THREE.AdditiveBlending, sizeAttenuation: true
    }));
    this.gCalor.add(this.calor);
  }

  /* ---------------- interacción ---------------- */

  _interaccion() {
    var self = this;
    var dom = this.renderer.domElement;

    this.raycaster = new THREE.Raycaster();
    this.raycaster.params.Points.threshold = 0.035;
    this.puntero = new THREE.Vector2();

    this.tip = document.createElement('div');
    this.tip.className = 'sis-globo__tip';
    this.tip.hidden = true;
    this.escenaHTML.appendChild(this.tip);

    var indiceEn = function (ev) {
      if (!self.puntos) { return -1; }
      var rect = dom.getBoundingClientRect();
      self.puntero.x = ((ev.clientX - rect.left) / rect.width) * 2 - 1;
      self.puntero.y = -((ev.clientY - rect.top) / rect.height) * 2 + 1;
      self.raycaster.setFromCamera(self.puntero, self.camara);
      var hits = self.raycaster.intersectObject(self.puntos, false);
      return hits.length ? hits[0].index : -1;
    };

    dom.addEventListener('pointermove', function (ev) {
      var i = indiceEn(ev);
      if (i < 0) { self.tip.hidden = true; dom.style.cursor = ''; return; }
      self._tooltip(ev, self.eventos[i]);
      dom.style.cursor = 'pointer';
    });

    dom.addEventListener('pointerleave', function () { self.tip.hidden = true; dom.style.cursor = ''; });

    dom.addEventListener('click', function (ev) {
      var i = indiceEn(ev);
      if (i < 0) { return; }
      self._situarAnillo(i);
      self._cintilloEvento(i);
      window.dispatchEvent(new CustomEvent('sis:sismo', { detail: { indice: i, evento: self.eventos[i], origen: 'globo' } }));
    });
  }

  _tooltip(ev, e) {
    if (!e) { return; }
    var rect = this.escenaHTML.getBoundingClientRect();
    this.tip.innerHTML =
      '<strong>Magnitud ' + esc(num(e.mag, 1)) + '</strong><br>' +
      esc(e.lugar || '') + '<br>' +
      esc(e.fecha || '') + ' UTC<br>' +
      'Profundidad ' + esc(num(e.profundidad, 0)) + ' km' +
      (e.municipio ? '<br>Cerca de ' + esc(e.municipio) : '');
    this.tip.style.left = Math.round(ev.clientX - rect.left + 14) + 'px';
    this.tip.style.top = Math.round(ev.clientY - rect.top + 14) + 'px';
    this.tip.hidden = false;
  }

  /* ---------------- interfaz ---------------- */

  _controlesUI() {
    var self = this;

    this.cont.querySelectorAll('[data-camara]').forEach(function (b) {
      b.addEventListener('click', function () {
        var vista = b.getAttribute('data-camara');
        self.cont.querySelectorAll('[data-camara]').forEach(function (x) { x.classList.toggle('is-activo', x === b); });
        self._pararRotacion();

        // «Global» no es solo alejar la cámara: cambia lo que se dibuja. El
        // ámbito publicado en el shortcode cubre Nariño y su zona de
        // subducción, así que un planeta con esos 50 puntos y el resto vacío
        // haría creer que en el mundo no tiembla. Con esta vista se pide la
        // sismicidad reciente del planeta y se ve el Cinturón de Fuego.
        if ('global' === vista) {
          self._volarA(self.camDefault);
          self._cargarMundo()
            .then(function () { self._aplicarConjunto('mundo', false); })
            .catch(function () {});
          return;
        }

        /* Las otras dos vistas miran el ámbito publicado en el shortcode. Si
           el globo abrió en la vista mundial puede que todavía se esté
           precargando, así que se espera a que llegue en vez de no hacer nada.

           «Zona sísmica» no vuela a un punto fijo: su encuadre se calcula con
           los epicentros del ámbito, así que lo hace _encuadrarDatos cuando
           los datos ya están. Volar antes daría un salto en dos tiempos. */
        var yaEstaba = 'local' === self.conjunto;
        if ('narino' === vista) { self._volarA(self.camNarino); }
        else if (yaEstaba && self.camDatos) { self._volarA(self.camDatos); }

        if (yaEstaba) { return; }

        self._cargarLocal()
          .then(function () { self._aplicarConjunto('local', 'sismos' === vista); })
          .catch(function () { self._cintilloTexto('No se pudieron cargar los sismos del ámbito.'); });
      });
    });

    this.cont.querySelectorAll('[data-capa]').forEach(function (b) {
      b.addEventListener('click', function () {
        var capa = b.getAttribute('data-capa');
        self.capas[capa] = !self.capas[capa];
        b.setAttribute('aria-pressed', self.capas[capa] ? 'true' : 'false');
        b.classList.toggle('is-activo', self.capas[capa]);
        if (capa === 'sismos') { self.gSismos.visible = self.capas.sismos; }
        if (capa === 'calor') { self.gCalor.visible = self.capas.calor; }
        if (capa === 'municipios') { self.gMapa.visible = self.capas.municipios; }
        if (capa === 'profundidad') {
          if (self.lineasProf) { self.lineasProf.visible = self.capas.profundidad; }
          self._modoRadiografia(self.capas.profundidad);
        }
      });
    });

    var rot = this.cont.querySelector('[data-rotar]');
    if (rot) {
      rot.setAttribute('aria-pressed', this.controles.autoRotate ? 'true' : 'false');
      rot.classList.toggle('is-activo', this.controles.autoRotate);
      rot.addEventListener('click', function () {
        self.controles.autoRotate = !self.controles.autoRotate;
        rot.setAttribute('aria-pressed', self.controles.autoRotate ? 'true' : 'false');
        rot.classList.toggle('is-activo', self.controles.autoRotate);
      });
    }
  }

  /* La rotación automática y un encuadre pedido a mano se estorban: al pedir
     una vista concreta —o al elegir un sismo— la rotación se detiene. */
  _pararRotacion() {
    if (!this.controles || !this.controles.autoRotate) { return; }
    this.controles.autoRotate = false;
    var rot = this.cont.querySelector('[data-rotar]');
    if (rot) {
      rot.setAttribute('aria-pressed', 'false');
      rot.classList.remove('is-activo');
    }
  }

  _volarA(destino) {
    this._camTransicion = { desde: this.camara.position.clone(), hasta: destino.clone(), t: 0 };
  }

  _cintilloEvento(i) {
    var e = this.eventos[i];
    if (!e) { return; }
    var caja = this.cont.querySelector('.sis-globo__cintillo');
    if (!caja) { return; }
    var etq = i === 0 ? 'Último sismo' : 'Sismo ' + (i + 1) + ' de ' + this.eventos.length;
    caja.innerHTML =
      '<strong class="sis-globo__cintillo-mag" style="color:' + '#' + colorPorMagnitud(e.mag).getHexString() + '">' +
      esc(num(e.mag, 1)) + '</strong>' +
      '<span class="sis-globo__cintillo-etq">' + esc(etq) + '</span>' +
      '<span class="sis-globo__sep">·</span>' +
      '<span class="sis-globo__cintillo-lugar">' + esc(e.lugar || '') + '</span>' +
      '<span class="sis-globo__sep">·</span>' +
      '<span>' + esc(num(e.profundidad, 0)) + ' km</span>' +
      '<span class="sis-globo__sep">·</span>' +
      '<span>' + esc((e.fecha || '').slice(0, 16)) + ' UTC</span>';
  }

  /* Mensaje suelto en el cintillo (cargando, error de una capa). */
  _cintilloTexto(texto) {
    var caja = this.cont.querySelector('.sis-globo__cintillo');
    if (!caja) { return; }
    caja.textContent = texto;
  }

  _quitarSkeleton() {
    var s = this.cont.querySelector('.sis-skeleton');
    if (s && s.parentNode) { s.parentNode.removeChild(s); }
  }

  _error(msg) {
    this._quitarSkeleton();
    var p = document.createElement('p');
    p.className = 'sis-globo__error';
    p.setAttribute('role', 'alert');
    p.textContent = msg;
    this.cont.appendChild(p);
  }

  /* Publica el conjunto cargado para que la línea de tiempo lo use sin
     volver a pedirlo, y escucha sus cambios de selección. */
  _emitirCargados() {
    window.dispatchEvent(new CustomEvent('sis:sismos-cargados', {
      detail: {
        eventos: this.eventos,
        conjunto: this.conjunto,
      },
    }));
  }

  _sincronizacion() {
    var self = this;
    window.addEventListener('sis:sismo', function (ev) {
      var d = ev.detail || {};
      if (d.origen === 'globo' || typeof d.indice !== 'number') { return; }
      self._situarAnillo(d.indice);
      self._cintilloEvento(d.indice);
      var e = self.eventos[d.indice];
      if (e && d.enfocar !== false) {
        self._pararRotacion();
        self._volarA(latLngAVector3(e.lat, e.lon, 2.2));
      }
    });
  }

  /* ---------------- bucle ---------------- */

  _loop() {
    var self = this;
    var ultimo = performance.now();

    function tick(ahora) {
      requestAnimationFrame(tick);
      if (!self.visible) { ultimo = ahora; return; }

      var dt = Math.min(0.05, (ahora - ultimo) / 1000);
      ultimo = ahora;
      self._t += dt;

      // Transición de cámara entre vistas predefinidas.
      if (self._camTransicion) {
        var tr = self._camTransicion;
        tr.t = Math.min(1, tr.t + dt * 1.1);
        var s = tr.t * tr.t * (3 - 2 * tr.t); // suavizado
        self.camara.position.lerpVectors(tr.desde, tr.hasta, s);
        if (tr.t >= 1) { self._camTransicion = null; }
      }

      // Latido del sismo seleccionado.
      if (self.anillo && !self.reducido) {
        var pulso = 1 + Math.sin(self._t * 3.4) * 0.22;
        self.anillo.scale.setScalar(pulso);
        self.anillo.material.opacity = 0.35 + Math.sin(self._t * 3.4) * 0.25;
      }

      // Respiración del campo de calor: la intensidad ondula suavemente para
      // que la capa se lea como energía y no como puntos fijos.
      if (self.calor && self._calorPts && !self.reducido) {
        var col = self.calor.geometry.attributes.color.array;
        for (var i = 0; i < self._calorPts.length; i++) {
          var p = self._calorPts[i];
          var f = p.intensidad * (0.72 + 0.28 * Math.sin(self._t * 1.6 + p.fase));
          col[i * 3] = p.r * f;
          col[i * 3 + 1] = p.g * f;
          col[i * 3 + 2] = p.b * f;
        }
        self.calor.geometry.attributes.color.needsUpdate = true;
      }

      self.controles.update();
      self.renderer.render(self.escena, self.camara);
    }

    requestAnimationFrame(tick);
  }
}

/* ================================================================= */

function arrancar() {
  document.querySelectorAll('[data-sis-globo]').forEach(function (cont) {
    if (cont.dataset.sisGloboListo) { return; }
    cont.dataset.sisGloboListo = '1';
    try {
      new GloboSismico(cont);
    } catch (e) {
      var s = cont.querySelector('.sis-skeleton');
      if (s && s.parentNode) { s.parentNode.removeChild(s); }
      var p = document.createElement('p');
      p.className = 'sis-globo__error';
      p.textContent = 'Este navegador no puede dibujar el globo 3D (WebGL no disponible). El resto de componentes funciona con normalidad.';
      cont.appendChild(p);
    }
  });
}

if (document.readyState !== 'loading') { arrancar(); }
else { document.addEventListener('DOMContentLoaded', arrancar); }

export { GloboSismico, colorPorMagnitud, latLngAVector3 };
