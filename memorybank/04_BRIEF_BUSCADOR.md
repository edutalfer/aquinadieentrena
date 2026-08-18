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

### ✅ La jerga ciclista: RESUELTO con un diccionario (2026-08-18)

`pipeline/correcciones.json` corrige las erratas de los subtítulos automáticos
**al construir el índice**, no sobre `data/transcripciones/`. Así el dato en
bruto queda intacto y reversible, y los episodios nuevos se corrigen solos.

20 reglas, **984 correcciones aplicadas**: corredores (Pogačar, Vingegaard,
Pidcock, Almeida, Evenepoel, Roglič), marcas (Canyon, Dura-Ace, Pinarello,
Cannondale, Specialized) y jerga (tubeless, gravel).
El usuario ve el texto ya corregido en el fragmento (decisión de Eduardo).

⚠️ **Las reglas se curan a mano, nunca automáticamente.** La detección por
parecido fonético da falsos positivos masivos: marcaba «para» como error de
«pájara» (5.189 veces), «cepo» como «EPO» (1.000), «opinión» como «piñón» y
«misma» como «Visma». Aplicarlo a ciegas habría destrozado las transcripciones.
Los intocables están documentados en el propio `correcciones.json`.

Tampoco se tocan variantes válidas del español: «casete» y «culote» son
correctas según la RAE.

**Para añadir una regla nueva:** editarla en `pipeline/correcciones.json`,
verificar antes el contexto real de la palabra, y reconstruir con
`python3 pipeline/03_construir_indice.py`.

Queda pendiente (P7) Whisper solo si algún día se quiere calidad de
transcripción real; con el diccionario, el buscador ya encuentra los términos
técnicos.

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
