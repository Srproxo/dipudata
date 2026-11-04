<?php $esSen = $l['camara'] === 'Senadores'; /** @var array $l @var array $pr @var list<array> $lista @var string $tipo @var string $resSel @var string $rolSel @var string $q @var int $total @var int $pagina @var int $paginas */ ?>
<nav class="migas" aria-label="Ubicación"><a href="/">Legisladores</a> <span aria-hidden="true">/</span> <a href="<?= e(App\Datos::urlLegislador($l)) ?>"><?= e($l['nombre']) ?></a> <span aria-hidden="true">/</span> <span>Proyectos</span></nav>
<p class="antetitulo">Proyectos parlamentarios</p>
<h1 class="titulo" style="font-size:clamp(1.5rem,3.5vw,2.25rem)">Proyectos de <?= e($l['nombre']) ?></h1>
<p class="bajada"><?= $esSen ? 'Proyectos presentados en el Senado en los que figura entre los firmantes' : 'Proyectos presentados en la Cámara de Diputados en los que figura como autor/a' ?>, entre el <?= e(fecha_es($pr['resumen']['desde'])) ?> y el <?= e(fecha_es($pr['resumen']['hasta'])) ?><?= $esSen ? '.' : ', y lo que la Cámara resolvió sobre cada uno cuando figura en el archivo oficial.' ?></p>

<form class="filtros" method="get" action="<?= e(App\Datos::urlLegislador($l)) ?>/proyectos" style="margin-top:1.5rem">
  <label>Buscar en el título
    <input type="search" name="q" value="<?= e($q) ?>" maxlength="80" placeholder="Tema o número de expediente" autocomplete="off">
  </label>
  <label>Autoría
    <select name="rol"><option value="">Todas</option>
      <?php foreach (categorias_proyectos() as $k => [$et]): if ($k === 'firmante' && !$esSen) continue; if ($k === 'sin' && !$esSen) continue; ?><option value="<?= e($k) ?>"<?= $rolSel === $k ? ' selected' : '' ?>><?= e($et) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Tipo
    <select name="tipo"><option value="">Todos</option>
      <?php foreach ($pr['resumen']['por_tipo'] as $t => $n): ?><option value="<?= e($t) ?>"<?= $tipo === $t ? ' selected' : '' ?>><?= e(etiqueta_tipo($t)) ?> (<?= (int)$n ?>)</option><?php endforeach; ?>
    </select>
  </label>
  <?php if (!$esSen): ?><label>Qué pasó
    <select name="resultado"><option value="">Todos</option>
      <?php foreach ($pr['resumen']['por_resultado'] as $t => $n): ?><option value="<?= e($t) ?>"<?= $resSel === $t ? ' selected' : '' ?>><?= e(ucfirst(mb_strtolower($t))) ?> (<?= (int)$n ?>)</option><?php endforeach; ?>
    </select>
  </label><?php endif; ?>
  <button class="boton" type="submit">Filtrar</button>
  <?php if ($q !== '' || $tipo || $resSel || $rolSel): ?><a class="enlace-limpiar" href="<?= e(App\Datos::urlLegislador($l)) ?>/proyectos">Quitar filtros</a><?php endif; ?>
</form>

<p class="conteo" aria-live="polite"><?= number_format($total, 0, ',', '.') ?> <?= $total === 1 ? 'proyecto' : 'proyectos' ?></p>
<?php if (!$lista): ?>
  <p class="vacio-txt">No hay proyectos con esos filtros.</p>
<?php else: ?>
<div class="tabla-caja"><table class="tabla tabla-docs">
  <thead><tr><th scope="col">Fecha</th><th scope="col">Expediente</th><th scope="col">Proyecto</th><?php if (!$esSen): ?><th scope="col">Qué pasó</th><?php endif; ?></tr></thead>
  <tbody>
  <?php foreach ($lista as $x): ?>
    <tr>
      <td class="num nowrap" data-etiqueta="Fecha"><?= e(date('d/m/Y', strtotime($x[1]))) ?></td>
      <td class="num nowrap" data-etiqueta="Expediente"><?php if ($esSen): ?><a href="https://www.senado.gob.ar/parlamentario/comisiones/verExp/<?= e($x[0]) ?>" rel="noopener"><?= e($x[3]) ?></a><?php else: ?><?= e($x[3] ?: '—') ?><?php endif; ?></td>
      <td><?= e($x[4]) ?><span class="tenue pequeno block"><?= e(etiqueta_tipo($x[2])) ?><?php $cx = categoria_proyecto($x); $cc = categorias_proyectos(); if ($cx !== 'propio' || $esSen): ?><span class="etq-rol"><?= e($cc[$cx][0]) ?></span><?php endif; ?><?= $cx !== 'recon' && !empty($x[8]) ? '' : '' ?></span></td>
      <?php if (!$esSen): ?><td data-etiqueta="Qué pasó"><?= $x[5] ? e(ucfirst(mb_strtolower($x[5]))) . ($x[6] ? '<span class="tenue pequeno block">' . e(fecha_es($x[6])) . '</span>' : '') : '<span class="tenue">Sin resolución registrada</span>' ?></td><?php endif; ?>
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
<?php if ($esSen): ?>
<p class="aviso"><strong>Cómo leer esta lista.</strong> Es la lista de proyectos que el Senado publica en la página de <?= e($l['nombre']) ?>: incluye los que firmó junto con otros senadores ("y otros") y no informa qué pasó con cada uno. Se muestran los proyectos de ley, declaración, comunicación, resolución y decreto; se descartan las notas y comunicaciones. El senador se identifica con el ID oficial del Senado. <a href="/metodologia">Cómo trabajamos</a>.</p>
<p class="fuente-pie">Fuente: senado.gob.ar, página de cada senador (proyectos). Cada expediente enlaza a su ficha oficial.</p>
<?php else: ?>
<p class="aviso"><strong>Cómo leer esta lista.</strong> El archivo oficial registra un solo autor por proyecto, sin cofirmantes: acá están los proyectos donde figura <?= e($l['nombre']) ?> como autor/a, no todos los que firmó. "Sin resolución registrada" quiere decir que el proyecto no figura entre los que la Cámara resolvió en el archivo "Resultado de proyectos"; no significa que haya sido rechazado ni que siga vigente. El autor se identifica por nombre completo y se verifica que tuviera mandato en la fecha del proyecto. <a href="/metodologia">Cómo trabajamos</a>.</p>
<p class="fuente-pie">Fuente: datos.hcdn.gob.ar, conjuntos "Proyectos parlamentarios" y "Resultado de proyectos" (Cámara de Diputados de la Nación).</p>
<?php endif; ?>
