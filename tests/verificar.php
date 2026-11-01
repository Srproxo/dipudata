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

$sv = array_filter($vot, fn($v) => ($v['c'] ?? 'D') === 'S' && $v['f'] >= '2025-12-10');
$malSv = 0;
foreach ($sv as $v) { $d = Datos::votacion($v['id']); if (count($d['votos'] ?? []) !== 72) { $malSv++; } }
ok(count($sv) === 183 && $malSv === 0, 'las 183 votaciones del Senado del mandato actual tienen los 72 votos de los senadores (' . count($sv) . ')');

// Senado 2005 - 9/12/2025 (senado.gob.ar)
$sh = array_filter($vot, fn($v) => ($v['c'] ?? 'D') === 'S' && $v['f'] < '2025-12-10');
$hm = Datos::senadoAnterioresMeta();
ok(count($sh) === ($hm['votaciones'] ?? -1) && count($sh) > 300, 'las votaciones anteriores del Senado están todas en el índice (' . count($sh) . ')');
ok(($hm['desde'] ?? '9') >= '2005-01-01' && ($hm['hasta'] ?? '9') < '2025-12-10', 'las votaciones anteriores del Senado van de 2005 al 9/12/2025');
$malSh = 0; $mas72 = 0; $sinNota = 0;
foreach ($sh as $v) {
    $d = Datos::votacion($v['id']);
    if (count($d['votos'] ?? []) > 72) { $mas72++; }
    if (empty($d['nota']) || str_starts_with((string)$d['nota'], 'La página del Senado muestra')) {
        $c = ['A' => 0, 'N' => 0, 'B' => 0, 'U' => 0];
        foreach ($d['votos'] as $x) { $c[$x[3]] = ($c[$x[3]] ?? 0) + 1; }
        if ([$c['A'], $c['N'], $c['B'], $c['U']] !== [$v['a'], $v['n'], $v['b'], $v['u']] || !$d['votos']) { $malSh++; }
    }
}
ok($mas72 === 0, 'ninguna votación del Senado tiene más de 72 senadores (las páginas que duplican la tabla se depuran)');
ok($malSh === 0, 'cada votación anterior del Senado que se cuenta coincide voto por voto con su cabecera');
ok(($hm['id_contradictorio'] ?? 1) === 0, 'el ID que trae la página del Senado nunca contradice el cruce por nombre, provincia y mandato');
$malAnt = 0; $nAnt = 0;
foreach ($sen as $l) {
    foreach (Datos::senadoAnteriores($l['slug']) as $m) {
        $nAnt++;
        if ($m['emitio'] + $m['ausente'] !== $m['votaciones'] || $m['afirmativos'] + $m['negativos'] + $m['abstenciones'] !== $m['emitio']
            || $m['voto_pct'] < 0 || $m['voto_pct'] > 100 || $m['mandato_desde'] > ($m['mandato_hasta'] ?: '9999')) { $malAnt++; }
    }
}
ok($nAnt > 0 && $malAnt === 0, 'las estadísticas por mandato anterior de los senadores son coherentes (' . $nAnt . ' mandatos)');
$ct = json_decode((string)@file_get_contents(__DIR__ . '/../storage/verificado/contraste_senado_datacp.json'), true);
ok(is_array($ct) && $ct['comparadas'] > 300 && count($ct['distintas']) <= $ct['comparadas'] * 0.01, 'los totales del Senado coinciden con datacp.ar en al menos el 99% de las votaciones comparadas');

// Proyectos
$pm = Datos::proyectosMeta();
$cr = $pm['cruce_diputados'] ?? [];
ok(($cr['proyectos'] ?? 0) > 100000 && ($cr['cruzados'] ?? 0) / max(1, $cr['proyectos']) > 0.9, 'más del 90% de los proyectos de Diputados se asignan a un diputado por su ID');
ok(empty($cr['nombre_ambiguo']), 'ningún proyecto se asigna cuando el nombre corresponde a más de un diputado');
$conPr = 0; $malPr = 0; $fueraMandato = 0;
foreach ($dip as $l) {
    $r = Datos::proyectosResumen('Diputados', $l['slug']);
    if (!$r) { continue; }
    $conPr++;
    $ps = Datos::proyectos('Diputados', $l['slug']);
    if (count($ps) !== $r['total'] || array_sum($r['resultados']) > $r['total']) { $malPr++; }
    $tray = $l['trayectoria'] ?? [];
    foreach ($ps as $p) {
        $ok = false;
        foreach ($tray as $t) { if ($p[1] >= $t['inicio'] && $p[1] <= ($t['cese'] ?: $t['fin'])) { $ok = true; break; } }
        if (!$ok) { $fueraMandato++; }
        if (preg_match('/&[a-z]+;|&#\d+;/', $p[3])) { $malPr++; }
    }
}
ok($conPr > 200 && $malPr === 0, 'los proyectos de cada diputado cuadran con su resumen y no traen entidades HTML (' . $conPr . ' diputados)');
$senPr = array_filter($sen, fn($l) => Datos::proyectosResumen('Senadores', $l['slug']) !== null);
ok($fueraMandato === 0, 'todo proyecto asignado a un diputado se publicó mientras ejercía su mandato (' . $fueraMandato . ' fuera)');
ok(count($senPr) >= 60, 'hay proyectos del buscador oficial para los senadores (' . count($senPr) . ')');
ok(($meta['validas']['Diputados'] ?? 0) === 141 && ($meta['validas']['Senadores'] ?? 0) === 183 && empty($meta['excluidas']), 'las 141 votaciones de Diputados y las 183 del Senado son válidas y ninguna quedó excluida');

echo $fallos ? "\n" . count($fallos) . " controles fallaron\n" : "\nTodo en orden\n";
exit($fallos ? 1 : 0);
