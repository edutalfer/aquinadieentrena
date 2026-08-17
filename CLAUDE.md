# CLAUDE.md — Aquí Nadie Entrena (aquinadieentrena.cc)

Web del podcast **Aquí Nadie Entrena**. Este fichero es el punto de entrada:
léelo entero antes de tocar nada.

---

## 1. Lectura obligatoria antes de actuar

En este orden, siempre:

| Fichero | Para qué |
|---|---|
| `memorybank/03_ESTADO_ACTUAL.md` | Conexión al servidor, trampas conocidas, flujo de trabajo |
| `memorybank/04_BRIEF_BUSCADOR.md` | Tarea en curso: alimentar el buscador |
| `memorybank/00_MEGAPLAN_WEB_ANE.md` | Objetivo del proyecto |
| `memorybank/01_ESQUELETO_WEB.md` | Arquitectura |
| `memorybank/02_DECISIONES.md` | Por qué está hecho así (no re-litigar decisiones cerradas) |
| `pipeline/LEEME.md` | Cómo funciona el pipeline de transcripciones |

Si tomas una decisión de arquitectura, **anótala en `02_DECISIONES.md`**. La
continuidad entre sesiones depende de eso.

---

## 2. Reglas duras — no negociables

Estas tres ya han costado sesiones enteras. No las descubras otra vez.

**La descarga de subtítulos NUNCA se hace desde el servidor.**
YouTube veta las IPs de centro de datos: «Sign in to confirm you're not a bot»
y HTTP 429. Están probados los player_client android, ios, tv, web_embedded y
mweb: fallan todos. El paso 1 del pipeline se ejecuta en el Mac de Eduardo y
punto. El resto de pasos da igual dónde corran.

**`curl` desde el servidor NO sirve para comprobar qué ve el público.**
El servidor se responde a sí mismo y salta la CDN. Esto ya provocó un
diagnóstico falso: se dio por bueno un arreglo que no lo era. La forma correcta:

```bash
IP=$(dig +short aquinadieentrena.cc @8.8.8.8 | head -1)
curl -s https://aquinadieentrena.cc/ --resolve "aquinadieentrena.cc:443:$IP" | grep "loQueSea"
```

**El repo ES el docroot**: `~/domains/aquinadieentrena.cc/public_html`.
No es `~/public_html`. Un `git push` ya despliega.

---

## 3. Entorno

**Servidor (Hostinger).** Los datos de conexión están en
`03_ESTADO_ACTUAL.md`. Autenticación por clave: hPanel rechaza ecdsa y el
conector MCP no puede usar ed25519 → hay que generar **RSA 4096**. Al pegar la
pública en hPanel los espacios se pierden con facilidad: debe quedar
`ssh-rsa` + espacio + clave + espacio + comentario.

Python y Node existen en el servidor pero **fuera del PATH**. Siempre:

```bash
source pipeline/entorno.sh
```

**Mac de Eduardo.** Copia local en
`/Users/eduardotalavera/Documents/Proyectos-IA/aquinadieentrena`.
El `python3` del sistema es 3.9 (Command Line Tools) — ver sección 5.

**Caché.** El `.htaccess` ya no cachea HTML. Pero CSS y JS se referencian con
`?v=AAAAMMDD`: **si tocas un CSS o un JS, sube ese número en `index.html` y
`episodios.html`** o nadie verá el cambio. La CDN está en Modo de desarrollo
(hPanel → Rendimiento → CDN); hay que desactivarlo cuando se cierre el rediseño.

**Protegido por `.htaccess`:** `memorybank/`, `pipeline/` y
`data/transcripciones/`. `data/indice_busqueda.json` **sí** se sirve porque lo
necesita el navegador. Si creas carpetas nuevas con material en bruto,
bloquéalas también.

---

## 4. Flujo de trabajo

```
Claude edita en el servidor → git commit → git push   (ya desplegado)
Eduardo edita en su Mac     → git push → git pull en el servidor
```

Si algo se rompe: `git revert`. Atrás en un segundo.

⚠️ El `git push` desde el Mac por HTTPS pide usuario y token de GitHub. El
clone por SSH falla (`Permission denied (publickey)`): el Mac no tiene clave
dada de alta en GitHub. Usar HTTPS o configurar una clave.

---

## 5. Estado de yt-dlp (agosto 2026)

El `yt-dlp` del Mac está **congelado en `2025.10.14`** y no se puede actualizar
con pip: cuelga del Python 3.9 del sistema, para el que yt-dlp ya no publica.
`pip3 install --upgrade` responde «already satisfied» y no hace nada.

Con esa versión, los cinco episodios probados fallan con
«The page needs to be reloaded». **No es un veto de IP** — es un extractor de
diez meses contra una web que ha cambiado.

Si hace falta recuperar la descarga automática, el camino es el binario
autocontenido (trae su propio Python):

```bash
curl -L -o "$HOME/bin/yt-dlp" https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp_macos
chmod +x "$HOME/bin/yt-dlp"
```

Mientras tanto la vía operativa es **descargar los subtítulos a mano desde
YouTube Studio** (los vídeos son de Eduardo). Formato **`.vtt`**, no `.srt`:
`02_segmentar.py` está escrito para VTT.

---

## 6. Tarea en curso: alimentar el buscador

**Objetivo.** Que el buscador encuentre cualquier cosa *dicha* en un episodio,
no solo lo que está en los títulos del timeline. Resultado: episodio + minuto
exacto + fragmento + enlace directo a YouTube en ese segundo.

**Hecho (17/08/2026).** El código ya lo soportaba (`assets/js/app.js`: carga
perezosa del índice, prioridad del timeline sobre la transcripción, fragmento
de contexto, enlace 3 s antes de la frase) y **ya tiene datos**: los 40 VTT de
Studio están renombrados a `<youtubeId>.es.vtt`, `data/episodios.js` tiene los
40 episodios con fecha, duración y timeline reales, y el índice trae 7.667
segmentos. Probado en local. Detalle y pendientes: `04_BRIEF_BUSCADOR.md`.

**Falta.** Bajar de Studio los VTT de seis episodios que no lo tienen y
escribir en YouTube el timeline de 17 episodios que no lo llevan. Los dos
listados están en `04_BRIEF_BUSCADOR.md`.

**Para un episodio nuevo:** VTT de Studio → `data/subtitulos_originales/` y
`pipeline/tmp/` como `<youtubeId>.es.vtt` → alta en `data/episodios.js` →
`./pipeline/pipeline.sh <youtubeId>` → commit y push.

`data/subtitulos_originales/` es la **fuente de verdad en bruto** y se versiona:
si algún día cambia la segmentación, se reconstruye el índice sin volver a
YouTube. `pipeline/tmp/` está en `.gitignore` y es scratch: no dejes ahí nada
que duela perder.

`data/episodios.js` es la **fuente de verdad editorial**: solo entra en el
índice lo que esté en esa lista. Los timelines salen del bloque «Temas del
episodio» de la descripción de YouTube.

**Tamaño del índice: medido y cerrado.** 3,9 MB en disco pero **1,4 MB por la
red** (el `.htaccess` ya comprime `application/json`) y solo se descarga al
tocar el buscador; buscar tarda 6-8 ms. No se trocea por año: para una búsqueda
global habría que descargar todos los trozos igual. Ver D14 en
`02_DECISIONES.md`.

---

## 7. Cómo comprobar que ha salido bien

- El índice no está vacío, y anota **cuánto pesa** de verdad (en disco y
  comprimido: es lo segundo lo que viaja).
- Buscar en la web una palabra que **no** esté en ningún título de timeline y
  ver si devuelve el minuto correcto («creatina» va bien para esto).
- Verificar contra producción con `curl --resolve` (sección 2), nunca con
  `curl` desde el servidor.

---

## 8. Estilo

- **Escribe y responde en español**, tuteo, registro informal. Es la voz de ANE.
- Marca: Azul ANE `#3F77DA`, Negro Pelotón `#191919`, Blanco Salida `#FFFFFF`.
  Para **texto y enlaces en web** usa Azul Enlace `#2A5CB8`: el Azul ANE solo
  da 4,3:1 sobre blanco y no vale para lectura.
- Tipografías: Archivo Black Italic (display, subsetada) e Inter 400–700 (texto).
- Antes de un refactor grande, propón y espera confirmación. Los arreglos
  puntuales, adelante.

---

## 9. Seguridad

Este repo es **público**. Nunca commitees claves privadas, tokens de GitHub,
credenciales de hPanel ni nada equivalente — tampoco en `memorybank/`, que está
bloqueado por `.htaccess` pero es perfectamente visible en GitHub.
