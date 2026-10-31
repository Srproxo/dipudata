<?php
declare(strict_types=1);

use LegisData\Security;

const SITE_URL = 'https://dipudata.com';

function e(mixed $v): string
{
    if (is_array($v)) {
        return htmlspecialchars(implode(', ', array_filter(array_map('strval', $v))), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
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

/**
 * Tooltip interactivo liviano con Alpine.js para métricas complejas.
 */
function tooltip_alpine(string $texto): string
{
    $safe = htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return <<<HTML
<span x-data="{ open: false }" class="relative inline-flex items-center ml-1.5 align-middle">
    <button type="button" @mouseenter="open = true" @mouseleave="open = false" @focus="open = true" @blur="open = false" class="text-slate-400 hover:text-slate-600 cursor-help focus:outline-none" aria-label="Información">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-4m0-4h.01"/></svg>
    </button>
    <div x-show="open" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-56 p-2 text-xs font-normal text-white bg-slate-900 rounded-lg shadow-xl z-50 pointer-events-none transition-all">
        {$safe}
    </div>
</span>
HTML;
}

/**
 * Retorna los estilos y colores oficiales del bloque/partido político del legislador.
 *
 * @param string $bloque
 * @return array{nombre: string, color: string, hex: string, hex_light: string, badge: string, badge_solid: string, border: string, border_subtle: string, text: string, dot: string, ring: string}
 */
function partido_colores(string $bloque): array
{
    $b = mb_strtolower(trim($bloque), 'UTF-8');

    // 1. La Libertad Avanza (Violeta / Púrpura oficial)
    if (str_contains($b, 'libertad avanza') || str_contains($b, 'lla') || str_contains($b, 'libertad')) {
        return [
            'nombre'        => 'La Libertad Avanza',
            'color'         => 'purple',
            'hex'           => '#7c3aed',
            'hex_light'     => '#f3e8ff',
            'badge'         => 'bg-purple-50 text-purple-800 border border-purple-200',
            'badge_solid'   => 'bg-purple-700 text-white',
            'border'        => 'border-purple-500',
            'border_subtle' => 'border-purple-200',
            'text'          => 'text-purple-700',
            'dot'           => 'bg-purple-600',
            'ring'          => 'ring-purple-400',
        ];
    }

    // 2. PRO (Amarillo oficial)
    if (str_contains($b, 'pro') || str_contains($b, 'propuesta republicana')) {
        return [
            'nombre'        => 'PRO',
            'color'         => 'amber',
            'hex'           => '#d97706',
            'hex_light'     => '#fef3c7',
            'badge'         => 'bg-amber-50 text-amber-900 border border-amber-300',
            'badge_solid'   => 'bg-amber-500 text-slate-950',
            'border'        => 'border-amber-400',
            'border_subtle' => 'border-amber-200',
            'text'          => 'text-amber-800',
            'dot'           => 'bg-amber-500',
            'ring'          => 'ring-amber-400',
        ];
    }

    // 3. UCR (Rojo Radical)
    if (str_contains($b, 'radical') || str_contains($b, 'ucr')) {
        return [
            'nombre'        => 'UCR',
            'color'         => 'red',
            'hex'           => '#dc2626',
            'hex_light'     => '#fee2e2',
            'badge'         => 'bg-red-50 text-red-800 border border-red-200',
            'badge_solid'   => 'bg-red-600 text-white',
            'border'        => 'border-red-500',
            'border_subtle' => 'border-red-200',
            'text'          => 'text-red-700',
            'dot'           => 'bg-red-600',
            'ring'          => 'ring-red-400',
        ];
    }

    // 4. Unión por la Patria / PJ (Celeste y Azul)
    if (str_contains($b, 'patria') || str_contains($b, 'todos') || str_contains($b, 'justicialista') || str_contains($b, 'peron')) {
        return [
            'nombre'        => 'Unión por la Patria',
            'color'         => 'sky',
            'hex'           => '#0284c7',
            'hex_light'     => '#e0f2fe',
            'badge'         => 'bg-sky-50 text-sky-800 border border-sky-200',
            'badge_solid'   => 'bg-sky-600 text-white',
            'border'        => 'border-sky-500',
            'border_subtle' => 'border-sky-200',
            'text'          => 'text-sky-700',
            'dot'           => 'bg-sky-600',
            'ring'          => 'ring-sky-400',
        ];
    }

    // 5. Frente de Izquierda (Rojo oscuro / Carmesí)
    if (str_contains($b, 'izquierda') || str_contains($b, 'fit')) {
        return [
            'nombre'        => 'Frente de Izquierda',
            'color'         => 'rose',
            'hex'           => '#be123c',
            'hex_light'     => '#ffe4e6',
            'badge'         => 'bg-rose-50 text-rose-900 border border-rose-300',
            'badge_solid'   => 'bg-rose-700 text-white',
            'border'        => 'border-rose-600',
            'border_subtle' => 'border-rose-200',
            'text'          => 'text-rose-800',
            'dot'           => 'bg-rose-700',
            'ring'          => 'ring-rose-400',
        ];
    }

    // 6. Encuentro Federal (Índigo)
    if (str_contains($b, 'encuentro federal') || str_contains($b, 'coalición federal') || str_contains($b, 'hacemos')) {
        return [
            'nombre'        => 'Encuentro Federal',
            'color'         => 'indigo',
            'hex'           => '#4338ca',
            'hex_light'     => '#e0e7ff',
            'badge'         => 'bg-indigo-50 text-indigo-800 border border-indigo-200',
            'badge_solid'   => 'bg-indigo-700 text-white',
            'border'        => 'border-indigo-500',
            'border_subtle' => 'border-indigo-200',
            'text'          => 'text-indigo-700',
            'dot'           => 'bg-indigo-600',
            'ring'          => 'ring-indigo-400',
        ];
    }

    // 7. Coalición Cívica (Esmeralda / Verde)
    if (str_contains($b, 'coalición cívica') || str_contains($b, 'civica') || str_contains($b, 'ari')) {
        return [
            'nombre'        => 'Coalición Cívica',
            'color'         => 'emerald',
            'hex'           => '#059669',
            'hex_light'     => '#d1fae5',
            'badge'         => 'bg-emerald-50 text-emerald-800 border border-emerald-200',
            'badge_solid'   => 'bg-emerald-600 text-white',
            'border'        => 'border-emerald-500',
            'border_subtle' => 'border-emerald-200',
            'text'          => 'text-emerald-700',
            'dot'           => 'bg-emerald-600',
            'ring'          => 'ring-emerald-400',
        ];
    }

    // 8. Innovación Federal (Cian)
    if (str_contains($b, 'innovación federal') || str_contains($b, 'innovacion') || str_contains($b, 'misiones')) {
        return [
            'nombre'        => 'Innovación Federal',
            'color'         => 'cyan',
            'hex'           => '#0891b2',
            'hex_light'     => '#cffafe',
            'badge'         => 'bg-cyan-50 text-cyan-800 border border-cyan-200',
            'badge_solid'   => 'bg-cyan-600 text-white',
            'border'        => 'border-cyan-500',
            'border_subtle' => 'border-cyan-200',
            'text'          => 'text-cyan-700',
            'dot'           => 'bg-cyan-600',
            'ring'          => 'ring-cyan-400',
        ];
    }

    // Neutro / Predeterminado
    return [
        'nombre'        => $bloque ?: 'Independiente',
        'color'         => 'slate',
        'hex'           => '#475569',
        'hex_light'     => '#f1f5f9',
        'badge'         => 'bg-slate-100 text-slate-700 border border-slate-200',
        'badge_solid'   => 'bg-slate-800 text-white',
        'border'        => 'border-slate-300',
        'border_subtle' => 'border-slate-200',
        'text'          => 'text-slate-700',
        'dot'           => 'bg-slate-500',
        'ring'          => 'ring-slate-300',
    ];
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
        . "script-src 'self' 'nonce-{$n}' https://cdn.tailwindcss.com https://cdn.jsdelivr.net 'unsafe-eval'; "
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
