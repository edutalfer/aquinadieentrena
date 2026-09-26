<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — panel privado · pestaña Estadísticas  /admin
   ------------------------------------------------------------
   Acceso con usuario y contraseña del navegador (Basic Auth,
   siempre sobre HTTPS). Las credenciales NO están en el repo
   (es público): ~/datos/admin.php devuelve
       ['usuario' => '...', 'hash' => password_hash('...')]
   Si ese fichero falta o no cuadra, no entra nadie.
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/_comun.php';   // acceso, CSRF, estilos y cabecera

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

/* Botones de correo: siempre las cuatro filas, aunque estén a cero */
$contactos = ['bici' => 0, 'marcas' => 0, 'tema' => 0, 'contacto' => 0];
$ultimoContacto = [];
foreach (filas($bd, 'SELECT destino, COUNT(*) AS n, MAX(fecha) AS ultima
                     FROM contactos WHERE fecha >= ? GROUP BY destino', [$desde]) as $r) {
    $contactos[$r['destino']] = (int) $r['n'];
    $ultimoContacto[$r['destino']] = $r['ultima'];
}
$nombresContacto = [
    'bici'     => ['¿Bici o cepo? · «Enviar mi bici»', 'Portada'],
    'marcas'   => ['Marcas · «Enviar propuesta»', 'Portada'],
    'tema'     => ['«Mándanoslo» tras una búsqueda sin resultado', 'Buscador'],
    'contacto' => ['«Contacto» del pie de página', 'Pie'],
];

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Panel privado — Aquí Nadie Entrena</title>
<?php ane_admin_estilo(); ?>
</head>
<body>

<?php
  $navPeriodos = '';
  foreach ([1 => 'Hoy', 7 => '7 días', 30 => '30 días', 90 => '90 días', 365 => 'Año'] as $k => $txt) {
      $navPeriodos .= '<a href="?d=' . $k . '" class="' . ($k === $dias ? 'si' : '') . '">' . $txt . '</a>';
  }
  $navPeriodos .= '<a href="?d=' . $dias . '&amp;csv=1">CSV</a>';
  ane_admin_cabecera('estadisticas', $navPeriodos);
?>

<main class="caja">

  <div class="cifras">
    <div class="cifra"><b><?= $tot['busquedas'] ?></b><span>Búsquedas</span></div>
    <div class="cifra"><b><?= (int) $tot['terminos'] ?></b><span>Términos distintos</span></div>
    <div class="cifra"><b><?= $pct($tot['vacias'], $tot['busquedas']) ?></b><span>Sin resultado (<?= $tot['vacias'] ?>)</span></div>
    <div class="cifra"><b><?= $pct($tot['clics'], $tot['busquedas']) ?></b><span>Acaban en YouTube (<?= $tot['clics'] ?> clics)</span></div>
  </div>

  <section>
    <h2 class="display">Botones de correo</h2>
    <p class="nota">Cuántas veces se pulsa cada botón. Son clics, no correos enviados:
       el correo lo termina (o no) la app de cada uno.</p>
    <table>
      <tr><th>Botón</th><th>Dónde</th><th class="n">Clics</th><th class="n">Último</th></tr>
      <?php foreach ($nombresContacto as $clave => [$nombre, $donde]): ?>
        <tr><td><?= e($nombre) ?></td><td><?= e($donde) ?></td>
            <td class="n <?= $contactos[$clave] ? '' : 'cero' ?>"><?= $contactos[$clave] ?></td>
            <td class="n"><?= isset($ultimoContacto[$clave]) ? e(hora_local($ultimoContacto[$clave], $zona, 'd/m H:i')) : '—' ?></td></tr>
      <?php endforeach; ?>
    </table>
  </section>

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

  <p class="pie">Registro anónimo: término, nº de resultados, página, fecha y botón de correo pulsado. Sin IP, sin cookies, sin identificadores.
     Los términos que parecen un correo o un teléfono no se guardan.</p>
</main>
</body>
</html>
