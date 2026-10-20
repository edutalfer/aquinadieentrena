<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio · entrar y salir
   ------------------------------------------------------------
   Formulario propio (no la ventana del navegador): aquí entran
   varias personas con permisos distintos. Tras 8 fallos en un
   cuarto de hora, ese usuario queda frenado 15 minutos.
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/_plantilla.php';

$bd = ane_pod_bd();

/* ---------- Salir ---------- */
if (isset($_GET['salir'])) {
    ane_pod_cierra_sesion();
    header('Location: /estudio/entrar.php?ok=fuera', true, 303);
    exit;
}

/* Si ya está dentro, no tiene nada que hacer aquí */
if (ane_pod_quien($bd) && !isset($_GET['otro'])) {
    header('Location: /estudio/', true, 302);
    exit;
}

/* Arranque. Con la base sin usuarios, el alta por web solo se abre si existe
   la señal ~/datos/estudio_alta, que se pone desde el servidor. Si no, una URL
   recién publicada sería un «créate tú el acceso de socio» para el primer bot
   que pasara. La vía recomendada es la línea de órdenes:
       php -f estudio/_alta.php eduardo socio "Eduardo"   */
$sinNadie = !(int) $bd->query('SELECT COUNT(*) FROM pod_usuarios')->fetchColumn();
$senal = ANE_DATOS . '/estudio_alta';
$vacio = $sinNadie && is_file($senal);

$error = '';
$volver = (string) ($_GET['volver'] ?? ($_POST['volver'] ?? ''));
if (!preg_match('~^/estudio/[A-Za-z0-9._/?=&-]*$~', $volver)) {
    $volver = '/estudio/';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($origen !== '' && parse_url($origen, PHP_URL_HOST) !== parse_url('http://' . $host, PHP_URL_HOST)) {
        http_response_code(403);
        exit('Petición rechazada.');
    }

    $usuario = ane_pod_nombre_usuario((string) ($_POST['usuario'] ?? ''));
    $clave = (string) ($_POST['clave'] ?? '');

    if ($vacio) {
        /* Alta del primer socio */
        if (!ane_pod_usuario_valido($usuario)) {
            $error = 'El usuario admite letras, números, punto, guion y guion bajo (3 a 32).';
        } elseif (mb_strlen($clave) < 10) {
            $error = 'La contraseña tiene que tener 10 caracteres o más.';
        } elseif ($clave !== (string) ($_POST['clave2'] ?? '')) {
            $error = 'Las dos contraseñas no coinciden.';
        } else {
            /* Darse de alta es entrar: si no, el panel diría «no ha entrado
               todavía» de alguien que está dentro en ese mismo momento. */
            $bd->prepare('INSERT INTO pod_usuarios (usuario, nombre, hash, rol, creado, ultimo)
                          VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([$usuario, ane_recorta((string) ($_POST['nombre'] ?? ''), 80),
                          password_hash($clave, PASSWORD_DEFAULT), 'socio', ane_ahora(), ane_ahora()]);
            @unlink($senal);   /* la señal vale para un alta y se acabó */
            $u = ane_pod_por_usuario($bd, $usuario);
            ane_pod_abre_sesion($u);
            header('Location: /estudio/', true, 303);
            exit;
        }
    } else {
        $u = $usuario !== '' ? ane_pod_por_usuario($bd, $usuario) : null;
        if ($usuario !== '' && ane_pod_fallos($bd, $usuario) >= 8) {
            $error = 'Demasiados intentos. Espera un cuarto de hora.';
        } elseif ($u && (int) $u['activo'] && password_verify($clave, (string) $u['hash'])) {
            ane_pod_limpia_fallos($bd, $usuario);
            $bd->prepare('UPDATE pod_usuarios SET ultimo = ? WHERE id = ?')->execute([ane_ahora(), (int) $u['id']]);
            ane_pod_abre_sesion($u);
            header('Location: ' . $volver, true, 303);
            exit;
        } else {
            if ($usuario !== '') {
                ane_pod_apunta_fallo($bd, $usuario);
            }
            usleep(600000);
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}

ane_pod_cabeza($vacio ? 'Primer acceso' : 'Entrar');
?>
<main style="max-width:430px;margin:0 auto;padding:60px 20px">
  <h1 class="display" style="font-size:38px;margin:0 0 6px">Estudio <em style="color:var(--azul)">ANE</em></h1>
  <p class="nota" style="margin-bottom:22px">
    <?= $vacio ? 'No hay nadie dado de alta todavía: crea el primer acceso de socio.'
               : 'Organización del podcast y de los proyectos. Área privada.' ?>
  </p>

  <?php if ($sinNadie && !$vacio): ?>
    <div class="aviso">El estudio está sin estrenar. El primer acceso se crea desde el servidor:
      <br><span class="clave">php -f estudio/_alta.php eduardo socio "Eduardo"</span></div>
  <?php endif; ?>

  <?php if ($error !== ''): ?><div class="aviso mal"><?= ane_h($error) ?></div><?php endif; ?>
  <?php if (($_GET['ok'] ?? '') === 'fuera'): ?><div class="aviso">Has salido.</div><?php endif; ?>

  <section>
    <form method="post" autocomplete="on">
      <input type="hidden" name="volver" value="<?= ane_h($volver) ?>">
      <?php if ($vacio): ?>
        <div class="campo"><label for="nombre">Tu nombre</label>
          <input type="text" id="nombre" name="nombre" value="<?= ane_h((string) ($_POST['nombre'] ?? '')) ?>" maxlength="80"></div>
      <?php endif; ?>
      <div class="campo"><label for="usuario">Usuario</label>
        <input type="text" id="usuario" name="usuario" value="<?= ane_h((string) ($_POST['usuario'] ?? '')) ?>"
               maxlength="32" autocapitalize="none" spellcheck="false" required autofocus></div>
      <div class="campo"><label for="clave">Contraseña</label>
        <input type="password" id="clave" name="clave" maxlength="200"
               autocomplete="<?= $vacio ? 'new-password' : 'current-password' ?>" required></div>
      <?php if ($vacio): ?>
        <div class="campo"><label for="clave2">Repite la contraseña</label>
          <input type="password" id="clave2" name="clave2" maxlength="200" autocomplete="new-password" required></div>
      <?php endif; ?>
      <button class="btn azul" type="submit"><?= $vacio ? 'Crear mi acceso' : 'Entrar' ?></button>
    </form>
  </section>

  <p class="nota">La sesión dura 30 días en este navegador. Cambiar la contraseña cierra las demás.</p>
</main>
<?php
ane_pod_pie();
