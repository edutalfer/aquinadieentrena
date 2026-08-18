# 🔍 BUSCADOR — Operativo. Guía de mantenimiento

> **Actualizado:** 2026-08-18
> Esto ya no es un brief de tarea pendiente: el buscador **funciona en
> producción**. Queda el mantenimiento semanal y algunas mejoras futuras.

---

## Estado

| Dato | Valor |
|---|---|
| Episodios indexados | 46 |
| Segmentos de transcripción | 8.675 |
| Bloques de timeline | 308 |
| Peso del índice | 4,44 MB en disco · **2,07 MB por la red** |

Verificado con navegador real. Buscar «creatina» → bloque en el 2:01 con
`&t=121s`. Términos que solo existen en las transcripciones («badlands»,
«iniesta») también caen donde deben.

`memorybank/`, `pipeline/`, `data/transcripciones/` y
`data/subtitulos_originales/` devuelven 403 al público; el índice y
`episodios.js` sí se sirven.

---

## Rutina semanal (episodio nuevo, cada domingo)

**En el Mac** — la descarga NO funciona desde el servidor (D10: YouTube veta
las IPs de centro de datos):

```bash
cd ~/Documents/Proyectos-IA/aquinadieentrena
git pull --rebase
# 1. Añadir el episodio a data/episodios.js (título, fecha, youtubeId, temas)
export PATH="$HOME/Library/Python/3.9/bin:$PATH"
./pipeline/pipeline.sh <youtubeId>
git add data/ && git commit -m "Transcripción del episodio X" && git push
```

Luego, en el servidor: `git pull`. Ya está publicado.

`data/episodios.js` es la **fuente de verdad editorial**: solo entra en el
índice lo que esté ahí.

---

## Limitaciones conocidas

### Dos episodios sin timeline
`tcVNXIfRcMw` y `l-OwmhoBQuY` — no existe timeline en ningún sitio, así que
solo se encuentran por transcripción. Si algún día se escriben, entran solos.

### La jerga ciclista sale fonética (P7)
Los subtítulos automáticos escriben «tubles» por «tubeless», así que buscar el
término correcto no encuentra episodios que lo tratan.

Fue una decisión consciente (D5): cobertura total hoy a cambio de calidad
regular. Dos salidas, de menor a mayor esfuerzo:

1. **Apaño barato:** los timelines pesan más que la transcripción en el
   ranking, así que escribir bien los términos clave en los bloques de
   `episodios.js` tapa buena parte del agujero sin retranscribir nada.
2. **Whisper en local:** mejor calidad con la jerga, 10-20 min por episodio.
   Merece la pena si las analíticas muestran búsquedas técnicas sin resultado.

### El peso del índice hay que vigilarlo
2,07 MB por la red con 46 episodios. A ~100 rondaría 4-5 MB. La carga perezosa
lo amortigua (solo baja si alguien busca), pero cuando moleste: trocear por
año o pasar a API. La estructura de datos no cambia, así que la migración es
barata.

---

## Al verificar, cuidado con la CDN

Dos trampas ya pisadas, ambas en `CLAUDE.md` y en `03_ESTADO_ACTUAL.md`:
`curl` desde el servidor no vale (se responde a sí mismo), y una ráfaga de
`curl` dispara el anti-bot de la CDN, que devuelve 403 en cualquier ruta y
parece que el sitio está roto. **Lo que zanja la duda es un navegador.**
