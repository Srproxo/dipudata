<?php /** @var array $secciones */ ?>
<p class="antetitulo">Cómo funciona el Congreso</p>
<h1 class="titulo">El Congreso, explicado sin vueltas.</h1>
<p class="bajada">Qué hace cada cámara, cómo se arma una ley y quiénes integran hoy cada bloque y cada delegación provincial. Los textos se basan en la Constitución Nacional; las cifras salen de los registros oficiales.</p>

<ul class="indice">
  <?php foreach ($secciones as $slug => $s): ?>
    <li><a href="/congreso/<?= e($slug) ?>"><span class="indice-t"><?= e($s['titulo']) ?></span><span class="indice-d"><?= e($s['resumen']) ?></span></a></li>
  <?php endforeach; ?>
  <li><a href="/congreso/bloques"><span class="indice-t">Los bloques</span><span class="indice-d">Quién integra cada bloque en Diputados y en el Senado.</span></a></li>
  <li><a href="/congreso/provincias"><span class="indice-t">Las provincias</span><span class="indice-d">Cuántos legisladores tiene cada distrito y quiénes son.</span></a></li>
</ul>
