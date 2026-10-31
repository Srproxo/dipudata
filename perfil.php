<?php
declare(strict_types=1);

/**
 * Vista Perfil del Legislador.
 * Vía router: /diputado/{slug} | /senador/{slug}  (define $slugLegislador y $camara)
 * Acceso directo: /perfil.php?slug={slug}
 */

require_once __DIR__ . '/app/bootstrap.php';

use App\Controllers\LegisladorController;
use App\Models\LegisladorModel;

$slugLegislador ??= (string)preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($_GET['slug'] ?? '')));
$camara ??= null;

$data = $slugLegislador !== '' ? (new LegisladorController())->perfil($slugLegislador, $camara) : null;
if ($data === null) {
    render_404();
    return;
}

$leg = $data['legislador'];
$cargo = $leg['camara'] === 'Senadores' ? 'Senador/a Nacional' : 'Diputado/a Nacional';
$real = $data['resumen']['crecimiento_real_pct'];
$url = SITE_URL . $data['url'];

$meta = [
    'title'       => "{$leg['nombre']} ({$cargo}, {$leg['provincia']}) | Votaciones y patrimonio real | DipuData",
    'description' => sprintf(
        '%s, %s por %s (%s). Asistencia %s, lealtad partidaria %s y crecimiento patrimonial real %s descontada la inflación.',
        $leg['nombre'], $cargo, $leg['provincia'], $leg['bloque'],
        pct((float)$leg['asistencia']), pct((float)$leg['indice_lealtad']), pct($real, true)
    ),
    'canonical'   => $url,
    'og_image'    => SITE_URL . '/api/og',
    'jsonld'      => [
        '@context' => 'https://schema.org',
        '@graph'   => [
            array_filter([
                '@type'       => 'Person',
                'name'        => $leg['nombre'],
                'jobTitle'    => $cargo,
                'url'         => $url,
                'image'       => $leg['foto_url'] ?: null,
                'description' => $leg['biografia_extracto'] ?: null,
                'affiliation' => ['@type' => 'Organization', 'name' => $leg['bloque']],
                'workLocation'=> ['@type' => 'AdministrativeArea', 'name' => $leg['provincia']],
                'memberOf'    => ['@type' => 'GovernmentOrganization', 'name' => $leg['camara'] === 'Senadores' ? 'Honorable Senado de la Nación Argentina' : 'Honorable Cámara de Diputados de la Nación Argentina'],
                'sameAs'      => $leg['nombre_wiki'] ? ['https://es.wikipedia.org/wiki/' . rawurlencode($leg['nombre_wiki'])] : null,
            ]),
            self_breadcrumb_perfil($leg, $data['url']),
        ],
    ],
];

$colores = [
    'Salud Pública'                     => ['bar' => 'bg-teal-500',    'txt' => 'text-teal-700'],
    'Educación'                         => ['bar' => 'bg-blue-500',    'txt' => 'text-blue-700'],
    'Educación Pública'                 => ['bar' => 'bg-blue-500',    'txt' => 'text-blue-700'],
    'Apertura Comercial'                => ['bar' => 'bg-amber-500',   'txt' => 'text-amber-700'],
    'Industria Nacional'                => ['bar' => 'bg-indigo-500',  'txt' => 'text-indigo-700'],
    'Previsión Social y Discapacidad'   => ['bar' => 'bg-rose-500',    'txt' => 'text-rose-700'],
    'Medio Ambiente y Derechos Humanos' => ['bar' => 'bg-emerald-500', 'txt' => 'text-emerald-700'],
    'Seguridad y Sistema Penal'         => ['bar' => 'bg-slate-700',   'txt' => 'text-slate-800'],
    'Género y Cuidados'                 => ['bar' => 'bg-fuchsia-500', 'txt' => 'text-fuchsia-700'],
    'Federalismo y Agro'                => ['bar' => 'bg-lime-600',    'txt' => 'text-lime-800'],
    'Transparencia Institucional'       => ['bar' => 'bg-cyan-600',    'txt' => 'text-cyan-800'],
    'Tecnología y Trabajo'              => ['bar' => 'bg-violet-500',  'txt' => 'text-violet-700'],
];
$colorDefault = ['bar' => 'bg-blue-600', 'txt' => 'text-blue-700'];
$badgeVoto = [
    'Afirmativo' => 'bg-emerald-100 text-emerald-700',
    'Negativo'   => 'bg-rose-100 text-rose-700',
    'Abstención' => 'bg-slate-200 text-slate-600',
    'Ausente'    => 'bg-slate-200 text-slate-600',
];
$maxMonto = max(array_map(fn($p) => (float)$p['monto_declarado'], $data['patrimonio']) ?: [1]);
$iniciales = implode('', array_map(fn($p) => mb_substr($p, 0, 1), array_slice(explode(' ', $leg['nombre']), 0, 2)));

$agenda = $data['agenda'] ?? ['puntaje_proyectos' => [], 'top3_ejes' => [], 'total_proyectos' => 0, 'total_autor' => 0, 'total_coautor' => 0];
$ultimosProyectos = $data['ultimos_proyectos'] ?? [];

send_view_headers();
require __DIR__ . '/app/Views/layouts/header.php';
?>
<nav class="text-sm text-slate-500 mb-6" aria-label="Migas de pan">
    <a href="/" class="hover:text-slate-900">Inicio</a> <span aria-hidden="true">/</span>
    <span><?= e($leg['camara']) ?></span> <span aria-hidden="true">/</span>
    <span class="text-slate-900 font-medium"><?= e($leg['nombre']) ?></span>
</nav>

<div class="grid gap-6 lg:grid-cols-3">
    <!-- 1. Bio -->
    <section class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="flex gap-5 items-start">
            <?php if (!empty($leg['foto_url'])): ?>
                <img src="<?= e($leg['foto_url']) ?>" alt="Foto de <?= e($leg['nombre']) ?>" width="96" height="96" class="w-24 h-24 rounded-full object-cover border-2 border-slate-200 shrink-0" loading="eager">
            <?php else: ?>
                <div class="w-24 h-24 rounded-full bg-slate-100 border-2 border-slate-200 grid place-items-center text-2xl font-bold text-slate-500 shrink-0" aria-hidden="true"><?= e($iniciales) ?></div>
            <?php endif; ?>
            <div class="min-w-0">
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight"><?= e($leg['nombre']) ?></h1>
                <p class="text-slate-500 mt-1"><?= e($cargo) ?></p>
                <div class="flex flex-wrap gap-2 mt-3">
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-700"><?= e($leg['provincia']) ?></span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-700"><?= e($leg['bloque']) ?></span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-mono bg-slate-50 text-slate-500 border border-slate-200">CUIT <?= e($leg['cuit']) ?></span>
                </div>
            </div>
        </div>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mt-6">Biografía</h2>
        <p class="mt-2 leading-relaxed text-slate-700"><?= e($leg['biografia_extracto'] ?: 'Biografía pendiente de sincronización con Wikipedia.') ?></p>
        <?php if ($leg['nombre_wiki']): ?>
            <a href="https://es.wikipedia.org/wiki/<?= e(rawurlencode($leg['nombre_wiki'])) ?>" rel="noopener nofollow" target="_blank" class="inline-block mt-2 text-sm text-blue-700 hover:underline">Fuente: Wikipedia ↗</a>
        <?php endif; ?>
    </section>

    <!-- KPIs -->
    <aside class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 grid grid-cols-2 lg:grid-cols-1 gap-6 content-start">
        <div>
            <p class="text-sm text-slate-500">Asistencia histórica</p>
            <p class="font-mono text-3xl font-semibold mt-1"><?= e(pct((float)$leg['asistencia'])) ?></p>
        </div>
        <div>
            <p class="text-sm text-slate-500">Lealtad partidaria</p>
            <p class="font-mono text-3xl font-semibold mt-1"><?= e(pct((float)$leg['indice_lealtad'])) ?></p>
        </div>
        <div class="col-span-2 lg:col-span-1 rounded-xl p-4 <?= $real >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>">
            <p class="text-sm font-medium">Crecimiento patrimonial real</p>
            <p class="font-mono text-3xl font-semibold mt-1"><?= $real >= 0 ? '▲' : '▼' ?> <?= e(pct($real, true)) ?></p>
            <p class="text-xs mt-1 opacity-80">Descontada la inflación (IPC INDEC)</p>
        </div>
    </aside>

    <!-- 2. Patrimonio -->
    <section class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-xl font-bold tracking-tight">Evolución patrimonial ajustada por inflación</h2>
        <p class="text-sm text-slate-500 mt-1">
            Nominal <span class="font-mono"><?= e(pct($data['resumen']['crecimiento_nominal_pct'], true)) ?></span> ·
            Inflación acumulada <span class="font-mono"><?= e(pct($data['resumen']['inflacion_acumulada_pct'])) ?></span> ·
            Real <span class="font-mono font-semibold <?= $real >= 0 ? 'text-emerald-700' : 'text-rose-700' ?>"><?= e(pct($real, true)) ?></span>
        </p>
        <?php if ($data['patrimonio'] === []): ?>
            <p class="mt-6 text-slate-500">Sin declaraciones juradas cargadas.</p>
        <?php else: ?>
        <div class="mt-6 space-y-4">
            <?php foreach ($data['patrimonio'] as $p): $v = (float)$p['variacion_real_porcentaje']; ?>
            <div class="grid grid-cols-[3rem_1fr_auto] items-center gap-3">
                <span class="font-mono text-sm text-slate-500"><?= e($p['anio']) ?></span>
                <div class="h-2.5 bg-slate-200 rounded-full overflow-hidden" role="img" aria-label="Patrimonio <?= e($p['anio']) ?>: <?= e(ars((float)$p['monto_declarado'])) ?>">
                    <div class="h-full bg-slate-700 rounded-full" style="width: <?= round((float)$p['monto_declarado'] / $maxMonto * 100, 1) ?>%"></div>
                </div>
                <span class="font-mono text-sm tabular-nums text-right w-44">
                    <?= e(ars((float)$p['monto_declarado'])) ?>
                    <span class="ml-1 <?= $v > 0 ? 'text-emerald-700' : ($v < 0 ? 'text-rose-700' : 'text-slate-400') ?>"><?= e(pct($v, true)) ?></span>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="text-xs text-slate-400 mt-4">Variación real interanual = (Monto año / Monto año anterior) ÷ (1 + IPC) − 1. Fuente: DDJJ Oficina Anticorrupción.</p>
        <?php endif; ?>
    </section>

    <!-- 3. Ejes temáticos -->
    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-xl font-bold tracking-tight">Apoyo por eje temático</h2>
        <p class="text-sm text-slate-500 mt-1">Votos afirmativos sobre votos emitidos (excluye ausencias).</p>
        <div class="mt-6 space-y-4">
            <?php foreach ($data['perfil'] as $eje => $valor):
                $c = $colores[$eje] ?? $colorDefault;
            ?>
            <div>
                <div class="flex justify-between text-sm mb-1.5">
                    <span class="font-medium text-slate-800"><?= e($eje) ?></span>
                    <span class="font-mono text-xs font-semibold <?= e($c['txt']) ?>"><?= e(pct($valor)) ?></span>
                </div>
                <div class="h-2 bg-slate-200 rounded-full overflow-hidden" role="progressbar" aria-valuenow="<?= e($valor) ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= e($eje) ?>">
                    <div class="h-full rounded-full <?= e($c['bar']) ?>" style="width: <?= e($valor) ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <a href="/match-360" class="mt-6 block text-center rounded-lg bg-slate-900 text-white text-sm font-semibold py-2.5 hover:bg-slate-800">¿Coincidís con este perfil? Hacé el Match 360</a>
    </section>

    <!-- Votaciones -->
    <section class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <h2 class="text-xl font-bold tracking-tight p-6 pb-4">Votaciones nominales</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase font-semibold text-slate-500">
                    <tr><th class="text-left px-6 py-3">Ley</th><th class="text-left px-6 py-3">Eje</th><th class="text-left px-6 py-3">Fecha</th><th class="text-left px-6 py-3">Voto</th></tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($data['votaciones'], 0, 10) as $v): ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-6 py-3 min-w-64"><?= e($v['ley']) ?></td>
                        <td class="px-6 py-3 whitespace-nowrap text-slate-500"><?= e($v['eje_tematico']) ?></td>
                        <td class="px-6 py-3 whitespace-nowrap font-mono text-slate-500"><?= e(date('d/m/Y', strtotime($v['fecha']))) ?></td>
                        <td class="px-6 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= e($badgeVoto[$v['voto']] ?? '') ?>"><?= e($v['voto']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 4. Agenda Legislativa Propia (Fase 3 - Proactividad e Iniciativa Parlamentaria) -->
    <section class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                        Iniciativa Parlamentaria
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Ponderación Proactiva (30%)</span>
                </div>
                <h2 class="text-xl font-bold tracking-tight text-slate-900 mt-1">Agenda Legislativa Propia</h2>
                <p class="text-sm text-slate-500">
                    Proyectos de ley redactados y presentados en el Congreso. Mide el foco proactivo más allá de la disciplina partidaria en votaciones.
                </p>
            </div>
            <div class="flex items-center gap-3 text-xs font-mono">
                <div class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-center">
                    <span class="block text-slate-400 uppercase text-[10px]">Autor Principal</span>
                    <strong class="text-slate-900 text-sm"><?= (int)($agenda['total_autor'] ?? 0) ?></strong>
                </div>
                <div class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-center">
                    <span class="block text-slate-400 uppercase text-[10px]">Co-autor</span>
                    <strong class="text-slate-700 text-sm"><?= (int)($agenda['total_coautor'] ?? 0) ?></strong>
                </div>
                <div class="px-3 py-1.5 bg-indigo-50 border border-indigo-200 rounded-lg text-center">
                    <span class="block text-indigo-600 uppercase text-[10px]">Total Proyectos</span>
                    <strong class="text-indigo-900 text-sm"><?= (int)($agenda['total_proyectos'] ?? 0) ?></strong>
                </div>
            </div>
        </div>

        <!-- Treemap Simple de los 3 Ejes Prioritarios -->
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 mb-3 flex items-center justify-between">
                <span>Top 3 Ejes de Concentración de Agenda</span>
                <span class="text-xs font-normal text-slate-400 lowercase">ponderación: autor x2, co-autor x1</span>
            </h3>
            
            <?php if (!empty($agenda['top3_ejes'])): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php 
                $treemapStyles = [
                    0 => ['bg' => 'bg-indigo-50/70', 'border' => 'border-indigo-200', 'badge' => 'bg-indigo-600 text-white', 'text' => 'text-indigo-950', 'sub' => 'text-indigo-600', 'progress' => 'bg-indigo-600'],
                    1 => ['bg' => 'bg-emerald-50/70', 'border' => 'border-emerald-200', 'badge' => 'bg-emerald-600 text-white', 'text' => 'text-emerald-950', 'sub' => 'text-emerald-600', 'progress' => 'bg-emerald-600'],
                    2 => ['bg' => 'bg-amber-50/70', 'border' => 'border-amber-200', 'badge' => 'bg-amber-600 text-white', 'text' => 'text-amber-950', 'sub' => 'text-amber-600', 'progress' => 'bg-amber-600'],
                ];
                foreach ($agenda['top3_ejes'] as $idx => $item): 
                    $st = $treemapStyles[$idx] ?? $treemapStyles[0];
                ?>
                <div class="rounded-xl border <?= $st['border'] ?> <?= $st['bg'] ?> p-4 transition-all hover:shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded <?= $st['badge'] ?>">
                                #<?= $idx + 1 ?> Prioridad
                            </span>
                            <span class="font-mono text-lg font-bold <?= $st['sub'] ?>">
                                <?= number_format((float)$item['porcentaje'], 1) ?>%
                            </span>
                        </div>
                        <h4 class="font-bold <?= $st['text'] ?> text-base tracking-tight leading-snug">
                            <?= e($item['eje']) ?>
                        </h4>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-200/60">
                        <div class="flex justify-between text-xs text-slate-600 mb-1.5 font-mono">
                            <span><?= (int)$item['proyectos'] ?> <?= (int)$item['proyectos'] === 1 ? 'proyecto' : 'proyectos' ?></span>
                            <span><?= number_format((float)$item['puntos'], 1) ?> pts</span>
                        </div>
                        <div class="h-1.5 bg-slate-200/80 rounded-full overflow-hidden">
                            <div class="h-full <?= $st['progress'] ?> rounded-full" style="width: <?= min(100, max(5, (float)$item['porcentaje'])) ?>%"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-sm text-slate-500 italic p-4 bg-slate-50 rounded-xl border border-slate-200">
                No se registran iniciativas legislativas propias en el período analizado.
            </p>
            <?php endif; ?>
        </div>

        <!-- Tabla Filtrable: Últimos 5 Proyectos como Autor Principal -->
        <div class="pt-2">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500">
                        Últimos Proyectos Presentados como Autor Principal
                    </h3>
                    <p class="text-xs text-slate-400">Iniciativas de autoría directa registradas con expediente oficial</p>
                </div>
                <div class="relative max-w-xs w-full">
                    <input type="text" id="filtroProyectos" placeholder="Filtrar por texto o estado..." 
                           class="w-full text-xs rounded-lg border border-slate-300 pl-8 pr-3 py-1.5 focus:border-slate-800 focus:outline-none focus:ring-1 focus:ring-slate-800"
                           aria-label="Filtrar proyectos de autoría">
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-sm" id="tablaProyectos">
                    <thead class="bg-slate-50 text-xs uppercase font-semibold text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="text-left px-4 py-3 whitespace-nowrap">Fecha</th>
                            <th class="text-left px-4 py-3 whitespace-nowrap">Expediente</th>
                            <th class="text-left px-4 py-3 min-w-[280px]">Título Sumario</th>
                            <th class="text-left px-4 py-3 whitespace-nowrap">Eje Temático</th>
                            <th class="text-left px-4 py-3 whitespace-nowrap">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    <?php 
                    $badgeEstado = [
                        'Sancionado'    => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        'Media Sanción' => 'bg-sky-50 text-sky-700 border-sky-200',
                        'Con Dictamen'  => 'bg-amber-50 text-amber-700 border-amber-200',
                        'En Comisión'   => 'bg-slate-100 text-slate-700 border-slate-200',
                        'En Trámite'    => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                    ];
                    if (!empty($ultimosProyectos)):
                        foreach ($ultimosProyectos as $p):
                            $bCls = $badgeEstado[$p['estado_tramite']] ?? 'bg-slate-100 text-slate-600 border-slate-200';
                    ?>
                        <tr class="hover:bg-slate-50/70 transition-colors fila-proyecto">
                            <td class="px-4 py-3 font-mono text-xs text-slate-500 whitespace-nowrap">
                                <?= e(date('d/m/Y', strtotime($p['fecha_presentacion']))) ?>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-700 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200"><?= e($p['expediente']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-slate-800 text-xs sm:text-sm font-medium">
                                <?= e($p['titulo_sumario']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-600 font-medium">
                                <?= e($p['eje_tematico']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-xs">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border <?= $bCls ?>">
                                    <?= e($p['estado_tramite']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-xs text-slate-400 italic">
                                No se encontraron proyectos de autoría directa para este legislador.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('filtroProyectos');
        if (!input) return;
        input.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const filas = document.querySelectorAll('.fila-proyecto');
            filas.forEach(function (fila) {
                const texto = fila.textContent.toLowerCase();
                fila.style.display = texto.includes(query) ? '' : 'none';
            });
        });
    });
    </script>
</div>
<?php require __DIR__ . '/app/Views/layouts/footer.php'; ?>
