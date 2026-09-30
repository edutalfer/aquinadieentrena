/* ============================================================
   AQUÍ NADIE ENTRENA — página /maillot
   ------------------------------------------------------------
   Monta la escena 3D, la conecta con la ficha y el formulario,
   y envía las ofertas sin recargar la página.
   Todo lo que pinta el servidor funciona también sin esto.

   Se empaqueta con three.js en assets/js/maillot.min.js:
   ver assets/js/maillot/LEEME.md
   ============================================================ */

import {
  WebGLRenderer, Scene, PerspectiveCamera, HemisphereLight, DirectionalLight,
  Raycaster, Vector2, NeutralToneMapping, SRGBColorSpace, Group,
} from "three";
import { creaMono, creaParche, pintaParche, ZONAS } from "./mono.js";

const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

const datos = JSON.parse($("#datos-maillot").textContent);
let proyectos = datos.proyectos || [];
let actual = datos.actual;
let huecos = {};
const proyectoActual = () => proyectos.find((p) => p.slug === actual) || proyectos[0] || { huecos: [], abierto: false };
function guardaDatos(lista) {
  if (lista) proyectos = lista;
  huecos = {};
  for (const h of proyectoActual().huecos) if (h.estado !== "oculto") huecos[h.zona] = h;
}
guardaDatos();

const ofertable = (h) => h && (h.estado === "libre" || h.estado === "oferta");
const euros = (n) => String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ".") + " €";
const reposo = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

let elegida = "";
let encima = "";

/* ============================================================
   Escena 3D
   ============================================================ */

const escena3d = (() => {
  const caja = $("#lienzo");
  let renderer;
  try {
    renderer = new WebGLRenderer({ antialias: true, alpha: true });
  } catch (e) {
    $("#escena").classList.add("escena--sin3d");
    return null;
  }
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.outputColorSpace = SRGBColorSpace;
  renderer.toneMapping = NeutralToneMapping;
  renderer.toneMappingExposure = 1.0;
  caja.appendChild(renderer.domElement);

  const scene = new Scene();
  const camara = new PerspectiveCamera(28, 1, 0.1, 20);
  const CENTRO_Y = 1.02;

  scene.add(new HemisphereLight("#ffffff", "#aab0ba", 1.35));
  const clave = new DirectionalLight("#ffffff", 2.1);
  clave.position.set(1.6, 2.6, 3.2);
  scene.add(clave);
  const relleno = new DirectionalLight("#ffffff", 0.7);
  relleno.position.set(-3, 1.2, 1.5);
  scene.add(relleno);
  const contra = new DirectionalLight("#ffffff", 1.1);
  contra.position.set(0.5, 2.2, -3.2);
  scene.add(contra);

  /* pivote (inclinación) → giro (vueltas) → mono */
  const pivote = new Group();
  const giro = new Group();
  pivote.add(giro);
  scene.add(pivote);
  pivote.position.y = CENTRO_Y;
  /* En móviles, rejilla algo más gruesa: la malla se calcula en el navegador */
  const movil = window.matchMedia("(max-width: 700px)").matches;
  const { grupo, cuerpo, malla } = creaMono(movil ? 0.0095 : 0.0075);
  grupo.position.y = -CENTRO_Y;
  giro.add(grupo);
  scene.updateMatrixWorld(true);

  /* Una calca por hueco del catálogo; se ven solo las del proyecto elegido */
  const parches = {};
  for (const zona of Object.keys(ZONAS)) {
    const m = creaParche(zona, malla);
    if (!m) continue;
    parches[zona] = m;
    grupo.add(m);
  }
  const pintaTodos = () => {
    for (const [zona, m] of Object.entries(parches)) {
      m.visible = !!huecos[zona];
      if (huecos[zona]) pintaParche(m, huecos[zona], { elegido: zona === elegida, encima: zona === encima });
    }
  };
  /* Las fuentes de marca tienen que estar cargadas antes de escribir en los lienzos */
  pintaTodos();
  Promise.all([
    document.fonts.load('italic 900 40px Archivo'),
    document.fonts.load('700 40px Inter'),
  ]).then(pintaTodos, pintaTodos);

  /* ---------- Cámara: que el mono quepa entero, sea cual sea la caja ---------- */
  function encuadra() {
    const w = caja.clientWidth, h = caja.clientHeight;
    if (!w || !h) return;
    renderer.setSize(w, h, false);
    camara.aspect = w / h;
    const tan = Math.tan((camara.fov * Math.PI / 180) / 2);
    const dist = Math.max(0.6 / tan, 0.42 / (tan * camara.aspect)) * 1.02;
    camara.position.set(0, CENTRO_Y + 0.05, dist);
    camara.lookAt(0, CENTRO_Y - 0.02, 0);
    camara.updateProjectionMatrix();
  }
  new ResizeObserver(encuadra).observe(caja);
  encuadra();

  /* ---------- Giro: arrastrar, botones y giro automático ---------- */
  let angulo = 0, anguloObj = 0, incl = 0.06, inclObj = 0.06;
  let auto = !reposo;
  let arrastre = null;
  const botonGiro = $("#girar");
  const ponAuto = (v) => {
    auto = v;
    if (botonGiro) botonGiro.setAttribute("aria-pressed", String(v));
  };
  ponAuto(auto);
  botonGiro && botonGiro.addEventListener("click", () => ponAuto(!auto));

  /* Lleva el giro al ángulo pedido por el camino más corto */
  function mira(rad) {
    ponAuto(false);
    const dos = Math.PI * 2;
    let d = ((rad - anguloObj) % dos + dos) % dos;
    if (d > Math.PI) d -= dos;
    anguloObj += d;
    inclObj = 0.06;
  }
  $$("[data-vista]").forEach((b) => b.addEventListener("click", () => mira(Number(b.dataset.vista) * Math.PI / 180)));

  const pista = $("#pista");
  const ocultaPista = () => { if (pista) pista.style.opacity = "0"; };

  const el = renderer.domElement;
  el.addEventListener("pointerdown", (e) => {
    arrastre = { x: e.clientX, y: e.clientY, a: anguloObj, i: inclObj, movido: false, id: e.pointerId, tipo: e.pointerType };
  });
  el.addEventListener("pointermove", (e) => {
    if (arrastre && arrastre.id === e.pointerId) {
      const dx = e.clientX - arrastre.x, dy = e.clientY - arrastre.y;
      if (!arrastre.movido && Math.hypot(dx, dy) > 6) {
        arrastre.movido = true;
        ponAuto(false);
        ocultaPista();
        try { el.setPointerCapture(e.pointerId); } catch (_) { /* nada */ }
      }
      if (arrastre.movido) {
        anguloObj = arrastre.a + dx * 0.012;
        /* En el móvil el arrastre vertical es para hacer scroll: solo inclina con ratón */
        if (arrastre.tipo === "mouse") inclObj = Math.max(-0.25, Math.min(0.4, arrastre.i + dy * 0.004));
      }
      return;
    }
    if (e.pointerType === "mouse") {
      const z = elige(e);
      if (z !== encima) {
        const antes = encima;
        encima = z;
        [antes, z].forEach((k) => k && parches[k] && pintaParche(parches[k], huecos[k], { elegido: k === elegida, encima: k === encima }));
        el.style.cursor = z ? "pointer" : "";
      }
    }
  });
  const suelta = (e) => {
    if (!arrastre || arrastre.id !== e.pointerId) return;
    const clic = !arrastre.movido;
    arrastre = null;
    if (clic && e.type === "pointerup") {
      const z = elige(e);
      if (z) {
        ocultaPista();
        ponAuto(false);
        eligeZona(z, { desde3d: true });
      }
    }
  };
  el.addEventListener("pointerup", suelta);
  el.addEventListener("pointercancel", suelta);
  el.addEventListener("pointerleave", () => {
    if (encima) {
      const k = encima;
      encima = "";
      parches[k] && pintaParche(parches[k], huecos[k], { elegido: k === elegida });
      el.style.cursor = "";
    }
  });

  /* ---------- Qué hueco hay bajo el puntero (el cuerpo tapa lo de detrás) ---------- */
  const rayo = new Raycaster();
  const punto = new Vector2();
  const objetivos = [...cuerpo, ...Object.values(parches)];
  function elige(e) {
    const r = el.getBoundingClientRect();
    punto.set(((e.clientX - r.left) / r.width) * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1);
    rayo.setFromCamera(punto, camara);
    const toque = rayo.intersectObjects(objetivos, false)[0];
    const z = toque && toque.object.userData.zona;
    return z && huecos[z] ? z : "";
  }

  /* ---------- Bucle: solo mientras se ve ---------- */
  let visible = true;
  new IntersectionObserver((ents) => { visible = ents[0].isIntersecting; }).observe(caja);
  let antes = performance.now();
  function bucle(t) {
    const dt = Math.min(0.05, (t - antes) / 1000);
    antes = t;
    if (visible) {
      if (auto && !arrastre) anguloObj += dt * 0.35;
      angulo += (anguloObj - angulo) * Math.min(1, dt * 7);
      incl += (inclObj - incl) * Math.min(1, dt * 7);
      giro.rotation.y = angulo;
      pivote.rotation.x = incl;
      renderer.render(scene, camara);
    }
    requestAnimationFrame(bucle);
  }
  requestAnimationFrame(bucle);

  return {
    mira,
    miraZona: (z) => ZONAS[z] && mira(ZONAS[z].mira),
    repinta: pintaTodos,
  };
})();

/* ============================================================
   Ficha, lista y formulario
   ============================================================ */

const ficha = $("#ficha-hueco");
const form = $("#form-oferta");
const select = $("#zona");
const importe = $("#importe");
const ayudaImporte = $("#ayuda-importe");

const ESTADOS = { libre: "Libre", oferta: "Con ofertas", cerrado: "Cerrado", adjudicado: "Adjudicado" };

function esc(s) {
  return String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}

const FICHA_INICIAL = ficha ? ficha.innerHTML : "";

function pintaFicha() {
  const h = huecos[elegida];
  if (!h) { if (ficha) ficha.innerHTML = FICHA_INICIAL; return; }
  let cifras;
  if (h.estado === "adjudicado") {
    cifras = `<div style="grid-column:1/-1"><b>${esc(h.adjudicado)}</b><span>Este hueco ya tiene marca</span></div>`;
  } else {
    const alta = h.maxima !== null
      ? `<b>${euros(h.maxima)}</b><span>Oferta más alta${h.ofertas > 1 ? ` · ${h.ofertas} ofertas` : ""}</span>`
      : `<b>—</b><span>Aún sin ofertas</span>`;
    const minima = !ofertable(h) ? `<b>Cerrado</b><span>No admite ofertas</span>`
      : h.siguiente > 1 ? `<b>${euros(h.siguiente)}</b><span>Oferta mínima ahora</span>`
      : `<b>Libre</b><span>Sin oferta mínima</span>`;
    cifras = `<div>${alta}</div><div>${minima}</div>`;
  }
  ficha.innerHTML = `
    <p class="ficha__zona">${esc(proyectoActual().nombre)} · ${esc(ESTADOS[h.estado] || "")}${h.cierre && ofertable(h) ? " · cierra el " + esc(h.cierre) : ""}</p>
    <h2 class="display">${esc(h.nombre)}</h2>
    <p class="ficha__desc">${esc(h.descripcion)}</p>
    <div class="ficha__cifras">${cifras}</div>`;
}

function cifraHueco(h) {
  switch (h.estado) {
    case "libre": return h.minimo > 1 ? `Oferta mínima: <b>${euros(h.minimo)}</b>` : "Sin ofertas todavía";
    case "adjudicado": return `Para <b>${esc(h.adjudicado)}</b>`;
    default: return h.maxima !== null ? `Oferta más alta: <b>${euros(h.maxima)}</b>` : "Sin ofertas";
  }
}

function pintaLista() {
  const ul = $("#lista-huecos");
  if (!ul) return;
  const p = proyectoActual();
  $("#lista-proyecto") && ($("#lista-proyecto").textContent = p.nombre);
  ul.innerHTML = Object.values(huecos).map((h) => `
    <li class="hueco${h.zona === elegida ? " hueco--elegido" : ""}" id="hueco-${esc(h.zona)}" data-zona="${esc(h.zona)}">
      <span class="chip chip--${h.estado}">${ESTADOS[h.estado]}</span>
      <h3 class="display">${esc(h.nombre)}</h3>
      <p>${esc(h.descripcion)}</p>
      <p class="hueco__cifra">${cifraHueco(h)}</p>
      ${h.cierre && ofertable(h) ? `<p>Cierra el ${esc(h.cierre)}</p>` : ""}
      ${p.abierto && ofertable(h) ? `<a class="mando" href="/maillot?p=${encodeURIComponent(p.slug)}&zona=${encodeURIComponent(h.zona)}#oferta" data-elegir="${esc(h.zona)}">Hacer oferta</a>` : ""}
    </li>`).join("");
}

function pintaOpciones() {
  if (!select) return;
  const antes = select.value;
  select.innerHTML = '<option value="">Elige un hueco</option>' + Object.values(huecos).filter(ofertable)
    .map((h) => `<option value="${esc(h.zona)}">${esc(h.nombre)}${h.siguiente > 1 ? " — desde " + euros(h.siguiente) : ""}</option>`).join("");
  if (huecos[antes] && ofertable(huecos[antes])) select.value = antes;
}

function pintaAyuda() {
  if (!ayudaImporte) return;
  const h = huecos[elegida];
  if (ofertable(h) && h.siguiente > 1) {
    ayudaImporte.textContent = `Euros, sin IVA. Como mínimo ${euros(h.siguiente)}.`;
    importe.placeholder = String(h.siguiente);
  } else {
    ayudaImporte.textContent = ofertable(h) ? "Euros, sin IVA. Este hueco no tiene oferta mínima."
      : "Euros, sin IVA. Elige un hueco para ver la oferta mínima.";
    importe.placeholder = "0";
  }
}

function eligeZona(z, { desde3d = false, desdeSelect = false } = {}) {
  if (!huecos[z]) return;
  elegida = z;
  pintaFicha();
  pintaAyuda();
  if (select && !desdeSelect && ofertable(huecos[z])) select.value = z;
  $$(".hueco").forEach((li) => li.classList.toggle("hueco--elegido", li.dataset.zona === z));
  escena3d && escena3d.repinta();
  if (escena3d && !desde3d) escena3d.miraZona(z);
  /* En el móvil la ficha queda debajo del mono: se baja hasta ella */
  if (desde3d && window.matchMedia("(max-width: 900px)").matches) {
    $("#oferta").scrollIntoView({ behavior: reposo ? "auto" : "smooth", block: "start" });
  }
}

select && select.addEventListener("change", () => select.value && eligeZona(select.value, { desdeSelect: true }));

/* «Hacer oferta» de la lista: sube al mono y a la ficha sin recargar */
document.addEventListener("click", (e) => {
  const a = e.target.closest("[data-elegir]");
  if (!a) return;
  e.preventDefault();
  eligeZona(a.dataset.elegir);
  $("#escena").scrollIntoView({ behavior: reposo ? "auto" : "smooth", block: "start" });
  setTimeout(() => importe && importe.focus({ preventScroll: true }), reposo ? 0 : 600);
});

/* ---------- Cambio de proyecto sin recargar ---------- */

function cambiaProyecto(slug) {
  if (!proyectos.some((p) => p.slug === slug)) return;
  actual = slug;
  elegida = "";
  guardaDatos();
  const p = proyectoActual();
  $$("[data-proyecto]").forEach((a) => {
    const si = a.dataset.proyecto === slug;
    a.classList.toggle("carrera--si", si);
    if (si) a.setAttribute("aria-current", "true"); else a.removeAttribute("aria-current");
  });
  $("#proyecto") && ($("#proyecto").value = slug);
  $("#form-proyecto-nombre") && ($("#form-proyecto-nombre").textContent = p.nombre);
  if (form) form.hidden = !p.abierto;
  $("#aviso-cerrado") && ($("#aviso-cerrado").hidden = !!p.abierto);
  const a = $("#aviso-form");
  a && a.remove();
  pintaFicha();
  pintaOpciones();
  pintaAyuda();
  pintaLista();
  escena3d && escena3d.repinta();
  try { history.replaceState(null, "", "/maillot?p=" + encodeURIComponent(slug) + "#mono"); } catch (_) { /* nada */ }
}

$$("[data-proyecto]").forEach((a) => a.addEventListener("click", (e) => {
  e.preventDefault();
  cambiaProyecto(a.dataset.proyecto);
}));

/* ---------- Envío sin recargar ---------- */

function limpiaErrores() {
  $$(".error", form).forEach((p) => p.remove());
  $$(".campo--error", form).forEach((c) => c.classList.remove("campo--error"));
  const a = $("#aviso-form");
  a && a.remove();
}

function avisa(texto, clase = "aviso") {
  const a = document.createElement("div");
  a.id = "aviso-form";
  a.className = clase;
  a.setAttribute("role", clase === "aviso" ? "alert" : "status");
  a.textContent = texto;
  form.parentNode.insertBefore(a, form);
  a.scrollIntoView({ behavior: reposo ? "auto" : "smooth", block: "nearest" });
}

form && form.addEventListener("submit", async (e) => {
  e.preventDefault();
  limpiaErrores();
  const boton = $(".enviar", form);
  boton.disabled = true;
  boton.textContent = "Enviando…";
  try {
    const r = await fetch(form.action, {
      method: "POST", body: new FormData(form), headers: { Accept: "application/json" }, credentials: "same-origin",
    });
    const res = await r.json();
    guardaDatos(res.proyectos);
    pintaLista();
    pintaOpciones();
    if (res.ok) {
      avisa(res.estado === "valida"
        ? "¡Oferta recibida! Ya es la más alta de este hueco. Al cierre te escribimos."
        : "¡Oferta recibida! La revisamos y, en cuanto la validemos, aparece en el mono. Al cierre te escribimos.",
        "aviso aviso--ok");
      importe.value = "";
      $("#mensaje").value = "";
    } else {
      for (const [campo, msg] of Object.entries(res.errores || {})) {
        const input = form.elements[campo];
        const p = document.createElement("p");
        p.className = "error";
        p.textContent = msg;
        const cont = campo === "_acepto" ? $(".acepto", form) : input && input.closest(".campo");
        if (cont) {
          cont.classList.add("campo--error");
          cont.insertAdjacentElement(campo === "_acepto" ? "afterend" : "beforeend", p);
        }
      }
      if (res.aviso) avisa(res.aviso);
    }
    pintaFicha();
    pintaAyuda();
    escena3d && escena3d.repinta();
  } catch (err) {
    avisa("No hemos podido enviar tu oferta. Revisa tu conexión y vuelve a intentarlo.");
  } finally {
    boton.disabled = false;
    boton.textContent = "Enviar mi oferta";
  }
});

/* Si se llega con ?zona= (desde la lista sin JS o un enlace), se abre ese hueco */
const inicial = (select && select.value) || new URLSearchParams(location.search).get("zona") || "";
if (huecos[inicial]) eligeZona(inicial);
