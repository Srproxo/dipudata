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

try {
    $data = $slugLegislador !== '' ? (new LegisladorController())->perfil($slugLegislador, $camara) : null;
} catch (\Throwable) {
    try {
        $data = $slugLegislador !== '' ? (new LegisladorController(new LegisladorModel(null)))->perfil($slugLegislador, $camara) : null;
    } catch (\Throwable) {
        $data = null;
    }
}
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
$defensaProv = $data['defensa_provincial'] ?? [
    'indice_lealtad_partido'     => (float)$leg['indice_lealtad'],
    'indice_defensa_provincial'  => 100.0,
    'brecha_tension'             => 0.0,
    'alertas_conflicto'          => [],
    'todos_los_votos'            => [],
];
$despacho = $data['despacho'] ?? null;
$mediaCamaraCosto = (float)($data['media_camara_costo'] ?? 50660000.00);
$costoPorProyecto = (float)($despacho['costo_por_proyecto_ars'] ?? 0.0);
$superaMedia = ($costoPorProyecto > $mediaCamaraCosto);
$alertasNepotismo = (int)($despacho['alertas_nepotismo'] ?? 0);
$totalAsesores = (int)($despacho['total_asesores'] ?? 0);
$costoMensual = (float)($despacho['costo_mensual_ars'] ?? 0.0);
$costoAnual = (float)($despacho['costo_anual_ars'] ?? 0.0);
$proyectosAutorAnio = (int)($despacho['proyectos_autor_anio'] ?? 0);

$auditoria = $data['auditoria'] ?? (new \App\Controllers\AuditorEngineController())->obtenerAuditoria((int)$leg['id']);
$coherencia = $auditoria['coherencia_espejo'] ?? ['indice_coherencia' => 100.0, 'alertas_panquequismo' => []];
$alianzas = $auditoria['grafo_alianzas'] ?? ['indice_transversalidad' => 0.0, 'bloques_aliados' => [], 'socios_frecuentes' => []];
$conflictos = $auditoria['conflictos_interes'] ?? ['total_aportes_declarados_ars' => 0.0, 'alertas_conflicto' => [], 'sectores_financiadores' => [], 'nivel_riesgo_etico' => 'Bajo'];
$promesas = $auditoria['promesas_campana'] ?? ['total_promesas' => 0, 'cumplidas' => 0, 'incumplidas' => 0, 'tasa_cumplimiento_pct' => 100.0, 'promesas' => []];

// Pre-cálculo Gráficos: 1. Evolución Patrimonial Real (USD MEP)
$evo = $data['evolucion'] ?? null;
$serieEvo = !empty($evo['serie']) ? $evo['serie'] : [];
$crecimientoRealUsd = (float)($evo['crecimiento_real_usd_pct'] ?? 0.0);
$crecimientoConstantes = (float)($evo['crecimiento_pesos_constantes_pct'] ?? 0.0);
$patrimonioInicialUsd = (float)($evo['patrimonio_inicial_usd'] ?? 0.0);
$patrimonioActualUsd = (float)($evo['patrimonio_actual_usd'] ?? 0.0);
$anioInicial = $evo['anio_inicial'] ?? (!empty($serieEvo) ? $serieEvo[0]['anio_fiscal'] : 2020);
$anioActual = $evo['anio_actual'] ?? (!empty($serieEvo) ? $serieEvo[count($serieEvo) - 1]['anio_fiscal'] : 2025);
$isPatrimonioCrecimiento = ($crecimientoRealUsd >= 0);

$chartWidth = 680;
$chartHeight = 270;
$padLeft = 74;
$padRight = 28;
$padTop = 26;
$padBottom = 42;
$plotW = $chartWidth - $padLeft - $padRight;
$plotH = $chartHeight - $padTop - $padBottom;

$chartPoints = [];
$linePath = '';
$areaPath = '';
$strokeColor = $isPatrimonioCrecimiento ? '#059669' : '#e11d48';
$yTicks = [0, 0.33, 0.66, 1.0];
$minVal = 0.0;
$maxVal = 1.0;
$valRange = 1.0;

if (!empty($serieEvo)) {
    $usdValues = array_map(fn($d) => (float)$d['patrimonio_usd'], $serieEvo);
    $minVal = min($usdValues) * 0.88;
    $maxVal = max($usdValues) * 1.08;
    $valRange = max($maxVal - $minVal, 1.0);
    $nPoints = count($serieEvo);

    foreach ($serieEvo as $i => $d) {
        $x = $nPoints === 1 ? $padLeft + $plotW / 2 : $padLeft + ($i / ($nPoints - 1)) * $plotW;
        $y = $padTop + $plotH - (((float)$d['patrimonio_usd'] - $minVal) / $valRange) * $plotH;
        $chartPoints[] = ['x' => $x, 'y' => $y, 'data' => $d];
    }

    $tension = 0.4;
    $linePath = 'M ' . number_format($chartPoints[0]['x'], 1, '.', '') . ' ' . number_format($chartPoints[0]['y'], 1, '.', '');
    for ($i = 0; $i < $nPoints - 1; $i++) {
        $p0 = $chartPoints[$i === 0 ? 0 : $i - 1];
        $p1 = $chartPoints[$i];
        $p2 = $chartPoints[$i + 1];
        $p3 = $chartPoints[$i + 2 < $nPoints ? $i + 2 : $i + 1];

        $cp1x = $p1['x'] + (($p2['x'] - $p0['x']) / 6) * $tension * 2.2;
        $cp1y = $p1['y'] + (($p2['y'] - $p0['y']) / 6) * $tension * 2.2;
        $cp2x = $p2['x'] - (($p3['x'] - $p1['x']) / 6) * $tension * 2.2;
        $cp2y = $p2['y'] - (($p3['y'] - $p1['y']) / 6) * $tension * 2.2;

        $linePath .= sprintf(' C %.1f %.1f, %.1f %.1f, %.1f %.1f', $cp1x, $cp1y, $cp2x, $cp2y, $p2['x'], $p2['y']);
    }

    $lastX = number_format($chartPoints[$nPoints - 1]['x'], 1, '.', '');
    $lastY = number_format($padTop + $plotH, 1, '.', '');
    $firstX = number_format($chartPoints[0]['x'], 1, '.', '');
    $areaPath = "{$linePath} L {$lastX} {$lastY} L {$firstX} {$lastY} Z";
}

// Pre-cálculo Gráficos: 2. Gráfico de Radar de Posicionamiento Temático
$radarSize = 360;
$radarCenter = $radarSize / 2;
$radarRadius = 108;
$radarAxes = array_keys($data['perfil']);
$totalRadarAxes = max(1, count($radarAxes));
$radarRings = [25, 50, 75, 100];

$shortLabels = [
    'Salud Pública'                     => 'Salud',
    'Educación'                         => 'Educación',
    'Educación Pública'                 => 'Educación',
    'Apertura Comercial'                => 'Comercio',
    'Industria Nacional'                => 'Industria',
    'Previsión Social y Discapacidad'   => 'Jubilados',
    'Medio Ambiente y Derechos Humanos' => 'Ambiente',
    'Seguridad y Sistema Penal'         => 'Seguridad',
    'Género y Cuidados'                 => 'Género',
    'Federalismo y Agro'                => 'Federalismo',
    'Transparencia Institucional'       => 'Transparencia',
    'Tecnología y Trabajo'              => 'Tecnología',
];

$radarPolygonPoints = [];
$radarVertices = [];
foreach ($radarAxes as $i => $axisName) {
    $val = (float)($data['perfil'][$axisName] ?? 0);
    $angle = ($i * 2 * M_PI / $totalRadarAxes) - (M_PI / 2);
    $r = (max(0, min(100, $val)) / 100) * $radarRadius;
    $x = $radarCenter + $r * cos($angle);
    $y = $radarCenter + $r * sin($angle);
    $radarPolygonPoints[] = sprintf('%.1f,%.1f', $x, $y);

    $outerX = $radarCenter + $radarRadius * cos($angle);
    $outerY = $radarCenter + $radarRadius * sin($angle);
    $labelDist = $radarRadius + 22;
    $lblX = $radarCenter + $labelDist * cos($angle);
    $lblY = $radarCenter + $labelDist * sin($angle);

    $anchor = 'middle';
    $cosVal = cos($angle);
    if ($cosVal > 0.25) {
        $anchor = 'start';
    } elseif ($cosVal < -0.25) {
        $anchor = 'end';
    }

    $radarVertices[] = [
        'axis'      => $axisName,
        'short'     => $shortLabels[$axisName] ?? $axisName,
        'val'       => $val,
        'angle'     => $angle,
        'x'         => $x,
        'y'         => $y,
        'outerX'    => $outerX,
        'outerY'    => $outerY,
        'lblX'      => $lblX,
        'lblY'      => $lblY,
        'anchor'    => $anchor,
    ];
}
$radarPointsStr = implode(' ', $radarPolygonPoints);

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

    <!-- 2. Patrimonio: Módulo Financiero con Gráfico Interactivo de Líneas y Área -->
    <section class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
        <div>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">
                        Módulo Financiero · Oficina Anticorrupción
                    </span>
                    <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight mt-0.5">
                        Evolución Patrimonial Real (USD MEP)
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Evolución patrimonial ajustada por inflación (IPC INDEC) y serie histórica en Dólar MEP.
                    </p>
                </div>

                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl font-mono tabular-nums text-sm font-bold self-start sm:self-auto <?= $isPatrimonioCrecimiento ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' : 'text-rose-700 bg-rose-50 border border-rose-200' ?>">
                    <span class="text-base"><?= $isPatrimonioCrecimiento ? '▲' : '▼' ?></span>
                    <div>
                        <div class="text-[10px] font-sans uppercase tracking-wider opacity-80">
                            Crecimiento real USD (<?= e($anioInicial) ?>–<?= e($anioActual) ?>)
                        </div>
                        <div class="text-base leading-tight">
                            <?= e(pct($crecimientoRealUsd, true)) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarjetas resumen del período -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 my-5">
                <div class="rounded-xl bg-slate-50 border border-slate-200/80 p-3.5">
                    <div class="text-xs text-slate-500 font-medium">Patrimonio Inicial (<?= e($anioInicial) ?>)</div>
                    <div class="text-lg font-mono tabular-nums font-bold text-slate-900 mt-0.5">
                        USD <?= number_format($patrimonioInicialUsd, 0, ',', '.') ?>
                    </div>
                    <div class="text-[11px] font-mono tabular-nums text-slate-400 mt-0.5">
                        ARS nominal: <?= e(ars((float)($serieEvo[0]['monto_pesos_nominal'] ?? 0))) ?>
                    </div>
                </div>

                <div class="rounded-xl bg-slate-50 border border-slate-200/80 p-3.5">
                    <div class="text-xs text-slate-500 font-medium">Última DDJJ (<?= e($anioActual) ?>)</div>
                    <div class="text-lg font-mono tabular-nums font-bold text-slate-900 mt-0.5">
                        USD <?= number_format($patrimonioActualUsd, 0, ',', '.') ?>
                    </div>
                    <div class="text-[11px] font-mono tabular-nums text-slate-400 mt-0.5">
                        ARS nominal: <?= e(ars((float)($serieEvo[count($serieEvo)-1]['monto_pesos_nominal'] ?? 0))) ?>
                    </div>
                </div>

                <div class="rounded-xl bg-slate-50 border border-slate-200/80 p-3.5">
                    <div class="text-xs text-slate-500 font-medium">Variación en ARS Constantes (IPC)</div>
                    <div class="text-lg font-mono tabular-nums font-bold mt-0.5 <?= $crecimientoConstantes >= 0 ? 'text-emerald-700' : 'text-rose-700' ?>">
                        <?= e(pct($crecimientoConstantes, true)) ?>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-0.5">
                        Deflactado por IPC acumulado INDEC
                    </div>
                </div>
            </div>

            <!-- Gráfico SVG Interactivo con Curva Suavizada Catmull-Rom (tension 0.4) -->
            <?php if (!empty($serieEvo)): ?>
            <div class="relative w-full overflow-hidden rounded-xl border border-slate-200/80 bg-slate-50/70 p-2 sm:p-4 my-4">
                <svg id="svgPatrimonio" viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight ?>" class="w-full h-auto overflow-visible select-none" role="img" aria-label="Gráfico de evolución patrimonial en Dólares MEP">
                    <defs>
                        <linearGradient id="patrimonioGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="<?= $strokeColor ?>" stopOpacity="0.22" />
                            <stop offset="100%" stopColor="<?= $strokeColor ?>" stopOpacity="0.01" />
                        </linearGradient>
                    </defs>

                    <!-- Grilla horizontal punteada -->
                    <?php foreach ($yTicks as $tickIdx => $tickPct):
                        $tickVal = $minVal + $tickPct * $valRange;
                        $tickY = $padTop + $plotH - $tickPct * $plotH;
                    ?>
                        <line x1="<?= $padLeft ?>" y1="<?= round($tickY, 1) ?>" x2="<?= $chartWidth - $padRight ?>" y2="<?= round($tickY, 1) ?>" stroke="#e2e8f0" stroke-dasharray="4 4" stroke-width="1" />
                        <text x="<?= $padLeft - 10 ?>" y="<?= round($tickY + 4, 1) ?>" text-anchor="end" class="fill-slate-400 text-[11px] font-mono tabular-nums">
                            USD <?= round($tickVal / 1000) ?>k
                        </text>
                    <?php endforeach; ?>

                    <!-- Línea vertical activa (guía interactiva) -->
                    <line id="lineaGuia" x1="<?= round($chartPoints[count($chartPoints)-1]['x'], 1) ?>" y1="<?= $padTop ?>" x2="<?= round($chartPoints[count($chartPoints)-1]['x'], 1) ?>" y2="<?= $padTop + $plotH ?>" stroke="#94a3b8" stroke-width="1.5" stroke-dasharray="3 3" />

                    <!-- Área y Curva Suavizada -->
                    <path d="<?= $areaPath ?>" fill="url(#patrimonioGrad)" />
                    <path d="<?= $linePath ?>" fill="none" stroke="<?= $strokeColor ?>" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />

                    <!-- Puntos interactivos con touch/hover -->
                    <?php foreach ($chartPoints as $idx => $pt):
                        $isLast = ($idx === count($chartPoints) - 1);
                        $d = $pt['data'];
                    ?>
                        <g class="cursor-pointer punto-grafico"
                           data-index="<?= $idx ?>"
                           data-anio="<?= e($d['anio_fiscal']) ?>"
                           data-usd="<?= number_format((float)$d['patrimonio_usd'], 0, ',', '.') ?>"
                           data-nominal="<?= e(ars((float)$d['monto_pesos_nominal'])) ?>"
                           data-constante="<?= e(ars((float)$d['patrimonio_pesos_constantes'])) ?>"
                           data-mep="<?= number_format((float)$d['dolar_mep_cierre'], 2, ',', '.') ?>"
                           data-ipc="<?= number_format((float)$d['ipc_interanual'], 1, ',', '.') ?>"
                           data-var="<?= e(pct((float)$d['variacion_interanual_usd_pct'], true)) ?>"
                           data-x="<?= round($pt['x'], 1) ?>">
                            <rect x="<?= round($pt['x'] - ($plotW / ($nPoints * 2)), 1) ?>" y="<?= $padTop ?>" width="<?= round($plotW / $nPoints, 1) ?>" height="<?= $plotH + $padBottom ?>" fill="transparent" />
                            <circle class="circle-pt" cx="<?= round($pt['x'], 1) ?>" cy="<?= round($pt['y'], 1) ?>" r="<?= $isLast ? 6.5 : 4.5 ?>" fill="<?= $isLast ? $strokeColor : '#ffffff' ?>" stroke="<?= $strokeColor ?>" stroke-width="<?= $isLast ? 3 : 2.5 ?>" />
                            <text x="<?= round($pt['x'], 1) ?>" y="<?= $padTop + $plotH + 24 ?>" text-anchor="middle" class="text-xs font-mono tabular-nums <?= $isLast ? 'fill-slate-900 font-bold' : 'fill-slate-500 font-medium' ?>">
                                <?= e($d['anio_fiscal']) ?>
                            </text>
                        </g>
                    <?php endforeach; ?>
                </svg>
            </div>

            <!-- Inspector / Tooltip Interactivo -->
            <?php $lastPt = $chartPoints[count($chartPoints)-1]['data']; ?>
            <div id="inspectorPatrimonio" class="bg-slate-900 text-white rounded-xl p-4 shadow-sm border border-slate-800">
                <div class="flex flex-wrap items-center justify-between gap-2 pb-3 mb-3 border-b border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xs uppercase tracking-wider font-semibold text-slate-400">Declaración Jurada Año Fiscal</span>
                        <span id="inspAnio" class="font-mono tabular-nums font-bold text-sm px-2 py-0.5 rounded bg-slate-800 text-white">
                            <?= e($lastPt['anio_fiscal']) ?>
                        </span>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-slate-400 font-mono tabular-nums">
                        <span>Dólar MEP (31/12): <strong id="inspMep" class="text-slate-200">$ <?= number_format((float)$lastPt['dolar_mep_cierre'], 2, ',', '.') ?></strong></span>
                        <span>·</span>
                        <span>IPC Anual: <strong id="inspIpc" class="text-slate-200"><?= number_format((float)$lastPt['ipc_interanual'], 1, ',', '.') ?>%</strong></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="bg-slate-800/80 rounded-lg p-3 border border-slate-700/70">
                        <div class="text-[11px] text-slate-400 uppercase font-medium">1. Pesos Nominales</div>
                        <div id="inspNominal" class="text-base font-mono tabular-nums font-bold text-slate-100 mt-0.5">
                            <?= e(ars((float)$lastPt['monto_pesos_nominal'])) ?>
                        </div>
                        <div class="text-[11px] font-mono tabular-nums text-slate-400 mt-0.5">Declarado en Oficina Anticorrupción</div>
                    </div>

                    <div class="bg-slate-800/80 rounded-lg p-3 border border-slate-700/70">
                        <div class="text-[11px] text-slate-400 uppercase font-medium">2. Pesos Constantes (IPC <?= e($anioActual) ?>)</div>
                        <div id="inspConstante" class="text-base font-mono tabular-nums font-bold text-amber-300 mt-0.5">
                            <?= e(ars((float)$lastPt['patrimonio_pesos_constantes'])) ?>
                        </div>
                        <div class="text-[11px] font-mono tabular-nums text-slate-400 mt-0.5">Ajustado por inflación acumulada</div>
                    </div>

                    <div class="bg-slate-800/80 rounded-lg p-3 border border-emerald-500/30">
                        <div class="text-[11px] text-emerald-300 uppercase font-medium">3. Equivalente en USD MEP</div>
                        <div id="inspUsd" class="text-lg font-mono tabular-nums font-extrabold text-white mt-0.5">
                            USD <?= number_format((float)$lastPt['patrimonio_usd'], 0, ',', '.') ?>
                        </div>
                        <div class="text-[11px] font-mono tabular-nums text-slate-300 mt-0.5">
                            Variación anual: <strong id="inspVar" class="<?= (float)$lastPt['variacion_interanual_usd_pct'] >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>"><?= e(pct((float)$lastPt['variacion_interanual_usd_pct'], true)) ?></strong>
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between text-[11px] text-slate-400">
                    <span>Tocá o pasá el cursor sobre cualquier año para auditar la conversión.</span>
                    <span class="font-mono">Fuente: Oficina Anticorrupción / INDEC</span>
                </div>
            </div>
            <?php else: ?>
                <p class="mt-6 text-slate-500">Sin declaraciones juradas cargadas.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- 3. Ejes temáticos: Gráfico de Radar Multidimensional + Barras Granulares -->
    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
        <div>
            <div class="pb-3 border-b border-slate-100">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">
                    Módulo Ideológico · Huella de Votación
                </span>
                <h2 class="text-xl font-bold tracking-tight text-slate-900 mt-0.5">
                    Posicionamiento por Ejes Temáticos
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    Radar multidimensional de afinidad parlamentaria (11 ejes) excluyendo ausencias.
                </p>
            </div>

            <!-- Gráfico de Radar SVG -->
            <div class="my-4 flex flex-col items-center">
                <div class="w-full max-w-[340px] relative">
                    <svg id="svgRadar" viewBox="0 0 <?= $radarSize ?> <?= $radarSize ?>" class="w-full h-auto overflow-visible select-none" role="img" aria-label="Gráfico de radar de posicionamiento en 11 ejes temáticos">
                        <defs>
                            <linearGradient id="radarGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stopColor="#2563eb" stopOpacity="0.4" />
                                <stop offset="100%" stopColor="#1d4ed8" stopOpacity="0.1" />
                            </linearGradient>
                        </defs>

                        <!-- Anillos concéntricos de grilla (25%, 50%, 75%, 100%) -->
                        <?php foreach ($radarRings as $ringPct):
                            $ringPts = [];
                            foreach ($radarAxes as $ri => $raxis) {
                                $rangle = ($ri * 2 * M_PI / $totalRadarAxes) - (M_PI / 2);
                                $rr = ($ringPct / 100) * $radarRadius;
                                $rx = $radarCenter + $rr * cos($rangle);
                                $ry = $radarCenter + $rr * sin($rangle);
                                $ringPts[] = sprintf('%.1f,%.1f', $rx, $ry);
                            }
                        ?>
                            <polygon points="<?= implode(' ', $ringPts) ?>" fill="none" stroke="#e2e8f0" stroke-width="1.2" />
                            <text x="<?= $radarCenter + 4 ?>" y="<?= round($radarCenter - (($ringPct / 100) * $radarRadius) + 4, 1) ?>" class="fill-slate-400 text-[9px] font-mono">
                                <?= $ringPct ?>%
                            </text>
                        <?php endforeach; ?>

                        <!-- Spoke lines y etiquetas de ejes alrededor del radar -->
                        <?php foreach ($radarVertices as $v): ?>
                            <line x1="<?= $radarCenter ?>" y1="<?= $radarCenter ?>" x2="<?= round($v['outerX'], 1) ?>" y2="<?= round($v['outerY'], 1) ?>" stroke="#e2e8f0" stroke-width="1.2" />
                            <text x="<?= round($v['lblX'], 1) ?>" y="<?= round($v['lblY'], 1) ?>" text-anchor="<?= $v['anchor'] ?>" dominant-baseline="middle" class="fill-slate-700 text-[10px] font-semibold">
                                <?= e($v['short']) ?>
                            </text>
                        <?php endforeach; ?>

                        <!-- Polígono de datos del legislador -->
                        <polygon points="<?= $radarPointsStr ?>" fill="url(#radarGrad)" stroke="#2563eb" stroke-width="2.5" />

                        <!-- Vértices del radar interactivos -->
                        <?php foreach ($radarVertices as $vi => $v): ?>
                            <circle class="radar-point cursor-pointer"
                                    cx="<?= round($v['x'], 1) ?>"
                                    cy="<?= round($v['y'], 1) ?>"
                                    r="4.5"
                                    fill="#2563eb"
                                    stroke="#ffffff"
                                    stroke-width="2"
                                    data-axis="<?= e($v['axis']) ?>"
                                    data-pct="<?= e(pct($v['val'])) ?>" />
                        <?php endforeach; ?>
                    </svg>

                    <!-- Tooltip flotante del Radar -->
                    <div id="radarTooltip" class="hidden absolute top-2 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-xs rounded-lg px-2.5 py-1 pointer-events-none shadow-md font-mono text-center z-10 whitespace-nowrap">
                        <span id="radarTooltipText"></span>
                    </div>
                </div>
            </div>

            <!-- Desglose granular con barras temáticas -->
            <div class="pt-4 border-t border-slate-100 space-y-3">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Desglose por Voto Emitido</p>
                <?php foreach ($data['perfil'] as $eje => $valor):
                    $c = $colores[$eje] ?? $colorDefault;
                ?>
                <div class="barra-eje-item" data-axis="<?= e($eje) ?>">
                    <div class="flex justify-between text-xs mb-1">
                        <span class="font-medium text-slate-700"><?= e($eje) ?></span>
                        <span class="font-mono text-xs font-semibold <?= e($c['txt']) ?>"><?= e(pct($valor)) ?></span>
                    </div>
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden" role="progressbar" aria-valuenow="<?= e($valor) ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= e($eje) ?>">
                        <div class="h-full rounded-full <?= e($c['bar']) ?>" style="width: <?= e($valor) ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <a href="/match-360" class="mt-6 block text-center rounded-xl bg-slate-900 text-white text-sm font-semibold py-3 hover:bg-slate-800 transition-colors shadow-sm">
            ¿Coincidís con este perfil? Hacé el Match 360 ↗
        </a>
    </section>

    <!-- Card: Defensa de su Provincia (Fase 4 - Tensión Partido vs. Provincia) -->
    <section class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                        Auditoría Territorial & Federal
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Provincia de <?= e($leg['provincia']) ?></span>
                </div>
                <h2 class="text-xl font-bold tracking-tight text-slate-900 mt-1">Defensa de su Provincia</h2>
                <p class="text-sm text-slate-500">
                    Auditoría de tensión entre disciplina de bloque partidario nacional y defensa de los intereses de <?= e($leg['provincia']) ?>.
                </p>
            </div>
            <?php if (!empty($defensaProv['alertas_conflicto'])): ?>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200 self-start md:self-auto">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <?= count($defensaProv['alertas_conflicto']) ?> <?= count($defensaProv['alertas_conflicto']) === 1 ? 'Voto en conflicto' : 'Votos en conflicto' ?>
            </span>
            <?php else: ?>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200 self-start md:self-auto">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                100% Alineado con <?= e($leg['provincia']) ?>
            </span>
            <?php endif; ?>
        </div>

        <!-- Métricas comparativas enfrentadas: Lealtad al Partido vs Defensa Provincial -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Métrica 1: Lealtad al Partido -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs uppercase font-mono tracking-wider text-slate-400">Disciplina Partidaria</span>
                        <h3 class="text-base font-bold text-slate-900 mt-0.5">Índice de Lealtad al Partido</h3>
                    </div>
                    <span class="px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-200 text-slate-800 font-mono">
                        <?= e($leg['bloque']) ?>
                    </span>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="font-mono text-3xl sm:text-4xl font-extrabold text-slate-900">
                        <?= number_format((float)$defensaProv['indice_lealtad_partido'], 1, ',', '.') ?>%
                    </span>
                    <span class="text-xs text-slate-500">cohesión de voto con su bloque</span>
                </div>
                <div class="mt-3 w-full bg-slate-200 rounded-full h-2.5 overflow-hidden">
                    <div class="bg-slate-800 h-2.5 rounded-full" style="width: <?= min(100, max(0, (float)$defensaProv['indice_lealtad_partido'])) ?>%"></div>
                </div>
            </div>

            <!-- Métrica 2: Defensa Provincial -->
            <div class="bg-emerald-50/50 border border-emerald-200 rounded-xl p-5 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs uppercase font-mono tracking-wider text-emerald-700">Compromiso Federal</span>
                        <h3 class="text-base font-bold text-emerald-950 mt-0.5">Índice de Defensa Provincial</h3>
                    </div>
                    <span class="px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-100 text-emerald-800 font-mono">
                        <?= e($leg['provincia']) ?>
                    </span>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="font-mono text-3xl sm:text-4xl font-extrabold <?= $defensaProv['indice_defensa_provincial'] >= 70 ? 'text-emerald-700' : ($defensaProv['indice_defensa_provincial'] >= 50 ? 'text-amber-700' : 'text-rose-700') ?>">
                        <?= number_format((float)$defensaProv['indice_defensa_provincial'], 1, ',', '.') ?>%
                    </span>
                    <span class="text-xs text-emerald-800/80">ponderado por criticidad</span>
                </div>
                <div class="mt-3 w-full bg-emerald-200/80 rounded-full h-2.5 overflow-hidden">
                    <div class="h-2.5 rounded-full <?= $defensaProv['indice_defensa_provincial'] >= 70 ? 'bg-emerald-600' : ($defensaProv['indice_defensa_provincial'] >= 50 ? 'bg-amber-500' : 'bg-rose-600') ?>" style="width: <?= min(100, max(0, (float)$defensaProv['indice_defensa_provincial'])) ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Indicador de Brecha de Tensión -->
        <?php if ($defensaProv['brecha_tension'] > 0): ?>
        <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl flex items-center justify-between text-xs text-amber-900">
            <span class="font-medium flex items-center gap-1.5">
                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Tensión Partidaria Detectada: La lealtad a la cúpula supera a la defensa provincial en un <strong>+<?= number_format($defensaProv['brecha_tension'], 1, ',', '.') ?>%</strong>.
            </span>
            <span class="font-mono font-bold text-amber-800">Prioridad: Partido Nacional</span>
        </div>
        <?php endif; ?>

        <!-- Tabla de Alertas: Votos en conflicto con su provincia -->
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-700 mb-3 flex items-center gap-2">
                <span>Votos en conflicto con su provincia</span>
                <span class="text-xs font-normal text-slate-400 lowercase">(votaciones que contradijeron a <?= e($leg['provincia']) ?>)</span>
            </h3>

            <?php if (!empty($defensaProv['alertas_conflicto'])): ?>
            <div class="overflow-x-auto rounded-xl border border-amber-200 bg-amber-50/30">
                <table class="w-full text-sm">
                    <thead class="bg-amber-100/60 text-xs uppercase font-semibold text-amber-900 border-b border-amber-200">
                        <tr>
                            <th class="text-left px-4 py-3 whitespace-nowrap">Ley Evaluada</th>
                            <th class="text-left px-4 py-3 whitespace-nowrap">Voto Legislador</th>
                            <th class="text-left px-4 py-3 whitespace-nowrap">Interés Provincial</th>
                            <th class="text-left px-4 py-3 min-w-[260px]">Contradicción con el Interés Regional</th>
                            <th class="text-left px-4 py-3 whitespace-nowrap">Criticidad</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-amber-100 bg-white">
                    <?php foreach ($defensaProv['alertas_conflicto'] as $alerta): 
                        $badgeCrit = match ($alerta['nivel_criticidad']) {
                            3 => 'bg-rose-100 text-rose-800 border-rose-300 font-bold',
                            2 => 'bg-amber-100 text-amber-800 border-amber-300',
                            default => 'bg-slate-100 text-slate-700 border-slate-300',
                        };
                        $critTexto = match ($alerta['nivel_criticidad']) {
                            3 => 'Nivel 3 (Crítica)',
                            2 => 'Nivel 2 (Media)',
                            default => 'Nivel 1 (Baja)',
                        };
                    ?>
                        <tr class="hover:bg-amber-50/50 transition-colors">
                            <td class="px-4 py-3 font-medium text-slate-900 text-xs sm:text-sm">
                                <div><?= e($alerta['ley']) ?></div>
                                <span class="font-mono text-[11px] text-slate-400"><?= e(date('d/m/Y', strtotime($alerta['fecha']))) ?></span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold <?= $alerta['voto_legislador'] === 'Afirmativo' ? 'bg-emerald-100 text-emerald-800' : ($alerta['voto_legislador'] === 'Negativo' ? 'bg-rose-100 text-rose-800' : 'bg-slate-200 text-slate-700') ?>">
                                    <?= e($alerta['voto_legislador']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    <?= e($alerta['voto_esperado_regional']) ?> (Favorable)
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs leading-relaxed text-slate-700">
                                <?php if ($alerta['disciplina_partidaria_perjudicial']): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200 mb-1">
                                    Voto por Disciplina Partidaria en perjuicio provincial
                                </span>
                                <?php endif; ?>
                                <p class="text-slate-800 font-medium"><?= e($alerta['descripcion_impacto']) ?></p>
                                <p class="text-[11px] text-slate-500 mt-1 italic"><?= e($alerta['motivo']) ?></p>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-xs">
                                <span class="inline-flex items-center px-2 py-0.5 rounded border text-[11px] <?= $badgeCrit ?>">
                                    <?= e($critTexto) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="p-5 rounded-xl border border-emerald-200 bg-emerald-50/50 text-center text-xs text-emerald-900">
                <strong>Sin votos en conflicto registrados:</strong> El legislador ha votado de forma consistente con las leyes de impacto regional para la provincia de <?= e($leg['provincia']) ?>.
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Auditoría y Transparencia: Ratio de Eficiencia Legislativa (Fase 6 - n8n) -->
    <section class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-violet-50 text-violet-800 border border-violet-200">
                        Auditoría y Transparencia
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Gasto Operativo vs. Producción Legislativa (2024)</span>
                </div>
                <h2 class="text-xl font-bold tracking-tight text-slate-900 mt-1">Ratio de Eficiencia Legislativa</h2>
                <p class="text-sm text-slate-500">
                    Costo real al Estado en estructura de despacho (módulo salarial de asesores) contrastado contra los proyectos de ley presentados como autor principal. Datos procesados vía n8n.
                </p>
            </div>
            
            <!-- Alerta visual de Nepotismo (Badge de Tailwind) -->
            <?php if ($alertasNepotismo > 0): ?>
            <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold bg-red-100 text-red-800 border border-red-300 self-start md:self-auto shadow-sm">
                <svg class="w-4 h-4 text-red-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>Alerta de Nepotismo: <?= $alertasNepotismo ?> <?= $alertasNepotismo === 1 ? 'asesor coincide' : 'asesores coinciden' ?> con su grupo familiar/societario</span>
            </div>
            <?php else: ?>
            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200 self-start md:self-auto">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Sin alertas de nepotismo familiar detectadas</span>
            </div>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- KPI Principal: Costo por Proyecto Presentado (Formato moneda, en rojo si supera la media) -->
            <div class="md:col-span-2 rounded-xl p-5 border flex flex-col justify-between <?= $superaMedia ? 'bg-rose-50/70 border-rose-300' : 'bg-emerald-50/60 border-emerald-200' ?>">
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase font-mono tracking-wider font-semibold <?= $superaMedia ? 'text-rose-800' : 'text-emerald-800' ?>">
                        KPI de Eficiencia Parlamentaria
                    </span>
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold font-mono <?= $superaMedia ? 'bg-rose-200 text-rose-900' : 'bg-emerald-200 text-emerald-900' ?>">
                        <?= $superaMedia ? '▲ SUPERA LA MEDIA' : '▼ POR DEBAJO DE LA MEDIA' ?>
                    </span>
                </div>

                <div class="my-4">
                    <span class="block text-xs font-medium text-slate-500 mb-1">Costo al Estado por cada Proyecto Presentado</span>
                    <div class="font-mono text-3xl sm:text-4xl font-extrabold tabular-nums <?= $superaMedia ? 'text-rose-700' : 'text-emerald-700' ?>">
                        <?= e(ars($costoPorProyecto)) ?>
                    </div>
                </div>

                <div class="pt-3 border-t <?= $superaMedia ? 'border-rose-200' : 'border-emerald-200' ?> flex flex-wrap items-center justify-between gap-2 text-xs">
                    <span class="text-slate-600">
                        Media de <?= $leg['camara'] === 'Senadores' ? 'la Cámara de Senadores' : 'la Cámara de Diputados' ?>: <strong class="font-mono text-slate-800"><?= e(ars($mediaCamaraCosto)) ?></strong>
                    </span>
                    <span class="font-mono font-bold <?= $superaMedia ? 'text-rose-800' : 'text-emerald-800' ?>">
                        <?= $superaMedia ? '+' : '' ?><?= number_format((($costoPorProyecto / max(1, $mediaCamaraCosto)) - 1) * 100, 1) ?>% vs media
                    </span>
                </div>
            </div>

            <!-- Métrica Secundaria: Total de Asesores -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 flex flex-col justify-between">
                <div>
                    <span class="text-xs uppercase font-mono tracking-wider text-slate-400">Estructura de Despacho</span>
                    <h3 class="text-sm font-bold text-slate-700 mt-1">Total de Asesores</h3>
                </div>
                <div class="my-3">
                    <div class="font-mono text-3xl font-extrabold text-slate-900">
                        <?= (int)$totalAsesores ?>
                    </div>
                    <span class="text-xs text-slate-500">planta permanente y transitoria</span>
                </div>
                <div class="pt-2 border-t border-slate-200 text-[11px] font-mono text-slate-500">
                    Gasto Mensual: <strong><?= e(ars($costoMensual)) ?></strong>
                </div>
            </div>

            <!-- Métrica Secundaria: Proyectos Propios de Autor -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 flex flex-col justify-between">
                <div>
                    <span class="text-xs uppercase font-mono tracking-wider text-slate-400">Producción Legislativa</span>
                    <h3 class="text-sm font-bold text-slate-700 mt-1">Proyectos de Autor (Año)</h3>
                </div>
                <div class="my-3">
                    <div class="font-mono text-3xl font-extrabold text-slate-900">
                        <?= (int)$proyectosAutorAnio ?>
                    </div>
                    <span class="text-xs text-slate-500">iniciativas de autoría propia</span>
                </div>
                <div class="pt-2 border-t border-slate-200 text-[11px] font-mono text-slate-500">
                    Costo Anual Proyectado: <strong><?= e(ars($costoAnual)) ?></strong>
                </div>
            </div>
        </div>

        <p class="text-[11px] text-slate-400 font-mono">
            * Metodología de auditoría: El ratio de costo por proyecto se calcula dividiendo la masa salarial anualizada del despacho (incluyendo 13 sueldos por SAC) sobre la cantidad de proyectos de ley ingresados formalmente con expediente de autoría principal. Datos provistos y actualizados mediante sincronizador de datos n8n.buceo.co.
        </p>
    </section>

    <!-- Auditoría Ética & Integridad Parlamentaria (Master PRD) -->
    <section class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-800 border border-blue-200">
                        Master PRD · Auditoría Cívica
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Integridad, Coherencia y Compromisos Electorales</span>
                </div>
                <h2 class="text-xl font-bold tracking-tight text-slate-900 mt-1">Auditoría Ética & Transparencia Parlamentaria</h2>
                <p class="text-sm text-slate-500">
                    Evaluación cruzada de coherencia entre administraciones, alianzas de bancada, financiamiento privado y cumplimiento de promesas.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold <?= ($conflictos['nivel_riesgo_etico'] ?? 'Bajo') === 'Bajo' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : (($conflictos['nivel_riesgo_etico'] ?? '') === 'Medio' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-red-50 text-red-800 border border-red-200') ?>">
                    Riesgo Ético Global: <?= e($conflictos['nivel_riesgo_etico'] ?? 'Bajo') ?>
                </span>
            </div>
        </div>

        <!-- Grid de 2x2 para las 4 dimensiones -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- Card 1: Coherencia Histórica / Leyes Espejo (Panquequismo) -->
            <div class="bg-slate-50 border border-slate-200/90 rounded-xl p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs uppercase font-mono tracking-wider text-slate-400">Coherencia Histórica</span>
                        <h3 class="text-base font-bold text-slate-800">Leyes Espejo entre Gobiernos</h3>
                    </div>
                    <?php if (empty($coherencia['alertas_panquequismo'])): ?>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        100% Coherente
                    </span>
                    <?php else: ?>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">
                        <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Panquequismo Detectado
                    </span>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-slate-600">
                    Compara el sentido de voto en proyectos equivalentes presentados bajo administraciones de signo político opuesto.
                </p>
                <?php if (!empty($coherencia['alertas_panquequismo'])): ?>
                    <div class="space-y-2">
                        <?php foreach ($coherencia['alertas_panquequismo'] as $ap): ?>
                            <div class="p-3 bg-rose-50 border border-rose-200 rounded-lg text-xs space-y-1">
                                <div class="font-bold text-rose-900"><?= e($ap['ley_actual']) ?></div>
                                <div class="text-rose-800 text-[11px]"><?= e($ap['descripcion']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-3 bg-white border border-slate-200 rounded-lg text-xs text-slate-600 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        No se registraron cambios de postura en las leyes espejo analizadas.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Card 2: Grafo de Alianzas Interbloque -->
            <div class="bg-slate-50 border border-slate-200/90 rounded-xl p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs uppercase font-mono tracking-wider text-slate-400">Diálogo & Consenso</span>
                        <h3 class="text-base font-bold text-slate-800">Grafo de Alianzas</h3>
                    </div>
                    <span class="font-mono text-sm font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2.5 py-0.5 rounded-full">
                        <?= e(number_format((float)($alianzas['indice_transversalidad'] ?? 0), 1)) ?>% Transversal
                    </span>
                </div>
                <p class="text-xs text-slate-600">
                    Proporción de iniciativas legislativas firmadas conjuntamente con legisladores de bancadas opositoras.
                </p>
                <div class="space-y-2">
                    <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Principales Socios Legislativos:</div>
                    <?php if (!empty($alianzas['socios_frecuentes'])): ?>
                        <div class="space-y-1.5">
                            <?php foreach ($alianzas['socios_frecuentes'] as $soc): ?>
                                <div class="flex items-center justify-between p-2 bg-white border border-slate-200 rounded-lg text-xs">
                                    <div class="font-medium text-slate-900 truncate">
                                        <?= e($soc['nombre']) ?>
                                        <span class="text-slate-400 text-[11px]">(<?= e($soc['bloque']) ?>)</span>
                                    </div>
                                    <span class="font-mono text-[11px] font-semibold text-indigo-600 shrink-0">
                                        <?= (int)$soc['proyectos_compartidos'] ?> proyectos
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-3 bg-white border border-slate-200 rounded-lg text-xs text-slate-500">
                            No registra coautorías interbloque en el período auditado.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card 3: Conflictos de Interés (Financiamiento Privado) -->
            <div class="bg-slate-50 border border-slate-200/90 rounded-xl p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs uppercase font-mono tracking-wider text-slate-400">Fondos Electorales</span>
                        <h3 class="text-base font-bold text-slate-800">Financiamiento vs. Votos</h3>
                    </div>
                    <span class="font-mono text-xs font-semibold px-2.5 py-1 rounded-full <?= empty($conflictos['alertas_conflicto']) ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-800' ?>">
                        <?= count($conflictos['alertas_conflicto'] ?? []) ?> <?= count($conflictos['alertas_conflicto'] ?? []) === 1 ? 'Alerta' : 'Alertas' ?>
                    </span>
                </div>
                <div class="text-xs text-slate-600">
                    Aportes privados declarados: <strong class="text-slate-900"><?= e(ars((float)($conflictos['total_aportes_declarados_ars'] ?? 0))) ?></strong> en campaña.
                </div>
                <?php if (!empty($conflictos['alertas_conflicto'])): ?>
                    <div class="space-y-2">
                        <?php foreach ($conflictos['alertas_conflicto'] as $ac): ?>
                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs space-y-1">
                                <div class="font-bold text-amber-900">Sector <?= e($ac['sector']) ?> (<?= e($ac['donante']) ?>)</div>
                                <div class="text-amber-800 text-[11px] leading-relaxed"><?= e($ac['alerta']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-3 bg-white border border-slate-200 rounded-lg text-xs text-slate-600 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        No se detectaron cruces de votación afín a aportantes privados directos.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Card 4: Tracking de Promesas de Campaña -->
            <div class="bg-slate-50 border border-slate-200/90 rounded-xl p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs uppercase font-mono tracking-wider text-slate-400">Contrato Electoral</span>
                        <h3 class="text-base font-bold text-slate-800">Promesas de Campaña</h3>
                    </div>
                    <span class="font-mono text-xs font-bold px-2.5 py-1 rounded-full <?= (float)($promesas['tasa_cumplimiento_pct'] ?? 100) >= 75 ? 'bg-emerald-100 text-emerald-800' : ((float)($promesas['tasa_cumplimiento_pct'] ?? 100) >= 50 ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') ?>">
                        <?= e(number_format((float)($promesas['tasa_cumplimiento_pct'] ?? 100), 1)) ?>% Cumplidas
                    </span>
                </div>
                <p class="text-xs text-slate-600">
                    Contraste objetivo entre compromisos asumidos en campaña y el voto emitido en las leyes respectivas:
                </p>
                <div class="space-y-2">
                    <?php if (!empty($promesas['promesas'])): ?>
                        <?php foreach ($promesas['promesas'] as $pr): ?>
                            <div class="p-3 bg-white border border-slate-200 rounded-lg text-xs space-y-1">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="font-medium text-slate-800">"<?= e($pr['promesa_texto']) ?>"</span>
                                    <?php if ($pr['estado'] === 'Cumplida'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 shrink-0">
                                            <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                            Cumplida
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 shrink-0">
                                            <svg class="w-3 h-3 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                            Incumplida
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-[11px] text-slate-500 font-mono">Ley: <?= e($pr['ley_vinculada']) ?> (Exp: <?= e($pr['expediente']) ?>)</div>
                                <div class="text-[11px] text-slate-600 italic"><?= e($pr['descripcion_verificacion']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-3 bg-white border border-slate-200 rounded-lg text-xs text-slate-500">
                            No se registran promesas de campaña auditadas en el período.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
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

    <script nonce="<?= csp_nonce() ?>">
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Filtro dinámico de proyectos
        const input = document.getElementById('filtroProyectos');
        if (input) {
            input.addEventListener('input', function () {
                const query = this.value.toLowerCase().trim();
                const filas = document.querySelectorAll('.fila-proyecto');
                filas.forEach(function (fila) {
                    const texto = fila.textContent.toLowerCase();
                    fila.style.display = texto.includes(query) ? '' : 'none';
                });
            });
        }

        // 2. Interacción del Gráfico de Evolución Patrimonial Real (USD MEP)
        const puntos = document.querySelectorAll('.punto-grafico');
        const guia = document.getElementById('lineaGuia');
        const inspAnio = document.getElementById('inspAnio');
        const inspMep = document.getElementById('inspMep');
        const inspIpc = document.getElementById('inspIpc');
        const inspNominal = document.getElementById('inspNominal');
        const inspConstante = document.getElementById('inspConstante');
        const inspUsd = document.getElementById('inspUsd');
        const inspVar = document.getElementById('inspVar');

        puntos.forEach(function (pt) {
            const update = function () {
                const anio = pt.getAttribute('data-anio');
                const usd = pt.getAttribute('data-usd');
                const nominal = pt.getAttribute('data-nominal');
                const constante = pt.getAttribute('data-constante');
                const mep = pt.getAttribute('data-mep');
                const ipc = pt.getAttribute('data-ipc');
                const v = pt.getAttribute('data-var');
                const x = pt.getAttribute('data-x');

                if (guia && x) {
                    guia.setAttribute('x1', x);
                    guia.setAttribute('x2', x);
                }
                if (inspAnio) inspAnio.textContent = anio;
                if (inspMep) inspMep.textContent = '$ ' + mep;
                if (inspIpc) inspIpc.textContent = ipc + '%';
                if (inspNominal) inspNominal.textContent = nominal;
                if (inspConstante) inspConstante.textContent = constante;
                if (inspUsd) inspUsd.textContent = 'USD ' + usd;
                if (inspVar) {
                    inspVar.textContent = v;
                    const num = parseFloat(v.replace('%', '').replace('+', ''));
                    inspVar.className = (num >= 0) ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold';
                }

                puntos.forEach(function (other) {
                    const c = other.querySelector('.circle-pt');
                    if (c) {
                        const isCurrent = (other === pt);
                        c.setAttribute('r', isCurrent ? '6.5' : '4.5');
                        c.setAttribute('fill', isCurrent ? '<?= $strokeColor ?>' : '#ffffff');
                    }
                });
            };

            pt.addEventListener('mouseenter', update);
            pt.addEventListener('click', update);
            pt.addEventListener('touchstart', update, { passive: true });
        });

        // 3. Interacción del Gráfico de Radar de Votaciones
        const radarPts = document.querySelectorAll('.radar-point');
        const tooltip = document.getElementById('radarTooltip');
        const tooltipText = document.getElementById('radarTooltipText');
        const barraItems = document.querySelectorAll('.barra-eje-item');

        radarPts.forEach(function (rpt) {
            rpt.addEventListener('mouseenter', function () {
                const axis = rpt.getAttribute('data-axis');
                const pct = rpt.getAttribute('data-pct');
                if (tooltip && tooltipText) {
                    tooltipText.textContent = axis + ': ' + pct;
                    tooltip.classList.remove('hidden');
                }
                rpt.setAttribute('r', '7');
                barraItems.forEach(function (bi) {
                    if (bi.getAttribute('data-axis') === axis) {
                        bi.classList.add('bg-blue-50/80', 'p-1', 'rounded-lg');
                    } else {
                        bi.classList.remove('bg-blue-50/80', 'p-1', 'rounded-lg');
                    }
                });
            });
            rpt.addEventListener('mouseleave', function () {
                if (tooltip) tooltip.classList.add('hidden');
                rpt.setAttribute('r', '4.5');
                barraItems.forEach(function (bi) {
                    bi.classList.remove('bg-blue-50/80', 'p-1', 'rounded-lg');
                });
            });
            rpt.addEventListener('click', function () {
                const axis = rpt.getAttribute('data-axis');
                const pct = rpt.getAttribute('data-pct');
                if (tooltip && tooltipText) {
                    tooltipText.textContent = axis + ': ' + pct;
                    tooltip.classList.remove('hidden');
                }
            });
        });
    });
    </script>
</div>
<?php require __DIR__ . '/app/Views/layouts/footer.php'; ?>
