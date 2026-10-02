<?php
/* ============================================================
   Cape Epic 2027 — datos compartidos de la app
   ------------------------------------------------------------
   Toda la carpeta /capeepic va con contraseña (.htaccess).
   Los datos viven FUERA del docroot y del repo:
     ~/datos/capeepic/<coleccion>.json   (un mapa id -> documento)
   GET               -> {cols:{coleccion:[docs]}, ahora}
   POST {accion:"guardar", c, doc} | {accion:"borrar", c, id}
   ============================================================ */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

/* Colecciones permitidas y máximo de documentos */
const COLECCIONES = [
    'casas' => 200, 'plan' => 1000, 'ajustes' => 50, 'equipo' => 60,
    'checkins' => 3000, 'spots' => 500, 'monos' => 30,
    'listas' => 200, 'hechos' => 5000, 'tomas' => 1000,
    'marcas' => 5000, 'gastos' => 1000,
];

$dir = getenv('CE_DATOS') ?: dirname(__DIR__, 4) . '/datos/capeepic';

function salir(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
    salir(500, ['error' => 'No se puede crear la carpeta de datos']);
}

/* Limpia un valor: solo texto, números, booleanos, null y listas/objetos pequeños */
function limpiar($v, int $nivel = 0)
{
    if (is_string($v)) return mb_substr($v, 0, 4000);
    if (is_int($v) || is_float($v) || is_bool($v) || $v === null) return $v;
    if (is_array($v) && $nivel < 3) {
        $out = [];
        $n = 0;
        foreach ($v as $k => $x) {
            if (++$n > 200) break;
            $out[is_int($k) ? $k : mb_substr((string)$k, 0, 64)] = limpiar($x, $nivel + 1);
        }
        return $out;
    }
    return null;
}

function leer(string $f): array
{
    if (!is_file($f)) return [];
    $fp = fopen($f, 'r');
    flock($fp, LOCK_SH);
    $j = json_decode(stream_get_contents($fp) ?: '', true);
    flock($fp, LOCK_UN);
    fclose($fp);
    return is_array($j) ? $j : [];
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    $cols = [];
    foreach (array_keys(COLECCIONES) as $c) {
        $cols[$c] = array_values(leer("$dir/$c.json"));
    }
    salir(200, ['cols' => $cols, 'ahora' => gmdate('c')]);
}

if ($metodo !== 'POST') salir(405, ['error' => 'Método no permitido']);

$crudo = file_get_contents('php://input') ?: '';
if (strlen($crudo) > 64000) salir(413, ['error' => 'Demasiado grande']);
$e = json_decode($crudo, true);
if (!is_array($e)) salir(400, ['error' => 'Petición no válida']);

$c = (string)($e['c'] ?? '');
if (!isset(COLECCIONES[$c])) salir(400, ['error' => 'Colección desconocida']);

$fp = fopen("$dir/$c.json", 'c+');
if (!$fp) salir(500, ['error' => 'No se puede abrir el fichero']);
flock($fp, LOCK_EX);
$docs = json_decode(stream_get_contents($fp) ?: '', true);
if (!is_array($docs)) $docs = [];

$accion = $e['accion'] ?? '';
if ($accion === 'guardar') {
    $doc = $e['doc'] ?? null;
    if (!is_array($doc)) salir(400, ['error' => 'Falta el documento']);
    $id = (string)($doc['id'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_\-]{1,80}$/', $id)) salir(400, ['error' => 'Identificador no válido']);
    if (!isset($docs[$id]) && count($docs) >= COLECCIONES[$c]) salir(400, ['error' => 'Se ha llegado al máximo de elementos']);
    $doc = limpiar($doc);
    $doc['id'] = $id;
    $doc['actualizado'] = gmdate('c');
    $docs[$id] = $doc;
} elseif ($accion === 'borrar') {
    unset($docs[(string)($e['id'] ?? '')]);
} else {
    salir(400, ['error' => 'Acción desconocida']);
}

ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($docs, JSON_UNESCAPED_UNICODE));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);
salir(200, ['ok' => true]);
