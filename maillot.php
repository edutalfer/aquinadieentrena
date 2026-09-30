<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — el mono y sus huecos de patrocinio
   ------------------------------------------------------------
   URL: /maillot  (el .htaccess la reescribe a este fichero)
   Un mono en 3D que se puede girar; cada hueco libre admite
   ofertas. Precios, plazos y ofertas se gestionan en el panel:
   /admin/maillot.php

   Funciona también sin JavaScript (y sin 3D): la lista de huecos
   y el formulario son HTML normal. El 3D va aparte, en
   assets/js/maillot.min.js (fuente en assets/js/maillot/).
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/api/_maillot.php';

header('Cache-Control: no-store, private');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');

$bd = ane_bd();
$quiereJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

$proyectos = ane_maillot_proyectos($bd, true);
$valores = [];
$errores = [];
$aviso = '';
$proyecto = (string) ($_GET['p'] ?? '');
$zonaElegida = (string) ($_GET['zona'] ?? '');

function ane_maillot_json(PDO $bd, array $extra): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($extra + ['proyectos' => ane_maillot_datos_publicos($bd)], JSON_UNESCAPED_UNICODE);
    exit;
}

function ane_maillot_url(string $proyecto, array $extra = []): string
{
    return '/maillot?' . http_build_query(['p' => $proyecto] + $extra);
}

/* ---------- Envío de una oferta ---------- */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $sello = ane_form_comprueba_sello((string) ($_POST['_sello'] ?? ''), 'maillot');
    $zonaElegida = (string) ($_POST['zona'] ?? '');
    $proyecto = (string) ($_POST['proyecto'] ?? '');

    /* Bots: campo trampa, sello falso o envío en menos de 3 s.
       Se les da la respuesta de siempre y no se guarda nada. */
    if (($_POST['web'] ?? '') !== '' || $sello === 'falso' || $sello === 'rapido') {
        if ($quiereJson) {
            ane_maillot_json($bd, ['ok' => true, 'estado' => 'pendiente']);
        }
        header('Location: ' . ane_maillot_url($proyecto, ['ok' => 'pendiente']) . '#oferta', true, 303);
        exit;
    }

    [$valores, $errores] = ane_maillot_valida($_POST, $proyectos);
    $motivo = '';
    if ($sello === 'caducado' && !$errores) {
        $aviso = 'La página llevaba mucho tiempo abierta y las cifras pueden haber cambiado. Revisa tu oferta y vuelve a enviarla.';
    } elseif (!$errores) {
        try {
            $r = ane_maillot_oferta($bd, $valores);
        } catch (Throwable $t) {
            error_log('ANE maillot: ' . $t->getMessage());
            $r = ['ok' => false, 'motivo' => 'error'];
        }
        if ($r['ok']) {
            if ($quiereJson) {
                ane_maillot_json($bd, ['ok' => true, 'estado' => $r['estado'], 'zona' => $valores['zona']]);
            }
            header('Location: ' . ane_maillot_url($proyecto, ['ok' => $r['estado'], 'zona' => $valores['zona']]) . '#oferta', true, 303);
            exit;
        }
        $motivo = $r['motivo'];
        switch ($motivo) {
            case 'bajo':
                $errores['importe'] = 'Ahora mismo la oferta mínima para este hueco es de '
                    . ane_maillot_euros($r['siguiente']) . '.';
                break;
            case 'cerrado':
                $aviso = 'Este hueco ya no admite ofertas.';
                break;
            case 'saturado':
                $aviso = 'Estamos recibiendo muchas ofertas a la vez. Espera un minuto y vuelve a enviarla.';
                break;
            default:
                $aviso = 'Algo ha fallado al guardar tu oferta. Vuelve a intentarlo en un momento.';
        }
    }
    if ($errores && !$aviso) {
        $aviso = count($errores) === 1 ? 'Revisa el campo marcado.' : 'Revisa los ' . count($errores) . ' campos marcados.';
    }
    if ($quiereJson) {
        ane_maillot_json($bd, ['ok' => false, 'motivo' => $motivo, 'errores' => $errores, 'aviso' => $aviso]);
    }
}

/* ---------- Datos para pintar ---------- */

$ajustes = ane_maillot_ajustes($bd);
$datosProyectos = ane_maillot_datos_publicos($bd);
if (!isset($proyectos[$proyecto])) {
    $proyecto = (string) array_key_first($proyectos);
}
$actual = null;
foreach ($datosProyectos as $dp) {
    if ($dp['slug'] === $proyecto) {
        $actual = $dp;
    }
}
$abierto = $actual && $actual['abierto'];
$algunoAbierto = (bool) array_filter($datosProyectos, fn($dp) => $dp['abierto']);
$huecos = [];
foreach ($actual['huecos'] ?? [] as $h) {
    $huecos[$h['zona']] = $h;
}
$ofertables = array_filter($huecos, fn($h) => in_array($h['estado'], ['libre', 'oferta'], true));
if (!isset($ofertables[$zonaElegida])) {
    $zonaElegida = '';
}
$ok = (string) ($_GET['ok'] ?? '');

$ESTADOS = [
    'libre'      => 'Libre',
    'oferta'     => 'Con ofertas',
    'cerrado'    => 'Cerrado',
    'adjudicado' => 'Adjudicado',
];

function ane_maillot_cifra_hueco(array $h): string
{
    switch ($h['estado']) {
        case 'libre':
            return $h['minimo'] > 1 ? 'Oferta mínima: <b>' . ane_maillot_euros($h['minimo']) . '</b>' : 'Sin ofertas todavía';
        case 'oferta':
            return 'Oferta más alta: <b>' . ane_maillot_euros($h['maxima']) . '</b>';
        case 'cerrado':
            return $h['maxima'] !== null ? 'Oferta más alta: <b>' . ane_maillot_euros($h['maxima']) . '</b>' : 'Sin ofertas';
        default:
            return 'Para <b>' . ane_h($h['adjudicado']) . '</b>';
    }
}

function ane_err(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? '<p class="error" id="error-' . $campo . '">' . ane_h($errores[$campo]) . '</p>' : '';
}

function ane_val(array $valores, string $campo): string
{
    return ane_h($valores[$campo] ?? '');
}

$datosJs = [
    'actual'    => $proyecto,
    'proyectos' => $datosProyectos,
];
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>El mono — Aquí Nadie Entrena</title>
<?php if (!$algunoAbierto): ?><meta name="robots" content="noindex"><?php endif; ?>
<meta name="description" content="Gira el mono de Aquí Nadie Entrena, elige un hueco libre y haz tu oferta para poner ahí tu marca.">
<meta name="theme-color" content="#191919">
<meta property="og:title" content="<?= ane_h($ajustes['titulo']) ?>">
<meta property="og:description" content="Elige un hueco del mono y haz tu oferta.">
<meta property="og:type" content="website">
<meta property="og:url" content="https://aquinadieentrena.cc/maillot">
<meta property="og:image" content="https://aquinadieentrena.cc/assets/img/icono-512.png">
<link rel="icon" href="/assets/img/icono-512.png">
<link rel="apple-touch-icon" href="/assets/img/icono-180.png">
<link rel="preload" href="/assets/fonts/archivo-black-italic.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/inter.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/assets/css/estilo.css?v=20260818d">
<style>
  .portada--mono { padding-bottom: 46px; }
  .pasos { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px; background: var(--gris-carbon);
           border: 1px solid var(--gris-carbon); margin: 30px 0 0; counter-reset: paso; }
  .pasos div { background: var(--negro); padding: 16px 18px; color: #C7CAD1; font-size: 15px; }
  .pasos b { display: block; font: 900 italic 22px/1 var(--display); text-transform: uppercase; color: var(--blanco); margin-bottom: 6px; }
  .pasos b::before { counter-increment: paso; content: counter(paso) ". "; color: var(--azul-destello); }
  @media (max-width: 760px) { .pasos { grid-template-columns: 1fr; } }

  /* ---------- Mono + ficha ---------- */
  .taller { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(320px, .75fr); gap: 22px; align-items: start; }
  @media (max-width: 900px) { .taller { grid-template-columns: 1fr; } }
  #escena, #oferta, #mono { scroll-margin-top: 100px; }
  .seccion--mono { padding-top: 40px; }
  .carreras { margin: 0 0 22px; }
  .carreras__titulo { font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--gris-medio); margin: 0 0 10px; }
  .carreras__lista { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
  .carrera { display: block; background: var(--blanco); border: 2px solid var(--negro); padding: 13px 16px 12px; text-decoration: none; color: var(--negro); }
  .carrera b { display: block; font: 900 italic 21px/1 var(--display); text-transform: uppercase; letter-spacing: -.01em; }
  .carrera span { display: block; font-size: 13px; color: var(--gris-carbon); margin-top: 6px; }
  .carrera small { display: inline-block; margin-top: 8px; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--azul-enlace); }
  .carrera:hover { border-color: var(--azul-enlace); }
  .carrera--si { background: var(--negro); color: var(--blanco); }
  .carrera--si span { color: #C7CAD1; }
  .carrera--si small { color: var(--azul-destello); }
  .form-proyecto { margin: 0 0 14px; font-size: 14px; color: var(--gris-carbon); }
  @media (max-width: 600px) { .carreras__lista { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .carrera { padding: 10px 11px; } .carrera b { font-size: 13.5px; } .carrera span { font-size: 12px; } .carrera small { font-size: 10px; } }
  .escena { position: relative; background: var(--blanco); border: 2px solid var(--negro); }
  .escena__lienzo { background: radial-gradient(ellipse at 50% 38%, #FAFBFC 0%, #EDF0F4 55%, #DCE1E8 100%); height: min(78vh, 680px); min-height: 420px; touch-action: pan-y; cursor: grab; user-select: none; }
  .escena__lienzo:active { cursor: grabbing; }
  @media (max-width: 600px) { .escena__lienzo { height: 480px; min-height: 0; } }
  .escena__lienzo canvas { display: block; width: 100%; height: 100%; }
  .escena__sin3d { display: none; padding: 40px 24px; text-align: center; color: var(--gris-medio); }
  .escena--sin3d .escena__lienzo { display: none; }
  .escena--sin3d .escena__sin3d { display: block; }
  .escena--sin3d .mandos { display: none; }
  .mandos { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between;
            padding: 12px 14px; border-top: 1px solid #E1E3E7; }
  .mandos__botones { display: flex; gap: 6px; flex-wrap: wrap; }
  .mando { font: 700 12px/1 var(--texto); letter-spacing: .08em; text-transform: uppercase; cursor: pointer;
           background: var(--blanco); color: var(--negro); border: 2px solid var(--negro); padding: 9px 12px; }
  .mando:hover, .mando[aria-pressed="true"] { background: var(--negro); color: var(--blanco); }
  .leyenda { display: flex; flex-wrap: wrap; gap: 12px; font-size: 13px; color: var(--gris-carbon); margin: 0; padding: 0; list-style: none; }
  .leyenda li { display: flex; align-items: center; gap: 6px; }
  .leyenda i { width: 14px; height: 14px; display: inline-block; border: 1px solid var(--negro); }
  .pista { position: absolute; left: 14px; top: 12px; font-size: 13px; font-weight: 600; color: var(--gris-medio);
           pointer-events: none; transition: opacity .4s; }

  .ficha { background: var(--blanco); border: 2px solid var(--negro); padding: 24px 24px 26px; }
  @media (min-width: 901px) { .ficha { position: sticky; top: 96px; } }
  .ficha__zona { font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--azul-enlace); margin: 0 0 8px; }
  .ficha h2 { font-size: clamp(28px, 3.4vw, 38px); margin: 0 0 10px; }
  .ficha__desc { margin: 0 0 16px; color: var(--gris-carbon); }
  .ficha__cifras { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: #E1E3E7; border: 1px solid #E1E3E7; margin: 0 0 18px; }
  .ficha__cifras div { background: var(--gris-humo); padding: 12px 14px; }
  .ficha__cifras b { display: block; font: 900 italic 26px/1 var(--display); letter-spacing: -.02em; }
  .ficha__cifras span { display: block; margin-top: 5px; font-size: 12px; color: var(--gris-medio); }
  .ficha__vacia { color: var(--gris-medio); margin: 0; }

  .campo { margin: 0 0 14px; }
  .campo label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 5px; }
  .campo .opc { font-weight: 400; color: var(--gris-medio); }
  .campo input, .campo select, .campo textarea { width: 100%; font: inherit; font-size: 16px; color: var(--negro);
    border: 1px solid #C9CDD3; background: var(--blanco); padding: 10px 11px; border-radius: 0; }
  .campo textarea { resize: vertical; }
  .campo input:focus, .campo select:focus, .campo textarea:focus { outline: 2px solid var(--azul); outline-offset: -1px; border-color: var(--azul); }
  .importe { position: relative; display: block; }
  .campo--importe input { font: 900 italic 26px/1 var(--display); padding-right: 40px; }
  .campo--importe .euro { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font: 900 italic 24px/1 var(--display); color: var(--gris-medio); }
  .campo .ayuda { font-size: 13px; color: var(--gris-medio); margin: 5px 0 0; }
  .campo--error input, .campo--error select { border-color: var(--negro); border-width: 2px; }
  .error { font-size: 14px; font-weight: 700; color: var(--negro); margin: 6px 0 0; }
  .error::before { content: "⚠ "; }
  .dos { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12px; }
  @media (max-width: 480px) { .dos { grid-template-columns: 1fr; } }
  .acepto { display: flex; gap: 10px; align-items: flex-start; font-size: 14px; margin: 4px 0 6px; cursor: pointer; }
  .acepto input { width: 20px; height: 20px; accent-color: var(--azul-enlace); margin: 1px 0 0; flex: none; }
  .datos-nota { font-size: 13px; color: var(--gris-medio); margin: 0 0 16px; }
  .datos-nota a, .acepto a { color: var(--azul-enlace); }
  .enviar { width: 100%; font: 700 16px/1 var(--texto); letter-spacing: .06em; text-transform: uppercase; cursor: pointer;
            background: var(--azul-enlace); color: var(--blanco); border: 2px solid var(--azul-enlace); padding: 16px 20px; }
  .enviar:hover { background: var(--negro); border-color: var(--negro); }
  .enviar:disabled { background: #C9CDD3; border-color: #C9CDD3; cursor: not-allowed; }
  .trampa { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
  .aviso { background: var(--negro); color: var(--blanco); padding: 14px 16px; margin: 0 0 16px; font-weight: 600; font-size: 15px; }
  .aviso--ok { background: var(--azul-enlace); }
  .aviso--info { background: var(--azul-destello); color: var(--negro); }

  /* ---------- Lista de huecos ---------- */
  .huecos { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 14px; margin: 0; padding: 0; list-style: none; }
  .hueco { background: var(--blanco); border: 2px solid var(--negro); padding: 18px 18px 16px; display: flex; flex-direction: column; gap: 8px; }
  .hueco h3 { font-size: 24px; margin: 0; }
  .hueco p { margin: 0; font-size: 14px; color: var(--gris-carbon); }
  .hueco__cifra { font-size: 15px !important; color: var(--negro) !important; }
  .hueco__cifra b { font-family: var(--display); font-style: italic; font-weight: 900; font-size: 20px; }
  .hueco .mando { align-self: flex-start; margin-top: auto; text-decoration: none; }
  .hueco--elegido { outline: 4px solid var(--azul); outline-offset: -2px; }
  .chip { align-self: flex-start; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; padding: 5px 8px; }
  .chip--libre { background: var(--azul-destello); color: var(--negro); }
  .chip--oferta { background: var(--azul-enlace); color: var(--blanco); }
  .chip--adjudicado { background: var(--negro); color: var(--blanco); }
  .chip--cerrado { background: #E1E3E7; color: var(--negro); }

  .condiciones { max-width: 760px; font-size: 16px; }
  .condiciones ul { padding-left: 22px; margin: 0 0 12px; }
  .condiciones li { margin-bottom: 8px; }
  .condiciones p { margin: 0 0 12px; }
  .condiciones a { color: var(--azul-enlace); }
  .intro-mono p { margin: 0 0 12px; }
</style>
</head>
<body>

<header class="cabecera">
  <div class="envoltorio cabecera__fila">
    <a class="logo" href="/"><img src="/assets/img/logo-negativo.png" alt="Aquí Nadie Entrena"></a>
    <nav class="menu">
      <a href="/#episodios">Episodios</a>
      <a href="/proyectos">Proyectos</a>
      <a href="/#hosts">Quiénes somos</a>
      <a href="/#newsletter">Newsletter</a>
      <a href="/#cepo">Bici o cepo</a>
      <a href="/#marcas">Marcas</a>
    </nav>
  </div>
</header>

<main>

  <section class="portada portada--mono">
    <div class="envoltorio">
      <p class="portada__eyebrow">Patrocinio · El mono</p>
      <h1 class="display"><?= ane_h($ajustes['titulo']) ?></h1>
      <div class="portada__sub intro-mono"><?= ane_form_texto($ajustes['intro']) ?></div>
      <div class="pasos">
        <div><b>Elige carrera y hueco</b>Gira el mono y pincha en cualquier hueco libre.</div>
        <div><b>Haz tu oferta</b>Ves la oferta más alta y cuánto tienes que poner para superarla.</div>
        <div><b>Hablamos</b>Al cierre escribimos a la oferta más alta de cada hueco.</div>
      </div>
    </div>
  </section>

  <section class="seccion seccion--humo seccion--mono" id="mono">
    <div class="envoltorio">
      <nav class="carreras" aria-label="Proyecto">
        <p class="carreras__titulo">Elige el proyecto</p>
        <div class="carreras__lista">
          <?php foreach ($datosProyectos as $dp): ?>
            <a class="carrera<?= $dp['slug'] === $proyecto ? ' carrera--si' : '' ?>" href="/maillot?p=<?= rawurlencode($dp['slug']) ?>#mono"
               data-proyecto="<?= ane_h($dp['slug']) ?>"<?= $dp['slug'] === $proyecto ? ' aria-current="true"' : '' ?>>
              <b><?= ane_h($dp['nombre']) ?></b>
              <span><?= ane_h($dp['detalle']) ?></span>
              <small><?= $dp['abierto'] ? 'Ofertas abiertas' : 'Ofertas cerradas' ?></small>
            </a>
          <?php endforeach; ?>
        </div>
      </nav>
      <div class="taller">

        <div class="escena" id="escena">
          <div class="escena__lienzo" id="lienzo" aria-label="Mono de Aquí Nadie Entrena en 3D. Arrastra para girarlo y pincha en un hueco." role="img">
            <p class="pista" id="pista">Arrastra para girar · Pincha en un hueco</p>
          </div>
          <div class="escena__sin3d" id="sin3d">
            <p>Tu navegador no puede mostrar el mono en 3D. Tienes todos los huecos en la lista de abajo.</p>
          </div>
          <div class="mandos">
            <div class="mandos__botones">
              <button class="mando" type="button" data-vista="0">Delante</button>
              <button class="mando" type="button" data-vista="180">Detrás</button>
              <button class="mando" type="button" data-vista="-90">Izquierda</button>
              <button class="mando" type="button" data-vista="90">Derecha</button>
              <button class="mando" type="button" id="girar" aria-pressed="true">Girar solo</button>
            </div>
            <ul class="leyenda">
              <li><i style="background:#B2C8F0"></i> Libre</li>
              <li><i style="background:#3F77DA"></i> Con ofertas</li>
              <li><i style="background:#191919"></i> Adjudicado</li>
            </ul>
          </div>
        </div>

        <div class="ficha" id="oferta" tabindex="-1">
          <?php if ($ok === 'valida' || $ok === 'pendiente'): ?>
            <div class="aviso aviso--ok" role="status">
              <?= $ok === 'valida'
                  ? '¡Oferta recibida! Ya es la más alta de este hueco. Al cierre te escribimos.'
                  : '¡Oferta recibida! La revisamos y, en cuanto la validemos, aparece en el mono. Al cierre te escribimos.' ?>
            </div>
          <?php endif; ?>

          <div id="ficha-hueco">
            <p class="ficha__zona">Elige un hueco</p>
            <h2 class="display">¿Dónde va tu marca?</h2>
            <p class="ficha__desc">Pincha en un hueco del mono o elígelo en el desplegable.</p>
          </div>

          <div class="aviso aviso--info" id="aviso-cerrado"<?= $abierto ? ' hidden' : '' ?>>Este proyecto no admite ofertas ahora mismo. Si te interesa, escríbenos a
            <a href="mailto:hola@aquinadieentrena.cc?subject=Hueco%20en%20el%20mono" style="color:inherit">hola@aquinadieentrena.cc</a>.</div>

          <?php if ($algunoAbierto): ?>

          <?php if ($aviso): ?><div class="aviso" role="alert" id="aviso-form"><?= ane_h($aviso) ?></div><?php endif; ?>

          <form method="post" action="/maillot#oferta" id="form-oferta" novalidate<?= $abierto ? '' : ' hidden' ?>>
            <input type="hidden" name="_sello" value="<?= ane_h(ane_form_sello('maillot')) ?>">
            <input type="hidden" name="proyecto" id="proyecto" value="<?= ane_h($proyecto) ?>">
            <p class="form-proyecto">Oferta para <b id="form-proyecto-nombre"><?= ane_h($actual['nombre'] ?? '') ?></b></p>
            <div class="trampa" aria-hidden="true">
              <label>No rellenes este campo <input type="text" name="web" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="campo<?= isset($errores['zona']) ? ' campo--error' : '' ?>">
              <label for="zona">Hueco</label>
              <select id="zona" name="zona" required>
                <option value="">Elige un hueco</option>
                <?php foreach ($ofertables as $h): ?>
                  <option value="<?= ane_h($h['zona']) ?>"<?= $h['zona'] === $zonaElegida ? ' selected' : '' ?>
                    ><?= ane_h($h['nombre']) ?><?= $h['siguiente'] > 1 ? ' — desde ' . ane_maillot_euros($h['siguiente']) : '' ?></option>
                <?php endforeach; ?>
              </select>
              <?= ane_err($errores, 'zona') ?>
            </div>

            <div class="campo campo--importe<?= isset($errores['importe']) ? ' campo--error' : '' ?>">
              <label for="importe">Tu oferta</label>
              <span class="importe"><input id="importe" name="importe" type="text" inputmode="numeric" autocomplete="off" maxlength="12"
                     value="<?= ane_val($valores, 'importe') ?>" placeholder="0" required aria-describedby="ayuda-importe"><span class="euro" aria-hidden="true">€</span></span>
              <p class="ayuda" id="ayuda-importe">Euros, sin IVA. Elige un hueco para ver la oferta mínima.</p>
              <?= ane_err($errores, 'importe') ?>
            </div>

            <div class="campo<?= isset($errores['empresa']) ? ' campo--error' : '' ?>">
              <label for="empresa">Empresa o marca</label>
              <input id="empresa" name="empresa" type="text" maxlength="120" autocomplete="organization" value="<?= ane_val($valores, 'empresa') ?>" required>
              <?= ane_err($errores, 'empresa') ?>
            </div>

            <div class="campo<?= isset($errores['correo']) ? ' campo--error' : '' ?>">
              <label for="correo">Correo de contacto</label>
              <input id="correo" name="correo" type="email" maxlength="160" autocomplete="email" value="<?= ane_val($valores, 'correo') ?>" placeholder="tu@empresa.com" required>
              <?= ane_err($errores, 'correo') ?>
            </div>

            <div class="dos">
              <div class="campo">
                <label for="contacto">Tu nombre <span class="opc">(opcional)</span></label>
                <input id="contacto" name="contacto" type="text" maxlength="120" autocomplete="name" value="<?= ane_val($valores, 'contacto') ?>">
              </div>
              <div class="campo<?= isset($errores['telefono']) ? ' campo--error' : '' ?>">
                <label for="telefono">Teléfono <span class="opc">(opcional)</span></label>
                <input id="telefono" name="telefono" type="tel" maxlength="30" autocomplete="tel" value="<?= ane_val($valores, 'telefono') ?>">
                <?= ane_err($errores, 'telefono') ?>
              </div>
            </div>

            <div class="campo">
              <label for="mensaje">Algo que quieras contarnos <span class="opc">(opcional)</span></label>
              <textarea id="mensaje" name="mensaje" rows="3" maxlength="1000"><?= ane_val($valores, 'mensaje') ?></textarea>
            </div>

            <label class="acepto<?= isset($errores['_acepto']) ? ' campo--error' : '' ?>">
              <input type="checkbox" name="_acepto" value="1"<?= !empty($_POST['_acepto']) ? ' checked' : '' ?> required>
              <span>He leído las <a href="#condiciones">condiciones</a> y la información sobre mis datos</span>
            </label>
            <?= ane_err($errores, '_acepto') ?>
            <p class="datos-nota">Tus datos los trata Eduardo Talavera Fernández (Aquí Nadie Entrena) solo para
              gestionar esta oferta y contactarte sobre ella. En la web solo sale el importe, nunca quién lo ofrece.
              Los borramos al cerrar el patrocinio de ese proyecto. Más en la <a href="/privacidad#patrocinio">política de privacidad</a>.</p>

            <button class="enviar" type="submit">Enviar mi oferta</button>
          </form>

          <?php endif; ?>
        </div>

      </div>
    </div>
  </section>

  <section class="seccion">
    <div class="envoltorio">
      <h2 class="display seccion__titulo" style="font-size:clamp(32px,5vw,54px);margin:0 0 6px">Todos los huecos</h2>
      <p style="margin:0 0 24px;color:var(--gris-carbon)">En <b id="lista-proyecto"><?= ane_h($actual['nombre'] ?? '') ?></b></p>
      <?php if (!$huecos): ?>
        <p>Estamos preparando los huecos del mono. Vuelve en unos días.</p>
      <?php else: ?>
      <ul class="huecos" id="lista-huecos">
        <?php foreach ($huecos as $h): ?>
          <li class="hueco<?= $h['zona'] === $zonaElegida ? ' hueco--elegido' : '' ?>" id="hueco-<?= ane_h($h['zona']) ?>" data-zona="<?= ane_h($h['zona']) ?>">
            <span class="chip chip--<?= $h['estado'] ?>"><?= $ESTADOS[$h['estado']] ?></span>
            <h3 class="display"><?= ane_h($h['nombre']) ?></h3>
            <p><?= ane_h($h['descripcion']) ?></p>
            <p class="hueco__cifra"><?= ane_maillot_cifra_hueco($h) ?></p>
            <?php if ($h['cierre'] && in_array($h['estado'], ['libre', 'oferta'], true)): ?>
              <p>Cierra el <?= ane_h($h['cierre']) ?></p>
            <?php endif; ?>
            <?php if ($abierto && in_array($h['estado'], ['libre', 'oferta'], true)): ?>
              <a class="mando" href="<?= ane_h(ane_maillot_url($proyecto, ['zona' => $h['zona']])) ?>#oferta" data-elegir="<?= ane_h($h['zona']) ?>">Hacer oferta</a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </section>

  <section class="seccion seccion--humo" id="condiciones">
    <div class="envoltorio">
      <h2 class="display" style="font-size:clamp(32px,5vw,54px);margin:0 0 20px">Condiciones</h2>
      <div class="condiciones"><?= ane_form_texto($ajustes['condiciones']) ?>
        <p>¿Dudas? Escríbenos a <a href="mailto:hola@aquinadieentrena.cc?subject=Hueco%20en%20el%20mono">hola@aquinadieentrena.cc</a>.</p>
      </div>
    </div>
  </section>

</main>

<footer class="pie">
  <div class="envoltorio">
    <p class="pie__legal">© 2026 Aquí Nadie Entrena · <a href="/aviso-legal">Aviso legal</a> · <a href="/privacidad">Privacidad</a> · <a href="/cookies">Cookies</a></p>
  </div>
</footer>

<script type="application/json" id="datos-maillot"><?= json_encode($datosJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script type="module" src="/assets/js/maillot.min.js?v=20261001d"></script>
<script src="/assets/js/stats.js?v=20260930"></script>
</body>
</html>
