<?php
/**
 * Hub Catálogo de Leyes Parlamentarias — DipuData (/leyes)
 * @var array $leyes
 * @var array $todos_ejes
 */

$totalLeyes = count($leyes);
$sancionadasCount = count(array_filter($leyes, fn($l) => str_contains(strtolower($l['estado_tramite'] ?? ''), 'sancionada')));
$vetadasCount = count(array_filter($leyes, fn($l) => str_contains(strtolower($l['estado_tramite'] ?? ''), 'vetad')));
$tramiteCount = count(array_filter($leyes, fn($l) => !str_contains(strtolower($l['estado_tramite'] ?? ''), 'sancionada') && !str_contains(strtolower($l['estado_tramite'] ?? ''), 'vetad')));
$penCount = count(array_filter($leyes, fn($l) => ($l['origen_iniciativa'] ?? '') === 'PEN'));
$legCount = $totalLeyes - $penCount;
?>

<!-- Migas de Pan (Breadcrumbs) -->
<nav class="text-sm text-slate-500 mb-6 flex flex-wrap items-center gap-1.5" aria-label="Migas de pan">
    <a href="/" class="hover:text-slate-900 transition-colors">Inicio</a>
    <span aria-hidden="true">/</span>
    <span class="text-slate-900 font-semibold">Leyes Tratadas</span>
</nav>

<!-- Hero / Encabezado del Catálogo -->
<section class="bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 text-white rounded-2xl shadow-xl p-6 sm:p-10 mb-8 relative overflow-hidden">
    <div class="absolute -right-16 -top-16 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="relative z-10 max-w-3xl">
        <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/10 backdrop-blur rounded-full text-indigo-300 text-xs font-semibold uppercase tracking-wider mb-4 border border-white/15">
            <span>🏛️ Congreso de la Nación Argentina</span>
            <span>•</span>
            <span>Inteligencia Legislativa</span>
        </div>
        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight mb-4 leading-tight">
            Archivo y Catálogo Cívico de <span class="text-indigo-400">Leyes Votadas</span>
        </h1>
        <p class="text-slate-300 text-base sm:text-lg leading-relaxed mb-6">
            Consultá el dossier completo de las votaciones nominales más trascendentes: de qué trata cada ley, quién la presentó, qué implica para la ciudadanía, por qué es clave y el desglose exacto de votos por bloque partidario y provincia.
        </p>

        <!-- Filtro Rápido en Vivo -->
        <div class="flex flex-col sm:flex-row gap-3 pt-2">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">🔍</span>
                <input type="text" id="filtro-leyes" placeholder="Buscar por título, expediente, autor o tema..."
                    class="w-full pl-10 pr-4 py-3 bg-white/10 hover:bg-white/15 focus:bg-white text-white focus:text-slate-900 rounded-xl border border-white/20 focus:border-indigo-500 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 transition-all">
            </div>
            <a href="/" class="inline-flex items-center justify-center gap-2 px-5 py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl transition-all shadow-md hover:shadow-indigo-500/25 shrink-0">
                <span>Explorar Legisladores</span>
                <span aria-hidden="true">→</span>
            </a>
        </div>
    </div>
</section>

<!-- Ticker de Pulso Parlamentario (Inspirado en DeQuéSeTrata) -->
<section class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs">
        <div class="flex items-center justify-between text-slate-400 text-xs uppercase font-mono tracking-wider">
            <span>Catálogo Cívico</span>
            <span>🏛️</span>
        </div>
        <div class="text-2xl sm:text-3xl font-mono font-black text-slate-900 mt-1">
            <?= $totalLeyes ?>
        </div>
        <p class="text-xs text-slate-500 mt-0.5">Leyes clave auditadas</p>
    </div>

    <div class="bg-white rounded-xl border border-emerald-200/80 bg-emerald-50/20 p-4 shadow-xs">
        <div class="flex items-center justify-between text-emerald-700 text-xs uppercase font-mono tracking-wider">
            <span>Sancionadas</span>
            <span>📜</span>
        </div>
        <div class="text-2xl sm:text-3xl font-mono font-black text-emerald-800 mt-1">
            <?= $sancionadasCount ?>
        </div>
        <p class="text-xs text-emerald-700 mt-0.5">Vigentes y en el BORA</p>
    </div>

    <div class="bg-white rounded-xl border border-blue-200/80 bg-blue-50/20 p-4 shadow-xs">
        <div class="flex items-center justify-between text-blue-700 text-xs uppercase font-mono tracking-wider">
            <span>En Trámite</span>
            <span>⏳</span>
        </div>
        <div class="text-2xl sm:text-3xl font-mono font-black text-blue-800 mt-1">
            <?= $tramiteCount ?>
        </div>
        <p class="text-xs text-blue-700 mt-0.5">Con dictamen o en debate</p>
    </div>

    <div class="bg-white rounded-xl border border-rose-200/80 bg-rose-50/20 p-4 shadow-xs">
        <div class="flex items-center justify-between text-rose-700 text-xs uppercase font-mono tracking-wider">
            <span>Vetadas / Tensión</span>
            <span>⚖️</span>
        </div>
        <div class="text-2xl sm:text-3xl font-mono font-black text-rose-800 mt-1">
            <?= $vetadasCount ?>
        </div>
        <p class="text-xs text-rose-700 mt-0.5">Veto presidencial total</p>
    </div>
</section>

<!-- Barra de Filtros: Origen de la Iniciativa y Ejes Temáticos -->
<div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs mb-8 space-y-4">
    <!-- Filtro por Origen de la Iniciativa (PEN vs Legislativa) -->
    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
        <div class="flex items-center gap-2">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Origen de la Ley:</span>
            <div class="flex flex-wrap gap-1.5" id="tags-origen">
                <button type="button" data-origen="todos"
                    class="btn-origen px-3 py-1 rounded-lg text-xs font-bold bg-slate-900 text-white shadow-xs transition-all">
                    Todas las iniciativas (<?= $totalLeyes ?>)
                </button>
                <button type="button" data-origen="PEN"
                    class="btn-origen px-3 py-1 rounded-lg text-xs font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 transition-all">
                    🦅 Agenda del Gobierno / PEN (<?= $penCount ?>)
                </button>
                <button type="button" data-origen="Legislativa"
                    class="btn-origen px-3 py-1 rounded-lg text-xs font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 transition-all">
                    📜 Iniciativa Parlamentaria Propia (<?= $legCount ?>)
                </button>
            </div>
        </div>
        <span id="contador-leyes" class="text-xs text-slate-500 font-mono font-semibold">Mostrando <?= $totalLeyes ?> leyes</span>
    </div>

    <!-- Filtro por Eje Temático -->
    <div>
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2.5">Filtrar por Eje Temático</h2>
        <div class="flex flex-wrap gap-1.5" id="tags-ejes">
            <button type="button" data-eje="todos"
                class="tag-filtro px-3 py-1 rounded-full text-xs font-bold bg-indigo-600 text-white shadow-xs border border-indigo-600 transition-all">
                Todos los temas
            </button>
            <?php
            $ejesConLeyes = [];
            foreach ($leyes as $l) {
                $ejeKey = $l['eje_slug'] ?? 'general';
                $ejesConLeyes[$ejeKey] = ($ejesConLeyes[$ejeKey] ?? 0) + 1;
            }
            $ejesNombres = [
                'apertura-comercial' => 'Apertura Comercial',
                'educacion'          => 'Educación',
                'salud-publica'      => 'Salud Pública',
                'prevision-social'   => 'Previsión Social',
                'medio-ambiente'     => 'Medio Ambiente',
                'transparencia'      => 'Transparencia',
                'federalismo'        => 'Federalismo',
                'seguridad'          => 'Seguridad',
                'industria-nacional' => 'Industria Nacional',
                'tecnologia'         => 'Tecnología',
            ];
            foreach ($ejesConLeyes as $slugEje => $cant):
                $nombre = $ejesNombres[$slugEje] ?? ucfirst(str_replace('-', ' ', $slugEje));
            ?>
                <button type="button" data-eje="<?= e($slugEje) ?>"
                    class="tag-filtro px-3 py-1 rounded-full text-xs font-medium bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-all">
                    <?= e($nombre) ?> (<?= $cant ?>)
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Grilla de Leyes -->
<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-16" id="grilla-leyes">
    <?php foreach ($leyes as $ley): 
        $origen = $ley['origen_iniciativa'] ?? 'Legislativa';
        $esPen = ($origen === 'PEN');
    ?>
        <article class="tarjeta-ley bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all flex flex-col overflow-hidden"
            data-eje="<?= e($ley['eje_slug'] ?? 'general') ?>"
            data-origen="<?= e($origen) ?>"
            data-texto="<?= strtolower(e($ley['titulo'] . ' ' . $ley['titulo_corto'] . ' ' . $ley['expediente'] . ' ' . $ley['quien_la_presento'] . ' ' . $ley['eje_nombre'])) ?>">
            
            <!-- Cabecera de la Tarjeta -->
            <div class="p-5 border-b border-slate-100 bg-slate-50/60">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold <?= $esPen ? 'bg-indigo-100 text-indigo-800 border border-indigo-200' : 'bg-slate-200 text-slate-800' ?>">
                            <?= $esPen ? '🦅 Agenda PEN' : '📜 Parlamentaria' ?>
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-white text-slate-600 border border-slate-200">
                            <?= e($ley['eje_nombre'] ?? 'General') ?>
                        </span>
                    </div>

                    <?php if (!empty($ley['boletin_oficial'])): ?>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                        <span>BORA</span>
                        <span aria-hidden="true">✓</span>
                    </span>
                    <?php endif; ?>
                </div>
                
                <h3 class="text-base sm:text-lg font-bold text-slate-900 leading-snug line-clamp-2">
                    <a href="/ley/<?= e($ley['slug']) ?>" class="hover:text-indigo-600 transition-colors">
                        <?= e($ley['titulo_corto']) ?>
                    </a>
                </h3>
                
                <div class="flex items-center justify-between text-xs text-slate-500 mt-2 font-mono">
                    <span><?= e($ley['expediente']) ?></span>
                    <span class="font-semibold text-slate-700"><?= e($ley['estado_tramite']) ?></span>
                </div>
            </div>

            <!-- Cuerpo del Dossier Cívico -->
            <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                <!-- Iniciador -->
                <div class="text-xs text-slate-600 bg-slate-50 rounded-lg p-2.5 border border-slate-100">
                    <span class="font-bold text-slate-800">Iniciativa:</span> <?= e($ley['quien_la_presento']) ?>
                </div>

                <!-- De qué trata -->
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">De qué trata</h4>
                    <p class="text-xs text-slate-700 leading-relaxed line-clamp-3">
                        <?= e($ley['resumen']) ?>
                    </p>
                </div>

                <!-- Qué implica -->
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Qué implica</h4>
                    <p class="text-xs text-slate-700 leading-relaxed line-clamp-2">
                        <?= e($ley['que_implica']) ?>
                    </p>
                </div>

                <!-- Botón de Acción -->
                <div class="pt-3 border-t border-slate-100 mt-auto flex items-center gap-2">
                    <a href="/ley/<?= e($ley['slug']) ?>"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-slate-900 hover:bg-indigo-600 text-white rounded-xl text-xs font-semibold transition-all shadow-xs">
                        <span>Ver Votación y Recorrido</span>
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<!-- Mensaje Sin Resultados -->
<div id="sin-resultados" class="hidden text-center py-16 bg-white rounded-2xl border border-dashed border-slate-300 mb-16">
    <div class="text-4xl mb-3">🔍</div>
    <h3 class="text-base font-bold text-slate-800 mb-1">No se encontraron leyes</h3>
    <p class="text-xs text-slate-500 max-w-md mx-auto mb-4">No hay leyes que coincidan con los criterios de búsqueda o los filtros seleccionados.</p>
    <button type="button" id="btn-limpiar-filtro" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 transition">
        Restablecer filtros
    </button>
</div>

<!-- Guía de Metodología Cívica -->
<section class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 mb-12 shadow-sm">
    <h2 class="text-xl font-bold text-slate-900 mb-4 flex items-center gap-2">
        <span>⚖️</span> Metodología de Auditoría Parlamentaria
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs text-slate-600 leading-relaxed">
        <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
            <h3 class="font-bold text-slate-900 mb-2 text-sm flex items-center gap-1.5">
                <span>🎯</span> Selección de Leyes Clave
            </h3>
            <p>
                Audita votaciones parlamentarias trascendentes que involucran impacto presupuestario, reformas de fondo, derechos civiles y tensiones entre intereses nacionales y provincias federales.
            </p>
        </div>
        <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
            <h3 class="font-bold text-slate-900 mb-2 text-sm flex items-center gap-1.5">
                <span>🤝</span> Índice de Lealtad Partidaria
            </h3>
            <p>
                Mide la coincidencia porcentual del voto individual respecto a la postura mayoritaria del bloque político del representante en este conjunto de leyes.
            </p>
        </div>
        <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
            <h3 class="font-bold text-slate-900 mb-2 text-sm flex items-center gap-1.5">
                <span>🌾</span> Índice de Defensa Provincial
            </h3>
            <p>
                Evalúa si el representante votó a favor de recursos, universidades y obras de su provincia o si priorizó la disciplina de bancada partidaria nacional.
            </p>
        </div>
    </div>
</section>

<!-- Script Interactivo de Filtrado Multidimensional -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const inputFiltro = document.getElementById('filtro-leyes');
    const tagsEjes = document.querySelectorAll('.tag-filtro');
    const btnsOrigen = document.querySelectorAll('.btn-origen');
    const tarjetas = document.querySelectorAll('.tarjeta-ley');
    const sinResultados = document.getElementById('sin-resultados');
    const contador = document.getElementById('contador-leyes');
    const btnLimpiar = document.getElementById('btn-limpiar-filtro');

    let ejeActivo = 'todos';
    let origenActivo = 'todos';
    let queryActivo = '';

    function filtrar() {
        let visibles = 0;
        tarjetas.forEach(card => {
            const eje = card.getAttribute('data-eje');
            const origen = card.getAttribute('data-origen');
            const texto = card.getAttribute('data-texto') || '';

            const coincideEje = (ejeActivo === 'todos' || eje === ejeActivo);
            const coincideOrigen = (origenActivo === 'todos' || origen === origenActivo);
            const coincideTexto = !queryActivo || texto.includes(queryActivo);

            if (coincideEje && coincideOrigen && coincideTexto) {
                card.style.display = '';
                visibles++;
            } else {
                card.style.display = 'none';
            }
        });

        if (contador) {
            contador.textContent = `Mostrando ${visibles} de <?= $totalLeyes ?> leyes`;
        }
        if (sinResultados) {
            sinResultados.classList.toggle('hidden', visibles > 0);
        }
    }

    if (inputFiltro) {
        inputFiltro.addEventListener('input', (e) => {
            queryActivo = e.target.value.toLowerCase().trim();
            filtrar();
        });
    }

    tagsEjes.forEach(tag => {
        tag.addEventListener('click', () => {
            tagsEjes.forEach(t => {
                t.className = 'tag-filtro px-3 py-1 rounded-full text-xs font-medium bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-all';
            });
            tag.className = 'tag-filtro px-3 py-1 rounded-full text-xs font-bold bg-indigo-600 text-white shadow-xs border border-indigo-600 transition-all';
            ejeActivo = tag.getAttribute('data-eje');
            filtrar();
        });
    });

    btnsOrigen.forEach(btn => {
        btn.addEventListener('click', () => {
            btnsOrigen.forEach(b => {
                b.className = 'btn-origen px-3 py-1 rounded-lg text-xs font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 transition-all';
            });
            btn.className = 'btn-origen px-3 py-1 rounded-lg text-xs font-bold bg-slate-900 text-white shadow-xs transition-all';
            origenActivo = btn.getAttribute('data-origen');
            filtrar();
        });
    });

    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', () => {
            if (inputFiltro) inputFiltro.value = '';
            queryActivo = '';
            ejeActivo = 'todos';
            origenActivo = 'todos';
            tagsEjes[0]?.click();
            btnsOrigen[0]?.click();
        });
    }
});
</script>
