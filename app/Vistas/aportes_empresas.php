<?php
/** @var list<array> $empresas personas jurídicas aportantes, de más a menos agrupaciones  @var string $q búsqueda */
$todas = isset($_GET['todas']) || $q !== '' || count($empresas) <= 60;
$lista = $todas ? $empresas : array_slice($empresas, 0, 50);
?>
<nav class="migas" aria-label="Ubicación"><a href="/aportes">Aportes a partidos</a> <span aria-hidden="true">/</span> <span>Empresas</span></nav>
<h1 class="titulo">Empresas que aportan a los partidos.</h1>
<p class="bajada" style="margin-bottom:1.5rem">Las personas jurídicas (empresas, cooperativas, asociaciones) que declararon aportes ante la Cámara Nacional Electoral desde 2019, a cuántas agrupaciones aportaron y cuánto en total. La actividad es la principal que cada una declaró ante ARCA.</p>
<p class="aviso"><strong>Cómo leerlo.</strong> Un mismo partido se presenta como una agrupación distinta en cada distrito y en cada elección: aportar a "La Libertad Avanza" de Neuquén y a la de Mendoza cuenta como dos agrupaciones. Un aporte es legal dentro de los topes de la Ley 26.215 y no indica que la empresa haya recibido algo a cambio.</p>

<form class="filtros" method="get" action="/aportes/empresas" style="margin-top:1.5rem">
  <label>Buscar empresa o actividad<input type="search" name="q" value="<?= e($q) ?>" maxlength="80" placeholder="Nombre o rubro" autocomplete="off"></label>
  <button class="boton" type="submit">Buscar</button>
  <?php if ($q !== ''): ?><a class="enlace-limpiar" href="/aportes/empresas">Quitar filtros</a><?php endif; ?>
</form>
<p class="conteo" aria-live="polite"><?= count($empresas) ?> <?= count($empresas) === 1 ? 'persona jurídica' : 'personas jurídicas' ?></p>
<div class="tabla-caja"><table class="tabla">
  <thead><tr><th scope="col">Empresa o entidad</th><th scope="col" class="der">Agrupaciones</th><th scope="col" class="der">Aportes</th><th scope="col" class="der">Total</th></tr></thead>
  <tbody>
  <?php foreach ($lista as $em): ?>
    <tr>
      <td><span><a href="/aportes/empresas/<?= e($em['slug']) ?>"><?= e($em['nombre']) ?></a><?php if ($em['actividad']): ?><br><span class="tenue pequeno"><?= e($em['actividad']) ?></span><?php endif; ?></span></td>
      <td class="der num" data-etiqueta="Agrupaciones"><?= (int)$em['agrupaciones'] ?></td>
      <td class="der num" data-etiqueta="Aportes"><?= (int)$em['aportes'] ?></td>
      <td class="der num" data-etiqueta="Total"><b style="white-space:nowrap">$ <?= number_format($em['total'], 0, ',', '.') ?></b></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php if (!$todas): ?><p><a href="/aportes/empresas?todas=1">Ver las <?= count($empresas) ?> personas jurídicas</a> <span class="tenue">(se muestran las 50 que aportaron a más agrupaciones)</span></p><?php endif; ?>
<p class="fuente-pie">Fuentes: Cámara Nacional Electoral, registro de aportantes (aportantes.electoral.gob.ar); actividad principal: Registro Nacional de Sociedades, Ministerio de Justicia (datos.jus.gob.ar). No se cuentan los aportes anulados. <a href="/metodologia#aportes">Metodología</a>.</p>
