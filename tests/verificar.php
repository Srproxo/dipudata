<?php
declare(strict_types=1);
/** Controles de integridad. Uso: php tests/verificar.php  (sale con código 1 si algo falla) */
require_once __DIR__ . '/../app/bootstrap.php';
use App\Datos;

$fallos = [];
function ok(bool $c, string $m): void { global $fallos; echo ($c ? "  ok    " : "  FALLA ") . $m . "\n"; if (!$c) $fallos[] = $m; }

$dip = Datos::legisladores('Diputados'); $sen = Datos::legisladores('Senadores'); $todos = Datos::legisladores();
ok(count($dip) === 257, 'hay 257 diputados en ejercicio (hay ' . count($dip) . ')');
ok(count($sen) > 60 && count($sen) <= 72, 'senadores plausibles, máximo 72 (hay ' . count($sen) . ')');
ok(count(array_unique(array_map(fn($l) => $l['camara'] . '/' . $l['slug'], $todos))) === count($todos), 'slugs únicos por cámara');

$prohibidos = ['cuit', 'lealtad', 'asistencia', 'patrimonio', 'ddjj', 'nepotismo', 'biografia', 'votaciones'];
$mal = [];
foreach ($todos as $l) { foreach (array_keys($l) as $k) { foreach ($prohibidos as $p) { if (stripos((string)$k, $p) !== false) $mal[] = "{$l['nombre']}:{$k}"; } } }
ok(!$mal, 'ningún legislador trae campos sin fuente (cuit, lealtad, asistencia, patrimonio…)');
ok(!array_filter($todos, fn($l) => empty($l['fuentes'])), 'todo legislador declara su fuente');
ok(!array_filter($todos, fn($l) => empty($l['bloque']) || empty($l['provincia'])), 'todo legislador tiene bloque y provincia');
ok(!array_filter(Datos::provincias(), fn($p) => $p['senadores'] > 3), 'ninguna provincia supera 3 senadores');
ok(array_sum(array_column(Datos::bloques('Diputados'), 'cantidad')) === count($dip), 'la suma de bloques de Diputados coincide con el padrón');
ok(array_sum(array_column(Datos::bloques('Senadores'), 'cantidad')) === count($sen), 'la suma de bloques del Senado coincide con el padrón');
ok(!array_filter($todos, fn($l) => $l['foto'] && !str_starts_with($l['foto'], 'https://api.argentinadatos.com/')), 'las fotos solo vienen del host permitido por la CSP');

$cod = (string)shell_exec('grep -rl "legisladores_master" ' . escapeshellarg(__DIR__ . '/../app') . ' ' . escapeshellarg(__DIR__ . '/../api') . ' ' . escapeshellarg(__DIR__ . '/../mvc.php') . ' 2>/dev/null');
ok(trim($cod) === '', 'ningún código PHP del sitio lee legisladores_master.json');

echo $fallos ? "\n" . count($fallos) . " controles fallaron\n" : "\nTodo en orden\n";
exit($fallos ? 1 : 0);
