# 📍 ESTADO ACTUAL — Léeme primero

> **Actualizado:** 2026-08-18
> Punto de partida para cualquier chat nuevo sobre la web de ANE.

---

## ⚠️ Antes que nada: lee `CLAUDE.md` (raíz del repo)

Ahí están las **reglas duras** (entorno, trampas, verificación, seguridad).
Este documento cuenta *en qué punto está el proyecto*; `CLAUDE.md` cuenta
*cómo trabajar sin romper nada*. Los dos, y `CLAUDE.md` primero.

Después, según la tarea:
- `00_MEGAPLAN_WEB_ANE.md` — objetivo y roadmap
- `01_ESQUELETO_WEB.md` — arquitectura
- `02_DECISIONES.md` — qué se decidió y por qué (D1–D15)
- `04_BRIEF_BUSCADOR.md` — buscador (ya operativo; queda mantenimiento)
- `05_BRIEF_ESTETICA.md` — rediseño visual (en curso)

---

## 🏆 El objetivo core está CUMPLIDO

El buscador de temas funciona en producción desde el 2026-08-18.

| Dato | Valor |
|---|---|
| Episodios indexados | **46** |
| Segmentos de transcripción | **8.675** |
| Bloques de timeline | **308** |
| Peso del índice | **4,44 MB en disco · 2,07 MB por la red** (gzip de la CDN) |

Verificado con navegador real: buscar «creatina» devuelve el bloque
«¿La creatina tiene beneficios en el ciclismo?» en el 2:01 con su `&t=121s`.

Carga perezosa: el índice solo se descarga cuando alguien toca el buscador,
así que los 2 MB no penalizan a quien entra y no busca.

---

## Dónde está cada cosa

- **Web pública:** https://aquinadieentrena.cc
- **Repo = docroot:** `~/domains/aquinadieentrena.cc/public_html`
  ⚠️ NO es `~/public_html`
- **GitHub:** `github.com/edutalfer/aquinadieentrena` (público)
- **Copia local de Eduardo:** `/Users/eduardotalavera/Documents/Proyectos-IA/aquinadieentrena`
- **Servidor:** `46.202.172.3` · puerto `65002` · usuario `u527801093`
- **Clave SSH:** `~/.ssh/claude-ane-2026` en el servidor (600, fuera del repo),
  dada de alta en hPanel como `claude-ane-3`

---

## 🔴 Pendientes que NO son código

| Qué | Quién | Nota |
|---|---|---|
| **Desactivar el Modo desarrollo de la CDN** | Eduardo | Se activó como medida temporal para trabajar el diseño (hPanel → Rendimiento → CDN). Sigue puesto. Desactivar al cerrar el rediseño |
| Dos episodios sin timeline | — | `tcVNXIfRcMw` y `l-OwmhoBQuY`: no existe timeline en ningún sitio. Solo se buscan por transcripción |
| Jerga mal transcrita («tubles» por «tubeless») | — | Ver P7 en `02_DECISIONES.md` |

---

## ⚠️ Dos chats trabajando en paralelo

Hay un chat de **estética** y hubo uno de **buscador**, ambos empujando al
mismo repo. Ya hubo que rebasar 6 commits del chat de estética sobre los del
buscador; salió limpio porque tocaban ficheros distintos, pero:

**Regla: `git pull --rebase` ANTES de empezar a trabajar, siempre.**

Riesgo real si ambos tocan `index.html` a la vez.

---

## Historial de trampas ya pisadas (no repetirlas)

1. **`curl` desde el servidor no sirve para verificar.** Se responde a sí mismo
   y salta la CDN. Provocó dar por bueno un arreglo que no lo era.
2. **La CDN tiene anti-bot.** Tras una ráfaga de `curl` devuelve 403 y un JS
   challenge en *cualquier* ruta, incluido `index.html`. Parece el sitio roto
   y no lo está. Lo que zanja la duda es **un navegador de verdad**.
3. **hPanel rechaza claves ecdsa** y el conector MCP no puede usar ed25519 →
   generar RSA 4096. Al pegarla, cuidado con los espacios.
4. **Python y Node están fuera del PATH** → `source pipeline/entorno.sh`.
5. **Assets versionados con `?v=`**: al tocar CSS o JS hay que subir el número
   en `index.html` Y en `episodios.html`, o nadie ve el cambio.
