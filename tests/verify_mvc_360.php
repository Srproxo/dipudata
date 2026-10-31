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

$fallaron = count(array_filter($tests, fn($t) => !$t['pass']));
echo "\n------------------------------------------------------------------------------\n";
echo sprintf(" RESUMEN FASE 2: %d PASARON | %d FALLARON\n", count($tests) - $fallaron, $fallaron);
echo "------------------------------------------------------------------------------\n";

exit($fallaron === 0 ? 0 : 1);
