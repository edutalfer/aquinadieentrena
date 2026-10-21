# Estudio ANE — gestor de episodios y proyectos

Área privada en `/estudio`. Organiza la grabación del podcast (fechas, temas,
guión, imágenes, menciones de patrocinio) y los proyectos con sus
patrocinadores y su presupuesto. Y permite **compartir el guión definitivo de
un episodio con el editor de vídeo**, en lectura, sin enseñarle nada más.

No está enlazada desde la web pública y va con `noindex`.

---

## Dos permisos y una frontera

| | Socio (Eduardo, Cristóbal) | Editor de vídeo |
|---|---|---|
| Episodios y guiones | Todos, y los edita | El guión definitivo de los que se le marquen, **solo lectura** |
| Enlaces, imágenes y archivos del guión | Sí | Sí, y se los descarga |
| Cuñas de patrocinio del episodio, en su sitio | Sí | **Sí**: las tiene que montar |
| Temas marcados «internos» | Sí | **No** |
| Notas internas del episodio | Sí | **No** |
| Agenda de patrocinadores (contactos, estados, importes) | Sí | **No** |
| Proyectos y presupuestos | Sí | **No** |
| Accesos | Sí | **No** (ni su propia contraseña: se la cambia un socio) |

El editor **no puede escribir nada**: todas las pantallas que guardan algo
exigen ser socio y le responden 403 aunque mande un token CSRF válido, y las
dos que sí puede abrir (`guion.php` y `fichero.php`) no atienden POST.

La frontera no es cosmética: cuando mira el editor, los temas internos, las
notas, la agenda de marcas y los proyectos **no se leen de la base**, los
ficheros de un tema interno devuelven 404 y las pantallas de socio responden
403 aunque teclee la URL a mano. Lo que sí ve de cada cuña es marca, tipo, qué
hay que decir y si está hecha: ni contactos ni dinero, que viven en otras
tablas. Si tocas `guion.php` o `fichero.php`, respeta eso.

## El guión

Un episodio es **número, título, fecha, duración y sitio**, más una lista de
**temas en orden**. El **título no es obligatorio**: con el número basta para
darlo de alta, y mientras no tenga título se le llama «Episodio 49» en todas
partes (`ane_pod_etiqueta()`). Lo que no puede ser es no poner ninguna de las
dos cosas. Cada tema lleva título, lo que se cuenta, **los enlaces que
hagan falta** y **las imágenes y archivos** que quieras: de las dos cosas,
tantas como necesites, y se pueden subir varias de una vez.

**Temas y patrocinios van en la misma lista.** Un patrocinio (la cuña de una
marca) se mete entre dos temas igual que se ordena un tema: con las flechas o
con «Llevarlo a…». En el guión sale en su sitio, en rojo (verde cuando ya está
hecho), y los temas se numeran 1, 2, 3 saltándose las cuñas. Por dentro, las
dos tablas (`pod_bloques` y `pod_menciones`) comparten la columna `orden`: lo
manejan `ane_pod_piezas()`, `ane_pod_mueve()` y `ane_pod_coloca()`, que
reescriben 10, 20, 30… cada vez para que no haya empates. Ver D22.

Los **enlaces** de un tema se pegan **varios de golpe, uno por línea**: la
dirección sola, o con su nombre delante («Rider Manual https://…»,
«Perfil | https://…»). Una línea que no parezca un enlace se queda fuera y se
avisa. Luego cada uno se puede retocar o borrar en su fila.

## Marcas, acuerdos, proyectos y episodios

1. Al **hablar** con una marca, se apunta en *Patrocinadores* (contacto, estado,
   siguiente paso).
2. Al **firmar**, «Firmar acuerdo» en su ficha (o «Nuevo acuerdo» en la ficha
   del proyecto): proyecto, cuánto pone, **episodios con cuña** («10-13, 20»),
   tipo y qué hay que decir. Tipos: anuncio explícito, anuncio integrado en
   sección, presencia en el evento, presencia en el maillot y product placement; **se pueden marcar
   varios** (`ANE_POD_MENCION_TIPOS`, guardados como «explicito,producto»).
3. Firmado (confirmado o facturado), el acuerdo **crea los episodios que
   falten** («Episodio 12») y mete su cuña en cada uno, al principio del guión.
   La marca pasa a «confirmado» y el dinero suma en el proyecto.

El proyecto **«Podcast»** es fijo (`pod_proyectos.fijo = 1`): siempre existe,
va el primero y no se borra. Ahí van las marcas que patrocinan episodios sueltos.

Reglas para que acuerdo y episodios no se descuadren (`ane_pod_sincroniza_acuerdo()`):

- El acuerdo **manda** en el tipo y el texto de sus cuñas pendientes; en el
  episodio solo se marca «hecha». Las hechas no se tocan nunca.
- Quitar un episodio del acuerdo (o descartarlo) quita su cuña **salvo si ya
  está hecha**. Borrar el acuerdo deja las hechas como cuñas sueltas.
- Quitar a mano una cuña de acuerdo, borrar el episodio o cambiarle el número
  **corrige la lista del acuerdo**, para que al guardarlo no resuciten.
- Nunca se borran episodios solos: pueden tener guión.

La lista de episodios va en **Próximos** (por número; el siguiente que se graba,
resaltado) y **Pasados** (plegados).

**Calendario.** Sale un episodio cada domingo, sin parar: el **56 sale el
domingo 25/10/2026**. Así que el número ya dice cuándo sale cada uno
(`ane_pod_sale()`), y pasa a Pasados el lunes siguiente (`ane_pod_ya_salio()`),
tenga o no fecha de grabación. Un episodio sin número usa el domingo de la
semana en que se graba. Si algún domingo no sale episodio, se mueve el ancla:
`pod_ancla_numero` y `pod_ancla_domingo` en la tabla `ajustes` (por defecto,
`ANE_POD_ANCLA = [56, '2026-10-25']`).

## Contratos

Cada marca guarda sus contratos firmados: en su ficha de *Patrocinadores* (se
elige de qué acuerdo es), al firmar el acuerdo, o en la tarjeta del acuerdo
dentro del proyecto. PDF, Word (docx, doc, odt) o foto (jpg, png), hasta 32 MB,
**comprobados por dentro**: un fichero renombrado no entra.

- Viven en `~/datos/estudio/contratos/` y solo los sirve `estudio/contrato.php`,
  **solo para socios** (el editor, 403). El PDF se puede ver en el navegador;
  lo demás solo se descarga.
- Borrar un acuerdo no borra su contrato: se queda en la marca. Borrar la marca
  borra sus contratos sueltos; los de un acuerdo siguen con el acuerdo.
- Backup: además de `~/datos/ane.db`, las carpetas `~/datos/estudio/imagenes/`
  y `~/datos/estudio/contratos/`.

## Notas de grabación

En el guión, cada tema (y *Bici o Cepo*) tiene un botón **«+ Nota»** junto a
su título, solo para socios: estás en ese tema, lo pulsas, escribes (y pegas un
enlace si hace falta) y la nota **se queda en ese tema**, sin elegir nada. Lo
que no va con ningún tema, con el «+ Nota» de «Notas de grabación», al final.
Se guarda **sin recargar** la página, para no perder el sitio.

- Las escribe `estudio/nota.php`, **solo socios**: `guion.php` lo ve el editor y
  sigue sin atender POST. Sin JavaScript, el formulario funciona igual.
- **Por defecto, las notas las ve el editor.** La casilla es **«que no la vea
  el editor»**, para lo que es solo vuestro (sale en gris, con 🔒). El editor
  nunca ve las privadas ni las de un tema interno.
- Al borrar un tema, sus notas pasan a las generales. El editor del episodio
  avisa de cuántas hay, para repasarlas después de grabar.

## Bici o Cepo

Todo episodio acaba en una sección fija, **Bici o Cepo**, que no se borra ni se
mueve: **cada foto con su descripción al lado**, tantas parejas como haga
falta. Ahí no hay texto de bloque: lo que se cuenta va en la descripción de
cada foto. En los demás temas, las imágenes funcionan igual.

El editor de vídeo **puede descargarse todo lo que subamos**: cada imagen tiene
su botón de descargar (baja el original guardado, no la miniatura) y los
archivos salen como lista de descargas.

---

## Piezas

| Fichero | Qué es |
|---|---|
| `estudio/entrar.php` | Formulario de entrada y salida. Si no hay nadie dado de alta, el primero se crea como socio |
| `estudio/index.php` | Episodios (socio) · Mis guiones (invitado) |
| `estudio/episodio.php` | Edición (solo socios): datos, temas con enlaces e imágenes, cuñas y quién lo ve |
| `estudio/guion.php` | El guión. Lo que ve el editor de vídeo, y la hoja de grabación del socio (imprimible) |
| `estudio/fichero.php` | Enseña o descarga un fichero tras comprobar quién pregunta |
| `estudio/proyectos.php` · `proyecto.php` | Proyectos, patrocinadores y presupuesto |
| `estudio/marcas.php` | Agenda de patrocinadores: estado, siguiente paso y dónde aparece cada marca |
| `estudio/accesos.php` | Usuarios, contraseñas y qué guión ve cada editor |
| `estudio/_plantilla.php` | Sesión (cookie firmada) y estilos |
| `estudio/_comun.php` | La puerta: deja listos `$bd`, `$yo_pod` y `$es_socio` |
| `api/_estudio.php` | Tablas `pod_*` y toda la lógica |
| `estudio/_demo.php` | Datos de demostración para mirarlo en local |

Los `_*.php` no se sirven nunca: los bloquea el `.htaccess`.

---

## Entrar la primera vez

El primer acceso se crea **desde el servidor**, no por web:

```bash
source pipeline/entorno.sh
php -f estudio/_alta.php eduardo socio "Eduardo"
```

Escupe la contraseña una vez. Lo mismo para Cristóbal. A partir de ahí, los
demás accesos se crean desde la pestaña **Accesos**, y la contraseña **se
genera sola y se enseña una vez** (en la base solo queda el hash de bcrypt).

El alta por web existe, pero solo se abre si la base no tiene usuarios **y**
existe la señal `~/datos/estudio_alta` (`touch` desde el servidor; se borra
sola al usarla). Sin eso, una URL recién publicada sería un «créate el acceso
de socio» para el primer bot que pasara.

- La sesión es una cookie firmada con `~/datos/secreto`, válida 30 días.
- Cambiar la contraseña de alguien **cierra sus sesiones en todos los sitios**.
  Desactivarlo, también.
- Tras 8 intentos fallidos, ese usuario queda frenado un cuarto de hora.

---

## Verlo en local

```bash
ANE_DATOS=/tmp/ane-demo php -f estudio/_demo.php     # datos de muestra
ANE_DATOS=/tmp/ane-demo php -S 127.0.0.1:8787 -t .   # y abre /estudio/
```

Usuarios de la demo: `eduardo` y `cristobal` (socios), `jose.edicion` (editor
de vídeo, solo ve el guión de la creatina). Contraseña de los tres:
`demo-estudio-2026`.

`_demo.php` **borra y reescribe** las tablas `pod_*`, así que se niega a correr
sin `ANE_DATOS` (sin esa variable iría a `~/datos`, que es la base de verdad) y
también si lo llama la web.

⚠️ Pruébalo con un navegador, no solo con `curl`: en local, `php -S` no lee el
`.htaccess` y el panel ya se pisó una vez con la cabecera `Referrer-Policy`
(ver trampa 7 en `memorybank/03_ESTADO_ACTUAL.md`).

---

## En el móvil

Está pensado para usarse con el teléfono en la mano: una columna, campos a
ancho completo, botones para dedos y campos de 16 px (si son más pequeños,
Safari hace zoom al tocarlos y descoloca la página).

Dos cosas que conviene saber si tocas los estilos:

- Varias rejillas llevan su reparto en el atributo `style` del propio HTML
  (`1fr 170px` para el orden de los temas, etc.). El atributo gana a cualquier
  regla, así que el móvil las aplana con `.campos { grid-template-columns: 1fr
  !important }`. Si añades otra rejilla con `style`, ya está cubierta.
- En `.subir`, el `flex: 1 1 230px` de escritorio se lee como **altura**
  cuando la caja pasa a columna, y los campos salen gigantes. Por eso el móvil
  les pone `flex: 0 0 auto`. Ojo con esto al añadir campos ahí.

Las tablas con muchas columnas o no existen (los patrocinadores de un proyecto
son tarjetas) o se apilan (las menciones del guión) o se pueden empujar de
lado (`.tabla-ancha`).

---

## Datos

Todo vive en `~/datos/ane.db`, **fuera del repo** (que es público), en tablas
con prefijo `pod_`:

`pod_usuarios` · `pod_episodios` · `pod_bloques` · `pod_enlaces` ·
`pod_ficheros` · `pod_menciones` · `pod_marcas` · `pod_proyectos` ·
`pod_proy_marcas` · `pod_accesos` · `pod_intentos`

Las **imágenes y los archivos** no van en la base ni en el repo: viven en
`~/datos/estudio/imagenes/` y solo salen por `estudio/fichero.php`, que mira
antes quién pregunta (`?f=<id>&v=<marca>` lo enseña, con `&d=1` lo descarga).
La dirección **siempre** se hace con `ane_pod_url_fichero()`: lleva una marca
del fichero porque SQLite reutiliza números de filas borradas, y sin ella una
foto nueva heredaba la dirección de una borrada y el navegador enseñaba la
vieja (pasó el 20/10/2026).

- **Imágenes** (JPG, PNG, WEBP, GIF, hasta 12 MB): se redibujan con GD y se
  reducen a 2000 px de lado. Eso valida que sean imágenes de verdad y de paso
  **borra los metadatos**, incluido dónde se hizo la foto.
- **Archivos** (PDF, audio, texto, subtítulos, hojas de cálculo, zip… hasta
  32 MB): se guardan tal cual y salen **siempre como adjunto** y como
  `application/octet-stream`, así que el navegador nunca los ejecuta. La lista
  de extensiones está en `ANE_POD_ARCHIVO_EXT`. **Para vídeo en bruto, Drive**:
  esto no es un almacén.

El límite de subida de PHP del hosting manda: si un fichero grande falla, hay
que subirlo en hPanel (`upload_max_filesize` y `post_max_size`).

Hay **datos personales**: contactos de empresas en `pod_marcas` y los accesos
de los invitados. Backup: copiar `~/datos/ane.db`. Cuando un invitado deje de
hacer falta, bórralo desde **Accesos**.

---

## Texto del guión

En el texto de los temas y de las menciones funciona:
`**negrita**`, `_cursiva_` y líneas que empiecen por `- ` (lista). Todo lo
demás se escapa: por ahí no entra HTML de nadie.
