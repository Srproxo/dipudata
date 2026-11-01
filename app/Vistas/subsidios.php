<?php /** @var array $idx @var string $anio @var string $q @var list<array> $items @var int $total @var float $suma @var int $pagina @var int $paginas */
$esCovid = $anio === 'covid';
?>
<p class="antetitulo">Cámara de Diputados</p>
<h1 class="titulo">Subsidios.</h1>
<p class="bajada">La Cámara de Diputados otorga subsidios a fundaciones, clubes, hospitales y otras organizaciones. Este es el registro que publica, con el monto de cada uno y el diputado al que figura asociado.</p>
<p class="aviso"><strong>Qué muestra y qué no.</strong> Los datos son los del registro oficial de la Cámara, de 2020 a 2023. La columna "diputado" indica a quién se asocia cada subsidio en ese registro; no dice cómo se decidió ni si la organización fue propuesta por esa persona. Los CUIT de los beneficiarios no se publican.</p>

<nav class="pestanas" aria-label="Año">
  <?php foreach ($idx as $a => $r): ?>
    <a href="/subsidios?anio=<?= e($a) ?>"<?= $anio === (string)$a ? ' aria-current="page"' : '' ?>><?= $a === 'covid' ? 'COVID-19 (2020)' : e($a) ?></a>
  <?php endforeach; ?>
</nav>

<dl class="participacion" style="margin-top:1.25rem">
  <div><dt>Registros</dt><dd><?= number_format((int)($idx[$anio]['n'] ?? 0), 0, ',', '.') ?></dd></div>
  <div><dt>Total</dt><dd><?= e(monto_ars((string)($idx[$anio]['total'] ?? 0))) ?></dd></div>
  <?php if (!$esCovid): ?><div><dt>Diputados en el registro</dt><dd><?= (int)($idx[$anio]['diputados'] ?? 0) ?></dd></div>
  <?php else: ?><div><dt>Transferencias efectuadas</dt><dd><?= (int)($idx[$anio]['efectuadas'] ?? 0) ?></dd></div><?php endif; ?>
</dl>

<form class="filtros" method="get" action="/subsidios" style="margin-top:1.25rem">
  <input type="hidden" name="anio" value="<?= e($anio) ?>">
  <?php if (!empty($dipSel)): ?><input type="hidden" name="diputado" value="<?= e($dipSel) ?>"><?php endif; ?>
  <label>Buscar
    <input type="search" name="q" value="<?= e($q) ?>" maxlength="80" placeholder="<?= $esCovid ? 'Beneficiario o área' : 'Beneficiario, diputado o provincia' ?>" autocomplete="off">
  </label>
  <button class="boton" type="submit">Filtrar</button>
  <?php if ($q !== ''): ?><a class="enlace-limpiar" href="/subsidios?anio=<?= e($anio) ?>">Quitar búsqueda</a><?php endif; ?>
</form>

<?php if (!empty($dipSel)): ?><p class="aviso" style="margin-top:1rem">Mostrando solo los registros asociados a un diputado. <a href="/subsidios?anio=<?= e($anio) ?>">Ver todos</a></p><?php endif; ?>
<p class="conteo" aria-live="polite"><?= number_format($total, 0, ',', '.') ?> <?= $total === 1 ? 'registro' : 'registros' ?><?= $q !== '' ? ', suman ' . e(monto_ars((string)$suma)) : '' ?></p>
<?php if (!$items): ?>
  <p class="vacio-txt">No hay registros con esa búsqueda.</p>
<?php else: ?>
<div class="tabla-caja"><table class="tabla tabla-docs">
  <?php if ($esCovid): ?>
  <thead><tr><th scope="col">Beneficiario</th><th scope="col">Área o interbloque</th><th scope="col" class="der">Monto</th><th scope="col">Estado</th><th scope="col">Fecha</th></tr></thead>
  <tbody>
  <?php foreach ($items as $it): ?>
    <tr><td><?= e($it['b']) ?></td><td class="tenue" data-etiqueta="Área"><?= e($it['a']) ?></td>
        <td class="der num" data-etiqueta="Monto"><?= e(monto_ars((string)$it['m'])) ?></td>
        <td data-etiqueta="Estado"><?= e(ucfirst($it['e'])) ?></td>
        <td class="num nowrap" data-etiqueta="Fecha"><?= $it['f'] ? e(date('d/m/Y', strtotime($it['f']))) : '—' ?></td></tr>
  <?php endforeach; ?>
  <?php else: ?>
  <thead><tr><th scope="col">Beneficiario</th><th scope="col" class="der">Monto</th><th scope="col">Diputado asociado</th><th scope="col">Provincia</th></tr></thead>
  <tbody>
  <?php foreach ($items as $it): ?>
    <tr><td><?= e($it['b']) ?></td>
        <td class="der num" data-etiqueta="Monto"><?= e(monto_ars((string)$it['m'])) ?></td>
        <td data-etiqueta="Diputado"><?= !empty($it['s']) ? '<a href="/diputado/' . e($it['s']) . '">' . e($it['d']) . '</a>' : e($it['d']) ?></td>
        <td class="tenue" data-etiqueta="Provincia"><?= e($it['p']) ?></td></tr>
  <?php endforeach; ?>
  <?php endif; ?>
  </tbody>
</table></div>
<?php if ($paginas > 1): ?>
<nav class="paginas" aria-label="Paginación">
  <?php if ($pagina > 1): ?><a href="<?= e(url_con(['pagina' => $pagina - 1])) ?>" rel="prev">← Anterior</a><?php endif; ?>
  <span class="num">Página <?= $pagina ?> de <?= $paginas ?></span>
  <?php if ($pagina < $paginas): ?><a href="<?= e(url_con(['pagina' => $pagina + 1])) ?>" rel="next">Siguiente →</a><?php endif; ?>
</nav>
<?php endif; endif; ?>
<p class="fuente-pie">Fuente: Subsidios, Cámara de Diputados de la Nación (datos.hcdn.gob.ar). Montos en pesos de cada año, sin ajustar por inflación. Los nombres se muestran con el formato del registro oficial.</p>
