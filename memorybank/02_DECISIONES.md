# 🧭 DECISIONES — Registro y preguntas abiertas

> **Última actualización:** 2026-08-17

---

## ✅ DECISIONES TOMADAS

| # | Fecha | Decisión | Motivo |
|---|---|---|---|
| D1 | 2026-08-17 | Frontend y backend separados | El backend v1 es un pipeline de datos, no un servidor |
| D2 | 2026-08-17 | Todo en GitHub, incluido `memorybank/` | Memoria persistente entre sesiones |
| D3 | 2026-08-17 | Buscador = timelines + transcripciones, con deep links `&t=Xs` | Objetivo core |
| D4 | 2026-08-17 | JSON canónico por episodio | Independiente del motor de búsqueda → sin re-trabajo si migramos |
| D5 | 2026-08-17 | Transcripciones vía subtítulos automáticos de YouTube | Cobertura inmediata; Whisper más adelante si la calidad duele |
| D6 | 2026-08-17 | Reutilizar la infraestructura existente, no duplicar nada | Web, dominio, repo y hosting ya estaban montados |
| D7 | 2026-08-17 | Tema "gorras" cerrado sin acción | Eduardo decide que no tiene importancia |
| D8 | 2026-08-17 | La web NO se mueve a `frontend/`: la raíz sigue siendo el sitio y se añaden `pipeline/`, `data/transcripciones/` y `memorybank/`, bloqueados por `.htaccess` | El repo ES el docroot; moverlo rompería el deploy |
| D9 | 2026-08-17 | Clave SSH RSA 4096 (`claude-ane-2026`) | hPanel rechaza ecdsa; la ed25519 anterior dejó de ser usable por el conector |
| D10 | 2026-08-17 | El paso de descarga del pipeline se ejecuta **en el Mac**, no en el servidor | YouTube bloquea la IP del hosting (HTTP 429 en todos los player_client) |
| D11 | 2026-08-17 | Índice de búsqueda en cliente (sin API) | Suficiente para el volumen actual; se revisará si el índice se dispara de tamaño |

---

## ❓ PREGUNTAS ABIERTAS

### P2 — Motor de búsqueda a futuro
Con ~5 episodios el índice en cliente va sobrado. Revisar cuando haya 50+:
si `indice_busqueda.json` pasa de ~2-3 MB, trocearlo por año o mover a API.

### P5 — Formularios
Bici o Cepo y marcas usan `mailto:`. Valorar Web3Forms/Formspree para que no
dependa del cliente de correo del visitante.

### P6 — Sección Proyectos
Está en el objetivo pero no existe todavía. Falta definir qué proyectos y con
qué estructura.

---

## 🖥️ ENTORNO DE TRABAJO

| Qué | Dónde |
|---|---|
| Copia local de Eduardo | `/Users/eduardotalavera/Documents/Proyectos-IA` (Mac) |
| Repo GitHub | `github.com/edutalfer/aquinadieentrena` (público) |
| Servidor | `46.202.172.3` · puerto `65002` · usuario `u527801093` |
| Docroot (= repo) | `~/domains/aquinadieentrena.cc/public_html` ⚠️ **no** `~/public_html` |
| Repo bare auxiliar | `~/ane.git` |
| Python / Node en el servidor | Fuera del PATH: `/opt/alt/python311/bin` y `/opt/alt/alt-nodejs22/root/usr/bin` (ver `pipeline/entorno.sh`) |
| Autenticación SSH | Clave RSA `claude-ane-2026` en hPanel → Avanzado → Acceso SSH |
| Flujo de deploy | Editar → commit → push a GitHub; en el servidor `git pull` |

---

## 📋 HISTÓRICO DE SESIONES

| Fecha | Qué se hizo |
|---|---|
| Previas | Web v1 diseñada, desplegada y subida a GitHub |
| 2026-08-17 | memorybank + mega plan + esqueleto. Verificado el repo real y la CDN. Pipeline de transcripciones escrito y probado. Buscador ampliado a transcripciones. SSH restablecido (RSA). Detectado el bloqueo de YouTube a la IP del hosting |
