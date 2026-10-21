<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio · notas de grabación
   ------------------------------------------------------------
   Apuntar, borrar o enseñar al editor una nota mientras se graba.
   Solo socios: el guión (guion.php) sigue siendo de solo lectura,
   también para el editor, y las escrituras pasan por aquí.

   Desde el botón «+ Nota» del guión se llama con fetch y responde
   JSON con la nota ya pintada, para no recargar y no perder el
   sitio. Sin JavaScript, el formulario funciona igual y vuelve al
   guión.
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

function ane_pod_responde(bool $json, int $episodio, string $query, array $datos = [], int $codigo = 200): void
{
    if ($json) {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: /estudio/guion.php?id=' . $episodio . ($query !== '' ? '&' . $query : ''), true, 303);
    exit;
}

$accion = (string) ($_POST['accion'] ?? '');

if ($accion === 'nueva') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!ane_pod_episodio($bd, $id)) {
        ane_pod_responde($json, $id, '', ['ok' => false, 'error' => 'Ese episodio no existe.'], 404);
    }
    /* El tema, si se elige, tiene que ser de este episodio; si no, va a las generales */
    $bloque = (int) ($_POST['bloque'] ?? 0);
    if ($bloque) {
        $st = $bd->prepare("SELECT id FROM pod_bloques WHERE id = ? AND episodio_id = ? AND tipo IN ('tema', 'bicicepo')");
        $st->execute([$bloque, $id]);
        $bloque = (int) $st->fetchColumn();
    }
    $texto = ane_recorta((string) ($_POST['texto'] ?? ''), 2000);
    $crudo = trim((string) ($_POST['url'] ?? ''));
    $url = ane_pod_url($crudo);
    if ($crudo !== '' && $url === '') {
        ane_pod_responde($json, $id, 'mal=nota_url#notas', ['ok' => false, 'error' => 'Ese enlace no parece una dirección válida.'], 400);
    }
    if ($texto === '' && $url === '') {
        ane_pod_responde($json, $id, 'mal=nota_vacia#notas', ['ok' => false, 'error' => 'Escribe algo o pega un enlace.'], 400);
    }
    $bd->prepare('INSERT INTO pod_notas (episodio_id, bloque_id, texto, url, editor, autor, creado) VALUES (?, ?, ?, ?, ?, ?, ?)')
       ->execute([$id, $bloque, $texto, $url, empty($_POST['editor']) ? 0 : 1,
                  $yo_pod['nombre'] !== '' ? $yo_pod['nombre'] : $yo_pod['usuario'], ane_ahora()]);
    $nota = ane_pod_nota($bd, (int) $bd->lastInsertId());
    ane_pod_responde($json, $id, 'ok=nota#n' . (int) $nota['id'],
                     ['ok' => true, 'id' => (int) $nota['id'], 'bloque' => $bloque, 'html' => ane_pod_html_nota($nota)]);
}

$nota = ane_pod_nota($bd, (int) ($_POST['fila'] ?? 0));
if (!$nota) {
    header('Location: /estudio/', true, 303);
    exit;
}
$episodio = (int) $nota['episodio_id'];

if ($accion === 'borrar') {
    $bd->prepare('DELETE FROM pod_notas WHERE id = ?')->execute([(int) $nota['id']]);
    ane_pod_responde($json, $episodio, 'ok=nota_borrada#' . ((int) $nota['bloque_id'] ? 'nb' . (int) $nota['bloque_id'] : 'notas'),
                     ['ok' => true]);
}

if ($accion === 'editor') {
    $bd->prepare('UPDATE pod_notas SET editor = 1 - editor WHERE id = ?')->execute([(int) $nota['id']]);
    ane_pod_responde($json, $episodio, 'ok=nota_editor#n' . (int) $nota['id'], ['ok' => true]);
}

ane_pod_responde($json, $episodio, '', ['ok' => false, 'error' => 'No sé hacer eso.'], 400);
