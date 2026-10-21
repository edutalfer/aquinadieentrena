<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio · episodios
   ------------------------------------------------------------
   Socio:  todos los episodios y lo que falta de cada uno.
   Editor: solo los guiones que se le han abierto.
   El editor de un episodio está en episodio.php; la vista de
   guión (la que se comparte) en guion.php.
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/_comun.php';

/* ---------- Acciones ---------- */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    ane_pod_csrf_exige();
    ane_pod_solo_socio();
    if (($_POST['accion'] ?? '') === 'crear') {
        $ahora = ane_ahora();
        $numero = max(0, min(9999, (int) ($_POST['numero'] ?? 0)));
        $titulo = ane_recorta((string) ($_POST['titulo'] ?? ''), 200);
        /* El título no hace falta: con el número vale. Las dos cosas vacías, no. */
        if (!$numero && $titulo === '') {
            header('Location: /estudio/?mal=vacio', true, 303);
            exit;
        }
        $bd->prepare('INSERT INTO pod_episodios (numero, titulo, fecha, creado, actualizado) VALUES (?, ?, ?, ?, ?)')
           ->execute([$numero, $titulo, ane_local_a_utc((string) ($_POST['fecha'] ?? '')), $ahora, $ahora]);
        header('Location: /estudio/episodio.php?id=' . (int) $bd->lastInsertId() . '&ok=creado', true, 303);
        exit;
    }
    header('Location: /estudio/', true, 303);
    exit;
}

$episodios = ane_pod_mis_episodios($bd, $yo_pod);

/* Los episodios salen los domingos: uno pasa a «Pasados» el lunes siguiente.
   Los que no tienen fecha (los que crea un acuerdo) cuentan como próximos. */
$proximos = [];
$pasados = [];
foreach ($episodios as $e) {
    if (ane_pod_ya_salio($e)) {
        $pasados[] = $e;
    } else {
        $proximos[] = $e;
    }
}
/* Próximos por número, de menor a mayor: así se ve por dónde vamos. Los que no
   tienen número, detrás, por fecha. Pasados, al revés: el último arriba. */
usort($proximos, fn ($a, $b) => [(int) $a['numero'] === 0, (int) $a['numero'], (string) $a['fecha'], (int) $a['id']]
                            <=> [(int) $b['numero'] === 0, (int) $b['numero'], (string) $b['fecha'], (int) $b['id']]);
usort($pasados, fn ($a, $b) => [(int) $b['numero'], (string) $b['fecha']] <=> [(int) $a['numero'], (string) $a['fecha']]);

/* El que toca grabar: el primero de los próximos que aún no se ha grabado */
$siguiente = null;
foreach ($proximos as $e) {
    if (!ane_pod_grabado($e)) {
        $siguiente = (int) $e['id'];
        break;
    }
}

/* Cuentas rápidas y patrocinadores de cada episodio: el resumen de trabajo
   de los socios. El editor tiene aquí solo su lista de guiones; las cuñas las
   ve dentro de cada guión, en su sitio. */
$pendientes = [];
$patrocinadores = [];
$proxima = null;
if ($es_socio) {
    $pendientes = $bd->query('SELECT episodio_id, COUNT(*) AS n FROM pod_menciones WHERE hecho = 0
                              GROUP BY episodio_id')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($bd->query('SELECT episodio_id, marca FROM pod_menciones ORDER BY orden, id') as $f) {
        $ep = (int) $f['episodio_id'];
        if ($f['marca'] !== '' && !in_array($f['marca'], $patrocinadores[$ep] ?? [], true)) {
            $patrocinadores[$ep][] = (string) $f['marca'];   /* una marca con dos cuñas sale una vez */
        }
    }
    $st = $bd->prepare("SELECT * FROM pod_episodios WHERE fecha IS NOT NULL AND fecha >= datetime('now', '-6 hours')
                        ORDER BY fecha LIMIT 1");
    $st->execute();
    $proxima = $st->fetch() ?: null;
}

/* Cuántos temas tiene cada episodio (una consulta, no una por fila).
   «Bici o Cepo» no cuenta como tema: está siempre. Al editor se le cuentan
   solo los que ve: si no, le saldrían más temas que en su guión. */
$bloques = $bd->query("SELECT episodio_id, COUNT(*) AS n FROM pod_bloques WHERE tipo <> '" . ANE_POD_CIERRE . "' AND descartado = 0"
                      . ($es_socio ? '' : ' AND interno = 0') . ' GROUP BY episodio_id')->fetchAll(PDO::FETCH_KEY_PAIR);

/* Una tarjeta de episodio: la misma en «Próximos» y en «Pasados» */
function ane_pod_tarjeta(array $e): void
{
    global $es_socio, $bloques, $pendientes, $patrocinadores, $siguiente;
    $id = (int) $e['id'];
    $nb = (int) ($bloques[$id] ?? 0);
    $np = (int) ($pendientes[$id] ?? 0);
    $marcas = $patrocinadores[$id] ?? [];
    $esSiguiente = $id === $siguiente;
    $grabado = !$esSiguiente && ane_pod_grabado($e) && !ane_pod_ya_salio($e);
    $sale = ane_pod_sale($e);
    $clases = 'tarjeta' . ($e['fecha'] ? '' : ' sinfecha') . ($esSiguiente ? ' siguiente' : '') . ($grabado ? ' grabado' : '');
    ?>
      <article class="<?= $clases ?>">
        <div>
          <?php if ($esSiguiente): ?><p class="toca">Lo siguiente que se graba</p><?php endif; ?>
          <div class="meta">
            <?php /* Sin título, el número ya es el título: no se repite aquí */
              if ((int) $e['numero'] && $e['titulo'] !== ''): ?><b>Episodio <?= (int) $e['numero'] ?></b> · <?php endif; ?>
            <?php if ($sale): ?><b class="sale"><?= ane_pod_ya_salio($e) ? 'Salió el ' : 'Sale el ' ?><?= ane_pod_dia_corto($sale) ?></b> · <?php endif; ?>
            <?= $e['fecha'] ? 'Grabación: ' . ane_pod_fecha_larga($e['fecha']) . ' · ' . ane_h(ane_pod_cuando($e['fecha'])) : 'Sin fecha de grabación' ?>
            <?php if ((int) $e['duracion']): ?> · <?= (int) $e['duracion'] ?> min<?php endif; ?>
            <?php if ($e['lugar'] !== ''): ?> · <?= ane_h($e['lugar']) ?><?php endif; ?>
          </div>
          <h3><a href="<?= $es_socio ? '/estudio/episodio.php?id=' . $id : '/estudio/guion.php?id=' . $id ?>">
            <?= ane_h($e['titulo'] !== '' ? $e['titulo'] : ane_pod_etiqueta($e)) ?></a></h3>
          <div class="meta">
            <?= $nb ?> tema<?= $nb === 1 ? '' : 's' ?>
            <?php if ($grabado): ?> · <span class="chip editando">Grabado · falta que salga</span><?php endif; ?>
            <?php if ($es_socio): ?>
              · <?= $marcas ? 'Patrocina: <b>' . ane_h(implode(', ', $marcas)) . '</b>'
                            : '<span class="dom">sin patrocinador</span>' ?>
              <?php if ($np): ?> · <b><?= $np ?> cuña<?= $np === 1 ? '' : 's' ?> por hacer</b><?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="der">
          <div class="acciones">
            <a class="btn claro pequeno" href="/estudio/guion.php?id=<?= $id ?>">Guión</a>
            <?php if ($es_socio): ?>
              <a class="btn pequeno" href="/estudio/episodio.php?id=<?= $id ?>">Editar</a>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php
}

ane_pod_cabeza('Episodios');
ane_pod_cabecera('episodios');
?>
<main class="caja">
<?php ane_pod_aviso(['creado' => 'Episodio creado.', 'borrado' => 'Episodio borrado.',
                     'guardado' => 'Cambios guardados.',
                     'vacio' => 'Pon al menos el número o el título.']); ?>

<?php if ($es_socio): ?>
  <div class="cifras">
    <div class="cifra">
      <b><?= count($proximos) ?></b><span>Episodios por delante</span>
    </div>
    <div class="cifra">
      <b><?= $proxima ? ane_pod_cuando($proxima['fecha']) : '—' ?></b>
      <span><?= $proxima ? 'Próxima grabación' : 'Sin grabación prevista' ?></span>
    </div>
    <div class="cifra">
      <b><?= array_sum(array_intersect_key($pendientes, array_flip(array_map(fn ($e) => (int) $e['id'], $proximos)))) ?></b>
      <span>Cuñas por hacer</span>
    </div>
  </div>

  <?php if ($proxima): ?>
    <div class="aviso">
      <b><?= ane_pod_cuando($proxima['fecha']) ?>:</b>
      <a href="/estudio/episodio.php?id=<?= (int) $proxima['id'] ?>"><?= ane_h(ane_pod_etiqueta($proxima)) ?></a>
      · <?= ane_pod_fecha_larga($proxima['fecha']) ?><?= $proxima['lugar'] !== '' ? ' · ' . ane_h($proxima['lugar']) : '' ?>
    </div>
  <?php endif; ?>

  <section>
    <h2>Nuevo episodio</h2>
    <p class="nota">Con el número o el título basta para empezar; el resto se rellena dentro.</p>
    <form method="post" class="campos" style="align-items:end">
      <?= ane_pod_csrf_campo() ?>
      <input type="hidden" name="accion" value="crear">
      <div class="campo" style="margin:0">
        <label for="numero">Nº</label>
        <input type="number" id="numero" name="numero" min="0" max="9999" placeholder="48">
      </div>
      <div class="campo" style="grid-column:span 2;margin:0">
        <label for="titulo">Título o tema</label>
        <input type="text" id="titulo" name="titulo" maxlength="200" placeholder="Pidcock, ruedas del mundial y Decathlon">
      </div>
      <div class="campo" style="margin:0">
        <label for="fecha">Grabación</label>
        <input type="datetime-local" id="fecha" name="fecha">
      </div>
      <div class="campo" style="margin:0"><button class="btn azul" type="submit">Crear</button></div>
    </form>
  </section>
<?php else: ?>
  <section>
    <h2>Tus guiones</h2>
    <p class="nota">Aquí están los episodios cuyo guión se ha compartido contigo. Solo lectura.</p>
  </section>
<?php endif; ?>

<?php if (!$episodios): ?>
  <h3 style="margin-top:26px"><?= $es_socio ? 'Episodios' : 'Compartidos contigo' ?></h3>
  <section><p class="vacio"><?= $es_socio ? 'Todavía no hay episodios. Crea el primero arriba, o firma un acuerdo con episodios.'
                                          : 'Aún no te han compartido ningún guión.' ?></p></section>
<?php else: ?>

  <h3 style="margin-top:26px">Próximos <span class="dom">· <?= count($proximos) ?></span></h3>
  <?php if (!$proximos): ?>
    <section><p class="vacio">Nada por delante. Crea el siguiente arriba.</p></section>
  <?php else: ?>
    <div class="tarjetas"><?php foreach ($proximos as $e) { ane_pod_tarjeta($e); } ?></div>
  <?php endif; ?>

  <?php if ($pasados): ?>
    <details class="pasados"<?= !$proximos ? ' open' : '' ?>>
      <summary><h3>Pasados <span class="dom">· <?= count($pasados) ?> · ya han salido</span></h3></summary>
      <div class="tarjetas"><?php foreach ($pasados as $e) { ane_pod_tarjeta($e); } ?></div>
    </details>
  <?php endif; ?>
<?php endif; ?>
</main>
<?php
ane_pod_pie();
