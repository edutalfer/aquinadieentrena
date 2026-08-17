# Pipeline de transcripciones — ANE

Genera el índice que permite buscar **dentro de lo hablado** en los episodios,
no solo en los títulos del timeline.

## ⚠️ Importante: la descarga se hace EN LOCAL

YouTube bloquea las IPs de centros de datos (error «Sign in to confirm you're
not a bot» / HTTP 429). Desde el servidor de Hostinger **no se pueden descargar
subtítulos**. El paso 1 se ejecuta en el Mac; el resto da igual dónde.

## Uso semanal (en el Mac, dentro del repo clonado)

```bash
# 1. Añade el episodio nuevo a data/episodios.js (título, fecha, youtubeId, temas)
# 2. Lanza el pipeline
./pipeline/pipeline.sh <youtubeId>
# 3. Sube los datos
git add data/ && git commit -m "Transcripción del episodio X" && git push
```

Luego, en el servidor: `git pull` y queda publicado.

## Primera vez (todos los episodios de golpe)

```bash
./pipeline/pipeline.sh --todos
```

## Requisitos en el Mac

```bash
pip3 install yt-dlp     # descarga de subtítulos
# python3 y node ya vienen con macOS / Homebrew
```

Si en el Mac `python3` y `node` están en el PATH normal, puedes vaciar
`pipeline/entorno.sh` o dejarlo como está (no molesta).

## Qué hace cada paso

| Script | Qué hace |
|---|---|
| `01_descargar_subtitulos.sh` | Baja los subtítulos automáticos en español → `pipeline/tmp/<id>.es.vtt` |
| `02_segmentar.py` | Limpia el VTT (quita el "rodillo" de líneas repetidas) y lo agrupa en segmentos de ~35 s → `data/transcripciones/<id>.json` |
| `03_construir_indice.py` | Junta todo en `data/indice_busqueda.json`, que es lo que lee el buscador |
| `pipeline.sh` | Encadena los tres |

Solo entran en el índice los episodios dados de alta en `data/episodios.js`:
esa lista manda.

## Notas

- `pipeline/tmp/` no se versiona (está en .gitignore)
- Las transcripciones en bruto sí se versionan, para no tener que volver a
  descargarlas nunca
- `memorybank/`, `pipeline/` y `data/transcripciones/` están bloqueados al
  público desde `.htaccess`; el índice sí se sirve porque lo necesita el navegador
