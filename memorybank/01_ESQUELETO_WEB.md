# 🏗️ ESQUELETO WEB ANE — Arquitectura

> **Última actualización:** 2026-08-17

## SITEMAP

```
aquinadieentrena.cc
├── /                → Home: BUSCADOR (protagonista) + últimos episodios + hosts
│                      + newsletter + Bici o Cepo + marcas
├── /episodios       → Archivo completo con timelines clicables
└── /proyectos       → (pendiente)
```

## ESTRUCTURA DEL REPO

El repo **es** el docroot, así que la web vive en la raíz. La separación
frontend/backend es por carpetas, no por ubicación:

```
public_html/  (= repo)
├── index.html, episodios.html     ← FRONTEND
├── assets/ (css, js, img, fonts)  ← FRONTEND
├── data/
│   ├── episodios.js               ← fuente editorial (timelines)
│   ├── transcripciones/*.json     ← BACKEND (bloqueado al público)
│   └── indice_busqueda.json       ← generado; sí se sirve
├── pipeline/                      ← BACKEND (bloqueado al público)
└── memorybank/                    ← documentación (bloqueado al público)
```

## EL BUSCADOR

### Dos capas

| Capa | Origen | Prioridad |
|---|---|---|
| Timelines | `episodios.js`, escritos a mano | Alta (texto curado) |
| Transcripciones | Subtítulos de YouTube segmentados | Base (cobertura total) |

Los aciertos en timeline siempre salen por encima de los de transcripción.

### Formato del índice

`data/indice_busqueda.json` — compacto a propósito:

```json
{"generado":"2026-08-17","episodios":[
  {"youtubeId":"abc123","segmentos":[[342,"texto del segmento"],...]}
]}
```

Cada segmento es `[segundoDeInicio, texto]`. La normalización (acentos,
mayúsculas) la hace el navegador con la misma función `limpia()` que ya usaba
el buscador de timelines.

### Comportamiento

- El índice se descarga **solo cuando el usuario toca el buscador** (la home
  sigue ligera para quien no busca)
- Resultado de voz: cita entre comillas con la palabra resaltada, minuto exacto
  y enlace a YouTube **3 segundos antes** de la frase (para no entrar a mitad)
- Tolerante a acentos y mayúsculas; exige que aparezcan todas las palabras

## FORMULARIOS

`mailto:` a `entrenaaquinadie@gmail.com` (Bici o Cepo y marcas). Pendiente
valorar un servicio de formularios real.

## NEWSLETTER

Embed de Beehiiv. Sin desarrollo propio.

## DEPLOY

`public_html` es el repo. Claude (o Eduardo) edita → commit → push a GitHub.
Para publicar cambios hechos en local: `git pull` en el servidor.
⚠️ La CDN de Hostinger cachea: si un cambio no se ve, purgar desde hPanel.
