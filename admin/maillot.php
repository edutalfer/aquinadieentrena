<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — panel privado · pestaña Mono
   ------------------------------------------------------------
   Huecos de patrocinio del mono (página pública /maillot), por
   proyecto (?p=<slug>):
     - el proyecto: nombre, fecha, si sale en la web y si admite ofertas
     - sus huecos: si se ofrecen, oferta mínima, subida mínima,
       fecha de cierre y a qué marca se han adjudicado
     - sus ofertas: validar, anular, borrar y CSV
   Y para toda la página: revisar ofertas antes de que cuenten y
   los textos. Todo lo que cambia algo va por POST con token CSRF.
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/_comun.php';
require_once dirname(__DIR__) . '/api/_maillot.php';

$bd = ane_bd();
$yo = '/admin/maillot.php';

$proyectos = ane_maillot_proyectos($bd);
$p = (string) ($_GET['p'] ?? $_POST['p'] ?? '');
if (!isset($proyectos[$p])) {
    $p = (string) array_key_first($proyectos);
}

function ane_vuelve(string $proyecto, string $query = '', string $ancla = ''): void
{
    global $yo;
    header('Location: ' . $yo . '?p=' . rawurlencode($proyecto) . ($query !== '' ? '&' . $query : '') . $ancla, true, 303);
    exit;
}

/* ---------- Acciones (POST) ---------- */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    ane_csrf_exige();
    $accion = (string) ($_POST['accion'] ?? '');
    $id = (int) ($_POST['puja'] ?? 0);

    switch ($accion) {
        case 'ajustes':
            ane_maillot_guarda_ajustes($bd, [
                'revision'    => empty($_POST['revision']) ? '0' : '1',
                'titulo'      => ane_recorta((string) ($_POST['titulo'] ?? ''), 120) ?: ANE_MAILLOT_AJUSTES['titulo'],
                'intro'       => ane_recorta((string) ($_POST['intro'] ?? ''), 3000),
                'condiciones' => ane_recorta((string) ($_POST['condiciones'] ?? ''), 6000),
            ]);
            ane_vuelve($p, 'ok=ajustes', '#ajustes');

        case 'nuevo_proyecto':
            $nombre = ane_recorta((string) ($_POST['nombre'] ?? ''), 80);
            $slug = ane_maillot_slug($nombre);
            if ($slug === '' || isset($proyectos[$slug])) {
                ane_vuelve($p, 'ok=repetido');
            }
            $orden = (int) $bd->query('SELECT COALESCE(MAX(orden), 0) + 10 FROM mono_proyectos')->fetchColumn();
            $bd->prepare('INSERT INTO mono_proyectos (slug, nombre, detalle, abierto, activo, orden) VALUES (?, ?, ?, 0, 1, ?)')
               ->execute([$slug, $nombre, ane_recorta((string) ($_POST['detalle'] ?? ''), 80), $orden]);
            ane_vuelve($slug, 'ok=proyecto');

        case 'proyecto':
            $bd->prepare('UPDATE mono_proyectos SET nombre = ?, detalle = ?, abierto = ?, activo = ?, orden = ? WHERE slug = ?')
               ->execute([
                   ane_recorta((string) ($_POST['nombre'] ?? ''), 80) ?: $proyectos[$p]['nombre'],
                   ane_recorta((string) ($_POST['detalle'] ?? ''), 80),
                   empty($_POST['abierto']) ? 0 : 1,
                   empty($_POST['activo']) ? 0 : 1,
                   max(0, min(9999, (int) ($_POST['orden'] ?? 0))),
                   $p,
               ]);
            ane_vuelve($p, 'ok=guardado');

        case 'huecos':
            $huecos = ane_maillot_huecos($bd, $p);
            $st = $bd->prepare('UPDATE mono_huecos SET nombre = ?, descripcion = ?, activo = ?, minimo = ?, incremento = ?,
                                  cierre = ?, adjudicado = ?, actualizado = ? WHERE proyecto = ? AND zona = ?');
            foreach (array_keys(ANE_MAILLOT_ZONAS) as $zona) {
                $h = $_POST['h'][$zona] ?? null;
                if (!is_array($h) || !isset($huecos[$zona])) {
                    continue;
                }
                $st->execute([
                    ane_recorta((string) ($h['nombre'] ?? ''), 60) ?: ANE_MAILLOT_ZONAS[$zona][0],
                    ane_recorta((string) ($h['descripcion'] ?? ''), 200),
                    empty($h['activo']) ? 0 : 1,
                    max(0, min(ANE_MAILLOT_MAX_IMPORTE, (int) ($h['minimo'] ?? 0))),
                    max(1, min(100000, (int) ($h['incremento'] ?? 50))),
                    ane_local_a_utc((string) ($h['cierre'] ?? '')),
                    ane_recorta((string) ($h['adjudicado'] ?? ''), 60),
                    ane_ahora(), $p, $zona,
                ]);
            }
            ane_vuelve($p, 'ok=huecos', '#huecos');

        case 'validar':
        case 'anular':
            $bd->prepare('UPDATE mono_pujas SET estado = ? WHERE id = ?')
               ->execute([$accion === 'validar' ? 'valida' : 'anulada', $id]);
            ane_vuelve($p, 'ok=' . $accion, '#ofertas');

        case 'borrar':
            $bd->prepare('DELETE FROM mono_pujas WHERE id = ?')->execute([$id]);
            ane_vuelve($p, 'ok=borrada', '#ofertas');

        case 'vaciar':
            $bd->prepare('DELETE FROM mono_pujas WHERE proyecto = ?')->execute([$p]);
            ane_vuelve($p, 'ok=vaciado');
    }
    ane_vuelve($p);
}

$proyecto = $proyectos[$p];
$huecos = ane_maillot_huecos($bd, $p);

/* ---------- Ofertas del proyecto ---------- */

$filtro = (string) ($_GET['zona'] ?? '');
if (!isset(ANE_MAILLOT_ZONAS[$filtro])) {
    $filtro = '';
}
$st = $bd->prepare('SELECT * FROM mono_pujas WHERE proyecto = ?' . ($filtro !== '' ? ' AND zona = ?' : '') . ' ORDER BY id DESC');
$st->execute($filtro !== '' ? [$p, $filtro] : [$p]);
$pujas = $st->fetchAll();

/* La más alta válida de cada hueco, para marcarla en la tabla */
$lider = [];
$st = $bd->prepare("SELECT zona, id FROM mono_pujas q WHERE proyecto = ? AND estado = 'valida' AND importe =
                      (SELECT MAX(importe) FROM mono_pujas r WHERE r.proyecto = q.proyecto AND r.zona = q.zona AND r.estado = 'valida')
                    ORDER BY id");
$st->execute([$p]);
foreach ($st as $r) {
    $lider[$r['zona']] ??= (int) $r['id'];
}

$ESTADO_PUJA = ['valida' => ['Válida', 'si'], 'pendiente' => ['Por revisar', 'medio'], 'anulada' => ['Anulada', 'no']];

if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ofertas-mono-' . $p . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Fecha', 'Proyecto', 'Hueco', 'Importe (€)', 'Estado', 'Más alta', 'Empresa', 'Contacto', 'Correo', 'Teléfono', 'Mensaje'], ';');
    foreach ($pujas as $o) {
        fputcsv($out, [ane_utc_a_local($o['fecha'], 'd/m/Y H:i'), $proyecto['nombre'], $huecos[$o['zona']]['nombre'] ?? $o['zona'],
                       $o['importe'], $ESTADO_PUJA[$o['estado']][0] ?? $o['estado'],
                       ($lider[$o['zona']] ?? 0) === (int) $o['id'] ? 'Sí' : '',
                       $o['empresa'], $o['contacto'], $o['correo'], $o['telefono'], $o['mensaje']], ';');
    }
    exit;
}

/* ---------- Cifras ---------- */

$ajustes = ane_maillot_ajustes($bd);
$ofrecidos = 0;
$conOferta = 0;
$suma = 0;
foreach ($huecos as $h) {
    if ((int) $h['activo']) {
        $ofrecidos++;
        if ($h['maxima'] !== null) {
            $conOferta++;
            $suma += $h['maxima'];
        }
    }
}
/* Pendientes por proyecto, para avisar en las pestañas */
$pendientesPor = [];
foreach ($bd->query("SELECT proyecto, COUNT(*) AS n FROM mono_pujas WHERE estado = 'pendiente' GROUP BY proyecto") as $r) {
    $pendientesPor[$r['proyecto']] = (int) $r['n'];
}
$pendientes = $pendientesPor[$p] ?? 0;

$mensajes = [
    'ajustes' => 'Ajustes guardados.', 'huecos' => 'Huecos guardados.', 'guardado' => 'Proyecto guardado.',
    'proyecto' => 'Proyecto creado. Nace sin admitir ofertas: revisa sus huecos y ábrelo cuando quieras.',
    'repetido' => 'Ya hay un proyecto con ese nombre.',
    'validar' => 'Oferta validada: ya cuenta en la web.', 'anular' => 'Oferta anulada: ya no cuenta en la web.',
    'borrada' => 'Oferta borrada.', 'vaciado' => 'Ofertas del proyecto borradas.',
];
$ok = $mensajes[(string) ($_GET['ok'] ?? '')] ?? '';
$url = fn(array $extra = []) => '?' . http_build_query(['p' => $p] + $extra);

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Mono — Panel privado — Aquí Nadie Entrena</title>
<?php ane_admin_estilo(); ?>
<style>
  .boton { display: inline-block; font: 700 12px/1 "Inter", sans-serif; letter-spacing: .08em; text-transform: uppercase;
           text-decoration: none; cursor: pointer; padding: 11px 15px; border: 2px solid var(--negro);
           background: var(--negro); color: #fff; }
  .boton:hover { background: var(--enlace); border-color: var(--enlace); }
  .boton--claro { background: #fff; color: var(--negro); }
  .boton--claro:hover { background: var(--negro); color: #fff; border-color: var(--negro); }
  .boton--peligro { background: #fff; color: var(--negro); border-color: #C9CDD3; }
  .boton--peligro:hover { background: var(--negro); color: #fff; border-color: var(--negro); }
  .boton--peq { padding: 7px 10px; font-size: 11px; }
  form.linea { display: inline; }
  .ok { background: var(--negro); color: #fff; padding: 12px 16px; margin-bottom: 18px; font-weight: 600; }
  .aviso-datos { background: var(--destello); padding: 12px 16px; margin-bottom: 18px; font-size: 14px; }
  .estado { display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; padding: 3px 8px; }
  .estado.estado--si { background: var(--enlace); color: #fff; }
  .estado.estado--medio { background: var(--destello); color: var(--negro); }
  .estado.estado--no { background: var(--linea); color: var(--negro); }
  .cifra .estado { font: 700 14px/1.2 "Inter", sans-serif; letter-spacing: .08em; padding: 7px 12px; margin: 4px 0 12px; }
  .campos { display: grid; gap: 16px; }
  .campos label.c { display: block; font-weight: 600; font-size: 14px; margin-bottom: 5px; }
  .ayuda { font-size: 13px; color: var(--gris); margin: 4px 0 0; }
  .campos input[type=text], .campos input[type=number], .campos textarea, .huecos input[type=text], .huecos input[type=number], .huecos input[type=datetime-local] {
    width: 100%; font: inherit; font-size: 14px; padding: 8px 9px; border: 1px solid #C9CDD3; background: #fff; color: var(--negro); }
  .campos textarea { resize: vertical; }
  .dos { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .tres { display: grid; grid-template-columns: 2fr 2fr 1fr; gap: 16px; }
  @media (max-width: 800px) { .dos, .tres { grid-template-columns: 1fr; } }
  .check { display: flex; gap: 10px; align-items: flex-start; font-size: 15px; cursor: pointer; }
  .check input { width: 18px; height: 18px; margin-top: 2px; accent-color: var(--enlace); flex: none; }
  .check small { display: block; color: var(--gris); font-size: 13px; }
  .tabla-scroll { overflow-x: auto; }
  .huecos td { vertical-align: top; padding: 10px 8px 10px 0; }
  .huecos tr.apagado td { background: #F7F8FA; }
  .huecos tr.apagado input { color: var(--gris); }
  .huecos .num { width: 96px; }
  .huecos .fecha { width: 190px; }
  .huecos .nom { min-width: 150px; }
  .huecos .desc { min-width: 220px; }
  .huecos .adj { min-width: 130px; }
  .huecos small { display: block; color: var(--gris); font-size: 12px; margin-top: 4px; }
  .ofertas td { font-size: 13px; vertical-align: middle; }
  .ofertas tr.anulada td { color: var(--gris); text-decoration: line-through; }
  .ofertas tr.anulada td:last-child { text-decoration: none; }
  .ofertas .lider { font-weight: 700; }
  .ofertas .importe { font: 900 italic 18px/1 "Archivo", sans-serif; white-space: nowrap; }
  .filtro { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 14px; }
  .filtro a { font-size: 12px; font-weight: 700; text-decoration: none; padding: 6px 10px; border: 1px solid #C9CDD3; color: var(--negro); }
  .filtro a.si { background: var(--negro); color: #fff; border-color: var(--negro); }
  .barra { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 4px; }
  .carreras { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 20px; }
  .carreras a { display: block; background: #fff; border: 2px solid var(--negro); padding: 10px 14px; text-decoration: none; color: var(--negro); }
  .carreras a b { display: block; font: 900 italic 17px/1 "Archivo", sans-serif; text-transform: uppercase; }
  .carreras a span { display: block; font-size: 12px; color: var(--gris); margin-top: 5px; }
  .carreras a.si { background: var(--negro); color: #fff; }
  .carreras a.si span { color: #C7CAD1; }
  .carreras a.apagado { border-style: dashed; opacity: .7; }
  .carreras .pend { display: inline-block; background: var(--destello); color: var(--negro); font-size: 11px; font-weight: 700; padding: 1px 6px; margin-left: 6px; }
</style>
</head>
<body>

<?php ane_admin_cabecera('maillot'); ?>

<main class="caja">

  <?php if ($ok): ?><div class="ok"><?= ane_h($ok) ?></div><?php endif; ?>

  <!-- ══════════ Proyectos ══════════ -->
  <nav class="carreras" aria-label="Proyecto">
    <?php foreach ($proyectos as $slug => $pr): ?>
      <a href="?p=<?= rawurlencode($slug) ?>" class="<?= $slug === $p ? 'si' : '' ?><?= (int) $pr['activo'] ? '' : ' apagado' ?>">
        <b><?= ane_h($pr['nombre']) ?><?php if (!empty($pendientesPor[$slug])): ?><span class="pend"><?= $pendientesPor[$slug] ?></span><?php endif; ?></b>
        <span><?= !(int) $pr['activo'] ? 'Oculto en la web' : ((int) $pr['abierto'] ? 'Ofertas abiertas' : 'Ofertas cerradas') ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="cifras">
    <div class="cifra"><?= !(int) $proyecto['activo'] ? '<span class="estado estado--no">Oculto</span>'
        : ((int) $proyecto['abierto'] ? '<span class="estado estado--si">Abierto</span>' : '<span class="estado estado--no">Cerrado</span>') ?><br><span>Ofertas en la web</span></div>
    <div class="cifra"><b><?= $conOferta ?><span style="font-size:22px;letter-spacing:0;color:var(--gris)"> / <?= $ofrecidos ?></span></b><span>Huecos con oferta</span></div>
    <div class="cifra"><b><?= ane_maillot_euros($suma) ?></b><span>Suma de las más altas</span></div>
    <div class="cifra"><b><?= $pendientes ?></b><span>Ofertas por revisar</span></div>
  </div>

  <?php if ($pendientes && (int) $ajustes['revision']): ?>
    <div class="aviso-datos">Tienes <?= $pendientes ?> oferta<?= $pendientes > 1 ? 's' : '' ?> por revisar en este proyecto. Mientras no las valides, no cuentan en la web. <a href="#ofertas">Ir a las ofertas</a></div>
  <?php endif; ?>

  <section>
    <div class="barra">
      <h2 class="display"><?= ane_h($proyecto['nombre']) ?></h2>
      <a class="boton boton--claro boton--peq" href="/maillot?p=<?= rawurlencode($p) ?>#mono" target="_blank" rel="noopener">Ver en la web ↗</a>
    </div>
    <form method="post" class="campos" style="margin-top:12px">
      <?= ane_csrf_campo() ?><input type="hidden" name="accion" value="proyecto"><input type="hidden" name="p" value="<?= ane_h($p) ?>">
      <div class="tres">
        <div><label class="c" for="pnombre">Nombre</label><input type="text" id="pnombre" name="nombre" value="<?= ane_h($proyecto['nombre']) ?>" maxlength="80"></div>
        <div><label class="c" for="pdetalle">Cuándo y dónde</label><input type="text" id="pdetalle" name="detalle" value="<?= ane_h($proyecto['detalle']) ?>" maxlength="80" placeholder="Marzo 2027 · Sudáfrica"></div>
        <div><label class="c" for="porden">Orden</label><input type="number" id="porden" name="orden" value="<?= (int) $proyecto['orden'] ?>" min="0" step="10"></div>
      </div>
      <div class="dos">
        <label class="check"><input type="checkbox" name="abierto" value="1"<?= (int) $proyecto['abierto'] ? ' checked' : '' ?>>
          <span>Admite ofertas<small>Apagado: el mono se ve con sus cifras, pero no se puede ofertar.</small></span></label>
        <label class="check"><input type="checkbox" name="activo" value="1"<?= (int) $proyecto['activo'] ? ' checked' : '' ?>>
          <span>Sale en la web<small>Apagado: el proyecto desaparece del selector de /maillot.</small></span></label>
      </div>
      <p><button class="boton" type="submit">Guardar proyecto</button></p>
    </form>
  </section>

  <!-- ══════════ Huecos ══════════ -->
  <section id="huecos">
    <h2 class="display">Huecos</h2>
    <p class="nota">Solo salen en el mono los que tengan marcado «Se ofrece». Importes en euros sin IVA. Hora de Madrid.
      Mínima 0 = sin oferta mínima. Si rellenas «Adjudicado a», el hueco deja de admitir ofertas y en el mono aparece esa marca.</p>
    <form method="post">
      <?= ane_csrf_campo() ?><input type="hidden" name="accion" value="huecos"><input type="hidden" name="p" value="<?= ane_h($p) ?>">
      <div class="tabla-scroll">
        <table class="huecos">
          <tr><th>Se ofrece</th><th>Hueco</th><th>Descripción</th><th class="n">Mínima</th><th class="n">Subida</th><th>Cierre</th><th>Adjudicado a</th><th class="n">Más alta</th></tr>
          <?php foreach ($huecos as $zona => $h): $n = 'h[' . ane_h($zona) . ']'; ?>
            <tr class="<?= (int) $h['activo'] ? '' : 'apagado' ?>">
              <td><label class="check"><input type="checkbox" name="<?= $n ?>[activo]" value="1"<?= (int) $h['activo'] ? ' checked' : '' ?>></label></td>
              <td class="nom"><input type="text" name="<?= $n ?>[nombre]" value="<?= ane_h($h['nombre']) ?>" maxlength="60"><small><?= ane_h($zona) ?></small></td>
              <td class="desc"><input type="text" name="<?= $n ?>[descripcion]" value="<?= ane_h($h['descripcion']) ?>" maxlength="200"></td>
              <td><input class="num" type="number" min="0" step="1" name="<?= $n ?>[minimo]" value="<?= (int) $h['minimo'] ?>"></td>
              <td><input class="num" type="number" min="1" step="1" name="<?= $n ?>[incremento]" value="<?= (int) $h['incremento'] ?>"></td>
              <td><input class="fecha" type="datetime-local" name="<?= $n ?>[cierre]" value="<?= ane_h(ane_utc_a_local($h['cierre'])) ?>"></td>
              <td class="adj"><input type="text" name="<?= $n ?>[adjudicado]" value="<?= ane_h($h['adjudicado']) ?>" maxlength="60" placeholder="—"></td>
              <td class="n" style="white-space:nowrap"><?= $h['maxima'] !== null ? '<b>' . ane_maillot_euros($h['maxima']) . '</b>' : '—' ?>
                <?php if ($h['total']): ?><small><a href="<?= ane_h($url(['zona' => $zona])) ?>#ofertas"><?= $h['total'] ?> oferta<?= $h['total'] > 1 ? 's' : '' ?></a></small><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <p class="ayuda">«Mínima»: la primera oferta tiene que llegar a esa cifra. «Subida»: cuánto tiene que superar cada oferta a la más alta.</p>
      <p><button class="boton" type="submit">Guardar huecos</button></p>
    </form>
  </section>

  <!-- ══════════ Ofertas ══════════ -->
  <section id="ofertas">
    <div class="barra">
      <h2 class="display">Ofertas</h2>
      <?php if ($pujas): ?><a class="boton boton--claro boton--peq" href="<?= ane_h($url(['csv' => 1] + ($filtro !== '' ? ['zona' => $filtro] : []))) ?>">Descargar CSV</a><?php endif; ?>
    </div>
    <p class="nota">Las de <?= ane_h($proyecto['nombre']) ?>, las más recientes arriba. En negrita, la más alta válida de cada hueco. La web solo enseña la cifra, nunca la empresa.</p>
    <div class="filtro">
      <a href="<?= ane_h($url()) ?>#ofertas" class="<?= $filtro === '' ? 'si' : '' ?>">Todas</a>
      <?php foreach ($huecos as $zona => $h): if (!$h['total']) continue; ?>
        <a href="<?= ane_h($url(['zona' => $zona])) ?>#ofertas" class="<?= $filtro === $zona ? 'si' : '' ?>"><?= ane_h($h['nombre']) ?> (<?= $h['total'] ?>)</a>
      <?php endforeach; ?>
    </div>
    <?php if (!$pujas): ?>
      <p class="vacio">Todavía no ha llegado ninguna oferta para este proyecto.</p>
    <?php else: ?>
    <div class="tabla-scroll">
      <table class="ofertas">
        <tr><th>Fecha</th><th>Hueco</th><th class="n">Importe</th><th>Estado</th><th>Empresa</th><th>Contacto</th><th>Mensaje</th><th></th></tr>
        <?php foreach ($pujas as $o): $esLider = ($lider[$o['zona']] ?? 0) === (int) $o['id']; ?>
          <tr class="<?= $o['estado'] === 'anulada' ? 'anulada' : '' ?><?= $esLider ? ' lider' : '' ?>">
            <td style="white-space:nowrap"><?= ane_h(ane_utc_a_local($o['fecha'], 'd/m H:i')) ?></td>
            <td><?= ane_h($huecos[$o['zona']]['nombre'] ?? $o['zona']) ?></td>
            <td class="n importe"><?= ane_maillot_euros((int) $o['importe']) ?></td>
            <td><span class="estado estado--<?= $ESTADO_PUJA[$o['estado']][1] ?? 'no' ?>"><?= $ESTADO_PUJA[$o['estado']][0] ?? ane_h($o['estado']) ?></span><?= $esLider ? '<br><small>La más alta</small>' : '' ?></td>
            <td><?= ane_h($o['empresa']) ?></td>
            <td><?= ane_h($o['contacto']) ?><?= $o['contacto'] !== '' ? '<br>' : '' ?><a href="mailto:<?= ane_h($o['correo']) ?>"><?= ane_h($o['correo']) ?></a><?= $o['telefono'] !== '' ? '<br>' . ane_h($o['telefono']) : '' ?></td>
            <td style="max-width:260px"><?= nl2br(ane_h($o['mensaje'])) ?></td>
            <td style="white-space:nowrap">
              <?php foreach (['validar' => 'Validar', 'anular' => 'Anular'] as $acc => $txt):
                  if (($acc === 'validar' && $o['estado'] === 'valida') || ($acc === 'anular' && $o['estado'] === 'anulada')) continue; ?>
                <form class="linea" method="post"><?= ane_csrf_campo() ?><input type="hidden" name="accion" value="<?= $acc ?>">
                  <input type="hidden" name="p" value="<?= ane_h($p) ?>"><input type="hidden" name="puja" value="<?= (int) $o['id'] ?>">
                  <button class="boton boton--peq<?= $acc === 'anular' ? ' boton--peligro' : '' ?>" type="submit"><?= $txt ?></button></form>
              <?php endforeach; ?>
              <form class="linea" method="post" onsubmit="return confirm('¿Borrar esta oferta? No se puede deshacer.')"><?= ane_csrf_campo() ?>
                <input type="hidden" name="accion" value="borrar"><input type="hidden" name="p" value="<?= ane_h($p) ?>"><input type="hidden" name="puja" value="<?= (int) $o['id'] ?>">
                <button class="boton boton--peq boton--peligro" type="submit">Borrar</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <?php if ($pujas && $filtro === ''): ?>
  <section>
    <h2 class="display">Al cerrar el proyecto</h2>
    <p class="nota">La web promete borrar los datos de contacto al cerrar el patrocinio de cada proyecto. Descarga antes el CSV si lo necesitas.</p>
    <form method="post" onsubmit="return confirm('¿Borrar las <?= count($pujas) ?> ofertas de <?= ane_h($proyecto['nombre']) ?>? No se puede deshacer.')">
      <?= ane_csrf_campo() ?><input type="hidden" name="accion" value="vaciar"><input type="hidden" name="p" value="<?= ane_h($p) ?>">
      <button class="boton boton--peligro" type="submit">Borrar las ofertas de este proyecto</button>
    </form>
  </section>
  <?php endif; ?>

  <!-- ══════════ Para toda la página ══════════ -->
  <section id="ajustes">
    <h2 class="display">Ajustes de la página</h2>
    <p class="nota">Valen para todos los proyectos. Para los textos: **negrita**, _cursiva_, [texto](https://enlace), listas con «- » al principio de la línea.</p>
    <form method="post" class="campos">
      <?= ane_csrf_campo() ?><input type="hidden" name="accion" value="ajustes"><input type="hidden" name="p" value="<?= ane_h($p) ?>">
      <label class="check"><input type="checkbox" name="revision" value="1"<?= (int) $ajustes['revision'] ? ' checked' : '' ?>>
        <span>Revisar cada oferta antes de que cuente<small>Protege de ofertas falsas (por ejemplo, 5.000 € a nombre de otra marca para bloquear un hueco). Apagado, cuentan al momento y puedes anularlas después.</small></span></label>
      <div><label class="c" for="titulo">Título</label>
        <input type="text" id="titulo" name="titulo" value="<?= ane_h($ajustes['titulo']) ?>" maxlength="120"></div>
      <div><label class="c" for="intro">Texto de entrada</label>
        <textarea id="intro" name="intro" rows="4"><?= ane_h($ajustes['intro']) ?></textarea></div>
      <div><label class="c" for="condiciones">Condiciones</label>
        <textarea id="condiciones" name="condiciones" rows="7"><?= ane_h($ajustes['condiciones']) ?></textarea>
        <p class="ayuda">Deja claro que las ofertas no son vinculantes. Si algún día pasan a serlo, esto lo tiene que revisar alguien que sepa de contratos.</p></div>
      <p><button class="boton" type="submit">Guardar ajustes</button></p>
    </form>
  </section>

  <section>
    <h2 class="display">Nuevo proyecto</h2>
    <p class="nota">Nace sin admitir ofertas, con los diez huecos y sin mínimos.</p>
    <form method="post" class="campos">
      <?= ane_csrf_campo() ?><input type="hidden" name="accion" value="nuevo_proyecto"><input type="hidden" name="p" value="<?= ane_h($p) ?>">
      <div class="dos">
        <div><label class="c" for="nnombre">Nombre</label><input type="text" id="nnombre" name="nombre" maxlength="80" required placeholder="Mallorca 312"></div>
        <div><label class="c" for="ndetalle">Cuándo y dónde</label><input type="text" id="ndetalle" name="detalle" maxlength="80" placeholder="Abril 2027 · Mallorca"></div>
      </div>
      <p><button class="boton boton--claro" type="submit">Crear proyecto</button></p>
    </form>
  </section>

</main>
</body>
</html>
