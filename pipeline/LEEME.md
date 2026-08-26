# Pipeline de transcripciones — ANE

Genera el índice que permite buscar **dentro de lo hablado** en los episodios,
no solo en los títulos de la línea de tiempo.

---

## 🟢 Añadir un episodio nuevo (lo normal, cada domingo)

Son dos pasos, y el primero es a mano por narices.

### 1. El subtítulo, desde YouTube Studio

`yt-dlp` **no funciona** para descargar: desde el servidor YouTube veta la IP
del hosting, y el yt-dlp del Mac (2025.10.14, atado al Python 3.9 del sistema)
responde «The page needs to be reloaded». Ver §5 de `CLAUDE.md`.

Así que el `.vtt` se baja a mano de YouTube Studio, que para eso los vídeos
son de Eduardo. **Formato VTT, no SRT.** Luego:

```bash
cd ~/Documents/Proyectos-IA/aquinadieentrena
git pull

YID=XXXXXXXXXXX     # lo que va después de "v=" en la URL
cp ~/Downloads/loQueSea.vtt data/subtitulos_originales/$YID.es.vtt
cp data/subtitulos_originales/$YID.es.vtt pipeline/tmp/$YID.es.vtt
```

### 2. El resto, de una orden

```bash
python3 pipeline/nuevo_episodio.py "https://youtu.be/XXXXXXXXXXX"
git add data/ && git commit -m "Episodio nuevo" && git push
```

Eso saca de la página del vídeo el título, la fecha, la duración y la línea de
tiempo, lo escribe en `data/episodios.js`, segmenta el VTT y reconstruye el
índice. No hay que copiar minutos a mano.

Para publicarlo, en el servidor: `git pull` (el repo es el docroot).

### De dónde sale la línea de tiempo

Del bloque **«Temas del episodio» de la descripción de YouTube**. Vale
cualquiera de los dos formatos, y da igual el orden:

```
Analizamos la nueva Trek Domane (19:20)
19:20 Analizamos la nueva Trek Domane
```

Si la descripción no lo lleva, el episodio entra igual y se encuentra por
transcripción, pero sin títulos de bloque, y el script avisa.

### Lo que el script NO deja pasar

Se planta y no escribe nada si la línea de tiempo está mal:

- minutos que no van de menos a más
- algún minuto por encima de la duración real del vídeo
- el episodio ya está dado de alta (no duplica)

Esos controles no son paranoia: ya cazaron cuatro erratas reales, una de ellas
un «¿Bici o cepo? (01:40:00)» en un vídeo de 1h21.

---

## Rehacer el índice entero

```bash
./pipeline/pipeline.sh --todos
```

Un par de minutos con los 47 episodios, y no descarga nada: reutiliza los VTT
que ya están en `data/subtitulos_originales/`.

---

## Qué hace cada pieza

| Script | Qué hace |
|---|---|
| `nuevo_episodio.py` | **La orden normal.** Metadatos + alta en `episodios.js` + segmentación + índice |
| `01_descargar_subtitulos.sh` | Bajaría los subtítulos con yt-dlp. **Hoy falla**; se salta solo si el VTT ya está en `pipeline/tmp/` |
| `02_segmentar.py` | Limpia el VTT y lo agrupa en segmentos de ~35 s → `data/transcripciones/<id>.json` |
| `03_construir_indice.py` | Junta todo en `data/indice_busqueda.json` y aplica `correcciones.json` |
| `correcciones.json` | Arregla las erratas del subtitulado automático («pogachar» → «Pogačar», «ledville» → «Leadville») al construir el índice, sin tocar el dato en bruto |
| `pipeline.sh` | Encadena los pasos 1-3 para uno o para todos |

Solo entran en el índice los episodios dados de alta en `data/episodios.js`:
esa lista manda.

---

## Comprobar que ha salido bien

- El índice no está vacío y pesa lo que debe (mide **en disco y por la red**).
- Buscar en la web una palabra que **no** esté en ningún título de bloque y ver
  si devuelve el minuto correcto («creatina» va bien para esto).
- Verificar en producción **con un navegador**, no con `curl`: la CDN corta las
  ráfagas de `curl` con un 403 y un JS challenge, y eso ya provocó un
  diagnóstico falso. Ver §2 de `CLAUDE.md`.

## Notas

- `data/subtitulos_originales/` es la fuente en bruto y **se versiona**: si
  cambia la segmentación, se reconstruye sin volver a YouTube.
  `pipeline/tmp/` es scratch y está en `.gitignore`.
- Python y Node existen en el servidor pero fuera del PATH:
  `source pipeline/entorno.sh`.
- `memorybank/`, `pipeline/`, `data/transcripciones/` y
  `data/subtitulos_originales/` están bloqueados al público desde `.htaccess`;
  el índice sí se sirve, porque lo necesita el navegador.
