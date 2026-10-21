<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio · un tema, en plena grabación
   ------------------------------------------------------------
   Desde el guión, junto a «+ Nota» de cada tema:

     pasar       el tema no da tiempo: se va TAL CUAL al episodio
                 siguiente (enlaces, fotos, archivos y notas)
     descartar   ya no interesa: sale del guión del editor y queda
                 tachado para los socios, con «Recuperar»
     recuperar   vuelve a su sitio

   Solo socios. Con la cabecera X-Estudio: fetch responde JSON con
   lo que hay que pintar en su lugar, para no recargar y no perder
   el sitio; sin JavaScript, vuelve al guión.
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/_comun.php';
ane_pod_solo_socio();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Solo POST.');
}
ane_pod_csrf_exige();

$json = ($_SERVER['HTTP_X_ESTUDIO'] ?? '') === 'fetch';
$accion = (string) ($_POST['accion'] ?? '');
$fila = (int) ($_POST['fila'] ?? 0);
/* Desde el editor del episodio se vuelve al editor; si no, al guión */
$alEditor = ($_POST['volver'] ?? '') === 'editor';

function ane_pod_tema_responde(bool $json, bool $alEditor, int $episodio, string $query, array $datos, int $codigo = 200): void
{
    if ($json) {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: /estudio/' . ($alEditor ? 'episodio.php' : 'guion.php') . '?id=' . $episodio
         . ($query !== '' ? '&' . $query : ''), true, 303);
    exit;
}

$st = $bd->prepare("SELECT * FROM pod_bloques WHERE id = ? AND tipo = 'tema'");
$st->execute([$fila]);
$tema = $st->fetch();
if (!$tema) {
    if ($json) {
        ane_pod_tema_responde(true, false, 0, '', ['ok' => false, 'error' => 'Ese tema ya no está.'], 404);
    }
    header('Location: /estudio/', true, 303);
    exit;
}
$episodio = (int) $tema['episodio_id'];
$ancla = $alEditor ? '#t' . $fila : '#b' . $fila;

if ($accion === 'pasar') {
    $destino = ane_pod_pasa_tema($bd, $fila);
    if (!$destino) {
        ane_pod_tema_responde($json, $alEditor, $episodio, 'mal=sin_siguiente' . $ancla,
                              ['ok' => false, 'error' => 'Este episodio no tiene número: no hay «siguiente».'], 400);
    }
    $titulo = (string) $tema['titulo'] !== '' ? (string) $tema['titulo'] : 'El tema';
    $html = '<div class="bloque movido noimprime" id="b' . $fila . '"><div class="min">→</div><div>'
          . '<span class="titulo-descartado">' . ane_h($titulo) . '</span> '
          . '<span class="dom">pasa al</span> <a href="/estudio/guion.php?id=' . (int) $destino['id'] . '#b' . $fila . '">'
          . ane_h(ane_pod_etiqueta($destino)) . '</a></div></div>';
    ane_pod_tema_responde($json, $alEditor, $episodio, 'ok=pasado&a=' . (int) $destino['numero'] . ($alEditor ? '#guion' : ''),
                          ['ok' => true, 'html' => $html]);
}

if ($accion === 'descartar' || $accion === 'recuperar') {
    $b = ane_pod_descarta_tema($bd, $fila, $accion === 'descartar');
    ane_pod_tema_responde($json, $alEditor, $episodio, 'ok=' . ($accion === 'descartar' ? 'descartado' : 'recuperado') . $ancla,
                          ['ok' => true, 'html' => $accion === 'descartar' ? ane_pod_html_descartado($b) : '']);
}

ane_pod_tema_responde($json, $alEditor, $episodio, '', ['ok' => false, 'error' => 'No sé hacer eso.'], 400);
