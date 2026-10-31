---
name: legisdata-bank-security
description: Estándares de seguridad grado bancario (OWASP ASVS L2/L3) para PHP 8.x, MySQL 8 y React SPA en producción sobre Hostinger.
---

# LegisData — Seguridad Grado Bancario

## 1. Base de Datos y Capa PDO
- Conexión PDO con `PDO::ATTR_EMULATE_PREPARES => false`, `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`.
- Prohibido concatenar variables en SQL. Todas las consultas usan parámetros vinculados (`:id`, `:camara`, etc.).
- Credenciales leídas desde variables de entorno o archivo de configuración fuera del alcance público (protegido por `.htaccess`).

## 2. Protección de API y Rate Limiting
- **Rate Limiting por IP (SHA-256 anonimizada)**: Ventana deslizante para proteger `GET /api/*` (120 req/min) y `POST /api/match` (30 req/min).
- **Validación de Entrada**: Whitelist estricta para filtros (`camara`, `provincia`, `estado`, `eje_tematico`), límites de paginación (`limit <= 50`, `page >= 1`), y esquema JSON estricto en `/api/match` (valores numéricos acotados a `[0, 100]`).
- **Audit Log Inmutable**: Tabla `audit_logs` (solo `INSERT`) para registrar ejecuciones de sincronización Cron, anomalías de rate limit e intentos de acceso inválido.

## 3. Cabeceras HTTP y Endurecimiento del Servidor (`.htaccess` & PHP)
- `Strict-Transport-Security: max-age=63072000; includeSubDomains; preload`
- `Content-Security-Policy` estricta permitiendo únicamente orígenes confiables (`'self'`, Google Fonts, Wikimedia Commons / Congreso para avatares).
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()`
- Bloqueo total de acceso HTTP a `/cron/*`, `/database/*`, `.env*`, `.git*`, `*.sql`, `*.log`.
- Los scripts en `/cron/` verifican `PHP_SAPI === 'cli'` o firma HMAC en cabecera `X-Cron-Signature`.
