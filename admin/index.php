<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — panel privado de estadísticas  /admin
   ------------------------------------------------------------
   Acceso con usuario y contraseña del navegador (Basic Auth,
   siempre sobre HTTPS). Las credenciales NO están en el repo
   (es público): ~/datos/admin.php devuelve
       ['usuario' => '...', 'hash' => password_hash('...')]
   Si ese fichero falta o no cuadra, no entra nadie.
   ============================================================ */

declare(strict_types=1);
require dirname(__DIR__) . '/api/_bd.php';

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

/* ---------- Acceso ---------- */

function ane_credenciales(): array
{
    $u = $_SERVER['PHP_AUTH_USER'] ?? null;
    $p = $_SERVER['PHP_AUTH_PW'] ?? null;
    if ($u === null) {   // algunos servidores solo pasan la cabecera cruda
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (stripos($h, 'basic ') === 0) {
            $dec = base64_decode(substr($h, 6), true);
            if ($dec !== false && strpos($dec, ':') !== false) {
                [$u, $p] = explode(':', $dec, 2);
            }
        }
    }
    return [(string) $u, (string) $p];
}

$cfgFichero = ANE_DATOS . '/admin.php';
$cfg = is_file($cfgFichero) ? require $cfgFichero : null;
[$usuario, $clave] = ane_credenciales();

$dentro = is_array($cfg) && isset($cfg['usuario'], $cfg['hash']) && $usuario !== ''
    && hash_equals((string) $cfg['usuario'], $usuario)
    && password_verify($clave, (string) $cfg['hash']);

if (!$dentro) {
    if ($usuario !== '') {
        usleep(600000);   // frena los intentos a lo bruto
    }
    header('WWW-Authenticate: Basic realm="ANE - panel privado", charset="UTF-8"');
    http_response_code(401);
    echo 'Acceso restringido.';
    exit;
}

/* ---------- Periodo y zona horaria ---------- */

$dias = (int) ($_GET['d'] ?? 30);
if (!in_array($dias, [1, 7, 30, 90, 365], true)) {
    $dias = 30;
}
$desde = gmdate('Y-m-d H:i:s', time() - $dias * 86400);

$zona = new DateTimeZone('Europe/Madrid');
$desfase = (new DateTime('now', $zona))->getOffset() / 3600;          // 1 o 2
$aLocal = sprintf("'%+d hours'", $desfase);                              // para SQLite

function hora_local(string $utc, DateTimeZone $zona, string $formato): string
{
    $f = new DateTime($utc, new DateTimeZone('UTC'));
    return $f->setTimezone($zona)->format($formato);
}

function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function reloj(int $seg): string
{
    $h = intdiv($seg, 3600);
    $m = intdiv($seg % 3600, 60);
    $s = $seg % 60;
    return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
}

$bd = ane_bd();

function filas(PDO $bd, string $sql, array $p = []): array
{
    $st = $bd->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}

/* ---------- Exportar CSV ---------- */

if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ane-busquedas-' . $dias . 'd-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM: que Excel respete las tildes
    fputcsv($out, ['fecha', 'termino', 'resultados', 'solo_voz', 'pagina'], ';');
    foreach (filas($bd, 'SELECT * FROM busquedas WHERE fecha >= ? ORDER BY fecha', [$desde]) as $r) {
        fputcsv($out, [hora_local($r['fecha'], $zona, 'Y-m-d H:i'), $r['termino'],
                       $r['resultados'], $r['voz'], $r['pagina']], ';');
    }
    exit;
}

/* ---------- Títulos de episodio (de data/episodios.js) ---------- */

$titulos = [];
$js = @file_get_contents(dirname(__DIR__) . '/data/episodios.js');
if ($js !== false && preg_match_all(
    '/titulo:\s*"((?:[^"\\\\]|\\\\.)*)",\s*fecha:\s*"[\d-]+",\s*duracion:\s*\d+,\s*youtubeId:\s*"([\w-]{11})"/u',
    $js, $m, PREG_SET_ORDER)) {
    foreach ($m as $x) {
        $titulos[$x[2]] = stripcslashes($x[1]);
    }
}

/* ---------- Consultas ---------- */

$tot = filas($bd, "
    SELECT COUNT(*) AS busquedas,
           COUNT(DISTINCT termino) AS terminos,
           SUM(resultados = 0) AS vacias
    FROM busquedas WHERE fecha >= ?", [$desde])[0];
$tot['clics'] = (int) filas($bd, 'SELECT COUNT(*) AS n FROM clics WHERE fecha >= ?', [$desde])[0]['n'];
$tot['busquedas'] = (int) $tot['busquedas'];
$tot['vacias'] = (int) $tot['vacias'];
$pct = function (int $a, int $b): string {
    return $b > 0 ? round(100 * $a / $b) . ' %' : '—';
};

$porDia = filas($bd, "
    SELECT date(fecha, $aLocal) AS dia, COUNT(*) AS n
    FROM busquedas WHERE fecha >= ? GROUP BY dia ORDER BY dia", [$desde]);
$maxDia = max(array_merge([1], array_column($porDia, 'n')));

$top = filas($bd, "
    SELECT b.termino, COUNT(*) AS n, MAX(b.resultados) AS res,
           (SELECT COUNT(*) FROM clics c WHERE c.termino = b.termino AND c.fecha >= :d) AS clics
    FROM busquedas b WHERE b.fecha >= :d
    GROUP BY b.termino ORDER BY n DESC, b.termino LIMIT 30", [':d' => $desde]);

$vacias = filas($bd, "
    SELECT termino, COUNT(*) AS n, MAX(fecha) AS ultima
    FROM busquedas WHERE fecha >= ? AND resultados = 0
    GROUP BY termino ORDER BY n DESC, ultima DESC LIMIT 30", [$desde]);

$soloVoz = filas($bd, "
    SELECT termino, COUNT(*) AS n, MAX(resultados) AS res
    FROM busquedas WHERE fecha >= ? AND resultados > 0 AND voz = resultados
    GROUP BY termino ORDER BY n DESC LIMIT 20", [$desde]);

$momentos = filas($bd, "
    SELECT youtube_id, segundo, COUNT(*) AS n, GROUP_CONCAT(DISTINCT termino) AS terminos
    FROM clics WHERE fecha >= ?
    GROUP BY youtube_id, segundo ORDER BY n DESC LIMIT 20", [$desde]);

$ultimas = filas($bd, 'SELECT * FROM busquedas ORDER BY id DESC LIMIT 40');

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Panel privado — Aquí Nadie Entrena</title>
<style>
  @font-face { font-family: "Archivo"; src: url("/assets/fonts/archivo-black-italic.woff2") format("woff2");
               font-weight: 900; font-style: italic; font-display: swap; }
  @font-face { font-family: "Inter"; src: url("/assets/fonts/inter.woff2") format("woff2");
               font-weight: 400 700; font-display: swap; }
  :root { --azul: #3F77DA; --negro: #191919; --enlace: #2A5CB8; --destello: #B2C8F0;
          --gris: #6E747F; --humo: #F2F3F5; --linea: #E1E3E7; }
  * { box-sizing: border-box; }
  body { margin: 0; font: 15px/1.5 "Inter", system-ui, sans-serif; color: var(--negro); background: var(--humo); }
  .display { font-family: "Archivo", sans-serif; font-weight: 900; font-style: italic;
             text-transform: uppercase; letter-spacing: -0.02em; line-height: .9; }
  header { background: var(--negro); color: #fff; padding: 22px 0; }
  .caja { max-width: 1180px; margin: 0 auto; padding: 0 20px; }
  header .caja { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between; }
  header h1 { margin: 0; font-size: 30px; }
  header h1 em { color: var(--azul); font-style: italic; }
  nav a { color: #C7CAD1; text-decoration: none; font-weight: 700; font-size: 12px; letter-spacing: .1em;
          text-transform: uppercase; padding: 7px 11px; border: 1px solid #33363D; margin-left: 4px; }
  nav a.si { background: var(--azul); border-color: var(--azul); color: #fff; }
  main { padding: 26px 0 60px; }
  .cifras { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; margin-bottom: 22px; }
  .cifra { background: #fff; border: 2px solid var(--negro); padding: 16px 18px; }
  .cifra b { display: block; font: 900 italic 40px/1 "Archivo", sans-serif; }
  .cifra span { font-size: 12px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--gris); }
  .rejilla { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
  @media (max-width: 860px) { .rejilla { grid-template-columns: 1fr; } }
  section { background: #fff; border: 1px solid var(--linea); padding: 18px 20px; margin-bottom: 18px; }
  section h2 { margin: 0 0 4px; font-size: 22px; }
  section p.nota { margin: 0 0 12px; color: var(--gris); font-size: 13px; }
  table { width: 100%; border-collapse: collapse; font-size: 14px; }
  th { text-align: left; font-size: 11px; letter-spacing: .1em; text-transform: uppercase; color: var(--gris);
       border-bottom: 2px solid var(--negro); padding: 6px 8px 6px 0; }
  td { border-bottom: 1px solid var(--linea); padding: 7px 8px 7px 0; vertical-align: top; }
  td.n, th.n { text-align: right; font-variant-numeric: tabular-nums; }
  td.cero { color: var(--gris); }
  a { color: var(--enlace); }
  .dias { display: flex; align-items: flex-end; gap: 3px; height: 130px; padding-top: 8px; }
  .dias div { flex: 1; background: var(--azul); min-height: 2px; position: relative; }
  .dias div:hover { background: var(--negro); }
  .vacio { color: var(--gris); font-style: italic; padding: 8px 0; }
  .pie { color: var(--gris); font-size: 12px; margin-top: 10px; }
</style>
</head>
<body>

<header>
  <div class="caja">
    <h1 class="display">Panel <em>privado</em></h1>
    <nav>
      <?php foreach ([1 => 'Hoy', 7 => '7 días', 30 => '30 días', 90 => '90 días', 365 => 'Año'] as $k => $txt): ?>
        <a href="?d=<?= $k ?>" class="<?= $k === $dias ? 'si' : '' ?>"><?= $txt ?></a>
      <?php endforeach; ?>
      <a href="?d=<?= $dias ?>&amp;csv=1">CSV</a>
    </nav>
  </div>
</header>

<main class="caja">

  <div class="cifras">
    <div class="cifra"><b><?= $tot['busquedas'] ?></b><span>Búsquedas</span></div>
    <div class="cifra"><b><?= (int) $tot['terminos'] ?></b><span>Términos distintos</span></div>
    <div class="cifra"><b><?= $pct($tot['vacias'], $tot['busquedas']) ?></b><span>Sin resultado (<?= $tot['vacias'] ?>)</span></div>
    <div class="cifra"><b><?= $pct($tot['clics'], $tot['busquedas']) ?></b><span>Acaban en YouTube (<?= $tot['clics'] ?> clics)</span></div>
  </div>

  <section>
    <h2 class="display">Búsquedas por día</h2>
    <?php if (!$porDia): ?>
      <p class="vacio">Todavía no hay búsquedas en este periodo.</p>
    <?php else: ?>
      <div class="dias">
        <?php foreach ($porDia as $d): ?>
          <div style="height: <?= round(100 * $d['n'] / $maxDia) ?>%" title="<?= e($d['dia']) ?>: <?= (int) $d['n'] ?>"></div>
        <?php endforeach; ?>
      </div>
      <p class="pie"><?= e($porDia[0]['dia']) ?> → <?= e(end($porDia)['dia']) ?> · pasa el ratón por una barra para ver el día</p>
    <?php endif; ?>
  </section>

  <div class="rejilla">
    <section>
      <h2 class="display">Lo más buscado</h2>
      <p class="nota">Qué interesa. «Resultados» es el máximo de momentos que encontró.</p>
      <?php if (!$top): ?><p class="vacio">Nada aún.</p><?php else: ?>
      <table>
        <tr><th>Término</th><th class="n">Veces</th><th class="n">Resultados</th><th class="n">Clics</th></tr>
        <?php foreach ($top as $r): ?>
          <tr><td><?= e($r['termino']) ?></td><td class="n"><?= (int) $r['n'] ?></td>
              <td class="n <?= $r['res'] ? '' : 'cero' ?>"><?= (int) $r['res'] ?></td>
              <td class="n <?= $r['clics'] ? '' : 'cero' ?>"><?= (int) $r['clics'] ?></td></tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </section>

    <section>
      <h2 class="display">Sin resultado</h2>
      <p class="nota">Ideas para episodios… o palabras que YouTube transcribe mal (candidatas a <code>pipeline/correcciones.json</code>).</p>
      <?php if (!$vacias): ?><p class="vacio">Ninguna. Todo lo que se busca, aparece.</p><?php else: ?>
      <table>
        <tr><th>Término</th><th class="n">Veces</th><th class="n">Última</th></tr>
        <?php foreach ($vacias as $r): ?>
          <tr><td><?= e($r['termino']) ?></td><td class="n"><?= (int) $r['n'] ?></td>
              <td class="n"><?= e(hora_local($r['ultima'], $zona, 'd/m H:i')) ?></td></tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </section>
  </div>

  <div class="rejilla">
    <section>
      <h2 class="display">Momentos más abiertos</h2>
      <p class="nota">El minuto al que salta la gente desde el buscador.</p>
      <?php if (!$momentos): ?><p class="vacio">Nadie ha abierto un resultado todavía.</p><?php else: ?>
      <table>
        <tr><th>Episodio · minuto</th><th class="n">Clics</th></tr>
        <?php foreach ($momentos as $r): ?>
          <tr><td>
                <a href="https://www.youtube.com/watch?v=<?= e($r['youtube_id']) ?>&amp;t=<?= (int) $r['segundo'] ?>s"
                   target="_blank" rel="noopener"><?= e($titulos[$r['youtube_id']] ?? $r['youtube_id']) ?></a>
                · <b><?= reloj((int) $r['segundo']) ?></b><br>
                <small style="color:var(--gris)">desde: <?= e(str_replace(',', ', ', $r['terminos'])) ?></small>
              </td><td class="n"><?= (int) $r['n'] ?></td></tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </section>

    <section>
      <h2 class="display">Solo en lo hablado</h2>
      <p class="nota">Búsquedas que encuentran algo únicamente en las transcripciones: temas que no están en ningún título de bloque.</p>
      <?php if (!$soloVoz): ?><p class="vacio">Nada aún.</p><?php else: ?>
      <table>
        <tr><th>Término</th><th class="n">Veces</th><th class="n">Momentos</th></tr>
        <?php foreach ($soloVoz as $r): ?>
          <tr><td><?= e($r['termino']) ?></td><td class="n"><?= (int) $r['n'] ?></td><td class="n"><?= (int) $r['res'] ?></td></tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </section>
  </div>

  <section>
    <h2 class="display">Últimas búsquedas</h2>
    <p class="nota">Las 40 más recientes, sin filtro de periodo. Hora de Madrid.</p>
    <?php if (!$ultimas): ?><p class="vacio">Todavía nada.</p><?php else: ?>
    <table>
      <tr><th>Cuándo</th><th>Término</th><th class="n">Resultados</th><th>Página</th></tr>
      <?php foreach ($ultimas as $r): ?>
        <tr><td><?= e(hora_local($r['fecha'], $zona, 'd/m H:i')) ?></td><td><?= e($r['termino']) ?></td>
            <td class="n <?= $r['resultados'] ? '' : 'cero' ?>"><?= (int) $r['resultados'] ?></td>
            <td><?= e($r['pagina']) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </section>

  <p class="pie">Registro anónimo: término, nº de resultados, página y fecha. Sin IP, sin cookies, sin identificadores.
     Los términos que parecen un correo o un teléfono no se guardan.</p>
</main>
</body>
</html>
