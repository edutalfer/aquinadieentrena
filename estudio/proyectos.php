<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio · proyectos
   ------------------------------------------------------------
   Cape Epic, ANE House, Mediterranean Epic… cada uno con su
   presupuesto y los patrocinadores que se van confirmando.
   El detalle de cada uno, en proyecto.php. Solo socios.
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/_comun.php';
ane_pod_solo_socio();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    ane_pod_csrf_exige();
    if (($_POST['accion'] ?? '') === 'crear') {
        $ahora = ane_ahora();
        $orden = (int) $bd->query('SELECT COALESCE(MAX(orden), 0) + 10 FROM pod_proyectos')->fetchColumn();
        $bd->prepare('INSERT INTO pod_proyectos (nombre, detalle, estado, objetivo, orden, creado, actualizado)
                      VALUES (?, ?, ?, ?, ?, ?, ?)')
           ->execute([ane_recorta((string) ($_POST['nombre'] ?? ''), 160) ?: 'Proyecto sin nombre',
                      ane_recorta((string) ($_POST['detalle'] ?? ''), 160), 'abierto',
                      max(0, (int) ($_POST['objetivo'] ?? 0)),
                      $orden, $ahora, $ahora]);
        header('Location: /estudio/proyecto.php?id=' . (int) $bd->lastInsertId() . '&ok=creado#gastos', true, 303);
        exit;
    }
    header('Location: /estudio/proyectos.php', true, 303);
    exit;
}

$proyectos = ane_pod_proyectos($bd);

/* Las marcas de todos los proyectos de una vez */
$porProyecto = [];
foreach ($bd->query('SELECT proyecto_id, estado, importe FROM pod_proy_marcas') as $f) {
    $porProyecto[(int) $f['proyecto_id']][] = $f;
}

$totales = ['objetivo' => 0, 'gastos' => 0, 'confirmado' => 0, 'negociando' => 0];
foreach ($proyectos as $p) {
    if ($p['estado'] === 'cerrado') {
        continue;
    }
    $c = ane_pod_cuentas($porProyecto[(int) $p['id']] ?? []);
    $totales['objetivo'] += (int) $p['objetivo'];
    $totales['gastos'] += (int) $p['gastos_cent'];   /* céntimos */
    $totales['confirmado'] += $c['confirmado'];
    $totales['negociando'] += $c['negociando'];
}

ane_pod_cabeza('Proyectos');
ane_pod_cabecera('proyectos');
?>
<main class="caja">
  <?php ane_pod_aviso(['creado' => 'Proyecto creado.', 'borrado' => 'Proyecto borrado.']); ?>

  <div class="cifras">
    <div class="cifra"><b><?= ane_pod_euros($totales['confirmado']) ?></b><span>Confirmado (proyectos vivos)</span></div>
    <div class="cifra"><b><?= ane_pod_euros($totales['negociando']) ?></b><span>En conversaciones</span></div>
    <div class="cifra"><b><?= ane_pod_euros($totales['objetivo']) ?></b><span>Objetivo</span></div>
    <div class="cifra"><b><?= ane_pod_euros_c($totales['gastos']) ?></b><span>Gastos</span></div>
  </div>

  <?php if (!$proyectos): ?>
    <section><p class="vacio">Ningún proyecto todavía.</p></section>
  <?php else: ?>
    <?php foreach ($proyectos as $p):
        $c = ane_pod_cuentas($porProyecto[(int) $p['id']] ?? []);
        $objetivo = (int) $p['objetivo'];
        $pct = $objetivo > 0 ? min(100, (int) round($c['confirmado'] * 100 / $objetivo)) : 0; ?>
      <section>
        <div class="barra" style="margin-bottom:10px">
          <div>
            <h2><a href="/estudio/proyecto.php?id=<?= (int) $p['id'] ?>" style="text-decoration:none;color:inherit">
              <?= ane_h($p['nombre']) ?></a></h2>
            <p class="nota" style="margin:0"><?= ane_h($p['detalle']) ?></p>
          </div>
          <div class="acciones">
            <?php if ((int) $p['fijo']): ?><span class="chip">fijo · episodios sueltos</span><?php endif; ?>
            <?php /* Un proyecto creado está en marcha: solo se avisa si ya se cerró */
              if ($p['estado'] === 'cerrado'): ?><?= ane_pod_chip('cerrado', ANE_POD_PROY_ESTADOS) ?><?php endif; ?>
            <a class="btn pequeno" href="/estudio/proyecto.php?id=<?= (int) $p['id'] ?>">Abrir</a>
          </div>
        </div>
        <div class="tabla-ancha">
        <table>
          <tr>
            <th class="n">Confirmado</th><th class="n">Negociando</th><th class="n">Objetivo</th>
            <th class="n">Gastos</th><th class="n">Marcas</th>
          </tr>
          <tr>
            <td class="n"><b><?= ane_pod_euros($c['confirmado']) ?></b></td>
            <td class="n"><?= ane_pod_euros($c['negociando']) ?></td>
            <td class="n"><?= $objetivo ? ane_pod_euros($objetivo) : '—' ?></td>
            <td class="n"><?= (int) $p['gastos_cent'] ? ane_pod_euros_c((int) $p['gastos_cent']) : '—' ?></td>
            <td class="n"><?= count($porProyecto[(int) $p['id']] ?? []) ?></td>
          </tr>
        </table>
        </div>
        <?php if ($objetivo > 0): ?>
          <div style="margin-top:10px;background:var(--humo);border:1px solid var(--linea);height:14px">
            <div style="width:<?= $pct ?>%;height:100%;background:var(--azul)"></div>
          </div>
          <p class="nota" style="margin:6px 0 0"><?= $pct ?> % del objetivo cubierto.
            <?php if ($c['confirmado'] * 100 < (int) $p['gastos_cent']): ?>
              Faltan <?= ane_pod_euros_c((int) $p['gastos_cent'] - $c['confirmado'] * 100) ?> para cubrir los gastos.
            <?php endif; ?></p>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>

  <section>
    <h2>Nuevo proyecto</h2>
    <form method="post">
      <?= ane_pod_csrf_campo() ?>
      <input type="hidden" name="accion" value="crear">
      <div class="campos">
        <div class="campo" style="margin:0"><label>Nombre</label>
          <input type="text" name="nombre" maxlength="160" placeholder="Cape Epic 2027" required></div>
        <div class="campo" style="margin:0"><label>Detalle</label>
          <input type="text" name="detalle" maxlength="160" placeholder="Marzo 2027 · Sudáfrica"></div>
        <div class="campo" style="margin:0"><label>Objetivo (€)</label>
          <input type="number" name="objetivo" min="0" step="100" value="0"></div>
      </div>
      <p class="nota" style="margin:12px 0 0">Los gastos (casa, vuelos…) se apuntan dentro del proyecto: su suma es el coste.</p>
      <p><button class="btn azul" type="submit">Crear proyecto</button></p>
    </form>
  </section>
</main>
<?php
ane_pod_pie();
