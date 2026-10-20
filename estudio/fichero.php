<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio · servir o descargar un fichero
   ------------------------------------------------------------
   Los ficheros viven en ~/datos/estudio/imagenes/, fuera del
   repo y del docroot: nadie llega a ellos por URL directa. Aquí
   se comprueba quién pregunta antes de soltar un byte, y los de
   un tema interno no existen para el editor.

     fichero.php?f=12        la imagen, para verla en el guión
     fichero.php?f=12&d=1    el fichero, para descargarlo

   Lo que no es imagen sale SIEMPRE como adjunto y como
   application/octet-stream: el navegador no lo ejecuta jamás.
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/_comun.php';

function ane_pod_no_hay(): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('No existe.');
}

$f = ane_pod_fichero($bd, (int) ($_GET['f'] ?? 0));
if (!$f || !ane_pod_puede_ver($bd, $yo_pod, (int) $f['episodio_id'])) {
    ane_pod_no_hay();
}

if (!$es_socio) {
    $st = $bd->prepare('SELECT interno FROM pod_bloques WHERE id = ?');
    $st->execute([(int) $f['bloque_id']]);
    if ((int) $st->fetchColumn()) {
        ane_pod_no_hay();
    }
}

$ruta = ane_pod_carpeta_imagenes() . '/' . basename((string) $f['fichero']);
if (!is_file($ruta)) {
    ane_pod_no_hay();
}

$descarga = isset($_GET['d']) || $f['tipo'] !== 'imagen';
$nombre = (string) $f['nombre'] !== '' ? (string) $f['nombre'] : basename((string) $f['fichero']);
/* El nombre viaja dos veces: en ASCII para navegadores antiguos y en UTF-8
   para los de ahora (RFC 5987), que es el que respeta tildes y eñes. */
$ascii = preg_replace('/[^\x20-\x7E]/', '_', $nombre) ?? 'descarga';
$ascii = str_replace(['"', '\\'], '_', $ascii);

/* La cabecera de _plantilla.php dice no-store: aquí interesa lo contrario,
   que el navegador no se baje la misma foto en cada recarga del guión. */
header('Cache-Control: private, max-age=86400');
header('Content-Type: ' . ($f['tipo'] === 'imagen' ? (string) $f['mime'] : 'application/octet-stream'));
header('Content-Length: ' . (string) filesize($ruta));
header('Content-Disposition: ' . ($descarga ? 'attachment' : 'inline')
     . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
header('X-Content-Type-Options: nosniff');
readfile($ruta);
