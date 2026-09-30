<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — panel privado · pestaña Mono
   ------------------------------------------------------------
   Huecos de patrocinio del mono (página pública /maillot):
     - ajustes generales (abierto, revisión, textos)
     - por hueco: si se ofrece, oferta mínima, subida mínima,
       fecha de cierre y a qué marca se ha adjudicado
     - ofertas recibidas: validar, anular, borrar y CSV
   Todo lo que cambia algo va por POST con token CSRF.
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/_comun.php';
require_once dirname(__DIR__) . '/api/_maillot.php';

$bd = ane_bd();
$yo = '/admin/maillot.php';

function ane_vuelve(string $query = ''): void
{
    global $yo;
    header('Location: ' . $yo . ($query !== '' ? '?' . $query : ''), true, 303);
    exit;
}

$huecos = ane_maillot_huecos($bd);   // también da de alta las zonas nuevas del catálogo

/* ---------- Acciones (POST) ---------- */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    ane_csrf_exige();
    $accion = (string) ($_POST['accion'] ?? '');
    $id = (int) ($_POST['puja'] ?? 0);

    switch ($accion) {
        case 'ajustes':
            ane_maillot_guarda_ajustes($bd, [
                'abierto'     => empty($_POST['abierto']) ? '0' : '1',
                'revision'    => empty($_POST['revision']) ? '0' : '1',
                'titulo'      => ane_recorta((string) ($_POST['titulo'] ?? ''), 120) ?: ANE_MAILLOT_AJUSTES['titulo'],
                'intro'       => ane_recorta((string) ($_POST['intro'] ?? ''), 3000),
                'condiciones' => ane_recorta((string) ($_POST['condiciones'] ?? ''), 6000),
            ]);
            ane_vuelve('ok=ajustes');

        case 'huecos':
            $st = $bd->prepare('UPDATE huecos SET nombre = ?, descripcion = ?, activo = ?, minimo = ?, incremento = ?,
                                  cierre = ?, adjudicado = ?, actualizado = ? WHERE zona = ?');
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
                    ane_ahora(), $zona,
                ]);
            }
            ane_vuelve('ok=huecos');

        case 'validar':
        case 'anular':
            $bd->prepare('UPDATE pujas SET estado = ? WHERE id = ?')
               ->execute([$accion === 'validar' ? 'valida' : 'anulada', $id]);
            ane_vuelve('ok=' . $accion . '#ofertas');

        case 'borrar':
            $bd->prepare('DELETE FROM pujas WHERE id = ?')->execute([$id]);
            ane_vuelve('ok=borrada#ofertas');

        case 'vaciar':
            $bd->exec('DELETE FROM pujas');
            ane_vuelve('ok=vaciado');
    }
    ane_vuelve();
}

/* ---------- Ofertas ---------- */

$filtro = (string) ($_GET['zona'] ?? '');
if (!isset(ANE_MAILLOT_ZONAS[$filtro])) {
    $filtro = '';
}
$sql = 'SELECT * FROM pujas' . ($filtro !== '' ? ' WHERE zona = ?' : '') . ' ORDER BY id DESC';
$st = $bd->prepare($sql);
$st->execute($filtro !== '' ? [$filtro] : []);
$pujas = $st->fetchAll();

/* La más alta válida de cada hueco, para marcarla en la tabla */
$lider = [];
foreach ($bd->query("SELECT zona, id FROM pujas p WHERE estado = 'valida' AND importe =
                      (SELECT MAX(importe) FROM pujas q WHERE q.zona = p.zona AND q.estado = 'valida')
                     ORDER BY id") as $r) {
    $lider[$r['zona']] ??= (int) $r['id'];
}

$ESTADO_PUJA = ['valida' => ['Válida', 'si'], 'pendiente' => ['Por revisar', 'medio'], 'anulada' => ['Anulada', 'no']];

if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ofertas-mono-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Fecha', 'Hueco', 'Importe (€)', 'Estado', 'Más alta', 'Empresa', 'Contacto', 'Correo', 'Teléfono', 'Mensaje'], ';');
    foreach ($pujas as $p) {
        fputcsv($out, [ane_utc_a_local($p['fecha'], 'd/m/Y H:i'), $huecos[$p['zona']]['nombre'] ?? $p['zona'],
                       $p['importe'], $ESTADO_PUJA[$p['estado']][0] ?? $p['estado'],
                       ($lider[$p['zona']] ?? 0) === (int) $p['id'] ? 'Sí' : '',
                       $p['empresa'], $p['contacto'], $p['correo'], $p['telefono'], $p['mensaje']], ';');
    }
    exit;
}

/* ---------- Cifras de cabecera ---------- */

$ajustes = ane_maillot_ajustes($bd);
$ofrecidos = 0;
$conOferta = 0;
$suma = 0;
$pendientes = 0;
foreach ($huecos as $h) {
    $pendientes += $h['pendientes'];
    if ((int) $h['activo']) {
        $ofrecidos++;
        if ($h['maxima'] !== null) {
            $conOferta++;
            $suma += $h['maxima'];
        }
    }
}

$mensajes = [
    'ajustes' => 'Ajustes guardados.', 'huecos' => 'Huecos guardados.', 'validar' => 'Oferta validada: ya cuenta en la web.',
    'anular' => 'Oferta anulada: ya no cuenta en la web.', 'borrada' => 'Oferta borrada.', 'vaciado' => 'Todas las ofertas borradas.',
];
$ok = $mensajes[(string) ($_GET['ok'] ?? '')] ?? '';

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
  .campos input[type=text], .campos textarea, .huecos input[type=text], .huecos input[type=number], .huecos input[type=datetime-local] {
    width: 100%; font: inherit; font-size: 14px; padding: 8px 9px; border: 1px solid #C9CDD3; background: #fff; color: var(--negro); }
  .campos textarea { resize: vertical; }
  .dos { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  @media (max-width: 800px) { .dos { grid-template-columns: 1fr; } }
  .check { display: flex; gap: 10px; align-items: flex-start; font-size: 15px; cursor: pointer; }
  .check input { width: 18px; height: 18px; margin-top: 2px; accent-color: var(--enlace); flex: none; }
  .check small { display: block; color: var(--gris); font-size: 13px; }
  .tabla-scroll { overflow-x: auto; }
  .huecos td { vertical-align: top; padding: 10px 8px 10px 0; }
  .huecos tr.apagado td { background: #F7F8FA; }
  .huecos tr.apagado input[type=text], .huecos tr.apagado input[type=number], .huecos tr.apagado input[type=datetime-local] { color: var(--gris); }
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
</style>
</head>
<body>

<?php ane_admin_cabecera('maillot'); ?>

<main class="caja">

  <?php if ($ok): ?><div class="ok"><?= ane_h($ok) ?></div><?php endif; ?>

  <div class="cifras">
    <div class="cifra"><?= (int) $ajustes['abierto'] ? '<span class="estado estado--si">Abierto</span>' : '<span class="estado estado--no">Cerrado</span>' ?><br><span>Ofertas en la web</span></div>
    <div class="cifra"><b><?= $conOferta ?><span style="font-size:22px;letter-spacing:0;color:var(--gris)"> / <?= $ofrecidos ?></span></b><span>Huecos con oferta</span></div>
    <div class="cifra"><b><?= ane_maillot_euros($suma) ?></b><span>Suma de las más altas</span></div>
    <div class="cifra"><b><?= $pendientes ?></b><span>Ofertas por revisar</span></div>
  </div>

  <?php if ($pendientes && (int) $ajustes['revision']): ?>
    <div class="aviso-datos">Tienes <?= $pendientes ?> oferta<?= $pendientes > 1 ? 's' : '' ?> por revisar. Mientras no las valides, no cuentan en la web. <a href="#ofertas">Ir a las ofertas</a></div>
  <?php endif; ?>

  <!-- ══════════ Huecos ══════════ -->
  <section>
    <div class="barra">
      <h2 class="display">Huecos</h2>
      <a class="boton boton--claro boton--peq" href="/maillot" target="_blank" rel="noopener">Ver la página ↗</a>
    </div>
    <p class="nota">Solo salen en la web los que tengan marcado «Se ofrece». Importes en euros sin IVA. Hora de Madrid.
      Si rellenas «Adjudicado a», el hueco deja de admitir ofertas y en el mono aparece esa marca.</p>
    <form method="post">
      <?= ane_csrf_campo() ?><input type="hidden" name="accion" value="huecos">
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
                <?php if ($h['total']): ?><small><a href="?zona=<?= rawurlencode($zona) ?>#ofertas"><?= $h['total'] ?> oferta<?= $h['total'] > 1 ? 's' : '' ?></a></small><?php endif; ?></td>
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
      <?php if ($pujas): ?><a class="boton boton--claro boton--peq" href="?csv=1<?= $filtro !== '' ? '&zona=' . rawurlencode($filtro) : '' ?>">Descargar CSV</a><?php endif; ?>
    </div>
    <p class="nota">Las más recientes arriba. En negrita, la más alta válida de cada hueco. La web solo enseña la cifra, nunca la empresa.</p>
    <div class="filtro">
      <a href="?#ofertas" class="<?= $filtro === '' ? 'si' : '' ?>">Todas</a>
      <?php foreach ($huecos as $zona => $h): if (!$h['total']) continue; ?>
        <a href="?zona=<?= rawurlencode($zona) ?>#ofertas" class="<?= $filtro === $zona ? 'si' : '' ?>"><?= ane_h($h['nombre']) ?> (<?= $h['total'] ?>)</a>
      <?php endforeach; ?>
    </div>
    <?php if (!$pujas): ?>
      <p class="vacio">Todavía no ha llegado ninguna oferta.</p>
    <?php else: ?>
    <div class="tabla-scroll">
      <table class="ofertas">
        <tr><th>Fecha</th><th>Hueco</th><th class="n">Importe</th><th>Estado</th><th>Empresa</th><th>Contacto</th><th>Mensaje</th><th></th></tr>
        <?php foreach ($pujas as $p): $esLider = ($lider[$p['zona']] ?? 0) === (int) $p['id']; ?>
          <tr class="<?= $p['estado'] === 'anulada' ? 'anulada' : '' ?><?= $esLider ? ' lider' : '' ?>">
            <td style="white-space:nowrap"><?= ane_h(ane_utc_a_local($p['fecha'], 'd/m H:i')) ?></td>
            <td><?= ane_h($huecos[$p['zona']]['nombre'] ?? $p['zona']) ?></td>
            <td class="n importe"><?= ane_maillot_euros((int) $p['importe']) ?></td>
            <td><span class="estado estado--<?= $ESTADO_PUJA[$p['estado']][1] ?? 'no' ?>"><?= $ESTADO_PUJA[$p['estado']][0] ?? ane_h($p['estado']) ?></span><?= $esLider ? '<br><small>La más alta</small>' : '' ?></td>
            <td><?= ane_h($p['empresa']) ?></td>
            <td><?= ane_h($p['contacto']) ?><?= $p['contacto'] !== '' ? '<br>' : '' ?><a href="mailto:<?= ane_h($p['correo']) ?>"><?= ane_h($p['correo']) ?></a><?= $p['telefono'] !== '' ? '<br>' . ane_h($p['telefono']) : '' ?></td>
            <td style="max-width:260px"><?= nl2br(ane_h($p['mensaje'])) ?></td>
            <td style="white-space:nowrap">
              <?php foreach (['validar' => 'Validar', 'anular' => 'Anular'] as $acc => $txt):
                  if (($acc === 'validar' && $p['estado'] === 'valida') || ($acc === 'anular' && $p['estado'] === 'anulada')) continue; ?>
                <form class="linea" method="post"><?= ane_csrf_campo() ?><input type="hidden" name="accion" value="<?= $acc ?>">
                  <input type="hidden" name="puja" value="<?= (int) $p['id'] ?>">
                  <button class="boton boton--peq<?= $acc === 'anular' ? ' boton--peligro' : '' ?>" type="submit"><?= $txt ?></button></form>
              <?php endforeach; ?>
              <form class="linea" method="post" onsubmit="return confirm('¿Borrar esta oferta? No se puede deshacer.')"><?= ane_csrf_campo() ?>
                <input type="hidden" name="accion" value="borrar"><input type="hidden" name="puja" value="<?= (int) $p['id'] ?>">
                <button class="boton boton--peq boton--peligro" type="submit">Borrar</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <!-- ══════════ Ajustes ══════════ -->
  <section>
    <h2 class="display">Ajustes de la página</h2>
    <p class="nota">Para los textos: **negrita**, _cursiva_, [texto](https://enlace), listas con «- » al principio de la línea.</p>
    <form method="post" class="campos">
      <?= ane_csrf_campo() ?><input type="hidden" name="accion" value="ajustes">
      <div class="dos">
        <label class="check"><input type="checkbox" name="abierto" value="1"<?= (int) $ajustes['abierto'] ? ' checked' : '' ?>>
          <span>Aceptar ofertas<small>Apagado: el mono se ve, pero el formulario no sale y la página no se indexa en Google.</small></span></label>
        <label class="check"><input type="checkbox" name="revision" value="1"<?= (int) $ajustes['revision'] ? ' checked' : '' ?>>
          <span>Revisar cada oferta antes de que cuente<small>Protege de ofertas falsas (por ejemplo, 5.000 € a nombre de otra marca para bloquear un hueco). Apagado, cuentan al momento y puedes anularlas después.</small></span></label>
      </div>
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

  <?php if ($pujas && $filtro === ''): ?>
  <section>
    <h2 class="display">Al cerrar la temporada</h2>
    <p class="nota">La web promete borrar los datos de contacto al cerrar el patrocinio de la temporada. Descarga antes el CSV si lo necesitas.</p>
    <form method="post" onsubmit="return confirm('¿Borrar las <?= count($pujas) ?> ofertas? No se puede deshacer.')">
      <?= ane_csrf_campo() ?><input type="hidden" name="accion" value="vaciar">
      <button class="boton boton--peligro" type="submit">Borrar todas las ofertas</button>
    </form>
  </section>
  <?php endif; ?>

</main>
</body>
</html>
