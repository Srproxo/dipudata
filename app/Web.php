<?php
declare(strict_types=1);

use App\Datos;

/** Helpers de presentación. Todo texto que viene de datos pasa por e(). */

function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Paleta de bloques. Coincidencia por nombre oficial; el resto usa grises distinguibles. */
function color_bloque(string $oficial): string
{
    static $mapa = [
        'LA LIBERTAD AVANZA' => '#6d28d9', 'UNIÓN POR LA PATRIA' => '#1d4ed8', 'PROVINCIAS UNIDAS' => '#0e7490',
        'PRO' => '#ca8a04', 'ARGENTINA FEDERAL' => '#be185d', 'UCR - UNIÓN CÍVICA RADICAL' => '#dc2626',
        'JUSTICIALISTA' => '#2563eb', 'COALICION CIVICA' => '#059669', 'COALICIÓN CÍVICA' => '#059669',
    ];
    if (isset($mapa[$oficial])) {
        return $mapa[$oficial];
    }
    $grises = ['#475569', '#64748b', '#78716c', '#52525b', '#6b7280', '#57534e'];
    return $grises[abs(crc32($oficial)) % count($grises)];
}

function iniciales(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre)) ?: [];
    $a = mb_substr($partes[0] ?? '', 0, 1);
    $b = mb_substr(end($partes) ?: '', 0, 1);
    return mb_strtoupper($a . $b, 'UTF-8');
}

function fecha_es(?string $iso): string
{
    if (!$iso || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) {
        return '—';
    }
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return (int)$m[3] . ' de ' . $meses[(int)$m[2]] . ' de ' . $m[1];
}

function avatar(array $l, int $px = 40): string
{
    $t = e($l['nombre']);
    if (!empty($l['foto'])) {
        return '<img class="avatar" src="' . e($l['foto']) . '" alt="" width="' . $px . '" height="' . $px . '" loading="lazy" decoding="async">';
    }
    return '<span class="avatar avatar-vacio" style="width:' . $px . 'px;height:' . $px . 'px" aria-hidden="true">' . e(iniciales($l['nombre'])) . '</span>';
}

function etiqueta_bloque(array $l): string
{
    return '<span class="bloque"><i style="background:' . e(color_bloque($l['bloque_oficial'])) . '"></i>' . e($l['bloque']) . '</span>';
}

/** Barra de composición de una cámara. */
function barra_composicion(array $bloques): string
{
    $total = max(1, array_sum(array_column($bloques, 'cantidad')));
    $h = '<div class="barra" role="img" aria-label="Bancas por bloque">';
    foreach ($bloques as $b) {
        $h .= '<span style="width:' . round($b['cantidad'] / $total * 100, 3) . '%;background:' . e(color_bloque($b['nombre_oficial'])) . '" title="' . e($b['nombre']) . ': ' . (int)$b['cantidad'] . '"></span>';
    }
    return $h . '</div>';
}

/**
 * Posiciones de las bancas de un hemiciclo de $n bancas, en filas concéntricas.
 * Devuelve [lista de [x, y, ángulo], radio de cada banca]; coordenadas con radio exterior 1 y centro (0, 0).
 */
function hemiciclo_posiciones(int $n): array
{
    $interior = 0.4;
    for ($filas = 2; $filas < 30; $filas++) {
        $paso = (1 - $interior) / ($filas - 1);
        $radios = [];
        $cap = 0;
        for ($i = 0; $i < $filas; $i++) {
            $radios[$i] = $interior + $i * $paso;
            $cap += (int)floor(M_PI * $radios[$i] / $paso) + 1;
        }
        if ($cap >= $n) {
            break;
        }
    }
    // Reparto proporcional al radio (resto mayor), sin pasar la capacidad de cada fila.
    $suma = array_sum($radios);
    $cant = [];
    $restos = [];
    foreach ($radios as $i => $r) {
        $q = $n * $r / $suma;
        $cant[$i] = (int)floor($q);
        $restos[$i] = $q - $cant[$i];
    }
    arsort($restos);
    $faltan = $n - array_sum($cant);
    foreach (array_keys($restos) as $i) {
        if ($faltan-- <= 0) {
            break;
        }
        $cant[$i]++;
    }
    $pos = [];
    foreach ($radios as $i => $r) {
        $k = $cant[$i];
        for ($j = 0; $j < $k; $j++) {
            $a = $k === 1 ? M_PI / 2 : M_PI - $j * M_PI / ($k - 1);
            $pos[] = [$r * cos($a), -$r * sin($a), $a, $r];
        }
    }
    // De izquierda a derecha; a igual ángulo, de adentro hacia afuera. Así cada bloque queda en una porción.
    usort($pos, static fn($p, $q) => abs($p[2] - $q[2]) > 1e-9 ? $q[2] <=> $p[2] : $p[3] <=> $q[3]);
    return [$pos, $paso * 0.44];
}

/**
 * Hemiciclo en SVG: una banca por legislador, con su foto y un anillo del color del bloque.
 * Los bloques van en el orden recibido (de mayor a menor), de izquierda a derecha.
 * El orden no representa una ubicación ideológica ni la disposición real del recinto.
 */
function hemiciclo(array $bloques, string $id): string
{
    $sillas = [];
    foreach ($bloques as $bi => $b) {
        $m = Datos::miembros($b);
        usort($m, static fn($x, $y) => strcmp(Datos::normalizar($x['apellido'] ?? $x['nombre']), Datos::normalizar($y['apellido'] ?? $y['nombre'])));
        foreach ($m as $l) {
            $sillas[] = [$l, $b, $bi];
        }
    }
    $n = count($sillas);
    if ($n === 0) {
        return '';
    }
    [$pos, $rs] = hemiciclo_posiciones($n);
    $R = 500.0;
    $r = $rs * $R;
    $margen = $r + 4;
    $ancho = 2 * ($R + $margen);
    $alto = $R + 2 * $margen;
    $cx = $ancho / 2;
    $cy = $R + $margen;
    $f = static fn(float $v) => rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    $camara = $bloques[0]['camara'] === 'Senadores' ? 'senadores' : 'diputados';
    $h = '<svg class="hemiciclo" id="' . e($id) . '" viewBox="0 0 ' . $f($ancho) . ' ' . $f($alto) . '" role="group" aria-label="Hemiciclo: ' . $n . ' ' . $camara . ', agrupados por bloque">';
    $h .= '<defs><clipPath id="' . e($id) . '-c" clipPathUnits="objectBoundingBox"><circle cx=".5" cy=".5" r=".5"/></clipPath></defs>';
    foreach ($sillas as $i => [$l, $b, $bi]) {
        $x = $cx + $pos[$i][0] * $R;
        $y = $cy + $pos[$i][1] * $R;
        $color = color_bloque($b['nombre_oficial']);
        $nombre = $l['nombre'] . ' · ' . $b['nombre'] . ' · ' . $l['provincia'];
        $h .= '<a href="' . e(Datos::urlLegislador($l)) . '" class="b' . $bi . '" aria-label="' . e($nombre) . '"><title>' . e($nombre) . '</title>';
        $h .= '<circle cx="' . $f($x) . '" cy="' . $f($y) . '" r="' . $f($r) . '" fill="#e2e8f0"/>';
        if (!empty($l['foto'])) {
            $ri = $r - 2.5;
            $h .= '<image href="' . e($l['foto']) . '" x="' . $f($x - $ri) . '" y="' . $f($y - $ri) . '" width="' . $f(2 * $ri) . '" height="' . $f(2 * $ri) . '" preserveAspectRatio="xMidYMin slice" clip-path="url(#' . e($id) . '-c)"/>';
        } else {
            $h .= '<text x="' . $f($x) . '" y="' . $f($y) . '" dy=".35em" text-anchor="middle" font-size="' . $f($r * 0.7) . '" fill="#334155">' . e(iniciales($l['nombre'])) . '</text>';
        }
        $h .= '<circle class="anillo" cx="' . $f($x) . '" cy="' . $f($y) . '" r="' . $f($r - 1.5) . '" fill="none" stroke="' . e($color) . '" stroke-width="' . $f(max(2.5, $r * 0.16)) . '"/></a>';
    }
    $h .= '<text x="' . $f($cx) . '" y="' . $f($cy - $R * 0.12) . '" text-anchor="middle" class="hemiciclo-total">' . $n . '</text>';
    $h .= '<text x="' . $f($cx) . '" y="' . $f($cy - $R * 0.03) . '" text-anchor="middle" class="hemiciclo-sub">bancas · quórum ' . (intdiv($n, 2) + 1) . '</text>';
    return $h . '</svg>';
}

function encabezados_seguridad(string $tipo = 'text/html; charset=utf-8', int $maxAge = 300): void
{
    header('Content-Type: ' . $tipo);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
    header('Strict-Transport-Security: max-age=63072000; includeSubDomains');
    // Sin scripts ni recursos externos, salvo Google Analytics cuando está configurado (solo se carga con el consentimiento de la persona).
    $csp = GA_ID === ''
        ? "default-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'"
        : "default-src 'none'; script-src 'self' https://www.googletagmanager.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://*.google-analytics.com https://*.googletagmanager.com; connect-src https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'";
    header('Content-Security-Policy: ' . $csp);
    header($maxAge > 0 ? "Cache-Control: public, max-age={$maxAge}, s-maxage={$maxAge}" : 'Cache-Control: no-store');
}

/**
 * Renderiza una vista dentro de la plantilla común.
 * $meta: title, description, canonical, seccion, robots?, jsonld?
 */
function render(string $vista, array $vars, array $meta, int $status = 200): void
{
    http_response_code($status);
    encabezados_seguridad('text/html; charset=utf-8', $status === 200 ? 300 : 0);
    $meta += ['robots' => 'index, follow, max-image-preview:large', 'seccion' => '', 'jsonld' => null];
    extract($vars, EXTR_SKIP);
    ob_start();
    require __DIR__ . "/Vistas/{$vista}.php";
    $contenido = (string)ob_get_clean();
    require __DIR__ . '/Vistas/_plantilla.php';
}

function render_404(): void
{
    render('404', [], [
        'title' => 'Página no encontrada | DipuData', 'description' => 'La página que buscás no existe.',
        'canonical' => SITE_URL . '/', 'robots' => 'noindex, follow',
    ], 404);
}

function css_version(): string
{
    return (string)(@filemtime(RAIZ . '/assets/css/dipudata.css') ?: 1);
}

function slug_provincia(string $nombre): string
{
    foreach (Datos::provincias() as $p) {
        if ($p['nombre'] === $nombre) {
            return $p['slug'];
        }
    }
    return '';
}

function monto_ars(string $n): string
{
    return is_numeric($n) ? '$ ' . number_format((float)$n, 2, ',', '.') : '';
}

/** Enlace de paginación conservando los filtros. */
function url_con(array $cambios): string
{
    $q = array_filter(array_merge($_GET, $cambios), static fn($v) => $v !== '' && $v !== null);
    return strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?') . ($q ? '?' . http_build_query($q) : '');
}

/** 'ABDALA DE MATARAZZO Norma Amanda' -> 'Norma Amanda Abdala de Matarazzo' */
function nombre_acta(string $n): string
{
    $ap = [];
    $no = [];
    foreach (preg_split('/\s+/', trim($n)) ?: [] as $w) {
        if ($w === mb_strtoupper($w, 'UTF-8') && preg_match('/\p{L}/u', $w)) { $ap[] = $w; } else { $no[] = $w; }
    }
    $t = static function (array $ws): string {
        $min = ['de', 'del', 'la', 'las', 'los', 'y', 'e'];
        $o = [];
        foreach ($ws as $i => $w) {
            $l = mb_strtolower($w, 'UTF-8');
            $o[] = ($i > 0 && in_array($l, $min, true)) ? $l : mb_convert_case($l, MB_CASE_TITLE, 'UTF-8');
        }
        return implode(' ', $o);
    };
    return trim(implode(' ', array_filter([implode(' ', $no), $t($ap)])));
}

function etiqueta_voto(string $c): string
{
    return ['A' => 'Afirmativo', 'N' => 'Negativo', 'B' => 'Abstención', 'U' => 'Ausente', 'P' => 'Preside', 'E' => 'No emite voto', 'S' => 'Sin votar', 'X' => 'Sin voto registrado'][$c] ?? $c;
}

function etiqueta_tipo(string $t): string
{
    return ['Declaracion' => 'Declaración', 'Resolucion' => 'Resolución', 'Comunicacion' => 'Comunicación', 'Ley' => 'Proyecto de ley'][$t] ?? $t;
}

/** Resultado de una votación: etiqueta y clase de color (también empate, o sin resultado publicado). */
function resultado_votacion(string $r): string
{
    [$clase, $t] = match ($r) {
        'AFIRMATIVO' => ['si', 'Afirmativo'],
        'NEGATIVO' => ['no', 'Negativo'],
        'EMPATE' => ['neutro', 'Empate'],
        default => ['neutro', 'Sin resultado publicado'],
    };
    return '<b class="res-' . $clase . '">' . $t . '</b>';
}

/** Monto abreviado para ejes de gráficos: "$ 207,3 M". */
function monto_corto(float $n, string $moneda = '$'): string
{
    $a = abs($n);
    [$div, $suf] = $a >= 1e9 ? [1e9, ' mil M'] : ($a >= 1e6 ? [1e6, ' M'] : ($a >= 1e3 ? [1e3, ' mil'] : [1, '']));
    return ($n < 0 ? '−' : '') . $moneda . ' ' . number_format($a / $div, $a / $div >= 100 || $div === 1 ? 0 : 1, ',', '.') . $suf;
}

/**
 * Gráfico de línea en SVG, sin JavaScript. $puntos: [etiqueta_x => valor]. El eje Y empieza en 0 (o en el mínimo si hay negativos)
 * y no se suaviza la línea. $titulo describe el gráfico en palabras (lectores de pantalla).
 * $segunda (opcional): otra serie con las mismas etiquetas (puede cubrir solo algunas), dibujada con línea punteada.
 */
function svg_linea(array $puntos, string $titulo, string $moneda = '$', array $segunda = []): string
{
    if (count($puntos) < 2) {
        return '';
    }
    $w = 640; $h = 260; $izq = 84; $der = 16; $arr = 14; $aba = 30;
    $vals = array_map('floatval', array_values($puntos));
    $todos = array_merge($vals, array_map('floatval', array_values($segunda)));
    $min = min(0.0, min($todos)); $max = max($todos);
    if ($max <= $min) { $max = $min + 1; }
    $n = count($vals);
    $x = static fn(int $i): float => $izq + ($w - $izq - $der) * ($n === 1 ? 0.5 : $i / ($n - 1));
    $y = static fn(float $v): float => $arr + ($h - $arr - $aba) * (1 - ($v - $min) / ($max - $min));
    $s = '<svg class="grafico" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-labelledby="g-' . md5($titulo) . '">'
       . '<title id="g-' . md5($titulo) . '">' . e($titulo) . '</title>';
    for ($k = 0; $k <= 4; $k++) {
        $v = $min + ($max - $min) * $k / 4; $yy = round($y($v), 1);
        $s .= '<line class="g-guia" x1="' . $izq . '" x2="' . ($w - $der) . '" y1="' . $yy . '" y2="' . $yy . '"/>'
            . '<text class="g-eje" x="' . ($izq - 8) . '" y="' . ($yy + 4) . '" text-anchor="end">' . e(monto_corto($v, $moneda)) . '</text>';
    }
    $camino = [];
    $i = 0;
    $paso = max(1, (int)ceil($n / 8));
    $etiquetas = '';
    foreach ($puntos as $et => $v) {
        $px = round($x($i), 1); $py = round($y((float)$v), 1);
        $camino[] = ($i ? 'L' : 'M') . $px . ' ' . $py;
        $etiquetas .= '<circle class="g-punto" cx="' . $px . '" cy="' . $py . '" r="3.5"/>';
        if (($i % $paso === 0 && ($n - 1 - $i) >= (int)ceil($paso / 2)) || $i === $n - 1) {
            $etiquetas .= '<text class="g-eje" x="' . $px . '" y="' . ($h - 8) . '" text-anchor="middle">' . e((string)$et) . '</text>';
        }
        $i++;
    }
    $s .= '<path class="g-linea" d="' . implode(' ', $camino) . '"/>';
    if ($segunda) {
        $camino2 = []; $puntos2 = ''; $i = 0; $seguido = false;
        foreach (array_keys($puntos) as $et) {
            if (isset($segunda[$et])) {
                $px = round($x($i), 1); $py = round($y((float)$segunda[$et]), 1);
                $camino2[] = ($seguido ? 'L' : 'M') . $px . ' ' . $py;
                $puntos2 .= '<rect class="g-punto-2" x="' . ($px - 3) . '" y="' . ($py - 3) . '" width="6" height="6"/>';
                $seguido = true;
            } else {
                $seguido = false;
            }
            $i++;
        }
        $s .= '<path class="g-linea-2" d="' . implode(' ', $camino2) . '"/>' . $puntos2;
    }
    return $s . $etiquetas . '</svg>';
}

/** Nombre corto de un lugar del mapa de pasajes. */
function lugar_corto(array $lugar): string
{
    static $cortos = ['San Fernando del Valle de Catamarca' => 'Catamarca', 'San Miguel de Tucumán' => 'Tucumán', 'San Salvador de Jujuy' => 'Jujuy',
        'San Carlos de Bariloche' => 'Bariloche', 'San Ramón de la Nueva Orán' => 'Orán'];
    return $lugar['provincia'] === 'Ciudad Autónoma de Buenos Aires' ? 'CABA' : ($cortos[$lugar['nombre']] ?? $lugar['nombre']);
}

/**
 * Mapa de la Argentina con las rutas de los pasajes de un diputado (SVG en línea, sin scripts).
 * Cada ruta es una curva entre los centros de las dos localidades; el grosor crece con la cantidad de tramos.
 * Aéreos: línea llena del acento, curvada hacia un lado; terrestres: línea discontinua, curvada hacia el otro.
 */
function svg_rutas(array $r, string $titulo): string
{
    $w = (int)$r['meta']['ancho']; $h = (int)$r['meta']['alto'];
    $id = 'm-' . md5($titulo);
    $s = '<svg class="mapa" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-labelledby="' . $id . '"><title id="' . $id . '">' . e($titulo) . '</title>';
    foreach ($r['provincias'] as $p) {
        $s .= '<path class="m-prov" d="' . e($p['d']) . '"/>';
    }
    $max = max(array_map(static fn(array $x): int => $x['ida'] + $x['vuelta'], $r['rutas']));
    $usados = [];
    $lineas = '';
    // Las más frecuentes se dibujan al final, por encima.
    foreach (array_reverse($r['rutas']) as $x) {
        $a = $r['lugares'][$x['a']]; $b = $r['lugares'][$x['b']];
        $usados[$x['a']] = $a; $usados[$x['b']] = $b;
        [$x1, $y1] = $a['xy']; [$x2, $y2] = $b['xy'];
        $k = $x['tipo'] === 'aereo' ? 0.15 : -0.15;  // punto de control corrido en perpendicular al tramo
        $cx = round(($x1 + $x2) / 2 - ($y2 - $y1) * $k, 1);
        $cy = round(($y1 + $y2) / 2 + ($x2 - $x1) * $k, 1);
        $n = $x['ida'] + $x['vuelta'];
        $grosor = round(1.25 + 3.5 * sqrt($n / $max), 2);
        $lineas .= '<path class="m-ruta' . ($x['tipo'] === 'aereo' ? '' : ' m-ruta-t') . '" style="stroke-width:' . $grosor . '" d="M' . $x1 . ' ' . $y1 . 'Q' . $cx . ' ' . $cy . ' ' . $x2 . ' ' . $y2 . '">'
            . '<title>' . e(lugar_corto($a) . ' – ' . lugar_corto($b) . ($x['tipo'] === 'aereo' ? ', aéreo: ' : ', terrestre: ') . $n . ($n === 1 ? ' tramo' : ' tramos')) . '</title></path>';
    }
    $s .= $lineas;
    // Puntos y nombres; un nombre que se superpone con otro ya escrito se omite (figura en la tabla).
    $cajas = [];
    $puntos = ''; $nombres = '';
    foreach ($usados as $lugar) {
        [$px, $py] = $lugar['xy'];
        $puntos .= '<circle class="m-punto" cx="' . $px . '" cy="' . $py . '" r="3"><title>' . e($lugar['nombre'] . ', ' . $lugar['provincia']) . '</title></circle>';
    }
    uasort($usados, static fn(array $p, array $q): int => $p['xy'][1] <=> $q['xy'][1]);
    foreach ($usados as $lugar) {
        [$px, $py] = $lugar['xy'];
        $txt = lugar_corto($lugar);
        $ancho = mb_strlen($txt) * 6.4;
        $izq = $px + 6 + $ancho > $w - 2;
        $bx = $izq ? $px - 6 - $ancho : $px + 6;
        $caja = [$bx, $py - 9, $bx + $ancho, $py + 4];
        $choca = false;
        foreach ($cajas as $c) {
            if ($caja[0] < $c[2] && $caja[2] > $c[0] && $caja[1] < $c[3] && $caja[3] > $c[1]) { $choca = true; break; }
        }
        if ($choca || $caja[0] < 1) {
            continue;
        }
        $cajas[] = $caja;
        $nombres .= '<text class="m-etq" x="' . round($izq ? $px - 6 : $px + 6, 1) . '" y="' . round($py + 4, 1) . '"' . ($izq ? ' text-anchor="end"' : '') . '>' . e($txt) . '</text>';
    }
    return $s . $puntos . $nombres . '</svg>';
}

/** Pestañas del perfil que tienen datos para este legislador: clave => título. "resumen" siempre está. */
function pestanas_perfil(array $l): array
{
    $esSen = $l['camara'] === 'Senadores';
    $ej = \App\Datos::ejes($l['camara'], $l['slug']);
    $vj = $esSen ? [] : \App\Datos::viajes();
    $p = ['resumen' => 'Resumen'];
    if (\App\Datos::mandatoActual($l['camara'], $l['slug']) || ($esSen ? \App\Datos::senadoAnteriores($l['slug']) : \App\Datos::participacion($l['slug']))) {
        $p['votaciones'] = 'Votaciones';
    }
    if (\App\Datos::proyectos($l['slug'], $l['camara']) || ($ej['con_eje'] ?? 0) > 0) {
        $p['proyectos'] = 'Proyectos y temas';
    }
    // La pestaña está siempre: si no hay declaración publicada, explica por qué.
    $p['patrimonio'] = 'Declaración jurada';
    if (!$esSen && (\App\Datos::comisionesDe($l['slug']) || isset($vj['actual'][$l['slug']]) || !empty($vj['por_diputado'][$l['slug']])
            || \App\Datos::subsidiosDiputado($l['slug']) || !empty($l['trayectoria']))) {
        $p['mas'] = 'Más datos';
    }
    return $p;
}

/** "agosto de 2026" a partir de "2026-08". */
function mes_es(string $am): string
{
    $m = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return ($m[(int)substr($am, 5, 2) - 1] ?? '') . ' de ' . substr($am, 0, 4);
}

/**
 * Montos de la declaración jurada en pesos de un mismo mes, con el IPC del INDEC: monto * IPC(último mes) / IPC(diciembre del año).
 * Devuelve [mes_base, [anio => factor]]; los años sin IPC de diciembre (anteriores a 2016) no figuran.
 */
function factores_ipc(): array
{
    $m = \App\Datos::ipc()['mensual'] ?? [];
    if (!$m) { return ['', []]; }
    $ult = array_key_last($m);
    $f = [];
    foreach ($m as $am => $v) { if (substr($am, 5, 2) === '12' && $v > 0) { $f[(int)substr($am, 0, 4)] = $m[$ult] / $v; } }
    return [$ult, $f];
}

/** Pesos por dólar oficial (BCRA) al cierre de cada año: [anio => valor]. */
function dolar_cierre(): array
{
    $f = [];
    foreach (\App\Datos::dolar()['cierre_anual'] ?? [] as $a => $c) { if (($c['valor'] ?? 0) > 0) { $f[(int)$a] = (float)$c['valor']; } }
    return $f;
}

/** Pesos por dólar MEP (ArgentinaDatos, no oficial) al cierre de cada año: [anio => valor]. */
function dolar_mep_cierre(): array
{
    $f = [];
    foreach (\App\Datos::dolarMep()['cierre_anual'] ?? [] as $a => $c) { if (($c['valor'] ?? 0) > 0) { $f[(int)$a] = (float)$c['valor']; } }
    return $f;
}

/** Monto en dólares con dos decimales: "US$ 1.234,56". */
function monto_usd(float $n): string
{
    return 'US$ ' . number_format($n, 2, ',', '.');
}

/** Claves de URL de los 11 temas del Match: clave => nombre del eje en ejes.json. */
function temas_match(): array
{
    return ['salud' => 'Salud', 'educacion' => 'Educación', 'industria' => 'Industria', 'comercio' => 'Comercio',
        'prevision' => 'Previsión Social', 'ambiente' => 'Medio Ambiente', 'seguridad' => 'Seguridad', 'genero' => 'Género',
        'federalismo' => 'Federalismo', 'transparencia' => 'Transparencia', 'tecnologia' => 'Tecnología'];
}

/**
 * Match por temas: similitud del coseno entre lo que le importa al usuario (0 a 3 por tema) y la proporción de proyectos
 * de cada legislador en cada tema. Solo legisladores con 10 proyectos o más con tema. Devuelve filas ordenadas de mayor a menor.
 */
function match_temas(array $usuario, string $camara = ''): array
{
    $temas = array_values(temas_match());
    $nu = sqrt(array_sum(array_map(static fn($v) => $v * $v, $usuario)));
    if ($nu == 0) { return []; }
    $filas = [];
    foreach (\App\Datos::legisladores($camara ?: null) as $l) {
        $e = \App\Datos::ejes($l['camara'], $l['slug']);
        if (!$e || $e['con_eje'] < 10) { continue; }
        $vec = []; foreach ($temas as $t) { $vec[$t] = ($e['ejes'][$t]['proyectos'] ?? 0) / $e['con_eje']; }
        $nl = sqrt(array_sum(array_map(static fn($v) => $v * $v, $vec)));
        if ($nl == 0) { continue; }
        $dot = 0.0; foreach ($temas as $t) { $dot += ($usuario[$t] ?? 0) * $vec[$t]; }
        $filas[] = ['l' => $l, 'afinidad' => $dot / ($nu * $nl), 'vec' => $vec, 'con_eje' => $e['con_eje']];
    }
    usort($filas, static fn($a, $b) => $b['afinidad'] <=> $a['afinidad'] ?: strcmp($a['l']['nombre'], $b['l']['nombre']));
    return $filas;
}

/**
 * Qué decir de las declaraciones juradas de un legislador: cuántas figuran, de qué años, cuáles faltan en el medio y,
 * si no hay ninguna, el motivo más probable según cuándo asumió. Solo habla de lo que figura en el archivo publicado.
 */
function estado_ddjj(array $l, array $dj): array
{
    $es = \App\Datos::ddjjEstado($l['camara'], $l['slug']);
    $sinTot = $es['sin_totales'] ?? [];
    if (!$dj) {
        return ['hay' => false, 'asumio_2025' => !empty($l['inicio']) && $l['inicio'] >= '2025-12-10', 'sin_totales' => $sinTot, 'inconsistente' => $es['inconsistente'] ?? [], 'ambiguo' => !empty($es['ambiguo'])];
    }
    $anios = array_values(array_unique(array_map(static fn($d) => (int)$d['anio'], $dj)));
    sort($anios);
    $faltan = [];
    for ($a = $anios[0]; $a <= end($anios); $a++) { if (!in_array($a, $anios, true)) { $faltan[] = $a; } }
    // Los años que figuran sin totales utilizables no cuentan como "faltantes": se dicen aparte.
    $sinAnios = array_map(static fn($x) => (int)$x['anio'], $sinTot);
    return ['hay' => true, 'desde' => $anios[0], 'hasta' => end($anios), 'cantidad' => count($anios), 'faltan' => array_values(array_diff($faltan, $sinAnios)), 'sin_totales' => $sinTot, 'inconsistente' => $es['inconsistente'] ?? []];
}

/** Perfil de un legislador en Markdown para lectores automáticos (LLM): solo datos que ya se publican, con fuente y límites. */
function perfil_markdown(array $l): string
{
    $sen = $l['camara'] === 'Senadores';
    $url = SITE_URL . \App\Datos::urlLegislador($l);
    $cargo = $sen ? 'Senador/a nacional' : 'Diputado/a nacional';
    $m = ['# ' . $l['nombre'], '', $cargo . ' por ' . $l['provincia'] . ' · bloque ' . $l['bloque'] . '.', '',
        '- Mandato: ' . ($l['mandato'] ?: 'sin dato') . ($l['jura'] ? ' · juró el ' . fecha_es($l['jura']) : ''),
        '- Perfil en la web: ' . $url, '- Datos actualizados al: ' . (\App\Datos::informe()['generado'] ?? ''), ''];
    $mv = \App\Datos::mandatoActual($l['camara'], $l['slug']);
    if ($mv) {
        $mt = \App\Datos::mandatoActualMeta();
        $m[] = '## Votaciones nominales desde el ' . fecha_es($mt['desde'] ?? '2025-12-10') . ' hasta el ' . fecha_es($mt['hasta'] ?? '');
        $m[] = '- Votó en ' . (int)$mv['emitio'] . ' de ' . (int)$mv['votaciones'] . ' votaciones (' . number_format((float)$mv['voto_pct'], 1, ',', '') . ' %): ' . (int)$mv['afirmativos'] . ' afirmativos, ' . (int)$mv['negativos'] . ' negativos, ' . (int)$mv['abstenciones'] . ' abstenciones; ausente en ' . (int)$mv['ausente'] . '.';
        if (($mv['coincidencia_bloque_n'] ?? 0) >= 20 && isset($mv['coincidencia_bloque_pct'])) { $m[] = '- Votó como la mayoría de su bloque en el ' . number_format((float)$mv['coincidencia_bloque_pct'], 0, ',', '') . ' % de las ' . (int)$mv['coincidencia_bloque_n'] . ' votaciones comparables.'; }
        $m[] = '- Fuente: votaciones nominales publicadas por la Cámara. Una cifra de votos no explica los motivos de ningún voto.'; $m[] = '';
    }
    $py = \App\Datos::proyectos($l['slug'], $l['camara']);
    if ($py && ($py['resumen']['total'] ?? 0) > 0) {
        $rs = $py['resumen'];
        $m[] = '## Proyectos presentados o firmados';
        $m[] = '- Total: ' . (int)$rs['total'] . ', entre el ' . fecha_es($rs['desde']) . ' y el ' . fecha_es($rs['hasta']) . '. Por tipo: ' . implode(', ', array_map(static fn($k, $v) => $k . ' ' . $v, array_keys($rs['por_tipo']), $rs['por_tipo'])) . '.';
        foreach (array_slice($py['proyectos'], 0, 5) as $p) { $m[] = '  - ' . fecha_es((string)$p[1]) . ' · ' . $p[2] . ' ' . $p[3] . ': ' . trim((string)$p[4]); }
        $m[] = '- Más proyectos no quiere decir más trabajo: mide cuántos presentó, no su calidad ni su resultado. Que no figure un resultado no es un rechazo.'; $m[] = '';
    }
    $dj = \App\Datos::ddjj($l['camara'], $l['slug']); $est = estado_ddjj($l, $dj);
    $m[] = '## Declaraciones juradas patrimoniales (Oficina Anticorrupción)';
    if ($dj) {
        $m[] = '| Año fiscal | Bienes | Deudas | Patrimonio neto | Ingresos netos | Tipo |'; $m[] = '|---|---|---|---|---|---|';
        foreach ($dj as $d) { $m[] = '| ' . (int)$d['anio'] . ' | ' . monto_ars((string)$d['bienes_final']) . ' | ' . monto_ars((string)$d['deudas_final']) . ' | ' . monto_ars((string)$d['patrimonio_neto_final']) . ' | ' . ($d['ingresos_netos'] !== null ? monto_ars((string)$d['ingresos_netos']) : '—') . ' | ' . $d['tipo'] . ' |'; }
        $m[] = ''; $m[] = 'Importes en pesos de cada año, tal como se declararon. El patrimonio neto es bienes menos deudas.';
    } else {
        $m[] = !empty($est['inconsistente']) ? 'El archivo oficial trae declaraciones del mismo año con montos distintos; no se publican cifras.' : (!empty($est['sin_totales']) ? 'Figura una declaración con los totales vacíos; no se publican cifras.' : (!empty($est['asumio_2025']) ? 'Asumió el 10/12/2025: su primera declaración todavía no está publicada.' : 'No figura en el archivo publicado (no quiere decir que no la haya presentado).'));
    }
    foreach ($est['inconsistente'] ?? [] as $x) { $m[] = 'Año ' . (int)$x['anio'] . ': el archivo trae ' . (int)$x['cantidad'] . ' declaraciones del mismo tipo con montos distintos; no se publica ninguna cifra de ese año.'; }
    $m[] = ''; $m[] = '## Fuentes y condiciones';
    foreach (($l['fuentes'] ?? []) as $f) { $m[] = '- ' . (is_array($f) ? trim(($f['nombre'] ?? '') . ' ' . ($f['url'] ?? '')) : (string)$f); }
    $m[] = '- Metodología: ' . SITE_URL . '/metodologia';
    $m[] = '- Licencia de los textos y la compilación: Creative Commons Atribución 4.0. Citar "DipuData (dipudata.com)". Los datos de origen oficial conservan la licencia de su fuente.';
    $m[] = '- DipuData no es un sitio oficial. Ante dudas, verificar en la fuente oficial.';
    return implode("\n", $m) . "\n";
}

/** llms.txt (resumen para modelos de lenguaje) y llms-full.txt (con el listado completo y el glosario). Todo sale de los datos del sitio. */
function llms_txt(bool $completo): string
{
    $inf = \App\Datos::informe();
    $nd = (int)($inf['diputados'] ?? 0); $ns = (int)($inf['senadores'] ?? 0); $gen = (string)($inf['generado'] ?? '');
    $u = SITE_URL;
    $t = ["# DipuData", "",
        "> Datos abiertos y verificados sobre los {$nd} diputados y {$ns} senadores nacionales de Argentina: quién ocupa cada banca, cómo votó, qué proyectos presentó y qué declaró. Todo con fuente oficial indicada en cada dato. No es un sitio oficial. Datos actualizados al " . fecha_es($gen) . ".", "",
        "Cómo usar este contenido:", "",
        "- La información es pública y se puede reutilizar, también por sistemas de IA, con la licencia Creative Commons Atribución 4.0: citar \"DipuData (dipudata.com)\" y la página de origen. Los datos de origen oficial conservan la licencia de su fuente.",
        "- Cada dato muestra su fuente. Si algo no figura, no quiere decir que no exista: solo se publica lo que se pudo verificar. Ante dudas, verificar en la fuente oficial.",
        "- El sitio no afirma intenciones ni causas. Una cifra, un voto o un proyecto no implican por sí mismos ninguna conducta.",
        "- Para pedir una corrección o la baja de un dato: {$u}/correccion", "",
        "## Datos para máquinas", "",
        "- [API pública de solo lectura (JSON)]({$u}/datos): legisladores, bloques, provincias, votaciones con el voto de cada legislador y declaraciones juradas. Especificación OpenAPI: {$u}/api/openapi.json",
        "- Perfil de cada legislador en Markdown: agregar `?formato=md` a su dirección (por ejemplo {$u}/diputado/hilda-aguirre?formato=md)",
        "- [Datos abiertos y cómo citar]({$u}/datos)", "- [Sitemap]({$u}/sitemap.xml)", "",
        "## Secciones principales", "",
        "- [Legisladores en ejercicio]({$u}/): directorio con buscador por nombre, cámara, provincia y bloque",
        "- [Votaciones]({$u}/votaciones): votaciones nominales de Diputados y del Senado, una por una, con el voto de cada legislador",
        "- [Leyes]({$u}/leyes): leyes sancionadas con su sumario y fechas",
        "- [Patrimonio declarado]({$u}/patrimonio): declaraciones juradas de la Oficina Anticorrupción, con el detalle impositivo declarado",
        "- [Comparar legisladores]({$u}/comparar): hasta cuatro, lado a lado",
        "- [Test de afinidad por votos]({$u}/afinidad): con qué legisladores coincide una persona según los votos reales",
        "- [Bloques]({$u}/congreso/bloques), [Provincias]({$u}/congreso/provincias), [Comisiones]({$u}/comisiones)",
        "- [Cuánto cobran]({$u}/remuneraciones), [Pasajes y misiones]({$u}/viajes), [Cuánto gasta la Cámara]({$u}/presupuesto), [Aportes a partidos]({$u}/aportes)", "",
        "## Metodología y reglas", "",
        "- [Metodología y fuentes]({$u}/metodologia): de dónde sale cada dato, qué no se publica y por qué",
        "- [Glosario]({$u}/glosario): qué quiere decir cada término",
        "- [Cómo funciona el Congreso]({$u}/congreso)", "",
        "## Legal", "",
        "- [Términos y condiciones]({$u}/terminos)", "- [Política de privacidad]({$u}/privacidad)", "- [Política de cookies]({$u}/cookies)", ""];
    if ($completo) {
        $t[] = "## Qué no se publica"; $t[] = "";
        foreach (["CUIT, documentos, domicilios ni teléfonos particulares.", "Datos de familiares que no sean legisladores, ni descripción de bienes.", "Nada sobre el personal de un legislador: las nóminas no dicen a qué legislador responde cada agente.", "Situación judicial: no hay una fuente oficial legible por máquina.", "Cuando una fuente trae valores inconsistentes, no se publican y se explica en la metodología."] as $x) { $t[] = "- {$x}"; }
        $t[] = ""; $t[] = "## Glosario"; $t[] = "";
        foreach (terminos_glosario() as $k => $g) { $t[] = '- **' . $g[0] . '**: ' . trim((string)($g[1] ?? '')); }
        $t[] = ""; $t[] = "## Bloques"; $t[] = "";
        foreach (\App\Datos::bloques() as $b) { $t[] = '- [' . $b['nombre'] . ' (' . $b['camara'] . ', ' . (int)$b['cantidad'] . ' bancas)](' . $u . \App\Datos::urlBloque($b) . ')'; }
        foreach (['Diputados' => 'Diputados', 'Senadores' => 'Senadores'] as $cam => $tit) {
            $t[] = ""; $t[] = "## {$tit} en ejercicio"; $t[] = "";
            $ls = \App\Datos::legisladores($cam); usort($ls, static fn($x, $y) => strcmp(\App\Datos::normalizar(($x['apellido'] ?? '') . ' ' . $x['nombre']), \App\Datos::normalizar(($y['apellido'] ?? '') . ' ' . $y['nombre'])));
            foreach ($ls as $l) { $t[] = '- [' . $l['nombre'] . '](' . $u . \App\Datos::urlLegislador($l) . '?formato=md): ' . $l['provincia'] . ', ' . $l['bloque']; }
        }
        $t[] = "";
    }
    return implode("\n", $t) . "\n";
}

/** Página a pantalla completa para TV: documento propio, sin el menú ni el pie del sitio. */
function render_tv(string $modo, array $vars, array $meta): void
{
    encabezados_seguridad('text/html; charset=utf-8', 0);
    $meta += ['robots' => 'noindex, nofollow'];
    extract($vars, EXTR_SKIP);
    require __DIR__ . '/Vistas/tv.php';
}

/** Opciones del modo TV tomadas de la dirección: segundos por diapositiva, lista que recorre y pausa. */
function tv_opciones(): array
{
    $seg = (int)($_GET['seg'] ?? 12);
    $lista = in_array($_GET['lista'] ?? '', ['diputados', 'senado', 'todos'], true) ? (string)$_GET['lista'] : '';
    return ['seg' => max(6, min(60, $seg ?: 12)), 'lista' => $lista, 'pausa' => !empty($_GET['pausa'])];
}

/** @return list<array> legisladores que recorre la lista del modo TV, en orden alfabético por apellido, por cámara. */
function tv_lista(string $lista): array
{
    $dip = \App\Datos::legisladores('Diputados'); $sen = \App\Datos::legisladores('Senadores');
    $orden = static function (array $a): array { usort($a, static fn($x, $y) => strcmp(\App\Datos::normalizar(($x['apellido'] ?? '') . ' ' . $x['nombre']), \App\Datos::normalizar(($y['apellido'] ?? '') . ' ' . $y['nombre']))); return $a; };
    return match ($lista) { 'diputados' => $orden($dip), 'senado' => $orden($sen), default => array_merge($orden($dip), $orden($sen)) };
}

function tv_url(array $l, int $n, array $o): string
{
    $q = ['seg' => $o['seg'], 'lista' => $o['lista']] + ($o['pausa'] ? ['pausa' => 1] : []);
    return '/tv/' . ($l['camara'] === 'Senadores' ? 'senador' : 'diputado') . '/' . $l['slug'] . '/' . $n . '?' . http_build_query($q);
}

/** Diapositivas de un legislador con datos que ya se publican en su perfil; las que no tienen datos se omiten (salvo la declaración jurada, que explica por qué falta). */
function tv_diapositivas(array $l): array
{
    $d = [['tipo' => 'portada']];
    $mv = \App\Datos::mandatoActual($l['camara'], $l['slug']);
    if ($mv) { $d[] = ['tipo' => 'votos', 'mv' => $mv, 'meta' => \App\Datos::mandatoActualMeta()]; }
    $py = \App\Datos::proyectos($l['slug'], $l['camara']);
    if ($py && ($py['resumen']['total'] ?? 0) > 0) { $d[] = ['tipo' => 'proyectos', 'py' => $py]; }
    $dj = \App\Datos::ddjj($l['camara'], $l['slug']);
    $d[] = ['tipo' => 'ddjj', 'dj' => $dj, 'est' => estado_ddjj($l, $dj)];
    $d[] = ['tipo' => 'cierre'];
    return $d;
}

/** Título legible de una votación para el test de afinidad (el oficial queda aparte, sin tocar). */
function afinidad_titulo(string $t, string $cod): string
{
    $t = trim($t);
    if ($cod === 'D') {
        $t = (string)preg_replace('/^O\.D\.\s*\d+\s*-\s*/u', '', $t);
        $t = (string)preg_replace('/\s*DICT\.\s*DE\s*(MAY|MIN)\..*$/iu', '', $t);
        $abrev = ['E.F.' => 'ejercicio fiscal', 'CORRESP.' => 'correspondiente', 'ADM.' => 'administración', 'PRESUP.' => 'presupuestos', 'MÍN.' => 'mínimos',
            'PRESERV.' => 'preservación', 'AMB.' => 'ambiente', 'MODIF.' => 'modificación',
            'DECL.' => 'declaración', 'IMP.' => 'impuesto', 'DEROG.' => 'derogación', 'CREAC.' => 'creación'];
        $t = rtrim($t, ' ') . ' ';
        foreach ($abrev as $k => $v) { $t = (string)preg_replace('/(?<![\p{L}])' . preg_quote($k, '/') . '(?![\p{L}])/u', $v, $t); }
        $t = rtrim(trim($t), '.');
        if ($t === '') { return 'Votación en general'; }
        $t = mb_strtoupper(mb_substr($t, 0, 1)) . mb_strtolower(mb_substr($t, 1));
        $t = (string)preg_replace_callback('/\b(bcra|rigi|dnu|afip|arca|pami|iva|indec|anses|ley)\b/iu', static fn($m) => strtolower($m[1]) === 'ley' ? 'Ley' : mb_strtoupper($m[1]), $t);
        return mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
    }
    $t = (string)preg_replace('/\s*\(En general\)\s*$/u', '', $t);
    $t = (string)preg_replace('/\s+(?:PE|CD|S|OV)-\d+\/\d+[^,]*(?:,\s*)?/u', ' ', $t);
    $t = (string)preg_replace('/,?\s*O\.D\.\s*(\d+\/\d+)\s*$/u', ' (Orden del Día $1)', $t);
    $t = trim(preg_replace('/\s+/u', ' ', $t), ' .,');
    if (preg_match('/^\(?O\.D\.\s*(\d+\/\d+)\)?$/u', $t, $m) || preg_match('/^\(Orden del Día (\d+\/\d+)\)$/u', $t, $m)) { return 'Orden del Día ' . $m[1]; }
    return $t === '' ? 'Votación en general' : $t;
}

/**
 * Preguntas del test de afinidad: las votaciones EN GENERAL del mandato actual (desde el 10/12/2025) con mayor paridad entre afirmativos y
 * negativos y al menos 80 % de presentes, hasta 10 por cámara, en orden cronológico. Es un criterio objetivo: no hay elección editorial.
 */
function afinidad_preguntas(string $camara): array
{
    static $memo = [];
    if (isset($memo[$camara])) { return $memo[$camara]; }
    $cod = $camara === 'Senadores' ? 'S' : 'D';
    $desde = (string)(\App\Datos::mandatoActualMeta()['desde'] ?? '2025-12-10');
    $cand = [];
    foreach (\App\Datos::votaciones() as $v) {
        if ($v['c'] !== $cod || empty($v['tiene_detalle']) || $v['f'] < $desde) { continue; }
        $general = $cod === 'S' ? (bool)preg_match('/\(En general\)\s*$/u', $v['t']) : (bool)preg_match('/VOT\.? EN GRAL|EN GENERAL/iu', $v['t']);
        $deciden = $v['a'] + $v['n']; $emitidos = $deciden + $v['b']; $total = $emitidos + $v['u'];
        if (!$general || $deciden === 0 || $total === 0 || $emitidos < 0.8 * $total) { continue; }
        $v['_paridad'] = abs($v['a'] - $v['n']) / $deciden;
        $cand[] = $v;
    }
    usort($cand, static fn($x, $y) => $x['_paridad'] <=> $y['_paridad'] ?: strcmp($x['id'], $y['id']));
    $sel = array_slice($cand, 0, 10);
    usort($sel, static fn($x, $y) => strcmp($x['f'] . ($x['h'] ?? ''), $y['f'] . ($y['h'] ?? '')));
    return $memo[$camara] = array_map(static fn($v) => [
        'id' => $v['id'], 'f' => $v['f'], 'titulo' => afinidad_titulo($v['t'], $cod), 'oficial' => $v['t'],
        'a' => $v['a'], 'n' => $v['n'], 'b' => $v['b'], 'u' => $v['u'], 'res' => $v['res'],
    ], $sel);
}

/**
 * Coincidencia de cada legislador con las respuestas ($resp: id de votación => 'a' a favor | 'n' en contra). Solo cuentan las votaciones
 * en que el legislador votó afirmativo o negativo; abstenciones y ausencias quedan afuera. Se muestran quienes votaron en al menos el 60 % de las respondidas (mínimo 4).
 */
function afinidad_calcular(string $camara, array $resp): array
{
    $votos = [];
    foreach (afinidad_preguntas($camara) as $q) {
        if (!isset($resp[$q['id']])) { continue; }
        foreach ((\App\Datos::votacion($q['id'])['votos'] ?? []) as $v) {
            if (isset($v[4])) { $votos[$v[4]][$q['id']] = $v[3]; }
        }
    }
    $min = max(4, (int)ceil(0.6 * count($resp)));  // al menos el 60 % de lo respondido (mínimo cuatro): con muy pocos votos la coincidencia es azar
    $filas = [];
    foreach ($votos as $clave => $vs) {
        $sen = str_starts_with($clave, 's:');
        $l = \App\Datos::legislador($sen ? 'Senadores' : 'Diputados', $sen ? substr($clave, 2) : $clave);
        if (!$l) { continue; }
        $coinc = 0; $cons = 0;
        foreach ($resp as $id => $r) {
            $v = $vs[$id] ?? null;
            if ($v === 'A' || $v === 'N') { $cons++; if (($r === 'a' && $v === 'A') || ($r === 'n' && $v === 'N')) { $coinc++; } }
        }
        if ($cons >= $min) { $filas[] = ['l' => $l, 'coinc' => $coinc, 'cons' => $cons, 'pct' => $coinc / $cons]; }
    }
    usort($filas, static fn($x, $y) => $y['pct'] <=> $x['pct'] ?: $y['cons'] <=> $x['cons'] ?: strcmp($x['l']['nombre'], $y['l']['nombre']));
    return $filas;
}

/** Vínculos familiares que este legislador declaró con otros legisladores en ejercicio, o que otros declararon con él (identidad confirmada por CUIT). */
function parentescos_legislador(array $l): array
{
    $etiq = ['CONYUGE / CONVIVIENTE' => 'cónyuge o conviviente', 'HIJO/A' => 'hijo/a'];
    $yo = ($l['camara'] === 'Senadores' ? 's:' : '') . $l['slug'];
    $por = [];
    foreach (\App\Datos::parentescos() as $r) {
        if ($r['declarante'] === $yo) { $otro = $r['familiar']; $dir = 'yo'; }
        elseif ($r['familiar'] === $yo) { $otro = $r['declarante']; $dir = 'otro'; }
        else { continue; }
        $por[$otro]['vinculo'] = $etiq[$r['vinculo']] ?? '';
        $por[$otro]['anio'] = (int)$r['anio'];
        $por[$otro][$dir] = true;
    }
    $sal = [];
    foreach ($por as $k => $x) {
        $sen = str_starts_with($k, 's:');
        $o = \App\Datos::legislador($sen ? 'Senadores' : 'Diputados', $sen ? substr($k, 2) : $k);
        if ($o && $x['vinculo'] !== '') { $sal[] = ['otro' => $o, 'vinculo' => $x['vinculo'], 'anio' => $x['anio'], 'yo' => !empty($x['yo']), 'otro_declaro' => !empty($x['otro'])]; }
    }
    return $sal;
}

/** Barras verticales en SVG: una por clave (por ejemplo, proyectos por año). Sin scripts. */
function svg_barras(array $serie, string $titulo): string
{
    $n = count($serie);
    if ($n < 2) {
        return '';
    }
    $w = 640; $h = 240; $izq = 40; $der = 12; $arr = 16; $aba = 30;
    $max = max(1, (int)max($serie));
    $ancho = ($w - $izq - $der) / $n;
    $barra = max(4.0, min(48.0, $ancho * 0.66));
    $id = 'b-' . md5($titulo);
    $s = '<svg class="grafico" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-labelledby="' . $id . '"><title id="' . $id . '">' . e($titulo) . '</title>';
    for ($k = 0; $k <= 4; $k++) {
        $v = $max * $k / 4; $yy = round($arr + ($h - $arr - $aba) * (1 - $k / 4), 1);
        $s .= '<line class="g-guia" x1="' . $izq . '" x2="' . ($w - $der) . '" y1="' . $yy . '" y2="' . $yy . '"/>'
            . '<text class="g-eje" x="' . ($izq - 6) . '" y="' . ($yy + 4) . '" text-anchor="end">' . e(number_format($v, 0, ',', '.')) . '</text>';
    }
    $paso = max(1, (int)ceil($n / 10));
    $i = 0;
    foreach ($serie as $et => $v) {
        $cx = $izq + $ancho * ($i + 0.5);
        $alto = ($h - $arr - $aba) * ((float)$v / $max);
        $s .= '<rect class="g-barra" x="' . round($cx - $barra / 2, 1) . '" y="' . round($h - $aba - $alto, 1) . '" width="' . round($barra, 1) . '" height="' . round($alto, 1) . '"><title>' . e($et . ': ' . number_format((float)$v, 0, ',', '.')) . '</title></rect>';
        if (($i % $paso === 0 && ($n - 1 - $i) >= (int)ceil($paso / 2)) || $i === $n - 1) {
            $s .= '<text class="g-eje" x="' . round($cx, 1) . '" y="' . ($h - 8) . '" text-anchor="middle">' . e((string)$et) . '</text>';
        }
        $i++;
    }
    return $s . '</svg>';
}

/** Barra horizontal con el reparto de una votación: afirmativos, negativos, abstenciones y ausentes. */
function barra_resultado(int $a, int $n, int $b, int $u): string
{
    $t = max(1, $a + $n + $b + $u);
    $tramos = [['a', $a, 'Afirmativos'], ['n', $n, 'Negativos'], ['b', $b, 'Abstenciones'], ['u', $u, 'Ausentes']];
    $h = '<div class="barra-resultado" role="img" aria-label="' . e("Afirmativos $a, negativos $n, abstenciones $b, ausentes $u") . '">';
    foreach ($tramos as [$c, $v, $et]) {
        if ($v > 0) {
            $h .= '<span class="br-' . $c . '" style="width:' . round($v * 100 / $t, 2) . '%" title="' . e($et . ': ' . $v) . '"></span>';
        }
    }
    $h .= '</div><ul class="leyenda leyenda-resultado">';
    foreach ($tramos as [$c, $v, $et]) {
        $h .= '<li><i class="br-' . $c . '"></i>' . e($et) . ' <b class="num">' . $v . '</b></li>';
    }
    return $h . '</ul>';
}

/**
 * Evolución del patrimonio neto declarado de un legislador, con las mismas cifras que su pestaña de declaración jurada:
 * pesos de cada año, pesos de hoy (IPC del INDEC, desde 2016) y dólares oficiales del BCRA al cierre de cada año.
 * La variación se calcula entre la primera y la última declaración que tienen conversión (IPC o dólar) y siempre informa los años usados.
 * Devuelve null si no hay declaraciones publicadas.
 */
function serie_patrimonio(array $l): ?array
{
    static $ipc = null, $usd = null;
    if ($ipc === null) { $ipc = factores_ipc(); $usd = dolar_cierre(); }
    [$mesBase, $fac] = $ipc;
    $dj = \App\Datos::ddjj($l['camara'], $l['slug']);
    if (!$dj) {
        return null;
    }
    $corr = $hoy = $dol = [];
    foreach ($dj as $d) {
        $a = (int)$d['anio']; $pn = (float)$d['patrimonio_neto_final'];
        $corr[$a] = $pn;
        if (isset($fac[$a])) { $hoy[$a] = $pn * $fac[$a]; }
        if (isset($usd[$a])) { $dol[$a] = $pn / $usd[$a]; }
    }
    ksort($corr); ksort($hoy); ksort($dol);
    $var = static function (array $s): ?array {
        if (count($s) < 2) { return null; }
        $a0 = array_key_first($s); $a1 = array_key_last($s);
        return $s[$a0] > 0 ? ['desde' => $a0, 'hasta' => $a1, 'pct' => ($s[$a1] / $s[$a0] - 1) * 100] : null;
    };
    $ult = array_key_last($corr);
    return ['mes_base' => $mesBase, 'corriente' => $corr, 'hoy' => $hoy, 'usd' => $dol, 'ultimo_anio' => $ult, 'ultimo' => $corr[$ult],
            'ultimo_hoy' => $hoy[$ult] ?? null, 'ultimo_usd' => $dol[$ult] ?? null, 'var_hoy' => $var($hoy), 'var_usd' => $var($dol), 'cantidad' => count($corr)];
}

/** "+12,3 %" / "−4,0 %" con el período entre paréntesis. */
function texto_variacion(?array $v): string
{
    if (!$v) { return '—'; }
    return ($v['pct'] >= 0 ? '+' : '−') . number_format(abs($v['pct']), 1, ',', '.') . ' %';
}

/** Clave de un legislador en la URL de comparación: "d:slug" (diputado) o "s:slug" (senador). */
function clave_legislador(array $l): string
{
    return ($l['camara'] === 'Senadores' ? 's:' : 'd:') . $l['slug'];
}

function legislador_por_clave(string $k): ?array
{
    if (!preg_match('/^([ds]):([a-z0-9]+(?:-[a-z0-9]+)*)$/', $k, $m)) {
        return null;
    }
    return \App\Datos::legislador($m[1] === 's' ? 'Senadores' : 'Diputados', $m[2]);
}

/**
 * Gráfico de varias líneas en SVG, sin JavaScript. $series: [nombre => [anio => valor]]. Cada serie usa un color, un trazo y un
 * marcador distintos (no depende solo del color). El eje Y empieza en 0. $titulo describe el gráfico para lectores de pantalla.
 */
function svg_lineas(array $series, string $titulo, string $moneda = '$'): string
{
    $anios = [];
    foreach ($series as $s) { foreach (array_keys($s) as $a) { $anios[$a] = true; } }
    $anios = array_keys($anios); sort($anios);
    if (count($anios) < 2) {
        return '';
    }
    $w = 640; $h = 280; $izq = 84; $der = 16; $arr = 14; $aba = 30;
    $todos = [];
    foreach ($series as $s) { foreach ($s as $v) { $todos[] = (float)$v; } }
    $min = min(0.0, min($todos)); $max = max($todos);
    if ($max <= $min) { $max = $min + 1; }
    $n = count($anios);
    $pos = array_flip($anios);
    $x = static fn(int $a): float => $izq + ($w - $izq - $der) * ($pos[$a] / ($n - 1));
    $y = static fn(float $v): float => $arr + ($h - $arr - $aba) * (1 - ($v - $min) / ($max - $min));
    $id = 'ls-' . md5($titulo);
    $s = '<svg class="grafico" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-labelledby="' . $id . '"><title id="' . $id . '">' . e($titulo) . '</title>';
    for ($k = 0; $k <= 4; $k++) {
        $v = $min + ($max - $min) * $k / 4; $yy = round($y($v), 1);
        $s .= '<line class="g-guia" x1="' . $izq . '" x2="' . ($w - $der) . '" y1="' . $yy . '" y2="' . $yy . '"/>'
            . '<text class="g-eje" x="' . ($izq - 8) . '" y="' . ($yy + 4) . '" text-anchor="end">' . e(monto_corto($v, $moneda)) . '</text>';
    }
    $paso = max(1, (int)ceil($n / 9));
    foreach ($anios as $i => $a) {
        if (($i % $paso === 0 && ($n - 1 - $i) >= (int)ceil($paso / 2)) || $i === $n - 1) {
            $s .= '<text class="g-eje" x="' . round($x($a), 1) . '" y="' . ($h - 8) . '" text-anchor="middle">' . $a . '</text>';
        }
    }
    $k = 0;
    foreach ($series as $nombre => $serie) {
        $c = $k % 4; $camino = []; $marc = ''; $seguido = false;
        ksort($serie);
        foreach ($serie as $a => $v) {
            $px = round($x((int)$a), 1); $py = round($y((float)$v), 1);
            $camino[] = ($seguido ? 'L' : 'M') . $px . ' ' . $py; $seguido = true;
            $t = '<title>' . e($nombre . ', ' . $a . ': ' . monto_corto((float)$v, $moneda)) . '</title>';
            $marc .= match ($c) {
                0 => '<circle class="g-m g-m' . $c . '" cx="' . $px . '" cy="' . $py . '" r="3.8">' . $t . '</circle>',
                1 => '<rect class="g-m g-m' . $c . '" x="' . ($px - 3.5) . '" y="' . ($py - 3.5) . '" width="7" height="7">' . $t . '</rect>',
                2 => '<path class="g-m g-m' . $c . '" d="M' . $px . ' ' . ($py - 5) . ' L' . ($px + 4.5) . ' ' . ($py + 3.5) . ' L' . ($px - 4.5) . ' ' . ($py + 3.5) . ' Z">' . $t . '</path>',
                default => '<path class="g-m g-m' . $c . '" d="M' . $px . ' ' . ($py - 5) . ' L' . ($px + 5) . ' ' . $py . ' L' . $px . ' ' . ($py + 5) . ' L' . ($px - 5) . ' ' . $py . ' Z">' . $t . '</path>',
            };
        }
        $s .= '<path class="g-l g-l' . $c . '" d="' . implode(' ', $camino) . '"/>' . $marc;
        $k++;
    }
    return $s . '</svg>';
}

/**
 * Coincidencia de votos desde el 10/12/2025 entre legisladores de una misma cámara: de las votaciones nominales en que los dos
 * emitieron voto (afirmativo, negativo o abstención), en cuántas votaron igual. Devuelve [clave_a|clave_b => [coinciden, comunes]].
 * Solo cuenta votaciones válidas del mandato actual; las que no cuadran con su cabecera no entran.
 */
function coincidencias_votos(array $claves): array
{
    $por_cam = ['D' => [], 'S' => []];
    foreach ($claves as $k) { $por_cam[$k[0] === 's' ? 'S' : 'D'][] = $k; }
    $res = [];
    foreach ($por_cam as $cam => $ks) {
        if (count($ks) < 2) { continue; }
        $slugs = [];
        foreach ($ks as $k) { $slugs[$k] = $cam === 'S' ? 's:' . substr($k, 2) : substr($k, 2); }
        foreach ($ks as $i => $a) { foreach (array_slice($ks, $i + 1) as $b) { $res[$a . '|' . $b] = [0, 0]; } }
        foreach (\App\Datos::votaciones() as $v) {
            if (($v['c'] ?? 'D') !== $cam || $v['f'] < '2025-12-10') { continue; }
            $d = \App\Datos::votacion($v['id']);
            if (!$d || (!empty($d['nota']) && str_contains((string)$d['nota'], 'no coincide'))) { continue; }
            $voto = [];
            foreach ($d['votos'] as $f) {
                $clave = $f[4] ?? null;
                if ($clave !== null && in_array($f[3], ['A', 'N', 'B'], true)) { $voto[$clave] = $f[3]; }
            }
            foreach ($res as $par => &$r) {
                [$a, $b] = explode('|', $par);
                if (!isset($slugs[$a], $slugs[$b])) { continue; }
                if (isset($voto[$slugs[$a]], $voto[$slugs[$b]])) { $r[1]++; $r[0] += $voto[$slugs[$a]] === $voto[$slugs[$b]] ? 1 : 0; }
            }
            unset($r);
        }
    }
    return $res;
}

/** Internos telefónicos con separador uniforme: "1354/1380" y "1325 /1327" pasan a "1354 / 1380". */
function interno_legible(string $t): string
{
    return trim((string)preg_replace('/\s*\/\s*/', ' / ', $t));
}

/** Categorías del gráfico de proyectos por año: clave => [etiqueta, descripción corta]. */
function categorias_proyectos(): array
{
    return ['propio' => ['Propios', 'el legislador es el autor o el primer firmante'], 'firmante' => ['Como firmante', 'firmó un proyecto cuyo primer firmante es otro senador'],
            'recon' => ['Reconocimientos y homenajes', 'declaraciones y resoluciones que expresan reconocimiento, beneplácito, homenaje, felicitación, conmemoración o declaran de interés'],
            'sin' => ['Sin dato del firmante', 'el extracto oficial no trae firmantes']];
}

/** Categoría de un proyecto de la lista de un legislador: reconocimiento, propio, firmante o sin dato. */
function categoria_proyecto(array $x): string
{
    if (!empty($x[8])) { return 'recon'; }
    return ['p' => 'propio', 'f' => 'firmante', 'n' => 'sin'][$x[7] ?? 'p'] ?? 'propio';
}

/**
 * Barras apiladas por año con una línea de referencia (el promedio de la cámara), en SVG, sin scripts.
 * $anios: [año => [categoria => cantidad]]; $promedio: [año => promedio por legislador]. Cada categoría tiene su color y se describe en la leyenda y en la tabla.
 */
function svg_proyectos_anuales(array $anios, array $promedio, string $titulo, string $etiquetaPromedio, string $notaParcial = ''): string
{
    $n = count($anios);
    if ($n < 2) { return ''; }
    $w = 640; $h = 270; $izq = 40; $der = 12; $arr = 16; $aba = 30;
    $tot = array_map(static fn($c) => array_sum($c), $anios);
    $max = max(1.0, (float)max($tot), (float)max($promedio ?: [0]));
    $max = (float)ceil($max / 4) * 4;
    $ancho = ($w - $izq - $der) / $n;
    $barra = max(5.0, min(44.0, $ancho * 0.62));
    $id = 'pa-' . md5($titulo);
    $s = '<svg class="grafico" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-labelledby="' . $id . '"><title id="' . $id . '">' . e($titulo) . '</title>';
    for ($k = 0; $k <= 4; $k++) {
        $yy = round($arr + ($h - $arr - $aba) * (1 - $k / 4), 1);
        $s .= '<line class="g-guia" x1="' . $izq . '" x2="' . ($w - $der) . '" y1="' . $yy . '" y2="' . $yy . '"/><text class="g-eje" x="' . ($izq - 6) . '" y="' . ($yy + 4) . '" text-anchor="end">' . e(number_format($max * $k / 4, 0, ',', '.')) . '</text>';
    }
    $y = static fn(float $v): float => $arr + ($h - $arr - $aba) * (1 - $v / $max);
    $cats = categorias_proyectos();
    $paso = max(1, (int)ceil($n / 10));
    $i = 0; $camino = []; $marc = '';
    foreach ($anios as $anio => $c) {
        $cx = $izq + $ancho * ($i + 0.5); $base = $h - $aba;
        foreach (array_keys($cats) as $cat) {
            $v = (int)($c[$cat] ?? 0);
            if ($v <= 0) { continue; }
            $alto = ($h - $arr - $aba) * ($v / $max);
            $base -= $alto;
            $s .= '<rect class="g-s g-s-' . $cat . '" x="' . round($cx - $barra / 2, 1) . '" y="' . round($base, 1) . '" width="' . round($barra, 1) . '" height="' . round($alto, 1) . '"><title>' . e($anio . ' · ' . $cats[$cat][0] . ': ' . $v) . '</title></rect>';
        }
        if (isset($promedio[$anio])) {
            $px = round($cx, 1); $py = round($y((float)$promedio[$anio]), 1);
            $camino[] = (count($camino) ? 'L' : 'M') . $px . ' ' . $py;
            $marc .= '<path class="g-prom-m" d="M' . $px . ' ' . ($py - 5) . ' L' . ($px + 5) . ' ' . $py . ' L' . $px . ' ' . ($py + 5) . ' L' . ($px - 5) . ' ' . $py . ' Z"><title>' . e($anio . ' · ' . $etiquetaPromedio . ': ' . number_format((float)$promedio[$anio], 1, ',', '.')) . '</title></path>';
        }
        if (($i % $paso === 0 && ($n - 1 - $i) >= (int)ceil($paso / 2)) || $i === $n - 1) {
            $s .= '<text class="g-eje" x="' . round($cx, 1) . '" y="' . ($h - 8) . '" text-anchor="middle">' . e((string)$anio . ($notaParcial !== '' && (string)$anio === $notaParcial ? '*' : '')) . '</text>';
        }
        $i++;
    }
    return $s . ($camino ? '<path class="g-prom" d="' . implode(' ', $camino) . '"/>' : '') . $marc . '</svg>';
}

/**
 * Figura completa de "proyectos por año" de un legislador: barras por categoría, línea con el promedio de su cámara, leyenda y tabla.
 * Devuelve '' si no hay datos suficientes.
 */
function grafico_proyectos(array $l, ?array $pr, bool $conTitulo = true): string
{
    if (!$pr || count($pr['proyectos']) < 2) { return ''; }
    $esSen = $l['camara'] === 'Senadores';
    $prom = \App\Datos::proyectosPromedios($l['camara']);
    $porAnio = [];
    foreach ($pr['proyectos'] as $x) { $a = substr($x[1], 0, 4); $c = categoria_proyecto($x); $porAnio[$a][$c] = ($porAnio[$a][$c] ?? 0) + 1; }
    ksort($porAnio);
    // El eje llega hasta el año en curso y arranca en el inicio del mandato actual: un año sin proyectos se tiene que ver como cero, no desaparecer.
    $desde = min((int)array_key_first($porAnio), !empty($l['inicio']) ? (int)substr($l['inicio'], 0, 4) : 9999);
    $hasta = max((int)array_key_last($porAnio), (int)date('Y'));
    $anios = [];
    for ($a = $desde; $a <= $hasta; $a++) { $anios[(string)$a] = $porAnio[(string)$a] ?? []; }
    $promedio = [];
    foreach (array_keys($anios) as $a) { if (isset($prom['anios'][$a]['promedio'])) { $promedio[$a] = (float)$prom['anios'][$a]['promedio']; } }
    $cats = categorias_proyectos();
    $usadas = array_filter(array_keys($cats), static fn($c) => array_sum(array_column($anios, $c)) > 0);
    $enCurso = ($pr['resumen']['hasta'] ?? '') !== '' && $hasta === (int)date('Y') ? date('Y') : '';
    $etProm = 'promedio por ' . ($esSen ? 'senador' : 'diputado');
    $svg = svg_proyectos_anuales($anios, $promedio, 'Proyectos por año de ' . $l['nombre'] . ' (' . implode(', ', array_map(static fn($c) => mb_strtolower($cats[$c][0]), $usadas)) . ') de ' . $desde . ' a ' . $hasta . ', con el ' . $etProm . ' de la cámara', $etProm . ' de la cámara', $enCurso);
    $h = '<figure class="grafico-caja grafico-ancho">' . ($conTitulo ? '<p class="sub-titulo">Proyectos por año</p>' : '') . $svg . '<ul class="leyenda leyenda-proyectos">';
    foreach ($usadas as $c) { $h .= '<li><i class="ley-s ley-s-' . $c . '"></i>' . e($cats[$c][0]) . '</li>'; }
    if ($promedio) { $h .= '<li><i class="ley-prom"></i>Promedio por ' . ($esSen ? 'senador' : 'diputado') . ' de la cámara</li>'; }
    $h .= '</ul><figcaption class="tenue pequeno">';
    $h .= $esSen ? '<strong>Propios</strong>: es el autor o primer firmante del proyecto. <strong>Como firmante</strong>: firmó un proyecto cuyo primer firmante es otro. ' : 'El archivo oficial de la Cámara registra un solo autor por proyecto y no trae cofirmantes: todo lo que figura es propio y no se puede distinguir a los firmantes. ';
    $h .= '<strong>Reconocimientos y homenajes</strong>: declaraciones, resoluciones y comunicaciones cuyo título expresa o pide reconocimiento, beneplácito, homenaje, felicitación, conmemoración o declara de interés algo; se cuentan aparte, sea cual sea la autoría. ';
    $h .= 'La línea es el promedio de proyectos por ' . ($esSen ? 'senador' : 'diputado') . ' que estaba en funciones ese año, calculado con los ' . ($esSen ? 'senadores' : 'diputados') . ' actuales (<a href="/metodologia#proyectos">cómo se calcula</a>). Unos pocos legisladores muy prolíficos suben el promedio: en la tabla está también la mediana (entre quienes estuvieron al menos medio año) y los legisladores-año que entran en cada cálculo: quien asumió el 10 de diciembre cuenta una fracción de ese año, y en los años viejos son pocos porque solo se cuentan los legisladores actuales. Más proyectos no quiere decir más trabajo ni mejores leyes: mide cuánto se presentó.';
    if ($enCurso !== '') { $h .= ' * Año en curso: datos hasta el ' . fecha_es($pr['resumen']['hasta']) . '.'; }
    $h .= '</figcaption></figure>';
    $h .= '<details class="ver-datos"><summary>Ver los números por año</summary><div class="tabla-caja"><table class="tabla tabla-cifras"><thead><tr><th scope="col">Año</th>';
    foreach ($usadas as $c) { $h .= '<th scope="col" class="der">' . e($cats[$c][0]) . '</th>'; }
    $h .= '<th scope="col" class="der">Total</th><th scope="col" class="der">Promedio de la cámara</th><th scope="col" class="der">Mediana de la cámara</th><th scope="col" class="der">Legisladores-año en el cálculo</th></tr></thead><tbody>';
    foreach (array_reverse($anios, true) as $a => $c) {
        $h .= '<tr><td class="num" data-etiqueta="Año">' . e((string)$a . ((string)$a === $enCurso ? '*' : '')) . '</td>';
        foreach ($usadas as $k) { $h .= '<td class="der num" data-etiqueta="' . e($cats[$k][0]) . '">' . (int)($c[$k] ?? 0) . '</td>'; }
        $h .= '<td class="der num" data-etiqueta="Total"><b>' . array_sum($c) . '</b></td><td class="der num" data-etiqueta="Promedio de la cámara">' . (isset($promedio[$a]) ? e(number_format($promedio[$a], 1, ',', '.')) : '—') . '</td>'
            . '<td class="der num" data-etiqueta="Mediana de la cámara">' . (isset($prom['anios'][$a]['mediana']) ? e(number_format((float)$prom['anios'][$a]['mediana'], 1, ',', '.')) : '—') . '</td>'
            . '<td class="der num" data-etiqueta="Legisladores-año en el cálculo">' . e(number_format((float)($prom['anios'][$a]['equivalentes'] ?? 0), 1, ',', '.')) . '</td></tr>';
    }
    return $h . '</tbody></table></div></details>';
}

/**
 * Glosario en lenguaje llano: [clave => [término, definición, dónde se ve]]. Las definiciones describen cómo se usan estos términos en DipuData;
 * donde la práctica de cada cámara puede variar, lo dicen.
 */
function terminos_glosario(): array
{
    return [
        'abstencion' => ['Abstención', 'Una forma de votar: el legislador está presente y no vota ni a favor ni en contra. Se cuenta aparte de los votos afirmativos y negativos.', '/votaciones'],
        'afirmativo-negativo' => ['Voto afirmativo y negativo', 'Afirmativo es votar a favor de lo que se propone; negativo, en contra. El resultado de una votación se decide con estos votos, y según el asunto hace falta una mayoría simple o una mayoría especial.', '/votaciones'],
        'ausente' => ['Ausente / "No votó"', 'Quiere decir que en esa votación no figura un voto del legislador. No dice por qué: puede ser una licencia, una ausencia o que estuvo presente y no emitió voto. Por eso en DipuData se escribe "no votó" y no se interpreta el motivo.', '/votaciones'],
        'bloque' => ['Bloque', 'Grupo de legisladores de una misma cámara que se organizan juntos, generalmente porque pertenecen al mismo partido o alianza. Un legislador puede cambiar de bloque durante su mandato, y por eso en cada votación del Senado se muestra el bloque que figuraba en esa fecha.', '/congreso/bloques'],
        'camara' => ['Cámara', 'El Congreso argentino tiene dos: la Cámara de Diputados (257 bancas, que representan a la población de cada distrito) y el Senado (72 bancas, tres por provincia y tres por la Ciudad de Buenos Aires).', '/congreso'],
        'comision' => ['Comisión', 'Grupo reducido de legisladores que estudia los proyectos de un tema (salud, presupuesto, educación…) antes de que lleguen al recinto. Cada proyecto se "gira" a una o más comisiones.', '/comisiones'],
        'conmutador' => ['Conmutador e interno', 'El conmutador es el teléfono general de la cámara; el interno es el número que hay que marcar después para llegar al despacho de cada legislador.', '/'],
        'declaracion-jurada' => ['Declaración jurada patrimonial', 'Documento en el que funcionarios y legisladores informan sus bienes, deudas e ingresos. Por la Ley 26.857 es público y lo publica la Oficina Anticorrupción. DipuData muestra solo los totales, nunca el CUIT, los datos de familiares ni el detalle de cada bien.', '/patrimonio'],
        'dolar-oficial' => ['Dólar oficial y MEP', 'Para pasar un patrimonio a dólares se usa el tipo de cambio oficial del Banco Central al último día hábil de cada año. El dólar MEP se muestra aparte y marcado como no oficial, porque no tiene una serie oficial.', '/patrimonio'],
        'electores-votos' => ['Electores, votos positivos, en blanco y nulos', 'Electores son las personas habilitadas para votar. Votos positivos son los que van a una agrupación; en blanco, los de quien votó sin elegir ninguna; nulos, los que no se pueden contar. El porcentaje de cada agrupación se calcula sobre los votos positivos.', '/congreso/provincias'],
        'dictamen' => ['Dictamen de comisión y Orden del Día', 'Un dictamen es el informe con el que una comisión recomienda qué hacer con un proyecto: aprobarlo, aprobarlo con modificaciones o rechazarlo. Cuando se numera para llevarlo al recinto se llama Orden del Día. Que un proyecto no tenga dictamen en los archivos oficiales no quiere decir que haya sido rechazado: muchos proyectos nunca llegan a ser dictaminados.', '/comisiones'],
        'sobre-tablas' => ['Sobre tablas', 'Pedido para que la cámara trate un proyecto en el recinto sin que antes haya un dictamen de comisión. Hace falta una mayoría especial para aprobarlo.', '/votaciones'],
        'escrutinio' => ['Escrutinio provisorio y definitivo', 'El provisorio es el primer recuento, el de las mesas, que se conoce la noche de la elección. El definitivo lo hace la Justicia electoral después y puede diferir un poco. Los resultados que muestra DipuData son los provisorios publicados por la Dirección Nacional Electoral.', '/congreso/provincias'],
        'expediente' => ['Expediente', 'El número con el que se registra cada proyecto en la cámara. Por ejemplo, "5242-D-2026" es el proyecto 5242 de Diputados de 2026.', '/votaciones'],
        'fuente-oficial' => ['Fuente oficial y "no figura en el archivo publicado"', 'DipuData solo publica lo que sale de un registro oficial y se pudo verificar. Cuando algo no aparece, se dice exactamente eso: no figura en el archivo publicado. No quiere decir que no exista o que no se haya presentado.', '/metodologia'],
        'devengado' => ['Crédito vigente y devengado', 'En el presupuesto, el crédito vigente es lo que el organismo tiene autorizado gastar en el año, con las modificaciones que tuvo. El devengado es el gasto ya reconocido porque se recibió el servicio o el bien, aunque todavía no se haya pagado. Se usan para mostrar cuánto gasta la Cámara.', '/presupuesto'],
        'ipc' => ['IPC y pesos de hoy', 'El Índice de Precios al Consumidor mide cuánto subieron los precios. Se usa para expresar un monto de años anteriores en pesos de hoy y poder compararlo. Para años anteriores a 2016 no hay serie oficial en este formato.', '/patrimonio'],
        'mandato' => ['Mandato', 'El período para el que fue elegido un legislador: cuatro años en Diputados y seis en el Senado. Cada dos años se renueva la mitad de Diputados y un tercio del Senado.', '/congreso'],
        'patrimonio-neto' => ['Patrimonio neto', 'Los bienes menos las deudas, tal como los declaró la persona al cierre de cada año. Un cambio entre años puede deberse a cómo se valúan los bienes en la declaración (por ejemplo, a valor fiscal o de compra) y no solo a compras, ventas o ahorro.', '/patrimonio'],
        'paso' => ['PASO', 'Primarias abiertas, simultáneas y obligatorias: elecciones previas a las generales, en las que cada agrupación elige sus listas internas. En DipuData cada agrupación suma los votos de todas sus listas.', '/congreso/provincias'],
        'proyecto-ley' => ['Proyecto de ley', 'Propuesta para crear o cambiar una ley. Para convertirse en ley tiene que aprobarla la Cámara de Diputados y el Senado, y promulgarla el Poder Ejecutivo.', '/leyes'],
        'proyecto-declaracion' => ['Proyecto de declaración', 'Expresa la opinión o el deseo de la cámara sobre un tema (un beneplácito, un reconocimiento, una opinión). No obliga a nadie ni cambia leyes.', '/votaciones'],
        'proyecto-resolucion-comunicacion' => ['Proyecto de resolución y de comunicación', 'Asuntos que decide una sola cámara: por ejemplo, pedidos de informes al Poder Ejecutivo, citaciones o decisiones internas. Según el reglamento de cada cámara se llaman de un modo u otro. No son leyes.', '/votaciones'],
        'propio-firmante' => ['Proyecto propio y proyecto firmado', 'En el Senado, un proyecto es propio cuando el senador es el primer firmante, y "firmado" cuando acompaña a otro. En Diputados, el archivo oficial registra un solo autor por proyecto y no trae cofirmantes.', '/'],
        'recinto' => ['Recinto y sesión', 'El recinto es la sala donde se reúne la cámara; la sesión, cada reunión en la que se debate y se vota.', '/congreso'],
        'reconocimientos' => ['Reconocimientos y homenajes', 'Proyectos cuyo título expresa o pide reconocimiento, beneplácito, homenaje, felicitación, conmemoración o declara de interés algo. Se cuentan aparte en el gráfico de proyectos por año.', '/'],
        'votacion-nominal' => ['Votación nominal', 'Votación en la que queda registrado cómo votó cada legislador, con su nombre. No todas las votaciones son nominales: en algunas se vota levantando la mano y solo queda el resultado.', '/votaciones'],
    ];
}

/** Texto simple de un dictamen de comisión: [comisión, tipo, número, fecha, observaciones]. */
function dictamen_texto(array $d): string
{
    [$comision, $tipo, $num, $fecha] = $d;
    $f = $fecha !== '' ? ' (' . fecha_es($fecha) . ')' : '';
    $c = $comision !== '' ? ' · ' . $comision : '';
    return match ($tipo) {
        'od' => 'Dictamen de la comisión de ' . ($comision !== '' ? $comision : 'la Cámara') . ($num !== '' ? ', Orden del Día ' . $num : '') . $f,
        'sn' => 'Dictamen de la comisión de ' . ($comision !== '' ? $comision : 'la Cámara') . ', todavía sin número' . $f,
        'st' => 'Pedido de tratamiento sobre tablas' . $c . $f,
        'a114' => 'Artículo 114 del Reglamento' . $c . $f,
        'a204' => 'Artículo 204 del Reglamento' . $c . $f,
        default => 'Dictamen' . $c . $f,
    };
}

/**
 * Cuánto avanzaron los proyectos de un diputado: presentados, con dictamen de comisión y con una resolución de la Cámara registrada
 * (aprobado, media sanción o sancionado). "Sin dictamen" quiere decir que no figura ninguno en el archivo oficial, no que haya sido rechazado.
 */
function avance_proyectos(array $proyectos): array
{
    $con = 0; $aprob = 0; $dictaminados_od = 0;
    foreach ($proyectos as $x) {
        if (!empty($x[9])) { $con++; }
        if (in_array($x[5], ['APROBADO', 'MEDIA SANCION', 'SANCIONADO'], true)) { $aprob++; }
    }
    return ['presentados' => count($proyectos), 'con_dictamen' => $con, 'aprobados' => $aprob];
}
