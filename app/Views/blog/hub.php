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

<?php if (!empty($programados)): ?>
<section class="mt-14 pt-10 border-t border-slate-200">
    <div class="flex items-center justify-between">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-amber-700 bg-amber-100 px-2.5 py-0.5 rounded-full">Cronograma Editorial</span>
            <h2 class="text-2xl font-extrabold tracking-tight mt-2 text-slate-900">Próximos informes programados (Noviembre y Diciembre 2026)</h2>
            <p class="text-slate-500 text-sm mt-1">Investigaciones de auditoría cívica con fecha de publicación escalonada.</p>
        </div>
        <span class="text-xs font-mono bg-slate-100 text-slate-600 px-3 py-1 rounded-full"><?= count($programados) ?> en calendario</span>
    </div>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 mt-6">
        <?php foreach ($programados as $p): ?>
        <article class="bg-slate-50 border border-slate-200 rounded-xl p-4 flex flex-col justify-between hover:bg-white hover:border-slate-300 transition">
            <div>
                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-slate-500 uppercase"><?= e(\App\Controllers\BlogController::nombreCategoria($p['categoria_slug'])) ?></span>
                    <time class="font-mono text-amber-800 font-medium bg-amber-50 border border-amber-200 px-2 py-0.5 rounded"><?= e(date('d/m/Y', strtotime($p['fecha_publicacion']))) ?></time>
                </div>
                <h3 class="text-sm font-bold tracking-tight mt-2 text-slate-900 leading-snug">
                    <a href="/blog/<?= e($p['categoria_slug']) ?>/<?= e($p['articulo_slug']) ?>" class="hover:underline"><?= e($p['titulo']) ?></a>
                </h3>
            </div>
            <a href="/blog/<?= e($p['categoria_slug']) ?>/<?= e($p['articulo_slug']) ?>" class="text-xs font-medium text-blue-700 mt-3 inline-flex items-center gap-1 hover:underline">
                Leer vista previa &rarr;
            </a>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
