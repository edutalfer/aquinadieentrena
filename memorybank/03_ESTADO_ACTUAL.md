# 📍 ESTADO ACTUAL — Léeme primero

> **Actualizado:** 2026-08-17
> Si empiezas un chat nuevo sobre la web de ANE, **este es el punto de partida**.
> Después lee `00_MEGAPLAN_WEB_ANE.md` (objetivo), `01_ESQUELETO_WEB.md`
> (arquitectura) y `02_DECISIONES.md` (por qué está hecho así).

---

## Lo esencial en diez líneas

- La web **está publicada y funcionando** en https://aquinadieentrena.cc
- El repo **es** el docroot: `~/domains/aquinadieentrena.cc/public_html`
  ⚠️ NO es `~/public_html`. Ese error ya costó una sesión.
- Repo: `github.com/edutalfer/aquinadieentrena` (público)
- El core del proyecto es un **buscador de temas** que encuentra el minuto
  exacto de cada episodio. Ya funciona con datos reales: **los 46 episodios,
  8.675 segmentos** de transcripción indexados (17/08/2026).
- Copia local de Eduardo: `/Users/eduardotalavera/Documents/Proyectos-IA/aquinadieentrena`

---

## Cómo conectarse al servidor

```
host 46.202.172.3 · puerto 65002 · usuario u527801093
```

Autenticación por clave. **hPanel rechaza claves ecdsa y el conector MCP no
puede usar ed25519**: hay que generar RSA 4096 y pegar la pública en
hPanel → plan de hosting → Avanzado → Acceso SSH.
⚠️ Al pegarla, los espacios se pierden con facilidad: debe quedar
`ssh-rsa` + espacio + clave + espacio + comentario.

Python y Node existen pero **fuera del PATH**. Siempre:
```bash
source pipeline/entorno.sh   # /opt/alt/python311/bin y /opt/alt/alt-nodejs22/...
```

---

## ⚠️ Trampa de la CDN — leer antes de "verificar" nada

**`curl` desde el propio servidor NO sirve para comprobar qué ve el público.**
El servidor se responde a sí mismo y salta la CDN. Esto ya provocó un
diagnóstico equivocado: se dio por bueno un arreglo que no lo era.

Para comprobar de verdad qué se sirve:
```bash
IP=$(dig +short aquinadieentrena.cc @8.8.8.8 | head -1)
curl -s https://aquinadieentrena.cc/ --resolve "aquinadieentrena.cc:443:$IP" | grep "loQueSea"
```

⚠️ **Segunda trampa, encontrada el 18/08/2026: la CDN tiene anti-bot.** Tras
unas pocas peticiones seguidas con `curl`, deja de servir los ficheros y
devuelve **403 con un JS challenge** («Checking your browser before
accessing»), sea cual sea la ruta. Parecía que el buscador estaba roto en
producción y lo que estaba bloqueado era el `curl`. **La verificación que vale
es con un navegador real**, que pasa el challenge y es lo que usa el público.

Estado actual de la caché:
- El `.htaccess` ya **no** cachea HTML (`no-cache, must-revalidate`)
- CSS y JS se referencian con `?v=AAAAMMDD` — **al cambiarlos hay que subir
  ese número en `index.html` y `episodios.html`**, si no, nadie ve el cambio
- La CDN está en **Modo de desarrollo** (hPanel → Rendimiento → CDN).
  Se activó para trabajar el diseño. Desactivarlo cuando se cierre el rediseño.

---

## Flujo de trabajo

```
Claude edita en el servidor → git commit → git push  (ya desplegado: el repo es el docroot)
Eduardo edita en su Mac     → git push → Claude hace git pull en el servidor
```

Si algo se rompe: `git revert` y atrás en un segundo.

---

## Estado de las dos líneas de trabajo abiertas

### 🔍 Buscador (chat dedicado)
✅ **Terminado y en producción** (18/08/2026): los 46 episodios, 8.675
segmentos y 308 bloques de timeline, verificados en https://aquinadieentrena.cc
con un navegador. Solo quedan dos episodios sin timeline, porque no lo tienen
en ningún sitio. Ver `04_BRIEF_BUSCADOR.md`

### 🎨 Estética (chat dedicado)
Ver `05_BRIEF_ESTETICA.md`
