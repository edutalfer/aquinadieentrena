<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio: gestor de episodios y proyectos
   ------------------------------------------------------------
   Librería común del área privada /estudio (Eduardo y Cristóbal)
   y de la vista de guión que se comparte con el editor de vídeo.
   No se sirve por web: la bloquea el .htaccess como todo `_*.php`.

   Dos roles, y la diferencia es el motivo de que esto exista:
     socio   → lo ve y lo toca todo
     editor  → SOLO el guión (temas, enlaces e imágenes) de los
               episodios que se le abran, en modo lectura. Nunca
               proyectos, patrocinadores, importes ni notas.

   El filtro no es visual: los datos de patrocinio y presupuesto
   no se leen de la base cuando quien mira es el editor.
   ============================================================ */

declare(strict_types=1);
require_once __DIR__ . '/_formularios.php';   /* trae _bd.php y las utilidades comunes */

/* ---------- Vocabulario ---------- */

const ANE_POD_MENCION_TIPOS = [
    'cuna'      => 'Cuña leída',
    'mencion'   => 'Mención suelta',
    'integrada' => 'Integrada en el tema',
    'producto'  => 'Producto en pantalla',
];

const ANE_POD_PATRO_ESTADOS = [
    'contactado'  => 'Contactado',
    'negociando'  => 'Negociando',
    'confirmado'  => 'Confirmado',
    'facturado'   => 'Facturado',
    'descartado'  => 'Descartado',
];

const ANE_POD_PROY_ESTADOS = [
    'idea'     => 'Idea',
    'abierto'  => 'En marcha',
    'cerrado'  => 'Cerrado',
];

/* La sección de cierre que lleva todo episodio. No se borra ni se mueve. */
const ANE_POD_CIERRE = 'bicicepo';
const ANE_POD_CIERRE_TITULO = 'Bici o Cepo';

/* Imágenes: se enseñan en el guión. Lo que se acepta y hasta dónde. */
const ANE_POD_IMAGEN_TIPOS = [
    IMAGETYPE_JPEG => ['image/jpeg', 'jpg'],
    IMAGETYPE_PNG  => ['image/png',  'png'],
    IMAGETYPE_WEBP => ['image/webp', 'webp'],
    IMAGETYPE_GIF  => ['image/gif',  'gif'],
];
const ANE_POD_IMAGEN_MAX = 12 * 1024 * 1024;   /* bytes */
const ANE_POD_IMAGEN_LADO = 2000;              /* px: se reduce lo que pase de aquí */

/* Los demás ficheros no se enseñan: se descargan. Se guardan tal cual y se
   sirven siempre como adjunto, así que el navegador nunca los ejecuta; aun
   así, solo entran extensiones con sentido aquí. Para vídeo en bruto, Drive:
   esto no es un almacén. */
const ANE_POD_ARCHIVO_EXT = ['pdf', 'txt', 'csv', 'srt', 'vtt', 'gpx', 'zip', 'doc', 'docx', 'odt',
                             'xls', 'xlsx', 'ods', 'ppt', 'pptx', 'key', 'mp3', 'wav', 'm4a', 'aac',
                             'mp4', 'mov', 'rtf', 'json', 'xml', 'psd', 'ai', 'eps', 'svg', 'heic'];
const ANE_POD_ARCHIVO_MAX = 32 * 1024 * 1024;   /* bytes */

/* ---------- Base de datos ---------- */

function ane_pod_bd(): PDO
{
    static $listo = false;
    $bd = ane_bd();
    if ($listo) {
        return $bd;
    }
    $bd->exec('
        -- Quién entra. La contraseña, con password_hash (bcrypt).
        CREATE TABLE IF NOT EXISTS pod_usuarios (
            id       INTEGER PRIMARY KEY,
            usuario  TEXT    NOT NULL UNIQUE,     -- minúsculas, sin espacios
            nombre   TEXT    NOT NULL DEFAULT \'\',
            hash     TEXT    NOT NULL,
            rol      TEXT    NOT NULL DEFAULT \'editor\',   -- socio | editor
            activo   INTEGER NOT NULL DEFAULT 1,
            nota     TEXT    NOT NULL DEFAULT \'\',
            creado   TEXT    NOT NULL,
            ultimo   TEXT                            -- último acceso, UTC
        );

        -- Un episodio del podcast: cuándo se graba y qué se cuenta.
        CREATE TABLE IF NOT EXISTS pod_episodios (
            id          INTEGER PRIMARY KEY,
            numero      INTEGER NOT NULL DEFAULT 0,    -- 0 = sin numerar
            titulo      TEXT    NOT NULL DEFAULT \'\',
            fecha       TEXT,                          -- grabación, UTC; NULL = sin fecha
            duracion    INTEGER NOT NULL DEFAULT 0,    -- minutos previstos; 0 = sin estimar
            lugar       TEXT    NOT NULL DEFAULT \'\',
            youtube_id  TEXT    NOT NULL DEFAULT \'\',
            notas       TEXT    NOT NULL DEFAULT \'\',   -- interno: no sale del círculo
            creado      TEXT    NOT NULL,
            actualizado TEXT    NOT NULL
        );
        CREATE INDEX IF NOT EXISTS i_pod_episodios_fecha ON pod_episodios (fecha);

        -- El guión: temas en orden, cada uno con su enlace y sus imágenes.
        -- «interno» = no lo ve el editor. tipo «bicicepo» = la sección de
        -- cierre, siempre la última y no se borra.
        CREATE TABLE IF NOT EXISTS pod_bloques (
            id          INTEGER PRIMARY KEY,
            episodio_id INTEGER NOT NULL,
            orden       INTEGER NOT NULL DEFAULT 0,
            tipo        TEXT    NOT NULL DEFAULT \'tema\',
            titulo      TEXT    NOT NULL DEFAULT \'\',
            texto       TEXT    NOT NULL DEFAULT \'\',
            interno     INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS i_pod_bloques ON pod_bloques (episodio_id, orden);

        -- Los enlaces de cada tema: tantos como haga falta, en orden.
        CREATE TABLE IF NOT EXISTS pod_enlaces (
            id          INTEGER PRIMARY KEY,
            episodio_id INTEGER NOT NULL,
            bloque_id   INTEGER NOT NULL,
            titulo      TEXT    NOT NULL DEFAULT \'\',   -- vacío = se enseña el dominio
            url         TEXT    NOT NULL,
            orden       INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS i_pod_enlaces ON pod_enlaces (bloque_id, orden);

        -- Lo que se sube a un tema: imágenes (se enseñan) y archivos (se
        -- descargan). Viven en ~/datos/estudio/imagenes/, fuera del repo y del
        -- docroot, y salen por estudio/fichero.php, que comprueba antes quién
        -- pregunta.
        CREATE TABLE IF NOT EXISTS pod_ficheros (
            id          INTEGER PRIMARY KEY,
            episodio_id INTEGER NOT NULL,
            bloque_id   INTEGER NOT NULL DEFAULT 0,
            tipo        TEXT    NOT NULL DEFAULT \'imagen\',   -- imagen | archivo
            fichero     TEXT    NOT NULL,                      -- nombre en disco
            nombre      TEXT    NOT NULL DEFAULT \'\',           -- nombre original, para la descarga
            mime        TEXT    NOT NULL,
            tamano      INTEGER NOT NULL DEFAULT 0,
            pie         TEXT    NOT NULL DEFAULT \'\',   -- la descripción de esa foto
            orden       INTEGER NOT NULL DEFAULT 0,
            creado      TEXT    NOT NULL
        );
        CREATE INDEX IF NOT EXISTS i_pod_ficheros ON pod_ficheros (bloque_id, orden);

        -- Patrocinadores y menciones que hay que hacer en el episodio.
        -- Van en orden, como los temas. Reservado a los socios.
        CREATE TABLE IF NOT EXISTS pod_menciones (
            id          INTEGER PRIMARY KEY,
            episodio_id INTEGER NOT NULL,
            marca_id    INTEGER NOT NULL DEFAULT 0,   -- 0 = marca escrita a mano
            marca       TEXT    NOT NULL DEFAULT \'\',
            tipo        TEXT    NOT NULL DEFAULT \'mencion\',
            momento     TEXT    NOT NULL DEFAULT \'\',   -- «al arrancar», «con el tema 3»
            texto       TEXT    NOT NULL DEFAULT \'\',   -- lo que hay que decir
            hecho       INTEGER NOT NULL DEFAULT 0,
            orden       INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS i_pod_menciones ON pod_menciones (episodio_id, orden);

        -- Marcas: la agenda de patrocinio. Reservado a los socios.
        CREATE TABLE IF NOT EXISTS pod_marcas (
            id          INTEGER PRIMARY KEY,
            nombre      TEXT    NOT NULL,
            contacto    TEXT    NOT NULL DEFAULT \'\',
            correo      TEXT    NOT NULL DEFAULT \'\',
            telefono    TEXT    NOT NULL DEFAULT \'\',
            estado      TEXT    NOT NULL DEFAULT \'contactado\',
            notas       TEXT    NOT NULL DEFAULT \'\',
            siguiente   TEXT    NOT NULL DEFAULT \'\',   -- siguiente paso
            seguimiento TEXT,                            -- fecha (AAAA-MM-DD) del siguiente paso
            creado      TEXT    NOT NULL,
            actualizado TEXT    NOT NULL
        );

        -- Proyectos (Cape Epic, ANE House...) con su presupuesto.
        -- Reservado a los socios.
        CREATE TABLE IF NOT EXISTS pod_proyectos (
            id          INTEGER PRIMARY KEY,
            nombre      TEXT    NOT NULL,
            detalle     TEXT    NOT NULL DEFAULT \'\',   -- «Marzo 2027 · Sudáfrica»
            estado      TEXT    NOT NULL DEFAULT \'idea\',
            descripcion TEXT    NOT NULL DEFAULT \'\',
            objetivo    INTEGER NOT NULL DEFAULT 0,     -- euros que hay que levantar
            coste       INTEGER NOT NULL DEFAULT 0,     -- euros que cuesta
            notas       TEXT    NOT NULL DEFAULT \'\',
            orden       INTEGER NOT NULL DEFAULT 0,
            creado      TEXT    NOT NULL,
            actualizado TEXT    NOT NULL
        );

        -- Qué marca está en qué proyecto, por cuánto y a cambio de qué.
        CREATE TABLE IF NOT EXISTS pod_proy_marcas (
            id             INTEGER PRIMARY KEY,
            proyecto_id    INTEGER NOT NULL,
            marca_id       INTEGER NOT NULL DEFAULT 0,
            marca          TEXT    NOT NULL DEFAULT \'\',
            estado         TEXT    NOT NULL DEFAULT \'contactado\',
            importe        INTEGER NOT NULL DEFAULT 0,
            contrapartidas TEXT    NOT NULL DEFAULT \'\',
            notas          TEXT    NOT NULL DEFAULT \'\'
        );
        CREATE INDEX IF NOT EXISTS i_pod_proy_marcas ON pod_proy_marcas (proyecto_id);

        -- Qué guión puede ver el editor. Sin fila aquí, no ve nada.
        CREATE TABLE IF NOT EXISTS pod_accesos (
            usuario_id  INTEGER NOT NULL,
            episodio_id INTEGER NOT NULL,
            creado      TEXT    NOT NULL,
            PRIMARY KEY (usuario_id, episodio_id)
        );

        -- Intentos de entrada fallidos, para frenar la fuerza bruta.
        CREATE TABLE IF NOT EXISTS pod_intentos (
            id      INTEGER PRIMARY KEY,
            fecha   TEXT NOT NULL,
            usuario TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS i_pod_intentos ON pod_intentos (fecha);
    ');
    /* Hasta el 20/10/2026 solo se subían imágenes, en su propia tabla */
    $tablas = $bd->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'pod_imagenes'")->fetchColumn();
    if ($tablas) {
        $bd->exec("INSERT INTO pod_ficheros (episodio_id, bloque_id, tipo, fichero, nombre, mime, pie, orden, creado)
                   SELECT episodio_id, bloque_id, 'imagen', fichero, '', mime, pie, orden, creado FROM pod_imagenes");
        $bd->exec('DROP TABLE pod_imagenes');
    }

    /* «Bici o Cepo» tuvo un rato una descripción suelta para todas las fotos.
       Ahora cada foto lleva la suya, así que ese texto se pega a la primera. */
    $sueltas = $bd->query("SELECT id, texto FROM pod_bloques
                           WHERE tipo = '" . ANE_POD_CIERRE . "' AND texto <> ''")->fetchAll();
    foreach ($sueltas as $b) {
        $st = $bd->prepare("SELECT id, pie FROM pod_ficheros
                            WHERE bloque_id = ? AND tipo = 'imagen' ORDER BY orden, id LIMIT 1");
        $st->execute([(int) $b['id']]);
        $primera = $st->fetch();
        if ($primera) {
            $pie = trim(((string) $primera['pie'] !== '' ? $primera['pie'] . "\n\n" : '') . $b['texto']);
            $bd->prepare('UPDATE pod_ficheros SET pie = ? WHERE id = ?')->execute([$pie, (int) $primera['id']]);
            $bd->prepare('UPDATE pod_bloques SET texto = ? WHERE id = ?')->execute(['', (int) $b['id']]);
        }
    }

    /* Hasta el 20/10/2026 cada tema tenía UN enlace, en una columna suya.
       Si esa columna sigue ahí, sus enlaces se pasan a la tabla y se vacía. */
    $columnas = $bd->query('PRAGMA table_info(pod_bloques)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (in_array('enlace', $columnas, true)) {
        $viejos = $bd->query("SELECT id, episodio_id, enlace FROM pod_bloques WHERE enlace <> ''")->fetchAll();
        if ($viejos) {
            $ins = $bd->prepare('INSERT INTO pod_enlaces (episodio_id, bloque_id, url, orden) VALUES (?, ?, ?, 10)');
            foreach ($viejos as $v) {
                $ins->execute([(int) $v['episodio_id'], (int) $v['id'], (string) $v['enlace']]);
            }
            $bd->exec("UPDATE pod_bloques SET enlace = ''");
        }
    }

    $listo = true;
    return $bd;
}

/* ---------- Acceso ---------- */

function ane_pod_usuario_valido(string $u): bool
{
    return (bool) preg_match('/^[a-z0-9][a-z0-9._-]{2,31}$/', $u);
}

function ane_pod_nombre_usuario(string $u): string
{
    return mb_strtolower(trim(preg_replace('/[^A-Za-z0-9._-]/', '', $u) ?? ''), 'UTF-8');
}

function ane_pod_por_usuario(PDO $bd, string $u): ?array
{
    $st = $bd->prepare('SELECT * FROM pod_usuarios WHERE usuario = ?');
    $st->execute([$u]);
    return $st->fetch() ?: null;
}

function ane_pod_por_id(PDO $bd, int $id): ?array
{
    $st = $bd->prepare('SELECT * FROM pod_usuarios WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/* Cuántos fallos lleva ese usuario en el último cuarto de hora */
function ane_pod_fallos(PDO $bd, string $u): int
{
    $bd->prepare("DELETE FROM pod_intentos WHERE fecha < datetime('now', '-1 day')")->execute();
    $st = $bd->prepare("SELECT COUNT(*) FROM pod_intentos WHERE usuario = ? AND fecha > datetime('now', '-15 minutes')");
    $st->execute([$u]);
    return (int) $st->fetchColumn();
}

function ane_pod_apunta_fallo(PDO $bd, string $u): void
{
    $bd->prepare('INSERT INTO pod_intentos (fecha, usuario) VALUES (?, ?)')->execute([ane_ahora(), $u]);
}

function ane_pod_limpia_fallos(PDO $bd, string $u): void
{
    $bd->prepare('DELETE FROM pod_intentos WHERE usuario = ?')->execute([$u]);
}

/* ---------- Episodios ---------- */

function ane_pod_episodio(PDO $bd, int $id): ?array
{
    $st = $bd->prepare('SELECT * FROM pod_episodios WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/* Los episodios en orden de grabación: primero los que tienen fecha,
   los más cercanos arriba; los sin fecha, al final. */
function ane_pod_episodios(PDO $bd, array $ids = null): array
{
    $sql = 'SELECT * FROM pod_episodios';
    $args = [];
    if ($ids !== null) {
        if (!$ids) {
            return [];
        }
        $sql .= ' WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $args = array_map('intval', $ids);
    }
    $sql .= ' ORDER BY (fecha IS NULL), fecha DESC, id DESC';
    $st = $bd->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

/* Cómo se llama un episodio por ahí: con número y título, «#49 · Lo que sea»;
   con número y sin título, «Episodio 49»; sin nada, «Sin título». El título
   no es obligatorio: para crear un episodio basta el número. */
function ane_pod_etiqueta(array $ep): string
{
    $numero = (int) ($ep['numero'] ?? 0);
    $titulo = trim((string) ($ep['titulo'] ?? ''));
    if ($titulo === '') {
        return $numero ? 'Episodio ' . $numero : 'Sin título';
    }
    return $numero ? '#' . $numero . ' · ' . $titulo : $titulo;
}

/* ---------- Guión ---------- */

/* Los temas, con «Bici o Cepo» siempre al final */
function ane_pod_bloques(PDO $bd, int $episodio, bool $soloPublicos = false): array
{
    $sql = 'SELECT * FROM pod_bloques WHERE episodio_id = ?' . ($soloPublicos ? ' AND interno = 0' : '');
    $st = $bd->prepare($sql . " ORDER BY (tipo = '" . ANE_POD_CIERRE . "'), orden, id");
    $st->execute([$episodio]);
    return $st->fetchAll();
}

/* Todo episodio acaba en «Bici o Cepo»: si no está, se crea */
function ane_pod_asegura_cierre(PDO $bd, int $episodio): int
{
    $st = $bd->prepare('SELECT id FROM pod_bloques WHERE episodio_id = ? AND tipo = ?');
    $st->execute([$episodio, ANE_POD_CIERRE]);
    $id = (int) $st->fetchColumn();
    if ($id) {
        return $id;
    }
    $bd->prepare('INSERT INTO pod_bloques (episodio_id, orden, tipo, titulo) VALUES (?, ?, ?, ?)')
       ->execute([$episodio, 999999, ANE_POD_CIERRE, ANE_POD_CIERRE_TITULO]);
    return (int) $bd->lastInsertId();
}

function ane_pod_siguiente_orden(PDO $bd, string $tabla, int $episodio): int
{
    $extra = $tabla === 'pod_bloques' ? " AND tipo <> '" . ANE_POD_CIERRE . "'" : '';
    $st = $bd->prepare('SELECT COALESCE(MAX(orden), 0) + 10 FROM ' . ane_pod_tabla($tabla)
                       . ' WHERE episodio_id = ?' . $extra);
    $st->execute([$episodio]);
    return (int) $st->fetchColumn();
}

/* Las únicas tablas que se pueden ordenar desde la web */
function ane_pod_tabla(string $tabla): string
{
    if (!in_array($tabla, ['pod_bloques', 'pod_menciones'], true)) {
        throw new InvalidArgumentException('tabla no permitida');
    }
    return $tabla;
}

/* Sube o baja una fila dentro de su episodio intercambiando el orden */
function ane_pod_mueve(PDO $bd, string $tabla, int $id, int $episodio, string $hacia): void
{
    $filas = ane_pod_filas_ordenadas($bd, $tabla, $episodio);
    $pos = null;
    foreach ($filas as $i => $f) {
        if ((int) $f['id'] === $id) {
            $pos = $i;
        }
    }
    if ($pos === null) {
        return;
    }
    $otra = $hacia === 'arriba' ? $pos - 1 : $pos + 1;
    if ($otra < 0 || $otra >= count($filas)) {
        return;
    }
    /* Reescribe los dos órdenes con la lista ya normalizada: así da igual
       que vinieran empatados a 0 de un apaño anterior */
    $st = $bd->prepare('UPDATE ' . ane_pod_tabla($tabla) . ' SET orden = ? WHERE id = ?');
    $st->execute([($otra + 1) * 10, (int) $filas[$pos]['id']]);
    $st->execute([($pos + 1) * 10, (int) $filas[$otra]['id']]);
}

/* «Bici o Cepo» no entra en el baile: va siempre al final */
function ane_pod_filas_ordenadas(PDO $bd, string $tabla, int $episodio): array
{
    $extra = $tabla === 'pod_bloques' ? " AND tipo <> '" . ANE_POD_CIERRE . "'" : '';
    $st = $bd->prepare('SELECT id, orden FROM ' . ane_pod_tabla($tabla)
                       . ' WHERE episodio_id = ?' . $extra . ' ORDER BY orden, id');
    $st->execute([$episodio]);
    return $st->fetchAll();
}

/* Los enlaces del episodio, agrupados por tema */
function ane_pod_enlaces(PDO $bd, int $episodio): array
{
    $st = $bd->prepare('SELECT * FROM pod_enlaces WHERE episodio_id = ? ORDER BY orden, id');
    $st->execute([$episodio]);
    $fuera = [];
    foreach ($st->fetchAll() as $f) {
        $fuera[(int) $f['bloque_id']][] = $f;
    }
    return $fuera;
}

function ane_pod_enlace(PDO $bd, int $id): ?array
{
    $st = $bd->prepare('SELECT * FROM pod_enlaces WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/* ---------- Ficheros: imágenes y archivos ---------- */

function ane_pod_carpeta_imagenes(): string
{
    $d = ANE_DATOS . '/estudio/imagenes';
    if (!is_dir($d)) {
        mkdir($d, 0700, true);
    }
    return $d;
}

/* Todo lo subido al episodio, agrupado por tema */
function ane_pod_ficheros_de(PDO $bd, int $episodio): array
{
    $st = $bd->prepare('SELECT * FROM pod_ficheros WHERE episodio_id = ? ORDER BY tipo DESC, orden, id');
    $st->execute([$episodio]);
    $fuera = [];
    foreach ($st->fetchAll() as $f) {
        $fuera[(int) $f['bloque_id']][] = $f;
    }
    return $fuera;
}

function ane_pod_solo(array $lista, string $tipo): array
{
    return array_values(array_filter($lista, fn ($f) => $f['tipo'] === $tipo));
}

function ane_pod_fichero(PDO $bd, int $id): ?array
{
    $st = $bd->prepare('SELECT * FROM pod_ficheros WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/* Un <input type="file" multiple> llega como columnas paralelas
   ($_FILES['fichero']['name'][0], ['tmp_name'][0]...). Esto lo devuelve como
   una lista de ficheros normales, venga uno o vengan diez. */
function ane_pod_subidas(string $campo): array
{
    $f = $_FILES[$campo] ?? null;
    if (!is_array($f) || !isset($f['tmp_name'])) {
        return [];
    }
    if (!is_array($f['tmp_name'])) {
        return [$f];
    }
    $fuera = [];
    foreach (array_keys($f['tmp_name']) as $i) {
        $fuera[] = ['name' => $f['name'][$i] ?? '', 'tmp_name' => $f['tmp_name'][$i] ?? '',
                    'error' => $f['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $f['size'][$i] ?? 0];
    }
    return array_slice($fuera, 0, 20);   /* un tope por si alguien suelta la carpeta entera */
}

/* Nombre original limpio, para que la descarga se llame como el original */
function ane_pod_nombre_fichero(string $n): string
{
    $n = preg_replace('/[\x00-\x1F\/\\\\]+/u', ' ', $n) ?? '';
    $n = preg_replace('/\s+/u', ' ', $n) ?? '';
    return mb_substr(trim($n), 0, 150, 'UTF-8');
}

function ane_pod_extension(string $nombre): string
{
    return mb_strtolower((string) pathinfo($nombre, PATHINFO_EXTENSION), 'UTF-8');
}

/* Guarda lo que llega de un <input type=file>. Devuelve '' si fue bien o el
   motivo si no.

   Si es una imagen de verdad (getimagesize, no el nombre ni lo que diga el
   navegador), se redibuja con GD: eso la valida, la reduce si es enorme y de
   paso borra los metadatos (incluido dónde se hizo la foto). Cualquier otro
   fichero se guarda tal cual y solo sale como adjunto. */
function ane_pod_guarda_fichero(PDO $bd, int $episodio, int $bloque, array $f, string $pie = ''): string
{
    $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        return 'vacio';
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return 'grande';
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($f['tmp_name'] ?? ''))) {
        return 'subida';
    }
    $tam = (int) ($f['size'] ?? 0);
    $original = ane_pod_nombre_fichero((string) ($f['name'] ?? ''));
    $info = @getimagesize($f['tmp_name']);
    $esImagen = $info && isset(ANE_POD_IMAGEN_TIPOS[$info[2]]);

    if ($esImagen) {
        if ($tam > ANE_POD_IMAGEN_MAX) {
            return 'grande';
        }
        [$mime, $ext] = ANE_POD_IMAGEN_TIPOS[$info[2]];
        $tipo = 'imagen';
    } else {
        if ($tam > ANE_POD_ARCHIVO_MAX) {
            return 'grande';
        }
        $ext = ane_pod_extension($original);
        if (!in_array($ext, ANE_POD_ARCHIVO_EXT, true)) {
            return 'formato';
        }
        $mime = 'application/octet-stream';
        $tipo = 'archivo';
    }

    $nombre = bin2hex(random_bytes(16)) . '.' . $ext;
    $destino = ane_pod_carpeta_imagenes() . '/' . $nombre;

    $hecho = false;
    if ($esImagen && function_exists('imagecreatefromstring') && $info[2] !== IMAGETYPE_GIF) {
        $hecho = ane_pod_reescribe_imagen((string) $f['tmp_name'], $destino, $info, $ext);
    }
    if (!$hecho && !move_uploaded_file($f['tmp_name'], $destino)) {
        return 'disco';
    }
    chmod($destino, 0600);
    if ($original === '') {
        $original = 'archivo.' . $ext;
    }

    $st = $bd->prepare('SELECT COALESCE(MAX(orden), 0) + 10 FROM pod_ficheros WHERE bloque_id = ?');
    $st->execute([$bloque]);
    $bd->prepare('INSERT INTO pod_ficheros (episodio_id, bloque_id, tipo, fichero, nombre, mime, tamano, pie, orden, creado)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
       ->execute([$episodio, $bloque, $tipo, $nombre, $original, $mime, (int) @filesize($destino),
                  ane_recorta($pie, 2000), (int) $st->fetchColumn(), ane_ahora()]);
    return '';
}

/* Redibuja la imagen (y la encoge si pasa de ANE_POD_IMAGEN_LADO) */
function ane_pod_reescribe_imagen(string $origen, string $destino, array $info, string $ext): bool
{
    $datos = @file_get_contents($origen);
    $img = $datos !== false ? @imagecreatefromstring($datos) : false;
    if (!$img) {
        return false;
    }
    $an = imagesx($img);
    $al = imagesy($img);
    $lado = max($an, $al);
    if ($lado > ANE_POD_IMAGEN_LADO) {
        $f = ANE_POD_IMAGEN_LADO / $lado;
        $nueva = imagescale($img, (int) round($an * $f), (int) round($al * $f));
        if ($nueva) {
            imagedestroy($img);
            $img = $nueva;
        }
    }
    $ok = false;
    if ($ext === 'png') {
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $ok = imagepng($img, $destino, 6);
    } elseif ($ext === 'webp' && function_exists('imagewebp')) {
        $ok = imagewebp($img, $destino, 82);
    } else {
        $ok = imagejpeg($img, $destino, 82);
    }
    imagedestroy($img);
    return (bool) $ok;
}

function ane_pod_borra_fichero(PDO $bd, array $f): void
{
    $ruta = ane_pod_carpeta_imagenes() . '/' . basename((string) $f['fichero']);
    if (is_file($ruta)) {
        @unlink($ruta);
    }
    $bd->prepare('DELETE FROM pod_ficheros WHERE id = ?')->execute([(int) $f['id']]);
}

/* Al borrar un tema o un episodio, sus ficheros se van con él */
function ane_pod_borra_ficheros(PDO $bd, string $campo, int $valor): void
{
    $campo = $campo === 'bloque_id' ? 'bloque_id' : 'episodio_id';
    $st = $bd->prepare("SELECT * FROM pod_ficheros WHERE $campo = ?");
    $st->execute([$valor]);
    foreach ($st->fetchAll() as $f) {
        ane_pod_borra_fichero($bd, $f);
    }
}

function ane_pod_motivo_fichero(string $codigo): string
{
    return [
        'grande'  => 'El fichero pesa demasiado: 12 MB las imágenes, 32 MB el resto (y el servidor puede admitir menos).',
        'formato' => 'Ese tipo de fichero no entra. Imágenes, PDF, audio, texto, hojas de cálculo y poco más.',
        'subida'  => 'La subida se cortó. Inténtalo otra vez.',
        'disco'   => 'No se ha podido guardar el fichero en el servidor.',
        'vacio'   => 'No elegiste ningún fichero.',
    ][$codigo] ?? 'No se ha podido subir el fichero.';
}

function ane_pod_tamano(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    return max(1, (int) round($bytes / 1024)) . ' KB';
}

/* ---------- Menciones ---------- */

/* Menciones de patrocinio: solo para socios. La función lo recuerda en el nombre. */
function ane_pod_menciones_socio(PDO $bd, int $episodio): array
{
    $st = $bd->prepare('SELECT * FROM pod_menciones WHERE episodio_id = ? ORDER BY orden, id');
    $st->execute([$episodio]);
    return $st->fetchAll();
}

/* ---------- Marcas y proyectos ---------- */

function ane_pod_marcas(PDO $bd): array
{
    return $bd->query('SELECT * FROM pod_marcas ORDER BY nombre COLLATE NOCASE')->fetchAll();
}

function ane_pod_marca(PDO $bd, int $id): ?array
{
    $st = $bd->prepare('SELECT * FROM pod_marcas WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function ane_pod_proyectos(PDO $bd): array
{
    return $bd->query('SELECT * FROM pod_proyectos ORDER BY orden, id')->fetchAll();
}

function ane_pod_proyecto(PDO $bd, int $id): ?array
{
    $st = $bd->prepare('SELECT * FROM pod_proyectos WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function ane_pod_proyecto_marcas(PDO $bd, int $proyecto): array
{
    $st = $bd->prepare('SELECT pm.*, m.nombre AS marca_nombre, m.correo AS marca_correo
                        FROM pod_proy_marcas pm LEFT JOIN pod_marcas m ON m.id = pm.marca_id
                        WHERE pm.proyecto_id = ? ORDER BY pm.id');
    $st->execute([$proyecto]);
    return $st->fetchAll();
}

/* Confirmado + facturado = dinero que se da por hecho */
function ane_pod_cuentas(array $filas): array
{
    $c = ['confirmado' => 0, 'negociando' => 0, 'total' => 0];
    foreach ($filas as $f) {
        $i = (int) $f['importe'];
        if (in_array($f['estado'], ['confirmado', 'facturado'], true)) {
            $c['confirmado'] += $i;
        } elseif (in_array($f['estado'], ['contactado', 'negociando'], true)) {
            $c['negociando'] += $i;
        }
    }
    $c['total'] = $c['confirmado'] + $c['negociando'];
    return $c;
}

/* ---------- Presentación ---------- */

function ane_pod_euros(int $n): string
{
    return number_format($n, 0, ',', '.') . ' €';
}

function ane_pod_fecha_larga(?string $utc): string
{
    if (!$utc) {
        return 'Sin fecha';
    }
    $dias = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $d = new DateTime($utc, new DateTimeZone('UTC'));
    $d->setTimezone(new DateTimeZone('Europe/Madrid'));
    return $dias[(int) $d->format('N') - 1] . ' ' . $d->format('j') . ' ' . $meses[(int) $d->format('n') - 1]
         . ' ' . $d->format('Y') . ' · ' . $d->format('H:i');
}

/* «Faltan 3 días», «Hoy», «Hace 2 semanas» */
function ane_pod_cuando(?string $utc): string
{
    if (!$utc) {
        return '';
    }
    $hoy = new DateTime('today', new DateTimeZone('Europe/Madrid'));
    $d = new DateTime($utc, new DateTimeZone('UTC'));
    $d->setTimezone(new DateTimeZone('Europe/Madrid'));
    $dias = (int) $hoy->diff(new DateTime($d->format('Y-m-d'), new DateTimeZone('Europe/Madrid')))->format('%r%a');
    if ($dias === 0) {
        return 'Hoy';
    }
    if ($dias === 1) {
        return 'Mañana';
    }
    if ($dias === -1) {
        return 'Ayer';
    }
    if ($dias > 0) {
        return $dias < 14 ? "En $dias días" : 'En ' . (int) round($dias / 7) . ' semanas';
    }
    $dias = -$dias;
    return $dias < 14 ? "Hace $dias días" : 'Hace ' . (int) round($dias / 7) . ' semanas';
}

/* Enlace de confianza: solo http(s), y escapado al pintarlo */
function ane_pod_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

function ane_pod_dominio(string $url): string
{
    $h = parse_url($url, PHP_URL_HOST) ?: $url;
    return preg_replace('/^www\./', '', (string) $h) ?? '';
}

/* Texto plano del guión → HTML: párrafos, listas con «- » y **negrita**.
   Todo se escapa antes: aquí no entra HTML de nadie. */
function ane_pod_texto(string $texto): string
{
    $fuera = [];
    $lista = [];
    foreach (preg_split('/\R/u', $texto) as $linea) {
        $l = trim($linea);
        if ($l === '') {
            continue;
        }
        if (preg_match('/^[-*•]\s+(.*)$/u', $l, $m)) {
            $lista[] = '<li>' . ane_pod_inline($m[1]) . '</li>';
            continue;
        }
        if ($lista) {
            $fuera[] = '<ul>' . implode('', $lista) . '</ul>';
            $lista = [];
        }
        $fuera[] = '<p>' . ane_pod_inline($l) . '</p>';
    }
    if ($lista) {
        $fuera[] = '<ul>' . implode('', $lista) . '</ul>';
    }
    return implode("\n", $fuera);
}

function ane_pod_inline(string $s): string
{
    $s = ane_h($s);
    $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s) ?? $s;
    return preg_replace('/(?<![\w*])_(.+?)_(?![\w*])/u', '<em>$1</em>', $s) ?? $s;
}
