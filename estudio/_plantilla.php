<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio · sesión y plantilla
   ------------------------------------------------------------
   Entrada con usuario y contraseña propios (formulario, no la
   ventana del navegador): aquí hay varias personas con permisos
   distintos, y el editor de vídeo no debe oler el resto del panel.

   La sesión es una cookie firmada (HMAC con ~/datos/secreto),
   no una sesión de PHP: en hosting compartido el recolector de
   sesiones las borra a los 24 minutos y esto tiene que aguantar
   semanas. Dentro van el id, la caducidad y una huella del hash
   de la contraseña → cambiar la contraseña cierra la sesión en
   todos los sitios, y desactivar al usuario también.

   Esto no exige haber entrado (lo necesita entrar.php). La puerta
   está en _comun.php, que incluye este fichero.

   No se sirve por web: el .htaccess bloquea `_*.php`.
   ============================================================ */

declare(strict_types=1);
require_once dirname(__DIR__) . '/api/_estudio.php';

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

const ANE_POD_COOKIE = 'ane_estudio';
const ANE_POD_DIAS = 30;

function ane_pod_https(): bool
{
    return ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? 'off') !== 'off';
}

function ane_pod_huella(array $u): string
{
    return substr(hash_hmac('sha256', (string) $u['hash'] . '|' . (int) $u['activo'], ane_secreto()), 0, 16);
}

function ane_pod_abre_sesion(array $u): void
{
    $exp = time() + ANE_POD_DIAS * 86400;
    $cuerpo = $u['id'] . '.' . $exp . '.' . ane_pod_huella($u);
    $valor = $cuerpo . '.' . substr(hash_hmac('sha256', $cuerpo, ane_secreto()), 0, 32);
    setcookie(ANE_POD_COOKIE, $valor, [
        'expires'  => $exp,
        'path'     => '/estudio',
        'secure'   => ane_pod_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function ane_pod_cierra_sesion(): void
{
    setcookie(ANE_POD_COOKIE, '', [
        'expires' => time() - 3600, 'path' => '/estudio',
        'secure' => ane_pod_https(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

/* Devuelve el usuario de la cookie, o null si no vale */
function ane_pod_quien(PDO $bd): ?array
{
    $c = (string) ($_COOKIE[ANE_POD_COOKIE] ?? '');
    if (!preg_match('/^(\d+)\.(\d{9,11})\.([0-9a-f]{16})\.([0-9a-f]{32})$/', $c, $m)) {
        return null;
    }
    $cuerpo = $m[1] . '.' . $m[2] . '.' . $m[3];
    if (!hash_equals(substr(hash_hmac('sha256', $cuerpo, ane_secreto()), 0, 32), $m[4])) {
        return null;
    }
    if ((int) $m[2] < time()) {
        return null;
    }
    $u = ane_pod_por_id($bd, (int) $m[1]);
    if (!$u || !(int) $u['activo'] || !hash_equals(ane_pod_huella($u), $m[3])) {
        return null;
    }
    return $u;
}

/* ---------- Plantilla ---------- */

function ane_pod_cabeza(string $titulo, string $clase = ''): void
{
    ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= ane_h($titulo) ?> · Estudio ANE</title>
<link rel="icon" href="/assets/img/favicon.png">
<?php ane_pod_estilo(); ?>
</head>
<body<?= $clase !== '' ? ' class="' . ane_h($clase) . '"' : '' ?>>
<?php
}

function ane_pod_pie(): void
{
    echo "</body>\n</html>\n";
}

function ane_pod_estilo(): void
{
    ?>
<style>
  @font-face { font-family: "Archivo"; src: url("/assets/fonts/archivo-black-italic.woff2") format("woff2");
               font-weight: 900; font-style: italic; font-display: swap; }
  @font-face { font-family: "Inter"; src: url("/assets/fonts/inter.woff2") format("woff2");
               font-weight: 400 700; font-display: swap; }
  :root { --azul: #3F77DA; --negro: #191919; --enlace: #2A5CB8; --destello: #B2C8F0; --profundo: #264783;
          --gris: #6E747F; --humo: #F2F3F5; --linea: #E1E3E7; --verde: #1F7A4D; --ambar: #9A6400; --rojo: #B3261E; }
  * { box-sizing: border-box; }
  body { margin: 0; font: 15px/1.55 "Inter", system-ui, sans-serif; color: var(--negro); background: var(--humo); }
  .display { font-family: "Archivo", sans-serif; font-weight: 900; font-style: italic;
             text-transform: uppercase; letter-spacing: -0.02em; line-height: .9; }
  .caja { max-width: 1180px; margin: 0 auto; padding: 0 20px; }
  a { color: var(--enlace); }

  /* ---- Cabecera ---- */
  header { background: var(--negro); color: #fff; padding: 20px 0 0; }
  header .fila { display: flex; flex-wrap: wrap; gap: 12px; align-items: baseline; justify-content: space-between; }
  header h1 { margin: 0; font-size: 28px; }
  header h1 em { color: var(--azul); font-style: italic; }
  header .quien { font-size: 12px; color: #9AA0AC; letter-spacing: .06em; text-transform: uppercase; font-weight: 700; }
  header .quien b { color: #fff; }
  header .quien a { color: #9AA0AC; margin-left: 10px; }
  .pestanas { display: flex; gap: 2px; margin-top: 16px; flex-wrap: wrap; }
  .pestanas a { color: #C7CAD1; text-decoration: none; font-weight: 700; font-size: 13px; letter-spacing: .07em;
                text-transform: uppercase; padding: 11px 16px 12px; border-bottom: 3px solid transparent; }
  .pestanas a:hover { color: #fff; }
  .pestanas a.si { color: #fff; border-bottom-color: var(--azul); }

  main.caja { padding: 28px 20px 70px; }
  h2 { font-size: 21px; margin: 0 0 4px; }
  h3 { font-size: 16px; margin: 22px 0 8px; }
  p.nota { margin: 0 0 14px; color: var(--gris); font-size: 13px; }
  section { background: #fff; border: 1px solid var(--linea); padding: 18px 20px; margin-bottom: 18px; }
  .barra { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 16px; }
  .cifras { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 20px; }
  .cifra { background: #fff; border: 2px solid var(--negro); padding: 14px 16px; }
  .cifra b { display: block; font: 900 italic 34px/1 "Archivo", sans-serif; }
  .cifra span { font-size: 11px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; color: var(--gris); }
  .dos { display: grid; grid-template-columns: 2fr 1fr; gap: 18px; align-items: start; }
  @media (max-width: 900px) { .dos { grid-template-columns: 1fr; } }

  /* ---- Formularios ---- */
  label { display: block; font-size: 11px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase;
          color: var(--gris); margin: 0 0 4px; }
  input[type=text], input[type=url], input[type=email], input[type=password], input[type=number],
  input[type=date], input[type=datetime-local], select, textarea {
          width: 100%; font: inherit; color: inherit; padding: 9px 11px; border: 1px solid var(--linea);
          background: #fff; border-radius: 0; }
  textarea { resize: vertical; min-height: 90px; line-height: 1.6; }
  input:focus, select:focus, textarea:focus { outline: 2px solid var(--azul); outline-offset: -1px; border-color: var(--azul); }
  .campo { margin-bottom: 14px; }
  .campos { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; }
  .cas { display: flex; gap: 8px; align-items: flex-start; font-size: 14px; }
  .cas input { width: auto; margin-top: 3px; }
  .cas label { text-transform: none; letter-spacing: 0; font-size: 14px; font-weight: 400; color: var(--negro); margin: 0; }

  .btn { display: inline-block; font: inherit; font-weight: 700; font-size: 13px; letter-spacing: .06em;
         text-transform: uppercase; padding: 10px 16px; border: 2px solid var(--negro); background: var(--negro);
         color: #fff; cursor: pointer; text-decoration: none; }
  .btn:hover { background: var(--profundo); border-color: var(--profundo); }
  .btn.claro { background: #fff; color: var(--negro); }
  .btn.claro:hover { background: var(--humo); color: var(--negro); }
  .btn.azul { background: var(--azul); border-color: var(--azul); }
  .btn.azul:hover { background: var(--profundo); border-color: var(--profundo); }
  .btn.pequeno { padding: 6px 10px; font-size: 11px; border-width: 1px; }
  .btn.peligro { background: #fff; color: var(--rojo); border-color: var(--rojo); }
  .btn.peligro:hover { background: var(--rojo); color: #fff; }
  .acciones { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
  form.enlinea { display: inline; }

  /* ---- Tablas y listas ---- */
  table { width: 100%; border-collapse: collapse; font-size: 14px; }
  th { text-align: left; font-size: 11px; letter-spacing: .09em; text-transform: uppercase; color: var(--gris);
       border-bottom: 2px solid var(--negro); padding: 6px 8px 6px 0; }
  td { border-bottom: 1px solid var(--linea); padding: 9px 8px 9px 0; vertical-align: top; }
  td.n, th.n { text-align: right; font-variant-numeric: tabular-nums; }
  tr.hecho td { color: var(--gris); }
  .vacio { color: var(--gris); font-style: italic; padding: 10px 0; }
  .dom { color: var(--gris); font-size: 12px; }

  /* ---- Chips de estado ---- */
  .chip { display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase;
          padding: 3px 8px; border: 1px solid var(--linea); background: var(--humo); color: var(--gris); white-space: nowrap; }
  .chip.idea { }
  .chip.planificado { border-color: var(--azul); color: var(--profundo); background: #EDF2FC; }
  .chip.grabado, .chip.negociando { border-color: var(--ambar); color: var(--ambar); background: #FDF6E7; }
  .chip.editando { border-color: var(--ambar); color: var(--ambar); background: #FDF6E7; }
  .chip.publicado, .chip.confirmado, .chip.facturado, .chip.abierto {
          border-color: var(--verde); color: var(--verde); background: #EAF5EF; }
  .chip.descartado, .chip.cerrado { border-color: var(--linea); color: var(--gris); text-decoration: line-through; }
  .chip.interno { border-color: var(--rojo); color: var(--rojo); background: #FBECEB; }

  /* ---- Tarjetas de episodio ---- */
  .tarjetas { display: grid; gap: 12px; }
  .tarjeta { background: #fff; border: 1px solid var(--linea); border-left: 4px solid var(--azul);
             padding: 14px 16px; display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: start; }
  .tarjeta.sinfecha { border-left-color: var(--linea); }
  .tarjeta h3 { margin: 2px 0 4px; font-size: 18px; }
  .tarjeta h3 a { text-decoration: none; color: var(--negro); }
  .tarjeta h3 a:hover { color: var(--enlace); }
  .tarjeta .meta { font-size: 13px; color: var(--gris); }
  .tarjeta .meta b { color: var(--negro); font-weight: 700; }
  .tarjeta .der { text-align: right; display: grid; gap: 6px; justify-items: end; }

  /* ---- Temas del guión en el editor ---- */
  .tema { border: 1px solid var(--linea); border-left: 4px solid var(--azul); padding: 14px 16px; margin-bottom: 12px; }
  .tema.interno { border-left-color: var(--rojo); background: #FDFBFB; }
  .tema.cierre { border-left-color: var(--negro); background: #FAFAFB; }
  .tema.hecho { border-left-color: var(--verde); }
  .tema h3 { margin: 0; font-size: 17px; }
  .subir { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 10px;
           border-top: 1px dashed var(--linea); padding-top: 10px; }
  .subir input[type=file] { width: auto; flex: 1 1 230px; font-size: 13px; padding: 6px; }
  .subir input[type=text] { width: auto; flex: 1 1 180px; }

  /* ---- Un patrocinio dentro del guión: rojo, para que no se confunda con un tema ---- */
  .tema.patrocinio { border-left-color: var(--rojo); background: #FDF7F6; }
  .tema.patrocinio.hecho { border-left-color: var(--verde); background: #F6FBF8; }
  .origen { margin: 0 0 8px; font-size: 13px; background: #fff; border: 1px solid #E9B8B4; padding: 6px 10px; }
  .cuerpo.leido { background: #fff; border: 1px solid var(--linea); padding: 8px 12px; margin-bottom: 10px; }
  .cuerpo.leido p:last-child { margin-bottom: 0; }
  .contratos { list-style: none; margin: 0 0 8px; padding: 0; display: grid; gap: 6px; }
  .contratos li { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between;
                  border: 1px solid var(--linea); background: #fff; padding: 7px 10px; font-size: 14px; }
  .contrato-acuerdo { margin-top: 12px; border-top: 1px dashed var(--linea); padding-top: 10px; }
  .contrato-acuerdo > label { margin-bottom: 6px; }
  .en-guion { margin-top: 10px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
  .en-guion a.chip { text-decoration: none; }
  details.firmar { margin-top: 14px; }
  details.firmar > summary { list-style: none; display: inline-block; }
  details.firmar > summary::-webkit-details-marker { display: none; }
  details.firmar[open] > summary { background: var(--profundo); border-color: var(--profundo); }
  .mover { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 10px;
           border-top: 1px dashed var(--linea); padding-top: 10px; }
  .mover label { margin: 0; }
  .mover select { width: auto; flex: 1 1 220px; min-width: 0; max-width: 100%; }

  /* ---- Añadir tema / añadir patrocinio, uno al lado del otro ---- */
  .anadir { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 18px; align-items: start; }
  .anadir > div { border: 1px dashed var(--linea); padding: 14px 16px; }
  .anadir h3 { margin-top: 0; }
  .patrocinio-nuevo { border-color: #E9B8B4 !important; background: #FDF7F6; }
  @media (max-width: 900px) { .anadir { grid-template-columns: 1fr; } }

  /* ---- Enlaces de un tema, en el editor ---- */
  .lista-enlaces { margin-top: 12px; border-top: 1px dashed var(--linea); padding-top: 10px; }
  .lista-enlaces > label { margin-bottom: 6px; }
  .fila-enlace { display: flex; gap: 6px; align-items: center; margin-bottom: 6px; }
  .fila-enlace input[type=text] { flex: 1 1 200px; }
  .fila-enlace input[type=url] { flex: 2 1 280px; }
  .escondida { display: none; }
  .nuevos-enlaces { display: grid; gap: 6px; margin-top: 8px; border: 1px dashed var(--linea);
                    background: #FAFBFC; padding: 10px; }
  .nuevos-enlaces label { margin: 0; }
  .nuevos-enlaces textarea { min-height: 70px; }
  .nuevos-enlaces .btn { justify-self: start; }
  .subir label.suelto { flex: 1 1 100%; margin: 0; }

  /* ---- Una foto y su descripción, juntas ---- */
  .pareja { display: grid; grid-template-columns: 260px 1fr; gap: 16px; align-items: start;
            border: 1px solid var(--linea); background: var(--humo); padding: 12px; margin-bottom: 12px; }
  .pareja figure { margin: 0; }
  .pareja img { display: block; width: 100%; height: 170px; object-fit: cover; background: var(--linea);
                border: 1px solid var(--linea); }
  .pareja figcaption { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
  .pareja textarea { min-height: 120px; }
  .parejas { margin-top: 12px; }
  .pareja.lectura { border: 0; background: none; padding: 0; margin-bottom: 18px; }
  .pareja.lectura img { height: auto; max-height: 420px; object-fit: contain; background: #fff; }
  .pareja.lectura .cuerpo p { margin: 0 0 8px; }
  .pareja .descarga, .pareja.lectura .descarga { font-size: 12px; font-weight: 700; letter-spacing: .04em;
            text-transform: uppercase; }

  /* ---- Archivos para descargar ---- */
  .archivos { list-style: none; margin: 12px 0 0; padding: 0; display: grid; gap: 8px; }
  .archivos li { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-size: 14px;
                 border: 1px solid var(--linea); background: var(--humo); padding: 8px 10px; }
  .archivos li input[type=text] { flex: 1 1 200px; font-size: 13px; padding: 5px 7px; }
  .archivos.lectura li { border: 0; background: none; padding: 0; }

  /* ---- Lista de episodios: el que toca, resaltado; los pasados, plegados ---- */
  .tarjeta.siguiente { border-left-width: 6px; border-color: var(--azul); background: #EDF2FC;
                       box-shadow: 0 0 0 2px var(--azul) inset; }
  .tarjeta .meta b.sale { color: var(--enlace); }
  .tarjeta .toca { margin: 0 0 4px; font: 900 italic 13px/1 "Archivo", sans-serif; text-transform: uppercase;
                   letter-spacing: .02em; color: var(--azul); }
  .tarjeta.grabado { border-left-color: var(--ambar); }
  details.pasados { margin-top: 26px; }
  details.pasados > summary { cursor: pointer; list-style: none; }
  details.pasados > summary::-webkit-details-marker { display: none; }
  details.pasados > summary h3 { display: inline; }
  details.pasados > summary::before { content: '▸ '; color: var(--gris); }
  details.pasados[open] > summary::before { content: '▾ '; }
  details.pasados .tarjetas { margin-top: 12px; }
  details.pasados .tarjeta { opacity: .8; border-left-color: var(--linea); }

  /* ---- Guión ---- */
  .guion { background: #fff; border: 1px solid var(--linea); padding: 26px 30px; }
  .guion .cab { border-bottom: 3px solid var(--negro); padding-bottom: 14px; margin-bottom: 22px; }
  .guion .cab h1 { font-size: 30px; margin: 0 0 6px; }
  .guion .cab .numero { margin: 0 0 4px; font-size: 13px; letter-spacing: .08em; color: var(--azul); }
  .bloque.cierre { border-top: 3px solid var(--negro); margin-top: 6px; }
  .bloque.patro { background: #FDF7F6; border-left: 4px solid var(--rojo); padding-left: 12px; margin-left: -16px; }
  .bloque.patro.hecho { background: #F6FBF8; border-left-color: var(--verde); }
  .bloque.patro .min { color: var(--rojo); font-size: 13px; letter-spacing: .04em; }
  .bloque.patro.hecho .min { color: var(--verde); }
  .bloque.cierre .min { color: var(--negro); }
  .bloque { border-bottom: 1px solid var(--linea); padding: 16px 0; display: grid;
            grid-template-columns: 62px 1fr; gap: 18px; }
  .bloque:last-child { border-bottom: 0; }
  .bloque .min { font: 900 italic 20px/1 "Archivo", sans-serif; color: var(--azul); padding-top: 2px; }
  .bloque h3 { margin: 0 0 6px; font-size: 18px; }
  .bloque .cuerpo p { margin: 0 0 8px; }
  .bloque .cuerpo ul { margin: 0 0 8px; padding-left: 20px; }
  .bloque.esinterno { background: #FBFBFC; }
  .enlaces { list-style: none; margin: 10px 0 0; padding: 0; display: grid; gap: 6px; }
  .enlaces li { font-size: 14px; display: flex; gap: 8px; align-items: baseline; }
  .enlaces .dom { color: var(--gris); font-size: 12px; }
  .aviso { background: #EDF2FC; border-left: 4px solid var(--azul); padding: 12px 14px; font-size: 14px; margin-bottom: 18px; }
  .aviso.ok { background: #EAF5EF; border-left-color: var(--verde); }
  .aviso.mal { background: #FBECEB; border-left-color: var(--rojo); }
  .clave { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; background: var(--humo);
           border: 1px solid var(--linea); padding: 2px 6px; font-size: 14px; }

  /* ---- Móvil ----
     Esto se usa con el teléfono en la mano: leyendo el guión mientras se
     graba y apuntando cosas desde cualquier sitio. Así que una columna,
     dedos en vez de ratón y nada de tablas apretadas. */
  @media (max-width: 600px) {
    .guion { padding: 18px 16px; }
    .guion .cab h1 { font-size: 24px; line-height: 1; }
    .bloque { grid-template-columns: 44px 1fr; gap: 12px; }
    .bloque .min { font-size: 16px; }
    .tarjeta { grid-template-columns: 1fr; }
    .tarjeta .der { justify-items: start; text-align: left; }
    .pareja { grid-template-columns: 1fr; }
    .mover select { flex: 1 1 100%; width: 100%; }   /* el texto de la opción es largo: que no empuje */
    .bloque.patro { margin-left: -8px; padding-left: 8px; }
    header h1 { font-size: 24px; }
    main.caja { padding: 20px 14px 60px; }
    section { padding: 16px 14px; }

    /* Las cifras, en una línea cada una: número y rótulo, sin cajones altos */
    .cifras { gap: 8px; }
    .cifra { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 12px 14px; }
    .cifra b { font-size: 26px; }
    .cifra span { text-align: right; }

    /* Varias rejillas llevan su reparto en el propio atributo style (170px
       para el orden, 110px para el número...). En el móvil no caben y el
       atributo gana a cualquier regla, así que aquí toca !important. */
    .campos { grid-template-columns: 1fr !important; }

    /* 16px o Safari hace zoom al tocar un campo y se descoloca la página */
    input[type=text], input[type=url], input[type=email], input[type=password],
    input[type=number], input[type=date], input[type=datetime-local], select, textarea { font-size: 16px; }

    /* Botones para dedos, no para ratón */
    .btn { padding: 12px 16px; }
    .btn.pequeno { padding: 9px 13px; font-size: 12px; }
    .acciones { gap: 10px; }

    /* En columna, el «flex: 1 1 230px» de arriba se lee como ALTURA y los
       campos salen gigantes: aquí cada uno ocupa lo suyo y punto. */
    .subir { flex-direction: column; align-items: stretch; }
    .subir input[type=file], .subir input[type=text] { width: 100%; flex: 0 0 auto; }
    .fila-enlace { flex-wrap: wrap; }
    .fila-enlace input[type=text], .fila-enlace input[type=url] { flex: 1 1 100%; }
    .fila-enlace .btn { flex: 1 1 auto; }

    /* El dominio del enlace, a su línea: si no, estruja el título a una
       palabra por renglón */
    .enlaces li { flex-wrap: wrap; }
    .enlaces li a { flex: 1 1 0; min-width: 0; overflow-wrap: anywhere; }   /* la flecha y el título, juntos */
    .enlaces li .dom { flex: 1 1 100%; margin-left: 18px; }

    .archivos li { align-items: flex-start; }
    .contratos li { flex-direction: column; align-items: flex-start; }
    .archivos li input[type=text] { flex: 1 1 100%; }
    .archivos li form { flex: 1 1 100%; }

    /* La foto y su descripción: la foto entera, no recortada */
    .pareja img { height: auto; max-height: 300px; object-fit: contain; }

    /* Tablas: que se puedan empujar de lado en vez de reventar la página */
    .tabla-ancha { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .tabla-ancha table { min-width: 520px; }

    /* Las menciones del guión, apiladas: se leen grabando, no se comparan */
    .menciones tr { display: block; border-bottom: 1px solid var(--linea); padding: 10px 0; }
    .menciones td { display: block; width: auto !important; border: 0; padding: 0 0 4px; }
    .menciones td.n { text-align: left; font-weight: 700; }
  }

  @media print {
    body { background: #fff; }
    header, .pestanas, .noimprime { display: none !important; }
    main.caja { padding: 0; max-width: none; }
    .guion { border: 0; padding: 0; }
  }
</style>
    <?php
}

/* $extra: botones propios de la pantalla */
function ane_pod_cabecera(string $pestana, string $extra = ''): void
{
    global $yo_pod, $es_socio;
    $pestanas = $es_socio
        ? ['episodios' => ['/estudio/', 'Episodios'], 'proyectos' => ['/estudio/proyectos.php', 'Proyectos'],
           'marcas' => ['/estudio/marcas.php', 'Patrocinadores'], 'accesos' => ['/estudio/accesos.php', 'Accesos']]
        : ['episodios' => ['/estudio/', 'Mis guiones']];
    ?>
<header>
  <div class="caja fila">
    <h1 class="display">Estudio <em>ANE</em></h1>
    <p class="quien">
      <b><?= ane_h($yo_pod['nombre'] !== '' ? $yo_pod['nombre'] : $yo_pod['usuario']) ?></b>
      · <?= $es_socio ? 'socio' : 'editor' ?>
      <a href="/estudio/entrar.php?salir=1">Salir</a>
    </p>
  </div>
  <div class="caja">
    <div class="pestanas">
      <?php foreach ($pestanas as $clave => [$href, $texto]): ?>
        <a href="<?= $href ?>" class="<?= $clave === $pestana ? 'si' : '' ?>"><?= $texto ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</header>
<?php if ($extra !== ''): ?><div class="caja" style="padding-top:18px"><?= $extra ?></div><?php endif;
}

/* Mensajes de vuelta tras un POST (?ok=guardado) */
function ane_pod_aviso(array $textos): void
{
    $ok = (string) ($_GET['ok'] ?? '');
    if ($ok !== '' && isset($textos[$ok])) {
        echo '<div class="aviso ok">' . ane_h($textos[$ok]) . '</div>';
    }
    $mal = (string) ($_GET['mal'] ?? '');
    if ($mal !== '' && isset($textos[$mal])) {
        echo '<div class="aviso mal">' . ane_h($textos[$mal]) . '</div>';
    }
}

function ane_pod_chip(string $valor, array $vocabulario): string
{
    $txt = $vocabulario[$valor] ?? $valor;
    return '<span class="chip ' . ane_h($valor) . '">' . ane_h($txt) . '</span>';
}
