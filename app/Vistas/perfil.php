<?php
use App\Datos;
/** @var array $l legislador */
$esSen = $l['camara'] === 'Senadores';
$bloque = null;
foreach (Datos::bloques($l['camara']) as $b) { if ($b['nombre_oficial'] === $l['bloque_oficial']) { $bloque = $b; break; } }
$provSlug = slug_provincia($l['provincia']);
$mismoBloque = $bloque ? count(Datos::miembros($bloque)) - 1 : 0;
$mismaProv = array_values(array_filter(Datos::legisladores($l['camara']), static fn($x) => $x['provincia'] === $l['provincia'] && $x['slug'] !== $l['slug']));
$cargo = $esSen ? 'Senador/a nacional' : 'Diputado/a nacional';
?>
<nav class="migas" aria-label="Ubicación"><a href="/">Legisladores</a> <span aria-hidden="true">/</span> <span><?= $esSen ? 'Senado' : 'Diputados' ?></span></nav>

<header class="perfil-cabeza">
  <?= avatar($l, 112) ?>
  <div>
    <p class="antetitulo"><?= e($cargo) ?> por <a href="/congreso/provincias/<?= e($provSlug) ?>"><?= e($l['provincia']) ?></a></p>
    <h1 class="titulo titulo-perfil"><?= e($l['nombre']) ?></h1>
    <p class="perfil-bloque"><?= etiqueta_bloque($l) ?>
      <?php if ($bloque): ?><span class="tenue">· <a href="<?= e(Datos::urlBloque($bloque)) ?>">ver los <?= (int)$bloque['cantidad'] ?> integrantes del bloque</a></span><?php endif; ?>
    </p>
  </div>
</header>

<?php if (!$l['verificado']): ?>
  <p class="aviso"><strong>Datos en verificación.</strong> El padrón del Senado de este sitio todavía se está contrastando con senado.gob.ar. Si encontrás un error, escribinos y lo corregimos.</p>
<?php endif; ?>

<div class="dos-col perfil-grilla">
  <section aria-labelledby="ficha">
    <h2 id="ficha" class="seccion-titulo">Ficha</h2>
    <dl class="ficha">
      <div><dt>Cámara</dt><dd><?= $esSen ? 'Senado de la Nación' : 'Cámara de Diputados de la Nación' ?></dd></div>
      <div><dt>Distrito</dt><dd><?= e($l['provincia']) ?></dd></div>
      <div><dt>Bloque</dt><dd><?= e($l['bloque']) ?></dd></div>
      <?php if ($l['mandato']): ?><div><dt>Mandato</dt><dd class="num"><?= e($l['mandato']) ?></dd></div><?php endif; ?>
      <?php if ($l['jura']): ?><div><dt>Juró el</dt><dd><?= e(fecha_es($l['jura'])) ?></dd></div><?php endif; ?>
      <?php if ($l['inicio'] && $l['inicio'] !== $l['jura']): ?><div><dt>Inicio del mandato</dt><dd><?= e(fecha_es($l['inicio'])) ?></dd></div><?php endif; ?>
    </dl>
  </section>

  <section aria-labelledby="fuentes">
    <h2 id="fuentes" class="seccion-titulo">De dónde sale cada dato</h2>
    <ul class="fuentes">
      <?php foreach ($l['fuentes'] as $campo => $fuente): ?>
        <li><span class="fuente-campo"><?= e(ucfirst($campo)) ?></span><span class="fuente-origen"><?= e($fuente) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <p class="tenue pequeno">Asistencia, votaciones y otras métricas se vuelven a publicar cuando estén calculadas desde las actas oficiales. <a href="/metodologia">Más sobre cómo trabajamos</a>.</p>
  </section>
</div>

<?php if ($mismaProv): ?>
<section class="seccion" aria-labelledby="misma-prov">
  <h2 id="misma-prov" class="seccion-titulo">Otros <?= $esSen ? 'senadores' : 'diputados' ?> por <?= e($l['provincia']) ?></h2>
  <ul class="lista-chips">
    <?php foreach (array_slice($mismaProv, 0, 30) as $x): ?>
      <li><a href="<?= e(Datos::urlLegislador($x)) ?>"><?= e($x['nombre']) ?></a><span class="tenue"><?= e($x['bloque']) ?></span></li>
    <?php endforeach; ?>
  </ul>
  <?php if (count($mismaProv) > 30): ?><p><a href="/congreso/provincias/<?= e($provSlug) ?>">Ver los <?= count($mismaProv) + 1 ?> de la provincia</a></p><?php endif; ?>
</section>
<?php endif; ?>
