<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Models\LegisladorModel;
use LegisData\Security;
use LegisData\Config;

class ApiN8nController
{
    public const DEFAULT_N8N_TOKEN = 'n8n_buceo_secret_token_2026_dipudata';

    private LegisladorModel $model;

    public function __construct(?LegisladorModel $model = null)
    {
        $this->model = $model ?? new LegisladorModel();
    }

    /**
     * Endpoint POST /api/n8n/update-despachos
     * Recibe data estructurada procesada por n8n.buceo.co y actualiza la tabla despacho_costos.
     */
    public function updateDespachos(): never
    {
        Security::sendSecurityHeaders();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json([
                'status'  => 'error',
                'message' => 'Método no permitido. Este endpoint requiere peticiones POST.',
            ], 405);
        }

        // 1. Verificación estricta de Token de Autenticación
        if (!$this->verificarToken()) {
            $this->json([
                'status'  => 'error',
                'message' => 'Acceso no autorizado: Token de autenticación de n8n inválido o ausente.',
            ], 401);
        }

        // 2. Lectura y validación del payload JSON
        $raw = (string)file_get_contents('php://input', false, null, 0, 1048576); // máx 1MB
        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            $this->json([
                'status'  => 'error',
                'message' => 'Cuerpo de la petición inválido. Se espera JSON estructurado.',
            ], 400);
        }

        // Soporta objeto único o lista en lote
        $items = isset($payload['despachos']) && is_array($payload['despachos'])
            ? $payload['despachos']
            : (isset($payload['legislador_id']) ? [$payload] : (array_is_list($payload) ? $payload : []));

        if (empty($items)) {
            $this->json([
                'status'  => 'error',
                'message' => 'El JSON no contiene registros válidos para procesar.',
            ], 422);
        }

        $procesados = [];
        $errores = [];

        foreach ($items as $idx => $item) {
            if (!is_array($item)) {
                $errores[] = "Elemento #{$idx}: Formato no válido.";
                continue;
            }

            $legId = isset($item['legislador_id']) ? (int)$item['legislador_id'] : 0;
            if ($legId <= 0) {
                $errores[] = "Elemento #{$idx}: legislador_id requerido y debe ser mayor a 0.";
                continue;
            }

            $anio = isset($item['anio']) ? (int)$item['anio'] : 2024;
            $totalAsesores = max(0, (int)($item['total_asesores'] ?? 0));
            $costoMensual = max(0.0, (float)($item['costo_mensual_ars'] ?? 0.0));
            $alertasNepotismo = max(0, (int)($item['alertas_nepotismo'] ?? 0));

            // Si no se informan proyectos de autor, se consulta al modelo
            $proyectosAutor = isset($item['proyectos_autor_anio'])
                ? max(0, (int)$item['proyectos_autor_anio'])
                : count($this->model->ultimosProyectosAutor($legId, 100));

            // Cálculo automatizado de gasto anual (con aguinaldo x13) y costo por proyecto
            $costoAnual = isset($item['costo_anual_ars'])
                ? (float)$item['costo_anual_ars']
                : round($costoMensual * 13, 2);

            $costoPorProyecto = isset($item['costo_por_proyecto_ars'])
                ? (float)$item['costo_por_proyecto_ars']
                : ($proyectosAutor > 0 ? round($costoAnual / $proyectosAutor, 2) : $costoAnual);

            $registro = [
                'legislador_id'          => $legId,
                'anio'                   => $anio,
                'total_asesores'         => $totalAsesores,
                'costo_mensual_ars'      => $costoMensual,
                'costo_anual_ars'        => $costoAnual,
                'alertas_nepotismo'      => $alertasNepotismo,
                'proyectos_autor_anio'   => $proyectosAutor,
                'costo_por_proyecto_ars' => $costoPorProyecto,
                'detalle_asesores_json'  => isset($item['detalle_asesores']) ? json_encode($item['detalle_asesores'], JSON_UNESCAPED_UNICODE) : null,
                'fuente'                 => 'n8n.buceo.co',
            ];

            $ok = $this->model->guardarDespachoCostos($registro);
            if ($ok) {
                $procesados[] = [
                    'legislador_id'          => $legId,
                    'anio'                   => $anio,
                    'total_asesores'         => $totalAsesores,
                    'costo_mensual_ars'      => $costoMensual,
                    'costo_anual_ars'        => $costoAnual,
                    'alertas_nepotismo'      => $alertasNepotismo,
                    'proyectos_autor_anio'   => $proyectosAutor,
                    'costo_por_proyecto_ars' => $costoPorProyecto,
                ];
            } else {
                $errores[] = "Legislador ID {$legId}: Error al persistir en base de datos.";
            }
        }

        $this->json([
            'status'     => empty($errores) ? 'ok' : (empty($procesados) ? 'error' : 'partial'),
            'message'    => sprintf('Procesados %d despachos vía n8n.buceo.co.', count($procesados)),
            'procesados' => count($procesados),
            'data'       => $procesados,
            'errores'    => $errores,
        ], empty($procesados) && !empty($errores) ? 500 : 200);
    }

    /**
     * Valida el token contra X-N8N-Token o Authorization: Bearer <token>.
     */
    private function verificarToken(): bool
    {
        $tokenEsperado = Config::env('N8N_TOKEN', self::DEFAULT_N8N_TOKEN);

        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders() ?: [];
        }

        $tokenRecibido = '';
        foreach ($headers as $k => $v) {
            $lower = strtolower($k);
            if ($lower === 'x-n8n-token') {
                $tokenRecibido = trim((string)$v);
                break;
            }
            if ($lower === 'authorization') {
                $tokenRecibido = trim((string)$v);
                if (stripos($tokenRecibido, 'Bearer ') === 0) {
                    $tokenRecibido = trim(substr($tokenRecibido, 7));
                }
                break;
            }
        }

        if ($tokenRecibido === '') {
            $tokenRecibido = $_SERVER['HTTP_X_N8N_TOKEN'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (stripos($tokenRecibido, 'Bearer ') === 0) {
                $tokenRecibido = trim(substr($tokenRecibido, 7));
            }
        }

        return $tokenRecibido !== '' && hash_equals($tokenEsperado, $tokenRecibido);
    }

    private function json(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
