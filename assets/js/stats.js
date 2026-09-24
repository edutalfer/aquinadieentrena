/* ============================================================
   AQUÍ NADIE ENTRENA — registro anónimo del buscador
   ------------------------------------------------------------
   Anota qué se busca, qué momento acaba abriendo la gente y qué
   botón de correo pulsa (bici o cepo, marcas...).
   Sin cookies, sin IP, sin identificadores de ningún tipo.
   Lo lee el panel privado /admin (ver api/stats.php).

   app.js llama a ANEStats.busqueda() cada vez que pinta
   resultados; aquí se espera a que la persona deje de teclear
   para no registrar «p», «po», «pog»... sino «pogačar».
   ============================================================ */

(function () {
  "use strict";

  var URL_API = "/api/stats.php";
  var ESPERA = 1500;   // ms sin teclear para dar la búsqueda por buena
  var PAGINA = /episodios/.test(location.pathname) ? "episodios" : "portada";

  var pendiente = null;     // búsqueda aún sin enviar
  var temporizador = null;
  var ultimaEnviada = "";   // evita registrar dos veces seguidas lo mismo
  var terminoActual = "";   // para asociar cada clic a su búsqueda

  function envia(datos) {
    datos.pagina = PAGINA;
    var cuerpo = JSON.stringify(datos);
    try {
      var blob = new Blob([cuerpo], { type: "application/json" });
      if (navigator.sendBeacon && navigator.sendBeacon(URL_API, blob)) return;
    } catch (e) { /* sigue con fetch */ }
    try {
      fetch(URL_API, {
        method: "POST", body: cuerpo, keepalive: true,
        headers: { "Content-Type": "application/json" }
      }).catch(function () {});
    } catch (e) { /* el registro nunca debe romper la web */ }
  }

  function vacia() {
    clearTimeout(temporizador);
    if (!pendiente) return;
    var clave = pendiente.termino.toLowerCase();
    if (clave !== ultimaEnviada) {
      envia({
        tipo: "busqueda",
        termino: pendiente.termino,
        resultados: pendiente.resultados,
        voz: pendiente.voz
      });
      ultimaEnviada = clave;
    }
    pendiente = null;
  }

  window.ANEStats = {
    /* resultados: total de momentos · voz: cuántos salen solo de lo hablado */
    busqueda: function (termino, resultados, voz) {
      terminoActual = termino;
      pendiente = { termino: termino, resultados: resultados, voz: voz || 0 };
      clearTimeout(temporizador);
      temporizador = setTimeout(vacia, ESPERA);
    }
  };

  /* Clic en un resultado: qué episodio y qué segundo abre la gente */
  function alPulsar(e) {
    var a = e.target && e.target.closest && e.target.closest("#listaResultados a.hallazgo");
    if (!a) return;
    vacia();   // primero la búsqueda que llevó al clic
    var v = a.href.match(/[?&]v=([A-Za-z0-9_-]{11})/);
    var t = a.href.match(/[?&]t=(\d+)/);
    if (!v) return;
    envia({ tipo: "clic", termino: terminoActual, youtubeId: v[1], segundo: t ? +t[1] : 0 });
  }
  /* Clic en un correo: qué botón de contacto se usa. Se distingue por el
     asunto, así no hay que marcar nada en el HTML. Cuenta clics, no
     correos enviados: el correo lo termina (o no) la app de cada uno. */
  function alPulsarCorreo(e) {
    var a = e.target && e.target.closest && e.target.closest('a[href^="mailto:"]');
    if (!a) return;
    var asunto = decodeURIComponent((a.href.split("subject=")[1] || "").split("&")[0]);
    var destino = /bici/i.test(asunto) ? "bici"
                : /colaboraci|propuesta/i.test(asunto) ? "marcas"
                : /tema/i.test(asunto) ? "tema"
                : "contacto";
    envia({ tipo: "contacto", destino: destino });
  }

  document.addEventListener("click", function (e) { alPulsar(e); alPulsarCorreo(e); });
  document.addEventListener("auxclick", alPulsar);   // clic con la rueda

  /* Si se cierra la pestaña con una búsqueda a medias, que no se pierda */
  addEventListener("pagehide", vacia);
  document.addEventListener("visibilitychange", function () {
    if (document.visibilityState === "hidden") vacia();
  });
})();
