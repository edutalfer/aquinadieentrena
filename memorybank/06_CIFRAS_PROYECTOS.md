# 📊 CIFRAS DE PROYECTOS — de dónde salen y cómo se actualizan

> Última actualización: **2026-10-02** (datos de YouTube vía Metricool; cifras de portada recalculadas).
> Las cifras de `proyectos.html` salen de aquí. Al actualizar, cambia la fecha
> de la página («Datos de YouTube a …») y la de este fichero.

## Cómo sacar los datos

Conector MCP de Metricool (no hay API REST, ver P8), marca `5337599`:

```
getAnalyticsDataByMetrics(brandId 5337599, from 2025-08-01, to hoy,
  metrics [YTVP01 fecha, YTVP04 videoId, YTVP06 views, YTVP07 watchMinutes,
           YTVP08 avgViewDuration (s), YTVP09 likes, YTVP17 título])
```

Da **todos los vídeos del canal con sus cifras acumuladas hasta hoy** (no
por rango: aquí no aplica la trampa de las visualizaciones por periodo).

- Horas = Σ watchMinutes / 60
- Visionado medio = Σ watchMinutes × 60 / Σ views (ponderado)
- **Podcast** = los vídeos cuyo ID está en `data/episodios.js` (fuente fiable)
  **más los episodios que aún no se han dado de alta** (a 02/10: `UVnhLGkQ0vQ`,
  27/09, «¿Qué está a punto de pasar en RAPHA?»). Comprueba el último domingo.

## Vídeos de cada proyecto (el nº de piezas cuadra con el dossier)

| Proyecto | Piezas | IDs |
|---|---|---|
| Andalucía Bike Race | 6 | 4vvQ3ymvazg B9I9Y527ATs fp0OXv2OsrA wywJ_G8Buyc 1v4JWkcbe-I jhuMXhTkHn0 |
| Cape Epic 2026 | 9 | sfOY9nmIOzM jlV3fD6zvCU b5OR1MXS8P8 3Pr64tcGbk0 HWBE2XI73-o 8JS-24axDYQ _6qZ6ZSnOks fXb7UcCh7oQ gxSTNfGGGpc |
| Cruzando Taiwán | 7 | 3-2bzm61Gsg ugJj-cq0Bfs zpJeQpvnQeA dyoSxezNMKQ 1YnWPg4FgHU Sugb57APefk mm7wG4tkDmE |
| The Traka 2026 | 1 | L6ewu4gQwL8 |
| ANE House 2026 | 23 | SGDjxwgC8KM SPIZqVhkVXE tMrIWslNHVk NVHS4wqynH4 3e70WAatA34 nJi8hEYbYoI Q_bhqtEgaTY n-E6ftjVbU0 Zu6Wvju7B9U UGP2qFIl9zs 4uKX6rYblAU LWA_UNDh1-E pBRkpwyIJzU CuYFnUigxWk o_nTypg2-Ik 1gLYgMcFPaQ Jszeu65jaoc 0Zk9v3_yteA 8C__g7ZjusQ j_do6nYdW7c ixz3hEqV4V8 KI5S5sIVsu4 JM7UG1U_NHE |

## Resultado a 2026-10-02 (YouTube)

| | Visualizaciones | Media | Horas | Visionado medio | Likes/pieza |
|---|---|---|---|---|---|
| Podcast (52 ep.) | 1.368.087 | 26.309 (últ. 5: 40.051) | 529.558 | 23:13 | 764 |
| ABR | 111.911 | 18.652 | 21.564 | 11:33 | 914 |
| Cape Epic 2026 | 238.135 | 26.459 (máx 34.511) | 51.785 | 13:02 | 1.267 |
| Cruzando Taiwán | 119.349 | 17.050 | 27.792 | 13:58 | 745 |
| The Traka 2026 | 27.089 | — | 3.533 | 7:49 | 946 |
| ANE House 2026 | **314.612** | 13.679 | 94.591 | 18:02 | 567 |
| Canal entero (vídeos desde sep. 2025) | 4.635.451 | — | 786.247 | — | — |

Podcast = 67,4 % de las horas del canal (la web dice «67 %»).

(A 2026-09-25: podcast 51 ep. 1.308.839 · ABR 111.109 · Cape 236.872 ·
Taiwán 117.950 · Traka 26.648 · ANE House 314.020.)

## Cifras de portada («Nuestro primer año», sep. 2025 – sep. 2026)

Recalculadas el 2026-10-02. Antes venían del dossier (26,8 M · 739.433 · 51.127 · 51).

- **Visualizaciones 27,9 M** = una sola consulta diaria de 2025-09-01 a
  2026-09-30, sumada: YouTube `YTEV02` (4.720.733) + TikTok `TKEV02`
  (3.000.007) + Instagram 20.189.974, que es `IGEV05` (visualizaciones de la
  cuenta) en los días que lo hay —desde el 13/01/2026— y `IGEV23` (Reels)
  antes. Con el mismo método, sep. 2025 – ago. 2026 da 24,87 M (el dossier
  decía ~23,9 M): **el método de Instagram no es idéntico al del dossier.**
  Solo Reels en Instagram daría 21,2 M.
- **Horas 786.247** = Σ watchMinutes/60 de todos los vídeos del canal
  publicados desde sep. 2025 (consulta por vídeo de arriba). Solo YouTube.
- **Seguidores 51.818** = último día con dato (01/10/2026): Instagram 28.640
  (`IGEV01`) + YouTube 17.200 (`YTEV01`, YouTube redondea a centenas) +
  TikTok 5.978 (`TKEV07`).
- **Episodios 52** = 51 de `data/episodios.js` + el del 27/09.

## ⚠️ Notas y pendientes

- ~~ANE House~~ **Aclarado (25/09):** las **428.928** visualizaciones del dossier
  suman **todas las plataformas** (lo confirmó Eduardo); en YouTube solo son
  314.020. La web muestra 428.928 con la etiqueta «sumando todas las
  plataformas». Ojo al actualizar: esa cifra no sale de la consulta de YouTube
  y **no se ha actualizado** (el 02/10 YouTube solo sumaba +592).
- **«Fuera del estudio: 49 piezas · 858.313 · 14:42»** (dossier) no se puede
  reproducir: incluye 3 vlogs de entrenamiento sin identificar, y ya en el
  dossier los cinco proyectos suman más que ese total. Se deja tal cual.
- El **67 %** (horas del podcast sobre el total del canal) se recalculó el
  02/10 con la consulta por vídeo (antes 68 %, del dossier).
