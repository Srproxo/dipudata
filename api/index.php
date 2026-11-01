<?php
declare(strict_types=1);

/**
 * API pública de solo lectura de DipuData.
 *   GET /api/legisladores?camara=&provincia=&bloque=&q=&pagina=&limite=
 *   GET /api/legisladores/{diputado|senador}/{slug}
 *   GET /api/bloques        GET /api/provincias
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

json_salida(['error' => 'No encontrado'], 404);
