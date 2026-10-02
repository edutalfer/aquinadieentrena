<?php
/* Cape Epic 2027 — subida de fotos (mono del día). Carpeta protegida con contraseña.
   Guarda la imagen reducida a 1600 px en capeepic/media/ (fuera del repo por .gitignore). */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

function salir(int $codigo, array $d): void { http_response_code($codigo); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') salir(405, ['error' => 'Método no permitido']);
$f = $_FILES['foto'] ?? null;
if (!$f || $f['error'] !== UPLOAD_ERR_OK) salir(400, ['error' => 'No ha llegado ninguna foto']);
if ($f['size'] > 25 * 1024 * 1024) salir(413, ['error' => 'La foto pesa más de 25 MB']);

$dir = __DIR__ . '/media';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$nombre = bin2hex(random_bytes(8)) . '.jpg';

try {
    $im = new Imagick($f['tmp_name']);
    if (method_exists($im, 'autoOrient')) $im->autoOrient();
    $im->setImageBackgroundColor('white');
    $im = $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
    $im->thumbnailImage(1600, 1600, true);
    $im->setImageFormat('jpeg');
    $im->setImageCompressionQuality(82);
    $im->stripImage();
    $im->writeImage("$dir/$nombre");
} catch (Throwable $e) {
    salir(400, ['error' => 'No se ha podido leer la imagen. Prueba con una foto JPG o PNG.']);
}
salir(200, ['url' => 'media/' . $nombre]);
