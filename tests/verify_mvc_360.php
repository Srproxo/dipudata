<?php
declare(strict_types=1);

/**
 * Suite de Verificación E2E para DipuData / Match Electoral 360 (Arquitectura MVC)
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
echo " DIPUDATA / MATCH ELECTORAL 360 — SUITE DE VERIFICACIÓN MVC\n";
echo "==============================================================================\n\n";

// 1. Modelos
$m = new LegisladorModel();
$all = $m->all();
assert_test("LegisladorModel: carga de legisladores desde dataset cívico", count($all) >= 8, "total=" . count($all));

$first = $m->findBySlug('martin-alexis-menem');
assert_test("LegisladorModel: búsqueda por slug ('martin-alexis-menem')", $first !== null && $first['nombre'] === 'Martín Alexis Menem');

$perfil = $first ? $m->perfilTematico($first['id_recinto']) : [];
assert_test("LegisladorModel: cálculo de perfil en 4 ejes temáticos", count($perfil) === 4 && isset($perfil['Salud Pública'], $perfil['Apertura Comercial']));

$ddjj = $first ? $m->patrimonio($first['cuit']) : [];
assert_test("LegisladorModel: historial de declaraciones juradas por CUIT", count($ddjj) >= 3, "años=" . count($ddjj));

$resumen = $m->resumenPatrimonial($ddjj);
assert_test("LegisladorModel: cálculo de variación patrimonial real ajustada por IPC", isset($resumen['crecimiento_real_pct']));

// 2. MatchEngineController
$engine = new MatchEngineController($m);
$respuestasProMercado = [
    'q1' => 1, 'q2' => 1, 'q3' => 1, 'q4' => 5, // Baja Salud/Educación
    'q5' => 1, 'q6' => 1, 'q7' => 5, 'q8' => 5  // Alta Apertura Comercial
];
$resMatch = $engine->calcular($respuestasProMercado);
assert_test("MatchEngineController: cálculo de vector de usuario en 4 ejes", count($resMatch['vector_usuario']) === 4);
assert_test("MatchEngineController: ranking Top 3 de afinidad con Similitud del Coseno", count($resMatch['top3']) === 3);
assert_test("MatchEngineController: afinidad entre 0 y 100%", $resMatch['top3'][0]['afinidad'] >= 0 && $resMatch['top3'][0]['afinidad'] <= 100, "top1=" . $resMatch['top3'][0]['afinidad'] . "%");

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
assert_test("MVC Router: vista de perfil (/diputado/martin-alexis-menem) renderizada", 
    str_contains($perfilHtml, 'Martín Alexis Menem') && 
    str_contains($perfilHtml, 'Evolución patrimonial ajustada por inflación') &&
    str_contains($perfilHtml, 'Apoyo por eje temático')
);

$_SERVER['REQUEST_URI'] = '/match-360';
ob_start();
require dirname(__DIR__) . '/mvc.php';
$matchHtml = ob_get_clean();
assert_test("MVC Router: vista de Match Electoral 360 (/match-360) renderizada", 
    str_contains($matchHtml, 'Match Electoral 360') && 
    str_contains($matchHtml, 'chart.js') &&
    str_contains($matchHtml, 'canvas id="radar"')
);

$_SERVER['REQUEST_URI'] = '/blog';
ob_start();
require dirname(__DIR__) . '/mvc.php';
$hubHtml = ob_get_clean();
assert_test("MVC Router: Hub de Blog (/blog) renderizado", str_contains($hubHtml, 'Periodismo de datos legislativos'));

$_SERVER['REQUEST_URI'] = '/blog/educacion-financiera/como-auditar-patrimonio-legisladores-inflacion-ipc';
ob_start();
require dirname(__DIR__) . '/mvc.php';
$spokeHtml = ob_get_clean();
assert_test("MVC Router: Spoke con auto-linking (/blog/.../...) renderizado", 
    str_contains($spokeHtml, 'Declaraciones Juradas') && 
    str_contains($spokeHtml, 'href="/diputado/')
);

$fallaron = count(array_filter($tests, fn($t) => !$t['pass']));
echo "\n------------------------------------------------------------------------------\n";
echo sprintf(" RESUMEN: %d PASARON | %d FALLARON\n", count($tests) - $fallaron, $fallaron);
echo "------------------------------------------------------------------------------\n";

exit($fallaron === 0 ? 0 : 1);
