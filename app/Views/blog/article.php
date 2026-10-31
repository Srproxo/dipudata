<?php
/** @var array $articulo @var string $html @var array $mencionados @var array $relacionados @var string $categoriaSlug @var string $nombreCategoria */
use App\Models\LegisladorModel;
?>
<nav class="text-sm text-slate-500 mb-6" aria-label="Migas de pan">
    <a href="/" class="hover:text-slate-900">Inicio</a> / <a href="/blog" class="hover:text-slate-900">Blog</a> /
    <a href="/blog/<?= e($categoriaSlug) ?>" class="hover:text-slate-900"><?= e($nombreCategoria) ?></a>
</nav>

<div class="grid gap-8 lg:grid-cols-[1fr_18rem]">
    <article class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-10">
        <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight leading-tight"><?= e($articulo['titulo']) ?></h1>
        <?php if (!empty($articulo['fecha_publicacion'])): ?>
        <time datetime="<?= e(date('c', strtotime($articulo['fecha_publicacion']))) ?>" class="block font-mono text-sm text-slate-400 mt-3"><?= e(date('d/m/Y', strtotime($articulo['fecha_publicacion']))) ?></time>
        <?php endif; ?>
        <div class="mt-8 space-y-5 leading-relaxed text-slate-700 [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:tracking-tight [&_h2]:text-slate-900 [&_h2]:pt-4">
            <?= $html /* HTML editorial de confianza (tabla blog_articulos) con enlaces generados por InternalLinker */ ?>
        </div>
    </article>

    <aside class="space-y-6">
        <?php if ($mencionados !== []): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Legisladores en esta nota</h2>
            <ul class="mt-4 space-y-3">
                <?php foreach ($mencionados as $l): ?>
                <li>
                    <a href="<?= e(LegisladorModel::urlPerfil($l)) ?>" class="font-semibold hover:underline"><?= e($l['nombre']) ?></a>
                    <p class="text-xs text-slate-500"><?= e($l['bloque']) ?> · <?= e($l['provincia']) ?></p>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($relacionados !== []): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Más en <?= e($nombreCategoria) ?></h2>
            <ul class="mt-4 space-y-3 text-sm">
                <?php foreach ($relacionados as $r): ?>
                <li><a href="/blog/<?= e($categoriaSlug) ?>/<?= e($r['articulo_slug']) ?>" class="font-medium hover:underline"><?= e($r['titulo']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <a href="/match-360" class="block rounded-2xl bg-slate-900 text-white p-5 hover:bg-slate-800">
            <p class="font-bold">Match Electoral 360</p>
            <p class="text-sm text-slate-300 mt-1">Descubrí qué legislador vota como vos pensás.</p>
        </a>
    </aside>
</div>
