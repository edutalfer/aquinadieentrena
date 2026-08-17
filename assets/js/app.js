/* ============================================================
   AQUÍ NADIE ENTRENA — buscador de temas y perfiles de etapa
   ============================================================ */

(function () {
  "use strict";

  var EPISODIOS = window.ANE_EPISODIOS || [];

  /* ---------- utilidades ---------- */

  function limpia(s) {
    return (s || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[¿?¡!"'.,;:()]/g, " ")
      .replace(/\s+/g, " ")
      .trim();
  }

  function reloj(seg) {
    var h = Math.floor(seg / 3600);
    var m = Math.floor((seg % 3600) / 60);
    var s = Math.floor(seg % 60);
    var mm = h > 0 ? String(m).padStart(2, "0") : String(m);
    return (h > 0 ? h + ":" : "") + mm + ":" + String(s).padStart(2, "0");
  }

  var MESES = ["ene", "feb", "mar", "abr", "may", "jun",
               "jul", "ago", "sep", "oct", "nov", "dic"];

  function fechaCorta(iso) {
    var p = iso.split("-");
    return p[2] + " " + MESES[parseInt(p[1], 10) - 1] + " " + p[0];
  }

  function enlace(ep, seg) {
    if (ep.youtubeId) {
      return "https://www.youtube.com/watch?v=" + ep.youtubeId + "&t=" + Math.floor(seg) + "s";
    }
    if (ep.spotifyId) {
      return "https://open.spotify.com/episode/" + ep.spotifyId;
    }
    return "https://www.youtube.com/@aqui.nadie.entrena";
  }

  function escapa(s) {
    return s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
  }

  /* Resalta las palabras buscadas sin romper los acentos del original */
  function resalta(texto, palabras) {
    var plano = limpia(texto);
    var marcas = new Array(texto.length).fill(false);

    // mapa: posición en "plano" → posición aproximada en "texto"
    palabras.forEach(function (p) {
      if (!p) return;
      var desde = 0, i;
      while ((i = plano.indexOf(p, desde)) !== -1) {
        for (var k = i; k < i + p.length && k < marcas.length; k++) marcas[k] = true;
        desde = i + p.length;
      }
    });

    var salida = "", abierto = false;
    for (var j = 0; j < texto.length; j++) {
      if (marcas[j] && !abierto) { salida += "<mark>"; abierto = true; }
      if (!marcas[j] && abierto) { salida += "</mark>"; abierto = false; }
      salida += escapa(texto[j]);
    }
    if (abierto) salida += "</mark>";
    return salida;
  }

  /* ---------- perfil de etapa ---------- */
  /* Cada tema es un "repecho": la altura es proporcional a lo que dura
     el bloque, y el punto en la base marca el minuto en que empieza.   */

  function perfil(ep) {
    var W = 1000, H = 120, BASE = 100, MIN_H = 16, MAX_H = 76;
    var total = ep.duracion || (ep.temas[ep.temas.length - 1].t + 300);

    var bloques = ep.temas.map(function (tema, i) {
      var fin = i + 1 < ep.temas.length ? ep.temas[i + 1].t : total;
      return { tema: tema, ini: tema.t, fin: fin, dur: Math.max(fin - tema.t, 1) };
    });

    var maxDur = Math.max.apply(null, bloques.map(function (b) { return b.dur; }));

    var puntos = ["0," + BASE];
    bloques.forEach(function (b) {
      var x1 = (b.ini / total) * W;
      var x2 = (b.fin / total) * W;
      var h = MIN_H + (b.dur / maxDur) * (MAX_H - MIN_H);
      puntos.push(x1 + "," + BASE);
      puntos.push(((x1 + x2) / 2) + "," + (BASE - h));
      puntos.push(x2 + "," + BASE);
    });
    puntos.push(W + "," + BASE);

    var linea = puntos.join(" ");
    var area = "0," + H + " " + linea + " " + W + "," + H;

    var marcas = bloques.map(function (b) {
      var x = (b.ini / total) * W;
      return '<a class="perfil__marca" href="' + enlace(ep, b.ini) + '" target="_blank" rel="noopener">' +
             '<title>' + escapa(b.tema.titulo) + ' — ' + reloj(b.ini) + '</title>' +
             '<circle cx="' + x + '" cy="' + BASE + '" r="5" fill="#191919"></circle>' +
             '<rect x="' + (x - 14) + '" y="' + (BASE - 14) + '" width="28" height="28" fill="transparent"></rect>' +
             '</a>';
    }).join("");

    return '' +
      '<div class="perfil">' +
        '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" role="img" ' +
             'aria-label="Perfil del episodio: ' + ep.temas.length + ' bloques">' +
          '<polygon points="' + area + '" fill="#3F77DA" opacity="0.32"></polygon>' +
          '<polyline points="' + linea + '" fill="none" stroke="#191919" stroke-width="2.5" ' +
                    'stroke-linejoin="round" vector-effect="non-scaling-stroke"></polyline>' +
          '<line x1="0" y1="' + BASE + '" x2="' + W + '" y2="' + BASE + '" ' +
                'stroke="#191919" stroke-width="2" vector-effect="non-scaling-stroke"></line>' +
          marcas +
        '</svg>' +
        '<div class="perfil__pie"><span>0:00</span><span>' + reloj(total) + '</span></div>' +
      '</div>';
  }

  /* ---------- tarjeta de episodio ---------- */

  function tarjeta(ep) {
    var temas = ep.temas.map(function (t) {
      return '<li><a href="' + enlace(ep, t.t) + '" target="_blank" rel="noopener">' +
             '<time>' + reloj(t.t) + '</time><span>' + escapa(t.titulo) + '</span></a></li>';
    }).join("");

    return '' +
      '<article class="episodio">' +
        '<span class="episodio__fecha">' + fechaCorta(ep.fecha) +
          ' · ' + Math.round(ep.duracion / 60) + ' min</span>' +
        '<h3 class="episodio__titulo display">' + escapa(ep.titulo) + '</h3>' +
        perfil(ep) +
        '<ul class="temas">' + temas + '</ul>' +
      '</article>';
  }

  function pinta(selector, lista) {
    var caja = document.querySelector(selector);
    if (!caja) return;
    caja.innerHTML = lista.map(tarjeta).join("");
  }

  /* ---------- indice de transcripciones (carga perezosa) ---------- */

  var VOZ = null;
  var vozCargando = false;

  function cargaVoz() {
    if (VOZ !== null || vozCargando) return;
    vozCargando = true;
    fetch("data/indice_busqueda.json")
      .then(function (r) { return r.ok ? r.json() : { episodios: [] }; })
      .then(function (datos) {
        var porId = {};
        EPISODIOS.forEach(function (ep) { porId[ep.youtubeId] = ep; });
        VOZ = [];
        (datos.episodios || []).forEach(function (e) {
          var ep = porId[e.youtubeId];
          if (!ep) return;
          VOZ.push({
            ep: ep,
            segmentos: e.segmentos.map(function (s) { return [s[0], s[1], limpia(s[1])]; })
          });
        });
        if (campo && campo.value.trim().length >= 2) muestra(campo.value);
      })
      .catch(function () { VOZ = []; });
  }

  /* Recorta un fragmento alrededor de la primera palabra encontrada */
  function fragmento(texto, plano, palabras) {
    var pos = -1;
    for (var i = 0; i < palabras.length; i++) {
      var p = plano.indexOf(palabras[i]);
      if (p !== -1 && (pos === -1 || p < pos)) pos = p;
    }
    if (pos === -1) pos = 0;
    var desde = Math.max(0, pos - 60);
    var hasta = Math.min(texto.length, pos + 120);
    return (desde > 0 ? "\u2026" : "") + texto.slice(desde, hasta).trim() +
           (hasta < texto.length ? "\u2026" : "");
  }

  function buscaVoz(palabras) {
    if (!VOZ) return [];
    var hallazgos = [];
    VOZ.forEach(function (entrada) {
      entrada.segmentos.forEach(function (s) {
        var todas = palabras.every(function (p) { return s[2].indexOf(p) !== -1; });
        if (!todas) return;
        hallazgos.push({
          ep: entrada.ep, origen: "voz", t: Math.max(0, s[0] - 3),
          texto: s[1], plano: s[2], peso: palabras.length
        });
      });
    });
    return hallazgos;
  }

  /* ---------- buscador ---------- */

  var campo = document.getElementById("busqueda");
  var panel = document.getElementById("resultados");
  var lista = document.getElementById("listaResultados");
  var estado = document.getElementById("estadoResultados");

  function busca(consulta) {
    var palabras = limpia(consulta).split(" ").filter(Boolean);
    if (!palabras.length) return [];

    var hallazgos = [];
    EPISODIOS.forEach(function (ep) {
      var epPlano = limpia(ep.titulo);
      ep.temas.forEach(function (tema) {
        var temaPlano = limpia(tema.titulo);
        var texto = temaPlano + " " + epPlano;
        var todas = palabras.every(function (p) { return texto.indexOf(p) !== -1; });
        if (!todas) return;
        // los aciertos en el título del bloque valen más que en el del episodio
        var peso = palabras.filter(function (p) { return temaPlano.indexOf(p) !== -1; }).length;
        hallazgos.push({ ep: ep, tema: tema, origen: "tema", peso: peso });
      });
    });

    hallazgos = hallazgos.concat(buscaVoz(palabras));

    hallazgos.sort(function (a, b) {
      if (a.origen !== b.origen) return a.origen === "tema" ? -1 : 1;
      if (b.peso !== a.peso) return b.peso - a.peso;
      return a.ep.fecha < b.ep.fecha ? 1 : -1;
    });
    return hallazgos;
  }

  function muestra(consulta) {
    if (!panel) return;
    var q = consulta.trim();

    if (q.length < 2) {
      panel.hidden = true;
      return;
    }

    var palabras = limpia(q).split(" ").filter(Boolean);
    var hallazgos = busca(q);
    panel.hidden = false;

    if (!hallazgos.length) {
      estado.textContent = "Sin resultados";
      lista.innerHTML =
        '<div class="vacio">' +
          '<p>De <b>“' + escapa(q) + '”</b> todavía no hemos hablado. O lo hemos llamado de otra manera.</p>' +
          '<p>Prueba con una sola palabra, o mándanoslo y lo sacamos en el próximo episodio: ' +
          '<a href="mailto:entrenaaquinadie@gmail.com?subject=Tema%20para%20el%20podcast">entrenaaquinadie@gmail.com</a></p>' +
        '</div>';
      return;
    }

    estado.textContent = hallazgos.length === 1
      ? "1 momento encontrado"
      : hallazgos.length + " momentos encontrados";

    lista.innerHTML = hallazgos.slice(0, 40).map(function (h) {
      if (h.origen === "voz") {
        return '<a class="hallazgo hallazgo--voz" href="' + enlace(h.ep, h.t) + '" target="_blank" rel="noopener">' +
                 '<span class="hallazgo__min">' + reloj(h.t) + '</span>' +
                 '<span>' +
                   '<p class="hallazgo__voz">\u00ab' + resalta(fragmento(h.texto, h.plano, palabras), palabras) + '\u00bb</p>' +
                   '<p class="hallazgo__ep">' + escapa(h.ep.titulo) +
                     ' <span style="color:#6E747F">\u00b7 ' + fechaCorta(h.ep.fecha) + '</span></p>' +
                 '</span>' +
               '</a>';
      }
      return '<a class="hallazgo" href="' + enlace(h.ep, h.tema.t) + '" target="_blank" rel="noopener">' +
               '<span class="hallazgo__min">' + reloj(h.tema.t) + '</span>' +
               '<span>' +
                 '<h3 class="hallazgo__tema display">' + resalta(h.tema.titulo, palabras) + '</h3>' +
                 '<p class="hallazgo__ep">' + resalta(h.ep.titulo, palabras) +
                   ' <span style="color:#6E747F">· ' + fechaCorta(h.ep.fecha) + '</span></p>' +
               '</span>' +
             '</a>';
    }).join("");
  }

  if (campo) {
    campo.addEventListener("focus", cargaVoz, { once: true });
    campo.addEventListener("input", function () { cargaVoz(); muestra(campo.value); });
    document.querySelectorAll(".chip").forEach(function (chip) {
      chip.addEventListener("click", function () {
        campo.value = chip.dataset.tema || chip.textContent;
        muestra(campo.value);
        panel.scrollIntoView({ behavior: "smooth", block: "start" });
      });
    });
  }

  /* ---------- arranque ---------- */

  var ordenados = EPISODIOS.slice().sort(function (a, b) {
    return a.fecha < b.fecha ? 1 : -1;
  });

  pinta("#ultimos", ordenados.slice(0, 3));
  pinta("#todos", ordenados);

  var contador = document.getElementById("contadorTemas");
  if (contador) {
    var n = EPISODIOS.reduce(function (acc, ep) { return acc + ep.temas.length; }, 0);
    contador.textContent = n;
  }
})();
