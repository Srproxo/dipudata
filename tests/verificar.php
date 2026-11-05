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

// Declaraciones del mismo año con montos distintos: no se elige una en silencio.
$estadoDj = json_decode((string)file_get_contents(__DIR__ . '/../storage/verificado/ddjj_estado.json'), true) ?: [];
$djIncAnios = 0; $djIncPublicado = []; $djIncNoDiez = []; $djAniosRepetidos = [];
foreach ($estadoDj as $clave => $es) {
    foreach ($es['inconsistente'] ?? [] as $x) {
        $djIncAnios++;
        $sl = str_starts_with($clave, 's:') ? substr($clave, 2) : $clave;
        $cam = str_starts_with($clave, 's:') ? 'Senadores' : 'Diputados';
        foreach (Datos::ddjj($cam, $sl) ?? [] as $d) { if ((int)$d['anio'] === (int)$x['anio']) { $djIncPublicado[] = $clave . ' ' . $x['anio']; } }
        foreach (['bienes', 'deudas'] as $k) {
            [$mn, $mx] = $x[$k];
            if ($mn > 0 && abs($mx / $mn - 1) > 1e-9 && abs($mx / $mn - 10) > 1e-6) { $djIncNoDiez[] = $clave . ' ' . $x['anio'] . ' ' . $k; }
        }
    }
}
foreach ($todos as $l) { $vistos = []; foreach (Datos::ddjj($l['camara'], $l['slug']) ?? [] as $d) { if (isset($vistos[$d['anio']])) { $djAniosRepetidos[] = $l['slug'] . ' ' . $d['anio']; } $vistos[$d['anio']] = true; } }
ok($djIncPublicado === [], 'ningún año de declaración jurada con declaraciones de montos distintos se publica con cifras' . ($djIncPublicado ? ': ' . implode(', ', array_slice($djIncPublicado, 0, 5)) : ''));
ok($djAniosRepetidos === [], 'cada legislador tiene a lo sumo una declaración jurada publicada por año' . ($djAniosRepetidos ? ': ' . implode(', ', array_slice($djAniosRepetidos, 0, 5)) : ''));
ok($djIncNoDiez === [], 'en cada año marcado como inconsistente la diferencia entre las declaraciones es de exactamente diez veces (si cambia, revisar el texto de /metodologia)' . ($djIncNoDiez ? ': ' . implode(', ', array_slice($djIncNoDiez, 0, 5)) : ''));
// Renderizado real de perfiles: sin avisos de PHP y con el resumen coherente con las declaraciones publicadas.
$avisosPerfil = []; $perfilIncoherente = [];
set_error_handler(static function ($n, $m, $f, $ln) use (&$avisosPerfil) { $avisosPerfil[] = basename($f) . ':' . $ln . ' ' . $m; return true; });
$conDj = $sinDj = [];
foreach ($todos as $l) { if (Datos::ddjj($l['camara'], $l['slug'])) { if (count($conDj) < 4) { $conDj[] = $l; } } elseif (count($sinDj) < 3) { $sinDj[] = $l; } }
foreach (array_merge($conDj, $sinDj) as $l) {
    foreach (['resumen', 'patrimonio'] as $tabP) {
        ob_start(); (static function ($l, $tab) { include __DIR__ . '/../app/Vistas/perfil.php'; })($l, $tabP); $htmlP = (string)ob_get_clean();
        $tiene = (bool)Datos::ddjj($l['camara'], $l['slug']);
        if ($tabP === 'resumen' && $tiene && str_contains($htmlP, 'No figura en el archivo de declaraciones juradas')) { $perfilIncoherente[] = $l['slug'] . ' dice que no figura pero tiene declaraciones'; }
        if ($tabP === 'resumen' && !$tiene && !str_contains($htmlP, 'declaración jurada') && !str_contains($htmlP, 'declaraciones juradas')) { $perfilIncoherente[] = $l['slug'] . ' no explica por qué falta la declaración'; }
    }
}
restore_error_handler();
ok($avisosPerfil === [] && $perfilIncoherente === [], 'los perfiles (resumen y declaración jurada) se renderizan sin avisos de PHP y el resumen es coherente con las declaraciones (' . (count($conDj) + count($sinDj)) . ' perfiles)' . ($avisosPerfil ? ': ' . implode(' | ', array_slice($avisosPerfil, 0, 2)) : '') . ($perfilIncoherente ? ': ' . implode(' | ', array_slice($perfilIncoherente, 0, 2)) : ''));
// Páginas legales, sitemap y Google (analítica apagada por defecto).
$vl = fn(string $n) => (string)file_get_contents(__DIR__ . '/../app/Vistas/' . $n . '.php');
$mvc = (string)file_get_contents(__DIR__ . '/../mvc.php'); $plt = (string)file_get_contents(__DIR__ . '/../app/Vistas/_plantilla.php');
$legales = ['terminos' => '/terminos', 'privacidad' => '/privacidad', 'cookies' => '/cookies'];
$legalFalta = []; foreach ($legales as $v => $ruta) { if (!is_file(__DIR__ . '/../app/Vistas/' . $v . '.php') || !str_contains($mvc, "'" . $ruta . "'") || !str_contains($mvc, "['" . $ruta . "', 'yearly'") || !str_contains($plt, 'href="' . $ruta . '"')) { $legalFalta[] = $ruta; } }
ok($legalFalta === [], 'las páginas legales existen, tienen ruta, están en el sitemap y se enlazan desde el pie' . ($legalFalta ? ': faltan ' . implode(', ', $legalFalta) : ''));
$pv = $vl('privacidad'); $tm = $vl('terminos');
ok(str_contains($pv, 'Ley 25.326') && str_contains($pv, 'AGENCIA DE ACCESO A LA INFORMACIÓN PÚBLICA') && str_contains($pv, 'diez días corridos') && str_contains($pv, 'cinco días hábiles') && str_contains($pv, 'Si quien lo pide tiene derecho a hacerlo, la información se quita'), 'la política de privacidad incluye la leyenda de la AAIP, los derechos, los plazos y la baja a pedido');
ok(str_contains($tm, 'No es un sitio oficial') && str_contains($tm, 'Creative Commons') && str_contains($tm, 'Ley aplicable'), 'los términos dicen que no es un sitio oficial, la licencia y la ley aplicable');
$bs = (string)file_get_contents(__DIR__ . '/../app/bootstrap.php'); $js = (string)file_get_contents(__DIR__ . '/../assets/js/analitica.js'); $wb = (string)file_get_contents(__DIR__ . '/../app/Web.php');
ok(!preg_match('/G-[A-Z0-9]{6,}/', $bs) && str_contains($bs, "define('GA_ID'") && str_contains($bs, "define('GSC_VERIFICACION'"), 'el ID de Analytics y el código de Search Console salen del entorno, no están escritos en el código');
ok(substr_count($js, 'googletagmanager.com') === 1 && str_contains($js, "if (actual === 'si') { cargar(); }") && str_contains($wb, "GA_ID === ''"), 'Google Analytics solo se carga con consentimiento y la CSP solo lo permite si está configurado');
ok(TITULAR_NOMBRE !== '' && TITULAR_DOMICILIO !== '', 'las páginas legales identifican al responsable del sitio (nombre y domicilio, Ley 25.326 art. 6)');
$legalAvisos = []; set_error_handler(static function ($n, $m, $f, $ln) use (&$legalAvisos) { $legalAvisos[] = basename($f) . ':' . $ln . ' ' . $m; return true; });
foreach (array_keys($legales) as $vv) { ob_start(); (static function () use ($vv) { include __DIR__ . '/../app/Vistas/' . $vv . '.php'; })(); $hh = (string)ob_get_clean(); if (strlen($hh) < 800) { $legalAvisos[] = $vv . ' casi vacía'; } }
restore_error_handler();
ok($legalAvisos === [] && (GA_ID !== '' || str_contains($vl('cookies'), 'DipuData no usa cookies')), 'las páginas legales se renderizan sin avisos de PHP y la de cookies dice la verdad según la configuración');
// Test de afinidad por votos.
$afMal = [];
foreach (['Diputados', 'Senadores'] as $cmA) {
    $pq = afinidad_preguntas($cmA);
    if (count($pq) !== 10) { $afMal[] = $cmA . ' tiene ' . count($pq) . ' preguntas'; }
    $ids = array_column($pq, 'id'); if (count($ids) !== count(array_unique($ids))) { $afMal[] = $cmA . ' repite preguntas'; }
    foreach ($pq as $q) {
        if (!Datos::votacion($q['id']) || $q['f'] < '2025-12-10' || preg_match('/O\.D\.\s*\d+\s*-|DICT\. DE M/u', $q['titulo'])) { $afMal[] = $cmA . ' ' . $q['id']; }
    }
    $todasA = array_fill_keys($ids, 'a'); $rA = afinidad_calcular($cmA, $todasA); $prev = 2;
    if (count($rA) < 0.8 * count(Datos::legisladores($cmA))) { $afMal[] = $cmA . ' compara a solo ' . count($rA) . ' de ' . count(Datos::legisladores($cmA)); }
    foreach ($rA as $f) { if ($f['coinc'] > $f['cons'] || $f['cons'] > 10 || $f['cons'] < 6 || $f['pct'] > $prev + 1e-9) { $afMal[] = $cmA . ' ' . $f['l']['slug'] . ' puntaje incoherente'; break; } $prev = $f['pct']; }
    if ($rA) {  // control cruzado a mano con el primer legislador de la lista
        $f0 = $rA[0]; $clave0 = ($f0['l']['camara'] === 'Senadores' ? 's:' : '') . $f0['l']['slug']; $c0 = 0; $k0 = 0;
        foreach ($ids as $idq) { foreach ((Datos::votacion($idq)['votos'] ?? []) as $vv) { if (($vv[4] ?? '') === $clave0 && in_array($vv[3], ['A', 'N'], true)) { $k0++; $c0 += $vv[3] === 'A' ? 1 : 0; } } }
        if ($c0 !== $f0['coinc'] || $k0 !== $f0['cons']) { $afMal[] = $cmA . ' control manual ' . $c0 . '/' . $k0 . ' vs ' . $f0['coinc'] . '/' . $f0['cons']; }
    }
}
ok($afMal === [], 'test de afinidad: 10 preguntas por cámara, votaciones reales del mandato actual, títulos legibles y puntajes que coinciden con el recuento manual' . ($afMal ? ': ' . implode(' | ', array_slice($afMal, 0, 3)) : ''));
$afAv = []; set_error_handler(static function ($n, $m, $f, $ln) use (&$afAv) { $afAv[] = basename($f) . ':' . $ln . ' ' . $m; return true; });
$pqD = afinidad_preguntas('Diputados'); $respD = []; foreach (array_slice($pqD, 0, 6) as $ii => $qq) { $respD[$qq['id']] = $ii % 2 ? 'n' : 'a'; }
foreach ([['indice', null], ['form', null], ['form', $respD]] as [$moA, $rsA]) {
    ob_start(); (static function ($modo, $rsA, $pqD) { $camara = 'Diputados'; $sel = 'diputados'; $preg = $pqD; $resp = $rsA ?? []; $res = $rsA === null ? null : afinidad_calcular('Diputados', $rsA); $consulta = $rsA !== null; include __DIR__ . '/../app/Vistas/afinidad.php'; })($moA, $rsA, $pqD); $hA = (string)ob_get_clean();
    if (strlen($hA) < 1500) { $afAv[] = 'afinidad ' . $moA . ' casi vacía'; }
}
restore_error_handler();
ok($afAv === [] && str_contains($vl('metodologia'), 'id="afinidad"'), 'las tres pantallas del test de afinidad se renderizan sin avisos y la metodología explica cómo se eligen las preguntas' . ($afAv ? ': ' . implode(' | ', array_slice($afAv, 0, 2)) : ''));
// Modo TV.
$tvMal = []; $tvAv = [];
set_error_handler(static function ($n, $m, $f, $ln) use (&$tvAv) { $tvAv[] = basename($f) . ':' . $ln . ' ' . $m; return true; });
foreach ($todos as $iTv => $lgTv) {
    if ($iTv % 9 !== 0) { continue; }  // muestra: uno de cada nueve legisladores, ambas cámaras
    $diaTv = tv_diapositivas($lgTv);
    if ($diaTv[0]['tipo'] !== 'portada' || end($diaTv)['tipo'] !== 'cierre' || count($diaTv) < 3) { $tvMal[] = $lgTv['slug'] . ' diapositivas mal armadas'; }
    foreach ($diaTv as $kTv => $_) {
        $op = ['seg' => 12, 'lista' => $lgTv['camara'] === 'Senadores' ? 'senado' : 'diputados', 'pausa' => false]; $lg = $lgTv; $dia = $diaTv; $nn = $kTv + 1; $ls = tv_lista($op['lista']); $pos = 0; $sig = '/x'; $ant = '/y'; $meta = ['title' => 't', 'robots' => 'noindex, nofollow'];
        ob_start(); (static function () use ($lg, $dia, $nn, $op, $ls, $pos, $sig, $ant, $meta) { $modo = 'slide'; include __DIR__ . '/../app/Vistas/tv.php'; })(); $hTv = (string)ob_get_clean();
        if (strlen($hTv) < 900 || !str_contains($hTv, 'http-equiv="refresh"')) { $tvMal[] = $lgTv['slug'] . ' diapositiva ' . $nn . ' vacía o sin avance'; }
    }
}
restore_error_handler();
$robTv = (string)file_get_contents(__DIR__ . '/../robots.txt');
ok($tvMal === [] && $tvAv === [] && str_contains($robTv, 'Disallow: /tv/') && count(tv_lista('todos')) === count(Datos::legisladores()), 'modo TV: diapositivas completas y sin avisos en una muestra de legisladores, avance automático, lista que cubre a todos y fuera de los buscadores' . ($tvMal ? ': ' . implode(' | ', array_slice($tvMal, 0, 2)) : '') . ($tvAv ? ': ' . implode(' | ', array_slice($tvAv, 0, 2)) : ''));
$djFisMal = []; $djFisN = 0;
foreach ($todos as $l) {
    foreach (Datos::ddjj($l['camara'], $l['slug']) ?? [] as $d) {
        $f = $d['fiscal'] ?? null; if (!$f) { continue; }
        $djFisN++;
        $suma = $f['sueldos'] + $f['capitales'] + $f['empresa'] + $f['trabajo_personal'];
        if (abs($suma - $f['total']) >= 1 || abs($f['total'] - $f['deducciones'] - $f['desgravaciones'] - (float)$d['ingresos_netos']) >= 1) { $djFisMal[] = $l['slug'] . ' ' . $d['anio']; }
    }
}
ok($djFisMal === [] && $djFisN > 500, 'detalle impositivo de las declaraciones: las categorías suman el total y total - deducciones - desgravaciones = ingreso neto (' . $djFisN . ' años)' . ($djFisMal ? ': ' . implode(', ', array_slice($djFisMal, 0, 5)) : ''));
$pf = (string)file_get_contents(__DIR__ . '/../app/Vistas/perfil.php'); $pl = (string)file_get_contents(__DIR__ . '/../app/Vistas/_plantilla.php'); $mv = (string)file_get_contents(__DIR__ . '/../mvc.php');
ok(is_file(__DIR__ . '/../app/Vistas/correccion.php') && str_contains($mv, "'/correccion'"), 'existe la página /correccion para pedir corrección o baja de un dato');
ok(str_contains($pf, '/correccion?desde=') && str_contains($pl, 'href="/correccion"'), 'cada perfil y el pie del sitio enlazan a /correccion');
ok(str_contains((string)file_get_contents(__DIR__ . '/../app/bootstrap.php'), 'const CONTACTO_EMAIL') && !preg_match('/mailto:[^\s"\']*@/', $pf), 'el correo de contacto sale de una sola constante, no está escrito en las vistas');
ok(str_contains((string)file_get_contents(__DIR__ . '/../app/Vistas/perfil.php'), 'No es la situación de la persona ante ARCA'), 'el detalle impositivo aclara que no es la situación ante ARCA');
$parRaw = is_file(__DIR__ . '/../storage/verificado/parentescos.json') ? (string)file_get_contents(__DIR__ . '/../storage/verificado/parentescos.json') : ''; $parMal = [];
foreach (Datos::parentescos() as $pr) {
    foreach (['declarante', 'familiar'] as $kk) { $sn = str_starts_with((string)$pr[$kk], 's:'); if (!Datos::legislador($sn ? 'Senadores' : 'Diputados', $sn ? substr((string)$pr[$kk], 2) : (string)$pr[$kk])) { $parMal[] = $pr[$kk]; } }
    if ($pr['declarante'] === $pr['familiar'] || !in_array($pr['vinculo'], ['CONYUGE / CONVIVIENTE', 'HIJO/A'], true)) { $parMal[] = $pr['declarante'] . ' ' . $pr['vinculo']; }
}
ok($parMal === [] && !preg_match('/\d{11}/', $parRaw), 'vínculos familiares entre legisladores: ambos existen, el vínculo es uno de los permitidos y no hay ningún CUIT (' . count(Datos::parentescos()) . ' vínculos)' . ($parMal ? ': ' . implode(', ', array_slice($parMal, 0, 5)) : ''));
ok(str_contains($pf, 'Vínculos familiares declarados con otros legisladores') && function_exists('parentescos_legislador') && str_contains((string)file_get_contents(__DIR__ . '/../app/Vistas/metodologia.php'), 'Familiares y personal de las Cámaras'), 'los vínculos entre legisladores tienen sección en el perfil y explicación en /metodologia, y el cruce con la nómina figura como no publicado');
ok(str_contains((string)file_get_contents(__DIR__ . '/../app/Vistas/metodologia.php'), 'Declaraciones del mismo año con montos distintos'), '/metodologia explica las declaraciones del mismo año con montos distintos (' . $djIncAnios . ' casos)');

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
// Resultados electorales (DINE, escrutinio provisorio): cada elección cita su fuente oficial y sus números cierran
$elec = Datos::resultadosElecciones(); $reMal = []; $claves = [];
$senSet = [
    2023 => ['buenos-aires', 'formosa', 'jujuy', 'la-rioja', 'misiones', 'san-juan', 'san-luis', 'santa-cruz'],
    2021 => ['catamarca', 'chubut', 'cordoba', 'corrientes', 'la-pampa', 'mendoza', 'santa-fe', 'tucuman'],
    2019 => ['ciudad-autonoma-de-buenos-aires', 'chaco', 'entre-rios', 'neuquen', 'rio-negro', 'salta', 'santiago-del-estero', 'tierra-del-fuego'],
];
$senSet[2017] = $senSet[2023];
$electoresAnio = [];
foreach ($elec as $e) {
    $m = $e['_meta']; $c = $m['clave']; $claves[] = $c;
    if (count($e['provincias']) !== 24 || count(array_filter(Datos::provincias(), fn($p) => !isset($e['provincias'][$p['slug']]))) !== 0) { $reMal[] = "$c: no tiene las 24 provincias"; }
    if (!str_starts_with((string)$m['url_archivo'], 'https://www.argentina.gob.ar/') || !preg_match('/^[0-9a-f]{64}$/', (string)$m['sha256_zip']) || $m['escrutinio'] !== 'provisorio' || !str_starts_with((string)$m['pagina'], 'https://www.argentina.gob.ar/dine/')) { $reMal[] = "$c: fuente"; }
    $con = [];
    foreach ($e['provincias'] as $slug => $r) {
        foreach (['diputados', 'senadores'] as $k) {
            if (empty($r[$k])) { continue; }
            if ($k === 'senadores') { $con[] = $slug; }
            $ag = $r[$k]['agrupaciones']; $nombres = array_column($ag, 'nombre');
            if (array_sum(array_column($ag, 'votos')) !== $r[$k]['positivos'] || count($nombres) !== count(array_unique($nombres)) || $r[$k]['positivos'] + array_sum($r[$k]['otros']) !== $r[$k]['votantes'] || $r[$k]['votantes'] > $r['electores']) { $reMal[] = "$c/$slug/$k"; }
            $v = array_column($ag, 'votos'); $o = $v; rsort($o);
            if ($v !== $o) { $reMal[] = "$c/$slug/$k orden"; }
        }
        if (empty($r['diputados'])) { $reMal[] = "$c/$slug sin diputados"; }
    }
    sort($con); $esp = $senSet[$m['anio']] ?? null; sort($esp);
    if ($con !== $esp) { $reMal[] = "$c: Senado en provincias inesperadas"; }
    $tot = array_sum(array_column($e['provincias'], 'electores'));
    if ($tot < 29000000 || $tot > 36500000) { $reMal[] = "$c: electores $tot"; }
    $electoresAnio[$m['anio']][] = $tot;
}
foreach ($electoresAnio as $an => $ts) { if (count($ts) === 2 && abs($ts[0] - $ts[1]) / max($ts) > 0.03) { $reMal[] = "$an: PASO y generales difieren más de 3% en electores"; } }
sort($claves);
ok(!$reMal, 'resultados electorales: ' . count($elec) . ' elecciones con fuente oficial y huella, 24 provincias, votos que suman, sin agrupaciones repetidas, Senado solo donde se renovó y electores coherentes' . ($reMal ? ' (' . implode('; ', array_slice($reMal, 0, 4)) . ')' : ''));
ok($claves === ['generales_2017', 'generales_2019', 'generales_2021', 'generales_2023', 'paso_2017', 'paso_2019', 'paso_2021'], 'resultados electorales: están las generales 2017 a 2023 y las PASO 2017 a 2021 (la DINE no publica las PASO 2023)');

// Contacto: el teléfono de cada cámara se puede marcar (tel:) y cada interno publicado se muestra con su teléfono
foreach (['Diputados', 'Senadores'] as $cam) {
    $cc = Datos::contactoCamara($cam);
    ok(preg_match('/^\+54\d{10}$/', (string)($cc['tel'] ?? '')) === 1 && !empty($cc['telefono']) && str_starts_with((string)($cc['url'] ?? ''), 'https://'), 'contacto de ' . $cam . ': teléfono con formato internacional, para marcar, con su fuente');
}
$internos = array_filter($sen, fn($l) => !empty($l['telefono']));
ok(count($internos) >= 60 && !array_filter($internos, fn($l) => !preg_match('/^\d{3,5}( \/ \d{3,5})*$/', interno_legible($l['telefono']))), 'los internos de los senadores tienen un formato válido (' . count($internos) . ')');

// Proyectos por año: cada proyecto tiene su rol y su marca de reconocimiento, y los promedios de la cámara cierran con los proyectos de cada legislador
$rolMal = 0; $porCamAnio = ['Diputados' => [], 'Senadores' => []]; $rolSen = ['p' => 0, 'f' => 0, 'n' => 0]; $reconN = 0; $totRol = 0;
foreach ($todos as $lg) {
    $pr = Datos::proyectos($lg['slug'], $lg['camara']);
    if (!$pr) { continue; }
    foreach ($pr['proyectos'] as $x) {
        $totRol++;
        if (count($x) !== 10 || !is_array($x[9]) || !in_array($x[7], ['p', 'f', 'n'], true) || !in_array($x[8], [0, 1], true)) { $rolMal++; continue; }
        if ($lg['camara'] === 'Diputados' && $x[7] !== 'p') { $rolMal++; }
        if ($x[8] === 1 && !in_array($x[2], ['Declaracion', 'Resolucion', 'Comunicacion'], true)) { $rolMal++; }
        if ($lg['camara'] === 'Senadores') { $rolSen[$x[7]]++; }
        $reconN += $x[8];
        $porCamAnio[$lg['camara']][substr($x[1], 0, 4)] = ($porCamAnio[$lg['camara']][substr($x[1], 0, 4)] ?? 0) + 1;
    }
}
ok($rolMal === 0 && $totRol > 25000, 'cada proyecto trae su rol (propio, firmante o sin dato) y su marca de reconocimiento; en Diputados todo es propio porque el archivo no trae cofirmantes (' . $totRol . ')');
$sh = $rolSen['p'] / max(1, array_sum($rolSen));
ok($sh > 0.1 && $sh < 0.5 && $rolSen['n'] < 50 && $reconN / max(1, $totRol) > 0.1 && $reconN / max(1, $totRol) < 0.5, 'proporciones razonables: propios del Senado ' . round($sh * 100) . ' %, sin dato ' . $rolSen['n'] . ', reconocimientos ' . round($reconN * 100 / $totRol) . ' %');
$prMal = 0; $prN = 0;
foreach (['Diputados', 'Senadores'] as $cam) {
    $pm2 = Datos::proyectosPromedios($cam);
    if (empty($pm2['anios'])) { $prMal++; continue; }
    foreach ($pm2['anios'] as $anio => $v) {
        $prN++;
        $sumaCat = $v['propios'] + $v['firmante'] + $v['reconocimientos'] + $v['sin_dato'];
        if ($sumaCat !== $v['proyectos'] || $v['proyectos'] !== ($porCamAnio[$cam][$anio] ?? 0)) { $prMal++; }
        if ($v['equivalentes'] <= 0 || $v['equivalentes'] > $v['legisladores'] + 0.001) { $prMal++; }
        if ($v['promedio'] === null ? $v['equivalentes'] >= 3 : ($v['equivalentes'] < 3 || abs($v['promedio'] - $v['proyectos'] / $v['equivalentes']) > 0.01)) { $prMal++; }
        if ($v['mediana'] !== null && ($v['mediana'] < 0 || $v['mediana_n'] < 3)) { $prMal++; }
    }
}
ok($prMal === 0 && $prN > 30, 'promedios de la cámara por año: las categorías suman el total, el total coincide con los proyectos de cada legislador y promedio = proyectos / legisladores-año (' . $prN . ' años)');
$gp = grafico_proyectos(Datos::legislador('Senadores', 'carlos-mauricio-espinola'), Datos::proyectos('carlos-mauricio-espinola', 'Senadores'));
ok(str_contains($gp, 'class="g-prom"') && str_contains($gp, 'g-s-propio') && str_contains($gp, 'g-s-firmante') && str_contains($gp, 'Promedio por senador') && !str_contains($gp, '<script'), 'el gráfico de proyectos por año muestra propios, firmantes y la línea del promedio de la cámara');
$gd = grafico_proyectos(Datos::legislador('Diputados', 'pablo-ansaloni'), Datos::proyectos('pablo-ansaloni', 'Diputados'));
ok(!str_contains($gd, 'g-s-firmante') && str_contains($gd, 'no se puede distinguir a los firmantes'), 'en Diputados el gráfico aclara que el archivo oficial no permite distinguir a los firmantes');

// Glosario: cada término tiene una definición corta y clara, enlaces a secciones que existen y anclas únicas
$gl = terminos_glosario(); $glMal = 0; $rutasOk = ['/', '/votaciones', '/congreso', '/congreso/bloques', '/congreso/provincias', '/comisiones', '/patrimonio', '/leyes', '/presupuesto', '/metodologia'];
foreach ($gl as $k => [$t, $d, $u]) {
    if (!preg_match('/^[a-z0-9-]+$/', $k) || trim($t) === '' || mb_strlen($d) < 40 || mb_strlen($d) > 520 || !in_array($u, $rutasOk, true)) { $glMal++; }
}
ok($glMal === 0 && count($gl) >= 20, 'glosario: ' . count($gl) . ' términos con definición breve, ancla válida y enlace a una sección que existe');

// Dictámenes de comisión: cruzados por ID con los proyectos, con fuente y huella, y coherentes con los resultados
$pm3 = Datos::proyectosMeta(); $df = $pm3['dictamenesFuente'] ?? $pm3['dictamenes_fuente'] ?? [];
ok(preg_match('/^[0-9a-f]{64}$/', (string)($df['sha256_csv'] ?? '')) === 1 && str_starts_with((string)($df['url'] ?? ''), 'https://datos.hcdn.gob.ar/') && ($df['filas'] ?? 0) > 24000, 'dictámenes: citan su fuente oficial (datos.hcdn.gob.ar), la huella sha256 del archivo y la cantidad de filas');
$dMal = 0; $dN = 0; $dSen = 0; $tiposOk = ['od', 'sn', 'st', 'a114', 'a204'];
foreach ($todos as $lg) {
    $pr = Datos::proyectos($lg['slug'], $lg['camara']);
    foreach ($pr['proyectos'] ?? [] as $x) {
        foreach ($x[9] ?? [] as $dc) {
            $dN++;
            if ($lg['camara'] === 'Senadores') { $dSen++; }
            if (count($dc) !== 5 || !in_array($dc[1], $tiposOk, true) || ($dc[3] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dc[3])) || ($dc[1] === 'od' && $dc[0] === '')) { $dMal++; }
        }
    }
}
ok($dMal === 0 && $dSen === 0 && $dN > 1500 && ($pm3['con_dictamen'] ?? 0) > 1000, 'dictámenes: cada uno tiene comisión, tipo válido y fecha válida; ninguno se atribuye al Senado (' . $dN . ' dictámenes en proyectos de diputados actuales)');
ok(($pm3['aprobados_con_dictamen_pct'] ?? 0) > 85, 'dictámenes: coherencia con los resultados, casi todo proyecto sancionado o con media sanción tiene dictamen (' . ($pm3['aprobados_con_dictamen_pct'] ?? 0) . ' %)');
ok(dictamen_texto(['Cultura', 'od', '0109', '2026-06-05', '']) === 'Dictamen de la comisión de Cultura, Orden del Día 0109 (5 de junio de 2026)' && dictamen_texto(['', 'st', '', '', '']) === 'Pedido de tratamiento sobre tablas', 'el texto de cada dictamen se escribe en lenguaje simple, también cuando falta la comisión o la fecha');

// Presupuesto de la Cámara (programa 17): los importes cuadran con su total y la página los muestra
$pr = Datos::presupuestoCamara(); $prA = $pr['anios'] ?? []; $prMal = 0; $prCerr = 0;
foreach ($prA as $a) {
    $sumC = 0; $sumD = 0;
    foreach ($a['incisos'] as $v) { $sumC += $v[0]; $sumD += $v[2]; if ($v[0] < 0 || $v[1] < 0 || $v[2] < 0) { $prMal++; } }
    if (abs($sumC - $a['credito']) > 10 || abs($sumD - $a['devengado']) > 10 || $a['devengado'] > $a['comprometido'] + 10 || $a['devengado'] <= 0 || !preg_match('/^[0-9a-f]{64}$/', (string)$a['sha256'])) { $prMal++; }
    if ($a['cerrado']) { $prCerr++; }
}
ok($prA && $prMal === 0 && $prCerr >= 10 && count($pr['_meta']['incisos'] ?? []) === 5, 'presupuesto de la Cámara: ' . count($prA) . ' ejercicios; en cada uno los cinco tipos de gasto suman el total, sin importes negativos y con la huella del archivo original');
$prAnios = array_column($prA, 'anio');
ok($prAnios === array_values(array_unique($prAnios)) && $prAnios === (function (array $x) { sort($x); return $x; })($prAnios) && $prAnios[0] === 2016, 'presupuesto de la Cámara: un ejercicio por año, en orden, desde 2016');
ok(count(array_filter($prA, static fn($a) => $a['factor_ipc'] !== null)) >= 8 && array_filter($prA, static fn($a) => $a['anio'] === 2016 && $a['factor_ipc'] === null), 'presupuesto de la Cámara: pesos de hoy con el IPC donde hay los 12 meses; 2016 queda sin convertir (la serie empieza en dic. 2016)');
ok(is_file(__DIR__ . '/../app/Vistas/presupuesto.php') && str_contains((string)file_get_contents(__DIR__ . '/../mvc.php'), "'/presupuesto'") && isset(terminos_glosario()['devengado']), 'presupuesto de la Cámara: tiene página, ruta y término en el glosario');

// Documentación y código no se desfasan: todo script que CLAUDE.md nombra existe (un PR armado sobre una base vieja ya borró uno en silencio)
$doc = (string)file_get_contents(__DIR__ . '/../CLAUDE.md'); $faltan = [];
preg_match_all('/`([a-z_]+\.py)`/', $doc, $mm);
foreach (array_unique($mm[1]) as $py) { if (!is_file(__DIR__ . '/../scripts/' . $py) && !in_array($py, ['json.py'], true)) { $faltan[] = $py; } }
ok(!$faltan && count(array_unique($mm[1])) > 25, 'todo script que nombra CLAUDE.md existe en scripts/ (' . count(array_unique($mm[1])) . ' nombrados)' . ($faltan ? ': faltan ' . implode(', ', $faltan) : ''));

echo $fallos ? "\n" . count($fallos) . " controles fallaron\n" : "\nTodo en orden\n";
exit($fallos ? 1 : 0);
