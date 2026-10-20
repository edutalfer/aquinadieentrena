<?php
/* ============================================================
   AQUÍ NADIE ENTRENA — Estudio · dar de alta desde el servidor
   ------------------------------------------------------------
   Crea el primer socio (o cualquier acceso) por línea de órdenes,
   sin abrir ninguna puerta en la web:

     source pipeline/entorno.sh
     php -f estudio/_alta.php eduardo socio "Eduardo"

   Escupe la contraseña UNA vez. A partir de ahí, los demás
   accesos se crean desde la pestaña Accesos del estudio.
   ============================================================ */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo por línea de órdenes.');
}

require dirname(__DIR__) . '/api/_estudio.php';

$usuario = ane_pod_nombre_usuario((string) ($argv[1] ?? ''));
$rol = (string) ($argv[2] ?? 'socio') === 'editor' ? 'editor' : 'socio';
$nombre = ane_recorta((string) ($argv[3] ?? ''), 80);

if (!ane_pod_usuario_valido($usuario)) {
    exit("Uso: php -f estudio/_alta.php <usuario> [socio|editor] [\"Nombre\"]\n"
       . "El usuario admite letras, números, punto, guion y guion bajo (3 a 32).\n");
}

$bd = ane_pod_bd();
if (ane_pod_por_usuario($bd, $usuario)) {
    exit("Ya existe el usuario «$usuario».\n");
}

/* Contraseña legible: 4 bloques de 4 sin caracteres que se confundan */
$abc = 'abcdefghijkmnpqrstuvwxyz23456789';
$partes = [];
for ($b = 0; $b < 4; $b++) {
    $p = '';
    for ($i = 0; $i < 4; $i++) {
        $p .= $abc[random_int(0, strlen($abc) - 1)];
    }
    $partes[] = $p;
}
$clave = implode('-', $partes);

$bd->prepare('INSERT INTO pod_usuarios (usuario, nombre, hash, rol, creado) VALUES (?, ?, ?, ?, ?)')
   ->execute([$usuario, $nombre, password_hash($clave, PASSWORD_DEFAULT), $rol, ane_ahora()]);

echo "Alta hecha en " . ANE_DATOS . "/ane.db\n\n";
echo "  usuario:     $usuario ($rol)\n";
echo "  contraseña:  $clave\n\n";
echo "Entra en https://aquinadieentrena.cc/estudio/ y cámbiala si quieres.\n";
echo "Esta contraseña no se vuelve a enseñar.\n";
