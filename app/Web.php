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
    header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' https://api.argentinadatos.com data:; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
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
