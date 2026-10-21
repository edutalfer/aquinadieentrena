# 📊 CIFRAS DE PROYECTOS — de dónde salen y cómo se actualizan

> Última actualización: **2026-10-21** (datos de YouTube vía Metricool; seguidores, horas y episodios de portada).
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
  **más los episodios que aún no se han dado de alta** (a 21/10: `UVnhLGkQ0vQ`
  27/09, `jflMTj7mS9U` 11/10 y `BgQbir_zsNQ` 18/10). Comprueba los domingos.

## Vídeos de cada proyecto (el nº de piezas cuadra con el dossier)

| Proyecto | Piezas | IDs |
|---|---|---|
| Andalucía Bike Race | 6 | 4vvQ3ymvazg B9I9Y527ATs fp0OXv2OsrA wywJ_G8Buyc 1v4JWkcbe-I jhuMXhTkHn0 |
| Cape Epic 2026 | 9 | sfOY9nmIOzM jlV3fD6zvCU b5OR1MXS8P8 3Pr64tcGbk0 HWBE2XI73-o 8JS-24axDYQ _6qZ6ZSnOks fXb7UcCh7oQ gxSTNfGGGpc |
| Cruzando Taiwán | 7 | 3-2bzm61Gsg ugJj-cq0Bfs zpJeQpvnQeA dyoSxezNMKQ 1YnWPg4FgHU Sugb57APefk mm7wG4tkDmE |
| The Traka 2026 | 1 | L6ewu4gQwL8 |
| ANE House 2026 | 23 | SGDjxwgC8KM SPIZqVhkVXE tMrIWslNHVk NVHS4wqynH4 3e70WAatA34 nJi8hEYbYoI Q_bhqtEgaTY n-E6ftjVbU0 Zu6Wvju7B9U UGP2qFIl9zs 4uKX6rYblAU LWA_UNDh1-E pBRkpwyIJzU CuYFnUigxWk o_nTypg2-Ik 1gLYgMcFPaQ Jszeu65jaoc 0Zk9v3_yteA 8C__g7ZjusQ j_do6nYdW7c ixz3hEqV4V8 KI5S5sIVsu4 JM7UG1U_NHE |

## Resultado a 2026-10-21 (YouTube)

| | Visualizaciones | Media | Horas | Visionado medio | Likes/pieza |
|---|---|---|---|---|---|
| Podcast (55 ep.) | 1.499.705 | 27.267 (últ. 5: 37.601) | 565.751 | 22:38 | 760 |
| ABR | 114.367 | 19.061 | 21.862 | 11:28 | 917 |
| Cape Epic 2026 | 242.144 | 26.905 (máx 35.284) | 52.379 | 12:58 | 1.272 |
| Cruzando Taiwán | 122.715 | 17.531 | 28.221 | 13:47 | 749 |
| The Traka 2026 | 29.226 | — | 3.625 | 7:26 | 955 |
| ANE House 2026 | **315.918** | 13.736 | 94.769 | 17:59 | 567 |
| Canal entero (vídeos desde sep. 2025) | — | — | 827.875 | — | — |

Podcast = 68,3 % de las horas del canal (la web dice «68 %»). La media de
los últimos 5 baja porque entra el episodio del 18/10 con solo tres días.

Historial: a 2026-10-02 podcast 52 ep. 1.368.087 · ABR 111.911 · Cape 238.135 ·
Taiwán 119.349 · Traka 27.089 · ANE House 314.612. A 2026-09-25: podcast 51
ep. 1.308.839 · ABR 111.109 · Cape 236.872 · Taiwán 117.950 · Traka 26.648.

## Cifras de portada («Nuestro primer año», sep. 2025 – sep. 2026)

Recalculadas el 2026-10-02 (antes venían del dossier: 26,8 M · 739.433 · 51.127 · 51).
El 2026-10-21 se actualizaron horas, seguidores y episodios; las
visualizaciones (27,9 M, periodo cerrado) no se volvieron a sumar.
La nota de la web ya dice que seguidores y episodios son «a octubre».

- **Visualizaciones 27,9 M** = una sola consulta diaria de 2025-09-01 a
  2026-09-30, sumada: YouTube `YTEV02` (4.720.733) + TikTok `TKEV02`
  (3.000.007) + Instagram 20.189.974, que es `IGEV05` (visualizaciones de la
  cuenta) en los días que lo hay —desde el 13/01/2026— y `IGEV23` (Reels)
  antes. Con el mismo método, sep. 2025 – ago. 2026 da 24,87 M (el dossier
  decía ~23,9 M): **el método de Instagram no es idéntico al del dossier.**
  Solo Reels en Instagram daría 21,2 M.
- **Horas 800.602** (21/10) = Σ watchMinutes/60, acumulados hasta hoy, de los
  vídeos del canal publicados de sep. 2025 a sep. 2026. Solo YouTube.
  (El 02/10 eran 786.247.)
- **Seguidores 53.062** = último día con dato (20/10/2026): Instagram 29.189
  (`IGEV01`) + YouTube 17.700 (`YTEV01`, YouTube redondea a centenas) +
  TikTok 6.173 (`TKEV07`). (01/10: 51.818.)
- **Episodios 55** (21/10) = un domingo tras otro desde el 07/09/2025.

## ⚠️ Notas y pendientes

- ~~ANE House~~ **Aclarado (25/09):** las **428.928** visualizaciones del dossier
  suman **todas las plataformas** (lo confirmó Eduardo); en YouTube solo son
  314.020. La web muestra 428.928 con la etiqueta «sumando todas las
  plataformas». Ojo al actualizar: esa cifra no sale de la consulta de YouTube
  y **no se ha actualizado** (a 21/10, en YouTube +1.898 desde el 25/09).
- **«Fuera del estudio: 49 piezas · 858.313 · 14:42»** (dossier) no se puede
  reproducir: incluye 3 vlogs de entrenamiento sin identificar, y ya en el
  dossier los cinco proyectos suman más que ese total. Se deja tal cual.
- El **68 %** (horas del podcast sobre el total del canal) sale de la consulta
  por vídeo (02/10: 67 %; dossier: 68 %).
