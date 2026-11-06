<?php /** @var list<array> $notas */ ?>
<p class="antetitulo">Análisis con datos oficiales</p>
<h1 class="titulo">Notas.</h1>
<p class="bajada">Análisis sobre lo que pasa en el Congreso, escritos con los mismos datos oficiales que publica el sitio. Cada nota dice de dónde sale cada cifra, cómo se calculó y qué diarios cita.</p>
<?php if (!$notas): ?>
  <p class="vacio">Todavía no hay notas publicadas.</p>
<?php else: ?>
<ul class="indice">
  <?php foreach ($notas as $n): ?>
  <li><a href="/notas/<?= e($n['slug']) ?>">
    <span class="antetitulo nota-antetitulo"><?= e($n['antetitulo']) ?> · <time datetime="<?= e($n['fecha']) ?>"><?= e(fecha_es($n['fecha'])) ?></time></span>
    <span class="indice-t"><?= e($n['titulo']) ?></span>
    <span class="indice-d"><?= e(nota_texto($n['bajada'])) ?></span>
  </a></li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
<p class="fuente-pie">Las notas no califican a nadie: muestran la métrica, cómo se calcula y con qué se compara. Si encontrás un error, <a href="/correccion">pedí una corrección</a>. Cómo se obtiene cada dato: <a href="/metodologia">cómo trabajamos</a>.</p>
