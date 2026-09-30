<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — ofertas por los huecos del mono
   ------------------------------------------------------------
   Librería común de maillot.php (página pública /maillot) y
   admin/maillot.php (gestión). No se sirve por web (`_*.php`).

   Cada oferta es por un hueco del mono EN UN PROYECTO (una
   carrera: Cape Epic, The Traka...). Los precios, los cierres y
   las ofertas van por proyecto.

   Las ofertas NO son vinculantes: son una forma de que una marca
   diga cuánto vale para ella un hueco. En la web solo se publica
   la cifra más alta de cada hueco, nunca quién la ha hecho.

   Cada oferta se comprueba y se guarda en la misma transacción:
   si dos marcas ofrecen a la vez, la segunda se compara ya con la
   primera y no puede entrar por debajo.
   ============================================================ */

declare(strict_types=1);
require_once __DIR__ . '/_formularios.php';   // ane_h, sellos, fechas, texto con formato

/* Zonas del mono. La posición de cada una está en assets/js/maillot/mono.js
   con la MISMA clave: si añades una aquí, dale sitio allí. */
const ANE_MAILLOT_ZONAS = [
    'pecho'        => ['Pecho',             'Delante, a la altura del pecho. El sitio que más se ve.'],
    'abdomen'      => ['Abdomen',           'Delante, justo debajo del pecho.'],
    'espalda-alta' => ['Espalda alta',      'Detrás, entre los omóplatos. Lo que ve la rueda de detrás.'],
    'espalda-baja' => ['Espalda baja',      'Detrás, sobre los riñones.'],
    'costado-izq'  => ['Costado izquierdo', 'Lateral, sobre el negro, bajo el brazo izquierdo.'],
    'costado-der'  => ['Costado derecho',   'Lateral, sobre el negro, bajo el brazo derecho.'],
    'manga-izq'    => ['Manga izquierda',   'Cara exterior de la manga.'],
    'manga-der'    => ['Manga derecha',     'Cara exterior de la manga.'],
    'culote-izq'   => ['Culotte izquierdo', 'Lateral del muslo, sobre el negro del culotte.'],
    'culote-der'   => ['Culotte derecho',   'Lateral del muslo, sobre el negro del culotte.'],
];

/* Proyectos con los que nace la sección (los de proyectos.html). Desde el
   panel se pueden renombrar, ocultar o añadir más. */
const ANE_MAILLOT_PROYECTOS = [
    'mediterranean-epic-mtb' => ['Mediterranean Epic MTB', 'Febrero 2027 · Castellón'],
    'cape-epic-2027'         => ['Cape Epic 2027',         'Marzo 2027 · Sudáfrica'],
    'the-traka-2027'         => ['The Traka 2027',         'Abril-mayo 2027 · Girona'],
    'swiss-epic-2027'        => ['Swiss Epic 2027',        'Agosto 2027 · Suiza'],
];

const ANE_MAILLOT_MAX_IMPORTE = 1000000;

const ANE_MAILLOT_AJUSTES = [
    'revision'    => '1',   // 1 = una oferta no cuenta hasta que la validas en el panel
    'titulo'      => 'Tu marca en nuestro mono',
    'intro'       => "Elige la carrera, gira el mono y pincha en el hueco donde quieres ver tu marca. Verás la oferta más alta de cada hueco y cuánto hace falta para superarla.\n\nAl cierre hablamos con la mejor oferta de cada hueco.",
    'condiciones' => "- Cada oferta es **por un proyecto**: tu marca va en el mono que llevamos en esa carrera y en el contenido que sale de ella.\n- Las ofertas **no son vinculantes** para ninguna de las dos partes: son la forma de decirnos cuánto vale para tu marca ese hueco.\n- Importes en euros y **sin IVA**.\n- Al cierre de cada hueco hablamos con la oferta más alta para cerrar el acuerdo. Podemos declinar cualquier oferta.\n- En la web solo se publica la cifra de la oferta más alta, nunca quién la ha hecho.",
];

/* ---------- Ajustes ---------- */

function ane_maillot_ajustes(PDO $bd): array
{
    $a = ANE_MAILLOT_AJUSTES;
    foreach ($bd->query('SELECT clave, valor FROM ajustes') as $r) {
        if (array_key_exists($r['clave'], $a)) {
            $a[$r['clave']] = $r['valor'];
        }
    }
    return $a;
}

function ane_maillot_guarda_ajustes(PDO $bd, array $nuevos): void
{
    $st = $bd->prepare('INSERT INTO ajustes (clave, valor) VALUES (?, ?)
                        ON CONFLICT(clave) DO UPDATE SET valor = excluded.valor');
    foreach ($nuevos as $k => $v) {
        if (array_key_exists($k, ANE_MAILLOT_AJUSTES)) {
            $st->execute([$k, (string) $v]);
        }
    }
}

/* ---------- Proyectos ---------- */

/* Todos, en su orden. $soloActivos: los que salen en la web */
function ane_maillot_proyectos(PDO $bd, bool $soloActivos = false): array
{
    $ins = $bd->prepare('INSERT OR IGNORE INTO mono_proyectos (slug, nombre, detalle, orden) VALUES (?, ?, ?, ?)');
    $n = 0;
    foreach (ANE_MAILLOT_PROYECTOS as $slug => [$nombre, $detalle]) {
        $ins->execute([$slug, $nombre, $detalle, 10 * ++$n]);
    }
    $out = [];
    foreach ($bd->query('SELECT * FROM mono_proyectos ORDER BY orden, rowid') as $p) {
        if (!$soloActivos || (int) $p['activo']) {
            $out[$p['slug']] = $p;
        }
    }
    return $out;
}

function ane_maillot_slug(string $s): string
{
    $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s;
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
}

/* ---------- Huecos ---------- */

/* Los huecos de un proyecto, en el orden del catálogo, con sus cifras:
   maxima (oferta válida más alta o null), validas, pendientes, total. */
function ane_maillot_huecos(PDO $bd, string $proyecto): array
{
    $ins = $bd->prepare('INSERT OR IGNORE INTO mono_huecos (proyecto, zona, nombre, descripcion, actualizado)
                         VALUES (?, ?, ?, ?, ?)');
    foreach (ANE_MAILLOT_ZONAS as $zona => [$nombre, $desc]) {
        $ins->execute([$proyecto, $zona, $nombre, $desc, ane_ahora()]);
    }
    $filas = [];
    $st = $bd->prepare('SELECT * FROM mono_huecos WHERE proyecto = ?');
    $st->execute([$proyecto]);
    foreach ($st as $h) {
        $filas[$h['zona']] = $h;
    }
    $cifras = [];
    $st = $bd->prepare("SELECT zona,
                               MAX(CASE WHEN estado = 'valida' THEN importe END) AS maxima,
                               SUM(estado = 'valida')    AS validas,
                               SUM(estado = 'pendiente') AS pendientes,
                               COUNT(*)                  AS total
                        FROM mono_pujas WHERE proyecto = ? GROUP BY zona");
    $st->execute([$proyecto]);
    foreach ($st as $c) {
        $cifras[$c['zona']] = $c;
    }
    $out = [];
    foreach (array_keys(ANE_MAILLOT_ZONAS) as $zona) {
        if (!isset($filas[$zona])) {
            continue;
        }
        $h = $filas[$zona];
        $c = $cifras[$zona] ?? [];
        $h['maxima'] = isset($c['maxima']) ? (int) $c['maxima'] : null;
        $h['validas'] = (int) ($c['validas'] ?? 0);
        $h['pendientes'] = (int) ($c['pendientes'] ?? 0);
        $h['total'] = (int) ($c['total'] ?? 0);
        $out[$zona] = $h;
    }
    return $out;
}

/* oculto (no se ofrece) | adjudicado | cerrado (pasó la fecha o el proyecto
   no admite ofertas) | oferta | libre */
function ane_maillot_estado(array $h, bool $proyectoAbierto = true): string
{
    if (!(int) $h['activo']) {
        return 'oculto';
    }
    if (trim((string) $h['adjudicado']) !== '') {
        return 'adjudicado';
    }
    if (!$proyectoAbierto || ($h['cierre'] && $h['cierre'] <= ane_ahora())) {
        return 'cerrado';
    }
    return $h['maxima'] !== null ? 'oferta' : 'libre';
}

/* Lo mínimo que se puede ofrecer ahora mismo */
function ane_maillot_siguiente(array $h): int
{
    $min = max(1, (int) $h['minimo']);
    if ($h['maxima'] === null) {
        return $min;
    }
    return max($min, (int) $h['maxima'] + max(1, (int) $h['incremento']));
}

/* Lo que ve el público de cada hueco. Nada de quién ha ofertado. */
function ane_maillot_publico(array $h, bool $proyectoAbierto = true): array
{
    $estado = ane_maillot_estado($h, $proyectoAbierto);
    return [
        'zona'        => $h['zona'],
        'nombre'      => $h['nombre'],
        'descripcion' => $h['descripcion'],
        'estado'      => $estado,
        'minimo'      => max(1, (int) $h['minimo']),
        'maxima'      => $h['maxima'],
        'ofertas'     => $h['validas'],
        'siguiente'   => ane_maillot_siguiente($h),
        'cierre'      => $h['cierre'] ? ane_utc_a_local($h['cierre'], 'j/n/Y H:i') : null,
        'adjudicado'  => $estado === 'adjudicado' ? $h['adjudicado'] : null,
    ];
}

/* Lo que ve el público de todos los proyectos activos, con sus huecos */
function ane_maillot_datos_publicos(PDO $bd): array
{
    $out = [];
    foreach (ane_maillot_proyectos($bd, true) as $slug => $p) {
        $abierto = (bool) (int) $p['abierto'];
        $huecos = [];
        foreach (ane_maillot_huecos($bd, $slug) as $h) {
            $pub = ane_maillot_publico($h, $abierto);
            if ($pub['estado'] !== 'oculto') {
                $huecos[] = $pub;
            }
        }
        $out[] = ['slug' => $slug, 'nombre' => $p['nombre'], 'detalle' => $p['detalle'],
                  'abierto' => $abierto, 'huecos' => $huecos];
    }
    return $out;
}

function ane_maillot_euros(int $n): string
{
    return number_format($n, 0, ',', '.') . ' €';
}

/* «1.200», «1200 €», «1 200» → 1200. Céntimos no: null. */
function ane_maillot_importe(string $s): ?int
{
    $s = trim(str_replace(['€', 'EUR', 'eur', ' ', "\u{00A0}", '.'], '', $s));
    if (!preg_match('/^\d{1,9}$/', $s)) {
        return null;
    }
    return (int) $s;
}

/* ---------- Validación de lo que envía el público ---------- */

/* Devuelve [datos limpios, errores por campo] */
function ane_maillot_valida(array $post, array $proyectos): array
{
    $d = [
        'proyecto' => (string) ($post['proyecto'] ?? ''),
        'zona'     => (string) ($post['zona'] ?? ''),
        'importe'  => ane_recorta((string) ($post['importe'] ?? ''), 20),
        'empresa'  => ane_recorta((string) ($post['empresa'] ?? ''), 120),
        'contacto' => ane_recorta((string) ($post['contacto'] ?? ''), 120),
        'correo'   => ane_recorta((string) ($post['correo'] ?? ''), 160),
        'telefono' => ane_recorta((string) ($post['telefono'] ?? ''), 30),
        'mensaje'  => ane_recorta((string) ($post['mensaje'] ?? ''), 1000),
    ];
    $e = [];
    if (!isset($proyectos[$d['proyecto']])) {
        $e['proyecto'] = 'Elige un proyecto.';
    }
    if (!isset(ANE_MAILLOT_ZONAS[$d['zona']])) {
        $e['zona'] = 'Elige un hueco.';
    }
    $n = ane_maillot_importe($d['importe']);
    if ($d['importe'] === '') {
        $e['importe'] = 'Escribe cuánto ofreces.';
    } elseif ($n === null) {
        $e['importe'] = 'Escribe el importe en euros, sin céntimos (por ejemplo, 1500).';
    } elseif ($n > ANE_MAILLOT_MAX_IMPORTE) {
        $e['importe'] = 'Para una cifra así, mejor escríbenos a hola@aquinadieentrena.cc.';
    }
    $d['importe_n'] = $n;
    if (mb_strlen($d['empresa'], 'UTF-8') < 2) {
        $e['empresa'] = 'Dinos qué empresa o marca hace la oferta.';
    }
    if (!filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) {
        $e['correo'] = 'Escribe un correo válido: es por donde te contestamos.';
    }
    if ($d['telefono'] !== '') {
        $cifras = preg_replace('/\D/', '', $d['telefono']);
        if (!preg_match('/^\+?[\d\s().-]+$/', $d['telefono']) || strlen($cifras) < 9 || strlen($cifras) > 15) {
            $e['telefono'] = 'Escribe un teléfono válido o déjalo vacío.';
        }
    }
    if (empty($post['_acepto'])) {
        $e['_acepto'] = 'Tienes que aceptar las condiciones y el tratamiento de tus datos.';
    }
    return [$d, $e];
}

/* ---------- Guardar una oferta ---------- */

/* Resultado: ['ok' => true, 'estado' => 'valida'|'pendiente']
         o    ['ok' => false, 'motivo' => 'cerrado'|'bajo'|'saturado', 'siguiente' => int] */
function ane_maillot_oferta(PDO $bd, array $d): array
{
    $bd->exec('BEGIN IMMEDIATE');
    try {
        $ajustes = ane_maillot_ajustes($bd);
        $p = ane_maillot_proyectos($bd, true)[$d['proyecto']] ?? null;
        $h = $p ? (ane_maillot_huecos($bd, $d['proyecto'])[$d['zona']] ?? null) : null;
        $estado = $h ? ane_maillot_estado($h, (bool) (int) $p['abierto']) : 'oculto';
        if (!in_array($estado, ['libre', 'oferta'], true)) {
            $bd->exec('ROLLBACK');
            return ['ok' => false, 'motivo' => 'cerrado'];
        }
        if ($d['importe_n'] < ane_maillot_siguiente($h)) {
            $bd->exec('ROLLBACK');
            return ['ok' => false, 'motivo' => 'bajo', 'siguiente' => ane_maillot_siguiente($h)];
        }
        /* Freno contra avalanchas de bots: 20 ofertas por minuto en total */
        $recientes = (int) $bd->query("SELECT COUNT(*) FROM mono_pujas
                                       WHERE fecha >= datetime('now', '-1 minute')")->fetchColumn();
        if ($recientes >= 20) {
            $bd->exec('ROLLBACK');
            return ['ok' => false, 'motivo' => 'saturado'];
        }
        $nuevoEstado = (int) $ajustes['revision'] ? 'pendiente' : 'valida';
        $bd->prepare('INSERT INTO mono_pujas (proyecto, zona, fecha, importe, empresa, contacto, correo, telefono, mensaje, estado)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([$d['proyecto'], $d['zona'], ane_ahora(), $d['importe_n'], $d['empresa'], $d['contacto'],
                      mb_strtolower($d['correo'], 'UTF-8'), $d['telefono'], $d['mensaje'], $nuevoEstado]);
        $bd->exec('COMMIT');
        return ['ok' => true, 'estado' => $nuevoEstado];
    } catch (Throwable $t) {
        if ($bd->inTransaction()) {
            $bd->exec('ROLLBACK');
        }
        throw $t;
    }
}
