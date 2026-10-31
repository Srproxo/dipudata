<?php
declare(strict_types=1);

/**
 * Front Controller MVC — DipuData / Match Electoral 360
 * Rutas (vía .htaccess):
 *   /diputado/{slug}             perfil.php
 *   /senador/{slug}              perfil.php
 *   /match-360                   match.php
 *   POST /api/match360           MatchEngineController
 *   /blog                        Hub
 *   /blog/{categoria}            Sub-hub
 *   /blog/{categoria}/{articulo} Spoke con auto-linking a perfiles
 */

require_once __DIR__ . '/app/bootstrap.php';

use App\Controllers\BlogController;
use App\Controllers\MatchEngineController;

$path = '/' . trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$slug = '[a-z0-9]+(?:-[a-z0-9]+)*';

switch (true) {
    case (bool)preg_match('#^/perfil/([^/]+)$#', $path, $m):
        $ident = trim($m[1]);
        $slug = null;
        $prefix = '/diputado/';

        $model = new \App\Models\LegisladorModel();
        $leg = is_numeric($ident) ? $model->findById((int)$ident) : $model->findBySlug($ident);
        if ($leg) {
            $prefix = str_starts_with(strtolower((string)$leg['camara']), 'senad') ? '/senador/' : '/diputado/';
            $slug = $leg['slug'] ?? null;
        }
        if (!$slug) {
            $db = \App\Config\Database::getConnection();
            if ($db) {
                try {
                    $stmt = $db->prepare('SELECT camara, wiki_slug, slug FROM legisladores WHERE id = ? OR wiki_slug = ? LIMIT 1');
                    $stmt->execute([$ident, $ident]);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row) {
                        $prefix = str_starts_with(strtolower((string)$row['camara']), 'senad') ? '/senador/' : '/diputado/';
                        $slug = !empty($row['slug']) ? $row['slug'] : strtolower(str_replace('_', '-', (string)$row['wiki_slug']));
                    }
                } catch (\Throwable) {}
            }
        }
        if (!$slug) {
            require_once __DIR__ . '/api/config.php';
            $all = \LegisData\Config::getMemoryStore()['legisladores'] ?? [];
            foreach ($all as $l) {
                if ((is_numeric($ident) && (int)$l['id'] === (int)$ident) || strcasecmp((string)($l['slug'] ?? ''), $ident) === 0 || strcasecmp((string)($l['wiki_slug'] ?? ''), $ident) === 0) {
                    $prefix = str_starts_with(strtolower((string)$l['camara']), 'senad') ? '/senador/' : '/diputado/';
                    $slug = !empty($l['slug']) ? $l['slug'] : strtolower(str_replace('_', '-', (string)$l['wiki_slug']));
                    break;
                }
            }
        }
        if ($slug) {
            header("Location: {$prefix}{$slug}", true, 301);
            exit;
        }
        header('Location: /', true, 302);
        exit;

    case (bool)preg_match("#^/(diputado|senador)/({$slug})$#", $path, $m):
        $camara = $m[1] === 'senador' ? 'Senadores' : 'Diputados';
        $slugLegislador = $m[2];
        require __DIR__ . '/perfil.php';
        break;

    case $path === '/match':
        header('Location: /match-360', true, 301);
        exit;

    case $path === '/match-360':
        require __DIR__ . '/match.php';
        break;

    case $path === '/api/match360':
        (new MatchEngineController())->handle();
        break;

    case $path === '/api/legisladores-master':
        \LegisData\Security::sendSecurityHeaders('application/json; charset=utf-8');
        $model = new \App\Models\LegisladorModel();
        $items = $model->all();
        $clean = array_map(static fn(array $l): array => [
            'id'             => (int)$l['id'],
            'id_recinto'     => (string)($l['id_recinto'] ?? ''),
            'nombre'         => (string)$l['nombre'],
            'slug'           => (string)$l['slug'],
            'camara'         => (string)$l['camara'],
            'provincia'      => (string)$l['provincia'],
            'bloque'         => (string)$l['bloque'],
            'cuit'           => (string)$l['cuit'],
            'estado'         => (string)($l['estado'] ?? 'Activo'),
            'indice_lealtad' => (float)($l['indice_lealtad'] ?? 0.0),
            'asistencia'     => (float)($l['asistencia'] ?? 0.0),
        ], $items);
        echo json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;

    case $path === '/api/n8n/update-despachos':
        (new \App\Controllers\ApiN8nController())->updateDespachos();
        break;

    case (bool)preg_match('#^/api/auditoria(?:/(.*))?$#', $path, $m):
        $sub = $m[1] ?? '';
        (new \App\Controllers\AuditorEngineController())->handle($sub);
        break;

    case $path === '/blog':
        try {
            $data = (new BlogController())->hub();
        } catch (\Throwable) {
            $data = (new BlogController(new \App\Models\BlogModel(null), new \App\Models\LegisladorModel(null)))->hub();
        }
        render('blog/hub', [
            'title'       => 'Blog DipuData | Periodismo de datos legislativos y educación financiera',
            'description' => 'Análisis de votaciones del Congreso, auditoría de declaraciones juradas ajustadas por inflación y guías de educación financiera cívica.',
            'canonical'   => SITE_URL . '/blog',
            'jsonld'      => ['@context' => 'https://schema.org', '@type' => 'Blog', 'name' => 'Blog DipuData', 'url' => SITE_URL . '/blog'],
        ], $data);
        break;

    case (bool)preg_match("#^/blog/({$slug})$#", $path, $m):
        try {
            $data = (new BlogController())->categoria($m[1]);
        } catch (\Throwable) {
            $data = (new BlogController(new \App\Models\BlogModel(null), new \App\Models\LegisladorModel(null)))->categoria($m[1]);
        }
        if ($data === null) {
            render_404();
            break;
        }
        $nombre = BlogController::nombreCategoria($m[1]);
        render('blog/categoria', [
            'title'       => "{$nombre} | Blog DipuData",
            'description' => "Artículos de {$nombre}: datos abiertos del Congreso argentino, votaciones nominales y patrimonio de legisladores.",
            'canonical'   => SITE_URL . "/blog/{$m[1]}",
            'jsonld'      => self_breadcrumb([['Blog', '/blog'], [$nombre, "/blog/{$m[1]}"]]),
        ], $data + ['nombreCategoria' => $nombre]);
        break;

    case (bool)preg_match("#^/blog/({$slug})/({$slug})$#", $path, $m):
        try {
            $data = (new BlogController())->articulo($m[1], $m[2]);
        } catch (\Throwable) {
            $data = (new BlogController(new \App\Models\BlogModel(null), new \App\Models\LegisladorModel(null)))->articulo($m[1], $m[2]);
        }
        if ($data === null) {
            render_404();
            break;
        }
        $art = $data['articulo'];
        $url = SITE_URL . "/blog/{$m[1]}/{$m[2]}";
        $nombreCat = BlogController::nombreCategoria($m[1]);
        render('blog/article', [
            'title'       => $art['titulo'] . ' | DipuData',
            'description' => $art['meta_descripcion'],
            'canonical'   => $url,
            'jsonld'      => [
                '@context' => 'https://schema.org',
                '@graph'   => [
                    [
                        '@type'         => 'Article',
                        'headline'      => $art['titulo'],
                        'description'   => $art['meta_descripcion'],
                        'datePublished' => date('c', strtotime($art['fecha_publicacion'] ?? 'now')),
                        'mainEntityOfPage' => $url,
                        'publisher'     => ['@type' => 'Organization', 'name' => 'DipuData', 'url' => SITE_URL],
                        'mentions'      => array_map(fn($l) => [
                            '@type' => 'Person',
                            'name'  => $l['nombre'],
                            'url'   => SITE_URL . \App\Models\LegisladorModel::urlPerfil($l),
                        ], $data['mencionados']),
                    ],
                    self_breadcrumb([['Blog', '/blog'], [$nombreCat, "/blog/{$m[1]}"], [$art['titulo'], "/blog/{$m[1]}/{$m[2]}"]]),
                ],
            ],
        ], $data + ['categoriaSlug' => $m[1], 'nombreCategoria' => $nombreCat]);
        break;

    default:
        render_404();
}

