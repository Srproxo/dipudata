<?php
declare(strict_types=1);
/** Controles de integridad. Uso: php tests/verificar.php  (sale con código 1 si algo falla) */
require_once __DIR__ . '/../app/bootstrap.php';
use App\Datos;

$fallos = [];
function ok(bool $c, string $m): void { global $fallos; echo ($c ? "  ok    " : "  FALLA ") . $m . "\n"; if (!$c) $fallos[] = $m; }

$dip = Datos::legisladores('Diputados'); $sen = Datos::legisladores('Senadores'); $todos = Datos::legisladores();
ok(count($dip) === 257, 'hay 257 diputados en ejercicio (hay ' . count($dip) . ')');
ok(count($sen) === 72, 'hay 72 senadores en ejercicio (hay ' . count($sen) . ')');
ok(count(array_unique(array_map(fn($l) => $l['camara'] . '/' . $l['slug'], $todos))) === count($todos), 'slugs únicos por cámara');

$prohibidos = ['cuit', 'lealtad', 'asistencia', 'patrimonio', 'ddjj', 'nepotismo', 'biografia', 'votaciones'];
$mal = [];
foreach ($todos as $l) { foreach (array_keys($l) as $k) { foreach ($prohibidos as $p) { if (stripos((string)$k, $p) !== false) $mal[] = "{$l['nombre']}:{$k}"; } } }
ok(!$mal, 'ningún legislador trae campos sin fuente (cuit, lealtad, asistencia, patrimonio…)');
ok(!array_filter($todos, fn($l) => empty($l['fuentes'])), 'todo legislador declara su fuente');
ok(!array_filter($todos, fn($l) => empty($l['bloque']) || empty($l['provincia'])), 'todo legislador tiene bloque y provincia');
ok(!array_filter(Datos::provincias(), fn($p) => $p['senadores'] !== 3), 'cada provincia tiene exactamente 3 senadores');
ok(!array_filter($todos, fn($l) => $l['verificado'] !== true), 'todo el padrón proviene de un registro oficial');
ok(!array_filter($sen, fn($l) => in_array($l['nombre'], ['Martín Lousteau', 'Guadalupe Tagliaferri'], true)), 'ningún senador con mandato terminado figura en ejercicio');
$idx = Datos::docsIndice();
ok(count($idx) === 10, 'hay 10 tipos de registros del Senado');
$malDocs = [];
foreach ($idx as $tipo => $t) { $n = 0; foreach (array_keys($t['anios']) as $a) { $n += count(Datos::docs($tipo, (string)$a)); } if ($n !== $t['total']) $malDocs[] = $tipo; }
ok(!$malDocs, 'el total de cada registro coincide con la suma de sus años');
ok(count(Datos::comisionesSenado()) === 49, 'hay 49 comisiones del Senado');
$urlsMal = 0;
foreach ($idx as $tipo => $t) { foreach (Datos::docs($tipo, (string)array_key_first($t['anios'])) as $it) { foreach (array_merge([$it['u'] ?? ''], array_values((array)($it['x'] ?? []))) as $u) { if ($u !== '' && !str_starts_with((string)$u, 'https://') && !is_numeric($u)) $urlsMal++; } } }
ok($urlsMal === 0, 'todo enlace a documentos usa https');
ok(array_sum(array_column(Datos::bloques('Diputados'), 'cantidad')) === count($dip), 'la suma de bloques de Diputados coincide con el padrón');
ok(array_sum(array_column(Datos::bloques('Senadores'), 'cantidad')) === count($sen), 'la suma de bloques del Senado coincide con el padrón');
ok(!array_filter($todos, fn($l) => $l['foto'] && !preg_match('#^https://(api\.argentinadatos\.com|www\.senado\.gob\.ar)/#', $l['foto'])), 'las fotos solo vienen de hosts permitidos por la CSP');

$cod = (string)shell_exec('grep -rl "legisladores_master" ' . escapeshellarg(__DIR__ . '/../app') . ' ' . escapeshellarg(__DIR__ . '/../api') . ' ' . escapeshellarg(__DIR__ . '/../mvc.php') . ' 2>/dev/null');
ok(trim($cod) === '', 'ningún código PHP del sitio lee legisladores_master.json');

ok(array_sum(array_column(Datos::interbloques(), 'cantidad')) === 42, 'los interbloques suman 42 diputados, como informa la HCDN');
ok(count(array_filter($dip, fn($l) => !empty($l['hcdn_id']))) === 257, 'los 257 diputados tienen su ID oficial de la HCDN');
ok(count(array_filter($dip, fn($l) => !empty($l['trayectoria']))) === 257, 'los 257 diputados tienen trayectoria registrada');
ok(!array_filter($dip, fn($l) => isset($l['mandatos'])), 'no se publica un conteo de mandatos (el histórico oficial arranca en 2007)');
$presid = array_filter(Datos::bloques('Diputados'), fn($b) => !empty($b['presidente']));
ok(count($presid) >= 20, 'casi todos los bloques de Diputados tienen presidente (hay ' . count($presid) . ' de ' . count(Datos::bloques('Diputados')) . ')');

$vot = Datos::votaciones();
ok(count($vot) >= 1300 && count(array_unique(array_column($vot, 'id'))) === count($vot), 'hay más de 1.300 votaciones nominales, sin repetidos (hay ' . count($vot) . ')');
$malV = 0;
foreach ($vot as $v) {
    $d = Datos::votacion($v['id']);
    if (!empty($d['nota'])) { continue; } // las que no cuadran se muestran con aviso y no entran en las estadísticas
    $c = ['A' => 0, 'N' => 0, 'B' => 0, 'U' => 0, 'P' => 0, 'E' => 0, 'X' => 0];
    foreach ($d['votos'] ?? [] as $x) { $c[$x[3]]++; }
    if ([$c['A'], $c['N'], $c['B'], $c['U']] !== [$v['a'], $v['n'], $v['b'], $v['u']]) { $malV++; }
}
ok($malV === 0, 'el detalle voto por voto de cada votación coincide con su cabecera');
$part = array_filter($dip, fn($l) => Datos::participacion($l['slug']) !== null);
ok(count($part) > 30, 'hay historial de votaciones para diputados que ya ejercían (' . count($part) . ')');
$pctMal = array_filter($part, fn($l) => ($p = Datos::participacion($l['slug'])) && ($p['asistencia_pct'] < 0 || $p['asistencia_pct'] > 100 || ($p['coincidencia_bloque_pct'] ?? 0) > 100));
ok(!$pctMal, 'ningún porcentaje de participación sale de 0 a 100');

$ley = Datos::leyes();
ok(count($ley) === 1193, 'hay 1.193 leyes en el sumario (hay ' . count($ley) . ')');
$fechaMal = 0; $sinTexto = 0;
foreach (array_slice($ley, 0, 400) as [$n]) {
    $d = Datos::ley($n);
    if (!$d || (!empty($d['fs']) && !empty($d['fp']) && $d['fs'] > $d['fp'])) { $fechaMal++; }
    if (!$d || empty($d['t'])) { $sinTexto++; }
}
ok($fechaMal === 0, 'en cada ley la sanción es anterior o igual a su publicación');
ok($sinTexto === 0, 'las leyes tienen título');
$jsonLey = (string)file_get_contents(__DIR__ . '/../storage/verificado/leyes/ley/27544.json');
ok(!str_contains($jsonLey, '1983') && !str_contains($jsonLey, '2850'), 'no se publican los valores repetidos de promulgación y veto');
$si = Datos::subsidiosIndice();
ok(isset($si['2021'], $si['2022'], $si['2023'], $si['covid']), 'hay subsidios de 2021, 2022, 2023 y COVID-19');
$sumaMal = 0;
foreach ([2021 => 25720000.0, 2022 => 23010000.0, 2023 => 23340000.0] as $a => $esperado) {
    if (abs(array_sum(array_column(Datos::subsidios((string)$a), 'm')) - $esperado) > 0.5) { $sumaMal++; }
}
ok($sumaMal === 0, 'los totales de subsidios coinciden con los de los archivos oficiales');
$cuit = 0;
foreach (['2021', '2022', '2023', 'covid'] as $a) { foreach (Datos::subsidios($a) as $it) { if (isset($it['cuit']) || isset($it['CUIT'])) $cuit++; } }
ok($cuit === 0, 'no se publica ningún CUIT de beneficiarios');
$exp = 0;
foreach ($vot as $v) { $d = Datos::votacion($v["id"]); $exp += count($d["expedientes"] ?? []); }
ok($exp > 1500, 'las votaciones traen sus expedientes (' . $exp . ')');

$conDj = 0; $djMal = 0; $djCuit = 0;
foreach ($todos as $l) {
    $dj = Datos::ddjj($l['camara'], $l['slug']);
    if ($dj) { $conDj++; }
    foreach ($dj as $d) {
        if ($d['bienes_final'] < 0 || $d['deudas_final'] < 0 || abs($d['patrimonio_neto_final'] - ($d['bienes_final'] - $d['deudas_final'])) > 0.01) { $djMal++; }
        if (isset($d['cuit'])) { $djCuit++; }
    }
}
ok($conDj >= 80, 'hay declaraciones juradas de legisladores en ejercicio (' . $conDj . ')');
ok($djMal === 0, 'en cada declaración jurada, patrimonio neto = bienes - deudas y ningún monto es negativo');
ok($djCuit === 0 && !str_contains((string)file_get_contents(__DIR__ . '/../storage/verificado/ddjj.json'), 'cuit'), 'no se publica ningún CUIT en las declaraciones juradas');

$meta = Datos::mandatoActualMeta();
ok(($meta['validas']['Diputados'] ?? 0) >= 100 && ($meta['validas']['Senadores'] ?? 0) >= 150, 'hay votaciones válidas del mandato actual en ambas cámaras');
$maMal = 0; $maN = 0;
foreach ($todos as $l) {
    $r = Datos::mandatoActual($l['camara'], $l['slug']);
    if (!$r) { continue; }
    $maN++;
    if ($r['emitio'] + $r['ausente'] !== $r['votaciones'] || $r['afirmativos'] + $r['negativos'] + $r['abstenciones'] !== $r['emitio'] || $r['voto_pct'] < 0 || $r['voto_pct'] > 100) { $maMal++; }
}
ok($maMal === 0 && $maN > 250, 'las estadísticas del mandato actual son coherentes (' . $maN . ' legisladores)');

$cd = Datos::comisionesDiputados();
ok(count($cd) >= 40 && array_sum(array_map(fn($c) => count($c['integrantes']), $cd)) > 1200, 'hay comisiones de Diputados con sus integrantes (' . count($cd) . ')');
$slugsDip = array_column($dip, 'slug');
$malCom = 0; foreach ($cd as $c) { foreach ($c['integrantes'] as $m) { if (!in_array($m['slug'], $slugsDip, true)) { $malCom++; } } }
ok($malCom === 0, 'todo integrante de comisión es un diputado en ejercicio');
$vj = Datos::viajes();
ok(count($vj['actual'] ?? []) > 180, 'hay pasajes emitidos desde el 10/12/2025 para más de 180 diputados (' . count($vj['actual'] ?? []) . ')');
$malVj = 0; foreach (($vj['actual'] ?? []) as $a) { if ($a['aereo'] + $a['terrestre'] !== $a['total']) { $malVj++; } }
ok($malVj === 0, 'en los pasajes, aéreos + terrestres = total');
$rem = Datos::remuneraciones();
ok(!empty($rem['remuneraciones'][0]['importe']) && $rem['remuneraciones'][0]['importe'] > 1000000, 'la dieta publicada es un importe razonable');

$sv = array_filter($vot, fn($v) => ($v['c'] ?? 'D') === 'S');
$malSv = 0;
foreach ($sv as $v) { $d = Datos::votacion($v['id']); if (count($d['votos'] ?? []) !== 72) { $malSv++; } }
ok(count($sv) === 183 && $malSv === 0, 'las 183 votaciones del Senado tienen los 72 votos de los senadores (' . count($sv) . ')');
ok(($meta['validas']['Diputados'] ?? 0) === 141 && ($meta['validas']['Senadores'] ?? 0) === 183 && empty($meta['excluidas']), 'las 141 votaciones de Diputados y las 183 del Senado son válidas y ninguna quedó excluida');

echo $fallos ? "\n" . count($fallos) . " controles fallaron\n" : "\nTodo en orden\n";
exit($fallos ? 1 : 0);
