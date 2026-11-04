<?php /** @var array<string,array{0:string,1:string,2:string}> $terminos */
uasort($terminos, static fn($a, $b) => strcmp(\App\Datos::normalizar($a[0]), \App\Datos::normalizar($b[0])));
?>
<p class="antetitulo">Para leer sin vueltas</p>
<h1 class="titulo">Glosario.</h1>
<p class="bajada">Qué quiere decir cada palabra que aparece en DipuData, explicada en simple. Si una palabra no está acá y te quedó una duda, avisanos.</p>

<nav class="glosario-indice" aria-label="Términos">
  <ul>
    <?php foreach ($terminos as $k => [$t]): ?><li><a href="#<?= e($k) ?>"><?= e($t) ?></a></li><?php endforeach; ?>
  </ul>
</nav>

<dl class="glosario">
  <?php foreach ($terminos as $k => [$t, $d, $url]): ?>
  <div id="<?= e($k) ?>" class="glosario-item">
    <dt><?= e($t) ?></dt>
    <dd><?= e($d) ?><?php if ($url !== '/'): ?> <a href="<?= e($url) ?>">Verlo en el sitio</a>.<?php endif; ?></dd>
  </div>
  <?php endforeach; ?>
</dl>
<p class="fuente-pie">Las definiciones explican cómo se usa cada término en este sitio. Para el detalle de cada dato y su fuente, mirá <a href="/metodologia">cómo trabajamos</a>.</p>
