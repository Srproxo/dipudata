<?php
declare(strict_types=1);

/**
 * LEGISDATA (dipudata.com) — Generador Dinámico de Sitemap XML (sitemap.xml)
 * Etapa 4: Dominancia SEO Técnica
 */

namespace LegisData;

use Throwable;

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/Security.php';

Security::sendSecurityHeaders('application/xml; charset=utf-8');
if (!headers_sent()) {
    header('Cache-Control: public, max-age=3600');
}

$pdo = Config::getConnection();
$baseUrl = 'https://dipudata.com';
$today = gmdate('Y-m-d');

require_once __DIR__ . '/app/bootstrap.php';

$baseUrl = 'https://dipudata.com';
$today = gmdate('Y-m-d');

$mvcUrls = [
    ['/', 'daily', '1.0'],
    ['/match-360', 'weekly', '0.9'],
    ['/leyes', 'daily', '0.9'],
    ['/blog', 'daily', '0.8'],
];

try {
    foreach ((new \App\Models\LegisladorModel())->all() as $l) {
        $mvcUrls[] = [\App\Models\LegisladorModel::urlPerfil($l), 'weekly', '0.9'];
    }
    foreach ((new \App\Models\LegisladorModel())->ejes() as $e) {
        $slug = $e['slug_seo'] ?? \App\Config\Database::slugify($e['nombre_eje'] ?? $e['nombre']);
        $mvcUrls[] = ["/eje/{$slug}", 'weekly', '0.85'];
    }
    $leyModel = new \App\Models\LeyModel();
    foreach ($leyModel->catalogo() as $slugLey => $lData) {
        $mvcUrls[] = ["/ley/{$slugLey}", 'weekly', '0.85'];
    }
    $blogModel = new \App\Models\BlogModel();
    foreach (array_keys($blogModel->categorias()) as $cat) {
        $mvcUrls[] = ["/blog/{$cat}", 'weekly', '0.7'];
    }
    $incluirTodos = isset($_GET['todos']) && $_GET['todos'] === '1';
    $articulosSitemap = $incluirTodos ? $blogModel->all() : $blogModel->publicados();
    foreach ($articulosSitemap as $a) {
        $mvcUrls[] = ["/blog/{$a['categoria_slug']}/{$a['articulo_slug']}", 'monthly', '0.8'];
    }
} catch (Throwable) {
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($mvcUrls as [$ruta, $freq, $prio]): ?>
  <url>
    <loc><?= htmlspecialchars($baseUrl . $ruta, ENT_XML1, 'UTF-8') ?></loc>
    <lastmod><?= $today ?></lastmod>
    <changefreq><?= $freq ?></changefreq>
    <priority><?= $prio ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
