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
    case (bool)preg_match("#^/(diputado|senador)/({$slug})$#", $path, $m):
        $camara = $m[1] === 'senador' ? 'Senadores' : 'Diputados';
        $slugLegislador = $m[2];
        require __DIR__ . '/perfil.php';
        break;

    case $path === '/match-360':
        require __DIR__ . '/match.php';
        break;

    case $path === '/api/match360':
        (new MatchEngineController())->handle();
        break;

    case $path === '/api/n8n/update-despachos':
        (new \App\Controllers\ApiN8nController())->updateDespachos();
        break;

    case $path === '/blog':
        $data = (new BlogController())->hub();
        render('blog/hub', [
            'title'       => 'Blog DipuData | Periodismo de datos legislativos y educación financiera',
            'description' => 'Análisis de votaciones del Congreso, auditoría de declaraciones juradas ajustadas por inflación y guías de educación financiera cívica.',
            'canonical'   => SITE_URL . '/blog',
            'jsonld'      => ['@context' => 'https://schema.org', '@type' => 'Blog', 'name' => 'Blog DipuData', 'url' => SITE_URL . '/blog'],
        ], $data);
        break;

    case (bool)preg_match("#^/blog/({$slug})$#", $path, $m):
        $data = (new BlogController())->categoria($m[1]);
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
        $data = (new BlogController())->articulo($m[1], $m[2]);
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

