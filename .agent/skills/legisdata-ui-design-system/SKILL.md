---
name: legisdata-ui-design-system
description: Sistema de diseño de DipuData y revisión de UX obligatoria. Usar antes de agregar o cambiar cualquier vista, componente, gráfico o estilo; nada nuevo se publica sin pasar esta revisión.
---

# DipuData — Sistema de diseño y revisión de UX

Regla de oro: **no se rompe el diseño.** Todo lo que se agrega a una pantalla pasa primero por la revisión de la sección 5.
Si la revisión no se aprueba, no se publica, aunque el dato sea correcto.

## 1. Principios
1. **Sobrio y neutral.** Papel blanco, tinta pizarra, un solo acento azul (`--acento`). El color de un bloque político solo
   aparece como punto chico (`.punto`, `.bloque i`), nunca como fondo de una sección.
2. **El dato primero, con su fuente al lado.** Cada cifra lleva debajo un texto `.tenue.pequeno` que dice de dónde sale,
   qué período cubre y qué NO significa. Sin esa nota, la cifra no va.
3. **Sin adjetivos ni veredictos.** Nada de "alerta roja", "panqueque", "se enriqueció", "ineficiente". Se describen hechos
   ("votó distinto que la mayoría de su bloque en 12 de 140 votaciones").
4. **Sin JavaScript y sin recursos externos.** Una sola hoja `assets/css/dipudata.css`; la CSP de `app/Web.php` no permite
   scripts. Prohibido: Tailwind, Chart.js, Alpine, React, Google Fonts, CDN, iconos externos.
5. **Móvil primero.** Todo se prueba a 375 px de ancho: tablas con `data-etiqueta`, toques de 44 px como mínimo.

## 2. Tokens (definidos en `:root`)
- Texto: `--tinta` #0f172a, `--tinta-2` #334155, `--tenue` #64748b. Fondos: `--papel` #fff, `--fondo` #f8fafc.
- Líneas: `--linea` #e2e8f0, `--linea-fuerte` #cbd5e1. Acento: `--acento` #1e3a8a, `--acento-claro` #eff6ff.
- Aviso: `--aviso-fondo` #fffbeb con borde `--aviso-borde` #f59e0b (solo para advertencias de método, no para acusar).
- Votos: `.voto-A` verde, `.voto-N` rojo, `.voto-B` amarillo, `.voto-U/.voto-P` gris. Esos tres colores se reservan a votos.
- Tipografía: `--serif` del sistema para títulos y cifras grandes; `--sans` del sistema para el cuerpo; `.num` (tabular-nums)
  para toda cifra, fecha y expediente. No se suman fuentes.
- Ancho máximo `--ancho` 68rem. Bordes rectos (radio 0), sin sombras.

## 3. Componentes aprobados (reusar antes de inventar)
| Necesidad | Componente |
| --- | --- |
| Migas de pan | `nav.migas` |
| Cifras clave | `dl.participacion` (3 a 6 cifras) |
| Resumen arriba del perfil | `section.resumen` con frases armadas solo con datos verificados |
| Ficha clave/valor | `dl.ficha` |
| Tabla | `.tabla-caja > table.tabla`, celdas con `data-etiqueta` para móvil |
| Pestañas | `nav.pestanas` con enlaces reales (`?tab=` o rutas) y `aria-current="page"`; cada pestaña es una URL indexable |
| Desplegable sin JS | `<details>` (como `.mas`) |
| Barra proporcional | `.barra > span` con `style="width:…%"` y leyenda `.leyenda` |
| Lista de nombres | `ul.lista-chips` |
| Advertencia de método | `p.aviso` |
| Paginación | `.paginas` |
| Cifra grande con fuente (tarjeta) | `ul.tarjetas > li.tarjeta` (`.tarjeta-t`, `.tarjeta-cifra`, `.tarjeta-nota`), con `medidor()` para porcentajes |
| Reparto de votos | `reparto_votos()` (colores de voto) y `svg_barras_votos()` para mes a mes |
| Barras simples por año | `svg_barras()` |

## 4. Gráficos
- Se dibujan como **SVG en línea generado en PHP**, sin bibliotecas. Colores con los tokens; texto con `--sans`.
- Cada gráfico lleva `role="img"`, un `<title>` con la conclusión en palabras y, debajo, la tabla con los mismos números
  (o un `<details>` "Ver los datos").
- Líneas: eje Y desde 0 para montos; años en el eje X; sin suavizado (la línea no inventa valores entre años).
- Radar: solo si hay un eje con fuente o clasificación publicada en `/metodologia`; si no, barras horizontales.
- Nunca se compara un año con otro en pesos sin ajustar sin decirlo en el pie.

## 5. Revisión de UX (checklist que aprueba o frena)
Responder sí a todo. Un "no" frena la publicación.
1. ¿Reusa un componente de la sección 3, o el nuevo usa solo tokens de la sección 2?
2. ¿Funciona sin JavaScript y sin pedir nada fuera de dipudata.com?
3. ¿Cada cifra tiene su nota de fuente, período y límites?
4. ¿El texto describe hechos, sin adjetivos ni veredictos sobre la persona?
5. ¿Se probó en escritorio (1280 px) y móvil (375 px) con `php -S` y un router que deje pasar `/assets`?
6. ¿Se mantiene el contraste AA y los toques de 44 px?
7. ¿Si falta el dato, la sección no se muestra (en vez de mostrar ceros o "sin datos")?
8. ¿`php tests/verificar.php` termina en "Todo en orden"?
Dejar el resultado de la revisión en el mensaje del commit o del PR ("Revisión de UX: 8/8").
