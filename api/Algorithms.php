<?php
declare(strict_types=1);

/**
 * LEGISDATA (dipudata.com) — Motores Algorítmicos y Financieros Core
 * Etapa 2: Backend PHP 8.x & Algoritmos Matemáticos
 *
 * Incluye:
 *   - Algoritmo A: calcularEvolucionPatrimonial($legislador_id, $pdo)
 *   - Algoritmo B: calcularPerfilTematico($legislador_id, $pdo)
 *   - Algoritmo C: calcularMatchElectoral($vector_usuario, $vector_legislador)
 */

namespace LegisData {

    use PDO;
    use Throwable;

    require_once __DIR__ . '/config.php';

    class Algorithms
    {
        public const EJES_TODOS = ['Salud', 'Educacion', 'MercadoLibre', 'Proteccionismo', 'Laboral'];
        public const DIMENSIONES_MATCH = ['Salud', 'Educacion', 'MercadoLibre', 'Proteccionismo'];

        /**
         * ALGORITMO A — Evolución Patrimonial Real (Deflactación Automática USD MEP + IPC)
         * Cruza patrimonio_ddjj con indicadores_economicos ordenado por anio_fiscal ASC.
         */
        public static function calcularEvolucionPatrimonial(int $legislador_id, ?PDO $pdo = null): array
        {
            $rows = [];

            if ($pdo !== null) {
                try {
                    $stmt = $pdo->prepare("
                        SELECT
                            p.anio_fiscal,
                            p.monto_pesos_nominal,
                            p.fuente,
                            i.ipc_interanual,
                            i.dolar_mep_cierre
                        FROM patrimonio_ddjj p
                        INNER JOIN indicadores_economicos i ON p.anio_fiscal = i.anio
                        WHERE p.legislador_id = :legislador_id
                        ORDER BY p.anio_fiscal ASC
                    ");
                    $stmt->execute([':legislador_id' => $legislador_id]);
                    $rows = $stmt->fetchAll();
                } catch (Throwable) {
                    $rows = [];
                }
            }

            if (empty($rows)) {
                $store = Config::getMemoryStore();
                $serieLeg = $store['patrimonio'][$legislador_id] ?? [];
                $indicadores = $store['indicadores'] ?? [];
                ksort($serieLeg);

                foreach ($serieLeg as $anio => $montoNominal) {
                    if (!isset($indicadores[$anio])) {
                        continue;
                    }
                    $rows[] = [
                        'anio_fiscal'         => (int)$anio,
                        'monto_pesos_nominal' => (float)$montoNominal,
                        'fuente'              => 'Oficina Anticorrupción',
                        'ipc_interanual'      => (float)$indicadores[$anio]['ipc_interanual'],
                        'dolar_mep_cierre'    => (float)$indicadores[$anio]['dolar_mep_cierre'],
                    ];
                }
            }

            if (empty($rows)) {
                return [
                    'legislador_id'                      => $legislador_id,
                    'serie'                              => [],
                    'anio_inicial'                       => null,
                    'anio_actual'                        => null,
                    'patrimonio_inicial_usd'             => 0.0,
                    'patrimonio_actual_usd'              => 0.0,
                    'patrimonio_inicial_pesos_nominal'   => 0.0,
                    'patrimonio_actual_pesos_nominal'    => 0.0,
                    'patrimonio_inicial_pesos_constantes'=> 0.0,
                    'patrimonio_actual_pesos_constantes' => 0.0,
                    'crecimiento_real_usd_pct'           => 0.0,
                    'crecimiento_pesos_constantes_pct'   => 0.0,
                    'tendencia'                          => 'estable',
                ];
            }

            $n = count($rows);
            // Calcular factor deflactor acumulado de IPC para expresar todos los años en Pesos Constantes del último año fiscal
            $deflactores = array_fill(0, $n, 1.0);
            for ($i = $n - 2; $i >= 0; $i--) {
                $ipcSiguiente = (float)$rows[$i + 1]['ipc_interanual'];
                $deflactores[$i] = $deflactores[$i + 1] * (1.0 + ($ipcSiguiente / 100.0));
            }

            $serie = [];
            $prevUsd = null;

            foreach ($rows as $idx => $r) {
                $anio = (int)$r['anio_fiscal'];
                $nominal = round((float)$r['monto_pesos_nominal'], 2);
                $dolarMep = round((float)$r['dolar_mep_cierre'], 2);
                $ipc = round((float)$r['ipc_interanual'], 2);

                $patrimonioUsd = $dolarMep > 0 ? round($nominal / $dolarMep, 2) : 0.0;
                $patrimonioConstante = round($nominal * $deflactores[$idx], 2);

                $varInteranualUsd = 0.0;
                if ($prevUsd !== null && $prevUsd > 0) {
                    $varInteranualUsd = round((($patrimonioUsd - $prevUsd) / $prevUsd) * 100.0, 2);
                }
                $prevUsd = $patrimonioUsd;

                $serie[] = [
                    'anio_fiscal'                 => $anio,
                    'monto_pesos_nominal'         => $nominal,
                    'dolar_mep_cierre'            => $dolarMep,
                    'ipc_interanual'              => $ipc,
                    'indice_deflactor_ipc'        => round($deflactores[$idx], 4),
                    'patrimonio_usd'              => $patrimonioUsd,
                    'patrimonio_pesos_constantes' => $patrimonioConstante,
                    'variacion_interanual_usd_pct'=> $varInteranualUsd,
                    'fuente'                      => (string)($r['fuente'] ?? 'Oficina Anticorrupción'),
                ];
            }

            $primero = $serie[0];
            $ultimo  = $serie[count($serie) - 1];

            $inicialUsd = $primero['patrimonio_usd'];
            $actualUsd  = $ultimo['patrimonio_usd'];
            $crecimientoRealUsdPct = $inicialUsd > 0
                ? round((($actualUsd - $inicialUsd) / $inicialUsd) * 100.0, 2)
                : 0.0;

            $inicialConst = $primero['patrimonio_pesos_constantes'];
            $actualConst  = $ultimo['patrimonio_pesos_constantes'];
            $crecimientoConstPct = $inicialConst > 0
                ? round((($actualConst - $inicialConst) / $inicialConst) * 100.0, 2)
                : 0.0;

            $tendencia = 'estable';
            if ($crecimientoRealUsdPct > 2.0) {
                $tendencia = 'crecimiento';
            } elseif ($crecimientoRealUsdPct < -2.0) {
                $tendencia = 'caida';
            }

            return [
                'legislador_id'                       => $legislador_id,
                'anio_inicial'                        => $primero['anio_fiscal'],
                'anio_actual'                         => $ultimo['anio_fiscal'],
                'patrimonio_inicial_usd'              => $inicialUsd,
                'patrimonio_actual_usd'               => $actualUsd,
                'patrimonio_inicial_pesos_nominal'    => $primero['monto_pesos_nominal'],
                'patrimonio_actual_pesos_nominal'     => $ultimo['monto_pesos_nominal'],
                'patrimonio_inicial_pesos_constantes' => $inicialConst,
                'patrimonio_actual_pesos_constantes'  => $actualConst,
                'dolar_mep_ultimo_cierre'             => $ultimo['dolar_mep_cierre'],
                'crecimiento_real_usd_pct'            => $crecimientoRealUsdPct,
                'crecimiento_pesos_constantes_pct'    => $crecimientoConstPct,
                'tendencia'                           => $tendencia,
                'serie'                               => $serie,
            ];
        }

        /**
         * ALGORITMO B — Perfilamiento Temático (Vectores de Votación 0 a 100%)
         * Fórmula exacta por eje:
         *   (Votos Afirmativos en Leyes del Eje) / (Votos Totales Emitidos en Leyes del Eje) * 100
         * Excluyendo las ausencias ('Ausente') para no distorsionar la huella ideológica.
         * Incluye además el índice ponderado por peso_ideologico de cada ley.
         */
        public static function calcularPerfilTematico(int $legislador_id, ?PDO $pdo = null): array
        {
            $rows = [];

            if ($pdo !== null) {
                try {
                    $stmt = $pdo->prepare("
                        SELECT
                            l.id AS ley_id,
                            l.eje_tematico,
                            l.peso_ideologico,
                            v.sentido_voto
                        FROM votos v
                        INNER JOIN leyes l ON v.ley_id = l.id
                        WHERE v.legislador_id = :legislador_id
                    ");
                    $stmt->execute([':legislador_id' => $legislador_id]);
                    $rows = $stmt->fetchAll();
                } catch (Throwable) {
                    $rows = [];
                }
            }

            if (empty($rows)) {
                $store = Config::getMemoryStore();
                $votosLeg = $store['votos'][$legislador_id] ?? [];
                $leyes = $store['leyes'] ?? [];

                foreach ($votosLeg as $leyId => $sentido) {
                    if (!isset($leyes[$leyId])) {
                        continue;
                    }
                    $rows[] = [
                        'ley_id'          => $leyId,
                        'eje_tematico'    => $leyes[$leyId]['eje_tematico'],
                        'peso_ideologico' => (float)$leyes[$leyId]['peso_ideologico'],
                        'sentido_voto'    => $sentido,
                    ];
                }
            }

            $acumuladores = [];
            foreach (self::EJES_TODOS as $eje) {
                $acumuladores[$eje] = [
                    'afirmativos'    => 0,
                    'negativos'      => 0,
                    'abstenciones'   => 0,
                    'ausentes'       => 0,
                    'total_emitidos' => 0,
                    'peso_afirmativo'=> 0.0,
                    'peso_total'     => 0.0,
                ];
            }

            foreach ($rows as $r) {
                $eje = (string)$r['eje_tematico'];
                if (!isset($acumuladores[$eje])) {
                    continue;
                }
                $sentido = (string)$r['sentido_voto'];
                $peso = (float)($r['peso_ideologico'] ?? 1.0);

                if ($sentido === 'Ausente') {
                    $acumuladores[$eje]['ausentes']++;
                    continue;
                }

                // Se excluyen las ausencias del denominador de votos emitidos
                $acumuladores[$eje]['total_emitidos']++;
                $acumuladores[$eje]['peso_total'] += $peso;

                if ($sentido === 'Afirmativo') {
                    $acumuladores[$eje]['afirmativos']++;
                    $acumuladores[$eje]['peso_afirmativo'] += (1.0 * $peso);
                } elseif ($sentido === 'Abstencion') {
                    $acumuladores[$eje]['abstenciones']++;
                    $acumuladores[$eje]['peso_afirmativo'] += (0.25 * $peso);
                } elseif ($sentido === 'Negativo') {
                    $acumuladores[$eje]['negativos']++;
                }
            }

            $vectores = [];
            $vectoresPonderados = [];
            $detalleEjes = [];

            foreach (self::EJES_TODOS as $eje) {
                $d = $acumuladores[$eje];
                // Fórmula exacta: (Votos Afirmativos en Leyes del Eje) / (Votos Totales Emitidos en Leyes del Eje) * 100
                $pctExacto = $d['total_emitidos'] > 0
                    ? round(($d['afirmativos'] / $d['total_emitidos']) * 100.0, 2)
                    : 0.0;

                $pctPonderado = $d['peso_total'] > 0.0
                    ? round(($d['peso_afirmativo'] / $d['peso_total']) * 100.0, 2)
                    : 0.0;

                $vectores[$eje] = $pctExacto;
                $vectoresPonderados[$eje] = $pctPonderado;

                $detalleEjes[$eje] = [
                    'eje'                   => $eje,
                    'afirmativos'           => $d['afirmativos'],
                    'negativos'             => $d['negativos'],
                    'abstenciones'          => $d['abstenciones'],
                    'ausentes'              => $d['ausentes'],
                    'total_emitidos'        => $d['total_emitidos'],
                    'porcentaje_afirmativo' => $pctExacto,
                    'score_ponderado'       => $pctPonderado,
                ];
            }

            return [
                'legislador_id'       => $legislador_id,
                'vectores'            => $vectores,
                'vectores_ponderados' => $vectoresPonderados,
                'detalle_ejes'        => $detalleEjes,
            ];
        }

        /**
         * ALGORITMO C — Motor de Match Electoral (Similitud de Coseno en 4 Dimensiones)
         * Dimensiones: ['Salud', 'Educacion', 'MercadoLibre', 'Proteccionismo']
         */
        public static function calcularMatchElectoral(array $vector_usuario, array $vector_legislador): float
        {
            $dot_product = 0.0;
            $norm_a = 0.0;
            $norm_b = 0.0;
            $dimensiones = self::DIMENSIONES_MATCH;

            foreach ($dimensiones as $dim) {
                $val_u = (float)($vector_usuario[$dim] ?? 0.0);
                $val_l = (float)($vector_legislador[$dim] ?? 0.0);
                $dot_product += $val_u * $val_l;
                $norm_a += ($val_u ** 2);
                $norm_b += ($val_l ** 2);
            }

            if ($norm_a == 0.0 || $norm_b == 0.0) {
                return 0.0;
            }

            $similitud = $dot_product / (sqrt($norm_a) * sqrt($norm_b));
            return round($similitud * 100.0, 2);
        }

        /**
         * Convierte las respuestas de la encuesta interactiva (8 preguntas) o un vector directo
         * en el vector normalizado [0..100] sobre las 4 dimensiones ['Salud', 'Educacion', 'MercadoLibre', 'Proteccionismo'].
         */
        public static function normalizarVectorEncuesta(array $payload): array
        {
            $dimensiones = self::DIMENSIONES_MATCH;

            // Caso 1: El cliente envía directamente vector_usuario o las 4 llaves en la raíz
            $sourceVector = isset($payload['vector_usuario']) && is_array($payload['vector_usuario'])
                ? $payload['vector_usuario']
                : $payload;

            $hasDirectKeys = false;
            foreach ($dimensiones as $dim) {
                if (isset($sourceVector[$dim]) && is_numeric($sourceVector[$dim])) {
                    $hasDirectKeys = true;
                    break;
                }
            }

            if ($hasDirectKeys) {
                $resultado = [];
                foreach ($dimensiones as $dim) {
                    $val = isset($sourceVector[$dim]) && is_numeric($sourceVector[$dim])
                        ? (float)$sourceVector[$dim]
                        : 50.0;
                    $resultado[$dim] = round(max(0.0, min(100.0, $val)), 2);
                }
                return $resultado;
            }

            // Caso 2: El cliente envía el arreglo 'respuestas' de las 8 preguntas del Wizard
            // Cada respuesta puede ser { eje: 'Salud', valor: 0..100 } o un mapa de preguntas Q1..Q8
            $mapaPreguntasEje = [
                1 => 'Salud',
                2 => 'Salud',
                3 => 'Educacion',
                4 => 'Educacion',
                5 => 'MercadoLibre',
                6 => 'MercadoLibre',
                7 => 'Proteccionismo',
                8 => 'Proteccionismo',
            ];

            $sumas = ['Salud' => 0.0, 'Educacion' => 0.0, 'MercadoLibre' => 0.0, 'Proteccionismo' => 0.0];
            $conteos = ['Salud' => 0, 'Educacion' => 0, 'MercadoLibre' => 0, 'Proteccionismo' => 0];

            $respuestas = $payload['respuestas'] ?? [];
            if (is_array($respuestas)) {
                $idx = 1;
                foreach ($respuestas as $key => $item) {
                    if (is_array($item)) {
                        $eje = (string)($item['eje'] ?? $item['dimension'] ?? ($mapaPreguntasEje[$idx] ?? 'Salud'));
                        $valor = isset($item['valor']) && is_numeric($item['valor'])
                            ? (float)$item['valor']
                            : (isset($item['score']) && is_numeric($item['score']) ? (float)$item['score'] : 50.0);
                    } else {
                        $numPregunta = is_numeric($key) ? ((int)$key + (isset($respuestas[0]) ? 1 : 0)) : $idx;
                        $eje = $mapaPreguntasEje[$numPregunta] ?? 'Salud';
                        $valor = is_numeric($item) ? (float)$item : 50.0;
                    }

                    if (isset($sumas[$eje])) {
                        $sumas[$eje] += max(0.0, min(100.0, $valor));
                        $conteos[$eje]++;
                    }
                    $idx++;
                }
            }

            $vectorFinal = [];
            foreach ($dimensiones as $dim) {
                $vectorFinal[$dim] = $conteos[$dim] > 0
                    ? round($sumas[$dim] / $conteos[$dim], 2)
                    : 50.0;
            }

            return $vectorFinal;
        }
    }
}

namespace {
    use LegisData\Algorithms;

    if (!function_exists('calcularEvolucionPatrimonial')) {
        function calcularEvolucionPatrimonial(int $legislador_id, ?\PDO $pdo = null): array
        {
            return Algorithms::calcularEvolucionPatrimonial($legislador_id, $pdo);
        }
    }

    if (!function_exists('calcularPerfilTematico')) {
        function calcularPerfilTematico(int $legislador_id, ?\PDO $pdo = null): array
        {
            return Algorithms::calcularPerfilTematico($legislador_id, $pdo);
        }
    }

    if (!function_exists('calcularMatchElectoral')) {
        function calcularMatchElectoral(array $vector_usuario, array $vector_legislador): float
        {
            return Algorithms::calcularMatchElectoral($vector_usuario, $vector_legislador);
        }
    }
}
