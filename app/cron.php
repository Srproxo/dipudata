<?php
declare(strict_types=1);

/**
 * Entrada CLI para Cron:
 *   php app/cron.php all
 *   php app/cron.php votaciones storage/csv/votaciones_datacp.csv
 *   php app/cron.php ddjj storage/csv/ddjj_anticorrupcion.csv
 *   php app/cron.php wikipedia
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/bootstrap.php';

use App\Controllers\SyncDataController;

$tarea = $argv[1] ?? 'all';
$ruta = $argv[2] ?? null;

try {
    $log = (new SyncDataController())->run($tarea, $ruta);
    fwrite(STDOUT, json_encode(['status' => 'ok', 'tarea' => $tarea, 'log' => $log], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[ERROR] {$tarea}: {$e->getMessage()}" . PHP_EOL);
    exit(1);
}
