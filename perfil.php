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
    'Salud Pública'      => ['bar' => 'bg-teal-500',   'txt' => 'text-teal-700'],
    'Educación Pública'  => ['bar' => 'bg-blue-500',   'txt' => 'text-blue-700'],
    'Industria Nacional' => ['bar' => 'bg-indigo-500', 'txt' => 'text-indigo-700'],
    'Apertura Comercial' => ['bar' => 'bg-amber-500',  'txt' => 'text-amber-700'],
];
$badgeVoto = [
    'Afirmativo' => 'bg-emerald-100 text-emerald-700',
    'Negativo'   => 'bg-rose-100 text-rose-700',
    'Abstención' => 'bg-slate-200 text-slate-600',
    'Ausente'    => 'bg-slate-200 text-slate-600',
];
$maxMonto = max(array_map(fn($p) => (float)$p['monto_declarado'], $data['patrimonio']) ?: [1]);
$iniciales = implode('', array_map(fn($p) => mb_substr($p, 0, 1), array_slice(explode(' ', $leg['nombre']), 0, 2)));

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
        <div class="mt-6 space-y-5">
            <?php foreach ($data['perfil'] as $eje => $valor): ?>
            <div>
                <div class="flex justify-between text-sm mb-1.5">
                    <span class="font-medium"><?= e($eje) ?></span>
                    <span class="font-mono <?= e($colores[$eje]['txt']) ?>"><?= e(pct($valor)) ?></span>
                </div>
                <div class="h-2.5 bg-slate-200 rounded-full overflow-hidden" role="progressbar" aria-valuenow="<?= e($valor) ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= e($eje) ?>">
                    <div class="h-full rounded-full <?= e($colores[$eje]['bar']) ?>" style="width: <?= e($valor) ?>%"></div>
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
</div>
<?php require __DIR__ . '/app/Views/layouts/footer.php'; ?>
