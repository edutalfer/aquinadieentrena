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
  <?php ane_pod_aviso(['guardado' => 'Guardado. Los episodios y las cuñas ya están al día.',
                       'marca' => 'Acuerdo apuntado. Si está firmado, sus episodios ya llevan la cuña.',
                       'borrado' => 'Borrado.', 'creado' => 'Proyecto creado.', 'marca_falta' => 'Falta la marca.',
                       'fijo' => '«Podcast» es fijo: no se puede borrar.']); ?>

  <div class="barra">
    <h2 class="display" style="font-size:30px"><?= ane_h($pr['nombre']) ?></h2>
    <div class="acciones">
      <?php if ((int) $pr['fijo']): ?><span class="chip">fijo</span><?php endif; ?>
      <?= ane_pod_chip($pr['estado'], ANE_POD_PROY_ESTADOS) ?>
    </div>
  </div>

  <div class="cifras">
    <div class="cifra"><b><?= ane_pod_euros($cuentas['confirmado']) ?></b><span>Confirmado</span></div>
    <div class="cifra"><b><?= ane_pod_euros($cuentas['negociando']) ?></b><span>En conversaciones</span></div>
    <div class="cifra"><b><?= $objetivo ? ane_pod_euros($objetivo) : '—' ?></b><span>Objetivo</span></div>
    <div class="cifra"><b><?= $coste ? ane_pod_euros(max(0, $coste - $cuentas['confirmado'])) : '—' ?></b>
      <span>Falta para el coste</span></div>
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
        $enGuion = ane_pod_episodios_de_acuerdo($bd, $mid); ?>
      <div id="a<?= $mid ?>" class="tema<?= $firmado ? ' hecho' : '' ?>">
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
          <div class="barra" style="margin:0 0 10px">
            <h3 style="margin:0"><?= ane_h(($m['marca_nombre'] ?? '') ?: $m['marca']) ?>
              <?php if ($m['marca_correo'] ?? ''): ?>
                <span class="dom"><?= ane_h($m['marca_correo']) ?></span><?php endif; ?></h3>
            <div class="acciones">
              <?php if ((int) $m['importe']): ?><b><?= ane_pod_euros((int) $m['importe']) ?></b><?php endif; ?>
              <?= ane_pod_chip($m['estado'], ANE_POD_PATRO_ESTADOS) ?>
            </div>
          </div>
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
            <div class="campo" style="margin:0"><label>Tipo de cuña</label>
              <select name="tipo">
                <?php foreach (ANE_POD_MENCION_TIPOS as $k => $v): ?>
                  <option value="<?= $k ?>"<?= $m['tipo'] === $k ? ' selected' : '' ?>><?= ane_h($v) ?></option>
                <?php endforeach; ?>
              </select></div>
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
      </div>
    <?php endforeach; ?>

    <h3 id="nuevo-acuerdo">Nuevo acuerdo</h3>
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
        <div class="campo" style="margin:0"><label>Tipo de cuña</label>
          <select name="tipo">
            <?php foreach (ANE_POD_MENCION_TIPOS as $k => $v): ?>
              <option value="<?= $k ?>"><?= ane_h($v) ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>
      <div class="campo" style="margin:12px 0 10px"><label>Qué hay que decir en cada cuña</label>
        <textarea name="texto" maxlength="4000" style="min-height:60px"></textarea></div>
      <div class="campo" style="margin:0 0 10px"><label>Contrapartidas</label>
        <textarea name="contrapartidas" maxlength="2000" style="min-height:60px"
                  placeholder="Logo en el mono · dos menciones por episodio · un reel"></textarea></div>
      <button class="btn azul" type="submit">Apuntar acuerdo</button>
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

  <?php if (!(int) $pr['fijo']): ?>
  <section>
    <h2>Borrar el proyecto</h2>
    <p class="nota">Se va con sus acuerdos. Las cuñas pendientes salen de los episodios; las hechas se quedan.</p>
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
