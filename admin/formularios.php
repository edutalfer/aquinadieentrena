<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — panel privado · pestaña Formularios
   ------------------------------------------------------------
   Formularios de inscripción a eventos, estilo Google Forms:
     (sin parámetros)      listado
     ?editar=<id>          ajustes y editor de preguntas
     ?respuestas=<id>      inscritos, lista de espera y CSV
   Todo lo que cambia algo va por POST con token CSRF.
   La página pública es /f/<código> → formulario.php
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/_comun.php';

$bd = ane_bd();
$yo = '/admin/formularios.php';

function ane_vuelve(string $query = ''): void
{
    global $yo;
    header('Location: ' . $yo . ($query !== '' ? '?' . $query : ''), true, 303);
    exit;
}

function ane_enlace_publico(array $f): string
{
    return 'https://aquinadieentrena.cc/f/' . $f['token'];
}

/* ---------- Acciones (POST) ---------- */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    ane_csrf_exige();
    $accion = (string) ($_POST['accion'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $ahora = ane_ahora();

    switch ($accion) {
        case 'crear':
            $titulo = ane_recorta((string) ($_POST['titulo'] ?? ''), 200) ?: 'Formulario sin título';
            $bd->prepare('INSERT INTO formularios (token, titulo, preguntas, creado, actualizado) VALUES (?, ?, ?, ?, ?)')
               ->execute([ane_form_token(), $titulo,
                          json_encode(ane_form_preguntas_iniciales(), JSON_UNESCAPED_UNICODE), $ahora, $ahora]);
            ane_vuelve('editar=' . (int) $bd->lastInsertId() . '&ok=creado');

        case 'guardar':
            $actual = ane_form_por_id($bd, $id);
            if (!$actual) {
                ane_vuelve();
            }
            /* Si el editor no manda las preguntas (JS roto) o llegan vacías,
               se quedan las que había: mejor eso que un formulario sin preguntas */
            $preguntas = ane_form_limpia_preguntas(json_decode((string) ($_POST['preguntas'] ?? ''), true));
            if (!$preguntas) {
                $preguntas = ane_form_preguntas($actual);
            }
            $bd->prepare('UPDATE formularios SET titulo = ?, descripcion = ?, preguntas = ?, limite = ?, cierre = ?,
                            abierto = ?, una_por_correo = ?, lista_espera = ?, mostrar_plazas = ?,
                            mensaje_ok = ?, aviso_extra = ?, actualizado = ? WHERE id = ?')
               ->execute([
                   ane_recorta((string) ($_POST['titulo'] ?? ''), 200) ?: 'Formulario sin título',
                   ane_recorta((string) ($_POST['descripcion'] ?? ''), 6000),
                   json_encode($preguntas, JSON_UNESCAPED_UNICODE),
                   max(0, min(100000, (int) ($_POST['limite'] ?? 0))),
                   ane_local_a_utc((string) ($_POST['cierre'] ?? '')),
                   empty($_POST['abierto']) ? 0 : 1,
                   empty($_POST['una_por_correo']) ? 0 : 1,
                   empty($_POST['lista_espera']) ? 0 : 1,
                   empty($_POST['mostrar_plazas']) ? 0 : 1,
                   ane_recorta((string) ($_POST['mensaje_ok'] ?? ''), 3000),
                   ane_recorta((string) ($_POST['aviso_extra'] ?? ''), 500),
                   $ahora, $id,
               ]);
            ane_vuelve('editar=' . $id . '&ok=guardado');

        case 'duplicar':
            $f = ane_form_por_id($bd, $id);
            if ($f) {
                /* Mismas preguntas con ids nuevos: son respuestas de otro evento */
                $preg = array_map(function ($p) { unset($p['id']); return $p; }, ane_form_preguntas($f));
                $bd->prepare('INSERT INTO formularios (token, titulo, descripcion, preguntas, limite, cierre, abierto,
                                una_por_correo, lista_espera, mostrar_plazas, mensaje_ok, aviso_extra, creado, actualizado)
                              VALUES (?, ?, ?, ?, ?, NULL, 1, ?, ?, ?, ?, ?, ?, ?)')
                   ->execute([ane_form_token(), mb_substr('Copia de ' . $f['titulo'], 0, 200), $f['descripcion'],
                              json_encode(ane_form_limpia_preguntas($preg), JSON_UNESCAPED_UNICODE),
                              $f['limite'], $f['una_por_correo'], $f['lista_espera'], $f['mostrar_plazas'],
                              $f['mensaje_ok'], $f['aviso_extra'], $ahora, $ahora]);
                ane_vuelve('editar=' . (int) $bd->lastInsertId() . '&ok=duplicado');
            }
            ane_vuelve();

        case 'borrar':
            $bd->prepare('DELETE FROM form_respuestas WHERE form_id = ?')->execute([$id]);
            $bd->prepare('DELETE FROM formularios WHERE id = ?')->execute([$id]);
            ane_vuelve('ok=borrado');

        case 'vaciar':
            $bd->prepare('DELETE FROM form_respuestas WHERE form_id = ?')->execute([$id]);
            ane_vuelve('respuestas=' . $id . '&ok=vaciado');

        case 'borrar_respuesta':
            $bd->prepare('DELETE FROM form_respuestas WHERE id = ? AND form_id = ?')
               ->execute([(int) ($_POST['respuesta'] ?? 0), $id]);
            ane_vuelve('respuestas=' . $id . '&ok=respuesta_borrada');

        case 'confirmar_reserva':
            $bd->prepare('UPDATE form_respuestas SET reserva = 0 WHERE id = ? AND form_id = ?')
               ->execute([(int) ($_POST['respuesta'] ?? 0), $id]);
            ane_vuelve('respuestas=' . $id . '&ok=reserva_confirmada');
    }
    ane_vuelve();
}

/* ---------- Respuestas en forma de tabla (pantalla y CSV) ---------- */

function ane_tabla_respuestas(PDO $bd, array $f): array
{
    $cols = [];
    foreach (ane_form_preguntas($f) as $p) {
        $cols[$p['id']] = $p['titulo'];
    }
    $st = $bd->prepare('SELECT * FROM form_respuestas WHERE form_id = ? ORDER BY reserva, id');
    $st->execute([(int) $f['id']]);
    $filas = [];
    foreach ($st->fetchAll() as $r) {
        $celdas = [];
        foreach ((array) json_decode($r['datos'], true) as $d) {
            if (!isset($cols[$d['id']])) {   // pregunta que ya no existe: se conserva
                $cols[$d['id']] = $d['titulo'] . ' (borrada)';
            }
            $celdas[$d['id']] = is_array($d['valor']) ? implode(', ', $d['valor']) : (string) $d['valor'];
        }
        $filas[] = ['id' => (int) $r['id'], 'fecha' => $r['fecha'], 'reserva' => (int) $r['reserva'], 'celdas' => $celdas];
    }
    return [$cols, $filas];
}

$vistaEditar = isset($_GET['editar']) ? ane_form_por_id($bd, (int) $_GET['editar']) : null;
$vistaResp = isset($_GET['respuestas']) ? ane_form_por_id($bd, (int) $_GET['respuestas']) : null;

/* ---------- CSV ---------- */

if (isset($_GET['csv'])) {
    $f = ane_form_por_id($bd, (int) $_GET['csv']);
    if (!$f) {
        ane_vuelve();
    }
    [$cols, $filas] = ane_tabla_respuestas($bd, $f);
    $nombre = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $f['titulo']) ?: 'formulario')), '-');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="inscritos-' . $nombre . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM: que Excel respete las tildes
    fputcsv($out, array_merge(['Nº', 'Fecha', 'Estado'], array_values($cols)), ';');
    $n = 0;
    foreach ($filas as $r) {
        $fila = [++$n, ane_utc_a_local($r['fecha'], 'd/m/Y H:i'), $r['reserva'] ? 'Lista de espera' : 'Confirmada'];
        foreach (array_keys($cols) as $c) {
            $fila[] = $r['celdas'][$c] ?? '';
        }
        fputcsv($out, $fila, ';');
    }
    exit;
}

/* ---------- Pintado ---------- */

$mensajes = [
    'creado' => 'Formulario creado. Ya tiene las preguntas de correo, nombre y teléfono: ajústalo y guarda.',
    'guardado' => 'Cambios guardados.', 'duplicado' => 'Formulario duplicado. Cambia el título, las fechas y guarda.',
    'borrado' => 'Formulario borrado, con todas sus respuestas.', 'vaciado' => 'Respuestas borradas.',
    'respuesta_borrada' => 'Inscripción borrada.', 'reserva_confirmada' => 'Reserva pasada a confirmada.',
];
$ok = $mensajes[(string) ($_GET['ok'] ?? '')] ?? '';

function ane_etiqueta_estado(array $e): string
{
    $t = ['abierto' => ['Abierto', 'si'], 'espera' => ['Lista de espera', 'medio'], 'completo' => ['Completo', 'no'],
          'cerrado' => ['Cerrado', 'no'], 'caducado' => ['Cerrado por fecha', 'no']][$e['estado']];
    return '<span class="estado estado--' . $t[1] . '">' . $t[0] . '</span>';
}

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Formularios — Panel privado — Aquí Nadie Entrena</title>
<?php ane_admin_estilo(); ?>
<style>
  .barra { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 18px; }
  .barra h2 { margin: 0; font-size: 30px; }
  .boton { display: inline-block; font: 700 12px/1 "Inter", sans-serif; letter-spacing: .08em; text-transform: uppercase;
           text-decoration: none; cursor: pointer; padding: 11px 15px; border: 2px solid var(--negro);
           background: var(--negro); color: #fff; }
  .boton:hover { background: var(--enlace); border-color: var(--enlace); }
  .boton--claro { background: #fff; color: var(--negro); }
  .boton--claro:hover { background: var(--negro); color: #fff; border-color: var(--negro); }
  .boton--peligro { background: #fff; color: var(--negro); border-color: #C9CDD3; }
  .boton--peligro:hover { background: var(--negro); color: #fff; border-color: var(--negro); }
  .boton--peq { padding: 7px 10px; font-size: 11px; }
  form.linea { display: inline; }
  .ok { background: var(--negro); color: #fff; padding: 12px 16px; margin-bottom: 18px; font-weight: 600; }
  .aviso-datos { background: var(--destello); padding: 12px 16px; margin-bottom: 18px; font-size: 14px; }
  .estado { display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
            padding: 3px 8px; }
  /* Doble clase a propósito: gana a reglas genéricas como «.cifra span»,
     que las volvía grises y con la tipografía de titular */
  .estado.estado--si { background: var(--enlace); color: #fff; }        /* 6,3:1 */
  .estado.estado--medio { background: var(--destello); color: var(--negro); }
  .estado.estado--no { background: var(--linea); color: var(--negro); }
  .cifra .estado { font: 700 14px/1.2 "Inter", sans-serif; letter-spacing: .08em; padding: 7px 12px; margin: 4px 0 12px; }
  .enlace { display: flex; gap: 6px; align-items: center; }
  .enlace code { font-size: 12px; background: var(--humo); padding: 4px 6px; }
  .acciones { display: flex; flex-wrap: wrap; gap: 6px; }
  .lista td { vertical-align: middle; padding: 12px 8px 12px 0; }
  .lista .tit { font-weight: 700; font-size: 16px; }
  .barrita { height: 6px; background: var(--humo); margin-top: 5px; width: 120px; }
  .barrita div { height: 100%; background: var(--azul); }

  /* Editor */
  .campos { display: grid; gap: 16px; }
  .campos label.c { display: block; font-weight: 600; font-size: 14px; margin-bottom: 5px; }
  .campos .ayuda { font-size: 13px; color: var(--gris); margin: 4px 0 0; }
  .campos input[type=text], .campos input[type=number], .campos input[type=datetime-local], .campos textarea, .p select {
    width: 100%; font: inherit; font-size: 15px; padding: 9px 10px; border: 1px solid #C9CDD3; background: #fff; color: var(--negro); }
  .campos textarea { resize: vertical; }
  .dos { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  @media (max-width: 700px) { .dos { grid-template-columns: 1fr; } }
  .check { display: flex; gap: 10px; align-items: flex-start; font-size: 15px; cursor: pointer; }
  .check input { width: 18px; height: 18px; margin-top: 2px; accent-color: var(--enlace); flex: none; }
  .check small { display: block; color: var(--gris); font-size: 13px; }
  .guardar-fijo { position: sticky; bottom: 0; background: var(--humo); padding: 14px 0; border-top: 1px solid var(--linea);
                  display: flex; gap: 10px; align-items: center; z-index: 5; }
  .p { background: #fff; border: 1px solid var(--linea); border-left: 5px solid var(--azul); padding: 16px 18px; margin-bottom: 12px; }
  .p__fila { display: grid; grid-template-columns: 1fr 240px; gap: 12px; }
  @media (max-width: 700px) { .p__fila { grid-template-columns: 1fr; } }
  .p input[type=text], .p textarea { width: 100%; font: inherit; font-size: 15px; padding: 9px 10px;
                                      border: 1px solid #C9CDD3; background: #fff; color: var(--negro); }
  .p .titulo-p { font-weight: 600; font-size: 16px; }
  .p .ayuda-p { margin-top: 8px; font-size: 14px; }
  .p textarea { margin-top: 8px; min-height: 92px; }
  .p__pie { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between;
            margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--linea); }
  .p__pie .acciones button { font: 600 12px/1 "Inter", sans-serif; padding: 7px 9px; cursor: pointer;
                             border: 1px solid #C9CDD3; background: #fff; color: var(--negro); }
  .p__pie .acciones button:hover { border-color: var(--negro); }
  .p__pie .acciones button:disabled { opacity: .35; cursor: default; }
  .etiqueta-op { font-size: 12px; color: var(--gris); margin: 8px 0 0; }
  .resp td { font-size: 13px; }
  .resp tr.reserva td { background: #F7F8FA; color: var(--gris); }
  .tabla-scroll { overflow-x: auto; }
  code { font-size: 13px; }
</style>
</head>
<body>

<?php ane_admin_cabecera('formularios'); ?>

<main class="caja">
<?php if ($ok): ?><div class="ok"><?= ane_h($ok) ?></div><?php endif; ?>

<?php if ($vistaEditar):
    /* ================= EDITOR ================= */
    $f = $vistaEditar;
    $e = ane_form_estado($bd, $f);
    $total = $e['ocupadas'] + $e['reservas'];
    $json = json_encode(ane_form_preguntas($f), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $tipos = json_encode(ANE_FORM_TIPOS, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
  <p><a href="<?= $yo ?>">← Todos los formularios</a></p>
  <div class="barra">
    <h2 class="display"><?= ane_h($f['titulo']) ?></h2>
    <div class="acciones">
      <a class="boton boton--claro" href="?respuestas=<?= (int) $f['id'] ?>">Respuestas (<?= $total ?>)</a>
      <a class="boton boton--claro" href="/f/<?= ane_h($f['token']) ?>?previa=1" target="_blank" rel="noopener">Vista previa</a>
    </div>
  </div>

  <section>
    <h2 class="display">Enlace para compartir</h2>
    <p class="nota">No aparece en ninguna parte de la web ni en Google: solo entra quien tenga el enlace. <?= ane_etiqueta_estado($e) ?></p>
    <div class="enlace">
      <code id="url"><?= ane_h(ane_enlace_publico($f)) ?></code>
      <button type="button" class="boton boton--peq" onclick="copiar(this)">Copiar</button>
      <a class="boton boton--peq boton--claro" href="/f/<?= ane_h($f['token']) ?>" target="_blank" rel="noopener">Abrir</a>
    </div>
  </section>

  <form method="post" id="editor">
    <?= ane_csrf_campo() ?>
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
    <input type="hidden" name="preguntas" id="preguntas-campo">

    <section>
      <h2 class="display">Cabecera</h2>
      <p class="nota">Lo que se ve arriba del formulario.</p>
      <div class="campos">
        <div>
          <label class="c" for="titulo">Título</label>
          <input type="text" id="titulo" name="titulo" maxlength="200" value="<?= ane_h($f['titulo']) ?>" required>
        </div>
        <div>
          <label class="c" for="descripcion">Descripción</label>
          <textarea id="descripcion" name="descripcion" rows="12" maxlength="6000"><?= ane_h($f['descripcion']) ?></textarea>
          <p class="ayuda">Formato: <code>**negrita**</code> · <code>_cursiva_</code> · líneas que empiezan por <code>- </code> son una lista ·
             <code>[texto](https://enlace)</code> · una línea en blanco separa párrafos. Los emojis funcionan tal cual 🚫🎁.</p>
        </div>
      </div>
    </section>

    <section>
      <h2 class="display">Preguntas</h2>
      <p class="nota">Arrastrar no hace falta: ordénalas con las flechas.
        <?php if ($total): ?><strong>Ya hay <?= $total ?> respuestas.</strong> Si borras una pregunta, lo que ya se contestó se conserva en «Respuestas» y en el CSV.<?php endif; ?></p>
      <div id="preguntas"></div>
      <button type="button" class="boton boton--claro" id="anadir">+ Añadir pregunta</button>
      <p class="nota" style="margin-top:12px">Al final del formulario siempre sale el aviso de privacidad con su casilla obligatoria: no hace falta añadirlo.</p>
    </section>

    <section>
      <h2 class="display">Plazas y cierre</h2>
      <p class="nota">Ahora mismo: <?= $e['ocupadas'] ?> confirmadas<?= $e['reservas'] ? ' y ' . $e['reservas'] . ' en lista de espera' : '' ?>.</p>
      <div class="campos">
        <div class="dos">
          <div>
            <label class="c" for="limite">Límite de plazas</label>
            <input type="number" id="limite" name="limite" min="0" max="100000" value="<?= (int) $f['limite'] ?>">
            <p class="ayuda">0 = sin límite. Al llegar al límite el formulario se cierra solo (o pasa a lista de espera).</p>
          </div>
          <div>
            <label class="c" for="cierre">Cerrar automáticamente el…</label>
            <input type="datetime-local" id="cierre" name="cierre" value="<?= ane_h(ane_utc_a_local($f['cierre'])) ?>">
            <p class="ayuda">Hora de Madrid. Vacío = sin fecha de cierre.</p>
          </div>
        </div>
        <label class="check"><input type="checkbox" name="abierto" value="1"<?= (int) $f['abierto'] ? ' checked' : '' ?>>
          <span>Aceptando respuestas<small>Desmárcalo para cerrar el formulario a mano en cualquier momento.</small></span></label>
        <label class="check"><input type="checkbox" name="lista_espera" value="1"<?= (int) $f['lista_espera'] ? ' checked' : '' ?>>
          <span>Lista de espera al llenarse<small>Cuando se cubren las plazas, se siguen aceptando inscripciones como reserva. Desde «Respuestas» puedes pasar una reserva a confirmada.</small></span></label>
        <label class="check"><input type="checkbox" name="mostrar_plazas" value="1"<?= (int) $f['mostrar_plazas'] ? ' checked' : '' ?>>
          <span>Mostrar las plazas que quedan<small>«Quedan 12 plazas · 38 de 50», con una barra.</small></span></label>
        <label class="check"><input type="checkbox" name="una_por_correo" value="1"<?= (int) $f['una_por_correo'] ? ' checked' : '' ?>>
          <span>Una inscripción por correo<small>Si alguien repite el mismo correo, se le avisa en vez de ocupar otra plaza. Usa la primera pregunta de tipo «Correo electrónico».</small></span></label>
      </div>
    </section>

    <section>
      <h2 class="display">Después de enviar</h2>
      <div class="campos">
        <div>
          <label class="c" for="mensaje_ok">Mensaje de confirmación</label>
          <textarea id="mensaje_ok" name="mensaje_ok" rows="4" maxlength="3000" placeholder="Ya estás dentro. Nos vemos allí."><?= ane_h($f['mensaje_ok']) ?></textarea>
          <p class="ayuda">Mismo formato que la descripción. Vacío = «Ya estás dentro. Nos vemos allí.»</p>
        </div>
        <div>
          <label class="c" for="aviso_extra">Frase extra en el aviso de privacidad</label>
          <input type="text" id="aviso_extra" name="aviso_extra" maxlength="500" value="<?= ane_h($f['aviso_extra']) ?>"
                 placeholder="Ej.: Compartimos tu nombre y correo con Bicilab y Gobik, que organizan el evento con nosotros.">
          <p class="ayuda">Solo si los datos se comparten con alguien más (coorganizadores, patrocinador…). Si no, déjalo vacío.</p>
        </div>
      </div>
    </section>

    <div class="guardar-fijo">
      <button class="boton" type="submit">Guardar cambios</button>
      <span class="nota" style="color:var(--gris);font-size:13px">Los cambios se ven al momento en el enlace público.</span>
    </div>
  </form>

  <section style="margin-top:28px">
    <h2 class="display">Otras acciones</h2>
    <div class="acciones">
      <form class="linea" method="post"><?= ane_csrf_campo() ?><input type="hidden" name="accion" value="duplicar"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
        <button class="boton boton--claro" type="submit">Duplicar para otro evento</button></form>
      <form class="linea" method="post" onsubmit="return confirm('¿Borrar el formulario «<?= ane_h(addslashes($f['titulo'])) ?>» y sus <?= $total ?> respuestas? No se puede deshacer.')">
        <?= ane_csrf_campo() ?><input type="hidden" name="accion" value="borrar"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
        <button class="boton boton--peligro" type="submit">Borrar formulario</button></form>
    </div>
  </section>

<script type="application/json" id="datos-preguntas"><?= $json ?></script>
<script type="application/json" id="datos-tipos"><?= $tipos ?></script>
<script>
/* Editor de preguntas. Todo se construye con nodos (nada de innerHTML con
   texto del usuario) y al guardar se vuelca a un JSON oculto que el
   servidor vuelve a validar entero. */
(function () {
  var preguntas = JSON.parse(document.getElementById('datos-preguntas').textContent);
  var TIPOS = JSON.parse(document.getElementById('datos-tipos').textContent);
  var CON_OPCIONES = ['opcion', 'casillas', 'desplegable'];
  var caja = document.getElementById('preguntas');

  function el(tag, props, hijos) {
    var n = document.createElement(tag);
    Object.keys(props || {}).forEach(function (k) {
      if (k === 'texto') n.textContent = props[k];
      else if (k.indexOf('on') === 0) n.addEventListener(k.slice(2), props[k]);
      else if (k in n) n[k] = props[k];
      else n.setAttribute(k, props[k]);
    });
    (hijos || []).forEach(function (h) { if (h) n.appendChild(h); });
    return n;
  }

  function pinta() {
    caja.textContent = '';
    preguntas.forEach(function (p, i) {
      var tipo = el('select', { onchange: function () {
        p.tipo = this.value;
        if (CON_OPCIONES.indexOf(p.tipo) >= 0 && !(p.opciones || []).length) p.opciones = ['Opción 1', 'Opción 2'];
        pinta();
      } });
      Object.keys(TIPOS).forEach(function (t) { tipo.appendChild(el('option', { value: t, texto: TIPOS[t], selected: t === p.tipo })); });

      var opciones = null;
      if (CON_OPCIONES.indexOf(p.tipo) >= 0) {
        opciones = el('div', {}, [
          el('p', { className: 'etiqueta-op', texto: 'Opciones, una por línea:' }),
          el('textarea', { value: (p.opciones || []).join('\n'), oninput: function () {
            p.opciones = this.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
          } })
        ]);
      }

      function mueve(d) { var j = i + d; var t = preguntas[i]; preguntas[i] = preguntas[j]; preguntas[j] = t; pinta(); }

      caja.appendChild(el('div', { className: 'p' }, [
        el('div', { className: 'p__fila' }, [
          el('input', { type: 'text', className: 'titulo-p', value: p.titulo || '', placeholder: 'Pregunta',
                        maxLength: 300, oninput: function () { p.titulo = this.value; } }),
          tipo
        ]),
        el('input', { type: 'text', className: 'ayuda-p', value: p.ayuda || '', maxLength: 500,
                      placeholder: 'Texto de ayuda (opcional)', oninput: function () { p.ayuda = this.value; } }),
        opciones,
        el('div', { className: 'p__pie' }, [
          el('label', { className: 'check' }, [
            el('input', { type: 'checkbox', checked: !!p.obligatoria, onchange: function () { p.obligatoria = this.checked; } }),
            el('span', { texto: 'Obligatoria' })
          ]),
          el('div', { className: 'acciones' }, [
            el('button', { type: 'button', texto: '↑', title: 'Subir', disabled: i === 0, onclick: function () { mueve(-1); } }),
            el('button', { type: 'button', texto: '↓', title: 'Bajar', disabled: i === preguntas.length - 1, onclick: function () { mueve(1); } }),
            el('button', { type: 'button', texto: 'Duplicar', onclick: function () {
              var c = JSON.parse(JSON.stringify(p)); c.id = ''; preguntas.splice(i + 1, 0, c); pinta(); } }),
            el('button', { type: 'button', texto: 'Eliminar', onclick: function () {
              if (!p.titulo || confirm('¿Eliminar la pregunta «' + p.titulo + '»?')) { preguntas.splice(i, 1); pinta(); } } })
          ])
        ])
      ]));
    });
    if (!preguntas.length) caja.appendChild(el('p', { className: 'vacio', texto: 'Sin preguntas: añade al menos una.' }));
  }

  document.getElementById('anadir').addEventListener('click', function () {
    preguntas.push({ id: '', tipo: 'corto', titulo: '', ayuda: '', obligatoria: true, opciones: [] });
    pinta();
    var ultimos = caja.querySelectorAll('.titulo-p');
    if (ultimos.length) ultimos[ultimos.length - 1].focus();
  });

  var cambiado = false;
  var editor = document.getElementById('editor');
  editor.addEventListener('input', function () { cambiado = true; });
  editor.addEventListener('click', function (e) { if (e.target.tagName === 'BUTTON' && e.target.type === 'button') cambiado = true; });
  editor.addEventListener('submit', function () {
    document.getElementById('preguntas-campo').value = JSON.stringify(preguntas);
    cambiado = false;
  });
  window.addEventListener('beforeunload', function (e) { if (cambiado) { e.preventDefault(); e.returnValue = ''; } });

  pinta();
})();
</script>

<?php elseif ($vistaResp):
    /* ================= RESPUESTAS ================= */
    $f = $vistaResp;
    $e = ane_form_estado($bd, $f);
    [$cols, $filas] = ane_tabla_respuestas($bd, $f);
?>
  <p><a href="<?= $yo ?>">← Todos los formularios</a></p>
  <div class="barra">
    <h2 class="display"><?= ane_h($f['titulo']) ?></h2>
    <div class="acciones">
      <a class="boton boton--claro" href="?editar=<?= (int) $f['id'] ?>">Editar</a>
      <?php if ($filas): ?><a class="boton" href="?csv=<?= (int) $f['id'] ?>">Descargar CSV</a><?php endif; ?>
    </div>
  </div>

  <div class="cifras">
    <div class="cifra"><b><?= $e['ocupadas'] ?><?= $e['limite'] ? '<span style="font-size:22px;letter-spacing:0;color:var(--gris)"> / ' . $e['limite'] . '</span>' : '' ?></b><span>Confirmadas</span></div>
    <div class="cifra"><b><?= $e['reservas'] ?></b><span>En lista de espera</span></div>
    <div class="cifra"><b><?= $e['limite'] ? $e['quedan'] : '∞' ?></b><span>Plazas libres</span></div>
    <div class="cifra"><?= ane_etiqueta_estado($e) ?><br><span>Estado</span></div>
  </div>

  <section>
    <h2 class="display">Inscritos</h2>
    <p class="nota">Por orden de llegada. Hora de Madrid. <?= $e['reservas'] ? 'Las reservas van al final, en gris.' : '' ?></p>
    <?php if (!$filas): ?>
      <p class="vacio">Todavía no se ha apuntado nadie.</p>
    <?php else: ?>
    <div class="tabla-scroll">
      <table class="resp">
        <tr><th class="n">Nº</th><th>Fecha</th><?php foreach ($cols as $c): ?><th><?= ane_h($c) ?></th><?php endforeach; ?><th></th></tr>
        <?php $n = 0; foreach ($filas as $r): ?>
          <tr class="<?= $r['reserva'] ? 'reserva' : '' ?>">
            <td class="n"><?= ++$n ?></td>
            <td style="white-space:nowrap"><?= ane_h(ane_utc_a_local($r['fecha'], 'd/m H:i')) ?><?= $r['reserva'] ? '<br><span class="estado estado--medio">Reserva</span>' : '' ?></td>
            <?php foreach (array_keys($cols) as $c): ?><td><?= ane_h($r['celdas'][$c] ?? '') ?></td><?php endforeach; ?>
            <td style="white-space:nowrap">
              <?php if ($r['reserva']): ?>
                <form class="linea" method="post"><?= ane_csrf_campo() ?><input type="hidden" name="accion" value="confirmar_reserva">
                  <input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><input type="hidden" name="respuesta" value="<?= $r['id'] ?>">
                  <button class="boton boton--peq" type="submit">Confirmar</button></form>
              <?php endif; ?>
              <form class="linea" method="post" onsubmit="return confirm('¿Borrar esta inscripción?')"><?= ane_csrf_campo() ?>
                <input type="hidden" name="accion" value="borrar_respuesta"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                <input type="hidden" name="respuesta" value="<?= $r['id'] ?>">
                <button class="boton boton--peq boton--peligro" type="submit">Borrar</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <?php if ($filas): ?>
  <section>
    <h2 class="display">Después del evento</h2>
    <p class="nota">El formulario promete borrar los datos un mes después del evento. Descarga antes el CSV si lo necesitas.</p>
    <form method="post" onsubmit="return confirm('¿Borrar las <?= count($filas) ?> inscripciones? No se puede deshacer.')">
      <?= ane_csrf_campo() ?><input type="hidden" name="accion" value="vaciar"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
      <button class="boton boton--peligro" type="submit">Borrar todas las respuestas</button>
    </form>
  </section>
  <?php endif; ?>

<?php else:
    /* ================= LISTADO ================= */
    $forms = $bd->query('SELECT * FROM formularios ORDER BY id DESC')->fetchAll();
    $hace30 = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
    $pendientesBorrar = [];
    foreach ($forms as $f) {
        $st = $bd->prepare('SELECT COUNT(*) FROM form_respuestas WHERE form_id = ?');
        $st->execute([(int) $f['id']]);
        if ($f['cierre'] && $f['cierre'] < $hace30 && (int) $st->fetchColumn() > 0) {
            $pendientesBorrar[] = $f['titulo'];
        }
    }
?>
  <div class="barra">
    <h2 class="display">Formularios</h2>
    <form method="post" class="acciones">
      <?= ane_csrf_campo() ?><input type="hidden" name="accion" value="crear">
      <input type="text" name="titulo" placeholder="Nombre del evento" maxlength="200" required
             style="font:inherit;padding:9px 10px;border:1px solid #C9CDD3;min-width:260px">
      <button class="boton" type="submit">+ Nuevo formulario</button>
    </form>
  </div>

  <?php if ($pendientesBorrar): ?>
    <div class="aviso-datos"><strong>Datos por borrar:</strong> <?= ane_h(implode(', ', $pendientesBorrar)) ?> cerró hace más de un mes
      y aún guarda inscripciones. El formulario promete borrarlas: descarga el CSV si lo necesitas y bórralas desde «Respuestas».</div>
  <?php endif; ?>

  <section>
    <?php if (!$forms): ?>
      <p class="vacio">Todavía no hay formularios. Escribe el nombre del evento arriba y pulsa «Nuevo formulario»:
         empieza con las preguntas de correo, nombre y teléfono ya puestas.</p>
    <?php else: ?>
    <div class="tabla-scroll">
      <table class="lista">
        <tr><th>Formulario</th><th>Estado</th><th>Inscritos</th><th>Cierre</th><th>Enlace</th><th></th></tr>
        <?php foreach ($forms as $f):
            $e = ane_form_estado($bd, $f); ?>
          <tr>
            <td><a class="tit" href="?editar=<?= (int) $f['id'] ?>" style="color:var(--negro)"><?= ane_h($f['titulo']) ?></a></td>
            <td><?= ane_etiqueta_estado($e) ?></td>
            <td style="white-space:nowrap">
              <strong><?= $e['ocupadas'] ?></strong><?= $e['limite'] ? ' / ' . $e['limite'] : '' ?><?= $e['reservas'] ? ' <span style="color:var(--gris)">+' . $e['reservas'] . ' en espera</span>' : '' ?>
              <?php if ($e['limite']): ?><div class="barrita"><div style="width:<?= min(100, round(100 * $e['ocupadas'] / $e['limite'])) ?>%"></div></div><?php endif; ?>
            </td>
            <td style="white-space:nowrap"><?= $f['cierre'] ? ane_h(ane_utc_a_local($f['cierre'], 'd/m/Y H:i')) : '—' ?></td>
            <td><div class="enlace"><code>/f/<?= ane_h($f['token']) ?></code>
              <button type="button" class="boton boton--peq boton--claro" data-url="<?= ane_h(ane_enlace_publico($f)) ?>" onclick="copiar(this)">Copiar</button></div></td>
            <td><div class="acciones">
              <a class="boton boton--peq boton--claro" href="?editar=<?= (int) $f['id'] ?>">Editar</a>
              <a class="boton boton--peq" href="?respuestas=<?= (int) $f['id'] ?>">Respuestas</a>
            </div></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>
  </section>

<?php endif; ?>
</main>

<script>
function copiar(b) {
  var url = b.getAttribute('data-url') || document.getElementById('url').textContent;
  var hecho = function () { var t = b.textContent; b.textContent = '¡Copiado!'; setTimeout(function () { b.textContent = t; }, 1600); };
  if (navigator.clipboard) { navigator.clipboard.writeText(url).then(hecho, function () { prompt('Copia el enlace:', url); }); }
  else { prompt('Copia el enlace:', url); }
}
</script>
</body>
</html>
