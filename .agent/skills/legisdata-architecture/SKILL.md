---
name: legisdata-architecture
description: Arquitectura real de DipuData (PHP 8 sin framework ni base de datos, JSON verificados, scripts Python) y cómo sumar una métrica nueva sin romperla.
---

# DipuData — Arquitectura

## 1. Cómo está armado
- `mvc.php` es el router (un `switch` de rutas); `app/Vistas/*.php` son las vistas; `app/Vistas/_plantilla.php` el marco común.
- `app/Datos.php` lee `storage/verificado/*.json`. **No hay base de datos.** Los `.sql` de `database/` son históricos y no se usan.
- `api/index.php` es una API de solo lectura sobre los mismos JSON. No hay endpoints de escritura ni ETL externo (n8n u otro):
  los datos se generan con `scripts/*.py` (solo biblioteca estándar) a partir de `storage/oficial/` y se versionan en git.
- `tests/verificar.php` controla la integridad; debe terminar en "Todo en orden".
- Despliegue: GitHub `main` → Hostinger.

## 2. Cómo se suma una métrica (del documento maestro o nueva)
1. **Fuente:** ¿hay una fuente oficial descargable? Si no, se frena y se anota en `/metodologia` → "Qué viene".
2. **Script:** `scripts/construir_<algo>.py` que lee `storage/oficial/` o `storage/verificado/` y escribe `storage/verificado/<algo>.json`
   con un `_meta` (fuente, fecha, desde, hasta, descartados y por qué).
3. **Lectura:** un método en `app/Datos.php` que valide el slug y devuelva `null`/`[]` si no hay dato.
4. **Control:** un `ok(...)` en `tests/verificar.php` que verifique que los números cuadran (totales, rangos, sin duplicados).
5. **Vista:** pasa por la revisión de UX (`legisdata-ui-design-system`, sección 5) y lleva su nota de fuente.
6. **Metodología:** explicar en `app/Vistas/metodologia.php` cómo se calcula y qué no significa.

## 3. Cálculos del documento maestro: cómo se pueden hacer acá
- **Similitud del coseno (Match):** `sim = Σ(u·l) / (√Σu² · √Σl²)`, con respuestas en [-1, 1] y 0 = neutro; un eje que el usuario
  dejó en 0 no suma. Se calcula en PHP en el servidor al enviar un formulario GET/POST, sin JavaScript.
  Requiere ejes temáticos con clasificación publicada (decisión pendiente de Marcelo).
- **Apoyo por eje:** primero normalizar. Votos: % de afirmativos sobre votos emitidos en las votaciones del eje.
  Proyectos: cantidad del eje sobre el total de proyectos del legislador (0 a 1). Recién ahí `0,70·votos + 0,30·proyectos`.
- **Pesos constantes (DDJJ):** `monto_t · IPC_final / IPC_dic_t` con la serie oficial del INDEC (`factores_ipc()` en `app/Web.php`),
  aprobado por Marcelo el 1/11/2026. Dólar oficial del BCRA al cierre de cada año (`bajar_dolar.py`) y, al lado, MEP de ArgentinaDatos marcado como no oficial (`bajar_dolar_mep.py`), por decisión de Marcelo del 2/11/2026.
- **Coincidencia con el bloque:** ya existe (`coincidencia_bloque_pct`). Es la versión con fuente del "índice de lealtad".
- **Coautorías entre bloques:** solo Senado, cruzando el mismo expediente entre las listas de los senadores en ejercicio.
