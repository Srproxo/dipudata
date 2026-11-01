<?php
use App\Datos;
/** @var array $b @var list<array> $miembros */
$esSen = $b['camara'] === 'Senadores';
$porProv = [];
foreach ($miembros as $m) { $porProv[$m['provincia']][] = $m; }
ksort($porProv);
?>
<nav class="migas" aria-label="Ubicación"><a href="/congreso/bloques">Bloques</a> <span aria-hidden="true">/</span> <span><?= $esSen ? 'Senado' : 'Diputados' ?></span></nav>
<p class="antetitulo">Bloque en <?= $esSen ? 'el Senado' : 'la Cámara de Diputados' ?></p>
<h1 class="titulo"><i class="punto punto-grande" style="background:<?= e(color_bloque($b['nombre_oficial'])) ?>"></i><?= e($b['nombre']) ?></h1>
<p class="bajada"><?= (int)$b['cantidad'] ?> <?= (int)$b['cantidad'] === 1 ? 'integrante' : 'integrantes' ?> de <?= count($b['provincias']) ?> <?= count($b['provincias']) === 1 ? 'provincia' : 'provincias' ?>.</p>
<?php if (!$esSen): ?><?php else: ?><p class="aviso">El padrón del Senado se está contrastando con senado.gob.ar; la composición puede tener ajustes.</p><?php endif; ?>

<section class="seccion" aria-labelledby="int">
  <h2 id="int" class="seccion-titulo">Quiénes lo integran</h2>
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col">Legislador</th><th scope="col">Provincia</th></tr></thead>
    <tbody>
    <?php foreach ($miembros as $m): ?>
      <tr><td class="celda-nombre"><?= avatar($m, 32) ?><a href="<?= e(Datos::urlLegislador($m)) ?>"><?= e($m['nombre']) ?></a></td>
          <td data-etiqueta="Provincia"><a href="/congreso/provincias/<?= e(slug_provincia($m['provincia'])) ?>"><?= e($m['provincia']) ?></a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
