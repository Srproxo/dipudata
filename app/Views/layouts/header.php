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
<header class="bg-white border-b border-slate-200">
    <nav class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between" aria-label="Principal">
        <a href="/" class="font-extrabold tracking-tight text-lg">Dipu<span class="text-blue-600">Data</span></a>
        <div class="flex items-center gap-1 text-sm font-medium">
            <a href="/" class="px-3 py-2 rounded-md text-slate-600 hover:bg-slate-100">Legisladores</a>
            <a href="/match-360" class="px-3 py-2 rounded-md text-slate-600 hover:bg-slate-100">Match 360</a>
            <a href="/blog" class="px-3 py-2 rounded-md text-slate-600 hover:bg-slate-100">Blog</a>
        </div>
    </nav>
</header>
<main class="max-w-6xl mx-auto px-4 py-8">
