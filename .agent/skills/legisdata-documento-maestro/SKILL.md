---
name: legisdata-documento-maestro
description: Hoja de ruta de DipuData según el documento maestro de Marcelo (perfil en pestañas, Match Electoral 360, auditoría): qué se puede hacer ya, qué espera una fuente y qué espera una decisión.
---

# DipuData — Hoja de ruta del documento maestro

El documento maestro es el rumbo del sitio. Se implementa por pasos, cada uno con fuente oficial, revisión de UX
(`legisdata-ui-design-system`) y controles en `tests/verificar.php`. Donde el documento choca con una regla de `CLAUDE.md`,
manda la regla hasta que Marcelo la cambie por escrito.

## Módulo 1: perfil del legislador
| Pieza | Estado | Qué falta |
| --- | --- | --- |
| Cabecera (foto, nombre, cargo, provincia, bloque) | Hecho | — |
| Biografía de Wikipedia | Frenado | No es fuente oficial |
| Pestañas (Resumen, Votos, Proyectos, Patrimonio, Más datos) | Se puede ya | Sin JS, con `nav.pestanas`; esperar la etapa 5 del perfil |
| 11 ejes temáticos con barras | Espera decisión | Clasificación propia de votaciones y proyectos, publicada en `/metodologia` |
| Lealtad partidaria | Hecho como "votó como la mayoría de su bloque" | Mostrarlo como barra |
| Defensa provincial | Sin fuente | No existe un "voto esperado regional" oficial |
| Alianzas entre bloques | Se puede en el Senado | Cruce de expedientes entre senadores en ejercicio; Diputados no publica cofirmantes |
| Últimos 5 proyectos | Hecho | — |
| Evolución patrimonial (gráfico) | Se puede ya en pesos de cada año | Pesos constantes con IPC espera decisión; MEP no es oficial |
| Costo por proyecto | Sin fuente | Nadie publica el gasto en asesores por despacho |
| Nepotismo por apellidos | Prohibido | Regla de familiares y personal |
| Conflicto de interés por aportes | Sin fuente por legislador | Aportes de campaña por agrupación, no por legislador |
| Coherencia histórica (leyes espejo) | Espera decisión | Lista de pares de leyes elegida y publicada a mano |
| Promesas vs. realidad | Sin fuente | Plataformas por partido, en texto libre |

## Módulo 2: Match Electoral 360
- Cuestionario de 12 preguntas como formulario de varios pasos sin JS (cada paso es un envío GET).
- Motor: similitud del coseno en PHP (ver `legisdata-architecture`), 0 = neutro.
- Resultado: mejor coincidencia, radar SVG de los ejes, aviso de disonancia en texto.
- Todo depende de la clasificación por ejes. Hasta entonces `/match` sigue en 503.
