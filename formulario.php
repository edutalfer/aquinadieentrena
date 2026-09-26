<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — formulario público de inscripción
   ------------------------------------------------------------
   URL: /f/<código>  (el .htaccess la reescribe a este fichero)
   Página «oculta»: no está enlazada en ningún sitio, no se indexa
   y el código es aleatorio. Solo llega quien tiene el enlace.
   Se crea y se gestiona en el panel: /admin/formularios.php
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/api/_formularios.php';

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');

$token = (string) ($_GET['t'] ?? '');
$bd = ane_bd();
$form = ane_form_por_token($bd, $token);

if (!$form) {
    http_response_code(404);
}

$url = '/f/' . rawurlencode($token);
$previa = isset($_GET['previa']);
$preguntas = $form ? ane_form_preguntas($form) : [];
$valores = [];
$errores = [];
$avisoGeneral = '';

/* ---------- Envío ---------- */

if ($form && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !$previa) {
    $sello = ane_form_comprueba_sello((string) ($_POST['_sello'] ?? ''), $token);

    /* Bots: el campo trampa relleno, un sello falso o un envío en menos
       de 3 s. Se les enseña la confirmación y no se guarda nada. */
    if (($_POST['web'] ?? '') !== '' || $sello === 'falso' || $sello === 'rapido') {
        header('Location: ' . $url . '?ok=1', true, 303);
        exit;
    }

    [$valores, $errores] = ane_form_valida($preguntas, $_POST);
    if (empty($_POST['_acepto'])) {
        $errores['_acepto'] = 'Tienes que aceptar el tratamiento de tus datos para inscribirte.';
    }
    if ($sello === 'caducado' && !$errores) {
        $avisoGeneral = 'La página llevaba mucho tiempo abierta. Revisa tus respuestas y vuelve a pulsar «Enviar».';
    } elseif (!$errores) {
        try {
            $r = ane_form_guarda($bd, $form, $preguntas, $valores);
        } catch (Throwable $t) {
            error_log('ANE formulario: ' . $t->getMessage());
            $r = ['ok' => false, 'motivo' => 'error'];
        }
        if ($r['ok']) {
            header('Location: ' . $url . '?ok=' . ($r['reserva'] ? 'espera' : '1'), true, 303);
            exit;
        }
        switch ($r['motivo']) {
            case 'duplicado':
                foreach ($preguntas as $p) {
                    if ($p['tipo'] === 'email') {
                        $errores[$p['id']] = 'Ya hay una inscripción con este correo.';
                        break;
                    }
                }
                break;
            case 'saturado':
                $avisoGeneral = 'Hay muchísima gente apuntándose ahora mismo. Espera un minuto y vuelve a pulsar «Enviar».';
                break;
            case 'error':
                $avisoGeneral = 'Algo ha fallado al guardar tu inscripción. Vuelve a intentarlo en un momento.';
                break;
            default:   // completo o cerrado mientras rellenaba: se pinta abajo
                break;
        }
    }
    if ($errores && !$avisoGeneral) {
        $avisoGeneral = count($errores) === 1
            ? 'Revisa la pregunta marcada.'
            : 'Revisa las ' . count($errores) . ' preguntas marcadas.';
    }
}

$estado = $form ? ane_form_estado($bd, $form) : null;
$ok = (string) ($_GET['ok'] ?? '');
$fase = !$form ? 'no-existe'
      : ($ok !== '' ? 'enviado'
      : ($estado['acepta'] || $previa ? 'formulario' : 'cerrado'));

/* ---------- Pintado de cada pregunta ---------- */

function ane_campo(array $p, $valor, bool $desactivado): string
{
    $id = ane_h($p['id']);
    $req = !empty($p['obligatoria']) ? ' required' : '';
    $dis = $desactivado ? ' disabled' : '';
    $v = is_string($valor) ? ane_h($valor) : '';
    $auto = '';
    if (preg_match('/nombre/i', $p['titulo'])) {
        $auto = ' autocomplete="name"';
    }
    switch ($p['tipo']) {
        case 'largo':
            return "<textarea id=\"$id\" name=\"$id\" rows=\"4\" maxlength=\"3000\" placeholder=\"Tu respuesta\"$req$dis>$v</textarea>";
        case 'email':
            return "<input id=\"$id\" name=\"$id\" type=\"email\" value=\"$v\" maxlength=\"200\" autocomplete=\"email\" placeholder=\"tu@correo.com\"$req$dis>";
        case 'telefono':
            return "<input id=\"$id\" name=\"$id\" type=\"tel\" value=\"$v\" maxlength=\"30\" autocomplete=\"tel\" inputmode=\"tel\" placeholder=\"Tu teléfono\"$req$dis>";
        case 'numero':
            return "<input id=\"$id\" name=\"$id\" type=\"text\" value=\"$v\" maxlength=\"30\" inputmode=\"decimal\" placeholder=\"Tu respuesta\"$req$dis>";
        case 'fecha':
            return "<input id=\"$id\" name=\"$id\" type=\"date\" value=\"$v\"$req$dis>";
        case 'desplegable':
            $h = "<select id=\"$id\" name=\"$id\"$req$dis><option value=\"\">Elige una opción</option>";
            foreach ($p['opciones'] as $o) {
                $sel = $valor === $o ? ' selected' : '';
                $h .= '<option' . $sel . '>' . ane_h($o) . '</option>';
            }
            return $h . '</select>';
        case 'opcion':
        case 'casillas':
            $multi = $p['tipo'] === 'casillas';
            $h = '<div class="opciones">';
            foreach ($p['opciones'] as $i => $o) {
                $marcado = $multi ? in_array($o, (array) $valor, true) : $valor === $o;
                $h .= '<label class="opcion"><input type="' . ($multi ? 'checkbox' : 'radio') . '" name="' . $id . ($multi ? '[]' : '')
                    . '" value="' . ane_h($o) . '"' . ($marcado ? ' checked' : '')
                    . (!$multi && $i === 0 ? $req : '') . $dis . '><span>' . ane_h($o) . '</span></label>';
            }
            return $h . '</div>';
        default:
            return "<input id=\"$id\" name=\"$id\" type=\"text\" value=\"$v\" maxlength=\"300\" placeholder=\"Tu respuesta\"$auto$req$dis>";
    }
}

$titulo = $form ? $form['titulo'] : 'Formulario no encontrado';
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= ane_h($titulo) ?> — Aquí Nadie Entrena</title>
<meta name="theme-color" content="#191919">
<?php if ($form): ?>
<meta property="og:title" content="<?= ane_h($form['titulo']) ?>">
<meta property="og:description" content="Inscripción · Aquí Nadie Entrena">
<meta property="og:image" content="https://aquinadieentrena.cc/assets/img/icono-512.png">
<?php endif; ?>
<link rel="icon" href="/assets/img/icono-512.png">
<link rel="apple-touch-icon" href="/assets/img/icono-180.png">
<link rel="preload" href="/assets/fonts/archivo-black-italic.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/inter.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/assets/css/estilo.css?v=20260818d">
<style>
  body { background: var(--gris-humo); }
  .hoja { max-width: 700px; margin: 0 auto; padding: 28px 16px 70px; }
  .tarjeta { background: var(--blanco); border: 1px solid #E1E3E7; padding: 24px 26px; margin-bottom: 14px; }
  .tarjeta--cabecera { border-top: 10px solid var(--azul); padding-top: 26px; }
  .tarjeta--cabecera h1 { font-size: clamp(30px, 6vw, 44px); margin: 0 0 18px; }
  .descripcion { font-size: 16px; }
  .descripcion p { margin: 0 0 12px; }
  .descripcion ul { margin: 0 0 12px; padding-left: 22px; }
  .descripcion li { margin-bottom: 3px; }
  .descripcion a { color: var(--azul-enlace); }
  .obligatorio-nota { font-size: 13px; color: var(--gris-medio); margin: 16px 0 0;
                      padding-top: 14px; border-top: 1px solid #E1E3E7; }
  .ast { color: var(--azul-enlace); font-weight: 700; }

  /* Plazas */
  .plazas { margin: 18px 0 2px; }
  .plazas__texto { display: flex; justify-content: space-between; gap: 12px; font-size: 14px; font-weight: 600; margin-bottom: 7px; }
  .plazas__texto span:last-child { color: var(--gris-medio); font-weight: 500; }
  .plazas__barra { height: 8px; background: var(--gris-humo); }
  .plazas__barra div { height: 100%; background: var(--azul); }

  /* Preguntas */
  .pregunta label.enunciado { display: block; font-weight: 600; font-size: 16px; margin-bottom: 4px; }
  .ayuda { font-size: 14px; color: var(--gris-medio); margin: 0 0 10px; }
  .pregunta input[type=text], .pregunta input[type=email], .pregunta input[type=tel],
  .pregunta input[type=date], .pregunta textarea, .pregunta select {
    width: 100%; max-width: 440px; font: inherit; font-size: 16px; color: var(--negro);
    border: 0; border-bottom: 1px solid #C9CDD3; background: transparent; padding: 9px 2px; margin-top: 6px;
    border-radius: 0; }
  .pregunta textarea { max-width: none; resize: vertical; }
  .pregunta select { border: 1px solid #C9CDD3; padding: 9px 10px; background: var(--blanco); }
  .pregunta input:focus, .pregunta textarea:focus, .pregunta select:focus {
    outline: none; border-bottom: 2px solid var(--azul); padding-bottom: 8px; }
  .opciones { display: grid; gap: 4px; margin-top: 6px; }
  .opcion { display: flex; gap: 12px; align-items: center; padding: 7px 0; cursor: pointer; font-size: 16px; }
  .opcion input { width: 20px; height: 20px; accent-color: var(--azul-enlace); margin: 0; flex: none; }
  .pregunta--error { border-left: 5px solid var(--azul-profundo); }
  .error { font-size: 14px; font-weight: 700; color: var(--negro); margin: 10px 0 0; }
  .error::before { content: "⚠ "; }

  /* Privacidad */
  .privacidad { font-size: 14px; color: var(--gris-carbon); }
  .privacidad p { margin: 0 0 10px; }
  .privacidad a { color: var(--azul-enlace); }
  .privacidad .opcion { font-size: 15px; font-weight: 600; color: var(--negro); align-items: flex-start; }
  .privacidad .opcion input { margin-top: 2px; }

  .aviso { background: var(--negro); color: var(--blanco); padding: 16px 20px; margin-bottom: 14px; font-weight: 600; font-size: 15px; }
  .aviso--espera { background: var(--azul-profundo); }
  .aviso--previa { background: var(--azul-destello); color: var(--negro); }

  .acciones-form { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 6px; }
  .enviar { font: 700 16px/1 var(--texto); letter-spacing: .06em; text-transform: uppercase; cursor: pointer;
            background: var(--azul-enlace); color: var(--blanco); border: 2px solid var(--azul-enlace); padding: 15px 30px; }
  .enviar:hover { background: var(--negro); border-color: var(--negro); }
  .enviar:disabled { background: #C9CDD3; border-color: #C9CDD3; cursor: not-allowed; }
  .trampa { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }

  .final h2 { font-size: clamp(30px, 6vw, 42px); margin: 0 0 14px; }
  .final p { font-size: 16px; margin: 0 0 12px; }
  .pie-form { text-align: center; font-size: 13px; color: var(--gris-medio); margin-top: 26px; }
  .pie-form a { color: var(--gris-medio); }
  @media (max-width: 560px) { .tarjeta { padding: 20px 18px; } .acciones-form { flex-direction: column-reverse; align-items: stretch; } }
</style>
</head>
<body>

<header class="cabecera">
  <div class="envoltorio cabecera__fila">
    <a class="logo" href="/"><img src="/assets/img/logo-negativo.png" alt="Aquí Nadie Entrena"></a>
  </div>
</header>

<main class="hoja">

<?php if ($fase === 'no-existe'): ?>

  <div class="tarjeta tarjeta--cabecera final">
    <h1 class="display">Este formulario no existe</h1>
    <p>Puede que el enlace esté mal copiado o que el formulario se haya retirado.
       Si crees que es un error, escríbenos a <a href="mailto:hola@aquinadieentrena.cc">hola@aquinadieentrena.cc</a>.</p>
  </div>

<?php else: ?>

  <div class="tarjeta tarjeta--cabecera">
    <h1 class="display"><?= ane_h($form['titulo']) ?></h1>
    <?php if ($fase !== 'enviado' && trim($form['descripcion']) !== ''): ?>
      <div class="descripcion"><?= ane_form_texto($form['descripcion']) ?></div>
    <?php endif; ?>

    <?php if ($fase === 'formulario' && (int) $form['mostrar_plazas'] && $estado['limite'] > 0):
        $pct = min(100, round(100 * $estado['ocupadas'] / $estado['limite'])); ?>
      <div class="plazas">
        <div class="plazas__texto">
          <span><?= $estado['quedan'] > 0
              ? ($estado['quedan'] === 1 ? 'Queda 1 plaza' : 'Quedan ' . $estado['quedan'] . ' plazas')
              : 'Plazas completas' ?></span>
          <span><?= $estado['ocupadas'] ?> de <?= $estado['limite'] ?></span>
        </div>
        <div class="plazas__barra"><div style="width: <?= $pct ?>%"></div></div>
      </div>
    <?php endif; ?>

    <?php if ($fase === 'formulario'): ?>
      <p class="obligatorio-nota"><span class="ast">*</span> Indica que la pregunta es obligatoria</p>
    <?php endif; ?>
  </div>

  <?php if ($fase === 'enviado'): ?>

    <div class="tarjeta final">
      <?php if ($ok === 'espera'): ?>
        <h2 class="display">Estás en la lista de espera</h2>
        <p>Las plazas ya estaban cubiertas, así que te hemos apuntado como reserva.
           Si alguien se cae, te avisamos por orden de inscripción.</p>
      <?php else: ?>
        <h2 class="display">¡Inscripción recibida!</h2>
        <?php if (trim($form['mensaje_ok']) !== ''): ?>
          <div class="descripcion"><?= ane_form_texto($form['mensaje_ok']) ?></div>
        <?php else: ?>
          <p>Ya estás dentro. Nos vemos allí.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>

  <?php elseif ($fase === 'cerrado'): ?>

    <div class="tarjeta final">
      <?php if ($estado['estado'] === 'completo'): ?>
        <h2 class="display">Plazas agotadas</h2>
        <p>Se han cubierto las <?= $estado['limite'] ?> plazas. ¡Gracias por el interés!</p>
      <?php else: ?>
        <h2 class="display">Inscripciones cerradas</h2>
        <p>Este formulario ya no acepta respuestas.</p>
      <?php endif; ?>
      <p>Si tienes cualquier duda, escríbenos a <a href="mailto:hola@aquinadieentrena.cc">hola@aquinadieentrena.cc</a>.</p>
    </div>

  <?php else: /* formulario */ ?>

    <?php if ($previa): ?>
      <div class="aviso aviso--previa">Vista previa: puedes ver el formulario tal cual, pero desde aquí no se envía nada.</div>
    <?php elseif ($estado['estado'] === 'espera'): ?>
      <div class="aviso aviso--espera">Las <?= $estado['limite'] ?> plazas están cubiertas. Si te apuntas, entras en la lista de espera y te avisamos si queda un hueco.</div>
    <?php endif; ?>

    <?php if ($avisoGeneral): ?>
      <div class="aviso" role="alert"><?= ane_h($avisoGeneral) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= ane_h($url) ?>">
      <input type="hidden" name="_sello" value="<?= ane_h(ane_form_sello($token)) ?>">
      <div class="trampa" aria-hidden="true">
        <label>No rellenes este campo <input type="text" name="web" tabindex="-1" autocomplete="off"></label>
      </div>

      <?php foreach ($preguntas as $p):
          $err = $errores[$p['id']] ?? ''; ?>
        <div class="tarjeta pregunta<?= $err ? ' pregunta--error' : '' ?>">
          <?php if (in_array($p['tipo'], ['opcion', 'casillas'], true)): ?>
            <p class="enunciado" style="font-weight:600;font-size:16px;margin:0 0 4px"><?= ane_h($p['titulo']) ?><?= $p['obligatoria'] ? ' <span class="ast">*</span>' : '' ?></p>
          <?php else: ?>
            <label class="enunciado" for="<?= ane_h($p['id']) ?>"><?= ane_h($p['titulo']) ?><?= $p['obligatoria'] ? ' <span class="ast">*</span>' : '' ?></label>
          <?php endif; ?>
          <?php if ($p['ayuda'] !== ''): ?><p class="ayuda"><?= ane_form_linea($p['ayuda']) ?></p><?php endif; ?>
          <?= ane_campo($p, $valores[$p['id']] ?? ($p['tipo'] === 'casillas' ? [] : ''), $previa) ?>
          <?php if ($err): ?><p class="error"><?= ane_h($err) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="tarjeta privacidad<?= isset($errores['_acepto']) ? ' pregunta--error' : '' ?>">
        <p><strong>Tus datos.</strong> El responsable es Eduardo Talavera Fernández (Aquí Nadie Entrena).
           Los usamos solo para gestionar tu inscripción a este evento y avisarte si hay cambios.
           Los conservamos hasta un mes después del evento y luego los borramos.
           <?= trim($form['aviso_extra']) !== '' ? ane_h($form['aviso_extra']) . ' ' : '' ?>Puedes pedir
           que los consultemos, corrijamos o borremos en <a href="mailto:hola@aquinadieentrena.cc">hola@aquinadieentrena.cc</a>.
           Más información en la <a href="/privacidad" target="_blank" rel="noopener">política de privacidad</a>.</p>
        <label class="opcion"><input type="checkbox" name="_acepto" value="1"<?= !empty($_POST['_acepto']) ? ' checked' : '' ?><?= $previa ? ' disabled' : '' ?> required>
          <span>He leído la información sobre mis datos y quiero inscribirme <span class="ast">*</span></span></label>
        <?php if (isset($errores['_acepto'])): ?><p class="error"><?= ane_h($errores['_acepto']) ?></p><?php endif; ?>
      </div>

      <div class="acciones-form">
        <button class="enviar" type="submit"<?= $previa ? ' disabled' : '' ?>><?= $estado['estado'] === 'espera' ? 'Apuntarme a la lista de espera' : 'Enviar' ?></button>
      </div>
    </form>

  <?php endif; ?>

<?php endif; ?>

  <p class="pie-form">Formulario de <a href="/">Aquí Nadie Entrena</a> · <a href="/privacidad">Privacidad</a></p>
</main>

<script>
/* Evita el doble envío si alguien pulsa dos veces seguidas */
document.querySelectorAll('form').forEach(function (f) {
  f.addEventListener('submit', function () {
    var b = f.querySelector('.enviar');
    if (b) { setTimeout(function () { b.disabled = true; b.textContent = 'Enviando…'; }, 0); }
  });
});
</script>
</body>
</html>
