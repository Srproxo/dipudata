<?php
declare(strict_types=1);

/**
 * API pública de solo lectura de DipuData.
 *   GET /api/legisladores?camara=&provincia=&bloque=&q=&pagina=&limite=
 *   GET /api/legisladores/{diputado|senador}/{slug}
 *   GET /api/bloques        GET /api/provincias
 *   GET /api/votaciones?camara=&anio=   GET /api/votaciones/{id}   GET /api/openapi.json
 * Devuelve únicamente datos de storage/verificado, con su fuente.
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Datos;

function json_salida(array $payload, int $codigo = 200): never
{
    encabezados_seguridad('application/json; charset=utf-8', $codigo === 200 ? 300 : 0);
    header('Access-Control-Allow-Origin: *');
    http_response_code($codigo);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function publico(array $l): array
{
    return [
        'slug' => $l['slug'], 'nombre' => $l['nombre'], 'camara' => $l['camara'], 'provincia' => $l['provincia'],
        'bloque' => $l['bloque'], 'mandato' => $l['mandato'], 'jura' => $l['jura'], 'inicio' => $l['inicio'],
        'foto' => $l['foto'], 'url' => SITE_URL . Datos::urlLegislador($l),
        'verificado' => $l['verificado'], 'fuentes' => $l['fuentes'],
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_salida(['error' => 'Método no permitido'], 405);
}

$ruta = '/' . trim((string)preg_replace('#^/api#', '', (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)), '/');

if ($ruta === '/legisladores') {
    $camara = in_array($_GET['camara'] ?? '', ['Diputados', 'Senadores'], true) ? $_GET['camara'] : null;
    $nq = Datos::normalizar(mb_substr((string)($_GET['q'] ?? ''), 0, 80));
    $prov = is_string($_GET['provincia'] ?? null) ? Datos::provincia($_GET['provincia']) : null;
    $bloque = is_string($_GET['bloque'] ?? null) ? $_GET['bloque'] : '';
    $lista = array_values(array_filter(Datos::legisladores($camara), static function (array $l) use ($nq, $prov, $bloque): bool {
        if ($prov && $l['provincia'] !== $prov['nombre']) return false;
        if ($bloque !== '' && trim((string)preg_replace('/[^a-z0-9]+/', '-', Datos::normalizar($l['bloque'])), '-') !== $bloque) return false;
        if ($nq !== '' && !str_contains(Datos::normalizar($l['nombre']), $nq)) return false;
        return true;
    }));
    $limite = max(1, min(100, (int)($_GET['limite'] ?? 50)));
    $pagina = max(1, (int)($_GET['pagina'] ?? 1));
    json_salida([
        'total' => count($lista), 'pagina' => $pagina, 'limite' => $limite,
        'datos' => array_map('publico', array_slice($lista, ($pagina - 1) * $limite, $limite)),
    ]);
}

if (preg_match('#^/legisladores/(diputado|senador)/([a-z0-9-]+)$#', $ruta, $m)) {
    $l = Datos::legislador($m[1] === 'senador' ? 'Senadores' : 'Diputados', $m[2]);
    $l ? json_salida(['datos' => publico($l)]) : json_salida(['error' => 'No encontrado'], 404);
}

if ($ruta === '/bloques') {
    json_salida(['datos' => array_map(static fn($b) => [
        'camara' => $b['camara'], 'nombre' => $b['nombre'], 'slug' => $b['slug'], 'bancas' => $b['cantidad'],
    ], Datos::bloques())]);
}

if ($ruta === '/provincias') {
    json_salida(['datos' => Datos::provincias()]);
}

if ($ruta === '/votaciones') {
    $camara = ['Diputados' => 'D', 'Senadores' => 'S'][$_GET['camara'] ?? ''] ?? null;
    $anio = preg_match('/^\d{4}$/', (string)($_GET['anio'] ?? '')) ? (string)$_GET['anio'] : '';
    $lista = array_values(array_filter(Datos::votaciones(), static fn(array $v): bool => ($camara === null || ($v['c'] ?? '') === $camara) && ($anio === '' || str_starts_with((string)($v['f'] ?? ''), $anio))));
    $limite = max(1, min(100, (int)($_GET['limite'] ?? 50)));
    $pagina = max(1, (int)($_GET['pagina'] ?? 1));
    json_salida([
        'total' => count($lista), 'pagina' => $pagina, 'limite' => $limite,
        'campos' => 'id, c (D/S), f fecha, h hora, t título, res resultado, m mayoría, a afirmativos, n negativos, b abstenciones, u ausentes',
        'datos' => array_slice($lista, ($pagina - 1) * $limite, $limite),
        'fuentes' => 'Votaciones nominales: HCDN (datos.hcdn.gob.ar y votaciones.hcdn.gob.ar) y Senado (senado.gob.ar/votaciones). Ver /metodologia.',
    ]);
}

if (preg_match('#^/votaciones/(S?\d{1,6})$#', $ruta, $m)) {
    $v = Datos::votacion($m[1]);
    $v ? json_salida(['id' => $m[1], 'datos' => $v, 'url' => SITE_URL . '/votaciones/' . $m[1]]) : json_salida(['error' => 'No encontrado'], 404);
}

if ($ruta === '/openapi.json') {
    $pag = [['name' => 'pagina', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1]], ['name' => 'limite', 'in' => 'query', 'schema' => ['type' => 'integer', 'maximum' => 100]]];
    $ok = ['200' => ['description' => 'JSON']];
    json_salida([
        'openapi' => '3.0.3',
        'info' => ['title' => 'API de DipuData', 'version' => '1.0', 'description' => 'Datos verificados del Congreso de la Nación Argentina. Solo lectura. Licencia CC BY 4.0: citar como "DipuData (dipudata.com)". Cada dato lleva su fuente oficial.',
            'license' => ['name' => 'CC BY 4.0', 'url' => 'https://creativecommons.org/licenses/by/4.0/']],
        'servers' => [['url' => SITE_URL . '/api']],
        'paths' => [
            '/' => ['get' => ['summary' => 'Índice de la API', 'responses' => $ok]],
            '/legisladores' => ['get' => ['summary' => 'Legisladores en ejercicio', 'parameters' => array_merge([
                ['name' => 'camara', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['Diputados', 'Senadores']]],
                ['name' => 'provincia', 'in' => 'query', 'schema' => ['type' => 'string']],
                ['name' => 'bloque', 'in' => 'query', 'schema' => ['type' => 'string']],
                ['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string']]], $pag), 'responses' => $ok]],
            '/legisladores/{camara}/{slug}' => ['get' => ['summary' => 'Un legislador', 'parameters' => [
                ['name' => 'camara', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['diputado', 'senador']]],
                ['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => $ok]],
            '/bloques' => ['get' => ['summary' => 'Bloques y bancas', 'responses' => $ok]],
            '/provincias' => ['get' => ['summary' => 'Provincias', 'responses' => $ok]],
            '/votaciones' => ['get' => ['summary' => 'Votaciones nominales (cabeceras)', 'parameters' => array_merge([
                ['name' => 'camara', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['Diputados', 'Senadores']]],
                ['name' => 'anio', 'in' => 'query', 'schema' => ['type' => 'integer']]], $pag), 'responses' => $ok]],
            '/votaciones/{id}' => ['get' => ['summary' => 'Una votación con el voto de cada legislador', 'parameters' => [
                ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => $ok]],
        ],
    ]);
}

if ($ruta === '/') {
    json_salida([
        'nombre' => 'API de DipuData', 'licencia' => 'CC BY 4.0', 'documentacion' => SITE_URL . '/datos', 'openapi' => SITE_URL . '/api/openapi.json',
        'endpoints' => ['/api/legisladores', '/api/legisladores/{diputado|senador}/{slug}', '/api/bloques', '/api/provincias', '/api/votaciones', '/api/votaciones/{id}', '/api/openapi.json'],
    ]);
}

if ($ruta === '/votaciones') {
    $camara = ['Diputados' => 'D', 'Senadores' => 'S'][$_GET['camara'] ?? ''] ?? null;
    $anio = preg_match('/^\d{4}$/', (string)($_GET['anio'] ?? '')) ? (string)$_GET['anio'] : '';
    $lista = array_values(array_filter(Datos::votaciones(), static fn(array $v): bool => ($camara === null || ($v['c'] ?? '') === $camara) && ($anio === '' || str_starts_with((string)($v['f'] ?? ''), $anio))));
    $limite = max(1, min(100, (int)($_GET['limite'] ?? 50)));
    $pagina = max(1, (int)($_GET['pagina'] ?? 1));
    json_salida([
        'total' => count($lista), 'pagina' => $pagina, 'limite' => $limite,
        'campos' => 'id, c (D/S), f fecha, h hora, t título, res resultado, m mayoría, a afirmativos, n negativos, b abstenciones, u ausentes',
        'datos' => array_slice($lista, ($pagina - 1) * $limite, $limite),
        'fuentes' => 'Votaciones nominales: HCDN (datos.hcdn.gob.ar y votaciones.hcdn.gob.ar) y Senado (senado.gob.ar/votaciones). Ver /metodologia.',
    ]);
}

if (preg_match('#^/votaciones/(S?\d{1,6})$#', $ruta, $m)) {
    $v = Datos::votacion($m[1]);
    $v ? json_salida(['id' => $m[1], 'datos' => $v, 'url' => SITE_URL . '/votaciones/' . $m[1]]) : json_salida(['error' => 'No encontrado'], 404);
}

if ($ruta === '/openapi.json') {
    $pag = [['name' => 'pagina', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1]], ['name' => 'limite', 'in' => 'query', 'schema' => ['type' => 'integer', 'maximum' => 100]]];
    $ok = ['200' => ['description' => 'JSON']];
    json_salida([
        'openapi' => '3.0.3',
        'info' => ['title' => 'API de DipuData', 'version' => '1.0', 'description' => 'Datos verificados del Congreso de la Nación Argentina. Solo lectura. Licencia CC BY 4.0: citar como "DipuData (dipudata.com)". Cada dato lleva su fuente oficial.',
            'license' => ['name' => 'CC BY 4.0', 'url' => 'https://creativecommons.org/licenses/by/4.0/']],
        'servers' => [['url' => SITE_URL . '/api']],
        'paths' => [
            '/' => ['get' => ['summary' => 'Índice de la API', 'responses' => $ok]],
            '/legisladores' => ['get' => ['summary' => 'Legisladores en ejercicio', 'parameters' => array_merge([
                ['name' => 'camara', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['Diputados', 'Senadores']]],
                ['name' => 'provincia', 'in' => 'query', 'schema' => ['type' => 'string']],
                ['name' => 'bloque', 'in' => 'query', 'schema' => ['type' => 'string']],
                ['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string']]], $pag), 'responses' => $ok]],
            '/legisladores/{camara}/{slug}' => ['get' => ['summary' => 'Un legislador', 'parameters' => [
                ['name' => 'camara', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['diputado', 'senador']]],
                ['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => $ok]],
            '/bloques' => ['get' => ['summary' => 'Bloques y bancas', 'responses' => $ok]],
            '/provincias' => ['get' => ['summary' => 'Provincias', 'responses' => $ok]],
            '/votaciones' => ['get' => ['summary' => 'Votaciones nominales (cabeceras)', 'parameters' => array_merge([
                ['name' => 'camara', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['Diputados', 'Senadores']]],
                ['name' => 'anio', 'in' => 'query', 'schema' => ['type' => 'integer']]], $pag), 'responses' => $ok]],
            '/votaciones/{id}' => ['get' => ['summary' => 'Una votación con el voto de cada legislador', 'parameters' => [
                ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => $ok]],
        ],
    ]);
}

if ($ruta === '/') {
    json_salida([
        'nombre' => 'API de DipuData', 'licencia' => 'CC BY 4.0', 'documentacion' => SITE_URL . '/datos', 'openapi' => SITE_URL . '/api/openapi.json',
        'endpoints' => ['/api/legisladores', '/api/legisladores/{diputado|senador}/{slug}', '/api/bloques', '/api/provincias', '/api/votaciones', '/api/votaciones/{id}', '/api/openapi.json'],
    ]);
}

json_salida(['error' => 'No encontrado'], 404);
