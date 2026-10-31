---
name: legisdata-architecture
description: Arquitectura en etapas, esquema MySQL 8+ y algoritmos matemáticos/financieros (Deflactación USD MEP/IPC, Perfilamiento Temático y Similitud de Coseno) para LegisData (dipudata.com).
---

# LegisData — Arquitectura y Algoritmos Core

## 1. Ejecución Controlada por Etapas (Stage-Gate)
- **Etapa 1 — Arquitectura y Modelo de Datos**: Diseño de tablas normalizadas (`legisladores`, `indicadores_economicos`, `patrimonio_ddjj`, `leyes`, `votos`, `audit_logs`, `rate_limits`), índices compuestos y dataset semilla realista del Congreso de la Nación Argentina.
- **Etapa 2 — Backend PHP 8.x & Motores Algorítmicos**: API RESTful pura sin dependencias pesadas, PDO estricto, motores matemáticos y scripts Cron (`sync_wikipedia.php`, `sync_datacp.php`, `sync_anticorrupcion.php`).
- **Etapa 3 — Frontend React + Tailwind CSS (UI/UX Data-First)**: SPA Mobile-First con las 4 vistas (`Home`, `Radiografía del Legislador`, `Wizard Match Electoral`, `Resultados Split-Screen`), gráficos interactivos y tipografía tabular.
- **Etapa 4 — Seguridad Grado Bancario & SEO Top-Ranking**: Endurecimiento HTTP/CSP/HSTS, SSR dinámico de metadatos y JSON-LD en el entrypoint para bots, OpenGraph dinámico y despliegue en GitHub + Hostinger (`dipudata.com`).

## 2. Especificación Exacta de los Algoritmos
1. **Algoritmo A — Evolución Patrimonial Real (`calcularEvolucionPatrimonial`)**:
   - Cruza `patrimonio_ddjj` con `indicadores_economicos` por `anio_fiscal = anio`.
   - Calcula `patrimonio_usd = round($monto_pesos_nominal / $dolar_mep_cierre, 2)`.
   - Calcula `patrimonio_pesos_constantes` ajustado por el índice acumulado de `ipc_interanual` hasta el último año disponible.
   - Calcula `crecimiento_real_usd_pct` entre el primer y último período declarado durante el mandato.
2. **Algoritmo B — Perfilamiento Temático (`calcularPerfilTematico`)**:
   - Para cada eje (`Salud`, `Educacion`, `MercadoLibre`, `Proteccionismo`, `Laboral`):
   - Excluye `Ausente` para no distorsionar la huella ideológica.
   - Pondera cada voto por `leyes.peso_ideologico` (Afirmativo = `1.0 * peso`, Abstención = `0.25 * peso`, Negativo = `0.0`).
   - Retorna el vector normalizado de `0` a `100` en cada dimensión más KPIs de `asistencia_historica` y `lealtad_partidaria`.
3. **Algoritmo C — Motor de Match Electoral (`calcularMatchElectoral`)**:
   - Aplica Similitud del Coseno entre `$vector_usuario` y `$vector_legislador` sobre las 4 dimensiones principales (`Salud`, `Educacion`, `MercadoLibre`, `Proteccionismo`):
     $$\text{similitud} = \frac{\sum_{d} U_d \cdot L_d}{\sqrt{\sum_{d} U_d^2} \cdot \sqrt{\sum_{d} L_d^2}} \times 100$$
   - Ordena descendente para retornar el **Top 3 más afines** y ascendente para el **Bottom 3 menos afines**.
