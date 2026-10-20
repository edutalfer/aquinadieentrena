<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio · patrocinadores
   ------------------------------------------------------------
   La agenda de marcas: con quién se ha hablado, en qué punto
   está y cuál es el siguiente paso. Desde aquí se enganchan a
   proyectos y a menciones de episodios. Solo socios.

   Son datos de contacto de empresas: nada de esto se publica.
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/_comun.php';
ane_pod_solo_socio();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    ane_pod_csrf_exige();
    $accion = (string) ($_POST['accion'] ?? '');
    $fila = (int) ($_POST['fila'] ?? 0);
    $ahora = ane_ahora();
    $campos = [
        ane_recorta((string) ($_POST['contacto'] ?? ''), 120),
        ane_recorta((string) ($_POST['correo'] ?? ''), 160),
        ane_recorta((string) ($_POST['telefono'] ?? ''), 40),
        isset(ANE_POD_PATRO_ESTADOS[(string) ($_POST['estado'] ?? '')]) ? (string) $_POST['estado'] : 'contactado',
        ane_recorta((string) ($_POST['siguiente'] ?? ''), 300),
        preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['seguimiento'] ?? '')) ? (string) $_POST['seguimiento'] : null,
        ane_recorta((string) ($_POST['notas'] ?? ''), 4000),
    ];

    switch ($accion) {
        case 'crear':
            $nombre = ane_recorta((string) ($_POST['nombre'] ?? ''), 120);
            if ($nombre === '') {
                header('Location: /estudio/marcas.php?mal=nombre', true, 303);
                exit;
            }
            $bd->prepare('INSERT INTO pod_marcas (nombre, contacto, correo, telefono, estado, siguiente, seguimiento,
                            notas, creado, actualizado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
               ->execute(array_merge([$nombre], $campos, [$ahora, $ahora]));
            header('Location: /estudio/marcas.php?ok=creada', true, 303);
            exit;

        case 'guardar':
            $bd->prepare('UPDATE pod_marcas SET nombre = ?, contacto = ?, correo = ?, telefono = ?, estado = ?,
                            siguiente = ?, seguimiento = ?, notas = ?, actualizado = ? WHERE id = ?')
               ->execute(array_merge([ane_recorta((string) ($_POST['nombre'] ?? ''), 120) ?: 'Sin nombre'],
                                     $campos, [$ahora, $fila]));
            header('Location: /estudio/marcas.php?ok=guardada#m' . $fila, true, 303);
            exit;

        case 'borrar':
            $bd->prepare('DELETE FROM pod_marcas WHERE id = ?')->execute([$fila]);
            /* En proyectos y menciones queda el nombre escrito, así que no se pierde el rastro */
            $bd->prepare('UPDATE pod_proy_marcas SET marca_id = 0 WHERE marca_id = ?')->execute([$fila]);
            $bd->prepare('UPDATE pod_menciones SET marca_id = 0 WHERE marca_id = ?')->execute([$fila]);
            header('Location: /estudio/marcas.php?ok=borrada', true, 303);
            exit;
    }
    header('Location: /estudio/marcas.php', true, 303);
    exit;
}

$marcas = ane_pod_marcas($bd);

/* Dónde aparece cada marca: proyectos y episodios */
$enProyectos = [];
foreach ($bd->query('SELECT pm.marca_id, pm.marca, pm.estado, pm.importe, p.nombre AS proyecto, p.id AS proyecto_id
                     FROM pod_proy_marcas pm JOIN pod_proyectos p ON p.id = pm.proyecto_id') as $f) {
    $clave = (int) $f['marca_id'] ?: 'n:' . mb_strtolower($f['marca']);
    $enProyectos[$clave][] = $f;
}
$enEpisodios = [];
foreach ($bd->query('SELECT mc.marca_id, mc.marca, mc.hecho, e.id AS episodio_id, e.titulo, e.numero, e.fecha
                     FROM pod_menciones mc JOIN pod_episodios e ON e.id = mc.episodio_id') as $f) {
    $clave = (int) $f['marca_id'] ?: 'n:' . mb_strtolower($f['marca']);
    $enEpisodios[$clave][] = $f;
}

$cuenta = ['confirmado' => 0, 'negociando' => 0];
foreach ($marcas as $m) {
    if (in_array($m['estado'], ['confirmado', 'facturado'], true)) {
        $cuenta['confirmado']++;
    } elseif (in_array($m['estado'], ['contactado', 'negociando'], true)) {
        $cuenta['negociando']++;
    }
}
$hoy = (new DateTime('today', new DateTimeZone('Europe/Madrid')))->format('Y-m-d');

ane_pod_cabeza('Patrocinadores');
ane_pod_cabecera('marcas');
?>
<main class="caja">
  <?php ane_pod_aviso(['creada' => 'Marca añadida.', 'guardada' => 'Cambios guardados.',
                       'borrada' => 'Marca borrada.', 'nombre' => 'Falta el nombre de la marca.']); ?>

  <div class="cifras">
    <div class="cifra"><b><?= count($marcas) ?></b><span>Marcas en la agenda</span></div>
    <div class="cifra"><b><?= $cuenta['confirmado'] ?></b><span>Confirmadas</span></div>
    <div class="cifra"><b><?= $cuenta['negociando'] ?></b><span>En conversación</span></div>
  </div>

  <?php
  $tocan = array_filter($marcas, fn ($m) => $m['seguimiento'] && $m['seguimiento'] <= $hoy
                                            && !in_array($m['estado'], ['facturado', 'descartado'], true));
  if ($tocan): ?>
    <div class="aviso"><b>Seguimiento pendiente:</b>
      <?php foreach ($tocan as $m): ?>
        <a href="#m<?= (int) $m['id'] ?>"><?= ane_h($m['nombre']) ?></a>
        (<?= ane_h((string) $m['seguimiento']) ?>)<?= $m === end($tocan) ? '' : ' · ' ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$marcas): ?>
    <section><p class="vacio">La agenda está vacía.</p></section>
  <?php endif; ?>

  <?php foreach ($marcas as $m): $mid = (int) $m['id'];
      $clave = $mid;
      $suyosPr = $enProyectos[$clave] ?? ($enProyectos['n:' . mb_strtolower($m['nombre'])] ?? []);
      $suyosEp = $enEpisodios[$clave] ?? ($enEpisodios['n:' . mb_strtolower($m['nombre'])] ?? []); ?>
    <section id="m<?= $mid ?>">
      <div class="barra" style="margin-bottom:12px">
        <h2><?= ane_h($m['nombre']) ?></h2>
        <?= ane_pod_chip($m['estado'], ANE_POD_PATRO_ESTADOS) ?>
      </div>
      <div class="dos">
        <form method="post">
          <?= ane_pod_csrf_campo() ?>
          <input type="hidden" name="accion" value="guardar">
          <input type="hidden" name="fila" value="<?= $mid ?>">
          <div class="campos">
            <div class="campo" style="margin:0"><label>Marca</label>
              <input type="text" name="nombre" maxlength="120" value="<?= ane_h($m['nombre']) ?>"></div>
            <div class="campo" style="margin:0"><label>Persona de contacto</label>
              <input type="text" name="contacto" maxlength="120" value="<?= ane_h($m['contacto']) ?>"></div>
            <div class="campo" style="margin:0"><label>Correo</label>
              <input type="email" name="correo" maxlength="160" value="<?= ane_h($m['correo']) ?>"></div>
            <div class="campo" style="margin:0"><label>Teléfono</label>
              <input type="text" name="telefono" maxlength="40" value="<?= ane_h($m['telefono']) ?>"></div>
            <div class="campo" style="margin:0"><label>Estado</label>
              <select name="estado">
                <?php foreach (ANE_POD_PATRO_ESTADOS as $k => $v): ?>
                  <option value="<?= $k ?>"<?= $m['estado'] === $k ? ' selected' : '' ?>><?= ane_h($v) ?></option>
                <?php endforeach; ?>
              </select></div>
            <div class="campo" style="margin:0"><label>Fecha del siguiente paso</label>
              <input type="date" name="seguimiento" value="<?= ane_h((string) $m['seguimiento']) ?>"></div>
          </div>
          <div class="campo" style="margin:14px 0"><label>Siguiente paso</label>
            <input type="text" name="siguiente" maxlength="300" value="<?= ane_h($m['siguiente']) ?>"
                   placeholder="Mandar dossier con las cifras del Tour"></div>
          <div class="campo"><label>Notas</label>
            <textarea name="notas" maxlength="4000" style="min-height:70px"><?= ane_h($m['notas']) ?></textarea></div>
          <div class="acciones">
            <button class="btn azul" type="submit">Guardar</button>
          </div>
        </form>
        <div>
          <h3 style="margin-top:0">Dónde aparece</h3>
          <?php if (!$suyosPr && !$suyosEp): ?>
            <p class="vacio">En ningún proyecto ni episodio.</p>
          <?php endif; ?>
          <?php if ($suyosPr): ?>
            <table>
              <tr><th>Proyecto</th><th class="n">€</th></tr>
              <?php foreach ($suyosPr as $f): ?>
                <tr><td><a href="/estudio/proyecto.php?id=<?= (int) $f['proyecto_id'] ?>"><?= ane_h($f['proyecto']) ?></a><br>
                  <?= ane_pod_chip($f['estado'], ANE_POD_PATRO_ESTADOS) ?></td>
                  <td class="n"><?= (int) $f['importe'] ? ane_pod_euros((int) $f['importe']) : '—' ?></td></tr>
              <?php endforeach; ?>
            </table>
          <?php endif; ?>
          <?php if ($suyosEp): ?>
            <table style="margin-top:12px">
              <tr><th>Menciones en episodios</th><th class="n"></th></tr>
              <?php foreach ($suyosEp as $f): ?>
                <tr><td><a href="/estudio/episodio.php?id=<?= (int) $f['episodio_id'] ?>#menciones">
                  <?= ane_h(mb_strimwidth(ane_pod_etiqueta($f), 0, 40, '…')) ?></a><br>
                  <span class="dom"><?= ane_h(ane_pod_fecha_larga($f['fecha'])) ?></span></td>
                  <td class="n"><?= (int) $f['hecho'] ? '✓' : 'por hacer' ?></td></tr>
              <?php endforeach; ?>
            </table>
          <?php endif; ?>
          <form method="post" onsubmit="return confirm('¿Borrar la marca de la agenda?')" style="margin-top:14px">
            <?= ane_pod_csrf_campo() ?>
            <input type="hidden" name="accion" value="borrar">
            <input type="hidden" name="fila" value="<?= $mid ?>">
            <button class="btn peligro pequeno" type="submit">Borrar de la agenda</button>
          </form>
        </div>
      </div>
    </section>
  <?php endforeach; ?>

  <section>
    <h2>Nueva marca</h2>
    <form method="post">
      <?= ane_pod_csrf_campo() ?>
      <input type="hidden" name="accion" value="crear">
      <div class="campos">
        <div class="campo" style="margin:0"><label>Marca</label>
          <input type="text" name="nombre" maxlength="120" required></div>
        <div class="campo" style="margin:0"><label>Persona de contacto</label>
          <input type="text" name="contacto" maxlength="120"></div>
        <div class="campo" style="margin:0"><label>Correo</label>
          <input type="email" name="correo" maxlength="160"></div>
        <div class="campo" style="margin:0"><label>Teléfono</label>
          <input type="text" name="telefono" maxlength="40"></div>
        <div class="campo" style="margin:0"><label>Estado</label>
          <select name="estado">
            <?php foreach (ANE_POD_PATRO_ESTADOS as $k => $v): ?>
              <option value="<?= $k ?>"><?= ane_h($v) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="campo" style="margin:0"><label>Fecha del siguiente paso</label>
          <input type="date" name="seguimiento"></div>
      </div>
      <div class="campo" style="margin:14px 0"><label>Siguiente paso</label>
        <input type="text" name="siguiente" maxlength="300"></div>
      <button class="btn azul" type="submit">Añadir marca</button>
    </form>
  </section>
</main>
<?php
ane_pod_pie();
