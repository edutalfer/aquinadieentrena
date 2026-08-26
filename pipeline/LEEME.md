# Pipeline de transcripciones — ANE

## 🟢 Añadir un episodio nuevo (lo normal, cada domingo)

**En el Mac** (nunca en el servidor: YouTube veta las IPs del hosting):

```bash
cd ~/Documents/Proyectos-IA/aquinadieentrena
git pull
export PATH="$HOME/Library/Python/3.9/bin:$PATH"

python3 pipeline/nuevo_episodio.py "https://www.youtube.com/watch?v=XXXXXXXXXXX"

git add data/ && git commit -m "Episodio nuevo" && git push
```

Ese único comando saca de YouTube el título, la fecha, la duración y **los
capítulos** (que se convierten en el timeline), lo escribe todo en
`data/episodios.js`, descarga los subtítulos, los segmenta y reconstruye el
índice. No hay que copiar minutos a mano.

Luego, para publicarlo, en el servidor: `git pull`.

### Requisitos
- **El vídeo debe tener capítulos en YouTube.** De ahí sale el timeline. Si no
  los tiene, el episodio se añade igual pero solo se encontrará por
  transcripción, y el script avisa.
- Si el episodio ya está en `episodios.js`, el script se planta y no duplica.

---

## Reprocesar todo (raro)

```bash
./pipeline/pipeline.sh --todos
```

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
| `nuevo_episodio.py` | **El que se usa normalmente**: de la URL de YouTube a la web, capítulos incluidos |
| `correcciones.json` | Erratas de los subtítulos automáticos, aplicadas al construir el índice |

Solo entran en el índice los episodios dados de alta en `data/episodios.js`:
esa lista manda.

## Notas

- `pipeline/tmp/` no se versiona (está en .gitignore)
- Las transcripciones en bruto sí se versionan, para no tener que volver a
  descargarlas nunca
- `memorybank/`, `pipeline/` y `data/transcripciones/` están bloqueados al
  público desde `.htaccess`; el índice sí se sirve porque lo necesita el navegador
