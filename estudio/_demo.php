<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio · datos de demostración
   ------------------------------------------------------------
   Rellena una base DE PRUEBA para ver el estudio en local:

     ANE_DATOS=/tmp/ane-demo php -f estudio/_demo.php
     ANE_DATOS=/tmp/ane-demo php -S 127.0.0.1:8787 -t .

   Borra y reescribe todas las tablas pod_* (y las imágenes),
   así que se niega a correr si no le han dicho dónde está la
   base (sin ANE_DATOS iría a ~/datos, que es la de verdad) o si
   lo llama la web.

   Usuarios: eduardo y cristobal (socios), jose.edicion (editor
   de vídeo, solo ve el guión de un episodio).
   Contraseña de los tres: demo-estudio-2026
   ============================================================ */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo por línea de órdenes.');
}
if (!getenv('ANE_DATOS')) {
    exit("Falta ANE_DATOS. Esto borra datos: dile dónde está la base de pruebas.\n"
       . "  ANE_DATOS=/tmp/ane-demo php -f estudio/_demo.php\n");
}

require dirname(__DIR__) . '/api/_estudio.php';

$bd = ane_pod_bd();
foreach (['pod_accesos', 'pod_menciones', 'pod_ficheros', 'pod_enlaces', 'pod_bloques', 'pod_episodios',
          'pod_proy_marcas', 'pod_proyectos', 'pod_marcas', 'pod_usuarios'] as $t) {
    $bd->exec("DELETE FROM $t");
}
foreach (glob(ane_pod_carpeta_imagenes() . '/*') ?: [] as $f) {
    @unlink($f);   /* las imágenes de la demo anterior */
}
$ahora = ane_ahora();

/* ---- Usuarios ---- */
$usuarios = [
    ['eduardo', 'Eduardo', 'socio', ''],
    ['cristobal', 'Cristóbal', 'socio', ''],
    ['jose.edicion', 'José', 'editor', 'Monta los vídeos. Solo ve guiones'],
];
$st = $bd->prepare('INSERT INTO pod_usuarios (usuario, nombre, hash, rol, nota, creado, ultimo) VALUES (?,?,?,?,?,?,?)');
foreach ($usuarios as [$u, $n, $r, $nota]) {
    $st->execute([$u, $n, password_hash('demo-estudio-2026', PASSWORD_DEFAULT), $r, $nota, $ahora,
                  $u === 'eduardo' ? $ahora : null]);
}
$ids = $bd->query('SELECT usuario, id FROM pod_usuarios')->fetchAll(PDO::FETCH_KEY_PAIR);

/* ---- Marcas ---- */
$marcas = [
    ['Gobik', 'Laura Pérez', 'laura@gobik.com', '', 'confirmado', 'Firmar el acuerdo del mono', '2026-10-24',
     'Entran en Cape Epic con el mono completo. Quieren dos reels por etapa.'],
    ['Bicilab', 'Jorge', 'jorge@bicilab.es', '600 11 22 33', 'negociando', 'Llamar con el presupuesto de ANE House', '2026-10-22',
     'Interesados en el Social Ride y en la casa del Tour.'],
    ['klinpig', 'Nacho', 'hola@klinpig.com', '', 'contactado', 'Mandar dossier con las cifras del Tour', '2026-10-27', ''],
    ['CMS', 'Ana', 'ana@cms.es', '', 'facturado', 'Nada: cobrado', null, 'Patrocinio de las cuñas del trimestre.'],
    ['Decathlon', '', '', '', 'descartado', '', null, 'No entran este año.'],
];
$st = $bd->prepare('INSERT INTO pod_marcas (nombre, contacto, correo, telefono, estado, siguiente, seguimiento, notas, creado, actualizado)
                    VALUES (?,?,?,?,?,?,?,?,?,?)');
foreach ($marcas as $m) {
    $st->execute(array_merge($m, [$ahora, $ahora]));
}
$mid = $bd->query('SELECT nombre, id FROM pod_marcas')->fetchAll(PDO::FETCH_KEY_PAIR);

/* ---- Proyectos ---- */
$proyectos = [
    ['Cape Epic 2027', 'Marzo 2027 · Sudáfrica', 'abierto', 32000, 28000, 10,
     'Ocho etapas con Eduardo y Cristóbal. Episodio diario post etapa y vlogs sueltos.'],
    ['ANE House 2027', 'Julio 2027 · Tour de Francia', 'abierto', 24000, 19000, 20,
     'Casa en el Tour: episodio post etapa con invitados y vlogs.'],
    ['Mediterranean Epic', 'Febrero 2027 · Castellón', 'idea', 6000, 4500, 30, ''],
];
$st = $bd->prepare('INSERT INTO pod_proyectos (nombre, detalle, estado, objetivo, coste, orden, descripcion, creado, actualizado)
                    VALUES (?,?,?,?,?,?,?,?,?)');
foreach ($proyectos as $p) {
    $st->execute(array_merge($p, [$ahora, $ahora]));
}
$pid = $bd->query('SELECT nombre, id FROM pod_proyectos')->fetchAll(PDO::FETCH_KEY_PAIR);

$st = $bd->prepare('INSERT INTO pod_proy_marcas (proyecto_id, marca_id, marca, estado, importe, contrapartidas, notas)
                    VALUES (?,?,?,?,?,?,?)');
$st->execute([$pid['Cape Epic 2027'], $mid['Gobik'], 'Gobik', 'confirmado', 12000,
              'Pecho del mono · dos menciones por episodio · un reel por etapa', '']);
$st->execute([$pid['Cape Epic 2027'], $mid['Bicilab'], 'Bicilab', 'negociando', 6000,
              'Espalda alta · mención en la intro', 'Pendiente de que cuadren el presupuesto de marzo.']);
$st->execute([$pid['Cape Epic 2027'], $mid['klinpig'], 'klinpig', 'contactado', 3000, 'Manga izquierda', '']);
$st->execute([$pid['ANE House 2027'], $mid['CMS'], 'CMS', 'confirmado', 9000,
              'Cuña leída en los 8 episodios de la casa', '']);
$st->execute([$pid['ANE House 2027'], $mid['Bicilab'], 'Bicilab', 'negociando', 7000, 'Presencia en la casa', '']);

/* ---- Episodios ---- */
$episodios = [
    [49, '¿La creatina sirve en ciclismo?', '+3 days 18:00', 75, 'Estudio · Valencia', '',
     'Confirmar los estudios que vamos a citar. Pedirle a Cristóbal las fotos de la bici antes del martes.'],
    [48, 'Pidcock, ruedas del mundial y Decathlon', '-16 days 19:30', 68, 'Estudio · Valencia', 'gy5RLwGDFs8',
     'Subido el 4 oct. Buen rendimiento del bloque de ruedas.'],
    [0, 'Preparar un maratón de MTB sin morir', null, 0, '', '',
     'Idea de Cristóbal. Encaja antes de Mediterranean Epic.'],
];
$st = $bd->prepare('INSERT INTO pod_episodios (numero, titulo, fecha, duracion, lugar, youtube_id, notas, creado, actualizado)
                    VALUES (?,?,?,?,?,?,?,?,?)');
foreach ($episodios as $e) {
    $fecha = $e[2] ? gmdate('Y-m-d H:i:s', strtotime($e[2] . ' Europe/Madrid')) : null;
    $st->execute([$e[0], $e[1], $fecha, $e[3], $e[4], $e[5], $e[6], $ahora, $ahora]);
}
$eid = $bd->query('SELECT titulo, id FROM pod_episodios')->fetchAll(PDO::FETCH_KEY_PAIR);
$ep1 = (int) $eid['¿La creatina sirve en ciclismo?'];
$ep2 = (int) $eid['Pidcock, ruedas del mundial y Decathlon'];

/* ---- Guión del episodio de la creatina ---- */
$bloques = [
    [10, 'Arranque y presentación', "Bienvenida de siempre y por qué traemos el tema.\n\n**Pregunta de entrada:** ¿por qué la creatina tiene fama de cosa de gimnasio?", 0],
    [20, 'Qué dice la evidencia', "- Qué es y cómo actúa, en dos minutos y sin química\n- Lo que está demostrado en fuerza y en sprints\n- Lo que NO está demostrado en resistencia larga\n- El meta-análisis de 2024 y por qué sus conclusiones son prudentes", 0],
    [30, 'Dosis, carga y mitos', "- Carga rápida o dosis baja mantenida\n- Agua, peso y la idea de «hinchazón»\n- Mitos: riñones, calvicie, calambres\n- Qué pasa si lo dejas", 0],
    [40, 'Caso práctico: Cape Epic', 'Cómo lo plantearíamos para ocho días seguidos de maratón. Aquí entra la experiencia de Cristóbal.', 0],
    [50, 'Preguntas de los oyentes', "Tres preguntas que llegaron por el buscador de la web:\n- «creatina y ayuno»\n- «creatina en mujeres»\n- «marcas baratas»", 0],
    [60, 'Cierre', 'Recordatorio del buscador de la web y despedida.', 0],
    [70, 'Recordatorios de producción', "- Micro de Cristóbal: probar el de mano, el de solapa dio problemas\n- Cortar el bloque de mitos si pasamos de 75 min", 1],
];
$st = $bd->prepare("INSERT INTO pod_bloques (episodio_id, orden, tipo, titulo, texto, interno)
                    VALUES (?,?,'tema',?,?,?)");
foreach ($bloques as $b) {
    $st->execute(array_merge([$ep1], $b));
}
$bid = $bd->query("SELECT titulo, id FROM pod_bloques WHERE episodio_id = $ep1")->fetchAll(PDO::FETCH_KEY_PAIR);

/* Enlaces: varios por tema */
$enlaces = [
    ['Qué dice la evidencia', 'Meta-análisis 2024 · creatina y resistencia', 'https://pubmed.ncbi.nlm.nih.gov/'],
    ['Qué dice la evidencia', 'Posición del ACSM sobre suplementos', 'https://www.acsm.org/'],
    ['Qué dice la evidencia', '', 'https://www.ncbi.nlm.nih.gov/'],
    ['Dosis, carga y mitos', 'La hoja de dosis que usamos', 'https://docs.google.com/'],
    ['Dosis, carga y mitos', 'El estudio de los calambres', 'https://pubmed.ncbi.nlm.nih.gov/'],
    ['Preguntas de los oyentes', 'Lo que ya hemos dicho de creatina', 'https://aquinadieentrena.cc/?q=creatina'],
];
$st = $bd->prepare('INSERT INTO pod_enlaces (episodio_id, bloque_id, titulo, url, orden) VALUES (?,?,?,?,?)');
foreach ($enlaces as $i => $e) {
    $st->execute([$ep1, (int) $bid[$e[0]], $e[1], $e[2], ($i + 1) * 10]);
}

/* La sección fija de cierre: aquí cada foto lleva su propia descripción */
$cierre = ane_pod_asegura_cierre($bd, $ep1);

/* ---- Guión del episodio ya publicado ---- */
$st = $bd->prepare("INSERT INTO pod_bloques (episodio_id, orden, tipo, titulo, texto, interno)
                    VALUES (?,?,'tema',?,?,0)");
$st->execute([$ep2, 10, 'Actualidad', 'Pidcock y su temporada. Qué esperamos de él en 2027.']);
$st->execute([$ep2, 20, 'Las ruedas del mundial', 'Perfil, altura y por qué se habló tanto de ellas.']);
$st->execute([$ep2, 30, 'Decathlon', 'La evolución de su gama y qué compraríamos hoy.']);
$bd->prepare('INSERT INTO pod_enlaces (episodio_id, bloque_id, titulo, url, orden) VALUES (?,?,?,?,10)')
   ->execute([$ep2, (int) $bd->query("SELECT id FROM pod_bloques WHERE episodio_id = $ep2 AND orden = 20")->fetchColumn(),
              'El reglamento de la UCI', 'https://www.uci.org/']);
ane_pod_asegura_cierre($bd, $ep2);

/* ---- Menciones del episodio de la creatina ---- */
$menciones = [
    [$mid['Gobik'], 'Gobik', 'cuna', 'Al arrancar', "Cuña de 30 s: mono de Cape Epic, código ANE15. **No decir precios.**", 0, 10],
    [$mid['CMS'], 'CMS', 'integrada', 'Con el tema de Cape Epic', 'Mencionar que el seguimiento de la casa lo lleva CMS.', 0, 20],
    [$mid['Bicilab'], 'Bicilab', 'mencion', 'En el cierre', 'Social Ride del 15 de noviembre: plazas por la web.', 1, 30],
];
$st = $bd->prepare('INSERT INTO pod_menciones (episodio_id, marca_id, marca, tipo, momento, texto, hecho, orden)
                    VALUES (?,?,?,?,?,?,?,?)');
foreach ($menciones as $m) {
    $st->execute(array_merge([$ep1], $m));
}

/* ---- Fotos de ejemplo en «Bici o Cepo» (se dibujan aquí, no hace falta traerlas) ---- */
if (function_exists('imagecreatetruecolor')) {
    $pies = [
        "**La Oiz entera, de perfil.** Orbea Oiz de 2019, talla M, con el cuadro bastante bien para los años que tiene.\n"
        . "- Tija telescópica puesta después, se nota en el cableado\n- Manillar de 760, para su altura se queda corto",
        "**La transmisión.** Aquí está el problema: plato y cassette de marcas distintas y la cadena pide el cambio ya.\n"
        . "Preguntarle cuántos kilómetros lleva sin tocarla.",
        "**Las ruedas, lo mejor de la bici.** Llantas de carbono que valen más que el resto del conjunto.\n"
        . "Si vende la bici, que las quite.",
    ];
    foreach ($pies as $i => $pie) {
        $im = imagecreatetruecolor(900, 600);
        imagefilledrectangle($im, 0, 0, 900, 600, imagecolorallocate($im, 25 + $i * 60, 40 + $i * 30, 60 + $i * 50));
        imagefilledrectangle($im, 40, 40, 860, 560, imagecolorallocate($im, 63, 119, 218));
        imagestring($im, 5, 60, 70, 'Foto de ejemplo ' . ($i + 1), imagecolorallocate($im, 255, 255, 255));
        $tmp = tempnam(sys_get_temp_dir(), 'anedemo');
        imagejpeg($im, $tmp, 85);
        imagedestroy($im);

        $nombre = bin2hex(random_bytes(16)) . '.jpg';
        copy($tmp, ane_pod_carpeta_imagenes() . '/' . $nombre);
        @unlink($tmp);
        $bd->prepare('INSERT INTO pod_ficheros (episodio_id, bloque_id, tipo, fichero, nombre, mime, tamano, pie, orden, creado)
                      VALUES (?,?,\'imagen\',?,?,?,?,?,?,?)')
           ->execute([$ep1, $cierre, $nombre, 'bici-oyente-' . ($i + 1) . '.jpg', 'image/jpeg',
                      (int) filesize(ane_pod_carpeta_imagenes() . '/' . $nombre), $pie, ($i + 1) * 10, $ahora]);
    }
}

/* Dos fotos más, en un tema normal: un tema admite tantas como quieras */
if (function_exists('imagecreatetruecolor')) {
    $tema = (int) $bid['Dosis, carga y mitos'];
    foreach (['**La tabla de dosis**, para ponerla en pantalla mientras lo contamos.',
              '**El bote**, para que se vea el formato del que hablamos.'] as $i => $pie) {
        $im = imagecreatetruecolor(900, 600);
        imagefilledrectangle($im, 0, 0, 900, 600, imagecolorallocate($im, 240, 240, 243));
        imagefilledrectangle($im, 60, 60, 840, 540, imagecolorallocate($im, 25, 25, 25));
        imagestring($im, 5, 90, 90, 'Foto del tema ' . ($i + 1), imagecolorallocate($im, 255, 255, 255));
        $tmp = tempnam(sys_get_temp_dir(), 'anedemo');
        imagejpeg($im, $tmp, 85);
        imagedestroy($im);
        $nombre = bin2hex(random_bytes(16)) . '.jpg';
        copy($tmp, ane_pod_carpeta_imagenes() . '/' . $nombre);
        @unlink($tmp);
        $bd->prepare('INSERT INTO pod_ficheros (episodio_id, bloque_id, tipo, fichero, nombre, mime, tamano, pie, orden, creado)
                      VALUES (?,?,\'imagen\',?,?,?,?,?,?,?)')
           ->execute([$ep1, $tema, $nombre, 'tabla-dosis-' . ($i + 1) . '.jpg', 'image/jpeg',
                      (int) filesize(ane_pod_carpeta_imagenes() . '/' . $nombre), $pie, ($i + 1) * 10, $ahora]);
    }
}

/* Un archivo para descargar: lo que el editor necesita bajarse tal cual */
$texto = "1\n00:00:04,000 --> 00:00:07,200\nHoy hablamos de creatina.\n";
$nombre = bin2hex(random_bytes(16)) . '.srt';
file_put_contents(ane_pod_carpeta_imagenes() . '/' . $nombre, $texto);
$bd->prepare('INSERT INTO pod_ficheros (episodio_id, bloque_id, tipo, fichero, nombre, mime, tamano, pie, orden, creado)
              VALUES (?,?,\'archivo\',?,?,?,?,?,10,?)')
   ->execute([$ep1, (int) $bid['Arranque y presentación'], $nombre, 'intro-locutada.srt',
              'application/octet-stream', strlen($texto), 'Los subtítulos de la intro, para el montaje', $ahora]);

/* ---- Acceso del editor: solo el episodio de la creatina ---- */
$bd->prepare('INSERT INTO pod_accesos (usuario_id, episodio_id, creado) VALUES (?,?,?)')
   ->execute([(int) $ids['jose.edicion'], $ep1, $ahora]);

echo 'Sembrado en ' . ANE_DATOS . "/ane.db\n";
