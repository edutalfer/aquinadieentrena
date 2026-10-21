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
$patrocinios = 0;
foreach ($guion as $pz) {
    if ($pz['clase'] === 'tema') {
        $ocultos += (int) $pz['fila']['interno'];
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
                       'nota_vacia' => 'Escribe algo o pega un enlace.']); ?>
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
        if (!$esCierre) {
            $n++;
        } ?>
      <div class="bloque<?= $esCierre ? ' cierre' : ((int) $b['interno'] ? ' esinterno' : '') ?>">
        <div class="min"><?= $esCierre ? '★' : $n ?></div>
        <div>
          <h3><?= ane_h($esCierre ? ANE_POD_CIERRE_TITULO
                                  : ($b['titulo'] !== '' ? $b['titulo'] : 'Tema ' . $n)) ?>
            <?php if ((int) $b['interno']): ?> <span class="chip interno">no compartido</span><?php endif; ?></h3>
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
          <?php if (!$esCierre) { ane_pod_pinta_notas($notas[$bid] ?? [], $bid); } ?>
        </div>
      </div>
    <?php endforeach; ?>

    <?php /* Notas generales de la grabación (las de un tema van con su tema) */
      $generales = $notas[0] ?? [];
      if ($generales || $es_socio): ?>
      <div class="bloque notas" id="notas"<?= $generales ? '' : ' hidden' ?>>
        <div class="min">✎</div>
        <div><h3>Notas de grabación</h3><?php ane_pod_pinta_notas($generales, 0); ?></div>
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
    <!-- ---- «+ Nota»: apuntar algo mientras se graba, sin perder el sitio ---- -->
    <details class="nota-rapida noimprime" id="nota-rapida">
      <summary aria-label="Apuntar una nota">+ Nota</summary>
      <form method="post" action="/estudio/nota.php" id="form-nota">
        <?= ane_pod_csrf_campo() ?>
        <input type="hidden" name="accion" value="nueva">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="campo" style="margin:0 0 8px"><label for="nota-texto">Qué hay que apuntar</label>
          <textarea id="nota-texto" name="texto" maxlength="2000" rows="3"
                    placeholder="Lo que se ha dicho y hay que mirar, corregir o poner en pantalla"></textarea></div>
        <div class="campo" style="margin:0 0 8px"><label for="nota-url">Enlace (opcional)</label>
          <input type="url" id="nota-url" name="url" maxlength="800" placeholder="https://…" inputmode="url"></div>
        <div class="campo" style="margin:0 0 8px"><label for="nota-tema">¿De qué tema?</label>
          <select id="nota-tema" name="bloque">
            <option value="0">General</option>
            <?php $k = 0; foreach ($guion as $pz): if ($pz['clase'] !== 'tema') { continue; } $k++; ?>
              <option value="<?= (int) $pz['fila']['id'] ?>"><?= $k ?>. <?= ane_h(mb_strimwidth($pz['fila']['titulo'] !== '' ? $pz['fila']['titulo'] : 'Tema ' . $k, 0, 40, '…')) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="cas" style="margin:0 0 10px"><input type="checkbox" id="nota-editor" name="editor" value="1">
          <label for="nota-editor">Que la vea el editor</label></div>
        <div class="acciones">
          <button class="btn azul" type="submit">Apuntar</button>
          <span class="nota-estado" id="nota-estado" role="status"></span>
        </div>
      </form>
    </details>
    <script>
      /* Apuntar sin recargar: la nota aparece en su sitio y sigues leyendo donde estabas.
         Sin JavaScript, el formulario se envía normal y vuelve al guión. */
      (function () {
        var form = document.getElementById('form-nota');
        var estado = document.getElementById('nota-estado');
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
              var seccion = document.getElementById('notas');
              if (seccion && d.bloque === 0) { seccion.hidden = false; }
              form.texto.value = ''; form.url.value = ''; form.editor.checked = false;
              estado.textContent = '✓ Apuntada';
              setTimeout(function () { estado.textContent = ''; document.getElementById('nota-rapida').open = false; }, 900);
            })
            .catch(function () { estado.textContent = 'Sin conexión: no se ha guardado.'; });
        });
        document.getElementById('nota-rapida').addEventListener('toggle', function () {
          if (this.open) { document.getElementById('nota-texto').focus(); }
        });
      })();
    </script>
  <?php endif; ?>

  <?php if ($es_socio): ?>
    <p class="nota noimprime" style="margin-top:14px">
      <?php
        $fuera = ['las notas'];
        if ($ocultos) {
            $fuera[] = $ocultos === 1 ? 'el tema interno' : "los $ocultos temas internos";
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
