<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\LegisladorModel;
use LegisData\Security;

/**
 * Motor de Match Electoral 360 — Similitud del Coseno.
 * 8 preguntas: por cada eje, 1 preferencia material (bolsillo) + 1 ideológica (valores).
 * Escala de respuesta 1..5 -> 0..100. Las preguntas "invertida" restan a favor del eje.
 */
class MatchEngineController
{
    public const PREGUNTAS = [
        ['id' => 'q1', 'eje' => 'Salud Pública', 'tipo' => 'material', 'invertida' => false,
         'texto' => 'Si mañana necesitaras una cirugía, preferirías atenderte en un hospital público bien financiado antes que pagar una prepaga.'],
        ['id' => 'q2', 'eje' => 'Salud Pública', 'tipo' => 'ideologica', 'invertida' => false,
         'texto' => 'El Estado debe garantizar la salud de toda la población aunque implique más gasto público.'],
        ['id' => 'q3', 'eje' => 'Educación Pública', 'tipo' => 'material', 'invertida' => false,
         'texto' => 'Tu familia usa (o usaría) escuelas o universidades públicas y gratuitas.'],
        ['id' => 'q4', 'eje' => 'Educación Pública', 'tipo' => 'ideologica', 'invertida' => true,
         'texto' => 'Las universidades públicas deberían arancelarse para quienes pueden pagarlas.'],
        ['id' => 'q5', 'eje' => 'Industria Nacional', 'tipo' => 'material', 'invertida' => false,
         'texto' => 'Tu ingreso depende directa o indirectamente de una fábrica, PyME industrial o economía regional.'],
        ['id' => 'q6', 'eje' => 'Industria Nacional', 'tipo' => 'ideologica', 'invertida' => false,
         'texto' => 'Es preferible pagar algo más caro un producto si eso protege empleo industrial argentino.'],
        ['id' => 'q7', 'eje' => 'Apertura Comercial', 'tipo' => 'material', 'invertida' => false,
         'texto' => 'Te beneficiaría poder comprar tecnología, ropa o insumos importados más baratos.'],
        ['id' => 'q8', 'eje' => 'Apertura Comercial', 'tipo' => 'ideologica', 'invertida' => false,
         'texto' => 'Bajar aranceles y desregular la economía genera más inversión y crecimiento a largo plazo.'],
    ];

    public const OPCIONES = [
        1 => 'Muy en desacuerdo',
        2 => 'En desacuerdo',
        3 => 'Neutral',
        4 => 'De acuerdo',
        5 => 'Muy de acuerdo',
    ];

    private LegisladorModel $model;

    public function __construct(?LegisladorModel $model = null)
    {
        $this->model = $model ?? new LegisladorModel();
    }

    /** POST /api/match360 — JSON: {"respuestas": {"q1": 1..5, ..., "q8": 1..5}} */
    public function handle(): void
    {
        Security::sendSecurityHeaders();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Método no permitido. Use POST.'], 405);
        }
        Security::enforceRateLimit(null, 'api_match360', 30);

        $raw = (string)file_get_contents('php://input', false, null, 0, 8192);
        $payload = json_decode($raw, true);
        $respuestas = is_array($payload) ? ($payload['respuestas'] ?? null) : null;

        $validadas = self::validarRespuestas($respuestas);
        if ($validadas === null) {
            $this->json(['status' => 'error', 'message' => 'Se requieren las 8 respuestas (q1..q8) con valores enteros de 1 a 5.'], 422);
        }

        $this->json(['status' => 'ok'] + $this->calcular($validadas));
    }

    public static function validarRespuestas(mixed $respuestas): ?array
    {
        if (!is_array($respuestas)) {
            return null;
        }
        $out = [];
        foreach (self::PREGUNTAS as $p) {
            $v = $respuestas[$p['id']] ?? null;
            if (!is_numeric($v) || (int)$v != $v || (int)$v < 1 || (int)$v > 5) {
                return null;
            }
            $out[$p['id']] = (int)$v;
        }
        return $out;
    }

    /** Respuestas 1..5 -> vector 0..100 por eje (promedio de pregunta material e ideológica). */
    public static function vectorUsuario(array $respuestas): array
    {
        $acum = array_fill_keys(LegisladorModel::EJES, []);
        foreach (self::PREGUNTAS as $p) {
            $valor = ($respuestas[$p['id']] - 1) * 25;
            $acum[$p['eje']][] = $p['invertida'] ? 100 - $valor : $valor;
        }
        return array_map(fn($vals) => round(array_sum($vals) / count($vals), 2), $acum);
    }

    public static function similitudCoseno(array $u, array $l): float
    {
        $dot = $normU = $normL = 0.0;
        foreach (LegisladorModel::EJES as $eje) {
            $dot += $u[$eje] * $l[$eje];
            $normU += $u[$eje] ** 2;
            $normL += $l[$eje] ** 2;
        }
        if ($normU == 0.0 || $normL == 0.0) {
            return 0.0;
        }
        return round($dot / (sqrt($normU) * sqrt($normL)) * 100, 2);
    }

    public function calcular(array $respuestas): array
    {
        $vectorUsuario = self::vectorUsuario($respuestas);
        $ranking = [];
        foreach ($this->model->all() as $leg) {
            $vectorLeg = $this->model->perfilTematico($leg['id_recinto']);
            $ranking[] = [
                'nombre'    => $leg['nombre'],
                'camara'    => $leg['camara'],
                'provincia' => $leg['provincia'],
                'bloque'    => $leg['bloque'],
                'url'       => LegisladorModel::urlPerfil($leg),
                'afinidad'  => self::similitudCoseno($vectorUsuario, $vectorLeg),
                'vector'    => $vectorLeg,
            ];
        }
        usort($ranking, fn($a, $b) => $b['afinidad'] <=> $a['afinidad']);

        return [
            'algoritmo'      => 'Similitud del Coseno (4 ejes)',
            'ejes'           => LegisladorModel::EJES,
            'vector_usuario' => $vectorUsuario,
            'top3'           => array_slice($ranking, 0, 3),
        ];
    }

    private function json(array $data, int $code = 200): never
    {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
