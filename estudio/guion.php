<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio · el guión
   ------------------------------------------------------------
   La única pantalla que ve el editor de vídeo: el guión definitivo,
   en lectura. Temas, enlaces, imágenes, archivos y las cuñas de
   patrocinio en su sitio, que las tiene que montar. Los temas
   internos y las notas ni se consultan cuando es él quien mira.

   Para un socio es además el guión de grabación, pensado para
   imprimir, con las notas internas al final.
   ============================================================ */

declare(strict_types=1);
require __DIR__ . '/_comun.php';

$id = (int) ($_GET['id'] ?? 0);
$ep = ane_pod_episodio($bd, $id);
if (!$ep || !ane_pod_puede_ver($bd, $yo_pod, $id)) {
    http_response_code($ep ? 403 : 404);
    ane_pod_cabeza('Guión');
    ane_pod_cabecera('episodios');
    echo '<main class="caja"><section><h2>Aquí no hay nada para ti</h2>'
       . '<p class="nota">O el episodio no existe, o no te lo han compartido.</p>'
       . '<p><a href="/estudio/">← Volver</a></p></section></main>';
    ane_pod_pie();
    exit;
}

/* Temas y patrocinios en su orden. Con el editor delante, los temas internos
   ni se piden a la base. */
$guion = ane_pod_guion($bd, $id, !$es_socio);
$enlaces = ane_pod_enlaces($bd, $id);
$ficheros = ane_pod_ficheros_de($bd, $id);
/* Notas de grabación: el editor solo recibe las marcadas para él */
$notas = ane_pod_notas($bd, $id, !$es_socio);

/* Los botones de grabación, junto al título de cada tema. Solo socios:
     + Nota          apuntar algo en ese tema (bloque 0 = notas generales)
     Pasar al #57    no da tiempo: el tema se va tal cual al siguiente
     ✕               ya no interesa: se descarta (se puede recuperar)
   Los dos últimos, solo en los temas: ni en «Bici o Cepo» ni en las notas. */
function ane_pod_botones_tema(array $ep, int $bloque, bool $esTema, string $titulo = ''): string
{
    global $es_socio;
    if (!$es_socio) {
        return '';
    }
    $html = '<div class="tema-botones noimprime">'
          . '<button type="button" class="tema-boton nota" aria-controls="dn' . $bloque . '" aria-expanded="false"'
          . ' title="Apuntar una nota en este tema">+ Nota</button>';
    if ($esTema) {
        $campos = ane_pod_csrf_campo() . '<input type="hidden" name="fila" value="' . $bloque . '">';
        $siguiente = (int) $ep['numero'] > 0 ? (int) $ep['numero'] + 1 : 0;
        if ($siguiente) {
            $aviso = '¿Pasar «' . ($titulo !== '' ? $titulo : 'este tema') . '» al episodio ' . $siguiente
                   . '? Se va tal cual: enlaces, fotos, archivos y notas.';
            $html .= '<form method="post" action="/estudio/tema.php" class="enlinea form-tema" data-confirma="' . ane_h($aviso) . '">'
                   . $campos . '<input type="hidden" name="accion" value="pasar">'
                   . '<button class="tema-boton pasar" type="submit" title="No da tiempo: pasarlo al episodio ' . $siguiente . '">'
                   . 'Pasar al #' . $siguiente . '</button></form>';
        }
        $html .= '<form method="post" action="/estudio/tema.php" class="enlinea form-tema">'
               . $campos . '<input type="hidden" name="accion" value="descartar">'
               . '<button class="tema-boton descartar" type="submit" title="Descartar el tema (se puede recuperar)"'
               . ' aria-label="Descartar el tema">✕</button></form>';
    }
    return $html . '</div>';
}

/* Las flechas ↑ ↓ de un tema, debajo de su número: sube o baja un puesto
   sobre la marcha (salta cuñas, no descartados). Solo socios. */
function ane_pod_flechas_tema(int $bloque): string
{
    global $es_socio;
    if (!$es_socio) {
        return '';
    }
    $html = '<div class="flechas noimprime">';
    foreach (['subir' => '↑', 'bajar' => '↓'] as $accion => $flecha) {
        $html .= '<form method="post" action="/estudio/tema.php" class="form-mover">' . ane_pod_csrf_campo()
               . '<input type="hidden" name="accion" value="' . $accion . '">'
               . '<input type="hidden" name="fila" value="' . $bloque . '">'
               . '<button class="flecha" type="submit" data-hacia="' . $accion . '" aria-label="'
               . ($accion === 'subir' ? 'Subir' : 'Bajar') . ' el tema un puesto">' . $flecha . '</button></form>';
    }
    return $html . '</div>';
}

/* El formulario de «+ Nota»: se abre debajo del título al pulsar el botón.
   Pulsas, escribes y la nota se queda en ese tema, sin elegir nada. */
function ane_pod_boton_nota(int $episodio, int $bloque): void
{
    global $es_socio;
    if (!$es_socio) {
        return;
    }
    ?>
          <details class="nota-tema noimprime" id="dn<?= $bloque ?>">
            <summary>+ Nota</summary>
            <form method="post" action="/estudio/nota.php" class="form-nota">
              <?= ane_pod_csrf_campo() ?>
              <input type="hidden" name="accion" value="nueva">
              <input type="hidden" name="id" value="<?= $episodio ?>">
              <input type="hidden" name="bloque" value="<?= $bloque ?>">
              <textarea name="texto" maxlength="2000" rows="3" aria-label="Qué hay que apuntar"
                        placeholder="Qué hay que apuntar"></textarea>
              <input type="url" name="url" maxlength="800" placeholder="Enlace (opcional)" inputmode="url" aria-label="Enlace">
              <div class="barra" style="margin:0">
                <label class="cas"><input type="checkbox" name="privada" value="1"> Que no la vea el editor</label>
                <div class="acciones">
                  <span class="nota-estado" role="status"></span>
                  <button class="btn azul pequeno" type="submit">Apuntar</button>
                </div>
              </div>
            </form>
          </details>
    <?php
}

/* Las notas de un tema (o las generales, bloque 0). Al socio siempre se le
   pinta la lista, aunque esté vacía, para que «+ Nota» tenga dónde dejarlas. */
function ane_pod_pinta_notas(array $lista, int $bloque): void
{
    global $es_socio;
    if (!$lista && !$es_socio) {
        return;
    }
    echo '<ul class="notas-grab" id="nb' . $bloque . '">';
    foreach ($lista as $nota) {
        echo ane_pod_html_nota($nota);
    }
    echo '</ul>';
}

$ocultos = 0;
$descartados = 0;
$patrocinios = 0;
foreach ($guion as $pz) {
    if ($pz['clase'] === 'tema') {
        $descartados += (int) $pz['fila']['descartado'];
        $ocultos += (int) $pz['fila']['interno'] && !(int) $pz['fila']['descartado'];
    } elseif ($pz['clase'] === 'patrocinio') {
        $patrocinios++;
    }
}

/* Numeración de los temas: la sección de cierre va aparte */
$n = 0;

function ane_pod_pinta_enlaces(array $lista): void
{
    if (!$lista) {
        return;
    }
    echo '<ul class="enlaces">';
    foreach ($lista as $e) {
        $url = ane_pod_url((string) $e['url']);
        if ($url === '') {
            continue;
        }
        $texto = $e['titulo'] !== '' ? $e['titulo'] : $url;
        echo '<li>→ <a href="' . ane_h($url) . '" target="_blank" rel="noopener noreferrer">' . ane_h($texto) . '</a>'
           . ($e['titulo'] !== '' ? ' <span class="dom">' . ane_h(ane_pod_dominio($url)) . '</span>' : '')
           . '</li>';
    }
    echo '</ul>';
}

/* Cada foto va con su descripción al lado. De todas se puede tirar el original. */
function ane_pod_pinta_fotos(array $fotos): void
{
    if (!$fotos) {
        return;
    }
    echo '<div class="parejas">';
    foreach ($fotos as $f) {
        $i = (int) $f['id'];
        echo '<div class="pareja lectura">'
           . '<figure><a href="' . ane_pod_url_fichero($f) . '" target="_blank" rel="noopener">'
           . '<img src="' . ane_pod_url_fichero($f) . '" alt="' . ane_h(mb_strimwidth((string) $f['pie'], 0, 80, '…'))
           . '" loading="lazy"></a>'
           . '<figcaption><a class="descarga" href="' . ane_pod_url_fichero($f, true) . '">Descargar</a></figcaption>'
           . '</figure>'
           . '<div class="cuerpo">' . ($f['pie'] !== '' ? ane_pod_texto((string) $f['pie']) : '') . '</div>'
           . '</div>';
    }
    echo '</div>';
}

/* Lo que no es imagen: lista para descargar */
function ane_pod_pinta_archivos(array $lista): void
{
    if (!$lista) {
        return;
    }
    echo '<ul class="archivos lectura">';
    foreach ($lista as $f) {
        echo '<li>↓ <a href="' . ane_pod_url_fichero($f, true) . '">' . ane_h($f['nombre']) . '</a>'
           . ' <span class="dom">' . ane_h(ane_pod_tamano((int) $f['tamano'])) . '</span>'
           . ($f['pie'] !== '' ? ' <span class="dom">· ' . ane_h($f['pie']) . '</span>' : '')
           . '</li>';
    }
    echo '</ul>';
}

ane_pod_cabeza('Guión · ' . ane_pod_etiqueta($ep));
ane_pod_cabecera('episodios');
?>
<main class="caja">
  <?php ane_pod_aviso(['nota' => 'Nota apuntada.', 'nota_borrada' => 'Nota borrada.',
                       'nota_editor' => 'Cambiado quién ve la nota.',
                       'nota_url' => 'Ese enlace no parece una dirección válida.',
                       'nota_vacia' => 'Escribe algo o pega un enlace.',
                       'pasado' => 'Tema pasado al episodio ' . (int) ($_GET['a'] ?? 0) . '.',
                       'descartado' => 'Tema descartado.', 'recuperado' => 'Tema recuperado.',
                       'sin_siguiente' => 'Este episodio no tiene número: no hay «siguiente» al que pasarlo.']); ?>
  <div class="barra noimprime">
    <p class="nota" style="margin:0"><a href="/estudio/">← <?= $es_socio ? 'Episodios' : 'Mis guiones' ?></a></p>
    <div class="acciones">
      <?php if ($es_socio): ?>
        <a class="btn claro pequeno" href="/estudio/episodio.php?id=<?= $id ?>">Editar</a>
      <?php endif; ?>
      <button class="btn claro pequeno" type="button" onclick="window.print()">Imprimir</button>
    </div>
  </div>

  <article class="guion">
    <div class="cab">
      <?php /* Sin título, el número hace de título y no se repite arriba */ ?>
      <?php if ((int) $ep['numero'] && $ep['titulo'] !== ''): ?>
        <p class="numero display">Episodio <?= (int) $ep['numero'] ?></p>
      <?php endif; ?>
      <h1 class="display"><?= ane_h($ep['titulo'] !== '' ? $ep['titulo'] : ane_pod_etiqueta($ep)) ?></h1>
      <p class="nota" style="margin:0">
        <?php if ($sale = ane_pod_sale($ep)): ?><b><?= ane_pod_ya_salio($ep) ? 'Salió el ' : 'Sale el ' ?><?= ane_pod_dia_corto($sale) ?></b> · <?php endif; ?>
        Grabación: <?= ane_pod_fecha_larga($ep['fecha']) ?>
        <?php if ((int) $ep['duracion']): ?> · <?= (int) $ep['duracion'] ?> min previstos<?php endif; ?>
        <?php if ($ep['lugar'] !== ''): ?> · <?= ane_h($ep['lugar']) ?><?php endif; ?>
      </p>
    </div>

    <?php if (count($guion) <= 1): ?>
      <p class="vacio">El guión está todavía en blanco.</p>
    <?php endif; ?>

    <?php foreach ($guion as $pz):
        $b = $pz['fila'];
        if ($pz['clase'] === 'patrocinio'): ?>
      <div class="bloque patro<?= (int) $b['hecho'] ? ' hecho' : '' ?>">
        <div class="min"><?= (int) $b['hecho'] ? '✓' : '€' ?></div>
        <div>
          <h3><span class="chip interno">Patrocinio</span> <?= ane_h($b['marca']) ?>
            <span class="dom"><?= ($tt = ane_pod_tipos_texto((string) $b['tipo'])) !== '' ? '· ' . ane_h($tt) : '' ?><?= (int) $b['hecho'] ? ' · hecho' : '' ?></span></h3>
          <div class="cuerpo"><?= ane_pod_texto($b['texto']) ?></div>
        </div>
      </div>
      <?php continue; endif;
        $bid = (int) $b['id'];
        $esCierre = $pz['clase'] === 'cierre';
        /* Descartado: una línea tachada, sin número (solo socios: al editor ni le llega) */
        if (!$esCierre && (int) $b['descartado']) {
            echo ane_pod_html_descartado($b);
            continue;
        }
        if (!$esCierre) {
            $n++;
        } ?>
      <div class="bloque<?= $esCierre ? ' cierre' : ' es-tema' . ((int) $b['interno'] ? ' esinterno' : '') ?>" id="b<?= $bid ?>">
        <div class="min"><?php if ($esCierre): ?>★<?php else: ?><span class="num"><?= $n ?></span><?= ane_pod_flechas_tema($bid) ?><?php endif; ?></div>
        <div>
          <div class="tema-cab">
          <h3><?= ane_h($esCierre ? ANE_POD_CIERRE_TITULO
                                  : ($b['titulo'] !== '' ? $b['titulo'] : 'Tema ' . $n)) ?>
            <?php if ((int) $b['interno']): ?> <span class="chip interno">no compartido</span><?php endif; ?></h3>
          <?= ane_pod_botones_tema($ep, $bid, !$esCierre, (string) $b['titulo']) ?>
          </div>
          <?php ane_pod_boton_nota($id, $bid); ?>
          <?php $fotos = ane_pod_solo($ficheros[$bid] ?? [], 'imagen');
                $archivos = ane_pod_solo($ficheros[$bid] ?? [], 'archivo');
                /* En «Bici o Cepo» cada foto va con su descripción: ahí no hay texto de bloque */
                if ($esCierre) {
                    ane_pod_pinta_fotos($fotos);
                } ?>
          <div class="cuerpo"><?= ane_pod_texto($b['texto']) ?></div>
          <?php ane_pod_pinta_enlaces($enlaces[$bid] ?? []); ?>
          <?php if (!$esCierre) { ane_pod_pinta_fotos($fotos); } ?>
          <?php ane_pod_pinta_archivos($archivos); ?>
          <?php ane_pod_pinta_notas($notas[$bid] ?? [], $bid); ?>
        </div>
      </div>
    <?php endforeach; ?>

    <?php /* Notas generales de la grabación (las de un tema van con su tema) */
      $generales = $notas[0] ?? [];
      if ($generales || $es_socio): ?>
      <div class="bloque notas" id="notas">
        <div class="min">✎</div>
        <div><div class="tema-cab"><h3>Notas de grabación</h3><?= ane_pod_botones_tema($ep, 0, false) ?></div>
          <?php ane_pod_boton_nota($id, 0); ?>
          <?php if (!$generales && $es_socio): ?><p class="dom noimprime" style="margin:0">Lo que no va con ningún tema.</p><?php endif; ?>
          <?php ane_pod_pinta_notas($generales, 0); ?></div>
      </div>
    <?php endif; ?>

    <?php if ($es_socio && $ep['notas'] !== ''): ?>
      <div class="bloque esinterno">
        <div class="min">!</div>
        <div><h3>Notas internas <span class="chip interno">solo socios</span></h3>
          <div class="cuerpo"><?= ane_pod_texto($ep['notas']) ?></div></div>
      </div>
    <?php endif; ?>
  </article>

  <?php if ($es_socio): ?>
    <script>
      /* «+ Nota» de cada tema: se guarda sin recargar, la nota aparece en su tema
         y sigues leyendo donde estabas. Sin JavaScript, el formulario se envía
         normal y vuelve al guión. */
      (function () {
        /* El botón «+ Nota» de cada tema abre y cierra su formulario */
        document.querySelectorAll('.tema-boton.nota').forEach(function (boton) {
          var caja = document.getElementById(boton.getAttribute('aria-controls'));
          boton.addEventListener('click', function () { caja.open = !caja.open; });
          caja.addEventListener('toggle', function () { boton.setAttribute('aria-expanded', caja.open ? 'true' : 'false'); });
        });

        /* «Pasar al #57» y «✕»: sin recargar. El tema se cambia por una línea
           que dice adónde fue (o tachado, con «Recuperar») y los temas que
           quedan se vuelven a numerar */
        function renumera() {
          document.querySelectorAll('.bloque.es-tema > .min .num').forEach(function (m, i) { m.textContent = i + 1; });
          actualizaFlechas();
        }

        /* ↑ ↓: el vecino de un tema es la primera pieza visible a su lado (tema
           o cuña); las líneas de descartado y de «pasa al…» no cuentan. Es la
           misma regla que aplica el servidor (ane_pod_sube_baja_tema). */
        function cuenta(el) {
          return el && el.classList.contains('bloque') && !el.classList.contains('descartado') &&
                 !el.classList.contains('movido') && (el.classList.contains('es-tema') || el.classList.contains('patro'));
        }
        function vecino(el, hacia) {
          var v = hacia === 'subir' ? el.previousElementSibling : el.nextElementSibling;
          while (v && v.classList.contains('bloque') && !cuenta(v)) {
            if (v.classList.contains('cierre')) { return null; }   /* Bici o Cepo: de ahí no se pasa */
            v = hacia === 'subir' ? v.previousElementSibling : v.nextElementSibling;
          }
          return cuenta(v) ? v : null;
        }
        function actualizaFlechas() {
          document.querySelectorAll('.bloque.es-tema').forEach(function (b) {
            b.querySelectorAll('.flecha').forEach(function (f) { f.disabled = !vecino(b, f.dataset.hacia); });
          });
        }
        document.querySelectorAll('form.form-mover').forEach(function (form) {
          form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var bloque = form.closest('.bloque');
            var hacia = form.querySelector('.flecha').dataset.hacia;
            var otro = vecino(bloque, hacia);
            if (!otro) { return; }
            var flechas = bloque.querySelectorAll('.flecha');
            flechas.forEach(function (f) { f.disabled = true; });
            fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Estudio': 'fetch' },
                                 credentials: 'same-origin' })
              .then(function (r) { return r.json(); })
              .then(function (d) {
                if (!d.ok) { throw new Error(); }
                if (d.movido) {
                  if (hacia === 'subir') { otro.before(bloque); } else { otro.after(bloque); }
                  bloque.classList.remove('recien'); void bloque.offsetWidth; bloque.classList.add('recien');
                  bloque.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
                renumera();
              })
              .catch(function () { actualizaFlechas(); alert('Sin conexión: no se ha movido.'); });
          });
        });
        actualizaFlechas();
        document.querySelectorAll('form.form-tema').forEach(function (form) {
          form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            if (form.dataset.confirma && !confirm(form.dataset.confirma)) { return; }
            var boton = form.querySelector('button');
            boton.disabled = true;
            fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Estudio': 'fetch' },
                                 credentials: 'same-origin' })
              .then(function (r) { return r.json(); })
              .then(function (d) {
                if (!d.ok) { boton.disabled = false; alert(d.error || 'No se ha podido.'); return; }
                form.closest('.bloque').outerHTML = d.html;
                renumera();
              })
              .catch(function () { boton.disabled = false; alert('Sin conexión: no se ha hecho.'); });
          });
        });

        document.querySelectorAll('form.form-nota').forEach(function (form) {
          var estado = form.querySelector('.nota-estado');
          var caja = form.closest('details');
          caja.addEventListener('toggle', function () { if (caja.open) { form.texto.focus(); } });
          form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            estado.textContent = 'Guardando…';
            fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Estudio': 'fetch' },
                                 credentials: 'same-origin' })
              .then(function (r) { return r.json(); })
              .then(function (d) {
                if (!d.ok) { estado.textContent = d.error || 'No se ha podido guardar.'; return; }
                var lista = document.getElementById('nb' + d.bloque) || document.getElementById('nb0');
                lista.insertAdjacentHTML('beforeend', d.html);
                form.texto.value = ''; form.url.value = ''; form.privada.checked = false;
                estado.textContent = '✓ Apuntada';
                setTimeout(function () { estado.textContent = ''; caja.open = false; }, 900);
              })
              .catch(function () { estado.textContent = 'Sin conexión: no se ha guardado.'; });
          });
        });
      })();
    </script>
  <?php endif; ?>

  <?php if ($es_socio): ?>
    <p class="nota noimprime" style="margin-top:14px">
      <?php
        $fuera = ['las notas privadas'];
        if ($ocultos) {
            $fuera[] = $ocultos === 1 ? 'el tema interno' : "los $ocultos temas internos";
        }
        if ($descartados) {
            $fuera[] = $descartados === 1 ? 'el tema descartado' : "los $descartados temas descartados";
        }
        $ultimo = array_pop($fuera);
        echo ane_h(ucfirst(($fuera ? implode(', ', $fuera) . ' y ' : '') . $ultimo));
      ?> no salen en la versión que ve el editor.
      <a href="/estudio/episodio.php?id=<?= $id ?>#accesos">Gestionar quién lo ve →</a>
    </p>
  <?php endif; ?>
</main>
<?php
ane_pod_pie();
