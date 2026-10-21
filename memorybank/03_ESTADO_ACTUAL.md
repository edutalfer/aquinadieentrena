# 📍 ESTADO ACTUAL — Léeme primero

> **Actualizado:** 2026-10-02
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

## 📝 Formularios de inscripción a eventos (2026-09-26)

Estilo Google Forms, pero cortan de verdad al llegar al límite (ver D18).

- **Crear y gestionar:** panel → pestaña **Formularios** (`/admin/formularios.php`).
  Un formulario nuevo nace con Correo, Nombre y apellidos y Teléfono móvil,
  todas obligatorias. Tipos de pregunta: corta, párrafo, correo, teléfono,
  número, fecha, opción única, casillas y desplegable.
- **Enlace público:** `https://aquinadieentrena.cc/f/<código>` (10 caracteres
  aleatorios). No está enlazado en la web y lleva `noindex`. Vista previa sin
  enviar: añadir `?previa=1`.
- **Descripción con formato:** `**negrita**`, `_cursiva_`, líneas `- ` = lista,
  `[texto](https://…)`. Todo el HTML se escapa antes.
- **Piezas:** `formulario.php` (público), `admin/formularios.php` (gestión),
  `admin/_comun.php` (acceso, CSRF, estilos y pestañas de todo el panel),
  `api/_formularios.php` (lógica). Tablas `formularios` y `form_respuestas`
  en `~/datos/ane.db`. Clave de firma en `~/datos/secreto` (se crea sola).
- **Datos personales:** el formulario promete borrarlos **un mes después del
  evento**. El listado avisa de los formularios cerrados hace más de 30 días que
  aún guardan inscripciones → descargar CSV y «Borrar todas las respuestas».
  Documentado en `privacidad.html` («Inscripción a eventos»).
- **Probado en local** con PHP 8.4 (límite, envío con la última plaza ya cogida,
  lista de espera, duplicados, validación, bots, CSRF, CSV) antes de publicar.

---

## 🚴 Página de Proyectos (2026-09-25)

`proyectos.html`, enlazada en el menú y en el pie de todas las páginas y desde la
tarjeta de marcas de la portada. Todo es HTML estático con estilos propios en la
página (no toca `estilo.css`). **Fuente de las cifras: el Dossier ANE 2027.**

- **Cifras actualizadas con Metricool el 02/10/2026** (datos reales de YouTube,
  vídeo a vídeo; también las cuatro de portada, recalculadas con Metricool). Qué vídeos forman cada proyecto, cómo se sacan y lo que queda
  por aclarar (visualizaciones de ANE House): **`06_CIFRAS_PROYECTOS.md`**.
- **Mantenimiento:** cuando termine un proyecto, se pasa de «Lo que viene» a
  «Lo que ya hemos hecho» con sus cifras reales, y si repite, se le pone el
  recuadro «La última vez» a la edición siguiente.
- Los botones «Quiero patrocinar este proyecto» llevan el asunto «Propuesta de
  colaboración — <proyecto>»: el panel los cuenta como «marcas».
- ANE House 2027 (Tour, julio 2027) confirmada por Eduardo el 25/09: sin
  proyección en el dossier. Entregables: episodio post etapa diario con
  invitados y **vlogs sueltos** (no diarios, lo pidió Eduardo). Lleva las cifras
  reales de 2026. Añadir proyección cuando la haya.
- Marcas «klinpig» y «CMS» se escriben tal cual (confirmado por Eduardo).
- Pendiente: fotos (van mejor subidas desde el Mac con git que por el chat).

---

## 👕 El mono: huecos de patrocinio con ofertas por proyecto (D19, D20)

Página `/maillot` (todavía **no enlazada** desde el menú: se enlaza cuando
Eduardo lo diga).

- **Proyectos:** selector arriba. Nacen Mediterranean Epic MTB, Cape Epic 2027,
  The Traka 2027 y Swiss Epic 2027, **abiertos a ofertas**, con los diez huecos
  visibles y **sin mínimo**. En el panel se renombran, se cierran, se ocultan o
  se crean más (los nuevos nacen cerrados).
- **Gestión:** panel → pestaña **Mono** (`/admin/maillot.php?p=<proyecto>`):
  datos del proyecto; por hueco, si se ofrece, oferta mínima, subida, cierre y
  «Adjudicado a»; ofertas (validar, anular, borrar, CSV) y borrar las del
  proyecto al cerrarlo. Abajo, para toda la página: **revisar antes de que
  cuenten** (activado) y los textos.
- **Zonas:** `pecho`, `abdomen`, `espalda-alta`, `espalda-baja`, `costado-izq/der`,
  `manga-izq/der`, `culote-izq/der`. Nombre y precio en la base; la posición,
  en `assets/js/maillot/mono.js` (`ZONAS`). Para añadir una zona hay que tocar
  **las dos**: `ANE_MAILLOT_ZONAS` en `api/_maillot.php` y `ZONAS` en `mono.js`.
- **3D:** fuente en `assets/js/maillot/` (`mono.js` = forma, equipaje y calcas;
  `maillot.js` = escena, selector y formulario). La forma es una función de
  distancia; la malla (~53.000 triángulos) se calcula al cargar (~0,5 s; en
  móvil, rejilla más gruesa). **Hay que recompilar** `assets/js/maillot.min.js`
  (ver `assets/js/maillot/LEEME.md`) y subir el `?v=` en `maillot.php`.
- **Acabado (01/10/2026):** pectorales, dorsales, glúteos, cuádriceps e isquios;
  oclusión ambiental por vértice (axilas, entrepierna); costuras raglán y de
  costado, paneles de rejilla detrás del hombro, canalé; bocas de cuello, mangas
  y perneras pintadas como hueco; luz de estudio (RoomEnvironment). Se probó
  vaciar las bocas de verdad en la función de distancia y **no sirve**: la tela
  (4,5 mm) es más fina que la rejilla (7,5 mm) y sale un borde roto.
- **Ajuste con las vistas de Virklon que mandó Eduardo (01/10/2026):** escote
  que baja por delante, rejilla delante y detrás del hombro, tira del bolsillo
  en curva detrás, corte blanco/negro en curva suave (no en pico), paneles
  laterales del culotte y puños neutros (sin azul): el mono es una base neutra
  para que destaquen las marcas.
- **Con la referencia de un mono Gobik negro (01/10/2026):** muslos rectos y
  separados desde la entrepierna, cadera y glúteo más contenidos, mangas separadas y
  **negro mate** (rugosidad alta, casi sin brillo). Silueta calcada de sus fotos
  de frente y espalda: mangas separadas del cuerpo, entrepierna alta (~0,93) y
  perneras largas.
- **Tablas:** `mono_proyectos`, `mono_huecos`, `mono_pujas` (datos de contacto
  de empresas) y `ajustes`, en `~/datos/ane.db`. En `privacidad.html` (#patrocinio).
- **Probado en local** (Playwright + SwiftShader): selector, clic en calcas,
  ofertas, proyecto cerrado, panel completo.

---

## 🎙️ Estudio: gestor del podcast y de los proyectos (2026-10-20)

✅ **En producción desde el 20/10/2026.** Verificado con navegador real:
`/estudio/` pide entrada, las librerías `_*.php` dan 403, las pantallas
redirigen al formulario sin sesión y las cabeceras van con `noindex` y
`no-store`. En el servidor: PHP 8.3.35, **GD sí** (y webp), subida hasta
1536 MB, así que el límite de verdad es el nuestro (12 MB imágenes, 32 MB
archivos).

Área privada **`/estudio`**, aparte del panel `/admin` (enlazada desde sus
pestañas). Organiza la grabación y comparte el guión con el editor de vídeo sin
enseñarle nada más. Ver **D21** y `estudio/LEEME.md`.

- **Dos roles.** *Socio* (Eduardo y Cristóbal): episodios, guiones, imágenes,
  cuñas, proyectos, presupuestos y accesos. En su listado, cada episodio dice
  **qué marcas lo patrocinan** y cuántas cuñas quedan por hacer. *Editor de
  vídeo*: solo el **guión definitivo** de los episodios que se le marquen, **en
  lectura**, **cuñas de patrocinio incluidas** en su sitio (las tiene que
  montar; corregido el 21/10/2026, D23). No puede escribir nada, ni su propia
  contraseña. No hay rol de invitado: no lo necesitan.
- **La frontera es de datos, no de CSS.** Con el editor delante, los temas
  marcados «internos», las notas, la agenda de marcas y los proyectos **no se
  consultan**, los ficheros de un tema interno devuelven 404, y `episodio.php`,
  `proyectos.php`, `proyecto.php`, `marcas.php` y `accesos.php` responden 403
  (también a sus POST, aunque lleven un token válido). De cada cuña ve marca,
  tipo, texto y si está hecha: ni contactos ni dinero. Si tocas `guion.php` o
  `fichero.php`, no rompas eso.
- **Un episodio** es número, título (**opcional**: con el número basta para
  crearlo, y entonces se le llama «Episodio 49»), fecha, duración y sitio, más
  **temas en orden**; cada tema con **los enlaces** y **las imágenes y archivos** que
  haga falta (varios de cada, y se suben varios de una vez). Cada imagen lleva
  **su propia descripción**, y van juntas: foto y texto emparejados. Todo
  episodio acaba en la sección fija **«Bici o Cepo»** —parejas de foto y
  descripción, sin texto de bloque—, que no se borra ni se mueve.
- **Temas y patrocinios van en una sola lista (21/10/2026, D22):** un patrocinio
  se intercala entre dos temas con las flechas o con «Llevarlo a…», y en el
  guión sale en su sitio (rojo; verde si ya está hecho). Los temas se numeran
  1, 2, 3 saltándose las cuñas. Ya no hay sección suelta de menciones ni
  «momento». Los **enlaces** se pegan **varios de golpe, uno por línea**, con
  su nombre delante o sin él («Rider Manual https://…», «Perfil | https://…»).
  La migración (una vez, `ajustes.pod_orden_unificado`) puso las menciones que
  hubiera al principio del guión con su «momento» escrito delante del texto.
- **Acuerdos (21/10/2026, D24):** la marca se apunta en *Patrocinadores* al
  hablar con ella; al firmar, **«Firmar acuerdo»** (en su ficha o en la del
  proyecto): proyecto, cuánto pone, episodios con cuña («10-13, 20»), tipo y
  texto. Firmado, **crea los episodios que falten** y les mete la cuña. Proyecto
  **fijo «Podcast»** para patrocinios de episodios sueltos (no se borra). El
  acuerdo manda en el texto de sus cuñas pendientes; quitar un episodio quita
  su cuña **salvo si está hecha**.
- **Lista de episodios:** **Próximos** por número, con el siguiente que se graba
  resaltado, y **Pasados** plegados. **Un episodio cada domingo, sin parar: el
  56 sale el domingo 25/10/2026** (D25). Así que el número dice cuándo sale cada
  uno (57 → 1/11, 60 → 22/11…) y pasa a Pasados el lunes siguiente, tenga o no
  fecha de grabación. Si algún domingo no sale episodio, hay que mover el ancla
  (`pod_ancla_numero` y `pod_ancla_domingo` en `ajustes`, o `ANE_POD_ANCLA`).
- **Tipos de cuña (20/10/2026):** *Anuncio explícito*, *Anuncio integrado en
  sección*, *Presencia en el evento*, *Presencia en el maillot* y *Product placement*, y **se pueden marcar
  varios** (casillas; se guardan como «explicito,producto»). Los viejos se
  convirtieron solos (cuña leída y mención suelta → explícito; integrada →
  integrado; producto en pantalla → product placement), marcado en
  `ajustes.pod_tipos_v2`.
- **Notas de grabación (21/10/2026, D26):** botón **«+ Nota»** junto al título
  de cada tema (y de *Bici o Cepo*; solo socios): la nota se queda en ese tema.
  Las generales, con el de «Notas de grabación». Se guarda sin recargar. **El
  editor ve todas las notas por defecto**; la casilla es «que no la vea el
  editor», para las privadas (que salen en gris con 🔒). Las escribe
  `estudio/nota.php`; `guion.php` sigue siendo solo lectura.
- **Agenda de marcas recogida (20/10/2026):** cada marca es una línea (estado,
  dinero firmado, acuerdos, contratos, contacto y siguiente paso) que se
  despliega al pulsar; «+ Nueva marca», plegada arriba. Fuera la **fecha del
  siguiente paso** y su aviso de «seguimiento pendiente», a petición de Eduardo
  (la columna `seguimiento` sigue en la base, sin usar). **Lo mismo en la
  ficha de cada proyecto:** cada acuerdo es una línea (marca, estado, dinero,
  episodios, contratos y tipos) que se despliega, y «+ Nuevo acuerdo», plegado.
- **Proyectos sin estado «Idea» (20/10/2026):** un proyecto creado se da por en
  marcha (lo dice Eduardo). Fuera la etiqueta junto a «Abrir»; solo se ve
  «Cerrado» si se cierra. Los que estaban en «idea» pasaron a «abierto».
- **Contratos (D25):** en la ficha de la marca (y del acuerdo, en su proyecto),
  también al firmar. PDF, Word o foto, comprobados por dentro. Ficheros en
  `~/datos/estudio/contratos/`, **solo socios** (`estudio/contrato.php`; el
  editor, 403). Borrar el acuerdo no borra el contrato.
- **Ficheros:** en `~/datos/estudio/imagenes/` (fuera del repo y del docroot),
  servidos por `estudio/fichero.php` tras comprobar quién pregunta: `?f=<id>`
  lo enseña y `?f=<id>&d=1` lo descarga. **El editor se descarga todo lo que
  subamos.** Las imágenes (JPG, PNG, WEBP, GIF, 12 MB) se redibujan con GD y se
  reducen a 2000 px: así se valida que lo sean y **se borran los metadatos**
  (incluida la ubicación). Los demás archivos (PDF, audio, texto, subtítulos,
  hojas, zip… 32 MB) se guardan tal cual y salen **siempre como adjunto** y
  como octet-stream, nunca inline. Para vídeo en bruto, Drive. Manda el límite
  de PHP del hosting, que se sube en hPanel si hace falta.
- **Entrada propia:** formulario y cookie firmada con `~/datos/secreto`, 30
  días. Nada de sesiones de PHP (el recolector del hosting las mata a los 24
  min). Contraseñas generadas en el panel y **enseñadas una vez**; cambiarlas o
  desactivar a alguien cierra sus sesiones. 8 fallos = 15 min de espera.
- **El primer acceso se crea por SSH**, no por web:
  `php -f estudio/_alta.php eduardo socio "Eduardo"`. El alta por web solo se
  abre si además existe la señal `~/datos/estudio_alta` (y se borra al usarla):
  sin eso, la URL recién publicada sería un «créate el acceso de socio» para el
  primer bot que pasara.
- **Piezas:** `estudio/` (entrar, index, episodio, guion, fichero, proyectos,
  proyecto, marcas, accesos, `_plantilla.php`, `_comun.php`) y
  `api/_estudio.php`. Tablas `pod_*` en `~/datos/ane.db`.
- **Datos personales:** contactos de empresas (`pod_marcas`, `pod_proy_marcas`)
  y los accesos del editor. No se publica nada de esto. **Pendiente de decidir
  con Eduardo** si hace falta una línea en `privacidad.html`: hoy son datos
  internos, no recogidos del público.
- **Probado en local** con PHP 8.2 y navegador: alta, entrada, permisos del
  editor (403 en las pantallas de socio, 403 en el guión no compartido, 404 en
  el fichero de un tema interno, cero fugas de marcas o importes en su HTML),
  orden de temas y menciones, varios enlaces por tema, subida de dos imágenes a
  la vez (reescalado de 2600 a 2000 px), subida de PDF, un `.sh` rechazado,
  descarga con `Content-Disposition: attachment` y el nombre original, CSRF,
  origen ajeno, URL con `javascript:` rechazada y HTML escapado. Probadas
  también las dos **migraciones** (el enlace único del tema y la tabla vieja de
  imágenes).
- **Para verlo en local:** `ANE_DATOS=/tmp/ane-demo php -f estudio/_demo.php`
  y `ANE_DATOS=/tmp/ane-demo php -S 127.0.0.1:8787 -t .`

---

## 🏔️ App Cape Epic 2027: /capeepic (actualizado 2026-10-02)

App web privada (PWA, funciona sin cobertura) para Eduardo, Cristóbal y acompañantes (15–28 mar 2027: semana previa en Stellenbosch, casa Twyfeling en Wellington del 21 al 29).
- **Simplificada (2026-10-02):** fuera Estamos aquí/posiciones (van con WhatsApp y GPS oficial) y todo lo de foto/vídeo. Pestañas: Hoy, Mapa, Etapas, Más. En Etapas: horario, avituallamientos y Puntos de interés (colección spots: nombre, tipo, enlace de Google Maps, km, notas).

- **Acceso:** toda la carpeta con contraseña (Basic Auth en `capeepic/.htaccess`). Usuario `ane`.
  Fichero de claves: `~/domains/aquinadieentrena.cc/.capeepic.htpasswd` (fuera de public_html y del repo).
  OJO: en `~/datos/` NO funciona, el servidor web no puede leerlo.
  Cambiar clave: `printf 'ane:%s\n' "$(openssl passwd -apr1 'NUEVA')" > ~/domains/aquinadieentrena.cc/.capeepic.htpasswd`
- **Código (ES modules, sin build):** `index.html`, `css/app.css`, `js/app.js` (router), `js/store.js` (sincronización + cola sin conexión),
  `js/data.js` (etapas, días, avituallamientos con km oficial), `js/views/*.js` (un módulo por función, se registran solos en las vistas).
  Leaflet en `vendor/leaflet/` (servido desde aquí para el modo sin cobertura).
- **Datos compartidos:** `api.php` → `~/datos/capeepic/<coleccion>.json` (casas, plan, ajustes, equipo, checkins, spots, monos, listas, hechos, tomas, marcas, gastos).
- **Tracks:** eliminados (los orientativos salían mal y confundían). Cuando salgan los GPX oficiales se pueden volver a añadir al mapa.
- **Avituallamientos:** km leídos de los perfiles oficiales (±0,5 km), en `WATER` de `js/data.js`. Revisar con el Rider Manual (feb 2027).
- **Service worker:** al cambiar ficheros, subir la versión `V` en `sw.js` (y `?v=` de app.css/app.js en index.html).

---

## Dónde está cada cosa

- **Web pública:** https://aquinadieentrena.cc
- **Repo = docroot:** `~/domains/aquinadieentrena.cc/public_html`
  ⚠️ NO es `~/public_html`
- **GitHub:** `github.com/edutalfer/aquinadieentrena` (público)
- **Copia local de Eduardo:** `/Users/eduardotalavera/Documents/Proyectos-IA/aquinadieentrena`
- **Servidor:** `46.202.172.3` · puerto `65002` · usuario `u527801093`
ℹ️ **20/10/2026:** la clave del Mac (`claude-ane-2026`, termina en
`…TY3qlYuCHw==`) dejó de funcionar y Eduardo la volvió a dar de alta en hPanel
ese mismo día. Si vuelve a salir `Permission denied (publickey,password)`, es
eso: hay que pegarla otra vez en hPanel → Avanzado → Acceso SSH.

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
7. **`Referrer-Policy: no-referrer` en el panel rompe los POST en local**: con esa
   política Chrome manda `Origin: null` y la comprobación de origen del CSRF los
   rechaza (403). En producción no pasaba porque el `.htaccess` pisa la cabecera
   con `strict-origin-when-cross-origin`, pero `php -S` en local no lee el
   `.htaccess`. Ahora el panel ya manda `same-origin`. **Prueba el panel con un
   navegador** (Playwright vale), no solo con `curl`, que no manda `Origin`.

8. **SQLite reutiliza el número de la última fila borrada** (las tablas no
   llevan `AUTOINCREMENT`). Pasó el 20/10/2026 en «Bici o Cepo»: se borró la
   foto 7, la nueva volvió a ser la 7, su dirección era idéntica
   (`fichero.php?f=7`) y el navegador, que la tenía guardada un día, enseñaba
   la vieja. Ahora las direcciones llevan una marca del fichero
   (`ane_pod_url_fichero()`: `?f=7&v=…`) y solo esas se guardan en caché.
   **Si sirves algo por id con caché, mete una marca así.**