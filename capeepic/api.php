<?php
/* ============================================================
   Cape Epic 2027 — casas compartidas
   ------------------------------------------------------------
   Toda la carpeta /capeepic va con contraseña (.htaccess).
   Los datos viven FUERA del docroot y del repo:
     ~/datos/capeepic_casas.json
   GET  -> {casas:[...]}
   POST {accion:"guardar", casa:{...}} | {accion:"borrar", id}
   ============================================================ */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

$dir = dirname(__DIR__, 4) . '/datos';
$fichero = $dir . '/capeepic_casas.json';

function salir(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
    salir(500, ['error' => 'No se puede crear la carpeta de datos']);
}

$fp = fopen($fichero, 'c+');
if (!$fp) {
    salir(500, ['error' => 'No se puede abrir el fichero de casas']);
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
flock($fp, $metodo === 'GET' ? LOCK_SH : LOCK_EX);
$crudo = stream_get_contents($fp);
$casas = $crudo ? (json_decode($crudo, true) ?: []) : [];

if ($metodo === 'GET') {
    flock($fp, LOCK_UN);
    salir(200, ['casas' => array_values($casas)]);
}

if ($metodo !== 'POST') {
    salir(405, ['error' => 'Método no permitido']);
}

$entrada = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($entrada)) {
    salir(400, ['error' => 'Petición no válida']);
}

$fecha = '/^\d{4}-\d{2}-\d{2}$/';
$accion = $entrada['accion'] ?? '';

if ($accion === 'guardar') {
    $c = $entrada['casa'] ?? [];
    $txt = fn(string $k, int $max) => mb_substr(trim((string)($c[$k] ?? '')), 0, $max);
    $casa = [
        'nombre'    => $txt('nombre', 120),
        'direccion' => $txt('direccion', 300),
        'desde'     => $txt('desde', 10),
        'hasta'     => $txt('hasta', 10),
        'checkin'   => $txt('checkin', 40),
        'contacto'  => $txt('contacto', 200),
        'notas'     => $txt('notas', 2000),
    ];
    if ($casa['nombre'] === '' || !preg_match($fecha, $casa['desde']) || !preg_match($fecha, $casa['hasta'])) {
        salir(400, ['error' => 'Faltan nombre, entrada o salida']);
    }
    if ($casa['hasta'] <= $casa['desde']) {
        salir(400, ['error' => 'La salida tiene que ser después de la entrada']);
    }
    $id = (string)($c['id'] ?? '');
    if (!preg_match('/^[a-f0-9]{12}$/', $id)) {
        if (count($casas) >= 100) {
            salir(400, ['error' => 'Demasiadas casas']);
        }
        $id = bin2hex(random_bytes(6));
    }
    $casa['id'] = $id;
    $casa['actualizada'] = gmdate('Y-m-d H:i:s');
    $casas[$id] = $casa;
} elseif ($accion === 'borrar') {
    unset($casas[(string)($entrada['id'] ?? '')]);
} else {
    salir(400, ['error' => 'Acción desconocida']);
}

ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($casas, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);
salir(200, ['ok' => true, 'casas' => array_values($casas)]);
