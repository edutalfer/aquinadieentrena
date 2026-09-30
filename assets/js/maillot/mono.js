/* ============================================================
   AQUÍ NADIE ENTRENA — el mono en 3D
   ------------------------------------------------------------
   Un mono de ciclismo hecho a base de funciones, sin modelo
   externo: el tronco es una superelipse que cambia de ancho con
   la altura, y mangas y perneras son tubos. Arriba blanco, abajo
   negro (el culote).

   Cada hueco de patrocinio es un «parche» que se calcula con la
   MISMA función que la superficie que lo lleva, un pelo por
   encima, así se ciñe al cuerpo sin trucos. Las claves de ZONAS
   deben coincidir con ANE_MAILLOT_ZONAS de api/_maillot.php.

   Ejes: y hacia arriba (en metros, más o menos), el pecho mira
   a +z y +x es el lado IZQUIERDO de quien lo lleva puesto.
   ============================================================ */

import {
  BufferGeometry, Float32BufferAttribute, Vector3, Mesh, Group,
  MeshStandardMaterial, MeshPhysicalMaterial, CanvasTexture, SRGBColorSpace,
  DoubleSide, PlaneGeometry, MeshBasicMaterial,
} from "three";

const PI = Math.PI;

/* ---------- Colores de marca ---------- */
export const COLOR = {
  azul: "#3F77DA", enlace: "#2A5CB8", profundo: "#264783", destello: "#B2C8F0",
  negro: "#191919", blanco: "#FFFFFF", humo: "#F2F3F5", linea: "#E1E3E7", gris: "#6E747F",
};

/* ---------- Utilidades ---------- */

/* Interpolación suave (Catmull-Rom) sobre una tabla [[x, v1, v2...], ...] */
function tabla(filas) {
  return function (x) {
    const n = filas.length;
    if (x <= filas[0][0]) return filas[0].slice(1);
    if (x >= filas[n - 1][0]) return filas[n - 1].slice(1);
    let i = 0;
    while (i < n - 2 && x > filas[i + 1][0]) i++;
    const p0 = filas[Math.max(0, i - 1)], p1 = filas[i], p2 = filas[i + 1], p3 = filas[Math.min(n - 1, i + 2)];
    const t = (x - p1[0]) / (p2[0] - p1[0]);
    const t2 = t * t, t3 = t2 * t;
    const out = [];
    for (let k = 1; k < p1.length; k++) {
      out.push(0.5 * ((2 * p1[k]) + (-p0[k] + p2[k]) * t + (2 * p0[k] - 5 * p1[k] + 4 * p2[k] - p3[k]) * t2
        + (-p0[k] + 3 * p1[k] - 3 * p2[k] + p3[k]) * t3));
    }
    return out;
  };
}

const lerp = (a, b, t) => a + (b - a) * t;
const S = (t, e) => Math.sign(t) * Math.pow(Math.abs(t), e);   // superelipse

/* ---------- Tronco ----------
   y: altura · a: medio ancho · b: medio fondo · zc: el centro se adelanta
   o se retrasa (pecho delante, glúteos detrás) */
const PERFIL = tabla([
  [0.860, 0.160, 0.106, -0.012],
  [0.900, 0.168, 0.116, -0.014],
  [0.960, 0.171, 0.119, -0.012],
  [1.030, 0.157, 0.112, -0.002],
  [1.090, 0.143, 0.107,  0.004],
  [1.180, 0.151, 0.116,  0.009],
  [1.270, 0.167, 0.127,  0.012],
  [1.340, 0.178, 0.127,  0.008],
  [1.392, 0.186, 0.117,  0.000],
  [1.428, 0.166, 0.100, -0.005],
  [1.458, 0.118, 0.080, -0.008],
  [1.484, 0.074, 0.066, -0.006],
  [1.505, 0.063, 0.059, -0.005],
]);
export const Y_ABAJO = 0.86;
export const Y_ARRIBA = 1.505;
const EXP = 2 / 2.35;

/* θ = 0 delante, crece hacia la izquierda de quien lo lleva (+x) */
function tronco(theta, y) {
  const [a, b, zc] = PERFIL(y);
  return new Vector3(a * S(Math.sin(theta), EXP), y, zc + b * S(Math.cos(theta), EXP));
}
function troncoCentro(y) {
  return new Vector3(0, y, PERFIL(y)[2]);
}

/* Donde acaba el blanco y empieza el culote: un poco más alto por detrás */
const corte = (theta) => 0.995 + 0.016 * (1 - Math.cos(theta)) / 2;

/* ---------- Tubos (mangas y perneras) ---------- */
function tubo({ p0, p1, radios, lado, fondo = 1 }) {
  const P0 = new Vector3(...p0), P1 = new Vector3(...p1);
  const arriba = P0.clone().sub(P1).normalize();            // eje, hacia el hombro o la cadera
  const fuera0 = new Vector3(lado, 0, 0);
  const fuera = fuera0.sub(arriba.clone().multiplyScalar(fuera0.dot(arriba))).normalize();
  const w = new Vector3().crossVectors(arriba, fuera);       // de «fuera» hacia la derecha de quien mira
  const R = tabla(radios);
  const f = (theta, s) => {
    const r = R(s)[0];
    const c = P0.clone().lerp(P1, s);
    const fz = Math.abs(w.z) > 0.5 ? fondo : 1;
    return c.add(fuera.clone().multiplyScalar(r * Math.cos(theta)))
            .add(w.clone().multiplyScalar(r * fz * Math.sin(theta)));
  };
  f.centro = (s) => P0.clone().lerp(P1, s);
  f.eje = arriba.clone().negate();
  f.radio = (s) => R(s)[0];
  return f;
}

const MANGA = (lado) => tubo({
  p0: [0.150 * lado, 1.405, -0.004], p1: [0.258 * lado, 1.146, 0.004], lado,
  radios: [[-0.1, 0.074], [0, 0.072], [0.25, 0.064], [0.6, 0.056], [1, 0.050]],
});
const PIERNA = (lado) => tubo({
  p0: [0.084 * lado, 0.945, -0.008], p1: [0.106 * lado, 0.565, 0.004], lado, fondo: 1.07,
  radios: [[-0.15, 0.093], [0, 0.092], [0.3, 0.088], [0.7, 0.078], [1, 0.071]],
});
const TUBOS = { manga: { 1: MANGA(1), [-1]: MANGA(-1) }, pierna: { 1: PIERNA(1), [-1]: PIERNA(-1) } };

/* ---------- Mallas a partir de una función (u, v) → punto ----------
   Las normales se calculan con la función de la superficie completa
   (no con la malla), así dos trozos que se tocan no dejan costura. */
function malla(pos, { nu, nv, cerradoU = false, normal, desplaza = 0 }) {
  const cols = cerradoU ? nu : nu + 1;
  const vert = [], nor = [], uv = [], idx = [];
  for (let j = 0; j <= nv; j++) {
    const v = j / nv;
    for (let i = 0; i < cols; i++) {
      const u = i / nu;
      const p = pos(u, v);
      const n = normal(u, v, p);
      if (desplaza) p.add(n.clone().multiplyScalar(desplaza));
      vert.push(p.x, p.y, p.z);
      nor.push(n.x, n.y, n.z);
      uv.push(u, v);
    }
  }
  for (let j = 0; j < nv; j++) {
    for (let i = 0; i < nu; i++) {
      const i1 = cerradoU ? (i + 1) % nu : i + 1;
      const a = j * cols + i, b = j * cols + i1, c = (j + 1) * cols + i, d = (j + 1) * cols + i1;
      idx.push(a, b, d, a, d, c);
    }
  }
  const g = new BufferGeometry();
  g.setAttribute("position", new Float32BufferAttribute(vert, 3));
  g.setAttribute("normal", new Float32BufferAttribute(nor, 3));
  g.setAttribute("uv", new Float32BufferAttribute(uv, 2));
  g.setIndex(idx);
  return g;
}

/* Normal numérica de una superficie f(θ, t), orientada hacia fuera */
function normalDe(f, centro) {
  const e = 1e-4;
  return (theta, t, p) => {
    const du = f(theta + e, t).sub(f(theta - e, t));
    const dv = f(theta, t + e).sub(f(theta, t - e));
    const n = new Vector3().crossVectors(du, dv).normalize();
    if (n.dot(p.clone().sub(centro(t))) < 0) n.negate();
    return n;
  };
}

const nTronco = normalDe(tronco, troncoCentro);
const nTubo = (f) => normalDe(f, (s) => f.centro(s));

/* Tapa plana (fondo de manga, pernera, cuello...) */
function tapa(borde, centro, pasos = 48) {
  const vert = [centro.x, centro.y, centro.z], idx = [];
  for (let i = 0; i < pasos; i++) {
    const p = borde(i / pasos * 2 * PI);
    vert.push(p.x, p.y, p.z);
  }
  for (let i = 0; i < pasos; i++) idx.push(0, 1 + i, 1 + (i + 1) % pasos);
  const g = new BufferGeometry();
  g.setAttribute("position", new Float32BufferAttribute(vert, 3));
  g.setIndex(idx);
  g.computeVertexNormals();
  return g;
}

/* ---------- El mono completo ---------- */
export function creaMono() {
  const grupo = new Group();
  const tela = (color, rugosidad, brillo) => new MeshPhysicalMaterial({
    color, roughness: rugosidad, metalness: 0, sheen: brillo, sheenRoughness: 0.6, sheenColor: "#ffffff",
  });
  const blanco = tela("#F4F5F7", 0.62, 0.35);
  const negro = tela("#161719", 0.55, 0.08);
  const interior = new MeshStandardMaterial({ color: "#D3D7DD", roughness: 1, side: DoubleSide });
  const interiorNegro = new MeshStandardMaterial({ color: "#0B0B0C", roughness: 1, side: DoubleSide });
  const cuerpo = [];
  const add = (g, m) => { const x = new Mesh(g, m); grupo.add(x); cuerpo.push(x); return x; };

  /* Tronco: blanco de la cintura para arriba, negro por debajo */
  const nuT = 128;
  add(malla((u, v) => { const th = u * 2 * PI; return tronco(th, lerp(corte(th), Y_ARRIBA, v)); },
    { nu: nuT, nv: 70, cerradoU: true, normal: (u, v, p) => { const th = u * 2 * PI; return nTronco(th, lerp(corte(th), Y_ARRIBA, v), p); } }), blanco);
  add(malla((u, v) => { const th = u * 2 * PI; return tronco(th, lerp(Y_ABAJO, corte(th), v)); },
    { nu: nuT, nv: 18, cerradoU: true, normal: (u, v, p) => { const th = u * 2 * PI; return nTronco(th, lerp(Y_ABAJO, corte(th), v), p); } }), negro);
  add(tapa((th) => tronco(th, Y_ABAJO), troncoCentro(Y_ABAJO)), interiorNegro);
  /* Cuello: el hueco se ve por dentro, más oscuro */
  add(tapa((th) => tronco(th, Y_ARRIBA - 0.012).multiply(new Vector3(0.94, 1, 0.94)), troncoCentro(Y_ARRIBA - 0.02)), interior);

  /* Cremallera: una línea fina en el centro del pecho */
  const cremallera = new MeshStandardMaterial({ color: "#C3C8CF", roughness: 0.5 });
  add(malla((u, v) => tronco(lerp(-0.012, 0.012, u), lerp(1.06, Y_ARRIBA, v)),
    { nu: 2, nv: 40, normal: (u, v, p) => nTronco(lerp(-0.012, 0.012, u), lerp(1.06, Y_ARRIBA, v), p), desplaza: 0.0012 }), cremallera);

  for (const lado of [1, -1]) {
    /* Manga, con un puño algo más gris y el interior oscuro */
    const m = TUBOS.manga[lado], nm = nTubo(m);
    add(malla((u, v) => m(u * 2 * PI, lerp(-0.1, 1, v)),
      { nu: 64, nv: 40, cerradoU: true, normal: (u, v, p) => nm(u * 2 * PI, lerp(-0.1, 1, v), p) }), blanco);
    add(malla((u, v) => m(u * 2 * PI, lerp(0.93, 1, v)),
      { nu: 64, nv: 3, cerradoU: true, normal: (u, v, p) => nm(u * 2 * PI, lerp(0.93, 1, v), p), desplaza: 0.0015 }),
      new MeshStandardMaterial({ color: "#DDE0E5", roughness: 0.7 }));
    add(tapa((th) => m(th, 1), m.centro(1)), interior);

    /* Pernera del culote, con la banda de silicona abajo */
    const p = TUBOS.pierna[lado], np = nTubo(p);
    add(malla((u, v) => p(u * 2 * PI, lerp(-0.15, 1, v)),
      { nu: 64, nv: 50, cerradoU: true, normal: (u, v, q) => np(u * 2 * PI, lerp(-0.15, 1, v), q) }), negro);
    add(malla((u, v) => p(u * 2 * PI, lerp(0.9, 1, v)),
      { nu: 64, nv: 4, cerradoU: true, normal: (u, v, q) => np(u * 2 * PI, lerp(0.9, 1, v), q), desplaza: 0.0015 }),
      new MeshStandardMaterial({ color: "#2E3035", roughness: 0.8 }));
    add(tapa((th) => p(th, 1), p.centro(1)), interiorNegro);
  }

  /* Sombra suave en el suelo, para que no flote en el vacío */
  const c = document.createElement("canvas");
  c.width = c.height = 128;
  const x = c.getContext("2d");
  const gr = x.createRadialGradient(64, 64, 4, 64, 64, 62);
  gr.addColorStop(0, "rgba(25,25,25,.28)");
  gr.addColorStop(1, "rgba(25,25,25,0)");
  x.fillStyle = gr;
  x.fillRect(0, 0, 128, 128);
  const sombra = new Mesh(new PlaneGeometry(0.62, 0.3),
    new MeshBasicMaterial({ map: new CanvasTexture(c), transparent: true, depthWrite: false }));
  sombra.rotation.x = -PI / 2;
  sombra.position.set(0, 0.47, 0);
  grupo.add(sombra);

  return { grupo, cuerpo };
}

/* ---------- Huecos ----------
   torso: t = rango de θ, y = rango de alturas
   manga / pierna: lado (+1 izquierda de quien lo lleva), s = tramo del tubo
   (0 arriba, 1 abajo), t = rango de ángulo alrededor (0 = cara exterior)
   mira: giro del mono (radianes) para dejar el hueco de frente
   vertical: el texto va girado 90° */
export const ZONAS = {
  "pecho":        { parte: "torso", t: [-0.66, 0.66], y: [1.232, 1.345], mira: 0 },
  "abdomen":      { parte: "torso", t: [-0.64, 0.64], y: [1.085, 1.195], mira: 0 },
  "espalda-alta": { parte: "torso", t: [PI - 0.72, PI + 0.72], y: [1.245, 1.372], mira: PI },
  "espalda-baja": { parte: "torso", t: [PI - 0.64, PI + 0.64], y: [1.08, 1.19], mira: PI },
  "costado-izq":  { parte: "torso", t: [PI / 2 - 0.3, PI / 2 + 0.3], y: [1.02, 1.19], mira: -1.25, vertical: true },
  "costado-der":  { parte: "torso", t: [3 * PI / 2 - 0.3, 3 * PI / 2 + 0.3], y: [1.02, 1.19], mira: 1.25, vertical: true },
  "manga-izq":    { parte: "manga", lado: 1, s: [0.3, 0.88], t: [-0.95, 0.95], mira: -PI / 2 },
  "manga-der":    { parte: "manga", lado: -1, s: [0.3, 0.88], t: [-0.95, 0.95], mira: PI / 2 },
  "culote-izq":   { parte: "pierna", lado: 1, s: [0.16, 0.8], t: [-0.78, 0.78], mira: -PI / 2, vertical: true },
  "culote-der":   { parte: "pierna", lado: -1, s: [0.16, 0.8], t: [-0.78, 0.78], mira: PI / 2, vertical: true },
};

/* Punto (u, v) del parche: u de izquierda a derecha según se mira, v de abajo arriba */
function funcionParche(z) {
  if (z.parte === "torso") {
    const pos = (u, v) => tronco(lerp(z.t[0], z.t[1], u), lerp(z.y[0], z.y[1], v));
    const nor = (u, v, p) => nTronco(lerp(z.t[0], z.t[1], u), lerp(z.y[0], z.y[1], v), p);
    return { pos, nor };
  }
  const f = TUBOS[z.parte][z.lado], nf = nTubo(f);
  const th = (u) => lerp(z.t[0], z.t[1], u);
  const s = (v) => lerp(z.s[1], z.s[0], v);
  return { pos: (u, v) => f(th(u), s(v)), nor: (u, v, p) => nf(th(u), s(v), p) };
}

/* Medidas reales del parche (ancho y alto por el centro), para no deformar el texto */
function medidas(pos) {
  let w = 0, h = 0;
  for (let i = 0; i < 20; i++) {
    w += pos(i / 20, 0.5).distanceTo(pos((i + 1) / 20, 0.5));
    h += pos(0.5, i / 20).distanceTo(pos(0.5, (i + 1) / 20));
  }
  return { w, h };
}

/* ¿El hueco está sobre el negro del culote? */
export const sobreNegro = (zona) => ZONAS[zona].parte === "pierna";

export function creaParche(zona) {
  const z = ZONAS[zona];
  const { pos, nor } = funcionParche(z);
  const g = malla(pos, { nu: 24, nv: 16, normal: nor, desplaza: 0.0035 });
  const { w, h } = medidas(pos);
  const lienzo = document.createElement("canvas");
  /* El lienzo sigue las proporciones del parche: el lado largo, 512 px */
  if (w >= h) {
    lienzo.width = 512;
    lienzo.height = Math.round(Math.max(96, 512 * h / w));
  } else {
    lienzo.height = 512;
    lienzo.width = Math.round(Math.max(96, 512 * w / h));
  }
  const tex = new CanvasTexture(lienzo);
  tex.colorSpace = SRGBColorSpace;
  tex.anisotropy = 4;
  const mat = new MeshStandardMaterial({
    map: tex, roughness: 0.55, metalness: 0,
    polygonOffset: true, polygonOffsetFactor: -2, polygonOffsetUnits: -2,
  });
  const mesh = new Mesh(g, mat);
  mesh.userData = { zona, lienzo, tex, vertical: !!z.vertical, mira: z.mira };
  return mesh;
}

/* ---------- Lo que se pinta en cada parche ---------- */

function euros(n) {
  return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ".") + " €";
}

/* Escribe una línea ajustando el tamaño para que quepa en «ancho» */
function linea(ctx, texto, x, y, ancho, tam, fuente) {
  let t = tam;
  ctx.font = fuente(t);
  while (ctx.measureText(texto).width > ancho && t > 8) {
    t -= 2;
    ctx.font = fuente(t);
  }
  ctx.fillText(texto, x, y);
  return t;
}

export function pintaParche(mesh, hueco, { elegido = false, encima = false } = {}) {
  const { lienzo, tex, vertical, zona } = mesh.userData;
  const ctx = lienzo.getContext("2d");
  let W = lienzo.width, H = lienzo.height;
  ctx.save();
  ctx.clearRect(0, 0, W, H);
  if (vertical) {        // se pinta en horizontal y se gira: se lee de abajo arriba
    ctx.translate(0, H);
    ctx.rotate(-PI / 2);
    [W, H] = [H, W];
  }
  const negroDebajo = sobreNegro(zona);
  let fondo, tinta, tinta2, arriba, abajo;
  switch (hueco.estado) {
    case "oferta":
      fondo = COLOR.azul; tinta = COLOR.blanco; tinta2 = COLOR.blanco;
      arriba = hueco.nombre; abajo = euros(hueco.maxima);
      break;
    case "adjudicado":
      fondo = negroDebajo ? COLOR.blanco : COLOR.negro;
      tinta = negroDebajo ? COLOR.negro : COLOR.blanco; tinta2 = tinta;
      arriba = hueco.adjudicado || "Adjudicado"; abajo = "";
      break;
    case "cerrado":
      fondo = COLOR.linea; tinta = COLOR.negro; tinta2 = COLOR.gris;
      arriba = hueco.nombre; abajo = hueco.maxima ? euros(hueco.maxima) : "Cerrado";
      break;
    default:   // libre
      fondo = COLOR.destello; tinta = COLOR.negro; tinta2 = COLOR.profundo;
      arriba = hueco.nombre; abajo = "Desde " + euros(hueco.minimo);
  }
  ctx.fillStyle = fondo;
  ctx.fillRect(0, 0, W, H);

  /* Marco: discontinuo si está libre, grueso si está elegido o bajo el ratón */
  const g = Math.round(Math.min(W, H) * 0.045);
  if (elegido || encima) {
    ctx.lineWidth = g * (elegido ? 2.4 : 1.4);
    ctx.strokeStyle = hueco.estado === "adjudicado" && !negroDebajo ? COLOR.azul : COLOR.negro;
    if (hueco.estado === "oferta") ctx.strokeStyle = COLOR.negro;
    ctx.strokeRect(ctx.lineWidth / 2, ctx.lineWidth / 2, W - ctx.lineWidth, H - ctx.lineWidth);
  } else if (hueco.estado === "libre") {
    ctx.lineWidth = g;
    ctx.setLineDash([g * 2.2, g * 1.4]);
    ctx.strokeStyle = COLOR.enlace;
    ctx.strokeRect(g, g, W - 2 * g, H - 2 * g);
    ctx.setLineDash([]);
  }

  ctx.fillStyle = tinta;
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  const ancho = W * 0.84;
  const display = (t) => `italic 900 ${t}px Archivo, "Arial Narrow", sans-serif`;
  const texto = (t) => `700 ${t}px Inter, system-ui, sans-serif`;
  /* En un parche alto y estrecho, cada palabra del nombre va en su línea */
  const alto = H > W * 1.15;
  const lineas = (alto ? arriba.toUpperCase().split(/\s+/) : [arriba.toUpperCase()])
    .map((t) => ({ t, f: display, color: tinta, peso: 1 }));
  if (abajo) lineas.push({ t: abajo.toUpperCase(), f: hueco.estado === "oferta" ? display : texto, color: tinta2, peso: 0.72 });
  if (lineas.length === 1) {
    linea(ctx, lineas[0].t, W / 2, H * 0.53, ancho, Math.round(H * 0.46), display);
  } else {
    const hueco_ = (H * 0.8) / lineas.length;
    const base = Math.min(hueco_ * 0.82, alto ? W * 0.3 : H * 0.34);
    lineas.forEach((l, i) => {
      ctx.fillStyle = l.color;
      linea(ctx, l.t, W / 2, H * 0.1 + hueco_ * (i + 0.55), ancho, Math.round(base * l.peso), l.f);
    });
  }
  ctx.restore();
  tex.needsUpdate = true;
}
