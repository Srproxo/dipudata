---
name: legisdata-ui-design-system
description: Sistema de Diseño e Interfaz (UI/UX) de LegisData (Neutralidad Visual, Data-First, Mobile-First, tipografía tabular y componentes de visualización).
---

# LegisData — Sistema de Diseño UI/UX

## 1. Principios Rectores
1. **Neutralidad Visual**: Estructura en escala de grises pizarra (`bg-slate-50`, tarjetas `bg-white shadow-sm border border-slate-200/80`, texto principal `text-slate-900`, secundario `text-slate-500`). Sin sesgos partidarios en la interfaz general.
2. **Claridad de Datos (Data-First)**: El color solo comunica significado semántico o eje temático.
3. **Mobile-First**: Diseño optimizado para consulta móvil en vivo (debates, sesiones legislativas). Tablas con scroll horizontal fluido y gráficos responsivos.

## 2. Tipografía y Escala
- **Fuente Principal**: `Inter` (Google Fonts). Títulos H1/H2 en `font-bold` (700) o `font-extrabold` (800) con `tracking-[-0.02em]`. Cuerpo `font-normal` (400) a `16px`.
- **Datos Numéricos**: `JetBrains Mono` con `tabular-nums` para montos en USD/ARS, porcentajes, fechas, CUIT y expedientes.

## 3. Paleta Semántica y Categórica (Tailwind CSS)
- **Votaciones y Evolución Patrimonial**:
  - Afirmativo / Crecimiento: `text-emerald-700 bg-emerald-100`
  - Negativo / Caída: `text-rose-700 bg-rose-100`
  - Abstención / Ausencia: `text-slate-600 bg-slate-200`
- **Ejes Temáticos**:
  - Educación / Ciencia: `blue-500` (`#3b82f6`)
  - Salud Pública: `teal-500` (`#14b8a6`)
  - Economía / Libre Mercado: `amber-500` (`#f59e0b`)
  - Industria / Proteccionismo: `indigo-500` (`#6366f1`)
  - Laboral: `violet-500` (`#8b5cf6`)

## 4. Vistas Obligatorias
- **Vista 1 (`/`)**: Hero *"Conocé a tus representantes"*, buscador grande con autocompletado, toggles rápidos (*Todos*, *Diputados*, *Senadores*) y filtro por Provincia y Bloque.
- **Vista 2 (`/perfil/:id`)**: Header Bio + KPIs (Asistencia Histórica, Lealtad Partidaria), Módulo Financiero (Gráfico de línea suavizada `tension: 0.4` en USD MEP + tooltip con ARS nominales, ARS constantes y USD MEP + flecha de crecimiento real), Módulo Ideológico (Gráfico de Radar `fillOpacity={0.4}`, `stroke="#e2e8f0"` + barras `h-2.5` + tabla de votaciones paginada a máximo 10 leyes por página).
- **Vista 3 (`/match`)**: Wizard tipo Typeform (8 preguntas, barra superior de progreso fina, botones de bloque con selección `ring-2 ring-blue-500 bg-blue-50`).
- **Vista 4 (Resultados en `/match`)**: Split Screen (*Tu perfil como Votante* vs *Tus mejores coincidencias Top 3* + *Menos afines Bottom 3*), botón *"Ver radiografía completa"* y botón de compartir con imagen OpenGraph dinámica.
