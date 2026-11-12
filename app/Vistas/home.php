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

<form class="buscador" method="get" action="/#directorio" role="search">
  <label for="buscar-nombre" class="buscador-t">Buscá un legislador por nombre</label>
  <div class="buscador-fila">
    <input id="buscar-nombre" type="search" name="q" maxlength="80" placeholder="Apellido o nombre" autocomplete="off">
    <button class="boton" type="submit">Buscar</button>
  </div>
</form>

<section class="seccion" aria-labelledby="atajos">
  <h2 id="atajos" class="seccion-titulo">Lo que más se busca</h2>
  <ul class="atajos">
    <li><a href="/votaciones"><b>Cómo votó cada uno</b><span>Votaciones nominales de Diputados y del Senado, una por una.</span></a></li>
    <li><a href="/patrimonio"><b>Patrimonio declarado</b><span>Cuánto declaró cada legislador, en pesos de hoy y en dólares, y cómo cambió.</span></a></li>
    <li><a href="/afinidad"><b>¿Con quién coincidís?</b><span>Test de afinidad: respondé en las votaciones más parejas y mirá con qué legisladores votás igual.</span></a></li>
    <li><a href="/tv"><b>Modo TV</b><span>Perfiles a pantalla completa, con diapositivas que pasan solas, para mostrar en una TV.</span></a></li>
    <li><a href="/comparar"><b>Comparar legisladores</b><span>Hasta cuatro, lado a lado: patrimonio, votos y proyectos.</span></a></li>
    <li><a href="/leyes"><b>Leyes</b><span>Las leyes sancionadas, con su sumario y sus fechas.</span></a></li>
    <li><a href="/remuneraciones"><b>Cuánto cobran</b><span>La dieta y los adicionales que publica la Cámara.</span></a></li>
    <li><a href="/viajes"><b>Pasajes y misiones</b><span>Pasajes emitidos y misiones oficiales de los diputados.</span></a></li>
    <li><a href="/comisiones"><b>Comisiones</b><span>Quién integra cada comisión de Diputados.</span></a></li>
    <li><a href="/congreso/bloques"><b>Bloques</b><span>Cuántos legisladores tiene cada bloque.</span></a></li>
  </ul>
</section>

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

  <p class="conteo" aria-live="polite"><?= $totalDir ?> <?= $totalDir === 1 ? 'legislador' : 'legisladores' ?><?= $filtrado ? ' con estos filtros' : '' ?><?= $paginas > 1 ? ' · página ' . $pagina . ' de ' . $paginas : '' ?></p>
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
  <?php if ($paginas > 1): ?>
  <nav class="paginas paginas-num" aria-label="Páginas del directorio">
    <?php if ($pagina > 1): ?><a href="<?= e(url_con(['pagina' => $pagina - 1 > 1 ? $pagina - 1 : null])) ?>#directorio" rel="prev">← Anterior</a><?php else: ?><span class="tenue">← Anterior</span><?php endif; ?>
    <span class="paginas-lista">
      <?php for ($i = 1; $i <= $paginas; $i++): ?>
        <?php if ($i === $pagina): ?><span class="pag-actual" aria-current="page"><?= $i ?></span><?php else: ?><a href="<?= e(url_con(['pagina' => $i > 1 ? $i : null])) ?>#directorio" aria-label="Página <?= $i ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
    </span>
    <?php if ($pagina < $paginas): ?><a href="<?= e(url_con(['pagina' => $pagina + 1])) ?>#directorio" rel="next">Siguiente →</a><?php else: ?><span class="tenue">Siguiente →</span><?php endif; ?>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
  <p class="volver-arriba"><a href="#arriba">↑ Volver arriba</a></p>
</section>
