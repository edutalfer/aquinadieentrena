<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio: gestor de episodios y proyectos
   ------------------------------------------------------------
   Librería común del área privada /estudio (Eduardo y Cristóbal)
   y de la vista de guión que se comparte con el editor de vídeo.
   No se sirve por web: la bloquea el .htaccess como todo `_*.php`.

   Dos roles, y la diferencia es el motivo de que esto exista:
     socio   → lo ve y lo toca todo
     editor  → SOLO el guión definitivo de los episodios que se le
               abran, en lectura: temas, enlaces, imágenes, archivos
               y las cuñas de patrocinio en su sitio (las tiene que
               montar). Nunca proyectos, la agenda de patrocinadores
               (contactos, estados, importes), accesos, notas
               internas ni temas marcados como internos.

   El filtro no es visual: nada de eso se lee de la base cuando
   quien mira es el editor, y sus pantallas le responden 403.
   ============================================================ */

declare(strict_types=1);
require_once __DIR__ . '/_formularios.php';   /* trae _bd.php y las utilidades comunes */

/* ---------- Vocabulario ---------- */

/* Cómo sale una marca en un episodio. Se pueden marcar varios a la vez:
   se guardan separados por comas («explicito,producto»). */
const ANE_POD_MENCION_TIPOS = [
    'explicito' => 'Anuncio explícito',
    'integrado' => 'Anuncio integrado en sección',
    'evento'    => 'Presencia en el evento',
    'maillot'   => 'Presencia en el maillot',
    'producto'  => 'Product placement',
];

/* Los tipos de antes del 20/10/2026, y a qué equivalen ahora */
const ANE_POD_TIPOS_VIEJOS = ['cuna' => 'explicito', 'mencion' => 'explicito', 'integrada' => 'integrado'];

const ANE_POD_PATRO_ESTADOS = [
    'contactado'  => 'Contactado',
    'negociando'  => 'Negociando',
    'confirmado'  => 'Confirmado',
    'facturado'   => 'Facturado',
    'descartado'  => 'Descartado',
];

/* Un proyecto creado se da por en marcha (lo decidió Eduardo el 20/10/2026:
   fuera el estado «Idea»). Solo se marca cuando se cierra. */
const ANE_POD_PROY_ESTADOS = [
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
        -- cierre, siempre la última y no se borra. «descartado» = se tachó
        -- durante la grabación: el editor no lo ve y se puede recuperar.
        CREATE TABLE IF NOT EXISTS pod_bloques (
            id          INTEGER PRIMARY KEY,
            episodio_id INTEGER NOT NULL,
            orden       INTEGER NOT NULL DEFAULT 0,
            tipo        TEXT    NOT NULL DEFAULT \'tema\',
            titulo      TEXT    NOT NULL DEFAULT \'\',
            texto       TEXT    NOT NULL DEFAULT \'\',
            interno     INTEGER NOT NULL DEFAULT 0,
            descartado  INTEGER NOT NULL DEFAULT 0
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

        -- Las cuñas de patrocinio del episodio, intercaladas entre los temas.
        -- Las ve también el editor, en su sitio del guión: las tiene que
        -- montar. Lo que no ve nunca es la agenda (pod_marcas) ni importes.
        CREATE TABLE IF NOT EXISTS pod_menciones (
            id          INTEGER PRIMARY KEY,
            episodio_id INTEGER NOT NULL,
            marca_id    INTEGER NOT NULL DEFAULT 0,   -- 0 = marca escrita a mano
            marca       TEXT    NOT NULL DEFAULT \'\',
            tipo        TEXT    NOT NULL DEFAULT \'explicito\',
            momento     TEXT    NOT NULL DEFAULT \'\',   -- ya no se usa (21/10/2026): lo dice el sitio en el guión
            texto       TEXT    NOT NULL DEFAULT \'\',   -- lo que hay que decir
            hecho       INTEGER NOT NULL DEFAULT 0,
            orden       INTEGER NOT NULL DEFAULT 0,
            acuerdo_id  INTEGER NOT NULL DEFAULT 0    -- 0 = cuña suelta; si no, viene de un acuerdo
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
            estado      TEXT    NOT NULL DEFAULT \'abierto\',   -- abierto | cerrado
            descripcion TEXT    NOT NULL DEFAULT \'\',
            objetivo    INTEGER NOT NULL DEFAULT 0,     -- euros que hay que levantar
            coste       INTEGER NOT NULL DEFAULT 0,     -- euros que cuesta
            notas       TEXT    NOT NULL DEFAULT \'\',
            orden       INTEGER NOT NULL DEFAULT 0,
            fijo        INTEGER NOT NULL DEFAULT 0,     -- 1 = «Podcast»: siempre está, no se borra
            creado      TEXT    NOT NULL,
            actualizado TEXT    NOT NULL
        );

        -- El ACUERDO con una marca: en qué proyecto, por cuánto, a cambio de
        -- qué y en qué episodios sale su cuña. Firmado (confirmado o
        -- facturado), crea los episodios que falten y les mete la cuña.
        CREATE TABLE IF NOT EXISTS pod_proy_marcas (
            id             INTEGER PRIMARY KEY,
            proyecto_id    INTEGER NOT NULL,
            marca_id       INTEGER NOT NULL DEFAULT 0,
            marca          TEXT    NOT NULL DEFAULT \'\',
            estado         TEXT    NOT NULL DEFAULT \'contactado\',
            importe        INTEGER NOT NULL DEFAULT 0,
            contrapartidas TEXT    NOT NULL DEFAULT \'\',
            notas          TEXT    NOT NULL DEFAULT \'\',
            episodios      TEXT    NOT NULL DEFAULT \'\',   -- «10-13, 20»: números de episodio
            tipo           TEXT    NOT NULL DEFAULT \'explicito\', -- tipos de cuña, «explicito,producto»
            texto          TEXT    NOT NULL DEFAULT \'\'    -- qué hay que decir en cada una
        );
        CREATE INDEX IF NOT EXISTS i_pod_proy_marcas ON pod_proy_marcas (proyecto_id);

        -- Lo que cuesta un proyecto, partida a partida («Casa», «Vuelos»...).
        -- La suma es el coste del proyecto. En CÉNTIMOS (312,40 € = 31240):
        -- «importe» (euros enteros) fue la primera versión y ya no se usa.
        CREATE TABLE IF NOT EXISTS pod_gastos (
            id          INTEGER PRIMARY KEY,
            proyecto_id INTEGER NOT NULL,
            concepto    TEXT    NOT NULL,
            importe     INTEGER NOT NULL DEFAULT 0,
            centimos    INTEGER NOT NULL DEFAULT 0,
            pagado      INTEGER NOT NULL DEFAULT 0,
            nota        TEXT    NOT NULL DEFAULT \'\',
            creado      TEXT    NOT NULL
        );
        CREATE INDEX IF NOT EXISTS i_pod_gastos ON pod_gastos (proyecto_id);

        -- Contratos firmados con las marcas. Van colgados de la marca y, si se
        -- sabe, del acuerdo. Fichero en ~/datos/estudio/contratos/ (fuera del
        -- repo y del docroot) y SOLO para socios: los sirve estudio/contrato.php.
        -- Si se borra el acuerdo, el contrato se queda en la marca.
        CREATE TABLE IF NOT EXISTS pod_contratos (
            id          INTEGER PRIMARY KEY,
            marca_id    INTEGER NOT NULL DEFAULT 0,
            acuerdo_id  INTEGER NOT NULL DEFAULT 0,
            fichero     TEXT    NOT NULL,            -- nombre en disco
            nombre      TEXT    NOT NULL DEFAULT \'\', -- nombre original
            mime        TEXT    NOT NULL,
            tamano      INTEGER NOT NULL DEFAULT 0,
            creado      TEXT    NOT NULL
        );
        CREATE INDEX IF NOT EXISTS i_pod_contratos ON pod_contratos (marca_id, acuerdo_id);

        -- Notas apuntadas mientras se graba: un texto, un enlace opcional, el
        -- tema al que van (0 = general) y si las ve el editor (por defecto, SÍ:
        -- se marca cuando una es solo para los socios).
        CREATE TABLE IF NOT EXISTS pod_notas (
            id          INTEGER PRIMARY KEY,
            episodio_id INTEGER NOT NULL,
            bloque_id   INTEGER NOT NULL DEFAULT 0,
            texto       TEXT    NOT NULL DEFAULT \'\',
            url         TEXT    NOT NULL DEFAULT \'\',
            editor      INTEGER NOT NULL DEFAULT 1,
            autor       TEXT    NOT NULL DEFAULT \'\',
            creado      TEXT    NOT NULL
        );
        CREATE INDEX IF NOT EXISTS i_pod_notas ON pod_notas (episodio_id, bloque_id);

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

    ane_pod_unifica_orden($bd);

    /* 21/10/2026: los acuerdos unen marca, proyecto, dinero y episodios (D24).
       Columnas nuevas en tablas que ya existían, y el proyecto fijo «Podcast». */
    ane_pod_columna($bd, 'pod_proyectos', 'fijo', 'INTEGER NOT NULL DEFAULT 0');
    ane_pod_columna($bd, 'pod_proy_marcas', 'episodios', "TEXT NOT NULL DEFAULT ''");
    ane_pod_columna($bd, 'pod_proy_marcas', 'tipo', "TEXT NOT NULL DEFAULT 'explicito'");
    ane_pod_columna($bd, 'pod_proy_marcas', 'texto', "TEXT NOT NULL DEFAULT ''");
    ane_pod_columna($bd, 'pod_menciones', 'acuerdo_id', 'INTEGER NOT NULL DEFAULT 0');
    ane_pod_proyecto_podcast($bd);
    /* 21/10/2026: las notas las ve el editor salvo que se diga lo contrario. Las
       apuntadas antes nacieron ocultas por la regla vieja: pasan a visibles */
    if (!$bd->query("SELECT valor FROM ajustes WHERE clave = 'pod_notas_v2'")->fetchColumn()) {
        $bd->exec('UPDATE pod_notas SET editor = 1');
        $bd->prepare("INSERT OR REPLACE INTO ajustes (clave, valor) VALUES ('pod_notas_v2', '1')")->execute();
    }
    /* 21/10/2026: el coste del proyecto sale de sus gastos (D27). El «coste
       previsto» que se tecleaba a mano entra como primer gasto, sin desglosar */
    if (!$bd->query("SELECT valor FROM ajustes WHERE clave = 'pod_gastos_v1'")->fetchColumn()) {
        $bd->exec("INSERT INTO pod_gastos (proyecto_id, concepto, importe, pagado, nota, creado)
                   SELECT id, 'Sin desglosar', coste, 0, 'El coste previsto que tenía el proyecto antes de llevar gastos', actualizado
                   FROM pod_proyectos p
                   WHERE coste > 0 AND NOT EXISTS (SELECT 1 FROM pod_gastos g WHERE g.proyecto_id = p.id)");
        $bd->prepare("INSERT OR REPLACE INTO ajustes (clave, valor) VALUES ('pod_gastos_v1', '1')")->execute();
    }
    /* 21/10/2026: un tema se puede descartar en plena grabación (D28) */
    ane_pod_columna($bd, 'pod_bloques', 'descartado', 'INTEGER NOT NULL DEFAULT 0');
    /* Los gastos admiten céntimos: pasan de euros enteros («importe») a céntimos */
    ane_pod_columna($bd, 'pod_gastos', 'centimos', 'INTEGER NOT NULL DEFAULT 0');
    if (!$bd->query("SELECT valor FROM ajustes WHERE clave = 'pod_gastos_v2'")->fetchColumn()) {
        $bd->exec('UPDATE pod_gastos SET centimos = importe * 100 WHERE centimos = 0');
        $bd->prepare("INSERT OR REPLACE INTO ajustes (clave, valor) VALUES ('pod_gastos_v2', '1')")->execute();
    }
    /* Los que quedaran como «idea» pasan a en marcha */
    $bd->exec("UPDATE pod_proyectos SET estado = 'abierto' WHERE estado NOT IN ('abierto', 'cerrado')");

    /* 20/10/2026: tipos nuevos de cuña, y se pueden marcar varios */
    if (!$bd->query("SELECT valor FROM ajustes WHERE clave = 'pod_tipos_v2'")->fetchColumn()) {
        foreach (['pod_menciones', 'pod_proy_marcas'] as $t) {
            foreach ($bd->query("SELECT id, tipo FROM $t")->fetchAll() as $f) {
                $bd->prepare("UPDATE $t SET tipo = ? WHERE id = ?")->execute([ane_pod_tipos_csv((string) $f['tipo']), (int) $f['id']]);
            }
        }
        $bd->prepare("INSERT OR REPLACE INTO ajustes (clave, valor) VALUES ('pod_tipos_v2', '1')")->execute();
    }

    $listo = true;
    return $bd;
}

/* Hasta el 21/10/2026 los temas y las menciones de patrocinio llevaban
   órdenes separados, y las menciones iban en su propia sección. Desde
   entonces comparten uno solo, para poder intercalar un patrocinio entre
   dos temas. Esto lo pone en marcha una vez por base:
   - las menciones que hubiera se colocan al principio del guión (desde ahí
     se mueven con las flechas o con «colocar después de»);
   - su «momento» («al arrancar», «con el tema 3») ya lo dice la posición, así
     que el texto pasa delante de lo que hay que decir, para no perderlo. */
function ane_pod_unifica_orden(PDO $bd): void
{
    if ($bd->query("SELECT valor FROM ajustes WHERE clave = 'pod_orden_unificado'")->fetchColumn()) {
        return;
    }
    $bd->beginTransaction();
    foreach ($bd->query('SELECT id FROM pod_episodios')->fetchAll(PDO::FETCH_COLUMN) as $ep) {
        $st = $bd->prepare('SELECT id, momento, texto FROM pod_menciones WHERE episodio_id = ? ORDER BY orden, id');
        $st->execute([(int) $ep]);
        $lista = [];
        foreach ($st->fetchAll() as $m) {
            if (trim((string) $m['momento']) !== '') {
                $bd->prepare("UPDATE pod_menciones SET texto = ?, momento = '' WHERE id = ?")
                   ->execute([trim('**Momento:** ' . trim((string) $m['momento']) . "\n" . $m['texto']), (int) $m['id']]);
            }
            $lista[] = ['pod_menciones', (int) $m['id']];
        }
        $st = $bd->prepare("SELECT id FROM pod_bloques WHERE episodio_id = ? AND tipo <> '" . ANE_POD_CIERRE . "'
                            ORDER BY orden, id");
        $st->execute([(int) $ep]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $b) {
            $lista[] = ['pod_bloques', (int) $b];
        }
        ane_pod_renumera($bd, $lista);
    }
    $bd->prepare("INSERT OR REPLACE INTO ajustes (clave, valor) VALUES ('pod_orden_unificado', '1')")->execute();
    $bd->commit();
}

/* Añade una columna si aún no está (SQLite no tiene «ADD COLUMN IF NOT EXISTS») */
function ane_pod_columna(PDO $bd, string $tabla, string $columna, string $definicion): void
{
    $hay = $bd->query('PRAGMA table_info(' . $tabla . ')')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array($columna, $hay, true)) {
        $bd->exec("ALTER TABLE $tabla ADD COLUMN $columna $definicion");
    }
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

/* Los temas, con «Bici o Cepo» siempre al final. Con $soloPublicos (el
   editor), ni los internos ni los descartados */
function ane_pod_bloques(PDO $bd, int $episodio, bool $soloPublicos = false): array
{
    $sql = 'SELECT * FROM pod_bloques WHERE episodio_id = ?' . ($soloPublicos ? ' AND interno = 0 AND descartado = 0' : '');
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

/* El guión es UNA lista: temas y patrocinios comparten el orden, para
   poder meter una cuña entre dos temas. «Bici o Cepo» no entra: va
   siempre al final. Cada pieza es [tabla, id]. */
function ane_pod_piezas(PDO $bd, int $episodio): array
{
    $st = $bd->prepare("SELECT 'pod_bloques' AS t, id, orden FROM pod_bloques
                        WHERE episodio_id = ? AND tipo <> '" . ANE_POD_CIERRE . "'
                        UNION ALL
                        SELECT 'pod_menciones' AS t, id, orden FROM pod_menciones WHERE episodio_id = ?
                        ORDER BY orden, t, id");
    $st->execute([$episodio, $episodio]);
    return array_map(fn ($f) => [(string) $f['t'], (int) $f['id']], $st->fetchAll());
}

/* Reescribe el orden de toda la lista: 10, 20, 30... Así no hay empates
   ni huecos raros, venga de donde venga */
function ane_pod_renumera(PDO $bd, array $lista): void
{
    foreach (array_values($lista) as $i => [$tabla, $id]) {
        $bd->prepare('UPDATE ' . ane_pod_tabla($tabla) . ' SET orden = ? WHERE id = ?')
           ->execute([($i + 1) * 10, $id]);
    }
}

/* Lo nuevo entra al final del guión (antes de «Bici o Cepo») */
function ane_pod_siguiente_orden(PDO $bd, int $episodio): int
{
    return (count(ane_pod_piezas($bd, $episodio)) + 1) * 10;
}

/* Las únicas tablas que se pueden ordenar desde la web */
function ane_pod_tabla(string $tabla): string
{
    if (!in_array($tabla, ['pod_bloques', 'pod_menciones'], true)) {
        throw new InvalidArgumentException('tabla no permitida');
    }
    return $tabla;
}

/* Sube o baja una pieza un puesto, sea tema o patrocinio */
function ane_pod_mueve(PDO $bd, int $episodio, string $tabla, int $id, string $hacia): void
{
    $lista = ane_pod_piezas($bd, $episodio);
    $pos = array_search([$tabla, $id], $lista, true);
    if ($pos === false) {
        return;
    }
    $otra = $hacia === 'arriba' ? $pos - 1 : $pos + 1;
    if ($otra < 0 || $otra >= count($lista)) {
        return;
    }
    [$lista[$pos], $lista[$otra]] = [$lista[$otra], $lista[$pos]];
    ane_pod_renumera($bd, $lista);
}

/* Coloca una pieza justo detrás de otra. $despues = '' la pone al
   principio; 'final' la deja al final; si no, «pod_bloques:12» */
function ane_pod_coloca(PDO $bd, int $episodio, string $tabla, int $id, string $despues): void
{
    $lista = ane_pod_piezas($bd, $episodio);
    $pos = array_search([$tabla, $id], $lista, true);
    if ($pos === false) {
        return;
    }
    $pieza = $lista[$pos];
    array_splice($lista, $pos, 1);
    if ($despues === 'final') {
        $lista[] = $pieza;
    } elseif ($despues === '') {
        array_unshift($lista, $pieza);
    } else {
        [$t, $i] = array_pad(explode(':', $despues, 2), 2, '');
        $donde = array_search([$t, (int) $i], $lista, true);
        if ($donde === false) {
            return;   /* destino que no está en este guión: mejor no tocar nada */
        }
        array_splice($lista, $donde + 1, 0, [$pieza]);
    }
    ane_pod_renumera($bd, $lista);
}

/* Un tema que no da tiempo pasa TAL CUAL al episodio siguiente (número + 1),
   con sus enlaces, fotos, archivos y notas, y entra al final de su guión,
   antes de «Bici o Cepo». Si ese episodio no existe, se crea. Devuelve el
   episodio de destino, o null si no se puede (no es un tema, o el episodio
   no tiene número y no hay «siguiente»). */
function ane_pod_pasa_tema(PDO $bd, int $bloque): ?array
{
    $st = $bd->prepare("SELECT b.id, b.episodio_id, e.numero FROM pod_bloques b
                        JOIN pod_episodios e ON e.id = b.episodio_id WHERE b.id = ? AND b.tipo = 'tema'");
    $st->execute([$bloque]);
    $b = $st->fetch();
    if (!$b || (int) $b['numero'] <= 0) {
        return null;
    }
    $origen = (int) $b['episodio_id'];
    $bd->beginTransaction();
    $destino = (int) ane_pod_episodio_numero($bd, (int) $b['numero'] + 1, true);
    $bd->prepare('UPDATE pod_bloques SET episodio_id = ?, descartado = 0, orden = 999998 WHERE id = ?')
       ->execute([$destino, $bloque]);
    foreach (['pod_enlaces', 'pod_ficheros', 'pod_notas'] as $t) {
        $bd->prepare("UPDATE $t SET episodio_id = ? WHERE bloque_id = ?")->execute([$destino, $bloque]);
    }
    ane_pod_coloca($bd, $destino, 'pod_bloques', $bloque, 'final');
    ane_pod_renumera($bd, ane_pod_piezas($bd, $origen));
    $ahora = ane_ahora();
    $bd->prepare('UPDATE pod_episodios SET actualizado = ? WHERE id IN (?, ?)')->execute([$ahora, $origen, $destino]);
    $bd->commit();
    return ane_pod_episodio($bd, $destino);
}

/* Subir o bajar un tema un puesto desde el guión, en plena grabación. Como
   ane_pod_mueve(), pero los temas descartados no cuentan: en el guión son
   una línea tachada y saltar por encima de ella sería un toque perdido. El
   tema se coloca justo antes (o después) de la primera pieza visible que
   tenga al lado, sea tema o cuña. Devuelve si se ha movido. */
function ane_pod_sube_baja_tema(PDO $bd, int $episodio, int $bloque, string $hacia): bool
{
    $lista = ane_pod_piezas($bd, $episodio);
    $pos = array_search(['pod_bloques', $bloque], $lista, true);
    if ($pos === false) {
        return false;
    }
    $st = $bd->prepare('SELECT id FROM pod_bloques WHERE episodio_id = ? AND descartado = 1');
    $st->execute([$episodio]);
    $fuera = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    $paso = $hacia === 'arriba' ? -1 : 1;
    for ($j = $pos + $paso; $j >= 0 && $j < count($lista); $j += $paso) {
        if (!($lista[$j][0] === 'pod_bloques' && in_array($lista[$j][1], $fuera, true))) {
            break;
        }
    }
    if ($j < 0 || $j >= count($lista)) {
        return false;   /* ya es el primero (o el último) */
    }
    $pieza = $lista[$pos];
    array_splice($lista, $pos, 1);
    $donde = $j > $pos ? $j - 1 : $j;   /* lo que había detrás se ha corrido un puesto */
    array_splice($lista, $hacia === 'arriba' ? $donde : $donde + 1, 0, [$pieza]);
    ane_pod_renumera($bd, $lista);
    return true;
}

/* Descartar (o recuperar) un tema. No se borra nada: sale del guión del
   editor y, para los socios, queda tachado con «Recuperar». Devuelve el tema. */
function ane_pod_descarta_tema(PDO $bd, int $bloque, bool $descartar): ?array
{
    $bd->prepare("UPDATE pod_bloques SET descartado = ? WHERE id = ? AND tipo = 'tema'")
       ->execute([$descartar ? 1 : 0, $bloque]);
    $st = $bd->prepare("SELECT * FROM pod_bloques WHERE id = ? AND tipo = 'tema'");
    $st->execute([$bloque]);
    return $st->fetch() ?: null;
}

/* El guión que se pinta: temas y patrocinios mezclados en su orden, y
   «Bici o Cepo» al final. Es el mismo para socios y editor, con una
   diferencia: con $soloPublicos (el editor) los temas internos ni se piden.
   Las cuñas sí van: el editor las tiene que montar. */
function ane_pod_guion(PDO $bd, int $episodio, bool $soloPublicos = false): array
{
    $piezas = [];
    $cierre = null;
    foreach (ane_pod_bloques($bd, $episodio, $soloPublicos) as $b) {
        if ($b['tipo'] === ANE_POD_CIERRE) {
            $cierre = ['clase' => 'cierre', 'fila' => $b];
        } else {
            $piezas[] = ['clase' => 'tema', 'fila' => $b];
        }
    }
    foreach (ane_pod_menciones($bd, $episodio) as $m) {
        $piezas[] = ['clase' => 'patrocinio', 'fila' => $m];
    }
    usort($piezas, fn ($a, $b) => [(int) $a['fila']['orden'], $a['clase'] === 'patrocinio', (int) $a['fila']['id']]
                              <=> [(int) $b['fila']['orden'], $b['clase'] === 'patrocinio', (int) $b['fila']['id']]);
    if ($cierre) {
        $piezas[] = $cierre;
    }
    return $piezas;
}

/* Enlaces pegados en un cuadro de texto, uno por línea. Vale la dirección
   sola o con su nombre delante: «Meta-análisis 2024 https://…» o
   «Meta-análisis 2024 | https://…». Devuelve [[titulo, url], ...] y
   cuántas líneas no se entendieron. */
function ane_pod_lineas_enlaces(string $texto): array
{
    $buenos = [];
    $malos = 0;
    foreach (preg_split('/\R/u', $texto) as $linea) {
        $linea = trim($linea);
        if ($linea === '') {
            continue;
        }
        if (preg_match('~^(.*?)\s*[|·:–—-]?\s*((?:https?://|www\.)\S+)\s*$~iu', $linea, $m)) {
            $url = ane_pod_url($m[2]);
            $titulo = preg_replace('/^[\s|·:–—-]+|[\s|·:–—-]+$/u', '', $m[1]) ?? '';
        } elseif (preg_match('~^[\w.-]+\.[a-z]{2,}(?:[/?#]\S*)?$~iu', $linea)) {
            $url = ane_pod_url($linea);   /* «pubmed.ncbi.nlm.nih.gov/…» sin http */
            $titulo = '';
        } else {
            $url = '';                    /* «hola» no es un enlace aunque https://hola valide */
            $titulo = '';
        }
        if ($url === '') {
            $malos++;
            continue;
        }
        $buenos[] = [ane_recorta($titulo, 200), $url];
    }
    return [array_slice($buenos, 0, 30), $malos];
}

/* Añade a un tema los enlaces de un cuadro de texto. Devuelve [añadidos, malos]. */
function ane_pod_mete_enlaces(PDO $bd, int $episodio, int $bloque, string $texto, string $tituloSuelto = ''): array
{
    [$lista, $malos] = ane_pod_lineas_enlaces($texto);
    /* Si es uno solo y viene un nombre aparte, ese nombre es el suyo */
    if (count($lista) === 1 && $lista[0][0] === '' && trim($tituloSuelto) !== '') {
        $lista[0][0] = ane_recorta($tituloSuelto, 200);
    }
    $st = $bd->prepare('SELECT COALESCE(MAX(orden), 0) FROM pod_enlaces WHERE bloque_id = ?');
    $st->execute([$bloque]);
    $orden = (int) $st->fetchColumn();
    $ins = $bd->prepare('INSERT INTO pod_enlaces (episodio_id, bloque_id, titulo, url, orden) VALUES (?, ?, ?, ?, ?)');
    foreach ($lista as [$titulo, $url]) {
        $orden += 10;
        $ins->execute([$episodio, $bloque, $titulo, $url, $orden]);
    }
    return [count($lista), $malos];
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

/* ---------- Notas de grabación ---------- */

/* Las notas de un episodio, agrupadas por tema (0 = generales). Con
   $paraEditor, solo las marcadas para él: las demás ni se leen. */
function ane_pod_notas(PDO $bd, int $episodio, bool $paraEditor = false): array
{
    $st = $bd->prepare('SELECT * FROM pod_notas WHERE episodio_id = ?' . ($paraEditor ? ' AND editor = 1' : '')
                       . ' ORDER BY id');
    $st->execute([$episodio]);
    $fuera = [];
    foreach ($st->fetchAll() as $n) {
        $fuera[(int) $n['bloque_id']][] = $n;
    }
    return $fuera;
}

function ane_pod_nota(PDO $bd, int $id): ?array
{
    $st = $bd->prepare('SELECT * FROM pod_notas WHERE id = ?');
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

/* La dirección de un fichero. Lleva una marca de ESE fichero (el principio de
   su nombre en disco, que es aleatorio): SQLite reutiliza el número de la
   última fila borrada, y sin la marca una foto nueva heredaría la dirección
   de la que se borró… y el navegador, que la tenía guardada, enseñaba la vieja.
   Pasó el 20/10/2026 en «Bici o Cepo». */
function ane_pod_url_fichero(array $f, bool $descarga = false): string
{
    return '/estudio/fichero.php?f=' . (int) $f['id'] . '&amp;v=' . substr((string) $f['fichero'], 0, 10)
         . ($descarga ? '&amp;d=1' : '');
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

/* Las cuñas de patrocinio de un episodio: marca, tipo, qué hay que decir y si
   ya está hecha. Nada de contactos ni importes: eso vive en pod_marcas y en
   pod_proy_marcas, que el editor no toca nunca. */
function ane_pod_menciones(PDO $bd, int $episodio): array
{
    $st = $bd->prepare('SELECT * FROM pod_menciones WHERE episodio_id = ? ORDER BY orden, id');
    $st->execute([$episodio]);
    return $st->fetchAll();
}

/* ---------- Tipos de cuña (se pueden marcar varios) ---------- */

/* Lo que llegue (casillas del formulario, «a,b» de la base o un tipo viejo) →
   lista limpia de tipos válidos, en el orden de ANE_POD_MENCION_TIPOS */
function ane_pod_tipos($valor): array
{
    $lista = is_array($valor) ? $valor : explode(',', (string) $valor);
    $limpia = [];
    foreach ($lista as $t) {
        $t = trim((string) $t);
        $t = ANE_POD_TIPOS_VIEJOS[$t] ?? $t;
        if (isset(ANE_POD_MENCION_TIPOS[$t])) {
            $limpia[$t] = true;
        }
    }
    return array_values(array_filter(array_keys(ANE_POD_MENCION_TIPOS), fn ($k) => isset($limpia[$k])));
}

function ane_pod_tipos_csv($valor): string
{
    return implode(',', ane_pod_tipos($valor));
}

/* «Anuncio explícito + Product placement» */
function ane_pod_tipos_texto(string $csv): string
{
    return implode(' + ', array_map(fn ($t) => ANE_POD_MENCION_TIPOS[$t], ane_pod_tipos($csv)));
}

/* Las casillas para elegir tipos. $id hace únicos los «for» de las etiquetas. */
function ane_pod_casillas_tipo(string $csv, string $id): string
{
    $marcados = ane_pod_tipos($csv);
    $html = '<div class="casillas-tipo">';
    foreach (ANE_POD_MENCION_TIPOS as $k => $v) {
        $html .= '<label class="cas" for="' . ane_h($id . $k) . '"><input type="checkbox" name="tipo[]" value="' . $k
               . '" id="' . ane_h($id . $k) . '"' . (in_array($k, $marcados, true) ? ' checked' : '') . '> '
               . ane_h($v) . '</label>';
    }
    return $html . '</div>';
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

/* El coste de un proyecto es la suma de sus gastos, EN CÉNTIMOS: va en la
   columna «gastos_cent» (la vieja «coste» ya no se usa, ver pod_gastos_v1) */
const ANE_POD_SUMA_GASTOS = '(SELECT COALESCE(SUM(centimos), 0) FROM pod_gastos g WHERE g.proyecto_id = p.id) AS gastos_cent';

function ane_pod_proyectos(PDO $bd): array
{
    return $bd->query('SELECT p.*, ' . ANE_POD_SUMA_GASTOS . ' FROM pod_proyectos p ORDER BY fijo DESC, orden, id')->fetchAll();
}

/* El proyecto «Podcast» existe siempre: ahí van las marcas que patrocinan
   episodios sueltos. Si no está, se crea; devuelve su id. */
function ane_pod_proyecto_podcast(PDO $bd): int
{
    $id = (int) $bd->query('SELECT id FROM pod_proyectos WHERE fijo = 1 ORDER BY id LIMIT 1')->fetchColumn();
    if ($id) {
        return $id;
    }
    $ahora = ane_ahora();
    $bd->prepare("INSERT INTO pod_proyectos (nombre, detalle, estado, descripcion, orden, fijo, creado, actualizado)
                  VALUES ('Podcast', 'Marcas que patrocinan episodios', 'abierto',
                          'Proyecto fijo: los patrocinios de episodios sueltos del podcast.', 0, 1, ?, ?)")
       ->execute([$ahora, $ahora]);
    return (int) $bd->lastInsertId();
}

function ane_pod_proyecto(PDO $bd, int $id): ?array
{
    $st = $bd->prepare('SELECT p.*, ' . ANE_POD_SUMA_GASTOS . ' FROM pod_proyectos p WHERE id = ?');
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

/* ---------- Gastos de un proyecto ---------- */

/* Ideas para el concepto: estas, más las que ya se hayan usado */
const ANE_POD_CONCEPTOS = ['Alojamiento', 'Vuelos', 'Transporte', 'Inscripción', 'Comidas', 'Material',
                           'Producción', 'Seguro'];

function ane_pod_gastos(PDO $bd, int $proyecto): array
{
    $st = $bd->prepare('SELECT * FROM pod_gastos WHERE proyecto_id = ? ORDER BY id');
    $st->execute([$proyecto]);
    return $st->fetchAll();
}

function ane_pod_gasto(PDO $bd, int $id): ?array
{
    $st = $bd->prepare('SELECT * FROM pod_gastos WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/* Total, pagado y lo que queda por pagar, en céntimos */
function ane_pod_suma_gastos(array $gastos): array
{
    $s = ['total' => 0, 'pagado' => 0, 'pendiente' => 0];
    foreach ($gastos as $g) {
        $s['total'] += (int) $g['centimos'];
        $s[(int) $g['pagado'] ? 'pagado' : 'pendiente'] += (int) $g['centimos'];
    }
    return $s;
}

function ane_pod_conceptos_gasto(PDO $bd): array
{
    $usados = $bd->query("SELECT DISTINCT concepto FROM pod_gastos WHERE concepto <> 'Sin desglosar'
                          ORDER BY concepto")->fetchAll(PDO::FETCH_COLUMN);
    return array_values(array_unique(array_merge(ANE_POD_CONCEPTOS, $usados)));
}

/* Lo que llega del formulario, ya limpio. El campo es numérico: llega con
   punto decimal («312.4»); por si acaso, también vale la coma. En céntimos */
function ane_pod_datos_gasto(array $post): array
{
    $importe = str_replace(',', '.', (string) ($post['importe'] ?? '0'));
    return [
        'concepto' => ane_recorta((string) ($post['concepto'] ?? ''), 120),
        'centimos' => max(0, min(1000000000, (int) round((float) $importe * 100))),
        'pagado'   => empty($post['pagado']) ? 0 : 1,
        'nota'     => ane_recorta((string) ($post['nota'] ?? ''), 500),
    ];
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

/* ---------- Acuerdos: la marca, el proyecto, el dinero y los episodios ---------- */

/* Solo los acuerdos firmados ponen cuñas en los episodios */
const ANE_POD_FIRMADO = ['confirmado', 'facturado'];
const ANE_POD_MAX_EPISODIOS = 60;   /* por acuerdo: más es casi seguro un error al teclear */

/* «10-13, 20 21; 30» → [10, 11, 12, 13, 20, 21, 30]. Ordenados y sin repetir. */
function ane_pod_numeros(string $texto): array
{
    $fuera = [];
    foreach (preg_split('/[\s,;]+/u', str_replace(['–', '—'], '-', trim($texto))) as $trozo) {
        if (preg_match('/^(\d{1,4})\s*-\s*(\d{1,4})$/', $trozo, $m)) {
            [$a, $b] = [(int) $m[1], (int) $m[2]];
            if ($a > $b) {
                [$a, $b] = [$b, $a];
            }
            for ($n = $a; $n <= $b && count($fuera) <= ANE_POD_MAX_EPISODIOS; $n++) {
                $fuera[] = $n;
            }
        } elseif (preg_match('/^\d{1,4}$/', $trozo)) {
            $fuera[] = (int) $trozo;
        }
    }
    $fuera = array_values(array_unique(array_filter($fuera, fn ($n) => $n >= 1 && $n <= 9999)));
    sort($fuera);
    return array_slice($fuera, 0, ANE_POD_MAX_EPISODIOS);
}

/* [10, 11, 12, 13, 20] → «10-13, 20» */
function ane_pod_texto_numeros(array $numeros): string
{
    $numeros = array_values(array_unique(array_map('intval', $numeros)));
    sort($numeros);
    $tramos = [];
    foreach ($numeros as $n) {
        $ultimo = count($tramos) - 1;
        if ($ultimo >= 0 && $n === $tramos[$ultimo][1] + 1) {
            $tramos[$ultimo][1] = $n;
        } else {
            $tramos[] = [$n, $n];
        }
    }
    return implode(', ', array_map(fn ($t) => $t[0] === $t[1] ? (string) $t[0] : $t[0] . '-' . $t[1], $tramos));
}

/* El episodio con ese número; si no existe y se pide, se crea vacío («Episodio 12») */
function ane_pod_episodio_numero(PDO $bd, int $numero, bool $crear = false): ?int
{
    $st = $bd->prepare('SELECT id FROM pod_episodios WHERE numero = ? ORDER BY id LIMIT 1');
    $st->execute([$numero]);
    $id = (int) $st->fetchColumn();
    if ($id || !$crear) {
        return $id ?: null;
    }
    $ahora = ane_ahora();
    $bd->prepare("INSERT INTO pod_episodios (numero, titulo, creado, actualizado) VALUES (?, '', ?, ?)")
       ->execute([$numero, $ahora, $ahora]);
    $id = (int) $bd->lastInsertId();
    ane_pod_asegura_cierre($bd, $id);
    return $id;
}

function ane_pod_acuerdo(PDO $bd, int $id): ?array
{
    $st = $bd->prepare('SELECT pm.*, COALESCE(NULLIF(m.nombre, \'\'), pm.marca) AS marca_nombre, p.nombre AS proyecto
                        FROM pod_proy_marcas pm
                        LEFT JOIN pod_marcas m ON m.id = pm.marca_id
                        LEFT JOIN pod_proyectos p ON p.id = pm.proyecto_id
                        WHERE pm.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/* Pone los episodios y las cuñas de un acuerdo en su sitio:
   - firmado: crea los episodios que falten y les mete la cuña (al principio
     del guión, desde ahí se coloca a mano); a las cuñas pendientes les copia
     marca, tipo y texto del acuerdo, que manda;
   - lo que ya no toca (episodio quitado, o acuerdo que deja de estar
     firmado): se quitan sus cuñas, SALVO las hechas, que ya se grabaron.
   Nunca borra episodios: pueden tener guión. */
function ane_pod_sincroniza_acuerdo(PDO $bd, int $acuerdoId): void
{
    $a = ane_pod_acuerdo($bd, $acuerdoId);
    if (!$a) {
        return;
    }
    $firmado = in_array($a['estado'], ANE_POD_FIRMADO, true);
    $quiere = [];
    foreach ($firmado ? ane_pod_numeros((string) $a['episodios']) : [] as $n) {
        $quiere[ane_pod_episodio_numero($bd, $n, true)] = true;
    }
    $tipo = ane_pod_tipos_csv((string) $a['tipo']);

    $st = $bd->prepare('SELECT id, episodio_id, hecho FROM pod_menciones WHERE acuerdo_id = ?');
    $st->execute([$acuerdoId]);
    $tiene = [];
    foreach ($st->fetchAll() as $c) {
        $ep = (int) $c['episodio_id'];
        if (!isset($quiere[$ep]) || isset($tiene[$ep])) {
            /* sobra (o está repetida): fuera, salvo que ya se haya grabado */
            if (!(int) $c['hecho']) {
                $bd->prepare('DELETE FROM pod_menciones WHERE id = ?')->execute([(int) $c['id']]);
                continue;
            }
        } elseif (!(int) $c['hecho']) {
            $bd->prepare('UPDATE pod_menciones SET marca_id = ?, marca = ?, tipo = ?, texto = ? WHERE id = ?')
               ->execute([(int) $a['marca_id'], (string) $a['marca_nombre'], $tipo, (string) $a['texto'], (int) $c['id']]);
        }
        $tiene[$ep] = true;
    }
    foreach (array_keys($quiere) as $ep) {
        if (isset($tiene[$ep])) {
            continue;
        }
        $bd->prepare('INSERT INTO pod_menciones (episodio_id, marca_id, marca, tipo, texto, orden, acuerdo_id)
                      VALUES (?, ?, ?, ?, ?, ?, ?)')
           ->execute([$ep, (int) $a['marca_id'], (string) $a['marca_nombre'], $tipo, (string) $a['texto'],
                      ane_pod_siguiente_orden($bd, $ep), $acuerdoId]);
        ane_pod_coloca($bd, $ep, 'pod_menciones', (int) $bd->lastInsertId(), '');
    }
}

/* Lo que llega de un formulario de acuerdo, limpio. Por defecto, firmado:
   un acuerdo se apunta cuando se firma; antes, la marca va en la agenda. */
function ane_pod_datos_acuerdo(array $post): array
{
    return [
        'estado'         => isset(ANE_POD_PATRO_ESTADOS[(string) ($post['estado'] ?? '')]) ? (string) $post['estado'] : 'confirmado',
        'importe'        => max(0, min(10000000, (int) ($post['importe'] ?? 0))),
        'episodios'      => ane_pod_texto_numeros(ane_pod_numeros((string) ($post['episodios'] ?? ''))),
        'tipo'           => ane_pod_tipos_csv($post['tipo'] ?? ''),
        'texto'          => ane_recorta((string) ($post['texto'] ?? ''), 4000),
        'contrapartidas' => ane_recorta((string) ($post['contrapartidas'] ?? ''), 2000),
        'notas'          => ane_recorta((string) ($post['notas'] ?? ''), 2000),
    ];
}

/* Apunta un acuerdo y pone sus episodios y cuñas. Si viene de la agenda y
   está firmado, la marca pasa a «confirmado» (salvo que ya esté facturada).
   Devuelve el id. */
function ane_pod_crea_acuerdo(PDO $bd, int $proyecto, int $marcaId, string $marca, array $d): int
{
    $bd->beginTransaction();
    $bd->prepare('INSERT INTO pod_proy_marcas (proyecto_id, marca_id, marca, estado, importe, contrapartidas, notas,
                                               episodios, tipo, texto)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
       ->execute([$proyecto, $marcaId, $marca, $d['estado'], $d['importe'], $d['contrapartidas'], $d['notas'],
                  $d['episodios'], $d['tipo'], $d['texto']]);
    $id = (int) $bd->lastInsertId();
    ane_pod_sincroniza_acuerdo($bd, $id);
    if ($marcaId && in_array($d['estado'], ANE_POD_FIRMADO, true)) {
        $bd->prepare("UPDATE pod_marcas SET estado = 'confirmado', actualizado = ?
                      WHERE id = ? AND estado IN ('contactado', 'negociando', 'descartado')")->execute([ane_ahora(), $marcaId]);
    }
    $bd->commit();
    return $id;
}

function ane_pod_actualiza_acuerdo(PDO $bd, int $id, array $d): void
{
    $bd->beginTransaction();
    $bd->prepare('UPDATE pod_proy_marcas SET estado = ?, importe = ?, contrapartidas = ?, notas = ?,
                                             episodios = ?, tipo = ?, texto = ? WHERE id = ?')
       ->execute([$d['estado'], $d['importe'], $d['contrapartidas'], $d['notas'],
                  $d['episodios'], $d['tipo'], $d['texto'], $id]);
    ane_pod_sincroniza_acuerdo($bd, $id);
    $bd->commit();
}

/* Para que la lista del acuerdo diga siempre la verdad cuando se toca algo
   desde un episodio: */

/* … se quita a mano una cuña que venía de un acuerdo: ese episodio sale de su lista */
function ane_pod_quita_de_acuerdo(PDO $bd, int $acuerdoId, int $numero): void
{
    $a = ane_pod_acuerdo($bd, $acuerdoId);
    if ($a && $numero > 0) {
        $lista = array_diff(ane_pod_numeros((string) $a['episodios']), [$numero]);
        $bd->prepare('UPDATE pod_proy_marcas SET episodios = ? WHERE id = ?')
           ->execute([ane_pod_texto_numeros($lista), $acuerdoId]);
    }
}

/* … a un episodio le cambian el número: sus acuerdos apuntan al número nuevo */
function ane_pod_renumera_en_acuerdos(PDO $bd, int $episodio, int $viejo, int $nuevo): void
{
    if ($viejo === $nuevo) {
        return;
    }
    $st = $bd->prepare('SELECT DISTINCT acuerdo_id FROM pod_menciones WHERE episodio_id = ? AND acuerdo_id > 0');
    $st->execute([$episodio]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $acuerdoId) {
        $a = ane_pod_acuerdo($bd, (int) $acuerdoId);
        if (!$a) {
            continue;
        }
        $lista = array_diff(ane_pod_numeros((string) $a['episodios']), [$viejo]);
        if ($nuevo > 0) {
            $lista[] = $nuevo;
        }
        $bd->prepare('UPDATE pod_proy_marcas SET episodios = ? WHERE id = ?')
           ->execute([ane_pod_texto_numeros($lista), (int) $acuerdoId]);
    }
}

/* … se borra el acuerdo: sus cuñas pendientes se van, las hechas se quedan sueltas */
function ane_pod_borra_acuerdo(PDO $bd, int $acuerdoId): void
{
    /* El contrato no se pierde: se queda en la marca, sin acuerdo */
    $bd->prepare('UPDATE pod_contratos SET acuerdo_id = 0 WHERE acuerdo_id = ?')->execute([$acuerdoId]);
    $bd->prepare('DELETE FROM pod_menciones WHERE acuerdo_id = ? AND hecho = 0')->execute([$acuerdoId]);
    $bd->prepare('UPDATE pod_menciones SET acuerdo_id = 0 WHERE acuerdo_id = ?')->execute([$acuerdoId]);
    $bd->prepare('DELETE FROM pod_proy_marcas WHERE id = ?')->execute([$acuerdoId]);
}

/* Los episodios que tiene de verdad un acuerdo, con su número y si la cuña ya está hecha */
function ane_pod_episodios_de_acuerdo(PDO $bd, int $acuerdoId): array
{
    $st = $bd->prepare('SELECT e.id, e.numero, e.titulo, mc.hecho FROM pod_menciones mc
                        JOIN pod_episodios e ON e.id = mc.episodio_id
                        WHERE mc.acuerdo_id = ? ORDER BY e.numero, e.id');
    $st->execute([$acuerdoId]);
    return $st->fetchAll();
}

/* ---------- Contratos ---------- */

/* Lo que se acepta como contrato, y cómo se reconoce por dentro (no por el nombre) */
const ANE_POD_CONTRATO_TIPOS = [
    'pdf'  => ['application/pdf', "%PDF-"],
    'jpg'  => ['image/jpeg', "\xFF\xD8\xFF"],
    'jpeg' => ['image/jpeg', "\xFF\xD8\xFF"],
    'png'  => ['image/png', "\x89PNG"],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', "PK\x03\x04"],
    'odt'  => ['application/vnd.oasis.opendocument.text', "PK\x03\x04"],
    'doc'  => ['application/msword', "\xD0\xCF\x11\xE0"],
];
const ANE_POD_CONTRATO_MAX = 32 * 1024 * 1024;

function ane_pod_carpeta_contratos(): string
{
    $d = ANE_DATOS . '/estudio/contratos';
    if (!is_dir($d)) {
        mkdir($d, 0700, true);
    }
    return $d;
}

function ane_pod_contrato(PDO $bd, int $id): ?array
{
    $st = $bd->prepare('SELECT * FROM pod_contratos WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/* Los contratos de una marca (con el proyecto de su acuerdo, si lo tiene) o de un acuerdo */
function ane_pod_contratos(PDO $bd, string $de, int $id): array
{
    $campo = $de === 'acuerdo' ? 'c.acuerdo_id' : 'c.marca_id';
    $st = $bd->prepare("SELECT c.*, p.nombre AS proyecto FROM pod_contratos c
                        LEFT JOIN pod_proy_marcas pm ON pm.id = c.acuerdo_id
                        LEFT JOIN pod_proyectos p ON p.id = pm.proyecto_id
                        WHERE $campo = ? ORDER BY c.id DESC");
    $st->execute([$id]);
    return $st->fetchAll();
}

/* Guarda un contrato subido. Devuelve '' si fue bien o el motivo (los de
   ane_pod_motivo_fichero). Se comprueba por dentro que el fichero sea lo
   que dice su extensión: un .pdf que no empieza por «%PDF-» no entra. */
function ane_pod_guarda_contrato(PDO $bd, int $marcaId, int $acuerdoId, array $f): string
{
    $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        return 'vacio';
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || (int) ($f['size'] ?? 0) > ANE_POD_CONTRATO_MAX) {
        return 'grande';
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($f['tmp_name'] ?? ''))) {
        return 'subida';
    }
    $original = ane_pod_nombre_fichero((string) ($f['name'] ?? ''));
    $ext = ane_pod_extension($original);
    if (!isset(ANE_POD_CONTRATO_TIPOS[$ext])) {
        return 'formato';
    }
    [$mime, $firma] = ANE_POD_CONTRATO_TIPOS[$ext];
    $cabeza = (string) @file_get_contents((string) $f['tmp_name'], false, null, 0, 8);
    if (strncmp($cabeza, $firma, strlen($firma)) !== 0) {
        return 'formato';
    }
    $nombre = bin2hex(random_bytes(16)) . '.' . $ext;
    $destino = ane_pod_carpeta_contratos() . '/' . $nombre;
    if (!move_uploaded_file($f['tmp_name'], $destino)) {
        return 'disco';
    }
    chmod($destino, 0600);
    $bd->prepare('INSERT INTO pod_contratos (marca_id, acuerdo_id, fichero, nombre, mime, tamano, creado)
                  VALUES (?, ?, ?, ?, ?, ?, ?)')
       ->execute([$marcaId, $acuerdoId, $nombre, $original ?: 'contrato.' . $ext, $mime,
                  (int) filesize($destino), ane_ahora()]);
    return '';
}

function ane_pod_borra_contrato(PDO $bd, array $c): void
{
    $ruta = ane_pod_carpeta_contratos() . '/' . basename((string) $c['fichero']);
    if (is_file($ruta)) {
        @unlink($ruta);
    }
    $bd->prepare('DELETE FROM pod_contratos WHERE id = ?')->execute([(int) $c['id']]);
}

/* ---------- Calendario: un episodio cada domingo, sin parar ---------- */

/* El 56 sale el domingo 25/10/2026 y desde ahí, uno cada domingo: el número
   ya dice cuándo sale cada episodio (el 57, el 1/11; el 60, el 22/11…). Si
   algún día se salta una semana, se mueve el ancla en `ajustes`
   (pod_ancla_numero y pod_ancla_domingo) y todo se recoloca solo. */
const ANE_POD_ANCLA = [56, '2026-10-25'];

function ane_pod_ancla(): array
{
    static $ancla = null;
    if ($ancla === null) {
        $ancla = ANE_POD_ANCLA;
        try {
            $f = ane_bd()->query("SELECT clave, valor FROM ajustes WHERE clave IN ('pod_ancla_numero', 'pod_ancla_domingo')")
                         ->fetchAll(PDO::FETCH_KEY_PAIR);
            if ((int) ($f['pod_ancla_numero'] ?? 0) > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['pod_ancla_domingo'] ?? ''))) {
                $ancla = [(int) $f['pod_ancla_numero'], (string) $f['pod_ancla_domingo']];
            }
        } catch (Throwable $e) {
            /* sin base todavía: vale el ancla de serie */
        }
    }
    return $ancla;
}

/* El domingo en que sale un episodio: por su número si lo tiene; si no, el
   domingo de la semana (de lunes a domingo) en que se graba */
function ane_pod_sale(array $ep): ?DateTime
{
    $numero = (int) ($ep['numero'] ?? 0);
    if ($numero > 0) {
        [$n, $domingo] = ane_pod_ancla();
        $d = new DateTime($domingo, new DateTimeZone('Europe/Madrid'));
        return $d->modify(($numero - $n >= 0 ? '+' : '') . (($numero - $n) * 7) . ' days');
    }
    return ane_pod_domingo($ep['fecha'] ?? null);
}

/* «dom 25 oct»; si cae en otro año, «dom 7 mar 2027» */
function ane_pod_dia_corto(DateTime $d): string
{
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $anio = $d->format('Y') !== (new DateTime('now', new DateTimeZone('Europe/Madrid')))->format('Y') ? ' ' . $d->format('Y') : '';
    return 'dom ' . $d->format('j') . ' ' . $meses[(int) $d->format('n') - 1] . $anio;
}

/* El domingo de la semana (de lunes a domingo) de una fecha */
function ane_pod_domingo(?string $utc): ?DateTime
{
    if (!$utc) {
        return null;
    }
    $d = new DateTime($utc, new DateTimeZone('UTC'));
    $d->setTimezone(new DateTimeZone('Europe/Madrid'));
    $d->setTime(0, 0);
    $dia = (int) $d->format('N');          /* 1 = lunes … 7 = domingo */
    return $d->modify('+' . (7 - $dia) . ' days');
}

/* Pasado = ya ha llegado el lunes siguiente al domingo en que sale. Sin número
   ni fecha, no hay forma de saberlo: próximo. */
function ane_pod_ya_salio(array $ep, ?DateTime $ahora = null): bool
{
    $domingo = ane_pod_sale($ep);
    if (!$domingo) {
        return false;
    }
    $ahora = $ahora ?: new DateTime('now', new DateTimeZone('Europe/Madrid'));
    return $ahora >= (clone $domingo)->modify('+1 day');
}

/* Grabado (la hora de grabación ya pasó), aunque aún no haya salido */
function ane_pod_grabado(array $ep): bool
{
    return !empty($ep['fecha']) && strtotime((string) $ep['fecha'] . ' UTC') <= time();
}

/* ---------- Presentación ---------- */

function ane_pod_euros(int $n): string
{
    return number_format($n, 0, ',', '.') . ' €';
}

/* Céntimos → «1.450,50 €»; si son euros redondos, sin decimales: «4.500 €» */
function ane_pod_euros_c(int $c): string
{
    return number_format($c / 100, $c % 100 ? 2 : 0, ',', '.') . ' €';
}

/* Céntimos → valor para un <input type="number">: «1450.50» o «4500» */
function ane_pod_valor_c(int $c): string
{
    return $c % 100 ? sprintf('%d.%02d', intdiv($c, 100), $c % 100) : (string) intdiv($c, 100);
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
