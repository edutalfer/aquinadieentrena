# 🔍 BRIEF — Alimentar el buscador

> Actualizado: 2026-08-18. Lee antes `03_ESTADO_ACTUAL.md`.

---

## Objetivo

Que el buscador encuentre **cualquier cosa dicha en un episodio**, no solo lo
que aparece en los títulos del timeline. Resultado esperado: episodio + minuto
exacto + fragmento de lo que se dijo + enlace directo a YouTube en ese segundo.

## Estado: ✅ HECHO, DESPLEGADO Y VERIFICADO EN PRODUCCIÓN

**Los 46 episodios están indexados: 8.675 segmentos.** No falta ninguno: la
serie de domingos va seguida del 7 sep 2025 al 16 ago 2026, y el único hueco
—julio de 2026— es intencionado (D15: el Tour se queda fuera).

Verificado el 18/08/2026 en **https://aquinadieentrena.cc con un navegador
real** (no con `curl`: ver la trampa del anti-bot en `03_ESTADO_ACTUAL.md`):

| Prueba en producción | Resultado |
|---|---|
| Índice | 4,4 MB en disco · **2,07 MB por la red** · ~1,7 s · solo al tocar el buscador |
| Contenido | 46 episodios, 8.675 segmentos |
| «creatina» | 6 momentos; el primero es el bloque «¿La creatina tiene beneficios en el ciclismo?» → `&t=121s` |
| «badlands» | 1 momento, `qG4PMXC7wLM&t=1295s` (bloque de captura, funcionando) |
| «iniesta» | 8 momentos, `sF7fe35IJi4&t=147s` |
| Portada | 46 episodios cargados, 3 tarjetas en «últimos» |
| `memorybank/`, `pipeline/`, `data/transcripciones/`, `data/subtitulos_originales/` | **403**, bloqueados |
| `data/episodios.js`, `data/indice_busqueda.json` | 200, se sirven |

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

## Timelines: 44 de 46

**Hechos.** De los 18 que no llevaban «Temas del
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

### ⚠️ Una corrección a mano que NO está en YouTube

En `gy5RLwGDFs8` (9 ago 2026) la **descripción de YouTube sigue diciendo**
«¿Bici o cepo? (01:40:00)» en un vídeo que dura 1h21. En `data/episodios.js`
está corregido a mano a **1:00:40**, que es el minuto bueno según Eduardo.

**Si algún día se regenera ese episodio desde la página de YouTube, volverá el
error.** Las salidas son dos: arreglar la descripción en YouTube (lo suyo), o
volver a poner 1:00:40 a mano después de regenerar.

### Los cuatro desajustes que cazó la validación

Todos resueltos por Eduardo el 17/08/2026. Sirven de ejemplo de que el control
merece la pena:

| Episodio | Qué pasaba | Cómo quedó |
|---|---|---|
| `gy5RLwGDFs8` · 9 ago | La descripción de YouTube ponía el cepo en 1:40:00, en un vídeo de 1h21 | **1:00:40**, a mano (ver aviso de arriba) |
| `-TUEBDC4eqs` · 11 ene | La captura ponía «¿Bici o cepo? (01:45:20)» en un vídeo de 1h31 | **1:02:46** |
| `DyquvTvjJYE` · 21 dic | Un bloque sin minuto («marca china») | Captura nueva: ese bloque no va, y entra «Introducción (00:00)» |
| `tiRqcZ6vol8` · 16 nov | La captura situaba el sorteo en 59:41; el audio lo pone en 1:24:32 | **1:24:32**. Va después del «bici o cepo» |

**Resultado: 0 bloques fuera de rango o desordenados en los 46 episodios.**

---

## Lo único que queda pendiente

- **Dos timelines que no existen** (la tabla de arriba). Si algún día se
  escriben en YouTube, se regenera y entran solos.
- **«tubles».** Los subtítulos automáticos destrozan la jerga y eso limita el
  buscador. Ver P7 en `02_DECISIONES.md`.
