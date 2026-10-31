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

$legisladores = [];
if ($pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT id, updated_at, estado FROM legisladores WHERE estado = 'Activo' ORDER BY id ASC");
        $legisladores = $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable) {
        $legisladores = [];
    }
}

if (empty($legisladores)) {
    $store = Config::getMemoryStore();
    foreach ($store['legisladores'] as $leg) {
        if (($leg['estado'] ?? 'Activo') === 'Activo') {
            $legisladores[] = [
                'id'         => $leg['id'],
                'updated_at' => $leg['updated_at'] ?? $today,
            ];
        }
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/', ENT_XML1, 'UTF-8') ?></loc>
    <lastmod><?= $today ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/match', ENT_XML1, 'UTF-8') ?></loc>
    <lastmod><?= $today ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>
<?php foreach ($legisladores as $leg):
    $id = (int)$leg['id'];
    $rawDate = (string)($leg['updated_at'] ?? $today);
    $lastmod = substr($rawDate, 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastmod)) {
        $lastmod = $today;
    }
?>
  <url>
    <loc><?= htmlspecialchars("{$baseUrl}/perfil/{$id}", ENT_XML1, 'UTF-8') ?></loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>
<?php endforeach; ?>
</urlset>
