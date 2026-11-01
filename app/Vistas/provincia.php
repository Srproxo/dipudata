<?php
use App\Datos;
/** @var array $p @var list<array> $dip @var list<array> $sen */
?>
<nav class="migas" aria-label="Ubicación"><a href="/congreso/provincias">Provincias</a> <span aria-hidden="true">/</span> <span><?= e($p['nombre']) ?></span></nav>
<p class="antetitulo">Delegación en el Congreso</p>
<h1 class="titulo"><?= e($p['nombre']) ?></h1>
<p class="bajada"><?= count($dip) ?> <?= count($dip) === 1 ? 'diputado' : 'diputados' ?> y <?= count($sen) ?> <?= count($sen) === 1 ? 'senador' : 'senadores' ?> en ejercicio.</p>
<?php foreach (['Senado' => $sen, 'Diputados' => $dip] as $titulo => $lista): if (!$lista) { continue; } ?>
<section class="seccion" aria-labelledby="s-<?= e(strtolower($titulo)) ?>">
  <h2 id="s-<?= e(strtolower($titulo)) ?>" class="seccion-titulo"><?= e($titulo) ?></h2>
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col">Legislador</th><th scope="col">Bloque</th></tr></thead>
    <tbody>
    <?php foreach ($lista as $l): ?>
      <tr><td class="celda-nombre"><?= avatar($l, 32) ?><a href="<?= e(Datos::urlLegislador($l)) ?>"><?= e($l['nombre']) ?></a></td><td data-etiqueta="Bloque"><?= etiqueta_bloque($l) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endforeach; ?>
