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
           . '<figure><a href="/estudio/fichero.php?f=' . $i . '" target="_blank" rel="noopener">'
           . '<img src="/estudio/fichero.php?f=' . $i . '" alt="' . ane_h(mb_strimwidth((string) $f['pie'], 0, 80, '…'))
           . '" loading="lazy"></a>'
           . '<figcaption><a class="descarga" href="/estudio/fichero.php?f=' . $i . '&amp;d=1">Descargar</a></figcaption>'
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
        echo '<li>↓ <a href="/estudio/fichero.php?f=' . (int) $f['id'] . '&amp;d=1">' . ane_h($f['nombre']) . '</a>'
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
        <?= ane_pod_fecha_larga($ep['fecha']) ?>
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
            <span class="dom">· <?= ane_h(ANE_POD_MENCION_TIPOS[$b['tipo']] ?? $b['tipo']) ?><?= (int) $b['hecho'] ? ' · hecho' : '' ?></span></h3>
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
        </div>
      </div>
    <?php endforeach; ?>

    <?php if ($es_socio && $ep['notas'] !== ''): ?>
      <div class="bloque esinterno">
        <div class="min">!</div>
        <div><h3>Notas internas <span class="chip interno">solo socios</span></h3>
          <div class="cuerpo"><?= ane_pod_texto($ep['notas']) ?></div></div>
      </div>
    <?php endif; ?>
  </article>

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
