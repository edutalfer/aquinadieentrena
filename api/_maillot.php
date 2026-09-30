<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — ofertas por los huecos del mono
   ------------------------------------------------------------
   Librería común de maillot.php (página pública /maillot) y
   admin/maillot.php (gestión). No se sirve por web (`_*.php`).

   Las ofertas NO son vinculantes: son una forma de que una marca
   diga cuánto vale para ella un hueco. En la web solo se publica
   la cifra más alta de cada hueco, nunca quién la ha hecho.

   Cada oferta se comprueba y se guarda en la misma transacción:
   si dos marcas ofrecen a la vez, la segunda se compara ya con la
   primera y no puede entrar por debajo.
   ============================================================ */

declare(strict_types=1);
require_once __DIR__ . '/_formularios.php';   // ane_h, sellos, fechas, texto con formato

/* Zonas del mono. La geometría de cada una está en assets/js/maillot/mono.js
   con la MISMA clave: si añades una aquí, dale forma allí. */
const ANE_MAILLOT_ZONAS = [
    'pecho'        => ['Pecho',             'Delante, a la altura del pecho. El sitio que más se ve.'],
    'abdomen'      => ['Abdomen',           'Delante, justo debajo del pecho.'],
    'espalda-alta' => ['Espalda alta',      'Detrás, entre los omóplatos. Lo que ve la rueda de detrás.'],
    'espalda-baja' => ['Espalda baja',      'Detrás, en la zona lumbar.'],
    'costado-izq'  => ['Costado izquierdo', 'Panel lateral, bajo el brazo izquierdo.'],
    'costado-der'  => ['Costado derecho',   'Panel lateral, bajo el brazo derecho.'],
    'manga-izq'    => ['Manga izquierda',   'Cara exterior de la manga.'],
    'manga-der'    => ['Manga derecha',     'Cara exterior de la manga.'],
    'culote-izq'   => ['Culote izquierdo',  'Lateral del muslo, sobre el negro del culote.'],
    'culote-der'   => ['Culote derecho',    'Lateral del muslo, sobre el negro del culote.'],
];

const ANE_MAILLOT_MAX_IMPORTE = 1000000;

const ANE_MAILLOT_AJUSTES = [
    'abierto'     => '0',   // 1 = se aceptan ofertas
    'revision'    => '1',   // 1 = una oferta no cuenta hasta que la validas en el panel
    'titulo'      => 'Tu marca en el mono ANE 2027',
    'intro'       => "Este es el mono con el que vamos a correr la temporada 2027. Cada hueco libre está a la venta: elige uno, mira la oferta más alta y haz la tuya.\n\nAl cierre hablamos con la mejor oferta de cada hueco.",
    'condiciones' => "- Las ofertas **no son vinculantes** para ninguna de las dos partes: son la forma de decirnos cuánto vale para tu marca ese hueco.\n- Importes en euros y **sin IVA**, por la temporada completa.\n- Al cierre de cada hueco hablamos con la oferta más alta para cerrar el acuerdo. Podemos declinar cualquier oferta.\n- En la web solo se publica la cifra de la oferta más alta, nunca quién la ha hecho.",
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

/* ---------- Huecos ---------- */

/* Todos los huecos del catálogo, en su orden, con sus cifras:
   maxima (oferta válida más alta o null), validas, pendientes, total. */
function ane_maillot_huecos(PDO $bd): array
{
    $ins = $bd->prepare('INSERT OR IGNORE INTO huecos (zona, nombre, descripcion, actualizado) VALUES (?, ?, ?, ?)');
    foreach (ANE_MAILLOT_ZONAS as $zona => [$nombre, $desc]) {
        $ins->execute([$zona, $nombre, $desc, ane_ahora()]);
    }
    $filas = [];
    foreach ($bd->query('SELECT * FROM huecos') as $h) {
        $filas[$h['zona']] = $h;
    }
    $cifras = [];
    foreach ($bd->query("SELECT zona,
                                MAX(CASE WHEN estado = 'valida' THEN importe END) AS maxima,
                                SUM(estado = 'valida')    AS validas,
                                SUM(estado = 'pendiente') AS pendientes,
                                COUNT(*)                  AS total
                         FROM pujas GROUP BY zona") as $c) {
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

/* oculto (no se ofrece) | adjudicado | cerrado (pasó la fecha) | oferta | libre */
function ane_maillot_estado(array $h): string
{
    if (!(int) $h['activo']) {
        return 'oculto';
    }
    if (trim((string) $h['adjudicado']) !== '') {
        return 'adjudicado';
    }
    if ($h['cierre'] && $h['cierre'] <= ane_ahora()) {
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
function ane_maillot_publico(array $h): array
{
    $estado = ane_maillot_estado($h);
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
function ane_maillot_valida(array $post): array
{
    $d = [
        'zona'     => (string) ($post['zona'] ?? ''),
        'importe'  => ane_recorta((string) ($post['importe'] ?? ''), 20),
        'empresa'  => ane_recorta((string) ($post['empresa'] ?? ''), 120),
        'contacto' => ane_recorta((string) ($post['contacto'] ?? ''), 120),
        'correo'   => ane_recorta((string) ($post['correo'] ?? ''), 160),
        'telefono' => ane_recorta((string) ($post['telefono'] ?? ''), 30),
        'mensaje'  => ane_recorta((string) ($post['mensaje'] ?? ''), 1000),
    ];
    $e = [];
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
         o    ['ok' => false, 'motivo' => 'cerrado'|'bajo'|'saturado'] */
function ane_maillot_oferta(PDO $bd, array $d): array
{
    $bd->exec('BEGIN IMMEDIATE');
    try {
        $ajustes = ane_maillot_ajustes($bd);
        $h = ane_maillot_huecos($bd)[$d['zona']] ?? null;
        $estado = $h ? ane_maillot_estado($h) : 'oculto';
        if (!(int) $ajustes['abierto'] || !in_array($estado, ['libre', 'oferta'], true)) {
            $bd->exec('ROLLBACK');
            return ['ok' => false, 'motivo' => 'cerrado'];
        }
        if ($d['importe_n'] < ane_maillot_siguiente($h)) {
            $bd->exec('ROLLBACK');
            return ['ok' => false, 'motivo' => 'bajo'];
        }
        /* Freno contra avalanchas de bots: 20 ofertas por minuto en total */
        $recientes = (int) $bd->query("SELECT COUNT(*) FROM pujas
                                       WHERE fecha >= datetime('now', '-1 minute')")->fetchColumn();
        if ($recientes >= 20) {
            $bd->exec('ROLLBACK');
            return ['ok' => false, 'motivo' => 'saturado'];
        }
        $nuevoEstado = (int) $ajustes['revision'] ? 'pendiente' : 'valida';
        $bd->prepare('INSERT INTO pujas (zona, fecha, importe, empresa, contacto, correo, telefono, mensaje, estado)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([$d['zona'], ane_ahora(), $d['importe_n'], $d['empresa'], $d['contacto'],
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
