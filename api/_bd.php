<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — acceso a la base de estadísticas
   ------------------------------------------------------------
   No se sirve por web: .htaccess bloquea los ficheros que
   empiezan por «_». Lo incluyen api/stats.php y admin/.

   Los datos viven FUERA del docroot y del repo (que es público):
     ~/datos/ane.db     base SQLite (se crea sola)
     ~/datos/admin.php  usuario y hash de la contraseña del panel
   ============================================================ */

declare(strict_types=1);

/* public_html/api → public_html → aquinadieentrena.cc → domains → ~ */
define('ANE_DATOS', dirname(__DIR__, 4) . '/datos');

function ane_bd(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!is_dir(ANE_DATOS) && !mkdir(ANE_DATOS, 0700, true)) {
        throw new RuntimeException('No se puede crear ' . ANE_DATOS);
    }
    $pdo = new PDO('sqlite:' . ANE_DATOS . '/ane.db', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 3000');

    /* Fechas en UTC «AAAA-MM-DD HH:MM:SS». Sin IP ni identificadores. */
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS busquedas (
            id         INTEGER PRIMARY KEY,
            fecha      TEXT    NOT NULL,
            termino    TEXT    NOT NULL,
            resultados INTEGER NOT NULL,
            voz        INTEGER NOT NULL,
            pagina     TEXT    NOT NULL
        );
        CREATE INDEX IF NOT EXISTS i_busquedas_fecha ON busquedas (fecha);

        CREATE TABLE IF NOT EXISTS clics (
            id         INTEGER PRIMARY KEY,
            fecha      TEXT    NOT NULL,
            termino    TEXT    NOT NULL,
            youtube_id TEXT    NOT NULL,
            segundo    INTEGER NOT NULL,
            pagina     TEXT    NOT NULL
        );
        CREATE INDEX IF NOT EXISTS i_clics_fecha ON clics (fecha);

        CREATE TABLE IF NOT EXISTS contactos (
            id      INTEGER PRIMARY KEY,
            fecha   TEXT NOT NULL,
            destino TEXT NOT NULL,   -- bici | marcas | tema | contacto
            pagina  TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS i_contactos_fecha ON contactos (fecha);
    ');
    return $pdo;
}

/* Minúsculas, espacios simples, sin caracteres de control, 80 máx. */
function ane_normaliza(string $s): string
{
    $s = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $s) ?? '';
    $s = preg_replace('/\s+/u', ' ', $s) ?? '';
    return mb_substr(mb_strtolower(trim($s), 'UTF-8'), 0, 80, 'UTF-8');
}

/* Lo que parezca un dato personal tecleado por error no se guarda:
   correos y ristras de 8+ cifras (teléfonos, DNI...). «30 40 50 mm»
   o «1.200 €» sí se guardan. */
function ane_parece_personal(string $s): bool
{
    return (bool) preg_match('/\S+@\S+\.\S+|(?:\d[\s.-]?){8,}/u', $s);
}
