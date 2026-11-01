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
| Pestañas (Resumen, Votaciones, Proyectos y temas, Declaración jurada, Más datos) | Hecho | — |
| 11 ejes temáticos con barras | Hecho por comisión de primer giro (proyectos; votos solo Diputados 2011-2020) | Más votos con expediente |
| Lealtad partidaria | Hecho como "votó como la mayoría de su bloque" | Mostrarlo como barra |
| Defensa provincial | Sin fuente | No existe un "voto esperado regional" oficial |
| Alianzas entre bloques | Hecho en el Senado (lista, no grafo; desde 10/12/2025) | Diputados no publica cofirmantes |
| Últimos 5 proyectos | Hecho | — |
| Evolución patrimonial (gráfico) | Hecho, en pesos de hoy con IPC del INDEC (desde 2016) | MEP no es oficial |
| Costo por proyecto | Sin fuente | Nadie publica el gasto en asesores por despacho |
| Nepotismo por apellidos | Prohibido | Regla de familiares y personal |
| Conflicto de interés por aportes | Sin fuente por legislador | Aportes de campaña por agrupación, no por legislador |
| Coherencia histórica (leyes espejo) | Espera decisión | Lista de pares de leyes elegida y publicada a mano |
| Promesas vs. realidad | Sin fuente | Plataformas por partido, en texto libre |

## Módulo 2: Match Electoral 360
- HECHO (1/11/2026, Marcelo eligió "Temas que te importan vs. su agenda"): `/match` sin JS, un formulario GET con los 11 temas
  (Nada/Poco/Bastante/Mucho = 0..3) y filtro de cámara. Coseno contra la proporción de proyectos por tema de cada legislador
  (`match_temas()` en `app/Web.php`), solo con 10 o más proyectos con tema. Resultados con `noindex`.
- Compara prioridades, no posiciones. Pendiente: coincidencia por votos (Marcelo lo pidió: "% de coincidencia con lo que se vota"),
  que necesita votaciones con tema; hoy solo Diputados 2011-2020 tiene expediente en las actas.
- Aportes de campaña por partido (CNE): Marcelo lo pidió; electoral.gov.ar estaba bloqueado desde el entorno.
