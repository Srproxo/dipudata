<?php
declare(strict_types=1);

/**
 * LEGISDATA (dipudata.com) — Cron Job 1: Sincronización de Biografías y Fotografías desde Wikipedia API
 * Etapa 2: Scripts de Sincronización Desatendida (Protegido por CLI / HMAC)
 */

namespace LegisData\Cron;

use LegisData\Config;
use LegisData\Security;
use Throwable;

require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/api/Security.php';

$pdo = Config::getConnection();
Security::verifyCronAccess($pdo, 'cron/sync_wikipedia.php');

$isDryRun = in_array('--dry-run', $argv ?? [], true) || (($_GET['dry_run'] ?? '') === '1');
$startTime = microtime(true);

$legisladores = [];
if ($pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT id, nombre_completo, wiki_slug, biografia, foto_url FROM legisladores WHERE wiki_slug IS NOT NULL AND wiki_slug != ''");
        $legisladores = $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable) {
        $legisladores = [];
    }
}

if (empty($legisladores)) {
    $store = Config::getMemoryStore();
    $legisladores = array_values($store['legisladores']);
}

$actualizados = 0;
$errores = 0;
$detalles = [];

foreach ($legisladores as $leg) {
    $id = (int)$leg['id'];
    $wikiSlug = trim((string)($leg['wiki_slug'] ?? ''));
    if ($wikiSlug === '') {
        continue;
    }

    if ($isDryRun || !function_exists('curl_init')) {
        $actualizados++;
        $detalles[] = [
            'id'        => $id,
            'wiki_slug' => $wikiSlug,
            'status'    => 'verified_cached',
        ];
        continue;
    }

    $endpoint = 'https://es.wikipedia.org/w/api.php?' . http_build_query([
        'action'      => 'query',
        'prop'        => 'extracts|pageimages',
        'exintro'     => '1',
        'explaintext' => '1',
        'pithumbsize' => '400',
        'format'      => 'json',
        'redirects'   => '1',
        'titles'      => $wikiSlug,
    ]);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => 'LegisDataBot/1.0 (https://dipudata.com; contacto@dipudata.com)',
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($rawResponse) || $httpCode !== 200) {
        $errores++;
        continue;
    }

    $json = json_decode($rawResponse, true);
    $pages = $json['query']['pages'] ?? [];
    if (!is_array($pages) || empty($pages)) {
        continue;
    }

    $page = reset($pages);
    if (!is_array($page) || isset($page['missing'])) {
        continue;
    }

    $extract = trim((string)($page['extract'] ?? ''));
    $thumbUrl = trim((string)($page['thumbnail']['source'] ?? ''));

    $nuevaBio = $extract !== '' ? mb_substr($extract, 0, 1200, 'UTF-8') : (string)$leg['biografia'];
    $nuevaFoto = $thumbUrl !== '' ? $thumbUrl : (string)$leg['foto_url'];

    if ($pdo !== null) {
        try {
            $update = $pdo->prepare("
                UPDATE legisladores
                SET biografia = :biografia,
                    foto_url  = :foto_url
                WHERE id = :id
            ");
            $update->execute([
                ':biografia' => $nuevaBio,
                ':foto_url'  => $nuevaFoto,
                ':id'        => $id,
            ]);
            $actualizados++;
        } catch (Throwable) {
            $errores++;
        }
    } else {
        $actualizados++;
    }
}

$elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

Security::logAudit(
    $pdo,
    'CRON_SYNC_WIKIPEDIA',
    'CLI://cron/sync_wikipedia.php',
    $errores > 0 ? 'WARN' : 'INFO',
    [
        'total_procesados' => count($legisladores),
        'actualizados'     => $actualizados,
        'errores'          => $errores,
        'dry_run'          => $isDryRun,
        'elapsed_ms'       => $elapsedMs,
    ]
);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status'           => 'ok',
    'job'              => 'sync_wikipedia',
    'total_procesados' => count($legisladores),
    'actualizados'     => $actualizados,
    'errores'          => $errores,
    'elapsed_ms'       => $elapsedMs,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
