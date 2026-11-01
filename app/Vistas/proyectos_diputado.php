<?php /** @var array $l @var array $pr @var list<array> $lista @var string $tipo @var string $resSel @var string $q @var int $total @var int $pagina @var int $paginas */ ?>
<nav class="migas" aria-label="Ubicación"><a href="/">Legisladores</a> <span aria-hidden="true">/</span> <a href="<?= e(App\Datos::urlLegislador($l)) ?>"><?= e($l['nombre']) ?></a> <span aria-hidden="true">/</span> <span>Proyectos</span></nav>
<p class="antetitulo">Proyectos parlamentarios</p>
<h1 class="titulo" style="font-size:clamp(1.5rem,3.5vw,2.25rem)">Proyectos de <?= e($l['nombre']) ?></h1>
<p class="bajada">Proyectos presentados en la Cámara de Diputados en los que figura como autor/a, entre el <?= e(fecha_es($pr['resumen']['desde'])) ?> y el <?= e(fecha_es($pr['resumen']['hasta'])) ?>, y lo que la Cámara resolvió sobre cada uno cuando figura en el archivo oficial.</p>

<form class="filtros" method="get" action="/diputado/<?= e($l['slug']) ?>/proyectos" style="margin-top:1.5rem">
  <label>Buscar en el título
    <input type="search" name="q" value="<?= e($q) ?>" maxlength="80" placeholder="Tema o número de expediente" autocomplete="off">
  </label>
  <label>Tipo
    <select name="tipo"><option value="">Todos</option>
      <?php foreach ($pr['resumen']['por_tipo'] as $t => $n): ?><option value="<?= e($t) ?>"<?= $tipo === $t ? ' selected' : '' ?>><?= e($t) ?> (<?= (int)$n ?>)</option><?php endforeach; ?>
    </select>
  </label>
  <label>Qué pasó
    <select name="resultado"><option value="">Todos</option>
      <?php foreach ($pr['resumen']['por_resultado'] as $t => $n): ?><option value="<?= e($t) ?>"<?= $resSel === $t ? ' selected' : '' ?>><?= e(ucfirst(mb_strtolower($t))) ?> (<?= (int)$n ?>)</option><?php endforeach; ?>
    </select>
  </label>
  <button class="boton" type="submit">Filtrar</button>
  <?php if ($q !== '' || $tipo || $resSel): ?><a class="enlace-limpiar" href="/diputado/<?= e($l['slug']) ?>/proyectos">Quitar filtros</a><?php endif; ?>
</form>

<p class="conteo" aria-live="polite"><?= number_format($total, 0, ',', '.') ?> <?= $total === 1 ? 'proyecto' : 'proyectos' ?></p>
<?php if (!$lista): ?>
  <p class="vacio-txt">No hay proyectos con esos filtros.</p>
<?php else: ?>
<div class="tabla-caja"><table class="tabla tabla-docs">
  <thead><tr><th scope="col">Fecha</th><th scope="col">Expediente</th><th scope="col">Proyecto</th><th scope="col">Qué pasó</th></tr></thead>
  <tbody>
  <?php foreach ($lista as $x): ?>
    <tr>
      <td class="num nowrap" data-etiqueta="Fecha"><?= e(date('d/m/Y', strtotime($x[1]))) ?></td>
      <td class="num nowrap" data-etiqueta="Expediente"><?= e($x[3] ?: '—') ?></td>
      <td><?= e($x[4]) ?><span class="tenue pequeno block"><?= e($x[2]) ?></span></td>
      <td data-etiqueta="Qué pasó"><?= $x[5] ? e(ucfirst(mb_strtolower($x[5]))) . ($x[6] ? '<span class="tenue pequeno block">' . e(fecha_es($x[6])) . '</span>' : '') : '<span class="tenue">Sin resolución registrada</span>' ?></td>
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
<p class="aviso"><strong>Cómo leer esta lista.</strong> El archivo oficial registra un solo autor por proyecto, sin cofirmantes: acá están los proyectos donde figura <?= e($l['nombre']) ?> como autor/a, no todos los que firmó. "Sin resolución registrada" quiere decir que el proyecto no figura entre los que la Cámara resolvió en el archivo "Resultado de proyectos"; no significa que haya sido rechazado ni que siga vigente. El autor se identifica por nombre completo y se verifica que tuviera mandato en la fecha del proyecto. <a href="/metodologia">Cómo trabajamos</a>.</p>
<p class="fuente-pie">Fuente: datos.hcdn.gob.ar, conjuntos "Proyectos parlamentarios" y "Resultado de proyectos" (Cámara de Diputados de la Nación).</p>
