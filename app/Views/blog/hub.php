<?php /** @var array $categorias @var array $articulos */ ?>
<header class="max-w-3xl">
    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight">Periodismo de datos legislativos</h1>
    <p class="text-slate-500 mt-3 text-lg">Votaciones, patrimonio ajustado por inflación y educación financiera cívica.</p>
</header>

<nav class="flex flex-wrap gap-2 mt-8" aria-label="Categorías">
    <?php foreach ($categorias as $slug => $n): ?>
    <a href="/blog/<?= e($slug) ?>" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-sm font-medium hover:border-slate-400">
        <?= e(\App\Controllers\BlogController::nombreCategoria($slug)) ?> <span class="font-mono text-slate-400"><?= (int)$n ?></span>
    </a>
    <?php endforeach; ?>
</nav>

<div class="grid gap-4 sm:grid-cols-2 mt-8">
    <?php foreach ($articulos as $a): ?>
    <article class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 hover:border-slate-300">
        <a href="/blog/<?= e($a['categoria_slug']) ?>" class="text-xs font-semibold uppercase tracking-wide text-blue-700"><?= e(\App\Controllers\BlogController::nombreCategoria($a['categoria_slug'])) ?></a>
        <h2 class="text-lg font-bold tracking-tight mt-2 leading-snug">
            <a href="/blog/<?= e($a['categoria_slug']) ?>/<?= e($a['articulo_slug']) ?>" class="hover:underline"><?= e($a['titulo']) ?></a>
        </h2>
        <p class="text-sm text-slate-500 mt-2"><?= e($a['meta_descripcion']) ?></p>
    </article>
    <?php endforeach; ?>
</div>
