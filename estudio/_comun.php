<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio · la puerta
   ------------------------------------------------------------
   Todo lo que incluya este fichero exige haber entrado: si la
   cookie no vale, se va al formulario y aquí no se ejecuta nada
   más. Deja preparados $bd, $yo_pod y $es_socio.

   No se sirve por web: el .htaccess bloquea `_*.php`.
   ============================================================ */

declare(strict_types=1);
require_once __DIR__ . '/_plantilla.php';

/* ---------- Puerta ---------- */

$bd = ane_pod_bd();
$yo_pod = ane_pod_quien($bd);

if (!$yo_pod) {
    $destino = $_SERVER['REQUEST_URI'] ?? '/estudio/';
    header('Location: /estudio/entrar.php?volver=' . urlencode($destino), true, 302);
    exit;
}

$es_socio = $yo_pod['rol'] === 'socio';

/* Las pantallas de socio se cierran aquí, no en la plantilla: el editor
   que teclee la URL a mano se topa con la misma puerta. */
function ane_pod_solo_socio(): void
{
    global $es_socio;
    if (!$es_socio) {
        http_response_code(403);
        ane_pod_cabeza('Sin permiso');
        echo '<main class="caja"><section><h2>Esto no es para ti</h2>'
           . '<p class="nota">Tu acceso llega al guión de los episodios que te han compartido.</p>'
           . '<p><a href="/estudio/">Ir a mis guiones</a></p></section></main>';
        ane_pod_pie();
        exit;
    }
}

/* Episodios cuyo guión puede ver el editor */
function ane_pod_mis_episodios(PDO $bd, array $u): array
{
    if ($u['rol'] === 'socio') {
        return ane_pod_episodios($bd);
    }
    $st = $bd->prepare('SELECT episodio_id FROM pod_accesos WHERE usuario_id = ?');
    $st->execute([(int) $u['id']]);
    return ane_pod_episodios($bd, $st->fetchAll(PDO::FETCH_COLUMN));
}

function ane_pod_puede_ver(PDO $bd, array $u, int $episodio): bool
{
    if ($u['rol'] === 'socio') {
        return true;
    }
    $st = $bd->prepare('SELECT 1 FROM pod_accesos WHERE usuario_id = ? AND episodio_id = ?');
    $st->execute([(int) $u['id'], $episodio]);
    return (bool) $st->fetchColumn();
}

/* ---------- CSRF ---------- */

function ane_pod_csrf(): string
{
    global $yo_pod;
    return hash_hmac('sha256', 'estudio|' . $yo_pod['id'] . '|' . substr((string) $yo_pod['hash'], 0, 20), ane_secreto());
}

function ane_pod_csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . ane_pod_csrf() . '">';
}

function ane_pod_csrf_exige(): void
{
    $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $origenOk = $origen === '' || parse_url($origen, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST);
    if (!$origenOk || !hash_equals(ane_pod_csrf(), (string) ($_POST['_csrf'] ?? ''))) {
        http_response_code(403);
        exit('Petición rechazada. Recarga la página y vuelve a intentarlo.');
    }
}

/* ---------- Una nota de grabación, pintada ----------
   La usan guion.php (al cargar) y nota.php (al apuntar una sin recargar).
   Los botones solo salen a los socios: el editor solo lee. */
function ane_pod_html_nota(array $n): string
{
    global $es_socio;
    $nid = (int) $n['id'];
    $url = ane_pod_url((string) $n['url']);
    /* Por defecto las ve el editor: lo que se distingue a la vista son las privadas */
    $html = '<li class="nota-grab' . ((int) $n['editor'] ? '' : ' privada') . '" id="n' . $nid . '">';
    if ((string) $n['texto'] !== '') {
        $html .= '<div class="nota-texto">' . ane_pod_texto((string) $n['texto']) . '</div>';
    }
    if ($url !== '') {
        /* Sin «https://» y recortado: en el móvil, una dirección larga dejaba la flecha sola */
        $corto = mb_strimwidth((string) preg_replace('~^https?://(www\.)?~i', '', $url), 0, 48, '…');
        $html .= '<div class="nota-enlace">→ <a href="' . ane_h($url) . '" target="_blank" rel="noopener noreferrer">'
               . ane_h($corto) . '</a></div>';
    }
    $html .= '<div class="nota-pie"><span class="dom">' . ane_h((string) $n['autor'])
           . ' · ' . ane_h(ane_utc_a_local((string) $n['creado'], 'j/n H:i')) . '</span>';
    if ($es_socio) {
        $campos = ane_pod_csrf_campo() . '<input type="hidden" name="fila" value="' . $nid . '">';
        $html .= '<form method="post" action="/estudio/nota.php" class="enlinea noimprime">' . $campos
               . '<input type="hidden" name="accion" value="editor">'
               . '<button class="chip-boton' . ((int) $n['editor'] ? ' si' : ' privada') . '" type="submit" title="Cambiar si la ve el editor">'
               . ((int) $n['editor'] ? '✓ la ve el editor' : '🔒 solo nosotros') . '</button></form>'
               . '<form method="post" action="/estudio/nota.php" class="enlinea noimprime" onsubmit="return confirm(\'¿Borrar la nota?\')">'
               . $campos . '<input type="hidden" name="accion" value="borrar">'
               . '<button class="chip-boton peligro" type="submit" aria-label="Borrar la nota">✕</button></form>';
    }
    return $html . '</div></li>';
}
