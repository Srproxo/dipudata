<?php /** @var string $categoria @var string $nombreCategoria @var array $articulos @var array $programados */ ?>
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

<?php if (!empty($programados)): ?>
<section class="mt-12 pt-8 border-t border-slate-200">
    <h2 class="text-xl font-bold tracking-tight text-slate-900">Próximas publicaciones en esta categoría</h2>
    <div class="grid gap-3 sm:grid-cols-2 mt-4">
        <?php foreach ($programados as $p): ?>
        <article class="bg-slate-50 border border-slate-200 rounded-xl p-4">
            <time class="font-mono text-xs text-amber-800 font-medium bg-amber-50 border border-amber-200 px-2 py-0.5 rounded"><?= e(date('d/m/Y', strtotime($p['fecha_publicacion']))) ?></time>
            <h3 class="text-sm font-bold tracking-tight mt-2 text-slate-900">
                <a href="/blog/<?= e($categoria) ?>/<?= e($p['articulo_slug']) ?>" class="hover:underline"><?= e($p['titulo']) ?></a>
            </h3>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
