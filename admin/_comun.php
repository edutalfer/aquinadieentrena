<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — común a todas las pestañas del panel
   ------------------------------------------------------------
   Acceso con usuario y contraseña del navegador (Basic Auth,
   siempre sobre HTTPS). Las credenciales NO están en el repo
   (es público): ~/datos/admin.php devuelve
       ['usuario' => '...', 'hash' => password_hash('...')]
   Si ese fichero falta o no cuadra, no entra nadie.

   No se sirve por web (el .htaccess bloquea `_*.php`).
   ============================================================ */

declare(strict_types=1);
require_once dirname(__DIR__) . '/api/_formularios.php';

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

/* ---------- Acceso ---------- */

function ane_credenciales(): array
{
    $u = $_SERVER['PHP_AUTH_USER'] ?? null;
    $p = $_SERVER['PHP_AUTH_PW'] ?? null;
    if ($u === null) {   // algunos servidores solo pasan la cabecera cruda
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (stripos($h, 'basic ') === 0) {
            $dec = base64_decode(substr($h, 6), true);
            if ($dec !== false && strpos($dec, ':') !== false) {
                [$u, $p] = explode(':', $dec, 2);
            }
        }
    }
    return [(string) $u, (string) $p];
}

$cfgFichero = ANE_DATOS . '/admin.php';
$cfg = is_file($cfgFichero) ? require $cfgFichero : null;
[$usuario, $clave] = ane_credenciales();

$dentro = is_array($cfg) && isset($cfg['usuario'], $cfg['hash']) && $usuario !== ''
    && hash_equals((string) $cfg['usuario'], $usuario)
    && password_verify($clave, (string) $cfg['hash']);

if (!$dentro) {
    if ($usuario !== '') {
        usleep(600000);   // frena los intentos a lo bruto
    }
    header('WWW-Authenticate: Basic realm="ANE - panel privado", charset="UTF-8"');
    http_response_code(401);
    echo 'Acceso restringido.';
    exit;
}

/* ---------- Protección contra envíos falsificados (CSRF) ----------
   Con Basic Auth el navegador manda la contraseña sola a cualquier
   petición a /admin, también a las que provoque otra web. Por eso
   todo POST del panel lleva un token firmado y se comprueba el origen. */

function ane_csrf(): string
{
    global $usuario;
    return hash_hmac('sha256', 'panel|' . $usuario, ane_secreto());
}

function ane_csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . ane_csrf() . '">';
}

function ane_csrf_exige(): void
{
    $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $origenOk = $origen === '' || parse_url($origen, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST);
    if (!$origenOk || !hash_equals(ane_csrf(), (string) ($_POST['_csrf'] ?? ''))) {
        http_response_code(403);
        exit('Petición rechazada. Recarga la página y vuelve a intentarlo.');
    }
}

/* ---------- Estilos y cabecera comunes ---------- */

function ane_admin_estilo(): void
{
    ?>
<style>
  @font-face { font-family: "Archivo"; src: url("/assets/fonts/archivo-black-italic.woff2") format("woff2");
               font-weight: 900; font-style: italic; font-display: swap; }
  @font-face { font-family: "Inter"; src: url("/assets/fonts/inter.woff2") format("woff2");
               font-weight: 400 700; font-display: swap; }
  :root { --azul: #3F77DA; --negro: #191919; --enlace: #2A5CB8; --destello: #B2C8F0; --profundo: #264783;
          --gris: #6E747F; --humo: #F2F3F5; --linea: #E1E3E7; }
  * { box-sizing: border-box; }
  body { margin: 0; font: 15px/1.5 "Inter", system-ui, sans-serif; color: var(--negro); background: var(--humo); }
  .display { font-family: "Archivo", sans-serif; font-weight: 900; font-style: italic;
             text-transform: uppercase; letter-spacing: -0.02em; line-height: .9; }
  header { background: var(--negro); color: #fff; padding: 22px 0 0; }
  .caja { max-width: 1180px; margin: 0 auto; padding: 0 20px; }
  header .caja { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between; }
  header h1 { margin: 0; font-size: 30px; }
  header h1 em { color: var(--azul); font-style: italic; }
  nav a { color: #C7CAD1; text-decoration: none; font-weight: 700; font-size: 12px; letter-spacing: .1em;
          text-transform: uppercase; padding: 7px 11px; border: 1px solid #33363D; margin-left: 4px; }
  nav a.si { background: var(--azul); border-color: var(--azul); color: #fff; }
  .pestanas { display: flex; gap: 4px; margin-top: 18px; }
  .pestanas a { color: #C7CAD1; text-decoration: none; font-weight: 700; font-size: 13px; letter-spacing: .08em;
                text-transform: uppercase; padding: 11px 18px 12px; border-bottom: 3px solid transparent; }
  .pestanas a:hover { color: #fff; }
  .pestanas a.si { color: #fff; border-bottom-color: var(--azul); }
  main.caja { padding: 32px 20px 60px; }   /* más específico que .caja, que ponía el margen de arriba a 0 */
  .cifras { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; margin-bottom: 22px; }
  .cifra { background: #fff; border: 2px solid var(--negro); padding: 16px 18px; }
  .cifra b { display: block; font: 900 italic 40px/1 "Archivo", sans-serif; }
  .cifra span { font-size: 12px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--gris); }
  .rejilla { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
  @media (max-width: 860px) { .rejilla { grid-template-columns: 1fr; } }
  section { background: #fff; border: 1px solid var(--linea); padding: 18px 20px; margin-bottom: 18px; }
  section h2 { margin: 0 0 4px; font-size: 22px; }
  section p.nota { margin: 0 0 12px; color: var(--gris); font-size: 13px; }
  table { width: 100%; border-collapse: collapse; font-size: 14px; }
  th { text-align: left; font-size: 11px; letter-spacing: .1em; text-transform: uppercase; color: var(--gris);
       border-bottom: 2px solid var(--negro); padding: 6px 8px 6px 0; }
  td { border-bottom: 1px solid var(--linea); padding: 7px 8px 7px 0; vertical-align: top; }
  td.n, th.n { text-align: right; font-variant-numeric: tabular-nums; }
  td.cero { color: var(--gris); }
  a { color: var(--enlace); }
  .dias { display: flex; align-items: flex-end; gap: 3px; height: 130px; padding-top: 8px; }
  .dias div { flex: 1; background: var(--azul); min-height: 2px; position: relative; }
  .dias div:hover { background: var(--negro); }
  .vacio { color: var(--gris); font-style: italic; padding: 8px 0; }
  .pie { color: var(--gris); font-size: 12px; margin-top: 10px; }
</style>
    <?php
}

/* $nav: botones propios de la pestaña (p. ej. los periodos de las estadísticas) */
function ane_admin_cabecera(string $pestana, string $nav = ''): void
{
    $pestanas = ['estadisticas' => ['/admin/', 'Estadísticas'], 'formularios' => ['/admin/formularios.php', 'Formularios']];
    ?>
<header>
  <div class="caja">
    <h1 class="display">Panel <em>privado</em></h1>
    <?php if ($nav !== ''): ?><nav><?= $nav ?></nav><?php endif; ?>
  </div>
  <div class="caja">
    <div class="pestanas">
      <?php foreach ($pestanas as $clave => [$href, $texto]): ?>
        <a href="<?= $href ?>" class="<?= $clave === $pestana ? 'si' : '' ?>"><?= $texto ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</header>
    <?php
}
