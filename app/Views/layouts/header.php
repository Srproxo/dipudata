<?php /** @var array $meta */ ?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($meta['title']) ?></title>
    <meta name="description" content="<?= e($meta['description']) ?>">
    <meta name="robots" content="<?= e($meta['robots'] ?? 'index, follow, max-image-preview:large') ?>">
    <link rel="canonical" href="<?= e($meta['canonical']) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="DipuData">
    <meta property="og:locale" content="es_AR">
    <meta property="og:title" content="<?= e($meta['title']) ?>">
    <meta property="og:description" content="<?= e($meta['description']) ?>">
    <meta property="og:url" content="<?= e($meta['canonical']) ?>">
    <meta property="og:image" content="<?= e($meta['og_image'] ?? SITE_URL . '/api/og') ?>">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <script nonce="<?= e(csp_nonce()) ?>" src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script defer nonce="<?= e(csp_nonce()) ?>" src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
    <script nonce="<?= e(csp_nonce()) ?>">
        tailwind.config = {
            theme: { extend: { fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace']
            } } }
        };
    </script>
    <?php if (!empty($meta['jsonld'])): ?>
    <script type="application/ld+json" nonce="<?= e(csp_nonce()) ?>"><?= json_encode($meta['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <?php endif; ?>
</head>
<body class="bg-gray-50 text-slate-900 font-sans antialiased">
<header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-200 shadow-[0_1px_3px_rgba(0,0,0,0.03)]">
    <nav class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-4" aria-label="Principal" x-data="{ mobileOpen: false }">
        <div class="flex items-center gap-6">
            <a href="/" class="font-extrabold tracking-tight text-xl flex items-center gap-2 text-slate-900 group">
                <span class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center font-mono font-bold text-sm shadow-sm group-hover:bg-blue-600 transition-colors">D</span>
                <span>Dipu<span class="text-blue-600">Data</span></span>
            </a>
            <div class="hidden md:flex items-center gap-1 text-sm font-medium">
                <a href="/" class="px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors">Legisladores</a>
                <a href="/match-360" class="px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors flex items-center gap-1.5">
                    <span>Match 360</span>
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700">11 Ejes</span>
                </a>
                <a href="/eje/salud-publica" class="px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors">Ejes Temáticos</a>
                <a href="/blog" class="px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors">Blog</a>
            </div>
        </div>

        <!-- Buscador rápido en Navbar -->
        <div class="hidden sm:flex items-center relative max-w-xs w-full">
            <form action="/" method="GET" class="w-full">
                <div class="relative">
                    <input type="text" name="q" placeholder="Buscar legislador o provincia..." 
                           class="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:border-slate-800 focus:outline-none focus:ring-1 focus:ring-slate-800 transition-all placeholder:text-slate-400"
                           aria-label="Buscar en DipuData">
                    <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </form>
        </div>

        <!-- Mobile Menu Toggle -->
        <button type="button" @click="mobileOpen = !mobileOpen" class="md:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Abrir menú">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <!-- Mobile dropdown -->
        <div x-show="mobileOpen" x-cloak @click.outside="mobileOpen = false" class="absolute top-16 left-0 w-full bg-white border-b border-slate-200 shadow-lg p-4 md:hidden flex flex-col gap-2 text-sm font-medium z-50">
            <a href="/" class="px-3 py-2 rounded-lg text-slate-700 hover:bg-slate-100">Legisladores</a>
            <a href="/match-360" class="px-3 py-2 rounded-lg text-slate-700 hover:bg-slate-100">Match Electoral 360</a>
            <a href="/eje/salud-publica" class="px-3 py-2 rounded-lg text-slate-700 hover:bg-slate-100">Ejes Temáticos</a>
            <a href="/blog" class="px-3 py-2 rounded-lg text-slate-700 hover:bg-slate-100">Blog de Datos</a>
        </div>
    </nav>
</header>
<main class="max-w-6xl mx-auto px-4 py-8">
