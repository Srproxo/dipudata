<?php
/**
 * Vista de Ley Parlamentaria — DipuData (/ley/{slug})
 * @var array $data
 */

$ley = $ley ?? ($data['ley'] ?? []);
$totales = $totales ?? ($data['totales'] ?? ['afirmativo' => 0, 'negativo' => 0, 'abstencion' => 0, 'ausente' => 0, 'total' => 0]);
$porBloque = $por_bloque ?? ($data['por_bloque'] ?? []);
$porProvincia = $por_provincia ?? ($data['por_provincia'] ?? []);
$porCamara = $por_camara ?? ($data['por_camara'] ?? []);
$votosNominales = $votos_nominales ?? ($data['votos_nominales'] ?? []);
$otrasLeyes = $otras_leyes ?? ($data['otras_leyes'] ?? []);

$pctAfirmativo = $totales['total'] > 0 ? round(($totales['afirmativo'] / $totales['total']) * 100, 1) : 0;
$pctNegativo = $totales['total'] > 0 ? round(($totales['negativo'] / $totales['total']) * 100, 1) : 0;
$pctAbstencion = $totales['total'] > 0 ? round(($totales['abstencion'] / $totales['total']) * 100, 1) : 0;
$pctAusente = $totales['total'] > 0 ? round(($totales['ausente'] / $totales['total']) * 100, 1) : 0;

$resultadoBadge = match (true) {
    str_contains(strtolower($ley['estado_tramite']), 'sancionada') => 'bg-emerald-100 text-emerald-800 border-emerald-300',
    str_contains(strtolower($ley['estado_tramite']), 'vetada') => 'bg-rose-100 text-rose-800 border-rose-300',
    str_contains(strtolower($ley['estado_tramite']), 'dictamen') => 'bg-blue-100 text-blue-800 border-blue-300',
    default => 'bg-amber-100 text-amber-800 border-amber-300',
};
?>

<!-- Migas de Pan (Breadcrumbs) -->
<nav class="text-sm text-slate-500 mb-6 flex flex-wrap items-center gap-1.5" aria-label="Migas de pan">
    <a href="/" class="hover:text-slate-900 transition-colors">Inicio</a>
    <span aria-hidden="true">/</span>
    <a href="/leyes" class="hover:text-slate-900 transition-colors">Leyes Tratadas</a>
    <span aria-hidden="true">/</span>
    <a href="/eje/<?= e($ley['eje_slug']) ?>" class="hover:text-slate-900 transition-colors"><?= e($ley['eje_nombre']) ?></a>
    <span aria-hidden="true">/</span>
    <span class="text-slate-900 font-semibold truncate max-w-xs sm:max-w-md"><?= e($ley['titulo_corto']) ?></span>
</nav>

<!-- Encabezado Principal de la Ley -->
<header class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8 mb-8 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-100">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-xs font-bold px-2.5 py-1 rounded bg-slate-900 text-white">
                <?= e($ley['expediente']) ?>
            </span>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full border <?= $resultadoBadge ?>">
                <?= e($ley['estado_tramite']) ?>
            </span>
            <a href="/eje/<?= e($ley['eje_slug']) ?>" class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 transition-colors">
                <span>Eje: <?= e($ley['eje_nombre']) ?> ↗</span>
            </a>
        </div>
        <div class="text-xs text-slate-500 font-mono">
            Votación: <?= date('d/m/Y', strtotime($ley['fecha_votacion'])) ?>
        </div>
    </div>

    <div>
        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-slate-900 leading-tight">
            <?= e($ley['titulo']) ?>
        </h1>
        <p class="text-sm sm:text-base text-slate-500 mt-2 font-medium">
            Iniciador oficial: <strong class="text-slate-800"><?= e($ley['quien_la_presento']) ?></strong> · <?= e($ley['camara_origen']) ?>
        </p>
    </div>

    <!-- Barra de Votos Totales -->
    <div class="space-y-2 pt-2">
        <div class="flex flex-wrap items-center justify-between text-xs font-semibold">
            <div class="flex items-center gap-4">
                <span class="text-emerald-700 flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span>
                    Afirmativos: <strong><?= $totales['afirmativo'] ?></strong> (<?= $pctAfirmativo ?>%)
                </span>
                <span class="text-rose-700 flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-rose-500 inline-block"></span>
                    Negativos: <strong><?= $totales['negativo'] ?></strong> (<?= $pctNegativo ?>%)
                </span>
                <span class="text-amber-700 flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-amber-400 inline-block"></span>
                    Abstenciones: <strong><?= $totales['abstencion'] ?></strong> (<?= $pctAbstencion ?>%)
                </span>
                <span class="text-slate-500 flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-slate-400 inline-block"></span>
                    Ausentes: <strong><?= $totales['ausente'] ?></strong> (<?= $pctAusente ?>%)
                </span>
            </div>
            <span class="font-mono text-slate-500">Padrón: <?= $totales['total'] ?> legisladores</span>
        </div>

        <div class="w-full h-3.5 rounded-full bg-slate-100 overflow-hidden flex shadow-inner">
            <div style="width: <?= $pctAfirmativo ?>%" class="bg-emerald-500 h-full" title="Afirmativo: <?= $pctAfirmativo ?>%"></div>
            <div style="width: <?= $pctNegativo ?>%" class="bg-rose-500 h-full" title="Negativo: <?= $pctNegativo ?>%"></div>
            <div style="width: <?= $pctAbstencion ?>%" class="bg-amber-400 h-full" title="Abstención: <?= $pctAbstencion ?>%"></div>
            <div style="width: <?= $pctAusente ?>%" class="bg-slate-300 h-full" title="Ausente: <?= $pctAusente ?>%"></div>
        </div>
    </div>
</header>

<!-- Pipeline Parlamentario Visual / Recorrido de la Ley -->
<?php if (!empty($ley['recorrido'])): ?>
<section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8 mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 mb-6 border-b border-slate-100">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Trámite Institucional</span>
                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full <?= ($ley['origen_iniciativa'] ?? 'Legislativa') === 'PEN' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-700 border border-slate-200' ?>">
                    <?= e($ley['origen_etiqueta'] ?? 'Iniciativa Parlamentaria') ?>
                </span>
            </div>
            <h2 class="text-xl font-bold tracking-tight text-slate-900 mt-1">Recorrido Parlamentario del Proyecto</h2>
        </div>
        <div class="text-xs font-mono text-slate-400">
            Expediente: <?= e($ley['expediente']) ?>
        </div>
    </div>

    <!-- Timeline Horizontal de 5 Fases -->
    <div class="relative">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 relative">
            <?php foreach ($ley['recorrido'] as $idx => $paso): 
                $estado = $paso['estado'];
                $circuloClase = match ($estado) {
                    'completado' => 'bg-emerald-600 text-white border-emerald-600 ring-4 ring-emerald-100',
                    'actual'     => 'bg-indigo-600 text-white border-indigo-600 ring-4 ring-indigo-100 animate-pulse',
                    'vetado'     => 'bg-rose-600 text-white border-rose-600 ring-4 ring-rose-100',
                    default      => 'bg-slate-200 text-slate-500 border-slate-300',
                };
                $badgeClase = match ($estado) {
                    'completado' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                    'actual'     => 'bg-indigo-50 text-indigo-800 border-indigo-200 font-bold',
                    'vetado'     => 'bg-rose-50 text-rose-800 border-rose-200 font-bold',
                    default      => 'bg-slate-100 text-slate-500 border-slate-200',
                };
                $textoEstado = match ($estado) {
                    'completado' => 'Completado',
                    'actual'     => 'En debate / Actual',
                    'vetado'     => 'Veto Presidencial',
                    default      => 'Pendiente',
                };
            ?>
                <div class="flex flex-col items-start p-4 rounded-xl bg-slate-50/70 border border-slate-200/80 hover:bg-slate-50 transition-colors">
                    <div class="flex items-center justify-between w-full mb-2">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border transition-all <?= $circuloClase ?>">
                            <?php if ($estado === 'completado'): ?>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            <?php elseif ($estado === 'vetado'): ?>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                            <?php else: ?>
                                <?= $idx + 1 ?>
                            <?php endif; ?>
                        </div>
                        <span class="text-[10px] uppercase font-semibold px-2 py-0.5 rounded-full border <?= $badgeClase ?>">
                            <?= $textoEstado ?>
                        </span>
                    </div>

                    <h3 class="font-bold text-xs sm:text-sm text-slate-900 leading-snug">
                        <?= e($paso['etapa']) ?>
                    </h3>
                    <span class="text-[11px] font-semibold text-slate-500 mt-0.5">
                        <?= e($paso['camara']) ?>
                    </span>
                    <p class="text-[11px] text-slate-600 mt-1 leading-relaxed">
                        <?= e($paso['detalle']) ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Conexión Oficial con el Boletín Oficial (BORA) si aplica -->
    <?php if (!empty($ley['boletin_oficial'])): 
        $bora = $ley['boletin_oficial'];
    ?>
    <div class="mt-6 p-4 rounded-xl bg-gradient-to-r from-blue-50 to-indigo-50/70 border border-blue-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start gap-3">
            <span class="text-2xl shrink-0">📜</span>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-blue-900">Boletín Oficial de la República Argentina (BORA)</span>
                    <span class="px-2 py-0.5 bg-blue-100 text-blue-800 text-[10px] font-bold rounded">Oficial</span>
                </div>
                <p class="text-xs sm:text-sm font-semibold text-slate-900 mt-0.5">
                    Publicada con <?= e($bora['decreto']) ?> · Fecha: <?= e($bora['fecha_publicacion']) ?> (Aviso N° <?= e($bora['numero_aviso']) ?>)
                </p>
                <p class="text-xs text-slate-600 mt-0.5">
                    <?= e($bora['sintesis']) ?>
                </p>
            </div>
        </div>
        <a href="<?= e($bora['url']) ?>" rel="noopener nofollow" target="_blank"
           class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-blue-700 hover:bg-blue-800 text-white rounded-lg text-xs font-semibold transition shadow-sm shrink-0">
            <span>Ver Publicación en BORA</span>
            <span aria-hidden="true">↗</span>
        </a>
    </div>
    <?php endif; ?>

    <!-- Barra de Compartir Votación Cívica -->
    <div class="mt-5 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
        <span class="font-medium text-slate-500">¿Querés que más ciudadanos sepan cómo votaron sus representantes?</span>
        <div class="flex items-center gap-2">
            <?php 
            $shareText = rawurlencode("🏛️ Mirá cómo votaron los diputados y senadores de tu provincia la ley: " . $ley['titulo_corto'] . " en DipuData:\n" . SITE_URL . "/ley/" . $ley['slug']);
            $shareUrl = rawurlencode(SITE_URL . "/ley/" . $ley['slug']);
            ?>
            <a href="https://api.whatsapp.com/send?text=<?= $shareText ?>" target="_blank" rel="noopener nofollow"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold transition">
                <span>WhatsApp</span>
            </a>
            <a href="https://twitter.com/intent/tweet?text=<?= $shareText ?>" target="_blank" rel="noopener nofollow"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-black text-white font-semibold transition">
                <span>X / Twitter</span>
            </a>
            <button type="button" onclick="navigator.clipboard.writeText(window.location.href); alert('Enlace copiado al portapapeles');"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold border border-slate-300 transition">
                <span>Copiar Enlace</span>
            </button>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Dossier Cívico: 3 Preguntas Clave (De qué trata, Qué implica, Por qué es importante) -->
<section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Card 1: ¿De qué trata? -->
    <article class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-3">
        <div class="flex items-center gap-2 text-blue-700 font-bold text-xs uppercase tracking-wider">
            <span class="w-7 h-7 rounded-lg bg-blue-50 flex items-center justify-center text-sm">📌</span>
            ¿De qué trata la ley?
        </div>
        <h2 class="text-base font-bold text-slate-900 leading-snug">Resumen Ciudadano</h2>
        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
            <?= e($ley['resumen']) ?>
        </p>
    </article>

    <!-- Card 2: ¿Qué implica? -->
    <article class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-3">
        <div class="flex items-center gap-2 text-indigo-700 font-bold text-xs uppercase tracking-wider">
            <span class="w-7 h-7 rounded-lg bg-indigo-50 flex items-center justify-center text-sm">⚙️</span>
            ¿Qué implica en la práctica?
        </div>
        <h2 class="text-base font-bold text-slate-900 leading-snug">Impacto Operativo y Fiscal</h2>
        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
            <?= e($ley['que_implica']) ?>
        </p>
    </article>

    <!-- Card 3: ¿Por qué es importante? -->
    <article class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-3">
        <div class="flex items-center gap-2 text-emerald-700 font-bold text-xs uppercase tracking-wider">
            <span class="w-7 h-7 rounded-lg bg-emerald-50 flex items-center justify-center text-sm">🎯</span>
            ¿Por qué es importante?
        </div>
        <h2 class="text-base font-bold text-slate-900 leading-snug">Relevancia Institucional</h2>
        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
            <?= e($ley['porque_es_importante']) ?>
        </p>
    </article>
</section>

<!-- Panel Interactivo de Votación Nominal con Agrupaciones -->
<section x-data="{ modo: 'bloque', busqueda: '', filtroVoto: 'todos' }" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8 mb-8 space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-slate-100">
        <div>
            <h2 class="text-xl font-extrabold tracking-tight text-slate-900">
                Radiografía de la Votación en Recinto
            </h2>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Explorá el comportamiento de los legisladores agrupados por partido político, por provincia o por sentido del voto.
            </p>
        </div>

        <!-- Selector de Modo de Agrupación -->
        <div class="flex items-center gap-1.5 p-1 bg-slate-100 rounded-xl self-start lg:self-auto shrink-0">
            <button type="button" @click="modo = 'bloque'" :class="modo === 'bloque' ? 'bg-white text-slate-900 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900 font-medium'" class="px-3 py-1.5 rounded-lg text-xs transition-all">
                🏛️ Por Bloque
            </button>
            <button type="button" @click="modo = 'provincia'" :class="modo === 'provincia' ? 'bg-white text-slate-900 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900 font-medium'" class="px-3 py-1.5 rounded-lg text-xs transition-all">
                🗺️ Por Provincia
            </button>
            <button type="button" @click="modo = 'desglose'" :class="modo === 'desglose' ? 'bg-white text-slate-900 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900 font-medium'" class="px-3 py-1.5 rounded-lg text-xs transition-all">
                📊 Por Sentido de Voto
            </button>
            <button type="button" @click="modo = 'nominal'" :class="modo === 'nominal' ? 'bg-white text-slate-900 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900 font-medium'" class="px-3 py-1.5 rounded-lg text-xs transition-all">
                📋 Padrón Nominal
            </button>
        </div>
    </div>

    <!-- MODO 1: AGRUPADO POR BLOQUE POLÍTICO -->
    <div x-show="modo === 'bloque'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($porBloque as $b): 
            $af = $b['afirmativo'];
            $neg = $b['negativo'];
            $tot = $b['total'];
            $pctAf = $tot > 0 ? round(($af / $tot) * 100, 1) : 0;
            $pctNeg = $tot > 0 ? round(($neg / $tot) * 100, 1) : 0;
        ?>
        <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/50 hover:bg-white hover:shadow-md transition-all space-y-3">
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-bold text-slate-900 text-sm truncate" title="<?= e($b['bloque']) ?>">
                    <?= e($b['bloque']) ?>
                </h3>
                <span class="font-mono text-xs px-2 py-0.5 rounded bg-slate-200 text-slate-700 font-bold shrink-0">
                    <?= $tot ?> bancas
                </span>
            </div>

            <!-- Mini barra de cohesión del bloque -->
            <div class="space-y-1">
                <div class="flex justify-between text-[11px] text-slate-500 font-mono">
                    <span class="text-emerald-700 font-semibold"><?= $af ?> a favor (<?= $pctAf ?>%)</span>
                    <span class="text-rose-700 font-semibold"><?= $neg ?> en contra (<?= $pctNeg ?>%)</span>
                </div>
                <div class="w-full h-2 rounded-full bg-slate-200 overflow-hidden flex">
                    <div style="width: <?= $pctAf ?>%" class="bg-emerald-500 h-full"></div>
                    <div style="width: <?= $pctNeg ?>%" class="bg-rose-500 h-full"></div>
                    <div style="width: <?= 100 - $pctAf - $pctNeg ?>%" class="bg-slate-400 h-full"></div>
                </div>
            </div>

            <div class="text-[11px] text-slate-400 flex items-center justify-between">
                <span>Abstenciones: <?= $b['abstencion'] ?></span>
                <span>Ausentes: <?= $b['ausente'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- MODO 2: AGRUPADO POR PROVINCIA -->
    <div x-show="modo === 'provincia'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($porProvincia as $p): 
            $af = $p['afirmativo'];
            $neg = $p['negativo'];
            $tot = $p['total'];
        ?>
        <div class="border border-slate-200 rounded-xl p-4 bg-white hover:border-blue-300 hover:shadow-sm transition-all space-y-2">
            <div class="flex items-center justify-between gap-2">
                <h3 class="font-bold text-slate-900 text-sm">
                    <?= e($p['provincia']) ?>
                </h3>
                <span class="font-mono text-xs text-slate-500">
                    <?= $tot ?> votos
                </span>
            </div>
            <div class="flex items-center gap-3 text-xs font-mono">
                <span class="text-emerald-700 font-bold">🟢 <?= $af ?></span>
                <span class="text-rose-700 font-bold">🔴 <?= $neg ?></span>
                <span class="text-amber-700">🟡 <?= $p['abstencion'] ?></span>
                <span class="text-slate-400">⚪ <?= $p['ausente'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- MODO 3: DESGLOSE POR SENTIDO DE VOTO (4 COLUMNAS) -->
    <div x-show="modo === 'desglose'" x-cloak class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Columna Afirmativo -->
        <div class="border border-emerald-200 bg-emerald-50/40 rounded-xl p-4 space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-emerald-200">
                <span class="font-bold text-xs uppercase tracking-wider text-emerald-900 flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    Afirmativo
                </span>
                <span class="font-mono text-xs font-extrabold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-full">
                    <?= $totales['afirmativo'] ?>
                </span>
            </div>
            <div class="space-y-2 max-h-[500px] overflow-y-auto pr-1">
                <?php foreach ($votosNominales as $vn): if ($vn['voto'] === 'Afirmativo'): ?>
                    <a href="<?= e($vn['url']) ?>" class="p-2 bg-white rounded-lg border border-emerald-100 hover:border-emerald-400 flex items-center justify-between text-xs transition-all group block">
                        <span class="font-semibold text-slate-900 group-hover:text-emerald-800 truncate"><?= e($vn['nombre']) ?></span>
                        <span class="text-[10px] text-slate-400 font-mono shrink-0 ml-1"><?= e($vn['provincia']) ?></span>
                    </a>
                <?php endif; endforeach; ?>
            </div>
        </div>

        <!-- Columna Negativo -->
        <div class="border border-rose-200 bg-rose-50/40 rounded-xl p-4 space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-rose-200">
                <span class="font-bold text-xs uppercase tracking-wider text-rose-900 flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-600"></span>
                    Negativo
                </span>
                <span class="font-mono text-xs font-extrabold text-rose-800 bg-rose-100 px-2 py-0.5 rounded-full">
                    <?= $totales['negativo'] ?>
                </span>
            </div>
            <div class="space-y-2 max-h-[500px] overflow-y-auto pr-1">
                <?php foreach ($votosNominales as $vn): if ($vn['voto'] === 'Negativo'): ?>
                    <a href="<?= e($vn['url']) ?>" class="p-2 bg-white rounded-lg border border-rose-100 hover:border-rose-400 flex items-center justify-between text-xs transition-all group block">
                        <span class="font-semibold text-slate-900 group-hover:text-rose-800 truncate"><?= e($vn['nombre']) ?></span>
                        <span class="text-[10px] text-slate-400 font-mono shrink-0 ml-1"><?= e($vn['provincia']) ?></span>
                    </a>
                <?php endif; endforeach; ?>
            </div>
        </div>

        <!-- Columna Abstención -->
        <div class="border border-amber-200 bg-amber-50/40 rounded-xl p-4 space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-amber-200">
                <span class="font-bold text-xs uppercase tracking-wider text-amber-900 flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    Abstención
                </span>
                <span class="font-mono text-xs font-extrabold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-full">
                    <?= $totales['abstencion'] ?>
                </span>
            </div>
            <div class="space-y-2 max-h-[500px] overflow-y-auto pr-1">
                <?php foreach ($votosNominales as $vn): if ($vn['voto'] === 'Abstención'): ?>
                    <a href="<?= e($vn['url']) ?>" class="p-2 bg-white rounded-lg border border-amber-100 hover:border-amber-400 flex items-center justify-between text-xs transition-all group block">
                        <span class="font-semibold text-slate-900 group-hover:text-amber-800 truncate"><?= e($vn['nombre']) ?></span>
                        <span class="text-[10px] text-slate-400 font-mono shrink-0 ml-1"><?= e($vn['provincia']) ?></span>
                    </a>
                <?php endif; endforeach; ?>
            </div>
        </div>

        <!-- Columna Ausente -->
        <div class="border border-slate-200 bg-slate-50 rounded-xl p-4 space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                <span class="font-bold text-xs uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                    Ausente
                </span>
                <span class="font-mono text-xs font-extrabold text-slate-700 bg-slate-200 px-2 py-0.5 rounded-full">
                    <?= $totales['ausente'] ?>
                </span>
            </div>
            <div class="space-y-2 max-h-[500px] overflow-y-auto pr-1">
                <?php foreach ($votosNominales as $vn): if ($vn['voto'] === 'Ausente'): ?>
                    <a href="<?= e($vn['url']) ?>" class="p-2 bg-white rounded-lg border border-slate-200 hover:border-slate-400 flex items-center justify-between text-xs transition-all group block">
                        <span class="font-semibold text-slate-900 group-hover:text-slate-800 truncate"><?= e($vn['nombre']) ?></span>
                        <span class="text-[10px] text-slate-400 font-mono shrink-0 ml-1"><?= e($vn['provincia']) ?></span>
                    </a>
                <?php endif; endforeach; ?>
            </div>
        </div>
    </div>

    <!-- MODO 4: PADRÓN NOMINAL COMPLETO (TABLA CON FILTROS EN TIEMPO REAL) -->
    <div x-show="modo === 'nominal'" x-cloak class="space-y-4">
        <div class="flex flex-col sm:flex-row items-center gap-3">
            <input type="text" x-model="busqueda" placeholder="Buscar por legislador, provincia o bloque..." class="w-full sm:max-w-xs px-3.5 py-2 text-xs border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            <div class="flex items-center gap-1 self-start sm:self-auto text-xs">
                <button type="button" @click="filtroVoto = 'todos'" :class="filtroVoto === 'todos' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700'" class="px-2.5 py-1 rounded-md font-medium">Todos</button>
                <button type="button" @click="filtroVoto = 'Afirmativo'" :class="filtroVoto === 'Afirmativo' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-800'" class="px-2.5 py-1 rounded-md font-medium">Afirmativos</button>
                <button type="button" @click="filtroVoto = 'Negativo'" :class="filtroVoto === 'Negativo' ? 'bg-rose-600 text-white' : 'bg-rose-50 text-rose-800'" class="px-2.5 py-1 rounded-md font-medium">Negativos</button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-mono uppercase tracking-wider">
                    <tr>
                        <th class="p-3">Legislador/a</th>
                        <th class="p-3">Cámara</th>
                        <th class="p-3">Provincia</th>
                        <th class="p-3">Bloque</th>
                        <th class="p-3 text-right">Voto Emitido</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php foreach ($votosNominales as $vn): 
                        $badgeClass = match ($vn['voto']) {
                            'Afirmativo' => 'bg-emerald-100 text-emerald-800 font-bold',
                            'Negativo'   => 'bg-rose-100 text-rose-800 font-bold',
                            'Abstención' => 'bg-amber-100 text-amber-800 font-bold',
                            default      => 'bg-slate-200 text-slate-600',
                        };
                    ?>
                    <tr x-show="(busqueda === '' || '<?= strtolower(addslashes($vn['nombre'] . ' ' . $vn['provincia'] . ' ' . $vn['bloque'])) ?>'.includes(busqueda.toLowerCase())) && (filtroVoto === 'todos' || filtroVoto === '<?= $vn['voto'] ?>')" class="hover:bg-slate-50/80 transition-colors">
                        <td class="p-3">
                            <a href="<?= e($vn['url']) ?>" class="font-bold text-slate-900 hover:text-blue-600 transition-colors flex items-center gap-2">
                                <span><?= e($vn['nombre']) ?></span>
                            </a>
                        </td>
                        <td class="p-3 text-slate-500 font-mono"><?= e($vn['camara']) ?></td>
                        <td class="p-3 text-slate-700"><?= e($vn['provincia']) ?></td>
                        <td class="p-3 text-slate-600 truncate max-w-xs"><?= e($vn['bloque']) ?></td>
                        <td class="p-3 text-right">
                            <span class="px-2 py-0.5 rounded text-[11px] <?= $badgeClass ?>">
                                <?= e($vn['voto']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Otras Leyes Relacionadas (Interlinking SEO) -->
<?php if (!empty($otrasLeyes)): ?>
<section class="bg-slate-50 rounded-2xl border border-slate-200 p-6 space-y-4">
    <div class="flex items-center justify-between pb-2 border-b border-slate-200">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700">
            Otras Leyes Clave Tratadas en el Congreso
        </h2>
        <a href="/leyes" class="text-xs font-semibold text-blue-600 hover:underline">Ver catálogo completo →</a>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <?php foreach ($otrasLeyes as $ol): ?>
            <a href="/ley/<?= e($ol['slug']) ?>" class="p-3 bg-white rounded-xl border border-slate-200 hover:border-blue-400 hover:shadow-sm transition-all group block space-y-1">
                <span class="text-[10px] font-mono font-bold text-slate-400"><?= e($ol['expediente']) ?></span>
                <h3 class="text-xs font-bold text-slate-900 group-hover:text-blue-700 truncate"><?= e($ol['titulo_corto']) ?></h3>
                <span class="text-[10px] text-blue-600 font-medium"><?= e($ol['eje_nombre']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
