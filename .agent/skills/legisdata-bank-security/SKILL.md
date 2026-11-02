---
name: legisdata-bank-security
description: Seguridad de DipuData: CSP sin scripts, cabeceras, validación de entradas y qué datos personales nunca se publican.
---

# DipuData — Seguridad y datos personales

## Cabeceras y servidor
- `encabezados_seguridad()` en `app/Web.php`: CSP `default-src 'none'`, sin `script-src` (solo JSON-LD, que no se ejecuta),
  imágenes solo de `self`, argentinadatos.com y senado.gob.ar. No agregar orígenes sin revisión.
- `.htaccess` niega por extensión `.sql`, `.env`, `.log`, `.md`, `.py`, `.json`, `.csv` y archivos ocultos (incluye `.git`); mantenerlo así al sumar tipos nuevos.
- Entradas: slugs con `^[a-z0-9]+(?:-[a-z0-9]+)*$`, filtros por lista blanca, paginación acotada. API solo `GET`.
- Formularios nuevos (Match): `GET` o `POST` sin guardar respuestas; nada de cookies de seguimiento.

## Datos que nunca se publican
- CUIT, datos del grupo familiar y descripción de bienes de las declaraciones juradas.
- Nada sobre el personal de un despacho: ninguna cámara publica qué asesor trabaja para quién. Cruces de apellidos
  ("nepotismo") quedan prohibidos: un apellido igual no prueba parentesco y expone a terceros.
- Informes sensibles van a `informes_privados/` (ignorado por git).
