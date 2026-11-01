<?php use App\Datos; /** @var array $c */
$porBloque = []; foreach ($c['integrantes'] as $m) { $porBloque[$m['bloque']] = ($porBloque[$m['bloque']] ?? 0) + 1; } arsort($porBloque); ?>
<nav class="migas" aria-label="Ubicación"><a href="/comisiones">Comisiones</a> <span aria-hidden="true">/</span> <span>Diputados</span></nav>
<p class="antetitulo">Comisión <?= e(strtolower($c['tipo'])) ?> · Cámara de Diputados</p>
<h1 class="titulo" style="max-width:30ch;font-size:clamp(1.6rem,3.6vw,2.4rem)"><?= e($c['nombre']) ?></h1>
<p class="bajada"><?= count($c['integrantes']) ?> integrantes. <?= e(implode(', ', array_map(static fn($b, $n) => "$b: $n", array_keys(array_slice($porBloque, 0, 4, true)), array_slice($porBloque, 0, 4, true)))) ?><?= count($porBloque) > 4 ? ' y otros' : '' ?>.</p>
<div class="tabla-caja" style="margin-top:1.5rem"><table class="tabla">
  <thead><tr><th scope="col">Diputado</th><th scope="col">Cargo</th><th scope="col">Bloque</th><th scope="col">Provincia</th></tr></thead>
  <tbody>
  <?php foreach ($c['integrantes'] as $m): ?>
    <tr><td><a href="/diputado/<?= e($m['slug']) ?>"><?= e($m['nombre']) ?></a></td><td data-etiqueta="Cargo"><?= e($m['cargo']) ?></td><td class="tenue" data-etiqueta="Bloque"><?= e($m['bloque']) ?></td><td class="tenue" data-etiqueta="Provincia"><?= e($m['provincia']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p class="fuente-pie">Fuente: comisiones permanentes, autoridades e integrantes, Cámara de Diputados (datos.hcdn.gob.ar), junio de 2026.</p>
