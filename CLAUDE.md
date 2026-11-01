# DipuData — guía para trabajar en este repositorio

Sitio cívico sobre el Congreso de la Nación Argentina (dipudata.com). PHP 8 sin framework ni base de datos: las páginas se
renderizan en el servidor a partir de archivos JSON en `storage/verificado/`. GitHub despliega `main` en Hostinger.

## Principio que manda
**Solo se publica lo que tiene fuente oficial y se pudo verificar.** La versión anterior del sitio publicaba CUIT al azar,
métricas de relleno y acusaciones inventadas: eso nunca debe volver a pasar. Cada dato muestra su fuente. Si un archivo trae
valores repetidos, vacíos o inconsistentes, no se publica y se explica en `/metodologia`.
- No se publican CUIT, datos de familiares ni descripción de bienes (declaraciones juradas).
- Sin rankings ni "crecimiento patrimonial": los montos son pesos de cada año, sin ajustar por inflación.
- Los pasajes de la Cámara "no implican viajes efectivamente realizados": decir "pasajes emitidos", no "viajó".
- No afirmar nada sobre el personal de un legislador: ni Diputados ni el Senado publican qué asesor trabaja para quién
  (la nómina dice solo "afectado a bloque político"). Informes sensibles van a `informes_privados/` (ignorado por git).

## Estructura
- `mvc.php` router; `api/index.php` API de solo lectura; `app/Datos.php` lee `storage/verificado/`; `app/Vistas/` vistas;
  `assets/css/dipudata.css` único estilo (sin CDN ni fuentes externas; CSP sin scripts).
- `storage/oficial/` fuentes comprimidas (lo que se descargó); `storage/verificado/` lo que lee el sitio (se genera).
- `scripts/` Python (solo biblioteca estándar). Orden: `construir_datos.py` → `construir_senado.py` →
  `construir_votaciones.py` → `construir_registros.py` → `construir_diputados_extra.py` → `construir_ddjj.py` →
  `construir_proyectos.py`. Control: `contrastar_senado_datacp.py` (totales del Senado contra datacp.ar).
  Descargas: `bajar_votaciones_senado.py` (senado.gob.ar; `--historico` baja 2005-2025, reanudable, se puede partir en
  dos tramos), `bajar_proyectos_senado.py` (buscador oficial del Senado por ID de senador, reanudable),
  `bajar_votaciones_recientes.py` (Diputados, ArgentinaDatos),
  `filtrar_ddjj.py` (recorta el archivo anual de la Oficina Anticorrupción a legisladores y quita el CUIT).
- `tests/verificar.php` controles de integridad: `php tests/verificar.php` debe terminar en "Todo en orden".

## Qué se aprendió de las fuentes
- Diputados oficial (datos.hcdn.gob.ar): votaciones nominales solo hasta el período 137 (enero de 2020).
- ArgentinaDatos replica las de Diputados desde 2025 (coincide con datacp.ar en las 141), pero **difiere del Senado en 16%**:
  el Senado se toma de `www.senado.gob.ar/votaciones/detalleActa/{id}` con el ID oficial de cada senador.
- datacp.ar tiene un buscador (`/api/congreso/actas-search`) útil para contrastar totales; no es fuente primaria.
- Un detalle de votación debe cuadrar con su cabecera. El presidente de la sesión figura como ausente en el detalle: se marca "Preside".
  Algunas filas vienen sin nombre (una coma): no son legisladores. Algunas páginas del Senado duplican la tabla.
- Los montos de las declaraciones juradas vienen en dos formatos (`155817519-23` y con punto decimal).
- Los números de acta de senado.gob.ar no siguen el orden de las fechas (1 = 2016, 1000 = 2011). Las páginas viejas casi no
  traen el ID del senador: se cruza con el listado histórico del Senado (mismo ID, con fechas de cada mandato) por apellido,
  nombres, provincia y mandato vigente. Si una página trae el ID, manda, y se controla que no contradiga el cruce.
- "Proyectos parlamentarios" (HCDN) trae un solo autor (primer firmante), por nombre: se cruza con el histórico de diputados
  (ID HCDN) exigiendo mandato vigente a la fecha. Los cofirmantes no son dato abierto. Los títulos traen entidades HTML.
- El buscador de proyectos del Senado filtra por ID de senador (autor o coautor) pero no informa el resultado.
- votaciones.hcdn.gob.ar no responde desde la nube (sí desde Argentina): bajarlo con Control remoto.
- Las columnas de promulgación y veto del archivo de leyes traen el mismo valor en todas las filas: no usarlas.

## Flujo de trabajo
Desarrollar en la rama indicada por la sesión, correr los scripts y `php tests/verificar.php`, probar las rutas con
`php -S` y revisar las pantallas (escritorio y móvil). Para publicar: `git merge -s ours origin/main` (conserva el árbol de la
rama y evita duplicados), abrir PR a `main` y mergear (squash). Mensajes de commit y PR en español, sin atribuir a personas.
