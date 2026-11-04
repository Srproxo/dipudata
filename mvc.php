<?php
declare(strict_types=1);

/**
 * Front controller de DipuData. Todas las páginas pasan por acá (ver .htaccess).
 * Los datos salen de storage/verificado (app/Datos.php); no hay base de datos.
 */

require_once __DIR__ . '/app/bootstrap.php';

use App\Datos;

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($metodo, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}

$path = '/' . trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$S = '[a-z0-9]+(?:-[a-z0-9]+)*';

function ir(string $destino, int $codigo = 301): never
{
    header('Location: ' . $destino, true, $codigo);
    exit;
}

function pagina_meta(string $titulo, string $desc, string $canonical, string $seccion, array $extra = []): array
{
    return ['title' => $titulo . ' | DipuData', 'description' => $desc, 'canonical' => SITE_URL . $canonical, 'seccion' => $seccion] + $extra;
}

switch (true) {
    case $path === '/':
        $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
        $camaraSel = in_array($_GET['camara'] ?? '', ['Diputados', 'Senadores'], true) ? (string)$_GET['camara'] : '';
        $provSel = is_string($_GET['provincia'] ?? null) && Datos::provincia($_GET['provincia']) ? $_GET['provincia'] : '';
        $bloqueSel = is_string($_GET['bloque'] ?? null) && preg_match("#^{$S}$#", $_GET['bloque']) ? $_GET['bloque'] : '';
        $nq = Datos::normalizar($q);
        $nombreProv = $provSel ? Datos::provincia($provSel)['nombre'] : '';
        $lista = array_values(array_filter(Datos::legisladores(), static function (array $l) use ($nq, $camaraSel, $nombreProv, $bloqueSel): bool {
            if ($camaraSel && $l['camara'] !== $camaraSel) return false;
            if ($nombreProv && $l['provincia'] !== $nombreProv) return false;
            if ($bloqueSel && trim((string)preg_replace('/[^a-z0-9]+/', '-', Datos::normalizar($l['bloque'])), '-') !== $bloqueSel) return false;
            if ($nq !== '') {
                $hay = ' ' . Datos::normalizar($l['nombre']) . ' ';
                foreach (explode(' ', $nq) as $t) { if (!str_contains($hay, $t)) return false; }
            }
            return true;
        }));
        $filtrado = $q !== '' || $camaraSel || $nombreProv || $bloqueSel;
        render('home', compact('lista', 'q', 'camaraSel', 'provSel', 'bloqueSel'), pagina_meta(
            'Legisladores del Congreso argentino',
            'Directorio de los diputados y senadores nacionales en ejercicio: bloque, provincia y mandato, con la fuente de cada dato.',
            '/', 'legisladores', [
                'robots' => $filtrado ? 'noindex, follow' : 'index, follow, max-image-preview:large',
                'jsonld' => ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'DipuData', 'url' => SITE_URL . '/', 'inLanguage' => 'es-AR',
                    'potentialAction' => ['@type' => 'SearchAction', 'target' => SITE_URL . '/?q={q}', 'query-input' => 'required name=q']],
            ]
        ));
        break;

    case (bool)preg_match("#^/(diputado|senador)/({$S})$#", $path, $m):
        $camara = $m[1] === 'senador' ? 'Senadores' : 'Diputados';
        $l = Datos::legislador($camara, $m[2]);
        if (!$l) { render_404(); break; }
        $cargo = $camara === 'Senadores' ? 'Senador/a nacional' : 'Diputado/a nacional';
        $pest = pestanas_perfil($l);
        $tab = is_string($_GET['tab'] ?? null) ? $_GET['tab'] : 'resumen';
        if (!isset($pest[$tab])) { if (isset($_GET['tab'])) { ir(Datos::urlLegislador($l)); } $tab = 'resumen'; }
        render('perfil', ['l' => $l, 'tab' => $tab], pagina_meta(
            $l['nombre'] . ', ' . strtolower($cargo) . ' por ' . $l['provincia'] . ($tab === 'resumen' ? '' : ': ' . mb_strtolower($pest[$tab])),
            $l['nombre'] . ': ' . strtolower($cargo) . ' por ' . $l['provincia'] . ', bloque ' . $l['bloque'] . '. ' . ($tab === 'resumen' ? 'Datos del padrón oficial con su fuente.' : $pest[$tab] . ' según registros oficiales, con su fuente.'),
            Datos::urlLegislador($l) . ($tab === 'resumen' ? '' : '?tab=' . $tab), 'legisladores', [
                'jsonld' => ['@context' => 'https://schema.org', '@type' => 'Person', 'name' => $l['nombre'], 'jobTitle' => $cargo,
                    'url' => SITE_URL . Datos::urlLegislador($l), 'affiliation' => ['@type' => 'Organization', 'name' => $l['bloque']]],
            ]
        ));
        break;

    case (bool)preg_match("#^/(diputado|senador)/({$S})/proyectos$#", $path, $m):
        $camaraP = $m[1] === 'senador' ? 'Senadores' : 'Diputados';
        $m = [$m[0], $m[2], $m[1]];
        $l = Datos::legislador($camaraP, $m[1]);
        $pr = $l ? Datos::proyectos($m[1], $camaraP) : null;
        if (!$l || !$pr) { render_404(); break; }
        $tipo = is_string($_GET['tipo'] ?? null) && isset($pr['resumen']['por_tipo'][$_GET['tipo']]) ? (string)$_GET['tipo'] : '';
        $resSel = is_string($_GET['resultado'] ?? null) && isset($pr['resumen']['por_resultado'][$_GET['resultado']]) ? (string)$_GET['resultado'] : '';
        $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
        $nq = Datos::normalizar($q);
        $rolSel = in_array($_GET['rol'] ?? '', ['propio', 'firmante', 'recon', 'sin'], true) ? (string)$_GET['rol'] : '';
        $lista = array_values(array_filter($pr['proyectos'], static function (array $x) use ($tipo, $resSel, $nq, $rolSel): bool {
            if ($tipo && $x[2] !== $tipo) return false;
            if ($rolSel && categoria_proyecto($x) !== $rolSel) return false;
            if ($resSel && ($x[5] ?: 'Sin resolución registrada') !== $resSel) return false;
            if ($nq !== '') {
                $hay = ' ' . Datos::normalizar($x[4] . ' ' . $x[3]) . ' ';
                foreach (explode(' ', $nq) as $w) { if (!str_contains($hay, $w)) return false; }
            }
            return true;
        }));
        $total = count($lista);
        $paginas = max(1, (int)ceil($total / 50));
        $pagina = min($paginas, max(1, (int)($_GET['pagina'] ?? 1)));
        $lista = array_slice($lista, ($pagina - 1) * 50, 50);
        $filtrado = $tipo || $resSel || $rolSel || $q !== '' || $pagina > 1;
        render('proyectos_legislador', compact('l', 'pr', 'lista', 'tipo', 'resSel', 'rolSel', 'q', 'total', 'pagina', 'paginas'), pagina_meta(
            'Proyectos de ' . $l['nombre'],
            'Proyectos parlamentarios en los que figura ' . $l['nombre'] . ' como autor/a o firmante (' . $pr['resumen']['total'] . ') y qué pasó con ellos, según los registros oficiales del Congreso.',
            Datos::urlLegislador($l) . '/proyectos', 'legisladores', ['robots' => $filtrado ? 'noindex, follow' : 'index, follow']
        ));
        break;

    case (bool)preg_match('#^/perfil/([^/]+)$#', $path, $m):
        foreach (Datos::legisladores() as $l) {
            if ($l['slug'] === strtolower(trim($m[1]))) { ir(Datos::urlLegislador($l)); }
        }
        ir('/', 302);

    case $path === '/congreso':
        render('congreso_indice', ['secciones' => secciones_congreso()], pagina_meta(
            'Cómo funciona el Congreso argentino',
            'Qué hace cada cámara, cómo se hace una ley y quiénes integran hoy cada bloque y cada provincia en el Congreso de la Nación.',
            '/congreso', 'congreso'
        ));
        break;

    case $path === '/congreso/bloques':
        render('bloques_indice', [], pagina_meta(
            'Bloques del Congreso argentino',
            'Qué es un bloque legislativo y cuántos legisladores tiene cada uno en Diputados y en el Senado.',
            '/congreso/bloques', 'bloques'
        ));
        break;

    case (bool)preg_match("#^/congreso/bloques/(diputados|senado)/({$S})$#", $path, $m):
        $camara = $m[1] === 'senado' ? 'Senadores' : 'Diputados';
        $b = Datos::bloque($camara, $m[2]);
        if (!$b) { render_404(); break; }
        render('bloque', ['b' => $b, 'miembros' => Datos::miembros($b)], pagina_meta(
            'Bloque ' . $b['nombre'] . ' en ' . ($camara === 'Senadores' ? 'el Senado' : 'Diputados'),
            'Los ' . $b['cantidad'] . ' integrantes del bloque ' . $b['nombre'] . ' en ' . ($camara === 'Senadores' ? 'el Senado' : 'la Cámara de Diputados') . ', por provincia.',
            Datos::urlBloque($b), 'bloques'
        ));
        break;

    case $path === '/congreso/provincias':
        render('provincias_indice', [], pagina_meta(
            'Las provincias en el Congreso argentino',
            'Cuántos diputados y senadores tiene cada provincia y la Ciudad de Buenos Aires, y quiénes son.',
            '/congreso/provincias', 'provincias'
        ));
        break;

    case (bool)preg_match("#^/congreso/provincias/({$S})$#", $path, $m):
        $p = Datos::provincia($m[1]);
        if (!$p) { render_404(); break; }
        $dip = array_values(array_filter(Datos::legisladores('Diputados'), static fn($l) => $l['provincia'] === $p['nombre']));
        $sen = array_values(array_filter(Datos::legisladores('Senadores'), static fn($l) => $l['provincia'] === $p['nombre']));
        render('provincia', compact('p', 'dip', 'sen'), pagina_meta(
            'Diputados y senadores de ' . $p['nombre'],
            count($dip) . ' diputados y ' . count($sen) . ' senadores nacionales por ' . $p['nombre'] . ', con su bloque.',
            '/congreso/provincias/' . $p['slug'], 'provincias'
        ));
        break;

    case (bool)preg_match("#^/congreso/({$S})$#", $path, $m):
        $secciones = secciones_congreso();
        if (!isset($secciones[$m[1]])) { render_404(); break; }
        $s = $secciones[$m[1]];
        render('congreso_seccion', ['s' => $s, 'slug' => $m[1], 'secciones' => $secciones], pagina_meta(
            $s['titulo'], $s['bajada'], '/congreso/' . $m[1], 'congreso'
        ));
        break;

    case $path === '/senado':
        render('senado_indice', [], pagina_meta(
            'Registros del Senado de la Nación',
            'Versiones taquigráficas, decretos, resoluciones, disposiciones y licitaciones del Senado, con búsqueda por año y por palabra.',
            '/senado', 'senado'
        ));
        break;

    case (bool)preg_match("#^/senado/({$S})$#", $path, $m):
        $tipo = $m[1];
        $t = Datos::docsIndice()[$tipo] ?? null;
        if (!$t) { render_404(); break; }
        $anios = array_map('strval', array_keys($t['anios']));
        $permiteTodos = $t['total'] <= 3000;
        $anio = is_string($_GET['anio'] ?? null) ? $_GET['anio'] : '';
        if (!in_array($anio, $anios, true) && !($anio === 'todos' && $permiteTodos)) {
            $anio = $permiteTodos ? 'todos' : $anios[0];
        }
        $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
        $nq = Datos::normalizar($q);
        $items = Datos::docs($tipo, $anio);
        if ($nq !== '') {
            $items = array_values(array_filter($items, static function (array $it) use ($nq): bool {
                $hay = ' ' . Datos::normalizar(($it['n'] ?? '') . ' ' . ($it['t'] ?? '') . ' ' . ($it['e'] ?? '')) . ' ';
                foreach (explode(' ', $nq) as $w) { if (!str_contains($hay, $w)) return false; }
                return true;
            }));
        }
        $total = count($items);
        $porPagina = 50;
        $paginas = max(1, (int)ceil($total / $porPagina));
        $pagina = min($paginas, max(1, (int)($_GET['pagina'] ?? 1)));
        $items = array_slice($items, ($pagina - 1) * $porPagina, $porPagina);
        $filtrado = $q !== '' || (isset($_GET['anio']) && $_GET['anio'] !== '') || $pagina > 1;
        render('senado_docs', compact('tipo', 't', 'anio', 'q', 'pagina', 'paginas', 'total', 'items', 'anios', 'permiteTodos'), pagina_meta(
            $t['titulo'] . ' del Senado',
            $t['descripcion'] . ' Fuente: Senado de la Nación, datos abiertos.',
            '/senado/' . $tipo, 'senado', ['robots' => $filtrado ? 'noindex, follow' : 'index, follow']
        ));
        break;

    case $path === '/leyes':
        $todas = Datos::leyes();
        $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
        $anios = array_values(array_unique(array_filter(array_map(static fn($l) => substr($l[2], 0, 4), $todas))));
        $anio = is_string($_GET['anio'] ?? null) && in_array($_GET['anio'], $anios, true) ? $_GET['anio'] : '';
        $nq = Datos::normalizar($q);
        $lista = array_values(array_filter($todas, static function (array $l) use ($nq, $anio): bool {
            if ($anio && substr($l[2], 0, 4) !== $anio) return false;
            if ($nq === '') return true;
            $hay = ' ' . Datos::normalizar($l[0] . ' ' . $l[1] . ' ' . $l[3]) . ' ';
            foreach (explode(' ', $nq) as $w) { if (!str_contains($hay, $w)) return false; }
            return true;
        }));
        $total = count($lista);
        $paginas = max(1, (int)ceil($total / 50));
        $pagina = min($paginas, max(1, (int)($_GET['pagina'] ?? 1)));
        $lista = array_slice($lista, ($pagina - 1) * 50, 50);
        render('leyes', compact('lista', 'anios', 'anio', 'q', 'total', 'pagina', 'paginas'), pagina_meta(
            'Leyes sancionadas por el Congreso argentino',
            'Más de mil leyes sancionadas entre 2008 y 2020, con su sumario y sus fechas. Buscá por número, tema o palabra clave. Fuente: datos abiertos de la HCDN.',
            '/leyes', 'leyes', ['robots' => ($q !== '' || $anio || $pagina > 1) ? 'noindex, follow' : 'index, follow']
        ));
        break;

    case (bool)preg_match('#^/leyes/(\d{3,6})$#', $path, $m):
        $ley = Datos::ley((int)$m[1]);
        if (!$ley) { render_404(); break; }
        render('ley', ['ley' => $ley], pagina_meta(
            'Ley ' . number_format((int)$ley['n'], 0, ',', '.') . ': ' . mb_substr($ley['t'] ?? '', 0, 90),
            'Ley ' . $ley['n'] . ($ley['t'] ?? '' ? ': ' . mb_substr($ley['t'], 0, 150) : '') . '. Sumario y fechas según la Cámara de Diputados.',
            '/leyes/' . $ley['n'], 'leyes'
        ));
        break;

    case $path === '/subsidios':
        $idx = Datos::subsidiosIndice();
        $anios = array_keys($idx);
        $anio = is_string($_GET['anio'] ?? null) && isset($idx[$_GET['anio']]) ? $_GET['anio'] : (string)($anios[count($anios) - 2] ?? '2023');
        $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
        $nq = Datos::normalizar($q);
        $items = Datos::subsidios($anio);
        $dipSel = is_string($_GET['diputado'] ?? null) && preg_match('/^[a-z0-9-]{3,80}$/', $_GET['diputado']) ? $_GET['diputado'] : '';
        if ($dipSel) {
            $items = array_values(array_filter($items, static fn($it) => ($it['s'] ?? '') === $dipSel));
        }
        if ($nq !== '') {
            $items = array_values(array_filter($items, static function (array $it) use ($nq): bool {
                $hay = ' ' . Datos::normalizar(($it['b'] ?? '') . ' ' . ($it['d'] ?? '') . ' ' . ($it['p'] ?? '') . ' ' . ($it['a'] ?? '')) . ' ';
                foreach (explode(' ', $nq) as $w) { if (!str_contains($hay, $w)) return false; }
                return true;
            }));
        }
        $total = count($items);
        $suma = array_sum(array_column($items, 'm'));
        $paginas = max(1, (int)ceil($total / 50));
        $pagina = min($paginas, max(1, (int)($_GET['pagina'] ?? 1)));
        $items = array_slice($items, ($pagina - 1) * 50, 50);
        render('subsidios', compact('idx', 'anio', 'q', 'items', 'total', 'suma', 'pagina', 'paginas', 'dipSel'), pagina_meta(
            'Subsidios de la Cámara de Diputados',
            'Subsidios que otorgó la Cámara de Diputados entre 2020 y 2023: a quién, por cuánto y a pedido de qué diputado figura en el registro oficial.',
            '/subsidios', 'subsidios', ['robots' => ($q !== '' || $dipSel || $pagina > 1 || isset($_GET['anio'])) ? 'noindex, follow' : 'index, follow']
        ));
        break;

    case (bool)preg_match('#^/ley/(\d+)$#', $path, $m):
        ir('/leyes/' . $m[1]);

    case $path === '/comisiones':
        render('comisiones', [], pagina_meta(
            'Comisiones del Congreso argentino',
            'Las comisiones permanentes de Diputados con sus integrantes y autoridades, y las comisiones del Senado.',
            '/comisiones', 'comisiones'
        ));
        break;

    case (bool)preg_match("#^/comisiones/({$S})$#", $path, $m):
        $c = Datos::comision($m[1]);
        if (!$c) { render_404(); break; }
        render('comision', ['c' => $c], pagina_meta(
            'Comisión ' . $c['nombre'] . ' de Diputados',
            'Integrantes de la comisión ' . $c['nombre'] . ' de la Cámara de Diputados: ' . count($c['integrantes']) . ' diputados, con su cargo, bloque y provincia.',
            '/comisiones/' . $c['slug'], 'comisiones'
        ));
        break;

    case $path === '/viajes':
        $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
        $nq = Datos::normalizar($q);
        $provincias = Datos::provincias();
        $provSel = is_string($_GET['provincia'] ?? null) && Datos::provincia($_GET['provincia']) ? $_GET['provincia'] : '';
        $nombreProv = $provSel ? Datos::provincia($provSel)['nombre'] : '';
        $actual = Datos::viajes()['actual'] ?? [];
        $filas = [];
        foreach (Datos::legisladores('Diputados') as $l) {
            if (!isset($actual[$l['slug']])) continue;
            if ($nombreProv && $l['provincia'] !== $nombreProv) continue;
            if ($nq !== '') {
                $hay = ' ' . Datos::normalizar($l['nombre']) . ' ';
                foreach (explode(' ', $nq) as $w) { if (!str_contains($hay, $w)) continue 2; }
            }
            $filas[] = [$l, $actual[$l['slug']]];
        }
        render('viajes', compact('filas', 'q', 'provSel', 'provincias'), pagina_meta(
            'Pasajes y misiones oficiales de los diputados',
            'Pasajes aéreos y terrestres emitidos por la Cámara de Diputados a pedido de cada diputado desde diciembre de 2025, y misiones oficiales al exterior.',
            '/viajes', 'viajes', ['robots' => ($q !== '' || $provSel) ? 'noindex, follow' : 'index, follow']
        ));
        break;

    case $path === '/remuneraciones':
        render('remuneraciones', [], pagina_meta(
            'Cuánto cobra un legislador nacional',
            'Dieta, gastos de representación, desarraigo, movilidad y descuentos que liquida la Cámara de Diputados a los legisladores nacionales.',
            '/remuneraciones', 'remuneraciones'
        ));
        break;

    case $path === '/votaciones':
        $todas = Datos::votaciones();
        $anios = array_values(array_unique(array_map(static fn($v) => substr($v['f'], 0, 4), $todas)));
        $anio = is_string($_GET['anio'] ?? null) && in_array($_GET['anio'], $anios, true) ? $_GET['anio'] : '';
        $res = in_array($_GET['resultado'] ?? '', ['AFIRMATIVO', 'NEGATIVO'], true) ? (string)$_GET['resultado'] : '';
        $cam = in_array($_GET['camara'] ?? '', ['D', 'S'], true) ? (string)$_GET['camara'] : '';
        $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
        $nq = Datos::normalizar($q);
        $lista = array_values(array_filter($todas, static function (array $v) use ($anio, $res, $nq, $cam): bool {
            if ($cam && ($v['c'] ?? 'D') !== $cam) return false;
            if ($anio && substr($v['f'], 0, 4) !== $anio) return false;
            if ($res && $v['res'] !== $res) return false;
            if ($nq !== '') {
                $hay = ' ' . Datos::normalizar($v['t']) . ' ';
                foreach (explode(' ', $nq) as $w) { if (!str_contains($hay, $w)) return false; }
            }
            return true;
        }));
        $total = count($lista);
        $paginas = max(1, (int)ceil($total / 50));
        $pagina = min($paginas, max(1, (int)($_GET['pagina'] ?? 1)));
        $lista = array_slice($lista, ($pagina - 1) * 50, 50);
        $filtrado = $anio || $res || $cam || $q !== '' || $pagina > 1;
        render('votaciones', compact('lista', 'anios', 'anio', 'res', 'q', 'total', 'pagina', 'paginas', 'cam'), pagina_meta(
            'Votaciones nominales del Congreso argentino',
            'Votaciones nominales del Senado (desde 2005) y de Diputados (desde 2011), con su resultado y cómo votó cada legislador.',
            '/votaciones', 'votaciones', ['robots' => $filtrado ? 'noindex, follow' : 'index, follow']
        ));
        break;

    case (bool)preg_match('#^/votaciones/(S?\d{1,6})$#', $path, $m):
        $cab = null;
        foreach (Datos::votaciones() as $v) { if ($v['id'] === $m[1]) { $cab = $v; break; } }
        $det = $cab ? Datos::votacion($m[1]) : null;
        if (!$cab || !$det) { render_404(); break; }
        $filtro = in_array($_GET['voto'] ?? '', ['A', 'N', 'B', 'U'], true) ? (string)$_GET['voto'] : '';
        render('votacion', compact('cab', 'det', 'filtro'), pagina_meta(
            'Votación del ' . fecha_es($cab['f']) . ': ' . mb_substr($cab['t'], 0, 80),
            $cab['t'] . ' Resultado: ' . strtolower(strip_tags(resultado_votacion((string)$cab['res']))) . ' (' . $cab['a'] . ' afirmativos, ' . $cab['n'] . ' negativos). Cómo votó cada diputado.',
            '/votaciones/' . $m[1], 'votaciones', ['robots' => $filtro ? 'noindex, follow' : 'index, follow']
        ));
        break;

    case $path === '/metodologia':
        render('metodologia', [], pagina_meta(
            'Metodología y fuentes',
            'De dónde salen los datos de DipuData, qué retiramos de la versión anterior y qué se publica a continuación.',
            '/metodologia', 'metodologia'
        ));
        break;

    case (bool)preg_match('#^/blog(/|$)#', $path):
        ir('/congreso');

    case $path === '/aportes/empresas':
        $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
        $nq = Datos::normalizar($q);
        $empresas = array_values(array_filter(Datos::aportes()['empresas'] ?? [], static fn($e) => $nq === '' || str_contains(Datos::normalizar($e['nombre'] . ' ' . ($e['actividad'] ?? '')), $nq)));
        render('aportes_empresas', compact('empresas', 'q'), pagina_meta(
            'Empresas que aportan a los partidos políticos',
            'Qué empresas y entidades hicieron aportes declarados ante la Cámara Nacional Electoral, a cuántas agrupaciones y por cuánto, con su actividad principal.',
            '/aportes/empresas', 'aportes', ['robots' => ($q !== '' || isset($_GET['todas'])) ? 'noindex, follow' : 'index, follow']
        ));
        break;

    case (bool)preg_match("#^/aportes/empresas/({$S})$#", $path, $m):
        $emp = Datos::empresaAportante($m[1]);
        if (!$emp) { render_404(); break; }
        render('aportes_empresa', ['emp' => $emp], pagina_meta(
            $emp['nombre'] . ': aportes a partidos políticos',
            $emp['nombre'] . ' declaró ' . $emp['aportes'] . ' aportes a ' . $emp['agrupaciones'] . ' agrupaciones políticas ante la Cámara Nacional Electoral.',
            '/aportes/empresas/' . $emp['slug'], 'aportes'
        ));
        break;

    case $path === '/aportes':
        $destinos = Datos::aportes()['destinos'] ?? [];
        if (!$destinos) { render_404(); break; }
        $cod = is_string($_GET['destino'] ?? null) && ctype_digit($_GET['destino']) ? (int)$_GET['destino'] : 0;
        $dest = null;
        foreach ($destinos as $d) { if ($d['cod'] === $cod) { $dest = $d; break; } }
        if (!$dest) { foreach ($destinos as $d) { if ($d['tipo'] === 'campaña') { $dest = $d; break; } } }
        $distritos = array_values(array_unique(array_column($dest['agrupaciones'], 'distrito'))); sort($distritos);
        $distSel = in_array($_GET['distrito'] ?? '', $distritos, true) ? (string)$_GET['distrito'] : '';
        $agSel = null;
        foreach ($dest['agrupaciones'] as $a) { if ($a['slug'] === ($_GET['agrupacion'] ?? null)) { $agSel = $a; break; } }
        render('aportes', compact('destinos', 'dest', 'distritos', 'distSel', 'agSel'), pagina_meta(
            'Aportes privados a los partidos políticos',
            'Cuánto recibió cada partido y alianza en aportes privados declarados ante la Cámara Nacional Electoral, por campaña y por año.',
            '/aportes', 'aportes', ['robots' => (isset($_GET['destino']) || $distSel || isset($_GET['todas']) || $agSel) ? 'noindex, follow' : 'index, follow']
        ));
        break;

    case $path === '/match':
        $pesos = []; $consulta = false;
        foreach (temas_match() as $clave => $tema) {
            $v = $_GET[$clave] ?? '0';
            $pesos[$tema] = is_string($v) && preg_match('/^[0-3]$/', $v) ? (int)$v : 0;
            if (isset($_GET[$clave])) { $consulta = true; }
        }
        $camara = ['diputados' => 'Diputados', 'senado' => 'Senadores'][$_GET['camara'] ?? ''] ?? '';
        if (isset($_GET['camara'])) { $consulta = true; }
        $filas = array_sum($pesos) > 0 ? match_temas($pesos, $camara) : [];
        render('match', compact('pesos', 'camara', 'filas'), pagina_meta(
            'Match por temas: quién trabaja los temas que te importan',
            'Marcá qué temas te importan y mirá qué diputados y senadores concentran sus proyectos en esos temas. Con datos oficiales de giros a comisiones.',
            '/match', 'match', ['robots' => $consulta ? 'noindex, follow' : 'index, follow']
        ));
        break;

    case $path === '/patrimonio':
        $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
        $nq = Datos::normalizar($q);
        $camaraSel = in_array($_GET['camara'] ?? '', ['Diputados', 'Senadores'], true) ? (string)$_GET['camara'] : '';
        $provSel = is_string($_GET['provincia'] ?? null) && Datos::provincia($_GET['provincia']) ? $_GET['provincia'] : '';
        $nombreProv = $provSel ? Datos::provincia($provSel)['nombre'] : '';
        $bloqueSel = is_string($_GET['bloque'] ?? null) && preg_match("#^{$S}$#", $_GET['bloque']) ? $_GET['bloque'] : '';
        $ordenes = ['nombre', 'anio', 'corriente', 'hoy', 'usd', 'var_hoy', 'var_usd'];
        $orden = in_array($_GET['orden'] ?? '', $ordenes, true) ? (string)$_GET['orden'] : 'hoy';
        $dir = ($_GET['dir'] ?? '') === 'asc' ? 'asc' : 'desc';
        $todosLeg = Datos::legisladores(); $conDatos = 0; $filas = [];
        foreach ($todosLeg as $lg) {
            $sp = serie_patrimonio($lg);
            if (!$sp) { continue; }
            $conDatos++;
            if ($camaraSel && $lg['camara'] !== $camaraSel) { continue; }
            if ($nombreProv && $lg['provincia'] !== $nombreProv) { continue; }
            if ($bloqueSel && trim((string)preg_replace('/[^a-z0-9]+/', '-', Datos::normalizar($lg['bloque'])), '-') !== $bloqueSel) { continue; }
            if ($nq !== '' && !str_contains(' ' . Datos::normalizar($lg['nombre']) . ' ', $nq) && !str_contains(Datos::normalizar($lg['nombre']), $nq)) { continue; }
            $filas[] = ['l' => $lg, 's' => $sp];
        }
        $valor = static fn(array $f) => match ($orden) {
            'nombre' => Datos::normalizar($f['l']['apellido'] ?? $f['l']['nombre']) . ' ' . Datos::normalizar($f['l']['nombre']),
            'anio' => $f['s']['ultimo_anio'], 'corriente' => $f['s']['ultimo'], 'hoy' => $f['s']['ultimo_hoy'], 'usd' => $f['s']['ultimo_usd'],
            'var_hoy' => $f['s']['var_hoy']['pct'] ?? null, 'var_usd' => $f['s']['var_usd']['pct'] ?? null,
        };
        // Los que no tienen el dato (sin conversión disponible) van siempre al final.
        usort($filas, static function (array $x, array $y) use ($valor, $dir): int {
            $vx = $valor($x); $vy = $valor($y);
            if ($vx === null || $vy === null) { return $vx === $vy ? 0 : ($vx === null ? 1 : -1); }
            $c = $vx <=> $vy;
            return $dir === 'asc' ? $c : -$c;
        });
        $total = count($filas);
        $paginas = max(1, (int)ceil($total / 50));
        $pagina = min($paginas, max(1, (int)($_GET['pagina'] ?? 1)));
        $filas = array_slice($filas, ($pagina - 1) * 50, 50);
        $filtrado = $q !== '' || $camaraSel || $provSel || $bloqueSel || $pagina > 1 || isset($_GET['orden']);
        render('patrimonio', compact('filas', 'total', 'conDatos', 'q', 'camaraSel', 'provSel', 'bloqueSel', 'orden', 'dir', 'pagina', 'paginas'), pagina_meta(
            'Patrimonio declarado de los legisladores: evolución y comparación',
            'Patrimonio neto declarado por diputados y senadores ante la Oficina Anticorrupción, en pesos de hoy y en dólares, con su evolución año a año y filtros por cámara, provincia y bloque.',
            '/patrimonio', 'patrimonio', ['robots' => $filtrado ? 'noindex, follow' : 'index, follow']
        ));
        break;

    case $path === '/comparar':
        $sel = [];
        foreach (['a', 'b', 'c', 'd'] as $p) {
            $lg = is_string($_GET[$p] ?? null) ? legislador_por_clave($_GET[$p]) : null;
            if ($lg && !in_array(clave_legislador($lg), array_map('clave_legislador', $sel), true)) { $sel[$p] = $lg; }
        }
        $todosLeg = Datos::legisladores();
        usort($todosLeg, static fn($x, $y) => strcmp(Datos::normalizar($x['apellido'] ?? $x['nombre']) . Datos::normalizar($x['nombre']), Datos::normalizar($y['apellido'] ?? $y['nombre']) . Datos::normalizar($y['nombre'])));
        render('comparar', compact('sel', 'todosLeg'), pagina_meta(
            'Comparar legisladores: patrimonio, votaciones y proyectos',
            'Elegí hasta cuatro diputados o senadores y compará su patrimonio declarado, cómo votaron desde el 10 de diciembre de 2025 y sus proyectos, con datos oficiales.',
            '/comparar', 'comparar', ['robots' => $sel ? 'noindex, follow' : 'index, follow']
        ));
        break;

    case (bool)preg_match('#^/(eje|ley|match-360)(/|$)#', $path):
        header('Retry-After: 86400');
        render('en_revision', [], pagina_meta('Sección en revisión', 'Estamos verificando estos datos contra las fuentes oficiales.', '/', '', ['robots' => 'noindex, follow']), 503);
        break;

    case $path === '/sitemap.xml':
        encabezados_seguridad('application/xml; charset=utf-8', 3600);
        $urls = [['/', 'daily', '1.0'], ['/congreso', 'monthly', '0.9'], ['/senado', 'weekly', '0.8'], ['/votaciones', 'monthly', '0.8'], ['/patrimonio', 'monthly', '0.8'], ['/comparar', 'monthly', '0.7'], ['/leyes', 'monthly', '0.8'], ['/comisiones', 'monthly', '0.7'], ['/viajes', 'monthly', '0.6'], ['/remuneraciones', 'monthly', '0.6'], ['/subsidios', 'monthly', '0.6'], ['/congreso/bloques', 'weekly', '0.8'], ['/congreso/provincias', 'weekly', '0.8'], ['/match', 'monthly', '0.6'], ['/aportes', 'monthly', '0.6'], ['/aportes/empresas', 'monthly', '0.5'], ['/metodologia', 'monthly', '0.5']];
        foreach (secciones_congreso() as $k => $_) { $urls[] = ["/congreso/{$k}", 'monthly', '0.8']; }
        foreach (array_keys(Datos::docsIndice()) as $k) { $urls[] = ['/senado/' . $k, 'weekly', '0.6']; }
        foreach (Datos::comisionesDiputados() as $cm) { $urls[] = ['/comisiones/' . $cm['slug'], 'monthly', '0.5']; }
        foreach (Datos::leyes() as $l) { $urls[] = ['/leyes/' . $l[0], 'yearly', '0.4']; }
        foreach (Datos::votaciones() as $v) { $urls[] = ['/votaciones/' . $v['id'], 'yearly', '0.4']; }
        foreach (Datos::bloques() as $b) { $urls[] = [Datos::urlBloque($b), 'weekly', '0.6']; }
        foreach (Datos::provincias() as $p) { $urls[] = ['/congreso/provincias/' . $p['slug'], 'weekly', '0.6']; }
        foreach (Datos::legisladores() as $l) { $urls[] = [Datos::urlLegislador($l), 'weekly', '0.7']; }
        foreach (Datos::aportes()['empresas'] ?? [] as $em) { $urls[] = ['/aportes/empresas/' . $em['slug'], 'monthly', '0.3']; }
        $hoy = (string)(Datos::informe()['generado'] ?? date('Y-m-d'));
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$u, $f, $pr]) {
            echo '<url><loc>' . e(SITE_URL . $u) . '</loc><lastmod>' . e($hoy) . '</lastmod><changefreq>' . $f . '</changefreq><priority>' . $pr . '</priority></url>' . "\n";
        }
        echo '</urlset>';
        break;

    default:
        render_404();
}
