<?php /** @var array $ley */
$refs = array_filter(preg_split('/\s+/', (string)($ley['r'] ?? '')) ?: []);
?>
<nav class="migas" aria-label="Ubicación"><a href="/leyes">Leyes</a> <span aria-hidden="true">/</span> <span>Ley <?= number_format((int)$ley['n'], 0, ',', '.') ?></span></nav>
<p class="antetitulo">Ley <?= number_format((int)$ley['n'], 0, ',', '.') ?></p>
<h1 class="titulo" style="max-width:36ch;font-size:clamp(1.4rem,3.2vw,2.1rem)"><?= e($ley['t'] ?? 'Sin título registrado') ?></h1>

<dl class="ficha" style="max-width:36rem;margin-top:1.5rem">
  <div><dt>Número</dt><dd class="num"><?= number_format((int)$ley['n'], 0, ',', '.') ?></dd></div>
  <?php if (!empty($ley['fs'])): ?><div><dt>Sancionada el</dt><dd><?= e(fecha_es($ley['fs'])) ?></dd></div><?php endif; ?>
  <?php if (!empty($ley['fp'])): ?><div><dt>Publicada en el Boletín Oficial</dt><dd><?= e(fecha_es($ley['fp'])) ?></dd></div><?php endif; ?>
</dl>

<?php if (!empty($ley['s'])): ?>
<section class="seccion" aria-labelledby="sumario">
  <h2 id="sumario" class="seccion-titulo">Sumario</h2>
  <p class="sumario"><?= e($ley['s']) ?></p>
  <?php if ($refs): ?>
    <p class="tenue pequeno" style="margin-top:1rem">Palabras clave</p>
    <ul class="refs"><?php foreach (array_slice($refs, 0, 40) as $r): ?><li><a href="/leyes?q=<?= e(urlencode($r)) ?>" style="color:inherit;text-decoration:none"><?= e(mb_strtolower($r, 'UTF-8')) ?></a></li><?php endforeach; ?></ul>
  <?php endif; ?>
</section>
<?php endif; ?>
<p class="fuente-pie">Fuente: Sumario de leyes y Leyes promulgadas, Cámara de Diputados de la Nación (datos.hcdn.gob.ar). El texto se muestra tal como lo publica la Cámara.</p>
