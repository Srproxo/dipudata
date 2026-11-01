<?php use App\Datos;
/** @var string $tipo @var array $t @var string $anio @var string $q @var int $pagina @var int $paginas @var int $total @var list<array> $items @var list<string> $anios */
?>
<nav class="migas" aria-label="Ubicación"><a href="/senado">Registros del Senado</a> <span aria-hidden="true">/</span> <span><?= e($t['titulo']) ?></span></nav>
<h1 class="titulo" style="max-width:26ch"><?= e($t['titulo']) ?></h1>
<p class="bajada"><?= e($t['descripcion']) ?></p>

<form class="filtros" method="get" action="/senado/<?= e($tipo) ?>" style="margin-top:1.5rem">
  <label>Buscar en el texto
    <input type="search" name="q" value="<?= e($q) ?>" maxlength="80" placeholder="Palabra o número" autocomplete="off">
  </label>
  <label>Año
    <select name="anio">
      <?php if ($permiteTodos): ?><option value="todos"<?= $anio === 'todos' ? ' selected' : '' ?>>Todos los años</option><?php endif; ?>
      <?php foreach ($anios as $a): ?><option value="<?= e($a) ?>"<?= $anio === $a ? ' selected' : '' ?>><?= $a === '0000' ? 'Sin fecha' : e($a) ?> (<?= (int)$t['anios'][$a] ?>)</option><?php endforeach; ?>
    </select>
  </label>
  <button class="boton" type="submit">Filtrar</button>
  <?php if ($q !== ''): ?><a class="enlace-limpiar" href="<?= e(url_con(['q' => '', 'pagina' => ''])) ?>">Quitar búsqueda</a><?php endif; ?>
</form>

<p class="conteo" aria-live="polite"><?= number_format($total, 0, ',', '.') ?> <?= $total === 1 ? 'registro' : 'registros' ?><?= $anio === 'todos' ? '' : ($anio === '0000' ? ' sin fecha' : ' en ' . e($anio)) ?><?= $q !== '' ? ' que contienen “' . e($q) . '”' : '' ?></p>

<?php if (!$items): ?>
  <p class="vacio-txt">No hay registros con esos filtros.</p>
<?php else: ?>
<div class="tabla-caja"><table class="tabla tabla-docs">
  <thead><tr><th scope="col">Fecha</th><th scope="col">Número</th><th scope="col">Detalle</th></tr></thead>
  <tbody>
  <?php foreach ($items as $it): ?>
    <tr>
      <td class="num nowrap" data-etiqueta="Fecha"><?= !empty($it['f']) ? e(date('d/m/Y', strtotime($it['f']))) : '—' ?></td>
      <td class="nowrap" data-etiqueta="Número"><?= e($it['n'] ?? '') ?></td>
      <td>
        <?= e($it['t'] ?? '') ?>
        <?php if (!empty($it['e']) || !empty($it['l'])): ?><span class="tenue pequeno block">Expediente <?= e($it['e'] ?? '—') ?><?= !empty($it['l']) ? ' · ' . e($it['l']) : '' ?></span><?php endif; ?>
        <?php $links = []; if (!empty($it['u'])) $links['Ver documento'] = $it['u'];
              foreach ((array)($it['x'] ?? []) as $k => $v) { if ($k === 'actas') $links['Acta de apertura'] = $v; if ($k === 'dictamen') $links['Dictamen'] = $v; if ($k === 'disposicion') $links['Disposición'] = $v; } ?>
        <?php if ($links || !empty($it['x']['monto'])): ?>
          <span class="docs-links pequeno">
            <?php foreach ($links as $txt => $href): ?><a href="<?= e($href) ?>" rel="noopener nofollow" target="_blank"><?= e($txt) ?> ↗</a><?php endforeach; ?>
            <?php if (!empty($it['x']['monto']) && monto_ars((string)$it['x']['monto'])): ?><span class="tenue">Monto total: <b class="num"><?= e(monto_ars((string)$it['x']['monto'])) ?></b></span><?php endif; ?>
          </span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<?php if ($paginas > 1): ?>
<nav class="paginas" aria-label="Paginación">
  <?php if ($pagina > 1): ?><a href="<?= e(url_con(['pagina' => $pagina - 1])) ?>" rel="prev">← Anterior</a><?php endif; ?>
  <span class="num">Página <?= $pagina ?> de <?= $paginas ?></span>
  <?php if ($pagina < $paginas): ?><a href="<?= e(url_con(['pagina' => $pagina + 1])) ?>" rel="next">Siguiente →</a><?php endif; ?>
</nav>
<?php endif; endif; ?>
<p class="fuente-pie">Fuente: <?= e($t['fuente']) ?>. Los textos se muestran tal como los publica el Senado.</p>
