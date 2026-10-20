<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio · un proyecto
   ------------------------------------------------------------
   Datos, presupuesto y los patrocinadores que se van sumando,
   con su importe y lo que se les ha prometido. Solo socios.
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
    header('Location: /estudio/proyecto.php?id=' . $id . ($query !== '' ? '&' . $query : ''), true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    ane_pod_csrf_exige();
    $accion = (string) ($_POST['accion'] ?? '');
    $fila = (int) ($_POST['fila'] ?? 0);

    switch ($accion) {
        case 'datos':
            $bd->prepare('UPDATE pod_proyectos SET nombre = ?, detalle = ?, estado = ?, descripcion = ?,
                            objetivo = ?, coste = ?, notas = ?, actualizado = ? WHERE id = ?')
               ->execute([ane_recorta((string) ($_POST['nombre'] ?? ''), 160) ?: 'Proyecto sin nombre',
                          ane_recorta((string) ($_POST['detalle'] ?? ''), 160),
                          isset(ANE_POD_PROY_ESTADOS[(string) ($_POST['estado'] ?? '')]) ? (string) $_POST['estado'] : 'idea',
                          ane_recorta((string) ($_POST['descripcion'] ?? ''), 6000),
                          max(0, (int) ($_POST['objetivo'] ?? 0)), max(0, (int) ($_POST['coste'] ?? 0)),
                          ane_recorta((string) ($_POST['notas'] ?? ''), 6000), ane_ahora(), $id]);
            ane_pod_vuelve_pr($id, 'ok=guardado');

        case 'marca_nueva':
            $marcaId = (int) ($_POST['marca_id'] ?? 0);
            $marca = $marcaId ? (string) (ane_pod_marca($bd, $marcaId)['nombre'] ?? '')
                              : ane_recorta((string) ($_POST['marca'] ?? ''), 120);
            if ($marca === '') {
                ane_pod_vuelve_pr($id, 'mal=marca#marcas');
            }
            $bd->prepare('INSERT INTO pod_proy_marcas (proyecto_id, marca_id, marca, estado, importe, contrapartidas, notas)
                          VALUES (?, ?, ?, ?, ?, ?, ?)')
               ->execute([$id, $marcaId, $marca,
                          isset(ANE_POD_PATRO_ESTADOS[(string) ($_POST['estado'] ?? '')]) ? (string) $_POST['estado'] : 'contactado',
                          max(0, (int) ($_POST['importe'] ?? 0)),
                          ane_recorta((string) ($_POST['contrapartidas'] ?? ''), 2000),
                          ane_recorta((string) ($_POST['notas'] ?? ''), 2000)]);
            ane_pod_vuelve_pr($id, 'ok=marca#marcas');

        case 'marca_guardar':
            $bd->prepare('UPDATE pod_proy_marcas SET estado = ?, importe = ?, contrapartidas = ?, notas = ?
                          WHERE id = ? AND proyecto_id = ?')
               ->execute([isset(ANE_POD_PATRO_ESTADOS[(string) ($_POST['estado'] ?? '')]) ? (string) $_POST['estado'] : 'contactado',
                          max(0, (int) ($_POST['importe'] ?? 0)),
                          ane_recorta((string) ($_POST['contrapartidas'] ?? ''), 2000),
                          ane_recorta((string) ($_POST['notas'] ?? ''), 2000), $fila, $id]);
            ane_pod_vuelve_pr($id, 'ok=guardado#marcas');

        case 'marca_borrar':
            $bd->prepare('DELETE FROM pod_proy_marcas WHERE id = ? AND proyecto_id = ?')->execute([$fila, $id]);
            ane_pod_vuelve_pr($id, 'ok=borrado#marcas');

        case 'borrar':
            $bd->prepare('DELETE FROM pod_proy_marcas WHERE proyecto_id = ?')->execute([$id]);
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
$coste = (int) $pr['coste'];

ane_pod_cabeza('Proyecto · ' . $pr['nombre']);
ane_pod_cabecera('proyectos');
?>
<main class="caja">
  <p class="nota"><a href="/estudio/proyectos.php">← Todos los proyectos</a></p>
  <?php ane_pod_aviso(['guardado' => 'Cambios guardados.', 'marca' => 'Patrocinador añadido.',
                       'borrado' => 'Borrado.', 'creado' => 'Proyecto creado.',
                       'mal=marca' => 'Falta la marca.', 'marca_falta' => 'Falta la marca.']); ?>

  <div class="barra">
    <h2 class="display" style="font-size:30px"><?= ane_h($pr['nombre']) ?></h2>
    <?= ane_pod_chip($pr['estado'], ANE_POD_PROY_ESTADOS) ?>
  </div>

  <div class="cifras">
    <div class="cifra"><b><?= ane_pod_euros($cuentas['confirmado']) ?></b><span>Confirmado</span></div>
    <div class="cifra"><b><?= ane_pod_euros($cuentas['negociando']) ?></b><span>En conversaciones</span></div>
    <div class="cifra"><b><?= $objetivo ? ane_pod_euros($objetivo) : '—' ?></b><span>Objetivo</span></div>
    <div class="cifra"><b><?= $coste ? ane_pod_euros(max(0, $coste - $cuentas['confirmado'])) : '—' ?></b>
      <span>Falta para el coste</span></div>
  </div>

  <!-- ---------------- Patrocinadores ---------------- -->
  <section id="marcas">
    <h2>Patrocinadores del proyecto</h2>
    <p class="nota">Estado, importe y lo que se les ha prometido. Esto no sale nunca de aquí.</p>
    <?php if (!$suyas): ?><p class="vacio">Ninguno todavía.</p><?php endif; ?>

    <?php foreach ($suyas as $m): $mid = (int) $m['id']; ?>
      <div class="tema<?= in_array($m['estado'], ['confirmado', 'facturado'], true) ? ' hecho' : '' ?>">
        <form method="post">
          <?= ane_pod_csrf_campo() ?>
          <input type="hidden" name="accion" value="marca_guardar">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="fila" value="<?= $mid ?>">
          <div class="barra" style="margin:0 0 10px">
            <h3 style="margin:0"><?= ane_h(($m['marca_nombre'] ?? '') ?: $m['marca']) ?>
              <?php if ($m['marca_correo'] ?? ''): ?>
                <span class="dom"><?= ane_h($m['marca_correo']) ?></span><?php endif; ?></h3>
            <?= ane_pod_chip($m['estado'], ANE_POD_PATRO_ESTADOS) ?>
          </div>
          <div class="campos" style="grid-template-columns:1fr 1fr">
            <div class="campo" style="margin:0"><label>Estado</label>
              <select name="estado">
                <?php foreach (ANE_POD_PATRO_ESTADOS as $k => $v): ?>
                  <option value="<?= $k ?>"<?= $m['estado'] === $k ? ' selected' : '' ?>><?= ane_h($v) ?></option>
                <?php endforeach; ?>
              </select></div>
            <div class="campo" style="margin:0"><label>Importe (€)</label>
              <input type="number" name="importe" min="0" step="100" value="<?= (int) $m['importe'] ?>"></div>
          </div>
          <div class="campo" style="margin:12px 0 10px"><label>Contrapartidas</label>
            <textarea name="contrapartidas" maxlength="2000" style="min-height:60px"><?= ane_h($m['contrapartidas']) ?></textarea></div>
          <div class="campo" style="margin:0 0 10px"><label>Notas</label>
            <textarea name="notas" maxlength="2000" style="min-height:60px"><?= ane_h($m['notas']) ?></textarea></div>
          <button class="btn pequeno" type="submit">Guardar</button>
        </form>
        <form method="post" onsubmit="return confirm('¿Quitar esta marca del proyecto?')" style="margin-top:10px">
          <?= ane_pod_csrf_campo() ?>
          <input type="hidden" name="accion" value="marca_borrar">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="fila" value="<?= $mid ?>">
          <button class="btn peligro pequeno" type="submit">Quitar del proyecto</button>
        </form>
      </div>
    <?php endforeach; ?>

    <h3>Añadir patrocinador</h3>
    <form method="post">
      <?= ane_pod_csrf_campo() ?>
      <input type="hidden" name="accion" value="marca_nueva">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="campos">
        <div class="campo" style="margin:0"><label>De la agenda</label>
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
              <option value="<?= $k ?>"><?= ane_h($v) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="campo" style="margin:0"><label>Importe (€)</label>
          <input type="number" name="importe" min="0" step="100" value="0"></div>
      </div>
      <div class="campo" style="margin:12px 0 10px"><label>Contrapartidas</label>
        <textarea name="contrapartidas" maxlength="2000" style="min-height:60px"
                  placeholder="Logo en el mono · dos menciones por episodio · un reel"></textarea></div>
      <button class="btn azul" type="submit">Añadir</button>
    </form>
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
        <div class="campo" style="margin:0"><label>Coste previsto (€)</label>
          <input type="number" name="coste" min="0" step="100" value="<?= $coste ?>"></div>
      </div>
      <div class="campo" style="margin:14px 0"><label>Qué es</label>
        <textarea name="descripcion" maxlength="6000" style="min-height:70px"><?= ane_h($pr['descripcion']) ?></textarea></div>
      <div class="campo"><label>Notas internas</label>
        <textarea name="notas" maxlength="6000" style="min-height:70px"><?= ane_h($pr['notas']) ?></textarea></div>
      <button class="btn azul" type="submit">Guardar</button>
    </form>
  </section>

  <section>
    <h2>Borrar el proyecto</h2>
    <p class="nota">Se va con sus patrocinadores apuntados.</p>
    <form method="post" onsubmit="return confirm('¿Borrar el proyecto?')">
      <?= ane_pod_csrf_campo() ?>
      <input type="hidden" name="accion" value="borrar">
      <input type="hidden" name="id" value="<?= $id ?>">
      <button class="btn peligro" type="submit">Borrar</button>
    </form>
  </section>
</main>
<?php
ane_pod_pie();
