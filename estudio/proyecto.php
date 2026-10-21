<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio · un proyecto
   ------------------------------------------------------------
   Datos, presupuesto, los patrocinadores que se van sumando
   (con su importe y lo que se les ha prometido) y los gastos,
   cuya suma es el coste del proyecto. Solo socios.
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/_comun.php';
ane_pod_solo_socio();

$id = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));
$pr = ane_pod_proyecto($bd, $id);
if (!$pr) {
    header('Location: /estudio/proyectos.php', true, 303);
    exit;
}

function ane_pod_vuelve_pr(int $id, string $query = ''): void
{
    /* Si vuelve a un acuerdo (…#a12) o a un gasto (…#g7), se abre: ?abre=a12 */
    if (preg_match('/#([ag]\d+)$/', $query, $m)) {
        $query = substr($query, 0, -strlen($m[0])) . '&abre=' . $m[1] . $m[0];
    }
    header('Location: /estudio/proyecto.php?id=' . $id . ($query !== '' ? '&' . ltrim($query, '&') : ''), true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    ane_pod_csrf_exige();
    $accion = (string) ($_POST['accion'] ?? '');
    $fila = (int) ($_POST['fila'] ?? 0);

    switch ($accion) {
        case 'datos':
            $bd->prepare('UPDATE pod_proyectos SET nombre = ?, detalle = ?, estado = ?, descripcion = ?,
                            objetivo = ?, notas = ?, actualizado = ? WHERE id = ?')
               ->execute([ane_recorta((string) ($_POST['nombre'] ?? ''), 160) ?: 'Proyecto sin nombre',
                          ane_recorta((string) ($_POST['detalle'] ?? ''), 160),
                          isset(ANE_POD_PROY_ESTADOS[(string) ($_POST['estado'] ?? '')]) ? (string) $_POST['estado'] : 'abierto',
                          ane_recorta((string) ($_POST['descripcion'] ?? ''), 6000),
                          max(0, (int) ($_POST['objetivo'] ?? 0)),
                          ane_recorta((string) ($_POST['notas'] ?? ''), 6000), ane_ahora(), $id]);
            ane_pod_vuelve_pr($id, 'ok=guardado');

        case 'marca_nueva':
            $marcaId = (int) ($_POST['marca_id'] ?? 0);
            $marca = $marcaId ? (string) (ane_pod_marca($bd, $marcaId)['nombre'] ?? '')
                              : ane_recorta((string) ($_POST['marca'] ?? ''), 120);
            if ($marca === '') {
                ane_pod_vuelve_pr($id, 'mal=marca_falta#nuevo-acuerdo');
            }
            $nuevo = ane_pod_crea_acuerdo($bd, $id, $marcaId, $marca, ane_pod_datos_acuerdo($_POST));
            ane_pod_vuelve_pr($id, 'ok=marca#a' . $nuevo);

        case 'marca_guardar':
            $a = ane_pod_acuerdo($bd, $fila);
            if ($a && (int) $a['proyecto_id'] === $id) {
                ane_pod_actualiza_acuerdo($bd, $fila, ane_pod_datos_acuerdo($_POST));
            }
            ane_pod_vuelve_pr($id, 'ok=guardado#a' . $fila);

        /* El contrato de un acuerdo: se cuelga del acuerdo y de su marca */
        case 'contrato_nuevo':
            $a = ane_pod_acuerdo($bd, $fila);
            if (!$a || (int) $a['proyecto_id'] !== $id) {
                ane_pod_vuelve_pr($id);
            }
            $r = 'vacio';
            $subidos = 0;
            foreach (ane_pod_subidas('contrato') as $f) {
                $r = ane_pod_guarda_contrato($bd, (int) $a['marca_id'], $fila, $f);
                $subidos += $r === '' ? 1 : 0;
            }
            ane_pod_vuelve_pr($id, ($subidos ? 'ok=contrato' : 'mal=contrato_' . $r) . '#a' . $fila);

        case 'contrato_borrar':
            $c = ane_pod_contrato($bd, $fila);
            $a = $c ? ane_pod_acuerdo($bd, (int) $c['acuerdo_id']) : null;
            if ($c && $a && (int) $a['proyecto_id'] === $id) {
                ane_pod_borra_contrato($bd, $c);
                ane_pod_vuelve_pr($id, 'ok=contrato_borrado#a' . (int) $a['id']);
            }
            ane_pod_vuelve_pr($id);

        /* Gastos: concepto, importe y si ya está pagado. Su suma es el coste */
        case 'gasto_nuevo':
            $g = ane_pod_datos_gasto($_POST);
            if ($g['concepto'] === '') {
                ane_pod_vuelve_pr($id, 'mal=gasto_falta#gastos');
            }
            $bd->prepare('INSERT INTO pod_gastos (proyecto_id, concepto, importe, pagado, nota, creado) VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([$id, $g['concepto'], $g['importe'], $g['pagado'], $g['nota'], ane_ahora()]);
            ane_pod_vuelve_pr($id, 'ok=gasto#gastos');

        case 'gasto_guardar':
            $g = ane_pod_gasto($bd, $fila);
            if ($g && (int) $g['proyecto_id'] === $id) {
                $d = ane_pod_datos_gasto($_POST);
                $bd->prepare('UPDATE pod_gastos SET concepto = ?, importe = ?, pagado = ?, nota = ? WHERE id = ?')
                   ->execute([$d['concepto'] ?: $g['concepto'], $d['importe'], $d['pagado'], $d['nota'], $fila]);
            }
            ane_pod_vuelve_pr($id, 'ok=guardado#gastos');

        case 'gasto_borrar':
            $bd->prepare('DELETE FROM pod_gastos WHERE id = ? AND proyecto_id = ?')->execute([$fila, $id]);
            ane_pod_vuelve_pr($id, 'ok=borrado#gastos');

        case 'marca_borrar':
            $a = ane_pod_acuerdo($bd, $fila);
            if ($a && (int) $a['proyecto_id'] === $id) {
                ane_pod_borra_acuerdo($bd, $fila);
            }
            ane_pod_vuelve_pr($id, 'ok=borrado#marcas');

        case 'borrar':
            /* «Podcast» no se borra: es donde caen los patrocinios de episodios sueltos */
            if ((int) $pr['fijo']) {
                ane_pod_vuelve_pr($id, 'mal=fijo');
            }
            $st = $bd->prepare('SELECT id FROM pod_proy_marcas WHERE proyecto_id = ?');
            $st->execute([$id]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $acuerdo) {
                ane_pod_borra_acuerdo($bd, (int) $acuerdo);
            }
            $bd->prepare('DELETE FROM pod_gastos WHERE proyecto_id = ?')->execute([$id]);
            $bd->prepare('DELETE FROM pod_proyectos WHERE id = ?')->execute([$id]);
            header('Location: /estudio/proyectos.php?ok=borrado', true, 303);
            exit;
    }
    ane_pod_vuelve_pr($id);
}

$suyas = ane_pod_proyecto_marcas($bd, $id);
$cuentas = ane_pod_cuentas($suyas);
$marcas = ane_pod_marcas($bd);
$objetivo = (int) $pr['objetivo'];
$gastos = ane_pod_gastos($bd, $id);
$suma = ane_pod_suma_gastos($gastos);
$coste = $suma['total'];

ane_pod_cabeza('Proyecto · ' . $pr['nombre']);
ane_pod_cabecera('proyectos');
?>
<main class="caja">
  <p class="nota"><a href="/estudio/proyectos.php">← Todos los proyectos</a></p>
  <?php ane_pod_aviso(['guardado' => 'Guardado. Los episodios y las cuñas ya están al día.',
                       'marca' => 'Acuerdo apuntado. Si está firmado, sus episodios ya llevan la cuña.',
                       'borrado' => 'Borrado.', 'creado' => 'Proyecto creado.', 'marca_falta' => 'Falta la marca.',
                       'fijo' => '«Podcast» es fijo: no se puede borrar.',
                       'gasto' => 'Gasto apuntado.', 'gasto_falta' => 'Falta el concepto: casa, vuelos…',
                       'contrato' => 'Contrato guardado.', 'contrato_borrado' => 'Contrato borrado.',
                       'contrato_formato' => 'Eso no entra como contrato: tiene que ser PDF, Word (docx, doc, odt) o una foto (jpg, png), y serlo de verdad.',
                       'contrato_grande' => 'El contrato pesa demasiado: hasta 32 MB.',
                       'contrato_vacio' => 'No elegiste ningún fichero.',
                       'contrato_subida' => 'La subida se cortó. Inténtalo otra vez.',
                       'contrato_disco' => 'No se ha podido guardar el contrato en el servidor.']); ?>

  <div class="barra">
    <h2 class="display" style="font-size:30px"><?= ane_h($pr['nombre']) ?></h2>
    <div class="acciones">
      <?php if ((int) $pr['fijo']): ?><span class="chip">fijo</span><?php endif; ?>
      <?php if ($pr['estado'] === 'cerrado'): ?><?= ane_pod_chip('cerrado', ANE_POD_PROY_ESTADOS) ?><?php endif; ?>
    </div>
  </div>

  <div class="cifras">
    <div class="cifra"><b><?= ane_pod_euros($cuentas['confirmado']) ?></b><span>Confirmado</span></div>
    <div class="cifra"><b><?= ane_pod_euros($cuentas['negociando']) ?></b><span>En conversaciones</span></div>
    <div class="cifra"><b><?= $objetivo ? ane_pod_euros($objetivo) : '—' ?></b><span>Objetivo</span></div>
    <div class="cifra"><b><?= $coste ? ane_pod_euros($coste) : '—' ?></b><span>Gastos</span></div>
    <?php if ($coste && $cuentas['confirmado'] >= $coste): ?>
      <div class="cifra"><b><?= ane_pod_euros($cuentas['confirmado'] - $coste) ?></b><span>Sobra tras los gastos</span></div>
    <?php else: ?>
      <div class="cifra"><b><?= $coste ? ane_pod_euros($coste - $cuentas['confirmado']) : '—' ?></b>
        <span>Falta para cubrir gastos</span></div>
    <?php endif; ?>
  </div>

  <!-- ---------------- Acuerdos ---------------- -->
  <section id="marcas">
    <h2>Acuerdos con marcas</h2>
    <p class="nota">Quién pone dinero en este proyecto, cuánto, a cambio de qué y en qué episodios sale su cuña.
      Firmado (confirmado o facturado), los episodios que falten <b>se crean solos</b> y cada uno lleva su cuña,
      que luego colocas entre los temas. Esto no sale nunca de aquí: el editor solo ve la cuña en el guión.</p>
    <?php if (!$suyas): ?><p class="vacio">Ningún acuerdo todavía.</p><?php endif; ?>

    <?php foreach ($suyas as $m):
        $mid = (int) $m['id'];
        $firmado = in_array($m['estado'], ANE_POD_FIRMADO, true);
        $enGuion = ane_pod_episodios_de_acuerdo($bd, $mid);
        $contratos = ane_pod_contratos($bd, 'acuerdo', $mid);
        $tipos = ane_pod_tipos_texto((string) $m['tipo']); ?>
      <details id="a<?= $mid ?>" class="marca acuerdo<?= $firmado ? ' firmado' : '' ?>"<?= (string) ($_GET['abre'] ?? '') === 'a' . $mid ? ' open' : '' ?>>
        <summary>
          <span class="marca-nombre"><?= ane_h(($m['marca_nombre'] ?? '') ?: $m['marca']) ?></span>
          <?= ane_pod_chip($m['estado'], ANE_POD_PATRO_ESTADOS) ?>
          <span class="marca-datos">
            <?php if ((int) $m['importe']): ?><b><?= ane_pod_euros((int) $m['importe']) ?></b><?php endif; ?>
            <?php if ($m['episodios'] !== ''): ?><span>episodios <?= ane_h($m['episodios']) ?></span><?php endif; ?>
            <?php if ($contratos): ?><span><?= count($contratos) ?> contrato<?= count($contratos) === 1 ? '' : 's' ?></span><?php endif; ?>
          </span>
          <?php if ($tipos !== ''): ?><span class="marca-siguiente"><?= ane_h($tipos) ?></span><?php endif; ?>
        </summary>
        <div class="marca-cuerpo">
        <form method="post" id="ab<?= $mid ?>" class="escondida"
              onsubmit="return confirm('¿Quitar este acuerdo? Sus cuñas pendientes salen de los episodios; las ya hechas se quedan.')">
          <?= ane_pod_csrf_campo() ?>
          <input type="hidden" name="accion" value="marca_borrar">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="fila" value="<?= $mid ?>">
        </form>
        <form method="post">
          <?= ane_pod_csrf_campo() ?>
          <input type="hidden" name="accion" value="marca_guardar">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="fila" value="<?= $mid ?>">
          <?php if ($m['marca_correo'] ?? ''): ?>
            <p class="dom" style="margin:0 0 10px">Contacto: <?= ane_h($m['marca_correo']) ?></p>
          <?php endif; ?>
          <div class="campos" style="grid-template-columns:1fr 1fr 1fr">
            <div class="campo" style="margin:0"><label>Estado</label>
              <select name="estado">
                <?php foreach (ANE_POD_PATRO_ESTADOS as $k => $v): ?>
                  <option value="<?= $k ?>"<?= $m['estado'] === $k ? ' selected' : '' ?>><?= ane_h($v) ?></option>
                <?php endforeach; ?>
              </select></div>
            <div class="campo" style="margin:0"><label>Cuánto pone (€)</label>
              <input type="number" name="importe" min="0" step="100" value="<?= (int) $m['importe'] ?>"></div>
            <div class="campo" style="margin:0"><label>Episodios con cuña</label>
              <input type="text" name="episodios" maxlength="300" value="<?= ane_h($m['episodios']) ?>"
                     placeholder="10-13, 20" inputmode="numeric"></div>
          </div>
          <div class="campos" style="grid-template-columns:1fr 2fr;margin-top:12px">
            <div class="campo" style="margin:0"><label>Tipo de cuña · uno o varios</label>
              <?= ane_pod_casillas_tipo((string) $m['tipo'], 'ta' . $mid) ?></div>
            <div class="campo" style="margin:0"><label>Qué hay que decir en cada cuña</label>
              <textarea name="texto" maxlength="4000" style="min-height:60px"><?= ane_h($m['texto']) ?></textarea></div>
          </div>
          <div class="campos" style="grid-template-columns:1fr 1fr;margin-top:12px">
            <div class="campo" style="margin:0"><label>Contrapartidas</label>
              <textarea name="contrapartidas" maxlength="2000" style="min-height:60px"><?= ane_h($m['contrapartidas']) ?></textarea></div>
            <div class="campo" style="margin:0"><label>Notas</label>
              <textarea name="notas" maxlength="2000" style="min-height:60px"><?= ane_h($m['notas']) ?></textarea></div>
          </div>

          <div class="en-guion">
            <?php if ($enGuion): ?>
              <span class="dom">En el guión de:</span>
              <?php foreach ($enGuion as $e): ?>
                <a class="chip<?= (int) $e['hecho'] ? ' confirmado' : ' planificado' ?>"
                   href="/estudio/episodio.php?id=<?= (int) $e['id'] ?>#guion"
                   title="<?= (int) $e['hecho'] ? 'Cuña hecha' : 'Cuña pendiente' ?>">
                  <?= (int) $e['hecho'] ? '✓ ' : '' ?>#<?= (int) $e['numero'] ?></a>
              <?php endforeach; ?>
            <?php elseif ($m['episodios'] !== '' && !$firmado): ?>
              <?php $varios = count(ane_pod_numeros((string) $m['episodios'])) > 1; ?>
              <span class="dom"><?= $varios ? 'Las cuñas se pondrán en los episodios' : 'La cuña se pondrá en el episodio' ?>
                <?= ane_h($m['episodios']) ?> cuando esté firmado.</span>
            <?php else: ?>
              <span class="dom">Sin episodios: este acuerdo no lleva cuñas en el podcast.</span>
            <?php endif; ?>
          </div>

          <div class="acciones" style="margin-top:10px">
            <button class="btn pequeno" type="submit">Guardar</button>
            <button class="btn peligro pequeno" type="submit" form="ab<?= $mid ?>">Quitar acuerdo</button>
          </div>
        </form>

        <!-- Contrato del acuerdo -->
        <div class="contrato-acuerdo">
          <label>Contrato<?= count($contratos) > 1 ? 's' : '' ?></label>
          <?php if ($contratos): ?>
            <ul class="contratos">
              <?php foreach ($contratos as $c): $cid = (int) $c['id']; ?>
                <li>
                  <form method="post" id="cb<?= $cid ?>" class="escondida"
                        onsubmit="return confirm('¿Borrar este contrato? No hay vuelta.')">
                    <?= ane_pod_csrf_campo() ?>
                    <input type="hidden" name="accion" value="contrato_borrar">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="fila" value="<?= $cid ?>">
                  </form>
                  <div><b><?= ane_h($c['nombre']) ?></b>
                    <span class="dom">· <?= ane_h(ane_pod_tamano((int) $c['tamano'])) ?> · <?= ane_h(ane_utc_a_local($c['creado'], 'j/n/Y')) ?></span></div>
                  <div class="acciones">
                    <?php if ($c['mime'] === 'application/pdf'): ?>
                      <a class="btn claro pequeno" href="/estudio/contrato.php?c=<?= $cid ?>" target="_blank" rel="noopener">Ver</a>
                    <?php endif; ?>
                    <a class="btn claro pequeno" href="/estudio/contrato.php?c=<?= $cid ?>&amp;d=1">Descargar</a>
                    <button class="btn peligro pequeno" type="submit" form="cb<?= $cid ?>" aria-label="Borrar contrato">✕</button>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <form method="post" enctype="multipart/form-data" class="subir">
            <?= ane_pod_csrf_campo() ?>
            <input type="hidden" name="accion" value="contrato_nuevo">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="fila" value="<?= $mid ?>">
            <input type="file" name="contrato[]" accept=".pdf,.docx,.doc,.odt,.jpg,.jpeg,.png" required>
            <button class="btn claro pequeno" type="submit"><?= $contratos ? 'Subir otro' : 'Subir contrato' ?></button>
          </form>
        </div>
        </div>
      </details>
    <?php endforeach; ?>

    <details id="nuevo-acuerdo" class="nueva-marca" style="margin-top:14px"<?= ($_GET['mal'] ?? '') === 'marca_falta' ? ' open' : '' ?>>
    <summary class="btn azul">+ Nuevo acuerdo</summary>
    <form method="post" class="anadir-uno">
      <?= ane_pod_csrf_campo() ?>
      <input type="hidden" name="accion" value="marca_nueva">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="campos">
        <div class="campo" style="margin:0"><label>Marca de la agenda</label>
          <select name="marca_id">
            <option value="0">— escribirla a mano —</option>
            <?php foreach ($marcas as $m): ?>
              <option value="<?= (int) $m['id'] ?>"><?= ane_h($m['nombre']) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="campo" style="margin:0"><label>…o a mano</label>
          <input type="text" name="marca" maxlength="120"></div>
        <div class="campo" style="margin:0"><label>Estado</label>
          <select name="estado">
            <?php foreach (ANE_POD_PATRO_ESTADOS as $k => $v): ?>
              <option value="<?= $k ?>"<?= $k === 'confirmado' ? ' selected' : '' ?>><?= ane_h($v) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="campo" style="margin:0"><label>Cuánto pone (€)</label>
          <input type="number" name="importe" min="0" step="100" value="0"></div>
        <div class="campo" style="margin:0"><label>Episodios con cuña</label>
          <input type="text" name="episodios" maxlength="300" placeholder="10-13, 20" inputmode="numeric"></div>
      </div>
      <div class="campo" style="margin:12px 0 0"><label>Tipo de cuña · uno o varios</label>
        <?= ane_pod_casillas_tipo('explicito', 'tnuevo') ?></div>
      <div class="campo" style="margin:12px 0 10px"><label>Qué hay que decir en cada cuña</label>
        <textarea name="texto" maxlength="4000" style="min-height:60px"></textarea></div>
      <div class="campo" style="margin:0 0 10px"><label>Contrapartidas</label>
        <textarea name="contrapartidas" maxlength="2000" style="min-height:60px"
                  placeholder="Logo en el mono · dos menciones por episodio · un reel"></textarea></div>
      <button class="btn azul" type="submit">Apuntar acuerdo</button>
    </form>
    </details>

    <?php /* Al volver de guardar (…#a12, …#g7), el acuerdo o el gasto tocado se abre
             solo. Espera a la página entera: los gastos van más abajo que este script */ ?>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var d = location.hash && document.getElementById(location.hash.slice(1));
        if (d && d.tagName === 'DETAILS') { d.open = true; d.scrollIntoView(); }
      });
    </script>
  </section>

  <!-- ---------------- Gastos ---------------- -->
  <section id="gastos">
    <h2>Gastos</h2>
    <p class="nota">Lo que cuesta el proyecto, partida a partida: casa, vuelos, inscripción… La suma es el coste
      del proyecto y se compara con lo confirmado arriba.</p>
    <?php if (!$gastos): ?><p class="vacio">Ningún gasto todavía.</p><?php endif; ?>

    <?php foreach ($gastos as $g): $gid = (int) $g['id']; ?>
      <details id="g<?= $gid ?>" class="marca gasto<?= (int) $g['pagado'] ? ' pagado' : '' ?>"<?= (string) ($_GET['abre'] ?? '') === 'g' . $gid ? ' open' : '' ?>>
        <summary>
          <span class="marca-nombre"><?= ane_h($g['concepto']) ?></span>
          <span class="marca-datos">
            <b><?= ane_pod_euros((int) $g['importe']) ?></b>
            <span><?= (int) $g['pagado'] ? '✓ pagado' : 'por pagar' ?></span>
          </span>
          <?php if ($g['nota'] !== ''): ?><span class="marca-siguiente"><?= ane_h($g['nota']) ?></span><?php endif; ?>
        </summary>
        <div class="marca-cuerpo">
          <form method="post" id="gb<?= $gid ?>" class="escondida" onsubmit="return confirm('¿Quitar este gasto?')">
            <?= ane_pod_csrf_campo() ?>
            <input type="hidden" name="accion" value="gasto_borrar">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="fila" value="<?= $gid ?>">
          </form>
          <form method="post">
            <?= ane_pod_csrf_campo() ?>
            <input type="hidden" name="accion" value="gasto_guardar">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="fila" value="<?= $gid ?>">
            <div class="campos" style="grid-template-columns:2fr 1fr">
              <div class="campo" style="margin:0"><label>Concepto</label>
                <input type="text" name="concepto" maxlength="120" list="conceptos" value="<?= ane_h($g['concepto']) ?>" required></div>
              <div class="campo" style="margin:0"><label>Importe (€)</label>
                <input type="number" name="importe" min="0" step="any" inputmode="decimal" value="<?= (int) $g['importe'] ?>"></div>
            </div>
            <div class="campo" style="margin:12px 0 0"><label>Nota</label>
              <input type="text" name="nota" maxlength="500" value="<?= ane_h($g['nota']) ?>"
                     placeholder="Opcional: qué es, dónde, cuántas noches…"></div>
            <label class="cas pagado-cas"><input type="checkbox" name="pagado" value="1"<?= (int) $g['pagado'] ? ' checked' : '' ?>> Ya está pagado</label>
            <div class="acciones" style="margin-top:10px">
              <button class="btn pequeno" type="submit">Guardar</button>
              <button class="btn peligro pequeno" type="submit" form="gb<?= $gid ?>">Quitar gasto</button>
            </div>
          </form>
        </div>
      </details>
    <?php endforeach; ?>

    <?php if ($gastos): ?>
      <div class="gastos-total">
        <span>Total</span><b><?= ane_pod_euros($suma['total']) ?></b>
        <?php if ($suma['pagado'] && $suma['pendiente']): ?>
          <span class="dom"><?= ane_pod_euros($suma['pagado']) ?> pagado · <?= ane_pod_euros($suma['pendiente']) ?> por pagar</span>
        <?php elseif ($suma['pagado']): ?>
          <span class="dom">todo pagado</span>
        <?php else: ?>
          <span class="dom">nada pagado aún</span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="post" class="gasto-nuevo">
      <?= ane_pod_csrf_campo() ?>
      <input type="hidden" name="accion" value="gasto_nuevo">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="campo" style="margin:0"><label>Concepto</label>
        <input type="text" name="concepto" maxlength="120" list="conceptos" placeholder="Casa, vuelos…" required></div>
      <div class="campo" style="margin:0"><label>Importe (€)</label>
        <input type="number" name="importe" min="0" step="any" inputmode="decimal" placeholder="0"></div>
      <div class="campo" style="margin:0"><label>Nota</label>
        <input type="text" name="nota" maxlength="500" placeholder="Opcional"></div>
      <label class="cas pagado-cas"><input type="checkbox" name="pagado" value="1"> Pagado</label>
      <button class="btn azul" type="submit">+ Añadir gasto</button>
    </form>
    <datalist id="conceptos">
      <?php foreach (ane_pod_conceptos_gasto($bd) as $c): ?><option value="<?= ane_h($c) ?>"><?php endforeach; ?>
    </datalist>
  </section>

  <!-- ---------------- Datos ---------------- -->
  <section>
    <h2>Datos y presupuesto</h2>
    <form method="post">
      <?= ane_pod_csrf_campo() ?>
      <input type="hidden" name="accion" value="datos">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="campos">
        <div class="campo" style="margin:0"><label>Nombre</label>
          <input type="text" name="nombre" maxlength="160" value="<?= ane_h($pr['nombre']) ?>"></div>
        <div class="campo" style="margin:0"><label>Detalle</label>
          <input type="text" name="detalle" maxlength="160" value="<?= ane_h($pr['detalle']) ?>"></div>
        <div class="campo" style="margin:0"><label>Estado</label>
          <select name="estado">
            <?php foreach (ANE_POD_PROY_ESTADOS as $k => $v): ?>
              <option value="<?= $k ?>"<?= $pr['estado'] === $k ? ' selected' : '' ?>><?= ane_h($v) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="campo" style="margin:0"><label>Objetivo (€)</label>
          <input type="number" name="objetivo" min="0" step="100" value="<?= $objetivo ?>"></div>
      </div>
      <div class="campo" style="margin:14px 0"><label>Qué es</label>
        <textarea name="descripcion" maxlength="6000" style="min-height:70px"><?= ane_h($pr['descripcion']) ?></textarea></div>
      <div class="campo"><label>Notas internas</label>
        <textarea name="notas" maxlength="6000" style="min-height:70px"><?= ane_h($pr['notas']) ?></textarea></div>
      <button class="btn azul" type="submit">Guardar</button>
    </form>
  </section>

  <?php if (!(int) $pr['fijo']): ?>
  <section>
    <h2>Borrar el proyecto</h2>
    <p class="nota">Se va con sus acuerdos y sus gastos. Las cuñas pendientes salen de los episodios; las hechas se quedan.</p>
    <form method="post" onsubmit="return confirm('¿Borrar el proyecto?')">
      <?= ane_pod_csrf_campo() ?>
      <input type="hidden" name="accion" value="borrar">
      <input type="hidden" name="id" value="<?= $id ?>">
      <button class="btn peligro" type="submit">Borrar</button>
    </form>
  </section>
  <?php endif; ?>
</main>
<?php
ane_pod_pie();
