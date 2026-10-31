<?php
declare(strict_types=1);

/**
 * Vista Match Electoral 360 (Fase 2) — Cuestionario Dinámico de N Ejes -> POST /api/match360 -> Radar Chart.js (11 vértices)
 */

require_once __DIR__ . '/app/bootstrap.php';

use App\Controllers\MatchEngineController;

$preguntas = array_map(fn($p) => [
    'id'        => $p['id'],
    'eje'       => $p['eje'],
    'tipo'      => $p['tipo'],
    'texto'     => $p['texto'],
    'invertida' => $p['invertida'],
], MatchEngineController::PREGUNTAS);

$opciones = MatchEngineController::OPCIONES;

$meta = [
    'title'       => 'Match Electoral 360 | ¿Qué legislador vota como vos pensás? (11 Ejes Temáticos) | DipuData',
    'description' => 'Respondé sobre jubilaciones, seguridad, medio ambiente, federalismo, educación, salud y economía. Calculamos tu afinidad con diputados y senadores mediante similitud del coseno sobre datos reales de votación.',
    'canonical'   => SITE_URL . '/match-360',
    'jsonld'      => [
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type'               => 'WebApplication',
                'name'                => 'Match Electoral 360 (Fase 2)',
                'url'                 => SITE_URL . '/match-360',
                'applicationCategory' => 'EducationalApplication',
                'operatingSystem'     => 'Web',
                'offers'              => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'ARS'],
            ],
            [
                '@type'      => 'FAQPage',
                'mainEntity' => [
                    [
                        '@type'          => 'Question',
                        'name'           => '¿Cómo funciona la Similitud del Coseno en 11 ejes?',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text'  => 'Tus respuestas generan un vector en un espacio vectorial de 11 dimensiones temáticas. Se compara el ángulo entre tu vector y el historial empírico de votaciones afirmativas de cada legislador.',
                        ],
                    ],
                    [
                        '@type'          => 'Question',
                        'name'           => '¿Puedo saltar preguntas sobre temas que no me interesan?',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text'  => 'Sí. Podés elegir "Saltar tema" o valor 0. El algoritmo proyecta la afinidad sobre los ejes donde manifestaste postura sin penalizar a los legisladores en los temas que decidiste omitir.',
                        ],
                    ],
                ],
            ],
        ],
    ],
];

send_view_headers();
require __DIR__ . '/app/Views/layouts/header.php';
?>
<div class="max-w-3xl mx-auto" id="wizard">
    <!-- Barra de progreso superior -->
    <div class="flex items-center justify-between text-xs text-slate-500 mb-2">
        <span class="font-semibold uppercase tracking-wider text-blue-700">Matriz Cívica 360 · 11 Ejes Temáticos</span>
        <span id="contador" class="font-mono" aria-live="polite">Pregunta 1 de <?= count($preguntas) ?></span>
    </div>
    <div class="h-1.5 bg-slate-200 rounded-full overflow-hidden" aria-hidden="true">
        <div id="barra" class="h-full bg-blue-600 transition-all duration-300" style="width:0%"></div>
    </div>

    <form id="form-match" class="mt-8" novalidate>
        <?php foreach ($preguntas as $i => $p): ?>
        <fieldset class="paso <?= $i === 0 ? '' : 'hidden' ?>" data-paso="<?= $i ?>" data-eje="<?= e($p['eje']) ?>">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-blue-100 text-blue-800 px-3 py-0.5 text-xs font-semibold">
                    <?= e($p['eje']) ?>
                </span>
                <span class="text-xs text-slate-500">
                    · <?= $p['tipo'] === 'material' ? 'Impacto material / Bolsillo' : 'Valores / Ideología' ?>
                </span>
            </div>

            <p class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-snug mt-4 text-slate-900">
                <?= e($p['texto']) ?>
            </p>

            <div class="mt-8 grid gap-2.5">
                <?php foreach ($opciones as $valor => $label): ?>
                <label class="opcion block w-full cursor-pointer rounded-xl border <?= $valor === 0 ? 'border-dashed border-slate-300 bg-slate-50/70' : 'border-slate-200 bg-white' ?> px-5 py-3.5 text-left font-medium transition hover:border-slate-400 has-[:checked]:ring-2 has-[:checked]:ring-blue-500 has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-blue-300">
                    <input type="radio" class="sr-only" name="<?= e($p['id']) ?>" value="<?= $valor ?>" required>
                    <div class="flex items-center justify-between">
                        <span class="<?= $valor === 0 ? 'text-slate-500 text-sm italic' : 'text-slate-800 text-base' ?>"><?= e($label) ?></span>
                        <span class="font-mono text-xs text-slate-400">
                            <?= $valor === 0 ? 'Ø' : ($valor === 3 ? '50%' : ($valor === 5 ? '100%' : ($valor === 1 ? '0%' : ''))) ?>
                        </span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php endforeach; ?>

        <div class="mt-8 pt-4 border-t border-slate-200 flex items-center justify-between">
            <button type="button" id="btn-atras" class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 invisible">
                ← Pregunta anterior
            </button>
            <div class="flex items-center gap-3">
                <button type="button" id="btn-saltar" class="text-xs font-semibold text-slate-500 hover:text-slate-800 underline underline-offset-4">
                    Saltar este tema →
                </button>
                <p id="error" class="text-sm text-rose-700" role="alert"></p>
            </div>
        </div>
    </form>
</div>

<!-- Vista de Resultados Split-Screen con Radar Chart.js de 11 vértices -->
<section id="resultado" class="hidden" aria-live="polite">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Tu Radiografía Electoral 360</h1>
            <p id="subtitulo-match" class="text-sm text-slate-500 mt-1">
                Polígono de afinidad multidimensional basado en <?= count($preguntas) ?> indicadores cívicos.
            </p>
        </div>
        <button type="button" id="btn-reiniciar" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-sm">
            ↺ Rehacer Cuestionario
        </button>
    </div>

    <div class="grid gap-6 lg:grid-cols-12 items-start">
        <!-- Radar Chart de 11 vértices -->
        <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold tracking-tight">Polígono Ideológico (11 Ejes)</h2>
                <span id="badge-top-score" class="rounded-full bg-emerald-100 text-emerald-800 font-mono text-xs px-2.5 py-0.5 font-semibold"></span>
            </div>
            <p id="leyenda" class="text-xs text-slate-500 mt-1"></p>

            <div class="relative mt-4 mx-auto w-full max-w-lg aspect-square">
                <canvas id="radar" role="img" aria-label="Gráfico de radar que dibuja un polígono de 11 vértices comparando tu perfil de votante con el del legislador con mayor coincidencia"></canvas>
            </div>

            <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap justify-center gap-4 text-xs">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-blue-600 inline-block"></span>
                    <span class="font-medium">Tu perfil como votante</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-amber-500 inline-block"></span>
                    <span class="font-medium" id="label-top-leg">Mayor coincidencia</span>
                </div>
            </div>
        </div>

        <!-- Ranking Top 3 con Similitud del Coseno -->
        <div class="lg:col-span-5 space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-lg font-bold tracking-tight">Tus 3 Mayores Afinidades</h2>
                <p class="text-xs text-slate-500 mt-0.5">Calculado por Similitud del Coseno vectorial sobre votaciones reales.</p>
                <ol id="top3" class="mt-4 space-y-3"></ol>
            </div>

            <!-- Desglose de tu vector por eje -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-600">Tu huella en los 11 ejes</h3>
                <div id="desglose-ejes" class="mt-4 space-y-2 text-xs"></div>
            </div>
        </div>
    </div>
</section>

<script nonce="<?= e(csp_nonce()) ?>" src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script nonce="<?= e(csp_nonce()) ?>">
(() => {
    const pasos = [...document.querySelectorAll('.paso')];
    const total = pasos.length;
    const form = document.getElementById('form-match');
    const barra = document.getElementById('barra');
    const contador = document.getElementById('contador');
    const atras = document.getElementById('btn-atras');
    const btnSaltar = document.getElementById('btn-saltar');
    const error = document.getElementById('error');
    let actual = 0;
    let grafico = null;

    const mostrar = (i) => {
        pasos.forEach((p, idx) => p.classList.toggle('hidden', idx !== i));
        barra.style.width = `${((i) / total) * 100}%`;
        contador.textContent = `Pregunta ${i + 1} de ${total}`;
        atras.classList.toggle('invisible', i === 0);
        const radioMarcado = pasos[i].querySelector('input:checked');
        if (!radioMarcado) {
            pasos[i].querySelector('input')?.focus({ preventScroll: true });
        }
    };

    const avanzar = () => {
        if (actual < total - 1) {
            actual++;
            mostrar(actual);
        } else {
            enviar();
        }
    };

    form.addEventListener('change', (ev) => {
        if (ev.target.type !== 'radio') return;
        error.textContent = '';
        setTimeout(avanzar, 180);
    });

    atras.addEventListener('click', () => {
        if (actual > 0) {
            actual--;
            mostrar(actual);
        }
    });

    btnSaltar.addEventListener('click', () => {
        const inputActual = pasos[actual].querySelector('input[type="radio"][value="0"]');
        if (inputActual) {
            inputActual.checked = true;
        }
        avanzar();
    });

    async function enviar() {
        barra.style.width = '100%';
        contador.textContent = 'Calculando afinidad vectorial...';
        const formData = new FormData(form);
        const respuestas = {};
        for (const [k, v] of formData.entries()) {
            respuestas[k] = Number(v);
        }

        try {
            const res = await fetch('/api/match360', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ respuestas })
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'ok') {
                throw new Error(data.message || 'Error en el cálculo');
            }
            renderResultado(data);
        } catch (e) {
            error.textContent = e.message;
            contador.textContent = `Pregunta ${actual + 1} de ${total}`;
        }
    }

    function el(tag, cls, text) {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined) n.textContent = text;
        return n;
    }

    function renderResultado(data) {
        document.getElementById('wizard').classList.add('hidden');
        document.getElementById('resultado').classList.remove('hidden');

        const mejor = data.top3 && data.top3[0] ? data.top3[0] : null;
        if (mejor) {
            document.getElementById('badge-top-score').textContent = `${mejor.afinidad.toFixed(1)}% Coincidencia`;
            document.getElementById('leyenda').textContent = `Comparando tu perfil con ${mejor.nombre} (${mejor.bloque} - ${mejor.provincia})`;
            document.getElementById('label-top-leg').textContent = mejor.nombre;
        }

        // Renderizar Top 3
        const lista = document.getElementById('top3');
        lista.replaceChildren();
        (data.top3 || []).forEach((l, i) => {
            const li = el('li', 'flex items-center gap-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4 hover:border-slate-300 transition');
            li.append(el('span', 'font-mono text-base font-bold text-slate-400 w-5 text-center', String(i + 1)));
            const info = el('div', 'min-w-0 flex-1');
            const a = el('a', 'font-bold text-slate-900 hover:text-blue-700 hover:underline', l.nombre);
            a.href = l.url;
            info.append(a, el('p', 'text-xs text-slate-500 truncate mt-0.5', `${l.camara} · ${l.bloque} (${l.provincia})`));
            li.append(info, el('span', 'font-mono text-lg font-bold text-blue-700', `${l.afinidad.toFixed(1)}%`));
            lista.append(li);
        });

        // Renderizar desglose de 11 ejes
        const desglose = document.getElementById('desglose-ejes');
        desglose.replaceChildren();
        const ejes = data.ejes || Object.keys(data.vector_usuario || {});
        ejes.forEach((eje) => {
            const val = data.vector_usuario[eje] ?? 0;
            const fila = el('div', 'flex items-center justify-between py-1 border-b border-slate-100 last:border-0');
            fila.append(el('span', 'text-slate-600 truncate max-w-[200px]', eje));
            const valSpan = el('span', 'font-mono font-semibold ' + (val > 0 ? 'text-blue-700' : 'text-slate-400'), val > 0 ? `${val.toFixed(0)}%` : 'Sin postura');
            fila.append(valSpan);
            desglose.append(fila);
        });

        // Configuración dinámica del Radar Chart de 11 vértices en Chart.js
        if (grafico) {
            grafico.destroy();
        }

        const ctx = document.getElementById('radar').getContext('2d');
        const userVals = ejes.map(e => data.vector_usuario[e] ?? 0);
        const legVals = mejor ? ejes.map(e => mejor.vector[e] ?? 0) : [];

        grafico = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: ejes,
                datasets: [
                    {
                        label: 'Tu perfil como votante',
                        data: userVals,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.25)',
                        pointBackgroundColor: '#2563eb',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#2563eb',
                        borderWidth: 2.5,
                        pointRadius: 3,
                    },
                    ...(mejor ? [{
                        label: mejor.nombre,
                        data: legVals,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.20)',
                        pointBackgroundColor: '#f59e0b',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#f59e0b',
                        borderWidth: 2,
                        borderDash: [5, 4],
                        pointRadius: 3,
                    }] : [])
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        min: 0,
                        max: 100,
                        ticks: {
                            stepSize: 25,
                            backdropColor: 'transparent',
                            font: { family: 'JetBrains Mono', size: 9 },
                            color: '#94a3b8'
                        },
                        grid: { color: '#e2e8f0' },
                        angleLines: { color: '#e2e8f0' },
                        pointLabels: {
                            font: { family: 'Inter', size: 10, weight: '600' },
                            color: '#334155'
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (c) => `${c.dataset.label}: ${c.formattedValue}%`
                        }
                    }
                }
            }
        });

        document.getElementById('resultado').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    document.getElementById('btn-reiniciar').addEventListener('click', () => {
        form.reset();
        actual = 0;
        document.getElementById('resultado').classList.add('hidden');
        document.getElementById('wizard').classList.remove('hidden');
        mostrar(0);
    });

    mostrar(0);
})();
</script>
<?php require __DIR__ . '/app/Views/layouts/footer.php'; ?>
