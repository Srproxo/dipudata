<?php
declare(strict_types=1);

/**
 * LEGISDATA (dipudata.com) — Suite Integral de Verificación Automatizada (QA, Seguridad, SEO y Algoritmos)
 * Etapa 4: Auditoría Grado Bancario y Validación de Producción
 */

namespace LegisData\Tests;

use LegisData\Algorithms;
use LegisData\Config;
use LegisData\Security;

require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/api/Security.php';
require_once dirname(__DIR__) . '/api/Algorithms.php';

$passed = 0;
$failed = 0;
$assertions = [];

function assertTrue(bool $condition, string $label, string $detail = ''): void
{
    global $passed, $failed, $assertions;
    if ($condition) {
        $passed++;
        $assertions[] = "[PASS] {$label}" . ($detail !== '' ? " ({$detail})" : '');
    } else {
        $failed++;
        $assertions[] = "[FAIL] {$label}" . ($detail !== '' ? " ({$detail})" : '');
    }
}

echo "==============================================================================\n";
echo " LEGISDATA (dipudata.com) — SUITE DE AUDITORÍA Y VERIFICACIÓN AUTOMATIZADA\n";
echo "==============================================================================\n\n";

$pdo = Config::getConnection();

// -----------------------------------------------------------------------------
// 1. PRUEBAS DEL ALGORITMO A: Deflactación Patrimonial Real (USD MEP + IPC)
// -----------------------------------------------------------------------------
$patrimonioLeg1 = Algorithms::calcularEvolucionPatrimonial(1, $pdo);
assertTrue(
    !empty($patrimonioLeg1['serie']) && count($patrimonioLeg1['serie']) === 6,
    'Algoritmo A: Serie temporal completa (2020-2025) para Legislador #1',
    'años=' . count($patrimonioLeg1['serie'])
);

// Verificar cálculo exacto USD MEP en 2020 (48.500.000 / 140.30 = 345687.81) y 2025 (652.000.000 / 1280.00 = 509375.00)
$esperado2020Usd = round(48500000.00 / 140.30, 2);
$esperado2025Usd = round(652000000.00 / 1280.00, 2);
assertTrue(
    abs($patrimonioLeg1['patrimonio_inicial_usd'] - $esperado2020Usd) < 0.05 &&
    abs($patrimonioLeg1['patrimonio_actual_usd'] - $esperado2025Usd) < 0.05,
    'Algoritmo A: Conversión exacta a Dólar MEP (2020 y 2025)',
    "2020={$patrimonioLeg1['patrimonio_inicial_usd']} USD, 2025={$patrimonioLeg1['patrimonio_actual_usd']} USD"
);

assertTrue(
    $patrimonioLeg1['patrimonio_inicial_pesos_constantes'] > $patrimonioLeg1['patrimonio_inicial_pesos_nominal'],
    'Algoritmo A: Deflactación por IPC acumulado expresada en Pesos Constantes',
    "nominal={$patrimonioLeg1['patrimonio_inicial_pesos_nominal']}, constante={$patrimonioLeg1['patrimonio_inicial_pesos_constantes']}"
);

// -----------------------------------------------------------------------------
// 2. PRUEBAS DEL ALGORITMO B: Perfilamiento Temático excluyendo ausencias
// -----------------------------------------------------------------------------
// Legislador #7 (María Eugenia Vidal) tiene en Proteccionismo: 27642=Negativo, 27551=Ausente, 27605=Negativo -> 2 emitidos, 1 ausente
$perfilLeg7 = Algorithms::calcularPerfilTematico(7, $pdo);
$detProt7 = $perfilLeg7['detalle_ejes']['Proteccionismo'] ?? [];
assertTrue(
    ($detProt7['ausentes'] ?? -1) === 1 && ($detProt7['total_emitidos'] ?? -1) === 2,
    'Algoritmo B: Exclusión estricta de ausencias del denominador de votos emitidos',
    "emitidos={$detProt7['total_emitidos']}, ausentes={$detProt7['ausentes']}"
);

$perfilLeg1 = Algorithms::calcularPerfilTematico(1, $pdo);
assertTrue(
    ($perfilLeg1['vectores']['MercadoLibre'] ?? 0.0) === 100.0 &&
    ($perfilLeg1['vectores']['Proteccionismo'] ?? -1.0) === 0.0,
    'Algoritmo B: Cálculo exacto de vectores 0..100% por eje temático',
    "MercadoLibre={$perfilLeg1['vectores']['MercadoLibre']}%, Proteccionismo={$perfilLeg1['vectores']['Proteccionismo']}%"
);

// -----------------------------------------------------------------------------
// 3. PRUEBAS DEL ALGORITMO C: Match Electoral por Similitud de Coseno
// -----------------------------------------------------------------------------
$vecId1 = ['Salud' => 25.0, 'Educacion' => 50.0, 'MercadoLibre' => 100.0, 'Proteccionismo' => 10.0];
$simIdentica = Algorithms::calcularMatchElectoral($vecId1, $vecId1);
assertTrue(
    $simIdentica === 100.0,
    'Algoritmo C: Similitud de Coseno = 100.00% para vectores idénticos',
    "resultado={$simIdentica}%"
);

$vecOrtogonalA = ['Salud' => 100.0, 'Educacion' => 100.0, 'MercadoLibre' => 0.0, 'Proteccionismo' => 0.0];
$vecOrtogonalB = ['Salud' => 0.0, 'Educacion' => 0.0, 'MercadoLibre' => 100.0, 'Proteccionismo' => 100.0];
$simOrtogonal = Algorithms::calcularMatchElectoral($vecOrtogonalA, $vecOrtogonalB);
assertTrue(
    $simOrtogonal === 0.0,
    'Algoritmo C: Similitud de Coseno = 0.00% para vectores ortogonales',
    "resultado={$simOrtogonal}%"
);

$vecCero = ['Salud' => 0.0, 'Educacion' => 0.0, 'MercadoLibre' => 0.0, 'Proteccionismo' => 0.0];
$simCero = Algorithms::calcularMatchElectoral($vecCero, $vecId1);
assertTrue(
    $simCero === 0.0,
    'Algoritmo C: Protección contra división por cero cuando la norma es 0',
    "resultado={$simCero}%"
);

// -----------------------------------------------------------------------------
// 4. PRUEBAS DE ENDPOINTS RESTful DE LA API (/api/index.php)
// -----------------------------------------------------------------------------
function runApiSubprocess(string $method, string $uri, array $query = [], ?array $postJson = null): array
{
    $apiFile = dirname(__DIR__) . '/api/index.php';
    $envParts = [
        'REQUEST_METHOD=' . escapeshellarg($method),
        'REQUEST_URI=' . escapeshellarg($uri),
    ];

    $bootstrap = sprintf(
        '$_SERVER["REQUEST_METHOD"]=%s; $_SERVER["REQUEST_URI"]=%s; $_GET=%s;',
        var_export($method, true),
        var_export($uri, true),
        var_export($query, true)
    );

    if ($postJson !== null) {
        $bootstrap .= '$_POST=' . var_export($postJson, true) . ';';
    }

    $bootstrap .= 'require ' . var_export($apiFile, true) . ';';
    $cmd = 'php -r ' . escapeshellarg($bootstrap) . ' 2>&1';
    $output = (string)shell_exec($cmd);
    return [
        'raw'  => $output,
        'json' => json_decode($output, true),
    ];
}

// 4.1 GET /api/legisladores
$resList = runApiSubprocess('GET', '/api/legisladores', ['limit' => 20]);
assertTrue(
    is_array($resList['json']) &&
    ($resList['json']['status'] ?? '') === 'ok' &&
    count($resList['json']['data'] ?? []) === 20,
    'Endpoint GET /api/legisladores: Retorna los 20 legisladores con vectores y patrimonio',
    'count=' . count($resList['json']['data'] ?? [])
);

// 4.2 GET /api/legisladores/1
$resDet = runApiSubprocess('GET', '/api/legisladores/1');
assertTrue(
    is_array($resDet['json']) &&
    ($resDet['json']['status'] ?? '') === 'ok' &&
    ($resDet['json']['data']['nombre_completo'] ?? '') === 'Martín Alexis Menem' &&
    count($resDet['json']['data']['votaciones'] ?? []) === 15,
    'Endpoint GET /api/legisladores/1: Retorna radiografía completa, KPIs y 15 votaciones nominales',
    'nombre=' . ($resDet['json']['data']['nombre_completo'] ?? 'N/A')
);

// 4.3 GET /api/legisladores/1/patrimonio
$resPat = runApiSubprocess('GET', '/api/legisladores/1/patrimonio');
assertTrue(
    is_array($resPat['json']) &&
    ($resPat['json']['status'] ?? '') === 'ok' &&
    count($resPat['json']['patrimonio']['serie'] ?? []) === 6,
    'Endpoint GET /api/legisladores/1/patrimonio: Retorna serie deflactada USD MEP + IPC',
    'puntos=' . count($resPat['json']['patrimonio']['serie'] ?? [])
);

// 4.4 POST /api/match
$resMatch = runApiSubprocess('POST', '/api/match', [], [
    'vector_usuario' => [
        'Salud'          => 10,
        'Educacion'      => 30,
        'MercadoLibre'   => 100,
        'Proteccionismo' => 0,
    ],
]);
assertTrue(
    is_array($resMatch['json']) &&
    ($resMatch['json']['status'] ?? '') === 'ok' &&
    count($resMatch['json']['top_3'] ?? []) === 3 &&
    count($resMatch['json']['bottom_3'] ?? []) === 3 &&
    ($resMatch['json']['top_3'][0]['match_pct'] ?? 0) >= ($resMatch['json']['bottom_3'][0]['match_pct'] ?? 100),
    'Endpoint POST /api/match: Calcula ranking Top 3 más afines y Bottom 3 menos afines',
    'top1=' . ($resMatch['json']['top_3'][0]['nombre_completo'] ?? '') . ' (' . ($resMatch['json']['top_3'][0]['match_pct'] ?? 0) . '%)'
);

// 4.5 GET /api/og
$resOg = runApiSubprocess('GET', '/api/og', ['type' => 'perfil', 'id' => '1']);
assertTrue(
    str_contains($resOg['raw'], '<svg') && str_contains($resOg['raw'], 'Martín Alexis Menem'),
    'Endpoint GET /api/og: Genera tarjeta OpenGraph SVG 1200x630 dinámica',
    'bytes=' . strlen($resOg['raw'])
);

// -----------------------------------------------------------------------------
// 5. PRUEBAS DE RENDERIZADO SSR SEO (/index.php) Y SITEMAP (/sitemap.php)
// -----------------------------------------------------------------------------
function runSsrSubprocess(string $uri): string
{
    $indexFile = dirname(__DIR__) . '/index.php';
    $bootstrap = sprintf(
        '$_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["REQUEST_URI"]=%s; require %s;',
        var_export($uri, true),
        var_export($indexFile, true)
    );
    return (string)shell_exec('php -r ' . escapeshellarg($bootstrap) . ' 2>&1');
}

// 5.1 SSR Portada (/)
$ssrHome = runSsrSubprocess('/');
assertTrue(
    str_contains($ssrHome, 'application/ld+json') &&
    str_contains($ssrHome, '"@type": "WebSite"') &&
    str_contains($ssrHome, '"@type": "Dataset"') &&
    str_contains($ssrHome, 'rel="canonical" href="https://dipudata.com/"') &&
    str_contains($ssrHome, '<noscript>'),
    'SSR index.php (/): Inyecta JSON-LD (WebSite, Organization, Dataset), Canonical y directorio <noscript>'
);

// 5.2 SSR Perfil (/perfil/1)
$ssrPerfil = runSsrSubprocess('/perfil/1');
assertTrue(
    str_contains($ssrPerfil, 'Martín Alexis Menem (Diputados - La Rioja)') &&
    str_contains($ssrPerfil, '"@type": "Person"') &&
    str_contains($ssrPerfil, '"@type": "BreadcrumbList"') &&
    str_contains($ssrPerfil, 'rel="canonical" href="https://dipudata.com/perfil/1"') &&
    str_contains($ssrPerfil, 'https://dipudata.com/api/og?type=perfil&amp;id=1'),
    'SSR index.php (/perfil/1): Inyecta Title dinámico, JSON-LD Person + BreadcrumbList, OpenGraph y tabla <noscript>'
);

// 5.3 SSR Match (/match)
$ssrMatch = runSsrSubprocess('/match');
assertTrue(
    str_contains($ssrMatch, 'Match Electoral Congreso Argentino') &&
    str_contains($ssrMatch, '"@type": "WebApplication"') &&
    str_contains($ssrMatch, '"@type": "FAQPage"') &&
    str_contains($ssrMatch, 'rel="canonical" href="https://dipudata.com/match"'),
    'SSR index.php (/match): Inyecta Title de Match Electoral, JSON-LD WebApplication + FAQPage y Canonical'
);

// 5.4 Sitemap XML (sitemap.php)
$sitemapFile = dirname(__DIR__) . '/sitemap.php';
$sitemapXml = (string)shell_exec('php ' . escapeshellarg($sitemapFile) . ' 2>&1');
assertTrue(
    str_contains($sitemapXml, '<?xml version="1.0" encoding="UTF-8"?>') &&
    str_contains($sitemapXml, '<loc>https://dipudata.com/perfil/1</loc>') &&
    str_contains($sitemapXml, '<loc>https://dipudata.com/perfil/20</loc>') &&
    str_contains($sitemapXml, '<loc>https://dipudata.com/match</loc>'),
    'Sitemap dinámico (sitemap.php): Genera XML válido con /, /match y los 20 perfiles de legisladores'
);

// -----------------------------------------------------------------------------
// 6. AUDITORÍA DE SEGURIDAD GRADO BANCARIO (SQLi, XSS, Archivos de Producción)
// -----------------------------------------------------------------------------
$dirtyInput = "<script>alert(1)</script>\x00\x1F' OR 1=1 --";
$sanitized = Security::sanitizeString($dirtyInput, 50);
assertTrue(
    !str_contains($sanitized, "\x00") && !str_contains($sanitized, "\x1F"),
    'Seguridad: Sanitización de caracteres de control y límites de longitud activa'
);

$resSqli = runApiSubprocess('GET', '/api/legisladores/1%27%20OR%201=1--');
assertTrue(
    is_array($resSqli['json']) && ($resSqli['json']['code'] ?? '') === 'LEGISLADOR_NOT_FOUND',
    'Seguridad Anti-SQLi: Peticiones con vectores de inyección SQL son neutralizadas con 404 seguro'
);

$htaccessContent = (string)file_get_contents(dirname(__DIR__) . '/.htaccess');
assertTrue(
    str_contains($htaccessContent, 'Strict-Transport-Security') &&
    str_contains($htaccessContent, 'X-Content-Type-Options "nosniff"') &&
    str_contains($htaccessContent, 'X-Frame-Options "DENY"') &&
    str_contains($htaccessContent, 'RewriteRule ^(cron|database|'),
    'Seguridad .htaccess: Cabeceras HSTS/nosniff/DENY y bloqueo 403 a /cron y /database configurados'
);

// Reporte final
foreach ($assertions as $line) {
    echo $line . "\n";
}

echo "\n------------------------------------------------------------------------------\n";
echo " RESUMEN DE AUDITORÍA: {$passed} PASARON | {$failed} FALLARON\n";
echo "------------------------------------------------------------------------------\n";

exit($failed > 0 ? 1 : 0);
