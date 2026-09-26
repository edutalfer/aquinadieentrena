<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — formularios de inscripción a eventos
   ------------------------------------------------------------
   Librería común de formulario.php (página pública /f/<código>)
   y admin/formularios.php (gestión). No se sirve por web: la
   bloquea el .htaccess como todo `_*.php`.

   A diferencia de Google Forms, el límite de plazas se aplica de
   verdad: la comprobación y el guardado van en la misma
   transacción, así que dos personas pulsando «Enviar» a la vez
   por la última plaza no pueden entrar las dos.
   ============================================================ */

declare(strict_types=1);
require_once __DIR__ . '/_bd.php';

const ANE_FORM_TIPOS = [
    'corto'       => 'Respuesta corta',
    'largo'       => 'Párrafo',
    'email'       => 'Correo electrónico',
    'telefono'    => 'Teléfono',
    'numero'      => 'Número',
    'fecha'       => 'Fecha',
    'opcion'      => 'Opción única',
    'casillas'    => 'Casillas (varias respuestas)',
    'desplegable' => 'Desplegable',
];
const ANE_FORM_CON_OPCIONES = ['opcion', 'casillas', 'desplegable'];
const ANE_FORM_MAX_PREGUNTAS = 40;

/* ---------- Utilidades ---------- */

function ane_h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function ane_ahora(): string
{
    return gmdate('Y-m-d H:i:s');
}

function ane_recorta(string $s, int $max): string
{
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    return mb_substr(trim($s), 0, $max, 'UTF-8');
}

/* Código de la URL: 10 caracteres sin los que se confunden (0/O, 1/l/I) */
function ane_form_token(): string
{
    $abc = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $t = '';
    for ($i = 0; $i < 10; $i++) {
        $t .= $abc[random_int(0, strlen($abc) - 1)];
    }
    return $t;
}

function ane_form_id_pregunta(): string
{
    return 'p' . bin2hex(random_bytes(4));
}

/* Secreto del servidor para firmar (anti-bots del formulario, CSRF del
   panel). Se crea solo la primera vez en ~/datos/, fuera del repo. */
function ane_secreto(): string
{
    static $s = null;
    if ($s !== null) {
        return $s;
    }
    $f = ANE_DATOS . '/secreto';
    if (!is_file($f)) {
        if (!is_dir(ANE_DATOS)) {
            mkdir(ANE_DATOS, 0700, true);
        }
        file_put_contents($f, bin2hex(random_bytes(32)), LOCK_EX);
        chmod($f, 0600);
    }
    return $s = trim((string) file_get_contents($f));
}

/* Hora de Madrid ⇄ UTC (el panel trabaja en hora local; la base en UTC) */
function ane_local_a_utc(string $local): ?string
{
    if ($local === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $local, new DateTimeZone('Europe/Madrid'));
    return $d ? $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') : null;
}

function ane_utc_a_local(?string $utc, string $formato = 'Y-m-d\TH:i'): string
{
    if (!$utc) {
        return '';
    }
    $d = new DateTime($utc, new DateTimeZone('UTC'));
    return $d->setTimezone(new DateTimeZone('Europe/Madrid'))->format($formato);
}

/* ---------- Lectura ---------- */

function ane_form_por_token(PDO $bd, string $token): ?array
{
    if (!preg_match('/^[A-Za-z0-9]{6,32}$/', $token)) {
        return null;
    }
    $st = $bd->prepare('SELECT * FROM formularios WHERE token = ?');
    $st->execute([$token]);
    return $st->fetch() ?: null;
}

function ane_form_por_id(PDO $bd, int $id): ?array
{
    $st = $bd->prepare('SELECT * FROM formularios WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function ane_form_preguntas(array $form): array
{
    $p = json_decode((string) $form['preguntas'], true);
    return is_array($p) ? $p : [];
}

/* Plazas ocupadas, reservas y estado a la vista del público:
   abierto | cerrado (a mano) | caducado (pasó la fecha) | completo | espera */
function ane_form_estado(PDO $bd, array $form): array
{
    $st = $bd->prepare('SELECT SUM(reserva = 0) AS ocupadas, SUM(reserva = 1) AS reservas
                        FROM form_respuestas WHERE form_id = ?');
    $st->execute([(int) $form['id']]);
    $r = $st->fetch() ?: [];
    $ocupadas = (int) ($r['ocupadas'] ?? 0);
    $reservas = (int) ($r['reservas'] ?? 0);
    $limite = (int) $form['limite'];

    if (!(int) $form['abierto']) {
        $estado = 'cerrado';
    } elseif ($form['cierre'] && $form['cierre'] <= ane_ahora()) {
        $estado = 'caducado';
    } elseif ($limite > 0 && $ocupadas >= $limite) {
        $estado = (int) $form['lista_espera'] ? 'espera' : 'completo';
    } else {
        $estado = 'abierto';
    }
    return [
        'estado'   => $estado,
        'ocupadas' => $ocupadas,
        'reservas' => $reservas,
        'limite'   => $limite,
        'quedan'   => $limite > 0 ? max(0, $limite - $ocupadas) : null,
        'acepta'   => in_array($estado, ['abierto', 'espera'], true),
    ];
}

/* ---------- Preguntas que llegan del editor del panel ---------- */

function ane_form_limpia_preguntas($crudas): array
{
    if (!is_array($crudas)) {
        return [];
    }
    $out = [];
    $vistos = [];
    foreach (array_slice($crudas, 0, ANE_FORM_MAX_PREGUNTAS) as $p) {
        if (!is_array($p)) {
            continue;
        }
        $titulo = ane_recorta((string) ($p['titulo'] ?? ''), 300);
        if ($titulo === '') {
            continue;
        }
        $tipo = (string) ($p['tipo'] ?? 'corto');
        if (!isset(ANE_FORM_TIPOS[$tipo])) {
            $tipo = 'corto';
        }
        $id = (string) ($p['id'] ?? '');
        if (!preg_match('/^p[0-9a-f]{8}$/', $id) || isset($vistos[$id])) {
            $id = ane_form_id_pregunta();
        }
        $vistos[$id] = true;

        $opciones = [];
        if (in_array($tipo, ANE_FORM_CON_OPCIONES, true)) {
            foreach ((array) ($p['opciones'] ?? []) as $o) {
                $o = ane_recorta((string) $o, 200);
                if ($o !== '' && !in_array($o, $opciones, true) && count($opciones) < 60) {
                    $opciones[] = $o;
                }
            }
            if (!$opciones) {
                $opciones = ['Opción 1'];
            }
        }
        $out[] = [
            'id'          => $id,
            'tipo'        => $tipo,
            'titulo'      => $titulo,
            'ayuda'       => ane_recorta((string) ($p['ayuda'] ?? ''), 500),
            'obligatoria' => !empty($p['obligatoria']),
            'opciones'    => $opciones,
        ];
    }
    return $out;
}

/* Las tres del formulario del Social Ride de Bicilab x Gobik */
function ane_form_preguntas_iniciales(): array
{
    return ane_form_limpia_preguntas([
        ['tipo' => 'email',    'titulo' => 'Correo',             'obligatoria' => true],
        ['tipo' => 'corto',    'titulo' => 'Nombre y apellidos', 'obligatoria' => true],
        ['tipo' => 'telefono', 'titulo' => 'Teléfono móvil',     'obligatoria' => true],
    ]);
}

/* ---------- Validación de lo que envía el público ---------- */

/* Devuelve [valores por id de pregunta, errores por id de pregunta] */
function ane_form_valida(array $preguntas, array $post): array
{
    $valores = [];
    $errores = [];
    foreach ($preguntas as $p) {
        $id = $p['id'];
        $crudo = $post[$id] ?? ($p['tipo'] === 'casillas' ? [] : '');
        $obl = !empty($p['obligatoria']);

        if ($p['tipo'] === 'casillas') {
            $v = array_values(array_intersect($p['opciones'], array_map('strval', (array) $crudo)));
            $valores[$id] = $v;
            if ($obl && !$v) {
                $errores[$id] = 'Marca al menos una opción.';
            }
            continue;
        }

        $v = ane_recorta(is_array($crudo) ? '' : (string) $crudo, $p['tipo'] === 'largo' ? 3000 : 300);
        $valores[$id] = $v;
        if ($v === '') {
            if ($obl) {
                $errores[$id] = 'Esta pregunta es obligatoria.';
            }
            continue;
        }
        switch ($p['tipo']) {
            case 'email':
                if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    $errores[$id] = 'Escribe un correo válido.';
                }
                break;
            case 'telefono':
                $cifras = preg_replace('/\D/', '', $v);
                if (!preg_match('/^\+?[\d\s().-]+$/', $v) || strlen($cifras) < 9 || strlen($cifras) > 15) {
                    $errores[$id] = 'Escribe un teléfono válido (al menos 9 cifras).';
                }
                break;
            case 'numero':
                if (!preg_match('/^-?\d+([.,]\d+)?$/', $v)) {
                    $errores[$id] = 'Escribe solo un número.';
                }
                break;
            case 'fecha':
                $d = DateTime::createFromFormat('Y-m-d', $v);
                if (!$d || $d->format('Y-m-d') !== $v) {
                    $errores[$id] = 'Escribe una fecha válida.';
                }
                break;
            case 'opcion':
            case 'desplegable':
                if (!in_array($v, $p['opciones'], true)) {
                    $errores[$id] = 'Elige una de las opciones.';
                }
                break;
        }
    }
    return [$valores, $errores];
}

/* ---------- Guardar una inscripción ---------- */

/* Resultado: ['ok' => true, 'reserva' => bool]
         o    ['ok' => false, 'motivo' => 'completo'|'cerrado'|'duplicado'|'saturado'] */
function ane_form_guarda(PDO $bd, array $form, array $preguntas, array $valores): array
{
    $datos = [];
    $correo = '';
    foreach ($preguntas as $p) {
        $v = $valores[$p['id']] ?? '';
        $datos[] = ['id' => $p['id'], 'titulo' => $p['titulo'], 'valor' => $v];
        if ($correo === '' && $p['tipo'] === 'email' && is_string($v)) {
            $correo = mb_strtolower(trim($v), 'UTF-8');
        }
    }

    /* IMMEDIATE: bloquea la escritura desde el primer momento, así nadie
       más puede contar plazas mientras decidimos si esta entra. */
    $bd->exec('BEGIN IMMEDIATE');
    try {
        $fresco = ane_form_por_id($bd, (int) $form['id']);
        $e = $fresco ? ane_form_estado($bd, $fresco) : ['acepta' => false, 'estado' => 'cerrado'];
        if (!$e['acepta']) {
            $bd->exec('ROLLBACK');
            return ['ok' => false, 'motivo' => $e['estado'] === 'completo' ? 'completo' : 'cerrado'];
        }

        /* Freno contra avalanchas de bots: 30 inscripciones por minuto en total */
        $recientes = (int) $bd->query("SELECT COUNT(*) FROM form_respuestas
                                       WHERE fecha >= datetime('now', '-1 minute')")->fetchColumn();
        if ($recientes >= 30) {
            $bd->exec('ROLLBACK');
            return ['ok' => false, 'motivo' => 'saturado'];
        }

        if ((int) $fresco['una_por_correo'] && $correo !== '') {
            $st = $bd->prepare('SELECT 1 FROM form_respuestas WHERE form_id = ? AND correo = ?');
            $st->execute([(int) $fresco['id'], $correo]);
            if ($st->fetchColumn()) {
                $bd->exec('ROLLBACK');
                return ['ok' => false, 'motivo' => 'duplicado'];
            }
        }

        $reserva = $e['estado'] === 'espera' ? 1 : 0;
        $bd->prepare('INSERT INTO form_respuestas (form_id, fecha, datos, correo, reserva) VALUES (?, ?, ?, ?, ?)')
           ->execute([(int) $fresco['id'], ane_ahora(),
                      json_encode($datos, JSON_UNESCAPED_UNICODE), $correo, $reserva]);
        $bd->exec('COMMIT');
        return ['ok' => true, 'reserva' => (bool) $reserva];
    } catch (Throwable $t) {
        if ($bd->inTransaction()) {
            $bd->exec('ROLLBACK');
        }
        throw $t;
    }
}

/* ---------- Sello anti-bots ----------
   Viaja oculto en el formulario: hora de carga firmada. Un envío en
   menos de 3 s es de un bot; uno de hace más de 12 h, una página
   olvidada abierta, que se vuelve a firmar. */

function ane_form_sello(string $token): string
{
    $t = (string) time();
    return $t . '.' . substr(hash_hmac('sha256', $t . '|' . $token, ane_secreto()), 0, 20);
}

/* 'ok' | 'rapido' | 'caducado' | 'falso' */
function ane_form_comprueba_sello(string $sello, string $token): string
{
    if (!preg_match('/^(\d{9,11})\.([0-9a-f]{20})$/', $sello, $m)) {
        return 'falso';
    }
    $esperado = substr(hash_hmac('sha256', $m[1] . '|' . $token, ane_secreto()), 0, 20);
    if (!hash_equals($esperado, $m[2])) {
        return 'falso';
    }
    $edad = time() - (int) $m[1];
    if ($edad < 3) {
        return 'rapido';
    }
    return $edad > 43200 ? 'caducado' : 'ok';
}

/* ---------- Texto con formato para la descripción ----------
   Lo mínimo para escribir como en el Google Form del vídeo:
     **negrita**   _cursiva_   [texto](https://enlace)
     - listas con guion al principio de la línea
     una línea en blanco separa párrafos
   Se escapa TODO el HTML antes de aplicar el formato. */

function ane_form_linea(string $s): string
{
    $s = ane_h($s);
    /* Los enlaces se apartan primero: sus direcciones pueden llevar «_» o «*» */
    $enlaces = [];
    $s = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u', function ($m) use (&$enlaces) {
        $enlaces[] = '<a href="' . $m[2] . '" target="_blank" rel="noopener">' . $m[1] . '</a>';
        return "\x01" . (count($enlaces) - 1) . "\x01";
    }, $s);
    $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', (string) $s);
    $s = preg_replace('/(^|[\s(])_(.+?)_(?=$|[\s).,;:!?])/u', '$1<em>$2</em>', (string) $s);
    return (string) preg_replace_callback('/\x01(\d+)\x01/', fn($m) => $enlaces[(int) $m[1]], (string) $s);
}

function ane_form_texto(string $texto): string
{
    $html = '';
    $lista = false;
    $parrafo = [];
    $cierraParrafo = function () use (&$parrafo, &$html) {
        if ($parrafo) {
            $html .= '<p>' . implode('<br>', $parrafo) . "</p>\n";
            $parrafo = [];
        }
    };
    foreach (preg_split('/\R/u', $texto) as $linea) {
        $l = rtrim($linea);
        if (preg_match('/^\s*[-•*]\s+(.*)$/u', $l, $m)) {
            $cierraParrafo();
            if (!$lista) {
                $html .= "<ul>\n";
                $lista = true;
            }
            $html .= '<li>' . ane_form_linea($m[1]) . "</li>\n";
            continue;
        }
        if ($lista) {
            $html .= "</ul>\n";
            $lista = false;
        }
        if (trim($l) === '') {
            $cierraParrafo();
        } else {
            $parrafo[] = ane_form_linea(trim($l));
        }
    }
    if ($lista) {
        $html .= "</ul>\n";
    }
    $cierraParrafo();
    return $html;
}
