<?php
use App\Datos;
/** @var array $s @var string $slug @var array $secciones */
?>
<nav class="migas" aria-label="Ubicación"><a href="/congreso">Cómo funciona el Congreso</a> <span aria-hidden="true">/</span> <span><?= e($s['titulo']) ?></span></nav>
<div class="articulo-grilla">
  <article class="articulo">
    <h1 class="titulo"><?= e($s['titulo']) ?></h1>
    <p class="bajada"><?= e($s['bajada']) ?></p>
    <?php foreach ($s['partes'] as [$h, $parrafos]): ?>
      <h2><?= e($h) ?></h2>
      <?php foreach ($parrafos as $p): ?><p><?= e($p) ?></p><?php endforeach; ?>
      <?php if (($s['datos'] ?? '') && $h !== '' && str_starts_with($h, 'Quiénes')):
        $camara = $s['datos'] === 'senado' ? 'Senadores' : 'Diputados';
        $bl = Datos::bloques($camara); $tot = array_sum(array_column($bl, 'cantidad')); ?>
        <p>Hoy la cámara tiene <strong class="num"><?= (int)$tot ?></strong> integrantes repartidos en <strong class="num"><?= count($bl) ?></strong> bloques.</p>
        <?= barra_composicion($bl) ?>
        <table class="tabla tabla-chica"><thead><tr><th scope="col">Bloque</th><th scope="col" class="der">Bancas</th></tr></thead><tbody>
          <?php foreach ($bl as $b): ?><tr><td><i class="punto" style="background:<?= e(color_bloque($b['nombre_oficial'])) ?>"></i><a href="<?= e(Datos::urlBloque($b)) ?>"><?= e($b['nombre']) ?></a></td><td class="der num"><?= (int)$b['cantidad'] ?></td></tr><?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if (!empty($s['fuentes'])): ?>
      <p class="fuente-pie">Fuente: <?php foreach ($s['fuentes'] as $i => [$t, $u]): ?><?= $i ? ' · ' : '' ?><a href="<?= e($u) ?>" rel="noopener"><?= e($t) ?></a><?php endforeach; ?></p>
    <?php endif; ?>
  </article>
  <aside class="lateral" aria-label="Más secciones">
    <p class="lateral-t">En esta guía</p>
    <ul>
      <?php foreach ($secciones as $k => $x): ?><li><a href="/congreso/<?= e($k) ?>"<?= $k === $slug ? ' aria-current="page"' : '' ?>><?= e($x['titulo']) ?></a></li><?php endforeach; ?>
      <li><a href="/congreso/bloques">Los bloques</a></li>
      <li><a href="/congreso/provincias">Las provincias</a></li>
    </ul>
  </aside>
</div>
