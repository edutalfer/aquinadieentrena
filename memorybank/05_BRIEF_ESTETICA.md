# 🎨 BRIEF — Rediseño estético

> Para el chat dedicado al diseño. Lee antes `03_ESTADO_ACTUAL.md`.

---

## Punto de partida

Eduardo ha visto por fin la versión correcta de la web (durante horas estuvo
viendo la antigua por culpa de la CDN, así que cualquier crítica anterior a
2026-08-17 mediodía no es fiable).

Su valoración: **no le convence, y no una parte concreta sino "todo"**.

---

## Qué hay ahora

**Ficheros:** `index.html` (una sola página con todas las secciones),
`episodios.html`, `assets/css/estilo.css` (~12,5 KB), `assets/js/app.js`.

**Secciones de la home, en orden:**
1. Cabecera negra pegajosa con logo PNG y menú
2. Portada negra: eyebrow, titular "Busca el *minuto* exacto", buscador con
   borde azul de 3px y chips de sugerencias
3. Resultados (oculto hasta que se busca), fondo gris humo
4. Últimos episodios: tarjetas con "perfil de etapa" (gráfico de barras) y
   lista de bloques con marca de tiempo
5. Quiénes somos: dos columnas de texto, fondo gris humo
6. Newsletter: fondo negro + iframe de Beehiiv
7. Manda algo: dos tarjetas (Bici o Cepo azul / Marcas negra) con `mailto:`
8. Pie negro con logo, claim y enlaces

**El CSS está bien construido:** tokens en variables CSS, paleta y tipografías
fieles al manual de marca (`ANE_Brand_Guidelines.pdf` en el proyecto).
Archivo Black Italic para titulares en caja alta, Inter para lectura.
No hay que reescribirlo de cero: hay base sólida.

---

## Diagnóstico de partida (de Claude, a validar con Eduardo)

Cumple el manual a rajatabla pero **le falta carácter**. Es correcto y plano:
todo son bloques rectangulares apilados, muy centrado en texto, **sin una sola
imagen**, y el negro-azul-blanco aplicado de forma literal. Para un podcast que
se define como irreverente y ruidoso, se ve más sobrio de lo que suena.

### Ideas propuestas (ninguna validada aún)

- **Fotos de los hosts.** La sección "Quiénes somos" es solo texto. Dos caras
  cambian la web entera.
- **El logo apilado como recurso gráfico**, no solo como logotipo de esquina:
  gigante, cortado por el borde, de fondo en la portada.
- **Diagonales y solapes.** El logo es un bloque inclinado y superpuesto; la
  web es toda cuadrícula recta. Hay una desconexión entre marca y maqueta.
- **Miniaturas reales de los episodios** en vez del gráfico de barras abstracto.

### Lo que pidió Claude y quedó pendiente de respuesta

Una **web de referencia que le guste**. Suele ser más rápido que describirlo.

---

## Reglas de marca que NO se tocan

(Del manual de identidad, resumido — el PDF completo está en el proyecto)

- **Logotipo**: se usa siempre el archivo original, nunca recompuesto con la
  tipografía. Nada de deformar, rotar, añadir sombras o invadir el área de
  respeto. Azul solo en la palabra NADIE.
- **Paleta**: Azul ANE `#3F77DA`, Negro Pelotón `#191919`, Blanco `#FFFFFF`.
  Sin verdes, sin rojos, sin degradados. Los grises y azules de apoyo están
  en `ANE_PALETA_CROMATICA.txt`.
- **Contraste**: Azul ANE sobre blanco es 4,3:1 → **solo titulares de 24 px+**.
  Para texto pequeño en azul, usar Azul Enlace `#2A5CB8` (6,3:1).
  Negro y azul juntos solo como bloques separados, nunca texto de uno sobre otro.
- **Tipografía**: Archivo Black Italic solo en titulares, siempre mayúsculas,
  interlineado 0,9, nunca más de tres líneas. Inter para todo lo que se lee.
  Acentos obligatorios también en mayúsculas (AQUÍ, CAMPEÓN).
- **Regla de oro**: una sola idea en Archivo por pieza. Si todo grita, no se
  entiende nada.
- **Tono**: tuteo, frases cortas, jerga ciclista sin explicar. Como se habla
  en la ruta, no como una nota de prensa.

---

## ⚠️ Al tocar CSS o JS: subir la versión

Los assets se referencian como `estilo.css?v=20260817`. Si se edita el CSS y
no se cambia ese número **en `index.html` y en `episodios.html`**, los
visitantes seguirán viendo el archivo viejo.

Y verificar siempre desde fuera, nunca con curl desde el servidor
(ver la trampa de la CDN en `03_ESTADO_ACTUAL.md`).
