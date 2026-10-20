<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio · ver o descargar un contrato
   ------------------------------------------------------------
   Solo socios: un contrato lleva importes y datos de la marca.
   El editor se lleva un 403 aunque adivine el número.

     contrato.php?c=7        el PDF, para leerlo en el navegador
     contrato.php?c=7&d=1    el fichero, para descargarlo

   Los ficheros viven en ~/datos/estudio/contratos/, fuera del
   repo y del docroot. Lo que no es PDF sale siempre como adjunto.
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/_comun.php';
ane_pod_solo_socio();

$c = ane_pod_contrato($bd, (int) ($_GET['c'] ?? 0));
$ruta = $c ? ane_pod_carpeta_contratos() . '/' . basename((string) $c['fichero']) : '';
if (!$c || !is_file($ruta)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('No existe.');
}

$enLinea = !isset($_GET['d']) && $c['mime'] === 'application/pdf';
$nombre = (string) $c['nombre'] !== '' ? (string) $c['nombre'] : basename((string) $c['fichero']);
$ascii = str_replace(['"', '\\'], '_', preg_replace('/[^\x20-\x7E]/', '_', $nombre) ?? 'contrato');

header('Cache-Control: private, no-store');
header('Content-Type: ' . ($enLinea ? 'application/pdf' : 'application/octet-stream'));
header('Content-Length: ' . (string) filesize($ruta));
header('Content-Disposition: ' . ($enLinea ? 'inline' : 'attachment')
     . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
header('X-Content-Type-Options: nosniff');
readfile($ruta);
