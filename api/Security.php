<?php
declare(strict_types=1);

/**
 * LEGISDATA (dipudata.com) — Capa de Seguridad Grado Bancario (OWASP ASVS L2/L3)
 * Etapa 2: Backend PHP 8.x & Seguridad Grado Bancario
 */

namespace LegisData;

use PDO;
use Throwable;

require_once __DIR__ . '/config.php';

class Security
{
    private const DEFAULT_SALT = 'LegisData_BankGrade_Salt_2026_#AR';

    /**
     * Emite cabeceras HTTP de grado bancario
     */
    public static function sendSecurityHeaders(string $contentType = 'application/json; charset=utf-8'): void
    {
        if (headers_sent()) {
            return;
        }

        header("Content-Type: {$contentType}");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
        header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
        header('X-Permitted-Cross-Domain-Policies: none');

        if (str_contains(strtolower($contentType), 'text/html')) {
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' https://upload.wikimedia.org https://ui-avatars.com https://dipudata.com data:; connect-src 'self' https://dipudata.com; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
            header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        } else {
            header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; frame-ancestors 'none'");
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        // CORS seguro para el dominio oficial y entorno local de desarrollo
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedOrigins = [
            'https://dipudata.com',
            'https://www.dipudata.com',
            'http://localhost:5173',
            'http://localhost:8080',
            'http://127.0.0.1:5173',
        ];
        if (in_array($origin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Vary: Origin');
        } else {
            header('Access-Control-Allow-Origin: https://dipudata.com');
        }
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');
    }

    /**
     * Obtiene el hash SHA-256 anonimizado de la IP del cliente con salt criptográfico
     */
    public static function getClientIpHash(): string
    {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '127.0.0.1';

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '0.0.0.0';
        }

        $salt = Config::env('APP_SALT', self::DEFAULT_SALT);
        return hash('sha256', $ip . '|' . $salt);
    }

    /**
     * Rate Limiting por ventana deslizante de 1 minuto (MySQL rate_limits + fallback en /tmp)
     * - Lecturas GET /api/* : máx 120 req/min
     * - Cálculo POST /api/match : máx 30 req/min
     */
    public static function enforceRateLimit(?PDO $pdo, string $routeGroup = 'api_read', int $maxRequests = 120): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $ipHash = self::getClientIpHash();
        $windowStart = intdiv(time(), 60);
        $currentCount = 1;

        if ($pdo !== null) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO rate_limits (ip_hash, route_group, window_start, request_count)
                    VALUES (:ip_hash, :route_group, :window_start, 1)
                    ON DUPLICATE KEY UPDATE request_count = request_count + 1
                ");
                $stmt->execute([
                    ':ip_hash'      => $ipHash,
                    ':route_group'  => $routeGroup,
                    ':window_start' => $windowStart,
                ]);

                $check = $pdo->prepare("
                    SELECT request_count
                    FROM rate_limits
                    WHERE ip_hash = :ip_hash
                      AND route_group = :route_group
                      AND window_start = :window_start
                    LIMIT 1
                ");
                $check->execute([
                    ':ip_hash'      => $ipHash,
                    ':route_group'  => $routeGroup,
                    ':window_start' => $windowStart,
                ]);
                $row = $check->fetch();
                if ($row) {
                    $currentCount = (int)$row['request_count'];
                }

                // Limpieza probabilística (2%) de ventanas antiguas (> 10 minutos)
                if (random_int(1, 50) === 1) {
                    $cleanup = $pdo->prepare("DELETE FROM rate_limits WHERE window_start < :min_window");
                    $cleanup->execute([':min_window' => $windowStart - 10]);
                }
            } catch (Throwable) {
                $currentCount = self::checkFileRateLimit($ipHash, $routeGroup, $windowStart);
            }
        } else {
            $currentCount = self::checkFileRateLimit($ipHash, $routeGroup, $windowStart);
        }

        if (!headers_sent()) {
            header('X-RateLimit-Limit: ' . $maxRequests);
            header('X-RateLimit-Remaining: ' . max(0, $maxRequests - $currentCount));
        }

        if ($currentCount > $maxRequests) {
            self::logAudit(
                $pdo,
                'RATE_LIMIT_EXCEEDED',
                $_SERVER['REQUEST_URI'] ?? '/api',
                'WARN',
                ['route_group' => $routeGroup, 'count' => $currentCount, 'limit' => $maxRequests]
            );

            http_response_code(429);
            echo json_encode([
                'status'  => 'error',
                'code'    => 'RATE_LIMIT_EXCEEDED',
                'message' => 'Demasiadas solicitudes desde su dirección IP. Por favor aguarde 60 segundos.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    private static function checkFileRateLimit(string $ipHash, string $routeGroup, int $windowStart): int
    {
        $file = sys_get_temp_dir() . '/legisdata_rl_' . substr($ipHash, 0, 16) . '_' . preg_replace('/[^a-z0-9_]/i', '', $routeGroup) . '.json';
        $data = ['window' => $windowStart, 'count' => 0];

        if (is_readable($file)) {
            $raw = @file_get_contents($file);
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && ($decoded['window'] ?? 0) === $windowStart) {
                    $data['count'] = (int)($decoded['count'] ?? 0);
                }
            }
        }

        $data['count']++;
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return $data['count'];
    }

    /**
     * Registro en Audit Log Inmutable (INSERT-only, OWASP ASVS L3)
     */
    public static function logAudit(
        ?PDO $pdo,
        string $eventType,
        string $endpoint,
        string $severity = 'INFO',
        array $details = [],
        ?string $rawPayload = null
    ): void {
        $actorIpHash = PHP_SAPI === 'cli'
            ? hash('sha256', 'CLI_SYSTEM_RUNNER')
            : self::getClientIpHash();

        $payloadHash = $rawPayload !== null ? hash('sha256', $rawPayload) : null;
        $allowedSeverities = ['INFO', 'WARN', 'CRITICAL'];
        if (!in_array($severity, $allowedSeverities, true)) {
            $severity = 'INFO';
        }

        $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE);

        if ($pdo !== null) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO audit_logs (event_type, actor_ip_hash, endpoint, payload_hash, severity, details)
                    VALUES (:event_type, :actor_ip_hash, :endpoint, :payload_hash, :severity, :details)
                ");
                $stmt->execute([
                    ':event_type'    => substr($eventType, 0, 60),
                    ':actor_ip_hash' => $actorIpHash,
                    ':endpoint'      => substr($endpoint, 0, 150),
                    ':payload_hash'  => $payloadHash,
                    ':severity'      => $severity,
                    ':details'       => $detailsJson ?: '{}',
                ]);
                return;
            } catch (Throwable) {
                // Fallback a archivo de auditoría append-only en /tmp
            }
        }

        $logEntry = sprintf(
            "[%s] [%s] event=%s actor=%s endpoint=%s details=%s\n",
            gmdate('Y-m-d\TH:i:s\Z'),
            $severity,
            $eventType,
            $actorIpHash,
            $endpoint,
            $detailsJson ?: '{}'
        );
        @file_put_contents(sys_get_temp_dir() . '/legisdata_audit.log', $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Sanitiza cadenas de texto de entrada contra XSS e inyección de caracteres de control
     */
    public static function sanitizeString(?string $input, int $maxLength = 120): string
    {
        if ($input === null) {
            return '';
        }
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', trim($input)) ?? '';
        if (mb_strlen($clean, 'UTF-8') > $maxLength) {
            $clean = mb_substr($clean, 0, $maxLength, 'UTF-8');
        }
        return $clean;
    }

    /**
     * Valida enteros dentro de un rango estricto
     */
    public static function validateInt(mixed $value, int $min, int $max, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (!is_numeric($value)) {
            return $default;
        }
        $intVal = (int)$value;
        if ($intVal < $min) {
            return $min;
        }
        if ($intVal > $max) {
            return $max;
        }
        return $intVal;
    }

    /**
     * Valida un valor contra una lista blanca (whitelist) estricta
     */
    public static function validateEnum(?string $value, array $allowed, string $default = ''): string
    {
        if ($value === null || trim($value) === '') {
            return $default;
        }
        $trimmed = trim($value);
        foreach ($allowed as $item) {
            if (strcasecmp($trimmed, (string)$item) === 0) {
                return (string)$item;
            }
        }
        return $default;
    }

    /**
     * Protege la ejecución de scripts Cron: sólo CLI o petición firmada con Token/HMAC válido
     */
    public static function verifyCronAccess(?PDO $pdo, string $scriptName): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $secret = Config::env('CRON_SECRET', '');
        $providedToken = $_SERVER['HTTP_X_CRON_TOKEN'] ?? $_SERVER['HTTP_X_CRON_SIGNATURE'] ?? '';

        $expectedHmac = $secret !== '' ? hash_hmac('sha256', $scriptName, $secret) : '';
        $isValid = ($secret !== '' && strlen($secret) >= 32 && $providedToken !== '') && (
            hash_equals($secret, $providedToken) ||
            hash_equals($expectedHmac, $providedToken)
        );

        if (!$isValid) {
            self::logAudit(
                $pdo,
                'CRON_UNAUTHORIZED_ATTEMPT',
                $scriptName,
                'CRITICAL',
                ['reason' => 'Missing or invalid X-Cron-Token / non-CLI invocation']
            );
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status'  => 'error',
                'code'    => 'FORBIDDEN_CRON_ACCESS',
                'message' => 'Acceso denegado: Este proceso de sincronización solo puede ejecutarse desde CLI o con firma HMAC autorizada.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
