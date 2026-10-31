<?php
declare(strict_types=1);

/**
 * LEGISDATA (dipudata.com) — Cron Job 3: Sincronización de DDJJ Patrimoniales (Oficina Anticorrupción)
 * Etapa 2: Scripts de Sincronización Desatendida (Protegido por CLI / HMAC)
 */

namespace LegisData\Cron;

use LegisData\Config;
use LegisData\Security;
use Throwable;

require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/api/Security.php';

$pdo = Config::getConnection();
Security::verifyCronAccess($pdo, 'cron/sync_anticorrupcion.php');

$startTime = microtime(true);
$store = Config::getMemoryStore();

$indicadores = $store['indicadores'];
$patrimonioSeries = $store['patrimonio'];

$ddjjSincronizadas = 0;
$indicadoresSincronizados = 0;
$errores = 0;

if ($pdo !== null) {
    try {
        $pdo->beginTransaction();

        // 1. Asegurar que todos los períodos fiscales tengan su indicador IPC y Dólar MEP de cierre
        $stmtInd = $pdo->prepare("
            INSERT INTO indicadores_economicos (anio, ipc_interanual, dolar_mep_cierre)
            VALUES (:anio, :ipc_interanual, :dolar_mep_cierre)
            ON DUPLICATE KEY UPDATE
                ipc_interanual   = VALUES(ipc_interanual),
                dolar_mep_cierre = VALUES(dolar_mep_cierre)
        ");

        foreach ($indicadores as $ind) {
            $stmtInd->execute([
                ':anio'             => (int)$ind['anio'],
                ':ipc_interanual'   => (float)$ind['ipc_interanual'],
                ':dolar_mep_cierre' => (float)$ind['dolar_mep_cierre'],
            ]);
            $indicadoresSincronizados++;
        }

        // 2. Cruzar y actualizar las Declaraciones Juradas Patrimoniales por legislador y año fiscal
        $stmtDdjj = $pdo->prepare("
            INSERT INTO patrimonio_ddjj (legislador_id, anio_fiscal, monto_pesos_nominal, fuente)
            VALUES (:legislador_id, :anio_fiscal, :monto_pesos_nominal, :fuente)
            ON DUPLICATE KEY UPDATE
                monto_pesos_nominal = VALUES(monto_pesos_nominal),
                fuente              = VALUES(fuente)
        ");

        foreach ($patrimonioSeries as $legId => $aniosMap) {
            foreach ($aniosMap as $anioFiscal => $montoNominal) {
                if ($montoNominal < 0) {
                    continue;
                }
                $stmtDdjj->execute([
                    ':legislador_id'       => (int)$legId,
                    ':anio_fiscal'         => (int)$anioFiscal,
                    ':monto_pesos_nominal' => round((float)$montoNominal, 2),
                    ':fuente'              => 'Oficina Anticorrupción',
                ]);
                $ddjjSincronizadas++;
            }
        }

        $pdo->commit();
    } catch (Throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errores++;
    }
} else {
    $indicadoresSincronizados = count($indicadores);
    foreach ($patrimonioSeries as $aniosMap) {
        $ddjjSincronizadas += count($aniosMap);
    }
}

$elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

Security::logAudit(
    $pdo,
    'CRON_SYNC_ANTICORRUPCION',
    'CLI://cron/sync_anticorrupcion.php',
    $errores > 0 ? 'WARN' : 'INFO',
    [
        'indicadores_sincronizados' => $indicadoresSincronizados,
        'ddjj_sincronizadas'        => $ddjjSincronizadas,
        'errores'                   => $errores,
        'elapsed_ms'                => $elapsedMs,
    ]
);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status'                    => 'ok',
    'job'                       => 'sync_anticorrupcion',
    'indicadores_sincronizados' => $indicadoresSincronizados,
    'ddjj_sincronizadas'        => $ddjjSincronizadas,
    'errores'                   => $errores,
    'elapsed_ms'                => $elapsedMs,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
