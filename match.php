<?php
declare(strict_types=1);

/**
 * Vista Match Electoral 360 — Formulario por pasos -> POST /api/match360 -> Radar Chart.js
 */

require_once __DIR__ . '/app/bootstrap.php';

use App\Controllers\MatchEngineController;

$preguntas = array_map(fn($p) => ['id' => $p['id'], 'eje' => $p['eje'], 'tipo' => $p['tipo'], 'texto' => $p['texto']], MatchEngineController::PREGUNTAS);
$opciones = MatchEngineController::OPCIONES;

$meta = [
    'title'       => 'Match Electoral 360 | ¿Qué legislador vota como vos pensás? | DipuData',
    'description' => 'Respondé 8 preguntas sobre salud, educación, industria y comercio. Comparamos tus respuestas con las votaciones reales del Congreso usando similitud del coseno.',
    'canonical'   => SITE_URL . '/match-360',
    'jsonld'      => [
        '@context' => 'https://schema.org',
        '@graph'   => [
            ['@type' => 'WebApplication', 'name' => 'Match Electoral 360', 'url' => SITE_URL . '/match-360', 'applicationCategory' => 'EducationalApplication', 'operatingSystem' => 'Web', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'ARS']],
            ['@type' => 'FAQPage', 'mainEntity' => [
                ['@type' => 'Question', 'name' => '¿Cómo se calcula la afinidad?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Tus respuestas generan un vector de 4 ejes (0 a 100). Se compara con el porcentaje de votos afirmativos de cada legislador en cada eje mediante similitud del coseno.']],
                ['@type' => 'Question', 'name' => '¿Se guardan mis respuestas?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'No. Las respuestas se procesan en el momento y no se almacenan ni se asocian a tu identidad.']],
            ]],
        ],
    ],
];

send_view_headers();
require __DIR__ . '/app/Views/layouts/header.php';
?>
<div class="max-w-2xl mx-auto" id="wizard">
    <div class="h-1 bg-slate-200 rounded-full overflow-hidden" aria-hidden="true">
        <div id="barra" class="h-full bg-blue-600 transition-all duration-300" style="width:0%"></div>
    </div>
    <p id="contador" class="font-mono text-xs text-slate-500 mt-3" aria-live="polite"></p>

    <form id="form-match" class="mt-8" novalidate>
        <?php foreach ($preguntas as $i => $p): ?>
        <fieldset class="paso <?= $i === 0 ? '' : 'hidden' ?>" data-paso="<?= $i ?>">
            <legend class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                <?= e($p['eje']) ?> · <?= $p['tipo'] === 'material' ? 'Tu bolsillo' : 'Tus valores' ?>
            </legend>
            <p class="text-2xl sm:text-3xl font-bold tracking-tight leading-snug mt-3"><?= e($p['texto']) ?></p>
            <div class="mt-8 grid gap-3">
                <?php foreach ($opciones as $valor => $label): ?>
                <label class="opcion block w-full cursor-pointer rounded-xl border border-slate-200 bg-white px-5 py-4 text-left font-medium transition hover:border-slate-400 has-[:checked]:ring-2 has-[:checked]:ring-blue-500 has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-blue-300">
                    <input type="radio" class="sr-only" name="<?= e($p['id']) ?>" value="<?= $valor ?>" required>
                    <?= e($label) ?>
                </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php endforeach; ?>

        <div class="mt-8 flex justify-between">
            <button type="button" id="btn-atras" class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 invisible">← Atrás</button>
            <p id="error" class="text-sm text-rose-700 self-center" role="alert"></p>
        </div>
    </form>
</div>

<section id="resultado" class="hidden" aria-live="polite">
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-xl font-bold tracking-tight">Tu perfil vs. tu mejor coincidencia</h2>
            <p id="leyenda" class="text-sm text-slate-500 mt-1"></p>
            <div class="relative mt-4 mx-auto w-full max-w-md aspect-square">
                <canvas id="radar" role="img" aria-label="Gráfico radar comparando tu perfil con el del legislador sugerido"></canvas>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-xl font-bold tracking-tight">Tus 3 mayores afinidades</h2>
            <ol id="top3" class="mt-4 space-y-3"></ol>
            <button type="button" id="btn-reiniciar" class="mt-6 w-full rounded-lg border border-slate-300 py-2.5 text-sm font-semibold hover:bg-slate-50">Volver a empezar</button>
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
    const error = document.getElementById('error');
    let actual = 0;
    let grafico = null;

    const mostrar = (i) => {
        pasos.forEach((p, idx) => p.classList.toggle('hidden', idx !== i));
        barra.style.width = `${(i / total) * 100}%`;
        contador.textContent = `Pregunta ${i + 1} de ${total}`;
        atras.classList.toggle('invisible', i === 0);
        pasos[i].querySelector('input:checked, input')?.focus({ preventScroll: true });
    };

    form.addEventListener('change', (ev) => {
        if (ev.target.type !== 'radio') return;
        error.textContent = '';
        setTimeout(() => {
            if (actual < total - 1) { actual++; mostrar(actual); }
            else { enviar(); }
        }, 180);
    });

    atras.addEventListener('click', () => { if (actual > 0) { actual--; mostrar(actual); } });

    async function enviar() {
        barra.style.width = '100%';
        contador.textContent = 'Calculando afinidad…';
        const respuestas = Object.fromEntries([...new FormData(form)].map(([k, v]) => [k, Number(v)]));
        try {
            const res = await fetch('/api/match360', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ respuestas })
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'ok') throw new Error(data.message || 'Error al calcular');
            renderResultado(data);
        } catch (e) {
            error.textContent = e.message;
            contador.textContent = `Pregunta ${total} de ${total}`;
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
        const mejor = data.top3[0];
        document.getElementById('leyenda').textContent = mejor
            ? `Comparado con ${mejor.nombre} (${mejor.afinidad.toFixed(1)}% de afinidad)` : 'Sin datos de legisladores';

        const lista = document.getElementById('top3');
        lista.replaceChildren();
        data.top3.forEach((l, i) => {
            const li = el('li', 'flex items-center gap-4 rounded-xl border border-slate-200 p-4');
            li.append(el('span', 'font-mono text-sm text-slate-400 w-5', String(i + 1)));
            const info = el('div', 'min-w-0 flex-1');
            const a = el('a', 'font-semibold hover:underline', l.nombre);
            a.href = l.url;
            info.append(a, el('p', 'text-sm text-slate-500 truncate', `${l.bloque} · ${l.provincia}`));
            li.append(info, el('span', 'font-mono text-lg font-semibold text-blue-700', `${l.afinidad.toFixed(1)}%`));
            lista.append(li);
        });

        const ejes = data.ejes;
        if (grafico) grafico.destroy();
        grafico = new Chart(document.getElementById('radar'), {
            type: 'radar',
            data: {
                labels: ejes,
                datasets: [
                    { label: 'Vos', data: ejes.map(e => data.vector_usuario[e]), borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.25)', pointBackgroundColor: '#2563eb', borderWidth: 2 },
                    ...(mejor ? [{ label: mejor.nombre, data: ejes.map(e => mejor.vector[e]), borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.2)', pointBackgroundColor: '#f59e0b', borderWidth: 2, borderDash: [5, 4] }] : [])
                ]
            },
            options: {
                maintainAspectRatio: false,
                scales: { r: { min: 0, max: 100, ticks: { stepSize: 25, backdropColor: 'transparent', font: { family: 'JetBrains Mono', size: 10 } }, grid: { color: '#e2e8f0' }, angleLines: { color: '#e2e8f0' }, pointLabels: { font: { family: 'Inter', size: 12, weight: '600' } } } },
                plugins: { legend: { position: 'bottom', labels: { font: { family: 'Inter' } } }, tooltip: { callbacks: { label: c => `${c.dataset.label}: ${c.formattedValue}%` } } }
            }
        });
        document.getElementById('resultado').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    document.getElementById('btn-reiniciar').addEventListener('click', () => {
        form.reset(); actual = 0;
        document.getElementById('resultado').classList.add('hidden');
        document.getElementById('wizard').classList.remove('hidden');
        mostrar(0);
    });

    mostrar(0);
})();
</script>
<?php require __DIR__ . '/app/Views/layouts/footer.php'; ?>
