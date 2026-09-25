# 📊 CIFRAS DE PROYECTOS — de dónde salen y cómo se actualizan

> Última actualización: **2026-09-25** (datos de YouTube vía Metricool).
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

## Vídeos de cada proyecto (el nº de piezas cuadra con el dossier)

| Proyecto | Piezas | IDs |
|---|---|---|
| Andalucía Bike Race | 6 | 4vvQ3ymvazg B9I9Y527ATs fp0OXv2OsrA wywJ_G8Buyc 1v4JWkcbe-I jhuMXhTkHn0 |
| Cape Epic 2026 | 9 | sfOY9nmIOzM jlV3fD6zvCU b5OR1MXS8P8 3Pr64tcGbk0 HWBE2XI73-o 8JS-24axDYQ _6qZ6ZSnOks fXb7UcCh7oQ gxSTNfGGGpc |
| Cruzando Taiwán | 7 | 3-2bzm61Gsg ugJj-cq0Bfs zpJeQpvnQeA dyoSxezNMKQ 1YnWPg4FgHU Sugb57APefk mm7wG4tkDmE |
| The Traka 2026 | 1 | L6ewu4gQwL8 |
| ANE House 2026 | 23 | SGDjxwgC8KM SPIZqVhkVXE tMrIWslNHVk NVHS4wqynH4 3e70WAatA34 nJi8hEYbYoI Q_bhqtEgaTY n-E6ftjVbU0 Zu6Wvju7B9U UGP2qFIl9zs 4uKX6rYblAU LWA_UNDh1-E pBRkpwyIJzU CuYFnUigxWk o_nTypg2-Ik 1gLYgMcFPaQ Jszeu65jaoc 0Zk9v3_yteA 8C__g7ZjusQ j_do6nYdW7c ixz3hEqV4V8 KI5S5sIVsu4 JM7UG1U_NHE |

## Resultado a 2026-09-25 (YouTube)

| | Visualizaciones | Media | Horas | Visionado medio | Likes/pieza |
|---|---|---|---|---|---|
| Podcast (51 ep.) | 1.308.839 | 25.664 (últ. 5: 36.639) | 513.669 | 23:30 | — |
| ABR | 111.109 | 18.518 | 21.481 | 11:36 | 914 |
| Cape Epic 2026 | 236.872 | 26.319 (máx 34.350) | 51.625 | 13:04 | 1.267 |
| Cruzando Taiwán | 117.950 | 16.850 | 27.613 | 14:02 | 743 |
| The Traka 2026 | 26.648 | — | 3.510 | 7:54 | 943 |
| ANE House 2026 | **314.020** | 13.653 | 94.512 | 18:03 | 567 |

## ⚠️ Pendiente de aclarar con Eduardo

- **ANE House**: el dossier dice **428.928** visualizaciones, pero en YouTube los
  23 vídeos suman 314.020, aunque horas, likes y visionado medio sí cuadran con
  el dossier. Probablemente el dossier suma otras plataformas. La web sigue
  mostrando 428.928 hasta que se confirme.
- **«Fuera del estudio: 49 piezas · 858.313 · 14:42»** (dossier) no se puede
  reproducir: incluye 3 vlogs de entrenamiento sin identificar, y ya en el
  dossier los cinco proyectos suman más que ese total. Se deja tal cual.
- El **68 %** (horas del podcast sobre el total del canal) es del dossier; no
  se ha recalculado el total del canal.
