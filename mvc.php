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

    case (bool)preg_match("#^/eje/({$slug})$#", $path, $m):
        $slugEje = $m[1];
        try {
            $data = (new \App\Controllers\LegisladorController())->eje($slugEje);
        } catch (\Throwable) {
            $data = (new \App\Controllers\LegisladorController(new \App\Models\LegisladorModel(null)))->eje($slugEje);
        }
        if ($data === null) {
            render_404();
            break;
        }
        $eje = $data['eje'];
        $canonicalEje = $eje['slug_seo'] ?? \App\Config\Database::slugify($eje['nombre_eje'] ?? $eje['nombre']);
        if ($slugEje !== $canonicalEje) {
            header("Location: /eje/{$canonicalEje}", true, 301);
            exit;
        }
        $nombreEje = $eje['nombre_eje'] ?? $eje['nombre'];
        $urlEje = SITE_URL . "/eje/{$canonicalEje}";
        render('eje', [
            'title'       => "{$nombreEje} en el Congreso | Votaciones, Leyes y Posturas | DipuData",
            'description' => "Votaciones nominales de diputados y senadores en {$nombreEje}. Proyectos de ley, disciplina de bloque y ranking de afinidad legislativa en Argentina.",
            'canonical'   => $urlEje,
            'jsonld'      => [
                '@context' => 'https://schema.org',
                '@graph'   => [
                    [
                        '@type'       => 'CollectionPage',
                        'name'        => "Eje Temático: {$nombreEje}",
                        'url'         => $urlEje,
                        'description' => $eje['descripcion'] ?? '',
                        'mainEntity'  => [
                            '@type'           => 'ItemList',
                            'itemListElement' => array_map(fn($l, $idx) => [
                                '@type'    => 'ListItem',
                                'position' => $idx + 1,
                                'url'      => SITE_URL . \App\Models\LegisladorModel::urlPerfil($l),
                                'name'     => $l['nombre'],
                            ], $data['ranking'], array_keys($data['ranking'])),
                        ],
                    ],
                    self_breadcrumb([['Ejes Temáticos', '/eje/salud-publica'], [$nombreEje, "/eje/{$canonicalEje}"]]),
                ],
            ],
        ], $data);
        break;

    case (bool)preg_match("#^/ley/({$slug})$#", $path, $m):
        $slugLey = $m[1];
        try {
            $data = (new \App\Controllers\LegisladorController())->ley($slugLey);
        } catch (\Throwable) {
            $data = (new \App\Controllers\LegisladorController(new \App\Models\LegisladorModel(null)))->ley($slugLey);
        }
        if ($data === null) {
            render_404();
            break;
        }
        $ley = $data['ley'];
        $urlLey = SITE_URL . "/ley/{$ley['slug']}";
        render('ley', [
            'title'       => "{$ley['titulo_corto']} ({$ley['expediente']}) | Resumen, Votación Nominal y Debate | DipuData",
            'description' => "Auditoría cívica de {$ley['titulo_corto']}. De qué trata, quién la presentó, qué implica y quién votó a favor o en contra en el Congreso de la Nación.",
            'canonical'   => $urlLey,
            'jsonld'      => [
                '@context' => 'https://schema.org',
                '@graph'   => [
                    [
                        '@type'                 => 'Legislation',
                        'name'                  => $ley['titulo'],
                        'alternateName'         => $ley['titulo_corto'],
                        'legislationIdentifier' => $ley['expediente'],
                        'url'                   => $urlLey,
                        'legislationDate'       => $ley['fecha_votacion'],
                        'abstract'              => $ley['resumen'],
                        'creator'               => [
                            '@type' => 'Organization',
                            'name'  => $ley['quien_la_presento'],
                        ],
                    ],
                    self_breadcrumb([['Leyes Tratadas', '/leyes'], [$ley['titulo_corto'], "/ley/{$ley['slug']}"]]),
                ],
            ],
        ], $data);
        break;

    case $path === '/leyes':
        try {
            $data = (new \App\Controllers\LegisladorController())->leyes();
        } catch (\Throwable) {
            $data = (new \App\Controllers\LegisladorController(new \App\Models\LegisladorModel(null)))->leyes();
        }
        render('leyes_hub', [
            'title'       => 'Leyes Clave del Congreso de la Nación | Votaciones Nominales y Resúmenes | DipuData',
            'description' => 'Directorio y archivo de leyes votadas en el Congreso Nacional. De qué tratan, quién las presentó, qué implican y cómo votó cada diputado y senador.',
            'canonical'   => SITE_URL . '/leyes',
            'jsonld'      => [
                '@context' => 'https://schema.org',
                '@graph'   => [
                    [
                        '@type'       => 'CollectionPage',
                        'name'        => 'Leyes Clave del Congreso de la Nación',
                        'url'         => SITE_URL . '/leyes',
                        'description' => 'Archivo cívico de leyes votadas con desglose nominal de votos por bloque y provincia.',
                    ],
                    self_breadcrumb([['Leyes Tratadas', '/leyes']]),
                ],
            ],
        ], $data);
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

