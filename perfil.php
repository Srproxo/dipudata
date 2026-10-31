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
    $data = $slugLegislador !== '' ? (new LegisladorController())->perfil($slugLegislador, null) : null;
} catch (\Throwable) {
    try {
        $data = $slugLegislador !== '' ? (new LegisladorController(new LegisladorModel(null)))->perfil($slugLegislador, null) : null;
    } catch (\Throwable) {
        $data = null;
    }
}
if ($data === null) {
    render_404();
    return;
}

$leg = $data['legislador'];
$canonicalUrl = \App\Models\LegisladorModel::urlPerfil($leg);
$expectedCamaraPrefix = str_starts_with(strtolower((string)$leg['camara']), 'senad') ? 'senador' : 'diputado';
$currentCamaraPrefix = ($camara === 'Senadores') ? 'senador' : 'diputado';

// Redirección 301 permanente para slugs alias (ej: /diputado/martin-menem -> /diputado/martin-alexis-menem) o corrección de cámara
if (($slugLegislador !== '' && $slugLegislador !== $leg['slug']) || ($camara !== null && $currentCamaraPrefix !== $expectedCamaraPrefix)) {
    header("Location: {$canonicalUrl}", true, 301);
    exit;
}
$cargo = $leg['camara'] === 'Senadores' ? 'Senador/a Nacional' : 'Diputado/a Nacional';
$partidoColores = partido_colores($leg['bloque'] ?? '');
$fichaWiki = $data['ficha_wiki'] ?? [];
$fotoUrl = !empty($leg['foto_url']) ? $leg['foto_url'] : ($fichaWiki['foto_url'] ?? null);
if (empty($leg['foto_url']) && !empty($fotoUrl)) {
    $leg['foto_url'] = $fotoUrl;
}
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
$ejesSlugMap = [
    'Salud Pública'                     => 'salud-publica',
    'Educación'                         => 'educacion',
    'Educación Pública'                 => 'educacion',
    'Apertura Comercial'                => 'apertura-comercial',
    'Industria Nacional'                => 'industria-nacional',
    'Previsión Social y Discapacidad'   => 'prevision-social',
    'Medio Ambiente y Derechos Humanos' => 'medio-ambiente',
    'Seguridad y Sistema Penal'         => 'seguridad',
    'Género y Cuidados'                 => 'genero',
    'Federalismo y Agro'                => 'federalismo',
    'Transparencia Institucional'       => 'transparencia',
    'Tecnología y Trabajo'              => 'tecnologia',
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
<nav class="text-sm text-slate-500 mb-6 flex flex-wrap items-center justify-between gap-3" aria-label="Migas de pan">
    <div>
        <a href="/" class="hover:text-slate-900 transition-colors">Inicio</a> <span aria-hidden="true" class="mx-1">/</span>
        <span class="text-slate-600"><?= e($leg['camara']) ?></span> <span aria-hidden="true" class="mx-1">/</span>
        <span class="text-slate-900 font-semibold"><?= e($leg['nombre']) ?></span>
    </div>
    <div class="flex items-center gap-2">
        <button id="btnAbrirPresentacion" data-action="abrir-presentacion" type="button" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-900 hover:bg-slate-800 text-white shadow-sm hover:shadow transition-all border border-slate-700/60 hover:border-slate-500 cursor-pointer group focus:outline-none focus:ring-2 focus:ring-amber-400">
            <svg class="w-4 h-4 text-amber-400 group-hover:scale-110 transition-transform pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
            </svg>
            <span class="pointer-events-none">Modo Presentación (Slides 16:9)</span>
        </button>
    </div>
</nav>

<div class="grid gap-6 lg:grid-cols-3">
    <!-- 1. Bio -->
    <section class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="flex gap-5 items-start">
            <?php if (!empty($fotoUrl)): ?>
                <img src="<?= e($fotoUrl) ?>" alt="Foto de <?= e($leg['nombre']) ?>" width="96" height="96" class="w-24 h-24 rounded-full object-cover border-2 <?= e($partidoColores['border']) ?> shadow-sm shrink-0" loading="eager">
            <?php else: ?>
                <div class="w-24 h-24 rounded-full bg-slate-100 border-2 <?= e($partidoColores['border']) ?> grid place-items-center text-2xl font-bold text-slate-500 shrink-0" aria-hidden="true"><?= e($iniciales) ?></div>
            <?php endif; ?>
            <div class="min-w-0">
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight"><?= e($leg['nombre']) ?></h1>
                <p class="text-slate-500 mt-1"><?= e($cargo) ?></p>
                <div class="flex flex-wrap items-center gap-2 mt-3">
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-700"><?= e($leg['provincia']) ?></span>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold <?= e($partidoColores['badge']) ?>">
                        <span class="w-2 h-2 rounded-full <?= e($partidoColores['dot']) ?>"></span>
                        <?= e($leg['bloque']) ?>
                    </span>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-mono bg-slate-50 text-slate-500 border border-slate-200">CUIT <?= e($leg['cuit']) ?></span>
                    <?php if (!empty($fichaWiki['cargo_actual']) && $fichaWiki['cargo_actual'] !== $cargo): ?>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200"><?= e($fichaWiki['cargo_actual']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mt-6">Biografía</h2>
        <p class="mt-2 leading-relaxed text-slate-700"><?= e($leg['biografia_extracto'] ?: 'Biografía pendiente de sincronización con Wikipedia.') ?></p>
        <?php if ($leg['nombre_wiki']): ?>
            <a href="https://es.wikipedia.org/wiki/<?= e(rawurlencode($leg['nombre_wiki'])) ?>" rel="noopener nofollow" target="_blank" class="inline-block mt-2 text-sm text-blue-700 hover:underline">Fuente: Wikipedia ↗</a>
        <?php endif; ?>

        <?php if (!empty($fichaWiki['nombre_completo'])): ?>
        <!-- Ficha Institucional y Biográfica Estructurada (Wikipedia) -->
        <div class="mt-6 pt-5 border-t border-slate-100">
            <div class="flex items-center justify-between gap-2 mb-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Ficha Institucional & Biográfica (Datos de Wikipedia)
                </h3>
                <?php if (!empty($fichaWiki['wiki_url'])): ?>
                    <a href="<?= e($fichaWiki['wiki_url']) ?>" rel="noopener nofollow" target="_blank" class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-800 transition-colors">
                        <span>Ver artículo completo</span>
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </a>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                <!-- Columna 1: Información Personal & Profesional -->
                <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5 space-y-2">
                    <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 font-semibold block border-b border-slate-200/60 pb-1">
                        Información Personal & Profesional
                    </span>
                    <?php if (!empty($fichaWiki['nacimiento_fecha'])): ?>
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-slate-500">Nacimiento:</span>
                        <span class="font-medium text-slate-900 text-right"><?= e($fichaWiki['nacimiento_fecha']) ?> (<?= e($fichaWiki['nacimiento_lugar']) ?>)</span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($fichaWiki['educacion'])): ?>
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-slate-500">Educación:</span>
                        <span class="font-medium text-slate-900 text-right"><?= e($fichaWiki['educacion']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($fichaWiki['ocupacion'])): ?>
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-slate-500">Ocupación:</span>
                        <span class="font-medium text-slate-900 text-right"><?= e($fichaWiki['ocupacion']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($fichaWiki['actividad_empresarial'])): ?>
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-slate-500">Actividad Privada:</span>
                        <span class="font-medium text-slate-900 text-right"><?= e($fichaWiki['actividad_empresarial']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($fichaWiki['religion'])): ?>
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-slate-500">Religión:</span>
                        <span class="font-medium text-slate-900 text-right"><?= e($fichaWiki['religion']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($fichaWiki['padres'])): ?>
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-slate-500">Padres:</span>
                        <span class="font-medium text-slate-900 text-right"><?= e($fichaWiki['padres']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($fichaWiki['hijos'])): ?>
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-slate-500">Hijos:</span>
                        <span class="font-medium text-slate-900 text-right"><?= e($fichaWiki['hijos']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Columna 2: Cargos Públicos & Roles Partidarios -->
                <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5 space-y-2">
                    <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 font-semibold block border-b border-slate-200/60 pb-1">
                        Cargos Institucionales & Partidarios
                    </span>
                    <?php if (!empty($fichaWiki['cargo_actual'])): ?>
                    <div>
                        <span class="text-[11px] text-slate-500 block">Cargo actual:</span>
                        <span class="font-bold text-slate-900"><?= e($fichaWiki['cargo_actual']) ?></span>
                        <?php if (!empty($fichaWiki['cargo_actual_desde'])): ?>
                            <span class="text-[10px] font-mono text-slate-500 block">Desde: <?= e($fichaWiki['cargo_actual_desde']) ?><?php if (!empty($fichaWiki['predecesora'])): ?> (Predecesora: <?= e($fichaWiki['predecesora']) ?>)<?php endif; ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($fichaWiki['rol_partidario'])): ?>
                    <div class="pt-1.5 border-t border-slate-200/60">
                        <span class="text-[11px] text-slate-500 block">Rol partidario:</span>
                        <span class="font-semibold text-purple-900"><?= e($fichaWiki['rol_partidario']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($fichaWiki['cargos_previos'])): ?>
                    <div class="pt-1.5 border-t border-slate-200/60">
                        <span class="text-[11px] text-slate-500 block mb-1">Trayectoria institucional previa:</span>
                        <ul class="space-y-1 text-[11px] text-slate-700">
                            <?php foreach ($fichaWiki['cargos_previos'] as $cp): ?>
                                <li class="flex items-start gap-1.5">
                                    <span class="text-purple-600 font-bold shrink-0">•</span>
                                    <span><?= e($cp) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Familiares destacados vinculados a la política / vida pública -->
            <?php if (!empty($fichaWiki['familiares'])): ?>
            <div class="mt-3 bg-slate-50 border border-slate-200/80 rounded-xl p-3">
                <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 font-semibold block mb-1.5">
                    Red Familiar & Vínculos Notables
                </span>
                <div class="flex flex-wrap gap-1.5">
                    <?php foreach ($fichaWiki['familiares'] as $fam): ?>
                        <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-700 text-[11px]">
                            <?= e($fam) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>

    <!-- KPIs -->
    <aside class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 grid grid-cols-2 lg:grid-cols-1 gap-6 content-start">
        <div>
            <p class="text-sm text-slate-500 flex items-center"><span>Asistencia histórica</span><?= tooltip_alpine("Porcentaje histórico de asistencia a votaciones y sesiones del recinto.") ?></p>
            <p class="font-mono text-3xl font-semibold mt-1"><?= e(pct((float)$leg['asistencia'])) ?></p>
        </div>
        <div>
            <p class="text-sm text-slate-500 flex items-center"><span>Lealtad partidaria</span><?= tooltip_alpine("Porcentaje de disciplina parlamentaria y coincidencia de voto con su bloque.") ?></p>
            <p class="font-mono text-3xl font-semibold mt-1"><?= e(pct((float)$leg['indice_lealtad'])) ?></p>
        </div>
        <div class="col-span-2 lg:col-span-1 rounded-xl p-4 <?= $real >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>">
            <p class="text-sm font-medium flex items-center justify-between"><span>Crecimiento patrimonial real</span><?= tooltip_alpine("Variación patrimonial real descontada la inflación acumulada (IPC INDEC).") ?></p>
            <p class="font-mono text-3xl font-semibold mt-1"><?= $real >= 0 ? '▲' : '▼' ?> <?= e(pct($real, true)) ?></p>
            <p class="text-xs mt-1 opacity-80">Descontada la inflación (IPC INDEC)</p>
        </div>
    </aside>
</div> <!-- Cierre Header Grid (Bio + KPIs) -->


<!-- ========================================================================= -->
<!-- DIVULGACIÓN PROGRESIVA: PESTAÑAS HORIZONTALES CON ALPINE.JS (MASTER PRD)  -->
<!-- ========================================================================= -->
<div x-data="{ tab: 'votaciones' }" class="mt-8 space-y-6">
    <!-- Barra de Pestañas Horizontales -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-1.5 sm:p-2 sticky top-20 z-30 backdrop-blur-md bg-white/95">
        <nav class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 sm:gap-2" aria-label="Secciones analíticas de perfil">
            <button type="button" @click="tab = 'votaciones'" 
                    :class="tab === 'votaciones' ? 'bg-slate-900 text-white font-bold shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-medium'"
                    class="py-2.5 sm:py-3 px-3 sm:px-4 rounded-xl text-xs sm:text-sm transition-all flex items-center justify-center gap-2">
                <span>📊</span>
                <span>Votaciones por Eje</span>
            </button>
            <button type="button" @click="tab = 'agenda'" 
                    :class="tab === 'agenda' ? 'bg-slate-900 text-white font-bold shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-medium'"
                    class="py-2.5 sm:py-3 px-3 sm:px-4 rounded-xl text-xs sm:text-sm transition-all flex items-center justify-center gap-2">
                <span>📜</span>
                <span>Agenda Propia</span>
                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded-full" :class="tab === 'agenda' ? 'bg-slate-800 text-slate-200' : 'bg-slate-200 text-slate-700'"><?= (int)($agenda['total_proyectos'] ?? 0) ?></span>
            </button>
            <button type="button" @click="tab = 'patrimonio'" 
                    :class="tab === 'patrimonio' ? 'bg-slate-900 text-white font-bold shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-medium'"
                    class="py-2.5 sm:py-3 px-3 sm:px-4 rounded-xl text-xs sm:text-sm transition-all flex items-center justify-center gap-2">
                <span>📈</span>
                <span>Patrimonio Real</span>
            </button>
            <button type="button" @click="tab = 'auditoria'" 
                    :class="tab === 'auditoria' ? 'bg-slate-900 text-white font-bold shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-medium'"
                    class="py-2.5 sm:py-3 px-3 sm:px-4 rounded-xl text-xs sm:text-sm transition-all flex items-center justify-center gap-2 relative">
                <span>⚖️</span>
                <span>Auditoría Ética</span>
                <?php if ($alertasNepotismo > 0 || !empty($conflictos['alertas_conflicto']) || !empty($data['conflictos_transparencia'])): ?>
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse" title="Alertas éticas detectadas"></span>
                <?php endif; ?>
            </button>
        </nav>
    </div>

    <!-- PESTAÑA 1: VOTACIONES -->
    <div x-show="tab === 'votaciones'" x-cloak class="space-y-6">
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
                        <?php $slugEje = $ejesSlugMap[$eje] ?? \App\Config\Database::slugify($eje); ?>
                        <a href="/eje/<?= e($slugEje) ?>" class="font-medium text-slate-800 hover:text-blue-600 hover:underline flex items-center gap-1 group">
                            <span><?= e($eje) ?></span>
                            <span class="text-slate-400 group-hover:text-blue-600 text-[10px]">↗</span>
                        </a>
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
                        <?php $slugEjeVoto = $ejesSlugMap[$v['eje_tematico']] ?? \App\Config\Database::slugify($v['eje_tematico']); ?>
                        <td class="px-6 py-3 whitespace-nowrap">
                            <a href="/eje/<?= e($slugEjeVoto) ?>" class="text-slate-600 hover:text-blue-600 hover:underline">
                                <?= e($v['eje_tematico']) ?>
                            </a>
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap font-mono text-slate-500"><?= e(date('d/m/Y', strtotime($v['fecha']))) ?></td>
                        <td class="px-6 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= e($badgeVoto[$v['voto']] ?? '') ?>"><?= e($v['voto']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    
    </div>

    <!-- PESTAÑA 2: AGENDA PROPIA -->
    <div x-show="tab === 'agenda'" x-cloak class="space-y-6">
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

    
    </div>

    <!-- PESTAÑA 3: PATRIMONIO -->
    <div x-show="tab === 'patrimonio'" x-cloak class="space-y-6">
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

    
    </div>

    <!-- PESTAÑA 4: AUDITORÍA ÉTICA -->
    <div x-show="tab === 'auditoria'" x-cloak class="space-y-6">
        
        <!-- Alertas de Integridad & Conflictos de Transparencia (Master PRD) -->
        <?php if (!empty($data['conflictos_transparencia']) || $alertasNepotismo > 0): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-rose-600 inline-block animate-ping"></span>
                    <h3 class="text-base font-bold text-slate-900">Panel de Alertas de Transparencia y Ética Pública</h3>
                </div>
                <span class="text-xs font-mono text-slate-500">Auditoría Cruzada</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Alertas de Nepotismo (Rojo) -->
                <div class="p-4 rounded-xl border border-rose-200 bg-rose-50/70 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-rose-800">
                            <svg class="w-4 h-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            Alerta de Nepotismo
                        </span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono bg-rose-200 text-rose-900">Nivel Alto</span>
                    </div>
                    <?php if (!empty($data['conflictos_transparencia'])): 
                        $nep = array_filter($data['conflictos_transparencia'], fn($c) => $c['tipo_alerta'] === 'Nepotismo');
                        foreach ($nep as $n): ?>
                            <p class="text-xs text-rose-900 leading-relaxed font-medium"><?= e($n['descripcion_alerta']) ?></p>
                        <?php endforeach; 
                        if (empty($nep)): ?>
                            <p class="text-xs text-rose-900 leading-relaxed font-medium">Designación de <?= $alertasNepotismo ?> asesores con coincidencia de apellido directo o grupo societario en la nómina de despacho.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-xs text-rose-900 leading-relaxed font-medium">Designación de <?= $alertasNepotismo ?> asesores con coincidencia de apellido directo o grupo societario en la nómina de despacho.</p>
                    <?php endif; ?>
                </div>

                <!-- Alertas de Conflicto de Interés / Aportantes de Campaña (Amarillo) -->
                <div class="p-4 rounded-xl border border-amber-200 bg-amber-50/70 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-amber-800">
                            <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Conflictos de Interés & Financiamiento
                        </span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono bg-amber-200 text-amber-900">Alerta Amarilla</span>
                    </div>
                    <?php if (!empty($data['conflictos_transparencia'])): 
                        $apor = array_filter($data['conflictos_transparencia'], fn($c) => $c['tipo_alerta'] === 'Aportante_Campana');
                        foreach ($apor as $ap): ?>
                            <p class="text-xs text-amber-900 leading-relaxed"><?= e($ap['descripcion_alerta']) ?></p>
                        <?php endforeach; 
                        if (empty($apor)): ?>
                            <p class="text-xs text-amber-900 leading-relaxed">Cruce de aportes privados de campaña electoral vinculados a sectores económicos favorecidos en leyes con voto afirmativo.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-xs text-amber-900 leading-relaxed">Cruce de aportes privados de campaña electoral vinculados a sectores económicos favorecidos en leyes con voto afirmativo.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

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

    
    </div>
</div>
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
    <!-- ========================================================================= -->
    <!-- MODAL DE PRESENTACIÓN ESTILO POWERPOINT (SLIDES 16:9 / MODO PRESENTACIÓN)  -->
    <!-- ========================================================================= -->
    <style nonce="<?= csp_nonce() ?>">
    @media print {
        body * { visibility: hidden !important; }
        #modalPresentacion, #modalPresentacion * { visibility: visible !important; }
        #modalPresentacion { position: fixed !important; inset: 0 !important; background: white !important; z-index: 99999 !important; padding: 0 !important; }
        #modalPresentacion header, #modalPresentacion footer { display: none !important; }
        #modalPresentacion main { padding: 0 !important; margin: 0 !important; }
        #slideViewport { border: none !important; box-shadow: none !important; width: 100% !important; min-height: 100vh !important; }
    }
    </style>
    <div id="modalPresentacion" class="fixed inset-0 z-[100] hidden bg-slate-950 flex flex-col justify-center items-center overflow-y-auto lg:overflow-hidden select-none p-2 sm:p-4 md:p-6 font-sans text-slate-900" role="dialog" aria-modal="true" aria-label="Modo Presentación Diapositivas">
        
        <!-- Header Flotante Minimalista para Transmisión / TV (Floating Pill Toolbar) -->
        <header class="fixed top-3 sm:top-4 left-1/2 -translate-x-1/2 z-[110] max-w-[95vw] pointer-events-auto">
            <div class="flex items-center gap-2 sm:gap-3 bg-slate-900/90 hover:bg-slate-900 text-white rounded-full px-3 sm:px-4 py-1.5 shadow-2xl backdrop-blur-md border border-white/10 transition-all">
                <!-- Badge TV / Logo -->
                <div class="flex items-center gap-1.5 text-xs font-bold tracking-wider uppercase text-blue-400 shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                    </svg>
                    <span class="hidden sm:inline">DipuData TV</span>
                </div>

                <span class="text-white/20 text-xs hidden sm:inline">|</span>

                <!-- Nombre Legislador y Cargo -->
                <span class="text-xs font-medium text-slate-200 truncate max-w-[120px] sm:max-w-[200px] md:max-w-xs">
                    <?= e($leg['nombre']) ?> <span class="hidden md:inline text-slate-400 font-normal">· <?= e($cargo) ?></span>
                </span>

                <span class="text-white/20 text-xs">|</span>

                <!-- Contador Diapositivas -->
                <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded-full bg-white/10 text-slate-200 whitespace-nowrap">
                    <span id="currentSlideNum" class="text-blue-400 font-bold">1</span> / 7
                </span>

                <!-- Controles de Navegación -->
                <div class="flex items-center gap-1">
                    <button id="btnSlidePrev" type="button" class="p-1 rounded-full bg-white/10 hover:bg-white/20 text-white transition flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7" title="Diapositiva anterior (←)">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" /></svg>
                    </button>
                    <button id="btnSlideNext" type="button" class="p-1 rounded-full bg-blue-600 hover:bg-blue-500 text-white transition flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 shadow" title="Siguiente diapositiva (→ / Espacio)">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
                    </button>
                </div>

                <span class="text-white/20 text-xs hidden md:inline">|</span>

                <!-- Herramientas TV: Fullscreen & PDF -->
                <div class="hidden md:flex items-center gap-1">
                    <button id="btnToggleFullscreen" type="button" class="p-1 px-2 rounded-full hover:bg-white/10 text-slate-300 hover:text-white transition text-[11px] font-medium flex items-center gap-1" title="Pantalla Completa (F)">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                        <span>Full</span>
                    </button>
                    <button id="btnPrintSlides" type="button" class="p-1 px-2 rounded-full hover:bg-white/10 text-slate-300 hover:text-white transition text-[11px] font-medium flex items-center gap-1" title="Exportar PDF (Ctrl+P)">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                        <span>PDF</span>
                    </button>
                </div>

                <!-- Botón Salir -->
                <button id="btnCerrarPresentacion" type="button" class="p-1 px-2 rounded-full bg-rose-500/20 hover:bg-rose-500/40 text-rose-300 hover:text-rose-100 transition text-xs font-bold flex items-center gap-1 shrink-0" title="Cerrar Presentación (Esc)">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                    <span class="hidden sm:inline text-[11px]">Salir</span>
                </button>
            </div>
        </header>

        <!-- Escenario Principal: Diapositiva 16:9 con Padding para los Controles Flotantes -->
        <main class="w-full max-w-7xl mx-auto my-auto py-3 sm:py-6 flex items-center justify-center grow pt-14 pb-14">
            <div id="slideViewport" class="w-full aspect-[16/9] min-h-[440px] max-h-[86vh] bg-white border border-slate-200 rounded-2xl shadow-2xl p-6 sm:p-8 flex flex-col justify-between relative overflow-hidden text-slate-900">
                
                <!-- DIAPOSITIVA 1: Portada Ejecutiva y Ficha Técnica -->
                <div class="slide-deck-card active h-full flex flex-col justify-between" data-slide="1">
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                        <span class="text-xs uppercase font-mono tracking-widest text-slate-400 font-semibold">
                            Diapositiva 01 · Resumen Ejecutivo
                        </span>
                        <span class="text-[11px] font-mono text-slate-400">
                            Congreso de la Nación Argentina · Auditoría 360
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 my-auto items-center">
                        <div class="flex items-center gap-5 md:col-span-2">
                            <?php if (!empty($fotoUrl)): ?>
                                <img src="<?= e($fotoUrl) ?>" alt="Foto oficial" class="w-24 h-24 sm:w-28 sm:h-28 rounded-full object-cover border-2 <?= e($partidoColores['border']) ?> shadow-sm shrink-0">
                            <?php else: ?>
                                <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-slate-100 border-2 <?= e($partidoColores['border']) ?> grid place-items-center text-3xl font-extrabold text-slate-400 shrink-0">
                                    <?= e($iniciales) ?>
                                </div>
                            <?php endif; ?>
                            <div class="min-w-0">
                                <h2 class="text-2xl sm:text-4xl font-extrabold tracking-tight text-slate-900 leading-tight">
                                    <?= e($leg['nombre']) ?>
                                </h2>
                                <p class="text-sm sm:text-base text-slate-500 mt-1 font-medium">
                                    <?= e($cargo) ?> · <?= e($leg['provincia']) ?>
                                </p>
                                <div class="flex flex-wrap items-center gap-2 mt-2">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold <?= e($partidoColores['badge']) ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?= e($partidoColores['dot']) ?>"></span>
                                        <?= e($leg['bloque']) ?>
                                    </span>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-mono bg-slate-50 text-slate-500 border border-slate-200">
                                        CUIT <?= e($leg['cuit']) ?>
                                    </span>
                                    <?php if (!empty($fichaWiki['cargo_actual']) && $fichaWiki['cargo_actual'] !== $cargo): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                            <?= e($fichaWiki['cargo_actual']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Tarjeta de dictamen de integridad -->
                        <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-4">
                            <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400">Estatus Ético Cívico</span>
                            <div class="text-base font-bold mt-1 <?= ($conflictos['nivel_riesgo_etico'] ?? 'Bajo') === 'Bajo' ? 'text-emerald-700' : 'text-amber-700' ?>">
                                Riesgo <?= e($conflictos['nivel_riesgo_etico'] ?? 'Bajo') ?>
                            </div>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                                Auditoría integral cruzando patrimonio, disciplina de bloque y promesas electorales.
                            </p>
                        </div>
                    </div>

                    <!-- 4 Tarjetas de KPIs Principales de Portada -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 border-t border-slate-100">
                        <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3">
                            <span class="text-[10px] uppercase font-mono text-slate-400">Asistencia Recinto</span>
                            <div class="text-xl sm:text-2xl font-mono font-bold text-slate-900 mt-0.5">
                                <?= e(pct((float)$leg['asistencia'])) ?>
                            </div>
                            <span class="text-[10px] text-slate-400">Sesiones ordinarias</span>
                        </div>
                        <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3">
                            <span class="text-[10px] uppercase font-mono text-slate-400">Lealtad Partidaria</span>
                            <div class="text-xl sm:text-2xl font-mono font-bold text-slate-900 mt-0.5">
                                <?= e(pct((float)$leg['indice_lealtad'])) ?>
                            </div>
                            <span class="text-[10px] text-slate-400">Coincidencia de bloque</span>
                        </div>
                        <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3">
                            <span class="text-[10px] uppercase font-mono text-slate-400">Patrimonio Real USD</span>
                            <div class="text-xl sm:text-2xl font-mono font-bold mt-0.5 <?= $crecimientoRealUsd >= 0 ? 'text-emerald-700' : 'text-rose-700' ?>">
                                <?= $crecimientoRealUsd >= 0 ? '▲' : '▼' ?> <?= e(pct($crecimientoRealUsd, true)) ?>
                            </div>
                            <span class="text-[10px] text-slate-400">Dólar MEP (2020–2025)</span>
                        </div>
                        <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3">
                            <span class="text-[10px] uppercase font-mono text-slate-400">Defensa Provincial</span>
                            <div class="text-xl sm:text-2xl font-mono font-bold text-slate-900 mt-0.5">
                                <?= number_format($defensaProv['indice_defensa_provincial'] ?? 100, 1) ?>%
                            </div>
                            <span class="text-[10px] text-slate-400">Alineación con su provincia</span>
                        </div>
                    </div>
                </div>

                <!-- DIAPOSITIVA 2: Evolución Patrimonial Real (USD MEP) -->
                <div class="slide-deck-card hidden h-full flex flex-col justify-between" data-slide="2">
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                        <span class="text-xs uppercase font-mono tracking-widest text-slate-400 font-semibold">
                            Diapositiva 02 · Módulo Financiero
                        </span>
                        <span class="text-[11px] font-mono text-slate-400">
                            Declaraciones Juradas (Oficina Anticorrupción) · Serie 2020–2025
                        </span>
                    </div>

                    <div class="my-auto space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h3 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                                    Evolución Patrimonial Neta en Dólares MEP
                                </h3>
                                <p class="text-xs text-slate-500">
                                    Moneda dura deflactada: comparativa entre valuación inicial y última DDJJ presentada.
                                </p>
                            </div>
                            <div class="px-3.5 py-1.5 rounded-xl font-mono text-sm font-bold <?= $crecimientoRealUsd >= 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?>">
                                <?= $crecimientoRealUsd >= 0 ? '▲ Crecimiento Real:' : '▼ Variación Real:' ?> <?= e(pct($crecimientoRealUsd, true)) ?> USD
                            </div>
                        </div>

                        <!-- Gráfico de líneas horizontal Catmull-Rom para presentación -->
                        <?php if (!empty($serieEvo)): ?>
                        <div class="w-full bg-slate-50/70 border border-slate-200/80 rounded-xl p-3">
                            <svg viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight ?>" class="w-full h-32 sm:h-44 overflow-visible">
                                <defs>
                                    <linearGradient id="deckPatrimonioGrad" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="<?= $strokeColor ?>" stopOpacity="0.22" />
                                        <stop offset="100%" stopColor="<?= $strokeColor ?>" stopOpacity="0.01" />
                                    </linearGradient>
                                </defs>
                                <?php foreach ($yTicks as $tickPct):
                                    $tickY = $padTop + $plotH - $tickPct * $plotH;
                                ?>
                                    <line x1="<?= $padLeft ?>" y1="<?= round($tickY, 1) ?>" x2="<?= $chartWidth - $padRight ?>" y2="<?= round($tickY, 1) ?>" stroke="#e2e8f0" stroke-dasharray="4 4" stroke-width="1" />
                                <?php endforeach; ?>
                                <path d="<?= $areaPath ?>" fill="url(#deckPatrimonioGrad)" />
                                <path d="<?= $linePath ?>" fill="none" stroke="<?= $strokeColor ?>" stroke-width="3" stroke-linecap="round" />
                                <?php foreach ($chartPoints as $pt): ?>
                                    <circle cx="<?= number_format($pt['x'], 1, '.', '') ?>" cy="<?= number_format($pt['y'], 1, '.', '') ?>" r="5" fill="#ffffff" stroke="<?= $strokeColor ?>" stroke-width="2.5" />
                                    <text x="<?= number_format($pt['x'], 1, '.', '') ?>" y="<?= number_format($chartHeight - 6, 1, '.', '') ?>" text-anchor="middle" fill="#64748b" font-size="10" font-family="monospace">
                                        <?= e($pt['data']['anio_fiscal']) ?>
                                    </text>
                                <?php endforeach; ?>
                            </svg>
                        </div>
                        <?php endif; ?>

                        <!-- Tarjetas Comparativas de Montos -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5">
                                <span class="text-[10px] font-mono text-slate-400">Patrimonio Inicial (<?= e($anioInicial) ?>)</span>
                                <div class="text-base sm:text-lg font-mono font-bold text-slate-900 mt-0.5">
                                    USD <?= number_format($patrimonioInicialUsd, 0, ',', '.') ?>
                                </div>
                                <span class="text-[11px] font-mono text-slate-400">ARS: <?= e(ars((float)($serieEvo[0]['monto_pesos_nominal'] ?? 0))) ?></span>
                            </div>
                            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5">
                                <span class="text-[10px] font-mono text-slate-400">Última DDJJ (<?= e($anioActual) ?>)</span>
                                <div class="text-base sm:text-lg font-mono font-bold text-slate-900 mt-0.5">
                                    USD <?= number_format($patrimonioActualUsd, 0, ',', '.') ?>
                                </div>
                                <span class="text-[11px] font-mono text-slate-400">ARS: <?= e(ars((float)($serieEvo[count($serieEvo)-1]['monto_pesos_nominal'] ?? 0))) ?></span>
                            </div>
                            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5">
                                <span class="text-[10px] font-mono text-slate-400">ARS Constantes (Deflactado IPC)</span>
                                <div class="text-base sm:text-lg font-mono font-bold mt-0.5 <?= $crecimientoConstantes >= 0 ? 'text-emerald-700' : 'text-rose-700' ?>">
                                    <?= e(pct($crecimientoConstantes, true)) ?>
                                </div>
                                <span class="text-[11px] text-slate-400">Impacto de inflación acumulada</span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                        <span>Fuente: Declaraciones Juradas Patrimoniales Integrales (Ley 25.188)</span>
                        <span>Cotización Dólar MEP BCRA / Rava Bursátil</span>
                    </div>
                </div>

                <!-- DIAPOSITIVA 3: Radar de Posicionamiento Ideológico (11 Ejes) -->
                <div class="slide-deck-card hidden h-full flex flex-col justify-between" data-slide="3">
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                        <span class="text-xs uppercase font-mono tracking-widest text-slate-400 font-semibold">
                            Diapositiva 03 · Perfil Ideológico 360
                        </span>
                        <span class="text-[11px] font-mono text-slate-400">
                            Matriz de 11 Ejes Temáticos · Algoritmo de Similitud del Coseno
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 my-auto items-center">
                        <div class="flex justify-center">
                            <div class="w-64 h-64 sm:w-72 sm:h-72 relative">
                                <svg viewBox="0 0 <?= $radarSize ?> <?= $radarSize ?>" class="w-full h-full">
                                    <?php foreach ($radarRings as $ring):
                                        $rRadius = ($ring / 100) * $radarRadius;
                                    ?>
                                        <circle cx="<?= $radarCenter ?>" cy="<?= $radarCenter ?>" r="<?= $rRadius ?>" fill="none" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="2,2" />
                                    <?php endforeach; ?>
                                    <?php foreach ($radarVertices as $v): ?>
                                        <line x1="<?= $radarCenter ?>" y1="<?= $radarCenter ?>" x2="<?= $v['outerX'] ?>" y2="<?= $v['outerY'] ?>" stroke="#cbd5e1" stroke-width="1" />
                                        <text x="<?= $v['lblX'] ?>" y="<?= $v['lblY'] ?>" text-anchor="<?= $v['anchor'] ?>" fill="#475569" font-size="9" font-family="sans-serif" font-weight="500">
                                            <?= e($v['short']) ?>
                                        </text>
                                    <?php endforeach; ?>
                                    <polygon points="<?= $radarPointsStr ?>" fill="rgba(37, 99, 235, 0.16)" stroke="#2563eb" stroke-width="2" />
                                </svg>
                            </div>
                        </div>

                        <!-- Barras de Afinidad Temática Destacadas -->
                        <div class="space-y-3">
                            <h3 class="text-base sm:text-lg font-bold text-slate-900">
                                Afinidades de Voto por Eje Temático
                            </h3>
                            <p class="text-xs text-slate-500">
                                Porcentaje de votos afirmativos sobre emitidos en leyes clave de la gestión actual.
                            </p>
                            <div class="space-y-2.5 mt-2">
                                <?php 
                                $topEjes = $data['perfil'];
                                arsort($topEjes);
                                $top3 = array_slice($topEjes, 0, 4, true);
                                foreach ($top3 as $nombreEje => $porcentaje):
                                    $colBar = $colores[$nombreEje] ?? 'bg-blue-600';
                                ?>
                                <div>
                                    <div class="flex justify-between text-xs font-medium mb-1">
                                        <span class="text-slate-700"><?= e($nombreEje) ?></span>
                                        <span class="font-mono font-bold text-slate-900"><?= number_format($porcentaje, 1) ?>%</span>
                                    </div>
                                    <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                                        <div class="h-full <?= e($colBar) ?> rounded-full" style="width: <?= max(0, min(100, $porcentaje)) ?>%"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                        <span>Ponderación: 70% Votaciones Nominales + 30% Iniciativa de Proyectos</span>
                        <span>Vértices: 11 Ejes Constitucionales</span>
                    </div>
                </div>

                <!-- DIAPOSITIVA 4: Iniciativa Parlamentaria y Proyectos Clave -->
                <div class="slide-deck-card hidden h-full flex flex-col justify-between" data-slide="4">
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                        <span class="text-xs uppercase font-mono tracking-widest text-slate-400 font-semibold">
                            Diapositiva 04 · Iniciativa Legislativa
                        </span>
                        <span class="text-[11px] font-mono text-slate-400">
                            Agenda Proactiva · Proyectos de Ley con Expediente Formal
                        </span>
                    </div>

                    <div class="my-auto space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5">
                                <span class="text-[10px] font-mono uppercase text-slate-400">Total Proyectos</span>
                                <div class="text-2xl font-mono font-extrabold text-slate-900 mt-0.5">
                                    <?= (int)$agenda['total_proyectos'] ?>
                                </div>
                                <span class="text-[10px] text-slate-400">Iniciativas registradas</span>
                            </div>
                            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5">
                                <span class="text-[10px] font-mono uppercase text-slate-400">Autor Principal</span>
                                <div class="text-2xl font-mono font-extrabold text-blue-600 mt-0.5">
                                    <?= (int)$agenda['total_autor'] ?>
                                </div>
                                <span class="text-[10px] text-slate-400">Peso doble (2.0) en agenda</span>
                            </div>
                            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5">
                                <span class="text-[10px] font-mono uppercase text-slate-400">Co-autorías</span>
                                <div class="text-2xl font-mono font-extrabold text-slate-700 mt-0.5">
                                    <?= (int)$agenda['total_coautor'] ?>
                                </div>
                                <span class="text-[10px] text-slate-400">Firmas compartidas (1.0)</span>
                            </div>
                        </div>

                        <!-- Últimos proyectos presentados como Autor -->
                        <div>
                            <h4 class="text-xs font-mono uppercase tracking-wider text-slate-500 mb-2 font-semibold">
                                Proyectos de Autoría Principal Destacados:
                            </h4>
                            <div class="space-y-2">
                                <?php if (!empty($ultimosProyectos)): ?>
                                    <?php foreach (array_slice($ultimosProyectos, 0, 3) as $pr): ?>
                                    <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3 flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono text-xs font-bold text-blue-700"><?= e($pr['expediente']) ?></span>
                                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-200/70 text-slate-700 font-medium"><?= e($pr['eje_tematico']) ?></span>
                                            </div>
                                            <p class="text-xs text-slate-800 font-medium truncate mt-0.5"><?= e($pr['titulo_sumario']) ?></p>
                                        </div>
                                        <span class="text-[10px] font-mono text-emerald-700 px-2.5 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 shrink-0 font-medium">
                                            <?= e($pr['estado_tramite']) ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-xs text-slate-400 italic">No registra proyectos de autoría propia en el período evaluado.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                        <span>Fuente: Mesa de Entradas H. Cámara de Diputados / Senado de la Nación</span>
                        <span>Fórmula: W_autor = 2.0 | W_coautor = 1.0</span>
                    </div>
                </div>

                <!-- DIAPOSITIVA 5: Tensión Federal: Defensa Provincial vs. Disciplina de Bloque -->
                <div class="slide-deck-card hidden h-full flex flex-col justify-between" data-slide="5">
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                        <span class="text-xs uppercase font-mono tracking-widest text-slate-400 font-semibold">
                            Diapositiva 05 · Tensión Federal
                        </span>
                        <span class="text-[11px] font-mono text-slate-400">
                            Interés Provincial vs. Disciplina Partidaria Nacional
                        </span>
                    </div>

                    <div class="my-auto space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center">
                            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-4">
                                <span class="text-[10px] font-mono uppercase text-slate-400">Defensa Provincial</span>
                                <div class="text-3xl font-mono font-extrabold text-blue-600 mt-1">
                                    <?= number_format($defensaProv['indice_defensa_provincial'] ?? 100, 1) ?>%
                                </div>
                                <p class="text-[10px] text-slate-500 mt-1">Alineación con intereses de <?= e($leg['provincia']) ?></p>
                            </div>
                            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-4">
                                <span class="text-[10px] font-mono uppercase text-slate-400">Lealtad al Bloque</span>
                                <div class="text-3xl font-mono font-extrabold text-slate-900 mt-1">
                                    <?= number_format($defensaProv['indice_lealtad_partido'] ?? 100, 1) ?>%
                                </div>
                                <p class="text-[10px] text-slate-500 mt-1">Acatamiento a la cúpula de <?= e($leg['bloque']) ?></p>
                            </div>
                            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-4">
                                <span class="text-[10px] font-mono uppercase text-slate-400">Brecha de Tensión</span>
                                <div class="text-3xl font-mono font-extrabold mt-1 <?= ($defensaProv['brecha_tension'] ?? 0) > 15 ? 'text-rose-600' : 'text-emerald-600' ?>">
                                    <?= number_format($defensaProv['brecha_tension'] ?? 0, 1) ?> pts
                                </div>
                                <p class="text-[10px] text-slate-500 mt-1">Divergencia entre partido y provincia</p>
                            </div>
                        </div>

                        <!-- Votos en conflicto con su provincia -->
                        <div>
                            <h4 class="text-xs font-mono uppercase tracking-wider text-slate-500 mb-2 font-semibold">
                                Auditoría de Votaciones de Impacto Regional:
                            </h4>
                            <div class="space-y-2">
                                <?php if (!empty($defensaProv['alertas_conflicto'])): ?>
                                    <?php foreach (array_slice($defensaProv['alertas_conflicto'], 0, 2) as $ac): ?>
                                    <div class="bg-rose-50 border border-rose-200 rounded-xl p-3">
                                        <div class="flex items-center justify-between text-xs font-bold text-rose-900">
                                            <span><?= e($ac['ley']) ?></span>
                                            <span class="font-mono text-[10px] px-2 py-0.5 rounded bg-rose-100 text-rose-800 font-semibold">
                                                Votó: <?= e($ac['voto_legislador']) ?> (Esperado: <?= e($ac['voto_esperado_regional']) ?>)
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-rose-700 mt-1"><?= e($ac['motivo']) ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 flex items-center gap-2 text-xs text-emerald-800 font-medium">
                                        <span class="font-bold">✔</span>
                                        <span>No registra votaciones en conflicto con las leyes estratégicas de <?= e($leg['provincia']) ?>.</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                        <span>Cruce: Voto Nominal vs. Posición Mayoritaria de Bloque</span>
                        <span>Matriz de Impacto Provincial (Leyes 2020–2024)</span>
                    </div>
                </div>

                <!-- DIAPOSITIVA 6: Eficiencia de Despacho, Asesores y Costo por Proyecto -->
                <div class="slide-deck-card hidden h-full flex flex-col justify-between" data-slide="6">
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                        <span class="text-xs uppercase font-mono tracking-widest text-slate-400 font-semibold">
                            Diapositiva 06 · Eficiencia de Despacho
                        </span>
                        <span class="text-[11px] font-mono text-slate-400">
                            Auditoría de Gasto Público y Personal (Integración n8n.buceo.co)
                        </span>
                    </div>

                    <div class="my-auto space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5">
                                <span class="text-[10px] font-mono uppercase text-slate-400">Total de Asesores</span>
                                <div class="text-3xl font-mono font-extrabold text-slate-900 mt-1">
                                    <?= (int)$totalAsesores ?>
                                </div>
                                <span class="text-[10px] text-slate-500">Masa salarial: <?= e(ars($costoMensual)) ?>/mes</span>
                            </div>

                            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5">
                                <span class="text-[10px] font-mono uppercase text-slate-400">Costo por Proyecto Propio</span>
                                <div class="text-2xl font-mono font-extrabold mt-1 <?= $superaMedia ? 'text-rose-600' : 'text-emerald-600' ?>">
                                    <?= e(ars($costoPorProyecto)) ?>
                                </div>
                                <span class="text-[10px] text-slate-500">Media Cámara: <?= e(ars($mediaCamaraCosto)) ?></span>
                            </div>

                            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5">
                                <span class="text-[10px] font-mono uppercase text-slate-400">Alertas de Nepotismo</span>
                                <div class="text-2xl font-mono font-extrabold mt-1 <?= $alertasNepotismo > 0 ? 'text-rose-600' : 'text-emerald-600' ?>">
                                    <?= (int)$alertasNepotismo ?> <?= $alertasNepotismo === 0 ? '✔' : '⚠' ?>
                                </div>
                                <span class="text-[10px] text-slate-500"><?= $alertasNepotismo === 0 ? 'Sin coincidencias familiares' : 'Posible familiar en nómina' ?></span>
                            </div>
                        </div>

                        <!-- Cuadro explicativo metodológico -->
                        <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-4 text-xs text-slate-600 leading-relaxed">
                            <strong class="text-slate-900 font-mono uppercase">Ratio de Retorno Cívico:</strong>
                            El costo por proyecto se calcula contrastando la erogación anual del despacho (salarios + SAC de asesores en nómina) sobre las iniciativas de ley con expediente formal ingresadas como autoría exclusiva en el año parlamentario analizado.
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                        <span>Fuente: Datos Abiertos HCDN / Senado de la Nación</span>
                        <span>Pipeline ETL: n8n.buceo.co /api/n8n/update-despachos</span>
                    </div>
                </div>

                <!-- DIAPOSITIVA 7: Auditoría Ética, Coherencia Histórica y Promesas -->
                <div class="slide-deck-card hidden h-full flex flex-col justify-between" data-slide="7">
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                        <span class="text-xs uppercase font-mono tracking-widest text-slate-400 font-semibold">
                            Diapositiva 07 · Auditoría Ética & Integridad
                        </span>
                        <span class="text-[11px] font-mono text-slate-400">
                            Leyes Espejo · Alianzas Opositoras · Financiamiento vs. Votos
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 my-auto">
                        <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-4">
                            <span class="text-[10px] font-mono uppercase text-slate-400">Coherencia en Leyes Espejo</span>
                            <div class="text-2xl font-mono font-extrabold text-slate-900 mt-1">
                                <?= number_format($coherencia['indice_coherencia'] ?? 100, 1) ?>%
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                <?= empty($coherencia['alertas_panquequismo']) ? 'Sin cambios de voto ante la misma ley bajo distintos signos de gobierno.' : 'Alerta de inconsistencia ante proyectos idénticos.' ?>
                            </p>
                        </div>

                        <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-4">
                            <span class="text-[10px] font-mono uppercase text-slate-400">Alianzas Transversales</span>
                            <div class="text-2xl font-mono font-extrabold text-blue-600 mt-1">
                                <?= number_format($alianzas['indice_transversalidad'] ?? 0, 1) ?>%
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                Capacidad de generar acuerdos parlamentarios y co-firmar proyectos con fuerzas opositoras.
                            </p>
                        </div>

                        <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-4">
                            <span class="text-[10px] font-mono uppercase text-slate-400">Cumplimiento de Promesas</span>
                            <div class="text-2xl font-mono font-extrabold mt-1 <?= ($promesas['tasa_cumplimiento_pct'] ?? 100) >= 70 ? 'text-emerald-600' : 'text-amber-600' ?>">
                                <?= number_format($promesas['tasa_cumplimiento_pct'] ?? 100, 1) ?>%
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                Promesas de campaña electoral ratificadas en votaciones del recinto (<?= (int)($promesas['cumplidas'] ?? 0) ?> cumplidas, <?= (int)($promesas['incumplidas'] ?? 0) ?> pendientes).
                            </p>
                        </div>

                        <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-4">
                            <span class="text-[10px] font-mono uppercase text-slate-400">Financiamiento Privado</span>
                            <div class="text-2xl font-mono font-extrabold text-slate-900 mt-1">
                                <?= e(ars((float)($conflictos['total_aportes_declarados_ars'] ?? 0))) ?>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                Cruce entre aportes de campaña electoral y votación de regímenes sectoriales.
                            </p>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                        <span>Dictamen Final: DipuData / Match Electoral 360</span>
                        <span>dipudata.com · Plataforma Cívica Independiente</span>
                    </div>
                </div>

            </div>
        </main>

        <!-- Dock Flotante Inferior: Selector Rápido de Diapositivas para TV -->
        <footer class="fixed bottom-3 sm:bottom-4 left-1/2 -translate-x-1/2 z-[110] max-w-[95vw] pointer-events-auto">
            <div class="flex items-center gap-1.5 sm:gap-2 bg-slate-900/90 text-white rounded-full px-3 py-1.5 shadow-2xl backdrop-blur-md border border-white/10 overflow-x-auto">
                <div class="flex items-center gap-1 shrink-0">
                    <button type="button" class="slide-nav-dot px-2.5 py-1 rounded-full text-xs font-bold bg-blue-600 text-white shadow transition-all" data-target="1">
                        1. Portada
                    </button>
                    <button type="button" class="slide-nav-dot px-2.5 py-1 rounded-full text-xs font-medium bg-white/10 hover:bg-white/20 text-slate-300 transition-all" data-target="2">
                        2. DDJJ USD
                    </button>
                    <button type="button" class="slide-nav-dot px-2.5 py-1 rounded-full text-xs font-medium bg-white/10 hover:bg-white/20 text-slate-300 transition-all" data-target="3">
                        3. Radar 360
                    </button>
                    <button type="button" class="slide-nav-dot px-2.5 py-1 rounded-full text-xs font-medium bg-white/10 hover:bg-white/20 text-slate-300 transition-all" data-target="4">
                        4. Proyectos
                    </button>
                    <button type="button" class="slide-nav-dot px-2.5 py-1 rounded-full text-xs font-medium bg-white/10 hover:bg-white/20 text-slate-300 transition-all" data-target="5">
                        5. Tensión Federal
                    </button>
                    <button type="button" class="slide-nav-dot px-2.5 py-1 rounded-full text-xs font-medium bg-white/10 hover:bg-white/20 text-slate-300 transition-all" data-target="6">
                        6. Eficiencia
                    </button>
                    <button type="button" class="slide-nav-dot px-2.5 py-1 rounded-full text-xs font-medium bg-white/10 hover:bg-white/20 text-slate-300 transition-all" data-target="7">
                        7. Auditoría
                    </button>
                </div>
                <span class="text-white/20 text-xs hidden lg:inline">|</span>
                <div class="hidden lg:flex items-center gap-1.5 text-[10px] font-mono text-slate-400 shrink-0">
                    <kbd class="px-1.5 py-0.5 rounded bg-white/10 text-slate-200">←</kbd>
                    <kbd class="px-1.5 py-0.5 rounded bg-white/10 text-slate-200">→</kbd>
                    <span class="text-slate-400">Navegar</span>
                    <span>·</span>
                    <kbd class="px-1.5 py-0.5 rounded bg-white/10 text-slate-200">F</kbd>
                    <span class="text-slate-400">Pantalla</span>
                    <span>·</span>
                    <kbd class="px-1.5 py-0.5 rounded bg-white/10 text-slate-200">Esc</kbd>
                    <span class="text-slate-400">Salir</span>
                </div>
            </div>
        </footer>

    </div>

    <!-- Script de Control del Modo Presentación PowerPoint con CSP Nonce Estricto -->
    <script nonce="<?= csp_nonce() ?>">
    (function () {
        function initPresentacion() {
            var modal = document.getElementById('modalPresentacion');
            var btnAbrir = document.getElementById('btnAbrirPresentacion');
            var btnCerrar = document.getElementById('btnCerrarPresentacion');
            var btnPrev = document.getElementById('btnSlidePrev');
            var btnNext = document.getElementById('btnSlideNext');
            var btnFs = document.getElementById('btnToggleFullscreen');
            var btnPrint = document.getElementById('btnPrintSlides');
            var slideCards = document.querySelectorAll('.slide-deck-card');
            var navDots = document.querySelectorAll('.slide-nav-dot');
            var counterNum = document.getElementById('currentSlideNum');
            var totalSlides = slideCards.length;
            var currentSlide = 1;

            function showSlide(n) {
                if (n < 1) n = 1;
                if (n > totalSlides) n = totalSlides;
                currentSlide = n;

                slideCards.forEach(function (c) {
                    var sNum = parseInt(c.getAttribute('data-slide'), 10);
                    if (sNum === currentSlide) {
                        c.classList.remove('hidden');
                        c.classList.add('flex');
                    } else {
                        c.classList.add('hidden');
                        c.classList.remove('flex');
                    }
                });

                navDots.forEach(function (d) {
                    var target = parseInt(d.getAttribute('data-target'), 10);
                    if (target === currentSlide) {
                        d.className = 'slide-nav-dot px-2.5 py-1 rounded-full text-xs font-bold bg-blue-600 text-white shadow transition-all';
                    } else {
                        d.className = 'slide-nav-dot px-2.5 py-1 rounded-full text-xs font-medium bg-white/10 hover:bg-white/20 text-slate-300 transition-all';
                    }
                });

                if (counterNum) counterNum.textContent = currentSlide;
            }

            function abrirModal() {
                if (!modal) return;
                modal.classList.remove('hidden');
                modal.style.display = 'flex';
                document.body.classList.add('overflow-hidden');
                showSlide(currentSlide);
                try {
                    var url = new URL(window.location.href);
                    url.searchParams.set('modo', 'presentacion');
                    window.history.replaceState({}, '', url.toString());
                } catch (e) {}
            }

            function cerrarModal() {
                if (!modal) return;
                modal.classList.add('hidden');
                modal.style.display = 'none';
                document.body.classList.remove('overflow-hidden');
                if (document.fullscreenElement) {
                    try { document.exitFullscreen(); } catch (e) {}
                }
                try {
                    var url = new URL(window.location.href);
                    url.searchParams.delete('modo');
                    url.searchParams.delete('slides');
                    window.history.replaceState({}, '', url.pathname + (url.search ? url.search : ''));
                } catch (e) {}
            }

            // Exponer globalmente para fallback
            window.abrirPresentacionModal = abrirModal;
            window.cerrarPresentacionModal = cerrarModal;
            window.cambiarSlidePresentacion = showSlide;

            if (btnAbrir) btnAbrir.addEventListener('click', abrirModal);
            if (btnCerrar) btnCerrar.addEventListener('click', cerrarModal);

            // Delegación de eventos en el documento para el botón de apertura y botones dentro del modal
            document.addEventListener('click', function (e) {
                var trigger = e.target.closest('#btnAbrirPresentacion, [data-action="abrir-presentacion"]');
                if (trigger) {
                    e.preventDefault();
                    abrirModal();
                }
            });

            if (btnPrev) {
                btnPrev.addEventListener('click', function () {
                    showSlide(currentSlide - 1);
                });
            }
            if (btnNext) {
                btnNext.addEventListener('click', function () {
                    showSlide(currentSlide + 1);
                });
            }

            navDots.forEach(function (dot) {
                dot.addEventListener('click', function () {
                    var t = parseInt(dot.getAttribute('data-target'), 10);
                    if (!isNaN(t)) showSlide(t);
                });
            });

            if (btnFs) {
                btnFs.addEventListener('click', function () {
                    if (!document.fullscreenElement) {
                        if (modal.requestFullscreen) {
                            modal.requestFullscreen();
                        } else if (modal.webkitRequestFullscreen) {
                            modal.webkitRequestFullscreen();
                        }
                    } else {
                        if (document.exitFullscreen) {
                            document.exitFullscreen();
                        }
                    }
                });
            }

            if (btnPrint) {
                btnPrint.addEventListener('click', function () {
                    window.print();
                });
            }

            // Navegación por Teclado
            window.addEventListener('keydown', function (e) {
                if (!modal || modal.classList.contains('hidden') || modal.style.display === 'none') return;

                if (e.key === 'ArrowRight' || e.key === ' ' || e.key === 'PageDown') {
                    e.preventDefault();
                    showSlide(currentSlide + 1);
                } else if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
                    e.preventDefault();
                    showSlide(currentSlide - 1);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    cerrarModal();
                } else if (e.key === 'f' || e.key === 'F') {
                    if (btnFs) btnFs.click();
                }
            });

            // Apertura automática si viene el parámetro ?modo=presentacion o ?slides=1
            try {
                var params = new URLSearchParams(window.location.search);
                if (params.get('modo') === 'presentacion' || params.get('slides') === '1') {
                    abrirModal();
                }
            } catch (e) {}
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initPresentacion);
        } else {
            initPresentacion();
        }
    })();
    </script>
</div>
<?php require __DIR__ . '/app/Views/layouts/footer.php'; ?>
