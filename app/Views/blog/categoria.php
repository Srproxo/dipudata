<?php /** @var string $categoria @var string $nombreCategoria @var array $articulos */ ?>
<nav class="text-sm text-slate-500 mb-6" aria-label="Migas de pan">
    <a href="/" class="hover:text-slate-900">Inicio</a> / <a href="/blog" class="hover:text-slate-900">Blog</a> /
    <span class="text-slate-900 font-medium"><?= e($nombreCategoria) ?></span>
</nav>
<h1 class="text-3xl font-extrabold tracking-tight"><?= e($nombreCategoria) ?></h1>
<div class="grid gap-4 sm:grid-cols-2 mt-8">
    <?php foreach ($articulos as $a): ?>
    <article class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-lg font-bold tracking-tight leading-snug">
            <a href="/blog/<?= e($categoria) ?>/<?= e($a['articulo_slug']) ?>" class="hover:underline"><?= e($a['titulo']) ?></a>
        </h2>
        <p class="text-sm text-slate-500 mt-2"><?= e($a['meta_descripcion']) ?></p>
    </article>
    <?php endforeach; ?>
</div>
