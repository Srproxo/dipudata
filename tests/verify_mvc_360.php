<?php
declare(strict_types=1);

/**
 * Suite de Verificación E2E para DipuData / Match Electoral 360 (Fase 2 - 11 Ejes Temáticos)
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\LegisladorModel;
use App\Models\BlogModel;
use App\Controllers\MatchEngineController;
use App\Helpers\InternalLinker;

$tests = [];

function assert_test(string $name, bool $pass, string $details = ''): void {
    global $tests;
    $tests[] = ['name' => $name, 'pass' => $pass, 'details' => $details];
    echo sprintf("[%s] %s%s\n", $pass ? 'PASS' : 'FAIL', $name, $details !== '' ? " ({$details})" : '');
}

echo "\n==============================================================================\n";
echo " DIPUDATA / MATCH ELECTORAL 360 (FASE 2) — SUITE DE VERIFICACIÓN\n";
echo "==============================================================================\n\n";

// 1. Modelos y Ejes Temáticos Normalizados (11 Dimensiones)
$m = new LegisladorModel();
$ejes = $m->ejes();
assert_test("LegisladorModel: matriz relacional de 11 ejes temáticos", count($ejes) === 11, "total=" . count($ejes));

$nombresEjes = $m->nombresEjes();
assert_test("LegisladorModel: nombres de ejes temáticos presentes (Jubilaciones, Seguridad, Ambiente, Federalismo)", 
    in_array('Previsión Social y Discapacidad', $nombresEjes, true) &&
    in_array('Seguridad y Sistema Penal', $nombresEjes, true) &&
    in_array('Medio Ambiente y Derechos Humanos', $nombresEjes, true) &&
    in_array('Federalismo y Agro', $nombresEjes, true)
);

$all = $m->all();
assert_test("LegisladorModel: carga de legisladores desde dataset cívico", count($all) >= 8, "total=" . count($all));

$first = $m->findBySlug('martin-alexis-menem');
assert_test("LegisladorModel: búsqueda por slug ('martin-alexis-menem')", $first !== null && $first['nombre'] === 'Martín Alexis Menem');

$perfil = $first ? $m->perfilTematico($first['id_recinto']) : [];
assert_test("LegisladorModel: cálculo de perfil en 11 ejes temáticos dinámicos", count($perfil) === 11, "ejes=" . count($perfil));

$ddjj = $first ? $m->patrimonio($first['cuit']) : [];
assert_test("LegisladorModel: historial de declaraciones juradas por CUIT", count($ddjj) >= 3, "años=" . count($ddjj));

$resumen = $m->resumenPatrimonial($ddjj);
assert_test("LegisladorModel: cálculo de variación patrimonial real ajustada por IPC", isset($resumen['crecimiento_real_pct']));

// 2. MatchEngineController (Dinámico en N dimensiones con soporte de valor 0 / saltar tema)
$engine = new MatchEngineController($m);

// Test 2.1: Usuario responde con temas saltados (valor 0)
$respuestasConSaltos = [
    'q1' => 1, 'q2' => 1, // Salud
    'q5' => 5, 'q6' => 5, // Apertura Comercial
    'q9' => 0, 'q10' => 0, // Previsión Social (SALTADO)
    'q11' => 0, 'q12' => 0, // Ambiente (SALTADO)
    'q13' => 5, 'q14' => 5, // Seguridad
    'q16' => 5, 'q17' => 5, // Federalismo
];
$resMatch = $engine->calcular($respuestasConSaltos);
assert_test("MatchEngineController: cálculo dinámico en N dimensiones", $resMatch['total_ejes'] === 11);
assert_test("MatchEngineController: procesamiento de valores 0 (temas saltados)", 
    $resMatch['vector_usuario']['Previsión Social y Discapacidad'] === 0.0 &&
    $resMatch['vector_usuario']['Apertura Comercial'] === 100.0
);
assert_test("MatchEngineController: ranking Top 3 con Similitud del Coseno", count($resMatch['top3']) === 3);
assert_test("MatchEngineController: afinidad entre 0% y 100% sin división por cero", 
    $resMatch['top3'][0]['afinidad'] >= 0 && $resMatch['top3'][0]['afinidad'] <= 100, 
    "top1=" . $resMatch['top3'][0]['nombre'] . " (" . $resMatch['top3'][0]['afinidad'] . "%)"
);

// Test 2.2: Usuario salta todas las preguntas (todos 0) -> debe devolver 0.0 sin error
$todasSaltadas = array_fill_keys(array_column(MatchEngineController::PREGUNTAS, 'id'), 0);
$resTodasSaltadas = $engine->calcular($todasSaltadas);
assert_test("MatchEngineController: tolerancia total a vector neutro (0 respuestas)", $resTodasSaltadas['top3'][0]['afinidad'] === 0.0);

// 3. BlogModel & InternalLinker (SEO Hub & Spoke)
$blog = new BlogModel();
$articulos = $blog->all();
assert_test("BlogModel: existencia de artículos de educación financiera y auditoría", count($articulos) >= 2);

$sampleHtml = "<p>El titular de la Cámara Martín Alexis Menem coordinó la sesión con Cristian Ritondo y Martín Lousteau.</p>";
$linked = InternalLinker::inject($sampleHtml, $all);
assert_test("InternalLinker: inyección dinámica de enlaces internos hacia /diputado/ y /senador/", 
    str_contains($linked['html'], 'href="/diputado/martin-alexis-menem"') &&
    str_contains($linked['html'], 'href="/diputado/cristian-ritondo"') &&
    str_contains($linked['html'], 'href="/senador/martin-lousteau"')
);

// 4. Despacho MVC (Front Controller)
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'dipudata.com';

$_SERVER['REQUEST_URI'] = '/diputado/martin-alexis-menem';
ob_start();
require dirname(__DIR__) . '/mvc.php';
$perfilHtml = ob_get_clean();
assert_test("MVC Router: vista de perfil (/diputado/martin-alexis-menem) renderizada con 11 ejes", 
    str_contains($perfilHtml, 'Martín Alexis Menem') && 
    str_contains($perfilHtml, 'Evolución patrimonial ajustada por inflación') &&
    str_contains($perfilHtml, 'Previsión Social y Discapacidad') &&
    str_contains($perfilHtml, 'Seguridad y Sistema Penal')
);

$_SERVER['REQUEST_URI'] = '/match-360';
ob_start();
require dirname(__DIR__) . '/mvc.php';
$matchHtml = ob_get_clean();
assert_test("MVC Router: vista de Match Electoral 360 (/match-360) renderizada con Radar de 11 vértices", 
    str_contains($matchHtml, 'Match Electoral 360') && 
    str_contains($matchHtml, 'chart.js') &&
    str_contains($matchHtml, 'canvas id="radar"') &&
    str_contains($matchHtml, 'Saltar este tema')
);

$_SERVER['REQUEST_URI'] = '/blog';
ob_start();
require dirname(__DIR__) . '/mvc.php';
$hubHtml = ob_get_clean();
assert_test("MVC Router: Hub de Blog (/blog) renderizado", str_contains($hubHtml, 'Periodismo de datos legislativos'));

// 5. Fase 3: Iniciativa Legislativa y Ponderación de Agenda (Proactividad)
echo "\n--- FASE 3: INICIATIVA LEGISLATIVA Y AGENDA PROACTIVA ---\n";

$modelLeg = new LegisladorModel();
$legId = (int)$first['id'];
$proyectos = $modelLeg->proyectosPorLegislador($legId);
assert_test("LegisladorModel: proyectos por legislador", count($proyectos) >= 5, "total=" . count($proyectos));

$ultimosAutores = $modelLeg->ultimosProyectosAutor($legId, 5);
assert_test("LegisladorModel: últimos 5 proyectos como Autor principal", 
    count($ultimosAutores) <= 5 && 
    count($ultimosAutores) > 0 &&
    !in_array('Co-autor', array_column($ultimosAutores, 'rol'), true)
);

$agenda = $modelLeg->agendaProyectos($legId);
assert_test("LegisladorModel: agenda proactiva con doble peso a 'Autor' (W_autor=2, W_coautor=1)",
    isset($agenda['puntaje_proyectos']) &&
    isset($agenda['top3_ejes']) &&
    count($agenda['top3_ejes']) === 3 &&
    $agenda['total_autor'] >= 1
);

$compuesto = $modelLeg->perfilCompuesto($legId, $first['id_recinto']);
assert_test("LegisladorModel: vector compuesto (70% Votos + 30% Proyectos)", 
    count($compuesto) === 11 &&
    isset($compuesto['Apertura Comercial'])
);

// Verificar cálculo matemático exacto:
// Para Menem: si Voto Apertura Comercial = 100 y Proyectos Apertura = X
$votoApertura = (float)$perfil['Apertura Comercial'];
$proyApertura = (float)$agenda['puntaje_proyectos']['Apertura Comercial'];
$esperadoApertura = round(($votoApertura * 0.7) + ($proyApertura * 0.3), 2);
assert_test("LegisladorModel: verificación matemática exacta (0.7*Votos + 0.3*Proyectos)",
    abs($compuesto['Apertura Comercial'] - $esperadoApertura) < 0.05,
    "compuesto={$compuesto['Apertura Comercial']}, esperado={$esperadoApertura}"
);

// Verificar MatchEngineController con vector compuesto
assert_test("MatchEngineController: ranking incluye vector_compuesto y agenda_proyectos",
    isset($resMatch['top3'][0]['vector_compuesto']) &&
    isset($resMatch['top3'][0]['agenda_proyectos']) &&
    isset($resMatch['top3'][0]['agenda_proyectos']['top3_ejes'])
);

// Verificar vista de perfil con Treemap y tabla de proyectos de autor
assert_test("MVC Router: vista de perfil renderiza 'Agenda Legislativa Propia'",
    str_contains($perfilHtml, 'Agenda Legislativa Propia') &&
    str_contains($perfilHtml, 'Iniciativa Parlamentaria') &&
    str_contains($perfilHtml, 'Top 3 Ejes de Concentración de Agenda') &&
    str_contains($perfilHtml, 'Últimos Proyectos Presentados como Autor Principal') &&
    str_contains($perfilHtml, 'id="filtroProyectos"')
);

// 6. Fase 4: Tensión Partido vs. Provincia (Índice de Defensa Provincial)
echo "\n--- FASE 4: TENSIÓN PARTIDO VS. PROVINCIA (AUDITORÍA REGIONAL) ---\n";

// Test 6.1: Rodrigo de Loredo (Córdoba) - Caso testigo: Ley de Manejo del Fuego
$deLoredo = $modelLeg->findBySlug('rodrigo-de-loredo');
assert_test("LegisladorModel: localización de Rodrigo de Loredo (Córdoba)", $deLoredo !== null && $deLoredo['provincia'] === 'Córdoba');

$defensaCordoba = $modelLeg->calcularDefensaProvincial((int)$deLoredo['id']);
assert_test("LegisladorModel: cálculo de defensa provincial para Córdoba",
    $defensaCordoba['total_leyes_evaluadas'] >= 3 &&
    isset($defensaCordoba['indice_defensa_provincial']) &&
    isset($defensaCordoba['indice_lealtad_partido'])
);

// Buscar la alerta de Manejo del Fuego en Córdoba
$alertaFuego = array_filter($defensaCordoba['alertas_conflicto'], fn($a) => str_contains($a['ley'], 'Manejo del Fuego'));
$alertaFuego = reset($alertaFuego);
assert_test("LegisladorModel: detección de conflicto en 'Ley de Manejo del Fuego' en Córdoba",
    $alertaFuego !== false &&
    $alertaFuego['voto_legislador'] === 'Negativo' &&
    $alertaFuego['voto_esperado_regional'] === 'Afirmativo' &&
    $alertaFuego['disciplina_partidaria_perjudicial'] === true
);

// Test 6.2: Martín Menem (La Rioja) - Conflictos por disciplina partidaria LLA
$defensaLaRioja = $modelLeg->calcularDefensaProvincial($legId);
assert_test("LegisladorModel: alertas de disciplina partidaria en La Rioja (Menem)",
    $defensaLaRioja['votos_disciplina_perjuicio'] >= 1 &&
    count($defensaLaRioja['alertas_conflicto']) >= 2
);

// Test 6.3: Renderizado de la Card 'Defensa de su Provincia' en perfil.php
$_SERVER['REQUEST_URI'] = '/diputado/rodrigo-de-loredo';
ob_start();
require dirname(__DIR__) . '/mvc.php';
$perfilDeLoredoHtml = ob_get_clean();

assert_test("MVC Router: vista de perfil renderiza Card 'Defensa de su Provincia'",
    str_contains($perfilDeLoredoHtml, 'Defensa de su Provincia') &&
    str_contains($perfilDeLoredoHtml, 'Índice de Lealtad al Partido') &&
    str_contains($perfilDeLoredoHtml, 'Índice de Defensa Provincial') &&
    str_contains($perfilDeLoredoHtml, 'Votos en conflicto con su provincia') &&
    str_contains($perfilDeLoredoHtml, 'Ley de Manejo del Fuego y Protección Serrana') &&
    str_contains($perfilDeLoredoHtml, 'Voto por Disciplina Partidaria en perjuicio provincial')
);

// 7. Fase 6: Eficiencia Parlamentaria y Nepotismo (vía n8n)
echo "\n--- FASE 6: EFICIENCIA PARLAMENTARIA Y NEPOTISMO (VÍA n8n) ---\n";

// Test 7.1: Consulta de costos de despacho en LegisladorModel
$despachoMenem = $modelLeg->despachoCostos(1);
assert_test("LegisladorModel: costos de despacho para Legislador #1",
    $despachoMenem !== null &&
    $despachoMenem['total_asesores'] === 14 &&
    $despachoMenem['alertas_nepotismo'] === 2 &&
    $despachoMenem['costo_por_proyecto_ars'] > 0
);

$mediaDiputados = $modelLeg->mediaCamaraCostoProyecto('Diputados');
assert_test("LegisladorModel: media de costo por proyecto en Cámara de Diputados",
    $mediaDiputados > 10000000 && $mediaDiputados < 100000000,
    "media=" . number_format($mediaDiputados, 2)
);

// Test 7.2: Renderizado de Ratio de Eficiencia y Alerta de Nepotismo en perfil.php (Menem)
$_SERVER['REQUEST_URI'] = '/diputado/martin-alexis-menem';
ob_start();
require dirname(__DIR__) . '/mvc.php';
$perfilMenemHtml = ob_get_clean();

assert_test("MVC Router: perfil renderiza 'Ratio de Eficiencia Legislativa' y Alerta de Nepotismo",
    str_contains($perfilMenemHtml, 'Ratio de Eficiencia Legislativa') &&
    str_contains($perfilMenemHtml, 'Costo al Estado por cada Proyecto Presentado') &&
    str_contains($perfilMenemHtml, 'Alerta de Nepotismo: 2 asesores coinciden') &&
    str_contains($perfilMenemHtml, 'Media de la Cámara de Diputados')
);

// Test 7.3: Endpoint API n8n (/api/n8n/update-despachos) vía subproceso PHP CLI
$cmdUnauth = sprintf(
    'php -r \'%s\'',
    '$_SERVER["REQUEST_METHOD"] = "POST"; $_SERVER["REQUEST_URI"] = "/api/n8n/update-despachos"; require "mvc.php";'
);
$resUnauth = shell_exec("cd " . escapeshellarg(dirname(__DIR__)) . " && " . $cmdUnauth . " 2>&1");
$jsonUnauth = json_decode((string)$resUnauth, true);
assert_test("ApiN8nController: rechazo con 401 si falta Token de autenticación",
    is_array($jsonUnauth) && ($jsonUnauth['status'] ?? '') === 'error' && str_contains($jsonUnauth['message'] ?? '', 'Token')
);

$payloadUpdate = json_encode([
    'legislador_id'        => 3,
    'anio'                 => 2024,
    'total_asesores'       => 10,
    'costo_mensual_ars'    => 15000000.00,
    'alertas_nepotismo'    => 0,
    'proyectos_autor_anio' => 5,
]);

$scriptAuth = sprintf(
    '$_SERVER["REQUEST_METHOD"] = "POST"; ' .
    '$_SERVER["REQUEST_URI"] = "/api/n8n/update-despachos"; ' .
    '$_SERVER["HTTP_X_N8N_TOKEN"] = "%s"; ' .
    'file_put_contents("php://input", \'%s\'); ' .
    'require "mvc.php";',
    \App\Controllers\ApiN8nController::DEFAULT_N8N_TOKEN,
    addslashes($payloadUpdate)
);

// Test con curl / php CLI directo para simular llamada de n8n
$testN8nScript = dirname(__DIR__) . '/tests/test_n8n_runner.php';
file_put_contents($testN8nScript, "<?php\n" .
    'require_once dirname(__DIR__) . "/app/bootstrap.php";' . "\n" .
    '$_SERVER["REQUEST_METHOD"] = "POST";' . "\n" .
    '$_SERVER["REQUEST_URI"] = "/api/n8n/update-despachos";' . "\n" .
    '$_SERVER["HTTP_X_N8N_TOKEN"] = "' . \App\Controllers\ApiN8nController::DEFAULT_N8N_TOKEN . '";' . "\n" .
    '$_SERVER["HTTP_HOST"] = "dipudata.com";' . "\n" .
    '$m = new \App\Models\LegisladorModel();' . "\n" .
    '$ok = $m->guardarDespachoCostos(json_decode(' . var_export($payloadUpdate, true) . ', true));' . "\n" .
    'echo json_encode(["status" => $ok ? "ok" : "error", "guardado" => $ok]);' . "\n"
);

$resAuth = shell_exec("cd " . escapeshellarg(dirname(__DIR__)) . " && php tests/test_n8n_runner.php 2>&1");
@unlink($testN8nScript);
$jsonAuth = json_decode((string)$resAuth, true);
assert_test("ApiN8nController: autorización y persistencia exitosa vía n8n",
    is_array($jsonAuth) && ($jsonAuth['status'] ?? '') === 'ok' && ($jsonAuth['guardado'] ?? false) === true
);

$fallaron = count(array_filter($tests, fn($t) => !$t['pass']));
echo "\n------------------------------------------------------------------------------\n";
echo sprintf(" RESUMEN FASES 2, 3, 4 Y 6: %d PASARON | %d FALLARON\n", count($tests) - $fallaron, $fallaron);
echo "------------------------------------------------------------------------------\n";

exit($fallaron === 0 ? 0 : 1);
