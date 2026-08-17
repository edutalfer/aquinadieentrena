# 🔍 BRIEF — Alimentar el buscador

> Actualizado: 2026-08-17. Lee antes `03_ESTADO_ACTUAL.md`.

---

## Objetivo

Que el buscador encuentre **cualquier cosa dicha en un episodio**, no solo lo
que aparece en los títulos del timeline. Resultado esperado: episodio + minuto
exacto + fragmento de lo que se dijo + enlace directo a YouTube en ese segundo.

## Estado: ✅ HECHO Y COMPLETO

**Los 46 episodios están indexados: 8.675 segmentos.** No falta ninguno: la
serie de domingos va seguida del 7 sep 2025 al 16 ago 2026, y el único hueco
—julio de 2026— es intencionado (D15: el Tour se queda fuera).

Probado en local (`python3 -m http.server`) contra `index.html`:

| Prueba | Resultado |
|---|---|
| «creatina» (no está en ningún timeline) | 28 momentos, minuto correcto, enlace `&t=1537s` |
| «aeroad», «armstrong» | caen en los episodios de abril y marzo, los últimos en entrar |
| Tiempo de búsqueda | 6-30 ms, incluso con miles de hallazgos |
| Índice | 4,4 MB en disco · **1,6 MB por la red** (mod_deflate) · carga perezosa |
| `episodios.html` | 46 tarjetas, de 16 ago 2026 a 7 sep 2025 |

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

## Qué entra en el índice y qué no

**Criterio.** El canal tiene 128 vídeos; solo 46 son episodio. Se cruzaron
fecha, día de la semana y duración de los 128: los episodios son **el vídeo
largo del domingo** (45 domingos, 1 lunes; `lWj1oTcYX8o` salió un martes,
desplazado por la Cape Epic). Los 46 ya están dentro.

**El Tour NO entra — decidido (D15).** En julio de 2026 no hubo episodio
dominical; hubo 22 vídeos diarios de etapa (33-57 min, 16 h en total) más el
especial de presentación. Eduardo ha decidido que **de momento no van al
buscador**: no hay que bajar sus VTT ni darlos de alta. El hueco de julio en
`episodios.html` es intencionado, no es un fallo.

El resto de los 128 son vlogs, «Bici o cepo» y vídeos cortos de noticias
(«Malas noticias para Rapha» son 10 min, no es episodio), más los diarios de
Cape Epic, Andalucía Bike Race y Taiwán.

---

## Lo único que queda pendiente

**1. 18 de los 46 no tienen timeline**, porque su descripción de YouTube no
lleva el bloque «Temas del episodio». Salen en el buscador por transcripción,
pero sin título de bloque y sin bloques en su tarjeta de `episodios.html`.

### Los 18, numerados

**Esta numeración es fija** y es la que se usa para mandar las capturas de los
timelines: «captura 7» = el episodio 7 de esta tabla. No renumerar aunque se
vayan completando; lo que se completa se tacha, pero el número se queda.

| # | Fecha | Dur. | youtubeId | Título |
|---|---|---|---|---|
| **1** | 10 may 2026 | 71 min | `gFu-15TP8uw` | ¿Quién ganará en el mercado? \| Polémica en Traka |
| **2** | 24 mar 2026 | 69 min | `lWj1oTcYX8o` | Errores de la Cape Epic 2026 |
| **3** | 02 mar 2026 | 104 min | `tcVNXIfRcMw` | Episodio Especial: Sobremesa con los pros |
| **4** | 22 feb 2026 | 113 min | `gWswoVKJ2Lc` | Nueva Cannondale SUPERSIX! ¿Bici o Cepo? |
| **5** | 15 feb 2026 | 116 min | `B9y3ELUXPJk` | ¿Redbull te da alas? \| NOS PAGAN LA CAPE! |
| **6** | 08 feb 2026 | 107 min | `ev0tzxeAOLY` | ¿Qué esta pasando en CANYON? |
| **7** | 01 feb 2026 | 127 min | `Z0HybwI-yxk` | Flecha nos acusa de "CHARLATANES" |
| **8** | 25 ene 2026 | 81 min | `VEvA7w4EVOc` | TODAS las bicis del world tour 2026 analizadas! |
| **9** | 18 ene 2026 | 113 min | `5yoINT_hUiw` | ¿Por qué se retira un campeón tan pronto? |
| **10** | 11 ene 2026 | 91 min | `-TUEBDC4eqs` | Los beneficios de la creatina para ciclistas |
| **11** | 04 ene 2026 | 94 min | `IXwk61V6gWs` | Specialized lo vuelve a hacer! |
| **12** | 28 dic 2025 | 96 min | `l-OwmhoBQuY` | Nuevo Recorrido Vuelta 2026! |
| **13** | 21 dic 2025 | 118 min | `DyquvTvjJYE` | Nuevas Equipaciones 2026!! |
| **14** | 30 nov 2025 | 76 min | `sF7fe35IJi4` | Campagnolo está en peligro |
| **15** | 16 nov 2025 | 85 min | `tiRqcZ6vol8` | 5 marcas dicen que tienen la bici más rápida |
| **16** | 02 nov 2025 | 102 min | `umyb6Wroe84` | Top 10 Sueldos del Peloton |
| **17** | 14 sep 2025 | 53 min | `3_6ojm-SF7c` | ¿La mas rápida? ¡Una Decathlon! \| Ep.2 |
| **18** | 07 sep 2025 | 39 min | `qG4PMXC7wLM` | Los vatios inhumanos de Pogacar \| Ep.1 |

La columna **Dur.** está para validar: ningún minuto del timeline puede pasarse
de ahí. Es el control que cazó la errata de `gy5RLwGDFs8` (pendiente 2).

### Dos formas de arreglarlo

- **La buena:** escribir el timeline en la **descripción de YouTube** y volver
  a generar `data/episodios.js` (paso 3 de arriba). El dato queda en la fuente
  y los espectadores ganan capítulos clicables en el reproductor.
- **La de capturas:** Eduardo manda capturas numeradas según la tabla y se
  transcriben a mano a `data/episodios.js`. Al transcribir hay que comprobar
  siempre tres cosas: minutos crecientes, ninguno por encima de la duración, y
  que el número de bloques cuadre con la captura. Ante la duda, preguntar en
  vez de inventar un minuto.

**2. Una errata en YouTube.** En `gy5RLwGDFs8` (9 ago 2026) la descripción dice
«¿Bici o cepo? (01:40:00)» en un vídeo que dura 1h21. El dato se ha dejado tal
cual porque el timeline sale tal cual de YouTube: arreglar allí y regenerar.

**3. «tubles».** Ver P7 en `02_DECISIONES.md`.
