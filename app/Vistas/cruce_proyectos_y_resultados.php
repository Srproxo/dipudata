<?php
use App\Datos;
/** @var array $cr @var array $info */
$pr = $cr['proyectos_resultados'] ?? [];
$filas = []; $tot = ['presentados' => 0, 'con_dictamen' => 0, 'votados' => 0, 'aprobados' => 0, 'ley' => 0];
foreach (Datos::legisladores('Diputados') as $l) {
    $s = $pr[$l['slug']] ?? null;
    if (!$s || empty($s['presentados'])) { continue; }
    $s += ['ley' => 0, 'con_dictamen' => 0, 'votados' => 0, 'aprobados' => 0, 'resolucion' => 0, 'votados_lista' => []];
    $filas[] = ['l' => $l, 's' => $s];
    foreach ($tot as $k => $_) { $tot[$k] += (int)$s[$k]; }
}
[$orden, $dir] = orden_tabla(['nombre', 'presentados', 'dictamen', 'votados', 'aprobados'], 'presentados');
$filas = ordenar_filas($filas, static fn($f) => match ($orden) {
    'nombre' => Datos::normalizar(($f['l']['apellido'] ?? '') . ' ' . $f['l']['nombre']),
    'dictamen' => $f['s']['con_dictamen'], 'votados' => $f['s']['votados'], 'aprobados' => $f['s']['aprobados'],
    default => $f['s']['presentados'],
}, $dir);
$pm = Datos::proyectosMeta();
?>
<nav class="migas" aria-label="Ubicación"><a href="/cruces">Cruces de datos</a> / Qué pasó con sus proyectos</nav>
<p class="antetitulo">Cruce de datos · Diputados</p>
<h1 class="titulo">Qué pasó con sus proyectos.</h1>
<p class="bajada">Cuántos proyectos presentó cada diputado en ejercicio a lo largo de su carrera en la Cámara, cuántos tuvieron dictamen de comisión y cuántos formaron parte de algo que se votó en el recinto de Diputados.</p>

<dl class="ficha" style="max-width:36rem;margin-top:1.5rem">
  <div><dt>Proyectos de los diputados actuales</dt><dd><?= number_format($tot['presentados'], 0, ',', '.') ?></dd></div>
  <div><dt>Con dictamen de comisión</dt><dd><?= number_format($tot['con_dictamen'], 0, ',', '.') ?> (<?= e(pct_txt($tot['con_dictamen'], $tot['presentados'])) ?>)</dd></div>
  <div><dt>Llegaron a votarse en el recinto</dt><dd><?= number_format($tot['votados'], 0, ',', '.') ?> (<?= e(pct_txt($tot['votados'], $tot['presentados'])) ?>)</dd></div>
  <div><dt>Aprobados en general en esa votación</dt><dd><?= number_format($tot['aprobados'], 0, ',', '.') ?></dd></div>
</dl>

<?php [$filasPag, $pagina, $paginas] = paginar($filas); ?>
<div class="tabla-caja" style="margin-top:2rem"><table class="tabla tabla-cifras">
  <thead><tr>
    <?= th_orden('nombre', 'Diputado', $orden, $dir, false) ?>
    <?= th_orden('presentados', 'Presentados', $orden, $dir) ?>
    <?= th_orden('dictamen', 'Con dictamen', $orden, $dir) ?>
    <?= th_orden('votados', 'Votados en el recinto', $orden, $dir) ?>
    <?= th_orden('aprobados', 'Aprobados en general', $orden, $dir) ?>
  </tr></thead>
  <tbody>
  <?php foreach ($filasPag as $f): $lg = $f['l']; $s = $f['s']; ?>
    <tr>
      <td class="celda-nombre-td"><span class="celda-nombre"><?= avatar($lg, 32) ?><span><a href="<?= e(Datos::urlLegislador($lg)) ?>/proyectos"><?= e($lg['nombre']) ?></a><span class="tenue pequeno block"><?= e($lg['bloque']) ?> · <?= e($lg['provincia']) ?></span></span></span></td>
      <td class="der num" data-etiqueta="Presentados"><span class="celda-valor"><?= (int)$s['presentados'] ?><span class="tenue pequeno block"><?= (int)$s['ley'] ?> de ley</span></span></td>
      <td class="der num" data-etiqueta="Con dictamen"><span class="celda-valor"><?= (int)$s['con_dictamen'] ?><span class="tenue pequeno block"><?= e(pct_txt((int)$s['con_dictamen'], (int)$s['presentados'], 0)) ?></span></span></td>
      <td class="der td-bloque" data-etiqueta="Votados en el recinto"><span class="num"><?= (int)$s['votados'] ?></span>
        <?php if ($s['votados_lista']): ?><details class="cruce-detalle"><summary>Ver</summary><ul class="lista-simple pequeno">
          <?php foreach (array_reverse($s['votados_lista']) as [$exp, $tipo, $tit, $vid, $res]): ?>
            <li><?= e($exp) ?> (<?= e(mb_strtolower($tipo)) ?>): <?= e(mb_strtoupper(mb_substr($tit, 0, 1)) . mb_strtolower(mb_substr($tit, 1, 120))) ?><?= mb_strlen($tit) > 121 ? '…' : '' ?> <a href="/votaciones/<?= e($vid) ?>">votación</a> <span class="tenue">(<?= e(mb_strtolower($res === 'AFIRMATIVO' ? 'aprobado' : ($res === 'NEGATIVO' ? 'rechazado' : 'sin resultado publicado'))) ?>)</span></li>
          <?php endforeach; ?>
        </ul></details><?php endif; ?>
      </td>
      <td class="der num" data-etiqueta="Aprobados en general"><?= (int)$s['aprobados'] ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?= nav_paginas($pagina, $paginas) ?>

<div class="aviso">
  <p style="margin:0 0 .5rem"><strong>Cómo leer este cruce.</strong></p>
  <ul class="lista-simple">
    <li><strong>Presentados</strong>: los proyectos donde el diputado figura como autor en el archivo oficial de la Cámara (desde <?= e(fecha_es((string)($pm['desde'] ?? ''))) ?>), de cualquier tipo: ley, resolución o declaración. El archivo trae un solo autor por proyecto, sin cofirmantes.</li>
    <li><strong>Con dictamen</strong>: tiene al menos un dictamen de comisión en el archivo oficial de dictámenes. Sin dictamen no quiere decir rechazado.</li>
    <li><strong>Votados en el recinto</strong>: el proyecto integró un orden del día (según el archivo de dictámenes) o un expediente que figura en una votación nominal de Diputados. Un orden del día puede reunir varios proyectos sobre el mismo tema que la comisión «tuvo a la vista»: que un proyecto esté en él no quiere decir que se haya aprobado su texto. Muchos proyectos de resolución y de declaración se aprueban sin votación nominal y no aparecen acá.</li>
    <li><strong>Aprobados en general</strong>: alguna de esas votaciones en general tuvo resultado afirmativo. Para ser ley, un proyecto necesita además la aprobación del Senado: este cruce no dice cuántos llegaron a ser ley, porque el índice de leyes no trae el número de expediente de origen y la «resolución» del archivo de proyectos de la Cámara está incompleta (<?= (int)($pm['con_resultado'] ?? 0) ?> resultados para todos los proyectos de los diputados actuales).</li>
    <li>El Senado no publica dictámenes ni el detalle de autores en formato abierto: por eso este cruce es solo de Diputados. Más proyectos no quiere decir «trabaja más».</li>
  </ul>
</div>
<p class="fuente-pie">Fuentes: proyectos parlamentarios y dictámenes de la Cámara de Diputados (datos.hcdn.gob.ar); votaciones nominales de la Cámara de Diputados.</p>
