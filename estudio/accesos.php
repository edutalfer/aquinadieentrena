<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio · accesos
   ------------------------------------------------------------
   Quién entra y hasta dónde:
     socio     → todo
     editor    → solo el guión definitivo de los episodios marcados,
                 en lectura (cuñas incluidas)

   Las contraseñas se generan aquí y se enseñan UNA vez: en la
   base solo queda el hash. Si se pierde, se genera otra.
   Nunca van en la URL (ni en un redirect): se pintan en la
   respuesta del propio POST.
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/_comun.php';
ane_pod_solo_socio();

/* Contraseña legible y decente: 4 bloques de 4 sin caracteres confusos */
function ane_pod_clave_nueva(): string
{
    $abc = 'abcdefghijkmnpqrstuvwxyz23456789';
    $partes = [];
    for ($b = 0; $b < 4; $b++) {
        $p = '';
        for ($i = 0; $i < 4; $i++) {
            $p .= $abc[random_int(0, strlen($abc) - 1)];
        }
        $partes[] = $p;
    }
    return implode('-', $partes);
}

$claveNueva = '';        /* se enseña una vez, sin pasar por la URL */
$claveDe = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    ane_pod_csrf_exige();
    $accion = (string) ($_POST['accion'] ?? '');
    $fila = (int) ($_POST['fila'] ?? 0);
    $destino = $fila ? ane_pod_por_id($bd, $fila) : null;

    switch ($accion) {
        case 'crear':
            $usuario = ane_pod_nombre_usuario((string) ($_POST['usuario'] ?? ''));
            $rol = ($_POST['rol'] ?? '') === 'socio' ? 'socio' : 'editor';
            if (!ane_pod_usuario_valido($usuario)) {
                $error = 'El usuario admite letras, números, punto, guion y guion bajo (3 a 32 caracteres).';
            } elseif (ane_pod_por_usuario($bd, $usuario)) {
                $error = 'Ese usuario ya existe.';
            } else {
                $claveNueva = ane_pod_clave_nueva();
                $bd->prepare('INSERT INTO pod_usuarios (usuario, nombre, hash, rol, nota, creado)
                              VALUES (?, ?, ?, ?, ?, ?)')
                   ->execute([$usuario, ane_recorta((string) ($_POST['nombre'] ?? ''), 80),
                              password_hash($claveNueva, PASSWORD_DEFAULT), $rol,
                              ane_recorta((string) ($_POST['nota'] ?? ''), 200), ane_ahora()]);
                $claveDe = $usuario;
            }
            break;

        case 'clave':
            if ($destino) {
                $claveNueva = ane_pod_clave_nueva();
                $bd->prepare('UPDATE pod_usuarios SET hash = ? WHERE id = ?')
                   ->execute([password_hash($claveNueva, PASSWORD_DEFAULT), (int) $destino['id']]);
                $claveDe = (string) $destino['usuario'];
                /* Cambiar el hash invalida las sesiones: si era la mía, me vuelvo a sellar */
                if ((int) $destino['id'] === (int) $yo_pod['id']) {
                    $yo_pod = ane_pod_por_id($bd, (int) $destino['id']);
                    ane_pod_abre_sesion($yo_pod);
                }
            }
            break;

        case 'mi_clave':
            $nueva = (string) ($_POST['clave'] ?? '');
            if (!password_verify((string) ($_POST['actual'] ?? ''), (string) $yo_pod['hash'])) {
                $error = 'La contraseña actual no es correcta.';
            } elseif (mb_strlen($nueva) < 10) {
                $error = 'La nueva contraseña tiene que tener 10 caracteres o más.';
            } elseif ($nueva !== (string) ($_POST['clave2'] ?? '')) {
                $error = 'Las dos contraseñas nuevas no coinciden.';
            } else {
                $bd->prepare('UPDATE pod_usuarios SET hash = ? WHERE id = ?')
                   ->execute([password_hash($nueva, PASSWORD_DEFAULT), (int) $yo_pod['id']]);
                $yo_pod = ane_pod_por_id($bd, (int) $yo_pod['id']);
                ane_pod_abre_sesion($yo_pod);
                header('Location: /estudio/accesos.php?ok=miclave', true, 303);
                exit;
            }
            break;

        case 'datos':
            if ($destino) {
                $bd->prepare('UPDATE pod_usuarios SET nombre = ?, nota = ?, activo = ? WHERE id = ?')
                   ->execute([ane_recorta((string) ($_POST['nombre'] ?? ''), 80),
                              ane_recorta((string) ($_POST['nota'] ?? ''), 200),
                              empty($_POST['activo']) ? 0 : 1, (int) $destino['id']]);
                header('Location: /estudio/accesos.php?ok=guardado#u' . (int) $destino['id'], true, 303);
                exit;
            }
            break;

        case 'accesos':
            if ($destino && $destino['rol'] === 'editor') {
                $bd->prepare('DELETE FROM pod_accesos WHERE usuario_id = ?')->execute([(int) $destino['id']]);
                $st = $bd->prepare('INSERT INTO pod_accesos (usuario_id, episodio_id, creado) VALUES (?, ?, ?)');
                $ahora = ane_ahora();
                foreach ((array) ($_POST['episodio'] ?? []) as $eid) {
                    if (ane_pod_episodio($bd, (int) $eid)) {
                        $st->execute([(int) $destino['id'], (int) $eid, $ahora]);
                    }
                }
                header('Location: /estudio/accesos.php?ok=accesos#u' . (int) $destino['id'], true, 303);
                exit;
            }
            break;

        case 'borrar':
            /* Quedarse sin ningún socio dejaría el estudio inaccesible */
            $socios = (int) $bd->query("SELECT COUNT(*) FROM pod_usuarios WHERE rol = 'socio' AND activo = 1")->fetchColumn();
            if ($destino && !((int) $destino['id'] === (int) $yo_pod['id'] || ($destino['rol'] === 'socio' && $socios <= 1))) {
                $bd->prepare('DELETE FROM pod_accesos WHERE usuario_id = ?')->execute([(int) $destino['id']]);
                $bd->prepare('DELETE FROM pod_usuarios WHERE id = ?')->execute([(int) $destino['id']]);
                header('Location: /estudio/accesos.php?ok=borrado', true, 303);
                exit;
            }
            $error = 'No se puede borrar: o eres tú, o es el último socio activo.';
            break;
    }
}

$usuarios = $bd->query("SELECT * FROM pod_usuarios ORDER BY rol = 'editor', nombre, usuario")->fetchAll();
$episodios = ane_pod_episodios($bd);
$accesos = [];
foreach ($bd->query('SELECT usuario_id, episodio_id FROM pod_accesos') as $f) {
    $accesos[(int) $f['usuario_id']][] = (int) $f['episodio_id'];
}

ane_pod_cabeza('Accesos');
ane_pod_cabecera('accesos');
?>
<main class="caja">
  <?php ane_pod_aviso(['guardado' => 'Cambios guardados.', 'accesos' => 'Accesos actualizados.',
                       'borrado' => 'Acceso borrado.', 'miclave' => 'Tu contraseña está cambiada.']); ?>
  <?php if ($error !== ''): ?><div class="aviso mal"><?= ane_h($error) ?></div><?php endif; ?>

  <?php if ($claveNueva !== ''): ?>
    <div class="aviso ok">
      <b>Contraseña de <?= ane_h($claveDe) ?>:</b> <span class="clave"><?= ane_h($claveNueva) ?></span><br>
      Cópiala ahora y pásasela por un canal privado: no se vuelve a enseñar.
      Entra en <span class="clave">https://aquinadieentrena.cc/estudio/</span>
    </div>
  <?php endif; ?>

  <section>
    <h2>Cómo funciona</h2>
    <p class="nota" style="margin:0">
      Un <b>socio</b> lo ve y lo toca todo: episodios, guiones, patrocinadores, proyectos y presupuestos.
      Un <b>editor</b> (el que monta el vídeo) solo ve el <b>guión definitivo</b> de los episodios que le
      marques, y en lectura: no puede tocar nada. Ve temas, enlaces, imágenes, archivos y las cuñas de
      patrocinio en su sitio, que las tiene que montar. No ve proyectos, ni la agenda de patrocinadores
      (contactos, estados, importes), ni accesos, ni notas internas, ni temas marcados como internos.
    </p>
  </section>

  <?php foreach ($usuarios as $u): $uid = (int) $u['id']; $esInv = $u['rol'] === 'editor'; ?>
    <section id="u<?= $uid ?>"<?= $esInv ? '' : ' style="border-left:4px solid var(--azul)"' ?>>
      <div class="barra" style="margin-bottom:12px">
        <div>
          <h2><?= ane_h($u['nombre'] !== '' ? $u['nombre'] : $u['usuario']) ?>
            <span class="dom" style="font-weight:400">· <?= ane_h($u['usuario']) ?></span></h2>
          <p class="nota" style="margin:0">
            <?= $esInv ? 'Editor' : 'Socio' ?>
            · Alta <?= ane_h(ane_utc_a_local($u['creado'], 'j/n/Y')) ?>
            · <?= $u['ultimo'] ? 'Última entrada ' . ane_h(ane_utc_a_local($u['ultimo'], 'j/n/Y H:i')) : 'No ha entrado todavía' ?>
            <?= $uid === (int) $yo_pod['id'] ? ' · eres tú' : '' ?>
          </p>
        </div>
        <div class="acciones">
          <?php if (!(int) $u['activo']): ?><span class="chip descartado">desactivado</span><?php endif; ?>
          <form method="post" class="enlinea" onsubmit="return confirm('¿Generar una contraseña nueva? La actual deja de valer.')">
            <?= ane_pod_csrf_campo() ?>
            <input type="hidden" name="accion" value="clave">
            <input type="hidden" name="fila" value="<?= $uid ?>">
            <button class="btn claro pequeno" type="submit">Nueva contraseña</button>
          </form>
          <?php if ($uid !== (int) $yo_pod['id']): ?>
            <form method="post" class="enlinea" onsubmit="return confirm('¿Borrar este acceso?')">
              <?= ane_pod_csrf_campo() ?>
              <input type="hidden" name="accion" value="borrar">
              <input type="hidden" name="fila" value="<?= $uid ?>">
              <button class="btn peligro pequeno" type="submit">Borrar</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <div class="dos">
        <form method="post">
          <?= ane_pod_csrf_campo() ?>
          <input type="hidden" name="accion" value="datos">
          <input type="hidden" name="fila" value="<?= $uid ?>">
          <div class="campos">
            <div class="campo" style="margin:0"><label>Nombre</label>
              <input type="text" name="nombre" maxlength="80" value="<?= ane_h($u['nombre']) ?>"></div>
            <div class="campo" style="margin:0"><label>Para acordarte de quién es</label>
              <input type="text" name="nota" maxlength="200" value="<?= ane_h($u['nota']) ?>"
                     placeholder="Monta los vídeos desde octubre"></div>
          </div>
          <div class="cas" style="margin:12px 0">
            <input type="checkbox" id="a<?= $uid ?>" name="activo" value="1" <?= (int) $u['activo'] ? 'checked' : '' ?>>
            <label for="a<?= $uid ?>">Puede entrar</label>
          </div>
          <button class="btn pequeno" type="submit">Guardar</button>
        </form>

        <?php if ($esInv): ?>
          <form method="post">
            <?= ane_pod_csrf_campo() ?>
            <input type="hidden" name="accion" value="accesos">
            <input type="hidden" name="fila" value="<?= $uid ?>">
            <h3 style="margin-top:0">Guiones que puede ver</h3>
            <?php if (!$episodios): ?>
              <p class="vacio">No hay episodios todavía.</p>
            <?php else: ?>
              <div style="max-height:230px;overflow:auto;border:1px solid var(--linea);padding:10px">
                <?php foreach ($episodios as $e): ?>
                  <div class="cas" style="margin-bottom:6px">
                    <input type="checkbox" id="e<?= $uid ?>_<?= (int) $e['id'] ?>" name="episodio[]" value="<?= (int) $e['id'] ?>"
                           <?= in_array((int) $e['id'], $accesos[$uid] ?? [], true) ? 'checked' : '' ?>>
                    <label for="e<?= $uid ?>_<?= (int) $e['id'] ?>">
                      <?= ane_h(mb_strimwidth(ane_pod_etiqueta($e), 0, 46, '…')) ?>
                      <span class="dom">· <?= ane_h(ane_pod_fecha_larga($e['fecha'])) ?></span></label>
                  </div>
                <?php endforeach; ?>
              </div>
              <p style="margin-top:10px"><button class="btn azul pequeno" type="submit">Guardar guiones</button></p>
            <?php endif; ?>
          </form>
        <?php else: ?>
          <div><h3 style="margin-top:0">Permisos</h3>
            <p class="nota">Socio: episodios, guiones, patrocinadores, proyectos y presupuestos.</p></div>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <section>
    <h2>Nuevo acceso</h2>
    <p class="nota">La contraseña se genera sola y se enseña una vez, aquí arriba.</p>
    <form method="post">
      <?= ane_pod_csrf_campo() ?>
      <input type="hidden" name="accion" value="crear">
      <div class="campos">
        <div class="campo" style="margin:0"><label>Usuario</label>
          <input type="text" name="usuario" maxlength="32" autocapitalize="none" spellcheck="false"
                 placeholder="cristobal" required></div>
        <div class="campo" style="margin:0"><label>Nombre</label>
          <input type="text" name="nombre" maxlength="80"></div>
        <div class="campo" style="margin:0"><label>Tipo</label>
          <select name="rol">
            <option value="editor">Editor de vídeo — solo guiones</option>
            <option value="socio">Socio — todo</option>
          </select></div>
        <div class="campo" style="margin:0"><label>Nota</label>
          <input type="text" name="nota" maxlength="200"></div>
      </div>
      <p><button class="btn azul" type="submit">Crear acceso</button></p>
    </form>
  </section>

  <section>
    <h2>Tu contraseña</h2>
    <p class="nota">Cambiarla cierra tu sesión en los demás navegadores.</p>
    <form method="post" style="max-width:420px">
      <?= ane_pod_csrf_campo() ?>
      <input type="hidden" name="accion" value="mi_clave">
      <div class="campo"><label for="actual">Contraseña actual</label>
        <input type="password" id="actual" name="actual" autocomplete="current-password" required></div>
      <div class="campo"><label for="nc">Nueva (10 caracteres o más)</label>
        <input type="password" id="nc" name="clave" autocomplete="new-password" required></div>
      <div class="campo"><label for="nc2">Repítela</label>
        <input type="password" id="nc2" name="clave2" autocomplete="new-password" required></div>
      <button class="btn" type="submit">Cambiar mi contraseña</button>
    </form>
  </section>
</main>
<?php
ane_pod_pie();
