<?php
declare(strict_types=1);

use LegisData\Security;

const SITE_URL = 'https://dipudata.com';

function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ars(float $monto): string
{
    if ($monto >= 1_000_000_000) {
        return '$ ' . number_format($monto / 1_000_000_000, 2, ',', '.') . ' mil M';
    }
    if ($monto >= 1_000_000) {
        return '$ ' . number_format($monto / 1_000_000, 1, ',', '.') . ' M';
    }
    return '$ ' . number_format($monto, 0, ',', '.');
}

function pct(float $v, bool $signo = false): string
{
    return ($signo && $v > 0 ? '+' : '') . number_format($v, 1, ',', '.') . '%';
}

function csp_nonce(): string
{
    static $nonce = null;
    return $nonce ??= base64_encode(random_bytes(16));
}

/** Cabeceras de seguridad para vistas MVC (Tailwind CDN + Chart.js con nonce). */
function send_view_headers(int $status = 200): void
{
    if (headers_sent()) {
        return;
    }
    http_response_code($status);
    Security::sendSecurityHeaders('text/html; charset=utf-8');
    $n = csp_nonce();
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'nonce-{$n}' https://cdn.tailwindcss.com https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; "
        . "img-src 'self' https://upload.wikimedia.org data:; "
        . "connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'");
}

function self_breadcrumb(array $items): array
{
    $list = [];
    foreach (array_merge([['Inicio', '/']], $items) as $i => [$nombre, $ruta]) {
        $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $nombre, 'item' => SITE_URL . $ruta];
    }
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
}

function self_breadcrumb_perfil(array $leg, string $ruta): array
{
    return [
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => SITE_URL . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $leg['camara'], 'item' => SITE_URL . '/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $leg['nombre'], 'item' => SITE_URL . $ruta],
        ],
    ];
}

/** @param array{title:string, description:string, canonical:string, jsonld?:array, og_image?:string, robots?:string} $meta */
function render(string $vista, array $meta, array $datos = [], int $status = 200): void
{
    send_view_headers($status);
    extract($datos, EXTR_SKIP);
    require __DIR__ . '/../Views/layouts/header.php';
    require __DIR__ . '/../Views/' . $vista . '.php';
    require __DIR__ . '/../Views/layouts/footer.php';
}

function render_404(): void
{
    render('404', [
        'title'       => 'Página no encontrada | DipuData',
        'description' => 'El recurso solicitado no existe.',
        'canonical'   => SITE_URL . '/',
        'robots'      => 'noindex, follow',
    ], [], 404);
}
