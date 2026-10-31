<?php
declare(strict_types=1);

/**
 * LEGISDATA (dipudata.com) — Router RESTful Principal (API v1)
 * Etapa 2: Backend PHP 8.x, Algoritmos Matemáticos y Seguridad Grado Bancario
 *
 * Endpoints soportados:
 *   - GET  /api/legisladores
 *   - GET  /api/legisladores/{id}
 *   - GET  /api/legisladores/{id}/patrimonio
 *   - POST /api/match
 *   - GET  /api/og
 *   - GET  /api/stats
 */

namespace LegisData;

use PDO;
use Throwable;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Algorithms.php';

// Preflight CORS
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    Security::sendSecurityHeaders();
    http_response_code(204);
    exit;
}

$pdo = Config::getConnection();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Determinar ruta limpia relativa a /api
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/api', PHP_URL_PATH) ?: '/api';
$route = $_GET['route'] ?? '';
if ($route === '') {
    $pos = strpos($requestUri, '/api');
    if ($pos !== false) {
        $route = substr($requestUri, $pos + 4);
    } else {
        $route = $requestUri;
    }
}
$route = '/' . trim($route, '/');

/**
 * Helper para emitir respuesta JSON estandarizada
 */
function respondJson(array $payload, int $statusCode = 200): never
{
    Security::sendSecurityHeaders('application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Obtiene todos los legisladores desde MySQL o desde el almacén en memoria resiliente
 */
function fetchAllLegisladores(?PDO $pdo): array
{
    if ($pdo !== null) {
        try {
            $stmt = $pdo->query("
                SELECT
                    id, id_congreso, cuit, wiki_slug,
                    LOWER(REPLACE(wiki_slug, '_', '-')) AS slug,
                    nombre_completo, camara, provincia, bloque_actual,
                    bloque_actual AS bloque, estado, biografia, foto_url,
                    asistencia_historica, lealtad_partidaria,
                    asistencia_historica AS asistencia_pct,
                    lealtad_partidaria AS lealtad_pct,
                    updated_at
                FROM legisladores
                ORDER BY id ASC
            ");
            $rows = $stmt ? $stmt->fetchAll() : [];
            if (!empty($rows)) {
                foreach ($rows as &$r) {
                    $r['id'] = (int)$r['id'];
                    $r['asistencia_historica'] = (float)$r['asistencia_historica'];
                    $r['lealtad_partidaria'] = (float)$r['lealtad_partidaria'];
                    $r['asistencia_pct'] = (float)$r['asistencia_pct'];
                    $r['lealtad_pct'] = (float)$r['lealtad_pct'];
                }
                unset($r);
            }
        } catch (Throwable) {
            $rows = [];
        }
    }

    $store = Config::getMemoryStore();
    $memoryLegisladores = array_values($store['legisladores'] ?? []);

    if (empty($rows)) {
        return $memoryLegisladores;
    }

    $existing = [];
    foreach ($rows as $r) {
        if (!empty($r['cuit'])) $existing[$r['cuit']] = true;
        if (!empty($r['id_congreso'])) $existing[$r['id_congreso']] = true;
    }
    foreach ($memoryLegisladores as $memLeg) {
        $cuit = $memLeg['cuit'] ?? '';
        $cong = $memLeg['id_congreso'] ?? '';
        if (!isset($existing[$cuit]) && !isset($existing[$cong])) {
            $rows[] = $memLeg;
        }
    }

    return $rows;
}

/**
 * Busca un legislador por ID numérico, slug o wiki_slug
 */
function findLegislador(?PDO $pdo, string $identifier): ?array
{
    $all = fetchAllLegisladores($pdo);
    $clean = trim($identifier);

    foreach ($all as $leg) {
        if (is_numeric($clean) && (int)$leg['id'] === (int)$clean) {
            return $leg;
        }
        if (
            strcasecmp((string)$leg['slug'], $clean) === 0 ||
            strcasecmp((string)$leg['wiki_slug'], $clean) === 0 ||
            strcasecmp((string)$leg['id_congreso'], $clean) === 0
        ) {
            return $leg;
        }
    }
    return null;
}

/**
 * Obtiene la lista completa de votaciones de un legislador con detalles de cada ley
 */
function fetchVotosLegislador(?PDO $pdo, int $legisladorId, string $filtroEje = ''): array
{
    $votosList = [];

    if ($pdo !== null) {
        try {
            $sql = "
                SELECT
                    v.id AS voto_id,
                    v.ley_id,
                    v.sentido_voto,
                    l.titulo,
                    l.resumen,
                    l.fecha_votacion,
                    l.eje_tematico,
                    l.peso_ideologico
                FROM votos v
                INNER JOIN leyes l ON v.ley_id = l.id
                WHERE v.legislador_id = :legislador_id
            ";
            $params = [':legislador_id' => $legisladorId];

            if ($filtroEje !== '') {
                $sql .= " AND l.eje_tematico = :eje";
                $params[':eje'] = $filtroEje;
            }
            $sql .= " ORDER BY l.fecha_votacion DESC, l.id ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            if (!empty($rows)) {
                foreach ($rows as $r) {
                    $votosList[] = [
                        'ley_id'          => (string)$r['ley_id'],
                        'titulo'          => (string)$r['titulo'],
                        'resumen'         => (string)($r['resumen'] ?? ''),
                        'fecha_votacion'  => (string)$r['fecha_votacion'],
                        'eje_tematico'    => (string)$r['eje_tematico'],
                        'peso_ideologico' => (float)$r['peso_ideologico'],
                        'sentido_voto'    => (string)$r['sentido_voto'],
                    ];
                }
                return $votosList;
            }
        } catch (Throwable) {
            // Fallback a memoria
        }
    }

    $store = Config::getMemoryStore();
    $votosMap = $store['votos'][$legisladorId] ?? [];
    $leyesMap = $store['leyes'] ?? [];

    foreach ($votosMap as $leyId => $sentido) {
        if (!isset($leyesMap[$leyId])) {
            continue;
        }
        $ley = $leyesMap[$leyId];
        if ($filtroEje !== '' && strcasecmp($ley['eje_tematico'], $filtroEje) !== 0) {
            continue;
        }
        $votosList[] = [
            'ley_id'          => (string)$ley['id'],
            'titulo'          => (string)$ley['titulo'],
            'resumen'         => (string)$ley['resumen'],
            'fecha_votacion'  => (string)$ley['fecha_votacion'],
            'eje_tematico'    => (string)$ley['eje_tematico'],
            'peso_ideologico' => (float)$ley['peso_ideologico'],
            'sentido_voto'    => (string)$sentido,
        ];
    }

    usort($votosList, static fn(array $a, array $b): int => strcmp($b['fecha_votacion'], $a['fecha_votacion']));
    return $votosList;
}

/**
 * Normaliza el nombre de cámara para admitir 'Diputado'/'Diputados' y 'Senador'/'Senadores'
 */
function matchesCamara(string $legCamara, string $filterCamara): bool
{
    if ($filterCamara === '' || strcasecmp($filterCamara, 'Todas') === 0 || strcasecmp($filterCamara, 'Todos') === 0) {
        return true;
    }
    $normLeg = str_starts_with(strtolower($legCamara), 'diputad') ? 'diputados' : 'senadores';
    $normFil = str_starts_with(strtolower($filterCamara), 'diputad') ? 'diputados' : (str_starts_with(strtolower($filterCamara), 'senad') ? 'senadores' : strtolower($filterCamara));
    return $normLeg === $normFil;
}

// ============================================================================
// RUTA 0: GET /api/legisladores-master (Para n8n ETL y Sincronizadores Externos)
// ============================================================================
if ($method === 'GET' && ($route === '/legisladores-master' || $route === '/legisladores_master')) {
    Security::sendSecurityHeaders('application/json; charset=utf-8');
    $model = new \App\Models\LegisladorModel();
    $items = $model->all();
    $clean = array_map(static fn(array $l): array => [
        'id'             => (int)$l['id'],
        'id_recinto'     => (string)($l['id_recinto'] ?? ''),
        'nombre'         => (string)$l['nombre'],
        'slug'           => (string)$l['slug'],
        'camara'         => (string)$l['camara'],
        'provincia'      => (string)$l['provincia'],
        'bloque'         => (string)$l['bloque'],
        'cuit'           => (string)$l['cuit'],
        'estado'         => (string)($l['estado'] ?? 'Activo'),
        'indice_lealtad' => (float)($l['indice_lealtad'] ?? 0.0),
        'asistencia'     => (float)($l['asistencia'] ?? 0.0),
    ], $items);
    echo json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ============================================================================
// RUTA 0B: GET /api/auditoria/* (Motor de Auditoría Ética Master PRD)
// ============================================================================
if ($method === 'GET' && preg_match('#^/auditoria(?:/(.*))?$#', $route, $audMatches)) {
    require_once dirname(__DIR__) . '/app/bootstrap.php';
    (new \App\Controllers\AuditorEngineController())->handle($audMatches[1] ?? '');
    exit;
}

// ============================================================================
// RUTA 1: GET /api/legisladores (Listado paginado + Búsqueda + Filtros + KPIs)
// ============================================================================
if ($method === 'GET' && ($route === '/legisladores' || $route === '/')) {
    Security::enforceRateLimit($pdo, 'api_read', 120);

    $q         = Security::sanitizeString($_GET['q'] ?? $_GET['search'] ?? '', 100);
    $camara    = Security::sanitizeString($_GET['camara'] ?? '', 30);
    $provincia = Security::sanitizeString($_GET['provincia'] ?? '', 80);
    $bloque    = Security::sanitizeString($_GET['bloque'] ?? '', 80);
    $estado    = Security::validateEnum($_GET['estado'] ?? '', ['Activo', 'Inactivo', 'Mandato Cumplido', 'Candidato'], '');
    $page      = Security::validateInt($_GET['page'] ?? 1, 1, 1000, 1);
    $limit     = Security::validateInt($_GET['limit'] ?? 250, 1, 1000, 250);

    $todos = fetchAllLegisladores($pdo);

    // Extraer metadatos únicos para poblar selectores del Frontend
    $provinciasSet = [];
    $bloquesSet = [];
    $conteoDiputados = 0;
    $conteoSenadores = 0;
    $sumaAsistencia = 0.0;
    $sumaLealtad = 0.0;

    foreach ($todos as $leg) {
        $provinciasSet[$leg['provincia']] = true;
        $bloquesSet[$leg['bloque_actual']] = true;
        if (matchesCamara($leg['camara'], 'Diputados')) {
            $conteoDiputados++;
        } else {
            $conteoSenadores++;
        }
        $sumaAsistencia += (float)$leg['asistencia_historica'];
        $sumaLealtad += (float)$leg['lealtad_partidaria'];
    }

    $provinciasDisponibles = array_keys($provinciasSet);
    sort($provinciasDisponibles);
    $bloquesDisponibles = array_keys($bloquesSet);
    sort($bloquesDisponibles);

    // Filtrar legisladores según criterios
    $filtrados = array_filter($todos, static function (array $leg) use ($q, $camara, $provincia, $bloque, $estado): bool {
        if ($camara !== '' && !matchesCamara((string)$leg['camara'], $camara)) {
            return false;
        }
        if ($provincia !== '' && strcasecmp($provincia, 'Todas') !== 0 && strcasecmp((string)$leg['provincia'], $provincia) !== 0) {
            return false;
        }
        if ($bloque !== '' && strcasecmp($bloque, 'Todos') !== 0 && strcasecmp((string)$leg['bloque_actual'], $bloque) !== 0) {
            return false;
        }
        if ($estado !== '' && strcasecmp((string)$leg['estado'], $estado) !== 0) {
            return false;
        }
        if ($q !== '') {
            $haystack = mb_strtolower(
                $leg['nombre_completo'] . ' ' . $leg['bloque_actual'] . ' ' . $leg['provincia'] . ' ' . $leg['id_congreso'],
                'UTF-8'
            );
            $needle = mb_strtolower($q, 'UTF-8');
            if (!str_contains($haystack, $needle)) {
                return false;
            }
        }
        return true;
    });

    $filtrados = array_values($filtrados);
    $total = count($filtrados);
    $totalPages = max(1, (int)ceil($total / $limit));
    $offset = ($page - 1) * $limit;
    $paginados = array_slice($filtrados, $offset, $limit);

    // Enriquecer cada legislador con su vector temático y resumen patrimonial en USD MEP
    $items = [];
    foreach ($paginados as $leg) {
        $id = (int)$leg['id'];
        $perfil = Algorithms::calcularPerfilTematico($id, $pdo);
        $patrimonio = Algorithms::calcularEvolucionPatrimonial($id, $pdo);

        $leg['vectores'] = $perfil['vectores'];
        $leg['vectores_ponderados'] = $perfil['vectores_ponderados'];
        $votosSimple = [];
        foreach (fetchVotosLegislador($pdo, $id) as $v) {
            $votosSimple[$v['ley_id']] = $v['sentido_voto'];
        }
        $leg['votos'] = $votosSimple;
        $leg['patrimonio_resumen'] = [
            'anio_inicial'                        => $patrimonio['anio_inicial'],
            'anio_actual'                         => $patrimonio['anio_actual'],
            'patrimonio_inicial_usd'              => $patrimonio['patrimonio_inicial_usd'],
            'patrimonio_actual_usd'               => $patrimonio['patrimonio_actual_usd'],
            'patrimonio_actual_pesos_nominal'     => $patrimonio['patrimonio_actual_pesos_nominal'],
            'patrimonio_actual_pesos_constantes'  => $patrimonio['patrimonio_actual_pesos_constantes'],
            'crecimiento_real_usd_pct'            => $patrimonio['crecimiento_real_usd_pct'],
            'tendencia'                           => $patrimonio['tendencia'],
        ];
        $items[] = $leg;
    }

    $countTodos = max(1, count($todos));
    respondJson([
        'status'     => 'ok',
        'data'       => $items,
        'pagination' => [
            'page'        => $page,
            'limit'       => $limit,
            'total'       => $total,
            'total_pages' => $totalPages,
        ],
        'meta'       => [
            'provincias' => $provinciasDisponibles,
            'bloques'    => $bloquesDisponibles,
            'camaras'    => ['Diputados', 'Senadores'],
            'kpis_congreso' => [
                'total_legisladores'    => count($todos),
                'diputados'             => $conteoDiputados,
                'senadores'             => $conteoSenadores,
                'asistencia_promedio'   => round($sumaAsistencia / $countTodos, 2),
                'lealtad_promedio'      => round($sumaLealtad / $countTodos, 2),
                'dolar_mep_referencia'  => 1280.00,
                'ultimo_anio_auditado'  => 2025,
            ],
        ],
    ]);
}

// ============================================================================
// RUTA 2: GET /api/legisladores/{id}/patrimonio (Serie Temporal Deflactada)
// ============================================================================
if ($method === 'GET' && preg_match('#^/legisladores/([^/]+)/patrimonio$#', $route, $matches)) {
    Security::enforceRateLimit($pdo, 'api_read', 120);

    $identifier = Security::sanitizeString($matches[1], 100);
    $legislador = findLegislador($pdo, $identifier);

    if ($legislador === null) {
        respondJson([
            'status'  => 'error',
            'code'    => 'LEGISLADOR_NOT_FOUND',
            'message' => 'No se encontró el legislador solicitado.',
        ], 404);
    }

    $id = (int)$legislador['id'];
    $evolucion = Algorithms::calcularEvolucionPatrimonial($id, $pdo);

    respondJson([
        'status'     => 'ok',
        'legislador' => [
            'id'              => $id,
            'id_congreso'     => $legislador['id_congreso'],
            'cuit'            => $legislador['cuit'],
            'nombre_completo' => $legislador['nombre_completo'],
            'camara'          => $legislador['camara'],
            'provincia'       => $legislador['provincia'],
            'bloque_actual'   => $legislador['bloque_actual'],
            'foto_url'        => $legislador['foto_url'],
        ],
        'patrimonio' => $evolucion,
    ]);
}

// ============================================================================
// RUTA 3: GET /api/legisladores/{id} (Ficha Completa: Radiografía del Legislador)
// ============================================================================
if ($method === 'GET' && preg_match('#^/legisladores/([^/]+)$#', $route, $matches)) {
    Security::enforceRateLimit($pdo, 'api_read', 120);

    $identifier = Security::sanitizeString($matches[1], 100);
    $legislador = findLegislador($pdo, $identifier);

    if ($legislador === null) {
        respondJson([
            'status'  => 'error',
            'code'    => 'LEGISLADOR_NOT_FOUND',
            'message' => 'No se encontró el legislador solicitado.',
        ], 404);
    }

    $id = (int)$legislador['id'];
    $filtroEje = Security::validateEnum(
        $_GET['eje'] ?? $_GET['eje_tematico'] ?? '',
        Algorithms::EJES_TODOS,
        ''
    );
    $votosPage  = Security::validateInt($_GET['votos_page'] ?? 1, 1, 100, 1);
    $votosLimit = Security::validateInt($_GET['votos_limit'] ?? 15, 1, 50, 15);

    $perfilTematico = Algorithms::calcularPerfilTematico($id, $pdo);
    $evolucionPatrimonial = Algorithms::calcularEvolucionPatrimonial($id, $pdo);
    $todosVotos = fetchVotosLegislador($pdo, $id, '');
    $votosFiltrados = $filtroEje !== '' ? fetchVotosLegislador($pdo, $id, $filtroEje) : $todosVotos;

    // Estadísticas globales de votos del legislador
    $afirmativos = 0;
    $negativos = 0;
    $abstenciones = 0;
    $ausentes = 0;
    foreach ($todosVotos as $v) {
        if ($v['sentido_voto'] === 'Afirmativo') {
            $afirmativos++;
        } elseif ($v['sentido_voto'] === 'Negativo') {
            $negativos++;
        } elseif ($v['sentido_voto'] === 'Abstencion') {
            $abstenciones++;
        } elseif ($v['sentido_voto'] === 'Ausente') {
            $ausentes++;
        }
    }

    $totalVotosFiltrados = count($votosFiltrados);
    $votosTotalPages = max(1, (int)ceil($totalVotosFiltrados / $votosLimit));
    $votosOffset = ($votosPage - 1) * $votosLimit;
    $votosPaginados = array_slice($votosFiltrados, $votosOffset, $votosLimit);

    respondJson([
        'status' => 'ok',
        'data'   => array_merge($legislador, [
            'kpis' => [
                'asistencia_historica'      => $legislador['asistencia_historica'],
                'lealtad_partidaria'        => $legislador['lealtad_partidaria'],
                'votos_totales_registrados' => count($todosVotos),
                'votos_afirmativos'         => $afirmativos,
                'votos_negativos'           => $negativos,
                'votos_abstenciones'        => $abstenciones,
                'votos_ausentes'            => $ausentes,
                'crecimiento_real_usd_pct'  => $evolucionPatrimonial['crecimiento_real_usd_pct'],
                'patrimonio_actual_usd'     => $evolucionPatrimonial['patrimonio_actual_usd'],
            ],
            'vectores'              => $perfilTematico['vectores'],
            'vectores_ponderados'   => $perfilTematico['vectores_ponderados'],
            'perfil_tematico'       => $perfilTematico,
            'patrimonio'            => $evolucionPatrimonial,
            'votaciones'            => $votosPaginados,
            'votaciones_pagination' => [
                'page'        => $votosPage,
                'limit'       => $votosLimit,
                'total'       => $totalVotosFiltrados,
                'total_pages' => $votosTotalPages,
                'filtro_eje'  => $filtroEje ?: 'Todos',
            ],
        ]),
    ]);
}

// ============================================================================
// RUTA 4: POST /api/match (Motor de Match Electoral por Similitud de Coseno)
// ============================================================================
if ($method === 'POST' && $route === '/match') {
    Security::enforceRateLimit($pdo, 'api_match', 30);

    $rawBody = file_get_contents('php://input') ?: '';
    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    if (empty($payload)) {
        Security::logAudit($pdo, 'INVALID_MATCH_PAYLOAD', '/api/match', 'WARN', ['reason' => 'Empty JSON payload']);
        respondJson([
            'status'  => 'error',
            'code'    => 'INVALID_PAYLOAD',
            'message' => 'Se requiere el vector de preferencias o las respuestas de la encuesta electoral.',
        ], 400);
    }

    $filtroCamara = Security::sanitizeString((string)($payload['camara'] ?? ''), 30);
    $filtroProvincia = Security::sanitizeString((string)($payload['provincia'] ?? ''), 80);

    $vectorUsuario = Algorithms::normalizarVectorEncuesta($payload);
    $legisladores = fetchAllLegisladores($pdo);

    $ranking = [];
    foreach ($legisladores as $leg) {
        if ($leg['estado'] !== 'Activo') {
            continue;
        }
        if ($filtroCamara !== '' && !matchesCamara((string)$leg['camara'], $filtroCamara)) {
            continue;
        }
        if ($filtroProvincia !== '' && strcasecmp($filtroProvincia, 'Todas') !== 0 && strcasecmp((string)$leg['provincia'], $filtroProvincia) !== 0) {
            continue;
        }

        $id = (int)$leg['id'];
        $perfil = Algorithms::calcularPerfilTematico($id, $pdo);
        $patrimonio = Algorithms::calcularEvolucionPatrimonial($id, $pdo);

        // Calcular similitud de coseno usando el vector temático del legislador
        $vectorLeg = $perfil['vectores'];
        $afinidadExacta = Algorithms::calcularMatchElectoral($vectorUsuario, $vectorLeg);
        $afinidadPonderada = Algorithms::calcularMatchElectoral($vectorUsuario, $perfil['vectores_ponderados']);

        // Combinación armónica (predominio del vector exacto + desempate por peso ideológico de leyes)
        $matchScore = round(($afinidadExacta * 0.75) + ($afinidadPonderada * 0.25), 2);
        if ($afinidadExacta > 0 && $matchScore == 0.0) {
            $matchScore = $afinidadExacta;
        }

        $ranking[] = [
            'id'                       => $id,
            'id_congreso'              => $leg['id_congreso'],
            'slug'                     => $leg['slug'],
            'nombre_completo'          => $leg['nombre_completo'],
            'camara'                   => $leg['camara'],
            'provincia'                => $leg['provincia'],
            'bloque_actual'            => $leg['bloque_actual'],
            'bloque'                   => $leg['bloque_actual'],
            'foto_url'                 => $leg['foto_url'],
            'asistencia_historica'     => $leg['asistencia_historica'],
            'lealtad_partidaria'       => $leg['lealtad_partidaria'],
            'match_pct'                => $afinidadExacta,
            'match_ponderado_pct'      => $matchScore,
            'vectores'                 => $vectorLeg,
            'vectores_ponderados'      => $perfil['vectores_ponderados'],
            'crecimiento_real_usd_pct' => $patrimonio['crecimiento_real_usd_pct'],
            'patrimonio_actual_usd'    => $patrimonio['patrimonio_actual_usd'],
        ];
    }

    // Ordenar descendente por match_pct (y match_ponderado_pct / asistencia_historica para desempate determinístico)
    usort($ranking, static function (array $a, array $b): int {
        if ($b['match_pct'] !== $a['match_pct']) {
            return $b['match_pct'] <=> $a['match_pct'];
        }
        if ($b['match_ponderado_pct'] !== $a['match_ponderado_pct']) {
            return $b['match_ponderado_pct'] <=> $a['match_ponderado_pct'];
        }
        return $b['asistencia_historica'] <=> $a['asistencia_historica'];
    });

    $top3 = array_slice($ranking, 0, 3);

    // Bottom 3 menos afines ordenados desde el menos afín (menor porcentaje primero)
    $ascRanking = $ranking;
    usort($ascRanking, static function (array $a, array $b): int {
        if ($a['match_pct'] !== $b['match_pct']) {
            return $a['match_pct'] <=> $b['match_pct'];
        }
        return $a['match_ponderado_pct'] <=> $b['match_ponderado_pct'];
    });
    $bottom3 = array_slice($ascRanking, 0, 3);

    Security::logAudit(
        $pdo,
        'ELECTORAL_MATCH_COMPUTED',
        '/api/match',
        'INFO',
        [
            'vector_usuario' => $vectorUsuario,
            'top_match_id'   => $top3[0]['id'] ?? null,
            'top_match_pct'  => $top3[0]['match_pct'] ?? 0,
        ],
        $rawBody
    );

    respondJson([
        'status'           => 'ok',
        'algoritmo'        => 'Similitud de Coseno (4 Dimensiones: Salud, Educacion, MercadoLibre, Proteccionismo)',
        'vector_usuario'   => $vectorUsuario,
        'top_afines'       => $top3,
        'top_3'            => $top3,
        'bottom_afines'    => $bottom3,
        'bottom_3'         => $bottom3,
        'total_evaluados'  => count($ranking),
        'ranking_completo' => $ranking,
    ]);
}

// ============================================================================
// RUTA 5: GET /api/og (Generador Dinámico de Tarjetas OpenGraph SVG 1200x630)
// ============================================================================
if ($method === 'GET' && $route === '/og') {
    Security::enforceRateLimit($pdo, 'api_read', 120);
    Security::sendSecurityHeaders('image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');

    $type = Security::validateEnum($_GET['type'] ?? 'perfil', ['perfil', 'match', 'home'], 'perfil');

    if ($type === 'match') {
        $topName = htmlspecialchars(Security::sanitizeString($_GET['top'] ?? 'Congreso Argentino', 60), ENT_QUOTES, 'UTF-8');
        $score   = Security::validateInt($_GET['score'] ?? 92, 0, 100, 92);
        $s       = Security::validateInt($_GET['s'] ?? 75, 0, 100, 75);
        $e       = Security::validateInt($_GET['e'] ?? 85, 0, 100, 85);
        $m       = Security::validateInt($_GET['m'] ?? 65, 0, 100, 65);
        $p       = Security::validateInt($_GET['p'] ?? 40, 0, 100, 40);

        echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 630" width="1200" height="630">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#0F172A"/>
      <stop offset="100%" stop-color="#1E3A8A"/>
    </linearGradient>
  </defs>
  <rect width="1200" height="630" fill="url(#bg)"/>
  <rect x="48" y="48" width="1104" height="534" rx="24" fill="#F8FAFC" stroke="#CBD5E1" stroke-width="2"/>
  <rect x="48" y="48" width="1104" height="88" rx="24" fill="#0F172A"/>
  <text x="90" y="102" font-family="Plus Jakarta Sans, Inter, sans-serif" font-size="28" font-weight="800" fill="#F8FAFC">DIPUDATA.COM — AUDITORÍA CÍVICA &amp; MATCH ELECTORAL</text>
  <text x="1110" y="102" text-anchor="end" font-family="JetBrains Mono, monospace" font-size="20" font-weight="700" fill="#38BDF8">CONGRESO ARGENTINO</text>

  <text x="90" y="210" font-family="Plus Jakarta Sans, Inter, sans-serif" font-size="22" font-weight="700" fill="#64748B" letter-spacing="2">MAYOR COINCIDENCIA EN VOTACIONES NOMINALES</text>
  <text x="90" y="275" font-family="Plus Jakarta Sans, Inter, sans-serif" font-size="52" font-weight="800" fill="#0F172A">{$topName}</text>

  <rect x="90" y="315" width="320" height="140" rx="16" fill="#EFF6FF" stroke="#BFDBFE" stroke-width="2"/>
  <text x="250" y="395" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="68" font-weight="800" fill="#1E3A8A">{$score}%</text>
  <text x="250" y="432" text-anchor="middle" font-family="Inter, sans-serif" font-size="18" font-weight="600" fill="#475569">SIMILITUD DE COSENO</text>

  <g transform="translate(470, 315)" font-family="Inter, sans-serif" font-size="18" font-weight="600" fill="#334155">
    <text x="0" y="24">Salud Pública ({$s}%)</text>
    <rect x="260" y="8" width="360" height="18" rx="9" fill="#E2E8F0"/>
    <rect x="260" y="8" width="{$s}%" style="max-width:360px" height="18" rx="9" fill="#1E3A8A"/>

    <text x="0" y="64">Educación y Ciencia ({$e}%)</text>
    <rect x="260" y="48" width="360" height="18" rx="9" fill="#E2E8F0"/>
    <rect x="260" y="48" width="{$e}%" style="max-width:360px" height="18" rx="9" fill="#15803D"/>

    <text x="0" y="104">Libre Mercado ({$m}%)</text>
    <rect x="260" y="88" width="360" height="18" rx="9" fill="#E2E8F0"/>
    <rect x="260" y="88" width="{$m}%" style="max-width:360px" height="18" rx="9" fill="#0284C7"/>

    <text x="0" y="144">Proteccionismo ({$p}%)</text>
    <rect x="260" y="128" width="360" height="18" rx="9" fill="#E2E8F0"/>
    <rect x="260" y="128" width="{$p}%" style="max-width:360px" height="18" rx="9" fill="#B45309"/>
  </g>

  <text x="90" y="540" font-family="Inter, sans-serif" font-size="20" font-weight="500" fill="#64748B">Descubrí qué legisladores votan como vos pensás en dipudata.com/match</text>
</svg>
SVG;
        exit;
    }

    // Modo Perfil de Legislador (o default institucional)
    $idParam = Security::sanitizeString($_GET['id'] ?? '1', 80);
    $leg = findLegislador($pdo, $idParam) ?? fetchAllLegisladores($pdo)[0];
    $id = (int)$leg['id'];
    $perfil = Algorithms::calcularPerfilTematico($id, $pdo);
    $patrimonio = Algorithms::calcularEvolucionPatrimonial($id, $pdo);

    $nombre    = htmlspecialchars((string)$leg['nombre_completo'], ENT_QUOTES, 'UTF-8');
    $camara    = htmlspecialchars((string)$leg['camara'], ENT_QUOTES, 'UTF-8');
    $bloque    = htmlspecialchars((string)$leg['bloque_actual'], ENT_QUOTES, 'UTF-8');
    $provincia = htmlspecialchars((string)$leg['provincia'], ENT_QUOTES, 'UTF-8');
    $asist     = number_format((float)$leg['asistencia_historica'], 1);
    $lealtad   = number_format((float)$leg['lealtad_partidaria'], 1);
    $usdActual = number_format((float)$patrimonio['patrimonio_actual_usd'], 0, ',', '.');
    $varUsd    = ($patrimonio['crecimiento_real_usd_pct'] >= 0 ? '+' : '') . number_format((float)$patrimonio['crecimiento_real_usd_pct'], 1) . '%';

    $vSalud = (int)round(($perfil['vectores']['Salud'] ?? 0) * 3.2);
    $vEduc  = (int)round(($perfil['vectores']['Educacion'] ?? 0) * 3.2);
    $vMerc  = (int)round(($perfil['vectores']['MercadoLibre'] ?? 0) * 3.2);
    $vProt  = (int)round(($perfil['vectores']['Proteccionismo'] ?? 0) * 3.2);

    echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 630" width="1200" height="630">
  <rect width="1200" height="630" fill="#F8FAFC"/>
  <rect x="36" y="36" width="1128" height="558" rx="20" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="3"/>
  <rect x="36" y="36" width="1128" height="84" rx="20" fill="#0F172A"/>
  <text x="76" y="88" font-family="Plus Jakarta Sans, Inter, sans-serif" font-size="26" font-weight="800" fill="#FFFFFF">DIPUDATA.COM — RADIOGRAFÍA PARLAMENTARIA &amp; PATRIMONIAL</text>
  <text x="1124" y="88" text-anchor="end" font-family="JetBrains Mono, monospace" font-size="20" font-weight="700" fill="#93C5FD">{$camara}</text>

  <text x="76" y="185" font-family="Plus Jakarta Sans, Inter, sans-serif" font-size="48" font-weight="800" fill="#0F172A">{$nombre}</text>
  <text x="76" y="228" font-family="Inter, sans-serif" font-size="24" font-weight="600" fill="#1E3A8A">Bloque {$bloque} · {$provincia}</text>

  <g transform="translate(76, 265)">
    <rect x="0" y="0" width="320" height="125" rx="14" fill="#F8FAFC" stroke="#CBD5E1" stroke-width="2"/>
    <text x="24" y="42" font-family="Inter, sans-serif" font-size="16" font-weight="700" fill="#64748B">ASISTENCIA HISTÓRICA</text>
    <text x="24" y="96" font-family="JetBrains Mono, monospace" font-size="44" font-weight="800" fill="#15803D">{$asist}%</text>

    <rect x="350" y="0" width="320" height="125" rx="14" fill="#F8FAFC" stroke="#CBD5E1" stroke-width="2"/>
    <text x="374" y="42" font-family="Inter, sans-serif" font-size="16" font-weight="700" fill="#64748B">LEALTAD PARTIDARIA</text>
    <text x="374" y="96" font-family="JetBrains Mono, monospace" font-size="44" font-weight="800" fill="#1E3A8A">{$lealtad}%</text>

    <rect x="700" y="0" width="348" height="125" rx="14" fill="#F8FAFC" stroke="#CBD5E1" stroke-width="2"/>
    <text x="724" y="42" font-family="Inter, sans-serif" font-size="16" font-weight="700" fill="#64748B">PATRIMONIO DDJJ (USD MEP)</text>
    <text x="724" y="92" font-family="JetBrains Mono, monospace" font-size="34" font-weight="800" fill="#0F172A">USD {$usdActual} ({$varUsd})</text>
  </g>

  <g transform="translate(76, 430)" font-family="Inter, sans-serif" font-size="17" font-weight="700" fill="#334155">
    <text x="0" y="20">Salud</text>
    <rect x="140" y="4" width="320" height="18" rx="9" fill="#E2E8F0"/>
    <rect x="140" y="4" width="{$vSalud}" height="18" rx="9" fill="#1E3A8A"/>

    <text x="0" y="60">Educación</text>
    <rect x="140" y="44" width="320" height="18" rx="9" fill="#E2E8F0"/>
    <rect x="140" y="44" width="{$vEduc}" height="18" rx="9" fill="#15803D"/>

    <text x="530" y="20">Libre Mercado</text>
    <rect x="690" y="4" width="320" height="18" rx="9" fill="#E2E8F0"/>
    <rect x="690" y="4" width="{$vMerc}" height="18" rx="9" fill="#0284C7"/>

    <text x="530" y="60">Proteccionismo</text>
    <rect x="690" y="44" width="320" height="18" rx="9" fill="#E2E8F0"/>
    <rect x="690" y="44" width="{$vProt}" height="18" rx="9" fill="#B45309"/>
  </g>

  <text x="76" y="558" font-family="Inter, sans-serif" font-size="18" font-weight="500" fill="#64748B">Fuentes oficiales: HCDN · Honorable Senado de la Nación · Oficina Anticorrupción (DDJJ)</text>
</svg>
SVG;
    exit;
}

// ============================================================================
// RUTA 6: GET /api/stats (Estadísticas Generales del Congreso e Indicadores)
// ============================================================================
if ($method === 'GET' && $route === '/stats') {
    Security::enforceRateLimit($pdo, 'api_read', 120);

    $legisladores = fetchAllLegisladores($pdo);
    $store = Config::getMemoryStore();

    $indicadores = array_values($store['indicadores']);
    if ($pdo !== null) {
        try {
            $stmt = $pdo->query("SELECT anio, ipc_interanual, dolar_mep_cierre FROM indicadores_economicos ORDER BY anio ASC");
            $dbInd = $stmt ? $stmt->fetchAll() : [];
            if (!empty($dbInd)) {
                $indicadores = $dbInd;
            }
        } catch (Throwable) {
            // Mantiene indicadores en memoria
        }
    }

    $porBloque = [];
    $porProvincia = [];
    foreach ($legisladores as $leg) {
        $b = $leg['bloque_actual'];
        $p = $leg['provincia'];
        $porBloque[$b] = ($porBloque[$b] ?? 0) + 1;
        $porProvincia[$p] = ($porProvincia[$p] ?? 0) + 1;
    }
    arsort($porBloque);
    arsort($porProvincia);

    respondJson([
        'status' => 'ok',
        'data'   => [
            'total_legisladores'     => count($legisladores),
            'total_leyes_auditadas'  => count($store['leyes']),
            'distribucion_bloques'   => $porBloque,
            'distribucion_provincias'=> $porProvincia,
            'indicadores_economicos' => $indicadores,
            'leyes_auditadas'        => array_values($store['leyes']),
        ],
    ]);
}

// Ruta no encontrada
respondJson([
    'status'  => 'error',
    'code'    => 'ENDPOINT_NOT_FOUND',
    'message' => "El endpoint solicitado ({$method} {$route}) no existe en la API de LegisData.",
], 404);
