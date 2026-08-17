# 🔍 BRIEF — Alimentar el buscador

> Para el chat dedicado al buscador. Lee antes `03_ESTADO_ACTUAL.md`.

---

## Objetivo

Que el buscador encuentre **cualquier cosa dicha en un episodio**, no solo lo
que aparece en los títulos del timeline. Resultado esperado: episodio + minuto
exacto + fragmento de lo que se dijo + enlace directo a YouTube en ese segundo.

---

## Qué está HECHO

- ✅ **El código del buscador ya lo soporta.** `assets/js/app.js` tiene la capa
  de transcripciones: carga perezosa del índice, prioridad a los bloques del
  timeline sobre la transcripción, fragmento de contexto recortado alrededor
  de la palabra, enlace 3 segundos antes de la frase.
  Probado con datos simulados: "POGAČAR" encuentra un texto que dice "Pogacar".
- ✅ **Pipeline escrito y documentado** en `pipeline/` (ver `pipeline/LEEME.md`)
- ✅ `.htaccess` bloquea `pipeline/` y `data/transcripciones/` al público;
  `data/indice_busqueda.json` sí se sirve porque lo necesita el navegador

## Qué FALTA

- ❌ **Los datos.** `data/indice_busqueda.json` está vacío
  (`{"generado":"2026-08-17","episodios":[]}`)

---

## ⛔ El bloqueo real: YouTube no deja descargar los subtítulos

Dos intentos, dos fracasos distintos:

| Dónde | Error |
|---|---|
| Servidor Hostinger | «Sign in to confirm you're not a bot» y HTTP 429. Probados los player_client android, ios, tv, web_embedded y mweb: **todos fallan**. La IP de centro de datos está vetada. |
| Mac de Eduardo | «The page needs to be reloaded» en 4 episodios. En el quinto (`j_pzuhI0vZ4`) sí conectó pero dijo **«There are no subtitles for the requested languages»** |

El yt-dlp del Mac era la versión `2025.10.14` (casi un año vieja) — ese es el
primer sospechoso del error de recarga.

### Vías a probar, en orden

1. **Actualizar yt-dlp en el Mac** y ver qué subtítulos existen realmente:
   ```bash
   pip3 install --user --upgrade yt-dlp
   export PATH="$HOME/Library/Python/3.9/bin:$PATH"
   yt-dlp --list-subs "https://www.youtube.com/watch?v=uhRAjdhf_rQ"
   ```
   Si los idiomas no son `es`, ajustar `--sub-langs` en
   `pipeline/01_descargar_subtitulos.sh` (puede ser `es-orig`, `es-ES`…).

2. **Forzar otro cliente**: añadir `--extractor-args "youtube:player_client=tv"`.

3. **Descargar a mano desde YouTube Studio.** Los vídeos son suyos: puede
   bajar los `.vtt`/`.srt` de cada episodio y dejarlos en `pipeline/tmp/`
   con el nombre `<youtubeId>.es.vtt`. El resto del pipeline funciona igual.
   Son 5 episodios: es viable y desbloquea todo hoy mismo.

4. **Whisper en local** si los subtítulos automáticos no existen o son malos.
   Mejor calidad con la jerga ciclista, pero 10-20 min por episodio.

---

## Cómo se ejecuta el pipeline (SIEMPRE en el Mac, nunca en el servidor)

```bash
cd ~/Documents/Proyectos-IA/aquinadieentrena
export PATH="$HOME/Library/Python/3.9/bin:$PATH"
./pipeline/pipeline.sh --todos          # o ./pipeline/pipeline.sh <youtubeId>
git add data/ && git commit -m "Transcripciones" && git push
```
Luego, en el servidor: `git pull`.

⚠️ El `git push` desde el Mac por HTTPS pedirá usuario y token de GitHub.
El clone por SSH falla (`Permission denied (publickey)`): el Mac no tiene
clave dada de alta en GitHub. Usar HTTPS o configurar una clave.

---

## Episodios dados de alta ahora mismo (`data/episodios.js`)

`uhRAjdhf_rQ` · `EdlZUQjCDjc` · `8kEa4K59chg` · `fc-mkO2m9bk` · `j_pzuhI0vZ4`

`data/episodios.js` es la **fuente de verdad editorial**: solo entran en el
índice los episodios que estén ahí. Los timelines salen de la descripción de
YouTube, del bloque "Temas del episodio".

---

## Al terminar, comprobar

- Que el índice no está vacío y cuánto pesa (interesa el dato: si con ~50
  episodios se dispara por encima de 2-3 MB, habrá que trocearlo por año
  o pasar a una API — ver P2 en `02_DECISIONES.md`)
- Buscar en la web una palabra que **no** esté en ningún título de timeline
  y ver si sale el minuto correcto
- Recordar la trampa de la CDN al verificar (ver `03_ESTADO_ACTUAL.md`)
