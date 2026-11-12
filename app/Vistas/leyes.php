<?php /** @var list<array> $lista @var list<array> $todas @var list<string> $anios @var string $anio @var string $q @var int $total @var int $pagina @var int $paginas */ ?>
<p class="antetitulo">Congreso de la Nación</p>
<h1 class="titulo">Leyes sancionadas.</h1>
<?php $ultima = $todas[0] ?? null; ?>
<p class="bajada">Las leyes que el Congreso sancionó desde 2008, con el sumario de cada una, las fechas de sanción y de publicación en el Boletín Oficial, el enlace al texto oficial y, cuando la hubo, la votación nominal. Se pueden buscar por número, por tema o por palabra clave.</p>
<p class="aviso"><strong>Período cubierto: leyes 26.352 a <?= $ultima ? number_format((int)$ultima[0], 0, ',', '.') : '' ?><?= $ultima && $ultima[2] ? ' (sancionada el ' . e(fecha_es($ultima[2])) . ')' : '' ?>.</strong> Hasta la 27.544 (febrero de 2020) los datos son de la Cámara de Diputados; las posteriores, de Infoleg, porque la Cámara todavía no las publicó en sus datos abiertos.</p>

<form class="filtros" method="get" action="/leyes" style="margin-top:1.5rem">
  <label>Buscar
    <input type="search" name="q" value="<?= e($q) ?>" maxlength="80" placeholder="Número, tema o palabra clave" autocomplete="off">
  </label>
  <label>Año de sanción
    <select name="anio"><option value="">Todos</option>
      <?php foreach ($anios as $a): ?><option value="<?= e($a) ?>"<?= $anio === $a ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
    </select>
  </label>
  <button class="boton" type="submit">Filtrar</button>
  <?php if ($q !== '' || $anio): ?><a class="enlace-limpiar" href="/leyes">Quitar filtros</a><?php endif; ?>
</form>

<p class="conteo" aria-live="polite"><?= number_format($total, 0, ',', '.') ?> <?= $total === 1 ? 'ley' : 'leyes' ?></p>
<?php if (!$lista): ?>
  <p class="vacio-txt">No hay leyes con esos filtros.</p>
<?php else: ?>
<div class="tabla-caja"><table class="tabla tabla-docs">
  <thead><tr><th scope="col">Ley</th><th scope="col">Asunto</th><th scope="col">Sanción</th></tr></thead>
  <tbody>
  <?php foreach ($lista as [$n, $t, $fs]): ?>
    <tr>
      <td data-etiqueta="Ley"><a class="ley-num" href="/leyes/<?= (int)$n ?>"><?= number_format((int)$n, 0, ',', '.') ?></a></td>
      <td><a href="/leyes/<?= (int)$n ?>" style="color:inherit"><?= e(mb_strlen($t) > 180 ? mb_substr($t, 0, 180) . '…' : $t) ?></a></td>
      <td class="num nowrap" data-etiqueta="Sanción"><?= $fs ? e(date('d/m/Y', strtotime($fs))) : '—' ?></td>
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
<p class="fuente-pie">Fuentes: leyes 26.352 a 27.544, Sumario de leyes y Leyes promulgadas de la Cámara de Diputados de la Nación (datos.hcdn.gob.ar); leyes posteriores, Base Infoleg de Normativa Nacional del Ministerio de Justicia (datos.jus.gob.ar, CC BY 4.0). Los títulos y sumarios se muestran tal como los publica cada fuente.</p>
