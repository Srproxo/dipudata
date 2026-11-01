---
name: legisdata-seo-dominance
description: SEO técnico de DipuData: metadatos server-side, JSON-LD, migas, sitemap y enlazado entre secciones.
---

# DipuData — SEO

- Todo se renderiza en el servidor (no hay SPA): `pagina_meta()` en `mvc.php` arma `title`, `description`, `canonical`, `robots`.
- Títulos: "Nombre (Diputado/a por Provincia) | DipuData". Descripciones con datos verificados, nunca adjetivos.
- JSON-LD: `WebSite` con `SearchAction` en `/`, `Person` en el perfil. Sumar `BreadcrumbList` cuando se toquen las migas.
- Migas de pan `nav.migas` en toda vista interna.
- Vistas filtradas o vacías: `noindex, follow`. Secciones en preparación: 503 con `Retry-After` (como `/eje` y `/match`).
- Pestañas del perfil: cada una es una URL (`/diputado/slug?tab=votos` o subruta) con su `canonical`, para que se indexen.
- Hub and spoke: una página por eje (`/eje/salud`) enlazando a legisladores y votaciones, solo cuando exista la clasificación.
- `sitemap.xml` se genera en `mvc.php`; sumar cada ruta nueva indexable.
- Sin OpenGraph de imágenes generadas por terceros; si se hace, SVG/PNG generado en el propio servidor.
