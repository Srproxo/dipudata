<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditorModel;
use App\Models\LegisladorModel;
use LegisData\Security;

/**
 * Controlador del Motor de Auditoría Ética y Transparencia Parlamentaria
 * DipuData / Master PRD
 *
 * Expone endpoints REST y servicios para:
 * - Traición Provincial por Disciplina Partidaria
 * - Coherencia Histórica y Detección de Panquequismo en Leyes Espejo
 * - Grafo de Alianzas Interbloque
 * - Conflictos de Interés (Financiamiento Privado vs. Votos)
 * - Tracking de Promesas de Campaña con Verificación Objetiva
 */
class AuditorEngineController
{
    private AuditorModel $model;
    private LegisladorModel $legisladorModel;

    public function __construct(?AuditorModel $model = null, ?LegisladorModel $legisladorModel = null)
    {
        $this->model = $model ?? new AuditorModel();
        $this->legisladorModel = $legisladorModel ?? new LegisladorModel();
    }

    /**
     * Resuelve peticiones dirigidas a /api/auditoria/*
     * Formatos soportados:
     * - GET /api/auditoria/{id}
     * - GET /api/auditoria/{id}/defensa
     * - GET /api/auditoria/{id}/coherencia
     * - GET /api/auditoria/{id}/alianzas
     * - GET /api/auditoria/{id}/conflictos
     * - GET /api/auditoria/{id}/promesas
     */
    public function handle(string $subpath = ''): void
    {
        Security::sendSecurityHeaders('application/json; charset=utf-8');
        Security::enforceRateLimit(null, 'api_auditoria', 60);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            $this->json(['status' => 'error', 'message' => 'Método no permitido. Use GET.'], 405);
        }

        $parts = array_values(array_filter(explode('/', trim($subpath, '/'))));
        if (empty($parts)) {
            // Si viene vía query param ?id=X
            $idRaw = $_GET['id'] ?? $_GET['legislador_id'] ?? null;
            if ($idRaw === null || !is_numeric($idRaw)) {
                $this->json([
                    'status'  => 'error',
                    'message' => 'ID de legislador no especificado. Uso: /api/auditoria/{id}',
                ], 400);
            }
            $legId = (int)$idRaw;
            $modulo = 'completa';
        } else {
            $idCandidate = $parts[0];
            $legId = is_numeric($idCandidate) ? (int)$idCandidate : null;
            if ($legId === null) {
                $leg = $this->legisladorModel->findBySlug($idCandidate);
                if ($leg) {
                    $legId = (int)$leg['id'];
                }
            }
            if ($legId === null) {
                $this->json([
                    'status'  => 'error',
                    'message' => 'Legislador no encontrado.',
                ], 404);
            }
            $modulo = $parts[1] ?? 'completa';
        }

        $data = match ($modulo) {
            'defensa'    => $this->model->defensaProvincial($legId),
            'coherencia' => $this->model->coherenciaHistorica($legId),
            'alianzas'   => $this->model->grafoAlianzas($legId),
            'conflictos' => $this->model->conflictosInteres($legId),
            'promesas'   => $this->model->trackingPromesas($legId),
            default      => $this->model->auditoriaCompleta($legId),
        };

        $this->json([
            'status' => 'ok',
            'modulo' => $modulo,
            'data'   => $data,
        ]);
    }

    /**
     * Métodos directos para renderizado en vistas SSR
     */
    public function obtenerAuditoria(int $legisladorId): array
    {
        return $this->model->auditoriaCompleta($legisladorId);
    }

    public function obtenerDefensaProvincial(int $legisladorId): array
    {
        return $this->model->defensaProvincial($legisladorId);
    }

    public function obtenerCoherenciaHistorica(int $legisladorId): array
    {
        return $this->model->coherenciaHistorica($legisladorId);
    }

    public function obtenerGrafoAlianzas(int $legisladorId): array
    {
        return $this->model->grafoAlianzas($legisladorId);
    }

    public function obtenerConflictosInteres(int $legisladorId): array
    {
        return $this->model->conflictosInteres($legisladorId);
    }

    public function obtenerPromesasCampana(int $legisladorId): array
    {
        return $this->model->trackingPromesas($legisladorId);
    }

    private function json(array $data, int $code = 200): never
    {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
