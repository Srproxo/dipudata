---
name: legisdata-seo-dominance
description: Estrategia de SEO técnico y semántico para posicionar dipudata.com en las primeras posiciones de Google para consultas sobre diputados, senadores, votaciones y patrimonio en Argentina.
---

# LegisData — Dominancia SEO & Indexación Semántica

## 1. SSR Dinámico de Metadatos en el Shell PHP (`index.php`)
- Aunque el frontend sea una SPA en React, cuando un crawler o navegador solicita `/`, `/perfil/{id}` o `/match`, el router principal (`index.php`) consulta la base de datos de forma ultrarrápida e inyecta en el `<head>` del HTML antes de entregar el bundle:
  - `<title>` optimizado por intención de búsqueda (ej. *"Nombre Apellido (Diputado - Provincia) | Votaciones, Patrimonio DDJJ y Asistencia | DipuData — LegisData"*).
  - `<meta name="description">`, `<link rel="canonical">`, OpenGraph (`og:title`, `og:description`, `og:image`, `og:url`, `og:type`) y Twitter Cards (`summary_large_image`).
  - Contenido semántico `<noscript>` con el resumen del perfil o listado de legisladores para garantizar indexación del 100% sin depender de JS rendering.

## 2. Datos Estructurados JSON-LD (Schema.org)
- En `/`: `WebSite` (con `SearchAction`), `GovernmentOrganization` / `Organization` y `Dataset` (Base de Datos Abierta de Votaciones y DDJJ del Congreso Argentino).
- En `/perfil/:id`: `Person` (`name`, `jobTitle`, `affiliation`, `addressRegion`, `image`, `description`, `sameAs` hacia Wikipedia) + `BreadcrumbList`.
- En `/match`: `WebApplication` + `FAQPage`.

## 3. Sitemap Dinámico, OpenGraph y Core Web Vitals
- `GET /sitemap.xml` generado dinámicamente en PHP incluyendo todas las URLs canónicas de perfiles de legisladores (`/perfil/{id}`), `/match` y `/`.
- `GET /api/og` genera una tarjeta SVG/PNG dinámica de alta resolución (1200x630) con el perfil del legislador o el resultado del Match Electoral para maximizar el CTR en X (Twitter), WhatsApp, Facebook y LinkedIn.
- Compresión Gzip/Brotli y caché de activos inmutables en `.htaccess`.
