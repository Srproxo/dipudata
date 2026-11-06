<?php /** @var array $n */ ?>
<nav class="migas" aria-label="Ubicación"><a href="/notas">Notas</a> <span aria-hidden="true">/</span> <span><?= e($n['antetitulo']) ?></span></nav>
<div class="articulo-grilla">
  <article class="articulo nota">
    <h1 class="titulo"><?= e($n['titulo']) ?></h1>
    <p class="bajada"><?= nota_inline($n['bajada']) ?></p>
    <p class="nota-fecha">Publicada el <time datetime="<?= e($n['fecha']) ?>"><?= e(fecha_es($n['fecha'])) ?></time> · Datos al <?= e(fecha_es($n['datos_al'])) ?></p>
    <?php foreach ($n['cuerpo'] as $b): ?>
      <?php if (isset($b['h2'])): ?>
        <h2><?= e($b['h2']) ?></h2>
      <?php elseif (isset($b['p'])): ?>
        <p><?= nota_inline($b['p']) ?></p>
      <?php elseif (isset($b['lista'])): ?>
        <ul><?php foreach ($b['lista'] as $li): ?><li><?= nota_inline($li) ?></li><?php endforeach; ?></ul>
      <?php elseif (isset($b['tabla'])): $t = $b['tabla']; $der = $t['der'] ?? []; ?>
        <div class="tabla-caja"><table class="tabla tabla-nota">
          <thead><tr><?php foreach ($t['cab'] as $i => $c): ?><th scope="col"<?= in_array($i, $der, true) ? ' class="der"' : '' ?>><?= e($c) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
          <?php foreach ($t['filas'] as $fila): ?>
            <tr><?php foreach ($fila as $i => $celda): ?><td<?= in_array($i, $der, true) ? ' class="der num"' : '' ?><?= $i ? ' data-etiqueta="' . e($t['cab'][$i]) . '"' : '' ?>><?= nota_inline((string)$celda) ?></td><?php endforeach; ?></tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    <?php endforeach; ?>

    <section class="nota-fuentes" aria-labelledby="nota-fuentes-t">
      <h2 id="nota-fuentes-t">De dónde salen estos datos</h2>
      <ul>
        <?php foreach ($n['fuentes'] as $f): ?>
          <li><a href="<?= e($f['url']) ?>"><?= e($f['nombre']) ?></a>. <?= e($f['detalle']) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php if (!empty($n['metodo'])): ?><p><strong>Cómo se calculó.</strong> <?= nota_inline($n['metodo']) ?></p><?php endif; ?>
      <?php if (!empty($n['prensa'])): ?>
        <h3 class="sub">Notas de prensa citadas</h3>
        <ul>
          <?php foreach ($n['prensa'] as $pr): ?>
            <li><?= e($pr['medio']) ?>, «<a href="<?= e($pr['url']) ?>" rel="noopener"><?= e($pr['titulo']) ?></a>», <?= e(fecha_es($pr['fecha'])) ?>.</li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <p class="fuente-pie">DipuData no califica a ningún legislador: muestra los datos, cómo se calculan y con qué se comparan. ¿Encontraste un error? <a href="/correccion">Pedí una corrección</a>. Licencia Creative Commons Atribución 4.0: citá «DipuData (dipudata.com)» y esta página.</p>
  </article>
  <aside class="lateral" aria-label="Más notas">
    <p class="lateral-t">Notas</p>
    <ul>
      <?php foreach (notas() as $o): ?>
        <li><a href="/notas/<?= e($o['slug']) ?>"<?= $o['slug'] === $n['slug'] ? ' aria-current="page"' : '' ?>><?= e($o['titulo']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </aside>
</div>
