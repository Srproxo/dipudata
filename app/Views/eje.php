<?php
/**
 * Vista de Silo Temático — DipuData (/eje/{slug_seo})
 * @var array $eje
 * @var array $leyes
 * @var array $ranking
 * @var array $otros_ejes
 */

$nombreEje = $eje['nombre_eje'] ?? $eje['nombre'] ?? '';
$descEje = $eje['descripcion'] ?? 'Análisis de posturas legislativas y votaciones nominales en este eje temático.';
$slugEje = $eje['slug_seo'] ?? \App\Config\Database::slugify($nombreEje);

$iconosEjes = [
    'salud-publica'      => '🏥',
    'educacion'          => '🎓',
    'apertura-comercial' => '🌐',
    'industria-nacional' => '🏭',
    'prevision-social'   => '👵',
    'medio-ambiente'     => '🌿',
    'seguridad'          => '⚖️',
    'genero'             => '🤝',
    'federalismo'        => '🌾',
    'transparencia'      => '🔍',
    'tecnologia'         => '💻',
];
$icono = $iconosEjes[$slugEje] ?? '📊';
?>

<!-- Migas de Pan (Breadcrumbs) -->
<nav class="text-sm text-slate-500 mb-6 flex flex-wrap items-center gap-1.5" aria-label="Migas de pan">
    <a href="/" class="hover:text-slate-900 transition-colors">Inicio</a>
    <span aria-hidden="true">/</span>
    <span class="text-slate-600">Ejes Temáticos</span>
    <span aria-hidden="true">/</span>
    <span class="text-slate-900 font-semibold"><?= e($nombreEje) ?></span>
</nav>

<!-- Encabezado de Silo Temático -->
<section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8 mb-8">
    <div class="flex flex-col md:flex-row md:items-start justify-between gap-6">
        <div class="space-y-3 max-w-3xl">
            <div class="flex items-center gap-2">
                <span class="text-2xl" aria-hidden="true"><?= $icono ?></span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-800 border border-blue-200">
                    Eje Temático del Congreso
                </span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">
                <?= e($nombreEje) ?>
            </h1>
            <p class="text-slate-600 text-sm sm:text-base leading-relaxed">
                <?= e($descEje) ?>
            </p>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-2 gap-3 shrink-0">
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 text-center">
                <span class="text-[10px] font-mono uppercase text-slate-400 block">Leyes Tratadas</span>
                <span class="text-lg font-bold text-slate-900 font-mono"><?= count($leyes) ?></span>
            </div>
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 text-center">
                <span class="text-[10px] font-mono uppercase text-slate-400 block">Legisladores Auditados</span>
                <span class="text-lg font-bold text-slate-900 font-mono"><?= count($ranking) ?></span>
            </div>
        </div>
    </div>
</section>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Columna Principal: Leyes Tratadas y Ranking de Legisladores -->
    <div class="lg:col-span-2 space-y-8">
        <!-- 1. Leyes Clave Tratadas en este Eje -->
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-lg font-bold tracking-tight text-slate-900 mb-4 flex items-center justify-between">
                <span>Leyes y Proyectos Clave en <?= e($nombreEje) ?></span>
                <span class="text-xs font-mono text-slate-400 font-normal"><?= count($leyes) ?> registrados</span>
            </h2>

            <?php if (!empty($leyes)): ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($leyes as $ley): ?>
                        <div class="py-3.5 first:pt-0 last:pb-0 space-y-1.5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-mono text-xs font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded">
                                    Expediente <?= e($ley['expediente']) ?>
                                </span>
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $ley['estado_tramite'] === 'Sancionada' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?>">
                                    <?= e($ley['estado_tramite']) ?>
                                </span>
                            </div>
                            <h3 class="text-sm font-semibold text-slate-900 leading-snug">
                                <?= e($ley['titulo']) ?>
                            </h3>
                            <div class="flex items-center gap-3 text-[11px] text-slate-500 font-mono">
                                <?php if (!empty($ley['sector_vinculado'])): ?>
                                    <span>Sector: <?= e($ley['sector_vinculado']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($ley['fecha_tratamiento'])): ?>
                                    <span>Tratamiento: <?= e(date('d/m/Y', strtotime($ley['fecha_tratamiento']))) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-slate-500 italic">No se registran expedientes principales categorizados en este eje por el momento.</p>
            <?php endif; ?>
        </section>

        <!-- 2. Ranking de Legisladores en este Eje -->
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight text-slate-900">
                        Postura de Legisladores en <?= e($nombreEje) ?>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Porcentaje de afinidad / apoyo a iniciativas y votaciones en este eje temático.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase font-semibold text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="text-left px-4 py-2.5">Legislador</th>
                            <th class="text-left px-4 py-2.5">Cámara / Provincia</th>
                            <th class="text-left px-4 py-2.5">Bloque</th>
                            <th class="text-right px-4 py-2.5">Afinidad Eje</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($ranking as $leg): 
                            $colores = partido_colores($leg['bloque'] ?? '');
                            $afinidad = (float)($leg['afinidad_eje'] ?? 50.0);
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3">
                                <a href="<?= e($leg['url']) ?>" class="font-bold text-slate-900 hover:text-blue-600 transition-colors flex items-center gap-2.5">
                                    <?php if (!empty($leg['foto_url'])): ?>
                                        <img src="<?= e($leg['foto_url']) ?>" alt="<?= e($leg['nombre']) ?>" class="w-7 h-7 rounded-full object-cover shrink-0 border <?= e($colores['border']) ?>">
                                    <?php else: ?>
                                        <span class="w-7 h-7 rounded-full bg-slate-200 text-slate-700 text-xs font-bold grid place-items-center shrink-0">
                                            <?= mb_substr($leg['nombre'], 0, 1) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span><?= e($leg['nombre']) ?></span>
                                </a>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">
                                <span class="font-medium text-slate-800"><?= e($leg['camara']) ?></span> · <?= e($leg['provincia']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold <?= e($colores['badge']) ?>">
                                    <?= e($leg['bloque']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2 font-mono">
                                    <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden hidden sm:block">
                                        <div class="h-full <?= $afinidad >= 60 ? 'bg-emerald-500' : ($afinidad >= 40 ? 'bg-amber-500' : 'bg-rose-500') ?>" style="width: <?= min(100, max(0, $afinidad)) ?>%"></div>
                                    </div>
                                    <span class="font-bold text-xs <?= $afinidad >= 60 ? 'text-emerald-700' : ($afinidad >= 40 ? 'text-amber-700' : 'text-rose-700') ?>">
                                        <?= number_format($afinidad, 1) ?>%
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- Columna Lateral: Silo Architecture (Otros Ejes Temáticos & Match 360) -->
    <div class="space-y-6">
        <!-- Call to Action: Match 360 -->
        <div class="bg-gradient-to-br from-blue-900 to-indigo-950 text-white rounded-2xl p-6 shadow-sm space-y-4">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-500/20 text-blue-200 border border-blue-400/30 font-mono">
                Match Electoral 360
            </span>
            <h3 class="text-lg font-bold leading-tight">
                ¿Qué legislador vota como vos en <?= e($nombreEje) ?>?
            </h3>
            <p class="text-xs text-blue-200 leading-relaxed">
                Respondé el test dinámico de 11 preguntas y descubrí tu grado de afinidad ideológica con diputados y senadores.
            </p>
            <a href="/match-360" class="inline-flex items-center justify-center w-full px-4 py-2.5 rounded-xl bg-white text-slate-900 font-bold text-xs hover:bg-blue-50 transition-colors shadow-sm">
                Hacer el Test de Afinidad →
            </a>
        </div>

        <!-- Silo Interlinking: Los otros 10 Ejes Temáticos -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">
                Otros Ejes Temáticos del Congreso
            </h3>
            <div class="space-y-1.5">
                <?php foreach ($otros_ejes as $oe): 
                    $oeSlug = $oe['slug_seo'] ?? \App\Config\Database::slugify($oe['nombre_eje'] ?? $oe['nombre']);
                    $oeName = $oe['nombre_eje'] ?? $oe['nombre'];
                    $oeIcon = $iconosEjes[$oeSlug] ?? '📌';
                ?>
                <a href="/eje/<?= e($oeSlug) ?>" class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 transition-colors group">
                    <span class="flex items-center gap-2 text-xs font-medium text-slate-700 group-hover:text-blue-700">
                        <span aria-hidden="true"><?= $oeIcon ?></span>
                        <span><?= e($oeName) ?></span>
                    </span>
                    <span class="text-slate-400 group-hover:text-blue-600 text-xs">→</span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
