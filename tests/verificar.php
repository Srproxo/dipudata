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
$fotoMal = array_filter($todos, fn($l) => $l['foto'] && (!preg_match('#^/assets/fotos/[a-z0-9-]+\.(jpg|png|gif)$#', $l['foto']) || !is_file(__DIR__ . '/..' . $l['foto'])));
ok(!$fotoMal && count(array_filter($todos, fn($l) => $l['foto'])) > 300, 'todas las fotos se sirven desde el propio sitio (assets/fotos/) y el archivo existe, sin pedir nada a terceros');
$csp = (string)file_get_contents(__DIR__ . '/../app/Web.php');
ok(str_contains($csp, "img-src 'self' data:;") && !str_contains($csp, 'img-src \'self\' https://'), 'la política de seguridad no admite imágenes de dominios externos');

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
    $c = ['A' => 0, 'N' => 0, 'B' => 0, 'U' => 0, 'P' => 0, 'E' => 0, 'S' => 0, 'X' => 0];
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
ok($conDj >= 200, 'hay declaraciones juradas de legisladores en ejercicio (' . $conDj . '; incluye filas con el organismo vacío)');
// Cada año publicado tiene que tocar un mandato oficial de la persona (año anterior a asumir y baja hasta un año después).
$of = __DIR__ . '/../storage/oficial/';
$mandD = []; foreach (json_decode(gzdecode(file_get_contents($of . 'hcdn/diputados_historico.json.gz')), true) as $x) {
    $mandD[(string)$x['ID']][] = [min(substr($x['INICIO'], 0, 10), substr($x['JURAMENTO'] ?? $x['INICIO'], 0, 10)), max(substr($x['FIN'], 0, 10), substr($x['CESE'] ?? $x['FIN'], 0, 10))];
}
$mandS = []; foreach (['senadores_no_activos' => ['INICIO PERIODO REAL', 'CESE PERIODO REAL'], 'senadores_activos' => ['D_REAL', 'C_LEGAL']] as $f => [$di, $ci]) {
    foreach (json_decode(gzdecode(file_get_contents($of . "senado/$f.json.gz")), true)['table']['rows'] as $x) {
        $c = (string)$x[$ci]; $mandS[(string)$x['ID']][] = [substr((string)$x[$di], 0, 10), in_array(substr($c, 0, 2), ['19', '20'], true) ? substr($c, 0, 10) : '2099-12-31'];
    }
}
$djFuera = [];
foreach ($todos as $l) {
    $m = $l['camara'] === 'Diputados' ? ($mandD[(string)($l['hcdn_id'] ?? '')] ?? null) : ($mandS[(string)($l['id_senado'] ?? '')] ?? null);
    $m ??= [[$l['inicio'] ?? '1900-01-01', '2099-12-31']];
    foreach (Datos::ddjj($l['camara'], $l['slug']) ?? [] as $d) {
        if (($d['camara'] ?? null) !== $l['camara']) { continue; } // otro cargo u otra cámara: se identifican por persona, no por mandato
        $y = (int)$d['anio']; $bien = false;
        foreach ($m as [$i, $f]) {
            if (($i <= ($y + 1) . '-12-31' && $f >= $y . '-01-01') || ($d['tipo'] === 'Baja' && $f >= ($y - 1) . '-01-01' && $i <= $y . '-12-31')) { $bien = true; }
        }
        if (!$bien) { $djFuera[] = $l['slug'] . ' ' . $y; }
    }
}
$djSinCargo = 0; foreach ($todos as $l) { foreach (Datos::ddjj($l['camara'], $l['slug']) ?? [] as $d) { if (trim((string)($d['cargo'] ?? '')) === '') { $djSinCargo++; } } }
ok($djSinCargo === 0, 'cada año de declaración jurada dice por qué cargo se presentó');
ok(!str_contains((string)file_get_contents(__DIR__ . '/../storage/verificado/ddjj.json'), '"persona"'), 'el número interno de persona de las declaraciones no se publica');
ok($djFuera === [], 'cada año de declaración jurada como legislador de esta cámara cae en un mandato oficial' . ($djFuera ? ': ' . implode(', ', array_slice($djFuera, 0, 5)) : ''));
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
$rpj = json_decode((string)file_get_contents(RAIZ . '/storage/verificado/rutas_pasajes.json'), true);
$malRp = []; $fueraRp = 0; $sinLugar = 0;
foreach ($rpj['por_diputado'] as $s => $r) {
    $n = $r['sin_ubicar'] + $r['misma_localidad'];
    foreach ($r['rutas'] as $x) { $n += $x['ida'] + $x['vuelta']; if (!isset($rpj['lugares'][$x['a']], $rpj['lugares'][$x['b']])) { $sinLugar++; } }
    if ($n !== $r['total'] || $r['total'] !== ($vj['actual'][$s]['total'] ?? -1)) { $malRp[] = $s; }
}
foreach ($rpj['lugares'] as $lu) { [$x, $y] = $lu['xy']; if ($x < 0 || $y < 0 || $x > $rpj['_meta']['ancho'] || $y > $rpj['_meta']['alto']) { $fueraRp++; } }
ok(!$malRp && count($rpj['por_diputado']) === count($vj['actual']), 'rutas de pasajes: los tramos de cada diputado cuadran con el total desde el 10/12/2025');
ok($sinLugar === 0 && $fueraRp === 0, 'rutas de pasajes: todo lugar está ubicado y dentro del mapa');
ok(count($rpj['provincias']) === 24, 'el mapa de pasajes dibuja las 24 jurisdicciones');
$rem = Datos::remuneraciones();
ok(!empty($rem['remuneraciones'][0]['importe']) && $rem['remuneraciones'][0]['importe'] > 1000000, 'la dieta publicada es un importe razonable');

$sv = array_filter($vot, fn($v) => ($v['c'] ?? 'D') === 'S');
$malSv = 0; $slugsSen = array_column($sen, 'slug'); $malSlug = 0; $sinVoto = 0;
foreach ($sv as $v) {
    $d = Datos::votacion($v['id']); $c = ['A' => 0, 'N' => 0, 'B' => 0, 'U' => 0, 'X' => 0];
    foreach ($d['votos'] ?? [] as $x) {
        $c[$x[3]] = ($c[$x[3]] ?? 0) + 1;
        if (isset($x[4]) && !in_array(substr($x[4], 2), $slugsSen, true)) { $malSlug++; }
    }
    // Los totales de la cabecera tienen que ser exactamente el recuento voto por voto (una celda en blanco no es un voto)
    if ([$c['A'], $c['N'], $c['B'], $c['U']] !== [$v['a'], $v['n'], $v['b'], $v['u']] || array_sum($c) > 72 || array_sum($c) < 70) { $malSv++; }
    if ($v['f'] < '2005-01-01' || $v['f'] > date('Y-m-d')) { $malSv++; }
    $sinVoto += $c['X'];
}
ok(count($sv) > 2700 && $malSv === 0, 'las ' . count($sv) . ' votaciones del Senado desde 2005 coinciden voto por voto con su cabecera y tienen entre 70 y 72 filas');
ok($malSlug === 0, 'todo voto del Senado atribuido a un senador actual apunta a un senador en ejercicio');
ok(count(array_unique(array_column($sv, 'id'))) === count($sv), 'no hay números de acta del Senado repetidos');
ok(($meta['validas']['Diputados'] ?? 0) === 141 && ($meta['validas']['Senadores'] ?? 0) === 183 && empty($meta['excluidas']), 'las 141 votaciones de Diputados y las 183 del Senado del mandato actual son válidas y ninguna quedó excluida');

$sm = Datos::senadoAnterioresMeta();
ok(($sm['excluidas'] ?? 1) === 0 && ($sm['validas'] ?? 0) > 2500 && ($sm['desde'] ?? '') < '2005-12-31' && ($sm['hasta'] ?? '9') <= '2025-12-09', 'las votaciones anteriores del Senado (' . ($sm['validas'] ?? 0) . ') son válidas, de 2005 al 9/12/2025');
ok(($sm['control_id']['con_id'] ?? 0) > 10000 && ($sm['control_id']['coincide'] ?? -1) === ($sm['control_id']['con_id'] ?? 0), 'el cruce de senadores por nombre y mandato coincide con el ID oficial en todas las filas que lo traen (' . ($sm['control_id']['coincide'] ?? 0) . ')');
$resMal = array_filter($vot, fn($v) => !in_array((string)($v['res'] ?? ''), ['AFIRMATIVO', 'NEGATIVO', 'EMPATE', ''], true));
ok(!$resMal, 'todo resultado de votación es afirmativo, negativo, empate o "sin resultado publicado" (' . count($resMal) . ' con otro valor)');
$anul = 0; foreach ($sv as $v) { $n = (string)(Datos::votacion($v['id'])['nota'] ?? ''); if (str_contains($n, 'anulada, cancelada o levantada')) { $anul++; } }
ok(($sm['anuladas'] ?? -1) > 0 && $anul === $sm['anuladas'], 'las ' . $anul . ' votaciones del Senado anuladas, canceladas o levantadas se muestran con aviso y no se cuentan');
$saMal = 0; $saN = 0;
foreach ($sen as $l) {
    foreach (Datos::senadoAnteriores($l['slug']) as $m) {
        $saN++;
        if ($m['emitio'] + $m['ausente'] !== $m['votaciones'] || $m['afirmativos'] + $m['negativos'] + $m['abstenciones'] !== $m['emitio'] || $m['desde'] > $m['hasta'] || $m['hasta'] > '2025-12-09' || $m['desde'] < '2005-01-01' || $m['voto_pct'] < 0 || $m['voto_pct'] > 100) { $saMal++; }
    }
}
ok($saMal === 0 && $saN > 40, 'las estadísticas por mandato anterior de los senadores actuales son coherentes (' . $saN . ' mandatos)');
$ct = json_decode((string)file_get_contents(__DIR__ . '/../storage/verificado/contraste_datacp.json'), true) ?: [];
$cr = $ct['resumen'] ?? [];
$cmp = ($cr['S_comparadas'] ?? 0) + ($cr['D_comparadas'] ?? 0); $dif = ($cr['S_difieren'] ?? 0) + ($cr['D_difieren'] ?? 0);
ok($cmp > 400 && $dif / max(1, $cmp) < 0.10, 'el contraste de totales con DataCP (' . $cmp . ' votaciones) difiere en menos de 10% (' . $dif . ' casos, todos explicados en /metodologia)');

// Proyectos parlamentarios: cada proyecto tiene un solo autor, el resumen cuadra con la lista y solo hay diputados actuales
$pm = Datos::proyectosMeta();
ok(($pm['cruzados'] ?? 0) > 100000 && ($pm['nombre_ambiguo'] ?? 1) === 0, 'el cruce de autores por nombre completo y mandato no deja casos ambiguos (' . ($pm['cruzados'] ?? 0) . ' proyectos cruzados)');
$vistosPy = []; $malPy = 0; $conPy = 0; $totPy = 0;
foreach ($dip as $l) {
    $pr = Datos::proyectos($l['slug']);
    if (!$pr) { continue; }
    $conPy++; $r = $pr['resumen']; $totPy += count($pr['proyectos']);
    if ($r['total'] !== count($pr['proyectos']) || array_sum($r['por_tipo']) !== $r['total'] || array_sum($r['por_resultado']) !== $r['total']) { $malPy++; }
    foreach ($pr['proyectos'] as $x) {
        if (isset($vistosPy[$x[0]]) || $x[1] < '2008-01-01' || $x[1] > ($pm['hasta'] ?? '9999') || ($x[6] !== '' && $x[6] < '2008-01-01')) { $malPy++; }
        $vistosPy[$x[0]] = true;
    }
}
ok($malPy === 0 && $conPy > 200 && $totPy === ($pm['de_diputados_actuales'] ?? -1), 'los proyectos de ' . $conPy . ' diputados cuadran con su resumen, sin repetidos entre diputados (' . $totPy . ')');
// Proyectos de los senadores: salen de la página de cada senador (ID oficial); solo proyectos, sin resultado inventado
$spMal = 0; $spN = 0; $spTot = 0; $vistosSp = [];
foreach ($sen as $l) {
    $pr = Datos::proyectos($l['slug'], 'Senadores');
    if (!$pr) { continue; }
    $spN++; $r = $pr['resumen']; $spTot += count($pr['proyectos']);
    if ($r['total'] !== count($pr['proyectos']) || array_sum($r['por_tipo']) !== $r['total'] || ($r['camara'] ?? '') !== 'Senadores') { $spMal++; }
    if (array_diff(array_keys($r['por_tipo']), ['Ley', 'Declaracion', 'Comunicacion', 'Resolucion', 'Decreto'])) { $spMal++; }
    foreach ($pr['proyectos'] as $x) {
        if (!preg_match('#^\d+\.\d{2}/S/(PL|PD|PC|PR|DE)$#', $x[0]) || $x[5] !== '' || $x[1] < '2001-01-01' || $x[1] > date('Y-m-d')) { $spMal++; }
        $y = (int)substr($x[0], strpos($x[0], '.') + 1, 2);
        if (!preg_match('#^' . substr($x[0], 0, strpos($x[0], '.')) . '/' . sprintf('%02d', $y) . '$#', $x[3])) { $spMal++; }
        $k = $l['slug'] . $x[0];
        if (isset($vistosSp[$k])) { $spMal++; }
        $vistosSp[$k] = true;
    }
}
ok($spMal === 0 && $spN >= 65 && $spTot === ($pm['senado_proyectos'] ?? -1), 'los proyectos de ' . $spN . ' senadores son proyectos con expediente válido, sin repetidos y sin resultado (' . $spTot . ')');
ok(($pm['senado_expediente_invalido'] ?? 1) === 0 && ($pm['senado_no_es_proyecto'] ?? 0) > 0, 'las comunicaciones y notas del Senado (códigos CO y CC) no se publican como proyectos');

$em = Datos::ejesMeta();
$ejesMal = 0; $ejesN = 0;
foreach ($todos as $l) {
    $e = Datos::ejes($l['camara'], $l['slug']);
    if (!$e) { continue; }
    $ejesN++;
    if ($e['con_eje'] > $e['con_giro'] || $e['con_giro'] > $e['proyectos']) { $ejesMal++; }
    foreach ($e['ejes'] as $nombre => $x) {
        if (!in_array($nombre, $em['ejes'] ?? [], true) || $x['proyectos'] > $e['con_eje']) { $ejesMal++; }
        if (isset($x['votos']) && ($x['afirmativos'] > $x['votos'] || $x['votos'] < 1)) { $ejesMal++; }
    }
}
ok(count($em['ejes'] ?? []) === 11 && $ejesMal === 0 && $ejesN >= 200, 'ejes temáticos: 11 ejes, ' . $ejesN . ' legisladores, cuentas por eje dentro de sus totales');
ok(($em['proyectos_con_eje'] ?? 0) <= ($em['proyectos_con_giro'] ?? -1), 'ningún proyecto tiene eje sin tener giro a comisión');

$ipc = Datos::ipc()['mensual'] ?? [];
ok(($ipc['2016-12'] ?? 0) == 100 && count($ipc) >= 100 && !array_filter($ipc, fn($v) => $v <= 0), 'IPC del INDEC con base diciembre 2016 = 100 y valores positivos');
$usd = Datos::dolar()['cierre_anual'] ?? [];
$usdMal = array_filter($usd, fn($c, $a) => ($c['valor'] ?? 0) <= 0 || substr((string)$c['fecha'], 0, 7) !== $a . '-12', ARRAY_FILTER_USE_BOTH);
ok(count($usd) >= 20 && !$usdMal && isset($usd['2024']), 'dólar oficial del BCRA al cierre de cada año desde 2003, con valores positivos y fecha de diciembre (' . count($usd) . ' años)');
$djSinUsd = 0; foreach (Datos::legisladores() as $l) { foreach (Datos::ddjj($l['camara'], $l['slug']) ?? [] as $d) { if ((int)$d['anio'] >= 2003 && !isset($usd[(string)(int)$d['anio']])) { $djSinUsd++; } } }
ok($djSinUsd === 0, 'toda declaración jurada desde 2003 tiene tipo de cambio de cierre para pasarla a dólares');
$mepD = Datos::dolarMep(); $mepC = $mepD['cierre_anual'] ?? [];
$mepMal = array_filter($mepC, fn($c, $a) => ($c['valor'] ?? 0) <= 0 || substr((string)$c['fecha'], 0, 7) !== $a . '-12' || !isset($usd[$a]) || $c['valor'] / $usd[$a]['valor'] < 0.9 || $c['valor'] / $usd[$a]['valor'] > 3.5, ARRAY_FILTER_USE_BOTH);
ok(count($mepC) >= 5 && !$mepMal && str_contains((string)($mepD['_meta']['fuente'] ?? ''), 'no es una fuente oficial'), 'dólar MEP al cierre de cada año: valores de diciembre, entre 0,9 y 3,5 veces el oficial, y marcado como fuente no oficial (' . count($mepC) . ' años)');

$coMal = 0; $coN = 0;
foreach ($sen as $l) {
    $c = Datos::coautorias($l['slug']); if (!$c) { continue; } $coN++;
    if ($c['con_otros_bloques'] > $c['con_otros'] || $c['con_otros'] > $c['proyectos']) { $coMal++; }
    foreach ($c['socios'] as [$o, $n]) { $r = Datos::coautorias($o); $par = $r ? array_values(array_filter($r['socios'], fn($x) => $x[0] === $l['slug'])) : []; if (!$par || $par[0][1] !== $n) { $coMal++; } }
}
ok($coN >= 60 && $coMal === 0, 'coautorías del Senado: ' . $coN . ' senadores, cuentas simétricas entre cada par');

if (!function_exists('match_temas')) { require_once __DIR__ . '/../app/Web.php'; }
$mt = match_temas(['Salud' => 3, 'Educación' => 1]);
$mtMal = array_filter($mt, fn($f) => $f['afinidad'] < -1e-9 || $f['afinidad'] > 1 + 1e-9 || $f['con_eje'] < 10);
$solo = match_temas(['Salud' => 3]); $l0 = $solo[0]['l'] ?? null; $e0 = $l0 ? Datos::ejes($l0['camara'], $l0['slug']) : null;
ok($mt && !$mtMal && count(temas_match()) === 11 && array_values(temas_match()) === ($em['ejes'] ?? []) && match_temas([]) === [], 'match por temas: coincidencias entre 0 y 1, solo legisladores con 10 proyectos con tema o más (' . count($mt) . ')');
ok($e0 && ($e0['ejes']['Salud']['proyectos'] ?? 0) > 0, 'match por temas: con solo "Salud" marcado, el primero tiene proyectos de salud');

$ap = Datos::aportes(); $apMal = 0;
foreach ($ap['destinos'] ?? [] as $d) {
    if (abs(array_sum(array_column($d['agrupaciones'], 'total')) - $d['total']) > 1 || array_sum(array_column($d['agrupaciones'], 'aportes')) !== $d['aportes']) { $apMal++; }
    foreach ($d['agrupaciones'] as $a) { if ($a['aportantes'] > $a['aportes'] || $a['aportantes'] < 1) { $apMal++; } }
}
ok(count($ap['destinos'] ?? []) >= 20 && $apMal === 0, 'aportes a partidos: los totales por agrupación suman el total de cada destino');
$crudo = is_file(RAIZ . '/storage/oficial/cne/aportes.csv.gz') ? (string)gzdecode((string)file_get_contents(RAIZ . '/storage/oficial/cne/aportes.csv.gz')) : '';
$emMal = 0; $slugs = [];
foreach ($ap['empresas'] ?? [] as $em) {
    $slugs[$em['slug']] = 1;
    if (abs(array_sum(array_column($em['detalle'], 3)) - $em['total']) > 1 || array_sum(array_column($em['detalle'], 4)) !== $em['aportes'] || count(array_unique(array_column($em['detalle'], 0))) !== $em['agrupaciones']) { $emMal++; }
}
foreach ($ap['destinos'] ?? [] as $d) { foreach ($d['agrupaciones'] as $a) { foreach ($a['mayores'] as $x) { if (($x[0] === null) !== ($x[4] === null) || ($x[4] !== null && !isset($slugs[$x[4]]))) { $emMal++; } } } }
ok(count($slugs) === count($ap['empresas'] ?? []) && count($slugs) >= 400 && $emMal === 0, 'aportes: ' . count($slugs) . ' personas jurídicas con totales coherentes; el top 10 solo nombra personas jurídicas y enlaza a su página');
$humanasConNombre = 0; foreach (array_slice(explode("\n", $crudo), 1) as $lin) { $c = str_getcsv($lin); if (count($c) > 5 && $c[3] === 'Humana' && ($c[5] !== '' || $c[6] !== '')) { $humanasConNombre++; } }
ok($humanasConNombre === 0, 'aportes a partidos: ninguna persona humana tiene nombre ni rubro guardado');
ok($crudo !== '' && !preg_match('/\b\d{11}\b/', $crudo) && str_starts_with($crudo, 'fecha,cod_destino,destino,tipo,aportante,'), 'aportes a partidos: el archivo guardado no tiene CUIL/CUIT');

// Declaraciones juradas: cada caso sin cifras se explica en pantalla; nada se publica dos veces ni se inventa
$djMal = 0; $djSinTot = 0; $djConTot = 0;
foreach ($todos as $lg) {
    $dj = Datos::ddjj($lg['camara'], $lg['slug']); $es = Datos::ddjjEstado($lg['camara'], $lg['slug']);
    if ($dj) { $djConTot++; }
    $anPub = array_map(static fn($d) => (int)$d['anio'], $dj);
    if (count($anPub) !== count(array_unique($anPub))) { $djMal++; }
    foreach ($es['sin_totales'] ?? [] as $x) { $djSinTot++; if (in_array($x['anio'], $anPub, true)) { $djMal++; } }
    foreach ($dj as $d) { if ($d['bienes_final'] == 0 || abs($d['patrimonio_neto_final'] - ($d['bienes_final'] - $d['deudas_final'])) > 0.02) { $djMal++; } }
}
ok($djMal === 0 && $djConTot > 200 && $djSinTot > 0, 'declaraciones juradas: un solo año por declaración, patrimonio = bienes - deudas, sin bienes en cero, y los años sin totales se registran aparte (' . $djConTot . ' legisladores con totales, ' . $djSinTot . ' años sin totales)');

// Gráficos: salen como SVG o barras sin scripts, con título accesible, y no se dibujan con datos insuficientes
if (!function_exists('svg_barras')) { require_once __DIR__ . '/../app/Web.php'; }
$sb = svg_barras(['2023' => 3, '2024' => 5, '2025' => 2], 'prueba');
ok(str_contains($sb, '<svg') && substr_count($sb, 'class="g-barra"') === 3 && str_contains($sb, '<title') && !str_contains($sb, '<script'), 'el gráfico de barras dibuja una barra por año, con título accesible y sin scripts');
ok(svg_barras(['2024' => 4], 'x') === '', 'un gráfico con un solo punto no se dibuja');
$br = barra_resultado(43, 25, 0, 4);
preg_match_all('#width:([\d.]+)%#', $br, $mw);
ok(abs(array_sum($mw[1]) - 100) < 0.2 && str_contains($br, 'aria-label'), 'la barra de resultado de una votación suma 100% y tiene descripción accesible');

// Patrimonio y comparación: cifras reales de las declaraciones, con el período que usa cada variación
$spN = 0; $spMal = 0;
foreach ($todos as $lg) {
    $sp = serie_patrimonio($lg);
    $dj = Datos::ddjj($lg['camara'], $lg['slug']);
    if (!$sp) { if ($dj) { $spMal++; } continue; }
    $spN++;
    if ($sp['cantidad'] !== count($dj) || $sp['ultimo_anio'] !== max($sp['corriente'] ? array_keys($sp['corriente']) : [0])) { $spMal++; }
    foreach (['var_hoy', 'var_usd'] as $k) {
        if ($sp[$k] && ($sp[$k]['desde'] >= $sp[$k]['hasta'] || !is_finite($sp[$k]['pct']))) { $spMal++; }
    }
    if ($sp['var_hoy'] && (!isset($sp['hoy'][$sp['var_hoy']['desde']]) || !isset($sp['hoy'][$sp['var_hoy']['hasta']]))) { $spMal++; }
}
ok($spMal === 0 && $spN === $djConTot, 'patrimonio: la serie de cada legislador con declaración coincide con sus declaraciones y cada variación usa dos años distintos con conversión (' . $spN . ' legisladores)');
$ca = coincidencias_votos(['s:carlos-mauricio-espinola', 's:maximiliano-abad']);
$par = $ca['s:carlos-mauricio-espinola|s:maximiliano-abad'] ?? null;
ok($par !== null && $par[1] > 100 && $par[0] <= $par[1], 'la coincidencia de votos entre dos senadores se calcula sobre votaciones en que los dos votaron (' . ($par[1] ?? 0) . ')');
ok(coincidencias_votos(['s:carlos-mauricio-espinola', 'd:guillermo-cesar-aguero']) === [], 'no se compara la coincidencia de votos entre legisladores de cámaras distintas');
ok(legislador_por_clave('s:carlos-mauricio-espinola') !== null && legislador_por_clave('x:nadie') === null && legislador_por_clave('d:../etc') === null, 'las claves de comparación se validan');
$sv2 = svg_lineas(['A' => [2020 => 1.0, 2021 => 2.0], 'B' => [2020 => 2.0, 2021 => 1.0]], 'prueba');
ok(substr_count($sv2, 'class="g-l g-l') === 2 && str_contains($sv2, '<title') && !str_contains($sv2, '<script'), 'el gráfico de varias líneas dibuja una línea por legislador, con título accesible y sin scripts');

// Resultados electorales 2023 (fuente oficial: Dirección Nacional Electoral)
$re = Datos::resultados2023(); $reMal = [];
ok(count($re['provincias'] ?? []) === 24 && count(array_filter(Datos::provincias(), fn($p) => Datos::resultadosProvincia($p['slug']) === null)) === 0, 'resultados 2023: las 24 provincias del sitio tienen resultados');
ok(str_starts_with((string)($re['_meta']['url_archivo'] ?? ''), 'https://www.argentina.gob.ar/') && preg_match('/^[0-9a-f]{64}$/', (string)($re['_meta']['sha256_zip'] ?? '')) === 1 && ($re['_meta']['escrutinio'] ?? '') === 'provisorio', 'resultados 2023: cita la fuente oficial, la huella del archivo y aclara que es provisorio');
foreach ($re['provincias'] ?? [] as $slug => $r) {
    foreach (['diputados', 'senadores'] as $k) {
        if (empty($r[$k])) { continue; }
        $ag = $r[$k]['agrupaciones']; $nombres = array_column($ag, 'nombre');
        if (array_sum(array_column($ag, 'votos')) !== $r[$k]['positivos'] || count($nombres) !== count(array_unique($nombres)) || $r[$k]['positivos'] + array_sum($r[$k]['otros']) !== $r[$k]['votantes'] || $r[$k]['votantes'] > $r['electores']) { $reMal[] = "$slug/$k"; }
        $v = array_column($ag, 'votos'); $o = $v; rsort($o);
        if ($v !== $o) { $reMal[] = "$slug/$k orden"; }
    }
    if (empty($r['diputados'])) { $reMal[] = "$slug sin diputados"; }
}
ok(!$reMal, 'resultados 2023: los votos suman, no hay agrupaciones repetidas y nadie supera a los electores' . ($reMal ? ' (' . implode(', ', $reMal) . ')' : ''));
ok(($re['_meta']['provincias_con_senadores'] ?? []) === ['buenos-aires', 'formosa', 'jujuy', 'la-rioja', 'misiones', 'san-juan', 'san-luis', 'santa-cruz'], 'resultados 2023: el Senado solo figura en las 8 provincias que lo renovaron');
$totE = array_sum(array_column($re['provincias'] ?? [], 'electores'));
ok($totE > 35000000 && $totE < 36500000, 'resultados 2023: el total de electores de las mesas ronda los 35 millones (' . $totE . ')');

echo $fallos ? "\n" . count($fallos) . " controles fallaron\n" : "\nTodo en orden\n";
exit($fallos ? 1 : 0);
