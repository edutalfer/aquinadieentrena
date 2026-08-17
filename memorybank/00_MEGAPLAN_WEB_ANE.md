# 🎯 MEGA PLAN — WEB AQUÍ NADIE ENTRENA

> **Última actualización:** 2026-08-17
> **Estado global:** Fase 3 — Buscador (a falta de datos)
> Fuente de verdad del proyecto. Se actualiza en cada sesión.

---

## OBJETIVO FINAL

Web sencilla y funcional en `aquinadieentrena.cc` cuyo **core es un buscador de
temas** tratados en los episodios.

El buscador NO se limita a los timelines descriptivos: busca **dentro de las
transcripciones completas**, y devuelve episodio + **momento exacto** (deep link
`&t=Xs`) + fragmento de contexto.

Alrededor de ese core: proyectos, formularios (Bici o Cepo y marcas),
newsletter y, en el futuro, tienda de merch.

---

## PRINCIPIOS

1. Frontend y backend separados (el "backend" v1 es un pipeline de datos, no un servidor)
2. Todo versionado en GitHub — el repo es la memoria entre sesiones
3. Sencillez primero: cada fase entrega algo usable
4. Datos reproducibles con un comando
5. `memorybank/` vive en el repo y se actualiza al final de cada sesión

---

## ROADMAP

### ✅ Fase -1 — Infraestructura
- [x] Web v1 diseñada, aprobada y desplegada
- [x] Dominio `aquinadieentrena.cc` en Hostinger
- [x] Repo GitHub + hosting operativos (`public_html` = repo)

### ✅ Fase 0 — Fundamentos
- [x] `memorybank/` con plan, esqueleto y decisiones
- [x] Fuente de transcripciones decidida: subtítulos de YouTube (D5)
- [x] Estructura backend añadida sin romper el deploy (D8)
- [x] SSH restablecido con clave RSA (D9)

### ✅ Fase 1 — Web base
- [x] Home con buscador, episodios, hosts, newsletter, Bici o Cepo y marcas
- [ ] Sección Proyectos (no existe aún)
- [ ] Valorar formularios reales en vez de `mailto:`

### 🔄 Fase 2 — Pipeline de transcripciones
- [x] Scripts escritos, probados y documentados (`pipeline/`)
- [x] yt-dlp instalado en el servidor
- [ ] ⚠️ **Descargar los subtítulos DESDE EL MAC** — el servidor está vetado por YouTube (D10)
- [ ] Commit de `data/transcripciones/` + `data/indice_busqueda.json`

### 🔄 Fase 3 — Buscador (el core)
- [x] Capa de búsqueda en transcripciones integrada en `app.js`
- [x] Carga perezosa del índice, prioridad al timeline, fragmento con contexto
- [ ] Verificar en producción con datos reales

### Fase 4 — Buscador v2
- [ ] Búsqueda semántica (encontrar "ruedas rápidas" cuando se dijo "material aero")
- [ ] Analytics de búsquedas = ideas para episodios

### Fase 5 — Tienda merch (sin fecha)

---

## AUTOMATIZACIÓN SEMANAL

Cada domingo, en el Mac:

```bash
# tras añadir el episodio a data/episodios.js
./pipeline/pipeline.sh <youtubeId>
git add data/ && git commit -m "Transcripción ep. X" && git push
```

Y en el servidor: `git pull`. Publicado.

---

## BLOQUEANTES

| Bloqueante | Afecta a | Acción |
|---|---|---|
| YouTube bloquea la IP del servidor | Fase 2 | Descargar desde el Mac (ver `pipeline/LEEME.md`) |
| CDN de Hostinger sirviendo versión antigua | Todo lo publicado | hPanel → Rendimiento → CDN → Purge cache |
