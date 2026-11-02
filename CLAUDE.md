# DipuData — guía para trabajar en este repositorio

Sitio cívico sobre el Congreso de la Nación Argentina (dipudata.com). PHP 8 sin framework ni base de datos: las páginas se
renderizan en el servidor a partir de archivos JSON en `storage/verificado/`. GitHub despliega `main` en Hostinger.

## Principio que manda
**Solo se publica lo que tiene fuente oficial y se pudo verificar.** La versión anterior del sitio publicaba CUIT al azar,
métricas de relleno y acusaciones inventadas: eso nunca debe volver a pasar. Cada dato muestra su fuente. Si un archivo trae
valores repetidos, vacíos o inconsistentes, no se publica y se explica en `/metodologia`.
- No se publican CUIT, datos de familiares ni descripción de bienes (declaraciones juradas).
- Sin rankings ni "crecimiento patrimonial": los montos se muestran como se declararon. Desde el 1/11/2026 (decisión de Marcelo) el patrimonio neto
  se expresa también en pesos de hoy con el IPC del INDEC (`bajar_ipc.py`, serie desde dic. 2016) y en dólares al tipo de cambio oficial del BCRA del cierre de cada año (`bajar_dolar.py`) y, al lado, en dólar MEP de ArgentinaDatos, no oficial, por decisión de Marcelo del 2/11/2026 (`bajar_dolar_mep.py`, que descarta cierres con el valor repetido); sin calificar la evolución.
- Los pasajes de la Cámara "no implican viajes efectivamente realizados": decir "pasajes emitidos", no "viajó".
- No afirmar nada sobre el personal de un legislador: ni Diputados ni el Senado publican qué asesor trabaja para quién
  (la nómina dice solo "afectado a bloque político"). Informes sensibles van a `informes_privados/` (ignorado por git).

## Estructura
- `mvc.php` router; `api/index.php` API de solo lectura; `app/Datos.php` lee `storage/verificado/`; `app/Vistas/` vistas;
  `assets/css/dipudata.css` único estilo (sin CDN ni fuentes externas; CSP sin scripts).
- `storage/oficial/` fuentes comprimidas (lo que se descargó); `storage/verificado/` lo que lee el sitio (se genera).
- `scripts/` Python (solo biblioteca estándar). Orden: `construir_datos.py` → `construir_senado.py` →
  `construir_votaciones.py` → `construir_registros.py` → `construir_diputados_extra.py` → `construir_ddjj.py` → `construir_proyectos.py` → `construir_ejes.py` → `construir_coautorias.py` → `construir_aportes.py` → `contrastar_datacp.py`.
  Descargas: `bajar_votaciones_hcdn.py` (Diputados 2020 en adelante, desde una máquina local), `bajar_ipc.py` (IPC del INDEC, datos.gob.ar), `bajar_dolar.py` (dólar oficial del BCRA al cierre de cada año, datos.gob.ar), `bajar_dolar_mep.py` (MEP de ArgentinaDatos, no oficial; después de bajar_dolar.py), `bajar_votaciones_senado.py` (senado.gob.ar, todas las actas desde 2005, reanudable, ~1 hora), `bajar_proyectos_senado.py` (lista de proyectos de cada senador), `bajar_giros_senado.py` (giros a comisiones de esos proyectos, ~1 hora), `bajar_votaciones_recientes.py` (Diputados, ArgentinaDatos),
  `filtrar_aportes.py <aportes.csv> <registro-nacional-sociedades-AAAA.zip>` (aportes de la CNE exportados desde el navegador; quita CUIL/CUIT, banco y nombre de personas humanas; agrega el rubro de las empresas),
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
- Las columnas de promulgación y veto del archivo de leyes traen el mismo valor en todas las filas: no usarlas.

- Senado, votaciones: los números de `detalleActa/{id}` NO siguen el orden de las fechas y los inexistentes devuelven 200 con una página vacía. Las actas viejas casi no traen el ID del
  senador: se cruza por nombre completo y mandato vigente (coincide 16.026/16.026 con el ID donde existe). Hay filas con la celda de voto en blanco (no son voto: "Sin voto registrado") y 6 actas sin votos ni totales.
- Proyectos: el CSV de Diputados trae UN autor por proyecto, como texto y sin ID, y sin cofirmantes; se cruza por nombre completo contra el histórico oficial y se valida el mandato en la fecha.
  "Resultado de proyectos" solo trae lo resuelto: ausencia no es rechazo. El Senado no publica proyectos abiertos: se lee la página de cada senador (ID en la URL); los códigos CO y CC del expediente no son proyectos.
- Segunda fuente: DataCP (datacp.ar, UBA, CC BY 4.0) `/api/congreso/export/leyes-clave/resultados` trae solo totales de leyes clave: sirve para contrastar, no para publicar votos. Sus rankings de intervenciones no se publican.
- Diputados 2020-2025: datos.hcdn.gob.ar sigue en el período 137. `votaciones.hcdn.gob.ar` corta el TLS desde el entorno en la nube,
  pero responde desde una máquina local: `bajar_votaciones_hcdn.py` (reanudable, caché en `storage/cache/`, ignorado) →
  `validar_votaciones_hcdn.py` → `convertir_votaciones_hcdn.py` genera `actas_cabecera/detalle_138..143` con el formato oficial
  (hasta el 9/12/2025; lo posterior sigue viniendo de ArgentinaDatos, que coincide 141/141 voto por voto). Los números de acta son los mismos
  de datos.hcdn.gob.ar (la muestra del 137 coincide 20/20). La página trae etiquetas extra: PRESIDENTE, SIN VOTAR y "PENDIENTE DE
  INCORPORACIÓN" (banca vacante, no es legislador). "Sin votar" en la cabecera a veces suma a quien preside y a las vacantes.
  Faltan 3 votaciones del 138 que figuran en el índice sin página de detalle (404: actas 3975, 3996, 3999) y 4 números de acta sin votación
  en el índice (138 R9 acta 3; 139 R11 acta 2; 139 R12 actas 1 y 3).
- Red del entorno: los dominios permitidos necesitan comodín (`*.senado.gob.ar`, `*.hcdn.gob.ar`, `*.datacp.ar`, ...). Para probar páginas con `php -S` hace falta un router que deje pasar `/assets` (mvc.php no lo hace).
- Ejes temáticos (decisión de Marcelo, 1/11/2026): el eje de un proyecto es el de su comisión de primer giro, según `scripts/ejes_por_comision.csv`
  (editable). Las actas del Senado y las recientes de Diputados no traen expediente: los votos por eje salen solo de Diputados 2011-2020.
- Aportes a partidos (CNE): aportantes.electoral.gob.ar rechaza pedidos automáticos ("Request Rejected"); no esquivarlo, el CSV lo exporta una persona. Se publican totales y los 10 mayores aportantes por agrupación:
  empresas con razón social y rubro (Registro Nacional de Sociedades), personas humanas sin nombre. No afirmar beneficio a cambio de un aporte. Agrupación electoral ≠ bloque: no se atribuyen aportes a legisladores.
- Declaraciones juradas: el último archivo de la Oficina Anticorrupción es el del año fiscal 2024; el de 2025 todavía no está.

## Flujo de trabajo
Desarrollar en la rama indicada por la sesión, correr los scripts y `php tests/verificar.php`, probar las rutas con
`php -S` y revisar las pantallas (escritorio y móvil). Para publicar: `git merge -s ours origin/main` (conserva el árbol de la
rama y evita duplicados), abrir PR a `main` y mergear (squash). Mensajes de commit y PR en español, sin atribuir a personas.
