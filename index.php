<?php
declare(strict_types=1);

/**
 * LEGISDATA (dipudata.com) — Entrypoint SSR de Metadatos SEO + Shell de la SPA React
 * Etapa 4: Dominancia SEO Técnica, Datos Estructurados JSON-LD, OpenGraph Dinámico y Noscript Semántico
 */

namespace LegisData;

use PDO;
use Throwable;

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/Security.php';
require_once __DIR__ . '/api/Algorithms.php';

Security::sendSecurityHeaders('text/html; charset=utf-8');

$pdo = Config::getConnection();
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$cleanPath = '/' . trim($requestUri, '/');
if ($cleanPath === '/index.php' || $cleanPath === '/app.html') {
    $cleanPath = '/';
}

/**
 * Obtiene todos los legisladores (MySQL o MemoryStore resiliente)
 */
function ssrFetchAllLegisladores(?PDO $pdo): array
{
    if ($pdo !== null) {
        try {
            $stmt = $pdo->query("
                SELECT
                    id, id_congreso, cuit, wiki_slug,
                    LOWER(REPLACE(wiki_slug, '_', '-')) AS slug,
                    nombre_completo, camara, provincia, bloque_actual,
                    estado, biografia, foto_url,
                    asistencia_historica, lealtad_partidaria, updated_at
                FROM legisladores
                ORDER BY id ASC
            ");
            $rows = $stmt ? $stmt->fetchAll() : [];
            if (!empty($rows)) {
                return $rows;
            }
        } catch (Throwable) {
            // Fallback a MemoryStore
        }
    }

    $store = Config::getMemoryStore();
    return array_values($store['legisladores']);
}

/**
 * Busca un legislador por ID numérico, slug o wiki_slug
 */
function ssrFindLegislador(?PDO $pdo, string $identifier): ?array
{
    $all = ssrFetchAllLegisladores($pdo);
    $clean = trim($identifier);
    foreach ($all as $leg) {
        if (is_numeric($clean) && (int)$leg['id'] === (int)$clean) {
            return $leg;
        }
        if (
            strcasecmp((string)($leg['slug'] ?? ''), $clean) === 0 ||
            strcasecmp((string)($leg['wiki_slug'] ?? ''), $clean) === 0 ||
            strcasecmp((string)($leg['id_congreso'] ?? ''), $clean) === 0
        ) {
            return $leg;
        }
    }
    return null;
}

/**
 * Obtiene las votaciones nominales de un legislador para el bloque <noscript>
 */
function ssrFetchVotos(?PDO $pdo, int $legisladorId): array
{
    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare("
                SELECT v.ley_id, v.sentido_voto, l.titulo, l.fecha_votacion, l.eje_tematico
                FROM votos v
                INNER JOIN leyes l ON v.ley_id = l.id
                WHERE v.legislador_id = :id
                ORDER BY l.fecha_votacion DESC
            ");
            $stmt->execute([':id' => $legisladorId]);
            $rows = $stmt->fetchAll();
            if (!empty($rows)) {
                return $rows;
            }
        } catch (Throwable) {
            // Fallback a MemoryStore
        }
    }

    $store = Config::getMemoryStore();
    $votosMap = $store['votos'][$legisladorId] ?? [];
    $leyesMap = $store['leyes'] ?? [];
    $result = [];
    foreach ($votosMap as $leyId => $sentido) {
        if (!isset($leyesMap[$leyId])) {
            continue;
        }
        $ley = $leyesMap[$leyId];
        $result[] = [
            'ley_id'         => $ley['id'],
            'titulo'         => $ley['titulo'],
            'fecha_votacion' => $ley['fecha_votacion'],
            'eje_tematico'   => $ley['eje_tematico'],
            'sentido_voto'   => $sentido,
        ];
    }
    return $result;
}

// ============================================================================
// CONSTRUCCIÓN DE METADATOS SEO, JSON-LD Y CONTENIDO <NOSCRIPT> SEGÚN LA RUTA
// ============================================================================
$baseUrl = 'https://dipudata.com';
$seoTitle = 'DipuData (LegisData) — Conocé a tus Representantes | Votaciones, Patrimonio DDJJ y Match Electoral';
$seoDescription = 'Plataforma de auditoría cívica y transparencia parlamentaria de la República Argentina. Consultá votaciones nominales, asistencia, lealtad partidaria, evolución patrimonial en USD MEP e IPC y hacé tu Match Electoral con Diputados y Senadores.';
$canonicalUrl = $baseUrl . '/';
$ogType = 'website';
$ogImage = $baseUrl . '/api/og?type=home';
$jsonLdSchemas = [];
$noscriptHtml = '';

// RUTA A: /perfil/{id}
if (preg_match('#^/perfil/([^/]+)$#', $cleanPath, $matches)) {
    $rawIdentifier = Security::sanitizeString($matches[1], 100);
    $legislador = ssrFindLegislador($pdo, $rawIdentifier);

    if ($legislador !== null) {
        $legId = (int)$legislador['id'];
        $nombre = (string)$legislador['nombre_completo'];
        $camara = (string)$legislador['camara'];
        $cargo = str_starts_with(strtolower($camara), 'diputad') ? 'Diputado/a Nacional' : 'Senador/a Nacional';
        $provincia = (string)$legislador['provincia'];
        $bloque = (string)$legislador['bloque_actual'];
        $asist = number_format((float)$legislador['asistencia_historica'], 1);
        $lealtad = number_format((float)$legislador['lealtad_partidaria'], 1);
        $wikiSlug = (string)$legislador['wiki_slug'];
        $biografia = (string)$legislador['biografia'];
        $fotoUrl = (string)$legislador['foto_url'];

        $patrimonio = Algorithms::calcularEvolucionPatrimonial($legId, $pdo);
        $perfilTematico = Algorithms::calcularPerfilTematico($legId, $pdo);
        $votos = ssrFetchVotos($pdo, $legId);

        $usdActual = number_format((float)$patrimonio['patrimonio_actual_usd'], 0, ',', '.');
        $varUsdSign = $patrimonio['crecimiento_real_usd_pct'] >= 0 ? '+' : '';
        $varUsd = $varUsdSign . number_format((float)$patrimonio['crecimiento_real_usd_pct'], 1) . '%';

        $seoTitle = "{$nombre} ({$camara} - {$provincia}) | Votaciones, Patrimonio DDJJ y Asistencia | DipuData — LegisData";
        $seoDescription = "Radiografía parlamentaria y patrimonial de {$nombre} ({$cargo} por {$provincia}, Bloque {$bloque}). Asistencia histórica: {$asist}%, Lealtad partidaria: {$lealtad}%, Patrimonio DDJJ deflactado: USD {$usdActual} ({$varUsd} real en USD MEP). Auditá sus votaciones nominales en el Congreso Argentino.";
        $canonicalUrl = "{$baseUrl}/perfil/{$legId}";
        $ogType = 'profile';
        $ogImage = "{$baseUrl}/api/og?type=perfil&id={$legId}";

        $jsonLdSchemas[] = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Person',
            '@id'         => "{$canonicalUrl}#person",
            'name'        => $nombre,
            'jobTitle'    => "{$cargo} de la Nación Argentina ({$camara})",
            'description' => $biografia,
            'image'       => $fotoUrl,
            'url'         => $canonicalUrl,
            'affiliation' => [
                '@type' => 'Organization',
                'name'  => "Bloque Parlamentario {$bloque}",
            ],
            'workLocation' => [
                '@type'   => 'Place',
                'name'    => "Honorable Congreso de la Nación Argentina — Representante por {$provincia}",
                'address' => [
                    '@type'           => 'PostalAddress',
                    'addressRegion'   => $provincia,
                    'addressCountry'  => 'AR',
                ],
            ],
            'sameAs' => [
                "https://es.wikipedia.org/wiki/" . rawurlencode($wikiSlug),
            ],
        ];

        $jsonLdSchemas[] = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type'    => 'ListItem',
                    'position' => 1,
                    'name'     => 'DipuData — Congreso Argentino',
                    'item'     => "{$baseUrl}/",
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 2,
                    'name'     => $camara,
                    'item'     => "{$baseUrl}/?camara=" . rawurlencode($camara),
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 3,
                    'name'     => $nombre,
                    'item'     => $canonicalUrl,
                ],
            ],
        ];

        // Construir tabla semántica <noscript> para indexación completa en Googlebot
        $escNombre = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $escCargo = htmlspecialchars($cargo, ENT_QUOTES, 'UTF-8');
        $escProvincia = htmlspecialchars($provincia, ENT_QUOTES, 'UTF-8');
        $escBloque = htmlspecialchars($bloque, ENT_QUOTES, 'UTF-8');
        $escBio = htmlspecialchars($biografia, ENT_QUOTES, 'UTF-8');
        $escCuit = htmlspecialchars((string)$legislador['cuit'], ENT_QUOTES, 'UTF-8');

        $rowsVotosHtml = '';
        foreach ($votos as $v) {
            $leyIdEsc = htmlspecialchars((string)$v['ley_id'], ENT_QUOTES, 'UTF-8');
            $tituloEsc = htmlspecialchars((string)$v['titulo'], ENT_QUOTES, 'UTF-8');
            $ejeEsc = htmlspecialchars((string)$v['eje_tematico'], ENT_QUOTES, 'UTF-8');
            $fechaEsc = htmlspecialchars((string)$v['fecha_votacion'], ENT_QUOTES, 'UTF-8');
            $sentidoEsc = htmlspecialchars((string)$v['sentido_voto'], ENT_QUOTES, 'UTF-8');
            $rowsVotosHtml .= "<tr><td>{$leyIdEsc}</td><td>{$tituloEsc}</td><td>{$ejeEsc}</td><td>{$fechaEsc}</td><td><strong>{$sentidoEsc}</strong></td></tr>";
        }

        $rowsPatrimonioHtml = '';
        foreach ($patrimonio['serie'] as $pRow) {
            $anioP = (int)$pRow['anio_fiscal'];
            $nomP = number_format((float)$pRow['monto_pesos_nominal'], 0, ',', '.');
            $constP = number_format((float)$pRow['patrimonio_pesos_constantes'], 0, ',', '.');
            $usdP = number_format((float)$pRow['patrimonio_usd'], 0, ',', '.');
            $mepP = number_format((float)$pRow['dolar_mep_cierre'], 2, ',', '.');
            $rowsPatrimonioHtml .= "<tr><td>{$anioP}</td><td>ARS {$nomP}</td><td>ARS {$constP}</td><td>USD {$usdP}</td><td>ARS {$mepP}</td></tr>";
        }

        $noscriptHtml = <<<HTML
        <article class="seo-noscript-profile">
          <header>
            <h1>{$escNombre} — {$escCargo} por {$escProvincia} ({$escBloque})</h1>
            <p><strong>CUIT Oficial:</strong> {$escCuit} | <strong>Cámara:</strong> {$camara} | <strong>Distrito:</strong> {$escProvincia} | <strong>Bloque:</strong> {$escBloque}</p>
            <p>{$escBio}</p>
          </header>
          <section>
            <h2>Indicadores Clave de Desempeño Parlamentario y Patrimonial</h2>
            <ul>
              <li><strong>Asistencia Histórica a Sesiones:</strong> {$asist}%</li>
              <li><strong>Lealtad Partidaria al Bloque {$escBloque}:</strong> {$lealtad}%</li>
              <li><strong>Patrimonio Declarado Actual (Deflactado USD MEP):</strong> USD {$usdActual} (Variación Real: {$varUsd})</li>
              <li><strong>Huella Ideológica de Votación:</strong> Salud {$perfilTematico['vectores']['Salud']}% | Educación {$perfilTematico['vectores']['Educacion']}% | Libre Mercado {$perfilTematico['vectores']['MercadoLibre']}% | Proteccionismo {$perfilTematico['vectores']['Proteccionismo']}% | Laboral {$perfilTematico['vectores']['Laboral']}%</li>
            </ul>
          </section>
          <section>
            <h2>Evolución Patrimonial Deflactada (Declaraciones Juradas Oficina Anticorrupción)</h2>
            <table>
              <thead>
                <tr><th>Año Fiscal</th><th>Patrimonio Nominal (ARS)</th><th>Pesos Constantes (Ajustado IPC)</th><th>Patrimonio Real (USD MEP)</th><th>Tipo de Cambio MEP</th></tr>
              </thead>
              <tbody>{$rowsPatrimonioHtml}</tbody>
            </table>
          </section>
          <section>
            <h2>Historial de Votaciones Nominales en Leyes Clave</h2>
            <table>
              <thead>
                <tr><th>Expediente / Ley</th><th>Proyecto de Ley</th><th>Eje Temático</th><th>Fecha</th><th>Voto Emitido</th></tr>
              </thead>
              <tbody>{$rowsVotosHtml}</tbody>
            </table>
          </section>
          <nav><a href="{$baseUrl}/">Volver al Directorio del Congreso Argentino</a> | <a href="{$baseUrl}/match">Calcular Match Electoral</a></nav>
        </article>
HTML;
    } else {
        http_response_code(404);
        $seoTitle = 'Legislador no encontrado | DipuData — LegisData';
    }
}
// RUTA B: /match
elseif ($cleanPath === '/match') {
    $seoTitle = 'Match Electoral Congreso Argentino | Calculá tu Afinidad con Diputados y Senadores | DipuData';
    $seoDescription = 'Respondé 8 preguntas sobre leyes reales votadas en el Congreso Argentino (Salud, Educación, Libre Mercado y Proteccionismo) y descubrí mediante Similitud de Coseno qué Diputados y Senadores votan exactamente como vos pensás.';
    $canonicalUrl = "{$baseUrl}/match";
    $ogType = 'website';
    $ogImage = "{$baseUrl}/api/og?type=match";

    $jsonLdSchemas[] = [
        '@context'            => 'https://schema.org',
        '@type'               => 'WebApplication',
        'name'                => 'Match Electoral — DipuData (LegisData)',
        'url'                 => $canonicalUrl,
        'applicationCategory' => 'CivicApplication',
        'operatingSystem'     => 'All',
        'description'         => $seoDescription,
        'offers'              => [
            '@type'         => 'Offer',
            'price'         => '0',
            'priceCurrency' => 'ARS',
        ],
    ];

    $jsonLdSchemas[] = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => [
            [
                '@type'          => 'Question',
                'name'           => '¿Cómo funciona el algoritmo de Match Electoral de DipuData?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => 'El motor utiliza Similitud de Coseno en un espacio vectorial de 4 dimensiones (Salud Pública, Educación y Ciencia, Libre Mercado y Proteccionismo) comparando tus respuestas con el historial real de votaciones nominales de cada Diputado y Senador de la Nación Argentina, excluyendo las ausencias.',
                ],
            ],
            [
                '@type'          => 'Question',
                'name'           => '¿De dónde provienen los datos de votaciones y patrimonio de los legisladores?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => 'Todos los registros provienen de los portales de Datos Abiertos de la Honorable Cámara de Diputados de la Nación (HCDN), del Honorable Senado de la Nación y de las Declaraciones Juradas Patrimoniales Integrales publicadas por la Oficina Anticorrupción (OA), deflactadas por Dólar MEP e IPC INDEC.',
                ],
            ],
        ],
    ];

    $noscriptHtml = <<<HTML
    <section class="seo-noscript-match">
      <h1>Match Electoral del Congreso Argentino — Test de Afinidad Legislativa</h1>
      <p>Calculá matemáticamente mediante Similitud de Coseno tu coincidencia ideológica con los Diputados y Senadores de la Nación Argentina sobre leyes reales (Ley de Bases, DNU 70/2023, Financiamiento Universitario, Etiquetado Frontal, Ley de Alquileres y Salud Pública).</p>
      <p><a href="{$baseUrl}/">Explorar todos los perfiles de Diputados y Senadores en DipuData</a></p>
    </section>
HTML;
}
// RUTA C: / (Portada & Directorio Completo)
else {
    $canonicalUrl = "{$baseUrl}/";
    $todosLegisladores = ssrFetchAllLegisladores($pdo);

    $jsonLdSchemas[] = [
        '@context'        => 'https://schema.org',
        '@type'           => 'WebSite',
        '@id'             => "{$baseUrl}/#website",
        'url'             => "{$baseUrl}/",
        'name'            => 'DipuData — LegisData Argentina',
        'alternateName'   => 'LegisData: Conocé a tus Representantes',
        'description'     => $seoDescription,
        'inLanguage'      => 'es-AR',
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => "{$baseUrl}/?q={search_term_string}",
            'query-input' => 'required name=search_term_string',
        ],
    ];

    $jsonLdSchemas[] = [
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        '@id'      => "{$baseUrl}/#organization",
        'name'     => 'DipuData (LegisData)',
        'url'      => "{$baseUrl}/",
        'logo'     => "{$baseUrl}/favicon.svg",
    ];

    $jsonLdSchemas[] = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Dataset',
        'name'        => 'Base de Datos Abierta de Votaciones Nominales y Evolución Patrimonial DDJJ del Congreso de la Nación Argentina',
        'description' => 'Dataset público auditado con votaciones nominales por eje temático, porcentaje de asistencia, lealtad partidaria y serie histórica de Declaraciones Juradas Patrimoniales deflactadas en USD MEP y Pesos Constantes (IPC) de Diputados y Senadores Nacionales.',
        'url'         => "{$baseUrl}/",
        'license'     => 'https://creativecommons.org/licenses/by/4.0/',
        'creator'     => [
            '@type' => 'Organization',
            'name'  => 'DipuData — LegisData',
        ],
        'keywords'    => [
            'Diputados Argentina',
            'Senadores Argentina',
            'Votaciones Congreso Argentino',
            'Declaraciones Juradas Patrimoniales DDJJ',
            'Dólar MEP',
            'Match Electoral Argentina',
        ],
    ];

    $linksHtml = '';
    foreach ($todosLegisladores as $leg) {
        $lId = (int)$leg['id'];
        $lNombre = htmlspecialchars((string)$leg['nombre_completo'], ENT_QUOTES, 'UTF-8');
        $lCamara = htmlspecialchars((string)$leg['camara'], ENT_QUOTES, 'UTF-8');
        $lProv = htmlspecialchars((string)$leg['provincia'], ENT_QUOTES, 'UTF-8');
        $lBloque = htmlspecialchars((string)$leg['bloque_actual'], ENT_QUOTES, 'UTF-8');
        $lAsist = number_format((float)$leg['asistencia_historica'], 1);
        $linksHtml .= "<li><a href=\"{$baseUrl}/perfil/{$lId}\"><strong>{$lNombre}</strong> — {$lCamara} por {$lProv} ({$lBloque}) — Asistencia: {$lAsist}%</a></li>\n";
    }

    $noscriptHtml = <<<HTML
    <main class="seo-noscript-directory">
      <h1>DipuData (LegisData) — Auditoría de Diputados y Senadores del Congreso de la Nación Argentina</h1>
      <p>Consultá votaciones nominales, asistencia histórica, lealtad partidaria y evolución patrimonial real en USD MEP e IPC de los representantes del Congreso Argentino.</p>
      <p><a href="{$baseUrl}/match">Ir al Match Electoral — Calculá tu afinidad con los legisladores</a></p>
      <h2>Directorio de Legisladores Nacionales Auditados</h2>
      <ul>
        {$linksHtml}
      </ul>
    </main>
HTML;
}

// ============================================================================
// LECTURA DEL SHELL HTML COMPILADO DE VITE E INYECCIÓN SSR
// ============================================================================
$distHtmlPath = __DIR__ . '/dist/index.html';
$fallbackHtmlPath = __DIR__ . '/index.html';
$rawHtml = is_readable($distHtmlPath)
    ? (string)file_get_contents($distHtmlPath)
    : (is_readable($fallbackHtmlPath) ? (string)file_get_contents($fallbackHtmlPath) : '');

$escTitle = htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8');
$escDesc  = htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8');
$escCanon = htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8');
$escOgImg = htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8');
$escOgType = htmlspecialchars($ogType, ENT_QUOTES, 'UTF-8');

$jsonLdBlock = '';
foreach ($jsonLdSchemas as $schema) {
    $encoded = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $jsonLdBlock .= "<script type=\"application/ld+json\">\n{$encoded}\n</script>\n";
}

// Reemplazar etiquetas estáticas en dist/index.html por las etiquetas dinámicas SSR
$html = preg_replace('/<title>.*?<\/title>/is', "<title>{$escTitle}</title>", $rawHtml) ?? $rawHtml;
$html = preg_replace('/<meta\s+name="description"\s+content="[^"]*"\s*\/?>/is', "<meta name=\"description\" content=\"{$escDesc}\" />", $html) ?? $html;
$html = preg_replace('/<link\s+rel="canonical"\s+href="[^"]*"\s*\/?>/is', "<link rel=\"canonical\" href=\"{$escCanon}\" />", $html) ?? $html;
$html = preg_replace('/<meta\s+property="og:title"\s+content="[^"]*"\s*\/?>/is', "<meta property=\"og:title\" content=\"{$escTitle}\" />", $html) ?? $html;
$html = preg_replace('/<meta\s+property="og:description"\s+content="[^"]*"\s*\/?>/is', "<meta property=\"og:description\" content=\"{$escDesc}\" />", $html) ?? $html;
$html = preg_replace('/<meta\s+property="og:type"\s+content="[^"]*"\s*\/?>/is', "<meta property=\"og:type\" content=\"{$escOgType}\" />", $html) ?? $html;
$html = preg_replace('/<meta\s+property="og:url"\s+content="[^"]*"\s*\/?>/is', "<meta property=\"og:url\" content=\"{$escCanon}\" />", $html) ?? $html;
$html = preg_replace('/<meta\s+property="og:image"\s+content="[^"]*"\s*\/?>/is', "<meta property=\"og:image\" content=\"{$escOgImg}\" />", $html) ?? $html;

$extraTwitterMeta = <<<META
    <meta name="twitter:title" content="{$escTitle}" />
    <meta name="twitter:description" content="{$escDesc}" />
    <meta name="twitter:image" content="{$escOgImg}" />
    {$jsonLdBlock}
META;

$html = str_replace('</head>', $extraTwitterMeta . "\n  </head>", $html);
$html = str_replace('<div id="root"></div>', "<div id=\"root\"></div>\n    <noscript>{$noscriptHtml}</noscript>", $html);

echo $html;
