<?php
declare(strict_types=1);

/**
 * LEGISDATA (dipudata.com) — Cron Job 2: Sincronización de Votaciones Nominales (Datos Abiertos HCDN / Senado)
 * Etapa 2: Scripts de Sincronización Desatendida (Protegido por CLI / HMAC)
 */

namespace LegisData\Cron;

use LegisData\Algorithms;
use LegisData\Config;
use LegisData\Security;
use Throwable;

require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/api/Security.php';
require_once dirname(__DIR__) . '/api/Algorithms.php';

$pdo = Config::getConnection();
Security::verifyCronAccess($pdo, 'cron/sync_datacp.php');

$startTime = microtime(true);

/**
 * Clasifica automáticamente un expediente legislativo en uno de los 5 ejes temáticos
 * a partir de palabras clave de su título y sumario oficial.
 */
function clasificarEjeTematico(string $titulo, string $resumen = ''): string
{
    $texto = mb_strtolower($titulo . ' ' . $resumen, 'UTF-8');

    if (preg_match('/(universidad|educaci|ciencia|tecnolog|docente|escuela|beca)/u', $texto)) {
        return 'Educacion';
    }
    if (preg_match('/(salud|hospital|médic|medicamento|sanitari|vacuna|paliativ|embarazo|garrahan)/u', $texto)) {
        return 'Salud';
    }
    if (preg_match('/(trabajo|teletrabajo|laboral|sindical|indemnizaci|despido|convenio|horas extras)/u', $texto)) {
        return 'Laboral';
    }
    if (preg_match('/(desregulaci|libertad|inversi|rigi|privatizaci|apertura|blanqueo|mercado)/u', $texto)) {
        return 'MercadoLibre';
    }
    return 'Proteccionismo';
}

$store = Config::getMemoryStore();
$leyesFeed = $store['leyes'];
$votosFeed = $store['votos'];

// Si existe un archivo JSON externo en database/feeds/datacp_votaciones.json, lo incorpora
$customFeedPath = dirname(__DIR__) . '/database/feeds/datacp_votaciones.json';
if (is_readable($customFeedPath)) {
    $rawFeed = file_get_contents($customFeedPath);
    $decoded = is_string($rawFeed) ? json_decode($rawFeed, true) : null;
    if (is_array($decoded) && isset($decoded['leyes']) && is_array($decoded['leyes'])) {
        foreach ($decoded['leyes'] as $leyItem) {
            $idLey = Security::sanitizeString((string)($leyItem['id'] ?? ''), 50);
            if ($idLey === '') {
                continue;
            }
            $titulo = Security::sanitizeString((string)($leyItem['titulo'] ?? ''), 255);
            $resumen = Security::sanitizeString((string)($leyItem['resumen'] ?? ''), 1000);
            $eje = Security::validateEnum(
                (string)($leyItem['eje_tematico'] ?? ''),
                Algorithms::EJES_TODOS,
                clasificarEjeTematico($titulo, $resumen)
            );
            $leyesFeed[$idLey] = [
                'id'              => $idLey,
                'titulo'          => $titulo,
                'resumen'         => $resumen,
                'fecha_votacion'  => (string)($leyItem['fecha_votacion'] ?? date('Y-m-d')),
                'eje_tematico'    => $eje,
                'peso_ideologico' => max(0.10, min(1.00, (float)($leyItem['peso_ideologico'] ?? 1.00))),
            ];
        }
    }
}

$leyesSincronizadas = 0;
$votosSincronizados = 0;
$errores = 0;

if ($pdo !== null) {
    try {
        $pdo->beginTransaction();

        $stmtLey = $pdo->prepare("
            INSERT INTO leyes (id, titulo, resumen, fecha_votacion, eje_tematico, peso_ideologico)
            VALUES (:id, :titulo, :resumen, :fecha_votacion, :eje_tematico, :peso_ideologico)
            ON DUPLICATE KEY UPDATE
                titulo          = VALUES(titulo),
                resumen         = VALUES(resumen),
                fecha_votacion  = VALUES(fecha_votacion),
                eje_tematico    = VALUES(eje_tematico),
                peso_ideologico = VALUES(peso_ideologico)
        ");

        foreach ($leyesFeed as $ley) {
            $stmtLey->execute([
                ':id'              => $ley['id'],
                ':titulo'          => $ley['titulo'],
                ':resumen'         => $ley['resumen'],
                ':fecha_votacion'  => $ley['fecha_votacion'],
                ':eje_tematico'    => $ley['eje_tematico'],
                ':peso_ideologico' => $ley['peso_ideologico'],
            ]);
            $leyesSincronizadas++;
        }

        $stmtVoto = $pdo->prepare("
            INSERT INTO votos (legislador_id, ley_id, sentido_voto)
            VALUES (:legislador_id, :ley_id, :sentido_voto)
            ON DUPLICATE KEY UPDATE
                sentido_voto = VALUES(sentido_voto)
        ");

        $allowedSentidos = ['Afirmativo', 'Negativo', 'Abstencion', 'Ausente'];
        foreach ($votosFeed as $legId => $mapaLeyes) {
            foreach ($mapaLeyes as $leyId => $sentido) {
                if (!in_array($sentido, $allowedSentidos, true)) {
                    continue;
                }
                $stmtVoto->execute([
                    ':legislador_id' => (int)$legId,
                    ':ley_id'        => (string)$leyId,
                    ':sentido_voto'  => $sentido,
                ]);
                $votosSincronizados++;
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errores++;
    }
} else {
    $leyesSincronizadas = count($leyesFeed);
    foreach ($votosFeed as $mapaLeyes) {
        $votosSincronizados += count($mapaLeyes);
    }
}

$elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

Security::logAudit(
    $pdo,
    'CRON_SYNC_DATACP',
    'CLI://cron/sync_datacp.php',
    $errores > 0 ? 'WARN' : 'INFO',
    [
        'leyes_sincronizadas' => $leyesSincronizadas,
        'votos_sincronizados' => $votosSincronizados,
        'errores'             => $errores,
        'elapsed_ms'          => $elapsedMs,
    ]
);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status'              => 'ok',
    'job'                 => 'sync_datacp',
    'leyes_sincronizadas' => $leyesSincronizadas,
    'votos_sincronizados' => $votosSincronizados,
    'errores'             => $errores,
    'elapsed_ms'          => $elapsedMs,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
