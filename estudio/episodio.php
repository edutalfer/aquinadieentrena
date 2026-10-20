<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio · editor de un episodio
   ------------------------------------------------------------
   Todo lo del episodio en una pantalla: datos de grabación,
   guión por temas (cada uno con su enlace y sus imágenes),
   «Bici o Cepo» de cierre, menciones de patrocinio y a quién se
   le abre el guión.

   Solo socios. Lo que ve el editor es guion.php, y de ahí se
   caen los temas internos, las menciones y las notas.
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/_comun.php';
ane_pod_solo_socio();

$id = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));
$ep = ane_pod_episodio($bd, $id);
if (!$ep) {
    header('Location: /estudio/', true, 303);
    exit;
}

function ane_pod_vuelve(int $id, string $query = ''): void
{
    header('Location: /estudio/episodio.php?id=' . $id . ($query !== '' ? '&' . $query : ''), true, 303);
    exit;
}

/* ---------- Acciones ---------- */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    ane_pod_csrf_exige();
    $accion = (string) ($_POST['accion'] ?? '');
    $fila = (int) ($_POST['fila'] ?? 0);
    $ahora = ane_ahora();

    switch ($accion) {
        case 'datos':
            $bd->prepare('UPDATE pod_episodios SET numero = ?, titulo = ?, fecha = ?, duracion = ?, lugar = ?,
                            youtube_id = ?, notas = ?, actualizado = ? WHERE id = ?')
               ->execute([
                   max(0, min(9999, (int) ($_POST['numero'] ?? 0))),
                   ane_recorta((string) ($_POST['titulo'] ?? ''), 200),
                   ane_local_a_utc((string) ($_POST['fecha'] ?? '')),
                   max(0, min(1000, (int) ($_POST['duracion'] ?? 0))),
                   ane_recorta((string) ($_POST['lugar'] ?? ''), 120),
                   ane_recorta((string) ($_POST['youtube_id'] ?? ''), 20),
                   ane_recorta((string) ($_POST['notas'] ?? ''), 6000),
                   $ahora, $id,
               ]);
            ane_pod_vuelve($id, 'ok=guardado');

        case 'borrar_episodio':
            ane_pod_borra_ficheros($bd, 'episodio_id', $id);
            foreach (['pod_bloques', 'pod_enlaces', 'pod_menciones'] as $t) {
                $bd->prepare("DELETE FROM $t WHERE episodio_id = ?")->execute([$id]);
            }
            $bd->prepare('DELETE FROM pod_accesos WHERE episodio_id = ?')->execute([$id]);
            $bd->prepare('DELETE FROM pod_episodios WHERE id = ?')->execute([$id]);
            header('Location: /estudio/?ok=borrado', true, 303);
            exit;

        /* ---- Guión ---- */
        case 'bloque_nuevo':
            $bd->prepare('INSERT INTO pod_bloques (episodio_id, orden, tipo, titulo, texto, interno)
                          VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([$id, ane_pod_siguiente_orden($bd, 'pod_bloques', $id), 'tema',
                          ane_recorta((string) ($_POST['titulo'] ?? ''), 200),
                          ane_recorta((string) ($_POST['texto'] ?? ''), 20000),
                          empty($_POST['interno']) ? 0 : 1]);
            $nuevo = (int) $bd->lastInsertId();
            /* El primer enlace, si lo escribió al crear el tema; los demás, dentro */
            $url = ane_pod_url((string) ($_POST['enlace'] ?? ''));
            if ($url !== '') {
                $bd->prepare('INSERT INTO pod_enlaces (episodio_id, bloque_id, titulo, url, orden) VALUES (?, ?, ?, ?, 10)')
                   ->execute([$id, $nuevo, ane_recorta((string) ($_POST['enlace_titulo'] ?? ''), 200), $url]);
            }
            ane_pod_vuelve($id, 'ok=tema#t' . $nuevo);

        case 'bloque_guardar':
            $st = $bd->prepare('SELECT tipo FROM pod_bloques WHERE id = ? AND episodio_id = ?');
            $st->execute([$fila, $id]);
            $tipo = (string) $st->fetchColumn();
            if ($tipo === ANE_POD_CIERRE) {
                /* La sección de cierre no cambia de nombre ni se esconde */
                $bd->prepare('UPDATE pod_bloques SET texto = ? WHERE id = ? AND episodio_id = ?')
                   ->execute([ane_recorta((string) ($_POST['texto'] ?? ''), 20000), $fila, $id]);
            } else {
                $bd->prepare('UPDATE pod_bloques SET titulo = ?, texto = ?, interno = ?
                              WHERE id = ? AND episodio_id = ?')
                   ->execute([ane_recorta((string) ($_POST['titulo'] ?? ''), 200),
                              ane_recorta((string) ($_POST['texto'] ?? ''), 20000),
                              empty($_POST['interno']) ? 0 : 1, $fila, $id]);
            }
            ane_pod_vuelve($id, 'ok=guardado#t' . $fila);

        case 'bloque_mover':
            ane_pod_mueve($bd, 'pod_bloques', $fila, $id, (string) ($_POST['hacia'] ?? ''));
            ane_pod_vuelve($id, 'guion=1#t' . $fila);

        case 'bloque_borrar':
            $st = $bd->prepare('SELECT tipo FROM pod_bloques WHERE id = ? AND episodio_id = ?');
            $st->execute([$fila, $id]);
            if ((string) $st->fetchColumn() === 'tema') {
                ane_pod_borra_ficheros($bd, 'bloque_id', $fila);
                $bd->prepare('DELETE FROM pod_enlaces WHERE bloque_id = ? AND episodio_id = ?')->execute([$fila, $id]);
                $bd->prepare('DELETE FROM pod_bloques WHERE id = ? AND episodio_id = ?')->execute([$fila, $id]);
            }
            ane_pod_vuelve($id, 'ok=borrado#guion');

        /* ---- Enlaces del tema ---- */
        case 'enlace_nuevo':
            $url = ane_pod_url((string) ($_POST['url'] ?? ''));
            $st = $bd->prepare('SELECT id FROM pod_bloques WHERE id = ? AND episodio_id = ?');
            $st->execute([$fila, $id]);
            if (!$st->fetchColumn()) {
                ane_pod_vuelve($id);
            }
            if ($url === '') {
                ane_pod_vuelve($id, 'mal=url#t' . $fila);
            }
            $st = $bd->prepare('SELECT COALESCE(MAX(orden), 0) + 10 FROM pod_enlaces WHERE bloque_id = ?');
            $st->execute([$fila]);
            $bd->prepare('INSERT INTO pod_enlaces (episodio_id, bloque_id, titulo, url, orden) VALUES (?, ?, ?, ?, ?)')
               ->execute([$id, $fila, ane_recorta((string) ($_POST['titulo'] ?? ''), 200), $url,
                          (int) $st->fetchColumn()]);
            ane_pod_vuelve($id, 'ok=enlace#t' . $fila);

        case 'enlace_guardar':
            $en = ane_pod_enlace($bd, $fila);
            if (!$en || (int) $en['episodio_id'] !== $id) {
                ane_pod_vuelve($id);
            }
            $url = ane_pod_url((string) ($_POST['url'] ?? ''));
            if ($url === '') {
                ane_pod_vuelve($id, 'mal=url#t' . (int) $en['bloque_id']);
            }
            $bd->prepare('UPDATE pod_enlaces SET titulo = ?, url = ? WHERE id = ?')
               ->execute([ane_recorta((string) ($_POST['titulo'] ?? ''), 200), $url, $fila]);
            ane_pod_vuelve($id, 'ok=guardado#t' . (int) $en['bloque_id']);

        case 'enlace_borrar':
            $en = ane_pod_enlace($bd, $fila);
            if ($en && (int) $en['episodio_id'] === $id) {
                $bd->prepare('DELETE FROM pod_enlaces WHERE id = ?')->execute([$fila]);
                ane_pod_vuelve($id, 'ok=borrado#t' . (int) $en['bloque_id']);
            }
            ane_pod_vuelve($id);

        /* ---- Imágenes y archivos ---- */
        case 'fichero_nuevo':
            $st = $bd->prepare('SELECT id FROM pod_bloques WHERE id = ? AND episodio_id = ?');
            $st->execute([$fila, $id]);
            if (!$st->fetchColumn()) {
                ane_pod_vuelve($id);
            }
            $fallo = '';
            $subidas = 0;
            foreach (ane_pod_subidas('fichero') as $f) {
                $r = ane_pod_guarda_fichero($bd, $id, $fila, $f, (string) ($_POST['pie'] ?? ''));
                if ($r === '') {
                    $subidas++;
                } elseif ($r !== 'vacio') {
                    $fallo = $r;
                }
            }
            if ($subidas === 0 && $fallo === '') {
                $fallo = 'vacio';
            }
            ane_pod_vuelve($id, ($fallo !== '' ? 'mal=' . $fallo : 'ok=subido') . '#t' . $fila);

        case 'fichero_pie':
            $f = ane_pod_fichero($bd, $fila);
            if ($f && (int) $f['episodio_id'] === $id) {
                $bd->prepare('UPDATE pod_ficheros SET pie = ? WHERE id = ?')
                   ->execute([ane_recorta((string) ($_POST['pie'] ?? ''), 2000), $fila]);
                ane_pod_vuelve($id, 'ok=guardado#t' . (int) $f['bloque_id']);
            }
            ane_pod_vuelve($id);

        case 'fichero_borrar':
            $f = ane_pod_fichero($bd, $fila);
            if ($f && (int) $f['episodio_id'] === $id) {
                $bloque = (int) $f['bloque_id'];
                ane_pod_borra_fichero($bd, $f);
                ane_pod_vuelve($id, 'ok=borrado#t' . $bloque);
            }
            ane_pod_vuelve($id);

        /* ---- Menciones de patrocinio ---- */
        case 'mencion_nueva':
            $marcaId = (int) ($_POST['marca_id'] ?? 0);
            $marca = $marcaId ? (string) (ane_pod_marca($bd, $marcaId)['nombre'] ?? '')
                              : ane_recorta((string) ($_POST['marca'] ?? ''), 120);
            if ($marca === '') {
                ane_pod_vuelve($id, 'mal=marca#menciones');
            }
            $bd->prepare('INSERT INTO pod_menciones (episodio_id, marca_id, marca, tipo, momento, texto, orden)
                          VALUES (?, ?, ?, ?, ?, ?, ?)')
               ->execute([$id, $marcaId, $marca,
                          isset(ANE_POD_MENCION_TIPOS[(string) ($_POST['tipo'] ?? '')]) ? (string) $_POST['tipo'] : 'mencion',
                          ane_recorta((string) ($_POST['momento'] ?? ''), 120),
                          ane_recorta((string) ($_POST['texto'] ?? ''), 4000),
                          ane_pod_siguiente_orden($bd, 'pod_menciones', $id)]);
            ane_pod_vuelve($id, 'ok=mencion#menciones');

        case 'mencion_guardar':
            $bd->prepare('UPDATE pod_menciones SET tipo = ?, momento = ?, texto = ?, hecho = ?
                          WHERE id = ? AND episodio_id = ?')
               ->execute([isset(ANE_POD_MENCION_TIPOS[(string) ($_POST['tipo'] ?? '')]) ? (string) $_POST['tipo'] : 'mencion',
                          ane_recorta((string) ($_POST['momento'] ?? ''), 120),
                          ane_recorta((string) ($_POST['texto'] ?? ''), 4000),
                          empty($_POST['hecho']) ? 0 : 1, $fila, $id]);
            ane_pod_vuelve($id, 'ok=guardado#menciones');

        case 'mencion_mover':
            ane_pod_mueve($bd, 'pod_menciones', $fila, $id, (string) ($_POST['hacia'] ?? ''));
            ane_pod_vuelve($id, 'menciones=1#menciones');

        case 'mencion_borrar':
            $bd->prepare('DELETE FROM pod_menciones WHERE id = ? AND episodio_id = ?')->execute([$fila, $id]);
            ane_pod_vuelve($id, 'ok=borrado#menciones');

        /* ---- Quién ve el guión ---- */
        case 'accesos':
            $bd->prepare('DELETE FROM pod_accesos WHERE episodio_id = ?')->execute([$id]);
            $st = $bd->prepare('INSERT INTO pod_accesos (usuario_id, episodio_id, creado) VALUES (?, ?, ?)');
            foreach ((array) ($_POST['editor'] ?? []) as $uid) {
                $u = ane_pod_por_id($bd, (int) $uid);
                if ($u && $u['rol'] === 'editor') {
                    $st->execute([(int) $u['id'], $id, $ahora]);
                }
            }
            ane_pod_vuelve($id, 'ok=accesos#accesos');
    }
    ane_pod_vuelve($id);
}

/* ---------- Datos ---------- */

ane_pod_asegura_cierre($bd, $id);
$bloques = ane_pod_bloques($bd, $id);
$enlaces = ane_pod_enlaces($bd, $id);
$ficheros = ane_pod_ficheros_de($bd, $id);
$menciones = ane_pod_menciones_socio($bd, $id);
$marcas = ane_pod_marcas($bd);
$editores = $bd->query("SELECT * FROM pod_usuarios WHERE rol = 'editor' ORDER BY nombre, usuario")->fetchAll();
$st = $bd->prepare('SELECT usuario_id FROM pod_accesos WHERE episodio_id = ?');
$st->execute([$id]);
$conAcceso = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

$temas = array_values(array_filter($bloques, fn ($b) => $b['tipo'] !== ANE_POD_CIERRE));

ane_pod_cabeza('Editar episodio');
ane_pod_cabecera('episodios');
?>
<main class="caja">
  <p class="nota"><a href="/estudio/">← Todos los episodios</a></p>

  <?php ane_pod_aviso(array_merge([
      'guardado' => 'Cambios guardados.', 'tema' => 'Tema añadido.', 'imagen' => 'Imagen subida.',
      'mencion' => 'Mención añadida.', 'borrado' => 'Borrado.', 'accesos' => 'Accesos actualizados.',
      'marca' => 'Falta la marca.', 'creado' => 'Episodio creado. Ahora los datos.',
      'enlace' => 'Enlace añadido.', 'url' => 'Ese enlace no parece una dirección válida.',
      'subido' => 'Subido.',
  ], array_map('ane_pod_motivo_fichero', ['grande' => 'grande', 'formato' => 'formato',
      'subida' => 'subida', 'disco' => 'disco', 'vacio' => 'vacio']))); ?>

  <div class="barra">
    <h2 class="display" style="font-size:30px"><?= ane_h(ane_pod_etiqueta($ep)) ?></h2>
    <div class="acciones">
      <a class="btn claro pequeno" href="/estudio/guion.php?id=<?= $id ?>">Ver el guión</a>
    </div>
  </div>

  <!-- ---------------- Datos ---------------- -->
  <section id="datos">
    <h2>Grabación</h2>
    <p class="nota">Fecha y hora en hora de Madrid. Las notas no salen del guión compartido.</p>
    <form method="post">
      <?= ane_pod_csrf_campo() ?>
      <input type="hidden" name="accion" value="datos">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="campos" style="grid-template-columns:110px 1fr">
        <div class="campo" style="margin:0"><label for="numero">Nº de episodio</label>
          <input type="number" id="numero" name="numero" min="0" max="9999" value="<?= (int) $ep['numero'] ?>"></div>
        <div class="campo" style="margin:0"><label for="titulo">Título o tema (opcional)</label>
          <input type="text" id="titulo" name="titulo" maxlength="200" value="<?= ane_h($ep['titulo']) ?>"></div>
      </div>
      <div class="campos" style="margin-top:14px">
        <div class="campo" style="margin:0"><label for="fecha">Fecha y hora</label>
          <input type="datetime-local" id="fecha" name="fecha" value="<?= ane_h(ane_utc_a_local($ep['fecha'])) ?>"></div>
        <div class="campo" style="margin:0"><label for="duracion">Duración prevista (min)</label>
          <input type="number" id="duracion" name="duracion" min="0" max="1000" value="<?= (int) $ep['duracion'] ?>"></div>
        <div class="campo" style="margin:0"><label for="lugar">Dónde</label>
          <input type="text" id="lugar" name="lugar" maxlength="120" placeholder="Estudio · Zoom"
                 value="<?= ane_h($ep['lugar']) ?>"></div>
        <div class="campo" style="margin:0"><label for="youtube_id">ID de YouTube (si ya está publicado)</label>
          <input type="text" id="youtube_id" name="youtube_id" maxlength="20" value="<?= ane_h($ep['youtube_id']) ?>"></div>
      </div>
      <div class="campo" style="margin:14px 0 10px"><label for="notas">Notas internas (nunca se comparten)</label>
        <textarea id="notas" name="notas" maxlength="6000" style="min-height:70px"><?= ane_h($ep['notas']) ?></textarea></div>
      <button class="btn azul" type="submit">Guardar</button>
    </form>
  </section>

  <!-- ---------------- Guión ---------------- -->
  <section id="guion">
    <h2>Guión</h2>
    <p class="nota">Temas en orden, cada uno con su enlace y sus imágenes. Un tema <b>interno</b> queda fuera
      de lo que ve el editor. En el texto valen <code>**negrita**</code>, <code>_cursiva_</code> y líneas
      que empiecen por <code>- </code>.</p>

    <?php if (!$temas): ?>
      <p class="vacio">Sin temas todavía.</p>
    <?php endif; ?>

    <?php foreach ($bloques as $b):
        $bid = (int) $b['id'];
        $esCierre = $b['tipo'] === ANE_POD_CIERRE;
        $i = $esCierre ? null : array_search($bid, array_column($temas, 'id'));
        $suyos = $enlaces[$bid] ?? [];
        $suyas = ane_pod_solo($ficheros[$bid] ?? [], 'imagen');
        $suyosArch = ane_pod_solo($ficheros[$bid] ?? [], 'archivo'); ?>
      <div id="t<?= $bid ?>" class="tema<?= $esCierre ? ' cierre' : ((int) $b['interno'] ? ' interno' : '') ?>">

        <?php if ($esCierre): ?>
          <div class="barra" style="margin:0 0 12px">
            <h3 style="margin:0"><?= ane_h(ANE_POD_CIERRE_TITULO) ?>
              <span class="chip">sección fija · siempre la última</span></h3>
          </div>
          <p class="nota" style="margin:-6px 0 12px">Cada foto con su descripción al lado. Tantas como quieras.</p>
        <?php else: ?>
          <?php foreach (['arriba', 'abajo'] as $hacia): ?>
            <form method="post" id="mv<?= $hacia ?><?= $bid ?>" class="escondida">
              <?= ane_pod_csrf_campo() ?>
              <input type="hidden" name="accion" value="bloque_mover">
              <input type="hidden" name="id" value="<?= $id ?>">
              <input type="hidden" name="fila" value="<?= $bid ?>">
              <input type="hidden" name="hacia" value="<?= $hacia ?>">
            </form>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php /* ---- Lo subido: en «Bici o Cepo» va arriba; en un tema, debajo del texto ---- */
        ob_start(); ?>
          <?php foreach ($suyas as $img): $iid = (int) $img['id']; ?>
            <?php /* Cada foto, con su descripción al lado: van juntas */ ?>
            <div class="pareja">
              <figure>
                <img src="/estudio/fichero.php?f=<?= $iid ?>" alt="<?= ane_h(mb_strimwidth($img['pie'], 0, 80, '…')) ?>" loading="lazy">
                <figcaption class="acciones">
                  <a class="btn claro pequeno" href="/estudio/fichero.php?f=<?= $iid ?>&amp;d=1">Descargar</a>
                  <form method="post" onsubmit="return confirm('¿Borrar la foto y su descripción?')">
                    <?= ane_pod_csrf_campo() ?>
                    <input type="hidden" name="accion" value="fichero_borrar">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="fila" value="<?= $iid ?>">
                    <button class="btn peligro pequeno" type="submit">Borrar</button>
                  </form>
                </figcaption>
              </figure>
              <form method="post">
                <?= ane_pod_csrf_campo() ?>
                <input type="hidden" name="accion" value="fichero_pie">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="fila" value="<?= $iid ?>">
                <div class="campo" style="margin:0 0 8px"><label>Descripción de esta foto</label>
                  <textarea name="pie" maxlength="2000"
                            placeholder="<?= $esCierre ? 'De quién es la bici, qué miramos, el veredicto…' : 'Qué se ve y para qué la usamos' ?>"><?= ane_h($img['pie']) ?></textarea></div>
                <button class="btn pequeno" type="submit">Guardar descripción</button>
              </form>
            </div>
          <?php endforeach; ?>

          <?php if ($suyosArch): ?>
            <ul class="archivos">
              <?php foreach ($suyosArch as $ar): $aid = (int) $ar['id']; ?>
                <li>
                  <?php /* Borrar vive en su propia forma, escondida: así el botón puede ir
                            en la misma fila que Guardar sin anidar formas. */ ?>
                  <form method="post" id="arb<?= $aid ?>" class="escondida"
                        onsubmit="return confirm('¿Borrar el archivo?')">
                    <?= ane_pod_csrf_campo() ?>
                    <input type="hidden" name="accion" value="fichero_borrar">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="fila" value="<?= $aid ?>">
                  </form>
                  <a href="/estudio/fichero.php?f=<?= $aid ?>&amp;d=1"><?= ane_h($ar['nombre']) ?></a>
                  <span class="dom"><?= ane_h(ane_pod_tamano((int) $ar['tamano'])) ?></span>
                  <form method="post" class="acciones">
                    <?= ane_pod_csrf_campo() ?>
                    <input type="hidden" name="accion" value="fichero_pie">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="fila" value="<?= $aid ?>">
                    <input type="text" name="pie" maxlength="2000" value="<?= ane_h($ar['pie']) ?>" placeholder="Para qué es (opcional)">
                    <button class="btn claro pequeno" type="submit">Guardar</button>
                    <button class="btn peligro pequeno" type="submit" form="arb<?= $aid ?>">✕</button>
                  </form>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <form method="post" enctype="multipart/form-data" class="subir">
            <?= ane_pod_csrf_campo() ?>
            <input type="hidden" name="accion" value="fichero_nuevo">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="fila" value="<?= $bid ?>">
            <label class="suelto">Imágenes y archivos<?= $suyas || $suyosArch ? ' (' . (count($suyas) + count($suyosArch)) . ')' : '' ?>
              · puedes elegir varios a la vez</label>
            <input type="file" name="fichero[]" multiple required>
            <input type="text" name="pie" maxlength="2000" placeholder="Descripción (opcional; se puede escribir después)">
            <button class="btn claro pequeno" type="submit">Subir</button>
          </form>
        <?php $subidos = ob_get_clean(); ?>

        <?php if ($esCierre) { echo $subidos; } ?>

        <?php if (!$esCierre): ?>
        <form method="post">
          <?= ane_pod_csrf_campo() ?>
          <input type="hidden" name="accion" value="bloque_guardar">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="fila" value="<?= $bid ?>">
          <div class="campos" style="grid-template-columns:1fr 170px">
            <div class="campo" style="margin:0"><label>Tema</label>
              <input type="text" name="titulo" maxlength="200" value="<?= ane_h($b['titulo']) ?>"
                     placeholder="Las ruedas del mundial"></div>
            <div class="campo" style="margin:0"><label>Orden</label>
              <div class="acciones">
                <button class="btn claro pequeno" type="submit" form="mvarriba<?= $bid ?>"
                        <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
                <button class="btn claro pequeno" type="submit" form="mvabajo<?= $bid ?>"
                        <?= $i === count($temas) - 1 ? 'disabled' : '' ?>>↓</button>
                <span class="chip"><?= (int) $i + 1 ?> de <?= count($temas) ?></span>
              </div>
            </div>
          </div>
          <div class="campo" style="margin:12px 0 10px"><label>Qué se cuenta</label>
            <textarea name="texto" maxlength="20000"><?= ane_h($b['texto']) ?></textarea></div>

          <div class="barra" style="margin:0">
            <div class="cas"><input type="checkbox" id="int<?= $bid ?>" name="interno" value="1"
                 <?= (int) $b['interno'] ? 'checked' : '' ?>>
              <label for="int<?= $bid ?>">Interno: fuera del guión compartido</label></div>
            <button class="btn pequeno" type="submit">Guardar</button>
          </div>
        </form>
        <?php endif; ?>

        <?php if (!$esCierre): ?>
          <!-- Enlaces del tema: tantos como haga falta -->
          <div class="lista-enlaces">
            <label>Enlaces<?= $suyos ? ' (' . count($suyos) . ')' : '' ?></label>
            <?php foreach ($suyos as $en): $enid = (int) $en['id']; ?>
              <?php /* El botón de borrar vive en esta forma y se dispara desde la fila:
                        dos formas no pueden anidarse, pero un botón sí puede apuntar a otra. */ ?>
              <form method="post" id="elb<?= $enid ?>" class="escondida"
                    onsubmit="return confirm('¿Borrar el enlace?')">
                <?= ane_pod_csrf_campo() ?>
                <input type="hidden" name="accion" value="enlace_borrar">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="fila" value="<?= $enid ?>">
              </form>
              <form method="post" class="fila-enlace">
                <?= ane_pod_csrf_campo() ?>
                <input type="hidden" name="accion" value="enlace_guardar">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="fila" value="<?= $enid ?>">
                <input type="text" name="titulo" maxlength="200" value="<?= ane_h($en['titulo']) ?>"
                       placeholder="Cómo se llama (opcional)">
                <input type="url" name="url" maxlength="800" value="<?= ane_h($en['url']) ?>" required>
                <button class="btn claro pequeno" type="submit">Guardar</button>
                <button class="btn peligro pequeno" type="submit" form="elb<?= $enid ?>">✕</button>
              </form>
            <?php endforeach; ?>
            <form method="post" class="fila-enlace">
              <?= ane_pod_csrf_campo() ?>
              <input type="hidden" name="accion" value="enlace_nuevo">
              <input type="hidden" name="id" value="<?= $id ?>">
              <input type="hidden" name="fila" value="<?= $bid ?>">
              <input type="text" name="titulo" maxlength="200" placeholder="Cómo se llama (opcional)">
              <input type="url" name="url" maxlength="800" placeholder="https://…" required>
              <button class="btn claro pequeno" type="submit">Añadir enlace</button>
            </form>
          </div>

          <?php echo $subidos; ?>

          <form method="post" onsubmit="return confirm('¿Borrar este tema, sus enlaces y sus ficheros?')" style="margin-top:10px">
            <?= ane_pod_csrf_campo() ?>
            <input type="hidden" name="accion" value="bloque_borrar">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="fila" value="<?= $bid ?>">
            <button class="btn peligro pequeno" type="submit">Borrar tema</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <h3>Añadir tema</h3>
    <form method="post">
      <?= ane_pod_csrf_campo() ?>
      <input type="hidden" name="accion" value="bloque_nuevo">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="campo" style="margin:0 0 10px"><label>Tema</label>
        <input type="text" name="titulo" maxlength="200" placeholder="Las ruedas del mundial" required></div>
      <div class="campo" style="margin:0 0 10px"><label>Qué se cuenta</label>
        <textarea name="texto" maxlength="20000" style="min-height:70px"></textarea></div>
      <div class="campos" style="margin:0 0 10px">
        <div class="campo" style="margin:0"><label>Primer enlace (opcional)</label>
          <input type="url" name="enlace" maxlength="800" placeholder="https://…"></div>
        <div class="campo" style="margin:0"><label>Cómo se llama</label>
          <input type="text" name="enlace_titulo" maxlength="200"></div>
      </div>
      <div class="barra" style="margin:0">
        <div class="cas"><input type="checkbox" id="nint" name="interno" value="1">
          <label for="nint">Interno</label></div>
        <button class="btn azul" type="submit">Añadir tema</button>
      </div>
      <p class="nota" style="margin:10px 0 0">Los demás enlaces, las imágenes y los archivos se añaden desde
        el tema, una vez creado. De todo, tantos como quieras.</p>
    </form>
  </section>

  <!-- ---------------- Menciones ---------------- -->
  <section id="menciones" style="border-left:4px solid var(--rojo)">
    <h2>Patrocinadores y menciones de este episodio</h2>
    <p class="nota">En orden, como los temas. Solo para socios: esto no aparece en el guión compartido.
      Las marcas salen de la pestaña <a href="/estudio/marcas.php">Patrocinadores</a>.</p>

    <?php if (!$menciones): ?><p class="vacio">Ninguna mención apuntada.</p><?php endif; ?>

    <?php foreach ($menciones as $j => $m): $mid = (int) $m['id']; ?>
      <div class="tema<?= (int) $m['hecho'] ? ' hecho' : '' ?>">
        <?php foreach (['arriba' => '↑', 'abajo' => '↓'] as $hacia => $flecha): ?>
          <form method="post" id="mm<?= $hacia ?><?= $mid ?>">
            <?= ane_pod_csrf_campo() ?>
            <input type="hidden" name="accion" value="mencion_mover">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="fila" value="<?= $mid ?>">
            <input type="hidden" name="hacia" value="<?= $hacia ?>">
          </form>
        <?php endforeach; ?>
        <form method="post">
          <?= ane_pod_csrf_campo() ?>
          <input type="hidden" name="accion" value="mencion_guardar">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="fila" value="<?= $mid ?>">
          <div class="barra" style="margin:0 0 10px">
            <h3 style="margin:0"><?= ane_h($m['marca']) ?></h3>
            <div class="acciones">
              <button class="btn claro pequeno" type="submit" form="mmarriba<?= $mid ?>"
                      <?= $j === 0 ? 'disabled' : '' ?>>↑</button>
              <button class="btn claro pequeno" type="submit" form="mmabajo<?= $mid ?>"
                      <?= $j === count($menciones) - 1 ? 'disabled' : '' ?>>↓</button>
              <span class="chip"><?= $j + 1 ?> de <?= count($menciones) ?></span>
            </div>
          </div>
          <div class="campos" style="grid-template-columns:1fr 1fr">
            <div class="campo" style="margin:0"><label>Tipo</label>
              <select name="tipo">
                <?php foreach (ANE_POD_MENCION_TIPOS as $k => $v): ?>
                  <option value="<?= $k ?>"<?= $m['tipo'] === $k ? ' selected' : '' ?>><?= ane_h($v) ?></option>
                <?php endforeach; ?>
              </select></div>
            <div class="campo" style="margin:0"><label>Momento</label>
              <input type="text" name="momento" maxlength="120" value="<?= ane_h($m['momento']) ?>"
                     placeholder="Al arrancar · con el tema 3"></div>
          </div>
          <div class="campo" style="margin:12px 0 10px"><label>Qué hay que decir</label>
            <textarea name="texto" maxlength="4000" style="min-height:60px"><?= ane_h($m['texto']) ?></textarea></div>
          <div class="barra" style="margin:0">
            <div class="cas"><input type="checkbox" id="h<?= $mid ?>" name="hecho" value="1"
                 <?= (int) $m['hecho'] ? 'checked' : '' ?>>
              <label for="h<?= $mid ?>">Hecha</label></div>
            <button class="btn pequeno" type="submit">Guardar</button>
          </div>
        </form>
        <form method="post" onsubmit="return confirm('¿Borrar la mención?')" style="margin-top:10px">
          <?= ane_pod_csrf_campo() ?>
          <input type="hidden" name="accion" value="mencion_borrar">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="fila" value="<?= $mid ?>">
          <button class="btn peligro pequeno" type="submit">Borrar mención</button>
        </form>
      </div>
    <?php endforeach; ?>

    <h3>Añadir mención</h3>
    <form method="post">
      <?= ane_pod_csrf_campo() ?>
      <input type="hidden" name="accion" value="mencion_nueva">
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
        <div class="campo" style="margin:0"><label>Tipo</label>
          <select name="tipo">
            <?php foreach (ANE_POD_MENCION_TIPOS as $k => $v): ?>
              <option value="<?= $k ?>"><?= ane_h($v) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="campo" style="margin:0"><label>Momento</label>
          <input type="text" name="momento" maxlength="120" placeholder="Al arrancar"></div>
      </div>
      <div class="campo" style="margin:12px 0 10px"><label>Qué hay que decir</label>
        <textarea name="texto" maxlength="4000" style="min-height:70px"></textarea></div>
      <button class="btn azul" type="submit">Añadir mención</button>
    </form>
  </section>

  <!-- ---------------- Accesos ---------------- -->
  <section id="accesos">
    <h2>Quién puede ver este guión</h2>
    <p class="nota">Editores dados de alta en <a href="/estudio/accesos.php">Accesos</a>.
      Verán el guión con sus enlaces e imágenes; ni menciones, ni notas, ni temas internos.</p>
    <?php if (!$editores): ?>
      <p class="vacio">No hay ningún editor dado de alta.
        <a href="/estudio/accesos.php">Crear uno →</a></p>
    <?php else: ?>
      <form method="post">
        <?= ane_pod_csrf_campo() ?>
        <input type="hidden" name="accion" value="accesos">
        <input type="hidden" name="id" value="<?= $id ?>">
        <?php foreach ($editores as $u): ?>
          <div class="cas" style="margin-bottom:8px">
            <input type="checkbox" id="u<?= (int) $u['id'] ?>" name="editor[]" value="<?= (int) $u['id'] ?>"
                   <?= in_array((int) $u['id'], $conAcceso, true) ? 'checked' : '' ?>>
            <label for="u<?= (int) $u['id'] ?>">
              <b><?= ane_h($u['nombre'] !== '' ? $u['nombre'] : $u['usuario']) ?></b>
              <span class="dom">(<?= ane_h($u['usuario']) ?>)</span>
              <?php if ($u['nota'] !== ''): ?> — <?= ane_h($u['nota']) ?><?php endif; ?>
              <?php if (!(int) $u['activo']): ?> <span class="chip descartado">desactivado</span><?php endif; ?>
            </label>
          </div>
        <?php endforeach; ?>
        <button class="btn azul" type="submit">Guardar accesos</button>
      </form>
    <?php endif; ?>
  </section>

  <section>
    <h2>Borrar el episodio</h2>
    <p class="nota">Se va con su guión, sus imágenes y sus menciones. No hay vuelta.</p>
    <form method="post" onsubmit="return confirm('¿Borrar el episodio entero y todo lo suyo?')">
      <?= ane_pod_csrf_campo() ?>
      <input type="hidden" name="accion" value="borrar_episodio">
      <input type="hidden" name="id" value="<?= $id ?>">
      <button class="btn peligro" type="submit">Borrar el episodio</button>
    </form>
  </section>
</main>
<?php
ane_pod_pie();
