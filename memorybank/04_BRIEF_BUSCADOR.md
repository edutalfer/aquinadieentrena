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

**1. Timelines: 44 de 46 hechos.** De los 18 que no llevaban «Temas del
episodio» en la descripción de YouTube, Eduardo mandó **16 capturas** de las
diapositivas del vídeo y se transcribieron a `data/episodios.js`. Total en la
web: **308 bloques**.

Los dos que siguen sin timeline, porque no existe:

| Fecha | youtubeId | Título |
|---|---|---|
| 02 mar 2026 | `tcVNXIfRcMw` | Episodio Especial: Sobremesa con los pros |
| 28 dic 2025 | `l-OwmhoBQuY` | Nuevo Recorrido Vuelta 2026! |

### Cómo se validó lo transcrito de las capturas

Leer minutos de una imagen es donde se cuela el error, así que cada bloque
pasó tres controles antes de entrar:

1. **Minutos crecientes** dentro del episodio.
2. **Ninguno por encima de la duración real** del vídeo. Este control cazó dos
   erratas (ver más abajo).
3. **Contraste con la transcripción**: para cada bloque se comprueba que en ese
   minuto se esté hablando de eso. 87 de 104 bloques se confirmaron solos; los
   17 restantes eran nombres propios que el subtitulado automático destroza
   («Campagnolo» → «Campañolo», «Rapha» → «Rafa») y se revisaron a mano.

El script de validación no está en el repo (es de un solo uso), pero el
procedimiento sí: **repetirlo con cualquier timeline que venga de una captura.**

### Si algún día se rehace

La vía buena sigue siendo escribir el timeline **en la descripción de
YouTube**: el dato queda en la fuente, se regenera solo, y los espectadores
ganan capítulos clicables en el reproductor.

**2. Una errata, en YouTube.** En `gy5RLwGDFs8` (9 ago 2026) la descripción
pone «¿Bici o cepo? (01:40:00)» en un vídeo que dura 1h21. Se ha dejado tal
cual porque ese timeline sale de YouTube: **hay que arreglarlo allí** y volver
a generar `data/episodios.js`. Según el audio, la sección arranca sobre 1:02.

Los otros tres desajustes que salieron al validar ya están resueltos por
Eduardo (17/08/2026), y son un buen ejemplo de que el control sirve:

| Episodio | Qué pasaba | Cómo quedó |
|---|---|---|
| `-TUEBDC4eqs` · 11 ene | La captura ponía «¿Bici o cepo? (01:45:20)» en un vídeo de 1h31 | **1:02:46**, confirmado por Eduardo |
| `DyquvTvjJYE` · 21 dic | Un bloque sin minuto («marca china») | Captura nueva: ese bloque no va, y entra «Introducción (00:00)» |
| `tiRqcZ6vol8` · 16 nov | La captura situaba el sorteo en 59:41; el audio lo pone en 1:24:32 | **1:24:32**, confirmado por Eduardo. Va después del «bici o cepo» |

**3. «tubles».** Ver P7 en `02_DECISIONES.md`.
