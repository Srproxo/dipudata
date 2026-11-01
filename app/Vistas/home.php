<?php
use App\Datos;
/** @var list<array> $lista @var string $q @var string $camaraSel @var string $provSel @var string $bloqueSel */
$comp = ['Diputados' => Datos::bloques('Diputados'), 'Senadores' => Datos::bloques('Senadores')];
$nDip = count(Datos::legisladores('Diputados'));
$nSen = count(Datos::legisladores('Senadores'));
$filtrado = $q !== '' || $camaraSel !== '' || $provSel !== '' || $bloqueSel !== '';
?>
<p class="antetitulo">Congreso de la Nación Argentina</p>
<h1 class="titulo">Quién ocupa cada banca.</h1>
<p class="bajada">Los <?= $nDip ?> diputados y <?= $nSen ?> senadores en ejercicio, con su bloque, su provincia y sus fechas de mandato. Todo sale de registros oficiales y cada dato indica su fuente.</p>

<div class="dos-col composicion">
<?php foreach ($comp as $camara => $bloques): $total = array_sum(array_column($bloques, 'cantidad')); ?>
  <section aria-label="Composición de <?= e($camara) ?>">
    <div class="fila-titulo"><h2><?= $camara === 'Senadores' ? 'Senado' : 'Diputados' ?></h2><p class="num"><?= (int)$total ?> bancas</p></div>
    <?= barra_composicion($bloques) ?>
    <ul class="leyenda">
      <?php foreach (array_slice($bloques, 0, 4) as $b): ?>
        <li><i style="background:<?= e(color_bloque($b['nombre_oficial'])) ?>"></i><a href="<?= e(Datos::urlBloque($b)) ?>"><?= e($b['nombre']) ?></a> <b class="num"><?= (int)$b['cantidad'] ?></b></li>
      <?php endforeach; ?>
      <?php if (count($bloques) > 4): ?><li><a href="/congreso/bloques#<?= $camara === 'Senadores' ? 'senado' : 'diputados' ?>">+ <?= count($bloques) - 4 ?> bloques</a></li><?php endif; ?>
    </ul>
  </section>
<?php endforeach; ?>
</div>

<section class="seccion" aria-labelledby="directorio">
  <h2 id="directorio" class="seccion-titulo">Directorio de legisladores</h2>
  <form class="filtros" method="get" action="/#directorio">
    <label>Buscar por nombre
      <input type="search" name="q" value="<?= e($q) ?>" maxlength="80" placeholder="Apellido o nombre" autocomplete="off">
    </label>
    <label>Cámara
      <select name="camara">
        <option value="">Ambas</option>
        <option value="Diputados"<?= $camaraSel === 'Diputados' ? ' selected' : '' ?>>Diputados</option>
        <option value="Senadores"<?= $camaraSel === 'Senadores' ? ' selected' : '' ?>>Senadores</option>
      </select>
    </label>
    <label>Provincia
      <select name="provincia">
        <option value="">Todas</option>
        <?php foreach (Datos::provincias() as $p): ?>
          <option value="<?= e($p['slug']) ?>"<?= $provSel === $p['slug'] ? ' selected' : '' ?>><?= e($p['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Bloque
      <select name="bloque">
        <option value="">Todos</option>
        <?php $vistos = []; foreach (array_merge(Datos::bloques('Diputados'), Datos::bloques('Senadores')) as $b) { if (isset($vistos[$b['slug']])) continue; $vistos[$b['slug']] = 1; ?>
          <option value="<?= e($b['slug']) ?>"<?= $bloqueSel === $b['slug'] ? ' selected' : '' ?>><?= e($b['nombre']) ?></option>
        <?php } ?>
      </select>
    </label>
    <button class="boton" type="submit">Filtrar</button>
    <?php if ($filtrado): ?><a class="enlace-limpiar" href="/#directorio">Quitar filtros</a><?php endif; ?>
  </form>

  <p class="conteo" aria-live="polite"><?= count($lista) ?> <?= count($lista) === 1 ? 'legislador' : 'legisladores' ?><?= $filtrado ? ' con estos filtros' : '' ?></p>
  <?php if (!$lista): ?>
    <p class="vacio-txt">Ningún legislador coincide con la búsqueda. Probá con menos filtros o con otra forma del nombre.</p>
  <?php else: ?>
  <div class="tabla-caja">
  <table class="tabla">
    <thead><tr><th scope="col">Legislador</th><th scope="col">Cámara</th><th scope="col">Provincia</th><th scope="col">Bloque</th></tr></thead>
    <tbody>
    <?php foreach ($lista as $l): ?>
      <tr>
        <td class="celda-nombre"><?= avatar($l, 36) ?><a href="<?= e(Datos::urlLegislador($l)) ?>"><?= e($l['nombre']) ?></a></td>
        <td data-etiqueta="Cámara"><?= $l['camara'] === 'Senadores' ? 'Senado' : 'Diputados' ?></td>
        <td data-etiqueta="Provincia"><a href="/congreso/provincias/<?= e(slug_provincia($l['provincia'])) ?>"><?= e($l['provincia']) ?></a></td>
        <td data-etiqueta="Bloque"><?= etiqueta_bloque($l) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
