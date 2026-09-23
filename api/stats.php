<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — POST /api/stats.php
   ------------------------------------------------------------
   Recibe de assets/js/stats.js:
     { tipo: "busqueda", termino, resultados, voz, pagina }
     { tipo: "clic",     termino, youtubeId, segundo, pagina }
   Responde 204 sin cuerpo. Nunca guarda IP ni cabeceras.
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/_bd.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

function fin(int $codigo): void
{
    http_response_code($codigo);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    fin(405);
}

/* Solo desde nuestra web (sendBeacon manda Origin en navegadores modernos) */
$origen = $_SERVER['HTTP_ORIGIN'] ?? '';
$propios = ['https://aquinadieentrena.cc', 'https://www.aquinadieentrena.cc'];
if ($origen !== '' && !in_array($origen, $propios, true)) {
    fin(403);
}

$crudo = file_get_contents('php://input', false, null, 0, 2048);
$d = json_decode($crudo === false ? '' : $crudo, true);
if (!is_array($d)) {
    fin(400);
}

$termino = ane_normaliza((string) ($d['termino'] ?? ''));
if (mb_strlen($termino, 'UTF-8') < 2 || ane_parece_personal($termino)) {
    fin(204);
}
$pagina = in_array($d['pagina'] ?? '', ['portada', 'episodios'], true) ? $d['pagina'] : 'otra';
$ahora = gmdate('Y-m-d H:i:s');

try {
    $bd = ane_bd();

    /* Freno contra inundaciones. Sin IP no hay freno por persona:
       es global, y muy por encima del tráfico real de la web. */
    $ultimoMinuto = (int) $bd->query("
        SELECT (SELECT COUNT(*) FROM busquedas WHERE fecha >= datetime('now', '-1 minute'))
             + (SELECT COUNT(*) FROM clics     WHERE fecha >= datetime('now', '-1 minute'))
    ")->fetchColumn();
    if ($ultimoMinuto > 300) {
        fin(429);
    }

    if (($d['tipo'] ?? '') === 'clic') {
        $yid = (string) ($d['youtubeId'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $yid)) {
            fin(400);
        }
        $segundo = max(0, min(36000, (int) ($d['segundo'] ?? 0)));
        $bd->prepare('INSERT INTO clics (fecha, termino, youtube_id, segundo, pagina)
                      VALUES (?, ?, ?, ?, ?)')
           ->execute([$ahora, $termino, $yid, $segundo, $pagina]);
    } else {
        $resultados = max(0, min(100000, (int) ($d['resultados'] ?? 0)));
        $voz = max(0, min($resultados, (int) ($d['voz'] ?? 0)));
        $bd->prepare('INSERT INTO busquedas (fecha, termino, resultados, voz, pagina)
                      VALUES (?, ?, ?, ?, ?)')
           ->execute([$ahora, $termino, $resultados, $voz, $pagina]);
    }
    fin(204);
} catch (Throwable $e) {
    error_log('ANE stats: ' . $e->getMessage());
    fin(500);
}
