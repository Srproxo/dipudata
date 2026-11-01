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
    return ['A' => 'Afirmativo', 'N' => 'Negativo', 'B' => 'Abstención', 'U' => 'Ausente', 'P' => 'Preside', 'E' => 'No emite voto', 'X' => 'Sin voto registrado'][$c] ?? $c;
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
function monto_corto(float $n): string
{
    $a = abs($n);
    [$div, $suf] = $a >= 1e9 ? [1e9, ' mil M'] : ($a >= 1e6 ? [1e6, ' M'] : ($a >= 1e3 ? [1e3, ' mil'] : [1, '']));
    return ($n < 0 ? '−' : '') . '$ ' . number_format($a / $div, $a / $div >= 100 || $div === 1 ? 0 : 1, ',', '.') . $suf;
}

/**
 * Gráfico de línea en SVG, sin JavaScript. $puntos: [etiqueta_x => valor]. El eje Y empieza en 0 (o en el mínimo si hay negativos)
 * y no se suaviza la línea. $titulo describe el gráfico en palabras (lectores de pantalla).
 */
function svg_linea(array $puntos, string $titulo): string
{
    if (count($puntos) < 2) {
        return '';
    }
    $w = 640; $h = 260; $izq = 84; $der = 16; $arr = 14; $aba = 30;
    $vals = array_map('floatval', array_values($puntos));
    $min = min(0.0, min($vals)); $max = max($vals);
    if ($max <= $min) { $max = $min + 1; }
    $n = count($vals);
    $x = static fn(int $i): float => $izq + ($w - $izq - $der) * ($n === 1 ? 0.5 : $i / ($n - 1));
    $y = static fn(float $v): float => $arr + ($h - $arr - $aba) * (1 - ($v - $min) / ($max - $min));
    $s = '<svg class="grafico" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-labelledby="g-' . md5($titulo) . '">'
       . '<title id="g-' . md5($titulo) . '">' . e($titulo) . '</title>';
    for ($k = 0; $k <= 4; $k++) {
        $v = $min + ($max - $min) * $k / 4; $yy = round($y($v), 1);
        $s .= '<line class="g-guia" x1="' . $izq . '" x2="' . ($w - $der) . '" y1="' . $yy . '" y2="' . $yy . '"/>'
            . '<text class="g-eje" x="' . ($izq - 8) . '" y="' . ($yy + 4) . '" text-anchor="end">' . e(monto_corto($v)) . '</text>';
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
    return $s . '<path class="g-linea" d="' . implode(' ', $camino) . '"/>' . $etiquetas . '</svg>';
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
