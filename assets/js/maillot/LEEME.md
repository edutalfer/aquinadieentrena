# El mono 3D — cómo se compila

La página `/maillot` carga **un único fichero**: `assets/js/maillot.min.js`.
Es esta carpeta más three.js, empaquetados y minificados con esbuild. No se usa
ninguna CDN externa: todo sale de nuestro servidor.

| Fichero | Qué hace |
|---|---|
| `mono.js` | La forma (función de distancia → malla con surface nets), el equipaje (shader: blanco/negro, cuello, puños, silicona), las zonas de patrocinio (`ZONAS`) como calcas y cómo se pinta cada una |
| `maillot.js` | Escena, luces, giro con el ratón o el dedo, clic en los huecos, selector de proyecto, ficha y envío de ofertas |

Para cambiar la silueta: `PERFIL` (tronco por alturas) y las piezas `MANGAS`,
`PIERNAS`, `DELTOIDES`, `GLUTEOS`. Para cambiar el diseño del mono (dónde
acaba el blanco, colores de puños...): la función `equipaje` del shader.

Las claves de `ZONAS` tienen que coincidir con `ANE_MAILLOT_ZONAS` en
`api/_maillot.php`.

## Recompilar

Con Node instalado, en cualquier carpeta temporal (fuera del repo):

```bash
npm init -y && npm i three@0.186.1 esbuild
NODE_PATH=$PWD/node_modules npx esbuild <repo>/assets/js/maillot/maillot.js \
  --bundle --minify --format=esm --target=es2020 --legal-comments=eof \
  --outfile=<repo>/assets/js/maillot.min.js
```

Luego sube el `?v=` de `maillot.min.js` en `maillot.php`. Pesa ~540 kB
(~140 kB comprimido) y solo lo descarga quien entra en `/maillot`.

Si subes de versión three.js, mira que el mono se sigue viendo igual: las
normales se calculan a mano, pero materiales y tone mapping cambian entre versiones.
