<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\LegisladorModel;
use LegisData\Security;

/**
 * Motor de Match Electoral 360 (Fase 2) — Similitud del Coseno Dinámica.
 * - Construye dinámicamente vectores de N dimensiones iterando sobre ejes_tematicos activos.
 * - Soporta valor 0 (saltar tema / sin interés) en cualquier eje sin provocar distorsión ni división por cero.
 * - Incluye los 11 ejes de la matriz legislativa nacional (jubilaciones, seguridad, medio ambiente, federalismo, etc.).
 */
class MatchEngineController
{
    public const PREGUNTAS = [
        // 1. Salud Pública
        ['id' => 'q1', 'eje' => 'Salud Pública', 'tipo' => 'material', 'invertida' => false,
         'texto' => 'Si necesitaras un tratamiento de alta complejidad, preferirías contar con un hospital público nacional de referencia antes que depender exclusivamente de una prepaga.'],
        ['id' => 'q2', 'eje' => 'Salud Pública', 'tipo' => 'ideologica', 'invertida' => false,
         'texto' => 'El Estado debe garantizar la provisión gratuita de medicamentos oncológicos y vacunas aunque demande mayor gasto público.'],

        // 2. Educación
        ['id' => 'q3', 'eje' => 'Educación', 'tipo' => 'material', 'invertida' => false,
         'texto' => 'Tu familia asiste (o proyecta asistir) a universidades o escuelas públicas gratuitas de gestión estatal.'],
        ['id' => 'q4', 'eje' => 'Educación', 'tipo' => 'ideologica', 'invertida' => true,
         'texto' => 'Las universidades nacionales deberían arancelar sus carreras de grado para solventar su presupuesto.'],

        // 3. Apertura Comercial
        ['id' => 'q5', 'eje' => 'Apertura Comercial', 'tipo' => 'material', 'invertida' => false,
         'texto' => 'Te beneficiaría que se eliminen aranceles para importar tecnología, electrónica e insumos al menor precio posible.'],
        ['id' => 'q6', 'eje' => 'Apertura Comercial', 'tipo' => 'ideologica', 'invertida' => false,
         'texto' => 'La apertura irrestricta de importaciones y la desregulación económica son el camino más eficiente para bajar la inflación.'],

        // 4. Industria Nacional
        ['id' => 'q7', 'eje' => 'Industria Nacional', 'tipo' => 'material', 'invertida' => false,
         'texto' => 'Tu actividad o la de tu entorno productivo depende de fábricas locales, talleres o PyMEs de manufactura nacional.'],
        ['id' => 'q8', 'eje' => 'Industria Nacional', 'tipo' => 'ideologica', 'invertida' => false,
         'texto' => 'El Congreso debe mantener regímenes de compre argentino y protección arancelaria frente a la competencia desleal extranjera.'],

        // 5. Previsión Social y Discapacidad (Nuevo)
        ['id' => 'q9', 'eje' => 'Previsión Social y Discapacidad', 'tipo' => 'material', 'invertida' => false,
         'texto' => 'Los haberes jubilatorios mínimos y las pensiones no contributivas por discapacidad deben recomponerse prioritariamente por sobre el superávit fiscal.'],
        ['id' => 'q10', 'eje' => 'Previsión Social y Discapacidad', 'tipo' => 'ideologica', 'invertida' => false,
         'texto' => 'La fórmula previsional debe incluir cláusulas gatillo obligatorias contra la inflación e índices salariales reales.'],

        // 6. Medio Ambiente y Derechos Humanos (Nuevo)
        ['id' => 'q11', 'eje' => 'Medio Ambiente y Derechos Humanos', 'tipo' => 'material', 'invertida' => false,
         'texto' => 'La protección de glaciares, humedales y cuencas de agua dulce debe ser estricta, incluso si posterga proyectos mineros o agropecuarios.'],
        ['id' => 'q12', 'eje' => 'Medio Ambiente y Derechos Humanos', 'tipo' => 'ideologica', 'invertida' => true,
         'texto' => 'Las exigencias ambientales a las empresas extractivas deben flexibilizarse para atraer grandes inversiones de capital.'],

        // 7. Seguridad y Sistema Penal (Nuevo)
        ['id' => 'q13', 'eje' => 'Seguridad y Sistema Penal', 'tipo' => 'material', 'invertida' => false,
         'texto' => 'Debe bajarse la edad de imputabilidad penal de menores y aumentarse las penas para delitos graves y crimen organizado.'],
        ['id' => 'q14', 'eje' => 'Seguridad y Sistema Penal', 'tipo' => 'ideologica', 'invertida' => false,
         'texto' => 'El sistema de seguridad debe priorizar la autoridad y el respaldo legal preventivo de las fuerzas policiales en la vía pública.'],

        // 8. Género y Cuidados
        ['id' => 'q15', 'eje' => 'Género y Cuidados', 'tipo' => 'ideologica', 'invertida' => false,
         'texto' => 'El Estado debe legislar un sistema integral de cuidados y ampliar licencias parentales compartidas para cerrar brechas laborales.'],

        // 9. Federalismo y Agro (Nuevo)
        ['id' => 'q16', 'eje' => 'Federalismo y Agro', 'tipo' => 'material', 'invertida' => false,
         'texto' => 'Deben eliminarse de forma inmediata las retenciones al campo y coparticipar en mayor porcentaje los impuestos federales con las provincias.'],
        ['id' => 'q17', 'eje' => 'Federalismo y Agro', 'tipo' => 'ideologica', 'invertida' => false,
         'texto' => 'Los recursos generados por la producción agropecuaria y minera en el interior del país deben quedar mayoritariamente en sus provincias de origen.'],

        // 10. Transparencia Institucional
        ['id' => 'q18', 'eje' => 'Transparencia Institucional', 'tipo' => 'ideologica', 'invertida' => false,
         'texto' => 'Ninguna persona condenada en segunda instancia por delitos contra la administración pública debería poder ser candidata electoral (Ficha Limpia).'],

        // 11. Tecnología y Trabajo
        ['id' => 'q19', 'eje' => 'Tecnología y Trabajo', 'tipo' => 'ideologica', 'invertida' => false,
         'texto' => 'Los repartidores y trabajadores de aplicaciones digitales deben contar con derechos laborales garantizados (ART, aportes y salario mínimo).'],
    ];

    public const OPCIONES = [
        1 => 'Muy en desacuerdo',
        2 => 'En desacuerdo',
        3 => 'Neutral',
        4 => 'De acuerdo',
        5 => 'Muy de acuerdo',
        0 => 'Saltar tema (No me interesa / Neutral)',
    ];

    private LegisladorModel $model;

    public function __construct(?LegisladorModel $model = null)
    {
        $this->model = $model ?? new LegisladorModel();
    }

    /** POST /api/match360 — JSON: {"respuestas": {"q1": 0..5, ...}} */
    public function handle(): void
    {
        Security::sendSecurityHeaders();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Método no permitido. Use POST.'], 405);
        }
        Security::enforceRateLimit(null, 'api_match360', 30);

        $raw = (string)file_get_contents('php://input', false, null, 0, 16384);
        $payload = json_decode($raw, true);
        $respuestas = is_array($payload) ? ($payload['respuestas'] ?? null) : null;

        $validadas = self::validarRespuestas($respuestas);
        if ($validadas === null) {
            $this->json([
                'status'  => 'error',
                'message' => 'Formato de respuestas inválido. Se espera un objeto clave-valor con enteros entre 0 (saltar) y 5.'
            ], 422);
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
            $id = $p['id'];
            if (!isset($respuestas[$id])) {
                // Si se omitió en el payload, se asume 0 (saltar tema)
                $out[$id] = 0;
                continue;
            }
            $v = $respuestas[$id];
            if (!is_numeric($v) || (int)$v != $v || (int)$v < 0 || (int)$v > 5) {
                return null;
            }
            $out[$id] = (int)$v;
        }
        return $out;
    }

    /**
     * Construye dinámicamente el vector del usuario para los N ejes activos de la base de datos.
     * Si el usuario eligió saltar preguntas (valor 0), el eje se computa como 0 (neutro sin preferencia).
     * @return array<string, float>
     */
    public function vectorUsuario(array $respuestas): array
    {
        $nombresEjes = $this->model->nombresEjes();
        $acum = array_fill_keys($nombresEjes, []);

        foreach (self::PREGUNTAS as $p) {
            $eje = $p['eje'];
            if (!isset($acum[$eje])) {
                continue;
            }
            $v = $respuestas[$p['id']] ?? 0;
            if ($v === 0) {
                // El usuario decidió saltar este tema
                continue;
            }
            // Escala 1..5 normalizada a 0..100 (1=0, 2=25, 3=50, 4=75, 5=100)
            $valor = ($v - 1) * 25.0;
            $acum[$eje][] = $p['invertida'] ? (100.0 - $valor) : $valor;
        }

        $vector = [];
        foreach ($nombresEjes as $eje) {
            $valores = $acum[$eje];
            if (empty($valores)) {
                $vector[$eje] = 0.0; // Neutro / Saltado
            } else {
                $vector[$eje] = round(array_sum($valores) / count($valores), 2);
            }
        }

        return $vector;
    }

    /**
     * Similitud del Coseno Dinámica sobre N dimensiones.
     * Si el usuario saltó temas (valor 0), se evalúa la afinidad sobre el subespacio de
     * temas en los que manifestó preferencia (> 0), evitando penalizar a legisladores por ejes omitidos.
     * Si no respondió ninguna pregunta (> 0), devuelve 0.0.
     *
     * @param array<string, float> $u Vector del votante
     * @param array<string, float> $l Vector del legislador
     * @param list<string> $ejes Lista de nombres de ejes a comparar
     */
    public static function similitudCoseno(array $u, array $l, array $ejes): float
    {
        $dot = 0.0;
        $normU = 0.0;
        $normL = 0.0;
        $ejesConPreferencia = 0;

        foreach ($ejes as $eje) {
            $valU = (float)($u[$eje] ?? 0.0);
            $valL = (float)($l[$eje] ?? 0.0);

            if ($valU > 0.0) {
                $ejesConPreferencia++;
                $dot += $valU * $valL;
                $normU += $valU ** 2;
                $normL += $valL ** 2;
            }
        }

        // Si el usuario no respondió o saltó todas las preguntas con valor 0
        if ($ejesConPreferencia === 0 || $normU <= 0.0 || $normL <= 0.0) {
            return 0.0;
        }

        $similitud = $dot / (sqrt($normU) * sqrt($normL));
        return round(max(0.0, min(100.0, $similitud * 100)), 2);
    }

    /**
     * Ejecuta el cálculo integral de afinidad contra todos los legisladores activos.
     */
    public function calcular(array $respuestas): array
    {
        $ejes = $this->model->nombresEjes();
        $vectorUsuario = $this->vectorUsuario($respuestas);

        $ranking = [];
        foreach ($this->model->all() as $leg) {
            $vectorLeg = $this->model->perfilTematico($leg['id_recinto']);
            $ranking[] = [
                'nombre'    => $leg['nombre'],
                'camara'    => $leg['camara'],
                'provincia' => $leg['provincia'],
                'bloque'    => $leg['bloque'],
                'url'       => LegisladorModel::urlPerfil($leg),
                'afinidad'  => self::similitudCoseno($vectorUsuario, $vectorLeg, $ejes),
                'vector'    => $vectorLeg,
            ];
        }

        usort($ranking, fn($a, $b) => $b['afinidad'] <=> $a['afinidad']);

        return [
            'algoritmo'      => sprintf('Similitud del Coseno (%d dimensiones dinámicas)', count($ejes)),
            'total_ejes'     => count($ejes),
            'ejes'           => $ejes,
            'vector_usuario' => $vectorUsuario,
            'top3'           => array_slice($ranking, 0, 3),
            'ranking_total'  => count($ranking),
        ];
    }

    private function json(array $data, int $code = 200): never
    {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
