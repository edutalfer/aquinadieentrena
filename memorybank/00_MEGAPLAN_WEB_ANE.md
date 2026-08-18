# 🎯 MEGA PLAN — WEB AQUÍ NADIE ENTRENA

> **Última actualización:** 2026-08-18
> **Estado global:** Fases 2 y 3 CUMPLIDAS. En curso: Fase 1 pendientes + rediseño estetico
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

### ✅ Fase 2 — Pipeline de transcripciones — CUMPLIDA
- [x] Scripts escritos, probados y documentados (`pipeline/`)
- [x] Subtítulos descargados desde el Mac (el servidor está vetado por YouTube, D10)
- [x] 46 episodios transcritos y commiteados
- [x] Timelines completados a partir de capturas de Eduardo (308 bloques)

### ✅ Fase 3 — Buscador (el core) — CUMPLIDA 🏆
- [x] Capa de búsqueda en transcripciones integrada en `app.js`
- [x] Carga perezosa del índice, prioridad al timeline, fragmento con contexto
- [x] **Verificado en producción con navegador real** (2026-08-18)
- [x] 46 episodios · 8.675 segmentos · índice de 2,07 MB por la red

**🏆 OBJETIVO CORE DEL PROYECTO CUMPLIDO.**

### Fase 4 — Buscador v2 (cuando duela, no antes)
- [ ] **Vigilar el peso del índice.** Con 46 episodios: 2,07 MB por la red.
      A ~100 episodios rondaría 4-5 MB. Solución prevista: trocear por año
      o pasar a API. No urgente, pero que no pille por sorpresa.
- [ ] Jerga mal transcrita: Whisper si las analíticas muestran que la gente
      busca términos técnicos y no encuentra (ver P7)
- [ ] Búsqueda semántica ("ruedas rápidas" → "material aero")
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

Ninguno bloquea el core. Pendientes menores en `03_ESTADO_ACTUAL.md`:
desactivar el Modo desarrollo de la CDN al cerrar el rediseño, dos episodios
sin timeline y la jerga mal transcrita.
