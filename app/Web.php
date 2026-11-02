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

function encabezados_seguridad(string $tipo = 'text/html; charset=utf-8', int $maxAge = 300): void
{
    header('Content-Type: ' . $tipo);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
    header('Strict-Transport-Security: max-age=63072000; includeSubDomains');
    header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' https://api.argentinadatos.com https://www.senado.gob.ar data:; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
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
        if ($i % $paso === 0 || $i === $n - 1) {
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
    if (\App\Datos::ddjj($l['camara'], $l['slug'])) {
        $p['patrimonio'] = 'Declaración jurada';
    }
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

/** Número con separador de miles y decimales con coma: 1.234 o 98,6. */
function num_es(float $n, int $dec = 0): string
{
    return number_format($n, $dec, ',', '.');
}

/**
 * Avance del mandato a partir del inicio oficial y del año de fin del padrón ("2023-2027": termina el 9/12/2027).
 * Devuelve [inicio ISO, fin ISO, % transcurrido, "faltan ..."] o null si no hay datos para calcularlo.
 */
function mandato_avance(array $l, ?string $hoy = null): ?array
{
    if (empty($l['mandato']) || !preg_match('/^(\d{4})-(\d{4})$/', (string)$l['mandato'], $m)) {
        return null;
    }
    $ini = $l['inicio'] ?: $m[1] . '-12-10';
    $fin = $m[2] . '-12-09';
    $t0 = strtotime($ini); $t1 = strtotime($fin); $th = strtotime($hoy ?? date('Y-m-d'));
    if (!$t0 || !$t1 || $t1 <= $t0 || $th < $t0) {
        return null;
    }
    $pct = min(100, ($th - $t0) / ($t1 - $t0) * 100);
    $d = (new DateTimeImmutable(date('Y-m-d', $th)))->diff(new DateTimeImmutable($fin));
    $resta = $th >= $t1 ? 'Mandato cumplido' : 'Faltan ' . implode(' y ', array_filter([
        $d->y ? $d->y . ($d->y === 1 ? ' año' : ' años') : '',
        $d->m ? $d->m . ($d->m === 1 ? ' mes' : ' meses') : '',
        !$d->y && !$d->m ? $d->d . ($d->d === 1 ? ' día' : ' días') : '',
    ]));
    return [$ini, $fin, $pct, $resta];
}

/** Porcentaje con un decimal, sin ",0" sobrante: 98,6 o 100. */
function pct_es(float $p): string
{
    $t = number_format($p, 1, ',', '.');
    return str_ends_with($t, ',0') ? substr($t, 0, -2) : $t;
}

/** Barra de medidor (0 a 100) para una tarjeta de cifra. Decorativa: el número va escrito al lado. */
function medidor(?float $pct): string
{
    if ($pct === null) {
        return '';
    }
    return '<span class="medidor" aria-hidden="true"><span style="width:' . e(number_format(max(0, min(100, $pct)), 1, '.', '')) . '%"></span></span>';
}

/**
 * Cómo votó: cuatro cifras grandes con los colores de voto y una barra al 100 %.
 * $c: ['A' => afirmativos, 'N' => negativos, 'B' => abstenciones, 'U' => no votó]; $etU: nombre de la cuarta categoría.
 */
function reparto_votos(array $c, string $etU = 'No votó'): string
{
    $total = max(1, array_sum($c));
    $et = ['A' => 'A favor', 'N' => 'En contra', 'B' => 'Abstenciones', 'U' => $etU];
    $h = '<div class="reparto"><ul class="reparto-cifras">';
    foreach ($et as $k => $t) {
        $h .= '<li class="reparto-' . $k . '"><b class="num">' . e(num_es((float)($c[$k] ?? 0))) . '</b><span>' . e($t) . '</span></li>';
    }
    $h .= '</ul><div class="reparto-barra" aria-hidden="true">';
    foreach ($et as $k => $t) {
        if (($c[$k] ?? 0) > 0) {
            $h .= '<span class="rb-' . $k . '" style="width:' . e(number_format($c[$k] / $total * 100, 2, '.', '')) . '%"></span>';
        }
    }
    return $h . '</div></div>';
}

/**
 * Barras apiladas verticales en SVG (sin JavaScript): $serie = [etiqueta => ['A' => n, 'N' => n, 'B' => n, 'U' => n]].
 * Mismos colores que los votos. $titulo describe el gráfico en palabras.
 */
function svg_barras_votos(array $serie, string $titulo): string
{
    if (count($serie) < 2) {
        return '';
    }
    $w = 640; $h = 256; $izq = 40; $der = 8; $arr = 12; $aba = 50;
    $max = max(1, max(array_map('array_sum', $serie)));
    $paso = $max <= 10 ? 2 : ($max <= 25 ? 5 : ($max <= 60 ? 10 : ($max <= 150 ? 25 : 50)));
    $tope = (int)(ceil($max / $paso) * $paso);
    $n = count($serie);
    $ancho = ($w - $izq - $der) / $n;
    $barra = min(56, $ancho * 0.62);
    $y = static fn(float $v): float => $arr + ($h - $arr - $aba) * (1 - $v / $tope);
    $id = 'gb-' . md5($titulo);
    $s = '<svg class="grafico grafico-barras" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-labelledby="' . $id . '"><title id="' . $id . '">' . e($titulo) . '</title>';
    for ($v = 0; $v <= $tope; $v += $paso) {
        $yy = round($y($v), 1);
        $s .= '<line class="g-guia" x1="' . $izq . '" x2="' . ($w - $der) . '" y1="' . $yy . '" y2="' . $yy . '"/>'
            . '<text class="g-eje" x="' . ($izq - 6) . '" y="' . ($yy + 4) . '" text-anchor="end">' . $v . '</text>';
    }
    $i = 0;
    foreach ($serie as $et => $c) {
        $x = round($izq + $ancho * $i + ($ancho - $barra) / 2, 1);
        $acum = 0;
        foreach (['A', 'N', 'B', 'U'] as $k) {
            $v = (int)($c[$k] ?? 0);
            if ($v <= 0) { continue; }
            $y1 = $y($acum + $v); $y0 = $y($acum);
            $s .= '<rect class="gb-' . $k . '" x="' . $x . '" y="' . round($y1, 1) . '" width="' . round($barra, 1) . '" height="' . round($y0 - $y1, 1) . '"/>';
            $acum += $v;
        }
        // Etiqueta en una o dos líneas ("mes|año").
        $lin = explode('|', (string)$et);
        $s .= '<text class="g-eje" x="' . round($x + $barra / 2, 1) . '" y="' . ($h - 26) . '" text-anchor="middle">' . e($lin[0]) . '</text>';
        if (isset($lin[1])) {
            $s .= '<text class="g-eje" x="' . round($x + $barra / 2, 1) . '" y="' . ($h - 5) . '" text-anchor="middle">' . e($lin[1]) . '</text>';
        }
        $i++;
    }
    return $s . '</svg>';
}

/** Barras verticales simples en SVG: [etiqueta => valor] (por ejemplo, proyectos por año). */
function svg_barras(array $serie, string $titulo): string
{
    if (count($serie) < 2) {
        return '';
    }
    $max = max(1, max(array_map('intval', $serie)));
    $w = 640; $h = 226; $izq = 40; $der = 8; $arr = 24; $aba = 30;
    $paso = $max <= 10 ? 2 : ($max <= 25 ? 5 : ($max <= 60 ? 10 : ($max <= 150 ? 25 : ($max <= 400 ? 50 : 100))));
    $tope = (int)(ceil($max / $paso) * $paso);
    $n = count($serie); $ancho = ($w - $izq - $der) / $n; $barra = min(48, $ancho * 0.62);
    $y = static fn(float $v): float => $arr + ($h - $arr - $aba) * (1 - $v / $tope);
    $id = 'gs-' . md5($titulo);
    $s = '<svg class="grafico grafico-barras" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-labelledby="' . $id . '"><title id="' . $id . '">' . e($titulo) . '</title>';
    for ($v = 0; $v <= $tope; $v += $paso) {
        $yy = round($y($v), 1);
        $s .= '<line class="g-guia" x1="' . $izq . '" x2="' . ($w - $der) . '" y1="' . $yy . '" y2="' . $yy . '"/>'
            . '<text class="g-eje" x="' . ($izq - 6) . '" y="' . ($yy + 4) . '" text-anchor="end">' . $v . '</text>';
    }
    $i = 0;
    $cada = max(1, (int)ceil($n / 12));
    foreach ($serie as $et => $v) {
        $x = round($izq + $ancho * $i + ($ancho - $barra) / 2, 1); $yy = round($y((float)$v), 1);
        if ($v > 0) {
            $s .= '<rect class="gs-barra" x="' . $x . '" y="' . $yy . '" width="' . round($barra, 1) . '" height="' . round($y(0) - $yy, 1) . '"/>';
        }
        if ($n <= 14) {
            $s .= '<text class="g-valor" x="' . round($x + $barra / 2, 1) . '" y="' . ($yy - 5) . '" text-anchor="middle">' . (int)$v . '</text>';
        }
        if ($i % $cada === 0 || $i === $n - 1) {
            $s .= '<text class="g-eje" x="' . round($x + $barra / 2, 1) . '" y="' . ($h - 10) . '" text-anchor="middle">' . e((string)$et) . '</text>';
        }
        $i++;
    }
    return $s . '</svg>';
}

/** "dic. 2025" a partir de "2025-12"; sin el año si $conAnio es false. */
function mes_corto(string $am, bool $conAnio = true): string
{
    $m = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sept.', 'oct.', 'nov.', 'dic.'];
    return ($m[(int)substr($am, 5, 2) - 1] ?? '') . ($conAnio ? ' ' . substr($am, 0, 4) : '');
}
