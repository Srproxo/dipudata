<?php use App\Datos; ?>
<nav class="migas" aria-label="Ubicación"><a href="/congreso">Cómo funciona el Congreso</a> <span aria-hidden="true">/</span> <span>Provincias</span></nav>
<h1 class="titulo">Las provincias en el Congreso.</h1>
<p class="bajada">Cada provincia y la Ciudad Autónoma de Buenos Aires eligen tres senadores. La cantidad de diputados depende de la población de cada distrito, por eso varía mucho.</p>
<div class="tabla-caja"><table class="tabla">
  <thead><tr><th scope="col">Distrito</th><th scope="col" class="der">Diputados</th><th scope="col" class="der">Senadores</th></tr></thead>
  <tbody>
  <?php foreach (Datos::provincias() as $p): ?>
    <tr><td><a href="/congreso/provincias/<?= e($p['slug']) ?>"><?= e($p['nombre']) ?></a></td>
        <td class="der num" data-etiqueta="Diputados"><?= (int)$p['diputados'] ?></td>
        <td class="der num" data-etiqueta="Senadores"><?= (int)$p['senadores'] ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
