/* ============================================================
   AQUÍ NADIE ENTRENA — el mono en 3D (maillot + culotte)
   ------------------------------------------------------------
   Sin modelo externo. El cuerpo se describe como una función de
   distancia (SDF): tronco de sección elíptica que cambia con la
   altura, deltoides, glúteos, mangas y perneras, todo fundido con
   uniones suaves (hombros, sisas y entrepierna sin costuras). De
   esa función sale la malla con «surface nets» y cada vértice se
   pega a la superficie exacta. Las normales salen del gradiente,
   así que se ve liso aunque la rejilla sea de 8 mm.

   Los colores del equipaje (maillot blanco, culotte negro, cuello,
   puños en Azul ANE, silicona de las perneras, cremallera) no
   están en la malla: los pinta un shader según la posición, así
   que las líneas salen nítidas con cualquier resolución.

   Cada hueco de patrocinio es una calca (DecalGeometry) proyectada
   sobre el cuerpo: se ciñe a la forma sin trucos.
   Las claves de ZONAS deben coincidir con ANE_MAILLOT_ZONAS de
   api/_maillot.php.

   Ejes: y hacia arriba (metros), el pecho mira a +z y +x es el
   lado IZQUIERDO de quien lo lleva puesto.
   ============================================================ */

import {
  BufferGeometry, Float32BufferAttribute, Uint32BufferAttribute, Vector3, Euler, Mesh, Group, Object3D,
  MeshStandardMaterial, MeshPhysicalMaterial, CanvasTexture, SRGBColorSpace, Raycaster,
  PlaneGeometry, MeshBasicMaterial, Color,
} from "three";
import { DecalGeometry } from "three/examples/jsm/geometries/DecalGeometry.js";

const PI = Math.PI;

/* ---------- Colores de marca ---------- */
export const COLOR = {
  azul: "#3F77DA", enlace: "#2A5CB8", profundo: "#264783", destello: "#B2C8F0",
  negro: "#191919", blanco: "#FFFFFF", humo: "#F2F3F5", linea: "#E1E3E7", gris: "#6E747F",
};

/* ============================================================
   1. La forma: función de distancia
   ============================================================ */

/* Tronco: y · medio ancho · medio fondo delante · medio fondo detrás */
const PERFIL = [
  [0.835, 0.105, 0.062, 0.070],
  [0.880, 0.150, 0.090, 0.100],
  [0.930, 0.160, 0.095, 0.110],
  [0.990, 0.158, 0.092, 0.108],
  [1.050, 0.146, 0.090, 0.098],
  [1.100, 0.136, 0.092, 0.094],
  [1.170, 0.142, 0.101, 0.096],
  [1.240, 0.155, 0.113, 0.099],
  [1.310, 0.168, 0.120, 0.102],
  [1.365, 0.174, 0.113, 0.102],
  [1.405, 0.160, 0.096, 0.095],
  [1.440, 0.132, 0.082, 0.086],
  [1.462, 0.100, 0.072, 0.076],
  [1.480, 0.080, 0.064, 0.068],
  [1.495, 0.074, 0.061, 0.065],
];
const Y_CUELLO = 1.487;
const Y_BASE = 0.838;

/* Tabla precalculada del perfil (Catmull-Rom), para no interpolar en cada punto */
const TAB_N = 1024;
const TAB_Y0 = PERFIL[0][0], TAB_Y1 = PERFIL[PERFIL.length - 1][0];
const TAB = new Float32Array(TAB_N * 3);
(function () {
  const n = PERFIL.length;
  for (let s = 0; s < TAB_N; s++) {
    const y = TAB_Y0 + (TAB_Y1 - TAB_Y0) * s / (TAB_N - 1);
    let i = 0;
    while (i < n - 2 && y > PERFIL[i + 1][0]) i++;
    const p0 = PERFIL[Math.max(0, i - 1)], p1 = PERFIL[i], p2 = PERFIL[i + 1], p3 = PERFIL[Math.min(n - 1, i + 2)];
    const t = (y - p1[0]) / (p2[0] - p1[0]), t2 = t * t, t3 = t2 * t;
    for (let k = 1; k <= 3; k++) {
      TAB[s * 3 + k - 1] = 0.5 * ((2 * p1[k]) + (-p0[k] + p2[k]) * t + (2 * p0[k] - 5 * p1[k] + 4 * p2[k] - p3[k]) * t2
        + (-p0[k] + 3 * p1[k] - 3 * p2[k] + p3[k]) * t3);
    }
  }
})();

function tronco(x, y, z) {
  const yc = Math.min(TAB_Y1, Math.max(TAB_Y0, y));
  const s = Math.round((yc - TAB_Y0) / (TAB_Y1 - TAB_Y0) * (TAB_N - 1)) * 3;
  const a = TAB[s], b = z >= 0 ? TAB[s + 1] : TAB[s + 2];
  const q = Math.sqrt((x / a) * (x / a) + (z / b) * (z / b));
  let d = (q - 1) * Math.min(a, b);
  d = Math.max(d, y - Y_CUELLO, Y_BASE - y);
  return d;
}

/* Cono redondeado (Íñigo Quílez), con el extremo b cortado en plano */
function cono(a, b, r1, r2) {
  const bax = b[0] - a[0], bay = b[1] - a[1], baz = b[2] - a[2];
  const l2 = bax * bax + bay * bay + baz * baz, L = Math.sqrt(l2);
  const rr = r1 - r2, a2 = l2 - rr * rr, il2 = 1 / l2;
  const dx = bax / L, dy = bay / L, dz = baz / L;
  const f = function (x, y, z) {
    const pax = x - a[0], pay = y - a[1], paz = z - a[2];
    const yy = pax * bax + pay * bay + paz * baz;
    const zz = yy - l2;
    const qx = pax * l2 - bax * yy, qy = pay * l2 - bay * yy, qz = paz * l2 - baz * yy;
    const x2 = qx * qx + qy * qy + qz * qz;
    const y2 = yy * yy * l2, z2 = zz * zz * l2;
    const k = Math.sign(rr) * rr * rr * x2;
    let d;
    if (Math.sign(zz) * a2 * z2 > k) d = Math.sqrt(x2 + z2) * il2 - r2;
    else if (Math.sign(yy) * a2 * y2 < k) d = Math.sqrt(x2 + y2) * il2 - r1;
    else d = (Math.sqrt(x2 * a2 * il2) + yy * rr) * il2 - r1;
    /* corte plano en b: manga y pernera acaban en un borde, como la prenda */
    return Math.max(d, (x - b[0]) * dx + (y - b[1]) * dy + (z - b[2]) * dz);
  };
  f.a = a; f.b = b; f.r1 = r1; f.r2 = r2; f.L = L; f.dir = [dx, dy, dz];
  return f;
}

function elipsoide(c, r) {
  return function (x, y, z) {
    const px = (x - c[0]) / r[0], py = (y - c[1]) / r[1], pz = (z - c[2]) / r[2];
    const k0 = Math.sqrt(px * px + py * py + pz * pz);
    const k1 = Math.sqrt(px * px / (r[0] * r[0]) + py * py / (r[1] * r[1]) + pz * pz / (r[2] * r[2]));
    return k1 > 0 ? k0 * (k0 - 1) / k1 : -Math.min(r[0], r[1], r[2]);
  };
}

function smin(a, b, k) {
  const h = Math.max(k - Math.abs(a - b), 0) / k;
  return Math.min(a, b) - h * h * k * 0.25;
}

/* Las piezas, a los dos lados. Proporciones de un ciclista de 1,80 m:
   brazos casi pegados al cuerpo, como en los configuradores de equipaje. */
export const MANGAS = {}, PIERNAS = {};
const DELTOIDES = {}, GLUTEOS = {}, PECTORALES = {}, DORSALES = {}, CUADRICEPS = {}, ISQUIOS = {};
for (const s of [1, -1]) {
  MANGAS[s] = cono([0.184 * s, 1.392, -0.006], [0.226 * s, 1.062, 0.014], 0.057, 0.044);
  PIERNAS[s] = cono([0.086 * s, 0.940, -0.012], [0.104 * s, 0.535, 0.012], 0.095, 0.068);
  DELTOIDES[s] = elipsoide([0.170 * s, 1.384, -0.004], [0.056, 0.066, 0.064]);
  PECTORALES[s] = elipsoide([0.066 * s, 1.318, 0.078], [0.078, 0.056, 0.042]);
  DORSALES[s] = elipsoide([0.112 * s, 1.235, -0.035], [0.052, 0.105, 0.064]);
  GLUTEOS[s] = elipsoide([0.066 * s, 0.962, -0.062], [0.088, 0.090, 0.071]);
  CUADRICEPS[s] = elipsoide([0.092 * s, 0.770, 0.030], [0.074, 0.165, 0.068]);
  ISQUIOS[s] = elipsoide([0.090 * s, 0.790, -0.032], [0.070, 0.150, 0.064]);
}

export function sdf(x, y, z) {
  const s = x >= 0 ? 1 : -1;          // simétrico: basta con el lado del punto
  let d = tronco(x, y, z);
  d = smin(d, PECTORALES[s](x, y, z), 0.025);
  d = smin(d, DORSALES[s](x, y, z), 0.035);
  d = smin(d, GLUTEOS[s](x, y, z), 0.03);
  d = smin(d, DELTOIDES[s](x, y, z), 0.03);
  d = smin(d, MANGAS[s](x, y, z), 0.02);
  /* Cada pierna, con sus músculos, se funde con la cadera; las dos piernas entre sí no */
  const pierna = (t) => smin(smin(PIERNAS[t](x, y, z), CUADRICEPS[t](x, y, z), 0.03), ISQUIOS[t](x, y, z), 0.03);
  d = smin(d, smin(pierna(1), pierna(-1), 0.012), 0.035);
  return d;
}

/* Oclusión ambiental: cuánto «se esconde» cada punto (axilas, entrepierna,
   bajo el glúteo). Se calcula una vez por vértice. */
function oclusion(x, y, z, nx, ny, nz) {
  let occ = 0, peso = 1;
  for (let i = 1; i <= 5; i++) {
    const h = 0.012 * i;
    occ += (h - sdf(x + nx * h, y + ny * h, z + nz * h)) * peso;
    peso *= 0.75;
  }
  return Math.min(1, Math.max(0.25, 1 - occ * 7));
}

/* ============================================================
   2. De la función a la malla: surface nets
   ============================================================ */

const CAJA = { x0: -0.35, x1: 0.35, y0: 0.50, y1: 1.54, z0: -0.2, z1: 0.2 };

export function mallaMono(paso = 0.008) {
  const nx = Math.ceil((CAJA.x1 - CAJA.x0) / paso) + 1;
  const ny = Math.ceil((CAJA.y1 - CAJA.y0) / paso) + 1;
  const nz = Math.ceil((CAJA.z1 - CAJA.z0) / paso) + 1;
  const campo = new Float32Array(nx * ny * nz);
  const idx = (i, j, k) => (k * ny + j) * nx + i;
  for (let k = 0; k < nz; k++) {
    const z = CAJA.z0 + k * paso;
    for (let j = 0; j < ny; j++) {
      const y = CAJA.y0 + j * paso;
      for (let i = 0; i < nx; i++) campo[idx(i, j, k)] = sdf(CAJA.x0 + i * paso, y, z);
    }
  }

  /* Un vértice por celda que cruza la superficie */
  const vcelda = new Int32Array((nx - 1) * (ny - 1) * (nz - 1)).fill(-1);
  const cidx = (i, j, k) => (k * (ny - 1) + j) * (nx - 1) + i;
  const pos = [];
  const ARISTAS = [[0, 1], [2, 3], [4, 5], [6, 7], [0, 2], [1, 3], [4, 6], [5, 7], [0, 4], [1, 5], [2, 6], [3, 7]];
  const esq = new Float32Array(8);
  for (let k = 0; k < nz - 1; k++) {
    for (let j = 0; j < ny - 1; j++) {
      for (let i = 0; i < nx - 1; i++) {
        let mascara = 0;
        for (let c = 0; c < 8; c++) {
          const v = campo[idx(i + (c & 1), j + ((c >> 1) & 1), k + ((c >> 2) & 1))];
          esq[c] = v;
          if (v < 0) mascara |= 1 << c;
        }
        if (mascara === 0 || mascara === 255) continue;
        let sx = 0, sy = 0, sz = 0, n = 0;
        for (const [c0, c1] of ARISTAS) {
          const v0 = esq[c0], v1 = esq[c1];
          if ((v0 < 0) === (v1 < 0)) continue;
          const t = v0 / (v0 - v1);
          sx += (c0 & 1) + ((c1 & 1) - (c0 & 1)) * t;
          sy += ((c0 >> 1) & 1) + (((c1 >> 1) & 1) - ((c0 >> 1) & 1)) * t;
          sz += ((c0 >> 2) & 1) + (((c1 >> 2) & 1) - ((c0 >> 2) & 1)) * t;
          n++;
        }
        vcelda[cidx(i, j, k)] = pos.length / 3;
        pos.push(CAJA.x0 + (i + sx / n) * paso, CAJA.y0 + (j + sy / n) * paso, CAJA.z0 + (k + sz / n) * paso);
      }
    }
  }

  /* Un quad por cada arista de la rejilla que cruza la superficie */
  const tri = [];
  const quad = (a, b, c, d, dentroPrimero) => {
    if (a < 0 || b < 0 || c < 0 || d < 0) return;
    if (dentroPrimero) tri.push(a, b, c, a, c, d);
    else tri.push(a, c, b, a, d, c);
  };
  for (let k = 1; k < nz - 1; k++) {
    for (let j = 1; j < ny - 1; j++) {
      for (let i = 1; i < nx - 1; i++) {
        const v0 = campo[idx(i, j, k)] < 0;
        if (v0 !== (campo[idx(i + 1, j, k)] < 0)) {   // arista en x
          quad(vcelda[cidx(i, j - 1, k - 1)], vcelda[cidx(i, j, k - 1)], vcelda[cidx(i, j, k)], vcelda[cidx(i, j - 1, k)], v0);
        }
        if (v0 !== (campo[idx(i, j + 1, k)] < 0)) {   // arista en y
          quad(vcelda[cidx(i - 1, j, k - 1)], vcelda[cidx(i - 1, j, k)], vcelda[cidx(i, j, k)], vcelda[cidx(i, j, k - 1)], v0);
        }
        if (v0 !== (campo[idx(i, j, k + 1)] < 0)) {   // arista en z
          quad(vcelda[cidx(i - 1, j - 1, k)], vcelda[cidx(i, j - 1, k)], vcelda[cidx(i, j, k)], vcelda[cidx(i - 1, j, k)], v0);
        }
      }
    }
  }

  /* Cada vértice, pegado a la superficie exacta; la normal, del gradiente */
  const nor = new Float32Array(pos.length);
  const ao = new Float32Array(pos.length / 3);
  const e = 0.0006;
  for (let v = 0; v < pos.length; v += 3) {
    let x = pos[v], y = pos[v + 1], z = pos[v + 2];
    let gx = 0, gy = 0, gz = 0;
    for (let it = 0; it < 3; it++) {
      const d = sdf(x, y, z);
      gx = sdf(x + e, y, z) - sdf(x - e, y, z);
      gy = sdf(x, y + e, z) - sdf(x, y - e, z);
      gz = sdf(x, y, z + e) - sdf(x, y, z - e);
      const g2 = (gx * gx + gy * gy + gz * gz) / (4 * e * e);
      if (g2 < 1e-6) break;
      const f = d / g2 / (2 * e);
      x -= gx * f; y -= gy * f; z -= gz * f;
      if (Math.abs(d) < 1e-5) break;
    }
    pos[v] = x; pos[v + 1] = y; pos[v + 2] = z;
    const gl = Math.hypot(gx, gy, gz) || 1;
    nor[v] = gx / gl; nor[v + 1] = gy / gl; nor[v + 2] = gz / gl;
    ao[v / 3] = oclusion(x, y, z, nor[v], nor[v + 1], nor[v + 2]);
  }

  const g = new BufferGeometry();
  g.setAttribute("position", new Float32BufferAttribute(pos, 3));
  g.setAttribute("normal", new Float32BufferAttribute(nor, 3));
  g.setAttribute("oclusion", new Float32BufferAttribute(ao, 1));
  g.setIndex(new Uint32BufferAttribute(tri, 1));
  g.computeBoundingSphere();
  return g;
}

/* ============================================================
   3. El equipaje: colores pintados por posición
   ============================================================ */

function materialEquipaje() {
  const m = new MeshPhysicalMaterial({
    color: "#ffffff", roughness: 0.6, metalness: 0, sheen: 0.18, sheenRoughness: 0.5, sheenColor: "#ffffff",
    envMapIntensity: 0.22,
  });
  const u = {
    cBlanco: { value: new Color("#DEE2E7") },
    cNegro: { value: new Color("#121315") },
    cAzul: { value: new Color(COLOR.azul) },
    cSilicona: { value: new Color("#2A2C31") },
    cDentro: { value: new Color("#9CA3AD") },
    cDentroNegro: { value: new Color("#0A0A0C") },
    cCostura: { value: new Color("#D2D6DC") },
    mA: { value: new Vector3(...MANGAS[1].a) }, mB: { value: new Vector3(...MANGAS[1].b) },
    pA: { value: new Vector3(...PIERNAS[1].a) }, pB: { value: new Vector3(...PIERNAS[1].b) },
    yCuello: { value: Y_CUELLO },
    cuelloAB: { value: new Vector3(TAB[TAB_N * 3 - 3], TAB[TAB_N * 3 - 2], TAB[TAB_N * 3 - 1]) },
    rMangaFin: { value: MANGAS[1].r2 }, rPiernaFin: { value: PIERNAS[1].r2 },
  };
  m.onBeforeCompile = (sh) => {
    Object.assign(sh.uniforms, u);
    sh.vertexShader = sh.vertexShader
      .replace("#include <common>", "#include <common>\nattribute float oclusion;\nvarying vec3 vObj;\nvarying vec3 vObjN;\nvarying float vOcl;")
      .replace("#include <begin_vertex>", "#include <begin_vertex>\nvObj = position;\nvObjN = normal;\nvOcl = oclusion;");
    sh.fragmentShader = sh.fragmentShader
      .replace("#include <common>", `#include <common>
varying vec3 vObj;
varying vec3 vObjN;
varying float vOcl;
uniform vec3 cBlanco, cNegro, cAzul, cSilicona, cDentro, cDentroNegro, cCostura;
uniform vec3 mA, mB, pA, pB;
uniform float yCuello, rMangaFin, rPiernaFin;
uniform vec3 cuelloAB;
float rugosidad = 0.6;
float linea(float d, float ancho) { float w = fwidth(d) + 0.0002; return 1.0 - smoothstep(ancho - w, ancho + w, abs(d)); }
/* Punto (t a lo largo, r al eje, radial) respecto a un tubo */
vec3 tubo(vec3 q, vec3 a, vec3 b, out vec3 radial) {
  vec3 ba = b - a; float L = length(ba); vec3 d = ba / L;
  float ax = dot(q - a, d);
  vec3 rv = (q - a) - d * ax;
  radial = normalize(rv + vec3(1e-6));
  return vec3(ax / L, length(rv), L);
}
vec3 equipaje(vec3 p, vec3 n) {
  vec3 q = vec3(abs(p.x), p.y, p.z);          // los dos lados son iguales
  vec3 qn = vec3(sign(p.x) * n.x, n.y, n.z);
  float th = atan(p.x, p.z);
  float at = abs(th);
  /* Corte blanco/negro de mono de contrarreloj: el negro sube por los
     costados; delante el blanco baja en pico y detrás, en U */
  float corte = at < 1.5708
    ? 0.905 + 0.225 * pow(clamp(at / 1.5708, 0.0, 1.0), 0.85)
    : 0.995 + 0.135 * pow(clamp((3.14159 - at) / 1.5708, 0.0, 1.0), 1.7);
  float wy = fwidth(p.y) + 0.0006;
  float grosor = 0.0045;
  float blanco = smoothstep(corte - wy, corte + wy, p.y);
  vec3 rm; vec3 m = tubo(q, mA, mB, rm);
  float enManga = step(m.y, 0.085) * step(0.22, m.x) * step(m.x, 1.03);
  blanco = max(blanco, enManga);
  vec3 c = mix(cNegro, cBlanco, blanco);
  rugosidad = mix(0.42, 0.6, blanco);

  /* Tejido: canalé fino (a lo largo en la manga, en horizontal en el tronco) */
  float canal = enManga > 0.5 ? sin(atan(rm.z, rm.x) * 110.0) : sin(p.y * 560.0);
  c *= 1.0 - 0.03 * blanco * canal;
  /* Paneles de rejilla detrás del hombro, como en los monos de crono */
  float xs = mix(0.074, 0.158, clamp((yCuello - p.y) / 0.19, 0.0, 1.0));   // costura raglán
  if (p.z < -0.02 && q.x > xs && q.x < xs + 0.042 && p.y > 1.24 && p.y < yCuello - 0.03 && enManga < 0.5) {
    vec2 uv = vec2(q.x * 900.0, p.y * 900.0);
    uv.x += step(1.0, mod(uv.y, 2.0)) * 0.5;
    float punto = 1.0 - smoothstep(0.22, 0.32, length(fract(uv) - 0.5));
    c = mix(c, c * 0.72, punto);
  }
  /* Costuras: raglán delante y detrás, costados y bajo del cuello */
  float cost = 0.0;
  if (p.y > 1.28 && p.y < yCuello - 0.01 && abs(p.z) > 0.025) cost = max(cost, linea(q.x - xs, 0.0009));
  if (enManga < 0.5 && p.y > corte + 0.01 && p.y < 1.33) cost = max(cost, linea(p.z + 0.004, 0.0009) * step(0.08, q.x));
  if (p.z < -0.05 && p.y > corte + 0.02) cost = max(cost, linea(p.y - 1.045, 0.001));
  c = mix(c, c * 0.78, cost * blanco);

  /* Cuello: ribete negro */
  c = mix(c, cNegro, smoothstep(yCuello - 0.009 - wy, yCuello - 0.009 + wy, p.y));
  /* Cremallera escondida */
  if (p.z > 0.0 && p.y > corte + 0.01 && p.y < yCuello - 0.013) c = mix(c, c * 0.84, linea(p.x, 0.0012));

  /* Puño en Azul ANE */
  if (enManga > 0.5) c = mix(c, cAzul, smoothstep(0.935 - 0.004, 0.935 + 0.004, m.x));
  /* Perneras: banda de silicona */
  vec3 rp; vec3 pp = tubo(q, pA, pB, rp);
  bool enPierna = pp.y < 0.13 && pp.x > 0.5 && pp.x < 1.03;
  if (enPierna) c = mix(c, cSilicona, smoothstep(0.935 - 0.004, 0.935 + 0.004, pp.x));

  /* Bocas de cuello, mangas y perneras: son tapas planas, pero se pintan como
     un hueco con el grosor de la tela en el borde y sombra hacia dentro */
  float boca = -1.0; vec3 dentro = cDentro; vec3 borde = c;
  if (p.y > yCuello - 0.002 && n.y > 0.7) {
    float e = length(vec2(p.x / cuelloAB.x, p.z / (p.z >= 0.0 ? cuelloAB.y : cuelloAB.z)));
    boca = (1.0 - e) * min(cuelloAB.x, cuelloAB.y); borde = cNegro;
  } else if (enManga > 0.5 && m.x > 0.995 && dot(qn, normalize(mB - mA)) > 0.7) {
    boca = rMangaFin - m.y; borde = cAzul;
  } else if (enPierna && pp.x > 0.995 && dot(qn, normalize(pB - pA)) > 0.7) {
    boca = rPiernaFin - pp.y; borde = cSilicona; dentro = cDentroNegro;
  }
  if (boca > -0.5) {
    float hondo = smoothstep(grosor, grosor + 0.03, boca);
    c = boca < grosor ? borde * 0.92 : mix(dentro, dentro * 0.35, hondo);
    rugosidad = 0.95;
  }
  return c * mix(0.35, 1.0, vOcl);
}`)
      .replace("vec4 diffuseColor = vec4( diffuse, opacity );",
               "vec4 diffuseColor = vec4( equipaje( vObj, normalize( vObjN ) ), opacity );")
      .replace("float roughnessFactor = roughness;", "float roughnessFactor = rugosidad;");
  };
  return m;
}

/* ============================================================
   4. Montaje
   ============================================================ */

export function creaMono(paso) {
  const grupo = new Group();
  const cuerpo = new Mesh(mallaMono(paso), materialEquipaje());
  grupo.add(cuerpo);

  /* Sombra suave en el suelo, para que no flote en el vacío */
  const c = document.createElement("canvas");
  c.width = c.height = 128;
  const x = c.getContext("2d");
  const gr = x.createRadialGradient(64, 64, 4, 64, 64, 62);
  gr.addColorStop(0, "rgba(25,25,25,.26)");
  gr.addColorStop(1, "rgba(25,25,25,0)");
  x.fillStyle = gr;
  x.fillRect(0, 0, 128, 128);
  const sombra = new Mesh(new PlaneGeometry(0.6, 0.28),
    new MeshBasicMaterial({ map: new CanvasTexture(c), transparent: true, depthWrite: false }));
  sombra.rotation.x = -PI / 2;
  sombra.position.set(0, 0.46, 0);
  grupo.add(sombra);

  return { grupo, cuerpo: [cuerpo], malla: cuerpo };
}

/* ============================================================
   5. Huecos: dónde va cada calca
   ------------------------------------------------------------
   desde: punto de fuera desde el que se lanza un rayo hacia el cuerpo
   hacia: dirección del rayo
   tam:   ancho × alto de la calca (m)
   mira:  giro del mono (radianes) para dejar el hueco de frente
   vertical: el texto va girado 90°
   ============================================================ */

function haciaFuera(tubo, t, lado) {
  const a = new Vector3(...tubo.a), b = new Vector3(...tubo.b);
  const c = a.clone().lerp(b, t);
  const eje = b.clone().sub(a).normalize();
  const fuera = new Vector3(lado, 0, 0);
  fuera.sub(eje.clone().multiplyScalar(fuera.dot(eje))).normalize();
  return { desde: c.clone().add(fuera.clone().multiplyScalar(0.6)), hacia: fuera.negate() };
}

export const ZONAS = {
  "pecho":        { desde: [0, 1.300, 1], hacia: [0, 0, -1], tam: [0.23, 0.085], mira: 0 },
  "abdomen":      { desde: [0, 1.150, 1], hacia: [0, 0, -1], tam: [0.20, 0.080], mira: 0 },
  "espalda-alta": { desde: [0, 1.320, -1], hacia: [0, 0, 1], tam: [0.25, 0.095], mira: PI },
  "espalda-baja": { desde: [0, 1.140, -1], hacia: [0, 0, 1], tam: [0.21, 0.080], mira: PI },
  "costado-izq":  { desde: [1, 1.02, 0.005], hacia: [-1, 0, 0], tam: [0.08, 0.12], mira: -1.2, vertical: true },
  "costado-der":  { desde: [-1, 1.02, 0.005], hacia: [1, 0, 0], tam: [0.08, 0.12], mira: 1.2, vertical: true },
  "manga-izq":    { tubo: "manga", lado: 1, t: 0.52, tam: [0.10, 0.12], mira: -PI / 2 },
  "manga-der":    { tubo: "manga", lado: -1, t: 0.52, tam: [0.10, 0.12], mira: PI / 2 },
  "culote-izq":   { tubo: "pierna", lado: 1, t: 0.5, tam: [0.09, 0.22], mira: -PI / 2, vertical: true },
  "culote-der":   { tubo: "pierna", lado: -1, t: 0.5, tam: [0.09, 0.22], mira: PI / 2, vertical: true },
};

/* ¿El hueco está sobre el negro del culotte? */
export const sobreNegro = (zona) => ZONAS[zona].tubo === "pierna" || zona.startsWith("costado");

const rayo = new Raycaster();
export function creaParche(zona, malla) {
  const z = ZONAS[zona];
  let desde, hacia;
  if (z.tubo) {
    ({ desde, hacia } = haciaFuera(z.tubo === "manga" ? MANGAS[z.lado] : PIERNAS[z.lado], z.t, z.lado));
  } else {
    desde = new Vector3(...z.desde);
    hacia = new Vector3(...z.hacia).normalize();
  }
  malla.updateMatrixWorld(true);
  rayo.set(malla.localToWorld(desde.clone()), hacia);   // el grupo aún no está girado
  const toque = rayo.intersectObject(malla, false)[0];
  if (!toque) return null;
  const n = toque.face.normal.clone();
  /* Orientación: +z de la calca = normal; «arriba» lo más vertical posible */
  const ayuda = new Object3D();
  ayuda.position.copy(toque.point);
  ayuda.lookAt(toque.point.clone().add(n));
  const geo = new DecalGeometry(malla, toque.point, new Euler().copy(ayuda.rotation), new Vector3(z.tam[0], z.tam[1], 0.07));
  /* DecalGeometry trabaja en coordenadas del mundo: se pasan a las de la malla */
  geo.applyMatrix4(malla.matrixWorld.clone().invert());
  /* Un pelo por encima de la tela: se ve siempre y el ratón la encuentra antes que al cuerpo */
  const pa = geo.attributes.position, na = geo.attributes.normal;
  for (let i = 0; i < pa.count; i++) {
    pa.setXYZ(i, pa.getX(i) + na.getX(i) * 0.0015, pa.getY(i) + na.getY(i) * 0.0015, pa.getZ(i) + na.getZ(i) * 0.0015);
  }

  const lienzo = document.createElement("canvas");
  const [w, h] = z.tam;
  if (w >= h) { lienzo.width = 512; lienzo.height = Math.round(Math.max(96, 512 * h / w)); }
  else { lienzo.height = 512; lienzo.width = Math.round(Math.max(96, 512 * w / h)); }
  const tex = new CanvasTexture(lienzo);
  tex.colorSpace = SRGBColorSpace;
  tex.anisotropy = 4;
  const mat = new MeshStandardMaterial({
    map: tex, roughness: 0.5, metalness: 0, transparent: true,
    polygonOffset: true, polygonOffsetFactor: -4, polygonOffsetUnits: -4, depthWrite: false,
  });
  const mesh = new Mesh(geo, mat);
  mesh.renderOrder = 2;
  mesh.userData = { zona, lienzo, tex, vertical: !!z.vertical, mira: z.mira };
  return mesh;
}

/* ============================================================
   6. Lo que se pinta en cada calca
   ============================================================ */

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

function rectRedondo(ctx, x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
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
  let fondo, tinta, tinta2, arriba, abajo, alfa = 1;
  switch (hueco.estado) {
    case "oferta":
      fondo = COLOR.azul; tinta = COLOR.blanco; tinta2 = COLOR.blanco;
      arriba = hueco.nombre; abajo = euros(hueco.maxima);
      break;
    case "adjudicado":
      fondo = null; tinta = negroDebajo ? COLOR.blanco : COLOR.negro; tinta2 = tinta;
      arriba = hueco.adjudicado || "Adjudicado"; abajo = "";
      break;
    case "cerrado":
      fondo = COLOR.linea; tinta = COLOR.negro; tinta2 = COLOR.gris; alfa = 0.9;
      arriba = hueco.nombre; abajo = hueco.maxima ? euros(hueco.maxima) : "Cerrado";
      break;
    default:   // libre
      fondo = negroDebajo ? "rgba(178,200,240,.92)" : "rgba(178,200,240,.78)";
      tinta = COLOR.negro; tinta2 = COLOR.profundo;
      arriba = hueco.nombre; abajo = hueco.minimo > 1 ? "Desde " + euros(hueco.minimo) : "Haz tu oferta";
  }
  const r = Math.min(W, H) * 0.08;
  const g = Math.round(Math.min(W, H) * 0.04);
  ctx.globalAlpha = alfa;
  if (fondo) {
    ctx.fillStyle = fondo;
    rectRedondo(ctx, 0, 0, W, H, r);
    ctx.fill();
  }
  ctx.globalAlpha = 1;
  /* Marco: discontinuo si está libre, grueso si está elegido o bajo el ratón */
  if (elegido || encima) {
    ctx.lineWidth = g * (elegido ? 2.4 : 1.4);
    ctx.strokeStyle = negroDebajo && hueco.estado !== "libre" ? COLOR.blanco : COLOR.negro;
    rectRedondo(ctx, ctx.lineWidth / 2, ctx.lineWidth / 2, W - ctx.lineWidth, H - ctx.lineWidth, r);
    ctx.stroke();
  } else if (hueco.estado === "libre") {
    ctx.lineWidth = g;
    ctx.setLineDash([g * 2.2, g * 1.4]);
    ctx.strokeStyle = COLOR.enlace;
    rectRedondo(ctx, g, g, W - 2 * g, H - 2 * g, r * 0.7);
    ctx.stroke();
    ctx.setLineDash([]);
  }

  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  const ancho = W * 0.84;
  const display = (t) => `italic 900 ${t}px Archivo, "Arial Narrow", sans-serif`;
  const texto = (t) => `700 ${t}px Inter, system-ui, sans-serif`;
  /* En una calca alta y estrecha, cada palabra del nombre va en su línea */
  const alto = H > W * 1.15;
  const lineas = (alto ? arriba.toUpperCase().split(/\s+/) : [arriba.toUpperCase()])
    .map((t) => ({ t, f: display, color: tinta, peso: 1 }));
  if (abajo) lineas.push({ t: abajo.toUpperCase(), f: hueco.estado === "oferta" ? display : texto, color: tinta2, peso: 0.72 });
  if (lineas.length === 1) {
    ctx.fillStyle = tinta;
    linea(ctx, lineas[0].t, W / 2, H * 0.53, ancho, Math.round(H * 0.5), display);
  } else {
    const hl = (H * 0.8) / lineas.length;
    const base = Math.min(hl * 0.82, alto ? W * 0.3 : H * 0.34);
    lineas.forEach((l, i) => {
      ctx.fillStyle = l.color;
      linea(ctx, l.t, W / 2, H * 0.1 + hl * (i + 0.55), ancho, Math.round(base * l.peso), l.f);
    });
  }
  ctx.restore();
  tex.needsUpdate = true;
}
