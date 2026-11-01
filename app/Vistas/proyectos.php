<?php
use App\Datos;
/** @var array $l @var list<array> $items @var int $total @var int $pagina @var int $paginas @var string $res @var array $resumen */
$esSen = $l['camara'] === 'Senadores';

?>
<nav class="migas" aria-label="Ubicación"><a href="/">Legisladores</a> <span aria-hidden="true">/</span> <a href="<?= e(Datos::urlLegislador($l)) ?>"><?= e($l['nombre']) ?></a> <span aria-hidden="true">/</span> <span>Proyectos</span></nav>
<p class="antetitulo"><?= $esSen ? 'Senado' : 'Cámara de Diputados' ?></p>
<h1 class="titulo">Proyectos de <?= e($l['nombre']) ?></h1>
<?php if ($esSen): ?>
<p class="bajada">Los <?= number_format((int)$resumen['total'], 0, ',', '.') ?> proyectos en los que figura como autor o coautor, según el buscador oficial del Senado, que identifica a cada senador por su ID. Incluye todos sus mandatos. El buscador no informa qué pasó con cada proyecto: el enlace lleva a su expediente en senado.gob.ar, con el trámite completo.</p>
<?php else: ?>
<p class="bajada">Los <?= number_format((int)$resumen['total'], 0, ',', '.') ?> proyectos que presentó como <b>primer firmante</b> <?= substr($resumen['desde'], 0, 4) === substr($resumen['hasta'], 0, 4) ? 'en ' . e(substr($resumen['desde'], 0, 4)) : 'entre ' . e(substr($resumen['desde'], 0, 4)) . ' y ' . e(substr($resumen['hasta'], 0, 4)) ?>, según los datos abiertos de la Cámara de Diputados. Los datos abiertos registran un solo autor por proyecto: los proyectos que acompañó como cofirmante no figuran acá.</p>
<form class="filtros" method="get">
  <label>Qué pasó con el proyecto
    <select name="resultado">
      <option value="">Todos</option>
      <?php foreach (['SANCIONADO', 'MEDIA SANCION', 'APROBADO', 'RECHAZADO', 'RETIRADO', 'ninguno'] as $r): ?>
        <option value="<?= e($r) ?>"<?= $res === $r ? ' selected' : '' ?>><?= e(etiqueta_resultado($r === 'ninguno' ? '' : $r)) ?><?= $r !== 'ninguno' && !empty($resumen['resultados'][$r]) ? ' (' . (int)$resumen['resultados'][$r] . ')' : '' ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="boton" type="submit">Filtrar</button>
  <?php if ($res): ?><a class="enlace-limpiar" href="?">Quitar filtro</a><?php endif; ?>
</form>
<?php endif; ?>

<p class="conteo"><?= number_format($total, 0, ',', '.') ?> <?= $total === 1 ? 'proyecto' : 'proyectos' ?><?= $res ? ' con este resultado' : '' ?></p>
<div class="tabla-caja"><table class="tabla">
  <thead><tr><th scope="col">Expediente</th><?php if (!$esSen): ?><th scope="col">Fecha</th><?php endif; ?><th scope="col">Tipo</th><th scope="col"><?= $esSen ? 'Extracto' : 'Título' ?></th><?php if (!$esSen): ?><th scope="col">Qué pasó</th><?php endif; ?></tr></thead>
  <tbody>
  <?php foreach ($items as $p): ?>
    <?php if ($esSen): ?>
    <tr><td class="num nowrap" data-etiqueta="Expediente"><a href="https://www.senado.gob.ar<?= e($p[3]) ?>" rel="external"><?= e($p[0]) ?></a></td><td data-etiqueta="Tipo"><?= e($p[1]) ?></td><td data-etiqueta="Extracto"><?= e($p[2]) ?></td></tr>
    <?php else: ?>
    <tr><td class="num nowrap" data-etiqueta="Expediente"><?= e($p[0]) ?></td><td class="num nowrap" data-etiqueta="Fecha"><?= e(fecha_es($p[1])) ?></td><td data-etiqueta="Tipo"><?= e($p[2]) ?></td><td data-etiqueta="Título"><?= e($p[3]) ?></td>
        <td data-etiqueta="Qué pasó"><?= $p[4] ? '<b>' . e(etiqueta_resultado($p[4])) . '</b>' . ($p[5] ? ' <span class="tenue pequeno nowrap">' . e(fecha_es($p[5])) . '</span>' : '') : '<span class="tenue">' . e(etiqueta_resultado('')) . '</span>' ?></td></tr>
    <?php endif; ?>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php if ($paginas > 1): ?>
<nav class="paginas" aria-label="Paginación">
  <?php if ($pagina > 1): ?><a href="<?= e(url_con(['pagina' => $pagina - 1])) ?>">← Anteriores</a><?php endif; ?>
  <span class="num">Página <?= $pagina ?> de <?= $paginas ?></span>
  <?php if ($pagina < $paginas): ?><a href="<?= e(url_con(['pagina' => $pagina + 1])) ?>">Siguientes →</a><?php endif; ?>
</nav>
<?php endif; ?>
<p class="tenue pequeno"><?php if ($esSen): ?>Fuente: buscador de proyectos del Senado de la Nación (senado.gob.ar), consultado por el ID oficial del senador.<?php else: ?>Fuente: "Proyectos parlamentarios", "Resultado proyectos" y "Resultado de proyectos" (Cámara de Diputados, datos.hcdn.gob.ar). "Sin resultado registrado" significa que la Cámara no anotó ninguna aprobación, sanción, rechazo ni retiro: puede seguir en comisión o haber perdido estado parlamentario.<?php endif; ?> <a href="/metodologia#proyectos">Cómo se arma esta lista</a>.</p>
