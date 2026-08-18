# 🧭 DECISIONES — Registro y preguntas abiertas

> **Última actualización:** 2026-08-18

---

## ✅ DECISIONES TOMADAS

| # | Fecha | Decisión | Motivo |
|---|---|---|---|
| D1 | 2026-08-17 | Frontend y backend separados | El backend v1 es un pipeline de datos, no un servidor |
| D2 | 2026-08-17 | Todo en GitHub, incluido `memorybank/` | Memoria persistente entre sesiones |
| D3 | 2026-08-17 | Buscador = timelines + transcripciones, con deep links `&t=Xs` | Objetivo core |
| D4 | 2026-08-17 | JSON canónico por episodio | Independiente del motor de búsqueda → sin re-trabajo si migramos |
| D5 | 2026-08-17 | Transcripciones vía subtítulos automáticos de YouTube | Cobertura inmediata; Whisper más adelante si la calidad duele |
| D6 | 2026-08-17 | Reutilizar la infraestructura existente, no duplicar nada | Web, dominio, repo y hosting ya estaban montados |
| D7 | 2026-08-17 | Tema "gorras" cerrado sin acción | Eduardo decide que no tiene importancia |
| D8 | 2026-08-17 | La web NO se mueve a `frontend/`: la raíz sigue siendo el sitio y se añaden `pipeline/`, `data/transcripciones/` y `memorybank/`, bloqueados por `.htaccess` | El repo ES el docroot; moverlo rompería el deploy |
| D9 | 2026-08-17 | Clave SSH RSA 4096 (`claude-ane-2026`) | hPanel rechaza ecdsa; la ed25519 anterior dejó de ser usable por el conector |
| D10 | 2026-08-17 | El paso de descarga del pipeline se ejecuta **en el Mac**, no en el servidor | YouTube bloquea la IP del hosting (HTTP 429 en todos los player_client) |
| D11 | 2026-08-17 | Índice de búsqueda en cliente (sin API) | Suficiente para el volumen actual; se revisará si el índice se dispara de tamaño |
| D12 | 2026-08-17 | Los VTT se bajan **a mano desde YouTube Studio** y se versionan en bruto en `data/subtitulos_originales/<youtubeId>.es.vtt` | yt-dlp no puede con la descarga (ver D10 y §5 de CLAUDE.md). Guardarlos en bruto permite rehacer la segmentación sin volver a YouTube |
| D13 | 2026-08-17 | Fecha, duración y timeline se sacan de la **página pública del vídeo con `curl`** desde el Mac, no con yt-dlp | El yt-dlp del Mac falla al extraer vídeo a vídeo («Es necesario volver a cargar la página»), pero el HTML público trae `uploadDate`, `lengthSeconds` y la descripción entera. El script está en el histórico de la sesión, no en el repo: es de un solo uso |
| D14 | 2026-08-17 | El índice sigue **en un solo fichero**, sin trocear por año | Medido en producción con el navegador: 4,4 MB en disco pero **2,07 MB por la red** y ~1,7 s de descarga, solo al tocar el buscador. (Ojo: `gzip -9` en local decía 1,6 MB; la CDN comprime menos, y el bueno es el dato de producción.) Buscar tarda decenas de ms sobre 8.675 segmentos. Trocear por año no ahorraría nada: una búsqueda global necesita todos los trozos igualmente |
| D15 | 2026-08-17 | Los **22 vídeos de etapa del Tour 2026** (julio, 33-57 min) **no entran en el buscador de momento** | Lo decide Eduardo. No hay que bajar sus VTT ni darlos de alta en `data/episodios.js`. Si algún día cambia de idea, el procedimiento es el mismo que para cualquier episodio |

---

## ❓ PREGUNTAS ABIERTAS

### P2 — Motor de búsqueda a futuro · **cerrada de momento (ver D14)**
Con 46 episodios: 4,4 MB en disco, **2,07 MB por la red**, decenas de ms por
búsqueda. Aguanta. Volver a mirarlo si el índice pasa de **~3 MB por la red** o si la
búsqueda se nota lenta en un móvil viejo. La salida entonces no es trocear por
año, es un índice invertido (término → posiciones) o una API.

### P7 — Calidad de los subtítulos automáticos
Los `.vtt` automáticos de YouTube destrozan la jerga: «tubeless» sale escrito
**«tubles»**, así que buscar «tubeless» no encuentra el episodio que lo lleva
en el título. Whisper en local lo arreglaría (D5 ya lo contemplaba) a cambio de
10-20 min por episodio. Decidir si compensa.

### P5 — Formularios
Bici o Cepo y marcas usan `mailto:`. Valorar Web3Forms/Formspree para que no
dependa del cliente de correo del visitante.

### P6 — Sección Proyectos
Está en el objetivo pero no existe todavía. Falta definir qué proyectos y con
qué estructura.

---

## 🖥️ ENTORNO DE TRABAJO

| Qué | Dónde |
|---|---|
| Copia local de Eduardo | `/Users/eduardotalavera/Documents/Proyectos-IA` (Mac) |
| Repo GitHub | `github.com/edutalfer/aquinadieentrena` (público) |
| Servidor | `46.202.172.3` · puerto `65002` · usuario `u527801093` |
| Docroot (= repo) | `~/domains/aquinadieentrena.cc/public_html` ⚠️ **no** `~/public_html` |
| Repo bare auxiliar | `~/ane.git` |
| Python / Node en el servidor | Fuera del PATH: `/opt/alt/python311/bin` y `/opt/alt/alt-nodejs22/root/usr/bin` (ver `pipeline/entorno.sh`) |
| Autenticación SSH | Clave RSA 4096 en el Mac: `~/.ssh/claude-ane-2026` (privada, 600). Dada de alta en hPanel → Avanzado → Acceso SSH con el nombre **`claude-ane-3`**. Probada el 18/08/2026 |
| Flujo de deploy | Editar → commit → push a GitHub; en el servidor `git pull` |

---

## 📋 HISTÓRICO DE SESIONES

| Fecha | Qué se hizo |
|---|---|
| Previas | Web v1 diseñada, desplegada y subida a GitHub |
| 2026-08-17 | memorybank + mega plan + esqueleto. Verificado el repo real y la CDN. Pipeline de transcripciones escrito y probado. Buscador ampliado a transcripciones. SSH restablecido (RSA). Detectado el bloqueo de YouTube a la IP del hosting |
| 2026-08-18 | **Buscador desplegado y verificado en producción.** Timelines de 16 episodios transcritos de capturas (308 bloques), `git pull` en el servidor y comprobación con navegador real. Encontrada la segunda trampa de la CDN: el anti-bot devuelve 403 a `curl` en ráfaga |
| 2026-08-17 | **Buscador alimentado.** Los VTT de Studio cruzados con sus youtubeId, `data/episodios.js` reconstruido y el índice generado. Primero 40 episodios; Eduardo bajó los 6 que faltaban en la misma sesión y quedó **completo: 46 episodios, 8.675 segmentos**. Probado en local |
