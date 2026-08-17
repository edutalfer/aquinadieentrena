# 🔍 BRIEF — Alimentar el buscador

> Actualizado: 2026-08-17. Lee antes `03_ESTADO_ACTUAL.md`.

---

## Objetivo

Que el buscador encuentre **cualquier cosa dicha en un episodio**, no solo lo
que aparece en los títulos del timeline. Resultado esperado: episodio + minuto
exacto + fragmento de lo que se dijo + enlace directo a YouTube en ese segundo.

## Estado: ✅ HECHO

`data/indice_busqueda.json` ya no está vacío: **40 episodios, 7.667 segmentos**.
Probado en local (`python3 -m http.server`) contra `index.html`:

| Prueba | Resultado |
|---|---|
| «creatina» (no está en ningún timeline) | 28 momentos, minuto correcto, enlace `&t=1537s` |
| Tiempo de búsqueda | 6-8 ms, incluso con 7.719 hallazgos |
| Índice | 3,9 MB en disco · **1,4 MB por la red** (mod_deflate) · carga perezosa |
| `episodios.html` | 40 tarjetas, de 16 ago 2026 a 7 sep 2025 |

---

## Cómo se hizo (para repetirlo con episodios nuevos)

1. Eduardo baja el `.vtt` de YouTube Studio (**VTT, no SRT**: `02_segmentar.py`
   está escrito para VTT).
2. Se guarda como `data/subtitulos_originales/<youtubeId>.es.vtt` y se copia a
   `pipeline/tmp/` con ese mismo nombre.
3. Se añade el episodio a `data/episodios.js` (título, fecha, duración,
   youtubeId, temas). Fecha, duración y timeline salen de la página pública del
   vídeo, que se puede leer con `curl` desde el Mac — ver D13 en
   `02_DECISIONES.md`. yt-dlp **no** sirve para esto ahora mismo.
4. `./pipeline/pipeline.sh <youtubeId>` (el paso 1 salta solo si el VTT ya está
   en `pipeline/tmp/`).
5. `git add data/ && git commit && git push`; en el servidor, `git pull`.

⚠️ El `git push` desde el Mac por HTTPS pide usuario y token de GitHub. El
clone por SSH falla: el Mac no tiene clave dada de alta en GitHub.

---

## Lo que quedó pendiente

**1. Faltan seis episodios por bajar de Studio.** El canal tiene 128 vídeos,
40 con VTT. Se cruzaron fecha, día de la semana y duración de los 128: los 40
indexados son 39 domingos + 1 lunes, así que el episodio es **el vídeo largo
del domingo**. Con ese criterio faltan estos seis, y solo estos:

| youtubeId | Fecha | Dur. | Timeline | Título |
|---|---|---|---|---|
| `KPiGXHKJ_gM` | dom 12 abr 2026 | 114 min | 9 temas | Van Der Poel IGNORA a Canyon en Roubaix! |
| `_pNQhuWqt3M` | dom 5 abr 2026 | 94 min | 6 temas | ¿Esta es la nueva Canyon Aeroad? \| Strava no tiene sentido |
| `38AjpfCo7dc` | dom 29 mar 2026 | 81 min | 9 temas | El futuro del textil en ciclismo |
| `lWj1oTcYX8o` | mar 24 mar 2026 | 69 min | — | Errores de la Cape Epic 2026 |
| `U1yfpFm5A4g` | dom 15 mar 2026 | 69 min | 7 temas | Specialized perdona a Armstrong?? |
| `piWBTCsMFdY` | dom 21 sep 2025 | 67 min | 7 temas | ¿Como de ancho es demasiado ancho!? \| EP.3 |

Es el hueco de marzo-abril de 2026 (Cape Epic) más el `EP.3` de septiembre.
`lWj1oTcYX8o` salió en martes, seguramente desplazado por la Cape Epic.

**El Tour NO entra — decidido (D15).** En julio de 2026 no hubo episodio
dominical; hubo 22 vídeos diarios de etapa (33-57 min, 16 h en total) más el
especial de presentación. Eduardo ha decidido que **de momento no van al
buscador**: no hay que bajar sus VTT ni darlos de alta. El hueco de julio en
`episodios.html` es intencionado, no es un fallo.

El resto de los 128 son vlogs, «Bici o cepo» y vídeos cortos de noticias
(«Malas noticias para Rapha» son 10 min, no es episodio), más los diarios de
Cape Epic, Andalucía Bike Race y Taiwán.

**2. 17 de los 40 no tienen timeline** porque su descripción de YouTube no
lleva el bloque «Temas del episodio». Salen en el buscador por transcripción,
pero sin título de bloque. Se arregla escribiendo el timeline en YouTube y
volviendo a pasar el paso 3.

**3. Una errata en YouTube.** En `gy5RLwGDFs8` (9 ago 2026) la descripción dice
«¿Bici o cepo? (01:40:00)» en un vídeo que dura 1h21. El dato se ha dejado tal
cual porque el timeline sale tal cual de YouTube: arreglar allí y regenerar.

**4. «tubles».** Ver P7 en `02_DECISIONES.md`.
