# 📍 ESTADO ACTUAL — Léeme primero

> **Actualizado:** 2026-09-23
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

## 📊 Estadísticas privadas del buscador (2026-09-23)

✅ **Verificado en producción con navegador real** (23/09/2026): búsqueda,
clic y panel funcionan a través de la CDN.

Panel en **https://aquinadieentrena.cc/admin** — usuario y contraseña del
navegador. Qué se busca, qué no encuentra nada, qué solo aparece en lo hablado,
qué minuto se abre y búsquedas por día. Exporta CSV. Ver D16 y D17.
También cuenta los **botones de correo** (tabla `contactos`, desde 23/09):
«Enviar mi bici», «Enviar propuesta», el «mándanoslo» del buscador sin
resultado y el «Contacto» del pie. Se distinguen por el asunto del `mailto:`
en `stats.js`: **si cambias el asunto de un botón, revisa esa clasificación**.
Son clics, no correos enviados.
El «mándanoslo» del buscador sin resultado escribe a
`entrenaaquinadie@gmail.com` y no a `hola@`: **es a propósito**, lo decidió
Eduardo el 24/09/2026. No proponer cambiarlo.

| Pieza | Dónde |
|---|---|
| Registro en el navegador | `assets/js/stats.js` (lo llama `app.js` tras pintar resultados) |
| Endpoint | `api/stats.php` (POST, responde 204) |
| Librería común | `api/_bd.php` — bloqueada por `.htaccess` |
| Panel | `admin/index.php` |
| **Base de datos** | `~/datos/ane.db` (SQLite, se crea sola) — **fuera del repo** |
| **Credenciales del panel** | `~/datos/admin.php` (usuario + hash bcrypt) — **fuera del repo** |

Backup: copiar `~/datos/ane.db`. Cambiar la contraseña: regenerar el hash con
`php -r 'echo password_hash("NUEVA", PASSWORD_DEFAULT);'` y pegarlo en
`~/datos/admin.php`. Si falta ese fichero, el panel no deja entrar a nadie.

Las búsquedas **solo se registran con el índice de transcripciones ya
cargado** (si no, contarían como «sin resultado» búsquedas que sí lo tienen).

---

## 🚴 Página de Proyectos (2026-09-25)

`proyectos.html`, enlazada en el menú y en el pie de todas las páginas y desde la
tarjeta de marcas de la portada. Todo es HTML estático con estilos propios en la
página (no toca `estilo.css`). **Fuente de las cifras: el Dossier ANE 2027.**

- Donde el dossier se contradecía se usaron las cifras de la ficha de cada
  proyecto: Cape Epic 2026 **231.554** visualizaciones y **25.773** de media;
  The Traka 2026 **25.343**. (Las diapositivas de 2027 dicen 231.515, 25.724 y
  25.366: conviene unificar el dossier.)
- **Mantenimiento:** cuando termine un proyecto, se pasa de «Lo que viene» a
  «Lo que ya hemos hecho» con sus cifras reales, y si repite, se le pone el
  recuadro «La última vez» a la edición siguiente.
- Los botones «Quiero patrocinar este proyecto» llevan el asunto «Propuesta de
  colaboración — <proyecto>»: el panel los cuenta como «marcas».
- ANE House 2027 (Tour, julio 2027) confirmada por Eduardo el 25/09: sin
  proyección ni entregables cerrados en el dossier; la ficha usa el formato de
  2026 y sus cifras reales. Añadir proyección cuando la haya.
- Pendiente: fotos (van mejor subidas desde el Mac con git que por el chat).

---

## Dónde está cada cosa

- **Web pública:** https://aquinadieentrena.cc
- **Repo = docroot:** `~/domains/aquinadieentrena.cc/public_html`
  ⚠️ NO es `~/public_html`
- **GitHub:** `github.com/edutalfer/aquinadieentrena` (público)
- **Copia local de Eduardo:** `/Users/eduardotalavera/Documents/Proyectos-IA/aquinadieentrena`
- **Servidor:** `46.202.172.3` · puerto `65002` · usuario `u527801093`
- **Clave SSH (24/09/2026):** `claude-ane-3` se **borró** de hPanel en una
  limpieza. La pública del Mac (privada en `~/.ssh/claude-ane-2026`) se volvió
  a dar de alta el mismo día como **`claude-ane-mac`**: es la clave fija del Mac
  (termina en `…TY3qlYuCHw==`). Ese día se retiró de `~/.ssh/authorized_keys` una
  clave huérfana que hPanel ya no mostraba. Comprobar con:
  `awk '/^ssh-/{print substr($2,length($2)-11), $3}' ~/.ssh/authorized_keys`
  `claude-ane-5` es la de un chat de claude.ai del 23/09: su privada ya no
  existe fuera de ese chat. Los chats de claude.ai generan su propia RSA 4096
  y Eduardo la da de alta; se pueden borrar cuando el chat termina.

---

## 🔴 Pendientes que NO son código

| Qué | Quién | Nota |
|---|---|---|
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
6. **Altas de episodio sin subir el `?v=`**: las del 6, 13 y 20/09/2026 solo
   tocaron `data/episodios.js` y el HTML siguió en `?v=20260831`. Arreglado el
   23/09. Tras cada alta, `git diff --stat` tiene que mostrar también
   `index.html` y `episodios.html`; si no, súbelo a mano.
